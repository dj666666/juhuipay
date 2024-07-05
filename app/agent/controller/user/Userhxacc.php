<?php

namespace app\agent\controller\user;

use app\admin\model\thirdacc\Hxacc;
use app\common\controller\AgentBackend;
use app\common\library\Utils;
use think\Exception;
use think\facade\Config;
use think\facade\Db;
use app\user\model\order\Order;

/**
 *
 *
 * @icon fa fa-circle-o
 */
class Userhxacc extends AgentBackend
{

    /**
     * Userrate模型对象
     * @var \app\admin\model\user\Userrate
     */
    protected $model = null;
    protected $acc_hx_code = null;
    protected $hx_type = null;

    public function _initialize() {
        parent::_initialize();

        $this->model   = new \app\admin\model\user\Userhxacc;
        $this->acc_hx_code = Config::get('mchconf.acc_hx_code');
        $this->hx_type     = Config::get('mchconf.hx_type');
        $this->view->assign("hxaccList", $this->getHxAccList());

    }

    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */


    /**
     * 查看
     */
    public function index() {

        //当前是否为关联查询
        $this->relationSearch = true;
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $total = $this->model
                ->withJoin(['user','hxacc'])
                ->where('userhxacc.agent_id', $this->auth->id)
                ->where($where)
                ->order($sort, $order)
                ->count();

            $list = $this->model
                ->withJoin(['user','hxacc'])
                ->where('userhxacc.agent_id', $this->auth->id)
                ->where($where)
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();

            foreach ($list as $row) {
                $row->visible(['id','name','user.username','hxacc.name','user.money','type','today_amount','all_amount','status','create_time']);

                $where = ['user_id' => $row['user_id'], 'status' => 1, 'hx_acc_id' => $row->id];
                //今日金额
                $today_amount = Order::where($where)->whereDay('createtime')->sum('amount');
                //总成功收款
                $all_suc_amount = Order::where($where)->sum('amount');

                $row['today_amount'] = $today_amount;
                $row['all_amount']   = $all_suc_amount;

            }
            $result = array("total" => $total, "rows" => $list);

            return json($result);
        }

        return $this->view->fetch();
    }


    /**
     * 添加
     */
    public function add() {

        if ($this->request->isPost()) {
            $params = $this->request->post('row/a');
            if ($params) {
                $params = $this->preExcludeFields($params);

                if ($this->dataLimit && $this->dataLimitFieldAutoFill) {
                    $params[$this->dataLimitField] = $this->auth->id;
                }

                $hx_types = $this->hx_type;

                $result = false;
                Db::startTrans();

                try {
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name     = str_replace('\\model\\', '\\validate\\', get_class($this->model));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.add' : $name) : $this->modelValidate;
                        validate($validate)->scene($this->modelSceneValidate ? 'edit' : $name)->check($params);
                    }

                    
                    //获取销卡平台的产品id
                    $hxAcc                = $this->getHxAccInfo($params['hx_acc_id']);
                    $params['name']       = $hxAcc['name'];
                    $params['product_id'] = $hxAcc['product_id'];
                    $params['hx_code']    = $hxAcc['hx_code'];
                    $params['pay_type']   = $hxAcc['pay_type'];
                    
                    $find = $this->model->where(['user_id' => $params['user_id'], 'product_id' => $params['product_id'], 'hx_code' => $params['hx_code']])->find();
                    if ($find) {
                        $this->error('该用户已经存在该平台类型，请核实');
                    }
                    
                    $params['agent_id'] = $this->auth->id;

                    $result = $this->model->save($params);
                    Db::commit();
                } catch (ValidateException $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                } catch (\PDOException $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if ($result !== false) {
                    $this->success();
                } else {
                    $this->error(__('No rows were inserted'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }

        return $this->view->fetch();
    }

    //切换启用状态
    public function change($ids = '') {
        if ($ids) {
            $row    = $this->model->get($ids);
            $status = $row['status'] == 1 ? 0 : 1;
            $re     = $this->model->where('id', $ids)->update(['status' => $status]);

            if ($re) {
                $this->success("切换成功");
            }

            $this->error("切换失败");
        }

        $this->error("参数缺少");
    }
    
    //组装下拉类型数据
    public function getHxAccList(){
        $hxAcc = Hxacc::where('status', 1)->select()->toArray();

        $list = [];
        foreach ($hxAcc as $k => $v){
            $list[$v['id']] = $v['name'];
        }

        return $list;
    }
    
    //获取系统核销通道详情
    public function getHxAccInfo($hx_acc_id){
        $hxAcc = Hxacc::where('id', $hx_acc_id)->find();
        return $hxAcc;
    }
}
