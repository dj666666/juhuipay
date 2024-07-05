<?php

namespace app\admin\controller\moneylog;

use app\common\controller\Backend;
use think\exception\ValidateException;
use think\facade\Db;

/**
 * 码商商户余额记录管理
 *
 * @icon fa fa-circle-o
 */
class Usermermoneylog extends Backend
{
    
    /**
     * Usermermoneylog模型对象
     * @var \app\admin\model\moneylog\Usermermoneylog
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\moneylog\Usermermoneylog;
        $this->view->assign("typeList", $this->model->getTypeList());
        $this->view->assign("isAutomaticList", $this->model->getIsAutomaticList());
    }
    
    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */
    

    /**
     * 查看
     */
    public function index()
    {
        //当前是否为关联查询
        $this->relationSearch = true;
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax())
        {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField'))
            {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $total = $this->model
                    ->withJoin(['user'])
                    ->where($where)
                    ->order($sort, $order)
                    ->count();

            $list = $this->model
                    ->withJoin(['user'])
                    ->where($where)
                    ->order($sort, $order)
                    ->limit($offset, $limit)
                    ->select();

            foreach ($list as $row) {
                $row->visible(['id','out_trade_no','type','amount','before_amount','after_amount','create_time','remark','ip_address','is_automatic']);

                if (!empty($row['user'])){
                    $row->getRelation('user')->visible(['username']);
                }

            }
            $result = array("total" => $total, "rows" => $list);

            return json($result);
        }
        return $this->view->fetch();
    }

    /**
     * 添加
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $params = $this->request->post('row/a');
            if ($params) {
                $params = $this->preExcludeFields($params);

                if ($this->dataLimit && $this->dataLimitFieldAutoFill) {
                    $params[$this->dataLimitField] = $this->auth->id;
                }
                $result = false;
                Db::startTrans();

                try {
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name     = str_replace('\\model\\', '\\validate\\', get_class($this->model));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name.'.add' : $name) : $this->modelValidate;
                        validate($validate)->scene($this->modelSceneValidate ? 'edit' : $name)->check($params);
                    }

                    //找出码商
                    $merchant = Db::name('user')->where('id',$params['user_id'])->find();

                    //0支出 1增加
                    switch ($params['type']) {
                        case 0:
                            if($merchant['mer_money'] < $params['amount']){
                                $this->error(__('余额不足'));
                            }

                            //减少码商对应商户余额
                            $new_money = bcsub($merchant['mer_money'],$params['amount'],2);
                            Db::name('user')->where(['id'=>$merchant['id'],'last_money_time'=>$merchant['last_money_time']])->update(['mer_money'=>$new_money,'last_money_time'=>time()]);

                            if(!$params['remark']){
                                $params['remark'] = '手动减少余额';
                            }
                            $params['before_amount'] = $merchant['mer_money'];
                            $params['after_amount'] = $new_money;
                            break;

                        case 1:
                            //增加码商对应商户余额
                            $new_money = bcadd($merchant['mer_money'],$params['amount'],2);
                            Db::name('user')->where(['id'=>$merchant['id'],'last_money_time'=>$merchant['last_money_time']])->update(['mer_money'=>$new_money,'last_money_time'=>time()]);

                            if(!$params['remark']){
                                $params['remark'] = '手动添加余额';
                            }

                            $params['before_amount'] = $merchant['mer_money'];
                            $params['after_amount'] = $new_money;
                            break;

                        default:
                            $this->error(__('类型错误'));
                            break;
                    }

                    $params['user_id']      = $merchant['id'];
                    $params['create_time']  = time();
                    $params['is_automatic'] = 1;
                    $params['out_trade_no'] = 11111111;
                    $params['ip_address']   = request()->ip();

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
}
