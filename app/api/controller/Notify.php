<?php

namespace app\api\controller;

use app\common\controller\Api;
use app\user\model\order\Order;
use think\facade\Db;
use think\facade\Config;
use think\cache\driver\Redis;
use fast\Http;
use think\facade\Log;
use think\Request;
use think\facade\Queue;
use app\common\controller\Jobs;
use app\common\library\MoneyLog;
use app\common\library\HandpaySignUtil;
use app\common\library\Accutils;
use app\common\library\Aes;
use app\common\library\Utils;
use app\common\library\Notify as CallbackNotify;
use app\common\library\Rsa;

/**
 * 示例接口
 */
class Notify extends Api
{

    //如果$noNeedLogin为空表示所有接口都需要登录才能请求
    //如果$noNeedRight为空表示所有接口都需要验证权限才能请求
    //如果接口已经设置无需登录,那也就无需鉴权了
    //
    // 无需登录的接口,*表示全部
    //protected $noNeedLogin = ['subAlipayRed','alipay','appHeart','appPush','hcNotify'];
    protected $noNeedLogin = ['*'];
    // 无需鉴权的接口,*表示全部
    protected $noNeedRight = ['*'];

    protected $redis;

    /*public function __construct(Request $request = null)
    {
        parent::__construct($request);
        //$this->redis = new Redis();
    }*/


    //支付宝口令红包 上报拆包结果
    public function subAlipayRed(){

        //1. 拆包成功
        //2. 口令错误 还有剩余次数
        //3. 口令错误 超过10次数*小时后再试
        //4. 红包被领完
        //5. 重复领取

        $out_trade_no = $this->request->post('out_trade_no');//订单号
        $status       = $this->request->post('status');
        $amount       = $this->request->post('amount');//实际金额
        $sign         = $this->request->post('sign');//签名
        $postdata     = $this->request->post();

        Log::write('subAlipayRed----'.date('Y-m-d H:i:s').'----'.request()->ip().'----'.json_encode($_POST,JSON_UNESCAPED_UNICODE),'info');

        if($status == 1){
            if(empty($out_trade_no) || empty($status) || empty($amount) || empty($sign)){
                $this->error('参数缺少');
            }
        }else{
            if(empty($out_trade_no) || empty($status) || empty($sign)){
                $this->error('参数缺少');
            }
        }


        $order = Db::name('order')->where(['out_trade_no'=>$out_trade_no,'status'=>2])->find();

        if(empty($order)){
            $this->error('订单不存在');
        }

        //写入日志
        $callbacklog=[
            'order_id'      => $order['id'],
            'out_trade_no'  => $order['out_trade_no'],
            'data'          => json_encode($postdata),
            'create_time'   => date('Y-m-d H:i:s',time()),
            'createtime'    => time(),
        ];
        Db::name('callback_log')->insert($callbacklog);

        $user = Db::name('user')->where(['id'=>$order['user_id']])->find();

        $mysign = md5('user_number='.$user['number'].'&amount='.$amount.'&out_trade_no='.$out_trade_no.'&status='.$status);
        if($mysign != $sign){
            $this->error('签名错误');
        }

        if($status == 1){

            //判断金额
            if($amount != $order['amount']){
                $this->error('实际金额错误');
            }

        }



        //处理错误类型
        if($status != 1){

            $updateData = [
                'status'            => 3,
                'remark'            => '口令错误',
                'deal_username'     => '自动',
                'ordertime'        => time(),
                'deal_ip_address'   =>  request()->ip(),
            ];

            //如果超过10次 把码下架
            if($status == 3){
                $updateData['remark'] = '口令错误超过十次';

                Db::name('group_qrcode')->where(['id'=>$order['qrcode_id'],'user_id'=>$order['user_id']])->update([
                    'status'=>'0',
                    'remark'=>'口令错误超过十次'
                ]);
            }

            if($status == 4){
                $updateData['remark'] = '红包已被领取完';
            }

            if($status == 5){
                $updateData['remark'] = '红包重复领取';
            }

            //修改订单
            Db::name('order')->where(['out_trade_no'=>$out_trade_no,'user_id'=>$order['user_id']])->update($updateData);

            $this->success('处理成功');

        }

        $notify = new \app\common\library\Notify();
        $notify->dealOrderNotify($order , 1 ,'自动' );

    }

    //个码hook监控上报
    public function alipay(){

        $trade_no = $this->request->post('trade_no');//支付宝单号
        $amount   = $this->request->post('amount');//实际金额
        $remark   = $this->request->post('remark');//收款理由 商户单号
        $key      = $this->request->post('key');//通道设备值
        $sign     = $this->request->post('sign');//签名
        $postdata = $this->request->post();

        Log::write('alipay----'.date('Y-m-d H:i:s').'----'.request()->ip().'----'.json_encode($_POST,JSON_UNESCAPED_UNICODE),'notify');

        if(empty($remark) || empty($amount) || empty($key)){
            $this->error('参数缺少');
        }
        

        $order = Db::name('order')
            ->where(['out_trade_no'=>$remark,'pay_amount'=>$amount,'status'=>2,'is_callback'=>0])
            ->where('expire_time','>',time())
            ->find();

        if(empty($order)){
            $this->error('订单不存在');
        }
        
        $user = Db::name('user')->where(['id'=>$order['user_id']])->find();

        //修改订单 开始回调
        $pay_time = time();

        $findmerchant = Db::name('merchant')->where('id',$order['mer_id'])->field('money,is_callback,rate')->find();
        $finduser = Db::name('user')->where('id',$order['user_id'])->field('rate')->find();

        //先修改订单状态 再发送回调
        $updata = [
            'status'            =>  1,
            'ordertime'         =>  $pay_time,
            'deal_ip_address'   =>  request()->ip(),
            'deal_username'     =>  '自动',
        ];

        $updateOrderRe = Db::name('order')->where('id',$order['id'])->update($updata);
        if(!$updateOrderRe){
            $this->error('操作失败请重试');
        }

        $result1 = false;
        $result2 = false;
        $result3 = false;
        $msg = '';

        // 启动事务
        Db::startTrans();
        try{

            //1.扣除商户余额
            $mer_fees = bcmul($order['amount'],$findmerchant['rate'],2);
            $result1 = Utils::merchantMoneyLogV2($order['mer_id'], $order['amount'], $mer_fees, $order['out_trade_no'], 0, '订单完成');

            //3.发送回调
            $callback = new CallbackNotify();

            $callbackre = $callback->sendCallBack($order['id'],1,$pay_time);

            if($callbackre['code'] != 1){

                $callbackarray = [
                    'is_callback'       => 2,
                    'callback_count'    => $order['callback_count']+1,
                    'callback_content'  => $callbackre['content'],
                ];


                //回调失败 加入队列
                if (Config::get('site.is_queue_notify')){

                    // 当前任务所需的业务数据，不能为 resource 类型，其他类型最终将转化为json形式的字符串
                    $queueData = [
                        'request_type'  => 3,
                        'order_id'      => $order['id'],
                        'out_trade_no'  => $order['out_trade_no'],
                    ];

                    //当前任务归属的队列名称，如果为新队列，会自动创建
                    $queueName = 'checkorder';
                    $delay = 5;
                    // 将该任务推送到消息队列，等待对应的消费者去执行
                    //$isPushed = Queue::push(Jobs::class, $data, $queueName);//立即执行
                    $isPushed = Queue::later($delay,Jobs::class, $queueData, $queueName);//延迟$delay秒后执行

                }

            }else{

                //回调成功
                $callbackarray = [
                    'is_callback'       => 1,
                    'callback_time'     => time(),
                    'callback_count'    => $order['callback_count']+1,
                    'callback_content'  => $callbackre['content'],
                ];

            }
            
            $result2 = Db::name('order')->where('id',$order['id'])->update($callbackarray);

            // 提交事务
            Db::commit();

        } catch (\Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }


        if($result1 == false || $result2 == false || $result3 == false){
            $this->error('处理失败');
        }
        
        $this->success('处理成功');

    }
    
   
    //个码状态栏监控心跳
    public function appHeart(){
        Log::write('appHeart----'.date('Y-m-d H:i:s').'----'.request()->ip().'----'.json_encode($_GET,JSON_UNESCAPED_UNICODE),'heart');

        $t          = $this->request->get('t');//时间
        $key        = $this->request->get('key');//设备值
        $sign       = $this->request->get('sign');//设备值
        $app_secret = Config::get('site.app_secret');
        
        $my_sign = md5($t.$app_secret.$key);

        if ($my_sign != $sign){
            return '校验失败';
        }

        $re = Db::name("group_qrcode")->where("android_key",$key)->update(['android_heart'=>time()]);
        if(!$re){
            return '心跳失败';
        }
        
        //$this->success('ok');
        return 'ok';

    }
    
    
    //个码状态栏监控回调
    public function appPush(){

        Log::write('appPush----'.date('Y-m-d H:i:s').'----'.request()->ip().'----'.json_encode($_GET,JSON_UNESCAPED_UNICODE),'notify');

        $t          = $this->request->get('t');//时间
        $type       = $this->request->get('type');//1微信 2支付宝
        $price      = $this->request->get('price');//金额
        $key        = $this->request->get('key');//设备值
        $sign       = $this->request->get('sign');//设备值
        $app_secret = Config::get('site.app_secret');

        $my_sign = md5($type.$price.$t.$app_secret.$key);

        if ($my_sign != $sign){
            $this->error('签名校验不通过');
        }

        $findqrcode = Db::name("group_qrcode")->where("android_key",$key)->find();
        if(empty($findqrcode)){
            $this->error('设备错误');
        }
        
        $now_time = time();
        
        $order = Db::name("order")
            ->where(['status'=>2,'pay_amount'=>$price,'qrcode_id'=>$findqrcode['id'],'is_callback'=>0])
            ->where("expire_time",'>',$now_time)
            ->find();
		
        if (empty($order)){
            $this->error('订单不存在');
        }


        $notify = new \app\common\library\Notify();
        $notify->dealOrderNotify($order , 1 ,'自动' );

    }


    //汇潮异步通知
    public function hcNotify(){
        
        $postData     = $this->request->post();
        $merNo        = $this->request->post('MerNo');
        $billNo       = $this->request->post('BillNo');//我传过去的系统订单号
        $orderNo      = $this->request->post('OrderNo');
        $payChannelNo = $this->request->post('payChannelNo');
        $amount       = $this->request->post('Amount');
        $succeed      = $this->request->post('Succeed');
        $result       = $this->request->post('Result');
        $signInfo     = $this->request->post('SignInfo');
        $fundChannel  = $this->request->post('FundChannel');
        $buyUserAccount  = $this->request->post('buyUserAccount');
        
        
        Log::write('hcNotify----'.request()->ip().'----'.json_encode($postData,JSON_UNESCAPED_UNICODE),'notify');
       
        //写入日志
        Utils::notifyLog($orderNo, $billNo,'收到回调'.json_encode($postData,JSON_UNESCAPED_UNICODE));
        try {
        
            $data = 'MerNo='.$merNo.'&BillNo='.$billNo.'&OrderNo='.$orderNo.'&Amount='.$amount.'&Succeed='.$succeed;
            
            //公钥
            $public_key  = Config::get('mchconf.hc_yimadai_public_key');
            
            $rsa = new Rsa($public_key,'');
            
            //验签
            $verify = $rsa->public_verifyV2($data, $signInfo);
            if(!$verify){
                Utils::notifyLog($orderNo, $billNo,'验签失败');
                return 'fail';
            }
        
            if($succeed != '88' && $result != 'SUCCESS'){
                Utils::notifyLog($orderNo, $billNo,'参数判断失败');
                return 'fail';
            }
            
            $now_time = time();
            
            $order = Db::name("order")
                ->where(['out_trade_no' => $billNo, 'status' => 2, 'amount' => $amount,'is_callback'=>0])
                ->where("expire_time",'>',$now_time)
                ->find();
    		
            if (empty($order)){
                Utils::notifyLog($orderNo, $billNo,'订单不存在');
                return '订单不存在';
            }
            
            Db::name("order")->where(['id' => $order['id'], 'status' => 2, 'amount' => $amount])->update(['xl_user_id' => $buyUserAccount]);

            $notify = new \app\common\library\Notify();
            $re     = $notify->dealOrderNotify($order , 1 ,'自动' );
            
            return $re == true ? 'ok' : 'exception';
            
        } catch (\Exception $e) {
            // 这是进行异常捕获
            Utils::notifyLog(11111, 11111,$e->getLine() .'-'.$e->getMessage());
            return 'system exception';
        }
    }
    
    //短信监控心跳
    public function smsAppHeart(){
        Log::write('smsAppHeart----'.date('Y-m-d H:i:s').'----'.request()->ip().'----'.json_encode($_GET,JSON_UNESCAPED_UNICODE),'heart');

        $key = $this->request->post('key');//设备值
        
        $app_secret = Config::get('site.app_secret');

        $re = Db::name("group_qrcode")->where("android_key",$key)->update(['android_heart'=>time()]);
        if(!$re){
            $this->error('心跳失败');
        }
        
        $this->success('ok');

    }
    
    
    //短信监控通知
    public function smsNotify(){
        $body  = $this->request->post('body');
        $phone = $this->request->post('phone');
        $time  = $this->request->post('time');
        $key   = $this->request->post('key');//设备值
        $postData = $this->request->post();
        
        Log::write('smsNotify----'.request()->ip().'----'.json_encode($postData,JSON_UNESCAPED_UNICODE),'notify');
        
        
        if(empty($body) || empty($phone) || empty($time) || empty($key)){
            $this->error('参数错误');
        }
        
        Utils::notifyLog('222222','222222',json_encode($postData, JSON_UNESCAPED_UNICODE));
        
        $body = preg_replace('# #', '', $body);
		$preg = "/\d+\\.\d+元/";
		preg_match_all($preg,$body,$matchArr);
		
		if(count($matchArr[0]) != 1){
		    $this->error('金额错误');
		}
		
		$money = $matchArr[0][0];//正则取出金额
		

		$findqrcode = Db::name("group_qrcode")->where("android_key",$key)->find();
        if(empty($findqrcode)){
            $this->error('设备错误');
        }
        
        $smsTime   = strtotime($time);
        $now_time  = time();
        $ordertime = time() - 299;//5分钟内的订单
        
        $order = Db::name("order")
            ->where(['user_id'=>$findqrcode['user_id'],'status'=>2,'pay_amount'=>$money,'qrcode_id'=>$findqrcode['id'],'is_callback'=>0])
            ->where('createtime','between',[$ordertime, $smsTime])
            ->where('expire_time','>',$now_time)
            ->find();
		
        if (empty($order)){
            $this->error('订单不存在');
        }

        $notify = new \app\common\library\Notify();
        $re     = $notify->dealOrderNotify($order , 1 ,'自动' );

        if($re){
            $this->success('处理成功');
        }
        
        $this->error('上报处理失败');
    }
    
    //瀚银异步通知
    public function handNotify(){
        
        $postData     = $this->request->post();
        $app_id       = $this->request->post('app_id');
        $created_time = $this->request->post('created_time');
        $hand_data    = $this->request->post('data');
        $id           = $this->request->post('id');
        $mode         = $this->request->post('mode');
        $signature    = $this->request->post('signature');
        $type         = $this->request->post('type');
        $timestamp    = $this->request->post('timestamp');
        
        Log::write('handNotify----'.request()->ip().'----'.json_encode($postData,JSON_UNESCAPED_UNICODE),'notify');

        if(empty($app_id) || empty($hand_data) || empty($id) || empty($signature) || empty($type)){
            return 'param error';
        }
        if($type != 'payment.succ'){
            return 'type error';
        }
        
        try {
            
            if (!is_array($hand_data)){
                return 'data error';
            }
            
            //写入日志
            Utils::notifyLog($hand_data['id'], $hand_data['order_no'],'瀚银收到回调'.json_encode($postData,JSON_UNESCAPED_UNICODE));
            
            $app_id      = Config::get('mchconf.hand_app_id');
            $notify_key  = Config::get('mchconf.hand_notify_key');
            
            $content = $app_id. '|'. $timestamp . '|'. $hand_data['id'];
            
            $handpaySignUtil = new HandpaySignUtil();
            $handpaySignUtil->PublicKey  = $notify_key;
            $verify_res = $handpaySignUtil->verifySign($signature, $content);
            
            //验签
            if(!$verify_res){
                Utils::notifyLog($hand_data['id'], $hand_data['order_no'],'验签失败');
                return 'sign fail';
            }
        
            $now_time = time();
            $amount   = $hand_data['pay_amt'] / 100;
            
            $order = Db::name("order")
                ->where(['out_trade_no' => $hand_data['order_no'], 'status' =>2, 'amount' => $amount,'is_callback'=>0])
                ->where("expire_time",'>',$now_time)
                ->find();
    		
            if (empty($order)){
                Utils::notifyLog($hand_data['id'], $hand_data['order_no'],'系统订单不存在');
                return '订单不存在';
            }

            $notify = new \app\common\library\Notify();
            $re     = $notify->dealOrderNotify($order , 1 ,'自动' );


            return $re == true ? 'success' : 'exception';
            
        } catch (\Exception $e) {
            // 这是进行异常捕获
            Utils::notifyLog('瀚银回调异常', '瀚银回调异常', $e->getLine() .'-'.$e->getMessage());
            return 'system exception';
        }
    }
    
    //新生异步通知
    public function hnaNotify(){
        
        $postData       = $this->request->post();
        $version        = $this->request->post('version');
        $tranCode       = $this->request->post('tranCode');
        $merOrderId     = $this->request->post('merOrderId');//我传过去的系统订单号
        $merId          = $this->request->post('merId');
        $tranAmt        = $this->request->post('tranAmt');
        $merAttach      = $this->request->post('merAttach');
        $charset        = $this->request->post('charset');
        $signType       = $this->request->post('signType');
        $resultCode     = $this->request->post('resultCode');//成功：0000 失败：4444
        $hnapayOrderId  = $this->request->post('hnapayOrderId');//新生支付平台唯一订单号
        $bizProtocolNo  = $this->request->post('bizProtocolNo');
        $payProtocolNo  = $this->request->post('payProtocolNo');
        $checkDate      = $this->request->post('checkDate');
        $bankCode       = $this->request->post('bankCode');
        $cardType       = $this->request->post('cardType');
        $shortCardNo    = $this->request->post('shortCardNo');
        $errorCode      = $this->request->post('errorCode');
        $errorMsg       = $this->request->post('errorMsg');
        $tranFinishTime = $this->request->post('tranFinishTime');
        $merTxnTm       = $this->request->post('merTxnTm');
        $signValue      = $this->request->post('signValue');
        $userId         = $this->request->post('userId');
        $buyerLogonId   = $this->request->post('buyerLogonId');
        
        Log::write('新生----'.request()->ip().'----'.json_encode($postData,JSON_UNESCAPED_UNICODE),'notify');
        Utils::notifyLog($merOrderId, $merOrderId,'新生收到回调'.json_encode($postData,JSON_UNESCAPED_UNICODE));

        if($resultCode == 4444){
            return 'not pay';
        }
        
        if (!is_array($postData)){
            return 'data error';
        }
        
        try {
            
            $wait_sign_str = [
                'version'       => '2.0',
                'tranCode'      => 'MUP11',
                'merOrderId'    => $merOrderId,
                'merId'         => $merId,
                'charset'       => '1',
                'signType'      => '1',
                'resultCode'    => $resultCode,
                'hnapayOrderId' => $hnapayOrderId,
            ];
            
            $wait_sign_str = Utils::signV3($wait_sign_str);
            
            $public_key  = Config::get('mchconf.hna_public_key');

            $rsa = new Rsa($public_key, '');
            //公钥解密
            $verify = $rsa->public_verifyV2($wait_sign_str, $signValue);
            
            if(!$verify){
                Utils::notifyLog($merOrderId, $merOrderId,'验签失败');
                return 'sign fail';
            }
            
            $now_time = time();
            
            $order = Db::name("order")
                ->where(['out_trade_no' => $merOrderId, 'status' =>2, 'pay_amount' => $tranAmt,'is_callback'=>0])
                ->where("expire_time",'>',$now_time)
                ->find();
    		
            if (empty($order)){
                Utils::notifyLog($merOrderId, $merOrderId,'系统订单不存在');
                return '订单不存在';
            }
            
            //单号更新到订单
            Db::name("order")->where(['id' => $order['id'], 'status' =>2, 'pay_amount' => $tranAmt])->update(['xl_order_id'=>$hnapayOrderId]);

            $notify = new \app\common\library\Notify();
            $re     = $notify->dealOrderNotify($order , 1 ,'自动' );


            return $re == true ? '200' : 'exception';
            
        } catch (\Exception $e) {
            
            Utils::notifyLog($merOrderId, $merOrderId, $e->getLine() .'-'.$e->getMessage());
            return 'system exception';
        }
    }

    //无忧异步通知
    public function wuyouNotify(){

        $postData   = $this->request->get();
        $orderid    = $this->request->get('orderid');//我传过去的系统订单号
        $state      = $this->request->get('state');
        $amount     = $this->request->get('amount');
        $sysorderid = $this->request->get('sysorderid');
        $attach     = $this->request->get('attach');
        $sign       = $this->request->get('sign');
        
        Log::write('无忧----'.request()->ip().'----'.json_encode($postData,JSON_UNESCAPED_UNICODE),'thirdNotify');
        
        if (!is_array($postData)){
            return 'data error';
        }
        if(empty($orderid) || empty($sysorderid) || empty($amount) || empty($sign)){
            return '参数缺少';
        }
        
        Utils::notifyLog($sysorderid, $orderid,'无忧收到回调'.json_encode($postData,JSON_UNESCAPED_UNICODE));
        
        try {
            $mer_key = '4181932637403f3e68b98f95e72d37c4';
            $wait_sign_str = 'orderid=' . $orderid . '&state=' . $state . '&amount=' . $amount . $mer_key;
            $mysign = md5($wait_sign_str);

            if($sign != $mysign){
                return 'sign fail';
            }

            $order = Order::where(['out_trade_no' => $orderid, 'status' =>2, 'amount' => $amount])->find();
            if (empty($order)){
                return '订单不存在';
            }

            //单号更新到订单
            Order::where(['id' => $order['id']])->update(['xl_order_id'=>$sysorderid]);

            $notify = new \app\common\library\Notify();
            $re     = $notify->dealOrderNotify($order , 1 ,'无忧' );


            return $re == true ? 'success' : 'exception';

        } catch (\Exception $e) {

            Utils::notifyLog($sysorderid, $orderid, $e->getLine() .'-'.$e->getMessage());
            return 'system exception';
        }
    }
    
    //微信直播异步通知
    public function wxpayNotify(){

        $postData       = $this->request->post();
        $memberid       = $this->request->post('memberid');//商户编号
        $orderid        = $this->request->post('orderid');//我传过去的系统订单号
        $amount         = $this->request->post('amount');
        $transaction_id = $this->request->post('transaction_id');//平台订单号
        $datetime       = $this->request->post('datetime');//订单时间
        $returncode     = $this->request->post('returncode');//交易状态 "00"表示成功，其它表示失败
        $attach         = $this->request->post('attach');//商户附加数据返回
        $sign           = $this->request->post('sign');
        $ip             = request()->ip();
        
        Log::write('微信直播----'.request()->ip().'----'.json_encode($postData,JSON_UNESCAPED_UNICODE),'thirdNotify');
        
        if(!in_array($ip,['16.163.194.220','18.163.244.101'])){
            return 'error';
        }
        
        if (!is_array($postData)){
            return 'data error';
        }
        
        if(empty($orderid) || empty($returncode) || empty($transaction_id) || empty($sign) || empty($amount)){
            return '参数缺少';
        }
        
        Utils::notifyLog($transaction_id, $orderid,'微信直播收到回调'.json_encode($postData,JSON_UNESCAPED_UNICODE));
        
        try {
            
            
            $mer_key = '7kk0o449v6r03ht77ievek3c61xnkzq0';
            $mysign  = strtoupper(Utils::sign($postData, $mer_key));
            
            if($sign != $mysign){
                return '签名错误';
            }

            $order = Order::where(['out_trade_no' => $orderid, 'amount' => $amount])->find();
            if (empty($order)){
                return '订单不存在';
            }

            //单号更新到订单
            Order::where(['id' => $order['id']])->update(['xl_order_id'=>$transaction_id]);

            $notify = new \app\common\library\Notify();
            $re     = $notify->dealOrderNotify($order , 1 ,'自动' );


            return $re == true ? 'ok' : 'exception';

        } catch (\Exception $e) {

            Utils::notifyLog($transaction_id, $orderid, $e->getLine() .'-'.$e->getMessage());
            return 'system exception';
        }
    }
    
    //汇付异步通知
    public function huifuNotify(){
        
        $postData       = $this->request->post();
        $resp_desc        = $this->request->post('resp_desc');
        $resp_code       = $this->request->post('resp_code');
        $sign           = $this->request->post('sign');
        $resp_data          = htmlspecialchars_decode($this->request->post('resp_data'));
        
        
        Log::write('汇付----'.request()->ip().'----'.json_encode($postData,JSON_UNESCAPED_UNICODE),'notify');
        
        try {
            
            
            if(empty($resp_desc) || empty($resp_code) || empty($sign) || empty($resp_data)){
                return '参数缺少';
            }
            
            $resp_data = htmlspecialchars_decode($resp_data);
            
            $resp_data_arr = json_decode($resp_data, true);
            
            if($resp_data_arr['resp_code'] != '00000000'){
                return '交易失败';
            }
            
            $out_trade_no = isset($resp_data_arr['mer_ord_id']) ? $resp_data_arr['mer_ord_id'] : '';//我方系统订单号
            $amount = isset($resp_data_arr['trans_amt']) ? $resp_data_arr['trans_amt'] : '';//金额
            if(empty($out_trade_no) || empty($amount)){
               return '参数缺少!'; 
            }
            
            
            
            $public_key  = Config::get('mchconf.huifu_public_key');

            $rsa = new Rsa($public_key, '');
            //公钥解密
            $verify = $rsa->public_verifyV2($resp_data, $sign);
            
            /*if(!$verify){
                Utils::notifyLog($out_trade_no, $out_trade_no,'验签失败');
                return 'sign fail';
            }*/
            
            $now_time = time();
            
            $order = Db::name("order")
                ->where(['out_trade_no' => $out_trade_no, 'status' =>2])
                ->find();
    		
            if (empty($order)){
                Utils::notifyLog($out_trade_no, $out_trade_no,'系统订单不存在');
                return '订单不存在';
            }
            
            if($amount != $order['amount']){
                return '金额错误';
            }
            
            $notify = new \app\common\library\Notify();
            $re     = $notify->dealOrderNotify($order , 1 ,'自动' );


            return $re == true ? '200' : 'exception';
            
        } catch (\Exception $e) {
            
            //Utils::notifyLog($out_trade_no, $out_trade_no, $e->getLine() .'-'.$e->getMessage());
            return 'system exception' . $e->getMessage();
        }
    }
    
    //账单收款上报
    public function zdNotify(){
        
        $trade_no    = $this->request->post('trade_no');//我方系统商户单号
        $amount      = $this->request->post('amount');//实际金额
        $android_key = $this->request->post('android_key');//通道设备值
        $time        = $this->request->post('pay_time');//支付时间
        $ali_trade_no= $this->request->post('ali_trade_no');//支付宝交易单号
        $postdata    = $this->request->post();
        
        Log::write('账单回调----'.json_encode($_POST,JSON_UNESCAPED_UNICODE),'notify');
        
        if(empty($ali_trade_no) || empty($amount) || empty($android_key)){
            $this->error('参数缺少');
        }
        
        $group_qrcode = Db::name("group_qrcode")->where("android_key", $android_key)->find();
        if(empty($group_qrcode)){
            $this->error('key错误');
        }
        if(empty($trade_no)){
            
            $order = Db::name('order')
            ->where(['pay_amount'=>$amount,'status'=>2,'qrcode_id'=>$group_qrcode['id']])
            ->where('expire_time','>',time())
            ->find();
            
        }else{
            
            if(strlen($trade_no) == 10){
                $order = Db::name('order')
                    ->where(['pay_remark'=>$trade_no,'pay_amount'=>$amount,'status'=>2])
                    ->find();
            }else{
                $order = Db::name('order')
                    ->where(['trade_no'=>$trade_no,'pay_amount'=>$amount,'status'=>2])
                    ->find();
            }
            
        }
        
        if(empty($order)){
            $this->error('订单不存在');
        }
        
        $user = Db::name('user')->where(['id'=>$order['user_id']])->find();

        //修改订单 开始回调
        $pay_time = time();
        $result   = false;
        
        try{
            
            //先修改订单状态 再发送回调
            $updata = [
                'xl_order_id'     => $ali_trade_no,
                'status'          => 1,
                'ordertime'       => $pay_time,
                'deal_ip_address' => request()->ip(),
                'deal_username'   => '上报',
            ];
    
            $updateOrderRe = Db::name('order')->where('id',$order['id'])->update($updata);
            if(!$updateOrderRe){
                $this->error('操作失败请重试');
            }
            
            //发送回调
            $callback = new CallbackNotify();
            
            $callbackre = $callback->sendCallBack($order['id'], 1, $pay_time);
            
            if($callbackre['code'] != 1){
                
                $callbackarray = [
                    'is_callback'       => 2,
                    'callback_time'     => time(),
                    'callback_count'    => $order['callback_count']+1,
                    'callback_content'  => $callbackre['content'],
                ];
                
                //回调失败 加入队列
                if (Config::get('site.is_queue_notify')){

                    // 当前任务所需的业务数据，不能为 resource 类型，其他类型最终将转化为json形式的字符串
                    $queueData = [
                        'request_type'  => 3,
                        'order_id'      => $order['id'],
                        'out_trade_no'  => $order['out_trade_no'],
                    ];

                    //当前任务归属的队列名称，如果为新队列，会自动创建
                    $queueName = 'checkorder';
                    $delay = 5;
                    // 将该任务推送到消息队列，等待对应的消费者去执行
                    //$isPushed = Queue::push(Jobs::class, $data, $queueName);//立即执行
                    $isPushed = Queue::later($delay,Jobs::class, $queueData, $queueName);//延迟$delay秒后执行

                }
                
            }else{

                //回调成功
                $callbackarray = [
                    'is_callback'       => 1,
                    'callback_time'     => time(),
                    'callback_count'    => $order['callback_count']+1,
                    'callback_content'  => $callbackre['content'],
                ];
            }
            
            $result = Db::name('order')->where('id',$order['id'])->update($callbackarray);
            
        } catch (\Exception $e) {
            Log::write('回调异常----'.$order['trade_no'].'----'.$e->getFile().'----'. $e->getLine() .'----' .$e->getMessage(),'notify');
            $this->error($e->getMessage());
        }
        
        if($result == false){
            $this->error('处理失败');
        }
        
        $this->success('处理成功');
    }
    
    public function aliwapNotify(){
        
        
        
        $out_trade_no   = $this->request->post('out_trade_no');//我方系统商户单号
        $total_amount   = $this->request->post('total_amount');//实际金额
        $trade_no       = $this->request->post('trade_no');//支付宝交易单号
        $trade_status   = $this->request->post('trade_status');//交易状态
        $buyer_logon_id = $this->request->post('buyer_logon_id');//买家账号
        $sign           = $this->request->post('sign');
        $postdata       = $this->request->post();
        
        Log::write('手机网站回调----'.json_encode($_POST,JSON_UNESCAPED_UNICODE),'notify');
        
        if(empty($trade_no) || empty($total_amount) || empty($out_trade_no) || empty($trade_status) || empty($sign)){
            $this->error('参数缺少');
        }
        
        try {
            
            $order = Db::name('order')->where(['out_trade_no'=>$out_trade_no,'status'=>2])->find();
            
            if(empty($order)){
                $this->error('订单不存在');
            }
            
            if($trade_status != 'TRADE_SUCCESS'){
                $this->error('not success');
            }
            
            if($trade_status == 'TRADE_SUCCESS'){
                
                $options['xl_order_id'] = $trade_no;
                $options['zfb_user_id'] = $buyer_logon_id;
                
                //处理订单 发送回调
                $notify = new CallbackNotify();
                $res    = $notify->dealOrderNotify($order, 1, 'ali', '', $options);
            }
            
            return 'success';
        
        }catch (Exception $e) {

            Log::write('手机网站回调异常----'.$e->getFile().'----'. $e->getLine() .'----' .$e->getMessage(),'notify');
            
            $this->error('异常失败');
        }
    }
}