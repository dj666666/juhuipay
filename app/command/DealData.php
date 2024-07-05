<?php
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


class DealData extends Command
{
    protected function configure(){
        
        //设置名称为task
        $this->setName('dealdata')
            //增加一个命令参数
            ->addArgument('action', Argument::OPTIONAL, "action", '')
            ->addArgument('force', Argument::OPTIONAL, "force", '');
        
    }
    
    protected function execute(Input $input, Output $output){
        
        $cron_deal_data = Config::get('site.cron_deal_data');

        if ($cron_deal_data == 1) {
            $accutils = new Accutils();
            $start_time = $accutils->getMsectime();
            
            $this->dealData();
            
            $end_time = $accutils->getMsectime();
            $time = ($end_time - $start_time) / 1000;
            
            // 指令输出
            Log::write('数据处理成功，耗时：'. $time , 'info');
        }
        
    }
    
    public function dealData(){
        //每天凌晨10分执行 保留前一天的 清理前三天的 比如3号凌晨10分 清理30 31 1
        //$starttime  = date('Y-m-d', strtotime("-7 day")) . ' 00:00:00';
        $endtime  = date('Y-m-d', strtotime("-4 day")) . ' 00:00:00';
        $s = strtotime($endtime);
        //halt($starttime,$endtime);
        
        Db::startTrans();

        try {
            
            $order = Db::name('order')->where(['agent_id'=>16, 'pay_type'=>'1014'])->where('createtime', '<', $endtime)->delete();

            Db::commit();
            
            $msg = '数量：'. $order;
            
        } catch (ValidateException $e) {
            Db::rollback();
            $msg = $e->getMessage();
        } catch (\PDOException $e) {
            Db::rollback();
            $msg = $e->getMessage();
        } catch (Exception $e) {
            Db::rollback();
            $msg = $e->getMessage();
        }
        
        Utils::notifyLog(333333, 333333,'处理成功--'. $msg);

    }
}