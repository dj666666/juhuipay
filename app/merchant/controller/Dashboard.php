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

namespace app\merchant\controller;

use app\merchant\model\Admin;
use app\merchant\model\User;
use app\common\model\Attachment;
use fast\Date;
use think\facade\Config;
use app\common\controller\MerchantBackend;
use think\facade\Db;
use app\admin\model\order\Order;

/**
 * 控制台.
 *
 * @icon fa fa-dashboard
 * @remark 用于展示当前系统中的统计数据、统计报表及重要实时数据
 */
class Dashboard extends MerchantBackend
{
    /**
     * 查看
     */
    public function index()
    {

        $merchant = Db::name('merchant')->where('id',$this->auth->id)->find();

        $success_where = ['mer_id'=>$this->auth->id,'status'=>1];
        $fail_where    = ['mer_id'=>$this->auth->id,'status'=>3];

        //今日总额
        $today_money = Order::where($success_where)->whereTime('createtime', 'today')->sum('amount');
        //今日订单数量
        $today_order = Order::where($success_where)->whereTime('createtime', 'today')->count();
        //今日失败订单数量
        $today_fail = Order::where($fail_where)->whereTime('createtime', 'today')->count();

        //昨日总额
        $yesterday_money = Order::where($success_where)->whereTime('createtime', 'yesterday')->sum('amount');
        //昨日订单数量
        $yesterday_order = Order::where($success_where)->whereTime('createtime', 'yesterday')->count();
        //昨日失败订单数量
        $yesterday_fail = Order::where($fail_where)->whereTime('createtime', 'yesterday')->count();

        //总额
        $all_money = Order::where($success_where)->sum('amount');
        //总订单数量
        $all_order = Order::where($success_where)->count();
        //总失败订单数量
        $all_fail = Order::where($fail_where)->count();

        //今日手续费
        $today_fees = Order::where($success_where)->whereTime('createtime', 'today')->sum('fees');
        //昨日手续费
        $yesterday_fees = Order::where($success_where)->whereTime('createtime', 'yesterday')->sum('fees');
        //总手续费
        $all_fees = Order::where($success_where)->sum('fees');


        //充值总手续费
        //$apply_fees = Db::name('applys')->where(['status'=>1])->sum('fees');
        //$apply_fees = Db::name('money_log')->where(['type'=>1,'is_recharge'=>1])->sum('fees');
        $apply_fees = 0;
        //今日总手续费
        //$today_apply_fees = Db::name('applys')->where(['status'=>1])->whereTime('createtime', 'today')->sum('fees');
        //$today_apply_fees = Db::name('money_log')->where(['type'=>1,'is_recharge'=>1])->whereTime('create_time', 'today')->sum('fees');
        $today_apply_fees = 0;
        //昨日总手续费
        //$yesterday_apply_fees = Db::name('applys')->where(['status'=>1])->whereTime('createtime', 'yesterday')->sum('fees');
        //$yesterday_apply_fees = Db::name('money_log')->where(['type'=>1,'is_recharge'=>1])->whereTime('create_time', 'yesterday')->sum('fees');
        $yesterday_apply_fees = 0;

        $this->view->assign([
            'mer_money'            => $merchant['money'],
            'yesterday_apply_fees' => $yesterday_apply_fees,
            'today_apply_fees'     => $today_apply_fees,
            'apply_fees'           => $apply_fees,
            'today_fees'           => $today_fees,
            'yesterday_fees'       => $yesterday_fees,
            'all_fees'             => $all_fees,
            'today_money'          => $today_money,
            'today_order'          => $today_order,
            'today_fail'           => $today_fail,
            'yesterday_money'      => $yesterday_money,
            'yesterday_order'      => $yesterday_order,
            'yesterday_fail'       => $yesterday_fail,
            'all_money'            => $all_money,
            'all_order'            => $all_order,
            'all_fail'             => $all_fail,

        ]);

        //$this->assignconfig('column', array_keys($userlist));
        //$this->assignconfig('userdata', array_values($userlist));

        return $this->view->fetch();
    }
}
