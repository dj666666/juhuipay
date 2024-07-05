<?php

namespace app\api\controller;

use app\admin\model\order\Order;
use app\common\controller\Api;
use app\common\library\AlipaySdk;
use app\common\library\Utils;
use think\facade\Config;
use think\facade\Db;
use think\facade\Cache;
use fast\Http;

/**
 * 首页接口.
 */
class Telegram extends Api
{
    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];
    
    public function setNotify(){
        $type = $this->request->post('type');
        if($type == 0){
            Cache::set('is_notify', 0);
        }else{
            Cache::set('is_notify', 1);
        }
        
        return true;
    }
    
    
    public function tgbot(){
        
        $type     = $this->request->post('type');
        $mer_no   = $this->request->post('mer_no');
        $order_no = $this->request->post('order_no');
        
        if(empty($type) || empty($mer_no)){
            $this->error('参数缺少');
        }
        
        $merchant = Db::name('merchant')->where(['number' => $mer_no])->find();
        if(empty($merchant)){
            $this->error('商户不存在');
        }
        
        if($type == 'today_data' || $type == 'yestoday_data' ){
            
            $success_where[] = ['mer_id','=',$merchant['id']];
            $success_where[] = ['status','=','1'];
            
            if($type == 'today_data'){
                $start_time = strtotime('today');//今日开始时间
                $end_time   = mktime(0,0,0,date('m'),date('d')+1,date('Y'))-1;//今日结束时间
                $time       = date('Y-m-d');
                $success_where[] = ['createtime', 'between', [$start_time, $end_time]];
                
            }else if ($type == 'yestoday_data') {
                $start_time = strtotime('yesterday');//昨天开始时间
                $end_time   = strtotime('today') - 1;//昨天结束时间
                $time       = date('Y-m-d', strtotime('-1 day'));
                $success_where[] = ['createtime', 'between', [$start_time, $end_time]];
            }else{
                $time = '全部';
            }
            
            $allmoney = 0;
            $allnum   = 0;
            
            $suc_result = Db::name('order')
                        ->where($success_where)
                        ->field('pay_type, sum(amount) as amount, count(*) as count')
                        ->group('pay_type')
                        ->select()->toArray();
            
            foreach ($suc_result as &$row){
                $acc = Db::name('acc')->where('code', $row['pay_type'])->find();
                $row['acc_name'] = $acc['name'];
                
                $allmoney += $row['amount'];
                $allnum   += $row['count'];
                
            }
            
            $return_str = $this->formartData($suc_result, $time, $allmoney, $allnum);
            
            $this->success('成功', $return_str);
            
            
        }elseif ($type == 'query_order') {
            
            if(empty($order_no)){
                $this->error('参数缺少');
            }
            
            //找出订单
            $order = Db::name('order')->where(['trade_no'=>$order_no,'mer_id'=>$merchant['id']])->field('user_id,status,amount,trade_no,out_trade_no,createtime,ordertime,zfb_code as cardno, zfb_nickname as cardpwd')->find();
            
            if(!$order){
                $this->error('订单不存在');
            }
            
            
            $pattern = '/(\d{4})\d{6}(\d{4})/';
            $replacement = '$1******$2';
            
            $order['cardpwd'] = preg_replace($pattern, $replacement, $order['cardpwd']);
            
            $user = Db::name('user')->where('id',$order['user_id'])->find();
            
            $order = $this->formartOrder($order);
            
            $return_data = [
                'order'            => $order,
                'user_tg_name'     => $user['tg_name'],
                'user_tg_group_id' => $user['tg_group_id'],
                'mer_group_id'     => $merchant['tg_group_id']
            ];
            
            $this->success('success',$return_data);
            
        }
        
    }
    
    public function getmoney(){
        
        $type   = $this->request->post('type', 'today');

        $agentList = Db::name('agent')->select();
        
        $acc_hx_code = Config::get('mchconf.acc_hx_code');
        
        $success_where[] = ['status', '=', '1'];
        
        if($type == 'today'){
            $start_time = strtotime('today');//今日开始时间
            $end_time   = mktime(0,0,0,date('m'),date('d')+1,date('Y'))-1;//今日结束时间
            $time       = date('Y-m-d');
            $success_where[] = ['createtime', 'between', [$start_time, $end_time]];
            
        }else if ($type == 'yesterday') {
            $start_time = strtotime('yesterday');//昨天开始时间
            $end_time   = strtotime('today') - 1;//昨天结束时间
            $time       = date('Y-m-d', strtotime('-1 day'));
            $success_where[] = ['createtime', 'between', [$start_time, $end_time]];
        }else if ($type == 'tenday') {
            //上次结算到目前为止的10天
            /*$startDate = date('Y-m-d', strtotime("today -10 days")) . ' 00:00:00'; // 获取当前时间的前十天日期作为起始日期
            $endDate   = date('Y-m-d', strtotime("today -1 days")) . ' 23:59:59'; // 获取当前日期作为结束日期
            
            $start_time = strtotime($startDate);
            $end_time   = strtotime($endDate);*/
            
            
            $time_res = $this->getCurrentDatePeriod();
            
            $success_where[] = ['createtime', 'between', [$time_res['start'], $time_res['end']]];
            $time = date('Y-m-d H:i:s', $time_res['start']) . '—' . date('Y-m-d H:i:s', $time_res['end']);
        }else{
            //上一个周期的10天
            /*$startDate = date('Y-m-d', strtotime("today -10 days")) . ' 00:00:00'; // 获取当前时间的前十天日期作为起始日期
            $endDate   = date('Y-m-d', strtotime("today -1 days")) . ' 23:59:59'; // 获取当前日期作为结束日期
            
            $start_time = strtotime($startDate);
            $end_time   = strtotime($endDate);*/
            
            
            $time_res = $this->getCurrentDatePeriodV2();
            
            $success_where[] = ['createtime', 'between', [$time_res['start'], $time_res['end']]];
            $time = date('Y-m-d H:i:s', $time_res['start'])  . '—' . date('Y-m-d H:i:s', $time_res['end']);
        }
        
        $allmoney = 0;
        $content  = $time ."\n";
        $allrate  = 0;
        
        foreach ($agentList as $k => $v){
            if($v['username'] == 'agent1'){
                continue;
            }
            $amount = Db::name('order')->where('agent_id', $v['id'])->where($success_where)->sum('amount');
            
            $allmoney += $amount;
            $rate     = bcmul($amount, $v['rate'], 2);
            $allrate  += $rate;
            
            $content .= $v['username']."(" . $v['nickname'].")：" . $amount . "|" . $rate . "\n";
            
        }
        
        $content .= "合计：" .$allmoney . "|" . $allrate ."\n";
        
        $this->success('成功', ['content' => $content, 'amount' => $allmoney, 'rate'=> $allrate]);
            
    }
    
    public function getCurrentMonthTimePeriods() {
        // 获取当前年份和月份
        $year = date('Y');
        $month = date('m');
    
        $timePeriods = [];
    
        // 1-10号
        $start = strtotime("$year-$month-01");
        $end = strtotime("$year-$month-10 23:59:59");
        $timePeriods['1-10'] = ['start' => $start, 'end' => $end];
    
        // 11-20号
        $start = strtotime("$year-$month-11");
        $end = strtotime("$year-$month-20 23:59:59");
        $timePeriods['11-20'] = ['start' => $start, 'end' => $end];
    
        // 21-月末
        $start = strtotime("$year-$month-21");
        $end = strtotime("last day of $year-$month 23:59:59");
        $timePeriods['21-end'] = ['start' => $start, 'end' => $end];
    
        return $timePeriods;
    }
    
    public function getCurrentDatePeriod() {
        // 获取当前年份、月份和日期
        $year  = date('Y');
        $month = date('m');
        $day   = date('d');
    
        if ($day <= 10) {
            // 1-10号
            $start = strtotime("$year-$month-01");
            $end   = strtotime("$year-$month-10 23:59:59");
        } elseif ($day <= 20) {
            // 11-20号
            $start = strtotime("$year-$month-11");
            $end   = strtotime("$year-$month-20 23:59:59");
        } else {
            // 21-月末
            $start = strtotime("$year-$month-21");
            $end   = strtotime("last day of $year-$month 23:59:59");
        }
    
        return ['start' => $start, 'end' => $end];
    }
    
    public function getCurrentDatePeriodV2() {
        // 获取当前年份、月份和日期
        $year  = date('Y');
        $month = date('m');
        $day   = date('d');
        
        if ($day >=1 && $day <= 10) {
            $month = $month-1;
        }
        
        if ($day >=1 && $day <= 10) {
            // 21-月末
            $start = strtotime("$year-$month-21");
            $end   = strtotime("last day of $year-$month 23:59:59");
        } elseif ($day >=11 && $day <= 20) {
            // 1-10号
            $start = strtotime("$year-$month-01");
            $end   = strtotime("$year-$month-10 23:59:59");
        } else {
            // 11-20号
            $start = strtotime("$year-$month-11");
            $end   = strtotime("$year-$month-20 23:59:59");
        }
    
        return ['start' => $start, 'end' => $end];
    }
    
    //格式化今日跑量
    public function formartData($list, $time, $allmoney, $allnum){
        $str  = '统计时间：' . $time . "\n";
        
        foreach ($list as $k => $v){
            $str .= "通道：[" . $v['pay_type'] ."]" . $v['acc_name'] . "：" .$v['amount'] ."\n";
        }
        
        $str .= "合计：" . $allmoney;
        
        return $str;
    }
    
    
    public function formartOrder($result){
        
        $str  = "商户订单号：" . $result['trade_no'] ."\n";
        $str .= "金额：" . $result['amount'] ."\n";
        
        
        if ($result['status'] == 1) {
            $deal_msg = '已支付';
        }elseif($result['status'] == 2){
            $deal_msg = '等待支付';
        }else{
            $deal_msg = '超时未付';
        }
        
        
        $str .= "订单状态：" . $deal_msg ."\n";
        
        $str .= "创建时间：" . date('Y-m-d H:i:s', $result['createtime']) ."\n";
        
        $order_time = empty($result['ordertime']) ? "无" : date('Y-m-d H:i:s', $result['ordertime']);
        
        $str .= "处理时间：" . $order_time . "\n";
        
        return $str;
    }
    
    
}
