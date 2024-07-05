<?php

namespace app\agent\controller\daifu;

use app\common\controller\AgentBackend;
use app\common\library\MoneyLog;
use app\common\library\Notify;
use app\common\library\Utils;
use think\facade\Config;
use think\facade\Db;

/**
 * 订单管理
 *
 * @icon fa fa-circle-o
 */
class Dforder extends AgentBackend
{

    /**
     * Order模型对象
     * @var \app\admin\model\daifu\Dforder
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\daifu\Dforder;
        $this->view->assign("statusList", $this->model->getStatusList());
        $this->view->assign("orderTypeList", $this->model->getOrderTypeList());
        $this->view->assign("isCallbackList", $this->model->getIsCallbackList());
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
                ->withJoin(['user','merchant'])
                ->where(['dforder.agent_id'=>$this->auth->id])
                ->where($where)
                ->order($sort, $order)
                ->count();

            $list = $this->model
                ->withJoin(['user'=>['nickname'], 'merchant'=>['nickname']],'left')
                ->where(['dforder.agent_id'=>$this->auth->id])
                ->where($where)
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();

            foreach ($list as $row) {
                if ($row['user']){
                    $row->getRelation('user')->visible(['nickname']);
                }
                if ($row['merchant']){
                    $row->getRelation('merchant')->visible(['nickname']);
                }
            }

            $countlist = $this->model->where(['dforder.agent_id'=>$this->auth->id])
                ->withJoin(['user'=>['nickname'], 'merchant'=>['nickname']],'left')
                ->where($where)
                ->field('sum(amount) as allmoney, sum(fees) as allfees, count(*) as allorder')
                ->select()->toArray();
            $countlist = $countlist[0];

            //今日订单数量
            $todayorder = $this->model->where(['agent_id'=>$this->auth->id])->whereDay('createtime')->count();
            $todayordermoney = $this->model->where(['agent_id'=>$this->auth->id])->whereDay('createtime')->sum('amount');

            $result = array("total" => $total, "rows" => $list, "extend" => [
                'allmoney'      => $countlist['allmoney'],
                'allorder'      => $countlist['allorder'],
                'allfees'       => $countlist['allfees'],
                'todayorder'    => $todayorder,
                'todayordermoney'    => $todayordermoney,
            ]);

            return json($result);
        }
        return $this->view->fetch();
    }


    /**
     * 冲正
     */
    public function reversal($ids = null){

        if(empty($ids)){
            $this->error(__('参数缺少'));
        }

        $order = $this->model->where(['id'=>$ids,'agent_id'=>$this->auth->id,'status'=>1])->find();

        if(!$order){
            $this->error('订单不存在');
        }
        
        $array  = [];
        $status = 4; //1成功 2处理中 3驳回 4冲正
        $time   = time();
        
        
        $result1 = $this->model->where('id',$ids)->update(['status'=>$status]);
        
        if(!$result1){
            $this->error('订单更新失败请重试');
        }
        
        
        //码商退余额
        MoneyLog::userMoneyChangeByDf($order['user_id'], $order['amount'], 0, $order['trade_no'], $order['out_trade_no'], '冲正退款', 0, 0);
        
        //商户加余额
        MoneyLog::merchantMoneyChangeByDf($order['mer_id'], $order['amount'], 0, $order['trade_no'], $order['out_trade_no'], '冲正退款', 1);
        
        
        //开始回调 0手动 1自动
        if($order['order_type'] == 0){
            $this->success('冲正成功');
        }
        
        //开始回调
        $notify = new Notify();
        $callbackre = $notify->sendDfCallBack($order['id'],$status,$time);

        if($callbackre['code'] != 1){
            $array = [
                'reversal_ip'       => request()->ip(),
                'reversal_callback' => 2,
                'is_callback'       => 2,
                'reversal_content'  => $callbackre['content'],
            ];
            
        }else{
            
            $array = [
                'reversal_time'     => $time,
                'reversal_ip'       => request()->ip(),
                'reversal_callback' => 1,
                'reversal_content'  => $callbackre['content'],
            ];
        }
        
        if ($callbackre['code'] != 1) {
            $this->error('冲正回调失败，未收到succee：'. $callbackre['content']);
        }
        
        $this->model->where('id',$ids)->update($updata);
        
        $this->success('冲正成功，回调成功');
    }


    /**
     * 详情
     */
    public function detail($ids = null){

        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $row = $row->toArray();

        $row['createtime']    = $row['createtime'] ? date('Y-m-d H:i:s', $row['createtime']) : '';
        $row['ordertime']     = $row['ordertime'] ? date('Y-m-d H:i:s', $row['ordertime']) : '';
        $row['expire_time']   = $row['expire_time'] ? date('Y-m-d H:i:s', $row['expire_time']) : '';
        $row['callback_time'] = $row['callback_time'] ? date('Y-m-d H:i:s', $row['callback_time']) : '';
        $this->view->assign("row", $row);
        return $this->view->fetch();

    }



    //驳回
    public function abnormal($ids = null){

        if(empty($ids) ){
            $this->error(__('参数缺少'));
        }

        $order = $this->model->where(['id'=>$ids,'agent_id'=>$this->auth->id,'status'=>2])->find();

        if(!$order){
            $this->error('订单不存在');
        }

        $pay_time = time();
        $status   = 3;
        
        $updata1 = [
            'status'          => $status,
            'ordertime'       => $pay_time,
            'deal_ip_address' => request()->ip(),
            'deal_user_id'    => $this->auth->id,
            'deal_username'   => $this->auth->username,
        ];
        
        $result1 = $this->model->where('id',$ids)->update($updata1);
        
        if (!$result1) {
            $this->error('订单更新失败请重试');
        }
        
        //开始回调 0手动 1自动
        if($order['order_type'] == 0){
            $this->success('驳回成功');
        }
        
        $notify = new Notify();
        $callbackre = $notify->sendDfCallBack($order['id'], $status, $pay_time);
        
        if($callbackre['code'] != 1){
            
            $updata = [
                'is_callback'      => 2,
                'callback_time'    => time(),
                'callback_content' => $callbackre['content'],
            ];
            
        }else{
            $updata = [
                'is_callback'      => 1,
                'callback_time'    => time(),
                'callback_content' => $callbackre['content'],
            ];
            
        }
        
        
        if ($callbackre['code'] != 1) {
            $this->error('驳回回调失败，未收到succee：'. $callbackre['content']);
        }
        
        $this->model->where('id',$ids)->update($updata);
        
        $this->success('驳回成功，回调成功');
        
    }
    
    
    //锁定
    public function lockorder($ids = null){
        
        if(empty($ids) ){
            $this->error(__('参数缺少'));
        }
        
        $updata = [
            'is_lock' => 1,
        ];
        
        $result1 = $this->model->where('id',$ids)->update($updata);
        if($result1 == false){
            $this->error('操作失败，请重试');
        }
        
        $this->success('锁定成功');

    }
    
    //解锁
    public function unlockorder($ids = null){
        
        if(empty($ids) ){
            $this->error(__('参数缺少'));
        }
        
        $updata = [
            'is_lock' => 0,
        ];
        
        $result1 = $this->model->where('id',$ids)->update($updata);
        if($result1 == false){
            $this->error('操作失败，请重试');
        }
        
        $this->success('解锁成功');

    }
    
}
