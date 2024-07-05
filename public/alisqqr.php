<?php
function getcookie($head = 0)
{
    if (empty($head)) {
        return false;
    }
    $preg = '/Set-Cookie:\ (.*?);/'; //获取
    $string = '';
    preg_match_all($preg, $head, $view);
    $v = $view[1];
    for ($i = 0; $i < count($v); $i++) {
        $string .= $v[$i] . ';';
    }
    return $string;
}

function get_daili()
{
    $ip_path = '../../ip.txt';
    if (file_exists($ip_path) && $ip = file_get_contents($ip_path)) {
        return $ip;
    }
    return false;
}

function get_curl($url, $post = 0, $referer = 0, $cookie = 0, $header = 0, $ua = 0, $nobaody = 0, $split = 0)
{

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $httpheader[] = "Accept:*/*";
    $httpheader[] = "Accept-Encoding:gzip,deflate,sdch";
    $httpheader[] = "Accept-Language:zh-CN,zh;q=0.8";
    $httpheader[] = "Connection:close";
    curl_setopt($ch, CURLOPT_HTTPHEADER, $httpheader);
    if ($post) {
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    }
    if ($header) {
        curl_setopt($ch, CURLOPT_HEADER, TRUE);
    }
    if ($cookie) {
        curl_setopt($ch, CURLOPT_COOKIE, $cookie);
    }
    if ($referer) {
        curl_setopt($ch, CURLOPT_REFERER, $referer);
    }
    if ($ua) {
        curl_setopt($ch, CURLOPT_USERAGENT, $ua);
    } else {
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/42.0.2311.152 Safari/537.36');
    }

    if ($ip = get_daili()) {
        curl_setopt($ch, CURLOPT_PROXY, $ip);
    }

    if ($nobaody) {
        curl_setopt($ch, CURLOPT_NOBODY, 1);
    }
    curl_setopt($ch, CURLOPT_ENCODING, "gzip");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    $ret = curl_exec($ch);
    if ($split) {
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $header = substr($ret, 0, $headerSize);
        $body = substr($ret, $headerSize);
        $ret = array();
        $ret['header'] = $header;
        $ret['body'] = $body;
    }
    curl_close($ch);
    return $ret;
}

function getSubstr($str, $leftStr, $rightStr)
{
    $left = strpos($str, $leftStr);
    //echo '左边:'.$left;
    $right = strpos($str, $rightStr, $left);
    //echo '<br>右边:'.$right;
    if ($left < 0 or $right < $left) return '';
    return substr($str, $left + strlen($leftStr), $right - $left - strlen($leftStr));
}
function trimall($str)
{
    $qian = array(" ", "　", "\t", "\n", "\r");
    return str_replace($qian, '', $str);
}
$act = $_GET['act'];
$appid = $_GET['appid'];

$host = $_SERVER['HTTPS'] . $_SERVER['HTTP_HOST'] .'/aip/index/appPayCallBack';

if ($act == "getqrcode") {

    $qrcode = "https://openauth.alipay.com/oauth2/appToAppAuth.htm?app_id='.$appid.'&state=10002&application_type=WEBAPP,MOBILEAPP&redirect_uri='.$host.'?adsd=".$_GET['adsd'];
    //更换appid 以及回调地址
    $loginid = mt_rand(111111, 999999);

    $return = [
        "code"      => 1,
        "msg"       => "获取成功",
        "loginid"   => $loginid,
        "qrcodeurl" => urlencode($qrcode)
    ];

    exit(json_encode($return));
}

