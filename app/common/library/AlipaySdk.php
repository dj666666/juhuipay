<?php

namespace app\common\library;

use think\facade\Config;
use think\facade\Db;
use think\Exception;
use think\facade\Log;
use fast\Http;
use fast\Random;
use app\common\library\AgentUtil;
use app\common\library\Rsa;
use app\common\library\Utils;
use app\common\library\MoneyLog;
use think\facade\Cache;


class AlipaySdk
{

    const ALI_PATH = __DIR__ . '/../../../extend/alipay-sdk/aop/';
    
    // 加载常量配置文件

    public $config = [];

    //获取用户信息 uid等
    public function getUserInfo($params) {

        $code     = $params['auth_code'];
        $order_id = $params['state'];

        require_once ALI_PATH . '/AopClient.php';
        require_once ALI_PATH . '/request/AlipaySystemOauthTokenRequest.php';
        $aliConfig               = Config::get('alipaysdkconf.alipay_config'); //加载配置项
        $aop                     = new \AopClient ();
        $aop->gatewayUrl         = $aliConfig['gatewayUrl'];
        $aop->appId              = $aliConfig['appId'];
        $aop->rsaPrivateKey      = $aliConfig['rsaPrivateKey'];
        $aop->alipayrsaPublicKey = $aliConfig['alipayrsaPublicKey'];
        $aop->apiVersion         = '1.0';
        $aop->signType           = 'RSA2';
        $aop->postCharset        = 'UTF-8';
        $request                 = new \AlipaySystemOauthTokenRequest ();
        $request->setGrantType("authorization_code");
        $request->setCode($code);
        $result         = $aop->execute($request);
        $responseNode   = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        $error_response = 'error_response';
        
        //这样判断和官方文档不一致是因为这里成功不反回code，返回user_id,所以我们使用user_id来判断
        if (isset($result->$responseNode->user_id)) {
            $user_id = $result->$responseNode->user_id;
            return $user_id;
        }

        $msg = $result->$error_response->code . '-' . $result->$error_response->sub_msg;
        Utils::notifyLog($order_id, 'getuid error', $msg);
        return '';

    }
    
    //获取用户信息 uid 用主体模式的
    public function getUserInfoByZhuTi($params) {

        $code     = $params['auth_code'];
        $order_id = $params['state'];
        $aid      = $params['aid'];//主体id
        
        $aliConfig = Db::name('alipay_zhuti')->where('id',$aid)->find();
        
        require_once ALI_PATH . '/AopClient.php';
        require_once ALI_PATH . '/request/AlipaySystemOauthTokenRequest.php';
        $aop                     = new \AopClient ();
        $aop->gatewayUrl         = 'https://openapi.alipay.com/gateway.do';
        $aop->appId              = $aliConfig['appid'];
        $aop->rsaPrivateKey      = $aliConfig['alipay_private_key'];
        $aop->alipayrsaPublicKey = $aliConfig['alipay_public_key'];
        $aop->apiVersion         = '1.0';
        $aop->signType           = 'RSA2';
        $aop->postCharset        = 'UTF-8';
        $request                 = new \AlipaySystemOauthTokenRequest ();
        $request->setGrantType("authorization_code");
        $request->setCode($code);
        $result         = $aop->execute($request);
        $responseNode   = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        $error_response = 'error_response';
        
        //这样判断和官方文档不一致是因为这里成功不反回code，返回user_id,所以我们使用user_id来判断
        if (isset($result->$responseNode->user_id)) {
            $user_id = $result->$responseNode->user_id;
            return $user_id;
        }

        $msg = $result->$error_response->code . '-' . $result->$error_response->sub_msg;
        Utils::notifyLog($order_id, 'getuid error', $msg);
        return '';

    }
    
    //获取用户信息 uid等
    public function getUserInfoByXcx($params) {

        $code = $params['auth_code'];

        require_once ALI_PATH . '/AopClient.php';
        require_once ALI_PATH . '/request/AlipaySystemOauthTokenRequest.php';
        $aliConfig = Db::name('alipay_zhuti')->where('id',1)->find();
        $aliConfig               = Config::get('alipaysdkconf.alipay_xcx_config'); //加载配置项
        $aop                     = new \AopClient ();
        $aop->gatewayUrl         = $aliConfig['gatewayUrl'];
        $aop->appId              = $aliConfig['appId'];
        $aop->rsaPrivateKey      = $aliConfig['rsaPrivateKey'];
        $aop->alipayrsaPublicKey = $aliConfig['alipayrsaPublicKey'];
        /*$aop->gatewayUrl         = 'https://openapi.alipay.com/gateway.do';
        $aop->appId              = $aliConfig['appid'];
        $aop->rsaPrivateKey      = $aliConfig['alipay_private_key'];
        $aop->alipayrsaPublicKey = $aliConfig['alipay_public_key'];*/
        $aop->apiVersion         = '1.0';
        $aop->signType           = 'RSA2';
        $aop->postCharset        = 'UTF-8';
        $request                 = new \AlipaySystemOauthTokenRequest ();
        $request->setGrantType("authorization_code");
        $request->setCode($code);
        $result         = $aop->execute($request);
        $responseNode   = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        $error_response = 'error_response';
        
        //这样判断和官方文档不一致是因为这里成功不反回code，返回user_id,所以我们使用user_id来判断
        if (isset($result->$responseNode->open_id)) {
            $open_id = $result->$responseNode->open_id;
            return $open_id;
        }

        $msg = $result->$error_response->code . '-' . $result->$error_response->sub_msg;
        Utils::notifyLog('111111', 'getopenid error', $msg);
        return '';

    }
    
    //获取用户信息 uid等
    public function getUserInfoByAppSq($params) {

        $app_auth_code = $params['app_auth_code'];
        $app_id        = $params['app_id'];
        $adsd          = $params['adsd'];

        require_once ALI_PATH . '/AopClient.php';
        require_once ALI_PATH . '/request/AlipaySystemOauthTokenRequest.php';

        $aliConfig = Db::name('alipay_zhuti')->where('appid', $app_id)->find();

        $aop                     = new \AopClient ();
        $aop->gatewayUrl         = 'https://openapi.alipay.com/gateway.do';
        $aop->appId              = $aliConfig['appid'];
        $aop->rsaPrivateKey      = $aliConfig['alipay_private_key'];
        $aop->alipayrsaPublicKey = $aliConfig['alipay_public_key'];
        $aop->apiVersion         = '1.0';
        $aop->signType           = 'RSA2';
        $aop->postCharset        = 'UTF-8';
        $request                 = new \AlipaySystemOauthTokenRequest ();
        $request->setGrantType("authorization_code");
        $request->setCode($app_auth_code);
        $result         = $aop->execute($request);
        $responseNode   = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        $error_response = 'error_response';

        //这样判断和官方文档不一致是因为这里成功不反回code，返回user_id,所以我们使用user_id来判断

        $resultCode = $result->$responseNode->code;
        if (!empty($resultCode) && $resultCode == 10000) {

            $appAuthToken = $result->alipay_open_auth_token_app_response->app_auth_token;
            $authAppId    = $result->alipay_open_auth_token_app_response->auth_app_id;
            $userId       = $result->alipay_open_auth_token_app_response->user_id;

            $dataCache = [
                'app_auth_token' => $appAuthToken,
                'auth_app_id'    => $authAppId,
                'user_id'        => $userId,
            ];

            Cache::set($adsd, $dataCache, 3600);
        }

    }
    
    //获取用户信息 uid 用主体模式的
    public function getUserInfoByYs($params) {

        $code     = $params['auth_code'];
        $aid      = $params['aid'];//收款码id
        
        $findQrcode = Db::name('group_qrcode')->where('id',$aid)->find();
        
        require_once ALI_PATH . '/AopClient.php';
        require_once ALI_PATH . '/request/AlipaySystemOauthTokenRequest.php';
        $aop                     = new \AopClient ();
        $aop->gatewayUrl         = 'https://openapi.alipay.com/gateway.do';
        $aop->appId              = $findQrcode['name'];
        $aop->rsaPrivateKey      = $findQrcode['cookie'];
        $aop->alipayrsaPublicKey = $findQrcode['xl_cookie'];
        $aop->apiVersion         = '1.0';
        $aop->signType           = 'RSA2';
        $aop->postCharset        = 'UTF-8';
        $request                 = new \AlipaySystemOauthTokenRequest();
        $request->setGrantType("authorization_code");
        $request->setCode($code);
        $result         = $aop->execute($request);
        $responseNode   = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        $error_response = 'error_response';
        
        //这样判断和官方文档不一致是因为这里成功不反回code，返回user_id,所以我们使用user_id来判断
        if (isset($result->$responseNode->user_id)) {
            $user_id = $result->$responseNode->user_id;
            return $user_id;
        }
        
        $msg = $result->$error_response->code . '-' . $result->$error_response->sub_msg;
        return '';

    }
    
    //发起支付宝app支付
    public function alipayAppPay($out_trade_no, $amount, $qrcode, $zhuti) {

        require_once ALI_PATH . '/AlipayConfig.php';
        require_once ALI_PATH . '/AopClient.php';
        require_once ALI_PATH . '/request/AlipayTradeAppPayRequest.php';

        $app_auth_token = $qrcode['app_auth_token'];

        $privateKey      = $zhuti['alipay_private_key']; //私钥
        $alipayPublicKey = $zhuti['alipay_public_key']; //公钥

        $alipayConfig = new \AlipayConfig();
        $alipayConfig->setServerUrl("https://openapi.alipay.com/gateway.do"); //网关
        $alipayConfig->setAppId("{$zhuti['appid']}");

        $alipayConfig->setPrivateKey($privateKey);
        $alipayConfig->setFormat("json");
        $alipayConfig->setAlipayPublicKey($alipayPublicKey);
        $alipayConfig->setCharset("UTF8");
        $alipayConfig->setSignType("RSA2");
        $alipayClient = new \AopClient($alipayConfig);
        $request      = new \AlipayTradeAppPayRequest();
        $request->setBizContent("{" .
            "\"total_amount\":\"$amount\"," .
            "\"out_trade_no\":\"$out_trade_no\"," .
            "\"subject\":\"商品购买\"" .
            "}");
        $responseResult = $alipayClient->sdkExecute($request, $app_auth_token);
        $decodedResult  = htmlspecialchars_decode($responseResult);

        return $decodedResult;
    }

    //发起支付宝pc支付
    public function alipayPcPay($out_trade_no, $amount, $qrcode, $zhuti) {

        require_once ALI_PATH . '/AopClient.php';
        require_once ALI_PATH . '/request/AlipayTradePagePayRequest.php';

        $app_auth_token = $qrcode['app_auth_token'];

        $privateKey      = $zhuti['alipay_private_key']; //应用私钥
        $alipayPublicKey = $zhuti['alipay_public_key']; //支付宝公钥

        $aop = new \AopClient();

        $aop->gatewayUrl         = 'https://openapi.alipay.com/gateway.do';
        $aop->appId              = $zhuti['appid'];
        $aop->rsaPrivateKey      = $privateKey;
        $aop->alipayrsaPublicKey = $alipayPublicKey;
        $aop->apiVersion         = '1.0';
        $aop->signType           = 'RSA2';
        $aop->postCharset        = 'UTF-8';
        $aop->format             = 'json';

        $request = new \AlipayTradePagePayRequest();

        // 订单数据
        $bizContent = [
            'out_trade_no' => $out_trade_no,
            'total_amount' => $amount,
            'subject'      => '商品购买',
            'body'         => '商品购买',
            'product_code' => 'FAST_INSTANT_TRADE_PAY',
            'qr_pay_mode'  => '1',
        ];
        $request->setBizContent(json_encode($bizContent));

        $responseResult = $aop->pageExecute($request, 'GET', $app_auth_token);

        $decodedResult  = htmlspecialchars_decode($responseResult);
        //$decodedResult = $responseResult;

        return $decodedResult;
    }

    //发起支付宝当面付支付
    public function alipayDmfPay($out_trade_no, $amount, $qrcode, $zhuti, $zfb_user_id) {

        require_once ALI_PATH . '/AopClient.php';
        require_once ALI_PATH . '/request/AlipayTradeCreateRequest.php';

        $app_auth_token = $qrcode['app_auth_token'];

        $privateKey      = $zhuti['alipay_private_key']; //应用私钥
        $alipayPublicKey = $zhuti['alipay_public_key']; //支付宝公钥

        $aop = new \AopClient();

        $aop->gatewayUrl         = 'https://openapi.alipay.com/gateway.do';
        $aop->appId              = $zhuti['appid'];
        $aop->rsaPrivateKey      = $privateKey;
        $aop->alipayrsaPublicKey = $alipayPublicKey;
        $aop->apiVersion         = '1.0';
        $aop->signType           = 'RSA2';
        $aop->postCharset        = 'UTF-8';
        $aop->format             = 'json';

        $request = new \AlipayTradeCreateRequest();

        // 订单数据
        $bizContent = [
            'out_trade_no'     => $out_trade_no,
            'total_amount'     => $amount,
            'subject'          => '商品购买',
            'buyer_id'         => $zfb_user_id,
            'timeout_express ' => '10m',
        ];
        $request->setBizContent(json_encode($bizContent));
        
        $responseResult = $aop->execute($request, null, $app_auth_token);
        
        $responseNode   = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        
        if($responseResult->$responseNode->code != '10000'){
            
            Log::write('alipayDmfPay----'.json_encode($responseResult, JSON_UNESCAPED_UNICODE),'info');
            return false;
        }
        return $responseResult->$responseNode->trade_no;
    }

    //发起支付宝手机网站支付
    public function alipayWapPay($out_trade_no, $amount, $qrcode, $zhuti, $time_expire) {

        require_once ALI_PATH . '/AopClient.php';
        require_once ALI_PATH . '/request/AlipayTradeWapPayRequest.php';

        $app_auth_token = $qrcode['app_auth_token'];

        $privateKey      = $zhuti['alipay_private_key']; //应用私钥
        $alipayPublicKey = $zhuti['alipay_public_key']; //支付宝公钥

        $aop = new \AopClient();

        $aop->gatewayUrl         = 'https://openapi.alipay.com/gateway.do';
        $aop->appId              = $zhuti['appid'];
        $aop->rsaPrivateKey      = $privateKey;
        $aop->alipayrsaPublicKey = $alipayPublicKey;
        $aop->apiVersion         = '1.0';
        $aop->signType           = 'RSA2';
        $aop->postCharset        = 'UTF-8';
        $aop->format             = 'json';

        $request = new \AlipayTradeWapPayRequest();

        // 订单数据
        $bizContent = [
            'out_trade_no'  => $out_trade_no,
            'total_amount'  => $amount,
            'subject'       => '商品购买',
            'product_code ' => 'QUICK_WAP_WAY',
            'time_expire '  => $time_expire,
        ];
        $request->setBizContent(json_encode($bizContent));

        $responseResult = $aop->pageExecute($request, 'POST', $app_auth_token);

        //$decodedResult  = htmlspecialchars_decode($responseResult);
        $decodedResult = $responseResult;

        return $decodedResult;
    }
    
    //发起支付宝小程序支付
    public function alipayJsApiPay($out_trade_no, $amount, $qrcode, $zhuti, $zfb_user_id) {

        require_once ALI_PATH . '/AopClient.php';
        require_once ALI_PATH . '/request/AlipayTradeCreateRequest.php';

        $app_auth_token = $qrcode['app_auth_token'];

        $privateKey      = $zhuti['alipay_private_key']; //应用私钥
        $alipayPublicKey = $zhuti['alipay_public_key']; //支付宝公钥

        $aop = new \AopClient();
        
        $aop->gatewayUrl         = 'https://openapi.alipay.com/gateway.do';
        $aop->appId              = $zhuti['appid'];
        $aop->rsaPrivateKey      = $privateKey;
        $aop->alipayrsaPublicKey = $alipayPublicKey;
        $aop->apiVersion         = '1.0';
        $aop->signType           = 'RSA2';
        $aop->postCharset        = 'UTF-8';
        $aop->format             = 'json';

        $request = new \AlipayTradeCreateRequest();
        
        // 订单数据
        $bizContent = [
            'out_trade_no'  => $out_trade_no,
            'total_amount'  => $amount,
            'subject'       => '商品购买',
            'seller_id'     => $qrcode['zfb_pid'],//收款账户id 使用轮询收款账户pid
            'buyer_id'      => $zfb_user_id,//买家pid 小程序获取后传回来
        ];
        
        $request->setBizContent(json_encode($bizContent));
        
        $responseResult = $aop->execute($request, 'POST', $app_auth_token);
        
        $responseNode   = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        
        if($responseResult->$responseNode->code != '10000'){
            
            Log::write('alipayJsApiPay----'.json_encode($responseResult, JSON_UNESCAPED_UNICODE),'info');halt($responseResult);
            return false;
        }
        return $responseResult;
    }
    
    //发起支付宝手机网站支付 官方原生
    public function alipayWapPayByYs($out_trade_no, $amount, $aliconf, $time_expire, $notify_url, $return_url) {

        require_once ALI_PATH . '/AopClient.php';
        require_once ALI_PATH . '/request/AlipayTradeWapPayRequest.php';
        
        $privateKey      = $aliconf['alipay_private_key']; //应用私钥
        $alipayPublicKey = $aliconf['alipay_public_key']; //支付宝公钥
        
        $aop = new \AopClient();

        $aop->gatewayUrl         = 'https://openapi.alipay.com/gateway.do';
        $aop->appId              = $aliconf['appid'];
        $aop->rsaPrivateKey      = $privateKey;
        $aop->alipayrsaPublicKey = $alipayPublicKey;
        $aop->apiVersion         = '1.0';
        $aop->signType           = 'RSA2';
        $aop->postCharset        = 'UTF-8';
        $aop->format             = 'json';

        $request = new \AlipayTradeWapPayRequest();

        // 订单数据
        $bizContent = [
            'out_trade_no'    => $out_trade_no,
            'total_amount'    => $amount,
            'subject'         => '袜子',
            'product_code'    => 'QUICK_WAP_WAY',
        ];
        
        $request->setBizContent(json_encode($bizContent));
        //异步接收地址，仅支持http/https，公网可访问
        $request->setNotifyUrl($notify_url);
        //同步跳转地址，仅支持http/https
        $request->setReturnUrl($return_url);

        $responseResult = $aop->pageExecute($request, 'POST');

        //$decodedResult  = htmlspecialchars_decode($responseResult);
        $decodedResult = $responseResult;

        return $decodedResult;
    }
    
    //发起支付宝当面付支付 官方原生
    public function alipayDmfPayByYs($out_trade_no, $amount, $aliconf, $buyer_user_id, $notify_url, $return_url, $is_fz = false) {

        require_once ALI_PATH . '/AopClient.php';
        require_once ALI_PATH . '/request/AlipayTradeCreateRequest.php';

        $privateKey      = $aliconf['alipay_private_key']; //应用私钥
        $alipayPublicKey = $aliconf['alipay_public_key']; //支付宝公钥

        $aop = new \AopClient();

        $aop->gatewayUrl         = 'https://openapi.alipay.com/gateway.do';
        $aop->appId              = $aliconf['appid'];
        $aop->rsaPrivateKey      = $privateKey;
        $aop->alipayrsaPublicKey = $alipayPublicKey;
        $aop->apiVersion         = '1.0';
        $aop->signType           = 'RSA2';
        $aop->postCharset        = 'UTF-8';
        $aop->format             = 'json';

        $request = new \AlipayTradeCreateRequest();

        // 订单数据
        $bizContent = [
            'out_trade_no'     => $out_trade_no,
            'total_amount'     => $amount,
            'subject'          => '商品购买',
            'buyer_id'         => $buyer_user_id,
            'timeout_express ' => '10m',
        ];
        
        if($is_fz){
            // //结算信息，按需传入
            $settleInfo = [
                'royalty_type'=> 'ROYALTY',
                'settle_detail_infos'=>[
                    [
                        'batch_no'=>$out_trade_no,
                        'trans_out_type'=>'userId',
                        'trans_out'=> 'userId',//主账号uid
                        'trans_in'=> 'userId',//子账号uid
                        'amount'=>0.01,
                    ]
                ]
            ];
            $bizContent['settle_info'] = $settleInfo;

        }
        
        
        $request->setBizContent(json_encode($bizContent));
        //异步接收地址，仅支持http/https，公网可访问
        $request->setNotifyUrl($notify_url);
        //同步跳转地址，仅支持http/https
        $request->setReturnUrl($return_url);
        
        
        
        $responseResult = $aop->execute($request);
        
        $responseNode   = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        
        if($responseResult->$responseNode->code != '10000'){
            
            Log::write('当面付----'.json_encode($responseResult, JSON_UNESCAPED_UNICODE),'info');
            return false;
        }
        return $responseResult->$responseNode->trade_no;
    }
    
    //订单码支付
    public function alipayDdmPay($out_trade_no, $amount, $qrcode, $zhuti, $notify_url, $return_url) {
        
        require_once ALI_PATH . '/AopClient.php';
        require_once ALI_PATH . '/request/AlipayTradePrecreateRequest.php';
        
        $app_auth_token  = $qrcode['app_auth_token'];
        
        $appid           = $zhuti['appid'];              //应用私钥
        $privateKey      = $zhuti['alipay_private_key']; //应用私钥
        $alipayPublicKey = $zhuti['alipay_public_key'];  //支付宝公钥
        
        $aop     = new \AopClient();
        $request = new \AlipayTradePrecreateRequest();
        
        $aop->gatewayUrl         = 'https://openapi.alipay.com/gateway.do';
        $aop->appId              = $appid;
        $aop->rsaPrivateKey      = $privateKey;
        $aop->alipayrsaPublicKey = $alipayPublicKey;
        $aop->apiVersion         = '1.0';
        $aop->signType           = 'RSA2';
        $aop->postCharset        = 'UTF-8';
        $aop->format             = 'json';

        $model = [];
        
        // 设置订单标题
        $model['subject'] = "大礼包".$amount.'元';
        
        // 设置产品码
        $model['product_code'] = "QR_CODE_OFFLINE";
        
        // 设置商户订单号
        $model['out_trade_no'] = $out_trade_no;
        
        // 设置订单总金额
        $model['total_amount'] = $amount;
        
        // 订单数据
        $bizContent = $model;
        $request->setBizContent(json_encode($bizContent));
        
        //异步接收地址，仅支持http/https，公网可访问
        $request->setNotifyUrl($notify_url);
        //同步跳转地址，仅支持http/https
        $request->setReturnUrl($return_url);
        
        if($qrcode['type'] == '1'){
            $responseResult = $aop->execute($request);
        }else{
            $responseResult = $aop->execute($request, null, $app_auth_token);
        }
        
        $responseNode   = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        
        if($responseResult->$responseNode->code != '10000'){
            
            return $responseResult->$responseNode->sub_msg;
        }
        
        $decodedResult = json_encode($responseResult);
        
        return $decodedResult;
    }
    
    //支付宝主体模式查单
    public function alipayCheckOrder($out_trade_no, $amount, $app_auth_token, $zhuti) {

        require_once __DIR__ . '/../../../extend/alipay-sdk/aop/AopClient.php';
        require_once __DIR__ . '/../../../extend/alipay-sdk/aop/request/AlipayTradeQueryRequest.php';

        $aop                     = new \AopClient ();
        $aop->gatewayUrl         = 'https://openapi.alipay.com/gateway.do';
        $aop->appId              = $zhuti['appid'];
        $aop->rsaPrivateKey      = $zhuti['alipay_private_key']; //私钥
        $aop->alipayrsaPublicKey = $zhuti['alipay_public_key']; //公钥
        $aop->apiVersion         = '1.0';
        $aop->signType           = 'RSA2';
        $aop->postCharset        = 'UTF-8';
        $aop->format             = 'json';
        $request                 = new \AlipayTradeQueryRequest ();
        $request->setBizContent("{" .
            "\"out_trade_no\":\"$out_trade_no\"" .
            "}");

        $result = $aop->execute($request, null, $app_auth_token);
        
        $ali_out_trade_no = '';
        $total_amount     = '';
        $trade_status     = '';
        $msg              = '';
        $trade_no         = '';

        if (isset($result->alipay_trade_query_response)) {
            $response         = $result->alipay_trade_query_response;//halt($response);
            $ali_out_trade_no = isset($response->out_trade_no) ? $response->out_trade_no : 'Not Provided';
            $total_amount     = isset($response->total_amount) ? $response->total_amount : 'Not Provided';
            $trade_status     = isset($response->trade_status) ? $response->trade_status : 'Not Provided';
            $trade_no         = isset($response->trade_no) ? $response->trade_no : 'Not Provided';

            if (isset($response->sub_msg)) {
                $msg = $response->sub_msg;
            } elseif (isset($response->msg)) {

                $msg = $response->msg;
            } else {
                $msg = 'Not Provided';
            }

            if ($trade_status == 'WAIT_BUYER_PAY') {
                $trade_status = '等待用户支付';
            }
        }

        $res = [
            'trade_status'     => $trade_status,
            'ali_out_trade_no' => $ali_out_trade_no,
            'total_amount'     => $total_amount,
            'msg'              => $msg,
            'trade_no'         => $trade_no
        ];

        return $res;
    }

    //支付宝主体模式查余额
    public function alipayQueryBalance($zhuti, $qrcode) {

        require_once __DIR__ . '/../../../extend/alipay-sdk/aop/AopClient.php';
        require_once __DIR__ . '/../../../extend/alipay-sdk/aop/request/AlipayTradeQueryRequest.php';
        require_once __DIR__ . '/../../../extend/alipay-sdk/aop/request/AlipayFundAccountQueryRequest.php';
        require_once __DIR__ . '/../../../extend/alipay-sdk/aop/request/AlipayDataBillBalanceQueryRequest.php';
        
        $aop                     = new \AopClient ();
        $aop->gatewayUrl         = 'https://openapi.alipay.com/gateway.do';
        $aop->appId              = $zhuti['appid'];
        $aop->rsaPrivateKey      = $zhuti['alipay_private_key']; //私钥
        $aop->alipayrsaPublicKey = $zhuti['alipay_public_key']; //公钥
        $aop->apiVersion         = '1.0';
        $aop->signType           = 'RSA2';
        $aop->postCharset        = 'UTF-8';
        $aop->format             = 'json';
        $request                 = new \AlipayDataBillBalanceQueryRequest();
        
        $account_type   = "ACCTRANS_ACCOUNT";
        $zfb_pid        = $qrcode['zfb_pid'];
        $app_auth_token = $qrcode['app_auth_token'];
        
        
        $bizContent = [
            'bill_user_id' => $zfb_pid,
        ];
        $request->setBizContent(json_encode($bizContent));
        
        
        if($qrcode['type'] == '1'){
            $result = $aop->execute($request);
        }else{
            $result = $aop->execute($request, null, $app_auth_token);
        }
        
        if (isset($result->alipay_data_bill_balance_query_response)) {
            $response          = $result->alipay_data_bill_balance_query_response;
            if($response->code == '10000'){
                $available_amount = isset($response->available_amount) ? $response->available_amount : '查询错误';
            }else{
                $available_amount = $response->sub_msg;
            }
            
        }else{
            $available_amount = '查询失败';
        }
        
        return $available_amount;
    }
    
    
    //支付宝主体模式查单 个码h5模式
    public function alipayGmCheckOrder($order, $app_auth_token, $zhuti, $bill_user_id) {

        require_once __DIR__ . '/../../../extend/alipay-sdk/aop/AopClient.php';
        require_once __DIR__ . '/../../../extend/alipay-sdk/aop/request/AlipayDataBillAccountlogQueryRequest.php';
        
        $start_time = date("Y-m-d") . ' 00:00:00';
        $end_time   = date('Y-m-d') . ' 23:59:59';
        
        $aop                     = new \AopClient ();
        $aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
        $aop->appId              = $zhuti['appid'];
        $aop->rsaPrivateKey      = $zhuti['alipay_private_key']; //私钥
        $aop->alipayrsaPublicKey = $zhuti['alipay_public_key']; //公钥
        $aop->apiVersion         = '1.0';
        $aop->signType           = 'RSA2';
        $aop->postCharset        ='UTF-8';
        $aop->format             ='json';
        $request                 = new \AlipayDataBillAccountlogQueryRequest();
        $request->setBizContent("{" .
            "  \"start_time\":\"$start_time\"," .
            "  \"end_time\":\"$end_time\"," .
            "  \"bill_user_id\":\"$bill_user_id\"" .
            "}"
        );
        
        $result = $aop->execute($request, null, $app_auth_token);
        
        $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        $resultCode   = $result->$responseNode->code;
        $resultMsg    = isset($result->$responseNode->sub_msg) ? $result->$responseNode->sub_msg : $result->$responseNode->msg;
        
        if(empty($resultCode) || $resultCode != 10000){
            Log::write('个码查单失败----'.json_encode($result, JSON_UNESCAPED_UNICODE),'info');
            $res_data = ['status' => false, 'msg' => $resultMsg , 'data' => ''];
            return $res_data;
        }
        
        if(!isset($result->$responseNode->detail_list)){
            $res_data = ['status' => false, 'msg' => '无订单数据' , 'data' => ''];
            return $res_data;
        }
        
        
        $detail_list = $result->$responseNode->detail_list;
        $res_data    = ['status' => true, 'msg' => $resultMsg , 'data' => $detail_list];
        return $res_data;
    }
    
    //支付宝主体模式查单 原生拉单接口回调的 0扫码授权 1基础授权
    public function alipayCheckYsOrder($out_trade_no, $amount, $qrcode, $zhuti) {
        
        require_once self::ALI_PATH . 'AopClient.php';
        require_once self::ALI_PATH . 'request/AlipayTradeQueryRequest.php';
        
        $app_auth_token  = $qrcode['app_auth_token'];
        
        $appid           = $zhuti['appid'];              //应用私钥
        $privateKey      = $zhuti['alipay_private_key']; //应用私钥
        $alipayPublicKey = $zhuti['alipay_public_key'];  //支付宝公钥
        
        $aop                     = new \AopClient ();
        $aop->gatewayUrl         = 'https://openapi.alipay.com/gateway.do';
        $aop->appId              = $appid;
        $aop->rsaPrivateKey      = $privateKey; //私钥
        $aop->alipayrsaPublicKey = $alipayPublicKey; //公钥
        $aop->apiVersion         = '1.0';
        $aop->signType           = 'RSA2';
        $aop->postCharset        = 'UTF-8';
        $aop->format             = 'json';
        $request                 = new \AlipayTradeQueryRequest ();
        $request->setBizContent("{" .
            "\"out_trade_no\":\"$out_trade_no\"" .
            "}");
        
        if($qrcode['type'] == '1'){
            $responseResult  = $aop->execute($request);
        }else{
            $responseResult  = $aop->execute($request, null, $app_auth_token);
        }
        
        $responseApiName = str_replace(".","_",$request->getApiMethodName())."_response";
        $response        = $responseResult->$responseApiName;
        
        if($response->code != 10000){
            return ['msg' => $response->sub_msg, 'status' => false];
        }
        
        
        
        $msg              = '查询成功';
        $response         = $responseResult ->alipay_trade_query_response;
        $ali_out_trade_no = isset($response->out_trade_no) ? $response->out_trade_no : 'Not Provided';
        $total_amount     = isset($response->total_amount) ? $response->total_amount : 'Not Provided';
        $trade_status     = isset($response->trade_status) ? $response->trade_status : 'Not Provided';
        $trade_no         = isset($response->trade_no) ? $response->trade_no : 'Not Provided';
        $send_pay_date    = isset($response->send_pay_date) ? $response->send_pay_date : 'Not Provided';
        
        if ($trade_status == 'WAIT_BUYER_PAY') {
            return ['msg' => '等待用户支付', 'status' => false];
        }
        
        $res = [
            'trade_status'     => $trade_status,
            'ali_out_trade_no' => $ali_out_trade_no,
            'total_amount'     => $total_amount,
            'msg'              => $msg,
            'trade_no'         => $trade_no,
            'send_pay_date'    => $send_pay_date,
            'status'           => true,
        ];

        return $res;
    }

    //支付宝基础授权模式查单 个码h5模式
    public function alipayGmCheckOrderByPublicKey($order, $alipayConfig, $bill_user_id) {

        require_once __DIR__ . '/../../../extend/alipay-sdk/aop/AopClient.php';
        require_once __DIR__ . '/../../../extend/alipay-sdk/aop/request/AlipayDataBillAccountlogQueryRequest.php';

        $start_time = date("Y-m-d") . ' 00:00:00';
        $end_time   = date('Y-m-d') . ' 23:59:59';

        $aop                     = new \AopClient ();
        $aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
        $aop->appId              = $alipayConfig['appid'];
        $aop->rsaPrivateKey      = $alipayConfig['private_key']; //私钥
        $aop->alipayrsaPublicKey = $alipayConfig['public_key']; //公钥
        $aop->apiVersion         = '1.0';
        $aop->signType           = 'RSA2';
        $aop->postCharset        ='UTF-8';
        $aop->format             ='json';
        $request                 = new \AlipayDataBillAccountlogQueryRequest();
        $request->setBizContent("{" .
            "  \"start_time\":\"$start_time\"," .
            "  \"end_time\":\"$end_time\"," .
            "  \"bill_user_id\":\"$bill_user_id\"" .
            "}"
        );

        $result = $aop->execute($request);
        
        $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        $resultCode   = $result->$responseNode->code;
        $resultMsg   = $result->$responseNode->msg;

        if(empty($resultCode) || $resultCode != 10000){
            Log::write('个码查单失败----'.json_encode($result, JSON_UNESCAPED_UNICODE),'info');
            $res_data = ['status' => false, 'msg' => $resultMsg , 'data' => ''];
            return $res_data;
        }

        if(!isset($result->$responseNode->detail_list)){
            $res_data = ['status' => false, 'msg' => '无订单数据' , 'data' => ''];
            return $res_data;
        }


        $detail_list = $result->$responseNode->detail_list;
        $res_data    = ['status' => true, 'msg' => $resultMsg , 'data' => $detail_list];
        return $res_data;
    }


    //支付宝主体模式 退款
    public function alipayOrderRefund($trade_no, $amount, $app_auth_token, $zhuti) {
        
        require_once ALI_PATH . '/AopClient.php';
        require_once ALI_PATH . '/request/AlipayTradeRefundRequest.php';
        $refund_reason           = '正常退款';
        $aop                     = new \AopClient ();
        $aop->gatewayUrl         = 'https://openapi.alipay.com/gateway.do';
        $aop->appId              = $zhuti['appid'];
        $aop->rsaPrivateKey      = $zhuti['alipay_private_key']; //私钥
        $aop->alipayrsaPublicKey = $zhuti['alipay_public_key']; //公钥
        $aop->apiVersion         = '1.0';
        $aop->signType           = 'RSA2';
        $aop->postCharset        = 'UTF-8';
        $aop->format             = 'json';
        $request                 = new \AlipayTradeRefundRequest();
        
        // 订单数据
        $bizContent = [
            'trade_no'      => $trade_no,//支付宝的交易单号
            'refund_amount' => $amount,
            'refund_reason' => '正常退款',
        ];
        $request->setBizContent(json_encode($bizContent));
        
        
        $responseResult  = $aop->execute($request, null, $app_auth_token);
        $responseApiName = str_replace(".","_",$request->getApiMethodName())."_response";
        $response        = $responseResult->$responseApiName;
        
        if($response->code != 10000){
            return ['msg' => $response->sub_msg, 'status' => false];
        }
        
        $decodedResult_str = json_encode($response);
        Log::write('退款----'.json_encode($response, JSON_UNESCAPED_UNICODE),'waring');
        
        //fund_change=Y为退款成功，fund_change=N或无此字段值返回时需通过退款查询接口进一步确认退款状态
        
        $msg            = isset($response->msg) ? $response->msg : 'Not Provided';
        $fund_change    = isset($response->fund_change) ? $response->fund_change : 'Not Provided';
        $trade_no       = isset($response->trade_no) ? $response->trade_no : 'Not Provided';
        $buyer_logon_id = isset($response->buyer_logon_id) ? $response->buyer_logon_id : 'Not Provided';//卖家账号
        $send_back_fee  = isset($response->send_back_fee) ? $response->send_back_fee : 'Not Provided';//实际退款金额
        
        if ($fund_change != 'Y') {
            return ['msg' => '退款失败|'. $msg, 'status' => false];
        }
        
        if($fund_change == 'Y'){
            $msg = '退款成功|金额：'.$send_back_fee.'|买家:' . $buyer_logon_id;
            return ['msg' => $msg, 'status' => true];
        }else{
            return ['msg' => '退款失败！|'. $msg, 'status' => false];
        }
        
        
        return $res;
    }
}