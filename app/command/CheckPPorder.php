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
use fast\Http;
use app\common\library\Accutils;
use app\common\library\MoneyLog;


class CheckPPorder extends Command
{
    protected function configure()
    {
        //设置名称为task
        $this->setName('checkpporder')
            //增加一个命令参数
            ->addArgument('action', Argument::OPTIONAL, "action", '')
            ->addArgument('force', Argument::OPTIONAL, "force", '');
    }

    protected function execute(Input $input, Output $output)
    {
        //获取输入参数
        $action = trim($input->getArgument('action'));
        $force = trim($input->getArgument('force'));

        $this->checkPPoder($output);
    }
    
    public function checkPPoder($output){
        $orderList = Db::name('order')->where(['status'=>1, 'pay_type'=>'1020'])->where('id','>',6318)->order('id','asc')->select()->toArray();
        if(empty($orderList)){
            $output->writeln('暂无皮皮直播订单');
            return;
        }
                    
        foreach ($orderList as $k => $v){

            $options = [
                CURLOPT_HTTPHEADER =>[
                    'Content-Type:text/plain; charset=utf-8',
                    'User-Agent:Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1',
                ]
            ];
            
            $postData = [
                'pyid'  => $v['xl_user_id'],
                'order_id' => $v['xl_order_id'],
            ];
            
            $url = 'https://act-feature-live.ippzone.com/live_api/pay/webpay_check';
            
            $result = Http::post($url, json_encode($postData), $options);
            
            
            
            if(strstr($result,'订单正在处理') != false){
                $re = Db::name('order')->where('id', $v['id'])->update(['remark'=>$v['remark'].'错误']);
                $output->writeln($v['out_trade_no'] .'----'.$re);

                //找到订单 开始回调
                //Utils::notifyLog($v['trade_no'], $v['out_trade_no'], '皮皮订单-'.json_encode($result, JSON_UNESCAPED_UNICODE));
                //$this->sendNotify($v, time());
            }
            
            
        }
    }
}