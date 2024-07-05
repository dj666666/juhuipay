<?php
declare (strict_types=1);

namespace app\command;

use app\admin\model\GroupQrcode;
use app\admin\model\order\Order;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\Exception;
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
use app\common\library\CheckOrderUtils;
use app\common\controller\Jobs;

class CheckAlipay extends Command
{
    protected function configure()
    {
        //设置名称为task
        $this->setName('checkalipay')
            //增加一个命令参数
            ->addArgument('action', Argument::OPTIONAL, "action", '')
            ->addArgument('force', Argument::OPTIONAL, "force", '');
    }

    protected function execute(Input $input, Output $output){
        //获取输入参数
        $action = trim($input->getArgument('action'));
        $force  = trim($input->getArgument('force'));



        // 配置任务，每隔20秒访问2次网站
        $task = new \EasyTask\Task();
        $task->setRunTimePath('./runtime');
        
        /*$task->addFunc(function () use($output){

            $accutils = new Accutils();
            $time_start = $accutils->getMsectime();
            $output->writeln("----------------------------------------------------------------------------");
            $output->writeln(date('Y-m-d H:i:s') . "我秀查单开始...");

            $this->checkWoxiuOrder($output);//查单

            $time_end = $accutils->getMsectime();
            $output->writeln(date('Y-m-d H:i:s') . '我秀查单结束...耗时' . (($time_end - $time_start) / 1000) . '秒');


        }, 'checkwoxiuWx',10, 1);*/
        
        /*$task->addFunc(function () use($output){

            $accutils = new Accutils();
            $time_start = $accutils->getMsectime();
            $output->writeln("----------------------------------------------------------------------------");
            $output->writeln(date('Y-m-d H:i:s') . "周转查单开始...");

            $this->checkAlipayZhouzOrder($output);//查单

            $time_end = $accutils->getMsectime();
            $output->writeln(date('Y-m-d H:i:s') . '周转查单结束...耗时' . (($time_end - $time_start) / 1000) . '秒');


        }, 'checkAlipayZzOrder',10, 1);*/
        
        $task->addFunc(function () use($output){

            $accutils = new Accutils();
            $time_start = $accutils->getMsectime();
            $output->writeln("----------------------------------------------------------------------------");
            $output->writeln(date('Y-m-d H:i:s') . "uid查单开始...");

            $this->checkAlipayGmUidOrder($output);//查单

            $time_end = $accutils->getMsectime();
            $output->writeln(date('Y-m-d H:i:s') . 'uid查单结束...耗时' . (($time_end - $time_start) / 1000) . '秒');


        }, 'checkAlipayGmUidOrder',8, 1);
        
        $task->addFunc(function () use($output){

            $accutils = new Accutils();
            $time_start = $accutils->getMsectime();
            $output->writeln("----------------------------------------------------------------------------");
            $output->writeln(date('Y-m-d H:i:s') . "个码1057开始...");

            $this->checkAlipayGmOrder($output);//查单

            $time_end = $accutils->getMsectime();
            $output->writeln(date('Y-m-d H:i:s') . '个码1057结束...耗时' . (($time_end - $time_start) / 1000) . '秒');


        }, 'checkAlipayGmOrder',10, 1);
        
        /*$task->addFunc(function () use($output){

            $accutils = new Accutils();
            $time_start = $accutils->getMsectime();
            $output->writeln("----------------------------------------------------------------------------");
            $output->writeln(date('Y-m-d H:i:s') . "批量查单开始...");

            $this->checkAlipayPlzzOrder($output);//查单

            $time_end = $accutils->getMsectime();
            $output->writeln(date('Y-m-d H:i:s') . '批量查单结束...耗时' . (($time_end - $time_start) / 1000) . '秒');


        }, 'checkAlipayPlzzOrder',10, 1);
        
        $task->addFunc(function () use($output){

            $accutils = new Accutils();
            $time_start = $accutils->getMsectime();
            $output->writeln("----------------------------------------------------------------------------");
            $output->writeln(date('Y-m-d H:i:s') . "报销查单开始...");

            $this->checkAlipayJsbxOrder($output);//查单

            $time_end = $accutils->getMsectime();
            $output->writeln(date('Y-m-d H:i:s') . '报销查单结束...耗时' . (($time_end - $time_start) / 1000) . '秒');


        }, 'checkAlipaybxOrder',10, 1);*/
        
        /*$task->addFunc(function () use($output){

            $accutils = new Accutils();
            $time_start = $accutils->getMsectime();
            $output->writeln("----------------------------------------------------------------------------");
            $output->writeln(date('Y-m-d H:i:s') . "当面付查单开始...");

            $this->checkAlipayDmfOrder($output);//查单

            $time_end = $accutils->getMsectime();
            $output->writeln(date('Y-m-d H:i:s') . '当面付查单结束...耗时' . (($time_end - $time_start) / 1000) . '秒');


        }, 'checkAlipayDmf',10, 1);*/
        
        /*$task->addFunc(function () use($output){

            $accutils = new Accutils();
            $time_start = $accutils->getMsectime();
            $output->writeln("----------------------------------------------------------------------------");
            $output->writeln(date('Y-m-d H:i:s') . "支付宝wap查单开始...");

            $this->checkAlipayWapOrder($output);//查单

            $time_end = $accutils->getMsectime();
            $output->writeln(date('Y-m-d H:i:s') . '支付宝wap查单结束...耗时' . (($time_end - $time_start) / 1000) . '秒');


        }, 'checkAlipayWap',10, 1);*/
        
        /*$task->addFunc(function () use($output){

            $accutils = new Accutils();
            $time_start = $accutils->getMsectime();
            $output->writeln("----------------------------------------------------------------------------");
            $output->writeln(date('Y-m-d H:i:s') . "支付宝app查单开始...");

            $this->checkAlipayAppOrder($output);//查单

            $time_end = $accutils->getMsectime();
            $output->writeln(date('Y-m-d H:i:s') . '支付宝app查单结束...耗时' . (($time_end - $time_start) / 1000) . '秒');


        }, 'checkAlipayApp',10, 1);
        
        $task->addFunc(function () use($output){

            $accutils = new Accutils();
            $time_start = $accutils->getMsectime();
            $output->writeln("----------------------------------------------------------------------------");
            $output->writeln(date('Y-m-d H:i:s') . "支付宝pc查单开始...");

            $this->checkAlipayPcOrder($output);//查单

            $time_end = $accutils->getMsectime();
            $output->writeln(date('Y-m-d H:i:s') . '支付宝pc查单结束...耗时' . (($time_end - $time_start) / 1000) . '秒');


        }, 'checkAlipayPc',10, 1);*/
        
        $task->addFunc(function () use($output){

            $accutils = new Accutils();
            $time_start = $accutils->getMsectime();
            $output->writeln("----------------------------------------------------------------------------");
            $output->writeln(date('Y-m-d H:i:s') . "账单查单开始...");

            $this->checkAlipayZdOrder($output);//查单

            $time_end = $accutils->getMsectime();
            $output->writeln(date('Y-m-d H:i:s') . '账单查单结束...耗时' . (($time_end - $time_start) / 1000) . '秒');


        }, 'checkAlipayZd',8, 1);
        
        /*$task->addFunc(function () use($output){

            $accutils = new Accutils();
            $time_start = $accutils->getMsectime();
            $output->writeln("----------------------------------------------------------------------------");
            $output->writeln(date('Y-m-d H:i:s') . "名片查单开始...");

            $this->checkAlipaySkmpOrder($output);//查单

            $time_end = $accutils->getMsectime();
            $output->writeln(date('Y-m-d H:i:s') . '名片查单结束...耗时' . (($time_end - $time_start) / 1000) . '秒');
        }, 'checkAlipaySkmp',8, 1);*/
        
        
        /*$task->addFunc(function () use($output){

            $accutils = new Accutils();
            $time_start = $accutils->getMsectime();
            $output->writeln("----------------------------------------------------------------------------");
            $output->writeln(date('Y-m-d H:i:s') . "小额uid查单开始...");

            $this->checkAlipayUidXeOrder($output);//查单

            $time_end = $accutils->getMsectime();
            $output->writeln(date('Y-m-d H:i:s') . '小额uid查单结束...耗时' . (($time_end - $time_start) / 1000) . '秒');
            
        }, 'checkAlipayUidXe',8, 1);*/
        
        
        $task->addFunc(function () use($output){

            $accutils = new Accutils();
            $time_start = $accutils->getMsectime();
            $output->writeln("----------------------------------------------------------------------------");
            $output->writeln(date('Y-m-d H:i:s') . "群账单开始...");

            $this->checkAlipayQZdOrder($output);//查单

            $time_end = $accutils->getMsectime();
            $output->writeln(date('Y-m-d H:i:s') . '群账单结束...耗时' . (($time_end - $time_start) / 1000) . '秒');


        }, 'checkAlipayQZd',8, 1);
        
        $task->addFunc(function () use($output){

            $accutils = new Accutils();
            $time_start = $accutils->getMsectime();
            $output->writeln("----------------------------------------------------------------------------");
            $output->writeln(date('Y-m-d H:i:s') . "经营码查单开始...");

            $this->checkAlipayJingYmaAOrder($output);//查单

            $time_end = $accutils->getMsectime();
            $output->writeln(date('Y-m-d H:i:s') . '经营码查单结束...耗时' . (($time_end - $time_start) / 1000) . '秒');


        }, 'checkAlipayJingyma',10, 1);
        
        /*$task->addFunc(function () use($output){

            $accutils = new Accutils();
            $time_start = $accutils->getMsectime();
            $output->writeln("----------------------------------------------------------------------------");
            $output->writeln(date('Y-m-d H:i:s') . "迅雷任务开始...");
            
            $this->checkXlzbZfb($output);//迅雷后台ck查单

            $time_end = $accutils->getMsectime();
            $output->writeln(date('Y-m-d H:i:s') . '迅雷任务结束...耗时' . (($time_end - $time_start) / 1000) . '秒');


        }, 'xlzbcheck',10, 1);*/
        
        /*$tbzf_time = 15;
        
        $task->addFunc(function () use($output){

            $accutils = new Accutils();
            $time_start = $accutils->getMsectime();
            $output->writeln("----------------------------------------------------------------------------");
            $output->writeln(date('Y-m-d H:i:s') . "淘宝直付任务开始...");
            $this->checkTbzfOrder($output);//淘宝直付查单

            $time_end = $accutils->getMsectime();
            $output->writeln(date('Y-m-d H:i:s') . '淘宝直付任务结束...耗时' . (($time_end - $time_start) / 1000) . '秒');


        }, 'checktbzf',15, 1);*/
        
        //$task->setDaemon(true);守护进程运行
        /*$task->addFunc(function () use($output){

            $accutils = new Accutils();
            $time_start = $accutils->getMsectime();
            $output->writeln("----------------------------------------------------------------------------");
            $output->writeln(date('Y-m-d H:i:s') . "云端任务开始...");

            $this->checkAlipay($output);//云端查单
            //$this->checkXlzbZfb($output);
            //$this->checkXlCk($output);
            //$this->checkXlzbZfbV2($output);//迅雷支付宝免ck查单
            //$this->checkylXlzcOrder($output);//愿聊-迅雷之锤-支付宝官方查单

            //$this->checkppzbZfb($output);//皮皮直播查单

            //$this->checkbaizhanzfb($output);//yy百战查单

            //$this->checkgmmOrder($output);//gmm查单
            //$this->checkUkiOrder($output);//uki查单
            //$this->checkTbzfOrder($output);//淘宝直付查单

            $time_end = $accutils->getMsectime();
            //$output->writeln(date('Y-m-d H:i:s') . '云端任务结束...耗时' . (($time_end - $time_start) / 1000) . '秒');


        }, 'checkalipay',10, 1);*/

        /*$task->addFunc(function () use($output){

            $accutils = new Accutils();
            $time_start = $accutils->getMsectime();
            $output->writeln("----------------------------------------------------------------------------");
            $output->writeln(date('Y-m-d H:i:s') . "gmm任务开始...");

            $this->checkgmmOrder($output);//gmm查单

            $time_end = $accutils->getMsectime();
            $output->writeln(date('Y-m-d H:i:s') . 'gmm任务结束...耗时' . (($time_end - $time_start) / 1000) . '秒');

        }, 'gmmCheck',10, 1);*/

        /*$task->addFunc(function () use($output){

            $accutils = new Accutils();
            $time_start = $accutils->getMsectime();
            $output->writeln("----------------------------------------------------------------------------");
            $output->writeln(date('Y-m-d H:i:s') . "淘宝核销任务开始...");

            $this->checkTbhxOrder($output);//云端查单

            $time_end = $accutils->getMsectime();
            $output->writeln(date('Y-m-d H:i:s') . '淘宝核销任务结束...耗时' . (($time_end - $time_start) / 1000) . '秒');


        }, 'tbhxCheck',10, 1);*/

        

        /*$task->addFunc(function () use($output){

            $accutils = new Accutils();
            $time_start = $accutils->getMsectime();
            $output->writeln("----------------------------------------------------------------------------");
            $output->writeln(date('Y-m-d H:i:s') . "云端uid中额任务开始...");

            $this->checkAlipayByUidZhongE($output);//云端查单

            $time_end = $accutils->getMsectime();
            //$output->writeln(date('Y-m-d H:i:s') . '云端uid中额任务结束...耗时' . (($time_end - $time_start) / 1000) . '秒');


        }, 'checkalipayze',10, 1);*/

        /*$task->addFunc(function () use($output){

            //$accutils = new Accutils();
            // $time_start = $accutils->getMsectime();
            // $output->writeln("----------------------------------------------------------------------------");
            // $output->writeln(date('Y-m-d H:i:s') . "云端1任务开始...");

            $this->checkAliPayByModulo($output,1,'云端1');//云端查单

            //$time_end = $accutils->getMsectime();
            //$output->writeln(date('Y-m-d H:i:s') . '云端1任务结束...耗时' . (($time_end - $time_start) / 1000) . '秒');


        }, 'checkalipay1',15, 1);

        $task->addFunc(function () use($output){

            //$accutils = new Accutils();
            // $time_start = $accutils->getMsectime();
            // $output->writeln("----------------------------------------------------------------------------");
            // $output->writeln(date('Y-m-d H:i:s') . "云端2任务开始...");

            $this->checkAliPayByModulo($output,2,'云端2');//云端查单

            //$time_end = $accutils->getMsectime();
            //$output->writeln(date('Y-m-d H:i:s') . '云端2任务结束...耗时' . (($time_end - $time_start) / 1000) . '秒');


        }, 'checkalipay2',15, 1);

        $task->addFunc(function () use($output){

            //$accutils = new Accutils();
            // $time_start = $accutils->getMsectime();
            // $output->writeln("----------------------------------------------------------------------------");
            // $output->writeln(date('Y-m-d H:i:s') . "云端3任务开始...");

            $this->checkAliPayByModulo($output,3,'云端3');//云端查单

            //$time_end = $accutils->getMsectime();
            //$output->writeln(date('Y-m-d H:i:s') . '云端3任务结束...耗时' . (($time_end - $time_start) / 1000) . '秒');


        }, 'checkalipay3',15, 1);

        $task->addFunc(function () use($output){

            //$accutils = new Accutils();
            // $time_start = $accutils->getMsectime();
            // $output->writeln("----------------------------------------------------------------------------");
            // $output->writeln(date('Y-m-d H:i:s') . "云端4任务开始...");

            $this->checkAliPayByModulo($output,4,'云端4');//云端查单

            //$time_end = $accutils->getMsectime();
            //$output->writeln(date('Y-m-d H:i:s') . '云端4任务结束...耗时' . (($time_end - $time_start) / 1000) . '秒');


        }, 'checkalipay4',15, 1);

        $task->addFunc(function () use($output){

            //$accutils = new Accutils();
            // $time_start = $accutils->getMsectime();
            // $output->writeln("----------------------------------------------------------------------------");
            // $output->writeln(date('Y-m-d H:i:s') . "云端5任务开始...");

            $this->checkAliPayByModulo($output,5,'云端5');//云端查单

            //$time_end = $accutils->getMsectime();
            //$output->writeln(date('Y-m-d H:i:s') . '云端5任务结束...耗时' . (($time_end - $time_start) / 1000) . '秒');


        }, 'checkalipay5',15, 1);

        $task->addFunc(function () use($output){

            //$accutils = new Accutils();
            // $time_start = $accutils->getMsectime();
            // $output->writeln("----------------------------------------------------------------------------");
            // $output->writeln(date('Y-m-d H:i:s') . "云端6任务开始...");

            $this->checkAliPayByModulo($output,6,'云端6');//云端查单

            //$time_end = $accutils->getMsectime();
            //$output->writeln(date('Y-m-d H:i:s') . '云端6任务结束...耗时' . (($time_end - $time_start) / 1000) . '秒');


        }, 'checkalipay6',15, 1);

        $task->addFunc(function () use($output){

            //$accutils = new Accutils();
            // $time_start = $accutils->getMsectime();
            // $output->writeln("----------------------------------------------------------------------------");
            // $output->writeln(date('Y-m-d H:i:s') . "云端7任务开始...");

            $this->checkAliPayByModulo($output,7,'云端7');//云端查单

            //$time_end = $accutils->getMsectime();
            //$output->writeln(date('Y-m-d H:i:s') . '云端7任务结束...耗时' . (($time_end - $time_start) / 1000) . '秒');


        }, 'checkalipay7',15, 1);

        $task->addFunc(function () use($output){

            //$accutils = new Accutils();
            // $time_start = $accutils->getMsectime();
            // $output->writeln("----------------------------------------------------------------------------");
            // $output->writeln(date('Y-m-d H:i:s') . "云端8任务开始...");

            $this->checkAliPayByModulo($output,8,'云端8');//云端查单

            //$time_end = $accutils->getMsectime();
            //$output->writeln(date('Y-m-d H:i:s') . '云端8任务结束...耗时' . (($time_end - $time_start) / 1000) . '秒');


        }, 'checkalipay8',15, 1);

        $task->addFunc(function () use($output){

            //$accutils = new Accutils();
            // $time_start = $accutils->getMsectime();
            // $output->writeln("----------------------------------------------------------------------------");
            // $output->writeln(date('Y-m-d H:i:s') . "云端9任务开始...");

            $this->checkAliPayByModulo($output,9,'云端9');//云端查单

            //$time_end = $accutils->getMsectime();
            //$output->writeln(date('Y-m-d H:i:s') . '云端9任务结束...耗时' . (($time_end - $time_start) / 1000) . '秒');


        }, 'checkalipay9',15, 1);

        $task->addFunc(function () use($output){

            //$accutils = new Accutils();
            // $time_start = $accutils->getMsectime();
            // $output->writeln("----------------------------------------------------------------------------");
            // $output->writeln(date('Y-m-d H:i:s') . "云端10任务开始...");

            $this->checkAliPayByModulo($output,0,'云端10');//云端查单

            //$time_end = $accutils->getMsectime();
            //$output->writeln(date('Y-m-d H:i:s') . '云端10任务结束...耗时' . (($time_end - $time_start) / 1000) . '秒');


        }, 'checkalipay10',15, 1);*/
        
        /*$gm_time = 15;
        
        $task->addFunc(function () use($output, $gm_time){

            //$accutils = new Accutils();
            // $time_start = $accutils->getMsectime();
            // $output->writeln("----------------------------------------------------------------------------");
            // $output->writeln(date('Y-m-d H:i:s') . "云端10任务开始...");

            $this->checkAliPayGmByModulo($output,1,'个码1');//云端查单

            //$time_end = $accutils->getMsectime();
            //$output->writeln(date('Y-m-d H:i:s') . '云端10任务结束...耗时' . (($time_end - $time_start) / 1000) . '秒');


        }, 'checkalipayGm1', $gm_time, 1);

        $task->addFunc(function () use($output, $gm_time){

            //$accutils = new Accutils();
            // $time_start = $accutils->getMsectime();
            // $output->writeln("----------------------------------------------------------------------------");
            // $output->writeln(date('Y-m-d H:i:s') . "云端10任务开始...");

            $this->checkAliPayGmByModulo($output,2,'个码2');//云端查单

            //$time_end = $accutils->getMsectime();
            //$output->writeln(date('Y-m-d H:i:s') . '云端10任务结束...耗时' . (($time_end - $time_start) / 1000) . '秒');


        }, 'checkalipayGm2', $gm_time, 1);
        
        $task->addFunc(function () use($output, $gm_time){

            //$accutils = new Accutils();
            // $time_start = $accutils->getMsectime();
            // $output->writeln("----------------------------------------------------------------------------");
            // $output->writeln(date('Y-m-d H:i:s') . "云端10任务开始...");

            $this->checkAliPayGmByModulo($output,3,'个码3');//云端查单

            //$time_end = $accutils->getMsectime();
            //$output->writeln(date('Y-m-d H:i:s') . '云端10任务结束...耗时' . (($time_end - $time_start) / 1000) . '秒');


        }, 'checkalipayGm3', $gm_time, 1);
        
        $task->addFunc(function () use($output){

            //$accutils = new Accutils();
            // $time_start = $accutils->getMsectime();
            // $output->writeln("----------------------------------------------------------------------------");
            // $output->writeln(date('Y-m-d H:i:s') . "云端10任务开始...");

            $this->checkAliPayGmByModulo($output,4,'个码4');//云端查单

            //$time_end = $accutils->getMsectime();
            //$output->writeln(date('Y-m-d H:i:s') . '云端10任务结束...耗时' . (($time_end - $time_start) / 1000) . '秒');


        }, 'checkalipayGm4', $gm_time, 1);
        $task->addFunc(function () use($output, $gm_time){

            //$accutils = new Accutils();
            // $time_start = $accutils->getMsectime();
            // $output->writeln("----------------------------------------------------------------------------");
            // $output->writeln(date('Y-m-d H:i:s') . "云端10任务开始...");

            $this->checkAliPayGmByModulo($output,5,'个码5');//云端查单

            //$time_end = $accutils->getMsectime();
            //$output->writeln(date('Y-m-d H:i:s') . '云端10任务结束...耗时' . (($time_end - $time_start) / 1000) . '秒');


        }, 'checkalipayGm5', $gm_time, 1);
        $task->addFunc(function () use($output, $gm_time){

            //$accutils = new Accutils();
            // $time_start = $accutils->getMsectime();
            // $output->writeln("----------------------------------------------------------------------------");
            // $output->writeln(date('Y-m-d H:i:s') . "云端10任务开始...");

            $this->checkAliPayGmByModulo($output,6,'个码6');//云端查单

            //$time_end = $accutils->getMsectime();
            //$output->writeln(date('Y-m-d H:i:s') . '云端10任务结束...耗时' . (($time_end - $time_start) / 1000) . '秒');


        }, 'checkalipayGm6', $gm_time, 1);
        $task->addFunc(function () use($output, $gm_time){

            //$accutils = new Accutils();
            // $time_start = $accutils->getMsectime();
            // $output->writeln("----------------------------------------------------------------------------");
            // $output->writeln(date('Y-m-d H:i:s') . "云端10任务开始...");

            $this->checkAliPayGmByModulo($output,7,'个码7');//云端查单

            //$time_end = $accutils->getMsectime();
            //$output->writeln(date('Y-m-d H:i:s') . '云端10任务结束...耗时' . (($time_end - $time_start) / 1000) . '秒');


        }, 'checkalipayGm7', $gm_time, 1);
        $task->addFunc(function () use($output, $gm_time){

            //$accutils = new Accutils();
            // $time_start = $accutils->getMsectime();
            // $output->writeln("----------------------------------------------------------------------------");
            // $output->writeln(date('Y-m-d H:i:s') . "云端10任务开始...");

            $this->checkAliPayGmByModulo($output,8,'个码8');//云端查单

            //$time_end = $accutils->getMsectime();
            //$output->writeln(date('Y-m-d H:i:s') . '云端10任务结束...耗时' . (($time_end - $time_start) / 1000) . '秒');


        }, 'checkalipayGm8', $gm_time, 1);
        $task->addFunc(function () use($output, $gm_time){

            //$accutils = new Accutils();
            // $time_start = $accutils->getMsectime();
            // $output->writeln("----------------------------------------------------------------------------");
            // $output->writeln(date('Y-m-d H:i:s') . "云端10任务开始...");

            $this->checkAliPayGmByModulo($output,9,'个码9');//云端查单

            //$time_end = $accutils->getMsectime();
            //$output->writeln(date('Y-m-d H:i:s') . '云端10任务结束...耗时' . (($time_end - $time_start) / 1000) . '秒');


        }, 'checkalipayGm9', $gm_time, 1);
        
        $task->addFunc(function () use($output, $gm_time){

            //$accutils = new Accutils();
            // $time_start = $accutils->getMsectime();
            // $output->writeln("----------------------------------------------------------------------------");
            // $output->writeln(date('Y-m-d H:i:s') . "云端10任务开始...");

            $this->checkAliPayGmByModulo($output,0,'个码10');//云端查单

            //$time_end = $accutils->getMsectime();
            //$output->writeln(date('Y-m-d H:i:s') . '云端10任务结束...耗时' . (($time_end - $time_start) / 1000) . '秒');


        }, 'checkalipayGm10', $gm_time, 1);
        
        
        $task->addFunc(function () use($output){

            //$accutils = new Accutils();
            // $time_start = $accutils->getMsectime();
            // $output->writeln("----------------------------------------------------------------------------");
            // $output->writeln(date('Y-m-d H:i:s') . "云端10任务开始...");

            $this->checkAliPayGmOnline($output,'个码检测在线');

            //$time_end = $accutils->getMsectime();
            //$output->writeln(date('Y-m-d H:i:s') . '云端10任务结束...耗时' . (($time_end - $time_start) / 1000) . '秒');


        }, 'checkalipayGmCheckOnline',90, 1);*/
        
        // 根据命令执行
        if ($action == 'start'){
            $task->start();
        }elseif ($action == 'status'){
            $task->status();
        }elseif ($action == 'stop'){
            $force = ($force == 'force'); //是否强制停止
            $task->stop($force);
        }else{
            exit('Command is not exist');
        }
    }
    
    //支付宝 经营码
    public function checkAlipayJingYmaAOrder($output){
        
        $orderList = Db::name('order')->where([
                'status'   => Order::STATUS_DEALING,
                'pay_type' => '1067',
            ])
            ->field('id,user_id,agent_id,mer_id,qrcode_id,mer_fees,fees,pay_type,amount,pay_amount,out_trade_no,trade_no,xl_order_id,xl_user_id,hand_order_id,callback_count,xl_pay_data,hand_pay_data,hc_pay_data,zfb_user_id')
            ->select()->toArray();
        
        if(empty($orderList)){
            return;
        }
        
        foreach ($orderList as $k1 => $v1){
            
            //没有支付信息的不查单
            if (empty($v1['xl_pay_data'])){
                continue;
            }
            
            $output->writeln("{$v1['trade_no']}----经营码处理");
            
            $check_res = CheckOrderUtils::checkAlipayGmOrder($v1);
            
            $output->writeln("{$v1['trade_no']}----经营码查单".$check_res['data']);
            
            if($check_res['is_exist'] == true){
                
                //找到订单 开始回调
                $this->formartLog("{$v1['trade_no']}----经营码成功");
                
                $options = [
                    'xl_order_id' => $check_res['url']
                ];
                
                //处理订单 发送回调
                $notify = new Notify();
                $res    = $notify->dealOrderNotify($v1, 1, '自动', '', $options);
                
            }
            
        }
        
    }
    
    //支付宝 群收款账单
    public function checkAlipayQZdOrder($output){
        
        $orderList = Db::name('order')->where([
                'status'   => Order::STATUS_DEALING,
                'pay_type' => '1064',
            ])
            ->field('id,user_id,agent_id,mer_id,qrcode_id,mer_fees,fees,pay_type,amount,pay_amount,out_trade_no,trade_no,xl_order_id,xl_user_id,hand_order_id,callback_count,xl_pay_data,hand_pay_data,hc_pay_data,zfb_user_id')
            ->select()->toArray();
        
        if(empty($orderList)){
            return;
        }
        
        foreach ($orderList as $k1 => $v1){
            
            //没有支付信息的不查单
            if (empty($v1['zfb_user_id'])){
                continue;
            }
            
            $output->writeln("{$v1['trade_no']}----群账单处理");
            
            $check_res = CheckOrderUtils::checkAlipayGmOrder($v1);
            
            $output->writeln("{$v1['trade_no']}----群账单查单".$check_res['data']);
            
            if($check_res['is_exist'] == true){
                
                //找到订单 开始回调
                $this->formartLog("{$v1['trade_no']}----群账单成功");
                
                $options = [
                    'xl_order_id' => $check_res['url']
                ];
                
                //处理订单 发送回调
                $notify = new Notify();
                $res    = $notify->dealOrderNotify($v1, 1, '自动', '', $options);
                
            }
            
        }
        
    }
    
    //支付宝 小额uid 1008
    public function checkAlipayUidXeOrder($output){
        
        $orderList = Db::name('order')->where([
                'status'   => Order::STATUS_DEALING,
                'pay_type' => '1008',
            ])
            ->field('id,user_id,agent_id,mer_id,qrcode_id,mer_fees,fees,pay_type,amount,pay_amount,out_trade_no,trade_no,xl_order_id,xl_user_id,hand_order_id,callback_count,xl_pay_data,hand_pay_data,hc_pay_data,zfb_user_id')
            ->select()->toArray();
        
        if(empty($orderList)){
            return;
        }
        
        foreach ($orderList as $k1 => $v1){
            
            //没有支付信息的不查单
            if (empty($v1['zfb_user_id'])){
                continue;
            }
            
            $output->writeln("{$v1['trade_no']}----小额uid处理");
            
            $check_res = CheckOrderUtils::checkAlipayGmOrder($v1);
            
            $output->writeln("{$v1['trade_no']}----小额uid查单".$check_res['data']);
            
            if($check_res['is_exist'] == true){
                
                //找到订单 开始回调
                $this->formartLog("{$v1['trade_no']}----小额uid查单成功");
                
                $options = [
                    'xl_order_id' => $check_res['url']
                ];
                
                //处理订单 发送回调
                $notify = new Notify();
                $res    = $notify->dealOrderNotify($v1, 1, '自动', '', $options);
                
            }
            
        }
        
    }
    
    //支付宝 收款名片
    public function checkAlipaySkmpOrder($output){
        
        $orderList = Db::name('order')->where([
                'status'   => Order::STATUS_DEALING,
                'pay_type' => '1063',
            ])
            ->field('id,user_id,agent_id,mer_id,qrcode_id,mer_fees,fees,pay_type,amount,pay_amount,out_trade_no,trade_no,xl_order_id,xl_user_id,hand_order_id,callback_count,xl_pay_data,hand_pay_data,hc_pay_data,zfb_user_id')
            ->select()->toArray();
        
        if(empty($orderList)){
            return;
        }
        
        foreach ($orderList as $k1 => $v1){
            
            //没有支付信息的不查单
            if (empty($v1['zfb_user_id'])){
                continue;
            }
            
            $output->writeln("{$v1['trade_no']}----名片处理");
            
            $check_res = CheckOrderUtils::checkAlipayGmOrder($v1);
            
            $output->writeln("{$v1['trade_no']}----名片查单".$check_res['data']);
            
            if($check_res['is_exist'] == true){
                
                //找到订单 开始回调
                $this->formartLog("{$v1['trade_no']}----名片查单成功");
                
                $options = [
                    'xl_order_id' => $check_res['url']
                ];
                
                //处理订单 发送回调
                $notify = new Notify();
                $res    = $notify->dealOrderNotify($v1, 1, '自动', '', $options);
                
            }
            
        }
        
    }
    
    //支付宝收款账单
    public function checkAlipayZdOrder($output){
        
        $orderList = Db::name('order')->where([
                'status'   => Order::STATUS_DEALING,
                'pay_type' => '1061',
            ])
            ->field('id,user_id,agent_id,mer_id,qrcode_id,mer_fees,fees,pay_type,amount,pay_amount,out_trade_no,trade_no,xl_order_id,xl_user_id,hand_order_id,callback_count,xl_pay_data,hand_pay_data,hc_pay_data,zfb_user_id')
            ->select()->toArray();
        
        if(empty($orderList)){
            return;
        }
        
        foreach ($orderList as $k1 => $v1){
            
            //没有支付信息的不查单
            if (empty($v1['zfb_user_id'])){
                continue;
            }
            
            $output->writeln("{$v1['trade_no']}----账单处理");
            
            $check_res = CheckOrderUtils::checkAlipayGmOrder($v1);
            
            $output->writeln("{$v1['trade_no']}----账单查单".$check_res['data']);
            
            if($check_res['is_exist'] == true){
                
                //找到订单 开始回调
                $this->formartLog("{$v1['trade_no']}----账单查单成功");
                
                $options = [
                    'xl_order_id' => $check_res['url']
                ];
                
                //处理订单 发送回调
                $notify = new Notify();
                $res    = $notify->dealOrderNotify($v1, 1, '自动', '', $options);
                
            }
            
        }
        
    }
    
    
     //支付宝个码-极速报销
    public function checkAlipayJsbxOrder($output){
        
        $orderList = Db::name('order')->where([
                'status'   => Order::STATUS_DEALING,
                'pay_type' => '1059',
            ])
            ->field('id,user_id,agent_id,mer_id,qrcode_id,mer_fees,fees,pay_type,amount,pay_amount,out_trade_no,trade_no,xl_order_id,xl_user_id,hand_order_id,callback_count,xl_pay_data,hand_pay_data,hc_pay_data,zfb_user_id')
            ->select()->toArray();
        
        if(empty($orderList)){
            return;
        }
        
        foreach ($orderList as $k1 => $v1){
            
            //没有支付信息的不查单
            if (empty($v1['zfb_user_id'])){
                continue;
            }
            
            $output->writeln("{$v1['trade_no']}----报销处理");
            
            $check_res = CheckOrderUtils::checkAlipayGmOrder($v1);
            
            $output->writeln("{$v1['trade_no']}----报销查单".$check_res['data']);
            
            if($check_res['is_exist'] == true){
                
                //找到订单 开始回调
                $this->formartLog("{$v1['trade_no']}----报销查单成功");
                
                $options = [
                    'xl_order_id' => $check_res['url']
                ];
                
                //处理订单 发送回调
                $notify = new Notify();
                $res    = $notify->dealOrderNotify($v1, 1, '自动', '', $options);
                
            }
            
        }
            
    }
    
    
    //支付宝个码-批量转账
    public function checkAlipayPlzzOrder($output){
        
        $orderList = Db::name('order')->where([
                'status'   => Order::STATUS_DEALING,
                'pay_type' => '1058',
            ])
            ->field('id,user_id,agent_id,mer_id,qrcode_id,mer_fees,fees,pay_type,amount,pay_amount,out_trade_no,trade_no,xl_order_id,xl_user_id,hand_order_id,callback_count,xl_pay_data,hand_pay_data,hc_pay_data,zfb_user_id')
            ->select()->toArray();
        
        if(empty($orderList)){
            return;
        }
        
        foreach ($orderList as $k1 => $v1){
            
            //没有支付信息的不查单
            if (empty($v1['zfb_user_id'])){
                continue;
            }
            
            $output->writeln("{$v1['trade_no']}----批量处理");
            
            $check_res = CheckOrderUtils::checkAlipayGmOrder($v1);
            
            $output->writeln("{$v1['trade_no']}----批量查单".$check_res['data']);
            
            if($check_res['is_exist'] == true){
                
                //找到订单 开始回调
                $this->formartLog("{$v1['trade_no']}----批量查单成功");
                
                $options = [
                    'xl_order_id' => $check_res['url']
                ];
                
                //处理订单 发送回调
                $notify = new Notify();
                $res    = $notify->dealOrderNotify($v1, 1, '自动', '', $options);
                
            }
            
        }
            
    }
    
    //支付宝个码-链接
    public function checkAlipayGmOrder($output){
        try{
            
            $orderList = Db::name('order')->where([
                    'status'   => Order::STATUS_DEALING,
                    'pay_type' => '1057',
                ])
                ->field('id,user_id,agent_id,mer_id,qrcode_id,mer_fees,fees,pay_type,amount,pay_amount,out_trade_no,trade_no,xl_order_id,xl_user_id,hand_order_id,callback_count,xl_pay_data,hand_pay_data,hc_pay_data,zfb_user_id')
                ->select()->toArray();
            
            if(empty($orderList)){
                return;
            }
            
            foreach ($orderList as $k1 => $v1){
                
                //订单没有更新主体id的 不查单
                if (empty($v1['xl_user_id'])){
                    continue;
                }
                
                $output->writeln("{$v1['trade_no']}----1057处理");
                
                $check_res = CheckOrderUtils::checkAlipayGmOrder($v1);
                
                $output->writeln("{$v1['trade_no']}----1057查单".$check_res['data']);
                
                if($check_res['is_exist'] == true){
                    
                    //找到订单 开始回调
                    $this->formartLog("{$v1['trade_no']}----1057查单成功");
                    
                    $options = [
                        'xl_order_id' => $check_res['url']
                    ];
                    
                    //处理订单 发送回调
                    $notify = new Notify();
                    $res    = $notify->dealOrderNotify($v1, 1, '自动', '', $options);
                    
                }
                
            }
        
        } catch (Exception $e) {
            $this->formartLog('1057错误：'.$e->getFile().'-'.$e->getLine() .'-'.$e->getMessage());
        }
    }
    
    //支付宝个码-uid
    public function checkAlipayGmUidOrder($output){
        
        $orderList = Db::name('order')->where([
                'status'   => Order::STATUS_DEALING,
                'pay_type' => '1056',
            ])
            ->field('id,user_id,agent_id,mer_id,qrcode_id,mer_fees,fees,pay_type,amount,pay_amount,out_trade_no,trade_no,pay_remark,xl_order_id,xl_user_id,hand_order_id,callback_count,xl_pay_data,hand_pay_data,hc_pay_data,zfb_user_id')
            ->select()->toArray();
        
        if(empty($orderList)){
            return;
        }
        
        foreach ($orderList as $k1 => $v1){
            
            //订单没有更新主体id的 不查单
            if (empty($v1['xl_user_id'])){
                continue;
            }
            
            $output->writeln("{$v1['trade_no']}----1056uid处理");
            
            $check_res = CheckOrderUtils::checkAlipayGmOrder($v1);
            
            $output->writeln("{$v1['trade_no']}----1056uid查单".$check_res['data']);
            
            if($check_res['is_exist'] == true){
                
                //找到订单 开始回调
                $this->formartLog("{$v1['trade_no']}----uid查单成功");
                
                $options = [
                    'xl_order_id' => $check_res['url']
                ];
                
                //处理订单 发送回调
                $notify = new Notify();
                $res    = $notify->dealOrderNotify($v1, 1, '自动', '', $options);
                
            }
        }
            
    }
    
    //支付宝资金周转-uid
    public function checkAlipayZhouzOrder($output){
        
        $orderList = Db::name('order')->where([
                'status'   => Order::STATUS_DEALING,
                'pay_type' => '1055',
            ])
            ->field('id,user_id,agent_id,mer_id,qrcode_id,mer_fees,fees,pay_type,amount,pay_amount,out_trade_no,trade_no,xl_order_id,xl_user_id,hand_order_id,callback_count,xl_pay_data,hand_pay_data,hc_pay_data,zfb_user_id')
            ->select()->toArray();
        
        if(empty($orderList)){
            return;
        }
        
        foreach ($orderList as $k1 => $v1){
            
            //没有支付信息的不查单
            if (empty($v1['zfb_user_id'])){
                continue;
            }
            
            $output->writeln("{$v1['trade_no']}----周转处理");

            $check_res = CheckOrderUtils::checkAlipayGmOrder($v1);
            
            $output->writeln("{$v1['trade_no']}----周转查单".$check_res['data']);
            
            if($check_res['is_exist'] == true){
                
                //找到订单 开始回调
                $this->formartLog("{$v1['trade_no']}----周转查单成功");
                
                $options = [
                    'xl_order_id' => $check_res['url']
                ];
                
                //处理订单 发送回调
                $notify = new Notify();
                $res    = $notify->dealOrderNotify($v1, 1, '自动', '', $options);
                
            }
            
        }
            
    }
    
    
    //支付宝当面付支付
    public function checkAlipayDmfOrder($output){
        
        $orderList = Db::name('order')->where([
                'status'   => Order::STATUS_DEALING,
                'pay_type' => '1052',
            ])
            ->field('id,user_id,agent_id,mer_id,qrcode_id,mer_fees,fees,pay_type,amount,pay_amount,out_trade_no,trade_no,xl_order_id,xl_user_id,hand_order_id,callback_count,xl_pay_data,hand_pay_data,hc_pay_data')
            ->select()->toArray();

        if(empty($orderList)){
            return;
        }

        foreach ($orderList as $k1 => $v1){
            $output->writeln('开始处理'.$v1['out_trade_no']);
            //没有支付信息的不查单
            if (empty($v1['xl_pay_data']) || empty($v1['hc_pay_data'])){
                continue;
            }

            $check_res = CheckOrderUtils::checkAlipayAppOrder($v1);

            if($check_res['is_exist'] == true){
                
                //找到订单 开始回调
                $this->formartLog("{$v1['trade_no']}----当面付查单成功");
                
                $options = [
                    'xl_order_id' => $check_res['url']
                ];
                
                //处理订单 发送回调
                $notify = new Notify();
                $res    = $notify->dealOrderNotify($v1, 1, '自动', '', $options);
                
            }else{
                $output->writeln($check_res['data']);

            }
        }
            
    }
    
    //支付宝wap支付
    public function checkAlipayWapOrder($output){
        
        $orderList = Db::name('order')->where([
                'status'   => Order::STATUS_DEALING,
                'pay_type' => '1053',
            ])
            ->field('id,user_id,agent_id,mer_id,qrcode_id,mer_fees,fees,pay_type,amount,pay_amount,out_trade_no,trade_no,xl_order_id,xl_user_id,hand_order_id,callback_count,xl_pay_data,hand_pay_data,hc_pay_data')
            ->select()->toArray();

        if(empty($orderList)){
            return;
        }

        foreach ($orderList as $k1 => $v1){
            $output->writeln('开始处理'.$v1['out_trade_no']);
            //没有支付信息的不查单
            if (empty($v1['xl_pay_data']) || empty($v1['hc_pay_data'])){
                continue;
            }

            $check_res = CheckOrderUtils::checkAlipayAppOrder($v1);

            if($check_res['is_exist'] == true){
                
                //找到订单 开始回调
                $this->formartLog("{$v1['trade_no']}----支付宝wap查单成功");
                
                $options = [
                    'xl_order_id' => $check_res['url']
                ];
                
                //处理订单 发送回调
                $notify = new Notify();
                $res    = $notify->dealOrderNotify($v1, 1, '自动', '', $options);
                
            }else{
                $output->writeln($check_res['data']);

            }
        }
            
    }
    
    
    //支付宝电脑pc支付
    public function checkAlipayPcOrder($output){
        
        $orderList = Db::name('order')->where([
                'status'   => Order::STATUS_DEALING,
                'pay_type' => '1051',
            ])
            ->field('id,user_id,agent_id,mer_id,qrcode_id,mer_fees,fees,pay_type,amount,pay_amount,out_trade_no,trade_no,xl_order_id,xl_user_id,hand_order_id,callback_count,xl_pay_data,hand_pay_data,hc_pay_data')
            ->select()->toArray();

        if(empty($orderList)){
            return;
        }

        foreach ($orderList as $k1 => $v1){
            $output->writeln('开始处理'.$v1['out_trade_no']);
            //没有支付信息的不查单
            if (empty($v1['xl_pay_data']) || empty($v1['hc_pay_data'])){
                continue;
            }

            $check_res = CheckOrderUtils::checkAlipayAppOrder($v1);

            if($check_res['is_exist'] == true){
                
                //找到订单 开始回调
                $this->formartLog("{$v1['trade_no']}----支付宝pc查单成功");
                
                $options = [
                    'xl_order_id' => $check_res['url']
                ];
                
                //处理订单 发送回调
                $notify = new Notify();
                $res    = $notify->dealOrderNotify($v1, 1, '自动', '', $options);
                
            }else{
                $output->writeln($check_res['data']);

            }
        }
            
    }
    
    //支付宝app查单
    public function checkAlipayAppOrder($output){
        
        $orderList = Db::name('order')->where([
                'status'   => Order::STATUS_DEALING,
                'pay_type' => '1050',
            ])
            ->field('id,user_id,agent_id,mer_id,qrcode_id,mer_fees,fees,pay_type,amount,pay_amount,out_trade_no,trade_no,xl_order_id,xl_user_id,hand_order_id,callback_count,xl_pay_data,hand_pay_data,hc_pay_data')
            ->select()->toArray();

        if(empty($orderList)){
            return;
        }

        foreach ($orderList as $k1 => $v1){
            $output->writeln('开始处理'.$v1['out_trade_no']);
            //没有支付信息的不查单
            if (empty($v1['xl_pay_data']) || empty($v1['hc_pay_data'])){
                continue;
            }

            $check_res = CheckOrderUtils::checkAlipayAppOrder($v1);

            if($check_res['is_exist'] == true){
                
                //找到订单 开始回调
                $this->formartLog("{$v1['trade_no']}----支付宝app查单成功");
                
                $options = [
                    'xl_order_id'     => $check_res['url']
                ];
                
                //处理订单 发送回调
                $notify = new Notify();
                $res    = $notify->dealOrderNotify($v1, 1, '自动', '', $options);
                
            }else{
                $output->writeln($check_res['data']);

            }
        }
            
    }
    
    //支付宝监控云端
    public function checkAliPay($output){
        try {

            $list = GroupQrcode::where(['acc_code' => 1008, 'status' => 1])->order('id','asc')->select();
            if (empty($list)) {
                $output->writeln("无云端通道");
                return;
            }
            $qrcode_num = count($list);

            $output->writeln("通道数：".$qrcode_num);

            $pay_success_num   = 0; //支付订单成功数
            $alipay_online_num = 0; //通道在线数

            $aliobj = new Alipay();

            foreach ($list as $row) {
                if(empty($row['cookie'])){
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
                        GroupQrcode::where('id', $row['id'])->update(['yd_is_diaoxian' => 1]);
                    }

                } else {

                    //掉线
                    $this->turnOffQrcode($row['id']);

                    //发送通知
                    $push_content = '通道'.$row['id'].'|'.$row['name'].'掉线了';
                    $this->pushNotice($push_content);
                    continue;
                }

                $old_money = $row['money'];
                $output->writeln("余额：".$money. '系统余额：' .$old_money);
                if ($old_money == $money) {
                    $this->formartLog("{$row['zfb_pid']}余额相同----{$old_money}----{$money}");
                    continue;
                }

                //更新为最新余额
                GroupQrcode::where('id', $row['id'])->update(['money' => $money]);

                //查看这个码待支付的订单
                $order_count = Order::where(['qrcode_id' => $row['id'], 'status' => 2, 'pay_type' => $row['acc_code']])
                    ->count();
                if ($order_count <= 0) {
                    continue;
                }

                //获取支付宝订单列表
                //$orders = $aliobj->getAliOrderV2($row['cookie'],$row['zfb_pid']);//获取订单请求
                $orders = $aliobj->getAliOrder($cookie, $row['zfb_pid'], 60*10);//获取订单请求

                if ($orders['status'] === 'deny') {
                    //请求频繁或者掉线
                    $this->turnOffQrcode($row['id']);

                    $push_content = '通道'.$row['id'].'|'.$row['name'].'掉线了';
                    $this->pushNotice($push_content);
                    continue;
                }

                $orderList = empty($orders['result']['detail']) ? array() : $orders['result']['detail'];

                foreach ($orderList as $order) {

                    $pay_money = $order['tradeAmount'];//⾦额
                    $pay_des   = $order['transMemo'];//备注
                    $tradeNo   = $order['tradeNo'];//⽀付宝订单号
                    if (!empty($pay_des)) {
                        $orderrow = Order::where([
                            'qrcode_id'  => $row['id'],
                            'status'     => 2,
                            'pay_type'   => $row['acc_code'],
                            'pay_amount'     => sprintf("%.2f", $pay_money),
                            'pay_remark' => $pay_des
                        ])
                            ->limit(1, 1)
                            ->find();

                        if (!empty($orderrow)) {

                            $this->formartLog("找到支付宝订单".json_encode($order) .'系统订单'.$orderrow['out_trade_no']);

                            //支付宝单号更新上去
                            $options = [
                                'xl_order_id' => $tradeNo
                            ];
                            $pay_success_num++;
                            $notify = new Notify();
                            $res    = $notify->dealOrderNotify($orderrow, 1, '自动', '',$options);
                            //$this->sendNotify($orderrow, $options);

                        }
                    }
                }


            }

            $this->formartLog("通道数：{$qrcode_num} 在线通道数：{$alipay_online_num} 支付成功数{$pay_success_num}");

            $output->writeln("在线通道数：".$alipay_online_num);
            $output->writeln("支付成功数：".$pay_success_num);

        } catch (Exception $e) {
            $this->formartLog('云端错误：'.$e->getFile().'-'.$e->getLine() .'-'.$e->getMessage());
        }

    }

    //支付宝uid中额
    public function checkAlipayByUidZhongE($output){
        try {

            $list = GroupQrcode::where(['acc_code' => '1041', 'status' => 1])->order('id','asc')->select();
            if (empty($list)) {
                $output->writeln("无云端通道");
                return;
            }
            $qrcode_num = count($list);

            $output->writeln("1041通道数：".$qrcode_num);

            $pay_success_num   = 0; //支付订单成功数
            $alipay_online_num = 0; //通道在线数

            $aliobj = new Alipay();

            foreach ($list as $row) {

                if(empty($row['cookie'])){
                    //掉线
                    $this->turnOffQrcode($row['id'],'未登录');
                    continue;
                }

                $cookie = base64_decode($row['cookie']);
                $m = $aliobj->GetMyMoney_2($cookie);

                if ($m['status'] == true) {

                    $money = $m['money'];

                    $alipay_online_num++;

                    if($row['yd_is_diaoxian'] == 0){
                        GroupQrcode::where('id', $row['id'])->update(['yd_is_diaoxian' => 1]);
                    }

                } else {

                    //掉线
                    $this->turnOffQrcode($row['id']);

                    //发送通知
                    $push_content = '通道'.$row['id'].'|'.$row['name'].'掉线了';
                    $this->pushNotice($push_content);
                    continue;
                }

                /*$old_money = $row['money'];
                $output->writeln("余额：".$money. '系统余额：' .$old_money);
                //余额相同不做查找订单处理
                if ($old_money == $money) {
                    continue;
                }

                //更新为最新余额
                GroupQrcode::where('id', $row['id'])->update(['money' => $money]);*/

                //查看这个码待支付的订单
                $order_count = Order::where(['qrcode_id' => $row['id'], 'status' => 2])
                    ->count();
                if ($order_count <= 0) {
                    continue;
                }

                //获取支付宝订单列表
                //$orders = $aliobj->getAliOrderV2($row['cookie'],$row['zfb_pid']);//获取订单请求
                $orders = $aliobj->getAliOrder($cookie, $row['zfb_pid'], 60*10);//获取订单请求

                if ($orders['status'] === 'deny') {
                    //请求频繁或者掉线
                    $this->turnOffQrcode($row['id']);

                    $push_content = '通道'.$row['id'].'|'.$row['name'].'掉线了';
                    $this->pushNotice($push_content);
                    continue;
                }else{
                    if($row['yd_is_diaoxian'] == GroupQrcode::ONlINE){
                        GroupQrcode::where('id', $row['id'])->update(['yd_is_diaoxian' => GroupQrcode::ONlINE]);
                    }
                }

                $orderList = empty($orders['result']['detail']) ? array() : $orders['result']['detail'];

                foreach ($orderList as $order) {

                    $pay_money = $order['tradeAmount'];//⾦额
                    $pay_des   = $order['transMemo'];//备注
                    $tradeNo   = $order['tradeNo'];//⽀付宝订单号
                    if (!empty($pay_des)) {
                        $orderrow = Order::where([
                            'qrcode_id'  => $row['id'],
                            'status'     => 2,
                            'pay_type'   => $row['acc_code'],
                            'pay_amount'     => sprintf("%.2f", $pay_money),
                            'pay_remark' => $pay_des
                        ])
                            ->limit(1, 1)
                            ->find();

                        if (!empty($orderrow)) {

                            $this->formartLog("uid中额找到支付宝订单".json_encode($order) .'系统订单'.$orderrow['out_trade_no']);

                            //支付宝单号更新上去
                            $options = [
                                'xl_order_id' => $tradeNo
                            ];
                            $pay_success_num++;

                            $notify = new Notify();
                            $res    = $notify->dealOrderNotify($orderrow, 1, '自动', '', $options);
                            //$this->sendNotify($orderrow, $options);

                        }
                    }
                }


            }

            $this->formartLog("1041通道数：{$qrcode_num} 在线通道数：{$alipay_online_num} 支付成功数：{$pay_success_num}");

        } catch (Exception $e) {
            $this->formartLog('uid中额错误：'.$e->getFile().'-'.$e->getLine() .'-'.$e->getMessage());
        }

    }

    /**
     * 支付宝uid小额 通用取模
     *
     * @param $output
     * @param $remainder integer 余数
     */
    public function checkAliPayByModulo($output, $remainder, $task_name){
        try {

            $list = GroupQrcode::where(['acc_code' => '1008', 'status' => 1])->order('id','asc')->select();
            if (empty($list)) {
                $output->writeln("无云端通道");
                return;
            }
            $qrcode_num = count($list);

            $output->writeln("1008通道数：".$qrcode_num);

            $pay_success_num   = 0; //支付订单成功数
            $alipay_online_num = 0; //通道在线数

            $aliobj = new Alipay();

            foreach ($list as $row) {
                //只处理模10=0的
                if($row['id'] % 10 != $remainder){
                    //$output->writeln($row['id'] . "不符合" . $remainder.'过滤');
                    continue;
                }
                
                $output->writeln($task_name . "处理" . $row['name']);
                
                if(empty($row['cookie'])){
                    $this->turnOffQrcode($row['id'],'未登录');
                    $output->writeln($row['id'] . 'cookie为空过滤');
                    continue;
                }

                $cookie = base64_decode($row['cookie']);

                //保活
                $aliobj->BaoHuo($cookie);

                /*$m = $aliobj->GetMyMoney_2($cookie);

                if ($m['status'] == true) {

                    $money = $m['money'];

                    $alipay_online_num++;

                    if($row['yd_is_diaoxian'] == GroupQrcode::ONlINE){
                        GroupQrcode::where('id', $row['id'])->update(['yd_is_diaoxian' => GroupQrcode::ONlINE]);
                    }

                } else {
                    $this->formartLog("{$row['zfb_pid']}掉线");
                    //掉线
                    $this->turnOffQrcode($row['id']);

                    //发送通知
                    $push_content = '通道'.$row['id'].'|'.$row['name'].'掉线了';
                    $this->pushNotice($push_content);
                    continue;
                }

                $old_money = $row['money'];
                $output->writeln("余额：".$money. '系统余额：' .$old_money);*/
                /*//余额相同代表没进钱 不做订单查找操作
                if ($old_money == $money) {
                    continue;
                }

                //更新为最新余额
                //GroupQrcode::where('id', $row['id'])->update(['money' => $money]);*/

                //查看这个码待支付的订单
                $order_count = Order::where(['qrcode_id' => $row['id'], 'status' => 2])
                    ->count();
                if ($order_count <= 0) {
                    continue;
                }

                //获取支付宝订单列表
                //$orders = $aliobj->getAliOrderV2($row['cookie'],$row['zfb_pid']);//获取订单请求
                $orders = $aliobj->getAliOrder($cookie, $row['zfb_pid'], 60*10);//获取订单请求
                
                if($orders['status'] === 'failed'){
                    $this->formartLog($row['zfb_pid']."请求频繁");
                    //请求频繁或者掉线
                    $this->turnOffQrcode($row['id'],'请求频繁');
                    continue;
                }
                
                if ($orders['status'] === 'deny') {
                    $this->formartLog($row['zfb_pid']."请求掉线");
                    //请求频繁或者掉线
                    $this->turnOffQrcode($row['id'],'掉线了');
                    
                    $push_content = '通道'.$row['id'].'|'.$row['name'].'掉线了';
                    $this->pushNotice($push_content);
                    continue;
                }else{
                    if($row['yd_is_diaoxian'] == GroupQrcode::ONlINE){
                        GroupQrcode::where('id', $row['id'])->update(['yd_is_diaoxian' => GroupQrcode::ONlINE]);
                    }
                }

                $orderList = empty($orders['result']['detail']) ? array() : $orders['result']['detail'];

                foreach ($orderList as $order) {

                    $pay_money = $order['tradeAmount'];//⾦额
                    $pay_des   = $order['transMemo'];//备注
                    $tradeNo   = $order['tradeNo'];//⽀付宝订单号
                    if (!empty($pay_des)) {
                        $orderrow = Order::where([
                            'qrcode_id'  => $row['id'],
                            'status'     => 2,
                            'pay_type'   => $row['acc_code'],
                            'pay_amount' => sprintf("%.2f", $pay_money),
                            'pay_remark' => $pay_des
                        ])
                            ->limit(1, 1)
                            ->find();

                        if (!empty($orderrow)) {

                            $this->formartLog("小额uid".$remainder."找到支付宝订单".json_encode($order) .'系统订单'.$orderrow['out_trade_no']);

                            //支付宝单号更新上去
                            $options = [
                                'xl_order_id' => $tradeNo
                            ];
                            $pay_success_num++;

                            $notify = new Notify();
                            $res    = $notify->dealOrderNotify($orderrow, 1, '自动', '', $options);

                        }
                    }
                }


            }

        } catch (Exception $e) {
            $this->formartLog('uid小额'.$remainder.'错误：'.$e->getFile().'-'.$e->getLine() .'-'.$e->getMessage());
        }

    }

    
    /**
     * 支付宝个码 通用取模
     *
     * @param $output
     * @param $remainder integer 余数
     */
    public function checkAliPayGmByModulo($output, $remainder, $task_name){
        try {

            $list = GroupQrcode::where(['acc_code' => '1025', 'status' => 1])->order('id','asc')->select();
            if (empty($list)) {
                $output->writeln("无云端通道");
                return;
            }
            
            $qrcode_num = count($list);

            

            $pay_success_num   = 0; //支付订单成功数
            $alipay_online_num = 0; //通道在线数

            $aliobj = new Alipay();

            foreach ($list as $row) {
                
                //只处理模10=0的
                if($row['id'] % 10 != $remainder){
                    //$output->writeln($row['id'] . "不符合" . $remainder.'过滤');
                    continue;
                }
                
                $output->writeln($task_name . "处理" . $row['name']);
                
                if(empty($row['cookie'])){
                    $this->turnOffQrcode($row['id'],'未登录');
                    $output->writeln($row['id'] . 'cookie为空过滤');
                    continue;
                }

                $cookie = base64_decode($row['cookie']);

                //保活
                $aliobj->BaoHuo($cookie);

                /*$m = $aliobj->GetMyMoney_2($cookie);

                if ($m['status'] == true) {

                    $money = $m['money'];

                    $alipay_online_num++;

                    if($row['yd_is_diaoxian'] == GroupQrcode::ONlINE){
                        GroupQrcode::where('id', $row['id'])->update(['yd_is_diaoxian' => GroupQrcode::ONlINE]);
                    }

                } else {
                    $this->formartLog("{$row['zfb_pid']}掉线");
                    //掉线
                    $this->turnOffQrcode($row['id']);

                    //发送通知
                    $push_content = '通道'.$row['id'].'|'.$row['name'].'掉线了';
                    $this->pushNotice($push_content);
                    continue;
                }

                $old_money = $row['money'];
                $output->writeln("余额：".$money. '系统余额：' .$old_money);*/
                /*//余额相同代表没进钱 不做订单查找操作
                if ($old_money == $money) {
                    continue;
                }

                //更新为最新余额
                //GroupQrcode::where('id', $row['id'])->update(['money' => $money]);*/

                //查看这个码待支付的订单
                $order_count = Order::where(['qrcode_id' => $row['id'], 'status' => 2])
                    ->count();
                if ($order_count <= 0) {
                    continue;
                }

                //获取支付宝订单列表
                //$orders = $aliobj->getAliOrderV2($row['cookie'],$row['zfb_pid']);//获取订单请求
                $orders = $aliobj->getAliOrder($cookie, $row['zfb_pid'], 60*10);//获取订单请求
                if(!is_array($orders)){
                    $this->formartLog($row['zfb_pid']."错误".$orders);
                    //请求频繁或者掉线
                    $this->turnOffQrcode($row['id'],'请求错误');
                    continue;
                }
                if($orders['status'] === 'failed'){
                    $this->formartLog($row['zfb_pid']."请求频繁");
                    //请求频繁或者掉线
                    $this->turnOffQrcode($row['id'],'请求频繁');
                    continue;
                }
                if ($orders['status'] === 'deny') {
                    $this->formartLog($row['zfb_pid']."请求掉线");
                    //请求频繁或者掉线
                    $this->turnOffQrcode($row['id'],'掉线了');
                    
                    $push_content = '通道'.$row['id'].'|'.$row['name'].'掉线了';
                    $this->pushNotice($push_content);
                    continue;
                }else{
                    if($row['yd_is_diaoxian'] == GroupQrcode::ONlINE){
                        GroupQrcode::where('id', $row['id'])->update(['yd_is_diaoxian' => GroupQrcode::ONlINE]);
                    }
                }

                $orderList = empty($orders['result']['detail']) ? array() : $orders['result']['detail'];

                foreach ($orderList as $order) {
                    
                    $pay_money   = $order['tradeAmount'];//⾦额
                    $tradeNo     = $order['tradeNo'];//⽀付宝订单号
                    $signProduct = $order['signProduct'];//转账模式
                    $balance     = $order['balance'];//余额
                    $tradeTime   = strtotime($order['tradeTime']);//交易时间
                    
                    /*if($signProduct != '转账码'){
                        continue;
                    }*/
                    
                    $now_time = time();
                    $orderrow = Order::where([
                        'qrcode_id'   => $row['id'],
                        'status'      => 2,
                        'pay_type'    => $row['acc_code'],
                        'pay_amount'  => sprintf("%.2f", $pay_money),
                        'is_callback' => 0
                    ])
                    ->where("createtime",'<',$tradeTime)
                    ->where("expire_time",'>',$tradeTime)
                    ->find();

                    if (!empty($orderrow)) {

                        $this->formartLog("个码".$remainder.$row['name']."----找到支付宝订单".json_encode($order) .'系统订单'.$orderrow['out_trade_no']);
                        
                        //更新为最新余额
                        GroupQrcode::where('id', $row['id'])->update(['money' => $balance]);
                        
                        //支付宝单号更新上去
                        $options = [
                            'xl_order_id' => $tradeNo
                        ];
                        $pay_success_num++;

                        $notify = new Notify();
                        $res    = $notify->dealOrderNotify($orderrow, 1, '自动', '', $options);

                    }
                    
                }


            }

        } catch (Exception $e) {
            $this->formartLog('个码'.$remainder.'错误：'.$e->getFile().'-'.$e->getLine() .'-'.$e->getMessage());
        }

    }

    
     /**
     * 支付宝个码 通用取模
     *
     * @param $output
     * @param $remainder integer 余数
     */
    public function checkAliPayGmOnline($output, $task_name){
        try {

            $list = GroupQrcode::where(['acc_code' => '1025', 'status' => 1])->field('id,name,zfb_pid,cookie,yd_is_diaoxian')->order('id','asc')->select();
            
            if (empty($list)) {
                $output->writeln("无个码通道");
                return;
            }
            $qrcode_num = count($list);

            $output->writeln("1025开启通道数：".$qrcode_num);

            
            $online_num = 0; //通道在线数
            

            $aliobj = new Alipay();

            foreach ($list as $row) {
                
                $output->writeln($task_name . "处理" . $row['name']);
                
                if(empty($row['cookie'])){
                    $this->turnOffQrcode($row['id'],'未登录');
                    $output->writeln($row['id'] . 'cookie为空过滤');
                    continue;
                }

                $cookie = base64_decode($row['cookie']);

                //保活
                $aliobj->BaoHuo($cookie);
                
                $orders = $aliobj->getAliOrder($cookie, $row['zfb_pid'], 60*10);//获取订单请求
                if(!is_array($orders)){
                    $this->formartLog($row['zfb_pid']."错误".$orders);
                    //请求频繁或者掉线
                    $this->turnOffQrcode($row['id'],'请求错误');
                    continue;
                }
                
                if($orders['status'] === 'failed'){
                    $this->formartLog($row['zfb_pid']."请求频繁");
                    //请求频繁或者掉线
                    $this->turnOffQrcode($row['id'],'请求频繁');
                    continue;
                }
                
                if ($orders['status'] === 'deny') {
                    $this->formartLog($row['zfb_pid']."请求掉线");
                    //请求频繁或者掉线
                    $this->turnOffQrcode($row['id'],'掉线了');
                    
                    //$push_content = '通道'.$row['id'].'|'.$row['name'].'掉线了';
                    //$this->pushNotice($push_content);
                    continue;
                }else{
                    $online_num++;
                    if($row['yd_is_diaoxian'] == GroupQrcode::ONlINE){
                        GroupQrcode::where('id', $row['id'])->update(['yd_is_diaoxian' => GroupQrcode::ONlINE]);
                    }
                }

                

            }
            
            $output->writeln("1025在线通道数：".$online_num);

        } catch (Exception $e) {
            $this->formartLog('个码检测在线错误：'.$e->getFile().'-'.$e->getLine() .'-'.$e->getMessage());
        }

    }



    //支付宝监控云端
    public function checkAliPayV2($output){

        $acc_code = '1008';
        $list = GroupQrcode::where(['acc_code' => $acc_code, 'status' => 1])->order('id','asc')->select();
        if (empty($list)) {
            return;
        }

        $qrcode_num = count($list);

        $output->writeln("通道数：".$qrcode_num);

        $pay_success_num   = 0; //支付订单成功数
        $alipay_online_num = 0; //通道在线数

        $aliobj = new Alipay();

        foreach ($list as $row) {
            if (empty($row['cookie'])) {
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

                if ($row['yd_is_diaoxian'] == 0) {
                    GroupQrcode::where('id', $row['id'])->update(['yd_is_diaoxian' => 1]);
                }

                $old_money = $row['money'];
                $output->writeln("余额：".$money. '系统余额：' .$old_money);
                if ($old_money == $money) {
                    $this->formartLog("{$row['zfb_pid']}余额相同----{$old_money}----{$money}");
                }else{
                    //更新为最新余额
                    GroupQrcode::where('id', $row['id'])->update(['money' => $money]);
                }



            } else {
                //掉线
                $this->turnOffQrcode($row['id']);
                //发送通知
                $push_content = '通道' . $row['id'] . '|' . $row['name'] . '掉线了';
                $this->pushNotice($push_content);
            }

        }

        //系统待查单的订单
        $orderData = Order::where(['status' => 2, 'pay_type' => $acc_code])->select()->toArray();
        //->field('id,qrcode_id,out_trade_no,trade_no,amount,pay_amount,pay_remark')

        if (empty($orderData)) {
            return;
        }

        foreach ($orderData as $k =>$orderInfo){

            $qrcode = GroupQrcode::where(['id' => $row['qrcode_id']])->find();

            //获取支付宝订单列表
            //$orders = $aliobj->getAliOrderV2($row['cookie'],$row['zfb_pid']);//获取订单请求
            $orders = $aliobj->getAliOrder($qrcode['cookie'], $qrcode['zfb_pid'], 60*10);//获取订单请求

            if ($orders['status'] === 'deny') {
                //请求频繁或者掉线
                $this->turnOffQrcode($qrcode['id']);

                $push_content = '通道'.$qrcode['id'].'|'.$qrcode['name'].'掉线了';
                $this->pushNotice($push_content);
                continue;
            }


            $orderList = empty($orders['result']['detail']) ? array() : $orders['result']['detail'];

            foreach ($orderList as $order) {

                $pay_money = $order['tradeAmount'];//⾦额
                $pay_des   = $order['transMemo'];//备注
                $tradeNo   = $order['tradeNo'];//⽀付宝订单号

                if ($pay_des != $orderInfo['pay_remark'] && $pay_money != $orderInfo['pay_amount']){
                    continue;
                }

                $this->formartLog("找到支付宝订单".json_encode($order) .'系统单号'.$orderInfo['out_trade_no']);

                //支付宝单号更新上去
                $options = [
                    'xl_order_id' => $tradeNo
                ];
                $pay_success_num++;

                $this->sendNotify($orderInfo, $options);

            }

        }

        $this->formartLog("通道数：{$qrcode_num} 在线通道数：{$alipay_online_num} 支付成功数{$pay_success_num}");

        $output->writeln("在线通道数：".$alipay_online_num);
        $output->writeln("支付成功数：".$pay_success_num);

    }

    //迅雷直播后台查订单记录
    public function checkXlzbZfb($output){

        $orderList = Db::name('order')->where(['status'=>2,'pay_type'=>'1014'])->whereDay('createtime')->select()->toArray();
        if(empty($orderList)){
            $output->writeln('暂无迅雷订单');
            return;
        }
        
        foreach ($orderList as $k => $v){
            
            
            $check_res = CheckOrderUtils::xlcheckOrderByCk($v);
            
            if($check_res['is_exist'] == true){
                
                //找到订单 开始回调
                $output->writeln($v['trade_no'].'找到订单');
                $this->sendNotify($v);
                
            }else{
                
                $output->writeln($v['trade_no'].'迅雷未找到订单');
                
            }
            
        }
        
        
        
        
    }

    //迅雷直播查单是否存在
    public function checkXlzbZfbV2($output){

        $orderList = Db::name('order')->where(['status'=>2,'pay_type'=>'1014'])->select()->toArray();
        if(empty($orderList)){
            return;
        }

        $params = [];
        $options = [];

        $accutils = new Accutils();
        $time = $accutils->getMsectime();
        foreach ($orderList as $k => $v){
            if (empty($v['xl_pay_data'])) {
                continue;
            }
            /*if($v['id'] % 2 != 0){ //只处理单数
                continue;
            }*/
            //$check_res = CheckOrderUtils::xunLeiCheck($v);
            $check_res = CheckOrderUtils::alipayH5Check($v);

            if($check_res['is_exist'] == true){
                //找到订单 开始回调
                $output->writeln($v['out_trade_no'].'迅雷找到订单');

                Utils::notifyLog($v['trade_no'], $v['out_trade_no'], '迅雷找到订单'.$check_res['url']);
                $this->sendNotify($v);
            }else{
                $output->writeln($v['out_trade_no'].'迅雷未找到订单');

            }

        }
    }

    //愿聊-迅雷之锤查单
    public function checkylXlzcOrder($output){

        $orderList = Db::name('order')->where(['status'=>2,'pay_type'=>'1029'])->select()->toArray();
        if(empty($orderList)){
            return;
        }

        $params = [];
        $options = [];

        $accutils = new Accutils();
        $time = $accutils->getMsectime();
        foreach ($orderList as $k => $v){
            if (empty($v['xl_pay_data'])) {
                continue;
            }

            $check_res = CheckOrderUtils::alipayH5Check($v);

            if($check_res['is_exist'] == true){
                //找到订单 开始回调
                $output->writeln($v['out_trade_no'].'迅雷1找到订单');

                Utils::notifyLog($v['trade_no'], $v['out_trade_no'], '迅雷1找到订单'.$check_res['url']);
                $this->sendNotify($v);
            }else{
                $output->writeln($v['out_trade_no'].'迅雷1未找到订单');

            }

        }
    }

    //皮皮直播查单
    public function checkppzbZfb($output){

        $orderList = Db::name('order')->where(['status'=>2,'pay_type'=>'1020'])->select()->toArray();
        if(empty($orderList)){
            $this->formartLog('暂无皮皮直播订单');
            return;
        }

        foreach ($orderList as $k => $v){

            $check_res = CheckOrderUtils::ppCheck($v);

            if($check_res['is_exist'] == true){
                //找到订单 开始回调
                Utils::notifyLog($v['trade_no'], $v['out_trade_no'], '皮皮找到订单'.$check_res['url']);
                $this->sendNotify($v);
            }

        }
    }

    //yy百战查单
    public function checkbaizhanzfb($output){

        $orderList = Db::name('order')->where('status',2)->whereIn('pay_type',  '1026,1027')->order('id','asc')->select()->toArray();
        if(empty($orderList)){
            $this->formartLog('暂无百战订单');
            return;
        }

        foreach ($orderList as $k => $v){

            $check_res = CheckOrderUtils::baizhanCheck($v);

            if($check_res['is_exist'] == true){
                //找到订单 开始回调
                Utils::notifyLog($v['trade_no'], $v['out_trade_no'], '百战找到订单'.$check_res['data']);
                $this->sendNotify($v);
            }

        }
    }

    //gmm查单
    public function checkgmmOrder($output){

        $orderList = Db::name('order')->where(['status'=>2,'pay_type'=>'1028'])->select()->toArray();
        if(empty($orderList)){
            return;
        }

        foreach ($orderList as $k => $v){

            $check_res = CheckOrderUtils::gmmCheck($v);

            if($check_res['is_exist'] == true){
                $this->formartLog('gmm查单结果'.json_encode($check_res,JSON_UNESCAPED_UNICODE));
                //找到订单 开始回调
                Utils::notifyLog($v['trade_no'], $v['out_trade_no'], 'gmm找到订单'.$check_res['data']);
                $this->sendNotify($v);
            }

        }
    }

    //uki查单
    public function checkUkiOrder($output){

        $orderList = Db::name('order')->where(['status'=>2,'pay_type'=>'1030'])->select()->toArray();
        if(empty($orderList)){
            return;
        }

        foreach ($orderList as $k => $v){
            if (empty($v['xl_pay_data'])) {
                continue;
            }

            $check_res = CheckOrderUtils::alipayH5Check($v);

            if($check_res['is_exist'] == true){
                //找到订单 开始回调
                $output->writeln($v['out_trade_no'].'uki找到订单');

                Utils::notifyLog($v['trade_no'], $v['out_trade_no'], 'uki找到订单'.$check_res['url']);
                $this->sendNotify($v);
            }else{
                $output->writeln($v['out_trade_no'].'uki找到订单');

            }

        }
    }

    //淘宝直付查单
    public function checkTbzfOrder($output){

        $orderList = Db::name('order')->where(['status'=>2,'pay_type'=>'1031'])->field('id,user_id,mer_id,out_trade_no,trade_no,pay_type,createtime,status,amount,pay_amount')->select()->toArray();
        if(empty($orderList)){
            return;
        }

        foreach ($orderList as $k => $v){

            $check_res = CheckOrderUtils::tbzfCheck($v);

            if($check_res['is_exist'] == true){
                //找到订单 开始回调
                $output->writeln($v['out_trade_no'].'淘宝直付找到订单');
                
                $this->formartLog("淘宝直付----找到订单" .'系统订单'.$v['out_trade_no']);
                
                $notify = new Notify();
                $res    = $notify->dealOrderNotify($v, 1, '自动', '', []);
                        
            }else{
                $output->writeln($v['out_trade_no'].'淘宝直付找到订单');

            }

        }
    }

    //淘宝核销查单
    public function checkTbhxOrder($output){

        $orderList = Order::where(['status'=>Order::STATUS_DEALING,'pay_type'=>'1032'])->where('zfb_code','<>','')->select()->toArray();
        if(empty($orderList)){
            return;
        }

        foreach ($orderList as $k => $v){

            $check_res = CheckOrderUtils::tbhxCheck($v);

            if($check_res['is_exist'] == true){

                //找到订单 开始回调
                $output->writeln($v['out_trade_no'].'----淘宝核销找到订单'.$check_res['data']);
                $this->formartLog("{$v['out_trade_no']}----淘宝核销找到订单----".$check_res['data']);

                $notify = new Notify();
                $res    = $notify->dealOrderNotify($v, 1, '自动');

            }else{
                $output->writeln($v['out_trade_no'].'----淘宝核销失败'.$check_res['data']);
                $this->formartLog("{$v['out_trade_no']}----淘宝核销失败----".$check_res['data']);
            }

        }
    }

    //拼多多代付查单
    public function checkPddDfOrder($output){

        $orderList = Order::where(['status'=>Order::STATUS_DEALING,'pay_type'=>'1035'])->select()->toArray();
        if(empty($orderList)){
            return;
        }

        foreach ($orderList as $k => $v){

            $check_res = CheckOrderUtils::pddDfCheck($v);

            if($check_res['is_exist'] == true){

                //找到订单 开始回调
                $output->writeln($v['out_trade_no'].'----拼多多已支付'.$check_res['data']);
                $this->formartLog("{$v['out_trade_no']}----拼多多订单已支付----".$check_res['data']);
                //Utils::notifyLog($v['trade_no'], $v['out_trade_no'], '淘宝核销找到订单');
                $this->sendNotify($v);
            }

        }
    }

    //我秀查单
    public function checkWoxiuOrder($output){

        $orderList = Order::where(['status'=>Order::STATUS_DEALING,'pay_type'=>'1033'])->select()->toArray();
        if(empty($orderList)){
            return;
        }

        foreach ($orderList as $k => $v){

            $check_res = CheckOrderUtils::woxiuCheckWxH5($v);

            if($check_res['is_exist'] == true){
                
                //找到订单 开始回调
                $this->formartLog("{$v1['trade_no']}----我秀查单成功");
                
                //处理订单 发送回调
                $notify = new Notify();
                $res    = $notify->dealOrderNotify($v1, 1, '自动', '', []);
                
            }else{
                $output->writeln($check_res['data']);

            }

        }
    }

    //检测迅雷ck
    public function checkXlCk($output){

        $starttime  = date("Y-m-d",strtotime("-30 day"));
        $endtime    = date('Y-m-d');
        $params     = [];
        $accutils   = new Accutils();
        $time       = $accutils->getMsectime();
        $qrcodeList = Db::name('group_qrcode')->where(['user_id'=>1,'status'=>1,'acc_code' => '1014'])->select()->toArray();

        foreach ($qrcodeList as $k => $v){
            $options = [
                CURLOPT_HTTPHEADER =>[
                    'Cookie:'.$v['xl_cookie'],
                ]
            ];

            $url = 'https://xluser-ssl.xunlei.com/tradingrecord/v1/GetTradingRecord?csrf_token=fff61cf90f6b183806aa527c57121625&appid=22003&starttime='.$starttime.'&endtime='.$endtime.'&paytype=&_='.$time;
            $result = json_decode(Http::get($url, $params, $options),true);

            $output->writeln(json_encode($result));

            if($result['code'] != 200){
                Db::name('group_qrcode')->where(['id' => $v['id']])->update(['status'=>0,'remark'=>'ck失效了','update_time'=>time()]);
                
                $output->writeln($v['id'].'----ck失效'.$check_res['data']);
                
            }
        }

    }


    public function formartLog($log){

        Log::write($log, 'checkAlipay');

    }

    /**
     * 推送通知
     *
     * @param $push_content
     * @return void
     */
    public function pushNotice($push_content){
        $push_uid      = ['UID_UEzu3KcyDzfFBL0hIABgfMC9qosu'];
        $push_topicIds = [];
        Wxpush::pushMsg($push_content, 1, $push_topicIds, $push_uid);
    }

    /**
     * 发送回调
     *
     * @param $order array 要发送回调的订单
     * @param $options array 扩展参数
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function sendNotify($order, $options=[]){

        $pay_time = time();
        $status   = 1;
        $findmerchant = Db::name('merchant')->where('id',$order['mer_id'])->find();

        //先修改订单状态 再发送回调
        $updata = [
            'status'            =>  $status,
            'ordertime'         =>  $pay_time,
            'deal_ip_address'   =>  '自动',
            'deal_username'     =>  '自动',
        ];

        if ($options){
            $updata = array_merge($updata, $options);
        }

        Db::name('order')->where('id',$order['id'])->update($updata);

        //发送回调
        $callback = new Notify();

        $callbackre = $callback->sendCallBack($order['id'], $status, $pay_time);

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


        }else{

            //回调成功
            $callbackarray = [
                'is_callback'       => 1,
                'callback_time'     => time(),
                'callback_count'    => $order['callback_count']+1,
                'callback_content'  => $callbackre['content'],
            ];


        }

        $result = Db::name('order')->where('id',$order['id'])->update($callbackarray);
    }

    /**
     * 关掉通道码 下线
     *
     * @param $qrcode_id
     * @param string $remark
     */
    public function turnOffQrcode($qrcode_id, $remark = '云端掉线'){
        //掉线更新通道，把通道关了
        $offLineData = [
            'yd_is_diaoxian' => GroupQrcode::OFFLINE,
            'status'         => GroupQrcode::STATUS_OFF,
            'remark'         => $remark,
            'update_time'    => time(),
        ];

        GroupQrcode::where('id', $qrcode_id)->update($offLineData);
    }
}