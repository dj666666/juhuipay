<?php
error_reporting(0);
//载入签名算法库
include ('sign.php');
//当前界面是进行网关参数获取以及发起POST请求
//下面参数均为商户自定义，可自行修改
//$ROOT_URL =  str_replace("\\", "", $http_type . trim( $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) ,"/"));

$http_type = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) ? 'https://' : 'http://';

$ROOT_URL = $http_type . $_SERVER['HTTP_HOST'];


//请求获取的网页类型，json 返回json数据，text直接跳转html界面支付，如没有特殊需要，建议默认text即可
$return_type = 'html';
//商户ID->到平台首页自行复制粘贴
$account_id = $_POST['merno'];
//S_KEY->商户KEY，到平台首页自行复制粘贴，该参数无需上传，用来做签名验证和回调验证，请勿泄露
$s_key = $_POST['merkey'];
//订单号码->这个是四方网站发起订单时带的订单信息，一般为用户名，交易号，等字段信息
$out_trade_no = date("YmdHis") . mt_rand(10000,99999);
//支付通道：支付宝（公开版）：alipay_auto、微信（公开版）：wechat_auto、服务版（免登陆/免APP）：service_auto
$thoroughfare = 'alipay_gd';
//支付金额
$amount = floatval($_POST['amount']);
$pay_type = $_POST['paytype'];



//$sign = sign($array,$s_key);

//轮训状态，是否开启轮训，状态 1 为关闭   2为开启
$robin = 2;
//设备KEY，新增加一条支付通道，会自动生成一个device Key，可在平台的公开版下看见，如果为轮训状态无需附带此参数，如果$robin参数为1的话，就必须附带设备KEY，进行单通道支付
$device_key = '';
$api_url = 'http://mgnowg.chxsw.xyz/api';
//下单接口
$gateway_url = $api_url.'/gateway/suborder';

//var_dump($gateway_url);die;
//异步通知接口url->用作于接收成功支付后回调请求
$callback_url = $api_url."/demo/getcallback";
//支付成功后自动跳转url
$success_url = $api_url."/demo/return";
//支付失败或者超时后跳转url
$error_url = $api_url.'/demo/error';
//支付类型->类型参数是服务版使用，公开版无需传参也可以
$type = intval($_POST['type']);
//备注
$remark = '';
//生成签名
//$sign = sign($s_key, ['amount'=>$amount,'trade_no'=>$out_trade_no,'mmer_no'=>$account_id,'notify_url'=>$callback_url,'return_url'=>$success_url,'return_type'=>$return_type]);
$sign = buildsign(['amount'=>$amount,'pay_type'=>$pay_type,'trade_no'=>$out_trade_no,'mer_no'=>$account_id,'notify_url'=>$callback_url,'return_url'=>$success_url,'return_type'=>$return_type], $s_key);
?>


<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<title>接口调用</title>
</head>
<body>
<form action="<?php echo $gateway_url;?>" method="post" id="frmSubmit">
    <input type="hidden" name="mer_no" value="<?php echo $account_id;?>" />
	<input type="hidden" name="return_type" value="<?php echo $return_type;?>"/>
	<input type="hidden" name="trade_no" value="<?php echo $out_trade_no;?>"/>
	<input type="hidden" name="sign" value="<?php echo $sign;?>"/>
	<input type="hidden" name="notify_url" value="<?php echo $callback_url;?>" />
	<input type="hidden" name="return_url" value="<?php echo $success_url;?>" />
	<input type="hidden" name="amount" value="<?php echo $amount;?>" />
	<input type="hidden" name="remark" value="<?php echo $remark;?>" />
	<input type="hidden" name="pay_type" value="<?php echo $pay_type;?>" />
	<input type="submit" name="btn" value="submit" />
</form>
<script type="text/javascript">
    document.getElementById("frmSubmit").submit();
</script>
</body>
</html>