<?php
namespace app\common\library;

use app\admin\model\order\Order;
use app\admin\model\thirdacc\Userqrcode;
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
use app\common\library\Accutils;
use app\common\library\Alipay;

class CheckOrderUtils
{

    //通用支付宝h5页面查单
    public static function alipayH5Check($order){

        //把支付宝链接参数拿出来
        $ali_url = str_replace('https://mclient.alipay.com/h5pay/landing/index.html?','', $order['xl_pay_data']);
        parse_str($ali_url,$ali_url_data);

        $accutils = new Accutils();
        $ts     = $accutils->getMsectime();

        $cashierMain_url = 'https://mclient.alipay.com/wapcashier/api/cashierMain.json';
        $casherData = [
            'h5_request_token'     => $ali_url_data['h5_request_token'],
            'query_params'         => urlencode($ali_url_data['query_params']),
            'app_name'             => $ali_url_data['app_name'],
            'targetDispatchSystem' => $ali_url_data['targetDispatchSystem'],
            'serverParams'         => $ali_url_data['serverParams'],
            't'                    => $ts,
        ];

        $header_arr = [
            'Referer:'.$order['xl_pay_data'],
            'Content-Type: application/json;charset=UTF-8',
        ];

        $options = [
            CURLOPT_HTTPHEADER => $header_arr,
        ];

        $res1 = json_decode(Http::post($cashierMain_url, json_encode($casherData), $options), true);

        //拿到contextId
        $contextId     = $res1['params']['contextId'];
        $refreshNoAuth = $res1['refreshNoAuth'];

        //拼接查单数据
        $ts = $accutils->getMsectime();

        $query_data = [
            'server_param'  => $ali_url_data['serverParams'],
            'pageToken'     => '',
            'contextId'     => $contextId,
            'refreshNoAuth' => $refreshNoAuth,
            'fromUser'      => true,
            'h5RouteToken'  => $ali_url_data['h5_request_token'],
            'ua'            => '',
            't'             => $ts,
        ];

        $query_url = 'https://mclient.alipay.com/wapcashier/api/h5RoutePayResultQuery.json';


        $res2 = Http::post($query_url, json_encode($query_data), $options);

        if(strstr($res2,'您还未完成付款') != false || strstr($res2,'无法获取付款结果') != false || strstr($res2,'继续付款') != false || strstr($res2,'系统繁忙') != false){
            $data = ['is_exist'=>false,'data'=>$res2,'url'=>''];
        }else{
            $data = ['is_exist'=>true,'data'=>$res2,'url'=>''];
        }

        return $data;
    }

    //迅雷查单
    public static function xunLeiCheck($order){

        $accutils = new Accutils();
        $time     = $accutils->getMsectime();
        $params   = [];
        $options  = [];

        /*//版本1 h5查单
        $str  = '_t='.$time.'&a=isExistOrder&c=paynew&front_orderid='.$order['xl_gorderid'].'&userid='.$order['xl_user_id'];
        $sign = md5('1002'.$str.'&*%$7987321GKwq');
        $url  = 'https://pc-live-ssl.xunlei.com/caller?c=paynew&a=isExistOrder&front_orderid='.$order['xl_gorderid'].'&userid='.$order['xl_user_id'].'&_t='.$time;
        */

        /*//版本4 h5查单
        $str = '_t='.$time.'&a=isExistOrder&c=paynew&front_orderid='.$order['xl_gorderid'].'&sec=JfUsM3N8RuXgBzxG&userid='.$order['xl_user_id'];
        $sign = md5('1002'.$str.'&*%$7987321GKwq');
        $url = 'https://pc-live-ssl.xunlei.com/caller?c=paynew&a=isExistOrder&front_orderid='.$order['xl_gorderid'].'&userid='.$order['xl_user_id'].'&sec=JfUsM3N8RuXgBzxG&_t='.$time.'&sign='.$sign;*/

        //版本5 h5查单
        $str  = '_t='.$time.'&a=isExistOrder&c=paynew&front_orderid='.$order['xl_gorderid'].'&sec=utvyUErFq2P8WCPA&userid='.$order['xl_user_id'];
        $sign = md5('1002'.$str.'&*%$7987321GKwq');
        $url  = 'https://pc-live-ssl.xunlei.com/caller?c=paynew&a=isExistOrder&front_orderid='.$order['xl_gorderid'].'&userid='.$order['xl_user_id'].'&sec=utvyUErFq2P8WCPA&_t='.$time.'&sign='.$sign;

        $result = json_decode(Http::get($url, $params, $options), true);

        if($result['result'] == 200 && $result['data']['is_exist'] == 1){
            $data = ['is_exist'=>true,'data'=>json_encode($result, JSON_UNESCAPED_UNICODE),'url'=>$url];
        }else{
            $data = ['is_exist'=>false,'data'=>json_encode($result, JSON_UNESCAPED_UNICODE),'url'=>$url];
        }

        return $data;
    }
    
    //迅雷后台ck查单
    public static function xlcheckOrderByCk($order){
        
        
        $qrcode = Db::name('group_qrcode')->where(['id' => $order['qrcode_id']])->find();

        $options = [
            CURLOPT_HTTPHEADER =>[
                'Cookie:'.$qrcode['xl_cookie'],
            ]
        ];
        $accutils  = new Accutils();
        $time      = $accutils->getMsectime();
        $params    = [];
        $starttime = date("Y-m-d",strtotime("-30 day"));
        $endtime   = date('Y-m-d');
        
        $url = 'https://xluser-ssl.xunlei.com/tradingrecord/v1/GetTradingRecord?csrf_token=fff61cf90f6b183806aa527c57121625&appid=22003&starttime='.$starttime.'&endtime='.$endtime.'&paytype=&_='.$time;
        
        $res    = Http::get($url, $params, $options);
        $result = json_decode($res, true);
        
        if(!isset($result['code'])){
            $data = ['is_exist'=>false,'data'=>'迅雷请求失败','url'=>''];
            return $data;
        }
        
        if($result['code'] != 200){
            
            Db::name('group_qrcode')->where(['id' => $order['qrcode_id']])->update(['status'=>0,'remark'=> $result['msg'],'update_time'=>time()]);
            
            $data = ['is_exist'=>false,'data'=> $result['msg'] ,'url'=>''];
            
            Log::write("{$order['out_trade_no']}----迅雷查单失败----".json_encode($result, JSON_UNESCAPED_UNICODE), 'info');
            return $data;
        }
        
        $data = ['is_exist'=>false,'data'=>'暂未找到','url'=>''];
        
        foreach($result['data']['records'] as $k1 => $xl_order){
            if($order['xl_order_id'] == $xl_order['xunleipayid'] && $order['amount'] == $xl_order['orderAmt']){
                //找到订单
                $data = ['is_exist'=>true,'data'=>'找到订单','url'=>''];
                break;
            }
        }
        
        return $data;
        
    }
    
    //皮皮查单
    public static function ppCheck($order){

        $postData = [
            'pyid'     => $order['xl_user_id'],
            'order_id' => $order['xl_order_id'],
        ];

        $options = [
            CURLOPT_HTTPHEADER =>[
                'Content-Type:text/plain; charset=utf-8',
                'User-Agent:Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1',
            ]
        ];

        $url = 'https://act-feature-live.ippzone.com/live_api/pay/webpay_check';

        $res    = Http::post($url, json_encode($postData), $options);
        $result = json_decode($res,true);


        if(strstr($res,'订单正在处理') != false || strstr($res,'交易不存在') != false){
            $data = ['is_exist'=>false,'data'=>$res,'url'=>$url];
        }elseif ($result['errcode'] == 1 && $result['data']['code'] == 1) {
            $data = ['is_exist'=>true,'data'=>$res,'url'=>$url];
        }else{
            $data = ['is_exist'=>false,'data'=>$res,'url'=>$url];
        }

        return $data;

    }

    //快手支付宝/微信查单
    public static function ksWxCheck($order){

        $postData = [
            'merchant_id'  => $order['xl_gorderid'],
            'out_order_no' => $order['xl_order_id'],
        ];

        $url = 'https://www.kuaishoupay.com/pay/order/pc/trade/query';

        $res    = Http::post($url, $postData, []);

        $result = json_decode($res,true);

        $data = ['is_exist'=>false,'data'=>'','url'=>''];


        if($result['result'] != 'SUCCESS' || $result['code'] != 'SUCCESS'){
            if($result['order_state'] != 'PROCESSING'){
                $data = ['is_exist'=>false,'data'=>$res,'url'=>''];
            }else{
                $data = ['is_exist'=>true,'data'=>$res,'url'=>''];
            }
        }else{
            $data = ['is_exist'=>false,'data'=>$res,'url'=>''];
        }

        return $data;
    }

    //yy百战查单
    public static function baizhanCheck($order){

        $postData = [
            'pyid'     => $order['xl_user_id'],
            'order_id' => $order['xl_order_id'],
        ];

        $accutils = new Accutils();
        $ts       = $accutils->getMsectime();
        $appId    = 39;
        $cmd      = 1061;
        $url      = 'https://turnover.baizhanlive.com/api/39/1061';

        $header_arr = [
            'Host: turnover.baizhanlive.com',
            'Referer: https://www.baizhanlive.com/',
        ];

        $options = [
            CURLOPT_HTTPHEADER => $header_arr,
        ];

        $data = [
            'version' => 0,
            'appId'   => $appId,
            'cmd'     => $cmd,
            'jsonMsg' => [
                'cmd'           => $cmd,
                'seq'           => $ts,
                'uid'           => 0,
                'appId'         => $appId,
                'usedChannel'   => 10015,
                'clientVersion' => '',
                'sdkVersion'    => '',
                'orderId'       => $order['xl_order_id'],
            ],
        ];
        //halt(json_encode($data));
        $sign = md5('turnover'. json_encode($data));

        $postData = [
            'appId' => $appId,
            'cmd'   => $cmd,
            'sign'  => $sign,
            'data'  => json_encode($data),
        ];
        $res    = Http::post($url, $postData, []);

        $result = json_decode($res,true);

        if($result['result'] != 1){
            $data = ['is_exist'=>false,'data'=>$res,'url'=>''];
            return $data;
        }

        $jsonMsg = json_decode($result['jsonMsg'],true);

        if (isset($jsonMsg['status']) &&  $jsonMsg['status'] == 1 && isset($jsonMsg['finish']) && $jsonMsg['finish'] == true) {
            $data = ['is_exist'=>true,'data'=>$result['jsonMsg'],'url'=>$url];
        }else{
            $data = ['is_exist'=>false,'data'=>$res,'url'=>$url];
        }

        return $data;

    }

    //gmm查单
    public static function gmmCheck($order){

        return self::gmmCheckByphone($order);

        //短单号后八位
        $short_order_id = substr($order['xl_order_id'], '-8');
        $device_id      = 'v2_2rF3Kp3VUbYmK6hQP6Itl6nZ3AxQAnrQ';
        $order_status   = '-1000'; //全部状态
        $url = 'https://www.gmmsj.com/gatew/consignapi/order/tradeListAllTypesV2?app_version=1.0.0.26719&device_id='.$device_id.'&system_deviceId='.$device_id.'&app_channel=chrome&src_code=7&orderType=b&goods_types=1%2C2%2C5%2C9%2C10%2C12%2C19%2C60%2C57%2C56%2C55%2C53%2C52&order_status='.$order_status.'&keyword='.$short_order_id.'&page=1&page_size=15&buy_type=1&start_time=&end_time=';
        //找出该通道挂的cookie

        $findQrcode = Db::name('group_qrcode')->where(['id' => $order['qrcode_id']])->find();

        $header_arr = [
            'Cookie: ' . $findQrcode['xl_cookie'],
        ];

        $options = [
            CURLOPT_HTTPHEADER => $header_arr,
        ];


        $res    = Http::get($url, [], $options);
        $result = json_decode($res,true);


        if($result['return_code'] != 0){
            $data = ['is_exist'=>false, 'data'=>$res, 'url'=>''];
            return $data;
        }

        $list        = $result['data']['list'];
        $total_count = $result['data']['total_count'];

        if($total_count < 1){
            $data = ['is_exist'=>false, 'data'=>$res, 'url'=>''];
            return $data;
        }

        $is_exist = false;
        foreach ($list as $k => $v){
            $res = json_encode($v, JSON_UNESCAPED_UNICODE);
            if($v['order_id'] == $order['xl_order_id'] && $v['state'] == 6 && $v['state_desc'] == '交易成功' && $v['total_price'] == $order['amount']){
                $is_exist = true;
                break;
            }
        }

        if($is_exist == true){
            $data = ['is_exist'=>true, 'data'=>$res, 'url'=>''];
        }else{
            $data = ['is_exist'=>false, 'data'=>$res, 'url'=>''];
        }

        return $data;

    }

    //gmmapp查单
    public static function gmmCheckByphone($order){

        $order_id = $order['xl_order_id'];


        //app的查单
        /*$device_id = '004bcbaa50b8752d820a7d4f906855cb-1005618709';
        $url = 'https://apiandroid.gmmsj.com/api/dianquanapi/order?src_code=10&method=TradeDetail&params='. urlencode( '{"order_id":"'.$order_id.'","app_version":"800","device_id":"'.$device_id.'","system_deviceId":"'.$device_id.'","app_channel":"official"}' );
       */
        
        //找出该通道挂的cookie
        $findQrcode = Db::name('group_qrcode')->where(['id' => $order['qrcode_id']])->find();
        
        //h5查单
        $device_id = empty($findQrcode['business_url']) ? 'v2_YGkPkped97MY49vbyutCYTEIq7pkzwHl' : $findQrcode['business_url'];
        
        $url = 'https://www.gmmsj.com/gmminf/dianquanapi/order?src_code=11&app_version=0&app_channel=android_browser&device_id='.$device_id.'&system_deviceId='.$device_id.'&method=TradeDetail&params=' . urlencode( '{"src_code":11,"device_id":"'.$device_id.'","system_deviceId":"'.$device_id.'","order_id":"'.$order_id.'","app_version":"webH5"}');
        
        $header_arr = [
            'Cookie: ' . $findQrcode['xl_cookie'],
        ];

        $options = [
            CURLOPT_HTTPHEADER => $header_arr,
        ];


        $res    = Http::get($url, [], $options);
        $result = json_decode($res,true);

        if($result['return_code'] != 0){
            $data = ['is_exist'=>false, 'data'=>json_encode($result, JSON_UNESCAPED_UNICODE), 'url'=>''];
            return $data;
        }

        $re_data = json_encode(['status'=>$result['data']['status'],'state_desc'=>$result['data']['state_desc']], JSON_UNESCAPED_UNICODE);

        if($result['data']['status'] == 1 || $result['data']['state_desc'] == '待付款'){

            $data = ['is_exist'=>false, 'data'=>$re_data, 'url'=>''];

        }elseif($result['data']['status'] == 3 && $result['data']['state_desc'] == '待发货'){

            $data = ['is_exist'=>false, 'data'=>$re_data, 'url'=>''];
            
            //付款了待发货
            Db::name('order')->where(['id'=>$order['id']])->update(['remark'=>$result['data']['state_desc']]);
            
        }elseif($result['data']['status'] == 6 || $result['data']['state_desc'] == '交易成功'){

            $data = ['is_exist'=>true, 'data'=>$re_data, 'url'=>''];
            
        }else{

            $data = ['is_exist'=>false, 'data'=>$re_data, 'url'=>''];
        }

        return $data;

    }

    //gmm取消订单
    public static function gmmCloseOrder($order, $is_job = true){

        //拿单号去取消
        $order_id  = $order['xl_order_id'];

        //pc取消订单的url
        //$device_id = 'v2_2rF3Kp3VUbYmK6hQP6Itl6nZ3AxQAnrQ';
        //$url = 'https://www.gmmsj.com/gatew/ordergw/closeorder?app_version=1.0.0.26735&device_id='.$device_id.'&system_deviceId='.$device_id.'&app_channel=chrome&src_code=7&goods_type=90000&cancel_reason=%E4%B8%8B%E9%94%99%E5%8D%95%E4%BA%86%EF%BC%8C%E7%9C%8B%E9%94%99%E5%95%86%E5%93%81%E6%8F%8F%E8%BF%B0&order_id='.$order_id;

        /*//app取消订单
        $device_id = '004bcbaa50b8752d820a7d4f906855cb-1005618709';
        $url = 'https://apiandroid.gmmsj.com/api/dianquanapi/order?src_code=10&method=CloseOrder&params=' . urlencode( '{"order_id":"'.$order_id.'","app_version":"800","device_id":"'.$device_id.'","system_deviceId":"'.$device_id.'","app_channel":"official"}');*/
        
        //找出该通道挂的cookie
        $findQrcode = Userqrcode::where(['id' => $order['qrcode_id']])->find();
        
        //h5取消订单
        $device_id = empty($findQrcode['business_url']) ? 'v2_YGkPkped97MY49vbyutCYTEIq7pkzwHl' : $findQrcode['business_url'];
        
        $url = 'https://www.gmmsj.com/api/dianquanapi/order?src_code=11&app_version=0&app_channel=ios_browser&device_id='.$device_id.'&system_deviceId='.$device_id.'&method=CloseOrder&params=' . urlencode( '{"src_code":"11","device_id":"'.$device_id.'","system_deviceId":"'.$device_id.'","order_id":"'.$order_id.'","cancel_reason":"下错单了，看错商品描述"}');

        

        $header_arr = [
            'Cookie: ' . $findQrcode['xl_cookie'],
        ];

        $options = [
            CURLOPT_HTTPHEADER => $header_arr,
        ];


        $res    = Http::get($url, [], $options);
        $result = json_decode($res,true);

        if($result['return_code'] != 0){
            $data = ['code'=>500, 'data'=>json_encode($result, JSON_UNESCAPED_UNICODE)];
        }else{
            $data = ['code'=>200, 'data'=>json_encode($result, JSON_UNESCAPED_UNICODE)];
            
            //更改取消标识
            Db::name('order')->where(['id'=>$order['id']])->update(['is_gmm_close'=>1]);
            //关停该码
            //self::closeQrcode($order['user_id'], $findQrcode);
        }

        return $data;

    }


    //淘宝直付查单 新版千牛
    public static function tbzfCheck($order){

        $url     = 'https://trade.taobao.com/trade/itemlist/asyncSold.htm?event_submit_do_query=1&_input_charset=utf8';
        $referer = 'https://myseller.taobao.com/';
        $Origin  = 'https://myseller.taobao.com';
        $Accept  = 'application/json, text/plain, */*';
        
        //找出该通道挂的cookie
        $findQrcode = Db::name('group_qrcode')->where(['id' => $order['qrcode_id']])->find();

        $header_arr = [
            'Cookie: ' . $findQrcode['xl_cookie'],
            'referer: ' . $referer,
            'Origin: ' . $Origin,
            'Accept: ' . $Accept,
        ];

        $options = [
            CURLOPT_HTTPHEADER => $header_arr,
        ];

        //$start_time = strtotime(date("Y-m-d",time())) . '000';
        //$end_time   = mktime(0,0,0,date('m'),date('d')+1,date('Y'))-1;
        //$end_time   = $end_time . '000';
        
        $start_time  = $order['createtime'] . '000';
        $end_time    = $order['expire_time'] . '000';
        $orderStatus = 'SUCCESS';
        
        $postData = 'prePageNo=1&sifg=0&action=itemlist%2FSoldQueryAction&tabCode=latest3Months&buyerNick=&dateBegin='.$start_time.'&dateEnd='.$end_time.'&orderStatus='.$orderStatus.'&rateStatus=ALL&pageSize=15&rxOldFlag=0&rxSendFlag=0&useCheckcode=false&tradeTag=0&rxHasSendFlag=0&auctionType=0&close=0&sellerNick=&notifySendGoodsType=ALL&useOrderInfo=false&logisticsService=ALL&isQnNew=true&pageNum=1&o2oDeliveryType=ALL&rxAuditFlag=0&queryOrder=desc&rxElectronicAuditFlag=0&queryMore=false&rxWaitSendflag=0&sellerMemo=0&rxElectronicAllFlag=0&rxSuccessflag=0&refund=ALL&errorCheckcode=false&mailNo=&yushouStatus=ALL&orderType=ALL&deliveryTimeType=&queryTag=&buyerEncodeId=&orderId=&auctionId='.$findQrcode['tb_good_id'].'&queryBizType=ALL&isHideNick=true';
        
        
        $res    = Http::post($url, $postData, $options);
        $encode = mb_detect_encoding($res, array("ASCII",'UTF-8',"GB2312","GBK",'BIG5'));
        $res    = mb_convert_encoding($res, 'UTF-8', $encode);
        $res    = trim($res);
        $result = json_decode($res,true);
        
        if(!isset($result['mainOrders'])){
            $data = ['is_exist'=>false, 'data'=> isset($result['error']) ? $result['error'] : '请求错误', 'url'=>''];
            return $data;
        }
        
        if(empty($result['mainOrders'])){
            $data = ['is_exist'=>false, 'data'=> '该时段没该订单', 'url'=>''];
            return $data;
        }
        
        $data = ['is_exist'=>false, 'data'=>'', 'url'=>''];

        foreach ($result as $k => $v){
            if($v['subOrders'][0]['itemInfo']['title'] == $findQrcode['name']){
                if($v['statusInfo']['text'] == '交易成功'){
                    $data = ['is_exist'=>true, 'data'=>'交易成功----商品：' .$findQrcode['name'] .'----订单id：'.$v['orderInfo']['id'] , 'url'=>''];
                }else{
                    $data = ['is_exist'=>false, 'data'=>'找到商品，交易状态：' . $v['statusInfo']['text'] , 'url'=>''];
                }

                break;
            }

        }

        return $data;

    }


    //淘宝直付查单
    public static function tbzfCheck_old($order){

        $url     = 'https://trade.taobao.com/trade/itemlist/asyncSold.htm?event_submit_do_query=1&_input_charset=utf8&';
        $referer = 'https://trade.taobao.com/trade/itemlist/list_sold_items.htm?action=itemlist%2FSoldQueryAction&event_submit_do_query=1&auctionStatus=SUCCESS&tabCode=success';


        //找出该通道店铺的ck
        $findQrcode = Db::name('group_qrcode')->where(['id' => $order['qrcode_id']])->find();
        $findShop = Db::name('tb_shop')->where(['id' => $findQrcode['tb_shop_id']])->find();

        $good_id = $findQrcode['tb_good_id'];

        $header_arr = [
            'Cookie: ' . $findShop['cookie'],
            'referer: ' . $referer,
            'x-requested-with: XMLHttpRequest',
        ];

        $options = [
            CURLOPT_HTTPHEADER => $header_arr,
        ];


        //用订单创建时间来查 确保订单支付时间段内商品唯一
        $start_time_ts = $order['createtime'] . '000';
        $now_time      = time();
        $end_time_ts   = $now_time . '000';//结束时间为此刻

        //转为时间格式
        $start_time = date('Y-m-d H:i:s', $order['createtime']);
        $end_time   = date('Y-m-d H:i:s', $now_time);

        //如果订单是超时的 时间范围改为订单的时间范围
        if($order['status'] == Order::STATUS_FAIL){
            $end_time_ts = $order['expire_time'] . '000';
            $end_time    = date('Y-m-d H:i:s', $order['expire_time']);
        }

        $postData = 'action=itemlist%2FSoldQueryAction&auctionType=0&buyerNick=&close=0&dateBegin='.$start_time_ts.'&dateEnd='.$end_time_ts.'&logisticsService=&notifySendGoodsType=ALL&o2oDeliveryType=ALL&orderStatus=SUCCESS&pageNum=1&pageSize=15&payDateBegin=0&payDateEnd=0&queryMore=true&queryOrder=desc&rateStatus=&refund=&rxAuditFlag=0&rxElectronicAllFlag=0&rxElectronicAuditFlag=0&rxHasSendFlag=0&rxOldFlag=0&rxSendFlag=0&rxSuccessflag=0&rxWaitSendflag=0&sellerMemo=0&sellerNick=&tabCode=success&tradeTag=0&useCheckcode=false&useOrderInfo=false&errorCheckcode=false&queryLabelValues='.urlencode('[{"label":"创建时间","value":"'.$start_time.' 到 '.$end_time.'","index":0},{"label":"商品ID","value":"'.$good_id.'","index":1},{"label":"宝贝名称","value":"","index":2},{"label":"买家昵称","value":"","index":4},{"label":"订单编号","value":"","index":7}]').'&prePageNo=1&auctionId=688557031754&sifg';
        //halt($postData);
        $res    = Http::post($url, $postData, $options);
        if(strstr($res,'淘宝网') != false){
            $data = ['is_exist'=>false, 'data'=> '登录失效', 'url'=>''];
            return $data;
        }
        
        //获取编码
        $encode = mb_detect_encoding($res, array("ASCII",'UTF-8',"GB2312","GBK",'BIG5'));
        //转换编码
        $res = mb_convert_encoding($res, 'UTF-8', $encode);
        $res = trim($res);
        $result = json_decode($res,true);

        if(!isset($result['mainOrders'])){
            $data = ['is_exist'=>false, 'data'=> '获取订单失败', 'url'=>''];
            return $data;
        }

        if(empty($result['mainOrders'])){
            $data = ['is_exist'=>false, 'data'=> '未找到该订单', 'url'=>''];
            return $data;
        }

        $data = ['is_exist'=>false, 'data'=> '未找到该订单', 'url'=>''];

        foreach ($result as $k => $v){
            if($v['payInfo']['actualFee'] == $order['amount']){
                if($v['statusInfo']['text'] == '交易成功'){
                    $data = ['is_exist'=>true, 'data'=>'成功-商品：' .$findQrcode['name'] .'-订单id：'.$v['orderInfo']['id'] , 'url'=>$v['orderInfo']['id']];
                }else{
                    $data = ['is_exist'=>false, 'data'=>'失败，找到订单，交易状态：' . $v['statusInfo']['text'] , 'url'=>$v['orderInfo']['id']];
                }
                
                break;
            }
            
        }

        return $data;

    }
    
    //淘宝核销查单
    public static function tbhxCheck($order){
        $url     = 'https://ma.taobao.com/consume/code.htm';
        $referer = 'https://ma.taobao.com/consume/code.htm';

        try {
            
            $order = Db::name('order')->where('id',$order['id'])->find();

            //找出该通道店铺的ck
            $findQrcode = Userqrcode::where(['id' => $order['qrcode_id']])->find();
            
            $header_arr = [
                'Cookie: ' . base64_decode($findQrcode['xl_cookie']),
                'referer: ' . $referer,
                //'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/110.0.0.0 Safari/537.36',
            ];
    
            $options = [
                CURLOPT_HTTPHEADER => $header_arr,
            ];
    
    
            $postData = '_tb_token_='.$findQrcode['token'].'&action=consume/code_action&event_submit_do_validate_code=%CC%E1%BD%BB&_fm.cod._0.co='.$order['zfb_code'].'&_fm.cod._0.m=';
            //halt($postData);
            $res = Http::post($url, $postData, $options);
            
            //获取编码
            $encode = mb_detect_encoding($res, array("ASCII",'UTF-8',"GB2312","GBK",'BIG5'));
            //转换编码
            $res = mb_convert_encoding($res, 'UTF-8', $encode);
            
            if(strstr($res,'淘宝网') != false && strstr($res,'手机扫码，安全登录') != false){
                
                //掉线更新通道，把通道关了
                $offLineData = [
                    'yd_is_diaoxian' => 0,
                    'status'         => Userqrcode::STATUS_OFF,
                    'remark'         => '淘宝失效'
                ];
                Userqrcode::where('id', $findQrcode['id'])->update($offLineData);
                
                $data = ['is_exist'=>false, 'data'=> '登录失效', 'url'=>''];
                return $data;
            }
            
            if($findQrcode['yd_is_diaoxian'] == 0){
                
                //改为在线
                $onLineData = [
                    'yd_is_diaoxian' => 1,
                    'remark'         => ''
                ];
                Userqrcode::where('id', $findQrcode['id'])->update($onLineData);
            }
            
            if(strstr($res,'无效的电子凭证码:不存在') != false){
                $data = ['is_exist'=>false, 'data'=> '电子凭证码:不存在', 'url'=>''];
                return $data;
            }
            
            if(strstr($res,'卖家未授权当前核销账号') != false){
                $data = ['is_exist'=>false, 'data'=> '卖家未授权当前核销账号', 'url'=>''];
                return $data;
            }
            
            if(strstr($res,'订单已退款') != false){
                $data = ['is_exist'=>false, 'data'=> '订单已退款', 'url'=>''];
                return $data;
            }
            
            if(strstr($res,'订单维权中') != false){
                $data = ['is_exist'=>false, 'data'=> '订单维权中', 'url'=>''];
                return $data;
            }
            
            if(strstr($res,'凭证已使用或者已取消') != false){
                $data = ['is_exist'=>false, 'data'=> '凭证已使用或者已取消', 'url'=>''];
                
                //如果有单号 再去核对一遍核销
                if (!empty($order['xl_order_id'])) {
                    $data = self::tbhxCheckByCheck($order);
                }
                
                return $data;
            }
            
            //取出商品单价
            $price_regex = '/<td class="price">([\d\.]+)<\/td>/';
            preg_match($price_regex, $res, $matches);
            $good_price = isset($matches[1]) ? $matches[1] : '';
            
            //取出数量
            $num_regex = '/<td class="num">(\d+)<\/td>/';
            preg_match($num_regex, $res, $matches);
            $good_num = isset($matches[1]) ? $matches[1] : '';
            
            //取出商品总价
            $order_price_regex = '/<td class="order-price" >\s*([\d\.]+)\s*<li>/';
            preg_match($order_price_regex, $res, $matches);
            $order_price = isset($matches[1]) ? $matches[1] : '';
            
            //halt($good_price,$good_num,$order_price);
            
            if($findQrcode['tb_good_price'] != $order_price && $order['pay_amount'] != $order_price){
                $data = ['is_exist'=>false, 'data'=> '核销码金额错误：'.$order_price, 'url'=>''];
                
                Order::where('id',$order['id'])->update(['hand_pay_data'=>'核销码金额错误：'.$order_price]);
                
                return $data;
            }
            if($good_num != 1){
                $data = ['is_exist'=>false, 'data'=> '核销商品数量错误', 'url'=>''];
                return $data;
            }
            
            if(strstr($res,'您要核销的') != false && strstr($res,'卖家：') != false && strstr($res,'本次核销') != false && strstr($res,'确认核销') != false){
                
                $data = self::tbhxCheckToConfirm($order, $findQrcode);
                
            }else{
                $data = ['is_exist'=>false, 'data'=> '查找失败', 'url'=>''];
            }
        
        } catch (\Exception $e) {
            
            Log::write("{$order['out_trade_no']}----淘宝核销异常----".$e->getLine() .'----'.$e->getMessage(), 'info');
            
            $data = ['is_exist'=>false, 'data'=> '淘宝核销异常', 'url'=>''];
            return $data;
        }
        
        return $data;
    }
    
    //淘宝核销查单
    public static function tbhxCheckToConfirm($order, $findQrcode){
        $url     = 'https://ma.taobao.com/consume/code.htm';
        $referer = 'https://ma.taobao.com/consume/code.htm';
        
        $header_arr = [
            'Cookie: ' . base64_decode($findQrcode['xl_cookie']),
            'referer: ' . $referer,
            //'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/110.0.0.0 Safari/537.36',
        ];
        
        $options = [
            CURLOPT_HTTPHEADER => $header_arr,
        ];
        
        $postData = '_tb_token_='.$findQrcode['token'].'&action=consume%2Fconfirm_action&event_submit_do_confirm=%CC%E1%BD%BB&code='.$order['zfb_code'].'&mobile=&consumeNum=1';
        
        //302跳转链接 直接get请求
        
        $url = self::curl_post($url, $postData, $header_arr);
        
        preg_match('/orderId=(\d+)/', $url, $matches);
        
        if(!isset($matches[1])){
            $data = ['is_exist'=>false, 'data'=> '确认核销失败，url错误' . $url, 'url'=>''];
            return $data;
        }
        
        $orderId = $matches[1];
        
        //单号更新到订单
        Order::where('id',$order['id'])->update(['xl_order_id'=>$orderId]);
            
        $res = Http::get($url, [], $options);
        
        //获取编码
        $encode = mb_detect_encoding($res, array("ASCII",'UTF-8',"GB2312","GBK",'BIG5'));
        //转换编码
        $res = mb_convert_encoding($res, 'UTF-8', $encode);
        
        if(strstr($res,'核销流程_确认消费_成功') != false && strstr($res,'核销成功啦，已消费') != false && strstr($res,'核销日期') != false){
            
            //取出核销日期
            $pattern = '/<em class="detail">(.*?)<\/em>/s';
            preg_match($pattern, $res, $matches);
            $hx_time = isset($matches[1]) ? $matches[1] : '';
            //获取编码
            $encode = mb_detect_encoding($hx_time, array("ASCII",'UTF-8',"GB2312","GBK",'BIG5'));
            //转换编码
            $hx_time = mb_convert_encoding($hx_time, 'UTF-8', $encode);
            
            //取出订单编号
            $pattern = '/<em>订单编号<\/em>：\s*<em>(\d+)<\/em>\s*<\/li>\s*<li>\s*<em>成交时间<\/em>：\s*<em>([\d-]+\s[\d:]+)<\/em>/';
            preg_match($pattern, $res, $matches);
            $orderNumber = $matches[1];
            $tb_order_pay_time = $matches[2];
            
            //取出商品总价
            $order_price_regex = '/<td class="order-price" >\s*([\d\.]+)\s*<li>/';
            preg_match($order_price_regex, $res, $matches);
            $order_price = isset($matches[1]) ? $matches[1] : '';
            
            //单号更新到订单
            Order::where('id',$order['id'])->update(['hc_pay_data'=>'tb成交时间'.$tb_order_pay_time.'核销时间'.$hx_time]);
            
            if($order['pay_amount'] == $order_price){
                $data = ['is_exist'=>true, 'data'=> '确认核销成功，核销日期：' . $hx_time .'金额' . $order_price, 'url'=>''];
            }else{
                $data = ['is_exist'=>false, 'data'=> '确认核销成功，金额：'.$order_price.'与订单金额'.$order['pay_amount'].'不匹配，不回调。核销日期：' . $hx_time, 'url'=>''];
            }
            
        }else{
            $data = ['is_exist'=>false, 'data'=> '核销码正确，确认核销失败', 'url'=>''];
        }
        
        return $data;
            
        
        
    }
    
    //淘宝核销-确认核销 用于有单号后手动查单确认
    public static function tbhxCheckByCheck($order){
        $url     = 'https://ma.taobao.com/consume/code.htm';
        $referer = 'https://ma.taobao.com/consume/code.htm';


        //找出该通道店铺的ck
        $findQrcode = Userqrcode::where(['id' => $order['qrcode_id']])->find();
        
        $header_arr = [
            'Cookie: ' . base64_decode($findQrcode['xl_cookie']),
            'referer: ' . $referer,
            //'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/110.0.0.0 Safari/537.36',
        ];

        $options = [
            CURLOPT_HTTPHEADER => $header_arr,
        ];


        $buyer       = '';
        $seller      = '';
        $consumeNum  = '1';
        $consumetime = urlencode(date('Y年m月d H:i:s'));
        
        //确认核销
        $url = 'https://ma.taobao.com/consume/success.htm?buyer='.$buyer.'&seller='.$seller.'&consumetime='.$consumetime.'&consumeNum='.$consumeNum.'&displayOrderInfoWithFormalBPM=display&orderId='.$order['xl_order_id'];
        
        $res    = Http::get($url, [], $options);
        //获取编码
        $encode = mb_detect_encoding($res, array("ASCII",'UTF-8',"GB2312","GBK",'BIG5'));
        //转换编码
        $res = mb_convert_encoding($res, 'UTF-8', $encode);
        
        if(strstr($res,'核销成功啦，已消费') != false && strstr($res,'核销日期') != false){
            
            //取出核销日期
            $pattern = '/<em class="detail">(.*?)<\/em>/s';
            preg_match($pattern, $res, $matches);
            $hx_time = isset($matches[1]) ? $matches[1] : '';
            //获取编码
            $encode = mb_detect_encoding($hx_time, array("ASCII",'UTF-8',"GB2312","GBK",'BIG5'));
            //转换编码
            $hx_time = mb_convert_encoding($hx_time, 'UTF-8', $encode);
            
            //取出订单编号和淘宝付款时间
            $pattern = '/<em>订单编号<\/em>：\s*<em>(\d+)<\/em>\s*<\/li>\s*<li>\s*<em>成交时间<\/em>：\s*<em>([\d-]+\s[\d:]+)<\/em>/';
            preg_match($pattern, $res, $matches);
            $orderNumber = $matches[1];
            $tb_order_pay_time = $matches[2];
            
            //取出商品总价
            $order_price_regex = '/<td class="order-price" >\s*([\d\.]+)\s*<li>/';
            preg_match($order_price_regex, $res, $matches);
            $order_price = isset($matches[1]) ? $matches[1] : '';
            
            //Order::where('id',$order['id'])->update(['xl_order_id'=>$orderNumber,'xl_pay_data'=>$seller,'hc_pay_data'=>$tb_order_pay_time]);
            
            
            $data = ['is_exist'=>true, 'data'=> '确认核销成功,成交时间：' . $tb_order_pay_time .'金额：' . $order_price, 'url'=>''];
        }else{
            
            $data = ['is_exist'=>false, 'data'=> '确认核销失败！！！', 'url'=>''];
        }
        
        return $data;
            
        
        
        
    }

    
    //拼多多代付模式查单
    public static function pddDfCheck($order){
        
        //找出该通道的ck
        $findQrcode = Userqrcode::where(['id' => $order['qrcode_id']])->find();
        
        $url = $order['hand_pay_data'];

        $header_arr = [
            'Cookie: ' . $findQrcode['xl_cookie'],
            //'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/110.0.0.0 Safari/537.36',
        ];

        $options = [
            CURLOPT_HTTPHEADER => $header_arr,
        ];
    
        $res = Http::get($url, [], $options);
        
        if(strstr($res,'帮我付一下这件商品吧') != false && strstr($res,'如果订单申请退款') != false && strstr($res,'立即支付') != false){
            $data = ['is_exist'=>false, 'data'=> '未支付', 'url'=>''];
            return $data;
        }
        
        if(strstr($res,'已经有人帮我代付') != false && strstr($res,'订单已支付') != false){
            $data = ['is_exist'=>true, 'data'=> '订单已支付', 'url'=>''];
            
            //预产码修改为已支付
            Db::name('tb_qrcode')->where(['id'=>$order['xl_pay_data']])->update(['pay_status'=>1,'update_time'=>time()]);
        }else{
            
            $data = ['is_exist'=>false, 'data'=> '未支付', 'url'=>''];
        }
        
        return $data;
    }
    
    //我秀查单
    public static function woxiuCheck($order){
        
        //找出该通道的ck
        $findQrcode = Userqrcode::where(['id' => $order['qrcode_id']])->find();
        
        $url = 'https://pay.woxiu.com/xiu/wap/h5_check.php?order='.$order['xl_order_id'];

        $header_arr = [
            'User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1',
        ];

        $options = [
            CURLOPT_HTTPHEADER => $header_arr,
        ];
    
        $res = Http::get($url, [], $options);
        $result = json_decode($res,true);

        if($result['code'] != 1){
            $data = ['is_exist'=>false, 'data'=> $result['msg'], 'url'=>''];
        }else{
            $data = ['is_exist'=>true, 'data'=> $result['msg'] .'金额'.$result['rmb'], 'url'=>''];
        }

        return $data;
    }
    
    //我秀微信小程序h5查单
    public static function woxiuCheckWxH5($order){
        
        //找出该通道的ck
        $findQrcode = Userqrcode::where(['id' => $order['qrcode_id']])->find();
        
        $url = 'https://pay.xiu521.com/Pay';
        
        $post_data = 'fxid=6666000134264302&fxordernum=f38fd21fea2c49c9&fxsign=88221c2478490c7a12e3195adc0cd8fd&fxaction=orderquery&t=0.005814803296002813';
        
        $header_arr = [
            'User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1',
        ];

        $options = [
            CURLOPT_HTTPHEADER => $header_arr,
        ];
    
        $res = Http::get($url, [], $options);
        $result = json_decode($res,true);

        if($result['code'] != 1){
            $data = ['is_exist'=>false, 'data'=> $result['msg'], 'url'=>''];
        }else{
            $data = ['is_exist'=>true, 'data'=> $result['msg'] .'金额'.$result['rmb'], 'url'=>''];
        }

        return $data;
    }
    
    //支付宝云端查单
    public static function zfbYdCheck($system_order,$is_uid){
        
        //找出该通道的ck
        $row = Userqrcode::where(['id' => $system_order['qrcode_id']])->find();
        
        $aliobj = new Alipay();

        $cookie = base64_decode($row['cookie']);
                
        /*$m = $aliobj->GetMyMoney_2($cookie);
        
        if ($m['status'] == false) {
            $data = ['is_exist'=>false, 'data'=> '支付宝掉线', 'url'=>''];
            return $data;
        }*/
        

        //获取支付宝订单列表
        //$orders = $aliobj->getAliOrderV2($row['cookie'],$row['zfb_pid']);//获取订单请求
        $orders = $aliobj->getAliOrder($cookie, $row['zfb_pid'], 60*60*5);//获取订单请求
        
        
        
        if(!is_array($orders)){
            $data = ['is_exist'=>false, 'data'=> $orders, 'url'=>''];
            return $data;
        }
        if($orders['status'] === 'failed'){
            $data = ['is_exist'=>false, 'data'=> $orders['msg'], 'url'=>''];
            return $data;
        }
        
        if ($orders['status'] === 'deny') {
            //请求频繁或者掉线
            $data = ['is_exist'=>false, 'data'=> '掉线了', 'url'=>''];
            return $data;
        }

        $orderList = empty($orders['result']['detail']) ? [] : $orders['result']['detail'];
        
        
        
        $is_find = false;
        $url     = '';
        foreach ($orderList as $order) {
            if($is_uid){
                //uid模式
                $pay_money = $order['tradeAmount'];//⾦额
                $pay_des   = $order['transMemo'];//备注
                $tradeNo   = $order['tradeNo'];//⽀付宝订单号
                if (!empty($pay_des)) {
                    if($system_order['pay_remark'] == $pay_des && $system_order['pay_amount'] == $pay_money){
                        
                        $is_find = true;
                    }
                }
            }else{
                //个码模式
                $pay_money   = $order['tradeAmount'];//⾦额
                $tradeNo     = $order['tradeNo'];//⽀付宝订单号
                $signProduct = $order['signProduct'];//转账模式
                $balance     = $order['balance'];//余额
                $tradeTime   = strtotime($order['tradeTime']);//交易时间
                
                $now_time = time();
                $orderrow = Order::where([
                    'id'          => $system_order['id'],
                    'qrcode_id'   => $system_order['qrcode_id'],
                    'pay_type'    => $row['acc_code'],
                    'pay_amount'  => sprintf("%.2f", $pay_money),
                    
                ])
                ->where("createtime",'<',$tradeTime)
                ->where("expire_time",'>',$tradeTime)
                ->limit(1, 1)
                ->find();
    
                if (!empty($orderrow)) {
                    $is_find = true;
                    $url = $order['tradeNo'];//单号
                }
                
            }
            
        }

        if($is_find){
            $data = ['is_exist'=>true, 'data'=> '找到订单，单号：'.$url, 'url'=>''];
            
        }else{
            $data = ['is_exist'=>false, 'data'=> '没找到订单', 'url'=>''];
        }

        return $data;
    }

    //支付宝主体模式查单
    public static function checkAlipayAppOrder($order){
        
        $zhuti = Db::name('alipay_zhuti')->where('id',$order['xl_user_id'])->find();
        if(empty($zhuti)){
            return ['is_exist' => false, 'data' => '未绑定主体', 'url' => ''];
        }
        
        $alipaySdk = new AlipaySdk();
        $pay_res = $alipaySdk->alipayCheckOrder($order['out_trade_no'], $order['pay_amount'], $order['hand_pay_data'], $zhuti);
        
        if ($pay_res['trade_status'] == 'TRADE_SUCCESS' && $pay_res['ali_out_trade_no'] == $order['out_trade_no'] && $pay_res['total_amount'] == $order['pay_amount']) {
            
            $data = ['is_exist' => true, 'data' => '已支付|'.$pay_res['trade_no'], 'url' => $pay_res['trade_no']];
        } else {
            $data = ['is_exist' => false, 'data' => $pay_res['msg'].'|'.$pay_res['trade_status'].'|'.$pay_res['trade_no'], 'url' => ''];
        }
        
        return $data;
    }
    
    //支付宝主体模式查单 个码
    public static function checkAlipayGmOrder($order){
        
        $zhuti      = Db::name('alipay_zhuti')->where('id',$order['xl_user_id'])->find();
        if(empty($zhuti)){
            $data = ['is_exist' => false, 'data' => '无绑定主体', 'url' => ''];
            return $data;
        }

        $findQrcode     = Db::name('group_qrcode')->where(['id' => $order['qrcode_id']])->find();
        $zfb_pid        = $order['zfb_user_id'];
        $app_auth_token = $order['hand_pay_data'];
        $alipaySdk      = new AlipaySdk();
        if(empty($app_auth_token)){
            return ['is_exist' => false, 'data' => '未跳转支付', 'url' => ''];
        }
        
        if($findQrcode['type'] == 1){

            $alipayConfig = [
                'appid'       => $findQrcode['auth_app_id'],    //个码应用的appid
                'private_key' => $zhuti['alipay_private_key'],  //主体的私钥
                'public_key'  => $findQrcode['xl_cookie'], //个码应用生成的公钥
            ];
            
            $pay_res = $alipaySdk->alipayGmCheckOrderByPublicKey($order, $alipayConfig, $zfb_pid);
        }else{
            $pay_res = $alipaySdk->alipayGmCheckOrder($order, $app_auth_token, $zhuti, $zfb_pid);
        }
        
        
        $alipay_order_no = '';
        $trans_amount    = '';
        $direction       = '';
        $trans_dt_ts     = '';
        $trans_dt        = '';
        
        if(!$pay_res['status']){
            $data = ['is_exist' => false, 'data' => $pay_res['msg'], 'url' => ''];
            return $data;
        }
        
        $alipayOrderList = $pay_res['data'];
        
        $order = Db::name('order')->where('out_trade_no',$order['out_trade_no'])->find();
        
        foreach ($alipayOrderList as $k => $v){
            
            
            $trans_dt_ts = strtotime($v->trans_dt);
            $trans_dt    = $v->trans_dt;
            $trans_memo  = isset($v->trans_memo) ? $v->trans_memo : '';
            
            if($order['pay_type'] == '1056'){ //1056uid模式匹配备注
            
                if($order['pay_amount'] == $v->trans_amount && $trans_dt_ts > $order['createtime'] && $trans_dt_ts < $order['expire_time'] && $trans_memo == $order['pay_remark']){
                    
                    $order_third_id = Db::name('order')->where('xl_order_id',$v->alipay_order_no)->where('id','<>',$order['id'])->find();
                    
                    if(!$order_third_id){
                        $alipay_order_no = $v->alipay_order_no;
                        $trans_amount    = $v->trans_amount;
                        $direction       = $v->direction;
                        break;
                    }else{
                        Log::write($order['out_trade_no']."----支付宝id存在----".$v->alipay_order_no, 'info');
                    }
                }
            }else{
                if($order['pay_amount'] == $v->trans_amount && $trans_dt_ts > $order['createtime'] && $trans_dt_ts < $order['expire_time']){
                
                    $order_third_id = Db::name('order')->where('xl_order_id',$v->alipay_order_no)->where('id','<>',$order['id'])->find();
                    
                    if(!$order_third_id){
                        $alipay_order_no = $v->alipay_order_no;
                        $trans_amount    = $v->trans_amount;
                        $direction       = $v->direction;
                        break;
                    }else{
                        Log::write($order['out_trade_no']."----支付宝id存在----".$v->alipay_order_no, 'info');
                    }
                    
                }
            }
            
            
        }
        
        if(!empty($alipay_order_no) && !empty($trans_amount) && !empty($trans_dt_ts)){
            $data = ['is_exist' => true, 'data' => '已支付|'.$trans_dt.'|'.$alipay_order_no, 'url' => $alipay_order_no];
        }else{
            $data = ['is_exist' => false, 'data' => '无查询结果', 'url' => ''];
        }
        
        
        return $data;
    }
    
    //支付宝主体模式查单 调用官方发起支付的 当面付 手机网站 订单码
    public static function checkAlipayYsOrder($order){
        
        $zhuti = Db::name('alipay_zhuti')->where('id',$order['xl_user_id'])->find();
        if(empty($zhuti)){
            return ['is_exist' => false, 'data' => '未绑定主体', 'url' => ''];
        }
        
        $findQrcode = Db::name('group_qrcode')->where(['id' => $order['qrcode_id']])->find();
        
        $alipaySdk = new AlipaySdk();
        
        if($findQrcode['type'] == 1){
            
            $zhuti = [
                'appid'              => $findQrcode['auth_app_id'],    //个码应用的appid
                'alipay_private_key' => $zhuti['alipay_private_key'],  //主体的私钥
                'alipay_public_key'  => $findQrcode['xl_cookie'],      //个码应用生成的公钥
            ];
            
        }
        
        $pay_res = $alipaySdk->alipayCheckYsOrder($order['out_trade_no'], $order['pay_amount'], $findQrcode, $zhuti);
        
        
        if($pay_res['status'] == false){
            return ['is_exist' => false, 'data' => $pay_res['msg'], 'url' => ''];
        }
        
        if ($pay_res['trade_status'] == 'TRADE_SUCCESS' && $pay_res['ali_out_trade_no'] == $order['out_trade_no'] && $pay_res['total_amount'] == $order['pay_amount']) {
            
            $data = ['is_exist' => true, 'data' => '已支付|'.$pay_res['send_pay_date'].'|'.$pay_res['trade_no'], 'url' => $pay_res['trade_no']];
        } else {
            $data = ['is_exist' => false, 'data' => $pay_res['msg'].'|'.$pay_res['trade_status'].'|'.$pay_res['trade_no'], 'url' => ''];
        }
        
        return $data;
    }
    
    //支付宝主体模式退款
    public static function alipayOrderRefund($order_no, $amount, $app_auth_token, $zhuti_id){
        
        $zhuti = Db::name('alipay_zhuti')->where('id', $zhuti_id)->find();
        if(empty($zhuti)){
            return ['is_exist' => false, 'data' => '未绑定主体', 'url' => ''];
        }
        
        $alipaySdk = new AlipaySdk();
        $pay_res = $alipaySdk->alipayOrderRefund($order_no, $amount, $app_auth_token, $zhuti);
        
        if($pay_res['status'] == false){
            $data = ['is_exist' => false, 'data' => $pay_res['msg'], 'url' => ''];
        }else{
            $data = ['is_exist' => true, 'data' => $pay_res['msg'], 'url' => ''];
        }
        
        return $data;
    }
    
    //支付宝主体模式 查余额
    public static function alipayQueryBalance($qrcode){
        
        $zhuti = Db::name('alipay_zhuti')->where('id', $qrcode['zhuti_id'])->find();
        if(empty($zhuti)){
            return ['status' => false, 'data' => '未绑定主体'];
        }
        
        $alipaySdk = new AlipaySdk();
        
        if($qrcode['type'] == 1){
            if(empty($qrcode['auth_app_id']) || empty($qrcode['xl_cookie'])){
                return ['status' => false, 'data' => '基础模式查询失败'];
            }
            $zhuti = [
                'appid'              => $qrcode['auth_app_id'],        //个码应用的appid
                'alipay_private_key' => $zhuti['alipay_private_key'],  //主体的私钥
                'alipay_public_key'  => $qrcode['xl_cookie'],          //个码应用生成的公钥
            ];
            
        }else{
            if(empty($qrcode['app_auth_token'])){
                return ['status' => false, 'data' => '无授权'];
            }
        }
        
        $balance = $alipaySdk->alipayQueryBalance($zhuti, $qrcode);
        
        
        return ['status' => true, 'data' => $balance];
    }
    
    //关停通道码 
    public static function closeQrcode($user_id, $qrcode){

        $num = Db::name('order')
            ->where(['qrcode_id'=>$qrcode['id']])
            ->whereDay('createtime')
            ->count();

        $money = Db::name('order')
            ->where(['status'=>1, 'qrcode_id'=>$qrcode['id']])
            ->whereDay('createtime')
            ->sum('amount');

        if($num >= $qrcode['max_order_num'] || $money >= $qrcode['max_money']){
            $remark = '上限自动关停';
            //关停该码
            if($num >= $qrcode['max_order_num']){
                $remark = '笔数上限自动关停';
            }
            if($money >= $qrcode['max_money']){
                $remark = '金额上限自动关停';
            }


            Userqrcode::where('id',$qrcode['id'])->update(['status'=>Userqrcode::STATUS_OFF, 'remark'=>$remark, 'update_time'=>time()]);

        }



    }
    
    public static function curl_post($url, $post_data, $headerArr=''){

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
        curl_setopt($curl, CURLOPT_POSTFIELDS, $post_data);
        //超时时间
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 20);
        //curl_setopt($curl, CURLOPT_COOKIE, $cookie);
        //执行命令
        $data = curl_exec($curl);
        $errdata = curl_error($curl);
        $response_headers = curl_getinfo($curl);
    
    
        // 返回的是302跳转，要从返回头里提取
        //var_dump($response_headers['url']);
    
        if (FALSE === $data || !empty($errdata)) {
            $errno = curl_errno($curl);
            $info = curl_getinfo($curl);
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
    
    
    //通用查单
    public static function commonQueryOrder($order){
        
        //支付宝云端
        if ($order['pay_type'] == '1008' || $order['pay_type'] == '1041'  || $order['pay_type'] == '1025') {
            
            if($order['pay_type'] == '1008' || $order['pay_type'] == '1041' ){
                $is_uid = true;
            }else{
                $is_uid = false;
            }
            
            $check_res = self::zfbYdCheck($order,$is_uid);
            
            return $check_res;
        }
        
        //支付宝主体模式 原生模式 app 电脑 手机网站 当面付 jsapi
        if (in_array($order['pay_type'], ['1065'])) {
            $check_res = self::checkAlipayYsOrder($order);
            return $check_res;
        }
        
        
        //支付宝主体模式 app 电脑 手机网站 当面付 jsapi
        if (in_array($order['pay_type'], ['1050','1051','1052','1053','1054'])) {
            
            $check_res = self::checkAlipayAppOrder($order);
            return $check_res;
        }
        
        //支付宝主体模式-uid个码模式
        if (in_array($order['pay_type'], Config::get('mchconf.zhuti_acc_code'))) {
            
            $check_res = self::checkAlipayGmOrder($order);
            return $check_res;
        }
        
        
        switch ($order['pay_type']) {
            case '1014':
                //迅雷直播
                $check_res = self::xunLeiCheck($order);
                break;
            case '1020':
                //皮皮直播
                $check_res = self::ppCheck($order);
                break;
            case '1026':
                $check_res = self::baizhanCheck($order);
                break;
            case '1027':
                $check_res = self::baizhanCheck($order);
                break;
            case '1028':
                $check_res = self::gmmCheck($order);
                break;
            case '1029':
                //愿聊 迅雷之锤支付宝查单
                $check_res = self::alipayH5Check($order);
                break;
            case '1030':
                //uki支付宝查单
                $check_res = self::alipayH5Check($order);
                break;
            case '1031':
                //淘宝直付查单
                $check_res = self::tbzfCheck($order);
                break;
            case '1032':
                //淘宝直付核销模式查单
                $check_res = self::tbhxCheck($order);
                break;
            case '1033':
                //我秀查单
                $check_res = self::woxiuCheck($order);
                break;
            case '1035':
                $check_res = self::pddDfCheck($order);
                break;
            default:
                $check_res = ['is_exist' => false, 'data' => '查单类型错误'];
        }
        
        return $check_res;
    }
    
    
}