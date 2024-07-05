<?php
/**
 * *
 *  * ============================================================================
 *  * Created by PhpStorm.
 *  * User: Ice
 *  * 邮箱: ice@sbing.vip
 *  * 网址: https://sbing.vip
 *  * Date: 2019/9/19 下午3:33
 *  * ============================================================================.
 */

namespace app\agent\controller;

use app\admin\model\User;
use app\agent\model\Admin;
use app\common\model\Attachment;
use fast\Date;
use think\facade\Config;
use app\common\controller\AgentBackend;
use think\facade\Db;
use app\admin\model\order\Order;

/**
 * 控制台.
 *
 * @icon fa fa-dashboard
 * @remark 用于展示当前系统中的统计数据、统计报表及重要实时数据
 */
class Dashboard extends AgentBackend
{
    /**
     * 查看
     */
    public function index()
    {
        try {
            Db::execute("SET @@sql_mode='';");
        } catch (\Exception $e) {

        }
        $column = [];
        /*$starttime = Date::unixtime('day', -6);
        $endtime = Date::unixtime('day', 0, 'end');
        $joinlist = Db::name("user")->where('jointime', 'between time', [$starttime, $endtime])
            ->field('jointime, status, COUNT(*) AS nums, DATE_FORMAT(FROM_UNIXTIME(jointime), "%Y-%m-%d") AS join_date')
            ->group('join_date')
            ->select();
        for ($time = $starttime; $time <= $endtime;) {
            $column[] = date("Y-m-d", $time);
            $time += 86400;
        }
        $userlist = array_fill_keys($column, 0);
        foreach ($joinlist as $k => $v) {
            $userlist[$v['join_date']] = $v['nums'];
        }

        $dbTableList = Db::query("SHOW TABLE STATUS");*/
        
        $agent_id      = $this->auth->id;
        $success_where = ['agent_id'=>$agent_id, 'status'=>1];
        //$fail_where    = ['agent_id'=>$agent_id, 'status'=>3];
        
        //今日总额
        $today_money = Order::where($success_where)->whereDay('createtime')->sum('amount');
        //今日失败总额
        //$today_fail_money = Order::where($fail_where)->whereDay('createtime')->sum('amount');
        //今日订单数量
        $today_order = Order::where($success_where)->whereDay('createtime')->count();
        //今日失败订单数量
        //$today_fail = Order::where($fail_where)->whereDay('createtime')->count();


        //昨日总额
        $yesterday_money = Order::where($success_where)->whereDay('createtime', 'yesterday')->sum('amount');
        //昨日失败总额
        //$yesterday_fail_money = Order::where($fail_where)->whereDay('createtime', 'yesterday')->sum('amount');
        //昨日订单数量
        $yesterday_order = Order::where($success_where)->whereDay('createtime', 'yesterday')->count();
        //昨日失败订单数量
        //$yesterday_fail = Order::where($fail_where)->whereDay('createtime', 'yesterday')->count();

        //总额
        $all_money = Order::where($success_where)->sum('amount');
        //总失败金额
        //$all_fail_money = Order::where($fail_where)->sum('amount');
        //总订单数量
        $all_order = Order::where($success_where)->count();
        //总失败订单数量
        //$all_fail = Order::where($fail_where)->count();

        //今日手续费
        $today_fees = Order::where($success_where)->whereDay('createtime')->sum('fees');
        //昨日手续费
        $yesterday_fees = Order::where($success_where)->whereDay('createtime', 'yesterday')->sum('fees');
        //总手续费
        $all_fees = Order::where($success_where)->sum('fees');
        
        //全部码商的总余额
        $all_user_money = 0;
        $userList = User::where(['agent_id'=>$this->auth->id])->field('money')->select();
        foreach ($userList as $k => $v){
            $all_user_money += $v['money'];
        }

        $this->view->assign([
            //'yesterday_apply_fees'        => $yesterday_apply_fees,
            //'today_apply_fees'        => $today_apply_fees,
            //'apply_fees'        => $apply_fees,
            'all_user_money'        => $all_user_money,
            'today_fees'            => $today_fees,
            'yesterday_fees'        => $yesterday_fees,
            'all_fees'              => $all_fees,
            'today_money'           => $today_money,
            'today_order'           => $today_order,
            'yesterday_money'       => $yesterday_money,
            'yesterday_order'       => $yesterday_order,
            'all_money'             => $all_money,
            'all_order'             => $all_order,
            /*'totaluser'       => User::count(),
            'totaladdon'      => count(get_addon_list()),
            'totaladmin'      => Admin::count(),
            'totalcategory'   => \app\common\model\Category::count(),
            'todayusersignup' => User::whereTime('jointime', 'today')->count(),
            'todayuserlogin'  => User::whereTime('logintime', 'today')->count(),
            'sevendau'        => User::whereTime('jointime|logintime|prevtime', '-7 days')->count(),
            'thirtydau'       => User::whereTime('jointime|logintime|prevtime', '-30 days')->count(),
            'threednu'        => User::whereTime('jointime', '-3 days')->count(),
            'sevendnu'        => User::whereTime('jointime', '-7 days')->count(),
            'dbtablenums'     => count($dbTableList),
            'dbsize'          => array_sum(array_map(function ($item) {
                return $item['Data_length'] + $item['Index_length'];
            }, $dbTableList)),
            'attachmentnums'  => Attachment::count(),
            'attachmentsize'  => Attachment::sum('filesize'),
            'picturenums'     => Attachment::where('mimetype', 'like', 'image/%')->count(),
            'picturesize'     => Attachment::where('mimetype', 'like', 'image/%')->sum('filesize'),*/
        ]);

        /*$this->assignconfig('column', array_keys($userlist));
        $this->assignconfig('userdata', array_values($userlist));*/
        
        //订单数和订单额统计
        list($orderSaleCategory, $orderSaleAmount, $orderSaleNums) = $this->getSaleOrderData($agent_id, '');
        
        $this->assignconfig('orderSaleCategory', $orderSaleCategory);
        $this->assignconfig('orderSaleAmount', $orderSaleAmount);
        $this->assignconfig('orderSaleNums', $orderSaleNums);
        
        return $this->view->fetch();
    }
    
    public function ordertrend(){
        
        $date     = $this->request->post('date', '');
        $agent_id = $this->auth->id;
        list($orderSaleCategory, $orderSaleAmount, $orderSaleNums) = $this->getSaleOrderData($agent_id, $date);
        
        $statistics = [
            'orderSaleCategory' => $orderSaleCategory,
            'orderSaleAmount'   => $orderSaleAmount,
            'orderSaleNums'     => $orderSaleNums,
        ];
        $this->success('', '', $statistics);
    }
    
    /**
     * 获取订单销量销售额统计数据
     */
    protected function getSaleOrderData($agent_id, $date = '')
    {

        if ($date) {
            list($start, $end) = explode(' - ', $date);
            $starttime = strtotime($start);
            $endtime = strtotime($end);
        } else {
            $starttime = \fast\Date::unixtime('day', 0, 'begin');
            $endtime = \fast\Date::unixtime('day', 0, 'end');
        }
        $totalseconds = $endtime - $starttime;
        $format = '%Y-%m-%d';
        if ($totalseconds > 86400 * 30 * 2) {
            $format = '%Y-%m';
        } else {
            if ($totalseconds > 86400) {
                $format = '%Y-%m-%d';
            } else {
                $format = '%H:00';
            }
        }

        $orderList = Order::where('createtime', 'between time', [$starttime, $endtime])
            ->where(['agent_id' => $agent_id, 'status' => 1])
            ->field('createtime, status, COUNT(*) AS nums, SUM(amount) AS amount, DATE_FORMAT(FROM_UNIXTIME(createtime), "' . $format . '") AS paydate')
            ->group('paydate')
            ->select();

        if ($totalseconds > 84600 * 30 * 2) {
            $starttime = strtotime('last month', $starttime);
            while (($starttime = strtotime('next month', $starttime)) <= $endtime) {
                $column[] = date('Y-m', $starttime);
            }
        } else {
            if ($totalseconds > 86400) {
                for ($time = $starttime; $time <= $endtime;) {
                    $column[] = date("Y-m-d", $time);
                    $time += 86400;
                }
            } else {
                for ($time = $starttime; $time <= $endtime;) {
                    $column[] = date("H:00", $time);
                    $time += 3600;
                }
            }
        }

        $orderSaleNums = $orderSaleAmount = array_fill_keys($column, 0);
        foreach ($orderList as $k => $v) {
            $orderSaleNums[$v['paydate']] = $v['nums'];
            $orderSaleAmount[$v['paydate']] = round($v['amount'], 2);
        }
        $orderSaleCategory = array_keys($orderSaleAmount);
        $orderSaleAmount = array_values($orderSaleAmount);
        $orderSaleNums = array_values($orderSaleNums);
        return [$orderSaleCategory, $orderSaleAmount, $orderSaleNums];
    }
    
}
