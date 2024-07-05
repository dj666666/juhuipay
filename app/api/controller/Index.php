<?php

namespace app\api\controller;

use app\admin\model\order\Order;
use app\common\controller\Api;
use app\common\library\AlipaySdk;
use app\common\library\Utils;
use think\facade\Config;
use think\facade\Db;
use think\facade\Cache;
use think\facade\Log;
use app\common\library\Accutils;

/**
 * 首页接口.
 */
class Index extends Api
{
    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];

    /**
     * 首页.
     */
    public function index()
    {
        $this->success('请求成功');
    }
    
    public function paysuccess(){
        return view('gateway/success');
    }
    
    
    //获取uid
    public function toAlipay(){
        $order_id = $this->request->get('order_id');
        
        $app_id   = '2021004109681524';
        $scope    = "auth_base";//用户信息授权，仅仅用于静默获取用户支付宝的uid
        //$scope    = "auth_user";//获取用户信息、网站支付宝登录
        $callback = urlencode(Utils::imagePath('/api/index/aliCallback', true));
        $url      = "https://openauth.alipay.com/oauth2/publicAppAuthorize.htm?app_id=$app_id&scope=$scope&redirect_uri=$callback&state=$order_id";

        header("location:$url");

    }
    
    public function aliCallback(){

        $order_id  = $this->request->get('state');
        $auth_code = $this->request->get('auth_code');//auth_code
        $params    = $this->request->get();//code
        Log::write(json_encode($params, JSON_UNESCAPED_UNICODE), 'info');

        $pay     = new AlipaySdk();
        $user_id = $pay->getUserInfo($params);
        if (!empty($user_id)) {

            Order::where(['out_trade_no' => $order_id])->update(['zfb_user_id' => $user_id]);

            $error_data = [
                'out_trade_no' => $order_id,
                'trade_no'     => $order_id,
                'msg'          => $order_id,
                'content'      => $order_id,
            ];

            //判断是否加入黑名单
            $check_black_res = Utils::checkBlackUid($user_id);
            if ($check_black_res) {
                $error_data['msg']  = $user_id.'-uid已拉黑';
                event('OrderError', $error_data);
                Order::where(['out_trade_no' => $order_id])->update(['remark' => 'uid已拉黑']);
                $this->error('支付失败');
            }

            //否则则跳我自己的支付地址
            $pay_url = Utils::imagePath('/api/gateway/order/' . $order_id, true);
            header("Location:" . $pay_url);
        }

        $this->error('失败，请重试');
    }
    
    //获取uid
    public function toAlipayV2(){
        
        $order_id = $this->request->get('order_id');
        $qid      = $this->request->get('qid');
        
        $findQrcode   = Db::name('group_qrcode')->where('id',$qid)->find();
        $alipayConfig = Db::name('alipay_zhuti')->where('id',$findQrcode['zhuti_id'])->find();
        
        
        $app_id   = $alipayConfig['appid'];
        
        $scope    = "auth_base";//用户信息授权，仅仅用于静默获取用户支付宝的uid
        //$scope    = "auth_user";//获取用户信息、网站支付宝登录
        $callback = urlencode(Utils::imagePath('/api/index/aliCallbackV2', true));
        $callback = $callback .'?aid='.$alipayConfig['id'];
        $url      = "https://openauth.alipay.com/oauth2/publicAppAuthorize.htm?app_id=$app_id&scope=$scope&redirect_uri=$callback&state=$order_id";
        
        header("location:$url");

    }
    
    public function aliCallbackV2(){
        
        $aid       = $this->request->get('aid');//主体id
        $order_id  = $this->request->get('state');
        $auth_code = $this->request->get('auth_code');//auth_code
        $params    = $this->request->get();//code
        
        if(empty($aid) || empty($auth_code) || empty($order_id)){
            $this->error('参数缺少');
        }
        
        Log::write('aliCallbackV2----'.json_encode($params, JSON_UNESCAPED_UNICODE), 'info');
        try {
            $pay     = new AlipaySdk();
            $user_id = $pay->getUserInfoByZhuTi($params);
            if (!empty($user_id)) {
    
                Order::where(['out_trade_no' => $order_id])->update(['zfb_user_id' => $user_id]);
    
                $error_data = [
                    'out_trade_no' => $order_id,
                    'trade_no'     => $order_id,
                    'msg'          => $order_id,
                    'content'      => $order_id,
                ];
    
                //判断是否加入黑名单
                $check_black_res = Utils::checkBlackUid($user_id);
                if ($check_black_res) {
                    $error_data['msg']  = $user_id.'-uid已拉黑';
                    event('OrderError', $error_data);
                    Order::where(['out_trade_no' => $order_id])->update(['remark' => 'uid已拉黑']);
                    $this->error('支付失败');
                }
    
                //否则则跳我自己的支付地址
                $pay_url = Utils::imagePath('/api/gateway/order/' . $order_id, true);
                header("Location:" . $pay_url);
            }
            
            $this->error('失败，请重试');
        
        } catch (Exception $e) {
            
            Log::write('aliCallbackV2----'.$e->getFile().'----'. $e->getLine() .'----' .$e->getMessage(),'info');
            $this->error('处理失败');
        }
    }
    
    //原生码挂收款码那的 获取uid
    public function toAlipayV3(){
        
        $order_id = $this->request->get('order_id');
        $qid      = $this->request->get('qid');
        
        $findQrcode = Db::name('group_qrcode')->where('id',$qid)->find();
        
        $app_id   = $findQrcode['name'];
        
        $scope    = "auth_base";//用户信息授权，仅仅用于静默获取用户支付宝的uid
        //$scope    = "auth_user";//获取用户信息、网站支付宝登录
        $callback = urlencode(Utils::imagePath('/api/index/aliCallbackV3', true));
        $callback = $callback .'?aid='.$qid;
        $url      = "https://openauth.alipay.com/oauth2/publicAppAuthorize.htm?app_id=$app_id&scope=$scope&redirect_uri=$callback&state=$order_id";
        
        header("location:$url");

    }
    
    public function aliCallbackV3(){
        
        $aid       = $this->request->param('aid');//收款码id
        $order_id  = $this->request->param('state');
        $auth_code = $this->request->param('auth_code');//auth_code
        $params    = $this->request->param();//code
        
        if(empty($aid) || empty($auth_code) || empty($order_id)){
            $this->error('参数缺少');
        }
        
        Log::write('阿里回调v3----'.json_encode($params, JSON_UNESCAPED_UNICODE), 'info');
        
            $pay     = new AlipaySdk();
            $user_id = $pay->getUserInfoByYs($params);
            if (!empty($user_id)) {
    
                Order::where(['out_trade_no' => $order_id])->update(['zfb_user_id' => $user_id]);
    
                $error_data = [
                    'out_trade_no' => $order_id,
                    'trade_no'     => $order_id,
                    'msg'          => $order_id,
                    'content'      => $order_id,
                ];
    
                //判断是否加入黑名单
                $check_black_res = Utils::checkBlackUid($user_id);
                if ($check_black_res) {
                    $error_data['msg']  = $user_id.'-uid已拉黑';
                    event('OrderError', $error_data);
                    Order::where(['out_trade_no' => $order_id])->update(['remark' => 'uid已拉黑']);
                    $this->error('支付失败');
                }
    
                //否则则跳我自己的支付地址
                $pay_url = Utils::imagePath('/api/gateway/order/' . $order_id, true);
                header("Location:" . $pay_url);
            }
            
            $this->error('失败，请重试');
        
        /*try {} catch (Exception $e) {
            
            Log::write('回调v3----'.$e->getFile().'----'. $e->getLine() .'----' .$e->getMessage(),'info');
            $this->error('处理失败');
        }*/
    }
    
    //小程序授权 传authcode换取uid
    public function authchange(){
        
        $auth_code = $this->request->post('auth_code');//auth_code
        $order_no  = $this->request->post('order_no');//系统订单号
        $params    = $this->request->post();//
        
        Log::write('authchange----'.json_encode($params, JSON_UNESCAPED_UNICODE), 'info');
        
        if(empty($auth_code) || empty($order_no)){
            $this->error('参数缺少');
        }
        
        $pay     = new AlipaySdk();
        $user_id = $pay->getUserInfoByXcx($params);//换取小程序openid
        
        if (!empty($user_id)) {
            
            Db::name('order')->where(['out_trade_no' => $order_no])->update(['zfb_user_id' => $user_id]);
            
            $order = Db::name('order')->where(['out_trade_no' => $order_no])->find();
            //halt($order);
            $findQrcode = Db::name('group_qrcode')->where(['id' => $order['qrcode_id']])->find();
            
            $accutils = new Accutils();
            $res = $accutils->alipayJsApiPayData($order, $findQrcode);

            $this->success('获取成功', $res['xl_pay_data']);
        }
        
        $this->error('失败，请重试');
        
    }
    
    //支付宝主体模式，扫码登录回调
    public function appPayCallBack() {
        $app_auth_code = $this->request->get('app_auth_code');
        $app_id        = $this->request->get('app_id');
        $adsd          = $this->request->get('adsd');
        $state         = base64_decode($this->request->get('state'));
        $params        = $this->request->get();
        $adsd          = $state;
        Log::write('appPayCallBack----'.json_encode($params, JSON_UNESCAPED_UNICODE), 'info');

        if(empty($app_auth_code) || empty($app_id)){
            $this->error('参数缺少');
        }
        
        /*$alipaySdk = new AlipaySdk();
        $res = $alipaySdk->getUserInfoByAppSq($params);*/
        
        $ali = Db::name('alipay_zhuti')->where('appid', $app_id)->find();
        
        Log::write(json_encode($params), 'info');
        
        require_once ALI_PATH . '/AopClient.php';
        require_once ALI_PATH . '/request/AlipaySystemOauthTokenRequest.php';
        require_once ALI_PATH . '/request/AlipayOpenAuthTokenAppRequest.php';
        
        //code换token
        $aop                     = new \AopClient ();
        $aop->gatewayUrl         = 'https://openapi.alipay.com/gateway.do';
        $aop->appId              = $ali['appid'];
        $aop->rsaPrivateKey      = $ali['alipay_private_key'];
        $aop->alipayrsaPublicKey = $ali['alipay_public_key'];
        $aop->apiVersion         = '1.0';
        $aop->signType           = 'RSA2';
        $aop->postCharset        = 'UTF-8';
        $aop->format             = 'json';
        $request                 = new \AlipayOpenAuthTokenAppRequest ();
        $request->setBizContent("{" .
            "\"grant_type\":\"authorization_code\"," .
            "\"code\":\"$app_auth_code\"" .
            "  }");
        $result = $aop->execute($request);
        // var_dump($result);
        $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        $resultCode   = $result->$responseNode->code;
        $resultMsg   = $result->$responseNode->msg;
        
        if (!empty($resultCode) && $resultCode == 10000) {
            
            $appAuthToken = $result->alipay_open_auth_token_app_response->app_auth_token;
            $authAppId    = $result->alipay_open_auth_token_app_response->auth_app_id;
            $userId       = $result->alipay_open_auth_token_app_response->user_id;
            
            $dataCache = [
                'app_auth_token' => $appAuthToken,
                'auth_app_id' => $authAppId,
                'user_id' => $userId,
            ];
            
            Cache::set($adsd, $dataCache, 300);
            Db::name('group_qrcode')->where('id',$adsd)->update(['app_auth_token'=>$appAuthToken,'auth_app_id'=>$authAppId,'zfb_pid'=>$userId]);
            
            echo "成功";
        } else {
            echo "失败:".$resultMsg;
        }

    }
    
    //支付宝主体模式，代办模式 扫码登录回调
    public function appPayCallBackV2() {
        
        $aid       = $this->request->get('aid');//主体id
        $order_id  = $this->request->get('state');
        $auth_code = $this->request->get('auth_code');//auth_code
        
        
        $app_auth_code = $this->request->get('app_auth_code');
        $app_id        = $this->request->get('app_id');
        $adsd          = $this->request->get('adsd');
        $state         = base64_decode($this->request->get('state'));
        $params        = $this->request->get();
        $adsd          = $state;
        
        
        if(!empty($aid) && !empty($order_id) && !empty($auth_code)){
            //1052静默获取uid
            Log::write('静默获取uid----'.json_encode($params, JSON_UNESCAPED_UNICODE), 'info');
            $this->getUserId($aid, $order_id, $auth_code);
        }else{
            Log::write('授权回调----'.json_encode($params, JSON_UNESCAPED_UNICODE), 'info');
        }
        
        
        if(empty($app_auth_code) || empty($app_id)){
            $this->error('参数缺少');
        }
        
        $ali = Db::name('alipay_zhuti')->where('appid', $app_id)->find();
        
        require_once ALI_PATH . '/AopClient.php';
        require_once ALI_PATH . '/request/AlipaySystemOauthTokenRequest.php';
        require_once ALI_PATH . '/request/AlipayOpenAuthTokenAppRequest.php';
        
        //code换token
        $aop                     = new \AopClient ();
        $aop->gatewayUrl         = 'https://openapi.alipay.com/gateway.do';
        $aop->appId              = $ali['appid'];
        $aop->rsaPrivateKey      = $ali['alipay_private_key'];
        $aop->alipayrsaPublicKey = $ali['alipay_public_key'];
        $aop->apiVersion         = '1.0';
        $aop->signType           = 'RSA2';
        $aop->postCharset        = 'UTF-8';
        $aop->format             = 'json';
        $request                 = new \AlipayOpenAuthTokenAppRequest ();
        $request->setBizContent("{" .
            "\"grant_type\":\"authorization_code\"," .
            "\"code\":\"$app_auth_code\"" .
            "  }");
        $result = $aop->execute($request);
        // var_dump($result);
        $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        $resultCode   = $result->$responseNode->code;
        $resultMsg    = $result->$responseNode->msg;
        
        
        if (!empty($resultCode) && $resultCode == 10000) {
            
            $appAuthToken = $result->$responseNode->tokens[0]->app_auth_token;
            $authAppId    = $result->$responseNode->tokens[0]->auth_app_id;
            $userId       = $result->$responseNode->tokens[0]->user_id;
            
            $dataCache = [
                'app_auth_token' => $appAuthToken,
                'auth_app_id' => $authAppId,
                'user_id' => $userId,
            ];
            
            Cache::set($adsd, $dataCache, 180);
            Db::name('group_qrcode')->where('id',$adsd)->update(['app_auth_token'=>$appAuthToken,'auth_app_id'=>$authAppId,'zfb_pid'=>$userId]);
            
            echo "成功";
        } else {
            echo "失败:".$resultMsg;
        }

    }
    
    public function getUserId($aid, $order_id, $auth_code){
        
        $params['aid']       = $aid;
        $params['order_id']  = $order_id;
        $params['auth_code'] = $auth_code;
        
        try {
            $pay     = new AlipaySdk();
            $user_id = $pay->getUserInfoByZhuTi($params);
            if (!empty($user_id)) {
    
                Order::where(['out_trade_no' => $order_id])->update(['zfb_user_id' => $user_id]);
    
                $error_data = [
                    'out_trade_no' => $order_id,
                    'trade_no'     => $order_id,
                    'msg'          => $order_id,
                    'content'      => $order_id,
                ];
    
                //判断是否加入黑名单
                $check_black_res = Utils::checkBlackUid($user_id);
                if ($check_black_res) {
                    $error_data['msg']  = $user_id.'-uid已拉黑';
                    event('OrderError', $error_data);
                    Order::where(['out_trade_no' => $order_id])->update(['remark' => 'uid已拉黑']);
                    $this->error('支付失败');
                }
    
                //否则则跳我自己的支付地址
                $pay_url = Utils::imagePath('/api/gateway/order/' . $order_id, true);
                header("Location:" . $pay_url);
            }
            
            $this->error('失败，请重试');
        
        } catch (Exception $e) {
            
            Log::write('aliCallbackV2----'.$e->getFile().'----'. $e->getLine() .'----' .$e->getMessage(),'info');
            $this->error('处理失败');
        }
    }
    
}
