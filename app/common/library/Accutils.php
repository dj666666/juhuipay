<?php

namespace app\common\library;

use app\admin\model\GroupQrcode;
use app\admin\model\order\Order;
use app\admin\model\User;
use DOMDocument;
use DOMXPath;
use League\Flysystem\Util;
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
use app\common\library\HandpaySignUtil;
use app\common\library\Wxpush;
use think\facade\Cache;
use app\common\library\AlipaySdk;


class Accutils
{

    //无忧 汇盈三方支付
    public function getWuYouPayData($order, $ip) {

        $findorder = Order::where(['id' => $order['id'], 'pay_type' => '1066'])->find();
        if (!empty($findorder['xl_order_id']) && !empty($findorder['xl_pay_data'])) {
            $data = [
                'xl_order_id' => $findorder['xl_order_id'],
                'xl_pay_data' => $findorder['xl_pay_data'],
                'xl_user_id'  => $findorder['xl_user_id'],
            ];
            return ['code' => 200, 'msg' => $data];
        }
        $mer_key     = '4181932637403f3e68b98f95e72d37c4';
        $appId       = '2106';
        $payMethod   = '1011';
        $amount      = $order['amount'];
        $notifyUrl   = Utils::imagePath('/api/notify/wuyouNotify', false);//回调通知地址
        $returnUrl   = Utils::imagePath('/api/gateway/order/' . $order['out_trade_no'], true);//同步地址
        $device_type = $this->get_device_type();

        $postData = [
            'appId'     => $appId,
            'payMethod' => $payMethod,
            'amount'    => $amount,
            'orderId'   => $order['out_trade_no'],
            'notifyUrl' => $notifyUrl,
            'returnUrl' => $returnUrl,
        ];

        $sign_str = 'appId=' . $appId . '&payMethod=' . $payMethod . '&amount=' . $amount . '&orderId=' . $order['out_trade_no'] . '&notifyUrl=' . $notifyUrl . $mer_key;
        $sign     = md5($sign_str);

        $private_key = 'MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQCrMonqglOQ0vI22UPUvst2CTCHyJumzJHqPB2pwGj0MQ4WjJP/1tToML+0ouJ4xrgyg6ZzBBhdAH+NLVRcWDwT8JDHPaSvQ2gVBYZeT04o1hPNyolRdEmAFT3pHXq3NnN6xe9dlPhV1U36cRqtdGlTR6EIvujhbUZZpWOc0j0Q9DaE63BCSNRgfz25E7o1m6ryPRsYhLegf0KMmTKs1kN+rDvoi2iQYMQF7JBFe2+jQvX8aglFjpe/u+l53KhJ6hjeDlOJFWFurubgu4WPkGMAPGlUEc8ArTla4PRk9xzwWUU13BzK6jbXan8z40+JX7V5Q5Dy2wgNdLkg/pkXDmZTAgMBAAECggEBAIQzEq001rMPMluIiwjODO+pSQCRuSCk+EiZA48CNgnbl7Vx+RenHeHvQxqKbbg2PCdF4lMO5oFq0RSD3JEy0bHUIvi4cWOl+cnB6nEJbKG8Lq7LqB5uXhO6U4SgbxLryWEVWDI7x0kA3qZ2kNNBAdR9i6zlP5BUge7X+IQxFVbw+UgTRkKM3sdnIKLaqZf9oWB1ptZL31oVORw/fW0epEnW3npxeSVTBRx2isCe3DZgxjqsuRyTK97mPoYiezBtPTsR+bC1BoGGTZAaYsgBNvMBg04tUBKfgKGMftcy60Aykzua19TKPPdQ1juIhKxgzwZypllioYFcH+Io2pnCPAECgYEA6+A13d4qfVrfQCtE0fH3hXO3Quh5n+XFiQOwlb/qlSiF9s6hzHhHL0OgWu81YU1HOo9geJ+O4x2sES80/+RFDNc8g5ck4ARntLIgdlQ4x0A0zSr7todfXa0t5WpXIpK5P46iY4qwXWODs6BX9WyFWVNcMsAqg8cKjgKTuTJYjnMCgYEAuc2uIliw07jy6LPz6bUoWSQU+6h2Tdhn4eoRrfBrtt9w7n5F8HnPET2qdkrQJgYIsvNgOmeduwQ58c4CN+GtO7ya/qGoWvAADtyT13R2c0LHehaMg3ioptJ1duHEypkLAHuHpb9I5QTJq7S7/k6FE6x92AbG4X4xBX4KF3ur8KECgYBBmP8iDtEeP5Fq1R20jWha8s16jBCXCV2gNyh63P6uMLDg7KJOrPyPBm2oHHJp9wXiIVGg+i7QtsXNmaVRrPgPFCS+K+CTdGYq+IbSoaWQtCh7DoMmRgudj7X94U8OTGO8azx6Fm3p6u0LnYIYvO9w4h/39T1dnJMw30KZ9IhwRQKBgHnQvoToLu5wiFlGefjUe6KNxHAFz6JT6i+0PWkTghtAPRMvmH0D7103V8X8YKE9PYDNjV5dRo0xRSgiT1QB0oiiq9+bbvxH81HLDeapBWul8ZA6rl8XwUK2IqsRc+r8Ebl8Q7/IPGtiCzJW6RXetuLiKRNzxfpauJsIOmeQ/nZhAoGAFz+V0IEI04OK7Sytao8M32pdBy+lWThIgloPkEB8FKuNbV5mHwG7mIZrw885fRvroA6m90JTxZhEKHSylIvQunMn5wcs6fp8nGzx7WEHI68Dm4UnTpUOVhDALOtDwJsCOuwXrczYBNmMWuKkSt8qQ/toGbR69d3BkGFywpblqH8=';
        $rsa         = new Rsa('', $private_key);
        //私钥加密
        $notifyUrl_sign = $rsa->private_encrypt($notifyUrl);

        $postData['notifyUrl'] = $notifyUrl_sign;
        $postData['sign']      = $sign;

        Log::write('汇盈待签名字符串----' . $sign_str, 'info');
        Log::write('汇盈发送参数----' . json_encode($postData, JSON_UNESCAPED_UNICODE), 'info');


        $url    = 'http://send.yunminwl.com/pay/create.aspx';
        $res    = Http::post($url, $postData, []);
        $result = json_decode($res, true);
        Log::write('汇盈发送结果----' . $res, 'info');

        if (!isset($result['id']) || !isset($result['data'])) {
            $error_data = [
                'out_trade_no' => $order['out_trade_no'],
                'trade_no'     => $order['trade_no'],
                'msg'          => '汇盈提单错误',
                'content'      => $res,
            ];
            event('OrderError', $error_data);
            return ['code' => 102, 'msg' => '获取支付信息失败'];
        }

        if (isset($result['id']) && $result['id'] != 0) {
            $error_data = [
                'out_trade_no' => $order['out_trade_no'],
                'trade_no'     => $order['trade_no'],
                'msg'          => '汇盈提单错误',
                'content'      => $res,
            ];
            event('OrderError', $error_data);
            return ['code' => 102, 'msg' => '获取支付信息失败'];
        }

        $data = [
            'xl_order_id'   => $result['data']['orderid'],
            'xl_pay_data'   => $result['data']['qrcode'],
            'xl_gorderid'   => $result['data']['sysorderid'],
            'hand_pay_data' => $res,
            //'device_type'   => $device_type,
        ];
        Order::where('id', $order['id'])->update($data);

        return ['code' => 200, 'msg' => $data];

    }

    //微信直播三方支付
    public function getWxPayData($order, $ip) {

        $findorder = Order::where(['id' => $order['id'], 'pay_type' => '1066'])->find();
        if (!empty($findorder['xl_order_id']) && !empty($findorder['xl_pay_data'])) {
            $data = [
                'xl_order_id' => $findorder['xl_order_id'],
                'xl_pay_data' => $findorder['xl_pay_data'],
                'xl_user_id'  => $findorder['xl_user_id'],
            ];
            return ['code' => 200, 'msg' => $data];
        }
        $mer_key      = '7kk0o449v6r03ht77ievek3c61xnkzq0';
        $mer_id       = '230707736';
        $pay_bankcode = '3010';

        //$orderid     = substr($order['out_trade_no'], 0, -1);
        $orderid     = $order['out_trade_no'];
        $notifyUrl   = Utils::imagePath('/api/notify/wxpayNotify', false);//回调通知地址
        $returnUrl   = Utils::imagePath('/api/gateway/order/' . $order['out_trade_no'], true);//同步地址
        $device_type = $this->get_device_type();

        $postData = [
            'pay_memberid'    => $mer_id,
            'pay_orderid'     => $orderid,
            'pay_applydate'   => date('Y-m-d H:i:s'),
            'pay_bankcode'    => $pay_bankcode,
            'pay_notifyurl'   => $notifyUrl,
            'pay_callbackurl' => $returnUrl,
            'pay_amount'      => $order['amount'],
        ];

        $sign = strtoupper(Utils::sign($postData, $mer_key));

        $postData['pay_md5sign']     = $sign;
        $postData['pay_productname'] = '精品袜子';

        Log::write('微信直播发送参数----' . json_encode($postData, JSON_UNESCAPED_UNICODE), 'info');


        $url    = 'http://mon.jumei100.top/Pay_Index.html';
        $res    = Http::post($url, $postData, []);
        $result = json_decode($res, true);

        Log::write('微信直播发送结果----' . $res, 'info');

        if (!isset($result['code']) || !isset($result['data']) || $result['code'] != 200) {
            $error_data = [
                'out_trade_no' => $order['out_trade_no'],
                'trade_no'     => $order['trade_no'],
                'msg'          => '微信直播提单错误',
                'content'      => $res,
            ];
            event('OrderError', $error_data);
            return ['code' => 102, 'msg' => '获取支付信息失败'];
        }

        $data = [
            //'xl_order_id'   => $result['data'],
            'xl_pay_data'   => $result['data'],
            'hand_pay_data' => $res,
            'xl_gorderid'   => isset($result['type']) ? $result['type'] : '', //没有type参数data返回就是url
            //'device_type'   => $device_type,
        ];
        Order::where('id', $order['id'])->update($data);

        return ['code' => 200, 'msg' => $data];

    }

    //汇潮支付宝获取支付信息
    public function hczfbPayData($order) {

        if (!empty($order['hc_pay_data'])) {
            return $order['hc_pay_data'];
        }

        //组装请求参数，加密
        $merchantNo  = Config::get('mchconf.hc_mch_no');
        $orderNo     = $order['out_trade_no'];
        $payType     = 'AliJsapiPay_OffLine';
        $amount      = $order['amount'];
        $subject     = '收银台';
        $desc        = '互联网支付';
        $randomStr   = Random::alnum(10);
        $adviceUrl   = Utils::imagePath('/api/notify/hcNotify', false);//回调通知地址
        $private_key = Config::get('mchconf.hc_mch_private_key');

        $data = 'AdviceUrl=' . $adviceUrl . '&Amount=' . $amount . '&MerchantNo=' . $merchantNo . '&MerchantOrderNo=' . $orderNo . '&PayType=' . $payType . '&RandomStr=' . $randomStr;

        $rsa = new Rsa('', $private_key);
        //私钥加密
        //$mysign = $rsa->private_encrypt($data);
        $mysign = $rsa->private_encryptV2($data);

        $xml_arr = [
            'MerchantNo'      => $merchantNo,
            'MerchantOrderNo' => $orderNo,
            'PayType'         => $payType,
            'Amount'          => $amount,
            'Subject'         => $subject,
            'Desc'            => $desc,
            'CompanyNo'       => 1,
            'RandomStr'       => $randomStr,
            'SignInfo'        => $mysign,
            'AdviceUrl'       => $adviceUrl,
            'SubAppid'        => 1,
            'UserId'          => 1,
            'SubMerchantType' => 5812,
            'PayWay'          => 'ZFB',
        ];

        $xml = "<?xml version='1.0' encoding='utf-8'?>";
        $xml = Utils::arrToXml($xml, $xml_arr);

        $hc_pay_data = base64_encode(trim($xml));

        $device_type = Utils::getClientOsInfo();
        //$device_type = $this->get_device_type();

        $updateData = [
            'hc_pay_data' => $hc_pay_data,
            'device_type' => $device_type,
        ];

        $order = Db::name('order')->where(['id' => $order['id']])->update($updateData);

        return $hc_pay_data;
    }

    //迅雷直播-支付宝
    public function xlzbzfb($user_id, $pay_type, $acc_robin_rule, $amount) {
        $qrcode = '';
        //随机模式
        if ($acc_robin_rule == 1) {

            $qrcode_list = Db::name('group_qrcode')->where(['user_id' => $user_id, 'acc_code' => $pay_type, 'status' => 1])->select();

            $qrcode_count = count($qrcode_list);
            if ($qrcode_count < 1) {
                return '';
            }

            $qrcode = $qrcode_list[mt_rand(0, $qrcode_count - 1)];
            return $qrcode;
        }

        //顺序模式
        if ($acc_robin_rule == 2) {

            //查询今日总订单数
            $order_count_today = Db::name('order')->where(['user_id' => $user_id, 'pay_type' => $pay_type])->whereDay('createtime')->count();

            // 查询该用户所有通道数
            $count_alipay = Db::name('group_qrcode')->where(['user_id' => $user_id, 'acc_code' => $pay_type, 'status' => 1])->count();

            if ($count_alipay < 1) {
                return '';
            }

            if ($order_count_today < 1) {
                $order_count_today = 1;
            }


            $start = $order_count_today % $count_alipay;

            $qrcode = Db::name('group_qrcode')->where(['user_id' => $user_id, 'acc_code' => $pay_type, 'status' => 1])->order('id desc')->limit($start, 1)->select();
            $qrcode = $qrcode[0];

        }

        return $qrcode;
    }

    //迅雷直播-支付宝获取支付信息
    public function xlzbgetPayData($order, $xl_user_id) {

        $findorder = Db::name('order')->where(['id' => $order['id'], 'pay_type' => '1014'])->find();
        if (!empty($findorder['xl_order_id']) && !empty($findorder['xl_pay_data']) && !empty($findorder['xl_user_id'])) {
            $data = [
                'xl_order_id' => $findorder['xl_order_id'],
                'xl_pay_data' => $findorder['xl_pay_data'],
                'xl_user_id'  => $findorder['xl_user_id'],
            ];
            return ['code' => 200, 'msg' => $data];
        }


        $t        = $this->getMsectime();
        $gorderid = $t . '_' . $xl_user_id;
        $num      = $order['amount'] * 10;
        $rmb      = intval($order['amount']);
        //$device_type = $this->get_device_type();
        $device_type = 'ios';


        /*//1.版本1 h5
        $fgUrl = 'https%3A%2F%2Flive.xunlei.com';
        $str   = '_t='.$t.'&activeid=4001004&bizno=live&ext2=source%3Ah5_live%3Bpage_from%3Ah5_largepay_'.$device_type.'%3Bgorderid%3A'.$gorderid.'&fgUrl='.$fgUrl.'&num='.$num.'&paytype=N2&productid=4001004&rmb='.$rmb.'&userid='.$xl_user_id.'&version=v2.0';
        
        $sign       = md5('1006'.$str.'8bq65ETvuW-DF{R');
        $url        = 'https://agent-paycenter-ssl.xunlei.com/payorder/v3/ActOrder?version=v2.0&userid='.$xl_user_id.'&activeid=4001004&productid=4001004&bizno=live&paytype=N2&num='.$num.'&rmb='.$rmb.'&fgUrl='.$fgUrl.'&ext2=source%3Ah5_live%3Bpage_from%3Ah5_largepay_'.$device_type.'%3Bgorderid%3A'.$gorderid.'&_t='.$t.'&sign='.$sign;*/


        /*//版本2 h5
        $fgUrl = 'https://live.xunlei.com//wap/pay/largeRecharge.html?allmoney=1';
        $str   = '_t='.$t.'&activeid=2006001&bizno=live&ext2=source%3Ah5_live%3Bpage_from%3Ah5_largepay_android%3Bgorderid%3A'.$gorderid.'&fgUrl='.urlencode($fgUrl).'&num='.$num.'&paytype=N2&rmb='.$rmb.'&userid='.$xl_user_id.'&version=v2.0';
        $sign = md5('1002'.$str.'&*%$7987321GKwq');
        
        $url = 'https://agent-paycenter-ssl.xunlei.com/payorder/v3/ActOrder?version=v2.0&userid='.$xl_user_id.'&activeid=2006001&bizno=live&paytype=N2&num='.$num.'&rmb='.$rmb.'&fgUrl='.urlencode($fgUrl).'&id='.$xl_user_id.'&gorderid='.$gorderid.'&ext2=source%3Ah5_live%3Bpage_from%3Ah5_largepay_'.$device_type.'%3Bgorderid%3A'.$gorderid.'&_t='.$t.'&sign='.$sign;*/

        //版本3 h5
        //https://agent-paycenter-ssl.xunlei.com/payorder/v3/ActOrder?version=v2.0&userid=444025408&activeid=4001004&bizno=live&paytype=N2&num=10&rmb=1&fgUrl=https://live.xunlei.com//wap/pay/largeRecharge.html?allmoney=1&id=444025408&gorderid=1675563521448_444025408&ext2=source:h5_live;page_from:h5_largepay_ios;gorderid:1675563521448_444025408&_t=1675563521449&sign=75e914e39276bfe9ec13da78198f66ef;

        /*$fgUrl = urlencode('https://live.xunlei.com//wap/pay/largeRecharge.html?allmoney=1&id='.$xl_user_id.'&gorderid='.$gorderid);
        
        $str   = '_t='.$t.'&activeid=4001004&bizno=live&ext2=source%3Ah5_live%3Bpage_from%3Ah5_largepay_'.$device_type.'%3Bgorderid%3A'.$gorderid.'&fgUrl='.$fgUrl.'&num='.$num.'&paytype=N2&productid=4001004&rmb='.$rmb.'&userid='.$xl_user_id.'&version=v2.0';
        $sign = md5('1006'.$str.'8bq65ETvuW-DF{R');
        $ext2 = urlencode('source:h5_live;page_from:h5_largepay_'.$device_type.';gorderid:'.$gorderid);
        $url  = 'https://agent-paycenter-ssl.xunlei.com/payorder/v3/ActOrder?version=v2.0&userid='.$xl_user_id.'&activeid=4001004&bizno=live&paytype=N2&num='.$num.'&rmb='.$rmb.'&fgUrl='.$fgUrl. '&ext2='.$ext2.'&_t='.$t.'&sign='.$sign;*/


        /*//版本4 2.09 h5
        //https://agent-paycenter-ssl.xunlei.com/payorder/v3/ActOrder?version=v2.0&userid=839281100&activeid=4001008&bizno=live&paytype=N2&num=20&rmb=2&fgUrl=https%3A%2F%2Flive.xunlei.com%2F%2Fwap%2Fpay%2FlargeRecharge.html%3Fallmoney%3D1%26h5from%3Dh5_multiple_productid_2%26sec%3DJfUsM3N8RuXgBzxG%26id%3D839281100%26gorderid%3D1675954291267_839281100&ext2=source%3Ah5_live%3Bpage_from%3Ah5_largepay_ios_3%3Bgorderid%3A1675954291267_839281100&_t=1675954291268&sign=8f31715295a0297ac29a42d17062a495
        
        $fgUrl = urlencode('https://live.xunlei.com//wap/pay/largeRecharge.html?allmoney=1&h5from=h5_multiple_productid_2&sec=JfUsM3N8RuXgBzxG&id='.$xl_user_id.'&gorderid='.$gorderid.'&ext2=source:h5_live;page_from:h5_largepay_'.$device_type.'_3;gorderid:'.$gorderid);
        
        $str = urlencode('_t='.$t.'&activeid=4001008&bizno=live&ext2=source:h5_live;page_from:h5_largepay_'.$device_type.'_3;gorderid:'.$gorderid.'&fgUrl=https://live.xunlei.com//wap/pay/largeRecharge.html?allmoney=1&h5from=h5_multiple_productid_2&sec=JfUsM3N8RuXgBzxG&id='.$xl_user_id.'&gorderid='.$gorderid.'&num='.$num.'&paytype=N2&rmb='.$rmb.'&userid='.$xl_user_id.'&version=v2.0');
        
        $sign1 = md5('1002'.$str.'&*%$7987321GKwq');
        
        $sign2 = md5('1006'.$str.'8bq65ETvuW-DF{R');

        $url = 'https://agent-paycenter-ssl.xunlei.com/payorder/v3/ActOrder?version=v2.0&userid='.$xl_user_id.'&activeid=4001008&bizno=live&paytype=N2&num='.$num.'&rmb='.$rmb.'&fgUrl='.$fgUrl.'&_t='.$t.'&sign='.$sign2 .'&callback=__jp_DKMIFE5';*/


        //版本5 2.19 h5
        //https://agent-paycenter-ssl.xunlei.com/payorder/v3/ActOrder?version=v2.0&userid=444025408&activeid=4001007&bizno=live&paytype=N2&num=10&rmb=1&fgUrl=https%3A%2F%2Flive.xunlei.com%2F%2Fwap%2Fpay%2FlargeRecharge.html%3Fallmoney%3D1%26h5from%3Dh5_multiple_productid%26sec%3DutvyUErFq2P8WCPA%26id%3D444025408%26gorderid%3D1676615736102_444025408&ext2=source%3Ah5_live%3Bpage_from%3Ah5_largepay_ios_custom_2%3Bgorderid%3A1676615736102_444025408&_t=1676615736105&sign=a64c679d39f4e30778c4fa640a629c3f&callback=__jp_7RG9NP4

        $fgUrl = urlencode('https://live.xunlei.com//wap/pay/largeRecharge.html?allmoney=1&h5from=h5_multiple_productid&sec=utvyUErFq2P8WCPA&id=' . $xl_user_id . '&gorderid=' . $gorderid . '&ext2=source:h5_live;page_from:h5_largepay_' . $device_type . '_custom_2;gorderid:' . $gorderid);

        //$str = urlencode('_t='.$t.'&activeid=4001007&bizno=live&ext2=source:h5_live;page_from:h5_largepay_'.$device_type.'_custom_2;gorderid:'.$gorderid.'&fgUrl=https://live.xunlei.com//wap/pay/largeRecharge.html?allmoney=1&h5from=h5_multiple_productid&sec=utvyUErFq2P8WCPA&id='.$xl_user_id.'&gorderid='.$gorderid.'&num='.$num.'&paytype=N2&rmb='.$rmb.'&userid='.$xl_user_id.'&version=v2.0');

        $str = '_t=' . $t . '&activeid=4001007&bizno=live&ext2=source%3Ah5_live%3Bpage_from%3Ah5_largepay_' . $device_type . '_custom_2%3Bgorderid%3A' . $gorderid . '&fgUrl=https%3A%2F%2Flive.xunlei.com%2F%2Fwap%2Fpay%2FlargeRecharge.html%3Fallmoney%3D1%26h5from%3Dh5_multiple_productid%26sec%3DutvyUErFq2P8WCPA%26id%3D' . $xl_user_id . '%26gorderid%3D' . $gorderid . '&num=' . $num . '&paytype=N2&rmb=' . $rmb . '&userid=' . $xl_user_id . '&version=v2.0';

        $sign = md5('1006' . $str . '8bq65ETvuW-DF{R');

        $url = 'https://agent-paycenter-ssl.xunlei.com/payorder/v3/ActOrder?version=v2.0&userid=' . $xl_user_id . '&activeid=4001007&bizno=live&paytype=N2&num=' . $num . '&rmb=' . $rmb . '&fgUrl=' . $fgUrl . '&_t=' . $t . '&sign=' . $sign;
        //halt($str,$url);

        $options = [];

        /*$daili = AgentUtil::shanchendaili($order['out_trade_no']);
        if (!empty($daili)) {
            if($daili['status'] == 0 && count($daili['list']) > 0){
                $daili = $daili['list'][0];
                $options = [
                    CURLOPT_PROXY => $daili['sever'],
                    CURLOPT_PROXYPORT => $daili['port'],
                    //CURLOPT_HTTPHEADER => $header_arr
                ];
            }else{
                $options = [];
            }
        }*/

        /*$daili = AgentUtil::liuguanDaili($order['out_trade_no']);
        if(isset($daili['serialNo'])){
            
            $proxyServer = "http://".$daili['data'][0]['ip'].":".$daili['data'][0]['port'];
            
            $options = [
                CURLOPT_PROXYAUTH => CURLAUTH_BASIC,
                CURLOPT_PROXY => $proxyServer,
            ];
        }else{
            $options = [];
        }*/


        $res = Http::get($url, [], $options);
        /*$result = str_replace('__jp_DKMIFE5(','',$res);
        $result = substr($result,0,strlen($result)-1);
        $result = json_decode($result, true);*/

        $result = json_decode($res, true);

        if ($result['data']['code'] != 200) {
            $data = [
                'out_trade_no' => $order['out_trade_no'],
                'trade_no'     => $order['trade_no'],
                'content'      => '迅雷获取支付信息失败' . $res,
            ];
            event('OrderError', $data);
            return ['code' => 102, 'msg' => '获取支付信息失败'];
        }

        $data = [
            'xl_order_id'   => $result['data']['orderId'],
            'xl_pay_data'   => $result['data']['url'],
            'xl_user_id'    => $result['data']['userId'],
            'xl_gorderid'   => $gorderid,
            'hc_pay_data'   => $res,
            'hand_pay_data' => $url,
            'device_type'   => $device_type,
        ];
        Db::name('order')->where('id', $order['id'])->update($data);

        return ['code' => 200, 'msg' => $data];

    }

    //迅雷20230730
    public function xlzbgetPayDataV2($order, $xl_user_id) {

        $findorder = Db::name('order')->where(['id' => $order['id'], 'pay_type' => '1014'])->find();
        if (!empty($findorder['xl_order_id']) && !empty($findorder['xl_pay_data']) && !empty($findorder['xl_user_id'])) {
            $data = [
                'xl_order_id' => $findorder['xl_order_id'],
                'xl_pay_data' => $findorder['xl_pay_data'],
                'xl_user_id'  => $findorder['xl_user_id'],
            ];
            return ['code' => 200, 'msg' => $data];
        }


        $t           = $this->getMsectime();
        $gorderid    = $t . '_' . $xl_user_id;
        $num         = $order['amount'] * 10;
        $rmb         = intval($order['amount']);
        $device_type = $this->get_device_type();
        $t2          = $this->getMsectime();

        $fgUrl    = urlencode('https://live.xunlei.com//wap/pay/largeRecharge.html?allmoney=1&sec=0&id=' . $xl_user_id . '&gorderid=' . $gorderid . '&ext2=source:h5_live;page_from:h5_largepay_' . $device_type . '_custom_1;gorderid:' . $gorderid);
        $sign_str = '_t=' . $t . '&activeid=2006001&bizno=live&ext2=source%3Ah5_live%3Bpage_from%3Ah5_largepay_' . $device_type . '_custom_1%3Bgorderid%3A' . $gorderid . '&fgUrl=https%3A%2F%2Flive.xunlei.com%2F%2Fwap%2Fpay%2FlargeRecharge.html%3Fallmoney%3D1%26sec%3D0%26id%3D' . $xl_user_id . '%26gorderid%3D' . $gorderid . '&num=' . $num . '&paytype=N2&rmb=' . $rmb . '&userid=' . $xl_user_id . '&version=v2.0';
        $sign     = md5('1002' . $sign_str . '&*%$7987321GKwq');
        $payUrl   = 'https://agent-paycenter-ssl.xunlei.com/payorder/v3/ActOrder?version=v2.0&userid=' . $xl_user_id . '&activeid=2006001&bizno=live&paytype=N2&num=' . $num . '&rmb=' . $rmb . '&fgUrl=' . $fgUrl . '&_t=' . $t . '&sign=' . $sign . '&callback=';

        //halt($sign_str,$payUrl);

        $options = [];

        /*$daili = AgentUtil::shanchendaili($order['out_trade_no']);
        if (!empty($daili)) {
            if($daili['status'] == 0 && count($daili['list']) > 0){
                $daili = $daili['list'][0];
                $options = [
                    CURLOPT_PROXY => $daili['sever'],
                    CURLOPT_PROXYPORT => $daili['port'],
                    //CURLOPT_HTTPHEADER => $header_arr
                ];
            }else{
                $options = [];
            }
        }*/

        /*$daili = AgentUtil::liuguanDaili($order['out_trade_no']);
        if(isset($daili['serialNo'])){

            $proxyServer = "http://".$daili['data'][0]['ip'].":".$daili['data'][0]['port'];

            $options = [
                CURLOPT_PROXYAUTH => CURLAUTH_BASIC,
                CURLOPT_PROXY => $proxyServer,
            ];
        }else{
            $options = [];
        }*/


        $res = Http::get($payUrl, [], $options);
        /*$result = str_replace('__jp_DKMIFE5(','',$res);
        $result = substr($result,0,strlen($result)-1);
        $result = json_decode($result, true);*/

        $result = json_decode($res, true);

        if ($result['data']['code'] != 200) {
            $data = [
                'out_trade_no' => $order['out_trade_no'],
                'trade_no'     => $order['trade_no'],
                'content'      => '迅雷获取支付信息失败' . $res,
            ];
            event('OrderError', $data);
            return ['code' => 102, 'msg' => '获取支付信息失败'];
        }

        $data = [
            'xl_order_id'   => $result['data']['orderId'],
            'xl_pay_data'   => $result['data']['url'],
            'xl_user_id'    => $result['data']['userId'],
            'xl_gorderid'   => $gorderid,
            'hc_pay_data'   => $res,
            'hand_pay_data' => $payUrl,
            'device_type'   => $device_type,
        ];
        Db::name('order')->where('id', $order['id'])->update($data);

        return ['code' => 200, 'msg' => $data];

    }

    //迅雷直播-支付宝获取支付信息
    public function xlzbgetPayDataPc($order, $xl_user_id) {

        $findorder = Db::name('order')->where(['id' => $order['id'], 'pay_type' => '1014'])->find();
        if (!empty($findorder['xl_order_id']) && !empty($findorder['xl_pay_data']) && !empty($findorder['xl_user_id'])) {
            $data = [
                'xl_order_id' => $findorder['xl_order_id'],
                'xl_pay_data' => $findorder['xl_pay_data'],
                'xl_user_id'  => $findorder['xl_user_id'],
            ];
            return ['code' => 200, 'msg' => $data];
        }


        $t           = $this->getMsectime();
        $gorderid    = $t . '_' . $xl_user_id;
        $num         = $order['amount'] * 10;
        $rmb         = round($order['amount'], 0);
        $device_type = $this->get_device_type();

        //版本3 pc
        $url = 'https://pc-live-ssl.xunlei.com/caller?c=paynew&a=jump&version=v2.0&sessionid=ws001.2CEBD83BEE138128161B21B0C3BDA95F&userid=' . $xl_user_id . '&opid=web&num=' . $num . '&rmb=' . $rmb . '&payfrom=pc_jiuwo&paytype=E&bankNo=&bizno=live&activeid=2006001&ext2=source%253Apc_live%253Buserid%253A' . $xl_user_id . '%253Bgorderid%253A' . $gorderid . '%253Bpay_channel%253A%27%27%253Bguid%253A09a28616fa21296e869643845586c258%253Bpage_from%253Alive_nav&ext3=';
        //$url = 'https://pc-live-ssl.xunlei.com/caller?c=paynew&a=jump&version=v2.0&sessionid=&userid='.$xl_user_id.'&opid=web&num='.$num.'&rmb='.$rmb.'&payfrom=pc_jiuwo2&paytype=E&bankNo=&bizno=live&activeid=2006001&ext2=&ext3=';


        $result = $this->curl_get($url);

        $data = [
            //'xl_order_id' => $result['data']['orderId'],
            'xl_pay_data'   => $result,
            'xl_user_id'    => $xl_user_id,
            'xl_gorderid'   => $gorderid,
            'hand_pay_data' => $url,
            'device_type'   => $device_type,
        ];
        Db::name('order')->where('id', $order['id'])->update($data);

        return ['code' => 200, 'msg' => $data];

    }


    //迅雷直播-微信获取支付信息
    public function xlzbWeiXingetPayData($order, $xl_uuid, $xl_user_id) {

        $findorder = Db::name('order')->where(['id' => $order['id'], 'pay_type' => '1015'])->find();
        if (!empty($findorder['xl_order_id']) && !empty($findorder['xl_pay_data']) && !empty($findorder['xl_user_id'])) {
            $data = [
                'xl_order_id' => $findorder['xl_order_id'],
                'xl_pay_data' => $findorder['xl_pay_data'],
                'xl_user_id'  => $findorder['xl_user_id'],
            ];
            return ['code' => 200, 'msg' => $data];
        }


        if (empty($xl_user_id)) {


            $daili = AgentUtil::shanchendaili($order['out_trade_no']);
            if ($daili['status'] != 0) {
                return ['code' => 103, 'msg' => '获取失败，请重试'];
            }

            $daili = $daili['list'][0];

            $params  = [];
            $options = [
                CURLOPT_PROXY     => $daili['sever'],
                CURLOPT_PROXYPORT => $daili['port'],
            ];

            $t      = $this->getMsectime();
            $sign   = md5('1002_t=' . $t . '&a=getroom&c=room&uuid=' . $xl_uuid . '&*%$7987321GKwq');
            $url    = 'https://pc-live-ssl.xunlei.com/caller?c=room&a=getroom&uuid=' . $xl_uuid . '&_t=' . $t . '&sign=' . $sign;
            $result = json_decode(Http::get($url, $params, $options), true);

            if (empty($result['data']['userInfo'])) {
                return ['code' => 101, 'msg' => '用户不存在'];
            }

            $xl_user_id = $result['data']['userInfo']['userid'];
            Db::name('group_qrcode')->where(['id' => $order['qrcode_id']])->update(['xl_user_id' => $xl_user_id]);

        }

        $t        = $this->getMsectime();
        $gorderid = $t . '_' . $xl_user_id;
        $num      = $order['amount'] * 10;
        $rmb      = round($order['amount'], 0);

        //$fgurl = 'https%3A%2F%2Flive.xunlei.com%2F%2Fwap%2Fpay%2FlargeRecharge.html%3Fallmoney%3D1%26id%3D'.$xl_user_id.'%26gorderid%3D'.$gorderid;

        $fgUrl = 'https%3A%2F%2Flive.xunlei.com';

        $str = '_t=' . $t . '&activeid=4001004&bizno=live&ext2=source%3Ah5_live%3Bpage_from%3Ah5_largepay_android%3Bgorderid%3A' . $gorderid . '&fgUrl=' . $fgUrl . '&num=' . $num . '&paytype=N2&productid=4001004&rmb=' . $rmb . '&userid=' . $xl_user_id . '&version=v2.0';

        $sign = md5('1006' . $str . '8bq65ETvuW-DF{R');

        $url = 'https://agent-paycenter-ssl.xunlei.com/payorder/v3/ActOrder?version=v2.0&userid=' . $xl_user_id . '&activeid=4001004&productid=4001004&bizno=live&paytype=N2&num=' . $num . '&rmb=' . $rmb . '&fgUrl=' . $fgUrl . '&ext2=source%3Ah5_live%3Bpage_from%3Ah5_largepay_ios%3Bgorderid%3A' . $gorderid . '&_t=' . $t . '&sign=' . $sign;

        $daili = AgentUtil::shanchendaili($order['out_trade_no']);
        if ($daili['status'] != 0) {
            return ['code' => 103, 'msg' => '获取失败，请重试'];
        }

        $daili   = $daili['list'][0];
        $params  = [];
        $options = [
            CURLOPT_PROXY     => $daili['sever'],
            CURLOPT_PROXYPORT => $daili['port'],
        ];
        $result  = json_decode(Http::get($url, $params, $options), true);

        if ($result['data']['code'] == 200) {

            $data = [
                'xl_order_id' => $result['data']['orderId'],
                'xl_pay_data' => $result['data']['url'],
                'xl_user_id'  => $result['data']['userId'],
                'xl_gorderid' => $gorderid,
            ];
            Db::name('order')->where(['id' => $order['id'], 'pay_type' => '1014'])->update($data);

            return ['code' => 200, 'msg' => $data];
        }

        return ['code' => 102, 'msg' => '获取支付信息失败'];
    }

    /**
     * 瀚银支付宝
     *
     * 只需要挂一个码就行，所以不需要轮询
     *
     */
    public function handzfb($user_id, $pay_type, $acc_robin_rule, $amount) {

        $qrcode_list = Db::name('group_qrcode')->where(['user_id' => $user_id, 'acc_code' => $pay_type, 'status' => 1])->select();

        $qrcode_count = count($qrcode_list);
        if ($qrcode_count < 1) {
            return '';
        }

        $qrcode = $qrcode_list[mt_rand(0, $qrcode_count - 1)];

        return $qrcode;
    }

    //瀚银获取支付信息
    public function handPayData($order, $user_client_ip, $i) {

        $findorder = Db::name('order')->where(['id' => $order['id'], 'pay_type' => '1013'])->find();
        if (!empty($findorder['hand_pay_data'])) {
            $data['hand_pay_data'] = $findorder['hand_pay_data'];
            return ['code' => 200, 'msg' => $data];
        }

        $device_type = $this->get_device_type();
        $app_id      = Config::get('mchconf.hand_app_id');
        $privete_key = Config::get('mchconf.hand_privete_key');
        $public_key  = Config::get('mchconf.hand_privete_key');
        $amount      = $order['amount'] * 100;
        $timestamp   = $this->getMsectime();

        $postData = [
            'order_no'     => $order['out_trade_no'],
            'order_time'   => date('YmdHis'),
            'mode'         => 'live', //live/mock模式
            'app_id'       => $app_id,
            'pay_type'     => 'inst_alipay_qr',
            'pay_amt'      => $amount, //单位分
            'currency'     => 'cny',
            'subject'      => '商城',
            'product_data' => [
                'goods_title' => $order['pay_remark'],
                ' goods_desc' => '购买',
            ],
            'client_ip'    => $user_client_ip,
            'timestamp'    => $timestamp,
            'time_expire'  => date('YmdHis', $order['expire_time']),
        ];

        $template                    = 'app_id|timestamp|order_no|order_time|pay_amt';
        $handpaySignUtil             = new HandpaySignUtil();
        $handpaySignUtil->PrivateKey = str_replace('sk_live_', '', $privete_key);
        $handpaySignUtil->PublicKey  = str_replace('sk_live_', '', $public_key);
        $signature                   = $handpaySignUtil->generateSignature($template, $postData);

        $url     = 'https://api.checkout.1tpay.com/paymentgw/sdkcontrol/v1/payment/create';
        $options = [
            CURLOPT_HTTPHEADER => [
                'Content-Type:application/json;charset=utf-8',
                'Content-Length:' . strlen(json_encode($postData)),
                'app_id:' . $app_id,
                'timestamp:' . $timestamp,
                'signature:' . $signature,
            ]
        ];

        $response = json_decode(Http::post($url, json_encode($postData), $options), true);

        Utils::notifyLog($order['trade_no'], $order['out_trade_no'], '瀚银获取信息：' . json_encode($response, JSON_UNESCAPED_UNICODE));

        $hand_pay_data = json_decode($response['data'], true);

        if (!isset($response['data']) || !isset($hand_pay_data['id'])) {

            $data = [
                'out_trade_no' => $order['out_trade_no'],
                'trade_no'     => $order['trade_no'],
                'content'      => '次数' . $i . '瀚银获取信息失败' . json_encode($response, JSON_UNESCAPED_UNICODE),
            ];
            event('OrderError', $data);

            return ['code' => 100, 'msg' => '获取支付信息失败，请刷新页面重试'];
        }

        $data = [
            'hand_pay_data' => $response['data'],
            'hand_order_id' => $hand_pay_data['id'],
            'device_type'   => $device_type,
        ];
        Db::name('order')->where(['id' => $order['id']])->update($data);

        return ['code' => 200, 'msg' => $data];


    }

    //汇付获取支付信息
    public function hfPayData($order, $user_client_ip, $i) {

        $findorder = Db::name('order')->where(['id' => $order['id']])->find();
        if (!empty($findorder['xl_pay_data']) && !empty($findorder['xl_order_id'])) {
            $data['xl_pay_data'] = $findorder['xl_pay_data'];
            return ['code' => 200, 'msg' => $data];
        }

        $device_type = $this->get_device_type();

        $alipayList    = Db::name('alipay_zhuti')->where(['agent_id' => $order['agent_id'], 'status' => 1])->select();
        $alipay_count  = count($alipayList);
        $alipay_config = $alipayList[mt_rand(0, $alipay_count - 1)];
        if (empty($alipay_config)) {
            $data = [
                'out_trade_no' => $order['out_trade_no'],
                'trade_no'     => $order['trade_no'],
                'content'      => '未获取到汇付配置信息',
                'msg'          => '配置错误',
            ];
            event('OrderError', $data);
            return ['code' => 100, 'msg' => '配置错误！'];
        }


        $huifu_id    = $alipay_config['appid'];
        $product_id  = $alipay_config['product_id'];
        $public_key  = $alipay_config['public_key'];
        $private_key = $alipay_config['alipay_private_key'];
        $notify_url  = 'virgo://' . Utils::imagePath('/api/notify/huifuNotify', false);//回调通知地址

        $risk_check_info = json_encode([
            "riskMngInfo" => [
                "subTradeType" => "4300" //4300：支付；4330：充值
            ],
            "ipAddr"      => $user_client_ip,
        ]);

        $postData = [
            'req_seq_id'      => $order['out_trade_no'] . time(),//请求流水号,商户需保持唯一
            'req_date'        => date('Ymd'),
            'mer_ord_id'      => $order['out_trade_no'],
            'huifu_id'        => $huifu_id,
            'trade_type'      => 'A_NATIVE',
            'trans_amt'       => $order['amount'],
            'goods_desc'      => '商品购买',
            'risk_check_info' => $risk_check_info,
            'notify_url'      => $notify_url,
            //'time_expire'     => date('YmdHis', $order['expire_time']),
        ];

        //私钥加密
        $rsa  = new Rsa($public_key, $private_key);
        $sign = $rsa->private_encryptV2(json_encode($postData), OPENSSL_ALGO_SHA256);

        $postBody = [
            'sys_id'    => $huifu_id,
            'sign_type' => 'RSA2',
            'sign'      => $sign,
            'data'      => json_encode($postData)
        ];

        $url = 'https://spin.cloudpnr.com/top/trans/pullPayInfo';

        $options = [
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json;charset=utf-8',
                'product_id:' . $product_id,
                'charset: UTF-8',
                'version: 1.0.0',
            ]
        ];

        Log::write('发送参数:' . request()->ip() . '----' . json_encode($postBody, JSON_UNESCAPED_UNICODE), 'info');

        $response = Http::post($url, json_encode($postBody), $options);
        $result   = json_decode($response, true);

        Utils::notifyLog($order['trade_no'], $order['out_trade_no'], '汇付获取信息：' . $response);

        if (!isset($result['resp_code']) || !isset($result['data']) || $result['resp_code'] != 10000) {

            $data = [
                'out_trade_no' => $order['out_trade_no'],
                'trade_no'     => $order['trade_no'],
                'content'      => '次数' . $i . '汇付获取信息失败' . $response,
                'msg'          => '汇付获取支付信息失败',
            ];
            event('OrderError', $data);

            return ['code' => 100, 'msg' => '获取支付信息失败，请刷新页面重试'];
        }
        $res_data = json_decode($result['data'], true);

        if ($res_data['resp_code'] != '00000100') {
            $data = [
                'out_trade_no' => $order['out_trade_no'],
                'trade_no'     => $order['trade_no'],
                'content'      => $res_data['sub_resp_desc'],
                'msg'          => '汇付获取支付信息失败',
            ];
            event('OrderError', $data);

            return ['code' => 100, 'msg' => '获取支付信息失败，请刷新页面重试'];
        }


        $data = [
            'hc_pay_data'   => $response,
            'hand_pay_data' => $result['data'],
            'xl_pay_data'   => $res_data['qr_code'],
            'xl_order_id'   => $res_data['out_trade_no'],
            'device_type'   => $device_type,
            'xl_user_id'    => $alipay_config['id'],
        ];
        Db::name('order')->where(['id' => $order['id']])->update($data);

        return ['code' => 200, 'msg' => $data];


    }

    //快手直播-支付宝获取支付信息
    public function kszfbPayData($order, $ks_user_id) {

        $findorder = Db::name('order')->where(['id' => $order['id'], 'pay_type' => '1019'])->find();
        if (!empty($findorder['xl_order_id']) && !empty($findorder['xl_pay_data'])) {
            $data = [
                'xl_order_id' => $findorder['xl_order_id'],
                'xl_pay_data' => $findorder['xl_pay_data'],
                'xl_user_id'  => $findorder['xl_user_id'],
            ];
            return ['code' => 200, 'msg' => $data];
        }

        //1.创建订单 获取到单号
        /*$daili_re = AgentUtil::shanchendaili($order['out_trade_no'], 2);
        if($daili_re['status'] != 0){
           return ['code'=>103,'msg'=>'获取失败，请重试']; 
        }
        $daili  = $daili_re['list'][0];*/
        $ksCoin   = $order['amount'] * 10;
        $fen      = round($order['amount'] * 100, 0);
        $url      = 'https://pay.ssl.kuaishou.com/payAPI/k/pay/kscoin/deposit/nlogin/kspay/cashier';
        $postData = [
            'ksCoin'    => $ksCoin,
            'fen'       => $fen,
            'userId'    => $ks_user_id,
            'customize' => true,
            'kpn'       => 'KUAISHOU',
            'kpf'       => 'PC_WEB',
        ];

        $options      = [
            //CURLOPT_PROXY => $daili['sever'],
            //CURLOPT_PROXYPORT => $daili['port'],
            CURLOPT_HTTPHEADER => [
                'Content-Type:application/json;charset=UTF-8',
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/108.0.0.0 Safari/537.36',
            ]
        ];
        $result       = json_decode(Http::post($url, json_encode($postData), $options), true);
        $getOrderNoRe = $result;
        if ($result['result'] == 1 && $result['code'] != 'SUCCESS') {
            return ['code' => 103, 'msg' => '获取失败，请重试'];
        }

        $merchantId      = $result['merchantId'];
        $ks_out_order_no = $result['ksOrderId'];
        $redirect_url    = 'https://www.kuaishoupay.com/services/h5-recharge?f=OTHER-OTHER-OTHER&login_from_phone=1&order_id=' . $ks_out_order_no . '&platform=ALIPAY&amt=' . $order['amount'] . '&type=1';

        //2.下单
        //$daili  = $daili_re['list'][1];
        $url      = 'https://www.kuaishoupay.com/pay/order/h5/trade/create_pay_order';
        $postData = [
            'provider'     => 'ALIPAY',
            'merchant_id'  => $merchantId,
            'out_order_no' => $ks_out_order_no,
            'redirect_url' => $redirect_url,
        ];
        $options  = [
            //CURLOPT_PROXY => $daili['sever'],
            //CURLOPT_PROXYPORT => $daili['port'],
            CURLOPT_HTTPHEADER => [
                'User-Agent: Mozilla/5.0 (Linux; Android 10; SM-G981B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.162 Mobile Safari/537.36',
            ]
        ];
        $result   = json_decode(Http::post($url, $postData, $options), true);

        if ($result['result'] == 'SUCCESS' && $result['code'] == 'SUCCESS') {

            $data = [
                'xl_order_id'   => $ks_out_order_no,
                'xl_pay_data'   => $result['gateway_pay_param']['provider_config'],
                'xl_user_id'    => $ks_user_id,
                'xl_gorderid'   => $result['gateway_pay_param']['out_trade_no'],
                'hand_pay_data' => json_encode($getOrderNoRe),
            ];
            Db::name('order')->where(['id' => $order['id'], 'pay_type' => '1019'])->update($data);

            return ['code' => 200, 'msg' => $data];
        }

        return ['code' => 102, 'msg' => '获取支付信息失败'];
    }

    //皮皮直播-支付宝
    public function ppzbzfb($user_id, $pay_type, $acc_robin_rule, $amount) {
        $qrcode = '';
        //随机模式
        if ($acc_robin_rule == 1) {

            $qrcode_list = Db::name('group_qrcode')->where(['user_id' => $user_id, 'acc_code' => $pay_type, 'status' => 1])->select();

            $qrcode_count = count($qrcode_list);
            if ($qrcode_count < 1) {
                return '';
            }

            $qrcode = $qrcode_list[mt_rand(0, $qrcode_count - 1)];
            return $qrcode;
        }

        //顺序模式
        if ($acc_robin_rule == 2) {

            //查询今日总订单数
            $order_count_today = Db::name('order')->where(['user_id' => $user_id, 'pay_type' => $pay_type])->whereDay('createtime')->count();

            // 查询该用户所有通道数
            $count_alipay = Db::name('group_qrcode')->where(['user_id' => $user_id, 'acc_code' => $pay_type, 'status' => 1])->count();

            if ($count_alipay < 1) {
                return '';
            }

            if ($order_count_today < 1) {
                $order_count_today = 1;
            }


            $start = $order_count_today % $count_alipay;

            $qrcode = Db::name('group_qrcode')->where(['user_id' => $user_id, 'acc_code' => $pay_type, 'status' => 1])->order('id desc')->limit($start, 1)->select();
            $qrcode = $qrcode[0];

        }

        return $qrcode;
    }

    //皮皮直播-支付宝获取支付信息
    public function ppzbzfbPayData($order, $py_id) {

        $findorder = Db::name('order')->where(['id' => $order['id'], 'pay_type' => '1020'])->find();
        if (!empty($findorder['xl_order_id']) && !empty($findorder['xl_pay_data'])) {
            $data = [
                'xl_order_id' => $findorder['xl_order_id'],
                'xl_pay_data' => $findorder['xl_pay_data'],
                'xl_user_id'  => $findorder['xl_user_id'],
            ];
            return ['code' => 200, 'msg' => $data];
        }

        $num    = $order['amount'] * 10;
        $amount = round($order['amount'], 0);
        $params = [];

        $device_type = $this->get_device_type();
        if ($device_type == 'ios') {
            $h_dt       = 1;
            $header_arr = [
                'Content-Type:application/json;charset=UTF-8',
                'User-Agent:Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1',
            ];
        } else {
            $h_dt       = 0;
            $header_arr = [
                'Content-Type:application/json;charset=UTF-8',
                'User-Agent:Mozilla/5.0 (Linux; U; Android 12; zh-cn; IN2010 Build/RKQ1.211119.001) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/90.0.4430.61 Mobile Safari/537.36 HeyTapBrowser/40.8.9.1',
            ];
        }


        $daili = AgentUtil::shanchendaili($order['out_trade_no']);
        if ($daili['status'] == 0) {
            $daili   = $daili['list'][0];
            $options = [
                CURLOPT_PROXY      => $daili['sever'],
                CURLOPT_PROXYPORT  => $daili['port'],
                CURLOPT_HTTPHEADER => $header_arr,
            ];
        } else {
            $options = [
                CURLOPT_HTTPHEADER => $header_arr,
            ];
        }

        $url      = 'https://act-feature-live.ippzone.com/live_api/pay/webpay';
        $postData = [
            'pyid'  => $py_id,
            'money' => $amount,
            'type'  => 1,//1支付宝
            'h_dt'  => $h_dt,
        ];


        $post_res = Http::post($url, json_encode($postData), $options);
        $result   = json_decode($post_res, true);

        if ($result['errcode'] != 1) {
            $data = [
                'out_trade_no' => $order['out_trade_no'],
                'trade_no'     => $order['trade_no'],
                'content'      => '皮皮获取支付信息失败' . $post_res,
            ];
            event('OrderError', $data);

            return ['code' => 102, 'msg' => '获取支付信息失败'];
        }

        $json_data   = [
            'biz_content' => urlencode('{"out_trade_no":"' . $result['data']['order_id'] . '","product_code":"QUICK_WAP_WAY","quit_url":"https://act-feature-live.ippzone.com/recharge.html","subject":"皮皮搞笑-' . $num . '皮币","total_amount":' . $amount . '}'),
            'return_url'  => 'https%3A%2F%2Fdocs.open.alipay.com',
        ];
        $zfb_pay_url = $this->curl_post($result['data']['mweb_url'], $json_data, []);

        $data = [
            'xl_order_id'   => $result['data']['order_id'],
            'hand_pay_data' => $result['data']['mweb_url'],
            'xl_pay_data'   => $zfb_pay_url,
            'xl_user_id'    => $py_id,
            'device_type'   => $device_type,
        ];

        Db::name('order')->where('id', $order['id'])->update($data);

        return ['code' => 200, 'msg' => $data];
    }

    //YY直播-支付宝获取支付信息
    public function yyzbzfbPayData($order, $py_id) {

        $findorder = Db::name('order')->where(['id' => $order['id'], 'pay_type' => '1021'])->find();
        if (!empty($findorder['xl_order_id']) && !empty($findorder['xl_pay_data'])) {
            $data = [
                'xl_order_id' => $findorder['xl_order_id'],
                'xl_pay_data' => $findorder['xl_pay_data'],
                'xl_user_id'  => $findorder['xl_user_id'],
            ];
            return ['code' => 200, 'msg' => $data];
        }

        $num    = $order['amount'] * 10;
        $amount = round($order['amount'], 0);
        $params = [];

        $device_type = $this->get_device_type();
        if ($device_type == 'ios') {
            $h_dt       = 1;
            $header_arr = [
                'Content-Type:application/json;charset=UTF-8',
                'User-Agent:Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1',
            ];
        } else {
            $h_dt       = 0;
            $header_arr = [
                'Content-Type:application/json;charset=UTF-8',
                'User-Agent:Mozilla/5.0 (Linux; U; Android 12; zh-cn; IN2010 Build/RKQ1.211119.001) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/90.0.4430.61 Mobile Safari/537.36 HeyTapBrowser/40.8.9.1',
            ];
        }


        $daili = AgentUtil::shanchendaili($order['out_trade_no']);
        if ($daili['status'] == 0) {
            $daili   = $daili['list'][0];
            $options = [
                CURLOPT_PROXY      => $daili['sever'],
                CURLOPT_PROXYPORT  => $daili['port'],
                CURLOPT_HTTPHEADER => $header_arr,
            ];
        } else {
            $options = [
                CURLOPT_HTTPHEADER => $header_arr,
            ];
        }

        $url      = 'https://act-feature-live.ippzone.com/live_api/pay/webpay';
        $postData = [
            'pyid'  => $py_id,
            'money' => $amount,
            'type'  => 1,//1支付宝
            'h_dt'  => $h_dt,
        ];


        $post_res = Http::post($url, json_encode($postData), $options);
        $result   = json_decode($post_res, true);

        if ($result['errcode'] != 1) {
            $data = [
                'out_trade_no' => $order['out_trade_no'],
                'trade_no'     => $order['trade_no'],
                'content'      => '皮皮获取支付信息失败' . $post_res,
            ];
            event('OrderError', $data);

            return ['code' => 102, 'msg' => '获取支付信息失败'];
        }

        $json_data   = [
            'biz_content' => urlencode('{"out_trade_no":"' . $result['data']['order_id'] . '","product_code":"QUICK_WAP_WAY","quit_url":"https://act-feature-live.ippzone.com/recharge.html","subject":"皮皮搞笑-' . $num . '皮币","total_amount":' . $amount . '}'),
            'return_url'  => 'https%3A%2F%2Fdocs.open.alipay.com',
        ];
        $zfb_pay_url = $this->curl_post($result['data']['mweb_url'], $json_data, []);

        $data = [
            'xl_order_id'   => $result['data']['order_id'],
            'hand_pay_data' => $result['data']['mweb_url'],
            'xl_pay_data'   => $zfb_pay_url,
            'xl_user_id'    => $py_id,
            'device_type'   => $device_type,
        ];

        Db::name('order')->where('id', $order['id'])->update($data);

        return ['code' => 200, 'msg' => $data];
    }

    //酷秀直播-微信内付jspai 获取支付信息
    public function kxzbwxneifuPayData($order, $kx_user_id, $openId) {

        $findorder = Db::name('order')->where(['id' => $order['id'], 'pay_type' => '1023'])->find();
        if (!empty($findorder['xl_user_id']) && !empty($findorder['xl_pay_data'])) {
            $data = [

                'xl_pay_data' => $findorder['xl_pay_data'],
                'xl_user_id'  => $findorder['xl_user_id'],
            ];
            return ['code' => 200, 'msg' => $data];
        }

        $device_type = $this->get_device_type();
        if ($device_type == 'ios') {
            $ua = 'Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1';

        } else {
            $ua = 'Mozilla/5.0 (Linux; U; Android 12; zh-cn; IN2010 Build/RKQ1.211119.001) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/90.0.4430.61 Mobile Safari/537.36 HeyTapBrowser/40.8.9.1';

        }

        $imei       = Random::alnum(7) . Random::alnum(4) . Random::alnum(4) . Random::alnum(4) . Random::alnum(12);
        $timestamp  = $this->getMsectime();
        $y          = '792f28d6ff1f34ec702c08626d454b39';
        $requestId  = md5('web' . $imei . $timestamp . $y);
        $header_arr = [
            'Referer:https://www.17kuxiu.com/',
            'Origin:https://www.17kuxiu.com',
            'imei:' . $imei,
            'userId:' . $kx_user_id,
            'os:web',
            'version:1.0.0',
            'User-Agent:' . $ua,
            'timestamp:' . $timestamp,
            'requestId:' . $requestId,
            'loginType:2',
            'mobileModel:web',
            'Content-Type:application/x-www-form-urlencoded'
        ];

        /*$daili = AgentUtil::shanchendaili($order['out_trade_no']);
        if($daili['status'] == 0){
            $daili = $daili['list'][0];
            $options = [
                CURLOPT_PROXY => $daili['sever'],
                CURLOPT_PROXYPORT => $daili['port'],
                CURLOPT_HTTPHEADER => $header_arr,
            ];
        }else{
            $options = [
                CURLOPT_HTTPHEADER => $header_arr,
            ];
        }*/
        $options = [
            CURLOPT_HTTPHEADER => $header_arr,
        ];
        $url     = 'https://api.17kuxiu.com/payment/order/hsqWebchatJsApi';

        $postData = [
            'amount'           => round($order['amount'], 0),
            'rechargeOrigin'   => 1,
            'openId'           => $openId,
            'successReturnUrl' => 'https%3A%2F%2Fwww.17kuxiu.com%2Fh5%2FwxVipcn%2Findex.html%3Ftest%3D1',
        ];


        $post_res = Http::post($url, $postData, $options);
        $result   = json_decode($post_res, true);

        if ($result['retCode'] != 200) {
            $data = [
                'out_trade_no' => $order['out_trade_no'],
                'trade_no'     => $order['trade_no'],
                'content'      => '酷秀获取支付信息失败' . $post_res,
            ];
            event('OrderError', $data);

            return ['code' => 102, 'msg' => '获取支付信息失败'];
        }

        $data = [
            'xl_pay_data'   => $result['data'],
            'xl_user_id'    => $kx_user_id,
            'device_type'   => $device_type,
            'hand_pay_data' => $post_res,
        ];

        Db::name('order')->where('id', $order['id'])->update($data);

        return ['code' => 200, 'msg' => $data];
    }

    //酷秀直播-微信h5 获取支付信息
    public function kxzbwxh5PayData($order, $kx_user_id) {

        $findorder = Db::name('order')->where(['id' => $order['id'], 'pay_type' => '1023'])->find();
        if (!empty($findorder['xl_user_id']) && !empty($findorder['xl_pay_data'])) {
            $data = [

                'xl_pay_data' => $findorder['xl_pay_data'],
                'xl_user_id'  => $findorder['xl_user_id'],
            ];
            return ['code' => 200, 'msg' => $data];
        }

        $device_type = $this->get_device_type();
        if ($device_type == 'ios') {
            $device_type = 'IOS';
            $ua          = 'Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1';

        } else {
            $device_type = 'Android';
            $ua          = 'Mozilla/5.0 (Linux; U; Android 12; zh-cn; IN2010 Build/RKQ1.211119.001) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/90.0.4430.61 Mobile Safari/537.36 HeyTapBrowser/40.8.9.1';

        }

        $imei       = strtolower(Random::alnum(7) . '-' . Random::alnum(4) . '-' . Random::alnum(4) . '-' . Random::alnum(4) . '-' . Random::alnum(12));
        $timestamp  = $this->getMsectime();
        $y          = '792f28d6ff1f34ec702c08626d454b39';
        $requestId  = md5('web' . $imei . $timestamp . $y);
        $header_arr = [
            'Referer:https://www.17kuxiu.com/',
            'Origin:https://www.17kuxiu.com',
            'imei:' . $imei,
            'userId:' . $kx_user_id,
            'os:web',
            'version:1.0.0',
            'User-Agent:' . $ua,
            'timestamp:' . $timestamp,
            'requestId:' . $requestId,
            'loginType:2',
            'mobileModel:web',
            'Content-Type:application/x-www-form-urlencoded',
            //'sec-ch-ua-platform: '
        ];

        /*$daili = AgentUtil::shanchendaili($order['out_trade_no']);
        if($daili['status'] == 0){
            $daili = $daili['list'][0];
            $options = [
                CURLOPT_PROXY => $daili['sever'],
                CURLOPT_PROXYPORT => $daili['port'],
                CURLOPT_HTTPHEADER => $header_arr,
            ];
        }else{
            $options = [
                CURLOPT_HTTPHEADER => $header_arr,
            ];
        }*/
        $options = [
            CURLOPT_HTTPHEADER => $header_arr,
        ];
        $url     = 'https://hapi.17kuxiu.com/payment/order/webchatH5';
        //https://hapi.17kuxiu.com/payment/order/ali4webchatPublic 支付宝h5
        //amount=6&majordomoId=&rechargeOrigin=2&company=kuxiu
        $postData = [
            'amount'         => round($order['amount'], 0),
            'majordomoId'    => '',
            'rechargeOrigin' => '',
            'company'        => 'kuxiu',
        ];


        $post_res = Http::post($url, $postData, $options);
        $result   = json_decode($post_res, true);

        if ($result['retCode'] != 200) {
            $data = [
                'out_trade_no' => $order['out_trade_no'],
                'trade_no'     => $order['trade_no'],
                'content'      => '酷秀获取支付信息失败' . $post_res,
            ];
            event('OrderError', $data);

            return ['code' => 102, 'msg' => '获取支付信息失败'];
        }

        $data = [
            'xl_pay_data'   => $result['data'],
            'xl_user_id'    => $kx_user_id,
            'device_type'   => $device_type,
            'hand_pay_data' => $post_res,
        ];

        Db::name('order')->where('id', $order['id'])->update($data);

        return ['code' => 200, 'msg' => $data];
    }

    //快手直播-微信h5 获取支付信息
    /*public function ksweixinPayData($order, $ks_user_cookie){
        
        $findorder = Db::name('order')->where(['id'=>$order['id']])->find();
        if(!empty($findorder['xl_order_id']) && !empty($findorder['xl_pay_data']) && !empty($findorder['hc_pay_data'])){
            $data = [
                'xl_order_id' => $findorder['xl_order_id'],
                'xl_pay_data' => $findorder['xl_pay_data'],
                'xl_user_id'  => $findorder['xl_user_id'],
                'hc_pay_data' => $findorder['hc_pay_data'],
            ];
            return ['code'=>200,'msg'=>$data];
        }
        
        $cookie = $ks_user_cookie;
        
        //1.创建订单 获取到单号
        $ksCoin = round($order['amount']*10, 0);
        $fen    = round($order['amount']*100, 0);
        $url    = 'https://www.kuaishoupay.com/rest/wd/pay/kscoin/deposit/kspay/cashier?kpn=KUAISHOU&kpf=OUTSIDE_IOS_H5';
        $postData  = [
            'source' => 'IOS_H5_NORMAL',
            'ksCoin' => $ksCoin,
            'fen' => $fen,
            'customize' => false,
            'kspayProvider' => 'WECHAT',
            'kspayProviderChannelType' => 'NORMAL',
        ];
        
        $options = [
            //CURLOPT_PROXY => $daili['sever'],
            //CURLOPT_PROXYPORT => $daili['port'],
            CURLOPT_HTTPHEADER =>[
                'Cookie:'.$cookie,
                'Content-Type:application/json;charset=UTF-8',
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/108.0.0.0 Safari/537.36',
            ]
        ];
        $result = json_decode(Http::post($url, json_encode($postData), $options),true);
        $getOrderNoRe = $result;
        if($result['result'] == 1 && $result['code'] != 'SUCCESS'){
            return ['code'=>103,'msg'=>'获取失败，请重试']; 
        }
        
        $merchantId = $result['merchantId'];
        $ksOrderId  = $result['ksOrderId'];
        $redirect_url = urlencode('https://www.kuaishoupay.com/services/h5-recharge?order_id='.$ksOrderId.'&platform=WECHAT&amt='.$ksCoin.'&type=1');
        
        //2.预下单
        $url = 'https://www.kuaishoupay.com/pay/order/h5/trade/cashier';
        $postData = [
            'provider'     => 'WECHAT',
            'merchant_id'  => $merchantId,
            'out_order_no' => $ksOrderId,
            'redirect_url' => $redirect_url,
        ];
        $options = [
            CURLOPT_HTTPHEADER =>[
                'Cookie:'.$cookie,
                'User-Agent: Mozilla/5.0 (Linux; Android 10; SM-G981B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.162 Mobile Safari/537.36',
            ]
        ];
        $result = json_decode(Http::post($url, $postData, $options),true);
        
        //3.下单
        $url = 'https://www.kuaishoupay.com/pay/order/h5/trade/create_pay_order';
        $redirect_url = urlencode('https://www.kuaishoupay.com/services/h5-recharge?order_id='.$ksOrderId.'&platform=WECHAT&amt='.$ksCoin.'&type=1&pay_amount='.$fen);
        
        $postData = [
            'provider'     => 'WECHAT',
            'merchant_id'  => $merchantId,
            'out_order_no' => $ksOrderId,
            'redirect_url' => $redirect_url,
        ];
        $res = Http::post($url, $postData, $options);
        $result = json_decode($res,true);
        if($result['result'] !== 'SUCCESS' || $result['code'] !== 'SUCCESS'){
            $data = [
                'out_trade_no' => $order['out_trade_no'],
                'trade_no'     => $order['trade_no'],
                'content'      => '快手微信支付信息失败'.$res,
            ];
            event('OrderError', $data);
            
            return ['code' => 102, 'msg'=>'获取支付信息失败'];
            
        }
        
        //4. 请求获取微信跳转支付信息
        $wx_url = json_decode($result['gateway_pay_param']['provider_config'], true);
        $wx_url = urldecode($wx_url['mweb_url']);
        $options = [
            CURLOPT_HTTPHEADER =>[
                'Referer:https://www.kuaishoupay.com/',
                'Host:wx.tenpay.com',
            ]
        ];
        $wx_res = Http::get($wx_url, [], $options);
        $regex='#"weixin(.*)"#';
        preg_match($regex,$wx_res,$matchArr);
        
        $wx_pay_url = str_replace('"', '',$matchArr[0]);
        

        $data = [
            'xl_order_id' => $ksOrderId,
            'xl_pay_data' => $wx_url,
            'xl_user_id'  => $ks_user_id,
            'xl_gorderid' => $result['gateway_pay_param']['merchant_id'],
            'hc_pay_data'  => $wx_pay_url,
            'hand_pay_data'  => $res,
        ];
        Db::name('order')->where(['id'=>$order['id']])->update($data);
        
        
        
        return ['code'=>200,'msg'=>$data];
        
    }*/

    //百战-支付宝获取支付信息
    public function yybaizhanzfbPayData($order, $yy_id) {

        $findorder = Db::name('order')->where(['id' => $order['id']])->find();
        if (!empty($findorder['xl_order_id']) && !empty($findorder['xl_pay_data'])) {
            $data = [
                'xl_order_id' => $findorder['xl_order_id'],
                'xl_pay_data' => $findorder['xl_pay_data'],
                'xl_user_id'  => $findorder['xl_user_id'],
            ];
            return ['code' => 200, 'msg' => $data];
        }

        $ts     = $this->getMsectime();
        $amount = intval($order['amount']);

        $device_type = Utils::getClientOsInfo();
        if ($device_type == 'iphone') {
            $device_type = 'iOS';
        } else {
            $device_type = 'Android';
        }

        $header_arr = [
            'Host: turnover.baizhanlive.com',
            'Referer: https://www.baizhanlive.com/',
            'sec-ch-ua-platform: "' . $device_type . '"',
        ];


        /*$daili = AgentUtil::shanchendaili($order['out_trade_no']);
        if($daili['status'] == 0){
            $daili = $daili['list'][0];
            $options = [
                CURLOPT_PROXY => $daili['sever'],
                CURLOPT_PROXYPORT => $daili['port'],
                CURLOPT_HTTPHEADER => $header_arr,
            ];
        }else{
            $options = [
                CURLOPT_HTTPHEADER => $header_arr,
            ];
        }*/

        $options = [
            CURLOPT_HTTPHEADER => $header_arr,
        ];

        $return_url = urlencode('https://www.baizhanlive.com/web/wallet/m_pay_ext_aDVfYnps.html?account=' . $yy_id . '&accountType=1&orderId=${orderId}');

        //$url = 'https://turnover.baizhanlive.com/charge_currency/charge?subappid=0&sid=0&ssid=0&currency=71&payMethod=Wap&payChannel=Zfb&amount=100&configId=3980006&returnUrl='.$return_url.'&userAccount='.$yy_id.'&userAccountType=1&seq='.$ts.'&expand=%7B%22turnoverOS%22%3A%22'.$device_type.'%22%7D&appid=39&usedChannel=10015';

        $url = 'https://turnover.baizhanlive.com/charge_currency/charge?subappid=0&sid=0&ssid=0&currency=71&payMethod=Wap&payChannel=Zfb&amount=' . $amount . '&configId=0&userAccount=' . $yy_id . '&userAccountType=1&seq=' . $ts . '&expand=%7B%22turnoverOS%22%3A%22' . $device_type . '%22%7D&appid=39&usedChannel=10015';

        $res = Http::get($url, [], $options);
        //halt($res);
        $result = json_decode($res, true);

        if ($result['result'] != 1) {
            $data = [
                'out_trade_no' => $order['out_trade_no'],
                'trade_no'     => $order['trade_no'],
                'content'      => '百战支付宝获取支付信息失败' . $res,
            ];
            event('OrderError', $data);

            return ['code' => 102, 'msg' => '获取支付信息失败'];
        }

        $data = [
            'xl_order_id'   => $result['orderId'],
            'xl_pay_data'   => $result['payUrl'],
            'xl_user_id'    => $yy_id,
            'hand_pay_data' => $res,
            'device_type'   => $device_type,
        ];

        Db::name('order')->where('id', $order['id'])->update($data);

        return ['code' => 200, 'msg' => $data];
    }

    //百战-微信获取支付信息
    public function yybaizhanweixinPayData($order, $yy_id) {

        $findorder = Db::name('order')->where(['id' => $order['id']])->find();
        if (!empty($findorder['xl_order_id']) && !empty($findorder['xl_pay_data'])) {
            $data = [
                'xl_order_id' => $findorder['xl_order_id'],
                'xl_pay_data' => $findorder['xl_pay_data'],
                'xl_user_id'  => $findorder['xl_user_id'],
            ];
            return ['code' => 200, 'msg' => $data];
        }

        $ts     = $this->getMsectime();
        $amount = intval($order['amount']);

        $device_type = Utils::getClientOsInfo();
        if ($device_type == 'iphone') {
            $device_type = 'iOS';
        } else {
            $device_type = 'Android';
        }

        $header_arr = [
            'Host: turnover.baizhanlive.com',
            'Referer: https://www.baizhanlive.com/',
            'sec-ch-ua-platform: "' . $device_type . '"',
        ];


        /*$daili = AgentUtil::shanchendaili($order['out_trade_no']);
        if($daili['status'] == 0){
            $daili = $daili['list'][0];
            $options = [
                CURLOPT_PROXY => $daili['sever'],
                CURLOPT_PROXYPORT => $daili['port'],
                CURLOPT_HTTPHEADER => $header_arr,
            ];
        }else{
            $options = [
                CURLOPT_HTTPHEADER => $header_arr,
            ];
        }*/

        $options = [
            CURLOPT_HTTPHEADER => $header_arr,
        ];

        $return_url = urlencode('https://www.baizhanlive.com/web/wallet/m_pay_ext_aDVfYnps.html?account=' . $yy_id . '&accountType=1&orderId=${orderId}');

        //$url = 'https://turnover.baizhanlive.com/charge_currency/charge?subappid=0&sid=0&ssid=0&currency=71&payMethod=Wap&payChannel=Zfb&amount=100&configId=3980006&returnUrl='.$return_url.'&userAccount='.$yy_id.'&userAccountType=1&seq='.$ts.'&expand=%7B%22turnoverOS%22%3A%22'.$device_type.'%22%7D&appid=39&usedChannel=10015';

        $url = 'https://turnover.baizhanlive.com/charge_currency/charge?subappid=0&sid=0&ssid=0&currency=71&payMethod=Wap&payChannel=Weixin&amount=' . $amount . '&configId=0&userAccount=' . $yy_id . '&userAccountType=1&seq=' . $ts . '&expand=%7B%22turnoverOS%22%3A%22' . $device_type . '%22%7D&appid=39&usedChannel=10015';

        $res = Http::get($url, [], $options);
        //halt($res);
        $result = json_decode($res, true);

        if ($result['result'] != 1) {
            $data = [
                'out_trade_no' => $order['out_trade_no'],
                'trade_no'     => $order['trade_no'],
                'content'      => '百战微信获取支付信息失败' . $res,
            ];
            event('OrderError', $data);

            return ['code' => 102, 'msg' => '获取支付信息失败'];
        }

        $data = [
            'xl_order_id'   => $result['orderId'],
            'xl_pay_data'   => $result['payUrl'],
            'xl_user_id'    => $yy_id,
            'hand_pay_data' => $res,
            'device_type'   => $device_type,
        ];

        Db::name('order')->where('id', $order['id'])->update($data);

        return ['code' => 200, 'msg' => $data];
    }


    //g买卖-支付宝
    public function gmmzfb($user_id, $pay_type, $acc_robin_rule, $amount) {

        $qrcode = '';
        //随机模式
        if ($acc_robin_rule == 1) {

            $qrcode_list = Db::name('group_qrcode')->where(['user_id' => $user_id, 'acc_code' => $pay_type, 'status' => 1])->select();

            $qrcode_count = count($qrcode_list);
            if ($qrcode_count < 1) {
                return '';
            }

            $qrcode_list_new = [];

            //查看这个号半小时内下了几单
            $stare_time = date('Y-m-d H:i:s', time() - (10 * 60) + 10);
            $end_time   = date('Y-m-d H:i:s', time());

            foreach ($qrcode_list as $k => $v) {
                $num = Db::name('order')->where(['qrcode_id' => $v['id'], 'pay_type' => $pay_type])->whereBetweenTime('createtime', $stare_time, $end_time)->count();
                if ($num < 3) {
                    $qrcode_list_new[] = $v;
                }
            }
            $qrcode_count = count($qrcode_list_new);
            $qrcode       = $qrcode_list_new[mt_rand(0, $qrcode_count - 1)];
            return $qrcode;
        }

        //顺序模式
        if ($acc_robin_rule == 2) {

            //查询今日总订单数
            $order_count_today = Db::name('order')->where(['user_id' => $user_id, 'pay_type' => $pay_type])->whereDay('createtime')->count();

            // 查询该用户所有通道数
            $count_alipay = Db::name('group_qrcode')->where(['user_id' => $user_id, 'acc_code' => $pay_type, 'status' => 1])->count();

            if ($count_alipay < 1) {
                return '';
            }

            if ($order_count_today < 1) {
                $order_count_today = 1;
            }


            //$start = $order_count_today%$count_alipay;
            //$qrcode = Db::name('group_qrcode')->where(['user_id'=>$user_id,'acc_code'=>$pay_type,'status'=>1])->order('id desc')->limit($start,1)->select();


            //先查全部通道，遍历过滤半小时3单的号，组成新数组，取下一个
            $qrcode_list = Db::name('group_qrcode')->where(['user_id' => $user_id, 'acc_code' => $pay_type, 'status' => 1])->order('id asc')->select();
            //查看这个号半小时内下了几单
            $stare_time = date('Y-m-d H:i:s', time() - (10 * 60) + 10);
            $end_time   = date('Y-m-d H:i:s', time());
            $qrcode_ids = [];

            foreach ($qrcode_list as $k => $v) {
                $num = Db::name('order')->where(['qrcode_id' => $v['id'], 'pay_type' => $pay_type])->whereBetweenTime('createtime', $stare_time, $end_time)->count();
                if ($num < 3) {
                    $qrcode_ids[] = $v['id'];
                }
            }
            $count_alipay = count($qrcode_ids);
            if ($count_alipay < 1) {
                return '';
            }
            $start = $order_count_today % $count_alipay;

            $qrcode = Db::name('group_qrcode')->where('id', 'in', $qrcode_ids)->order('id asc')->limit($start, 1)->select();

            return $qrcode[0];
        }

        return $qrcode;
    }


    //g买卖-支付宝获取支付信息
    public function gmmzfbPayDataPc($order, $qrcode_id, $gmm_recharge_id, $gmm_cookie, $gmm_pay_cookie, $i) {

        $findorder = Db::name('order')->where(['id' => $order['id']])->find();
        if (!empty($findorder['xl_order_id']) && !empty($findorder['xl_pay_data'])) {
            $data = [
                'xl_order_id' => $findorder['xl_order_id'],
                'xl_pay_data' => $findorder['xl_pay_data'],
                'xl_user_id'  => $findorder['xl_user_id'],
            ];
            return ['code' => 200, 'msg' => $data];
        }

        /*$header_arr = [
            'Cookie: useQr=true; JSESSIONID=514A38E0D592CE3AF8625368E1DE7F40; ctoken=HnE4Lk8t8fJrWzV5; _uab_collina=167724812649054248112652; mobileSendTime=-1; credibleMobileSendTime=-1; ctuMobileSendTime=-1; riskMobileBankSendTime=-1; riskMobileAccoutSendTime=-1; riskMobileCreditSendTime=-1; riskCredibleMobileSendTime=-1; riskOriginalAccountMobileSendTime=-1; cna=f7R/HBcuXCMCAXeIcHwGlnvC; zone=RZ41B; ALIPAYJSESSIONID=RZ55NYvaQHLCLV2KsKjdNpADw78VKisuperapiGZ00RZ41; _umdata=G2A53720F9F73FE8286F5F625EF7E9C87B0AC08; JSESSIONID=514A38E0D592CE3AF8625368E1DE7F40; spanner=T1Huk7qPDWnpFelks+N7jG+TZraaQsgQ4EJoL7C0n0A=; rtk=tN8FS2DUnUd+FjSXhNzO30sUGfXG2tqBpBdvOBT2zhbLz0M8B7q',
        ];
        
        $options = [
            CURLOPT_HTTPHEADER => $header_arr,
        ];
        $alipay_res = Http::get('https://mapi.alipay.com/gateway.do?seller_email=c2cplatform%40souzhuangbei.com&_input_charset=utf-8&subject=G%E4%B9%B0%E5%8D%96-%E8%99%9A%E6%8B%9F%E7%82%B9%E5%88%B8%E4%B8%8D%E5%8F%AF%E9%80%80%E6%8D%A2%2C%E8%B0%A8%E9%98%B2%E8%AF%88%E9%AA%97%21&sign=a7c89b831311c18cebc3bd50dd0d8b44&notify_url=http%3A%2F%2Fpayment.billing.sdo.com%2Falipay%2Fpuregateway%2Fnotify&payment_type=1&out_trade_no=791000123PP021230224230251000002&partner=2088801940275530&service=create_direct_pay_by_user&seller_account_name=c2cplatform%40souzhuangbei.com&total_fee=97.40&anti_phishing_key=KP9RyK24dqEkEaTipg%3D%3D&exter_invoke_ip=113.110.166.130&return_url=https%3A%2F%2Fqb.sdo.com%2Fpc%2Fpay%2Fpayment.html%3ForderId%3DDQMO9100000000018611677250937783%26backUrl%3Dhttps%25253A%25252F%25252Fwww.gmmsj.com%26detailUrl%3Dhttps%25253A%25252F%25252Fwww.gmmsj.com%25252Fpc%25252Fbuy%25252Fmatchorderdetail.html%25253Fgoods_type%25253D90000%252526order_id%25253DDQMO9100000000018611677250937783%252526from%25253Dqb&sign_type=MD5&seller_id=2088801940275530', [], []);

        halt($alipay_res);*/
        /*//再访问支付宝的url，拿到qrcode
        $alipayUrl= 'https://mapi.alipay.com/gateway.do?seller_email=c2cplatform%40souzhuangbei.com&_input_charset=utf-8&subject=G%E4%B9%B0%E5%8D%96-%E8%99%9A%E6%8B%9F%E7%82%B9%E5%88%B8%E4%B8%8D%E5%8F%AF%E9%80%80%E6%8D%A2%2C%E8%B0%A8%E9%98%B2%E8%AF%88%E9%AA%97%21&sign=a7c89b831311c18cebc3bd50dd0d8b44&notify_url=http%3A%2F%2Fpayment.billing.sdo.com%2Falipay%2Fpuregateway%2Fnotify&payment_type=1&out_trade_no=791000123PP021230224230251000002&partner=2088801940275530&service=create_direct_pay_by_user&seller_account_name=c2cplatform%40souzhuangbei.com&total_fee=97.40&anti_phishing_key=KP9RyK24dqEkEaTipg%3D%3D&exter_invoke_ip=113.110.166.130&return_url=https%3A%2F%2Fqb.sdo.com%2Fpc%2Fpay%2Fpayment.html%3ForderId%3DDQMO9100000000018611677250937783%26backUrl%3Dhttps%25253A%25252F%25252Fwww.gmmsj.com%26detailUrl%3Dhttps%25253A%25252F%25252Fwww.gmmsj.com%25252Fpc%25252Fbuy%25252Fmatchorderdetail.html%25253Fgoods_type%25253D90000%252526order_id%25253DDQMO9100000000018611677250937783%252526from%25253Dqb&sign_type=MD5&seller_id=2088801940275530';
        $alipay_res = $this->curl_get($alipayUrl,[]);
        $alipay_res = $this->curl_get($alipay_res,[]);
        
        $header_arr = [
            'Cookie: useQr=false; JSESSIONID=E98CAC99A99DA7283B9DB65DC2B63DA6; cna=f+01G3yXTAMCAXjqTZOpVu4Y; _uab_collina=166951284499750145311418; mobileSendTime=-1; credibleMobileSendTime=-1; ctuMobileSendTime=-1; riskMobileBankSendTime=-1; riskMobileAccoutSendTime=-1; riskMobileCreditSendTime=-1; riskCredibleMobileSendTime=-1; riskOriginalAccountMobileSendTime=-1; _umdata=G214BBCB8C24F5DE0A67B700CE76767EF089BF0; unicard1.vm="K1iSL1Da55TWYosUIxTqdw=="; NEW_ALIPAY_TIP=1; ctoken=H9E2c09gXqD5sDZi; JSESSIONID=E98CAC99A99DA7283B9DB65DC2B63DA6; spanner=5SDJEodpZr2EjDj6Hpga6aROVyIfj5+GXt2T4qEYgj0=; zone=RZ41A; ALIPAYJSESSIONID=RZ42XIUgLaTFFgfg7bHbHvOjsjwQ4junitradeadapterGZ00RZ41; rtk=17dJMwQgafktVF+U+7dF0n4VJNefV9rakaWRo/MwaDOSufJPaNc',
        ];
        
        $options = [
            CURLOPT_HTTPHEADER => $header_arr,
        ];
        
        $alipay_res = Http::get($alipay_res, [], $options);

        $regex = '/<input name="qrCode".*?value="(.*?)"/i';
        
        preg_match($regex,$alipay_res,$matchArr);
        halt($alipay_res,$matchArr);*/

        $ts        = $this->getMsectime();
        $amount    = intval($order['amount']);
        $device_id = 'v2_MzXUoDr1aFzO4iJyZi2qBa6UR4qeUhCA';

        //比如充值金额100 匹配单价1 数量就是100/1
        $price     = 1; //点券单价
        $quantity  = $amount / $price;
        $pay_price = bcmul(bcmul($quantity, $price, 1), 100, 1);


        $device_type = Utils::getClientOsInfo();

        $header_arr = [
            'Host: www.gmmsj.com',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/109.0.0.0 Safari/537.36',
            'sec-ch-ua-platform: "Windows"',
            'Cookie: ' . $gmm_cookie,
        ];


        /*$daili = AgentUtil::shanchendaili($order['out_trade_no']);
        if($daili['status'] == 0){
            $daili = $daili['list'][0];
            $options = [
                CURLOPT_PROXY => $daili['sever'],
                CURLOPT_PROXYPORT => $daili['port'],
                CURLOPT_HTTPHEADER => $header_arr,
            ];
        }else{
            $options = [
                CURLOPT_HTTPHEADER => $header_arr,
            ];
        }*/

        $options = [
            CURLOPT_HTTPHEADER => $header_arr,
        ];

        $url = 'https://www.gmmsj.com/gatew/matchgw/wantbuygoods?app_version=1.0.0.26719&device_id=' . $device_id . '&system_deviceId=' . $device_id . '&app_channel=chrome&src_code=7&b_account=' . $gmm_recharge_id . '&ext_goods_type=90000&game_id=791000218&quantity=' . $quantity . '&price=' . $price;

        $res = Http::get($url, [], $options);
        //halt($res);
        $result = json_decode($res, true);

        if ($result['return_code'] != 0) {
            $data = [
                'out_trade_no' => $order['out_trade_no'],
                'trade_no'     => $order['trade_no'],
                'content'      => $i . '-1-gmm下单失败' . $res,
            ];
            event('OrderError', $data);


            Db::name('group_qrcode')->where('id', $qrcode_id)->update(['status' => 0, 'remark' => $result['return_message']]);

            return ['code' => 102, 'msg' => '获取支付信息失败'];
        }
        $book_id    = $result['data']['book_id'];
        $s_mid      = $result['data']['s_mid'];
        $goods_name = $result['data']['goods_name'];
        $gbao_url   = $result['data']['gbao_url'];
        $order_id   = $result['data']['order_id'];

        $data = [
            'xl_order_id' => $order_id,
            'xl_user_id'  => $gmm_recharge_id,
            'hc_pay_data' => $res,
            'device_type' => $device_type,
        ];

        Db::name('order')->where('id', $order['id'])->update($data);

        $refre = 'https://qb.sdo.com/pc/pay/payment.html' . $gbao_url . '&listUrl=https%3A%2F%2Fwww.gmmsj.com%2Fpc%2Fmy%2Fbuylist.html&backUrl=https%3A%2F%2Fwww.gmmsj.com&detailUrl=https%3A%2F%2Fwww.gmmsj.com%2Fpc%2Fbuy%2Fmatchorderdetail.html%3Fgoods_type%3D90000%26order_id%3D' . $order_id . '%26from%3Dqb&tradeMode=undefined&gameAppId=791000218';

        $topay_url = 'https://qb.sdo.com/qbInf/alipayPayBegin?traceNo=' . $order_id . '&itemType=9&userIdDest=' . $s_mid . '&orderType=9&subject=' . urlencode($goods_name) . '&itemDetail=%7B%22envType%22%3A%22%22%7D&itemId=' . $book_id . '&price=' . $pay_price . '&endpointTypeSrc=3&couponIds=&redirectUrl=https%3A%2F%2Fqb.sdo.com%2Fpc%2Fpay%2Fpayment.html%3ForderId%3D' . $order_id . '%26backUrl%3Dhttps%25253A%25252F%25252Fwww.gmmsj.com%26detailUrl%3Dhttps%25253A%25252F%25252Fwww.gmmsj.com%25252Fpc%25252Fbuy%25252Fmatchorderdetail.html%25253Fgoods_type%25253D90000%252526order_id%25253D' . $order_id . '%252526from%25253Dqb&reqFrom=3&gameAppId=791000218';

        //halt($refre,$topay_url);
        $header_arr = [
            'Host: qb.sdo.com',
            'Referer: ' . $refre,
            'Cookie: ' . $gmm_pay_cookie,
        ];

        $options = [
            CURLOPT_HTTPHEADER => $header_arr,
        ];

        $res2 = Http::get($topay_url, [], $options);

        $result2 = json_decode($res2, true);
        if (!is_array($result2) || $result2['return_code'] != 0) {
            $data = [
                'out_trade_no' => $order['out_trade_no'],
                'trade_no'     => $order['trade_no'],
                'content'      => $i . '-2-gmm获取支付信息失败' . $res2,
            ];
            event('OrderError', $data);

            return ['code' => 102, 'msg' => '获取支付信息失败'];
        }

        $alipayUrl = $result2['data']['alipayUrl'];
        $orderId   = $result2['data']['orderId'];


        /*//再访问支付宝的url，拿到qrcode
        $alipay_res = $this->curl_get($alipayUrl,[]);
        $alipay_res = $this->curl_get($alipay_res,[]);
        
        $header_arr = [
            'Cookie: useQr=false; JSESSIONID=6DF42A9BA10FE88C47606B6D6C65B43E; cna=f+01G3yXTAMCAXjqTZOpVu4Y; _uab_collina=166951284499750145311418; mobileSendTime=-1; credibleMobileSendTime=-1; ctuMobileSendTime=-1; riskMobileBankSendTime=-1; riskMobileAccoutSendTime=-1; riskMobileCreditSendTime=-1; riskCredibleMobileSendTime=-1; riskOriginalAccountMobileSendTime=-1; _umdata=G214BBCB8C24F5DE0A67B700CE76767EF089BF0; unicard1.vm="K1iSL1Da55TWYosUIxTqdw=="; NEW_ALIPAY_TIP=1; ctoken=H9E2c09gXqD5sDZi; JSESSIONID=6DF42A9BA10FE88C47606B6D6C65B43E; spanner=DO8uxorvAEewMTE3RUqGVVlZtw17IFk74EJoL7C0n0A=; zone=RZ55A; ALIPAYJSESSIONID=RZ42XIUgLaTFFgfg7bHbHvOjsjwQ4junitradeadapterGZ00RZ55; rtk=P2475N3mwin8C+FGF2E+cqN6nwogoIHTyTmHQJBNJY/iIf+1Tw0',
        ];
        
        $options = [
            CURLOPT_HTTPHEADER => $header_arr,
        ];
        
        $alipay_res = Http::get($alipay_res, [], []);

        $regex = '/<input name="qrCode".*?value="(.*?)"/i';
        
        preg_match($regex,$alipay_res,$matchArr);
        halt($alipayUrl,$alipay_res,$matchArr);
        $alipay_qrcode = $matchArr[1];*/


        $data = [
            'xl_pay_data'   => $alipayUrl,
            'hand_pay_data' => $res2,
            'hand_order_id' => $orderId,
        ];

        Db::name('order')->where('id', $order['id'])->update($data);

        return ['code' => 200, 'msg' => $data];
    }

    //g买卖app-支付宝获取支付信息
    public function gmmzfbPayDataPhone($order, $qrcode, $i) {

        $qrcode_id      = $qrcode['id'];
        $b_account      = $qrcode['zfb_pid'];
        $gmm_cookie     = $qrcode['xl_cookie'];
        $gmm_pay_cookie = $qrcode['cookie'];

        $findorder = Db::name('order')->where(['id' => $order['id']])->find();
        if (!empty($findorder['xl_order_id']) && !empty($findorder['xl_pay_data'])) {
            $data = [
                'xl_order_id' => $findorder['xl_order_id'],
                'xl_pay_data' => $findorder['xl_pay_data'],
                'xl_user_id'  => $findorder['xl_user_id'],
            ];
            return ['code' => 200, 'msg' => $data];
        }

        $ts        = $this->getMsectime();
        $amount    = intval($order['amount']);
        $device_id = '004bcbaa50b8752d820a7d4f906855cb-1005618709';

        //比如充值金额100 匹配单价1 数量就是100/1
        $price     = 1; //点券单价
        $quantity  = ($amount / $price) * 100; //数量*100
        $pay_price = bcmul($amount, 100, 0);


        $device_type = Utils::getClientOsInfo();

        $header_arr = [
            'Cookie: ' . $gmm_cookie,
        ];


        /*$daili = AgentUtil::shanchendaili($order['out_trade_no']);
        if($daili['status'] == 0){
            $daili = $daili['list'][0];
            $options = [
                CURLOPT_PROXY => $daili['sever'],
                CURLOPT_PROXYPORT => $daili['port'],
                CURLOPT_HTTPHEADER => $header_arr,
            ];
        }else{
            $options = [
                CURLOPT_HTTPHEADER => $header_arr,
            ];
        }*/

        $options = [
            CURLOPT_HTTPHEADER => $header_arr,
        ];

        //生成订单
        $url = 'https://apiandroid.gmmsj.com/api/dianquanapi/order?src_code=10&method=WantBuyGoods&params=' . urlencode('{"game_id":791000218,"price":"' . $price . '","b_account":"' . $b_account . '","mid_account":"alipay","quantity":' . $quantity . ',"currency_id":3,"area_id":0,"app_version":"800","device_id":"' . $device_id . '","system_deviceId":"' . $device_id . '","app_channel":"official"}');
        //halt($url);
        $res = Http::get($url, [], $options);
        //halt($res);
        $result = json_decode($res, true);

        if ($result['return_code'] != 0) {
            $data = [
                'out_trade_no' => $order['out_trade_no'],
                'trade_no'     => $order['trade_no'],
                'content'      => '1-gmm下单失败' . json_encode($result, JSON_UNESCAPED_UNICODE),
            ];
            event('OrderError', $data);

            Db::name('group_qrcode')->where('id', $qrcode_id)->update(['status' => 0, 'remark' => $result['return_message']]);

            return ['code' => 102, 'msg' => '获取支付信息失败'];
        }

        $book_id    = $result['data']['book_id'];
        $s_mid      = $result['data']['s_mid'];
        $goods_name = $result['data']['goods_name'];
        $order_id   = $result['data']['order_id'];

        $data = [
            'xl_order_id' => $order_id,
            'xl_user_id'  => $b_account,
            'hc_pay_data' => $res,
            'device_type' => $device_type,
        ];

        Db::name('order')->where('id', $order['id'])->update($data);

        /*$book_id    = 'DQMP9100000000045801677394658865';
        $s_mid      = 'UNKNOWNUSER';
        $goods_name = 'G买卖点券求购商品';
        $order_id   = 'DQMO9100000000045801677394658865';*/


        $topay_url = 'https://qb.sdo.com/qbInf/alipayPayBegin?traceNo=' . $order_id . '&userIdDest=' . $s_mid . '&orderType=9&subject=' . urlencode($goods_name) . '&itemDetail=' . urlencode($goods_name) . '&itemType=9&itemId=' . $book_id . '&price=' . $pay_price . '&endpointTypeSrc=1&couponIds=&redirectUrl=https%3A%2F%2Fqb.sdo.com%2FqbInf%2FalipayRedirect&reqFrom=2&gameAppId=791000218&_=' . $ts;

        //halt($topay_url);
        $header_arr = [
            'Host: qb.sdo.com',
            'Cookie: ' . $gmm_pay_cookie,
        ];

        $options = [
            CURLOPT_HTTPHEADER => $header_arr,
        ];

        $res2 = Http::get($topay_url, [], $options);

        $result2 = json_decode($res2, true);
        if ($result2['return_code'] != 0) {
            $data = [
                'out_trade_no' => $order['out_trade_no'],
                'trade_no'     => $order['trade_no'],
                'content'      => '2-gmm获取支付信息失败' . $res2,
            ];
            event('OrderError', $data);

            return ['code' => 102, 'msg' => '获取失败，请刷新页面或者重新发起支付!!!'];
        }

        $alipayUrl = $result2['data']['alipayUrl'];
        $orderId   = $result2['data']['orderId'];

        $data = [
            'xl_pay_data'   => $alipayUrl,
            'hand_pay_data' => $res2,
            'hand_order_id' => $orderId,
        ];

        Db::name('order')->where('id', $order['id'])->update($data);

        return ['code' => 200, 'msg' => $data];
    }

    //g买卖h5-支付宝获取支付信息
    public function gmmzfbPayDataH5($order, $qrcode, $i) {

        $qrcode_id      = $qrcode['id'];
        $b_account      = $qrcode['zfb_pid'];
        $gmm_cookie     = $qrcode['xl_cookie'];
        $gmm_pay_cookie = $qrcode['cookie'];


        $findorder = Db::name('order')->where(['id' => $order['id']])->find();

        if (!empty($findorder['xl_order_id']) && !empty($findorder['xl_pay_data'])) {
            $data = [
                'pay_amount'  => $findorder['pay_amount'],
                'xl_order_id' => $findorder['xl_order_id'],
                'xl_pay_data' => $findorder['xl_pay_data'],
                'xl_user_id'  => $findorder['xl_user_id'],
            ];
            return ['code' => 200, 'msg' => $data];
        }

        $ts        = $this->getMsectime();
        $amount    = intval($order['amount']);
        $device_id = empty($qrcode['business_url']) ? 'v2_YGkPkped97MY49vbyutCYTEIq7pkzwHl' : $qrcode['business_url'];

        //比如充值金额100 匹配单价1 数量就是100/1
        $price      = 0.973; //点券单价
        $quantity   = bcdiv($amount, $price, 0); //金额/单价 最大到账点券
        $quantity2  = bcmul(bcdiv($amount, $price, 0), 100, 0); //h5api接口得再乘100
        $pay_amount = round(bcmul($quantity, $price, 4), 2); //用户实际支付金额 四舍五入2位小数
        $pay_price  = bcmul($pay_amount, 100, 2);
        //$device_type = Utils::getClientOsInfo();
        $device_type = $this->get_device_type();

        $header_arr = [
            'Cookie: ' . $gmm_cookie,
            'Referer: https://www.gmmsj.com/h5/buyconfirm/matchtrade_buy/index.html?game_id=791000218',
            'User-Agent: Mozilla/5.0 (Linux; Android 8.0.0; SM-G955U Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/87.0.4280.141 Mobile Safari/537.36',
            'Host: www.gmmsj.com',
        ];


        /*$daili = AgentUtil::shanchendaili($order['out_trade_no']);
        if($daili['status'] == 0 && !empty($daili['list'])){
            $daili = $daili['list'][0];
            $options = [
                CURLOPT_PROXY      => $daili['sever'],
                CURLOPT_PROXYPORT  => $daili['port'],
                CURLOPT_HTTPHEADER => $header_arr,
            ];
        }else{
            $options = [
                CURLOPT_HTTPHEADER => $header_arr,
            ];
        }*/

        $options = [
            CURLOPT_HTTPHEADER => $header_arr,
        ];

        if (empty($findorder['xl_order_id']) && empty($findorder['xl_pay_data'])) {
            //h5 生成订单

            $url = 'https://www.gmmsj.com/api/dianquanapi/order?src_code=11&app_version=0&app_channel=android_browser&device_id=' . $device_id . '&system_deviceId=' . $device_id . '&method=WantBuyGoods&params=%7B%22src_code%22:11,%22device_id%22:%22' . $device_id . '%22,%22system_deviceId%22:%22' . $device_id . '%22,%22game_id%22:%22791000218%22,%22price%22:%22' . $price . '%22,%22quantity%22:' . $quantity2 . ',%22b_account%22:%22' . $b_account . '%22,%22mid_account%22:%22alipay%22,%22currency_id%22:3,%22area_id%22:0%7D';

            //$url = 'https://www.gmmsj.com/api/dianquanapi/order?src_code=11&app_version=0&app_channel=android_browser&device_id='.$device_id.'&system_deviceId='.$device_id.'&method=WantBuyGoods&params=%7B%22src_code%22:11,%22device_id%22:%22'.$device_id.'%22,%22system_deviceId%22:%22v2_ULC60dtjru2vEZgyLbJQqLFNUhVyb9Om%22,%22game_id%22:%22791000218%22,%22price%22:%220.973%22,%22quantity%22:1000,%22b_account%22:%2218655440873%22,%22mid_account%22:%22alipay%22,%22currency_id%22:3,%22area_id%22:0,%22sessionKey%22:%22e13f99f33dd24906bbace6c5c8449ff2%22%7D';
            $url = 'https://www.gmmsj.com/api/dianquanapi/order?src_code=11&app_version=0&app_channel=android_browser&device_id=v2_ULC60dtjru2vEZgyLbJQqLFNUhVyb9Om&system_deviceId=v2_ULC60dtjru2vEZgyLbJQqLFNUhVyb9Om&method=WantBuyGoods&params=%7B%22src_code%22:11,%22device_id%22:%22v2_ULC60dtjru2vEZgyLbJQqLFNUhVyb9Om%22,%22system_deviceId%22:%22v2_ULC60dtjru2vEZgyLbJQqLFNUhVyb9Om%22,%22game_id%22:%22791000218%22,%22price%22:%220.973%22,%22quantity%22:1000,%22b_account%22:%2218655440873%22,%22mid_account%22:%22alipay%22,%22currency_id%22:3,%22area_id%22:0,%22sessionKey%22:%220d6d0fe9769e40b385a3094aecb8da0f%22%7D';
            // halt($url);
            //$url = 'https://www.gmmsj.com/api/dianquanapi/order?src_code=11&app_version=0&app_channel=android_browser$qb.sdo.com&device_id=v2_K7zsmsr1X0K5cUkLhay6b1PhuWk6kaoc&system_deviceId=v2_K7zsmsr1X0K5cUkLhay6b1PhuWk6kaoc&method=WantBuyGoods&params=%7B%22src_code%22:11,%22device_id%22:%22v2_K7zsmsr1X0K5cUkLhay6b1PhuWk6kaoc%22,%22system_deviceId%22:%22v2_K7zsmsr1X0K5cUkLhay6b1PhuWk6kaoc%22,%22game_id%22:%22791000218%22,%22price%22:%220.973%22,%22quantity%22:1000,%22b_account%22:%2218655440873%22,%22mid_account%22:%22alipay%22,%22currency_id%22:3,%22area_id%22:0%7D';

            $res = Http::get($url, [], $options);
            //halt($res);
            $result = json_decode($res, true);

            if ($result['return_code'] != 0) {
                $data = [
                    'out_trade_no' => $order['out_trade_no'],
                    'trade_no'     => $order['trade_no'],
                    'msg'          => '1-gmm下单失败',
                    'content'      => json_encode($result, JSON_UNESCAPED_UNICODE),
                ];
                event('OrderError', $data);

                if ($result['return_code'] == '-10242500') {
                    $remark = $result['return_code'] . '-ck失效';
                } else {
                    $remark = $result['return_code'] . '-' . $result['return_message'];
                }

                Db::name('group_qrcode')->where('id', $qrcode_id)->update(['status' => 0, 'remark' => $remark]);

                return ['code' => 102, 'msg' => '获取支付信息失败'];
            }

            $book_id    = $result['data']['book_id'];
            $s_mid      = $result['data']['s_mid'];
            $goods_name = $result['data']['goods_name'];
            $order_id   = $result['data']['order_id'];

            $data = [
                'pay_amount'  => $pay_amount,
                'quantity'    => $quantity,
                'xl_order_id' => $order_id,
                'xl_user_id'  => $b_account,
                'hc_pay_data' => $res,
                'device_type' => $device_type,
            ];

            Db::name('order')->where('id', $order['id'])->update($data);


        } else {

            $order_id   = $order['xl_order_id'];
            $book_id    = str_replace('DQMO', 'DQMP', $order_id);
            $s_mid      = 'UNKNOWNUSER';
            $goods_name = 'G买卖-虚拟点券不可退换,谨防诈骗!';

        }


        //判断ck2是否存在 空的则取获取tk
        /*if (empty($qrcode['cookie'])) {
            $authorize_url = 'https://gmmwlogin.gmmsj.com/v1/oauth/authorize?appid=791000086&state=123&mode=self&display=layer&redirect_url=https://qb.sdo.com/m/set/login.html?back_url=https://qb.sdo.com/m/pay/payconfirm.html?refer=h5&pay_info=' . '{"gameAppId":"791000218","traceNo":"'.$order_id.'","userIdDest":"UNKNOWNUSER","orderType":9,"itemType":9,"amount":"'.$pay_price.'","itemId":"'.$book_id.'","itemDetail":"G买卖点券求购商品","subject":"G买卖点券求购商品","order_url":"//www.gmmsj.com/h5/order/detail/index.html?orderId='.$order_id.'&goods_type=9&from=qb"}';

            $localtion_url = $this->curl_get($authorize_url, []);halt($authorize_url);
            $qbs_url = str_replace('https://qb.sdo.com/m/set/login.html?back_url=https://qb.sdo.com/m/pay/payconfirm.html?','', $localtion_url);

            parse_str($qbs_url,$abs_url_data);
            $ticket = $abs_url_data['ticket'];

            $qbs_login_url = 'https://qb.sdo.com/qbInf/login?appId=791000123&ticket='.$ticket.'&endpointType=2';

            $qbsdo_ck = $this->curl_get($qbs_login_url, []);
            halt($qbsdo_ck);

        }*/

        //halt($pay_price);
        $topay_url = 'https://qb.sdo.com/qbInf/alipayPayBegin?traceNo=' . $order_id . '&userIdDest=' . $s_mid . '&orderType=9&subject=' . urlencode($goods_name) . '&itemDetail=' . urlencode($goods_name) . '&itemType=9&itemId=' . $book_id . '&price=' . $pay_price . '&endpointTypeSrc=1&couponIds=&redirectUrl=https%3A%2F%2Fqb.sdo.com%2FqbInf%2FalipayRedirect&reqFrom=2&gameAppId=791000218&_=' . $ts;

        //halt($topay_url);
        $header_arr = [
            'Host: qb.sdo.com',
            'Cookie: ' . $gmm_pay_cookie,
        ];

        /*$options = [
            CURLOPT_PROXY => $daili['sever'],
            CURLOPT_PROXYPORT => $daili['port'],
            CURLOPT_HTTPHEADER => $header_arr,
        ];*/

        $options = [
            CURLOPT_HTTPHEADER => $header_arr,
        ];

        $res2 = Http::get($topay_url, [], $options);

        $result2 = json_decode($res2, true);
        if ($result2['return_code'] != 0) {
            $data = [
                'out_trade_no' => $order['out_trade_no'],
                'trade_no'     => $order['trade_no'],
                'msg'          => '2-gmm获取支付信息失败',
                'content'      => json_encode($result2, JSON_UNESCAPED_UNICODE),
            ];
            event('OrderError', $data);

            return ['code' => 102, 'msg' => '获取失败，请刷新页面或者重新发起支付!!!'];
        }

        $alipayUrl = $result2['data']['alipayUrl'];
        $orderId   = $result2['data']['orderId'];

        $data = [
            'pay_amount'    => $pay_amount,
            'xl_pay_data'   => $alipayUrl,
            'hand_pay_data' => $res2,
            'hand_order_id' => $orderId,
        ];

        Db::name('order')->where('id', $order['id'])->update($data);

        return ['code' => 200, 'msg' => $data];

        //try {} catch (\Exception $e) {}
        // 这是进行异常捕获

        $data = [
            'out_trade_no' => $order['out_trade_no'],
            'trade_no'     => $order['trade_no'],
            'msg'          => 'gmm异常',
            'content'      => $e->getLine() . '-' . $e->getMessage(),
        ];
        event('OrderError', $data);

        return ['code' => 500, 'msg' => '发起异常，请重新下单或刷新页面重试'];


    }

    //愿聊-迅雷之锤-支付宝
    public function ylxlzczfb($user_id, $pay_type, $acc_robin_rule, $amount) {
        $qrcode = '';
        //随机模式
        if ($acc_robin_rule == 1) {

            $qrcode_list = Db::name('group_qrcode')->where(['user_id' => $user_id, 'acc_code' => $pay_type, 'status' => 1])->select();

            $qrcode_count = count($qrcode_list);
            if ($qrcode_count < 1) {
                return '';
            }

            $qrcode = $qrcode_list[mt_rand(0, $qrcode_count - 1)];
            return $qrcode;
        }

        //顺序模式
        if ($acc_robin_rule == 2) {

            //查询今日总订单数
            $order_count_today = Db::name('order')->where(['user_id' => $user_id, 'pay_type' => $pay_type])->whereDay('createtime')->count();

            // 查询该用户所有通道数
            $count_alipay = Db::name('group_qrcode')->where(['user_id' => $user_id, 'acc_code' => $pay_type, 'status' => 1])->count();

            if ($count_alipay < 1) {
                return '';
            }

            if ($order_count_today < 1) {
                $order_count_today = 1;
            }


            $start = $order_count_today % $count_alipay;

            $qrcode = Db::name('group_qrcode')->where(['user_id' => $user_id, 'acc_code' => $pay_type, 'status' => 1])->order('id desc')->limit($start, 1)->select();
            $qrcode = $qrcode[0];

            return $qrcode;
        }

        return $qrcode;
    }

    //愿聊-迅雷之锤-获取支付宝信息
    public function ylxlzczfbPayData($order, $qrcode) {

        $qrcode_id  = $qrcode['id'];
        $xl_phone   = $qrcode['zfb_pid'];
        $xl_user_id = $qrcode['xl_user_id'];

        $findorder = Db::name('order')->where(['id' => $order['id']])->find();
        if (!empty($findorder['xl_order_id']) && !empty($findorder['xl_pay_data'])) {
            $data = [
                'xl_order_id' => $findorder['xl_order_id'],
                'xl_pay_data' => $findorder['xl_pay_data'],
                'xl_user_id'  => $findorder['xl_user_id'],
            ];
            return ['code' => 200, 'msg' => $data];
        }

        $ts     = $this->getMsectime();
        $amount = intval($order['amount']);

        $header_arr = [
            'User-Agent: ' . 'Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1',
            'Content-Type: application/json',
            'Host: svr-mozhi.xunleizhichui.com',
        ];


        /*$daili = AgentUtil::shanchendaili($order['out_trade_no']);
        if($daili['status'] == 0){
            $daili = $daili['list'][0];
            $options = [
                CURLOPT_PROXY => $daili['sever'],
                CURLOPT_PROXYPORT => $daili['port'],
                CURLOPT_HTTPHEADER => $header_arr,
            ];
        }else{
            $options = [
                CURLOPT_HTTPHEADER => $header_arr,
            ];
        }*/

        $options = [
            CURLOPT_HTTPHEADER => $header_arr,
        ];

        //h5
        $url = 'https://svr-mozhi.xunleizhichui.com/melon.bizpay.s/v1/alipay_h5_start';

        $postData = [
            'userid'  => $xl_user_id,
            'phoneno' => $xl_phone,
            'money'   => $amount,
            //'productid' => '100028',    
            'base'    => [
                'app' => 'com.cn.xcub.make.h5',
                'av'  => '1.1.0',
                'dt'  => 3,
                'did' => '19a3d6e1435da4aa0d829a719c5e06b9',
                'ch'  => 'web_pay',
                'ts'  => $ts,
            ],
        ];

        $res    = Http::post($url, json_encode($postData), $options);
        $result = json_decode($res, true);

        if ($result['code'] != 0) {
            $data = [
                'out_trade_no' => $order['out_trade_no'],
                'trade_no'     => $order['trade_no'],
                'content'      => '迅雷1-下单失败' . json_encode($result, JSON_UNESCAPED_UNICODE),
            ];
            event('OrderError', $data);

            //Db::name('group_qrcode')->where('id',$qrcode_id)->update(['status'=>0,'remark'=>$result['msg']]);

            return ['code' => 102, 'msg' => '获取支付信息失败'];
        }

        $order_id    = $result['data']['orderid'];
        $trade_param = $result['data']['trade_param'];


        //提交openapi.alipay

        $alipay_url1 = $this->curl_get($trade_param);
        $alipay_url  = $this->curl_get($alipay_url1);

        $data = [
            'xl_order_id' => $order_id,
            'xl_pay_data' => $alipay_url,
            'xl_user_id'  => $xl_user_id,
            'hc_pay_data' => $res,
        ];

        Db::name('order')->where('id', $order['id'])->update($data);

        return ['code' => 200, 'msg' => $data];
    }


    //uki-获取支付宝信息
    public function ukiZfbPayData($order, $qrcode) {

        $qrcode_id  = $qrcode['id'];
        $xl_phone   = $qrcode['zfb_pid'];
        $xl_user_id = $qrcode['xl_user_id'];

        $findorder = Db::name('order')->where(['id' => $order['id']])->find();
        if (!empty($findorder['xl_order_id']) && !empty($findorder['xl_pay_data'])) {
            $data = [
                'xl_order_id' => $findorder['xl_order_id'],
                'xl_pay_data' => $findorder['xl_pay_data'],
                'xl_user_id'  => $findorder['xl_user_id'],
            ];
            return ['code' => 200, 'msg' => $data];
        }

        $ts     = $this->getMsectime();
        $amount = intval($order['amount']);

        $header_arr = [
            'User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1',
            'Host: h5-api.neoclub.cn',
            'Content-Type: application/json',
            'Authorization: token ' . $qrcode['xl_cookie'],
        ];


        /*$daili = AgentUtil::shanchendaili($order['out_trade_no']);
        if($daili['status'] == 0){
            $daili = $daili['list'][0];
            $options = [
                CURLOPT_PROXY => $daili['sever'],
                CURLOPT_PROXYPORT => $daili['port'],
                CURLOPT_HTTPHEADER => $header_arr,
            ];
        }else{
            $options = [
                CURLOPT_HTTPHEADER => $header_arr,
            ];
        }*/

        $options = [
            CURLOPT_HTTPHEADER => $header_arr,
        ];

        //h5
        $url = 'https://h5-api.neoclub.cn/v1/bff/web/order/create';

        $postData = '{"goods":[{"id":"H5.DIAMOND' . $amount . '.NEOCLUB.CN"}],"payMethod":"Alipay","userId":"' . $qrcode['xl_user_id'] . '","platform":"h5","returnUrl":"https://pay.neoclub.cn/","aliTradeType":"MWEB"}';

        $res    = Http::post($url, $postData, $options);
        $result = json_decode($res, true);

        if ($result['code'] != 0) {
            $data = [
                'out_trade_no' => $order['out_trade_no'],
                'trade_no'     => $order['trade_no'],
                'msg'          => 'uki-下单失败',
                'content'      => son_encode($result, JSON_UNESCAPED_UNICODE),
            ];
            event('OrderError', $data);
            return ['code' => 102, 'msg' => '获取支付信息失败'];
        }

        $orderId = $result['data']['orderId'];
        $payInfo = $result['data']['payInfo']['payInfo'];
        $payInfo = 'https://openapi.alipay.com/gateway.do?' . $payInfo;


        //提交openapi.alipay
        $alipay_url = $this->curl_get($payInfo);
        $alipay_url = $this->curl_get($alipay_url);

        $data = [
            'xl_order_id' => $orderId,
            'xl_pay_data' => $alipay_url,
            'xl_user_id'  => $qrcode['xl_user_id'],
            'hc_pay_data' => $payInfo,
        ];

        Db::name('order')->where('id', $order['id'])->update($data);

        return ['code' => 200, 'msg' => $data];
    }

    //极楚甄-获取支付宝信息
    public function jczZfbPayData($order, $qrcode) {

        $findorder = Db::name('order')->where(['id' => $order['id']])->find();
        if (!empty($findorder['xl_order_id']) && !empty($findorder['xl_pay_data'])) {
            $data = [
                'xl_order_id' => $findorder['xl_order_id'],
                'xl_pay_data' => $findorder['xl_pay_data'],
                'xl_user_id'  => $findorder['xl_user_id'],
            ];
            return ['code' => 200, 'msg' => $data];
        }

        $amount = intval($order['amount']);
        $price  = intval(bcmul($amount, 100));

        $header_arr = [
            //'User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1',
            //'Host: api.overthefence.cn',
            //'Content-Type: application/json, text/plain, */*',
            'Cookie: ' . $qrcode['xl_cookie'],
        ];


        /*$daili = AgentUtil::shanchendaili($order['out_trade_no']);
        if($daili['status'] == 0){
            $daili = $daili['list'][0];
            $options = [
                CURLOPT_PROXY => $daili['sever'],
                CURLOPT_PROXYPORT => $daili['port'],
                CURLOPT_HTTPHEADER => $header_arr,
            ];
        }else{
            $options = [
                CURLOPT_HTTPHEADER => $header_arr,
            ];
        }*/

        $options = [
            CURLOPT_HTTPHEADER => $header_arr,
        ];

        $url = 'https://api.overthefence.cn/order';

        $postData = [
            'type'       => 4,
            'accountId'  => $qrcode['zfb_pid'],
            'goodsInfo'  => [
                'goodsSkuId' => 1517019677589552,
                'goodsId'    => 23,
                'price'      => $price,
                'amount'     => 1,
                'name'       => '极楚币充值',
            ],
            'totalPrice' => $price,
            'remark'     => '',
        ];
//halt($options);
        $res    = Http::post($url, json_encode($postData, JSON_UNESCAPED_UNICODE), $options);
        $result = json_decode($res, true);

        if ($result['code'] != 0) {
            $data = [
                'out_trade_no' => $order['out_trade_no'],
                'trade_no'     => $order['trade_no'],
                'msg'          => '篱笆币-下单失败',
                'content'      => json_encode($result, JSON_UNESCAPED_UNICODE),
            ];
            event('OrderError', $data);
            return ['code' => 102, 'msg' => '获取支付信息失败'];
        }

        /*$payInfo = $this->dealAlipyForm();

        $alipay_url = $payInfo['action'];
        $biz_content = $payInfo['biz_content'];

        //提交openapi.alipay
        $alipay_url = $this->curl_get($alipay_url);
        $alipay_url = $this->curl_get($alipay_url);*/

        $data = [
            //'xl_order_id'   => $orderId,
            'xl_pay_data' => $result['data']['formBody'],
            'xl_user_id'  => $qrcode['xl_user_id'],
            //'hc_pay_data'   => $payInfo,
        ];

        Db::name('order')->where('id', $order['id'])->update($data);

        return ['code' => 200, 'msg' => $data];
    }

    //我秀-获取支付宝信息
    public function woxiuPayData($order, $qrcode) {

        $findorder = Db::name('order')->where(['id' => $order['id']])->find();

        if (!empty($findorder['xl_order_id']) && !empty($findorder['xl_pay_data'])) {
            $data = [
                'xl_order_id' => $findorder['xl_order_id'],
                'xl_pay_data' => $findorder['xl_pay_data'],
            ];
            return ['code' => 200, 'msg' => $data];
        }

        $amount     = intval($order['amount']);
        $price      = intval(bcmul($amount, 100));
        $t          = '0.' . Random::numeric(16);
        $referer    = 'https://pay.woxiu.com/xiu/wap/h5_allin.php';
        $header_arr = [
            'User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1',
            'Host: pay.woxiu.com',
            //'Content-Type: application/json, text/plain, */*',
            'Referer: ' . $referer,
        ];


        /*$daili = AgentUtil::shanchendaili($order['out_trade_no']);
        if($daili['status'] == 0){
            $daili = $daili['list'][0];
            $options = [
                CURLOPT_PROXY => $daili['sever'],
                CURLOPT_PROXYPORT => $daili['port'],
                CURLOPT_HTTPHEADER => $header_arr,
            ];
        }else{
            $options = [
                CURLOPT_HTTPHEADER => $header_arr,
            ];
        }*/

        $options = [
            CURLOPT_HTTPHEADER => $header_arr,
        ];


        $url = 'https://pay.woxiu.com/xiu/paycenter/m_pay.php';

        //扫码入口 返回一个链接的
        $postData = 'user_id=' . $qrcode['xl_user_id'] . '&money=' . $amount . '&recharge_type=aliAllin&t=' . $t . '&ref=h5';

        $res    = Http::post($url, $postData, $options);
        $result = json_decode($res, true);

        if ($result['code'] != 1) {
            $data = [
                'out_trade_no' => $order['out_trade_no'],
                'trade_no'     => $order['trade_no'],
                'msg'          => '我秀-下单失败',
                'content'      => json_encode($result, JSON_UNESCAPED_UNICODE),
            ];
            event('OrderError', $data);

            //关停该码
            GroupQrcode::where('id', $qrcode['id'])->update(['status' => GroupQrcode::STATUS_OFF, 'update_time' => time()]);

            return ['code' => 102, 'msg' => '获取支付信息失败，请刷新页面或重新发起'];
        }

        $data = [
            'xl_order_id' => $result['data']['form']['orderNo'],
            'xl_pay_data' => $result['data']['form']['code_url'],
            'xl_user_id'  => $qrcode['zfb_pid'],
            'hc_pay_data' => $res,
        ];

        Db::name('order')->where('id', $order['id'])->update($data);

        return ['code' => 200, 'msg' => $data];
    }
    
    //我秀-获取支付宝h5
    public function woxiuPayDataH5($order, $qrcode) {

        $findorder = Db::name('order')->where(['id' => $order['id']])->find();

        if (!empty($findorder['xl_order_id']) && !empty($findorder['xl_pay_data'])) {
            $data = [
                'xl_order_id' => $findorder['xl_order_id'],
                'xl_pay_data' => $findorder['xl_pay_data'],
            ];
            return ['code' => 200, 'msg' => $data];
        }

        $amount     = intval($order['amount']);
        $price      = intval(bcmul($amount, 100));
        $t          = '0.' . Random::numeric(16);
        $referer    = 'https://pay.woxiu.com/xiu/wap/h5_allin.php';
        $header_arr = [
            'User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1',
            'Host: pay.woxiu.com',
            //'Content-Type: application/json, text/plain, */*',
            'Referer: ' . $referer,
        ];
        
        $options = [
            CURLOPT_HTTPHEADER => $header_arr,
        ];
        
        $url = 'https://pay.woxiu.com/xiu/paycenter/m_pay.php';

        //h5入口，返回支付宝form提交
        $postData = 'user_id=' . $qrcode['xl_user_id'] . '&money=' . $amount . '&recharge_type=alipay&t=' . $t . '&ref=h5';

        $res    = Http::post($url, $postData, $options);
        $result = json_decode($res, true);


        if ($result['code'] != 1) {
            $data = [
                'out_trade_no' => $order['out_trade_no'],
                'trade_no'     => $order['trade_no'],
                'msg'          => '我秀-下单失败',
                'content'      => json_encode($result, JSON_UNESCAPED_UNICODE),
            ];
            event('OrderError', $data);

            //关停该码
            GroupQrcode::where('id', $qrcode['id'])->update(['status' => GroupQrcode::STATUS_OFF, 'update_time' => time()]);

            return ['code' => 102, 'msg' => '获取支付信息失败，请刷新页面或重新发起'];
        }

        $data = [
            'xl_order_id' => $result['data']['order_id'],
            'xl_pay_data' => $result['data']['form'],
            'xl_user_id'  => $qrcode['zfb_pid'],
            'hc_pay_data' => $res,
        ];

        Db::name('order')->where('id', $order['id'])->update($data);

        return ['code' => 200, 'msg' => $data];
    }
    
    //我秀-获取微信小程序h5
    public function woxiuWxPayData($order, $qrcode) {

        $findorder = Db::name('order')->where(['id' => $order['id']])->find();

        if (!empty($findorder['xl_order_id']) && !empty($findorder['xl_pay_data'])) {
            $data = [
                'xl_order_id' => $findorder['xl_order_id'],
                'xl_pay_data' => $findorder['xl_pay_data'],
            ];
            return ['code' => 200, 'msg' => $data];
        }

        $amount     = intval($order['amount']);
        $price      = intval(bcmul($amount, 100));
        $t          = '0.' . Random::numeric(16);
        $referer    = 'https://pay.woxiu.com/xiu/wap/h5_allin.php';
        $header_arr = [
            'User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1',
            'Host: pay.woxiu.com',
            //'Content-Type: application/json, text/plain, */*',
            'Referer: ' . $referer,
        ];
        
        $options = [
            CURLOPT_HTTPHEADER => $header_arr,
        ];
        
        $url = 'https://pay.woxiu.com/xiu/paycenter/m_pay.php';
        
        $postData = 'user_id=' . $qrcode['xl_user_id'] . '&money=' . $amount . '&recharge_type=wxWpayH5&t=' . $t . '&ref=h5';
        
        $res    = Http::post($url, $postData, $options);
        $result = json_decode($res, true);


        if ($result['code'] != 1) {
            $data = [
                'out_trade_no' => $order['out_trade_no'],
                'trade_no'     => $order['trade_no'],
                'msg'          => '我秀-下单失败',
                'content'      => json_encode($result, JSON_UNESCAPED_UNICODE),
            ];
            event('OrderError', $data);

            //关停该码
            GroupQrcode::where('id', $qrcode['id'])->update(['status' => GroupQrcode::STATUS_OFF, 'update_time' => time()]);

            return ['code' => 102, 'msg' => '获取支付信息失败，请刷新页面或重新发起'];
        }
        
        /*//请求表单
        $code_url = $result['data']['form']['code_url'];
        $res1  = Http::get($code_url);
        
        //匹配出地址
        $pattern = '/src="([^"]*)"/i';
        preg_match($pattern, $res1, $match);
        $url2 = isset($match[1]) ? $match[1] : '';
        
        $res2  = Http::get($url2);
        //匹配出地址
        $pattern = '/a.href = "([^"]*)"/i';
        preg_match($pattern, $res2, $match);
        $url3 = isset($match[1]) ? $match[1] : '';//最终支付地址*/
        
        
        $data = [
            'xl_order_id' => $result['data']['order_id'],
            'xl_pay_data' => $result['data']['form']['code_url'],
            
            'xl_user_id'  => $qrcode['zfb_pid'],
            'hc_pay_data' => $res,
        ];
        
        Db::name('order')->where('id', $order['id'])->update($data);

        return ['code' => 200, 'msg' => $data];
    }


    //支付宝app支付获取支付信息
    public function alipayAppPayData($order, $qrcode) {

        $qrcode_id = $qrcode['id'];

        $findorder = Db::name('order')->where(['id' => $order['id']])->find();
        if (!empty($findorder['xl_pay_data'])) {
            $data = [
                'xl_pay_data' => $findorder['xl_pay_data'],
                'hc_pay_data' => $findorder['hc_pay_data'],
            ];
            return ['code' => 200, 'msg' => $data];
        }

        $device_type = $this->get_device_type();

        $zhuti = Db::name('alipay_zhuti')->where('id', $qrcode['zhuti_id'])->find();

        $alipaySdk = new AlipaySdk();
        $payUrl    = $alipaySdk->alipayAppPay($order['out_trade_no'], $order['pay_amount'], $qrcode, $zhuti);

        parse_str($payUrl, $payData);

        $data = [
            'xl_user_id'    => $qrcode['zhuti_id'],
            'xl_pay_data'   => json_encode($payData),
            'hc_pay_data'   => $payUrl,
            'hand_pay_data' => $qrcode['app_auth_token'],
            'device_type'   => $device_type,
        ];

        Db::name('order')->where('id', $order['id'])->update($data);

        return ['code' => 200, 'msg' => $data];

    }

    //支付宝app支付获取支付信息
    public function alipayPcPayData($order, $qrcode) {

        $qrcode_id = $qrcode['id'];

        $findorder = Db::name('order')->where(['id' => $order['id']])->find();
        if (!empty($findorder['xl_pay_data'])) {
            $data = [
                'xl_pay_data' => $findorder['xl_pay_data'],
                'hc_pay_data' => $findorder['hc_pay_data'],
            ];
            return ['code' => 200, 'msg' => $data];
        }

        $device_type = $this->get_device_type();

        $zhuti = Db::name('alipay_zhuti')->where('id', $qrcode['zhuti_id'])->find();

        $alipaySdk = new AlipaySdk();
        $payUrl    = $alipaySdk->alipayPcPay($order['out_trade_no'], $order['pay_amount'], $qrcode, $zhuti);

        //$payData = str_replace("<script>document.forms['alipaysubmit'].submit();</script>", '', $payUrl);

        //$script = "<script>document.forms['alipaysubmit'].submit();</script>";//form中js部分
        //$payData = substr($payUrl,0,strrpos($payUrl,$script));//将form中js部分去掉
        //$payData = str_replace("'",'"', $payUrl);
        
        
        $data = [
            'xl_user_id'    => $qrcode['zhuti_id'],
            'xl_pay_data'   => $payUrl,
            'hc_pay_data'   => $payUrl,
            'hand_pay_data' => $qrcode['app_auth_token'],
            'device_type'   => $device_type,
        ];

        Db::name('order')->where('id', $order['id'])->update($data);

        return ['code' => 200, 'msg' => $data];

    }

    //支付宝当面付支付获取支付信息
    public function alipayDmfPayData($order, $qrcode) {

        $qrcode_id = $qrcode['id'];

        $findorder = Db::name('order')->where(['id' => $order['id']])->find();
        if (!empty($findorder['xl_pay_data'])) {
            $data = [
                'xl_pay_data' => $findorder['xl_pay_data'],
                'hc_pay_data' => $findorder['hc_pay_data'],
            ];
            return ['code' => 200, 'msg' => $data];
        }

        $device_type = $this->get_device_type();

        $zhuti = Db::name('alipay_zhuti')->where('id', $qrcode['zhuti_id'])->find();

        $alipaySdk = new AlipaySdk();
        $payUrl    = $alipaySdk->alipayDmfPay($order['out_trade_no'], $order['pay_amount'], $qrcode, $zhuti, $order['zfb_user_id']);
        
        if($payUrl == false){
            $data = [
                'out_trade_no' => $order['out_trade_no'],
                'trade_no'     => $order['trade_no'],
                'msg'          => '当面付下单失败',
                'content'      => '失败',
            ];
            event('OrderError', $data);
        }
        
        $data = [
            'xl_user_id'    => $qrcode['zhuti_id'],
            'xl_pay_data'   => $payUrl,
            'hc_pay_data'   => $payUrl,
            'hand_pay_data' => $qrcode['app_auth_token'],
            'device_type'   => $device_type,
        ];

        Db::name('order')->where('id', $order['id'])->update($data);

        return ['code' => 200, 'msg' => $data];

    }

    //支付宝wap支付获取支付信息
    public function alipayWapPayData($order, $qrcode) {

        $qrcode_id = $qrcode['id'];

        $findorder = Db::name('order')->where(['id' => $order['id']])->find();
        if (!empty($findorder['xl_pay_data'])) {
            $data = [
                'xl_pay_data' => $findorder['xl_pay_data'],
                'hc_pay_data' => $findorder['hc_pay_data'],
            ];
            return ['code' => 200, 'msg' => $data];
        }

        $device_type = $this->get_device_type();
        $time_expire = date('Y-m-d H:i:s', $order['expire_time']);
        $zhuti       = Db::name('alipay_zhuti')->where('id', $qrcode['zhuti_id'])->find();

        $alipaySdk = new AlipaySdk();
        $payUrl    = $alipaySdk->alipayWapPay($order['out_trade_no'], $order['pay_amount'], $qrcode, $zhuti, $time_expire);

        //$payData = str_replace("<script>document.forms['alipaysubmit'].submit();</script>", '', $payUrl);

        $script = "<script>document.forms['alipaysubmit'].submit();</script>";//form中js部分
        //$payData = substr($payUrl,0,strrpos($payUrl,$script));//将form中js部分去掉
        //$payData = str_replace("'",'"', $payUrl);

        $data = [
            'xl_user_id'    => $qrcode['zhuti_id'],
            'xl_pay_data'   => $payUrl,
            'hc_pay_data'   => $payUrl,
            'hand_pay_data' => $qrcode['app_auth_token'],
            'device_type'   => $device_type,
        ];

        Db::name('order')->where('id', $order['id'])->update($data);

        return ['code' => 200, 'msg' => $data];

    }
    
    //支付宝小程序支付获取支付信息
    public function alipayJsApiPayData($order, $qrcode) {

        $qrcode_id = $qrcode['id'];

        $findorder = Db::name('order')->where(['id' => $order['id']])->find();
        if (!empty($findorder['xl_pay_data'])) {
            $data = [
                'xl_pay_data' => $findorder['xl_pay_data'],
                'hc_pay_data' => $findorder['hc_pay_data'],
            ];
            return ['code' => 200, 'msg' => $data];
        }

        $device_type = $this->get_device_type();
        $zfb_user_id = $order['zfb_user_id'];
        $zhuti       = Db::name('alipay_zhuti')->where('id', $qrcode['zhuti_id'])->find();

        $alipaySdk = new AlipaySdk();
        $payUrl    = $alipaySdk->alipayJsApiPay($order['out_trade_no'], $order['pay_amount'], $qrcode, $zhuti, $zfb_user_id);

        //$payData = str_replace("<script>document.forms['alipaysubmit'].submit();</script>", '', $payUrl);

        $script = "<script>document.forms['alipaysubmit'].submit();</script>";//form中js部分
        //$payData = substr($payUrl,0,strrpos($payUrl,$script));//将form中js部分去掉
        //$payData = str_replace("'",'"', $payUrl);

        $data = [
            'xl_user_id'    => $qrcode['zhuti_id'],
            'xl_pay_data'   => $payUrl,
            'hc_pay_data'   => $payUrl,
            'hand_pay_data' => $qrcode['app_auth_token'],
            'device_type'   => $device_type,
        ];

        Db::name('order')->where('id', $order['id'])->update($data);

        return ['code' => 200, 'msg' => $data];

    }
    
    
    //支付宝个码
    public function alipayGmPayData($order, $qrcode) {
        
        $device_type = $this->get_device_type();
        
        $data = [
            'xl_user_id'    => $qrcode['zhuti_id'],
            'zfb_user_id'   => $qrcode['zfb_pid'],
            'hand_pay_data' => $qrcode['app_auth_token'],
            'device_type'   => $device_type,
        ];
        
        Db::name('order')->where('id', $order['id'])->update($data);
        
        return ['code' => 200, 'msg' => $data];

    }
    
    //支付宝个码
    public function qqscanpay($order, $qrcode) {
        
        $device_type = $this->get_device_type();
        
        $data = [
            
            'device_type'   => $device_type,
        ];
        
        Db::name('order')->where('id', $order['id'])->update($data);
        
        return ['code' => 200, 'msg' => $data];

    }
    
    
    //支付宝wap支付获取支付信息
    public function alipayWapPayDataYs($order, $qrcode) {

        $qrcode_id = $qrcode['id'];

        $findorder = Db::name('order')->where(['id' => $order['id']])->find();
        if (!empty($findorder['xl_pay_data'])) {
            $data = [
                'xl_pay_data' => $findorder['xl_pay_data'],
                'hc_pay_data' => $findorder['hc_pay_data'],
            ];
            return ['code' => 200, 'msg' => $data];
        }

        $device_type = $this->get_device_type();
        $time_expire = date('Y-m-d H:i:s', $order['expire_time']);
        
        $zhuti['alipay_public_key']  = $qrcode['xl_cookie'];
        $zhuti['alipay_private_key'] = $qrcode['cookie'];
        $zhuti['appid']              = $qrcode['name'];
        
        $notify_url = Utils::imagePath('/api/notify/aliwapNotify', true);
        $return_url = Utils::imagePath('/api/index/paysuccess', true);
        
        $alipaySdk = new AlipaySdk();
        $payUrl    = $alipaySdk->alipayWapPayByYs($order['out_trade_no'], $order['pay_amount'], $zhuti, $time_expire, $notify_url, $return_url);
        
        //$payData = str_replace("<script>document.forms['alipaysubmit'].submit();</script>", '', $payUrl);
        

        $data = [
            'xl_user_id'    => $qrcode['name'],
            'xl_pay_data'   => $payUrl,
            //'hand_pay_data' => $qrcode['app_auth_token'],
            'device_type'   => $device_type,
        ];

        Db::name('order')->where('id', $order['id'])->update($data);

        return ['code' => 200, 'msg' => $data];

    }
    
    //支付宝当面付支付获取支付信息
    public function alipayDmfPayDataYs($order, $qrcode) {

        $qrcode_id = $qrcode['id'];

        $findorder = Db::name('order')->where(['id' => $order['id']])->find();
        if (!empty($findorder['xl_pay_data'])) {
            $data = [
                'xl_pay_data' => $findorder['xl_pay_data'],
                'hc_pay_data' => $findorder['hc_pay_data'],
            ];
            return ['code' => 200, 'msg' => $data];
        }

        $device_type = $this->get_device_type();
        $time_expire = date('Y-m-d H:i:s', $order['expire_time']);
        
        $zhuti['alipay_public_key']  = $qrcode['xl_cookie'];
        $zhuti['alipay_private_key'] = $qrcode['cookie'];
        $zhuti['appid']              = $qrcode['name'];
        
        $notify_url = Utils::imagePath('/api/notify/aliwapNotify', true);
        $return_url = Utils::imagePath('/api/index/paysuccess', true);
        
        $alipaySdk = new AlipaySdk();
        $payUrl    = $alipaySdk->alipayDmfPayByYs($order['out_trade_no'], $order['pay_amount'], $zhuti, $findorder['zfb_user_id'], $notify_url, $return_url);
        
        $data = [
            'xl_user_id'    => $qrcode['name'],
            'xl_pay_data'   => $payUrl,
            //'hc_pay_data'   => $payUrl,
            //'hand_pay_data' => $qrcode['app_auth_token'],
            'device_type'   => $device_type,
        ];

        Db::name('order')->where('id', $order['id'])->update($data);

        return ['code' => 200, 'msg' => $data];

    }


    public function shtAlipayPay($order, $qrcode, $user_ip) {

        $findorder = Db::name('order')->where(['id' => $order['id']])->find();
        if (!empty($findorder['xl_pay_data'])) {
            $data = [
                'xl_pay_data' => $findorder['xl_pay_data'],
                'hc_pay_data' => $findorder['hc_pay_data'],
            ];
            return ['code' => 200, 'msg' => $data];
        }
        $url = 'https://payment.lnchuangling.top/api/pay/unifiedOrder';

        $subject    = '新汇商品-' . $order['amount'] . '元' . $order['out_trade_no'];
        $ts         = $this->getMsectime();
        $appid      = $qrcode['zfb_pid'];
        $notify_url = Utils::imagePath('/api/notify/shtNotify', true);

        $post_data = [
            'mchOrderNo'   => $order['trade_no'],
            'wayCode'      => 'ALI_WAP',
            'amount'       => $order['amount'] * 100,
            'currency'     => 'CNY',
            'clientIp'     => $user_ip,
            'subject'      => $subject, //商品标题
            'body'         => [],
            'divisionMode' => 1,
            'mchNo'        => $qrcode['pay_url'],
            'appId'        => $appid,
            'version'      => '1.0',
            'notifyUrl'    => $notify_url,
        ];

        $sign              = Util::sign($post_data, $qrcode['cookie']);
        $post_data['sign'] = $sign;

        Log::write('商户通提交----' . json_encode($post_data, JSON_UNESCAPED_UNICODE), 'thirdPay');

        $header_arr = [
            'Content-Type:application/x-www-form-urlencoded',
        ];
        $options    = [
            CURLOPT_HTTPHEADER => $header_arr,
        ];

        $res = Http::post($url, $post_data, $options);

        Log::write('商户通提交结果----' . $res, 'thirdPay');

        $result = json_decode($res, true);

        //收到
        Utils::notifyLog($order['trade_no'], $order['out_trade_no'], '商户通提交结果' . $res);

        if (!isset($result['code']) || $result['code'] != 200) {
            return ['code' => 500, 'msg' => '匹配订单失败'];
        }

        $device_type = Utils::getClientOsInfo();

        $data = [
            'xl_order_id'   => $result['data']['payOrderId'],
            'xl_pay_data'   => $result['data']['payData'],
            'hc_pay_data'   => json_encode($post_data, JSON_UNESCAPED_UNICODE),
            'hand_pay_data' => $res,
            'device_type'   => $device_type,
        ];

        Db::name('order')->where('id', $order['id'])->update($data);

        return ['code' => 200, 'msg' => $data];
    }

    
    //支付宝订单码支付获取支付信息
    public function alipayDdmPayData($order, $qrcode) {
        
        $device_type = $this->get_device_type();
        $zhuti       = Db::name('alipay_zhuti')->where('id', $qrcode['zhuti_id'])->find();
        $notify_url  = Utils::alipayPath('/api/notify/aliwapNotify', true);
        $return_url  = Utils::alipayPath('/api/index/paysuccess', true);
        
        if($qrcode['type'] == 1){
            
            $zhuti = [
                'appid'              => $qrcode['auth_app_id'],    //个码应用的appid
                'alipay_private_key' => $zhuti['alipay_private_key'],  //主体的私钥
                'alipay_public_key'  => $qrcode['xl_cookie'], //个码应用生成的公钥
            ];
        }
        
        $alipaySdk = new AlipaySdk();
        $payUrl    = $alipaySdk->alipayDdmPay($order['out_trade_no'], $order['pay_amount'], $qrcode, $zhuti, $notify_url, $return_url);
        
        $pay_data = json_decode($payUrl, true);
        if(!is_array($pay_data)){
            $xl_pay_data = $payUrl;
        }else{
            $xl_pay_data =$pay_data['alipay_trade_precreate_response']['qr_code'];
        }
        
        $data = [
            'zfb_user_id'   => $qrcode['zfb_pid'],
            'xl_user_id'    => $qrcode['zhuti_id'],
            'xl_pay_data'   => $xl_pay_data,
            'hc_pay_data'   => $payUrl,
            'hand_pay_data' => $qrcode['app_auth_token'],
            'device_type'   => $device_type,
        ];
        
        Db::name('order')->where('id', $order['id'])->update($data);
        
        if (strstr($payUrl, 'FORBIDDEN')  != false){
            Db::name('group_qrcode')->where(['id' => $qrcode['id']])->update(['status'=>0,'remark'=>'清退']);
            return ['code' => 500, 'msg' => '订单失效，请重新发起'];
        }
        
        return ['code' => 200, 'msg' => $data];

    }
    

    public function curl_get($getUrl, $headerArr = '', $is_cookie = false) {

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $getUrl);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        //设置header参数格式是数组
        if ($headerArr) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headerArr);
        }

        $data = curl_exec($ch);

        $response_headers = curl_getinfo($ch);

        //关闭URL请求
        curl_close($ch);

        if ($is_cookie) {
            return $response_headers['Cookie'];
        }

        return $response_headers['redirect_url'];


        //return $data;
    }

    public function curl_get_noredirect($getUrl, $headerArr = '') {

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $getUrl);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        //curl_setopt($ch, CURLOPT_AUTOREFERER, true); //自动设置header中的referer信息
        //curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); //将数据保留到返回结果中 而非直接输出
        //curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); //跟随重定向

        //设置header参数格式是数组
        if ($headerArr) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headerArr);
        }

        $ret = curl_exec($ch);

        $info = curl_getinfo($ch);

        //var_dump($info);

        //关闭URL请求
        curl_close($ch);

        //return $info['redirect_url'];


        return $ret;
    }

    public function curl_post($url, $json_data, $headerArr = '') {

        //初始化
        $curl = curl_init();
        //设置抓取的url
        curl_setopt($curl, CURLOPT_URL, $url);
        //设置头文件的信息作为数据流输出
        //curl_setopt($curl, CURLOPT_HEADER, 1);
        //curl_setopt($curl, CURLOPT_HEADER,1);

        //设置获取的信息以文件流的形式返回，而不是直接输出。
        //curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);

        //设置post方式提交
        curl_setopt($curl, CURLOPT_POST, 1);
        //设置header参数格式是数组
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headerArr);
        //curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
        //post提交的数据  !!!这个数据要通过http_build_query转换
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($json_data));
        //超时时间
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 20);
        //curl_setopt($curl, CURLOPT_COOKIE, $cookie);
        //执行命令
        $data             = curl_exec($curl);
        $errdata          = curl_error($curl);
        $response_headers = curl_getinfo($curl);


        // 返回的是302跳转，要从返回头里提取
        //var_dump($response_headers['url']);

        if (FALSE === $data || !empty($errdata)) {
            $errno = curl_errno($curl);
            $info  = curl_getinfo($curl);
            curl_close($curl);

            return [
                'ret'   => FALSE,
                'errno' => $errno,
                'msg'   => $errdata,
                'info'  => $info,
            ];
        }
        //关闭URL请求
        curl_close($curl);

        return $response_headers['url'];
    }

    public function get_device_type() {
        //全部变成小写字母
        $agent = strtolower($_SERVER['HTTP_USER_AGENT']);
        $type  = 'android';
        //分别进行判断
        if (strpos($agent, 'iphone') || strpos($agent, 'ipad')) {
            $type = 'ios';
        }

        if (strpos($agent, 'android')) {
            $type = 'android';
        }
        return $type;
    }

    /**
     * 新生支付宝
     *
     * 只需要挂一个码就行，所以不需要轮询
     *
     */
    public function xinszfb($user_id, $pay_type, $acc_robin_rule, $amount) {

        $qrcode_list = Db::name('group_qrcode')->where(['user_id' => $user_id, 'acc_code' => $pay_type, 'status' => 1])->select();

        $qrcode_count = count($qrcode_list);
        if ($qrcode_count < 1) {
            return '';
        }

        $qrcode = $qrcode_list[mt_rand(0, $qrcode_count - 1)];

        return $qrcode;
    }

    public function xinszfbPayData($order, $qrcode) {

        $findorder = Db::name('order')->where(['id' => $order['id'], 'pay_type' => '1021'])->find();
        if (!empty($findorder['xl_pay_data'])) {
            $data = $findorder['xl_pay_data'];
            return ['code' => 200, 'msg' => json_decode($data, true)];
        }


        $mch_id          = '11000006766';
        $mch_private_key = Config::get('mchconf.hna_mch_privete_key');
        $mch_public_key  = Config::get('mchconf.hna_mch_public_key');
        $public_key      = Config::get('mchconf.hna_public_key');


        $wait_sign_str1 = [
            'tranAmt'         => $order['pay_amount'],
            'payType'         => 'HnaZFB',
            'exPayMode'       => '',
            'cardNo'          => '',
            'holderName'      => '',
            'identityCode'    => '',
            'merUserId'       => '',
            'orderExpireTime' => '5',
            'frontUrl'        => Utils::imagePath('/api/demo/paysuccess', true),
            'notifyUrl'       => Utils::imagePath('/api/notify/hnaNotify', true),
            'riskExpand'      => '',
            'goodsInfo'       => '',
            'orderSubject'    => '购物',
            'orderDesc'       => '',
            'merchantId'      => '{"02":"' . $qrcode['zfb_pid'] . '"}',
            'bizProtocolNo'   => '',
            'payProtocolNo'   => '',
            'merUserIp'       => '101.43.73.6',
            'payLimit'        => '',
        ];

        $wait_sign_str1 = json_encode($wait_sign_str1);
        $rsa            = new Rsa($public_key, '');
        $msgCiphertext  = $rsa->public_encrypt($wait_sign_str1);

        $postData = [
            'version'       => '2.0',
            'tranCode'      => 'MUP11',
            'merId'         => $mch_id,
            'merOrderId'    => $order['out_trade_no'],
            'submitTime'    => date('YmdHis'),
            'signType'      => '1',
            'charset'       => '1',
            'msgCiphertext' => $msgCiphertext,
        ];

        $wait_sign_str2 = Utils::signV3($postData);

        //私钥加密
        $rsa       = new Rsa($mch_public_key, $mch_private_key);
        $signature = $rsa->private_encryptV2($wait_sign_str2);

        $postData['signValue'] = $signature;
        $postData['merAttach'] = time();

        Utils::notifyLog($order['trade_no'], $order['out_trade_no'], $wait_sign_str1 . "\n\n" . $wait_sign_str2 . "\n\n" . json_encode($postData));

        $data = [
            'xl_pay_data' => json_encode($postData),
        ];

        Db::name('order')->where('id', $order['id'])->update($data);


        return ['code' => 200, 'msg' => $postData];
    }



    //获取毫秒时间
    public function getMsectime() {
        list($msec, $sec) = explode(' ', microtime());
        $msectime = (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
        return intval($msectime);
    }


    public function dealAlipyForm() {

        libxml_use_internal_errors(true);

        $html = stripslashes('<form name=\"punchout_form\" method=\"post\" action=\"https://openapi.alipay.com/gateway.do?app_cert_sn=33a3498bec01e9610f22c90b1c223f99&charset=utf-8&alipay_root_cert_sn=687b59193f3f462dd5336e5abf83c5d8_02941eef3187dddf3d3b83462e1dfcf6&method=alipay.trade.wap.pay&sign=C7ovghvSbVKNLK8lLzxKv4akkxDirUXdjBEZmwvT2wvjDmHTlaKrGdYA7ApvEwRqZXR27qDYhsFgGK5ZPi0uNWYAmbPQruOd/SKEZ5UCqSa/vRBa2APvOonvDb+kR0zrZ6+3c7Uep5DlYmdzxR3FoDp/EGFB9Xug63fSh/AOrxh0TaXzWdL7LP/8PrcUo7dNbSb/nOK2Cum4nKFuq1sYcC7rF68BA4WzvSdQIkOFO2iRifPD6GbbDDNnpswUgE2xw2iRw2tI9mlMUNGlhbYPemq7ij64u1b2PRrn5OvTm9mk3xbnHHyPis2fS22QKWpANGUDm9SON0g1yNPBEUUG3w==&return_url=https://h5.overthefence.cn/fencecoin/#/userCenter&notify_url=https://api.overthefence.cn/alipay/h5/order/notify&version=1.0&app_id=2021003187644022&sign_type=RSA2&timestamp=2023-04-23 10:21:28&alipay_sdk=alipay-sdk-java-4.35.87.ALL&format=json\">\n<input type=\"hidden\" name=\"biz_content\" value=\"{&quot;out_trade_no&quot;:&quot;1542521750552609&quot;,&quot;passback_params&quot;:&quot;account_id=5741441171;user_id=1542520683102241&quot;,&quot;subject&quot;:&quot;极楚币充值&quot;,&quot;total_amount&quot;:&quot;50&quot;}\">\n<input type=\"submit\" value=\"立即支付\" style=\"display:none\" >\n</form>\n<script>document.forms[0].submit();</script>');

        $doc = new DOMDocument();
        $doc->loadHTML($html);

        // Use DOMXPath to query the DOMDocument for the form element
        $xpath = new DOMXPath($doc);
        $form  = $xpath->query('//form[@name="punchout_form"]')->item(0);

        // Get the action attribute of the form element
        $action = $form->getAttribute('action');

        // Loop through each input element and get its name and value attributes
        $inputs = $form->getElementsByTagName('input');

        $temp           = [];
        $temp['action'] = $action;
        foreach ($inputs as $input) {
            $name        = $input->getAttribute('name');
            $value       = $input->getAttribute('value');
            $temp[$name] = $value;
        }

        return $temp;
    }
}