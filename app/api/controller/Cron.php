<?php

namespace app\api\controller;

use app\common\controller\Api;
use think\facade\Db;
use think\facade\Config;
use app\common\library\Utils;
use app\common\library\Accutils;
use think\cache\driver\Redis;
use think\facade\Log;
use think\facade\Queue;
use app\common\controller\Jobs;
use think\Request;
use app\api\library\Alipay;

@set_time_limit(0);

/**
 * 示例接口
 */
class Cron extends Api
{

    //如果$noNeedLogin为空表示所有接口都需要登录才能请求
    //如果$noNeedRight为空表示所有接口都需要验证权限才能请求
    //如果接口已经设置无需登录,那也就无需鉴权了
    //
    // 无需登录的接口,*表示全部
    protected $noNeedLogin = ['*'];
    // 无需鉴权的接口,*表示全部
    protected $noNeedRight = ['*'];
    
    //支付宝监控
    public function alipay()
    {
        $list = Db::name('group_qrcode')->where(['acc_code'=>1007,'status'=>1])->select();
        if(empty($list)){
            echo('无云端通道');die;
        }
        
        
        $aliobj = new Alipay();
        foreach ($list as $row)
        {
            $cookie = base64_decode($row['cookie']);
            
            //if(empty($row['zfb_pid']))
            //{
            //    $beat = $aliobj->GetMyPID($cookie,$row['id']);
            //}
            //$beat = $aliobj->BaoHuo($cookie);
            //$m = $aliobj->GetMyMoney($cookie);
            $m = $aliobj->GetMyMoney_2($cookie);
           
            if($m['status']==true){
                $money = $m['money'];
            }else{
                //掉线
                Db::name('group_qrcode')->where('id', $row['id'])->update(['yd_is_diaoxian' => 0]);
                 $this->error('掉线了');
               
            }
            
            //$orders = $aliobj->getAliOrder($row['cookie'],$row['zfb_pid']);//获取订单请求
            //$orders = $aliobj->getAliOrderV2($cookie,$row['zfb_pid']);//获取订单请求
            
            $old_money = $row['money'];
            
            
            if($old_money != $money){
                
                //Db::name('group_qrcode')->where('id', $row['id'])->update(['money' => $money,'update_time'=>time()]);
                
                $order_count = Db::name('order')->where(['status'=>2,'qrcode_id'=>$row['id'],'pay_type'=>$row['acc_code']])
                ->where('expire_time','>',time())
                ->count();
                
                if($order_count>0){
                    
                    //$orders = $aliobj->getAliOrderV2($row['cookie'],$row['zfb_pid']);//获取订单请求
                    $orders = $aliobj->getAliOrderV2($cookie,$row['zfb_pid']);//获取订单请求
                    //$this->success($orders);
                    if($orders['status']==='deny') {
                        Db::name('group_qrcode')->where('id', $row['id'])->update(['status' => 0,'yd_is_diaoxian'=>0]);
                        //$this->sendsms($row['user_id'],$row['id']);
                        //continue;//请求频繁或者掉线
                    }
                    $_orderlist = empty($orders['result']['detail']) ? array(): $orders['result']['detail'];
                    $_order=[];
                    $orderrow=null;
                    foreach ($_orderlist as $order)
                    {
                           $orderrow=null;
                           $pay_money=$order['tradeAmount'];//⾦额
                           $pay_des=$order['transMemo'];//备注
                           $tradeNo=$order['tradeNo'];//⽀付宝订单号
                           if(!empty($pay_des))
                           {
                                $orderrow = Db::name('order')
                                ->where('trade_no',$pay_des)
                                ->where('status',2)
                                ->where('pay_type',$row['acc_code'])
                                ->where('amount',sprintf("%.2f",$pay_money))
                                ->where('expire_time','>',time())
                                ->order('id desc')->find();
                                
                                if(!empty($orderrow)){
                                    
                                    //修改订单状态，回调
                                    $orderrow = Db::name('order')
                                        ->where('id',$orderrow['id'])
                                        ->update(['status'=>1,'ordertime'=>time()]);
                                }
                            }
                       }
                }
            }
        }
        $this->success('请求成功');
    }
    
   
}