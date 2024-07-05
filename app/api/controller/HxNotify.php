<?php

namespace app\api\controller;

use app\admin\model\order\Order;
use app\admin\model\user\User;
use app\common\controller\Api;
use Exception;
use think\facade\Db;
use think\facade\Config;
use app\common\library\Aes;
use app\common\library\Utils;
use app\common\library\Notify;
use think\cache\driver\Redis;
use fast\Http;
use think\facade\Log;
use think\Request;
use think\facade\Queue;
use app\common\controller\Jobs;
use app\admin\model\user\Userhxacc;

/**
 * 核销平台回调
 */
class HxNotify extends Api
{

    //如果$noNeedLogin为空表示所有接口都需要登录才能请求
    //如果$noNeedRight为空表示所有接口都需要验证权限才能请求
    //如果接口已经设置无需登录,那也就无需鉴权了
    //
    // 无需登录的接口,*表示全部
    protected $noNeedLogin = ['xiaoka', 'mskNotify','qwNotify','eChaKaNotify'];
    // 无需鉴权的接口,*表示全部
    protected $noNeedRight = ['*'];

    //173销卡回调
    public function xiaoka(){

        $pxOrderId   = $this->request->post('pxOrderId');//平台单号编号
        $apiOrderId  = $this->request->post('apiOrderId');//我方系统订单号
        $finalPrice  = $this->request->post('finalPrice');//实际面值
        $status      = $this->request->post('status'); //订单状态：1-处理成功 2-处理失败
        $finalRate   = $this->request->post('finalRate'); //最终折扣:“99.7”
        $finalAmount = $this->request->post('finalAmount'); //结算金额
        $remarks     = $this->request->post('remarks'); //失败时，原因说明
        $finishTime  = $this->request->post('finishTime');//订单完成时间
        $signature   = $this->request->post('signature');//加密值
        $post_data   = $this->request->post();

        Log::write('mskNotify----' . request()->ip() . '----' . json_encode($post_data, JSON_UNESCAPED_UNICODE), 'thirdNotify');

        if (empty($pxOrderId) || empty($apiOrderId) || empty($finalPrice) || empty($status) || empty($finalRate) || empty($finalAmount) || empty($finishTime) || empty($signature)) {
            return '参数缺少';
        }
        
        Utils::notifyLog($UserOrderId, $UserOrderId, '173销卡回调' . json_encode($post_data, JSON_UNESCAPED_UNICODE));
        
        
        try {

            $key     = 'BSYDSPFKRXk2zENY1PyQAPSc1T6CA5bM';
            $signStr = 'pxOrderId' . $pxOrderId . 'apiOrderId' . $apiOrderId . 'status' . $status . 'finalAmount' . $finalAmount . 'finalPrice' . $finalPrice . 'finalRate' . $finalRate . 'key' . $key;

            $mysign = md5($signStr);
            if ($mysign != $signature) {
                return '签名错误' . $mysign;
            }
            $apiOrderId = str_replace("XK", '', $apiOrderId);
            $order = Order::where(['out_trade_no' => $apiOrderId, 'amount' => $finalPrice])->find();
            if (empty($order)) {
                return '信息不存在';
            }

            //支付成功的单子不处理
            if ($order['status'] == 1) {
                return '信息不存在!';
            }

            //如果不是提交成功的状态 不继续核销
            if ($order['third_hx_status'] != 1) {
                return '信息不存在!!';
            }

            if ($status == 1) {
                $hx_status = 3;//状态:0=提交失败,1=提交成功,2=核销失败,3=核销成功
            } else {
                $hx_status = 2;
            }

            //修改核销状态
            Order::where('id', $order['id'])->update(['third_hx_status' => $hx_status, 'xl_pay_data'=>$remarks]);

            if ($status == 1) {
                //判断该码商是否开启自动销卡回调
                $user = User::find($order['user_id']);

                if ($user['is_third_hx'] == 1) {

                    //收到成功回调 处理订单
                    $notify = new Notify();
                    $res    = $notify->dealOrderNotify($order, 1, '173销卡');
                    if (!$res) {
                        return '处理失败';
                    }
                }
            }

            


        } catch (Exception $e) {
            
            Log::write('xiaokaError----'.$apiOrderId.'----'.$e->getFile().'----'. $e->getLine() .'----' .$e->getMessage(),'thirdNotify');
            return '处理失败';
        }

        return 'Y';

    }

    //盟收卡回调
    public function mskNotify(){

        $UserOrderId = $this->request->get('UserOrderId'); //我方系统订单号
        $RealValue   = $this->request->get('RealValue'); //卡密实金额
        $UserIncome  = $this->request->get('UserIncome');//商户获得金额
        $Code        = $this->request->get('Code');//状态码
        $Msg         = $this->request->get('Msg');//返回信息
        $sign        = $this->request->get('sign');//前面
        $get_data    = $this->request->get();

        Log::write('mskNotify----' . request()->ip() . '----' . json_encode($get_data, JSON_UNESCAPED_UNICODE), 'thirdNotify');
        
        if (empty($UserOrderId) || strlen($RealValue) < 1 || strlen($UserIncome) < 1 || strlen($Code) < 1 || empty($sign)) {
            return '参数缺少';
        }
        
        
        try {
            
            
            Utils::notifyLog($UserOrderId, $UserOrderId, '盟收卡回调' . json_encode($get_data, JSON_UNESCAPED_UNICODE));
            
            $order = Order::where(['out_trade_no' => $UserOrderId])->find();
            if (empty($order)) {
                return '信息不存在';
            }
            
            //根据用户添加的销卡平台，获取这个通道对应的核销配置
            $userHxAcc = Userhxacc::where(['id'=>$order['hx_acc_id'],'pay_type' => $order['pay_type']])->find()->toArray();
            if(empty($userHxAcc['third_hx_id']) || empty($userHxAcc['third_hx_key'])){
                $this->orderEerrorMsg($order['id'], 2, '配置错误');
                return '配置错误';
            }
            
            $key     = $userHxAcc['third_hx_key'];
            $signStr = 'Code=' . $Code . '&Ext=&Msg=' . $Msg . '&RealValue=' . $RealValue . '&UserIncome=' . $UserIncome . '&UserOrderId=' . $UserOrderId . '&key=' . $key;
            
            $mysign = strtoupper(md5($signStr));
            if ($mysign != $sign) {
                $this->orderEerrorMsg($order['id'], 2, '签名错误');
                return '签名错误';
            }
    
            //0正在处理中，2成功，3失败
            if ($Code == 0) {
                return '正在处理中';
            }
    
            
    
            //支付成功的单子不处理
            if ($order['status'] == 1) {
                return '信息不存在!';
            }
    
            //如果不是提交成功的状态 不继续核销
            if ($order['third_hx_status'] != 1) {
                return '信息不存在!!';
            }
            
            //0正在处理中，2成功，3失败 
            if ($Code == 2) {
                //判断金额
                if($order['amount'] != $RealValue){
                    $this->orderEerrorMsg($order['id'], 2, '金额错误'.$RealValue);
                    return '金额错误';
                }
                
                $hx_status = 3;
            } else {
                $hx_status = 2;
            }
            
            
            //修改核销状态
            Order::where('id', $order['id'])->update(['third_hx_status' => $hx_status, 'xl_pay_data'=>$Msg ,'quantity' => $RealValue]);
    
            if ($Code == 2) {
                //判断该码商是否开启自动销卡回调
                $user = User::find($order['user_id']);
    
                if ($user['is_third_hx'] == 1) {
    
                    //收到成功回调 处理订单
                    $notify = new Notify();
                    $res    = $notify->dealOrderNotify($order, 1, '盟收卡');
                    if (!$res) {
                        return '处理失败';
                    }
                }
            }
            
            
            
        }catch (Exception $e) {
            
            Log::write('mskNotifyError----'.$UserOrderId.'----'.$e->getFile().'----'. $e->getLine() .'----' .$e->getMessage(),'thirdNotify');
            return '异常失败，请联系客服';
        }
        
        return 'ok';
    }

    //青蛙回调
    public function qwNotify(){

        $customerId    = $this->request->post('customerId'); //商户ID
        $orderId       = $this->request->post('orderId'); //我方系统订单号
        $systemOrderId = $this->request->post('systemOrderId'); //青蛙系统订单号
        $status        = $this->request->post('status');//状态码 0验卡中，1处理中，2处理成功，3处理失败
        $cardNumber    = $this->request->post('cardNumber'); //充值卡卡号，使用Aes加密过需解密
        $cardPassword  = $this->request->post('cardPassword'); //充值卡密码，使用Aes加密过需解密
        $amount        = $this->request->post('amount'); //提交面值
        $successAmount = $this->request->post('successAmount');//结算面值
        $actualAmount  = $this->request->post('actualAmount');//结算金额
        $extendParams  = $this->request->post('extendParams');//扩展信息（原样返回商户的扩展信息）
        $message       = $this->request->post('message');//错误返回错误信息，成功返回成功
        $realPrice     = $this->request->post('realPrice');//真实面值
        $sign          = $this->request->post('sign');//签名
        $post_data     = $this->request->post();

        Log::write('qwNotify----' . request()->ip() . '----' . json_encode($post_data, JSON_UNESCAPED_UNICODE), 'thirdNotify');

        if (empty($customerId) || strlen($orderId) < 1 || strlen($status) < 1 || empty($realPrice) || empty($sign)) {
            return '参数缺少';
        }

        try {

            Utils::notifyLog($systemOrderId, $orderId, '青蛙回调' . json_encode($post_data, JSON_UNESCAPED_UNICODE));

            $order = Order::where(['out_trade_no' => $orderId])->find();
            if (empty($order)) {
                return '信息不存在';
            }

            //根据用户添加的销卡平台，获取这个通道对应的核销配置
            $userHxAcc = Userhxacc::where(['id' => $order['hx_acc_id'], 'pay_type' => $order['pay_type']])->find()->toArray();
            if (empty($userHxAcc['third_hx_id']) || empty($userHxAcc['third_hx_key'])) {
                $this->orderEerrorMsg($order['id'], 2, '配置错误');
                return '配置错误';
            }

            $mysign = Utils::sign($post_data, $userHxAcc['third_hx_key']);
            if ($mysign != $sign) {
                $this->orderEerrorMsg($order['id'], 2, '签名错误');
                return '签名错误';
            }

            ///0验卡中，1处理中，2处理成功，3处理失败
            if ($status == 0) {
                return '验卡中';
            }

            //支付成功的单子不处理
            if ($order['status'] == 1) {
                return '信息不存在!';
            }

            //如果不是提交成功的状态 不继续核销
            if ($order['third_hx_status'] != 1) {
                return '信息不存在!!';
            }


            //0验卡中，1处理中，2处理成功，3处理失败
            if ($status == 2) {
                //判断金额
                if($order['amount'] != $realPrice){
                    $this->orderEerrorMsg($order['id'], 2, '金额错误'.$realPrice);
                    return '金额错误';
                }

                $hx_status = 3; //系统核销状态 0未提交 1提交成功 2核销失败 3核销成功
            } else {
                $hx_status = 2;
            }


            //修改核销状态
            Order::where('id', $order['id'])->update(['third_hx_status' => $hx_status, 'xl_pay_data'=>$message ,'quantity' => $realPrice]);

            if ($status == 2) {
                //判断该码商是否开启自动销卡回调
                $user = User::find($order['user_id']);

                if ($user['is_third_hx'] == 1) {

                    //收到成功回调 处理订单
                    $notify = new Notify();
                    $res    = $notify->dealOrderNotify($order, 1, '青蛙');
                    if (!$res) {
                        return '处理失败';
                    }
                }
            }

        }catch (Exception $e) {

            Log::write('qwNotifyError----'.$orderId.'----'.$e->getFile().'----'. $e->getLine() .'----' .$e->getMessage(),'thirdNotify');
            return '异常失败，请联系客服';
        }

        return 'SUCCESS';
    }

    //e查卡回调
    public function eChaKaNotify(){

        $merOrderId    = $this->request->post('merOrderId'); //我方系统订单号
        $bindStatus    = $this->request->post('bindStatus');//状态（0：待绑定、1：绑定成功、2：绑定失败）
        $cardBrand     = $this->request->post('cardBrand'); //是否通用（0为通用，其他非通用）
        $realFaceValue = $this->request->post('realFaceValue'); //成功绑定金额
        $finishDate    = $this->request->post('finishDate'); //完成时间
        $retmsg        = $this->request->post('retmsg');//失败原因
        $createDate    = $this->request->post('createDate');//提交时间
        $sign          = $this->request->post('sign');//扩展信息（原样返回商户的扩展信息）
        $post_data     = $this->request->post();

        Log::write('eChaKaNotify----' . request()->ip() . '----' . json_encode($post_data, JSON_UNESCAPED_UNICODE), 'thirdNotify');

        if (empty($merOrderId) || strlen($bindStatus) < 1 || strlen($realFaceValue) < 1 || empty($sign)) {
            return '参数缺少';
        }

        try {

            Utils::notifyLog($merOrderId, $merOrderId, 'e查卡回调' . json_encode($post_data, JSON_UNESCAPED_UNICODE));

            $order = Order::where(['out_trade_no' => $merOrderId])->find();
            if (empty($order)) {
                return '信息不存在';
            }

            //根据用户添加的销卡平台，获取这个通道对应的核销配置
            $userHxAcc = Userhxacc::where(['id' => $order['hx_acc_id'], 'pay_type' => $order['pay_type']])->find()->toArray();
            if (empty($userHxAcc['third_hx_id']) || empty($userHxAcc['third_hx_key'])) {
                $this->orderEerrorMsg($order['id'], 2, '配置错误');
                return '配置错误';
            }

            $signStr = $merOrderId.'&'.$realFaceValue.'&'.$bindStatus.'&'.$userHxAcc['third_hx_key'];
            $mysign  = md5($signStr);
            if ($mysign != $sign) {
                $this->orderEerrorMsg($order['id'], 2, '签名错误');
                return '签名错误';
            }

            ///0：待绑定、1：绑定成功、2：绑定失败
            if ($bindStatus == 0) {
                return '待绑定';
            }

            //支付成功的单子不处理
            if ($order['status'] == 1) {
                return '信息不存在!';
            }

            //如果不是提交成功的状态 不继续核销
            if ($order['third_hx_status'] != 1) {
                return '信息不存在!!';
            }

            //0：待绑定、1：绑定成功、2：绑定失败
            if ($bindStatus == 1) {
                //判断金额
                if($order['amount'] != $realFaceValue){
                    $this->orderEerrorMsg($order['id'], 2, '金额错误'.$realFaceValue);
                    return '金额错误';
                }

                $hx_status = 3; //系统核销状态 0未提交 1提交成功 2核销失败 3核销成功
            } else {
                $hx_status = 2;
            }


            //修改核销状态
            Order::where('id', $order['id'])->update(['third_hx_status' => $hx_status, 'xl_pay_data'=>$retmsg ,'quantity' => $realFaceValue]);

            if ($bindStatus == 1) {
                //判断该码商是否开启自动销卡回调
                $user = User::find($order['user_id']);

                if ($user['is_third_hx'] == 1) {

                    //收到成功回调 处理订单
                    $notify = new Notify();
                    $res    = $notify->dealOrderNotify($order, 1, 'e查卡');
                    if (!$res) {
                        return '处理失败';
                    }
                }
            }

        }catch (Exception $e) {

            Log::write('eChaKaNotify----'.$merOrderId.'----'.$e->getFile().'----'. $e->getLine() .'----' .$e->getMessage(),'thirdNotify');
            return '异常失败，请联系客服';
        }

        return 'Y';
    }

    //订单信息统一修改
    public function orderEerrorMsg($order_id, $hx_status, $msg){
        
        Order::where('id', $order_id)->update(['third_hx_status' => $hx_status, 'xl_pay_data'=>$msg]);
    }

}