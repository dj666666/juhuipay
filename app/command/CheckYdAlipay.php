<?php
declare (strict_types=1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Db;
use think\facade\Log;
use app\common\library\Wxpush;
use app\common\library\Alipay;
use app\common\library\Notify;
use app\common\library\Utils;
use think\facade\Queue;

class CheckYdAlipay extends Command
{
    protected function configure()
    {
        // 指令配置
        //配置命令的名字（"think"后面的）
        $this->setName('checkydalipay')
            //配置一个必填参数
            //->addArgument('name', Argument::REQUIRED, "your name")
            //配置一个必填参数
            //->addArgument('sex', Argument::OPTIONAL, "your sex")
            //配置一个选项
            //->addOption('city', null, Option::VALUE_REQUIRED, 'city name')
            ->setDescription('the checkydalipay command');
    }

    /*protected function execute(Input $input, Output $output)
    {

        $name = $input->getArgument('name');
        $sex = $input->getArgument('sex');
        $sex = $sex ?: 'woman or man';
        $name = $name ?: 'wolfcode';
        $city = '';
        if ($input->hasOption('city')) {
            $city = PHP_EOL . 'From ' . $input->getOption('city');
        }

        // 指令输出
        $output->writeln("hello {$name}" .'-'. "{$sex}" . '!' . $city);

    }*/

    protected function execute(Input $input, Output $output)
    {
        $time_start = microtime(true);

        $output->writeln("----------------------------------------------------------------------------");
        $output->writeln(date('Y-m-d H:i:s'));
        $output->writeln("任务开始...");

        $this->checkAlipay($output);            // 调用方法

        $time_end = microtime(true);
        $output->writeln('任务结束...耗时' . round($time_end - $time_start, 3) . '秒');
    }

    //支付宝监控
    public function checkAliPay($output){

        $push_uid = ['UID_UEzu3KcyDzfFBL0hIABgfMC9qosu'];
        $push_topicIds = [];
        
        $list = Db::name('group_qrcode')->where(['acc_code' => 1008, 'status' => 1])->order('id','asc')->select();
        if (empty($list)) {
            $output->writeln("无云端通道");
            return;
        }

        $output->writeln("通道数：".count($list));

        $pay_success_num = 0;
        $alipay_online_num = 0;

        $aliobj = new Alipay();

        foreach ($list as $row) {
            if(empty($row['cookie'])){
                //发送通知
                $push_content = '通道'.$row['id'].'|'.$row['name'].'掉线了';
                Wxpush::pushMsg($push_content,1,$push_topicIds,$push_uid);
                continue;
            }
            $cookie = base64_decode($row['cookie']);
            
            //if(empty($row['zfb_pid']))
            //{
            //    $beat = $aliobj->GetMyPID($cookie,$row['id']);
            //}
            //$beat = $aliobj->BaoHuo($cookie);
            //$m = $aliobj->GetMyMoney($cookie);
            $m = $aliobj->GetMyMoney_2($cookie);

            if ($m['status'] == true) {
                
                $money = $m['money'];
                
                $alipay_online_num++;
                
                if($row['yd_is_diaoxian'] == 0){
                    Db::name('group_qrcode')->where('id', $row['id'])->update(['yd_is_diaoxian' => 1]);
                }
                
            } else {
                //掉线
                Db::name('group_qrcode')->where('id', $row['id'])->update(['yd_is_diaoxian' => 0]);
                
                //发送通知
                $push_content = '通道'.$row['id'].'|'.$row['name'].'掉线了';
                Wxpush::pushMsg($push_content,1,$push_topicIds,$push_uid);

                continue;
            }

            $old_money = $row['money'];
            $output->writeln("余额：".$money. '系统余额：' .$old_money);
            if ($old_money != $money) {

                //更新为最新余额
                Db::name('group_qrcode')->where('id', $row['id'])->update(['money' => $money,'update_time'=>time()]);

                $order_count = Db::name('order')->where(['status' => 2, 'qrcode_id' => $row['id'], 'pay_type' => $row['acc_code']])
                    ->where('expire_time', '>', time())
                    ->count();

                if ($order_count > 0) {

                    //$orders = $aliobj->getAliOrderV2($row['cookie'],$row['zfb_pid']);//获取订单请求
                    $orders = $aliobj->getAliOrder($cookie, $row['zfb_pid']);//获取订单请求

                    if ($orders['status'] === 'deny') {
                        Db::name('group_qrcode')->where('id', $row['id'])->update(['status' => 0, 'yd_is_diaoxian' => 0]);
                        //$this->sendsms($row['user_id'],$row['id']);
                        //continue;//请求频繁或者掉线
                        //发送通知
                        $push_content = '通道'.$row['id'].'|'.$row['name'].'掉线了';
                        Wxpush::pushMsg($push_content,1,$push_topicIds,$push_uid);
                        
                        continue;
                    }

                    $orderList = empty($orders['result']['detail']) ? array() : $orders['result']['detail'];
                    $_order    = [];
                    $orderrow  = null;
                    foreach ($orderList as $order) {
                        $orderrow  = null;
                        $pay_money = $order['tradeAmount'];//⾦额
                        $pay_des   = $order['transMemo'];//备注
                        $tradeNo   = $order['tradeNo'];//⽀付宝订单号
                        if (!empty($pay_des)) {
                            $orderrow = Db::name('order')
                                ->where('trade_no', $pay_des)
                                ->where('status', 2)
                                ->where('pay_type', $row['acc_code'])
                                ->where('amount', sprintf("%.2f", $pay_money))
                                ->where('expire_time', '>', time())
                                ->order('id','desc')->find();

                            if (!empty($orderrow)) {
                                $pay_time = time();
                                //修改订单状态
                                $update_re = Db::name('order')
                                    ->where('id', $orderrow['id'])
                                    ->update(['status' => 1, 'ordertime' =>$pay_time]);

                                $pay_success_num++;

                                $this->sendNotify($orderrow,$pay_time);

                            }
                        }
                    }
                }
            }
            
            
        }

        $output->writeln("在线通道数：".$alipay_online_num);
        $output->writeln("支付成功数：".$pay_success_num);
    }

                                
    public function formartLog($mer_id, $mer_name, $log_id, $str){
        
        $str = "账号：" . $mer_id . "|" . $mer_name . "|" . $log_id . $str;
        
        Log::write($str, 'checkMoneyLog');

    }
    public function sendNotify($order,$pay_time){
        
        $findmerchant = Db::name('merchant')->where('id',$order['mer_id'])->field('money,is_callback,rate')->find();

        //发送回调
        $callback = new Notify();

        $callbackre = $callback->sendCallBack($order['id'],1,$pay_time);

        if($callbackre['code'] != 1){

            $callbackarray = [
                'is_callback'       => 2,
                'callback_count'    => $order['callback_count']+1,
                'callback_content'  => $callbackre['content'],
            ];


            //回调失败 加入队列
            if (Config::get('site.is_queue_notify')){

                // 当前任务所需的业务数据，不能为 resource 类型，其他类型最终将转化为json形式的字符串
                $queueData = [
                    'request_type'  => 3,
                    'order_id'      => $order['id'],
                    'out_trade_no'  => $order['out_trade_no'],
                ];

                //当前任务归属的队列名称，如果为新队列，会自动创建
                $queueName = 'checkorder';
                $delay = 5;
                // 将该任务推送到消息队列，等待对应的消费者去执行
                //$isPushed = Queue::push(Jobs::class, $data, $queueName);//立即执行
                $isPushed = Queue::later($delay,Jobs::class, $queueData, $queueName);//延迟$delay秒后执行

            }

            $msg = '回调失败未收到success：'.$callbackre['content'];

        }else{

            //回调成功
            $callbackarray = [
                'is_callback'       => 1,
                'callback_time'     => time(),
                'callback_count'    => $order['callback_count']+1,
                'callback_content'  => $callbackre['content'],
            ];


        }
        
        //扣除商户余额
        $mer_fees = bcmul($order['amount'],$findmerchant['rate'],2);
        $result1 = Utils::merchantMoneyLogV2($order['mer_id'], $order['amount'], $mer_fees, $order['out_trade_no'], 0, '订单完成');


        Db::name('order')->where('id',$order['id'])->update($callbackarray);
    }
}
