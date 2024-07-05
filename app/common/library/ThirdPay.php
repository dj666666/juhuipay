<?php
namespace app\common\library;

use app\admin\model\user\User;
use app\admin\model\order\Order;
use fast\Random;
use think\facade\Config;
use think\facade\Db;
use think\Exception;
use fast\Http;
use think\facade\Log;

class ThirdPay
{
    /**
     * 单例对象
     */
    protected static $instance;

    public static function instance(){
        if (is_null(self::$instance)) {
            self::$instance = new static();
        }

        return self::$instance;
    }

    public function __construct($options = []){

    }

    /**
     * 统一取平台
     *
     * @param $out_trade_no
     * @param $amount
     * @param $cardNo
     * @param $cardPwd
     * @param $orderInfo
     * @return array|bool
     */
    public function checkPayType($agent_id, $out_trade_no, $amount, $pay_type, $acc_ids, $is_hand = false){
        
        //根据商户号绑定的三方代付平台，随机挑一家来提交
        if(empty($acc_ids)){
            return ['status' => false, 'msg' => '无绑定通道'];
        }
        
        //找出通道信息
        $dfAcc = Db::name('thirdpay_acc')->where(['agent_id'=> $agent_id, 'pay_type' => $pay_type])->find();
        if(empty($dfAcc)){
            return ['status' => false, 'msg' => '无三方通道'];
        }
        
        if(empty($dfAcc['merchant_no']) || empty($dfAcc['merchant_key']) || empty($dfAcc['api_url']) || empty($dfAcc['product_id'])){
            return ['status' => false, 'msg' => '无对接信息'];
        }
        
        switch ($dfAcc['code']){
            case '2001':
                $res = $this->feicuizhifu($out_trade_no, $amount, $dfAcc);
                $res['msg'] = '翡翠-'.$res['msg'];
                break;
            default:
                $res = ['status' => false, 'msg' => '通道类型错误'];
        }
        
        return $res;

    }
    
    //查单
    public function checkDfTypeByQueryOrder($out_trade_no, $df_acc_id){
        
        //找出通道信息
        $dfAcc = Db::name('df_acc')->where('id',$df_acc_id)->find();
        
        switch ($dfAcc['code']){
            case '2001':
                $res = $this->haoNanQueryOrder($out_trade_no, $dfAcc);
                $res['msg'] = '浩南-'.$res['msg'];
                break;
            default:
                $res = ['status' => false, 'msg' => '代付通道类型错误'];
        }

        return true;

    }

    public function feicuizhifu($out_trade_no, $amount, $payAcc){
        $url          = $payAcc['api_url'];
        $merchant_no  = $payAcc['merchant_no'];
        $merchant_key = $payAcc['merchant_key'];
        $product_id   = $payAcc['product_id'];
        $amount = $amount * 100;
        $NotifyUrl = Utils::imagePath('/api/PayNotify/huiCuiPay', true);
        
        $postData = [
            'method'              => 'placeOrder',
            'timestamp'           => date('Y-m-d H:i:s'),
            'memberId'            => $merchant_no,
            'callerOrderId'       => $out_trade_no,
            'amount'              => $amount,
            'channelCode'         => $product_id,
            'merchantCallbackUrl' => $NotifyUrl,
            'Amount'              => $amount,
            'CallbackUrl'         => $NotifyUrl,
        ];
        
        $sign = Utils::signByhuiCui($postData, $merchant_key);
        $postData['sign'] = $sign;
        
        $header_arr = [
            'Content-Type:application/json',
        ];
        $options    = [
            CURLOPT_HTTPHEADER => $header_arr,
        ];
        
        Log::write('翡翠提交----' . json_encode($postData, JSON_UNESCAPED_UNICODE), 'thirdPay');

        $res = Http::post($url, json_encode($postData), $options);
        
        //提交结果
        Utils::notifyLog($out_trade_no, $out_trade_no, '翡翠提交结果' . $res);
        
        $result = json_decode($res, true);
        
        if (!isset($result['code']) || $result['code'] != 1000) {
            
            $returnData = ['status' => false, 'msg' => isset($result['memo']) ? $result['memo'] : '未知错误', 'data' => ''];
        }else{
            $returnData = ['status' => true, 'msg' => $result['memo'], 'data' => $result['data']['message']['url']];
        }
        
        $options = [];
        
        if($returnData['status']){
            $options['xl_order_id'] = $result['data']['orderId'];
            $options['xl_pay_data'] = $result['data']['message']['url'];
            $options['pay_url']     = $result['data']['message']['url'];
        }
        
        $this->updateOrder($out_trade_no, $payAcc, $returnData , $res, $options);
        
        return $returnData;
    }
    
    
    //修改订单状态
    public function updateOrder($out_trade_no, $payAcc, $returnData, $res, $options = []){
        
        if($returnData['status']){
            $is_third = 1;
        }else{
            $is_third = 2;
        }
        
        $data = [
            'is_third_pay' => $is_third,
            'third_pay_id' => $payAcc['id'],
            'hc_pay_data'  => $res,
        ];
        
        if(!empty($options)){
            $data = array_merge($data, $options);
        };
        
        Db::name('order')->where('out_trade_no', $out_trade_no)->update($data);
    }
    
}