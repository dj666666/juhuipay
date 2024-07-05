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

class ThirdDf
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
    public function checkDfType($out_trade_no, $bank_user, $bank_type, $bank_number, $amount, $df_acc_ids, $is_hand = false){

        //根据商户号绑定的三方代付平台，随机挑一家来提交
        $df_acc_ids = explode(',', $df_acc_ids);
        
        if(empty($df_acc_ids)){
            $this->updateOrder($out_trade_no, 0, 0 , '商户无代付通道');
        }
        
        $count = count($df_acc_ids);
        if($count == 0){
            $this->updateOrder($out_trade_no, 0, 0 , '商户无代付通道');
        }
        
        $df_acc_id = $df_acc_ids[mt_rand(0, $count - 1)];
        
        //找出通道信息
        $dfAcc = Db::name('df_acc')->where('id',$df_acc_id)->find();
        
        if(empty($dfAcc['merchant_no']) || empty($dfAcc['merchant_key']) || empty($dfAcc['sub_order_url'])){
            $this->updateOrder($out_trade_no, $dfAcc['id'], $dfAcc['code'] , '无对接信息');
        }

        switch ($dfAcc['code']){
            case '2001':
                $res = $this->haoNan($out_trade_no, $bank_user, $bank_type, $bank_number, $amount, $dfAcc);
                $res['msg'] = '浩南-'.$res['msg'];
                break;
            default:
                $res = ['status' => false, 'msg' => '代付通道类型错误'];
        }
        
        $this->updateOrder($out_trade_no, $dfAcc['id'], $dfAcc['code'] , $res);
        
        if(!$is_hand){
            return true;
        }
        
        return $res;
        

    }
    
    //代付查单
    public function checkDfTypeByQueryOrder($out_trade_no, $df_acc_id){
        
        //找出通道信息
        $dfAcc = Db::name('df_acc')->where('id',$df_acc_id)->find();
        
        switch ($dfAcc['code']){
            case '2001':
                $res = $this->haoNanQueryOrder($out_trade_no, $bank_user, $bank_type, $bank_number, $amount, $dfAcc);
                $res['msg'] = '浩南-'.$res['msg'];
                break;
            default:
                $res = ['status' => false, 'msg' => '代付通道类型错误'];
        }
        
        $this->updateOrder($out_trade_no, $dfAcc['id'], $dfAcc['code'] , $res['msg']);
        
        return true;

    }
    
    //浩南代付
    public function haoNan($out_trade_no, $bank_user, $bank_type, $bank_number, $amount, $dfAcc){
        $url       = $dfAcc['sub_order_url'];
        $merchant_no = $dfAcc['merchant_no'];
        $merchant_key    = $dfAcc['merchant_key'];
        $Timestamp = time();
        $NotifyUrl = Utils::imagePath('/api/WithdrawNotify/hnNotify', true);
        
        $postData = [
            'Timestamp' => $Timestamp,
            'AccessKey' => $merchant_no,
            'PayChannelId' => 600,
            'Payee' => $bank_user,
            'PayeeNo' => $bank_number,
            'PayeeAddress' => $bank_type,
            'OrderNo' => $out_trade_no,
            'Amount' => $amount,
            'CallbackUrl' => $NotifyUrl,
        ];
        
        $sign = Utils::signV5($postData, 'SecretKey', $merchant_key);
        $postData['Sign'] = $sign;
        
        $header_arr = [
            'Content-Type:application/json',
        ];
        $options    = [
            CURLOPT_HTTPHEADER => $header_arr,
        ];
        
        Log::write('浩南提交----' . json_encode($postData, JSON_UNESCAPED_UNICODE), 'thirdDf');

        
        $res = Http::post($url, json_encode($postData), $options);
        
        //t提交结果
        Utils::notifyLog($out_trade_no, $out_trade_no, '浩南提交结果' . $res);
        
        $result = json_decode($res, true);
        
        if (!isset($result['Code']) || $result['Code'] != 0) {
            
            $returnData = ['status' => false, 'msg' => isset($result['Message']) ? $result['Message'] : '未知错误'];
        }else{
            
            $returnData = ['status' => true, 'msg' => $result['Message']];
        }
        
        return $returnData;
    }
    
    //浩南查单
    public function haoNanQueryOrder($out_trade_no, $dfAcc){
        
        $url = $dfAcc['query_order_url'];
        $merchant_no = $dfAcc['merchant_no'];
        $merchant_key = $dfAcc['merchant_key'];
        $Timestamp = time();
        $postData = [
            'Timestamp' => $Timestamp,
            'AccessKey' => $merchant_no,
            'OrderNo' => $out_trade_no,
        ];
        
        $sign = Utils::signV5($postData, 'SecretKey', $merchant_key);
        $postData['Sign'] = $sign;
        
        $header_arr = [
            'Content-Type:application/json',
        ];
        $options    = [
            CURLOPT_HTTPHEADER => $header_arr,
        ];
        
        Log::write('haonanQuery----' . json_encode($postData, JSON_UNESCAPED_UNICODE), 'thirdDf');

        
        $res = Http::post($url, json_encode($postData), $options);
        
        //提交结果
        Log::write('queryRes----' . $res, 'thirdDf');
        
        $res = json_decode($res, true);
        
        if (!isset($result['Code']) || $result['Code'] != 0) {
            
            $returnData = ['status' => false, 'msg' => isset($result['Message']) ? $result['Message'] : '未知错误', 'data'=> ''];
        }else{
            
            $returnData = ['status' => true, 'msg' => $result['Message'], 'data' => $result];
        }
        
        return $returnData;
    }
    
    //修改订单状态
    public function updateOrder($out_trade_no, $third_df_id, $third_df_code, $returnData){
        
        if($returnData['status']){
            $is_third_df = 1;
        }else{
            $is_third_df = 2;
        }
        
        Db::name('df_order')->where('out_trade_no', $out_trade_no)->update([
            'is_third_df'   => $is_third_df,
            'third_df_id'   => $third_df_id,
            'third_df_code' => $third_df_code,
            'error_msg'     => $returnData['msg']
        ]);
    }
    
}