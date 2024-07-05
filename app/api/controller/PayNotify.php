<?php

namespace app\api\controller;

use app\common\controller\Api;
use app\common\controller\Jobs;
use app\common\library\Notify as CallbackNotify;
use app\common\library\Utils;
use think\facade\Config;
use think\facade\Db;
use think\facade\Log;
use think\facade\Queue;

class PayNotify extends Api
{
    // 无需登录的接口,*表示全部
    //protected $noNeedLogin = ['subAlipayRed','alipay','appHeart','appPush','hcNotify'];
    protected $noNeedLogin = ['*'];
    // 无需鉴权的接口,*表示全部
    protected $noNeedRight = ['*'];


    public function huiCuiPay(){
        
        $info = file_get_contents('php://input');
        
        $post_data = json_decode($info, true);
        
        Log::write('翡翠回调----'.$info,'thirdPay');
        
        if(!is_array($post_data)){
            return 'params failed';
        }
        
        $sign         = $post_data['sign'];
        $memberId     = $post_data['memberId'];
        $orderId      = $post_data['orderId'];//订单编号
        $trade_no     = $post_data['callerOrderId'];//我方系统商户单号
        $orderStatus  = $post_data['orderStatus'];//订单状态（NP：等待支付；AP：订单成功；AF：订单失败；AC：订单关闭）
        $orderAmount  = $post_data['orderAmount'];//订单金额 (单位：分)
        $actualAmount = $post_data['actualAmount'];//实际金额 (单位：分)
        
        
        if(empty($memberId) || empty($orderId) || empty($trade_no)  || empty($orderStatus)  || empty($orderAmount)){
            $this->error('参数缺少');
        }
        
        if(request()->ip() != '43.129.239.62'){
            $this->error('failed');
        }
        
        try{
            
            Utils::notifyLog($trade_no, $trade_no, '翡翠回调'.$info);
            
            $payAcc = Db::name('thirdpay_acc')->where('merchant_no',$memberId)->find();
            if(empty($payAcc)){
                $this->error('商户信息错误');
            }
            
            $orderAmount = $orderAmount / 100;
            
            //半小时内
            $time = time() - 60 * 30;
            $order = Db::name('order')
                ->where(['out_trade_no'=> $trade_no,'amount'=> $orderAmount])
                ->where('createtime', '>', $time)
                ->find();

            if(empty($order)){
                return 'failed1';
            }
            
            
            if($orderStatus == 'AP'){
                $status = 1;
            }else if($orderStatus == 'AF'){
                $status = 3;
            }else{
                $status = 5;
            }
            if($status != 1 || $status !=3){
                return 'order error';
            };
            
            //先修改订单状态 再发送回调
            $updata = [
                'status'          => $status,
                'ordertime'       => time(),
                'deal_ip_address' => request()->ip(),
                'deal_username'   => '翡翠',
                'hand_pay_data'   => json_encode($_POST,JSON_UNESCAPED_UNICODE),
            ];

            $updateOrderRe = Db::name('order')->where('id',$order['id'])->update($updata);
            if(!$updateOrderRe){
                return 'order fail';
            }

            if($status == 1){

                //发送回调
                $callback   = new CallbackNotify();
                $callbackre = $callback->sendCallBack($order['id'], 1, time());

                if($callbackre['code'] != 1){

                    $callbackarray = [
                        'is_callback'       => 2,
                        'callback_time'     => time(),
                        'callback_count'    => $order['callback_count']+1,
                        'callback_content'  => $callbackre['content'],
                    ];

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
            }

        } catch (\Exception $e) {
            Log::write('回调异常----'.$e->getFile().'----'. $e->getLine() .'----' .$e->getMessage(),'notify');
            $this->error($e->getMessage());
        }
        
        return 'OK';
    }
}