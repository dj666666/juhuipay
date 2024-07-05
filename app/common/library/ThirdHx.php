<?php
namespace app\common\library;

use app\admin\model\user\User;
use app\admin\model\order\Order;
use app\admin\model\user\Userhxacc;
use fast\Random;
use think\facade\Config;
use think\facade\Db;
use think\Exception;
use fast\Http;
use think\facade\Log;

class ThirdHx
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
    public function checkXkType($out_trade_no, $amount, $cardNo, $cardPwd, $orderInfo){

        //根据用户添加的销卡平台，随机挑一家来提交
        $userHxAccList = Userhxacc::where(['user_id'=>$orderInfo['user_id'] , 'status'=>1 , 'pay_type' => $orderInfo['pay_type']])->select()->toArray();
        
        $count = count($userHxAccList);
        if($count == 0){
            return ['status' => false, 'msg' => '码商无销卡通道'];
        }
        
        $hxAcc = $userHxAccList[mt_rand(0, $count - 1)];

        if(empty($hxAcc['third_hx_id']) || empty($hxAcc['third_hx_key'])){
            return ['status' => false, 'msg' => '码商未填写对接信息'];
        }

        switch ($hxAcc['hx_code']){
            case 'hx1001':
                $res = $this->mengShouKa($out_trade_no, $amount, $cardNo, $cardPwd, $orderInfo, $hxAcc);
                $res['msg'] = '盟收-'.$res['msg'];
                break;
            case 'hx1002':
                $res = $this->qingwaXaiaoKa($out_trade_no, $amount, $cardNo, $cardPwd, $orderInfo, $hxAcc);
                $res['msg'] = '青蛙-'.$res['msg'];
                break;
            case 'hx1003':
                //e查卡京东走特定方法接口
                if ($orderInfo['pay_type'] == '1040'){
                    $res = $this->eChaKaByEKa($out_trade_no, $amount, $cardNo, $cardPwd, $orderInfo, $hxAcc);
                }else{
                    $res = $this->eChaKaByCommon($out_trade_no, $amount, $cardNo, $cardPwd, $orderInfo, $hxAcc);
                }
                $res['msg'] = 'e查卡-'.$res['msg'];
                break;
            default:
                $res = ['status' => false, 'msg' => '销卡平台类型错误'];
        }

        return $res;

    }


    /**
     * 173销卡
     *
     * @param $out_trade_no
     * @param $amount
     * @param $cardNo
     * @param $cardPwd
     * @param $orderInfo
     * @return array
     */
    public function xiaoka($out_trade_no, $amount, $cardNo, $cardPwd, $orderInfo){
        $amount      = intval($amount);
        $url         = 'https://www.173xiaoka.com/pxapi/order/api/batchSubmit';
        //$hx_no       = Utils::buildOutTradeNo();//生成单号
        $hx_no       = 'XK' . $out_trade_no;
        $merchantId  = 436;
        $productCode = '67';//骏卡智充卡
        $merchantKey = 'BSYDSPFKRXk2zENY1PyQAPSc1T6CA5bM';
        $CardData        = $cardNo . ',' . $cardPwd . ',' . $hx_no .';';
        $customRate  = '93.3';
        $callbackUrl = Utils::imagePath('/api/HxNotify/xiaoka', true);
        $signStr     = 'merchantId' . $merchantId . 'productCode' . $productCode . 'data' . $CardData .'customRate'.$customRate.'key' . $merchantKey;
        $signature   = md5($signStr);
        
        $aes         = new Aes($merchantKey,'DES-EDE3');//24位
        $encrypt     = base64_decode($aes->encrypt($CardData));
        $data        = bin2hex($encrypt);
        
        $postData    = [
            'merchantId'  => $merchantId,
            'productCode' => $productCode,
            'amount'      => $amount,
            'data'        => $data,
            'customRate'  => $customRate,
            'callbackURL' => $callbackUrl,
            'signature'   => $signature,
        ];
        
        $options = [
            CURLOPT_HTTPHEADER => [
                'Content-Type:application/json;charset=utf-8',
            ]
        ];
        
        //发送
        Utils::notifyLog($out_trade_no, $out_trade_no, '173销卡提交'.json_encode($postData) . '签名字符串'.$signStr .'卡密加密信息'.$CardData);

        $res    = Http::post($url, json_encode($postData), $options);
        $result = json_decode($res, true);

        //收到
        Utils::notifyLog($out_trade_no, $out_trade_no, '173销卡收到'.$res);

        if ($result['code'] != 200) {
            $hx_status = 0;//核销状态:0=提交失败,1=提交成功,2=核销失败,3=核销成功
            $returnData = ['status' => false, 'msg' => $result['message']];
        }else{
            $hx_status = 1;
            $returnData = ['status' => true, 'msg' => $result['message']];
        }

        //修改提交核销状态
        Order::where('id',$orderInfo['id'])->update(['third_hx_status'=>$hx_status]);

        return $returnData;

    }

    /**
     * 盟收卡核销
     *
     * @param $out_trade_no
     * @param $amount
     * @param $cardNo
     * @param $cardPwd
     * @param $orderInfo
     * @return array
     */
    public function mengShouKa($out_trade_no, $amount, $cardNo, $cardPwd, $orderInfo, $hxAcc){
        $url       = 'http://www.mengshouka.com/Interface/SubCards.aspx';
        $hx_acc_id = $hxAcc['id'];
        $UserID    = $hxAcc['third_hx_id'];
        $UserKey1  = $hxAcc['third_hx_key'];
        $UserKey2  = $hxAcc['third_hx_key']; //aes DES-EDE3模式，只读取24位密钥 自动截取
        $BatchNo   = time();
        $CardType  = $hxAcc['product_id']; //产品id
        $CardStr   = intval($amount) . '#'.$cardNo.'#'.$cardPwd;
        $aes       = new Aes($UserKey2,'DES-EDE3');//128-12位密钥 192-24位密钥 256-32位密钥
        $encrypt   = $aes->encrypt($CardStr);
        $encrypt   = base64_decode($encrypt);
        $CardInfo  = bin2hex($encrypt);
        $NotifyUrl = Utils::imagePath('/api/HxNotify/mskNotify', true);
        $signStr   = 'BatchNo=' . $BatchNo . '&CardInfo=' . $CardInfo . '&CardType=' . $CardType . '&Ext=&NotifyUrl=' . $NotifyUrl . '&UserID=' . $UserID . '&UserOrderId=' . $out_trade_no . '&key=' . $UserKey1;
        $sign      = strtoupper(md5($signStr));

        $sendData = 'BatchNo=' . $BatchNo . '&UserID=' . $UserID . '&UserOrderId=' . $out_trade_no . '&CardType=' . $CardType . '&CardInfo=' . $CardInfo . '&NotifyUrl=' . $NotifyUrl . '&Sign=' . $sign . '&Ext=';
        $url      = $url . '?' . $sendData;

        //发送
        //Utils::notifyLog($out_trade_no, $out_trade_no, '盟收卡提交' . $url . '签名字符串' . $signStr .'CardInfo串'.$CardStr);
        Log::write('mengShouKa----' . $url, 'thirdNotify');
        
        $res    = Http::get($url);
        $res    = str_replace("'", '"', $res);
        $result = json_decode(trim($res), true);
        
        //收到
        Utils::notifyLog($out_trade_no, $out_trade_no, '盟收卡收到' . $res);

        if (!isset($result['Code']) || $result['Code'] != 0) {
            $hx_status = 0;
            $returnData = ['status' => false, 'msg' => isset($result['Msg']) ? $result['Msg'] : '未知错误'];
        }else{
            $hx_status = 1;
            $returnData = ['status' => true, 'msg' => $result['Msg']];
        }

        //修改提交核销状态
        Order::where('id', $orderInfo['id'])->update(['third_hx_status' => $hx_status, 'hx_acc_id' => $hx_acc_id, 'xl_pay_data'=>$returnData['msg']]);

        return $returnData;
    }

    /**
     * 盟收卡查单
     *
     * @param $out_trade_no
     * @return mixed|string
     */
    public function mengShouKaQuery($out_trade_no){
        $url = 'http://www.mengshouka.com/Interface/OrderInfo.aspx';
        $UserID = 12644;
        $UserKey = '7c19f62a31b52b6033fc6f0b9f5cd5e7';

        $sendData = 'UserID='.$UserID.'&UserOrderId='.$out_trade_no;
        $signStr = $sendData .'&key='.$UserKey;
        $sign = md5($signStr);

        $sendData = $sendData .'$Sign=' .$sign;
        $url = $url . '?' . $sendData;

        $res = Http::get($url);
        $result = json_decode($res, true);

        //0正在处理中，1成功，2失败 详见状态码说明
        if (isset($result['Msg'])){
            return $result['Msg'];
        }

        return '查询失败';
    }

    /**
     * 青蛙销卡平台
     *
     * @param $out_trade_no
     * @param $amount
     * @param $cardNo
     * @param $cardPwd
     * @param $orderInfo
     */
    public function qingwaXaiaoKa($out_trade_no, $amount, $cardNo, $cardPwd, $orderInfo, $hxAcc){

        $url        = 'http://api.qw918.cn/tocard.html';
        $hx_acc_id  = $hxAcc['id'];
        $notify_url = Utils::imagePath('/api/HxNotify/qwNotify', true);
        $mcode      = '';
        $custom     = time();
        $amount     = intval($amount);
        //卡密加密
        $aes     = new Aes($hxAcc['third_hx_sign_key'], 'AES-128-ECB');//作模式为ECB，填充方式为PKCS5Padding 16位密钥
        $encrypt = $aes->encrypt($cardNo);
        $encrypt = base64_decode($encrypt);
        $cardNo  = bin2hex($encrypt);

        $encrypt = $aes->encrypt($cardPwd);
        $encrypt = base64_decode($encrypt);
        $cardPwd = bin2hex($encrypt);


        $signData = [
            'customerId'   => $hxAcc['third_hx_id'],
            'timestamp'    => time(),
            'orderId'      => $out_trade_no,
            'productCode'  => $hxAcc['product_id'],
            'cardNumber'   => $cardNo,
            'cardPassword' => $cardPwd,
            'amount'       => $amount,
            'notify_url'   => $notify_url,
            'mcode'        => $mcode,
            'custom'       => $custom,
            'batchno'      => $out_trade_no,
        ];

        $sign             = Utils::sign($signData, $hxAcc['third_hx_key']);
        $signData['sign'] = $sign;
        //发送
        //Utils::notifyLog($out_trade_no, $out_trade_no, '青蛙提交签名字符串' . json_encode($signData) . '发送参数' . json_encode($signData));
        Log::write('qingwaXaiaoKa----' . json_encode($signData, JSON_UNESCAPED_UNICODE), 'thirdNotify');
        
        $header_arr = [
            'Content-Type:application/x-www-form-urlencoded',
        ];
        $options    = [
            CURLOPT_HTTPHEADER => $header_arr,
        ];

        $res = Http::post($url, $signData, $options);

        //收到
        Utils::notifyLog($out_trade_no, $out_trade_no, '青蛙收到' . $res);
        if(strstr($res,'xml') != false){
            $result = Utils::xmlToArr($res);
        }else{
            $result = json_decode($res, true);
        }
        

        if (!isset($result['code']) || $result['code'] != 1) {
            $hx_status = 0;
            $returnData = ['status' => false, 'msg' => isset($result['message']) ? $result['message'] : $result['msg']];
        }else{
            $hx_status = 1;
            $returnData = ['status' => true, 'msg' => $result['message']];
        }

        //修改提交核销状态
        Order::where('id', $orderInfo['id'])->update(['third_hx_status' => $hx_status, 'hx_acc_id' => $hx_acc_id, 'xl_pay_data'=>$returnData['msg']]);

        return $returnData;
    }

    /**
     * e查卡京东e卡
     *
     * @param $out_trade_no
     * @param $amount
     * @param $cardNo
     * @param $cardPwd
     * @param $orderInfo
     * @param $hxAcc
     * @return array
     */
    public function eChaKaByEKa($out_trade_no, $amount, $cardNo, $cardPwd, $orderInfo, $hxAcc){
        $url            = 'https://www.echak.cn/merchant/api/merSubmitOrder';
        $hx_acc_id      = $hxAcc['id'];
        $aes            = new Aes($hxAcc['third_hx_sign_key'], 'DES-EDE3');//128-12位密钥 192-24位密钥 256-32位密钥
        $encrypt        = $aes->encrypt($cardPwd);
        $encrypt        = base64_decode($encrypt);
        $cardPwdEncrypt = bin2hex($encrypt);
        $signStr        = $hxAcc['third_hx_id'] . '&' . $out_trade_no . '&' . $cardPwdEncrypt . '&' . $hxAcc['third_hx_key'];
        $sign           = md5($signStr);

        $header_arr = [
            'Content-Type:application/json',
        ];
        $options = [
            CURLOPT_HTTPHEADER => $header_arr,
        ];

        $postData = [
            'merchantId' => $hxAcc['third_hx_id'],
            'merOrderId' => $out_trade_no,
            'cardPwd'    => $cardPwdEncrypt,
            'sign'       => $sign,
        ];

        //发送
        //Utils::notifyLog($out_trade_no, $out_trade_no, 'e查卡京东提交' . json_encode($postData) . '签名字符串' . $signStr);
        Log::write('eChaKaByEKa----' . json_encode($postData, JSON_UNESCAPED_UNICODE), 'thirdNotify');

        $res    = Http::post($url, json_encode($postData), $options);
        $result = json_decode($res, true);

        //收到
        Utils::notifyLog($out_trade_no, $out_trade_no, 'e查卡提交结果' . $res);

        if (!isset($result['code']) || $result['code'] != 200) {
            $hx_status = 0;
            $returnData = ['status' => false, 'msg' => isset($result['extend']) ? $result['extend']['message'] : '未知错误'];
        }else{
            $hx_status = 1;
            $returnData = ['status' => true, 'msg' => isset($result['extend']) ? $result['extend']['message'] : '未知错误'];
        }

        //修改提交核销状态
        Order::where('id', $orderInfo['id'])->update(['third_hx_status' => $hx_status, 'hx_acc_id' => $hx_acc_id, 'xl_pay_data'=>$returnData['msg']]);

        return $returnData;

    }

    /**
     * e查卡通用卡密
     *
     * @param $out_trade_no
     * @param $amount
     * @param $cardNo
     * @param $cardPwd
     * @param $orderInfo
     * @param $hxAcc
     * @return array
     */
    public function eChaKaByCommon($out_trade_no, $amount, $cardNo, $cardPwd, $orderInfo, $hxAcc){
        $url            = 'https://www.echak.cn/merchant/api/merSubmitOrderCommon';
        $hx_acc_id      = $hxAcc['id'];
        $orderType      = $hxAcc['product_id']; //沃尔玛WALM
        $aes            = new Aes($hxAcc['third_hx_sign_key'], 'DES-EDE3');//128-12位密钥 192-24位密钥 256-32位密钥
        $encrypt        = $aes->encrypt($cardNo);
        $encrypt        = base64_decode($encrypt);
        $cardNoEncrypt  = bin2hex($encrypt); //加密后的卡号
        $encrypt        = $aes->encrypt($cardPwd);
        $encrypt        = base64_decode($encrypt);
        $cardPwdEncrypt = bin2hex($encrypt); //加密后的卡密
        $signStr        = $hxAcc['third_hx_id'] . '&' . $out_trade_no . '&' . $cardNoEncrypt . '&' . $cardPwdEncrypt . '&' . $orderType . '&' . $hxAcc['third_hx_key'];
        $sign           = md5($signStr);

        $header_arr = [
            'Content-Type:application/json',
        ];
        $options = [
            CURLOPT_HTTPHEADER => $header_arr,
        ];

        $postData = [
            'merchantId' => $hxAcc['third_hx_id'],
            'merOrderId' => $out_trade_no,
            'cardNo'     => $cardNoEncrypt,
            'cardPwd'    => $cardPwdEncrypt,
            'faceValue'  => $amount,
            'sign'       => $sign,
            'orderType'  => $orderType,
        ];

        //发送
        //Utils::notifyLog($out_trade_no, $out_trade_no, 'e查卡通用提交' . json_encode($postData) . '签名字符串' . $signStr);
        Log::write('eChaKaByCommon----' . json_encode($postData, JSON_UNESCAPED_UNICODE), 'thirdNotify');
        
        $res    = Http::post($url, json_encode($postData), $options);
        $result = json_decode($res, true);

        //收到
        Utils::notifyLog($out_trade_no, $out_trade_no, 'e查卡提交结果' . $res);

        if (!isset($result['code']) || $result['code'] != 200) {
            $hx_status = 0;
            $returnData = ['status' => false, 'msg' => isset($result['extend']) ? $result['extend']['message'] : $result['msg']];
        }else{
            $hx_status = 1;
            $returnData = ['status' => true, 'msg' => isset($result['extend']) ? $result['extend']['message'] : '未知错误'];
        }

        //修改提交核销状态
        Order::where('id', $orderInfo['id'])->update(['third_hx_status' => $hx_status, 'hx_acc_id' => $hx_acc_id, 'xl_pay_data'=>$returnData['msg']]);

        return $returnData;

    }

}