<?php

namespace app\merchant\controller\bank;

use app\common\controller\MerchantBackend;
use app\common\library\Utils;
use think\exception\ValidateException;
use think\facade\Db;

/**
 * 提现记录
 *
 * @icon fa fa-circle-o
 */
class Applys extends MerchantBackend
{
    
    /**
     * Applys模型对象
     * @var \app\merchant\model\bank\Applys
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\merchant\model\bank\Applys;
        $this->view->assign("statusList", $this->model->getStatusList());
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
                    ->where(['mer_id'=>$this->auth->id])
                    ->where($where)
                    ->order($sort, $order)
                    ->count();

            $list = $this->model
                    ->where(['mer_id'=>$this->auth->id])
                    ->where($where)
                    ->order($sort, $order)
                    ->limit($offset, $limit)
                    ->select();

            foreach ($list as $row) {
                $row->visible(['id','out_trade_no','amount','status','createtime','updatetime','remark','deal_username','deal_ip_address','ip_address']);
            }

            $allmoney = $this->model->where(['mer_id'=>$this->auth->id,'status'=>1])->where($where)->sum('amount');

            $result = array("total" => $total, "rows" => $list,'extend'=>[
                'allmoney' => $allmoney
            ]);

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

                //找出商户
                $findmerchant = Db::name('merchant')->where('id',$this->auth->id)->field('id,money,rate')->find();
                if( $params['amount'] > $findmerchant['money']){
                    $this->error('余额不足');
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

                    $params['user_id']      = 0;
                    $params['mer_id']       = $this->auth->id;
                    $params['agent_id']     = $this->auth->agent_id;
                    $params['out_trade_no'] = $this->buildnumber();
                    $params['ip_address']   = request()->ip();

                    $result = $this->model->save($params);

                    //写入余额记录
                    Utils::merchantMoneyLogV2($this->auth->id,$params['amount'],0,$params['out_trade_no'],0,'提现',1);

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

    //生成15位申请充值订单号
    public function buildnumber(){
        $number = date("Ymd") . mt_rand(1000000,9999999);
        $re = Db::name('applys')->where('out_trade_no',$number)->find();
        if($re){
            return $this->buildnumber();
        }
        return $number;
    }

}
