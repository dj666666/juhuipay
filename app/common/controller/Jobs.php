<?php
/**
 * @description 消费者
 * @author Sean
 * @version v1.0.0
 * @Date 2021/6/17
 * @Time 10:34
 */

namespace app\common\controller;

use app\admin\model\order\Order;
use think\facade\Db;
use think\facade\Log;
use think\queue\Job;
use think\facade\Queue;
use think\facade\Cache;
use think\facade\Config;
use app\common\library\Wxpush;
use app\common\library\Alipay;
use app\common\library\Notify;
use app\common\library\Utils;
use app\common\library\Accutils;
use app\common\library\MoneyLog;
use app\common\library\CheckOrderUtils;
use fast\Http;

class Jobs
{
    public function fire(Job $job,$data){

        $isJobDone = $this->doRequestJob($data);

        // 如果任务执行成功后 记得删除任务，不然这个任务会重复执行，直到达到最大重试次数后失败后，执行failed方法
        if ($isJobDone) {
            $job->delete();
        } else {
            //通过这个方法可以检查这个任务已经重试了几次了
            $attempts = $job->attempts();
            echo $attempts;

            /*if ($attempts == 0 || $attempts == 1) {
                // 重新发布这个任务
                $job->release(15); //$delay为延迟时间，延迟2S后继续执行
            } elseif ($attempts == 2) {
                $job->release(5); // 延迟5S后继续执行
            }*/

            if ($attempts >= 1 && $attempts < 5) {

                $delay = 10;
                $remark = '超时-执行失败-重新发布任务'.$delay.'秒后继续执行';
                Utils::queueLog($data['trade_no'],$data['out_trade_no'],$remark);

                // 重新发布这个任务
                $job->release($delay); //$delay为延迟时间，延迟$delayS后继续执行
            }else{

                $remark = '超时-执行失败-达到最大重试次数删除任务';
                Utils::queueLog($data['trade_no'],$data['out_trade_no'],$remark);

                $job->delete();
            }
        }


    }

    /**
     * @Desc: 任务执行失败后自动执行方法
     * @param $data
     */
    public function failed($data)
    {
        // ...任务达到最大重试次数后，失败了
        Log::error('任务达到最大重试次数后，失败了 '.json_encode($data));
    }


    /**
     * 中间方法，调度执行
     */
    private function doRequestJob($data)
    {
        switch ($data['request_type']) {
            case 1:
                $bool = $this->testJob($data);
                break;
            case 2:
                $bool = $this->changeMoney($data);
                break;
            case 3:
                $bool = $this->sendNOtify($data);
                break;
            case 4:
                $bool = $this->checkOrderTime($data);
                break;
            case 5:
                $bool = $this->checkxlzbTimeOutOrder($data);
                break;
            case 6:
                $bool = $this->gmmCloseOrder($data);
                break;
            case 7:
                $bool = $this->delTestOrder($data);
                break;
            case 8:
                $bool = $this->checkDdmOrder($data);
                break;
            default:
                $bool = true;
                break;
        }
        return $bool;
    }


    /**
     * @Desc: 自定义需要加入的队列任务
     */
    private function testJob($data)
    {
        $jsonData = json_encode($data);

        if($data){
            //收到返回的回调 写入回调日志
            $callbacklog=[
                'order_id'=>'11111',
                'data'=>'11111',
                'create_time'=>date('Y-m-d H:i:s',time()),
                'createtime'=>time(),
            ];
            $re = Db::name('callback_log')->insert($callbacklog);

            return true;
        }else{
            return false;
        }
    }

    /**
     * 修改余额
     * @param $data
     * @return bool
     */
    private function changeMoney($data){

        $findmerchant = Db::name('merchant')->where(['id'=>$data['mer_id']])->find();

        $new_money = bcsub($findmerchant['money'],bcadd($data['amount'],$data['fees'],2),2);
        $result = Db::name('merchant')
            ->where(['id'=>$findmerchant['id'],'last_money_time'=>$findmerchant['last_money_time']])
            ->update(['money'=>$new_money,'last_money_time'=>time()]);

        //订单金额
        $logData['agent_id'] = $findmerchant['agent_id'];
        $logData['mer_id'] = $findmerchant['id'];
        $logData['out_trade_no'] = $data['out_trade_no'];
        $logData['amount'] = $data['amount'];
        $logData['before_amount'] = $findmerchant['money'];
        $after = bcsub($findmerchant['money'],$data['amount'],2);
        $logData['after_amount'] = $after;
        $logData['type'] = 0;
        $logData['create_time'] = time();
        $logData['remark'] = 'api提单扣款';
        $logData['ip_address'] = $data['ip'];


        //手续费
        $logData2['agent_id'] = $findmerchant['agent_id'];
        $logData2['mer_id'] = $findmerchant['id'];
        $logData2['out_trade_no'] = $data['out_trade_no'];
        $logData2['amount'] = $data['fees'];
        $logData2['before_amount'] = $after;
        $logData2['after_amount'] = $new_money;
        $logData2['type'] = 0;
        $logData2['create_time'] = time();
        $logData2['remark'] = '手续费扣款';
        $logData2['ip_address'] = $data['ip'];

        $insertdata = [$logData,$logData2];

        $result2 = Db::name('money_log')->insertAll($insertdata);

        if ($result && $result2){
            return true;
        }
        return false;
    }

    /**
     * 异步发送回调通知
     *
     * @param $data
     * @return bool
     */
    private function sendNOtify($data){


        $order = Db::name('order')->where(['id'=>$data['order_id'],'status'=>1,'is_callback'=>2])->find();
        if (!$order) {

            $remark = '回调-执行失败-订单不存在'.$order['callback_count'];
            Utils::queueLog($data['trade_no'],$data['out_trade_no'],$remark);

            return true;
        }

        //开始回调
        $callback = new Notify();

        $callbackre = $callback->sendCallBack($order['id'],1,$order['ordertime']);


        //发送失败 隔一会再发送
        /*if($callbackre['code'] != 1){

            //回调次数+1
            Db::name('order')->where('id',$order['id'])->inc('callback_count');
            $deal_count = $order['callback_count']+1;

            //判断回调次数 超过3次 则不再发送
            if($deal_count == 4){//走到这说明是发了三次了 将不再执行

                $remark = '回调-执行成功-通知失败：'.$callbackre['content'].'，已执行'.$order['callback_count'].'次不再执行';
                Utils::queueLog($data['trade_no'],$data['out_trade_no'],$remark);

                return true;
            }

            // 当前任务所需的业务数据，不能为 resource 类型，其他类型最终将转化为json形式的字符串
            $queueData = [
                'request_type'  => 3,
                'order_id'      => $order['id'],
            ];

            //当前任务归属的队列名称，如果为新队列，会自动创建
            $queueName = 'checkorder';
            if($order['callback_count'] == 1){
                $delay = 15;
            }elseif($order['callback_count'] == 2){
                $delay = 30;
            }else{
                $delay = 40;
            }


            // 将该任务推送到消息队列，等待对应的消费者去执行
            //$isPushed = Queue::push(Jobs::class, $data, $queueName);//立即执行
            $isPushed = Queue::later($delay,Jobs::class, $queueData, $queueName);//延迟$delay秒后执行

            $remark = '回调-执行成功-通知失败：'.$callbackre['content'].'，等待'.$delay.'秒再次准备执行';
            Utils::queueLog($data['trade_no'],$data['out_trade_no'],$remark);

            return true;
        }*/


        $updata = [
            'is_callback'       => 1,
            'callback_count'    => $order['callback_count']+1,
            'callback_time'     => time(),
            'callback_content'  => $callbackre['content'],
            //'deal_username'     => '系统',
            'remark'            => empty($order['remark']) ? '自动回调' : '',
        ];


        $result1 = Db::name('order')->where(['id'=>$order['id']])->update($updata);

        if($result1){

            $remark = '通知-执行成功-回调成功：'.$callbackre['content'];
            Utils::queueLog($data['trade_no'],$data['out_trade_no'],$remark);

        }

        return true;
    }

    /**
     * 检测订单超时
     *
     * @param $data
     * @return bool
     */
    private function checkOrderTime($data){

        $order = Db::name('order')->where(['id'=>$data['order_id']])->find();
        if(empty($order)){
            return true; 
        }
        $now_time    = time();
        $update_data = [];

        //获取用户ip归属地
        /*if (!empty($order['user_ip_address'])) {
            $address = Utils::getClientAddress($order['user_ip_address']);
            $update_data = ['user_ip_from' => $address];
        }*/

        /*$key = 'user_id:' . $order['user_id'] . ':' . $order['out_trade_no'];
        $value = Cache::get($key);
        if ($value){
            $update_data = ['request_num' => $value];
        }*/

        if($update_data){
            Order::where(['id'=>$order['id']])->update($update_data);
        }

        if($now_time < $order['expire_time']){
            $remark = '超时-执行失败-订单未超时';
            Utils::queueLog($data['trade_no'],$data['out_trade_no'],$remark);
            return false;
        }

        if($order['status'] != 2){

            if ($order['status'] == 1) {
                $remark = '超时-执行失败-订单已支付';
            }else{
                $remark = '超时-执行失败-订单已失败';
            }

            Utils::queueLog($data['trade_no'],$data['out_trade_no'],$remark);

            return true;
        }

        $updata = [
            'status'    => Order::STATUS_FAIL,
            'ordertime' => $now_time,
            'remark'    => empty($order['remark']) ? '超时未付' : $order['remark'] .'-超时未付',
        ];

        $result1 = Order::where(['id'=>$order['id']])->update($updata);

        if($result1){
            
            //订单超时余额退还 user_sub_order_rate = 0 提单先扣 1 订单完成扣
            if (Config::get('site.user_sub_order_rate') == '0' && Config::get('site.user_rate') == '2') {
                //码商余额退回
                MoneyLog::userMoneyChange($order['user_id'], $order['amount'], 0, $order['trade_no'], $order['out_trade_no'], '未付退款', 1, 0);
            }
            if (Config::get('site.mer_sub_order_rate') == '0' && Config::get('site.merchant_rate' == '2')) {
                //商户余额退回 
                MoneyLog::merchantMoneyChange($order['mer_id'], $order['amount'], 0, $order['trade_no'], $order['out_trade_no'], '未付退款', 1, 0);
            }
            
            //迅雷订单超时加入二次查单队列
            if($order['pay_type'] == '1014'){
                $delay = Config::get('site.xl_check_order_time');
                $queueData = [
                    'request_type'  => 5,
                    'order_id'      => $order['id'],
                    'out_trade_no'  => $order['out_trade_no'],
                    'trade_no'      => $order['trade_no'],
                ];
    
                //当前任务归属的队列名称，如果为新队列，会自动创建
                $queueName = 'checkorder';
                // 将该任务推送到消息队列，等待对应的消费者去执行
                //$isPushed = Queue::push(Jobs::class, $data, $queueName);//立即执行
                $isPushed = Queue::later($delay,Jobs::class, $queueData, $queueName);//延迟$delay秒后执行
            }
            
            //订单码加入二次查单队列
            if($order['pay_type'] == '1065'){
                $delay = Config::get('site.xl_check_order_time');
                $queueData = [
                    'request_type'  => 8,
                    'order_id'      => $order['id'],
                    'out_trade_no'  => $order['out_trade_no'],
                    'trade_no'      => $order['trade_no'],
                ];
    
                //当前任务归属的队列名称，如果为新队列，会自动创建
                $queueName = 'checkorder';
                // 将该任务推送到消息队列，等待对应的消费者去执行
                //$isPushed = Queue::push(Jobs::class, $data, $queueName);//立即执行
                $isPushed = Queue::later($delay,Jobs::class, $queueData, $queueName);//延迟$delay秒后执行
            }
            
            if($order['pay_type'] == '1035'){
                //这个码更新为解锁状态
                Db::name('tb_qrcode')->where(['id'=>$order['xl_pay_data']])->update(['is_lock'=>0,'update_time'=>time()]);
            }
            
            
            $remark = '超时-执行成功';
            Utils::queueLog($data['trade_no'],$data['out_trade_no'],$remark);
            return true;
        }


        Utils::queueLog($data['trade_no'],$data['out_trade_no'],'超时-执行错误');

        return false;

    }
    
    
    //迅雷直播超时订单二次查单
    private function checkxlzbTimeOutOrder($data){

        $order = Db::name('order')->where(['id'=>$data['order_id']])->find();
        if(empty($order)){
            Utils::queueLog($data['trade_no'],$data['out_trade_no'],'订单不存在');
            return true;
        }
        
        //$check_res = CheckOrderUtils::xunLeiCheck($order);
        $check_res = CheckOrderUtils::alipayH5Check($order);

        if($check_res['is_exist'] == true){
            //找到订单 开始回调
            Db::name('order')->where(['id'=>$data['order_id']])->update(['remark'=> $order['remark'] . '二次查单成功']);
            Utils::queueLog($data['trade_no'],$data['out_trade_no'],'查单-执行成功-二次查单找到订单'.$check_res['url']);
            $notify = new Notify();
            $res    = $notify->dealOrderNotify($order, 1, '自动');
        }else{
            Utils::queueLog($data['trade_no'],$data['out_trade_no'],'查单-执行成功-未找到订单'.$check_res['url']);
        }
            
        return true;
    }
    
    
    //g买卖10分钟取消订单
    private function gmmCloseOrder($data){

        $order = Db::name('order')->where(['id'=>$data['order_id']])->find();
        if(empty($order)){
            Utils::queueLog($data['trade_no'], $data['out_trade_no'], '订单不存在');
            return true;
        }
        
        if($order['status'] == 1){
            Utils::queueLog($data['trade_no'], $data['out_trade_no'], 'gmm取消订单失败-订单已支付无需取消');
            return true;
        }
        
        if(empty($order['xl_order_id']) || empty($order['xl_pay_data'])){
            Utils::queueLog($data['trade_no'], $data['out_trade_no'], '订单未发起gmm支付');
            return true;
        }
        
        $result = CheckOrderUtils::gmmCloseOrder($order);

        Log::error('gmm取消订单'.$data['out_trade_no'].$result['data']);


        if($result['code'] == 200){
            Utils::queueLog($data['trade_no'],$data['out_trade_no'],'gmm取消订单成功-'.$result['data']);
        }else{
            Utils::queueLog($data['trade_no'], $data['out_trade_no'], 'gmm取消订单失败-'. $result['data']);
        }
        
        return true;
    }
    
    /**
     * 删除测试订单
     *
     * @param $data
     * @return bool
     */
    private function delTestOrder($data){
        
        $result1 = Db::name('order')->where('id',$data['order_id'])->delete();
        
        if($result1){
            $remark = '删除-执行成功';
            Utils::queueLog($data['trade_no'],$data['out_trade_no'],$remark);
            return true;
        }
        
        Utils::queueLog($data['trade_no'],$data['out_trade_no'],'删除-执行错误');
        
        return true;

    }
    
    
    //订单码超时未付再查一次
    private function checkDdmOrder($data){
        
        $order = Db::name('order')->where(['id'=>$data['order_id']])->find();
        if(empty($order)){
            return true;
        }
        
        $check_res = CheckOrderUtils::checkAlipayYsOrder($order);

        if($check_res['is_exist'] == true){
            
            //找到订单 开始回调
            if($order['status'] != 1){
                Log::error('订单码二次查单成功----'.$data['out_trade_no'].'----'.$data['trade_no']);
                Utils::queueLog($data['trade_no'],$data['out_trade_no'],'查单-执行成功-二次查单找到订单'.$check_res['url']);
                $notify = new Notify();
                $res    = $notify->dealOrderNotify($order, 1, '自动');
            }else{
                Log::error('订单码二次查单成功但单子是成功的，不处理回调----'.$data['out_trade_no'].'----'.$data['trade_no']);
            }
            
        }else{
            Utils::queueLog($data['trade_no'],$data['out_trade_no'],'查单-执行成功-未找到订单'.$check_res['url']);
        }
            
        return true;
    }
    
}