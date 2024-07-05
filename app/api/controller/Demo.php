<?php

namespace app\api\controller;

use app\common\controller\Api;
use app\common\library\Utils;
use app\common\library\Wxpush;
use app\common\library\Rsa;
use think\cache\driver\Redis;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Db;
use think\Request;
use think\facade\Log;
use fast\Random;
use fast\Http;
use app\common\library\Accutils;
use app\common\library\AlipaySdk;

//use fast\Rsa;
use app\common\library\AgentUtil;
use app\common\library\MoneyLog;


/**
 * 示例接口.
 */
class Demo extends Api
{
    //如果$noNeedLogin为空表示所有接口都需要登录才能请求
    //如果$noNeedRight为空表示所有接口都需要验证权限才能请求
    //如果接口已经设置无需登录,那也就无需鉴权了
    //
    // 无需登录的接口,*表示全部
    protected $noNeedLogin = ['*'];
    //protected $noNeedLogin = ['robinTest','viewtest','test', 'ftttest','test1','getcallback','htAddIp', 'moneytest','redisTest','editmoneytest','checkorder','curlToPayTest','topaytest','filedemo','doit','xgaddip','signtest','taotaotest','hczfbtest'];
    
    // 无需鉴权的接口,*表示全部
    protected $noNeedRight = ['test2'];
    
    //同步商户通道
    public function syncMeracc(){
        $agentList = Db::name('agent')->select()->toArray();
        foreach ($agentList as $k1 => $v1){
            //找出代理下面的商户
            $merList = Db::name('merchant')->where('agent_id', $v1['id'])->select()->toArray();
            foreach ($merList as $k2 => $v2){
                Utils::syncMerAcc($v1['id'], $v2['id']);
            }
        }
        halt(1);
        //for($i = 1; $i<=40; $i++){
            
        
            $url = 'https://wxapi.xinhesk.com/tocard';
            
            $token = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpZCI6MTM5LCJ1c2VybmFtZSI6IiIsInNob3BpZCI6MTM2LCJyYXRlZ3JvdXAiOjAsIm1vYmlsZSI6IjE1Nzc5MDMyNDU2IiwiZW1haWwiOm51bGwsIm1vbmV5IjoiMTkuMDMwMCIsImxhc3RfbG9naW5faXAiOiIxMDMuMTUxLjE3Mi44NiIsInl1dGkiOjAsInVzZXJSZWFsIjp7ImlkIjoyMTAsInVpZCI6MTM5LCJuYW1lIjoiXHU0ZTAxXHU2NzcwIiwicmV0eXBlIjoxLCJjbGFzIjoxLCJoYXN0eXBlIjoyLCJpZGNhcmQiOiIzNjA3MzExOTk4MDUxNjM0MVgiLCJjYW5hZGEiOm51bGwsInBvc2l0aXZlX2ltZyI6bnVsbCwiYmFja19pbWciOm51bGwsImhhbmRfaW1nIjpudWxsLCJjYW5hZGFfaW1nIjpudWxsLCJ4dWtlX2ltZyI6bnVsbCwiY29tcGFueV9uYW1lIjpudWxsLCJyZW1hcmtzIjoiIiwiY3JlYXRlX3RpbWUiOiIyMDIzLTA3LTI0IDAxOjA1OjQzIiwidXBkYXRlX3RpbWUiOiIyMDIzLTA3LTI0IDAxOjA1OjQzIiwiZGVsZXRlX3RpbWUiOjAsIm9yZGVybm8iOiIyZmExOTAwMDU3ODNmZWFjN2QyNDE0YTg0ODkzMDg2MiIsImV2aWRlbmNlSGFzaCI6IkU0MjRGRDJBOTFBN0NDN0UiLCJldmlkZW5jZXVybCI6Imh0dHBzOlwvXC95aXNhYXMtaW1nLm9zcy1jbi1iZWlqaW5nLmFsaXl1bmNzLmNvbVwvZGF0YVwvd3d3cm9vdFwvd3d3LmVjbG91ZHNpZ24uY29tXC91c2VyZGF0YVwvZG93bmxvYWRcL2Rvd25sb2FkXC81ZlwvYjdcLyVFNSVBRCU5OCVFOCVBRiU4MSVFNiU4QSVBNSVFNSU5MSU4QS5wIiwicWlhbnVybCI6bnVsbH0sImF1ZCI6IiIsImV4cCI6MTcwNzc0MDE3MywiaWF0IjoxNjk5OTY0MTczLCJpc3MiOiIiLCJqdGkiOiJlNDg4MTlmNDFmOThlZDQ1MzhlZGZhYzZlMDAyZGJiMyIsIm5iZiI6MTY5OTk2NDE3Mywic3ViIjoiIn0.HMniHxPEnMenzU4fr-PjkQj6FGQ8WlT9BtzTFFIKQY8';
            
            $postData = [
                'cardno'    => 'RC3010435917',
                'cardprice' => 30,
                'cardpsw'   => '296341172949476',
                'cardtype'  => 131,
                'feilv'     => '',
                'type'      => 0,
            ];  
            
            $header_arr = [
                'Content-Type: application/json',
                'Origin: https://wx.xinhesk.com',
                'Authorization: bearer ' . $token,
                'User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 16_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.6 Mobile/15E128 Safari/514.1'
            ];
            
            $options = [
                CURLOPT_HTTPHEADER => $header_arr,
            ];
            
            //$daili = AgentUtil::liuguanDaili('1243443545354');halt($daili);
            /*if (!empty($daili)) {
                
                if($daili['code'] == 0 || isset($daili['serialNo'])){
                    $daili = $daili['data'][0];
                    $options[CURLOPT_PROXY] = $daili['ip'];
                    $options[CURLOPT_PROXYPORT] = $daili['port'];
                    
                    //$proxyServer = "http://".$daili['ip'].":".$daili['port'];
                    //$options[CURLOPT_PROXYAUTH] = CURLAUTH_BASIC;
                    //$options[CURLOPT_PROXY]     = $proxyServer;
                }
            }else{
                echo '无代理';
            }
            
            /*$options[CURLOPT_PROXY] = '183.162.226.240';
            $options[CURLOPT_PROXYPORT] = '20065';*/
            
            /*$options[CURLOPT_PROXY] = '183.162.226.244';
            $options[CURLOPT_PROXYPORT] = '39012';*/
            
            //$options = AgentUtil::getProxyByCache($options);
            
            $res  = Http::post($url, json_encode($postData), $options);
            
            
            if (strstr($res, 'Empty') != false) {
                //加上代理ip
                AgentUtil::setProxyCache(2, 121212, 1, false);
            }
            
            echo $res; //$this->success('11',$res);
            
            //usleep(1500000);
        //};
        
        /*$trade_no = 'P1722248400553263104';
        $order    = Db::name('order')->where('trade_no', $trade_no)->find();
        MoneyLog::checkMoneyRateType($order['mer_id'], $order['amount'], $order['mer_fees'], $order['trade_no'], $order['out_trade_no'],'merchant');*/
        
        /*$agentList = Db::name('agent')->select()->toArray();
        foreach ($agentList as $k1 => $v1){
            //找出代理下面的商户
            $merList = Db::name('merchant')->where('agent_id', $v1['id'])->select()->toArray();
            foreach ($merList as $k2 => $v2){
                Utils::syncMerAcc($v1['id'], $v2['id']);
            }
        }*/
        
        
    }
    
    public function alipayurl(){
        
        return view();
    }
    
    
    public function subAliUrl(){
        $qrcode_id = $this->request->post('qrcode_id');
$pay_url= html_entity_decode($this->request->post('pay_url')); 
$business_url = html_entity_decode($this->request->post('business_url')); 
$username  = $this->request->post('username');
        
        Log::write(json_encode($_POST, JSON_UNESCAPED_UNICODE), 'info');

        $user = Db::name('user')->where('username', $username)->find();
        if(!$user){
            $this->error('账号不存在');
        }
        
        if(!is_numeric($qrcode_id)){
            $this->error('请输入id');
        }
        
        $qrcode = Db::name('group_qrcode')->where(['id' => $qrcode_id, 'user_id' => $user['id']])->find();
        if(!$qrcode){
            $this->error('码不存在');
        }
        
        $update = [];
        
        if($business_url){
            $update['business_url'] = $business_url;
        }
        
        if($pay_url){
            $update['pay_url'] = $pay_url;
        }
        
        if(empty($update)){
            $this->error('失败，无数据');
        }else{
            $update['update_time'] = time();
        }
        
        $re = Db::name('group_qrcode')->where('id', $qrcode_id)->update($update);
        
        if($re){
            $this->success('更新成功');
        }
        
        $this->error('失败请重试');
    }
    
    public function balanceTest(){
        /*$row = Db::name('group_qrcode')->where('id', 2468)->find();
        $zhuti = Db::name('alipay_zhuti')->where('id', $row['zhuti_id'])->find();
        $alipaySDK = new AlipaySdk();
        $balance = $alipaySDK->alipayQueryBalance($row['zfb_pid'], $row['app_auth_token'], $zhuti);*/
        $order = Db::name('order')->where('out_trade_no', '202402297091462277529')->find();
        //商户加余额
        //MoneyLog::checkMoneyRateType($order['mer_id'],$order['amount'], 0, $order['trade_no'], $order['out_trade_no'],'merchant');
        $rate_type = Config::get('site.user_rate_type');
        halt($order['amount'],$order['fees']);
        MoneyLog::checkMoneyRateType($order['user_id'],$order['amount'], $order['fees'], $order['trade_no'], $order['out_trade_no'],'user');
    
    }
    public function agenttest(){
        /*$agentList = Db::name('agent')->order('id','asc')->select();
        foreach ($agentList as $k => $v){
            $userList = Db::name('user')->where(['agent_id'=>$v['id']])->order('id','asc')->select();

            foreach ($userList as $k1 =>$v1){
                //同步绑定代理下的所有商户
                Utils::syncMerUserByAddUser($v['id'], $v1['id']);
            }

        }*/

        /*$num = 0;
        $tbList = Db::name('tb_order_test')->select();
        foreach ($tbList as $k => $v){
            
            preg_match('/\d+/', $v['tb_order_id'], $matches);
            $tb_order_id = $matches[0];

            
            $findorder = Db::name('order')->where('xl_order_id', $tb_order_id)->find();
            if($findorder){
                $num++;
            }
        }
        halt($num);*/
        $no_find = [];
        $num = 0;
        /*$tbList = Db::name('order_test')->select();
        foreach ($tbList as $k => $v){
            $order_id = trim($v['order_id']);
            $findorder = Db::name('order')->where(['mer_id'=>9, 'status'=>1])->where('trade_no', $order_id)->whereDay('createtime','yesterday')->whereNull('deletetime')->find();
            if($findorder){
                $num++;
            }else{
                $no_find[] = $order_id;
            }
        }*/
        /*$tbList = Db::name('order')->where(['mer_id'=>9, 'status'=>1])->whereDay('createtime','yesterday')->select();
        
        foreach ($tbList as $k => $v){
            $order_id = trim($v['trade_no']);
            $findorder = Db::name('order_test')->where('order_id', $order_id)->find();
            if($findorder){
                $num++;
            }else{
                $no_find[] = $order_id;
            }
        }*/
        
        halt($num,$no_find);
        
    }
    public function signtest(){
        $num = Random::numeric(8);
        
        //公钥
        $public_key  = 'MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCWvajWTY4AqEB0rLlk7VbjZulUzysJtofrkf9k
k1gc6scLaiy+0NTij4HtcnGKHQzaxG10sCsMqCaq8n8BauQkpzk+PBNm2IezN8tJxqXb1RHGFFnW
UZKvk1zreaiGadQp0aeUw5IATY+VFG6TZUI1zV9mr27F6qazLi+hGnTy/wIDAQAB';
        
        //私钥
        $private_key = 'MIICdgIBADANBgkqhkiG9w0BAQEFAASCAmAwggJcAgEAAoGBAJa9qNZNjgCoQHSs
uWTtVuNm6VTPKwm2h+uR/2STWBzqxwtqLL7Q1OKPge1ycYodDNrEbXSwKwyoJqry
fwFq5CSnOT48E2bYh7M3y0nGpdvVEcYUWdZRkq+TXOt5qIZp1CnRp5TDkgBNj5UU
bpNlQjXNX2avbsXqprMuL6EadPL/AgMBAAECgYA+7/Mlfv4SMi2vSUqi5CGKErbL
TTA3/vkjFzMd6BM7B5+RmYQTK5gm/CoQXN8g/l4WnTriJIfk4fQ7HcJ/cgTM0Lf3
lkGQ3uc+6qMV67HhBZEmbRWlVobb/j4pE1dw0hsdtlvti0xoMKh4sIiDphuGi1F5
mreS/jm0TcWXs9fqkQJBAM31wu2qbgU7f6tDzlE9DmoaK+7859VQgLirFQPcn+K3
vKohEwnHG3NoGKwbH486wlvqVtaLjmRyfO0EDqnAN8cCQQC7XWTjUF2EMbLHN66q
F9wLxXcKiiAA5HZNhAd9GTSDA4q7kGMjzWy+OwukqxhQEpQAov/Ft1A02YDjz4a2
pxsJAkABGzDQ1fmBTbCB2vtgtFM/fqR9xB36p1QJqeGTA7xYG2SIBWV0x/z9wbFg
O0UQH+CrXbbZsCYzo+nH3B24C7BBAkEAmqxQ2u6/JKA6bAdlo3kq6HTM/uBj5xiz
KO6zl+w002sbHhfmH+o3uRrZU8kCuyd7EsN8zmW0Ssy7gFUTarmssQJAHbFfIXbL
IUG/IF6jaIo0t6TqAQFbM2FP6+hQ2p8p9MD4xWPODiTKCmVUtMuEvhq2dsU/ypCv
wVVakqXKmoqzVQ==';
dump($private_key);
        $rsa = new Rsa($public_key,$private_key);
        //$rsa_arr = $rsa->create_key();
        $str = 'dj55566622';
        //私钥加密
        $str1 = $rsa->private_encrypt($str);
        dump($str1);
        $str2 = $rsa->public_decrypt($str1);
        var_dump($str2);die;
        var_dump($rsa_arr);die;
        $amount     = $this->request->post('amount');//金额
        $trade_no   = $this->request->post('trade_no');//商户平台订单号
        $mer_no     = $this->request->post('mer_no');//商户编号
        $return_type= $this->request->post('return_type');//返回类型 html/json
        $pay_type   = $this->request->post('pay_type');//通道编码
        $sign       = $this->request->post('sign');//加密值
        $notify_url = $this->request->post('notify_url');//异步回调地址
        $return_url = $this->request->post('return_url');//同步回调地址
        $remark     = $this->request->post('remark');//备注
        $waitsign   = $this->request->post();

        if (empty($amount)  || empty($trade_no) || empty($mer_no) || empty($return_type) || empty($sign) || empty($notify_url) || empty($return_url) || empty($pay_type)){
            $this->error('参数缺少');
        }
        //根据mer_no找到商户
        $findmerchant = Db::name('merchant')->where(['number'=>$mer_no,'status'=>'normal'])->field('id,agent_id,username,money,rate,add_money,api_ip,userids,secret_key,last_money_time,min_money,max_money,is_diy_rate,diyratejson,status')->find();

        if(!$findmerchant){
            $this->error('信息不存在');
        }

    
        $mysign = Utils::sign($waitsign,$findmerchant['secret_key']);

        if($mysign != $sign){
            $this->error('签名错误!');
        }
        
        
    }
    
    public function getsqCache(){
        $acc_id = '2061';
        $dd = Cache::get($acc_id);
        halt($dd);
    }
    //余额检测
    public function moneytest(){
        //->whereDay('create_time','today')
        $list = Db::name('user_money_log')->where('user_id',80)->order('id asc')->whereDay('create_time','yesterday')->select();
        $count = count($list);

        //如果找不到记录则说明这个merid不存在或者没记录
        if ($count < 1){
            echo "一共".$count."条记录，"."无记录\n";
            die;
        }

        foreach($list as $key => $value){

            //当前数据条数的变更后余额 是否等于变更前的余额
            //判断是增加还是减少 0支出 1增加 2冻结减少 3解冻增加
            if($value['type'] == 0 || $value['type'] == 2){

                $money = bcsub($value['before_amount'],$value['amount'],2);

                if($money != $value['after_amount']){

                    $msg =  "id：".$value['id']."\n"."商户id：".$value['user_id'];
                    
                    halt($msg);
                    
                    break;
                }

            }elseif($value['type'] == 1 || $value['type'] == 3){
                $money = bcadd($value['before_amount'],$value['amount'],2);

                if($money != $value['after_amount']){

                    $msg =  "id：".$value['id']."\n"."商户id：".$value['user_id'];
                    
                    halt($msg);
                    
                    break;
                }
            }

            //如果是最后一条记录则停止
            if($value['id'] == $list[$count-1]['id']){
                echo "一共".$count."条记录，".$list[$count-1]['id']."完事了\n";
                break;
            }


            //金额正确了再判断是否跟下一条的变更前金额相等
            if($money != $list[$key+1]['before_amount']){

                $msg =  "当前id：".$value['id']."   更新后金额：".$value['after_amount']."\n"."订单号：".$value['out_trade_no']."\n"."下一id：".$list[$key+1]['id']."   更新前金额：".$list[$key+1]['before_amount']."\n"."下一订单号：".$list[$key+1]['out_trade_no'];
                

                halt($msg);
                break;
            }

        }
        
        echo '执行成功'."\n";

    }
    
    public function moneytest2(){
        
        $list = Db::name('order')->where(['user_id'=>80,'status'=>1])->where('createtime','BETWEEN', ['1686061620','1686067199'])->order('id asc')->select();
        
        $num = 0;
        $num1 = 0;
        $num2 = 0;
        foreach ($list as $k => $v){
            $count = Db::name('user_money_log')->where('out_trade_no',$v['out_trade_no'])->count();
            if($count == 2){
                $num++;
            }elseif($count == 4){
                $num1++;
            }else{
                $num2++;
                var_dump($v['out_trade_no']);
            }
        }
        
        halt($num,$num1,$num2);
                

    }
    # 多维数组转换xml
    public function arr2xmls($arr){
        $simxml = new SimpleXMLElement("<?xml version='1.0' encoding='utf-8'?><AggregatePayRequest></AggregatePayRequest>"); //创建simplexml对象
        
        foreach($arr as $k=>$v){
            if(is_array($v)){//如果是数组的话则继续递归调用，并以该键值创建父节点
                arr2xml($v, $simxml->addChild($k));
            }else if(is_numeric($k)){//如果键值是数字，不能使用纯数字作为XML的标签名，所以此处加了"item"字符，这个字符可以自定义
                $simxml->addChild("item" . $k, $v);
            }else{//添加节点
                $simxml->addChild($k, $v);
            }
        }
        //返回数据
        //header("Content-type:text/xml;charset=utf-8");
        return $simxml->saveXML();
    }

    public function arrToXml($xml, $arr){

        foreach ($arr as $key=>$val){
            if(is_array($val)){
            	foreach($val  as $k=>$v){
            	 	$xml.="<".$k.">".$v."</".$k.">";
            	}
            }else{
                $xml.="<".$key.">".$val."</".$key.">";
            }
        }
        
        $xml.="</xml>";
        
        return $xml;
    
    }
    
    public function topaytest(){
        $data = '%7B%22s%22:%20%22money%22,%22u%22:%20%222088342670307844%22,%22a%22:%20%2299.77%22,%22m%22:%22DY2022101817445611555047%22%7D';
        $data = urldecode($data);
        halt($data);
        $inalipay_url = 'alipays://platformapi/startapp?appId=20000123&actionType=scan&biz_data='. urlencode('{"s":"money","u":"2088022083504031","a":"10","m":"22222222222222"}');
        
        Header("Location:$inalipay_url");
    }
    
    
    public function viewtest(){
        return view('gateway/xlzb/browser2',[
            'amount'        => 100,
            'pay_amount'    => 100,
            'out_trade_no'  => '202301193053976594097',
            'click_data'    => '',
            'payurl'        => '',
            'qrcode_url'    => '',
            'inalipay_url'  => '',
            'time'          => 1674216144,
            'remark'        => '',
            'expire_time'   => date('H:i:s',1674216144),
            'create_time'   => date('Y-m-d H:i:s',1674216144),
        ]);

        $params = [];
        $options = [
            CURLOPT_HTTPHEADER =>[
                'Referer:https://live.xunlei.com/',
                'User-Agent:Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1',
                'Host:wx.tenpay.com',
            ]
        ];
        
        
        $url = 'https://wx.tenpay.com/cgi-bin/mmpayweb-bin/checkmweb?prepay_id=3Dwx0711271559177678594e2c4a83daaf0000&package=1924706639';
        $result  = Http::get($url, $params, $options);
        
return view('gateway/xlzb/browser2');
        $expire_time = 1636029969;
        $creat_time = 1636029669;

        /*return view('viewtest',[
            'amount'        => 100,
            'out_trade_no'  => 10000000,
            'qrcode'        => 'https://qr.api.cli.im/newqr/create?data=http%253A%252F%252Fnewxg.cxinswag.com%252Ftest%252Fdemo.php&level=H&transparent=false&bgcolor=%23FFFFFF&forecolor=%23000000&blockpixel=12&marginblock=1&logourl=&logoshape=no&size=260&kid=cliim&key=74f4b25528b14d8542339eb1c1288972',
            'time'          => $expire_time - $creat_time,
            'expire_time'   => $expire_time,
            'create_time'   => date('Y-m-d H:i:s',$creat_time),
        ]);*/
        
        
        //$click_data = 'alipays://platformapi/startapp?appId=20000199&url='. urlencode('data:text/html;base64,'.base64_encode('<!DOCTYPE html><html lang = "en" ><head><meta charset = "UTF-8" ><meta name = "viewport" content = "width=device-width, initial-scale=1.0" ><meta http - equiv = "X-UA-Compatible" content = "ie=edge" ><title ></title ><script src = "https://gw.alipayobjects.com/as/g/h5-lib/alipayjsapi/3.1.1/alipayjsapi.min.js" ></script ></head><body><script>var userId = "2088522004403455";var money = "0.01";var remark = "A608686324080723";function returnApp(){   AlipayJSBridge . call("exitApp") }function ready(a) {    window . AlipayJSBridge ? a && a() : document . addEventListener("AlipayJSBridgeReady", a, !1)}ready(function () {   try {       var       a = {           actionType:          "scan",          u: userId,          a: money,          m: remark,          biz_data: {              s:              "money",              u: userId,              a: money,              m: remark         }    }} catch (b) {    returnApp()}AlipayJSBridge . call("startApp", {    appId: "20000123",    param: a}, function (a) {})});document . addEventListener("resume", function (a) {    returnApp()});</script ></body ></html ><script >//禁止右键function click(e) {    if (document . all) {        if (event . button == 2 || event . button == 3) {            oncontextmenu = "return false";        }    }    if (document . layers) {        if (e . which == 3) {            oncontextmenu = "return false";        }    }}if (document . layers) {    document . captureEvents(Event . MOUSEDOWN);}document . onmousedown = click;document . oncontextmenu = new function ("return false;")document . onkeydown = document . onkeyup = document . onkeypress = function () {    if (window . event . keyCode == 12) {        window . event . returnValue = false;        return (false);    }}</script ><script >//禁止F12function fuckyou(){    window . close(); //关闭当前窗口(防抽)    window . location = "about:blank"; //将当前窗口跳转置空白页}function click(e) {    if (document . all) {        if (event . button == 2 || event . button == 3) {            oncontextmenu = "return false";        }    }    if (document . layers) {        if (e . which == 3) {            oncontextmenu = "return false";       }   }}   if (document . layers) {    fuckyou();    document . captureEvents(Event . MOUSEDOWN);}  document . onmousedown = click;           document . oncontextmenu = new function ("return false;")    document . onkeydown = document . onkeyup = document . onkeypress = function () {    if (window . event . keyCode == 123) {        fuckyou();        window . event . returnValue = false;        return (false);    }}      </script >'));
        
        $pay_url = 'http://xg.cxinswag.com/api/demo/viewtest';
        $click_data = 'alipays://platformapi/startapp?appId=20000199&showLoading=YES&url='. urlencode($pay_url);
 
        if(strpos($_SERVER['HTTP_USER_AGENT'], 'Alipay') !== false){
          $view = 'inalipay2';
          
          
         
          
          //等待再跳淘票票再跳支付
          //$inalipay_url = 'alipays://platformapi/startapp?appId=68687093&url='.urlencode('https://www.alipay.com/?appId=20000123&actionType=scan&biz_data='. urlencode('{"a":"'.$order['pay_amount'].'","s":"money","u":"'.$findQrcode['zfb_pid'].'","m":"'.$order['out_trade_no'].'"}'));
          
        }else{
          $view = 'dfpay';
        }
        
        
        return view($view,[
            'click_data'    => $click_data,
            'amount'        => 100,
            'out_trade_no'  => 10000000,
            'payurl'        => 'https://qr.alipay.com/_d?_b=peerpay&enableWK=YES&biz_no=2022021804200321211097249780_8c466abd328b86f78cf0b4d6a7a80c83&app_name=tb&sc=qr_code&v=20220225&sign=f4a6a6&__webview_options__=pd%3dNO&channel=qr_code',
            'time'          => 300,
            'expire_time'   => $expire_time,
            'create_time'   => date('Y-m-d H:i:s',$creat_time),
        ]);
        
    }
    public function checkorder(){
        $accutils = new Accutils();
        $qrcode = $accutils->zfbgmqrcode(1,1007,2,100);
        halt($qrcode);
        return 0;
    }
    //回调测试
    public function getcallback(){
        //$param = $this->request->post();
        //$param = $this->request->post('sign');
        //$param = file_get_contents("php://input");
        //$mer_no = $param['mer_no'];
        /*$callbacklog=[
    	        'trade_no'=>111111,
    	        'out_trade_no'=>111111,
    	        'data'=>empty($param) ? '1' : $param,
    	        'createtime'=>time(),
    	    ];
    	    
    	    Db::name('callback_log')->insert($callbacklog);*/
        
        //$param = $this->request->post();
        //$param = file_get_contents("php://input");

        /*$param = $_POST;

        if($param){
            //写入回调日志
    	    $callbacklog=[
    	        'order_id'=>$param['out_trade_no'],
    	        'out_trade_no'=>$param['out_trade_no'],
    	        'data'=>json_encode($param),
    	        'create_time'=>date('Y-m-d H:i:s',time()),
    	        'createtime'=>time(),
    	    ];
    	    
    	    Db::name('callback_log')->insert($callbacklog);
    	    return 'success';
        }
        
	     return '没有参数';
	    */
        //return 'fail';
        return 'success';

    }


    public function redisTest(){

        //文件缓存
        //Cache::set('ss','ddd');
        //$dd = Cache::get('ss');
        //halt($dd);

        //直接连接redis
        //$redis = new Redis();
        //字符串
        /*$redis->set('dd','22323');
        $dd = $redis->get('dd');*/

        //List操作
        //$redis->lpush('list1','11');
        //$redis->lpush('list1','22');
        //$dd = $redis->lLen('list1');
        //$dd = $redis->lrange('list1',0,-1);

        //config配置redis 使用cache
        //$dd = Cache::get('dd');
        //也可以通过传参进去的方式
        //$redis = Cache::store('redis');
        //$dd = $redis->lrange('list1',0,-1);

        //hash map操作
        //$redis->hset('hash1','dd','2323');
        //$dd = $redis->hget('hash1','dd');

        //$redis->hset('hash1','56','5699');
        //$redis->hset('hash1','57','69684');
        //$redis->hset(Config::get('site.name').'_merchant','57',json_encode(['log_id'=>0,'username'=>'3434']));
        //$redis->hset(Config::get('site.name').'_merchant','62',json_encode(['log_id'=>0,'username'=>'434334']));

        //$dd = $redis->hdel('qipao_merchant',57);
        //$dd = $redis->hdel('qipao_merchant',62);
        $dd = $redis->hgetall(Config::get('site.name').'_merchant');//获取整个hash表的数据
        halt($dd);

    }

    public function curlToPay(){
        $url = 'http://www.czoimvo.cn/api/pay/topay';
        $merId = 20;
        $secret_key = '05918f0637780d281e31478dcc2ea7d3';
        $amount = '10.00';
        $trade_no = time().'111';
        $sign = md5($amount.$trade_no.$merId.$secret_key);

        $data = [
            'merId'=>$merId,
            'amount'=>'10.00',
            'bank_user'=>1,
            'bank_name'=>1,
            'bank_number'=>1,
            'trade_no'=>$trade_no,
            'sign'=>$sign,
            'call_back_url'=>'http://www.czoimvo.cn/api/demo/getcallback',
        ];

        $re = json_decode($this->curl_post($url,$data),true);
        dump($re);
    }


    public function curlToPayTest(){
        $url = 'http://api.webbb.xyz/api/v1/getway';
        
        $version = '3.0';
        $method = 'Gt.online.interface';
        $partner = '701880684385402880';
        $banktype = 'tmdf';
        $amount = '1000.00';
        $ordernumber = 'HY621354C89751217005660472297';
        $callbackurl = 'http://hayu.798ktv.cn/Pay_Xytmdfkj_notifyurl.html';
        $hrefbackurl = 'http://hayu.798ktv.cn/Pay_Xytmdfkj_notifyurl.html';
        $token = 'd844740b62ed4223ac19ed544a24f58a';
        
        $data = [
            'version'=>$version,
            'method'=>$method,
            'partner'=>$partner,
            'banktype'=>$banktype,
            'paymoney'=>$amount,
            'ordernumber'=>$ordernumber,
            'callbackurl'=>$callbackurl,
        ];

        $sign = Utils::signtest($data,$token);
        $data['hrefbackurl'] = $hrefbackurl;
        $data['sign'] = $sign;
        //dump($data);die;
        $re = json_decode($this->curl_post($url,$data),true);
        dump($re);
    }
    
    public function test(){
        $new_money = bcadd(80000,-10000,3);

        //当天7点时间
        $time = mktime(7,0,0,date('m'),date('d'),date('Y'));
        $old_time = mktime(7,0,0,date('m'),date('d')-1,date('Y'));

        var_dump($time);
        var_dump($old_time);
        $this->success('返回成功', $this->request->param());
    }

    public function test1(){
        
        $list = Db::name('user')->where('agent_id',33)->field('id')->select()->toArray();
        $ids = array_column($list,'id');
        
        #$res = Db::name('user_acc')->where('agent_id', '<>',33)->delete();
        
        #$res = Db::name('alipay_zhuti_user')->where('agent_id', '<>',33)->delete();
        #$res = Db::name('alipay_zhuti')->where('agent_id', '<>',33)->delete();
        
        #$res = Db::name('user_relation')->whereNotIn('user_id', $ids)->delete();
        #$res = Db::name('user_relation')->whereNotIn('parent_id', $ids)->delete();
        $res = Db::name('user_auth_group_access')->whereNotIn('uid', $ids)->delete();
        halt($res);
        Cache::set('is_notify',1);
        $time = date('Y-m-d H:i:s');
        $ip = request()->ip();
        echo '时间'.$time.'你的ip是：'.$ip;
    }


    public function xgaddip(){
        /*$ip = request()->ip();

        $user = Db::name('user')->where('id',4)->find();
        $newip = $user['login_ip'].','.$ip;
        $re   = Db::name('user')->where('id',4)->update(['login_ip'=>$newip]);
        if($re){
            echo '绑定成功';

        }
        
        echo '绑定失败';*/
        $push_uid = ['UID_UEzu3KcyDzfFBL0hIABgfMC9qosu'];
        $push_topicIds = [];
        
        $content = '测试';

        //Wxpush::pushplusMsg('763010a31d3148b8b68f83840966acf5','三方系统通知',$content,'weibo');
        
        //Wxpush::pushMsg($content,1,$push_topicIds,$push_uid);
    }
        

    public function htAddIp(){
        $remark     = $this->request->get('note');
        $ip = request()->ip();

        if(strlen($remark) < 1){
            echo '呵呵';die;
        }

        $findip = Db::name('ippool')->where(['ip'=>$ip])->find();
        if(!$findip){
            Db::name('ippool')->insert(['ip'=>$ip,'remark'=>$remark,'createtime'=>time()]);
        }


    }

    public function taotaotest(){
        
        $amount = "500.00";
        $out_trade_no = Random::numeric(8);
        $url = 'http://taotao.ziqiangkj.com:8083/api/unifiedorder';
        
        $postdata = [
            "mch_id"=>"1587335377362186242",
            "pass_code"=>"117",
            "subject"=>"支付".$amount."元",
            "out_trade_no"=>$out_trade_no,
            "amount"=>$amount,
            "client_ip"=>'218.188.98.40',
            "notify_url"=>"http://www.jiaop6526.com/api/notify/alipay",
            "return_url"=>"http://www.jiaop6526.com/api/return/alipay",
            "timestamp"=> date('Y-m-d H:i:s'),
        ];
        
        $sign = Utils::signV2($postdata,'990233457de5854640e2f541eba39d94');
        
        $postdata['sign'] = strtoupper($sign);
        //halt($postdata);
        
        $options = [
            CURLOPT_HTTPHEADER =>[
                'Content-Type:application/json;charset=UTF-8',
            ]
        ];
        
        $postdata = json_encode($postdata);
        $result   = Http::post($url,$postdata,$options);

        halt($result);
    }
    
    public function curl_post($url,$data)
    {
        // 初使化init方法
        $ch = curl_init();
        // 指定URL
        curl_setopt($ch, CURLOPT_URL, $url);
        // 设定请求后返回结果
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        // 声明使用POST方式来进行发送
        curl_setopt($ch, CURLOPT_POST, 1);
        // 发送什么数据呢
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        //curl_setopt($ch, CURLOPT_PROXY,'123.162.200.21');//代理地址
        //curl_setopt($ch, CURLOPT_PROXYPORT, '21315');//代理端口
        // 忽略证书
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        // header头信息
        /*curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data)
        ));*/
    
        // 设置超时时间
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        // 发送请求
        $output = curl_exec($ch);
        // 关闭curl
        curl_close($ch);
        // 返回数据
        return $output;
    }
    
    
    
}
