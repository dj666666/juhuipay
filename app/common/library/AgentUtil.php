<?php
namespace app\common\library;

use fast\Random;
use think\facade\Config;
use think\facade\Db;
use think\Exception;
use fast\Http;
use app\common\library\Utils;
use think\facade\Cache;


class AgentUtil
{
    
    //统一处理获取代理ip //TODO
    public static function getProxy($options, $type, $out_trade_no, $ip_num = 1, $rand_city = true){
        
        if ($type == 1) {
            $result = self::shanchendaili($out_trade_no, $ip_num, $rand_city);
            if(!empty($result)){
                if($result['status'] == 0 && count($result['list']) > 0){
                    $daili = $result['list'][0];
                    $options[CURLOPT_PROXY] = $daili['sever'];
                    $options[CURLOPT_PROXYPORT] = $daili['port'];
                }
            }
            
        }
        
        if($type == 2){
            $result = self::liuguanDaili($out_trade_no, $ip_num, $rand_city);
            if(isset($result['serialNo'])){
                
                $daili = $result['data'][0];
                $options[CURLOPT_PROXY] = $daili['ip'];
                $options[CURLOPT_PROXYPORT] = $daili['port'];
                
                /*$proxyServer = "http://".$result['data'][0]['ip'].":".$result['data'][0]['port'];
                $options = [
                    CURLOPT_PROXYAUTH => CURLAUTH_BASIC,
                    CURLOPT_PROXY => $proxyServer,
                ];*/
            }
            
        }
        
        return $options;
    }
    
    public static function getProxyByCache($options){
        $key       = 'mango_proxy';
        $proxyInfo = Cache::get($key);
        if($proxyInfo){
            
            $proxyInfo                  = json_decode($proxyInfo, true);
            $options[CURLOPT_PROXY]     = $proxyInfo['ip'];
            $options[CURLOPT_PROXYPORT] = $proxyInfo['port'];
        }
        
        return $options;
    }
    
    public static function setProxyCache($type, $out_trade_no, $ip_num = 1, $rand_city = true, $update_cache = false){
        
        $key       = 'mango_proxy';
        $proxyInfo = Cache::get($key);
        if(!empty($proxyInfo) && !$update_cache){
           return true; 
        }
        
        
        if ($type == 1) {
            
            $result = self::shanchendaili($out_trade_no, 5, $ip_num, false);
            
            if(!empty($result)){
                if($result['status'] == 0 && count($result['list']) > 0){
                    
                    $daili['ip']   = $result['list'][0]['sever'];
                    $daili['port'] = $result['list'][0]['port'];
                    
                    $daili = json_encode($daili);
                    Cache::set($key, $daili, 290);
                }
            }
            
        }
        
        if($type == 2){
            
            $result = self::liuguanDaili($out_trade_no, 300, $ip_num, false);
            
            if(isset($result['serialNo'])){
                
                $daili['ip']   = $result['data'][0]['ip'];
                $daili['port'] = $result['data'][0]['port'];
                
                $daili = json_encode($daili);
                Cache::set($key, $daili, 290);
            }
            
        }
            
        return true;
    }
    
    
    //闪臣
    public static function shanchendaili($out_trade_no, $use_time = 1, $ip_num = 1, $rand_city = true){
        
        if($rand_city){
            $daili_list = Db::name('daili_city')->where(['status'=>1,'type'=>1])->order('id', 'asc')->select()->toArray();
            if(empty($daili_list)){
                return false;
            }
            
            $count  = count($daili_list);
            $bianma = $daili_list[mt_rand(0, $count - 1)];
        }else{
            $bianma['province'] = 1131;//省份
            $bianma['city']     = 1132;//城市
        }
        
        $params  = [];
        $options = [];
        
        $url = 'https://sch.shanchendaili.com/api.html?action=get_ip&key=HU7e291a115779032456lIlM&time='.$use_time.'&count='.$ip_num.'&protocol=http&type=json&province='.$bianma['province'].'&city='.$bianma['city'].'&only=0';

        $result  = json_decode(Http::get($url, $params, $options),true);
        
        Utils::notifyLog($out_trade_no, $out_trade_no, '闪臣'.$bianma['city'].'-'.json_encode($result,JSON_UNESCAPED_UNICODE));

        return $result;

    }

    //流冠
    public static function liuguanDaili($out_trade_no, $use_time = 60, $ip_num = 1, $rand_city = true){
        
        if($rand_city){
            $daili_list = Db::name('daili_city')->where(['status'=>1,'type'=>2])->order('id', 'asc')->select()->toArray();
            if(empty($daili_list)){
                return false;
            }
            
            $count      = count($daili_list);
            $bianma     = $daili_list[mt_rand(0, $count - 1)];
        
            $pid = $bianma['province'];//省份 -1表示中国
            $cid = $bianma['city'];//城市 可为空
        }else{
            $pid =  26; //省份 -1表示中国
            $cid =  315; //城市 可为空
        }
        
        $params     = [];
        $options    = [];
        $getipHost  ='http://api.hailiangip.com:8422';
        $orderId    = 'O20110312411121373894';
        $secret     = '7b258999ad414cb0b680689a0294918a';
        $time       = time();//当前时间戳
    	$type       = 1;//ip协议  1表示HTTP/HTTPS
    	$num        = $ip_num;//提取数量 1-200之间
    	
    	$unbindTime = $use_time;//占用时长（单位秒）
    	$dataType   = 0;//返回的数据格式 0表示json
    	$noDuplicate = 0;//是否去重 0表示不去重 1表示24小时去重
    	$singleIp   = 0;//异常切换  0表示切换  1表示不切换
    
        $sign = strtolower(md5('orderId='.$orderId.'&secret='.$secret.'&time='.$time));
    
    	$getipUrl='/api/getIp?type='.$type.'&num='.$num.'&pid='.$pid.'&unbindTime='.$unbindTime.'&cid='.$cid.'&orderId='.$orderId.'&time='.$time.'&sign='.$sign.'&noDuplicate='.$noDuplicate.'&dataType='.$dataType.'&lineSeparator=0&singleIp='.$singleIp;
    
    	$getipLink = $getipHost.$getipUrl;

        $result  = Http::get($getipLink, $params, $options);
        
        $ip_data = json_decode($result,true);
         
        if(!isset($ip_data['serialNo'])){
            $msg = '流冠-'.$pid.'-'.$cid.'-'.$result;
        }else{
            $msg = '流冠-'.$result;
        }
        
        Utils::notifyLog($out_trade_no, $out_trade_no, $msg);
        
        return $ip_data;

    }
    
}