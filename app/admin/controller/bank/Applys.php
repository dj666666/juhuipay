<?php

namespace app\admin\controller\bank;

use app\common\controller\Backend;
use app\common\library\Utils;
use think\facade\Db;

/**
 * 提现记录
 *
 * @icon fa fa-circle-o
 */
class Applys extends Backend
{
    
    /**
     * Applys模型对象
     * @var \app\admin\model\bank\Applys
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\bank\Applys;
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
                    ->withJoin(['merchant'])
                    ->where($where)
                    ->order($sort, $order)
                    ->count();

            $list = $this->model
                    ->withJoin(['merchant'])
                    ->where($where)
                    ->order($sort, $order)
                    ->limit($offset, $limit)
                    ->select();

            foreach ($list as $row) {
                $row->visible(['id','out_trade_no','amount','status','createtime','updatetime','remark','deal_username','deal_ip_address','ip_address']);
                $row->getRelation('merchant')->visible(['username']);
            }

            $allmoney = $this->model->withJoin(['merchant'])->where(['applys.status'=>1])->where($where)->sum('amount');

            $result = array("total" => $total, "rows" => $list,'extend'=>[
                'allmoney' => $allmoney
            ]);

            return json($result);
        }
        return $this->view->fetch();
    }

    //申请同意
    public function agree($ids = null){
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }

        if($row['updatetime']) {
            $this->error('该订单已处理');
        }

        $updateData['updatetime'] = time();
        $updateData['status'] = 1;
        $updateData['deal_username'] = $this->auth->username;
        $updateData['deal_ip_address'] = request()->ip();

        //更新订单状态
        $result1 = $row->save($updateData);

        if($result1 == false){
            $this->error('操作失败');
        }else{
            $this->success('操作成功');
        }

    }

    //申请拒绝
    public function refuse($ids = null){
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }

        //未收到 如果没更新过该订单 就说明是第一次点
        if($row['updatetime']) {
            $this->error('该订单已处理');
        }

        // 启动事务
        Db::startTrans();
        try {

            //更新订单状态
            $updateData['fees'] = 0;
            $updateData['real_money'] = 0;
            $updateData['updatetime'] = time();
            $updateData['status'] = 2;
            $updateData['deal_username'] = $this->auth->username;
            $updateData['deal_ip_address'] = request()->ip();

            $result = $row->save($updateData);


            //找出商户
            $findmerchant = Db::name('merchant')->where('id',$row['mer_id'])->field('id,money,rate')->find();
            $new_money = bcadd($findmerchant['money'],$row['amount'],2);

            //写入余额记录
            Utils::merchantMoneyLog($row['mer_id'],0,$row['amount'],$new_money,$row['out_trade_no'],1,'提现拒绝',1);

            // 提交事务
            Db::commit();

        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
        }

        if($result){
            $this->success('操作成功');
        }else{
            $this->error('操作失败');
        }
    }
}
