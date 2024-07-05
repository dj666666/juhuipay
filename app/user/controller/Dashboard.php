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

namespace app\user\controller;


use app\admin\model\moneylog\Usermoneylog;
use app\admin\model\order\Order;
use app\admin\model\user\User;
use app\common\library\Utils;
use app\common\model\Attachment;
use app\user\controller\thirdacc\Useracc;
use fast\Date;
use think\facade\Config;
use app\common\controller\UserBackend;
use think\facade\Db;


/**
 * 控制台.
 *
 * @icon fa fa-dashboard
 * @remark 用于展示当前系统中的统计数据、统计报表及重要实时数据
 */
class Dashboard extends UserBackend
{
    /**
     * 查看
     */
    public function index()
    {
        /*try {
            Db::execute("SET @@sql_mode='';");
        } catch (\Exception $e) {

        }
        $column = [];
        $starttime = Date::unixtime('day', -6);
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

        $user = User::find($this->auth->id);

        $success_where = ['user_id'=>$this->auth->id,'status'=>1];

        //今日总额
        $today_money = Order::where($success_where)->whereDay('createtime')->sum('amount');
        //今日订单数量
        $today_order = Order::where($success_where)->whereDay('createtime')->count();

        //昨日总额
        $yesterday_money = Order::where($success_where)->whereDay('createtime', 'yesterday')->sum('amount');
        //昨日订单数量
        $yesterday_order = Order::where($success_where)->whereDay('createtime', 'yesterday')->count();

        //总成功额
        $all_money = Order::where($success_where)->sum('amount');
        //总订单数量
        $all_order = Order::where($success_where)->count();

        //今日手续费
        $today_fees = Order::where($success_where)->whereDay('createtime')->sum('fees');
        //昨日手续费
        $yesterday_fees = Order::where($success_where)->whereDay('createtime', 'yesterday')->sum('fees');
        //总手续费
        $all_fees = Order::where($success_where)->sum('fees');

        //充值总手续费
        //$apply_fees = Db::name('applys')->where(['status'=>1])->sum('fees');
        //$apply_fees = Db::name('money_log')->where(['type'=>1,'is_recharge'=>1])->sum('fees');

        //今日总手续费
        //$today_apply_fees = Db::name('applys')->where(['status'=>1])->whereTime('createtime', 'today')->sum('fees');
        //$today_apply_fees = Db::name('money_log')->where(['type'=>1,'is_recharge'=>1])->whereTime('create_time', 'today')->sum('fees');

        //昨日总手续费
        //$yesterday_apply_fees = Db::name('applys')->where(['status'=>1])->whereTime('createtime', 'yesterday')->sum('fees');
        //$yesterday_apply_fees = Db::name('money_log')->where(['type'=>1,'is_recharge'=>1])->whereTime('create_time', 'yesterday')->sum('fees');

        //获取我的下级码商
        $userIds          = Utils::getMySecUser($this->auth->id);

        $sec_today_order      = Order::where('status', 1)->where('user_id', 'in', $userIds)->whereDay('createtime')->count();
        $sec_today_amount     = Order::where('status', 1)->where('user_id', 'in', $userIds)->whereDay('createtime')->sum('amount');
        //下级返佣收入
        $sec_today_fees       = Usermoneylog::where(['user_id' => $this->auth->id, 'type' => 1, 'is_commission' => 1])->whereDay('create_time')->sum('amount');

        $sec_yesterday_order  = Order::where('status', 1)->where('user_id', 'in', $userIds)->whereDay('createtime', 'yesterday')->count();
        $sec_yesterday_amount = Order::where('status', 1)->where('user_id', 'in', $userIds)->whereDay('createtime', 'yesterday')->sum('amount');
        $sec_yesterday_fees   = Usermoneylog::where(['user_id' => $this->auth->id, 'type' => 1, 'is_commission' => 1])->whereDay('create_time', 'yesterday')->sum('amount');


        $this->view->assign([
            //'yesterday_apply_fees'        => $yesterday_apply_fees,
            //'today_apply_fees'        => $today_apply_fees,
            //'apply_fees'        => $apply_fees,
            'money'                => $user['money'],
            'today_fees'           => $today_fees,
            'yesterday_fees'       => $yesterday_fees,
            'all_fees'             => $all_fees,
            'today_money'          => $today_money,
            'today_order'          => $today_order,
            'yesterday_money'      => $yesterday_money,
            'yesterday_order'      => $yesterday_order,
            'all_money'            => $all_money,
            'all_order'            => $all_order,
            'sec_today_order'      => $sec_today_order,
            'sec_today_amount'     => $sec_today_amount,
            'sec_today_fees'       => $sec_today_fees,
            'sec_yesterday_order'  => $sec_yesterday_order,
            'sec_yesterday_amount' => $sec_yesterday_amount,
            'sec_yesterday_fees'   => $sec_yesterday_fees,
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

        return $this->view->fetch();
    }
}
