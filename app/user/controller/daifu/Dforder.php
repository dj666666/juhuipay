<?php

namespace app\user\controller\daifu;

use app\common\controller\Jobs;
use app\common\controller\UserBackend;
use app\common\library\MoneyLog;
use app\common\library\Notify;
use app\common\library\Utils;
use app\user\model\merchant\Merchant;
use app\user\model\user\User;
use think\facade\Config;
use think\facade\Db;
use think\facade\Log;
use think\facade\Queue;
use think\facade\Cache;

/**
 * 订单管理
 *
 * @icon fa fa-circle-o
 */
class Dforder extends UserBackend
{

    /**
     * Order模型对象
     * @var \app\admin\model\daifu\Dforder
     */
    protected $model = null;
    protected $redis = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\daifu\Dforder;
        $this->view->assign("statusList", $this->model->getStatusList());
        $this->view->assign("orderTypeList", $this->model->getOrderTypeList());
        $this->view->assign("isCallbackList", $this->model->getIsCallbackList());
        $this->view->assign("isResetorderList", $this->model->getIsResetorderList());
        $this->redis = Cache::store('redis');
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
        if ($this->request->isAjax()){

            /*$user_id = $this->auth->id;

            //接口限流
            $redis = Cache::store('redis');
            $key = 'xc:user:'.$user_id.':order_index';

            //限制次数为1
            $limit = 1;
            $check = $redis->exists($key);
            if($check){
                $redis->incr($key);//键值递增
                $count = $redis->get($key);
                if($count > $limit){
                    if($count > 100){
                        $redis->del($key);
                    }else{
                        $this->error('request error');
                    }

                }
            }else{
                $redis->incr($key);
                //设置过期时间
                $redis->expire($key,1);
            }*/


            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')){
                return $this->selectpage();
            }

            $filter = json_decode($this->request->get('filter'),true);

            list($where, $sort, $order, $offset, $limit) = $this->buildparams();

            //如果是选择了处理中，则限制只显示一条
            /*if(isset($filter['status']) && $filter['status'] == '2'){
                $offset= 0;
                $limit = 1;
                $order = 'ase';
            }*/
            
            $user_id = $this->auth->id;
            
            //找出下发分配的商户id
            $findsuer = Db::name('user')->where('id',$user_id)->field('merids')->find();
            
            $total = $this->model
                ->withJoin(['merchant'=>['username']],'left')
                ->where(['user_id'=>$this->auth->id,'dforder.is_third_df'=>'0'])
                //->where('mer_id','in',$findsuer['merids'])
                //->where('is_robbed',1)
                ->where($where)
                ->order($sort, $order)
                ->count();

            $list = $this->model
                ->withJoin(['merchant'=>['username']],'left')
                ->where(['user_id'=>$this->auth->id,'dforder.is_third_df'=>'0'])
                //->where('mer_id','in',$findsuer['merids'])
                //->where('is_robbed',1)
                ->where($where)
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();

            foreach ($list as $row) {
                $row->visible(['id','user_id','out_trade_no','trade_no','amount','bank_user','bank_type','bank_number','is_robbed','status','is_callback','createtime','ordertime','remark','outbank', 'pz_img']);
            }

            $success_where = ['user_id'=>$this->auth->id,'status'=>1];
            $allMoney = $this->model->where($success_where)->sum('amount');
            $allOrder = $this->model->where($success_where)->count();
            $todayMoney = $this->model->where($success_where)->whereDay('createtime')->sum('amount');
            $todayOrder = $this->model->where($success_where)->whereDay('createtime')->count();

            $result = array("total" => $total, "rows" => $list, 'extend'=>[
                'allmoney'   => $allMoney,
                'allorder'   => $allOrder,
                'todayMoney' => $todayMoney,
                'todayOrder' => $todayOrder,

            ]);

            return json($result);
        }

        $this->assignconfig('uid',$this->auth->id);
        $this->assignconfig('ordertime',86400);

        return $this->view->fetch();
    }




    //查询有无新订单
    public function getOrder(){

        $orderCount = $this->model
            ->where(['agent_id' => $this->auth->agent_id,'status' => 2, 'is_robbed' => 0])
            ->count();

        $result = ['applynum' => 0, 'ordernum' => $orderCount];

        return json($result);
    }
    
    //完成
    public function complete(){

        $ids     = $this->request->post('ids');
        $outbank = $this->request->post('outbank');
        $user_id = $this->auth->id;

        if(!$ids){
            $this->error(__('参数缺少'));
        }

        $order = $this->model->where(['id'=>$ids,'user_id'=>$this->auth->id,'status'=>2])->find();

        if(!$order){
            $this->error('订单不存在');
        }

        $pay_time = time();
        $status   = 1;

        $updata = [
            'status'          => $status,
            'ordertime'       => $pay_time,
            'deal_ip_address' => request()->ip(),
            'deal_user_id'    => $this->auth->id,
            'deal_username'   => $this->auth->username,
            'remark'          => $outbank,
        ];

        $result1 = $this->model->where('id',$ids)->update($updata);
        if(!$result1) {
            $this->error('订单处理失败');
        }
        
        //开始回调 1自动 0手动
        if($order['order_type'] == 1){

            $notify = new Notify();
            $callbackre = $notify->sendDfCallBack($order['id'], $status, $pay_time);

            if($callbackre['code'] != 1){

                $msg = '，回调失败，未收到success：'.$callbackre['content'];

            }else{

                $updata = [
                    'is_callback'      => 1,
                    'callback_time'    => time(),
                    'callback_content' => $callbackre['content'],
                ];
                
                $this->model->where('id',$ids)->update($updata);
                
                $msg = '，回调成功';
            }
            
        }else{
            $msg = '，无需回调';
        }
        
        //码商余额记录 码商加余额
        $user_df_rate = Config::get('site.user_df_rate');
        if ($user_df_rate != 0) {
            $rateType = $user_df_rate == 1 ? 1 : 0;
            $remark   = $rateType == 1 ? '代付增加' : '代付扣除';
            MoneyLog::userMoneyChangeByDf($user_id, $order['amount'], 0, $order['trade_no'], $order['out_trade_no'], $remark, $rateType, 0);
        }
        
        //码商余额记录 商户扣余额
        $merchant_df_rate = Config::get('site.merchant_df_rate');
        if ($merchant_df_rate != 0) {
            $rateType = $merchant_df_rate == 1 ? 1 : 0;
            $remark   = $rateType == 1 ? '代付增加' : '代付扣除';
            MoneyLog::merchantMoneyChangeByDf($order['mer_id'], $order['amount'], 0, $order['trade_no'], $order['out_trade_no'], $remark, $rateType);
        }
        
        $this->success('处理成功'. $msg);
        
    }

    //驳回
    public function abnormal(){

        $ids     = $this->request->post('id');
        $remark  = $this->request->post('remark','');
        $user_id = $this->auth->id;

        if(empty($ids) || empty($remark)){
            $this->error(__('参数缺少'));
        }

        $order = $this->model->where(['id'=>$ids,'user_id'=>$this->auth->id,'status'=>2])->find();

        if(!$order){
            $this->error('订单不存在');
        }

        $pay_time = time();
        $status   = 3;

        $updata = [
            'status'          => $status,
            'ordertime'       => $pay_time,
            'deal_ip_address' => request()->ip(),
            'deal_user_id'    => $this->auth->id,
            'deal_username'   => $this->auth->username,
            'remark'          => $remark,
        ];

        $result1 = $this->model->where('id',$ids)->update($updata);

        if(!$result1) {
            $this->error('订单处理失败');
        }

        $msg = '';

        //开始回调 0手动 1自动
        if($order['order_type'] == 1 ){

            $notify = new Notify();
            $callbackre = $notify->sendDfCallBack($order['id'], $status, $pay_time);

            if($callbackre['code'] != 1){

                $msg = '回调失败，未收到success：'.$callbackre['content'];

            }else{

                $updata = [
                    'is_callback'      => 1,
                    'callback_time'    => time(),
                    'callback_content' => $callbackre['content'],
                ];

                $this->model->where('id',$ids)->update($updata);
                
                $msg = '回调成功';
            }

        }

        //商户余额退回
        //$trade_no = empty($order['trade_no']) ? $order['out_trade_no'] : $order['trade_no'];
        //MoneyLog::merchantMoneyChangeByDf($order['mer_id'], $order['amount'], $order['fees'], $trade_no, $order['out_trade_no'], '驳回退款',1);
        
        if (!empty($msg)) {
            $this->success('驳回成功'. $msg);
        }

        $this->success('驳回成功');

    }

    //冲正
    public function reversalOrder($ids = null){
        $this->error('系统错误');
        if(empty($ids)){
            $this->error('参数缺少');
        }

        $order = $this->model->where(['id'=>$ids,'user_id'=>$this->auth->id,'status'=>1])->find();

        if(!$order){
            $this->error('订单不存在');
        }

        $status = 4;
        $reversal_time = time();

        $updata = [
            'status'        => $status,
            'reversal_time' => $reversal_time,
            'reversal_ip'   => request()->ip(),
        ];

        $result1 = $this->model->where('id',$ids)->update($updata);

        $msg = '';

        //开始回调 0手动 1自动
        if($order['order_type'] == 1 ){

            $notify = new Notify();
            $callbackre = $notify->sendDfCallBack($order['id'], $status, $reversal_time);

            if($callbackre['code'] != 1){

                $msg = '回调失败，未收到success：'.$callbackre['content'];

            }else{

                $updata = [
                    'reversal_callback' => 1,
                    'reversal_content'  => $callbackre['content'],
                ];

                $this->model->where('id',$ids)->update($updata);
            }

        }


        if (!empty($msg)) {
            $this->error('冲正成功，'. $msg);
        }

        $this->success('冲正成功，回调成功');
    }

    //发送回调
    public function sendNotify(){

        $ids = $this->request->post('ids');

        if(empty($ids)){
            $this->error('参数缺少');
        }

        $order = $this->model->where(['id'=>$ids,'user_id'=>$this->auth->id,'is_callback'=>0])->find();

        if(!$order){
            $this->error('订单不存在');
        }

        $status = $order['status'];

        //开始回调
        $notify = new Notify();
        $callbackre = $notify->sendDfCallBack($order['id'], $status, $order['ordertime']);

        if($callbackre['code'] != 1){
            $msg = '回调失败，未收到success：'.$callbackre['content'];
            $this->error($msg);
        }

        $updata = [
            'is_callback'      => 1,
            'callback_time'    => time(),
            'callback_content' => $callbackre['content'],
        ];

        $result = $this->model->where('id',$ids)->update($updata);

        if(!$result){
            $this->error('回调成功，处理失败');
        }

        $this->success('回调成功');

    }

    //弃单
    public function releaseOrder($ids = null){

        if(empty($ids)){
            $this->error('参数缺少');
        }

        $username = $this->auth->username;
        $user_id = $this->auth->id;


        $order = $this->model->where(['id'=>$ids,'user_id'=>$this->auth->id,'status'=>2,'is_robbed'=>1])->find();

        Log::write('releaseOrder----'.request()->ip().'----'.$ids. '----'.$username.'----'.$order['out_trade_no'].'----弃单','dfOrder');

        if(!$order){
            $this->error('订单不存在');
        }


        $re = $this->model->where('id',$ids)->update([
            'user_id'     => '',
            'is_robbed'   => 0,
            'robbed_time' => time(),
        ]);

        if($re){
            $this->success('释放成功');
        }

        $this->error('释放失败');



    }

    //删除码商对应订单量
    public function delMyOrderNumByRedis($user_id){
        $my_order_num_key = 'xc:user:'.$user_id.':my_order_num';
        $this->redis->del($my_order_num_key);
    }
    
    //上传凭证
    public function uploadimg($ids = null){
        $row = $this->model->get($ids);
        if(!empty($row['pz_img'])){
            $this->error("凭证已上传");
        }
        
        if ($this->request->isPost()) {
            
            $params = $this->request->post('row/a');
            if ($params) {
                $params = $this->preExcludeFields($params);
                
                
                if(empty($params)){
                    $this->error('无操作');
                }
                
                $updata['pz_img'] = $params['image'];
                $result = $this->model->where('id',$ids)->update($updata);
                
                if ($result !== false) {
                    $this->success();
                } else {
                    $this->error('更新失败，请重试');
                }
            }
            
            
        }
        
        return $this->view->fetch();
        
    }
    
    
    //点开详情 处理单子
    public function detail($ids = null){
        
        $row = $this->model->get($ids);
        
        if ($this->request->isPost()) {
            
            $params = $this->request->post('row/a');
            if ($params) {
                $params = $this->preExcludeFields($params);
                
                $order = Db::name('df_order')->where(['id'=>$ids,'user_id'=>$this->auth->id,'status'=>2])->find();
                
                if(!$order){
                    $this->error('订单不存在');
                }
                $findMer  = Db::name('merchant')->where('id', $order['mer_id'])->find();
                $pay_time = time();
                $status   = 1;
                
                $updata = [
                    'status'          => $status,
                    'ordertime'       => $pay_time,
                    'deal_ip_address' => request()->ip(),
                    'deal_user_id'    => $this->auth->id,
                    'deal_username'   => $this->auth->username,
                    'outbank'         => $params['outbank'],
                ];
                
                $result1 = Db::name('df_order')->where('id',$ids)->update($updata);
                if(!$result1) {
                    $this->error('订单处理失败');
                }
                
                
                $msg = '';
                
                //开始回调 1自动 0手动
                if($order['order_type'] == '1' && $findMer['is_callback'] == '1'){
        
                    $notify = new Notify();
                    $callbackre = $notify->sendDfCallBack($order['id'], $status, $pay_time);
        
                    if($callbackre['code'] != 1){
        
                        $msg = '回调失败，未收到success：'.$callbackre['content'];
                        
                    }else{
                        $msg = '，回调成功';
                        $updata = [
                            'is_callback'      => 1,
                            'callback_time'    => time(),
                            'callback_content' => $callbackre['content'],
                        ];
                        
                        Db::name('df_order')->where('id',$ids)->update($updata);
                    }
                    
                }
                
                //码商余额记录 码商加余额
                $user_df_rate = Config::get('site.user_df_rate');
                if ($user_df_rate != 0) {
                    $rateType = $user_df_rate == 1 ? 1 : 0;
                    $remark   = $rateType == 1 ? '代付完成' : '代付扣除';
                    MoneyLog::userMoneyChangeByDf($this->auth->id, $order['amount'], 0, $order['trade_no'], $order['out_trade_no'], $remark, $rateType, 0);
                }
                
                //码商余额记录 商户扣余额
                $merchant_df_rate = Config::get('site.merchant_df_rate');
                if ($merchant_df_rate != 0) {
                    $rateType = $merchant_df_rate == 1 ? 1 : 0;
                    $remark   = $rateType == 1 ? '代付完成' : '代付扣除';
                    MoneyLog::merchantMoneyChangeByDf($order['mer_id'], $order['amount'], 0, $order['trade_no'], $order['out_trade_no'], $remark, $rateType);
                }
                
                if ($callbackre['code'] != 1) {
                    $this->error('处理成功，但'. $msg);
                }
                
                $this->success('处理成功' . $msg);
            
            }
            
            $this->error('无修改');
        }    
        
        $this->view->assign('row', $row);

        //$url = Utils::imagePath('/api/index/order/'.$row['out_trade_no'], true);
        //$this->assignconfig('orderurl',$url);
        
        
        //支付宝直接跳转卡
        
        $zfburl = 'https://ds.alipay.com/?scheme='.urlencode('alipays://platformapi/startapp?appId=09999988&actionType=toCard&sourceId=bill&bankAccount='.$row['bank_user'].'&cardNo='.$row['bank_number'].'&money='.$row['amount'].'&amount='.$row['amount'].'&bankMark=&cardIndex=&cardNoHidden=true&cardChannel=HISTORY_CARD&orderSource=from&buyId=auto');
        $this->assignconfig('zfburl',$zfburl);
        
        return $this->view->fetch();
    }
    
}
