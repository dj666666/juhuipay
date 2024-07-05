<?php

namespace app\api\controller;

use app\admin\model\order\Order;
use app\admin\model\user\User;
use app\common\controller\Api;
use app\common\library\QrcodeService;
use app\common\library\ThirdHx;
use app\common\library\ThirdPay;
use think\Exception;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Config;
use app\common\library\Utils;
use app\common\library\MoneyLog;
use app\common\library\Accutils;
use app\common\library\Rsa;
use think\cache\driver\Redis;
use think\facade\Log;
use think\facade\Queue;
use app\common\controller\Jobs;
use fast\Random;
use app\admin\model\ippool\Blackippool;

/**
 * 示例接口
 */
class Gateway extends Api
{

    //如果$noNeedLogin为空表示所有接口都需要登录才能请求
    //如果$noNeedRight为空表示所有接口都需要验证权限才能请求
    //如果接口已经设置无需登录,那也就无需鉴权了
    //
    // 无需登录的接口,*表示全部
    protected $noNeedLogin = ['subtest', 'moneytt','suborder','checkOrder','order','queryorder','queryBalance','test', 'test1','aestest','getOrder','reportOrder','subcode','getCode','ordertest','jumptopay','subnickname','toHnapay','subTbCode','subJwCard','setName','subJDCard','subAcount','getZdOrder','getAaOrder'];
    // 无需鉴权的接口,*表示全部
    protected $noNeedRight = ['test2'];
    
    public function suborder(){

        $amount      = $this->request->post('amount');//金额
        $trade_no    = $this->request->post('trade_no');//商户平台订单号
        $mer_no      = $this->request->post('mer_no');//商户编号
        $return_type = $this->request->post('return_type');//返回类型 html/json
        $pay_type    = $this->request->post('pay_type');//通道编码
        $sign        = $this->request->post('sign');//加密值
        $notify_url  = $this->request->post('notify_url');//异步回调地址
        $return_url  = $this->request->post('return_url');//同步回调地址
        $remark      = $this->request->post('remark');//备注
        $post_data   = $this->request->post();
        
        
        try {
            
            $file = fopen('lock.txt', 'w+');
            
            //加锁
            if (flock($file, LOCK_EX)) {
                
                Log::write(request()->ip() . '----' . json_encode($post_data, JSON_UNESCAPED_UNICODE), 'suborder');
                
                $sub_error_data = [
                    'out_trade_no' => 'suborder',
                    'trade_no'     => empty($trade_no) ? 'error' : $trade_no,
                    'msg'          => '提单',
                    'content'      => $post_data,
                ];
    
                if (empty($amount) || empty($trade_no) || empty($mer_no) || empty($return_type) || empty($sign) || empty($notify_url) || empty($return_url) || empty($pay_type)) {
                    $sub_error_data['msg'] = '提单失败-参数缺少';
                    event('OrderError', $sub_error_data);
                    $this->error('参数缺少');
                }
                //根据mer_no找到商户
                $findmerchant = Db::name('merchant')->where(['number' => $mer_no, 'status' => 'normal'])->find();
                $sub_error_data['agent_id'] = $findmerchant['agent_id'];
                
                if (!$findmerchant) {
                    $sub_error_data['msg'] = '提单失败-信息不存在';
                    event('OrderError', $sub_error_data);
                    $this->error('信息不存在');
                }
                
                if ($findmerchant['sub_order_status'] != 1) {
                    $sub_error_data['msg'] = '提单失败-提单状态未打开';
                    event('OrderError', $sub_error_data);
                    $this->error('权限不足!!');
                }
                
                if (Config::get('site.order_checkmerchantpayip') == '1') {
                    if (empty($findmerchant['api_ip'])) {
                        $sub_error_data['msg'] = '提单失败-权限不足';
                        event('OrderError', $sub_error_data);
                        $this->error('权限不足');
                    }
        
                    $api_ip = explode(",", $findmerchant['api_ip']);
                    if (!in_array(request()->ip(), $api_ip)) {
                        $sub_error_data['msg'] = '提单失败-权限不足!';
                        event('OrderError', $sub_error_data);
                        $this->error('权限不足!');
                    }
                    
                }
                
                $mysign = Utils::sign($post_data, $findmerchant['secret_key']);
                
                if ($mysign != $sign) {
                    $sub_error_data['msg'] = '提单失败-签名错误';
                    $sub_error_data['content'] = json_encode($sub_error_data['content'], JSON_UNESCAPED_UNICODE) . '系统签名：'.$mysign;
                    event('OrderError', $sub_error_data);
                    $this->error('签名错误');
                }
                
                if (!in_array($return_type, ['json', 'html'])) {
                    $sub_error_data['msg'] = '提单失败-返回类型错误';
                    event('OrderError', $sub_error_data);
                    $this->error('返回类型错误!');
                }
                
                // || Utils::isDecimal($amount)
                if ($amount < $findmerchant['min_money'] || $amount > $findmerchant['max_money']) {
                    $sub_error_data['msg'] = '提单失败-单笔金额错误';
                    event('OrderError', $sub_error_data);
                    $this->error('单笔金额错误');
                }
                
                //商户余额 o不开启1增加2扣除
                if (Config::get('site.merchant_rate') == '2') {
                    //判断商户是否有余额
                    if ($findmerchant['money'] < $amount) {
                        $sub_error_data['msg'] = '提单失败-商户余额不足';
                        event('OrderError', $sub_error_data);
                        $this->error('余额不足');
                    }
                }

                $merAcc = Db::name('mer_acc')->where(['mer_id' => $findmerchant['id'], 'acc_code' => $pay_type])->find();
                if(empty($merAcc)){
                    $sub_error_data['msg'] = '提单失败-商户通道不存在';
                    event('OrderError', $sub_error_data);
                    $this->error('通道错误');
                }

                if ($merAcc['status'] == 0) {
                    $sub_error_data['msg'] = '提单失败-通道关闭';
                    event('OrderError', $sub_error_data);
                    $this->error('通道错误');
                }

                //三方代收
                if($findmerchant['is_third_pay'] == '1'){
                    return $this->toThirdPay($findmerchant, $post_data);
                }
                
                $findAgent      = Db::name('agent')->where('id',$findmerchant['agent_id'])->cache(true,60)->find();
                $qrcodeService  = new QrcodeService($findmerchant['agent_id'], $findmerchant);
                $get_qrcode_res = $qrcodeService->getAccQrcode($findmerchant['id'], $pay_type, $trade_no, $amount);
                
                if(!isset($get_qrcode_res['code'])){
                    $error_msg             = '请检查对接信息';
                    $sub_error_data['msg'] = '提单失败'.$error_msg;
                    event('OrderError', $sub_error_data);
                    $this->error($error_msg);
                }
    
                if ($get_qrcode_res['code'] != 200) {
                    $error_msg = '系统异常请联系客服';
    
                    //没有通道
                    if ($get_qrcode_res['code'] == 300) {
                        $error_msg = $get_qrcode_res['msg'];
                    }
    
                    //没取到码商
                    if ($get_qrcode_res['code'] == 101) {
                        $error_msg = '暂无可用通道';
                    }
    
                    //取到了码商没取到码
                    if ($get_qrcode_res['code'] == 102) {
                        $error_msg = '暂无可用通道!';
                    }
    
                    $sub_error_data['msg'] = '提单失败'.$get_qrcode_res['msg'];
                    event('OrderError', $sub_error_data);
                    $this->error($error_msg);
                }
    
                $user          = $get_qrcode_res['user'];
                $qrcode        = $get_qrcode_res['qrcode'];
                $now_time      = time();
                $mer_fees      = bcmul($amount, $merAcc['rate'], 2);
                $user_fees     = bcmul($amount, $user['rate'], 2);
                $out_trade_no  = Utils::buildOutTradeNo();
                $creat_time    = $now_time;
                $expire_time   = $now_time + Config::get('site.expire_time');
                $pay_url       = Utils::imagePath('/api/gateway/order/' . $out_trade_no, true);
                $qrcode_url    = empty($qrcode['pay_url']) ? empty($qrcode['image']) ? $qrcode['image'] : Utils::imagePath($qrcode['image'], false) : $qrcode['pay_url'];
                $float_amount  = $amount;
                $time_s        = date('s', $now_time);//当前时间----秒
                $tb_qrcode_id  = isset($qrcode['tb_qrcode']) ? $qrcode['tb_qrcode']['id'] : '';
                $tb_qrcode_url = isset($qrcode['tb_qrcode']) ? $qrcode['tb_qrcode']['pay_url'] : '';
                $ext_params    = [];
                
                $findAcc    = Db::name('acc')->where(['code' => $pay_type])->find();
                
                $float_amount = $qrcodeService->getAccFloatAmount($findAcc['float_json'], $amount, $qrcode['id']);
                
                //$pay_remark = '商城购物'. Random::numeric(8);
                $pay_remark = Random::numeric(10);
                
                //按订单来算 每次加1
                if ($pay_type == '1025' || $pay_type == '1007') {
                    $order_num  = Db::name('order')->where('pay_type', $pay_type)->whereDay('createtime')->count();
                    $pay_remark = 10000000 + $order_num;
                }
                if(in_array($pay_type, ['1008','1041'])){
                    $pay_remark = '爱心早餐'. Random::numeric(8);
                }
                
                //生成订单
                $data = [
                    'user_id'      => $user['id'],
                    'mer_id'       => $findmerchant['id'],
                    'agent_id'     => $findmerchant['agent_id'],
                    'trade_no'     => $trade_no,
                    'out_trade_no' => $out_trade_no,
                    'pay_type'     => $pay_type,
                    'amount'       => $amount,
                    'pay_amount'   => $float_amount,
                    'fees'         => $user_fees,
                    'mer_fees'     => $mer_fees,
                    'qrcode_id'    => $qrcode['id'],
                    'qrcode_name'  => $qrcode['name'],
                    'return_type'  => $return_type,
                    'pay_url'      => $pay_url,
                    'notify_url'   => $notify_url,
                    'return_url'   => $return_url,
                    'createtime'   => $creat_time,
                    'expire_time'  => $expire_time,
                    'order_type'   => 1,
                    'ip_address'   => request()->ip(),
                    'status'       => 2,
                    'remark'       => $remark ?? '',
                    'pay_remark'   => $pay_remark,
                    'xl_pay_data'  => $tb_qrcode_id,
                    'hand_pay_data'  => $tb_qrcode_url,
                ];
                
                $data = array_merge($data, $ext_params);
                
                $result1 = Db::name('order')->insertGetId($data);
                
                //每个代理单独控制提单是否扣款， 0不扣 1扣
                if ($findAgent['sub_order_rate'] == '1' ){
                    
                    //提单码商扣余额
                    MoneyLog::userMoneyChange($user['id'], $amount, $user['rate'], $trade_no, $out_trade_no, '提单扣除', 0, 0);
                    
                    //商户余额记录
                    MoneyLog::merchantMoneyChange($findmerchant['id'], $amount, 0, $trade_no, $out_trade_no, '提单扣除', 0, 0);
                }
                
                if (!$result1) {
                    $sub_error_data['msg'] = '提单失败-db错误';
                    event('OrderError', $sub_error_data);
                    $this->error('提交失败');
                }
                
                // 当前任务所需的业务数据，不能为 resource 类型，其他类型最终将转化为json形式的字符串
                $queueData = [
                    'request_type' => 4,
                    'order_id'     => $result1,
                    'out_trade_no' => $out_trade_no,
                    'trade_no'     => $trade_no,
                ];
                
                //当前任务归属的队列名称，如果为新队列，会自动创建
                $queueName = 'checkorder';
                $delay     = Config::get('site.expire_time');
                
                
                // 将该任务推送到消息队列，等待对应的消费者去执行
                //$isPushed = Queue::push(Jobs::class, $data, $queueName);//立即执行
                $isPushed = Queue::later($delay, Jobs::class, $queueData, $queueName);//延迟$delay秒后执行
               
                $sub_error_data['msg'] = '成功-码商【' . $user['username'] . '】' . $qrcode['name'];
                event('OrderError', $sub_error_data);
                
                //执行完成解锁
                flock($file,LOCK_UN);
                //关闭文件
                fclose($file);
                
                
                if ($return_type == 'json') {
                    $returnData = [
                        'out_trade_no' => $out_trade_no,
                        'amount'       => $amount,
                        'trade_no'     => $trade_no,
                        'expire_time'  => $expire_time,
                        'pay_url'      => $pay_url,
                    ];
    
                    return json(['code' => 1, 'msg' => 'success', 'data' => $returnData, 'time' => time()]);
                }
    
                return redirect('order/' . $out_trade_no);
    
            }
    
            $this->error('system error');
            
        }catch (Exception $e) {
            
            Log::write('提单异常----'.$e->getFile().'----'. $e->getLine() .'----' .$e->getMessage(),'suborder');
            
            $sub_error_data['msg'] = '提单异常'.$e->getMessage();
            event('OrderError', $sub_error_data);
            
            $this->error('异常失败'.$e->getMessage());
        }
    }
    
    
    //三方代收
    public function toThirdPay($findmerchant, $post_data){
        
        $now_time      = time();
        $out_trade_no  = Utils::buildOutTradeNo();
        $creat_time    = $now_time;
        $expire_time   = $now_time + Config::get('site.expire_time');
        $pay_url       = Utils::imagePath('/api/gateway/order/' . $out_trade_no, true);
        
        //生成订单
        $data = [
            'user_id'      => 9999,
            'mer_id'       => $findmerchant['id'],
            'agent_id'     => $findmerchant['agent_id'],
            'trade_no'     => $post_data['trade_no'],
            'out_trade_no' => $out_trade_no,
            'pay_type'     => $post_data['pay_type'],
            'amount'       => $post_data['amount'],
            'pay_amount'   => $post_data['amount'],
            'fees'         => 0,
            'mer_fees'     => 0,
            'qrcode_id'    => 1,
            'qrcode_name'  => '三方',
            'return_type'  => $post_data['return_type'],
            'pay_url'      => $pay_url,
            'notify_url'   => $post_data['notify_url'],
            'return_url'   => $post_data['return_url'],
            'createtime'   => $creat_time,
            'expire_time'  => $expire_time,
            'order_type'   => 1,
            'ip_address'   => request()->ip(),
            'status'       => 2,
            'remark'       => $remark ?? '',
            'pay_remark'   => time(),
        ];
        
        $result1 = Db::name('order')->insertGetId($data);
        
        //当前任务归属的队列名称，如果为新队列，会自动创建
        $queueName = 'checkorder';
        $delay     = Config::get('site.expire_time');
        
        // 当前任务所需的业务数据，不能为 resource 类型，其他类型最终将转化为json形式的字符串
        $queueData = [
            'request_type' => 4,
            'order_id'     => $result1,
            'out_trade_no' => $out_trade_no,
            'trade_no'     => $post_data['trade_no'],
        ];
        
        // 将该任务推送到消息队列，等待对应的消费者去执行
        //$isPushed = Queue::push(Jobs::class, $data, $queueName);//立即执行
        $isPushed = Queue::later($delay, Jobs::class, $queueData, $queueName);//延迟$delay秒后执行
        
        $third_res  = ThirdPay::instance()->checkPayType($findmerchant['agent_id'], $out_trade_no, $post_data['amount'], $post_data['pay_type'], $findmerchant['third_acc_ids']);
        
        if($third_res['status'] == false){
            
            Log::write(request()->ip() . '----三方提单失败'. '----' . $third_res['msg'], 'thirdPay');
            
            $this->error('提单失败');
        }
        
        $returnData = [
            'out_trade_no' => $out_trade_no,
            'amount'       => $post_data['amount'],
            'trade_no'     => $post_data['trade_no'],
            'expire_time'  => $expire_time,
            'pay_url'      => $third_res['data'],
        ];
        
        return json(['code' => 1, 'msg' => 'success', 'data' => $returnData, 'time' => time()]);
            
            
            
    }
    //重定向到订单页面
    public function order($order_sn){
        
        $ip = request()->ip();
        Log::write($ip.'----'.$order_sn,'topay');
        
        $error_data = [
            'out_trade_no' => empty($order_sn) ? 'empty' : $order_sn,
            'trade_no'     => 'order',
            'msg'          => empty($order_sn) ? 'empty' : $order_sn,
            'content'      => empty($order_sn) ? 'empty' : $order_sn,
        ];
        
        if (empty($order_sn)){
            $error_data['msg'] = '参数缺少';
            event('OrderError', $error_data);
            $this->error('参数缺少');
        }

        $order = Db::name('order')->where(['out_trade_no'=>$order_sn])->whereDay('createtime','today')->find();
        if(empty($order)){
            $error_data['msg'] = '订单不存在';
            event('OrderError', $error_data);
            $this->success('订单不存在');
        }
        
        $error_data['trade_no'] = $order['trade_no'];

        //获取用户ip归属地
        //$address = Utils::getClientAddress($ip);
        
        //记录用户ip 设备
        $ip_device_data = [];
        
        if(empty($order['device_type'])){
            $device_type = Utils::getClientOsInfo();
            $ip_device_data['device_type'] = $device_type;
        }
        if(empty($order['user_ip_address'])){
            $ip_device_data['user_ip_address'] = $ip;
        }
        
        if(!empty($ip_device_data)){
            Db::name('order')->where(['id'=>$order['id']])->update($ip_device_data);
        }
        
        //判断是否加入黑名单
        $check_black_res = Utils::checkBlackUid($ip);
        if ($check_black_res) {
            $error_data['msg']  = $ip.'-ip已拉黑';
            event('OrderError', $error_data);
            Order::where(['id' => $order['id']])->update(['hc_pay_data' => 'ip已拉黑']);
            
            Blackippool::create(['ip' => $ip, 'remark' => $order['trade_no'] . '订单拉黑']);
            
            $this->error('支付失败');
        }

        //限制这个用户ip每天支付单数
        $user_pay_num = Config::get('site.user_pay_num');
        
        if($user_pay_num > 0){
            $ip_count =  Order::where(['status'=>Order::STATUS_COMPLETE,'user_ip_address'=>$ip])->whereDay('createtime')->count();
            if($ip_count >= $user_pay_num){
                
                $error_data['msg']  = $ip.'支付上限';
                event('OrderError', $error_data);
                $this->success('支付上限，请联系客服');
            }
        }
        
        if($order['status'] == 1){
            $this->success('支付完成');
        }

        if($order['status'] == 3){
            $this->error('支付失败');
        }

        if(time() > $order['expire_time']){
            $this->error('订单已过期');
        }

        //$findAcc    = Db::name('acc')->where(['code' => $order['pay_type']])->cache(true, 600)->find();
        $findAcc    = Db::name('acc')->where(['code' => $order['pay_type']])->find();
        $findQrcode = Db::name('group_qrcode')->where(['id' => $order['qrcode_id']])->find();

        $view         = $findAcc['mapping_path'];
        $pay_url      = $order['pay_url'];
        $click_data   = '';
        $inalipay_url = '';
        $remark       = $order['remark'];
        $pay_remark   = $order['pay_remark'];
        
        $accutils = new Accutils();
        switch ($order['pay_type']) {
            case '1001':
                //微信群二维码
                $pay_url = $findQrcode['pay_url'];
                $remark = $order['pay_remark'];
                
                break;
            case '1003':
                //抖音红包
                //$click_data = "snssdk1128://search/trending";//抖音排行榜
                $click_data = 'snssdk1128://user/profile/'.$findQrcode['zfb_pid'].'?refer=web&gd_label=click_wap_profile_bottom&type=need_follow&needlaunchlog=1';
                //$remark = $order['pay_remark'];
                
                //0623模式改成抖音分享链接的
                //$click_data = $findQrcode['pay_url'];
                
                $remark = $order['pay_remark'];
                
                //抖音群红包
                //$click_data = "snssdk1128://";
                //$view = 'dygroup';
                
                break;
            case '1008':
                
                //小额uid主体模式
                $res = $accutils->alipayGmPayData($order, $findQrcode);
                
                //$pay_url = 'alipays://platformapi/startapp?appId=60000105&url='.urlencode('https://www.alipay.com/?appId=20000123&actionType=scan&biz_data='.urlencode('{"s":"money","u":"'.$findQrcode['zfb_pid'].'","a":"'.$order['pay_amount'].'","m":"'.$order['pay_remark'].'"}'));
                
                /*//跳转打开自己的页面再点击支付
                //$click_data = 'https://ds.alipay.com/?from=mobilecodec&scheme='. urlencode('alipays://platformapi/startapp?saId=10000007&clientVersion=3.7.0.0718&qrcode='. $order['pay_url']);
                
                $click_data = 'alipays://platformapi/startapp?appId=20000067&url=' . urlencode('https://render.alipay.com/p/s/i?scheme=' . urlencode('alipays://platformapi/startapp?saId=10000007&qrcode='. urlencode($order['pay_url'])));
                
                if(strpos($_SERVER['HTTP_USER_AGENT'], 'Alipay') != false){

                    $view = 'gateway/zfbgm/inalipay2';

                    //内部再点击发起支付
                    $inalipay_url = 'https://ds.alipay.com/?from=mobilecodec&scheme='.urlencode('alipays://platformapi/startapp?saId=10000007&clientVersion=3.7.0.0718&qrcode='.urlencode('alipays://platformapi/startapp?appId=20000123&actionType=scan&biz_data={"s":"money","u":"'.$findQrcode['zfb_pid'].'","a":"'.$order['pay_amount'].'","m":"'.$order['pay_remark'].'"}'));//对应inalipay2页面

                }else{
                    $view = 'gateway/zfbgm/browser7';
                }*/
                
                /*//0516 小额10-100新模式
                $pay_url = 'https://render.alipay.com/p/s/i?scheme='.urlencode('alipays://platformapi/startapp?appId=20000123&actionType=scan&u='.$findQrcode['zfb_pid'].'&a='.$order['pay_amount'].'&m='.$order['pay_remark'].'&biz_data={"s":"money","u":"'.$findQrcode['zfb_pid'].'","a":"'.$order['pay_amount'].'","m":"'.$order['pay_remark'].'"}');
                
                $click_data = $pay_url;*/
                
                
                //0524小额
               /* $pay_url = 'alipays://platformapi/startapp?appId=20000123&actionType=scan&biz_data=' . urlencode('{"s": "money","u": "'.$findQrcode['zfb_pid'].'","a": "'.$order['pay_amount'].'","m":"'.$order['pay_remark'].'"}');
                
                $click_data = $pay_url;*/
                
                /*$pay_url = 'https://render.alipay.com/p/s/i?scheme='.urlencode('alipays://platformapi/startapp?appId=20000123&actionType=scan&biz_data=' . urlencode('{"s": "money","u": "'.$findQrcode['zfb_pid'].'","a": "'.$order['pay_amount'].'","m":"'.$order['pay_remark'].'"}'));
                $click_data = $pay_url;*/
                
                //6.01小额1-50
                /*$pay_url = 'alipays://platformapi/startapp?appId=20000123&actionType=scan&biz_data='.urlencode('{"s": "money","u": "'.$findQrcode['zfb_pid'].'","a": "'.$order['pay_amount'].'","m":"'.$order['pay_remark'].'"}');
                
                $pay_url = str_replace('+', '%20', $pay_url); 
                $click_data = $pay_url;*/
                
                /*//个码链接模式
                $click_data = 'alipays://platformapi/startapp?appId=20000067&url='.urlencode('https://render.alipay.com/p/s/i?scheme='.urlencode('alipays://platformapi/startapp?saId=10000007&qrcode='.$findQrcode['pay_url']));*/
                
                //$pay_url = $findQrcode['pay_url'];
                
                
                //6.3小额1-50 跳银行卡再跳uid转账
                /*$pay_url = 'alipays://platformapi/startapp?appId=60000105&url='.urlencode('https://www.alipay.com/?appId=20000123&actionType=scan&biz_data={"s":"money","u":"'.$findQrcode['zfb_pid'].'","a":"'.$order['pay_amount'].'","m":"'.$order['pay_remark'].'"}');

                $click_data = 'alipayqr://platformapi/startapp?saId=10000007&qrcode='.urlencode('alipays://platformapi/startapp?appId=60000105&url='.urlencode('https://www.alipay.com/?appId=20000123&actionType=scan&biz_data={"s":"money","u":"'.$findQrcode['zfb_pid'].'","a":"'.$order['pay_amount'].'","m":"'.$order['pay_remark'].'"}'));*/
                
                
                /*$click_data = 'https://www.alipay.com/?appId=20000116&actionType=toAccount&sourceId=contactStage&chatUserId='.$findQrcode['zfb_pid'].'&displayName=TK&chatUserName=TK&chatUserType=1&skipAuth=true&amount='.$order['pay_amount'].'&memo='.$order['pay_remark'];
                
                $pay_url = $click_data;*/
                
                //2024 0321 跑中额uid得拿来跑小额不行
                /*$click_data = 'alipayqr://platformapi/startapp?saId=10000007&qrcode=' . urlencode('https://ds.alipay.com/?from=pc&appId=20000116&actionType=toAccount&goBack=NO&amount='.$order['pay_amount'].'&userId='.$findQrcode['zfb_pid'].'&memo='.$order['out_trade_no']);
                $pay_url = 'https://ds.alipay.com/?from=pc&appId=20000116&actionType=toAccount&goBack=NO&amount='.$order['pay_amount'].'&userId='.$findQrcode['zfb_pid'].'&memo='.$order['out_trade_no'];*/
                
                
                //2024 0321 
                //跳到支付宝打开自己的页面，请求组转加密方式 再跳
                $click_data = 'alipays://platformapi/startapp?saId=20000989&url=' . urlencode($order['pay_url']);
                
                if(strpos($_SERVER['HTTP_USER_AGENT'], 'Alipay') != false){

                    $view = 'gateway/zfbgm/inalipay/zfbuid';
                    
                    //内部再点击发起支付
                    $inalipay_url = 'alipays://platformapi/startapp?appId=20000989&url='.urlencode('https://www.alipay.com/?appId=20000116&actionType=toAccount&sourceId=contactStage&chatUserId='.$findQrcode['zfb_pid'].'&displayName=TK&chatUserName=TK&chatLoginId=186******71&chatHeaderUrl=http://tfs.alipayobjects.com/images/partner/TB1OD00cMSJDuNj_160X160&chatUserType=1&skipAuth=true&amount='.$order['pay_amount'].'&memo='.$order['out_trade_no']);//对应zfbuid页面
                    
                }else{
                    $view = 'gateway/zfbgm/browser8';
                }
                
                
                break;
            case '1041':
                //uid中额
                $pay_amount = intval($order['pay_amount']);
                
                //二维码
                $pay_url = 'alipays://platformapi/startapp?appId=60000105&url='.urlencode('https://www.alipay.com/?appId=20000123&actionType=scan&biz_data={"s":"money","u":"'.$findQrcode['zfb_pid'].'","a":"'.$order['pay_amount'].'","m":"'.$order['pay_remark'].'"}');
                
                //点击跳转
                //$click_data = 'alipayqr://platformapi/startapp?saId=10000007&qrcode='.urlencode('alipays://platformapi/startapp?appId=60000105&url='.urlencode('https://www.alipay.com/?appId=20000123&actionType=scan&biz_data={"s":"money","u":"'.$findQrcode['zfb_pid'].'","a":"'.$order['pay_amount'].'","m":"'.$order['pay_remark'].'"}'));
                
                //$click_data = 'alipays://platformapi/startapp?appId=20000123&actionType=scan&biz_data=' . urlencode('{"s": "money","u": "'.$findQrcode['zfb_pid'].'","a": "'.$order['pay_amount'].'","m":"'.$order['pay_remark'].'"}');
                
                
                //$click_data = 'https://render.alipay.com/p/s/i?scheme='.urlencode('alipays://platformapi/startapp?appId=20000123&actionType=scan&u='.$findQrcode['zfb_pid'].'&a='.$order['pay_amount'].'&m='.$order['pay_remark'].'&biz_data={"s":"money","u":"'.$findQrcode['zfb_pid'].'","a":"'.$order['pay_amount'].'","m":"'.$order['pay_remark'].'"}');
                
                //跳陌生人转账
                //$click_data = 'https://ds.alipay.com/?from=mobilecodec&scheme='.urlencode('alipays://platformapi/startapp?saId=10000007&clientVersion=3.7.0.0718&qrcode='.urlencode('alipays://platformapi/startapp?appId=09999988&actionType=toAccount&goBack=NO&amount='.$order['pay_amount'].'&userId='.$findQrcode['zfb_pid']));
                
                //2024 0320 uid中额
                $click_data = 'https://www.alipay.com/?appId=20000116&actionType=toAccount&sourceId=contactStage&chatUserId='.$findQrcode['zfb_pid'].'&displayName=TK&chatUserName=TK&chatUserType=1&skipAuth=true&amount='.$order['pay_amount'].'&memo='.$order['pay_remark'];
                $pay_url = $click_data;
                
                $view = 'gateway/zfbgm/browser8';
                break;
            case '1007':
                //用来生成二维码 中文备注需要编码一下
                //$pay_url = 'alipays://platformapi/startapp?appId=20000123&actionType=scan&biz_data={"s": "money", "u": "'.$findQrcode['zfb_pid'].'", "a": "'.$order['pay_amount'].'", "m": "'.$order['pay_remark'].'"}';
                //
                //$pay_url = 'alipays://platformapi/startapp?appId=20000123&actionType=scan&biz_data='. urlencode('{"a":"'.$order['pay_amount'].'","s":"money","u":"'.$findQrcode['zfb_pid'].'","m":"'.$order['pay_remark'].'"}');
    
                //$pay_url = 'alipays://platformapi/startapp?appId=60000105&url='.urlencode('https://www.alipay.com/?appId=20000123&actionType=scan&biz_data='.urlencode('{"s":"money","u":"'.$findQrcode['zfb_pid'].'","a":"'.$order['pay_amount'].'","m":"'.$order['pay_remark'].'"}'));
                
                //先跳陌生人转账
                //$pay_url = 'alipays://platformapi/startapp?appId=09999988&actionType=toAccount&goBack=NO&amount='.$order['pay_amount'].'&userId='.$findQrcode['zfb_pid'].'&memo='.$order['out_trade_no'];
                
                //用来生成二维码
                //$pay_url = 'https://render.alipay.com/p/s/i?scheme='.urlencode('alipays://platformapi/startapp?appId=60000105&url=https://www.alipay.com/?appId=20000123&actionType=scan&biz_data='. urlencode('{"s": "money","u": "'.$findQrcode['zfb_pid'].'","a": "'.$order['pay_amount'].'","m":"'.$order['out_trade_no'].'"}'));
                
                
                
                //点击加载一下支付宝页面再跳
                //$click_data = 'alipays://platformapi/startapp?appId=60000105&url=' . urlencode('https://www.alipay.com/?appId=20000123&actionType=scan&biz_data={"s":"money","u":"'.$findQrcode['zfb_pid'].'","a":"'.$order['pay_amount'].'","m":"'.$order['out_trade_no'].'"}');
                //$click_data = 'alipays://platformapi/startapp?appId=60000105&url=' . urlencode('https://www.alipay.com/?appId=20000123&actionType=scan&biz_data='. urlencode('{"a":"'.$order['pay_amount'].'","s":"money","u":"'.$findQrcode['zfb_pid'].'","m":"'.$order['out_trade_no'].'"}'));
                
                
                //点击直接跳 urlencode一下
                //$click_data = 'alipays://platformapi/startapp?appId=20000123&actionType=scan&biz_data='. urlencode('{"s":"money","u":"'.$findQrcode['zfb_pid'].'","a":"'.$order['pay_amount'].'","m":"'.$order['out_trade_no'].'"}');
                
                //点击直接跳不encode
                //$click_data = 'alipays://platformapi/startapp?appId=20000123&actionType=scan&biz_data={"s": "money", "u": "2088802548625055", "a": "0.10", "m": "2022053017524722967"}';
                
                //点击直接跳，先打开淘票票再跳支付
                //alipays://platformapi/startapp?appId=68687093&url=https://www.alipay.com/?appId=20000123&actionType=scan&biz_data={"a":"30.00","s":"money","u":"2088342994500547","m":"F602673845464856"}
                //$click_data = 'alipays://platformapi/startapp?appId=68687093&url='.urlencode('https://www.alipay.com/?appId=20000123&actionType=scan&biz_data='. urlencode('{"a":"'.$order['pay_amount'].'","s":"money","u":"'.$findQrcode['zfb_pid'].'","m":"'.$order['out_trade_no'].'"}'));
                
                
                //点击直接跳 browser
                //$click_data = 'https://render.alipay.com/p/s/i?scheme='.urlencode('alipays://platformapi/startapp?appId=60000105&url='.urlencode('https://www.alipay.com/?appId=20000123&actionType=scan&biz_data={"a":"'.$order['pay_amount'].'","s":"money","u":"'.$findQrcode['zfb_pid'].'","m":"'.$order['out_trade_no'].'"}'));
                //$click_data = 'alipays://platformapi/startapp?appId=68687009&url='.urlencode('https://www.alipay.com/?appId=20000123&actionType=scan&biz_data={"s": "money","u": "'.$findQrcode['zfb_pid'].'","a": "'.$order['amount'].'","m":"'.$order['out_trade_no'].'"}');
                
                //uid 小额500-800浏览器直接跳转发起支付
                //$click_data = 'https://render.alipay.com/p/s/i?scheme='.urlencode('alipays://platformapi/startapp?appId=20000123&actionType=scan&u='.$findQrcode['zfb_pid'].'&a='.$order['pay_amount'].'&m='.$order['pay_remark'].'&biz_data={"s":"money","u":"'.$findQrcode['zfb_pid'].'","a":"'.$order['pay_amount'].'","m":"'.$order['pay_remark'].'"}');
                
                
                
                
                //点击直接跳64编码
                /*$click_data = 'alipays://platformapi/startapp?appId=20000199&url='. urlencode('data:text/html;base64,'.base64_encode('<!DOCTYPE html><html lang = "en" ><head><meta charset = "UTF-8" ><meta name = "viewport" content = "width=device-width, initial-scale=1.0" ><meta http - equiv = "X-UA-Compatible" content = "ie=edge" ><title ></title ><script src = "https://gw.alipayobjects.com/as/g/h5-lib/alipayjsapi/3.1.1/alipayjsapi.min.js" ></script ></head><body><script>var userId = "'.$findQrcode['zfb_pid'].'";var money = "'.$order['pay_amount'].'";var remark = "'.$order['out_trade_no'].'";function returnApp(){   AlipayJSBridge . call("exitApp") }function ready(a) {    window . AlipayJSBridge ? a && a() : document . addEventListener("AlipayJSBridgeReady", a, !1)}ready(function () {   try {       var       a = {           actionType:          "scan",          u: userId,          a: money,          m: remark,          biz_data: {              s:              "money",              u: userId,              a: money,              m: remark         }    }} catch (b) {    returnApp()}AlipayJSBridge . call("startApp", {    appId: "20000123",    param: a}, function (a) {})});document . addEventListener("resume", function (a) {    returnApp()});</script ></body ></html ><script >//禁止右键function click(e) {    if (document . all) {        if (event . button == 2 || event . button == 3) {            oncontextmenu = "return false";        }    }    if (document . layers) {        if (e . which == 3) {            oncontextmenu = "return false";        }    }}if (document . layers) {    document . captureEvents(Event . MOUSEDOWN);}document . onmousedown = click;document . oncontextmenu = new function ("return false;")document . onkeydown = document . onkeyup = document . onkeypress = function () {    if (window . event . keyCode == 12) {        window . event . returnValue = false;        return (false);    }}</script ><script >//禁止F12function fuckyou(){    window . close(); //关闭当前窗口(防抽)    window . location = "about:blank"; //将当前窗口跳转置空白页}function click(e) {    if (document . all) {        if (event . button == 2 || event . button == 3) {            oncontextmenu = "return false";        }    }    if (document . layers) {        if (e . which == 3) {            oncontextmenu = "return false";       }   }}   if (document . layers) {    fuckyou();    document . captureEvents(Event . MOUSEDOWN);}  document . onmousedown = click;           document . oncontextmenu = new function ("return false;")    document . onkeydown = document . onkeyup = document . onkeypress = function () {    if (window . event . keyCode == 123) {        fuckyou();        window . event . returnValue = false;        return (false);    }}      </script >'));*/
                
                
                
                //点击跳转到支付宝打开自己的页面再跳
                //alipays://platformapi/startapp?appId=20000199&url=
                //alipays://platformapi/startapp?appId=20000067&url=
                //alipays://platformapi/startapp?saId=10000007&qrcode=" + encodeURIComponent('http://risk.duosheng168.net/douluo/web/toAliApp/BG2206040033050144'
                //alipays://platformapi/startapp?appId=60000029&showLoading=YES&url=
                
                //alipays://platformapi/startapp?saId=20000989&url=https%3A%2F%2Fpay.ailefe..com%2F%3Fc%3DPay%26a%3DpreAlipay%26osn%3DYMX2022060111302662133238
                //$click_data = 'alipays://platformapi/startapp?saId=20000199&url='. urlencode($order['pay_url']);
                //$click_data = 'alipays://platformapi/startapp?appId=60000029&showLoading=YES&url='. urlencode($order['pay_url']);
                //$click_data = 'alipays://platformapi/startapp?appId=20000989&url='. urlencode($order['pay_url']);
                /*$click_data = 'https://ds.alipay.com/?from=mobilecodec&scheme='. urlencode('alipays://platformapi/startapp?saId=10000007&clientVersion=3.7.0.0718&qrcode='. $order['pay_url']);
                
                
                if(strpos($_SERVER['HTTP_USER_AGENT'], 'Alipay') != false){
                    
                    $view = 'gateway/zfbgm/inalipay2';
                  
                    //$inalipay_url = 'alipays://platformapi/startapp?appId=20000123&actionType=scan&biz_data='. urlencode('{"s":"money","u":"'.$findQrcode['zfb_pid'].'","a":"'.$order['pay_amount'].'","m":"'.$order['out_trade_no'].'"}');
                  
                    //通过php内部跳转
                    //$inalipay_url = Utils::imagePath('/api/gateway/jumptopay?order_sn='.$order['out_trade_no'],true);
                  
                    //等待再跳淘票票再跳支付
                    //$inalipay_url = 'alipays://platformapi/startapp?appId=68687093&url='.urlencode('https://www.alipay.com/?appId=20000123&actionType=scan&biz_data='. urlencode('{"a":"'.$order['pay_amount'].'","s":"money","u":"'.$findQrcode['zfb_pid'].'","m":"'.$order['out_trade_no'].'"}'));
                    
                    //内部再点击发起支付
                    $inalipay_url = 'https://ds.alipay.com/?from=mobilecodec&scheme='.urlencode('alipays://platformapi/startapp?saId=10000007&clientVersion=3.7.0.0718&qrcode='.urlencode('alipays://platformapi/startapp?appId=20000123&actionType=scan&biz_data={"s":"money","u":"'.$findQrcode['zfb_pid'].'","a":"'.$order['pay_amount'].'","m":"'.$order['pay_remark'].'"}'));//对应inalipay2页面
                
                }else{
                  $view = 'gateway/zfbgm/browser7';
                  // = 'gateway/zfbgm/browser2';
                }*/
                
                
                /*//0324 直接跳 打开陌生人转账 不带备注
                //https://ds.alipay.com/?from=mobilecodec&scheme=alipays://platformapi/startapp?saId=10000007&clientVersion=3.7.0.0718&qrcode=alipays://platformapi/startapp?appId=09999988&actionType=toAccount&goBack=NO&amount=600&userId=2088542748345091
                $click_data = 'https://ds.alipay.com/?from=mobilecodec&scheme='.urlencode('alipays://platformapi/startapp?saId=10000007&clientVersion=3.7.0.0718&qrcode='.urlencode('alipays://platformapi/startapp?appId=09999988&actionType=toAccount&goBack=NO&amount='.$order['pay_amount'].'&userId='.$findQrcode['zfb_pid']));

                $view = 'gateway/zfbgm/browser7';*/
                
                //2023 0516 小额10-100新模式
                //$pay_url = 'https://render.alipay.com/p/s/i?scheme='.urlencode('alipays://platformapi/startapp?appId=20000123&actionType=scan&u=2088642246828572&a=29.98&m=商城购物25029250&biz_data={"s":"money","u":"'.$findQrcode['zfb_pid'].'","a":"'.$order['pay_amount'].'","m":"'.$order['pay_remark'].'"}');
                //$click_data = $pay_url;
                
                
                /*//2024 0320 uid中额
                $click_data = 'https://www.alipay.com/?appId=20000116&actionType=toAccount&sourceId=contactStage&chatUserId='.$findQrcode['zfb_pid'].'&displayName=TK&chatUserName=TK&chatUserType=1&skipAuth=true&amount='.$order['pay_amount'].'&memo='.$order['pay_remark'];
                $pay_url = $click_data;
                
                
                $view = 'gateway/zfbgm/browser7';*/
                
                $click_data = 'alipays://platformapi/startapp?saId=20000989&url=' . urlencode($order['pay_url']);
                
                if(strpos($_SERVER['HTTP_USER_AGENT'], 'Alipay') != false){

                    $view = 'gateway/zfbgm/inalipay/zfbuid';
                    
                    //内部再点击发起支付
                    $inalipay_url = 'alipays://platformapi/startapp?appId=20000989&url='.urlencode('https://www.alipay.com/?appId=20000116&actionType=toAccount&sourceId=contactStage&chatUserId='.$findQrcode['zfb_pid'].'&displayName=TK&chatUserName=TK&chatLoginId=186******71&chatHeaderUrl=http://tfs.alipayobjects.com/images/partner/TB1OD00cMSJDuNj_160X160&chatUserType=1&skipAuth=true&amount='.$order['pay_amount'].'&memo='.$order['pay_remark']);//对应zfbuid页面
                    
                }else{
                    $view = 'gateway/zfbgm/browser8';
                }
                
                break;
            case '1009':
                //支付宝小荷包
                //用来生成二维码
                $pay_url    = 'https://qr.alipay.com/cgx15992kxtauw3mqtzwub0';
                //点击跳转
                $click_data = 'https://qr.alipay.com/cgx15992kxtauw3mqtzwub0';
                
                $view = 'gateway/zfbheb/browser2';
                break;
            case '1010':
                //支付宝房租
                $pay_url    = 'https://ur.alipay.com/6Fv7Rkox809c5rMhEKWrB4';//用来生成二维码
                $click_data = 'https://ur.alipay.com/6Fv7Rkox809c5rMhEKWrB4';//点击跳转
                $remark     = $findQrcode['zfb_pid'];
                break;
            case '1011':
                //微信个码
                /*$pay_url = $findQrcode['pay_url'];//用来生成二维码
                if(strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false){
                    $view         = 'gateway/wxgm/inwechat';
                    $inalipay_url = $order['pay_url'];
                }else{
                    $view = 'gateway/wxgm/browser';
                }*/
                
                
                //$click_data = 'weixin://'.$findQrcode['pay_url'];
                if($view == 'gateway/wxgm/browser'){
                    $pay_url = $findQrcode['pay_url'];
                }else{
                    $pay_url = Utils::imagePath($findQrcode['image'], true);
                }
                
                break;
            case '1012':
                //汇潮支付宝
                if(strpos($_SERVER['HTTP_USER_AGENT'], 'Alipay') !== false){
                    $remark = $accutils->hczfbPayData($order);
    
                    $view = 'gateway/hczfb/inalipay';
                }else{
                    $view = 'gateway/hczfb/browser';
                }
                
                 //跳到支付宝打开自己的页面，请求组转加密方式 再跳
                $click_data = 'alipays://platformapi/startApp?appId=20000067&url=' . urlencode($order['pay_url']);
                
                break;
            case '1013':
                //瀚银支付宝
                for ($i = 0; $i<5; $i++){
                    $res = $accutils->handPayData($order, $ip, $i);
                    if($res['code'] == 200){
                        break;
                    }
                    sleep(1);
                }
                
                if($res['code'] == 200){
                    
                    $pay_data = json_decode($res['msg']['hand_pay_data'], true);
    
                    //用来生成二维码
                    $pay_url = $pay_data['expand']['pay_info'];
                    //用来点击跳转
                    //$click_data = 'alipays://platformapi/startapp?appId=10000007&qrcode=' . $pay_data['expand']['pay_info'];
                    $click_data = 'alipays://platformapi/startApp?appId=20000067&url=' . $pay_data['expand']['pay_info'];
                    
                }else{
                    $this->error($res['msg']);
                }
                
                break;
            case '1014':
                //迅雷直播支付宝 用迅雷的userid，不是用挂码的那个id
                $xl_res = $accutils->xlzbgetPayDataV2($order, $findQrcode['xl_user_id']);
                if($xl_res['code'] == 200){
                    $pay_url = $xl_res['msg']['xl_pay_data'];
                    $click_data = $xl_res['msg']['xl_pay_data'];//跳官方h5再打开支付宝
                }else{
                    $this->error('获取失败，请刷新页面或者重新发起支付');
                }
                break;
            case '1015':
                //迅雷直播微信
                $xl_res = $accutils->xlzbWeiXingetPayData($order, $findQrcode['zfb_pid'], $findQrcode['xl_user_id']);
                //halt($xl_res);
                if($xl_res['code'] == 200){
                    $pay_url = $xl_res['msg']['xl_pay_data'];
                    $click_data = $xl_res['msg']['xl_pay_data'];
                    //$click_data = 'alipays://platformapi/startApp?appId=20000067&url=' . $xl_res['msg']['xl_pay_data'];

                }else{
                     $this->error('获取失败，请刷新页面或者重新发起支付');
                }
                break;
                break;
            case '1017':
                
                //支付宝转卡
                $pay_url = $findQrcode['pay_url'];
                //$click_data = 'alipays://platformapi/startapp?appId=20000989&url='. $findQrcode['pay_url'];
                
                $pay_url = 'https://ds.alipay.com/?scheme='.urlencode('alipays://platformapi/startapp?appId=09999988&actionType=toCard&sourceId=bill&bankAccount='.$findQrcode['pay_url'].'&cardNo='.$findQrcode['zfb_pid'].'&money='.$order['pay_amount'].'&amount='.$order['pay_amount'].'&bankMark=&cardIndex=&cardNoHidden=true&cardChannel=HISTORY_CARD&orderSource=from&buyId=auto');
                $click_data = $pay_url;
                
                break;
                
            case '1018':
                
                //转账码
                $click_data = 'alipays://platformapi/startapp?appId=20000989&url='. urlencode('https://ur.alipay.com/_15blmjlqoVtPIBZhGfI8MF');
                
                if(!empty($order['pay_qrcode_image'])){
                    echo '您的转账码已提交,请等待支付结果';die;
                }
                
                break;
            case '1019':
                //快手支付宝
                $res = $accutils->kszfbPayData($order, $findQrcode['zfb_pid']);
                //halt($xl_res);
                if($res['code'] != 200){
                    $this->error('获取失败，请刷新页面或者重新发起支付');
                }

                if(strpos($_SERVER['HTTP_USER_AGENT'], 'Alipay') == false){
                    $pay_url = $order['pay_url'];
                    
                    
                    $pay_data = $res['msg']['xl_pay_data'];
                    parse_str($pay_data, $pay_data_arr);
                    
                    $pay_data_arr['notify_url'] = urlencode($pay_data_arr['notify_url']);
                    $pay_data_arr['return_url'] = urlencode($pay_data_arr['return_url']);
                    $pay_data_arr['sign']       = urlencode($pay_data_arr['sign']);
                    $pay_data_arr['timestamp']  = urlencode($pay_data_arr['timestamp']);
                    
                    $remark = $pay_data_arr;
                    
                    $view = 'gateway/kszb/topay';
                }else{
                    
                    $pay_url = $order['pay_url'];
                    $click_data = 'alipays://platformapi/startApp?' . $res['msg']['xl_pay_data'];
                    $view = 'gateway/kszb/browser';
                }
                
                
                break;
            case '1020':
                
                //皮皮直播支付宝
                for ($i = 0; $i<5; $i++){
                    
                    $xl_res = $accutils->ppzbzfbPayData($order, $findQrcode['zfb_pid']);
                    if($xl_res['code'] == 200){
                        break;
                    }
                    sleep(1);
                }
                
                
                if($xl_res['code'] == 200){
                    $pay_url = $xl_res['msg']['xl_pay_data'];
                    $click_data = $xl_res['msg']['xl_pay_data'];//跳官方h5打开支付宝
                    
                }else{
                    $this->error('获取失败，请刷新页面或者重新发起支付');
                }
                break;
            case '1021':
                
                $res = $accutils->xinszfbPayData($order ,$findQrcode);
                
                //方式1 浏览器打开自己界面点击跳转支付宝再提交表单到新生支付页面
                //新生支付宝h5
                if(strpos($_SERVER['HTTP_USER_AGENT'], 'Alipay') !== false){
                    $remark = $res['msg'];
                    $view = 'gateway/xinszfb/inalipay';
                }else{
                    $view = 'gateway/xinszfb/browser';
                }
                
                //方式1 跳到支付宝打开自己的页面，请求组转加密方式 再跳
                $click_data = 'alipays://platformapi/startApp?appId=20000067&url=' . urlencode($order['pay_url']);
                    
                //方式2 浏览器打开自己界面点击跳新生页面，让新生去打开支付宝
                //$click_data = Utils::imagePath('/api/gateway/topay/'.$order['out_trade_no'], true);
                
                break;
            case '1022':
                //YY支付宝
                $res = $accutils->yyzbzfbPayData($order ,$findQrcode);
                
                $click_data = 'alipays://platformapi/startApp?appId=20000067&url=' . urlencode($order['pay_url']);

                break;
            case '1023':
                //酷秀微信内付
                /*$res = $accutils->kxzbwxneifuPayData($order, $findQrcode['zfb_pid'], $findQrcode['xl_user_id']);
                if($res['code'] == 200){
                    $remark = $res['msg']['xl_pay_data'];//$jsApiParameters
                }else{
                    $this->error('获取失败，请刷新页面或者重新发起支付');
                }
                
                if(strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false){
                    //微信内
                    $view = 'gateway/kxzb/inweixin';
                }else{
                    $view = 'gateway/kxzb/browser';
                }
                
                $click_data = 'weixin://' . urlencode($order['pay_url']);*/
                
                //酷秀微信h5
                $res = $accutils->kxzbwxh5PayData($order, $findQrcode['zfb_pid']);
                if($res['code'] != 200){
                    $this->error('获取失败，请刷新页面或者重新发起支付');
                }
                
                $view = 'gateway/kxzb/wxbrowser';
                $click_data = $res['msg']['xl_pay_data'];
                
                break;
            case '1024':
                //快手微信h5
                $res = $accutils->ksweixinPayData($order, $findQrcode['xl_cookie']);
                if($res['code'] != 200){
                    $this->error('获取失败，请刷新页面或者重新发起支付');
                }
                
                $view = 'gateway/kszb/wxbrowser';
                
                $click_data = $res['msg']['hc_pay_data'];
                
                break;
            case '1025':
                
                //$click_data = $res['msg']['xl_pay_data'];
                //$click_data = $res['msg']['xl_pay_data'];
                
                //$view = 'gateway/zfbgm/qrcode/imgbrowser';
                
                $pay_url = $findQrcode['pay_url'];
                
                //20230517直接打开码
                /*$click_data = 'alipays://platformapi/startapp?appId=20000067&url='. urlencode('https://render.alipay.com/p/s/i?scheme='.urlencode('alipays://platformapi/startapp?saId=10000007&qrcode='.urlencode($pay_url)));*/
                
                //$click_data = 'alipays://platformapi/startapp?appId=20000067&url='.urlencode('https://render.alipay.com/p/s/i?scheme='.urlencode('alipays://platformapi/startapp?saId=10000007&qrcode='.$findQrcode['pay_url']));
                
                //输入名字的
                //$view = 'gateway/zfbgm/qrcode/browser4';
                
                //不输入名字的
                //$view = 'gateway/zfbgm/browser8';
                
                
                //输入名字跳内部 暂时不行
                $click_data = 'alipays://platformapi/startapp?saId=20000989&url=' . urlencode($order['pay_url']);
                
                if(strpos($_SERVER['HTTP_USER_AGENT'], 'Alipay') != false){

                    $view = 'gateway/zfbgm/inalipay/zfbgm';
                    
                    //内部再点击发起支付
                    $inalipay_url = 'alipays://platformapi/startapp?appId=20000989&url='. urlencode($findQrcode['pay_url']);
                    
                }else{
                    $view = 'gateway/zfbgm/zfbh5name';
                }
                
                break;
            
            case '1042':
                //uid大额
                //$click_data = $res['msg']['xl_pay_data'];
                //$click_data = $res['msg']['xl_pay_data'];
                
                //$view = 'gateway/zfbgm/qrcode/imgbrowser';
                
                $pay_url = $findQrcode['pay_url'];
                
                //20230517直接打开码
                //$click_data = 'alipays://platformapi/startapp?appId=20000067&url='. urlencode('https://render.alipay.com/p/s/i?scheme='.urlencode('alipays://platformapi/startapp?saId=10000007&qrcode='.urlencode($pay_url)));
                $click_data = $findQrcode['pay_url'];
                
                //输入名字的
                //$view = 'gateway/zfbgm/bigbrowser';
                
                //不输入名字的
                //$view = 'gateway/zfbgm/browser8';
                
                //输入名字的
                $view = 'gateway/zfbgm/qrcode/browser4';
                
                //不输入名字的
                //$view = 'gateway/zfbgm/browser8';
                
                break;
            case '1043':
                //uid超大额
                //$click_data = $res['msg']['xl_pay_data'];
                //$click_data = $res['msg']['xl_pay_data'];
                
                //$view = 'gateway/zfbgm/qrcode/imgbrowser';
                
                $pay_url    = $findQrcode['pay_url'];
                
                //20230517直接打开码
                $click_data = 'alipays://platformapi/startapp?appId=20000067&url='. urlencode('https://render.alipay.com/p/s/i?scheme='.urlencode('alipays://platformapi/startapp?saId=10000007&qrcode='.urlencode($pay_url)));
                
                //输入名字的
                $view = 'gateway/zfbgm/verybigbrowser';
                
                //不输入名字的
                //$view = 'gateway/zfbgm/browser8';
                
                break;
            case '1026':
                //百战支付宝
                $res = $accutils->yybaizhanzfbPayData($order, $findQrcode['zfb_pid']);
                if($res['code'] != 200){
                    $this->error('获取失败，请刷新页面或者重新发起支付');
                }
                
                $click_data = $res['msg']['xl_pay_data'];
                break;
            case '1027':
                //百战微信
                $res = $accutils->yybaizhanweixinPayData($order, $findQrcode['zfb_pid']);
                if($res['code'] != 200){
                    $this->error('获取失败，请刷新页面或者重新发起支付');
                }
                
                $click_data = $res['msg']['xl_pay_data'];
                break;
            case '1028':
                //gmm支付宝
                
                //$res = $accutils->gmmzfbPayDataPc($order, $findQrcode['id'], $findQrcode['zfb_pid'], $findQrcode['xl_cookie'], $findQrcode['cookie'],0);
                
                //$res = $accutils->gmmzfbPayDataPhone($order, $findQrcode, 0);
                $res = $accutils->gmmzfbPayDataH5($order, $findQrcode, 0);
                
                if($res['code'] != 200){
                    $this->error($res['msg']);
                }
                
                //$pay_url = 'alipays://platformapi/startApp?appId=20000125&orderSuffix=' . urlencode($res['msg']['xl_pay_data']);
                $order['pay_amount'] = $res['msg']['pay_amount'];
                
                $click_data = urlencode($res['msg']['xl_pay_data']);
                
                break;
            case '1029':
                //迅雷直播支付宝 用迅雷的userid
                $xl_res = $accutils->ylxlzczfbPayData($order, $findQrcode);
                if($xl_res['code'] != 200){
                    $this->error('获取失败，请刷新页面或者重新发起支付');
                }
                
                $pay_url    = $xl_res['msg']['xl_pay_data'];
                $click_data = $xl_res['msg']['xl_pay_data'];//跳官方h5再打开支付宝
                    
                break;
            case '1030':
                //uki支付宝
                $xl_res = $accutils->ukiZfbPayData($order, $findQrcode);
                if($xl_res['code'] != 200){
                    $this->error('获取失败，请刷新页面或者重新发起支付');
                }
                
                $pay_url    = $xl_res['msg']['xl_pay_data'];
                $click_data = $xl_res['msg']['xl_pay_data'];//跳官方h5再打开支付宝
                    
                break;
            case '1031':
                //淘宝直付
                $pay_url    = $findQrcode['pay_url'];
                $click_data = $findQrcode['pay_url'];
                $view = 'gateway/tbzf/browser';
                break;
            case '1032':
                //淘宝核销
                $pay_url    = $findQrcode['pay_url'];
                $click_data = $findQrcode['pay_url'];

                break;
            case '1033':
                //我秀
                /*$res = $accutils->woxiuPayData($order, $findQrcode);
                if($res['code'] != 200){
                    $this->error($res['msg']);
                }
                
                $pay_url    = $res['msg']['xl_pay_data'];
                
                $click_data = 'https://ds.alipay.com/?from=mobilecodec&scheme='.urlencode('alipays://platformapi/startapp?saId=10000007&clientVersion=3.7.0.0718&qrcode='.$pay_url);*/
                
                $res = $accutils->woxiuWxPayData($order, $findQrcode);
                if($res['code'] != 200){
                    $this->error($res['msg']);
                }
                
                $pay_url    = $res['msg']['xl_pay_data'];
                
                $click_data = $res['msg']['xl_pay_data'];
                
                $view = 'gateway/woxiu/wxbrowser';
                break;
            case '1034':
                //骏网智充卡
                $pay_url    = '';
                $click_data = '';
                $order['pay_amount'] = intval($order['pay_amount']);
                $view = 'gateway/jwzc/browser2';
                if(!empty($order['zfb_code']) || !empty($order['zfb_nickname'])){
                    echo '您的卡密已提交,请等待支付结果';die;
                }
                break;
            case '1035':
                //pdd代付
                $pay_url    = $order['hand_pay_data'];
                $click_data = $order['hand_pay_data'];
                break;
            case '1036':
                //数字人名币
                $pay_url   = $findQrcode['name']; //钱包姓名
                $remark    = $findQrcode['zfb_pid']; //钱包编号
                $click_data    = $findQrcode['zfb_pid']; 
                
                $view = 'gateway/szqb/browser2';
                break;
                
            case '1037':
                //骏网益享卡
                $pay_url    = '';
                $click_data = '';
                $order['pay_amount'] = intval($order['pay_amount']);
                $view = 'gateway/jwyx/browser';
                if(!empty($order['zfb_code']) || !empty($order['zfb_nickname'])){
                    echo '您的卡密已提交,请等待支付结果';die;
                }
                break;
                
            case '1038':
                //沃尔玛
                $pay_url    = '';
                $click_data = '';
                $order['pay_amount'] = intval($order['pay_amount']);
                $view = 'gateway/jwzc/woerma';
                if(!empty($order['zfb_code']) || !empty($order['zfb_nickname'])){
                    echo '您的卡密已提交,请等待支付结果';die;
                }
                break;
            case '1039':
                //沃尔玛
                $pay_url    = '';
                $click_data = '';
                $order['pay_amount'] = intval($order['pay_amount']);
                $view = 'gateway/jwzc/lefuhk';
                if(!empty($order['zfb_code']) || !empty($order['zfb_nickname'])){
                    echo '您的卡密已提交,请等待支付结果';die;
                }
                break;
                
            case '1040':
                //京东e卡
                $pay_url    = '';
                $click_data = '';
                $order['pay_amount'] = intval($order['pay_amount']);
                $view = 'gateway/jwzc/jdek';
                if(!empty($order['zfb_code']) || !empty($order['zfb_nickname'])){
                    echo '您的卡密已提交,请等待支付结果';die;
                }
                break;
            case '1066':
                //无忧
                $payInfo = $accutils->getWuYouPayData($order, $ip);
                if($payInfo['code'] != 200){
                    return '获取支付链接失败，请刷新页面或者重新拉起支付！！！';
                }
                
                /*if(strpos($_SERVER['HTTP_USER_AGENT'], 'Alipay') != false){
                    
                    $view = 'gateway/wuyou/browser';
                    
                }else{
                    $view = 'gateway/wuyou/browser';
                }*/
                
                //跳到支付宝打开自己的页面，请求组转加密方式 再跳
                $pay_url    = $order['pay_url'];
                $click_data = $payInfo['msg']['xl_pay_data'];
                
                break;
                
                
            case '1068':
                //微信直播
                $payInfo = $accutils->getWxPayData($order, $ip);
                if($payInfo['code'] != 200){
                    return '获取支付链接失败，请刷新页面或者重新拉起支付！！！';
                }
                
                $pay_url    = $order['pay_url'];
                $click_data = $payInfo['msg']['xl_pay_data'];
                //直接跳这个url
                Header("Location:$click_data");
                break;
                
            case '1045':
                //卡卡
                $pay_url = $findQrcode['pay_url'];
                
                $view = 'gateway/kaka/browser';
                break;
            case '1046':
                //咸鱼转账
                $pay_url = $findQrcode['pay_url'];
                
                $view = 'gateway/xianyu/browser';
                break;
            case '1047':
                //支付宝亲情卡
                $pay_url = $findQrcode['pay_url'];
                $pay_remark = $pay_url;
                $view = 'gateway/qinqingka/browser';
                break;
            case '1048':
                //支付宝AA收款
                //$pay_url = $findQrcode['pay_url'];
                $click_data = 'https://render.alipay.com/p/s/i?scheme='.urlencode('alipays://platformapi/startapp?saId=20000180&url=' . urlencode($findQrcode['pay_url']));
                $pay_url = $click_data;
                break;
            case '1050':
                
                if(strpos($_SERVER['HTTP_USER_AGENT'], 'Alipay') !== false){
                    
                    //支付宝主体模式 app支付
                    $res = $accutils->alipayAppPayData($order, $findQrcode);
                    
                    if($res['code'] != 200){
                        echo '发起支付错误，请刷新页面重试或重新拉单'; die;
                    }
                    
                    $inalipay_url = $res['msg']['hc_pay_data'];
                    $view = 'gateway/zfbapp/inalipayzs';
                }else{
                    
                    $view = 'gateway/zfbapp/browserzs';
                }
                
                //跳到支付宝打开自己的页面，请求组转加密方式 再跳
                //$click_data = 'alipays://platformapi/startApp?appId=20000067&url=' . urlencode($order['pay_url']);

                
                break;
            case '1051':

                //支付宝主体模式 电脑网站pc支付
                // $res = $accutils->alipayPcPayData($order, $findQrcode);

                // if($res['code'] != 200){
                //     echo '发起支付错误，请刷新页面重试或重新拉单'; die;
                // }

                // if(strpos($_SERVER['HTTP_USER_AGENT'], 'Alipay') !== false){
                //     $inalipay_url = $res['msg']['xl_pay_data'];
                //     $view = 'gateway/zfbpc/inalipay';
                // }else{
                //     $view = 'gateway/zfbpc/browser';
                // }
                
                // //跳到支付宝打开自己的页面，请求组转加密方式 再跳
                // $click_data = 'alipays://platformapi/startapp?appId=20000067&url=' . urlencode($order['pay_url']);
                
                // //$view = 'gateway/zfbpc/browser2';
                // //$click_data = $res['msg']['xl_pay_data'];
                
                
                if(strpos($_SERVER['HTTP_USER_AGENT'], 'Alipay') !== false){
                    $res = $accutils->alipayPcPayData($order, $findQrcode);

                    if($res['code'] != 200){
                        echo '发起支付错误，请刷新页面重试或重新拉单'; die;
                    }
                    
                    $inalipay_url = $res['msg']['xl_pay_data'];
                    $view = 'gateway/zfbpc/inalipayzs';
                }else{
                    $view = 'gateway/zfbpc/browserzs';
                }
                
                
                break;
            case '1052':
                //支付宝主体模式 当面付支付
                if(strpos($_SERVER['HTTP_USER_AGENT'], 'Alipay') !== false){

                    if(empty($order['zfb_user_id'])){
                        $pay_url = Utils::imagePath('/api/index/toAlipayV2?order_id='.$order['out_trade_no'].'&qid='.$findQrcode['id'], true);
                        header("Location:" . $pay_url);
                    }else{

                        //支付宝主体模式 当面付支付
                        $res = $accutils->alipayDmfPayData($order, $findQrcode);
                        
                        if($res['code'] != 200){
                            echo '发起支付错误，请刷新页面重试或重新拉单'; die;
                        }
                        
                        $inalipay_url = $res['msg']['xl_pay_data'];
                        $view = 'gateway/zfbdmf/inalipay';
                    }
                    
                }else{
                    $view = 'gateway/zfbdmf/browser';
                }
                
                //跳到支付宝打开自己的页面，请求组转加密方式 再跳
                $click_data = 'alipays://platformapi/startapp?appId=20000067&url=' . urlencode($order['pay_url']);
                
                break;
            case '1053':
                
                //支付宝主体模式 手机网站支付
                /*if(strpos($_SERVER['HTTP_USER_AGENT'], 'Alipay') !== false){
                    
                    $res = $accutils->alipayWapPayData($order, $findQrcode);
                    
                    $inalipay_url = $res['msg']['xl_pay_data'];
                    $view = 'gateway/zfbwap/inalipay';
                    
                }else{
                    $view = 'gateway/zfbwap/browser';
                }
                
                //跳到支付宝打开自己的页面，请求组转加密方式 再跳
                $click_data = 'alipays://platformapi/startapp?appId=20000067&url=' . urlencode($order['pay_url']);*/
                
                $res = $accutils->alipayWapPayData($order, $findQrcode);
                    
                $inalipay_url = $res['msg']['xl_pay_data'];
                $view = 'gateway/zfbwap/inalipay';
                
                break;
            case '1054':

                //支付宝主体模式 jsapi支付
                
                /*if(strpos($_SERVER['HTTP_USER_AGENT'], 'Alipay') !== false){
                    
                    
                    if(empty($order['zfb_user_id'])){
                        $pay_url = Utils::imagePath('/api/index/toAlipayV2?order_id='.$order['out_trade_no'].'&qid='.$findQrcode['id'], true);
                        header("Location:" . $pay_url);
                    }else{
                        
                        $res = $accutils->alipayJsApiPayData($order, $findQrcode);
                    
                        if($res['code'] != 200){
                            echo '发起支付错误，请刷新页面重试或重新拉单'; die;
                        }
                        
                        $inalipay_url = $res['msg']['xl_pay_data'];
                        $view = 'gateway/zfbxcx/inalipay';
                    }
                    
                    
                    
                }else{
                    $view = 'gateway/zfbxcx/browser';
                }*/

                //跳到支付宝小程序
                $click_data = 'https://ds.alipay.com/?scheme=' . urlencode('alipays://platformapi/startapp?appId=2021004109696102&page=pages/index/index');
                
                break;
            case '1055':

                //支付宝个码 资金周转主体授权模式
                $res = $accutils->alipayGmPayData($order, $findQrcode);
                
                /*if(strpos($_SERVER['HTTP_USER_AGENT'], 'Alipay') !== false){
                    
                    //$click_data = 'alipays://platformapi/startapp?appId=20000067&url='.urlencode('https://render.alipay.com/p/s/i?scheme='.urlencode('alipays://platformapi/startapp?saId=10000007&qrcode='.));
                    
                        $click_data = 'alipays://platformapi/startapp?saId=10000007&clientVersion=3.7.0.0718&qrcode='.urlencode($findQrcode['pay_url']);
                        
                        //$view = 'gateway/zfbgm/browser8';
                        $view = 'gateway/zfbgm/zfbgminalipay';
                    
                }else{
                    
                    //跳到支付宝打开自己的页面，请求组转加密方式 再跳
                    $click_data = 'alipays://platformapi/startapp?appId=20000067&url=' . urlencode($order['pay_url']);
                    
                    $view = 'gateway/zfbpc/browserzs';
                }*/
                
                $pay_url = $findQrcode['pay_url'];
                
                //$click_data = 'alipays://platformapi/startapp?appId=20000067&url='.urlencode('https://render.alipay.com/p/s/i?scheme='.urlencode('alipays://platformapi/startapp?saId=10000007&qrcode='.$findQrcode['pay_url']));
                
                //这个一般
                //$click_data = 'https://ds.alipay.com/?from=mobilecodec&scheme='. urlencode('alipays://platformapi/startapp?saId=10000007&clientVersion=3.7.0.0718&qrcode='. $findQrcode['pay_url']);
                
                $click_data = 'alipays://platformapi/startapp?appId=20000067&url=' . urlencode($pay_url);
                
                
                $view = 'gateway/zfbgm/browser8';
                
                
                
                break;
            case '1056':

                //支付宝个码 uid 主体授权模式
                $res = $accutils->alipayGmPayData($order, $findQrcode);
                
                
                //$pay_url = 'https://render.alipay.com/p/s/i?scheme='.urlencode('alipays://platformapi/startapp?appId=20000123&actionType=scan&u='.$findQrcode['zfb_pid'].'&a='.$order['pay_amount'].'&m='.$order['pay_remark'].'&biz_data={"s":"money","u":"'.$findQrcode['zfb_pid'].'","a":"'.$order['pay_amount'].'","m":"'.$order['pay_remark'].'"}');
                
                /*if(strpos($_SERVER['HTTP_USER_AGENT'], 'Alipay') !== false){
                    
                        //$click_data = 'alipays://platformapi/startapp?appId=09999988&actionType=toAccount&goBack=NO&amount=&userId='.$findQrcode['zfb_pid'];
                        
                        $click_data = 'https://render.alipay.com/p/s/i?scheme='.urlencode('alipays://platformapi/startapp?appId=20000123&actionType=scan&u='.$findQrcode['zfb_pid'].'&a='.$order['pay_amount'].'&m='.$order['pay_remark'].'&biz_data={"s":"money","u":"'.$findQrcode['zfb_pid'].'","a":"'.$order['pay_amount'].'","m":"'.$order['pay_remark'].'"}');
                        
                        $view = 'gateway/zfbgm/browser7';
                    
                }else{
                    
                    //跳到支付宝打开自己的页面，请求组转加密方式 再跳
                    $click_data = 'alipays://platformapi/startapp?appId=20000067&url=' . urlencode($order['pay_url']);
                    $view = 'gateway/zfbpc/browserzs';
                }*/
                
                
                //24.3.4注释
                //$click_data = 'https://m.alipay.com/?from=pc&appId=20000116&actionType=toAccount&sourceId=contactStage&chatUserId='.$findQrcode['zfb_pid'].'&displayName=TK&chatUserName=TK&chatLoginId=186******71&chatHeaderUrl=http://tfs.alipayobjects.com/images/partner/TB1OD00cMSJDuNj_160X160&chatUserType=1&skipAuth=true&amount='.$order['pay_amount'].'&memo='.$order['out_trade_no'];
                
                
                //2024 0304新增
                /*$click_data = 'alipayqr://platformapi/startapp?saId=10000007&qrcode=' . urlencode('https://ds.alipay.com/?from=pc&appId=20000116&actionType=toAccount&goBack=NO&amount='.$order['pay_amount'].'&userId='.$findQrcode['zfb_pid'].'&memo='.$order['out_trade_no']);
                
                
                $pay_url = 'https://ds.alipay.com/?from=pc&appId=20000116&actionType=toAccount&goBack=NO&amount='.$order['pay_amount'].'&userId='.$findQrcode['zfb_pid'].'&memo='.$order['out_trade_no'];
                
                $view = 'gateway/zfbgm/browser7';*/
                
                //2024 0324改成中额300-1000模式
                $click_data = 'alipays://platformapi/startapp?saId=20000989&url=' . urlencode($order['pay_url']);
                
                if(strpos($_SERVER['HTTP_USER_AGENT'], 'Alipay') != false){

                    $view = 'gateway/zfbgm/inalipay/zfbuid';
                    
                    //内部再点击发起支付
                    $inalipay_url = 'alipays://platformapi/startapp?appId=20000989&url='.urlencode('https://www.alipay.com/?appId=20000116&actionType=toAccount&sourceId=contactStage&chatUserId='.$findQrcode['zfb_pid'].'&displayName=TK&chatUserName=TK&chatLoginId=186******71&chatHeaderUrl=http://tfs.alipayobjects.com/images/partner/TB1OD00cMSJDuNj_160X160&chatUserType=1&skipAuth=true&amount='.$order['pay_amount'].'&memo='.$order['pay_remark']);//对应zfbuid页面
                    
                }else{
                    $view = 'gateway/zfbgm/browser8';
                }
                
                break;
            case '1057':

                //支付宝个码 链接 主体授权模式
                $res = $accutils->alipayGmPayData($order, $findQrcode);
                
                
                $pay_url = $findQrcode['pay_url'];
                
                //$click_data = 'alipays://platformapi/startapp?appId=20000067&url='.urlencode('https://render.alipay.com/p/s/i?scheme='.urlencode('alipays://platformapi/startapp?saId=10000007&qrcode='.$findQrcode['pay_url']));
                
                //$click_data = 'alipays://platformapi/startapp?appId=20000067&url=' . urlencode($pay_url);
                
                $click_data = $pay_url;
                
                /*if(strpos($_SERVER['HTTP_USER_AGENT'], 'Alipay') !== false){
                    //$click_data = 'https://render.alipay.com/p/s/i?scheme='.urlencode('alipays://platformapi/startapp?saId=10000007&qrcode='. $findQrcode['pay_url']);
                    
                    //$click_data = 'alipays://platformapi/startapp?appId=20000067&url=' . urlencode($pay_url);

                    $click_data = $pay_url;
                    $view = 'gateway/zfbgm/browser7';
                    
                }else{
                    
                    $pay_url = $order['pay_url'];
                    $view = 'gateway/zfbpc/browserzs';
                }*/
                
                $click_data = 'https://render.alipay.com/p/s/i/?scheme='. urlencode('alipays://platformapi/startapp?appId=20000067&url=' . urlencode($pay_url));
                
                break;
            case '1058':

                //支付宝批量转账 主体授权模式
                $res = $accutils->alipayGmPayData($order, $findQrcode);
                
                $remark     = $findQrcode['pay_url'];
                $pay_remark = $findQrcode['remark'];
                
                $pay_url = 'alipays://platformapi/startapp?appId=2019052465361241&page=%2Fpages%2Fopen%2Findex%2Findex%3FtargetUrl%3D%252Fpages%252FselectUser%252Fhome%252Findex%253FbackUrl%253D%25252Fpages%25252Ftransfer%25252Findex%25252Findex%2526showGroup%253Dtrue';
                
                $click_data = $pay_url;
                
                $view = 'gateway/zfbgm/plbrowser';
                
                break;
            case '1059':
                
                //极速报销模式
                $res = $accutils->alipayGmPayData($order, $findQrcode);
                
                $pay_url = $order['pay_url'];
                
                //$click_data = 'alipays://platformapi/startapp?appId=20000067&url='.urlencode('https://render.alipay.com/p/s/i?scheme='.urlencode('alipays://platformapi/startapp?saId=10000007&qrcode='.$findQrcode['pay_url']));
                $click_data = $findQrcode['pay_url'];
                
                $view = 'gateway/zfbgm/jsbx/browser';
                
                break;
            case '1060':
                //汇付支付宝
                /*for ($i = 0; $i<1; $i++){
                    $res = $accutils->hfPayData($order, $ip, $i);
                    if($res['code'] == 200){
                        break;
                    }
                    sleep(1);
                }*/
                
                $res = $accutils->hfPayData($order, $ip, 1);
                
                if($res['code'] != 200){
                    $this->error($res['msg']);
                }
                
                /*//方式1
                if(strpos($_SERVER['HTTP_USER_AGENT'], 'Alipay') !== false){
                    $inalipay_url = $res['msg']['xl_pay_data'];
                    $view = 'gateway/hfzfb/inalipay';
                }else{

                    $view = 'gateway/hfzfb/browser';
                }
                
                //跳到支付宝打开自己的页面，请求组转加密方式 再跳
                $click_data = 'alipays://platformapi/startapp?appId=20000067&url=' . urlencode($order['pay_url']);
                
                //用来生成二维码
                $pay_url = $click_data;
                */
                //用来生成二维码
                $pay_url = $res['msg']['xl_pay_data'];
                
                //点击跳转
                $click_data = $pay_url;
                
                ///$click_data = 'alipays://platformapi/startapp?appId=20000067&url=' . urlencode($pay_url);
                break;
            case '1061':
                
                //支付宝账单收款
                $res = $accutils->alipayGmPayData($order, $findQrcode);
                
                $remark = $order['zfb_nickname'];
                
                $click_data = 'alipays://platformapi/startapp?appId=20000167&forceRequest=0&returnAppId=recent&tLoginId='.$order['zfb_nickname'].'&tUnreadCount=0&tUserId=&tUserType=1';
                
                $pay_url = $click_data;
                
                break;
            case '1062':
                //支付宝个码链接 固定金额 主体授权模式 
                $res = $accutils->alipayGmPayData($order, $findQrcode);
                
                $pay_url = '';
                
                //根据金额取出对应的码
                $qrcode_list = json_decode($findQrcode['gd_alipay_json'], true);
                
                foreach ($qrcode_list as $value) {
                    if($value['amount'] == $order['amount']){
                        $pay_url = $value['pay_url'];
                        break;
                    }
                }
                
                if(empty($pay_url)){
                    echo "无支付渠道，请重试";die;
                }
                
                $click_data = $pay_url;
                
                $view = 'gateway/zfbgm/zfbsm';
                break;
            case '1063':
                
                //支付宝个码 收款名片 固定金额 主体授权模式
                $res = $accutils->alipayGmPayData($order, $findQrcode);
                
                $pay_url = $findQrcode['pay_url'];
                
                $click_data = $pay_url;
                
                break;
            case '1064':
                
                //支付宝收款账单 群收款 主体授权模式
                $res = $accutils->alipayGmPayData($order, $findQrcode);
                
                $pay_url = $findQrcode['pay_url'];
                
                $click_data = $pay_url;
                
                break;
            case '1065':
                
                //支付宝订单码主体授权模式
                $res = $accutils->alipayDdmPayData($order, $findQrcode);
                if($res['code'] != 200){
                    echo $res['msg'];die;
                }
                $pay_url      = $order['pay_url'];
                $inalipay_url = $res['msg']['xl_pay_data'];
                
                $click_data = 'https://render.alipay.com/p/s/i/?scheme='. urlencode('alipays://platformapi/startapp?appId=20000067&url=' . urlencode($res['msg']['xl_pay_data']));
                
                break;
            case '1066':
                
                //支付宝个码 大额 链接 主体授权模式
                $res = $accutils->alipayGmPayData($order, $findQrcode);

                $pay_url = $findQrcode['pay_url'];
                
                $click_data = $pay_url;
                $view = 'gateway/zfbgm/browser8';
                
                break;
            case '1067':
                //支付宝经营码  主体授权模式
                $res = $accutils->alipayGmPayData($order, $findQrcode);
                
                $pay_url = $findQrcode['pay_url'];
                
                $click_data = $pay_url;
                break;
            case '1080':
                //qq扫码
                $res = $accutils->qqscanpay($order, $findQrcode);
                
                $pay_url = $findQrcode['pay_url'];
                
                $click_data = $pay_url;
                
                $view = 'gateway/qq/browser';
                
                break;
            case '1081':
                //云闪付扫码
                $res = $accutils->qqscanpay($order, $findQrcode);
                
                $pay_url = $findQrcode['pay_url'];
                
                $click_data = $pay_url;
                
                $view = 'gateway/ysf/browser';
                
                break;
            case '1082':
                //手机网站 原生
                $res = $accutils->alipayWapPayDataYs($order, $findQrcode);
                    
                $inalipay_url = $res['msg']['xl_pay_data'];
                
                $view = 'gateway/zfbwap/inalipay';
                break;
            case '1083':
                //当面付 原生
                if(strpos($_SERVER['HTTP_USER_AGENT'], 'Alipay') != false){
                    
                    if(empty($order['zfb_user_id'])){
                        $pay_url = Utils::imagePath('/api/index/toAlipayV3?order_id='.$order['out_trade_no'].'&qid='.$findQrcode['id'], true);
                        header("Location:" . $pay_url);
                    }else{
                        
                        //支付宝主体模式 当面付支付
                        $res = $accutils->alipayDmfPayDataYs($order, $findQrcode);
                        
                        if($res['code'] != 200){
                            echo '发起支付错误，请刷新页面重试或重新拉单'; die;
                        }
                        
                        $inalipay_url = $res['msg']['xl_pay_data'];
                        $view = 'gateway/zfbdmf/inalipay';
                    }
                    
                }else{
                    $view = 'gateway/zfbdmf/browser';
                }
                
                //跳到支付宝打开自己的页面，请求组转加密方式 再跳
                //$click_data = 'alipays://platformapi/startapp?appId=20000067&url=' . urlencode($order['pay_url']);
                
                break;
            case '1084':
                //商付通 支付宝
                if(strpos($_SERVER['HTTP_USER_AGENT'], 'Alipay') != false){
                    $res = $accutils->shtAlipayPay($order, $findQrcode, $ip);

                    if($res['code'] != 200){
                        echo '发起支付错误，请刷新页面重试或重新拉单'; die;
                    }

                    $inalipay_url = $res['msg']['xl_pay_data'];
                    $view = 'gateway/sht/inalipay';

                }else{
                    $view = 'gateway/sht/browser';
                }

                //跳到支付宝打开自己的页面，请求组转加密方式 再跳
                $click_data = 'alipays://platformapi/startapp?appId=20000067&url=' . urlencode($order['pay_url']);

                break;
            default:
                $this->error('系统通道错误，请检查');
                break;
        }
        
        
        return view($view,[
            'name'          => $findQrcode['name'],
            'zfb_pid'       => $findQrcode['zfb_pid'],
            'amount'        => $order['amount'],
            'pay_amount'    => $order['pay_amount'],
            'trade_no'  => $order['trade_no'],
            'out_trade_no'  => $order['out_trade_no'],
            'click_data'    => $click_data,
            'payurl'        => $pay_url,
            'qrcode_url'    => Utils::imagePath($findQrcode['image'], true),
            'inalipay_url'  => $inalipay_url,
            'time'          => $order['expire_time'] - time(),
            'remark'        => $remark,
            'pay_remark'    => $pay_remark,
            'expire_time'   => date('Y-m-d H:i:s',$order['expire_time']),
            'create_time'   => date('Y-m-d H:i:s',$order['createtime']),
        ]);


    }
    
    
    
    //跳转到新生支付页面
    public function toHnapay($order_sn){
        if (empty($order_sn)) {
            $this->error('参数错误');
        }
        $order = Db::name('order')->where(['out_trade_no'=>$order_sn])->find();
        $view  = 'gateway/xinszfb/inalipay';
        if(empty($order['xl_pay_data'])){
            return '订单错误，请重新发起支付';
        }
        
        return view($view,[
            'remark' => json_decode($order['xl_pay_data'], true)
        ]);
    }
    
    
    //内部跳转发起支付
    public function jumptopay(){
        
        $order_sn  = $this->request->get('order_sn');//单号
        
        $order = Db::name('order')->where(['out_trade_no'=>$order_sn])->find();

        $findQrcode = Db::name('group_qrcode')->where(['id'=>$order['qrcode_id']])->find();


        $inalipay_url = 'alipays://platformapi/startapp?appId=20000123&actionType=scan&biz_data='. urlencode('{"s":"money","u":"'.$findQrcode['zfb_pid'].'","a":"'.$order['pay_amount'].'","m":"'.$order['pay_remark'].'"}');
        
        //alipays://platformapi/startapp?appId=20000123&actionType=scan&biz_data=%7B%22s%22:%20%22money%22,%22u%22:%20%222088342670307844%22,%22a%22:%20%2299.77%22,%22m%22:%22DY2022101817445611555047%22%7D
        Header("Location:$inalipay_url");
    }
    
    
    //前端轮询查单
    public function checkOrder(){

        $out_trade_no = $this->request->param('tradeNo');//订单号

        $order = Db::name('order')->where(['out_trade_no'=>$out_trade_no])->field('id,out_trade_no,trade_no,status,expire_time')->whereDay('createtime','today')->find();

        if(empty($order)){
            $this->success('订单不存在',['status'=>5]);
        }

        /*//采用缓存策略
        $key = 'user_id:' . $order['user_id'] . ':' . $out_trade_no;
        if (Cache::get($key)){
            Cache::inc($key, 1);
        }else{
            Cache::set($key, 1, 630);
        }*/

        Order::where('id', $order['id'])->inc('request_num')->limit(1)->update();

        if(time() > $order['expire_time']){
            $this->success('订单过期',['status'=>3]);
        }
        if($order['status'] == 1){
            $this->success('支付完成',['status'=>1]);
        }
        if($order['status'] == 2){
            $pay_url = '';
            $this->success('等待支付',['status'=>2,'pay_url'=>$pay_url]);
        }
        if($order['status'] == 3){
            $this->success('支付失败',['status'=>3]);
        }
    }


    //查单
    public function queryOrder(){
        $mer_no     = $this->request->post('mer_no');
        $sign       = $this->request->post('sign');
        $trade_no   = $this->request->post('trade_no');
        $wait_sign  = $this->request->post();

        Log::write('queryOrder----'.request()->ip().'----'.json_encode($_POST,JSON_UNESCAPED_UNICODE),'info');
 
        if(empty($mer_no) || empty($sign) || empty($trade_no)){
            $this->error('参数缺少');
        }

        $findmerchant = Db::name('merchant')->where(['number'=>$mer_no])->find();

        $mysign = Utils::sign($wait_sign,$findmerchant['secret_key']);

        if($mysign != $sign){
            $this->error('签名错误!');
        }

        //找出订单
        $order = Db::name('order')->where(['trade_no'=>$trade_no])->field('status,amount,trade_no,out_trade_no,createtime,ordertime')->find();

        if(!$order){
            $this->error('订单不存在');
        }

        $this->success('success',$order);

    }


    //支付宝口令红包 页面提交口令
    public function subcode(){

        $out_trade_no = $this->request->post('tradeNo');//订单号
        $zfb_code     = trim($this->request->post('code'));//口令
        Log::write('subcode----'.request()->ip().'----'.json_encode($_POST,JSON_UNESCAPED_UNICODE),'info');

        if(!is_numeric($zfb_code) || (strlen($zfb_code) != 8)){
            $this->error('请输入正确口令');
        }

        $order = Db::name('order')->where(['out_trade_no'=>$out_trade_no])->find();

        if(empty($order)){
            $this->error('订单不存在');
        }

        if(strlen($order['zfb_code']) > 1){
            $this->error("该口令已提交，请等待支付结果");
        }

        //判断该口令是否存在
        $findcode = Db::name('order')->where(['zfb_code'=>$zfb_code])->find();
        if ($findcode) {
            $this->error("该口令已经存在，请检查红包口令");
        }

        $result = Db::name('order')->where(['out_trade_no'=>$out_trade_no])->update([
            'zfb_code'=> $zfb_code,
        ]);

        if(!$result){
            $this->error("错误，请重试");
        }

        $this->success("提交成功，请等待支付结果");
    }


    //支付宝口令红包 脚本获取口令
    public function getCode(){
        $user_number = $this->request->post('user_number');//订单号
        $user = Db::name('user')->where(['number'=>$user_number])->find();
        if (empty($user)) {
            $this->error('用户不存在');
        }

        $order = Db::name('order')->where(['user_id'=>$user['id'],'status'=>2,'pay_type'=>'1004'])->where('zfb_code','<>','')->order('id','asc')->find();
        if(empty($order)){
            $this->error('暂无订单');
        }

        $this->success('获取成功',['out_trade_no'=>$order['out_trade_no'],'amount'=>$order['amount'],'code'=>$order['zfb_code']]);
    }
    
    //支付宝房租-提交昵称
    public function subnickname(){

        $out_trade_no = $this->request->post('order_sn');//订单号
        $zfb_nickname     = trim($this->request->post('nickname'));
        Log::write('subcode----'.date('Y-m-d H:i:s').'----'.request()->ip().'----'.json_encode($_POST,JSON_UNESCAPED_UNICODE),'info');

        if(empty($zfb_nickname) || empty($out_trade_no)){
            $this->error('请输入正确昵称');
        }

        $order = Db::name('order')->where(['out_trade_no'=>$out_trade_no,'status'=>2])->find();

        if(empty($order)){
            $this->error('订单不存在');
        }

        if($order['zfb_nickname']){
            $this->error("该订单支付宝名称已提交，请继续支付");
        }

    
        $result = Db::name('order')->where(['out_trade_no'=>$out_trade_no,'status'=>2])->update([
            'zfb_nickname'=> $zfb_nickname,
        ]);

        if(!$result){
            $this->error("错误，请重试");
        }

        $this->success("提交成功，请继续支付");
    }
    
    //个码提交支付名字
    public function setName(){
        $out_trade_no  = $this->request->post('tradeNo');//订单号
        $pay_user_name = trim($this->request->post('name'));//名字

        Log::write('名字----'.request()->ip().'----'.json_encode($_POST,JSON_UNESCAPED_UNICODE),'info');

        if(empty($out_trade_no) || empty($pay_user_name)){
            $this->error('请输入正确名字');
        }

        $order = Db::name('order')->where(['out_trade_no'=>$out_trade_no,'status'=>2])->find();

        if(empty($order)){
            $this->error('订单不存在');
        }

        $result = Db::name('order')->where(['out_trade_no'=>$out_trade_no,'status'=>2])->update([
            'zfb_nickname'=> $pay_user_name,
        ]);
        
        /*if(!$result){
            $this->error("错误，请重试");
        }*/

        $this->success("提交成功，请继续支付");
    }

    //支付宝收款页面，提交账号
    public function subAcount(){
        
        $out_trade_no = $this->request->post('tradeNo');//订单号
        $zfb_nickname = trim($this->request->post('name'));//会员填的账号/口令
        
        if(empty($out_trade_no) || empty($zfb_nickname)){
            $this->error('参数缺少');
        }
        
        Log::write('subAcount----'.request()->ip().'----'.json_encode($_POST,JSON_UNESCAPED_UNICODE),'info');
        
        // 手机号的正则表达式，这里默认为中国的手机号
        $mobilePattern = "/^1[3456789]\d{9}$/";
        
        // 邮箱的正则表达式
        $emailPattern = "/^[a-zA-Z0-9_-]+@[a-zA-Z0-9_-]+(\.[a-zA-Z0-9_-]+)+$/";
        $t1 = preg_match($mobilePattern, $zfb_nickname);
        $t2 = preg_match($emailPattern, $zfb_nickname);
        
        if (!preg_match($mobilePattern, $zfb_nickname) && !preg_match($emailPattern, $zfb_nickname)){
            $this->error('支付宝账号格式不正确');
        }
        
        
        $order = Db::name('order')->where(['out_trade_no'=>$out_trade_no])->find();

        if(empty($order)){
            $this->error('订单不存在，请刷新页面重新提交');
        }

        /*if(strlen($order['zfb_nickname']) > 1){
            $this->error("该订单账号已提交，请等待付款通知");
        }*/
        if($order['zfb_nickname'] == $zfb_nickname){
            $this->success("提交成功，请继续支付");
        }
        
        
        $result = Db::name('order')->where(['out_trade_no'=>$out_trade_no])->update([
            'zfb_nickname' => $zfb_nickname,
            'sub_time'     => time(),
        ]);
        
        if(!$result){
            $this->error("提交错误，请重试");
        }
        
        if($order['pay_type'] == '1061'){
            
            $click_data = 'alipays://platformapi/startapp?appId=20000167&forceRequest=0&returnAppId=recent&tLoginId='.$zfb_nickname.'&tUnreadCount=0&tUserId='.$order['zfb_user_id'].'=&tUserType=1';
            
            $this->success("提交成功", $click_data);
        }
        
        $this->success("提交成功");
    }
    
    //淘宝核销 页面提交核销码
    public function subTbCode(){

        $out_trade_no = $this->request->post('tradeNo');//订单号
        $zfb_code     = trim($this->request->post('code'));//核销码
        
        Log::write('subcode----'.request()->ip().'----'.json_encode($_POST,JSON_UNESCAPED_UNICODE),'info');
        
        $zfb_code = str_replace(' ', '', $zfb_code);

        if(!is_numeric($zfb_code)){
            $this->error('请输入正确核销码');
        }
        
        if(strlen($zfb_code) != 10){
            $this->error('核销码是10位的数字哦！');
        }
        
        $order = Db::name('order')->where(['out_trade_no'=>$out_trade_no])->find();

        if(empty($order)){
            $this->error('订单不存在');
        }

        if(strlen($order['zfb_code']) > 1){
            $this->error("该核销码已提交，请等待支付结果");
        }

        //判断该口令是否存在
        $findcode = Db::name('order')->where(['zfb_code'=>$zfb_code])->find();
        if ($findcode) {
            $this->error("该核销码已经存在，请核实");
        }

        $result = Db::name('order')->where(['out_trade_no'=>$out_trade_no])->update([
            'zfb_code'=> $zfb_code,
        ]);

        if(!$result){
            $this->error("错误，请重试");
        }

        $this->success("提交成功，请等待支付结果");
    }
    
    //骏网智充卡 提交卡密
    public function subJwCard(){

        $out_trade_no = $this->request->post('tradeNo');//订单号
        $cardno       = trim($this->request->post('cardno'));//卡号
        $cardpwd      = trim($this->request->post('cardpwd'));//密码
        
        Log::write('subJwCard----'.request()->ip().'----'.json_encode($_POST,JSON_UNESCAPED_UNICODE),'info');
        
        Db::startTrans();

        try {
            
            $cardno = str_replace(' ', '', $cardno);
            
            if(empty($cardno) || empty($cardpwd)){
                $this->error('请输入卡号密码');
            }
            
            /*if(!is_numeric($cardno)){
                $this->error('请输入正确卡号，卡号是16位的');
            }
            
            if(strlen($cardno) != 16 || strlen($cardpwd) != 16){
                $this->error('卡号卡密是16位的！');
            }*/
            
            if($cardno == $cardpwd){
                $this->error('卡号和卡密不能相同！');
            }
            
            $order = Order::where(['out_trade_no'=>$out_trade_no])->find();
    
            if(empty($order)){
                $this->error('订单不存在');
            }
            
            $user = User::find($order['user_id']);
            //判断这个码商是否开启卡密重复提交 0=关闭,1=开启
            if ($user['is_repeat'] == '0'){
                if(strlen($order['zfb_code']) > 1 || strlen($order['zfb_nickname']) > 1){
                    $this->error("卡密已经提交，请等待支付结果");
                }
        
                //判断该口令是否存在
                $findCard = Order::where(['zfb_code'=>$cardno])->find();
                if ($findCard) {
                    $this->error("该卡号已经存在，请核实");
                }
                $findCardPwd = Order::where(['zfb_nickname'=>$cardpwd])->find();
                if ($findCardPwd) {
                    $this->error("该卡号已经存在，请核实");
                }
            }
            
            
            $result = Order::where(['out_trade_no'=>$out_trade_no])->update([
                'zfb_code'     => $cardno,
                'zfb_nickname' => $cardpwd,
            ]);
    
            if(!$result){
                $this->error("错误，请重试");
            }
    
            //判断这个码商是否开启自动提交三方核销
            $user = User::find($order['user_id']);
            if ($user['is_third_hx'] == 1){
                ThirdHx::instance()->checkXkType($out_trade_no, $order['amount'], $cardno, $cardpwd, $order);
            }
            
            Db::commit();
            
        }catch (Exception $e) {
            Db::rollback();
            Log::write('subJwCard----'.$out_trade_no.'----'.$e->getFile().'----'. $e->getLine() .'----' .$e->getMessage(),'info');
            $this->error('提交失败，请联系客服');
        }

        $this->success("提交成功，请等待支付结果");
    }
    
    //京东E卡 提交卡密
    public function subJdCard(){

        $out_trade_no = $this->request->post('tradeNo');//订单号
        //$cardno       = trim($this->request->post('cardno'));//卡号
        $cardpwd      = trim($this->request->post('cardpwd'));//密码
        
        Log::write('subJdCard----'.request()->ip().'----'.json_encode($_POST,JSON_UNESCAPED_UNICODE),'info');
        
        Db::startTrans();

        try {
            
            $cardpwd = str_replace(' ', '', $cardpwd);
            
            if(empty($cardpwd)){
                $this->error('请输入京东卡密');
            }
            
            $order = Order::where(['out_trade_no'=>$out_trade_no])->find();
    
            if(empty($order)){
                $this->error('订单不存在');
            }
            
            $user = User::find($order['user_id']);
            //判断这个码商是否开启卡密重复提交 0=关闭,1=开启
            if ($user['is_repeat'] == '0'){
                if(strlen($order['zfb_nickname']) > 1){
                    $this->error("卡密已经提交，请等待支付结果");
                }
        
                //判断该卡密是否存在
                $findCardPwd = Order::where(['zfb_nickname'=>$cardpwd])->find();
                if ($findCardPwd) {
                    $this->error("该卡密已经存在，请核实");
                }
            }
            
            $result = Order::where(['out_trade_no'=>$out_trade_no])->update([
                'zfb_nickname' => $cardpwd,
            ]);
    
            if(!$result){
                $this->error("提交失败，请重试");
            }
    
            //判断这个码商是否开启自动提交三方核销
            if ($user['is_third_hx'] == 1){
                ThirdHx::instance()->checkXkType($out_trade_no, $order['amount'], 'JD111111', $cardpwd, $order);
            }
            
            Db::commit();
            
        }catch (Exception $e) {
            Db::rollback();
            Log::write('subJdCard----'.$out_trade_no.'----'.$e->getFile().'----'. $e->getLine() .'----' .$e->getMessage(),'info');
            $this->error('提交失败，请联系客服');
        }

        $this->success("提交成功，请等待支付结果");
    }
    
    //杭州市民卡永辉上报
    public function smkReport(){
        
        $out_trade_no = $this->request->post('out_trade_no');//系统订单号
        $alipay_url  = trim($this->request->post('alipay_url'));//支付宝码
        $device_code = trim($this->request->post('device_code'));//设备码
        $phone       = trim($this->request->post('phone'));//挂码手机号
        
        Log::write('smkReport----'.request()->ip().'----'.json_encode($_POST,JSON_UNESCAPED_UNICODE),'info');
        Utils::notifyLog($out_trade_no, $out_trade_no, json_encode($_POST,JSON_UNESCAPED_UNICODE));
        
        $order = Db::name('order')->where(['out_trade_no'=>$out_trade_no])->find();
        if(empty($order)){
            $this->error('订单不存在');
        }
        
        if(strlen($order['xl_pay_data']) > 1){
            $this->error("该单号已提交");
        }
        
        //TODO
        
    }
    
    //获取账单订单
    public function getZdOrder(){
        $android_key = $this->request->get('android_key');
        
        if(empty($android_key)){
            $this->error('参数缺少');
        }
        
        $group_qrcode = Db::name("group_qrcode")->where("android_key",$android_key)->find();
        if(empty($group_qrcode)){
            $this->error('key错误');
        }
        
        /*if($group_qrcode['status'] == 0){
            Db::name("group_qrcode")->where("id",$group_qrcode['id'])->update(['status'=>1]);
        }*/
        
        $file = fopen('zdlock.txt', 'w+');
        
        if ($file === false) {
            Log::write('文件锁异常----'. $android_key,'timerError');
        }
        
        try {
            
            
            if (flock($file, LOCK_EX)) {
                
                //1061个人账单
                $order = Db::name('order')
                ->where(['status' => 2, 'qrcode_id'=>$group_qrcode['id'], 'is_gmm_close'=>0])
                ->where('zfb_nickname', '<>', '')
                ->where('pay_type' , '1061')
                ->whereDay('createtime','today')
                ->order('id','asc')
                ->field('id, trade_no, pay_amount, zfb_nickname as zfb_account')
                ->find();
                
                if(!empty($order)){
                    $real_order = $order;
                }else{
                    //1064群账单
                    $order = Db::name('order')
                    ->where(['status' => 2, 'qrcode_id'=>$group_qrcode['id'], 'is_gmm_close'=>0])
                    ->where('pay_type', '1064')
                    ->whereDay('createtime','today')
                    ->order('id','asc')
                    ->field('id, trade_no, pay_amount, zfb_nickname as zfb_account, pay_remark')
                    ->find();
                    
                    if(!empty($order)){
                        $order['trade_no'] = $order['pay_remark'];
                        unset($order['pay_remark']);
                    }
                    
                    $real_order = $order;
                }
                
                
                if(empty($real_order)){
                    $this->error('暂无订单');
                }
                
                Log::write('获取到账单----'.request()->ip().'----'.$order['trade_no'],'timerError');
                
                //更改为推送给安卓了
                Db::name('order')->where('id', $order['id'])->update([
                    'is_gmm_close' => 1,
                ]);
                
                $order['android_key'] = $android_key;
                
                $this->success('获取成功', $order);
                
            }else{
                Log::write('获取锁失败');
                
                $this->error('账单异常');
            }
            
            
        } catch (Exception $e) {
           
            Log::write('账单异常----'.$android_key.'----'. $e->getLine() . '----'.$e->getMessage(),'timerError');
            
            $this->error('账单异常');
            
        } finally {
            
            //无论前面的代码是否正常执行，这里的代码总是会被执行
            // 释放锁定
            if ($file) {
                flock($file, LOCK_UN);
                // 关闭文件
                fclose($file);
            }
        }
        
    }
    
    
    //获取群账单收款订单
    public function getAaOrder(){
        $android_key = $this->request->get('android_key');
        
        if(empty($android_key)){
            $this->error('参数缺少');
        }
        
        $group_qrcode = Db::name("group_qrcode")->where("android_key",$android_key)->find();
        if(empty($group_qrcode)){
            $this->error('key错误');
        }
        
        /*if($group_qrcode['status'] == 0){
            Db::name("group_qrcode")->where("id",$group_qrcode['id'])->update(['status'=>1]);
        }*/
        
        $file = fopen('aalock.txt', 'w+');
        
        if ($file === false) {
            Log::write('文件锁异常----'.$android_key ,'timerError');
        }
        
        try {
            
            
            if (flock($file, LOCK_EX)) {
                
                $order = Db::name('order')
                ->where(['status' => 2, 'qrcode_id'=>$group_qrcode['id']])
                ->where('pay_type' , '1064')
                ->whereDay('createtime','today')
                ->order('id','asc')
                ->field('id, trade_no, pay_amount')
                ->find();
                
                
                if(empty($order)){
                    $this->error('暂无订单');
                }
                
                Log::write('获取到群账单----'.request()->ip().'----'.$order['trade_no'],'timerError');
                
                //更改为推送给安卓了
                Db::name('order')->where('id', $order['id'])->update([
                    'is_gmm_close' => 1,
                ]);
                
                $this->success('获取成功', $order);
                
            }else{
                Log::write('获取锁失败');
                
                $this->error('aa异常');
            }
            
            
        } catch (Exception $e) {
           
            Log::write('群账单异常----'.$android_key.'----'. $e->getLine() . '----'.$e->getMessage(),'timerError');
            
            $this->error('群账单异常');
            
        } finally {
            
            //无论前面的代码是否正常执行，这里的代码总是会被执行
            // 释放锁定
            if ($file) {
                flock($file, LOCK_UN);
                // 关闭文件
                fclose($file);
            }
        }
        
    }
    
    public function unlockFile($file){
        //执行完成解锁
        flock($file,LOCK_UN);
        //关闭文件
        fclose($file);
    }
}