<?php

namespace app\admin\controller\statistics;

use app\admin\model\bank\Applys;
use app\admin\model\order\Order;
use app\common\controller\Backend;
use fast\Date;
use think\Db;
use app\admin\model\User;

/**
 * 订单收入趋势图
 *
 * @icon fa fa-bar-chart
 */
class Record extends Backend
{

    /**
     * 
     */
    protected $model = null;
    protected $noNeedRight = [];
    protected $isSuperAdmin = false;

    /**
     * 查看
     */
    public function index()
    {
        if ($this->request->isPost()) {
            $date = $this->request->post('date', '');
            
            list($category, $incomeData, $payData, $balanceData, $extend) = $this->getIncomeStatisticsData($date);
            $statistics = ['category' => $category, 'incomeData' => $incomeData, 'payData' => $payData, 'balanceData' => $balanceData, 'extend' => $extend];
            
            $this->success('', '', $statistics);
        }
        list($category, $incomeData, $payData, $balanceData, $extend) = $this->getIncomeStatisticsData();
        $this->assignconfig('category', $category);
        $this->assignconfig('incomeData', $incomeData);
        $this->assignconfig('payData', $payData);
        $this->assignconfig('balanceData', $balanceData);
        $this->view->assign('extend', $extend);
        return $this->view->fetch();
    }

    /**
     * 获取收支统计数据
     * @param string $date
     * @return array
     */
    protected function getIncomeStatisticsData($date = '')
    {
        if ($date) {
            list($start, $end) = explode(' - ', $date);

            $starttime = strtotime($start);
            $endtime = strtotime($end);
        } else { // 默认是当月
            $starttime = Date::unixtime('month', 0, 'begin');
            $endtime = Date::unixtime('month', 0, 'end');
        }
        $totalseconds = $endtime - $starttime;

        $format = '%Y-%m-%d';
        if ($totalseconds > 86400 * 30 * 2) { // 大于两个月，则横坐标为以月为粒度 形式'Y-m'
            $format = '%Y-%m';
        } else {
            if ($totalseconds > 86400) { // 小于两个月 且大于一天，则横坐标以天为粒度 形式'Y-m-d'
                $format = '%Y-%m-%d';
            } else { // 小于一天，则横坐标为以小时为粒度 形式'H:00'
                $format = '%H:00';
            }
        }
        
        //订单金额
        $orderAmountList = Order::where('createtime', 'between time', [$starttime, $endtime])->where('status',1)
            ->field('createtime, SUM(amount) AS amount, DATE_FORMAT(FROM_UNIXTIME(createtime), "' . $format . '") AS pay_date')
            ->group('pay_date')
            ->select();
            
        //订单手续费
        $orderFeesList = Order::where('createtime', 'between time', [$starttime, $endtime])->where('status',1)
            ->field('createtime, SUM(fees) AS amount, DATE_FORMAT(FROM_UNIXTIME(createtime), "' . $format . '") AS pay_date')
            ->group('pay_date')
            ->select();

        $applyFeesList = Applys::where('createtime', 'between time', [$starttime, $endtime])->where('status',1)
            ->field('createtime, SUM(fees) AS amount, DATE_FORMAT(FROM_UNIXTIME(createtime), "' . $format . '") AS pay_date')
            ->group('pay_date')
            ->select();
            
            
        if ($totalseconds > 84600 * 30 * 2) { // 大于两个月，则横坐标为以月为粒度 形式'Y-m'
            $starttime = strtotime('last month', $starttime);
            while (($starttime = strtotime('next month', $starttime)) <= $endtime) {
                $column[] = date('Y-m', $starttime);
            }
        } else {
            if ($totalseconds > 86400) { // 小于两个月 且大于一天，则横坐标以天为粒度 形式'Y-m-d'
                for ($time = $starttime; $time <= $endtime;) {
                    $column[] = date("Y-m-d", $time);
                    $time += 86400;
                }
            } else { // 小于一天，则横坐标为以小时为粒度 形式'H:00'
                for ($time = $starttime; $time <= $endtime;) {
                    $column[] = date("H:00", $time);
                    $time += 3600;
                }
            }
        }
        
        //订单金额
        $list = array_fill_keys($column, 0);
        $allIncome = 0;
        foreach ($orderAmountList as $k => $v) {
            $list[$v['pay_date']] = round($v['amount'], 2);
            $allIncome += round($v['amount'], 2);
        }

        
        //支出 手续费
        $feesList = array_fill_keys($column, 0);
        $allPay = 0;
        foreach ($orderFeesList as $k => $v) {
            $feesList[$v['pay_date']] = round($v['amount'], 2);
            $allPay += round($v['amount'], 2);
        }

        //提现金额
        foreach ($applyFeesList as $k => $v) {
            $feesList[$v['pay_date']] = $feesList[$v['pay_date']] + round($v['amount'], 2);
            $allPay += round($v['amount'], 2);
        }
        
        
        
        $balanceList = array_fill_keys($column, 0);
        // 结余
        $allBalance = $allIncome - $allPay;
        foreach ($balanceList as $k => $v) {
            $balanceList[$k] = round($list[$k] - $feesList[$k], 2);
        }
        
        $category = array_keys($list);
        $incomeData = array_values($list);
        $feesData = array_values($feesList);
        $balanceData = array_values($balanceList);
        $extend = array('allIncome'=>$allIncome,'allPay'=>$allPay,'allBalance'=>$allBalance);
       
        return [$category, $incomeData, $feesData, $balanceData, $extend];
    }

}