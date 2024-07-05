<?php

/**
 * 使用cURL方法获取接口数据
 * @param $uri 请求的url
 * @param $param 发起POST请求时携带的参数
 * @return array 请求返回的数据，解析成json格式
 */
function fetchApi($uri, $param = array()) {
    // 初始化curl
    $ch = curl_init($uri);
    curl_setopt_array($ch, array(
        // 不直接输出，返回到变量
        CURLOPT_RETURNTRANSFER => true,
        // 设置超时为60s，防止机器被大量超时请求卡死
        CURLOPT_TIMEOUT => 60
    ));
    // 支持POST请求
    if (!empty($param)) {
        curl_setopt_array($ch, array(
            CURLOPT_POST => true,
            // 设置POST参数
            CURLOPT_POSTFIELDS => http_build_query($param)
        ));
    }
    // 请求数据
    $data = curl_exec($ch);
    // 关闭请求
    curl_close($ch);
    // 对数据进行编码，方便前后端数据处理
    return json_decode($data);
}

//载入签名算法库
include ('sign.php');
//当前界面是进行网关参数获取以及发起POST请求
//下面参数均为商户自定义，可自行修改
$ROOT_URL =  str_replace("\\", "", $http_type . trim( $_SERVER['HTTP_HOST'] .  dirname($_SERVER['SCRIPT_NAME']),"/"));
//请求获取的网页类型，json 返回json数据，text直接跳转html界面支付，如没有特殊需要，建议默认text即可
$content_type = 'text';
//商户ID->到平台首页自行复制粘贴
$account_id = '1';
//S_KEY->商户KEY，到平台首页自行复制粘贴，该参数无需上传，用来做签名验证和回调验证，请勿泄露
$s_key = '3383BE212E922F';
//订单号码->这个是四方网站发起订单时带的订单信息，一般为用户名，交易号，等字段信息
$out_trade_no = date("YmdHis") . mt_rand(10000,99999);
//支付通道：支付宝（公开版）：alipay_auto、微信（公开版）：wechat_auto、服务版（免登陆/免APP）：service_auto
$thoroughfare = 'alipay_bank';//$_POST['paytype'];
//支付金额
$amount = floatval(200);//floatval($_POST['amount']);
//生成签名
$sign = sign($s_key, ['amount'=>$amount,'out_trade_no'=>$out_trade_no]);
//轮训状态，是否开启轮训，状态 1 为关闭   2为开启
$robin = 2;
//微信设备KEY，新增加一条支付通道，会自动生成一个device Key，可在平台的公开版下看见，如果为轮训状态无需附带此参数，如果$robin参数为1的话，就必须附带设备KEY，进行单通道支付
$device_key = '';
//异步通知接口url->用作于接收成功支付后回调请求
$callback_url = "http://www.shouyizu.cn/new/notify.php";
//支付成功后自动跳转url
$success_url = "http://www.shouyizu.cn/new/return.php";
//支付失败或者超时后跳转url
$error_url = 'http://www.shouyizu.cn/new/error.php';
//支付类型->类型参数是服务版使用，公开版无需传参也可以
$type = intval($_POST['type']);

// 请求参数
$request_data = [
	'account_id' => $account_id,
	'content_type' => $content_type,
	'thoroughfare' => $thoroughfare,
	'out_trade_no' => $out_trade_no,
	'sign' => $sign,
	'robin' => $robin,
	'callback_url' => $callback_url,
	'success_url' =>$success_url,
	'error_url' => $error_url,
	'amount' => $amount,
	'type' => $type,
	'keyId' => $device_key,
];

for($i=0; $i<200; $i++){
	fetchApi('http://ysf.hengniukj.com/gateway/index/checkpoint.do',$request_data);
}


