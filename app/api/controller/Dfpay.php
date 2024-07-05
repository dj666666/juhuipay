<?php

namespace app\api\controller;

use app\common\controller\Api;
use app\common\library\Aes;
use app\common\library\MoneyLog;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Config;
use app\common\library\Utils;
use app\common\library\ThirdDf;
use think\cache\driver\Redis;
use think\facade\Log;
use think\facade\Queue;
use app\common\controller\Jobs;
use think\Request;
use fast\Random;


/**
 * 抢单代付
 */
class Dfpay extends Api
{

    //如果$noNeedLogin为空表示所有接口都需要登录才能请求
    //如果$noNeedRight为空表示所有接口都需要验证权限才能请求
    //如果接口已经设置无需登录,那也就无需鉴权了
    //
    // 无需登录的接口,*表示全部
    protected $noNeedLogin = ['subDfOrder','queryorder','queryBalance','aestest','suborderV2','getBank','subBankOrder'];
    // 无需鉴权的接口,*表示全部
    protected $noNeedRight = ['test2'];
    
    //代付下单 无锁
    public function subDfOrder(){
        
        $amount      = $this->request->post('amount');//金额
        $bank_name   = $this->request->post('bank_name');//开户行
        $bank_user   = $this->request->post('bank_user');//姓名
        $bank_number = $this->request->post('bank_number');//银行账户
        $trade_no    = $this->request->post('trade_no');//商户平台订单号
        $mer_no      = $this->request->post('mer_no');//商户编号
        $sign        = $this->request->post('sign');//加密值
        $notify_url  = $this->request->post('notify_url');//异步回调地址
        $remark      = $this->request->post('remark');//备注
        $post_data   = $this->request->post();

        Log::write('代付下单----'.request()->ip().'----'.json_encode($_POST,JSON_UNESCAPED_UNICODE),'info');

        if (empty($amount)  || empty($trade_no) || empty($mer_no) || empty($bank_name) || empty($bank_user) || empty($bank_number) || empty($sign) || empty($notify_url)){
            $this->error('参数缺少');
        }

        $findmerchant = Db::name('merchant')->where(['number'=>$mer_no,'status'=>'normal'])->find();

        if(!$findmerchant){
            $this->error('信息不存在');
        }

        if(empty($findmerchant['api_ip'])){
            $this->error('权限不足');
        }
        
        $api_ip = explode(",",$findmerchant['api_ip']);
        if(!in_array(request()->ip(),$api_ip)){
            $this->error('权限不足');
        }
        
        if($findmerchant['secret_type'] == '1'){
            $mysign = Utils::sign($post_data, $findmerchant['secret_key']);
        }else{
            $sign_str = Utils::signStr($post_data,$findmerchant['secret_key']);
            $aes      = new Aes($findmerchant['secret_key'],'AES-256-ECB');//32位密钥
            $mysign   = $aes->encrypt($sign_str);
        }
        
        if($mysign != $sign){
            $this->error('签名错误!');
        }

        /*//判断是否先扣手续费
        if (Config::get('site.is_recharge_fee')) {
            $fees = $findmerchant['add_money'];//充值先扣手续费
        }else{

            if($findmerchant['is_diy_rate'] == 1){
                $fees = Utils::getFeesByDiv($amount,$findmerchant['diyratejson']);
                if($fees < 0){
                    $this->error('手续费错误');
                }
            }else{
                $fees = bcmul($amount,$findmerchant['rate'],2);
                $fees = bcadd($fees,$findmerchant['add_money'],2);
            }
        }*/

        if($findmerchant['is_diy_rate'] == 1){
            $fees = Utils::getFeesByDiv($amount,$findmerchant['diyratejson']);
            if($fees < 0){
                $this->error('手续费错误');
            }
        }else{
            $fees = bcmul($amount,$findmerchant['df_rate'],2);
            $fees = bcadd($fees,$findmerchant['add_money'],2);
        }
        
        
        //如果商户是扣款，则判断是否有余额
        $merchant_df_rate = Config::get('site.merchant_df_rate');
        if ($merchant_df_rate == 2) {
            if($findmerchant['money'] < $amount){
                $this->error('余额不足');
            }
        }
        
        
        
        /*if(empty($findmerchant['userids'])){
            $this->error('暂无可用人员');
        }

        $user = Utils::getUser($findmerchant['id'],$findmerchant['userids']);
        if(empty($user)){
            $this->error('暂无可用人员！');
        }*/

        $out_trade_no = Utils::buildDfOutTradeNo();
        $creat_time   = time();

        $result1 = false;

        Db::startTrans();
        try {

            //生成订单
            $data = [
                //'user_id'       => $user['id'],
                'mer_id'        => $findmerchant['id'],
                'agent_id'      => $findmerchant['agent_id'],
                'trade_no'      => $trade_no,
                'out_trade_no'  => $out_trade_no,
                'bank_type'     => $bank_name,
                'bank_user'     => $bank_user,
                'bank_number'   => $bank_number,
                'amount'        => $amount,
                'fees'          => $fees,
                'notify_url'    => $notify_url,
                'createtime'    => $creat_time,
                'order_type'    => 1,
                'ip_address'    => request()->ip(),
                'status'        => 2,
                'remark'        => $remark ?? '',
            ];

            $result1 = Db::name('df_order')->insertGetId($data);
            
            //商户扣除余额
            //MoneyLog::merchantMoneyChangeByDf($findmerchant['id'], $amount, $fees, $trade_no, $out_trade_no, 'api代付扣除',0);
            
            Db::commit();
            
        } catch (\Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        
        if($result1 == false){
            $this->error('提交失败');
        }

       
        /*// 当前任务所需的业务数据，不能为 resource 类型，其他类型最终将转化为json形式的字符串
        $queueData = [
            'request_type'  => 4,
            'order_id'      => $result1,
            'out_trade_no'  => $out_trade_no,
            'trade_no'      => $trade_no,
        ];

        //当前任务归属的队列名称，如果为新队列，会自动创建
        $queueName = 'checkorder';
        $delay = Config::get('site.expire_time')+2;
        // 将该任务推送到消息队列，等待对应的消费者去执行
        //$isPushed = Queue::push(Jobs::class, $data, $queueName);//立即执行
        $isPushed = Queue::later($delay,Jobs::class, $queueData, $queueName);//延迟$delay秒后执行*/
        
        //判断是否转入三方代付
        if($findmerchant['is_third_df'] == '1'){
            $df_res = ThirdDf::instance()->checkDfType($out_trade_no, $amount, $findmerchant['df_acc_ids']);
            
        }
        
        return json(['code' => 1, 'msg' => 'success', 'data' => $out_trade_no, 'time' => time()]);

    }
    
    //代付下单 文件锁
    public function subDfOrderV1(){
        
        $amount      = $this->request->post('amount');//金额
        $bank_name   = $this->request->post('bank_name');//开户行
        $bank_user   = $this->request->post('bank_user');//姓名
        $bank_number = $this->request->post('bank_number');//银行账户
        $trade_no    = $this->request->post('trade_no');//商户平台订单号
        $mer_no      = $this->request->post('mer_no');//商户编号
        $sign        = $this->request->post('sign');//加密值
        $notify_url  = $this->request->post('notify_url');//异步回调地址
        $remark      = $this->request->post('remark');//备注
        $post_data   = $this->request->post();

        Log::write(date('Y-m-d H:i:s').'----'.request()->ip().'----'.json_encode($_POST,JSON_UNESCAPED_UNICODE),'info');

        
        $file = fopen('dflock.txt','w+');

        //加锁
        if(flock($file,LOCK_EX)){

            if (empty($amount)  || empty($trade_no) || empty($mer_no) || empty($bank_name) || empty($bank_user) || empty($bank_number) || empty($sign) || empty($notify_url)){
                $this->error('参数缺少');
            }

            $findmerchant = Db::name('merchant')->where(['number'=>$mer_no,'status'=>'normal'])->find();
    
            if(!$findmerchant){
                $this->error('信息不存在');
            }

            if(empty($findmerchant['api_ip'])){
                $this->error('权限不足');
            }
            
            $api_ip = explode(",",$findmerchant['api_ip']);
            if(!in_array(request()->ip(),$api_ip)){
                $this->error('权限不足');
            }
            
            if($findmerchant['secret_type'] == '1'){
                $mysign = Utils::sign($post_data, $findmerchant['secret_key']);
            }else{
                $sign_str = Utils::signStr($post_data,$findmerchant['secret_key']);
                $aes      = new Aes($findmerchant['secret_key'],'AES-256-ECB');//32位密钥
                $mysign   = $aes->encrypt($sign_str);
            }
            
            if($mysign != $sign){
                $this->error('签名错误!');
            }

            /*//判断是否先扣手续费
            if (Config::get('site.is_recharge_fee')) {
                $fees = $findmerchant['add_money'];//充值先扣手续费
            }else{

                if($findmerchant['is_diy_rate'] == 1){
                    $fees = Utils::getFeesByDiv($amount,$findmerchant['diyratejson']);
                    if($fees < 0){
                        $this->error('手续费错误');
                    }
                }else{
                    $fees = bcmul($amount,$findmerchant['rate'],2);
                    $fees = bcadd($fees,$findmerchant['add_money'],2);
                }
            }*/

            if($findmerchant['is_diy_rate'] == 1){
                $fees = Utils::getFeesByDiv($amount,$findmerchant['diyratejson']);
                if($fees < 0){
                    $this->error('手续费错误');
                }
            }else{
                $fees = bcmul($amount,$findmerchant['df_rate'],2);
                $fees = bcadd($fees,$findmerchant['add_money'],2);
            }

            if($findmerchant['money'] < ($fees + $amount)){
                $this->error('余额不足');
            }
            
            /*if(empty($findmerchant['userids'])){
                $this->error('暂无可用人员');
            }

            $user = Utils::getUser($findmerchant['id'],$findmerchant['userids']);
            if(empty($user)){
                $this->error('暂无可用人员！');
            }*/

            $out_trade_no = Utils::buildDfOutTradeNo();
            $creat_time   = time();

            $result1 = false;

            Db::startTrans();
            try {

                //生成订单
                $data = [
                    //'user_id'       => $user['id'],
                    'mer_id'        => $findmerchant['id'],
                    'agent_id'      => $findmerchant['agent_id'],
                    'trade_no'      => $trade_no,
                    'out_trade_no'  => $out_trade_no,
                    'bank_type'     => $bank_name,
                    'bank_user'     => $bank_user,
                    'bank_number'   => $bank_number,
                    'amount'        => $amount,
                    'fees'          => $fees,
                    'notify_url'    => $notify_url,
                    'createtime'    => $creat_time,
                    'order_type'    => 1,
                    'ip_address'    => request()->ip(),
                    'status'        => 2,
                    'remark'        => $remark ?? '',
                ];

                $result1 = Db::name('df_order')->insertGetId($data);

                //商户扣除余额
                MoneyLog::merchantMoneyChange($findmerchant['id'], $amount, $fees, $trade_no, $out_trade_no, 'api提单扣除',0, 0);

                Db::commit();

            } catch (\Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }

            if($result1 == false){
                $this->error('提交失败');
            }

            //执行完成解锁
            flock($file,LOCK_UN);//解锁

            //关闭文件
            fclose($file);

            /*// 当前任务所需的业务数据，不能为 resource 类型，其他类型最终将转化为json形式的字符串
            $queueData = [
                'request_type'  => 4,
                'order_id'      => $result1,
                'out_trade_no'  => $out_trade_no,
                'trade_no'      => $trade_no,
            ];

            //当前任务归属的队列名称，如果为新队列，会自动创建
            $queueName = 'checkorder';
            $delay = Config::get('site.expire_time')+2;
            // 将该任务推送到消息队列，等待对应的消费者去执行
            //$isPushed = Queue::push(Jobs::class, $data, $queueName);//立即执行
            $isPushed = Queue::later($delay,Jobs::class, $queueData, $queueName);//延迟$delay秒后执行*/
            
            
            return json(['code' => 1, 'msg' => 'success', 'data' => $out_trade_no, 'time' => time()]);

        }

        $this->error('system error');

    }

    //redis锁
    public function suborderV2(){
        $amount     = $this->request->post('amount');//金额
        $bank_name  = $this->request->post('bank_name');//开户行
        $bank_user  = $this->request->post('bank_user');//姓名
        $bank_number= $this->request->post('bank_number');//银行账户
        $trade_no   = $this->request->post('trade_no');//商户平台订单号
        $mer_no     = $this->request->post('mer_no');//商户编号
        $sign       = $this->request->post('sign');//加密值
        $notify_url = $this->request->post('notify_url');//异步回调地址
        $remark     = $this->request->post('remark');//备注
        $waitsign   = $this->request->post();

        Log::write(date('Y-m-d H:i:s').'----'.request()->ip().'----'.json_encode($_POST,JSON_UNESCAPED_UNICODE),'info');

        $random = mt_rand(1,100000);
        $ttl    = 1;
        $site_name = Config::get('site.name');

        $redis = Cache::store('redis');

        $ok = $redis->lock($site_name.$trade_no, $random, $ttl);


        //加锁
        if($ok){

            if (empty($amount)  || empty($trade_no) || empty($mer_no) || empty($bank_name) || empty($bank_user) || empty($bank_number) || empty($sign) || empty($notify_url)){
                $this->error('参数缺少');
            }
            //根据mer_no找到商户
            $findmerchant = Db::name('merchant')->where(['number'=>$mer_no,'status'=>'normal'])->field('id,agent_id,username,money,rate,add_money,api_ip,userids,secret_key,last_money_time,min_money,max_money,is_diy_rate,diyratejson,status')->find();

            if(!$findmerchant){
                $this->error('信息不存在');
            }

            if(empty($findmerchant['api_ip'])){
                $this->error('权限不足');
            }

            $api_ip = explode(",",$findmerchant['api_ip']);
            if(!in_array(request()->ip(),$api_ip)){
                $this->error('权限不足');
            }

            $mysign = Utils::sign($waitsign,$findmerchant['secret_key']);

            if($mysign != $sign){
                $this->error('签名错误!');
            }

            if(empty($findmerchant['userids'])){
                $this->error('暂无可用人员');
            }

            $user = Utils::getUser($findmerchant['id'],$findmerchant['userids']);
            if(empty($user)){
                $this->error('暂无可用人员');
            }


            //判断是否先扣手续费
            if (Config::get('site.is_recharge_fee')) {
                $fees = $findmerchant['add_money'];//充值先扣手续费
            }else{

                if($findmerchant['is_diy_rate'] == 1){
                    $fees = Utils::getFeesByDiv($amount,$findmerchant['diyratejson']);
                    if($fees == 0){
                        $this->error('手续费错误');
                    }
                }else{
                    $fees = bcmul($amount,$findmerchant['rate'],2);
                    $fees = bcadd($fees,$findmerchant['add_money'],2);
                }
            }



            $out_trade_no = Utils::buildOutTradeNo();
            $creat_time = time();


            $result1 = false;
            $result2 = false;

            Db::startTrans();
            try {

                //生成订单
                $data = array(
                    //'user_id'       => $user['id'],
                    'mer_id'       => $findmerchant['id'],
                    'agent_id'     => $findmerchant['agent_id'],
                    'trade_no'     => $trade_no,
                    'out_trade_no' => $out_trade_no,
                    'amount'       => $amount,
                    'fees'         => $fees,
                    'notify_url'   => $notify_url,
                    'createtime'   => $creat_time,
                    'order_type'   => 1,
                    'ip_address'   => request()->ip(),
                    'status'       => 2,
                    'remark'       => $remark ?? '',
                );

                $result1 = Db::name('order')->insertGetId($data);

                //扣除余额
                Utils::merchantMoneyLogV3($this->auth->id,$amount,$fees,$out_trade_no,'api订单扣款',true,0);

                Db::commit();

            } catch (\Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }

            //判断随机数是否相等，再删除锁
            if ($redis->get($site_name.$trade_no) == $random) {
                $redis->rm($site_name.$trade_no);
            }

            if($result1 == false || $result2 == false){
                $this->error('提交失败');
            }

            /*// 当前任务所需的业务数据，不能为 resource 类型，其他类型最终将转化为json形式的字符串
            $queueData = [
                'request_type'  => 4,
                'order_id'      => $result1,
                'out_trade_no'  => $out_trade_no,
                'trade_no'      => $trade_no,
            ];

            //当前任务归属的队列名称，如果为新队列，会自动创建
            $queueName = 'checkorder';
            $delay = Config::get('site.expire_time')+2;
            // 将该任务推送到消息队列，等待对应的消费者去执行
            //$isPushed = Queue::push(Jobs::class, $data, $queueName);//立即执行
            $isPushed = Queue::later($delay,Jobs::class, $queueData, $queueName);//延迟$delay秒后执行*/

            return json(['code'=>1,'msg'=>'success','data'=>$out_trade_no,'time'=>time()]);

        }


    }

    //代付查单
    public function queryOrder(){
        $mer_no     = $this->request->post('mer_no');
        $sign       = $this->request->post('sign');
        $trade_no   = $this->request->post('trade_no');
        $post_data  = $this->request->post();
        
        Log::write('dfqueryOrder----'.request()->ip().'----'.json_encode($_POST,JSON_UNESCAPED_UNICODE),'info');

        if(empty($mer_no) || empty($sign) || empty($trade_no)){
            $this->error('参数缺少');
        }

        $merchant = Db::name('merchant')->where(['number'=>$mer_no])->find();
        
        if($merchant['secret_type'] == '1'){
            $mysign = Utils::sign($post_data, $merchant['secret_key']);
        }else{
            $sign_str = Utils::signStr($post_data,$merchant['secret_key']);
            $aes      = new Aes($merchant['secret_key'],'AES-256-ECB');//32位密钥
            $mysign   = $aes->encrypt($sign_str);
        }
        
        if($mysign != $sign){
            $this->error('签名错误');
        }
        
        //找出订单
        $order = Db::name('df_order')
            ->where(['mer_id'=>$merchant['id'], 'trade_no'=>$trade_no])
            ->field('amount,status,trade_no,out_trade_no,createtime,ordertime')
            ->find();

        if(!$order){
            $this->error('订单不存在');
        }

        $this->success('success',$order);

    }

    //代付查询余额
    public function queryBalance(){
        
        $mer_no = $this->request->post('mer_no');
        $sign   = $this->request->post('sign');

        Log::write('代付查余额----'.request()->ip().'----'.json_encode($_POST,JSON_UNESCAPED_UNICODE),'info');
        
        if(empty($mer_no) || empty($sign)){
            $this->error('参数缺少');
        }

        $merchant = Db::name('merchant')->where(['number'=>$mer_no])->field('money,secret_type,secret_key')->find();
        if(empty($merchant)){
            $this->error("信息不存在");
        }
        
        if($merchant['secret_type'] == '1'){
            $mysign = Utils::sign(['mer_no'=>$mer_no],$merchant['secret_key']);
        }else{
            $sign_str = Utils::signStr($post_data,$merchant['secret_key']);
            $aes      = new Aes($merchant['secret_key'],'AES-256-ECB');//32位密钥
            $mysign   = $aes->encrypt($sign_str);
        }
        
        if($mysign != $sign){
            $this->error('签名错误!');
        }
        
        $this->success('success',$merchant['money']);

    }


    //代收获取银行卡
    public function getBank(){
        
        $mer_no     = $this->request->post('mer_no');     //商户编号
        $sign       = $this->request->post('sign');       //加密值
        $random_str = $this->request->post('random_str'); //随机值
        $post_data  = $this->request->post();
        
        Log::write('getBank----'.request()->ip().'----'.json_encode($_POST,JSON_UNESCAPED_UNICODE),'info');

        if(empty($mer_no) || empty($sign) || empty($random_str)){
            $this->error('参数缺少');
        }
        
        $merchant = Db::name('merchant')->where(['number' => $mer_no, 'status' => 'normal'])->find();
        if(!$merchant){
            $this->error('信息不存在');
        }
        
        /*if(Config::get('site.encrypt_type') == 'md5'){
            $mysign = Utils::sign($post_data, $merchant['secret_key']);
        }else{
            $sign_str = Utils::signStr($post_data,$merchant['secret_key']);
            $aes      = new Aes($merchant['secret_key'],'AES-256-ECB');//32位密钥
            $mysign   = $aes->encrypt($sign_str);
        }
        
        if($mysign != $sign){
            $this->error('签名错误');
        }*/
        
        $re = Utils::getUserBankCard($merchant['agent_id'], $merchant['id'], $merchant['userids'], $merchant['bank_card_rule']);
        
        
        if($re == false){
            $this->error('暂无可用卡');
        }
        
        $this->success('success',$re);
        
    }
    
    //代收下单
    public function subBankOrder(){
        
        $mer_no      = $this->request->post('mer_no');     //商户编号
        $amount      = $this->request->post('amount');     //金额
        $bank_user   = $this->request->post('card_user');    //姓名
        $bank_number = $this->request->post('card_number'); //账户
        $bank_name   = $this->request->post('card_name');//开户行
        $trade_no    = $this->request->post('trade_no');   //商户平台订单号
        $notify_url  = $this->request->post('notify_url'); //异步回调地址
        $sign        = $this->request->post('sign');       //加密值
        $random_str  = $this->request->post('random_str'); //随机值
        $post_data   = $this->request->post();

        Log::write('subBankOrder----'.request()->ip().'----'.json_encode($_POST,JSON_UNESCAPED_UNICODE),'info');

        if (empty($amount)  || empty($trade_no) || empty($mer_no) || empty($bank_user) || empty($bank_number) || empty($bank_name) || empty($sign) || empty($notify_url) || empty($random_str)){
            $this->error('参数缺少');
        }

        $merchant = Db::name('merchant')->where(['number'=>$mer_no,'status'=>'normal'])->find();

        if(!$merchant){
            $this->error('信息不存在');
        }

        if(empty($merchant['api_ip'])){
            $this->error('权限不足');
        }
        
        $api_ip = explode(",", $merchant['api_ip']);
        if(!in_array(request()->ip(), $api_ip)){
            $this->error('权限不足');
        }
        
        if(strlen($random_str) < 8){
            $this->error('八位字符串');
        }
        
        if(Config::get('site.encrypt_type') == 'md5'){
            $mysign = Utils::sign($post_data, $merchant['secret_key']);
        }else{
            $sign_str = Utils::signStr($post_data,$merchant['secret_key']);
            $aes      = new Aes($merchant['secret_key'],'AES-256-ECB');//32位密钥
            $mysign   = $aes->encrypt($sign_str);
        }
        
        // if($mysign != $sign){
        //     $this->error('签名错误!');
        // }

        $bankCard = Db::name('bank_card')->where(['bank_number'=>$bank_number,'bank_user'=>$bank_user,'bank_name'=>$bank_name,'status'=>1])->find();
        if(empty($bankCard)){
            $this->error('卡号不存在');
        }
        
        $out_trade_no = Utils::buildOutTradeNo();
        $creat_time   = time();

        //生成订单
        $data = [
            'user_id'       => $bankCard['user_id'],
            'mer_id'        => $merchant['id'],
            'agent_id'      => $merchant['agent_id'],
            'card_id'       => $bankCard['id'],
            'trade_no'      => $trade_no,
            'out_trade_no'  => $out_trade_no,
            'bank_name'     => $bank_name,
            'bank_user'     => $bank_user,
            'bank_number'   => $bank_number,
            'amount'        => $amount,
            'notify_url'    => $notify_url,
            'createtime'    => $creat_time,
            'ip_address'    => request()->ip(),
        ];

        $result = Db::name('bank_order')->insert($data);

        if(!$result){
            $this->error('提交失败');
        }
        
        $this->success('success',$out_trade_no);
        //return json(['code'=>1,'msg'=>'success','data'=>$out_trade_no,'time'=>time()]);

    }
    
    //代收查单
    public function queryBankOrder(){
        $mer_no     = $this->request->post('mer_no');
        $sign       = $this->request->post('sign');
        $trade_no   = $this->request->post('trade_no');
        $post_data  = $this->request->post();

        if(empty($mer_no) || empty($sign) || empty($trade_no)){
            $this->error('参数缺少');
        }

        $merchant = Db::name('merchant')->where(['number'=>$mer_no,'status'=>'normal'])->find();

        if(Config::get('site.encrypt_type') == 'md5'){
            $mysign = Utils::sign($post_data, $merchant['secret_key']);
        }else{
            $sign_str = Utils::signStr($post_data,$merchant['secret_key']);
            $aes      = new Aes($merchant['secret_key'],'AES-256-ECB');//32位密钥
            $mysign   = $aes->encrypt($sign_str);
        }

        if($mysign != $sign){
            $this->error('签名错误');
        }

        //找出订单
        $order = Db::name('bank_order')
            ->where(['mer_id'=>$merchant['id'], 'trade_no'=>$trade_no, 'is_callback' => 0])
            ->field('amount,status,trade_no,out_trade_no,createtime')
            ->find();

        if(!$order){
            $this->error('订单不存在');
        }

        $this->success('success',$order);

    }

}