<?php

namespace app\api\controller;

use app\admin\model\order\Order;
use app\admin\model\user\User;
use app\common\controller\Api;
use Exception;
use think\facade\Db;
use think\facade\Config;
use app\common\library\Aes;
use app\common\library\Utils;
use app\common\library\Notify;
use think\cache\driver\Redis;
use fast\Http;
use think\facade\Log;
use think\Request;
use think\facade\Queue;
use app\common\controller\Jobs;
use jianyan\excel\Excel;
use app\common\library\ThirdDf;
use app\common\library\MoneyLog;

/**
 * 代付平台回调
 */
class WithdrawNotify extends Api
{

    //如果$noNeedLogin为空表示所有接口都需要登录才能请求
    //如果$noNeedRight为空表示所有接口都需要验证权限才能请求
    //如果接口已经设置无需登录,那也就无需鉴权了
    //
    // 无需登录的接口,*表示全部
    protected $noNeedLogin = ['hnNotify','hnQueryOrder'];
    // 无需鉴权的接口,*表示全部
    protected $noNeedRight = ['*'];
    
    //浩南回调
    public function hnNotify(){

        $Timestamp    = $this->request->post('Timestamp'); //
        $AccessKey       = $this->request->post('AccessKey'); //商户ID
        $Amount = $this->request->post('Amount'); //用户支付实际金额
        $status        = $this->request->post('Status');//0验卡中，1处理中，2处理成功，3处理失败
        $OrderNo    = $this->request->post('OrderNo');//我方订单号
        $Comment  = $this->request->post('Comment');//订单描述
        $sign          = $this->request->post('Sign');//签名
        $post_data     = $this->request->post();

        Log::write('浩南回调----' . request()->ip() . '----' . json_encode($post_data, JSON_UNESCAPED_UNICODE), 'thirdNotify');

        if (empty($Timestamp) || empty($AccessKey) || strlen($status) < 1 || empty($OrderNo) || empty($sign)) {
            return '参数缺少';
        }

        try {
            
            Utils::notifyLog($OrderNo, $OrderNo, '浩南回调' . json_encode($post_data, JSON_UNESCAPED_UNICODE));
            
            $order = Db::name('df_order')->where(['out_trade_no' => $OrderNo])->find();

            if (empty($order)) {
                return '信息不存在';
            }

            //根据用户添加的销卡平台，获取这个通道对应的核销配置
            $dfAcc = DB::name('df_acc')->where(['merchant_no' => $AccessKey])->find();
            if (empty($dfAcc['merchant_no']) || empty($dfAcc['merchant_key'])) {
                $this->orderEerrorMsg($order['id'], 2, '配置错误');
                return '配置错误';
            }
            unset($post_data['Sign']);
            $mysign = Utils::signV5($post_data, 'SecretKey', $dfAcc['merchant_key']);
            if ($mysign != $sign) {
                $this->orderEerrorMsg($order['id'], 2, '签名错误');
                return '签名错误';
            }

            //1等待中，2支付中，4成功 8暂时失败 16撤单表示订单代付失败（商户余额已退回订单金额！）请用【当前状态判断订单状态为最终失败状态！！】

            if ($status == 1 || $status == 2) {
                return '待处理';
            }

            //支付成功的单子不处理
            if ($order['status'] == 1) {
                return '信息不存在!';
            }

            /*//如果不是提交成功的状态 不继续核销
            if ($order['third_hx_status'] != 1) {
                return '信息不存在!!';
            }*/

            /*//反查
            $queryOrder = ThirdDf::instance()->haoNanQueryOrder($OrderNo, $dfAcc);
            if(!$queryOrder['status']){
                $this->orderEerrorMsg($order['id'], $Comment);
                return '查单失败';
            }*/
            
            if(!in_array($status, [4, 16])){
                return 'false';
            }
            
            //1=完成,2=处理中,3=驳回,4=冲正 
            if ($status == 4) {
                $system_status = 1;
                //判断金额
                if($order['amount'] != $Amount){
                    $this->orderEerrorMsg($order['id'], 2, '金额错误'.$Amount);
                    return '金额错误';
                }
                
            }else{
                $system_status = 3;
                
                //处理退款
                MoneyLog::merchantMoneyChangeByDf($order['mer_id'], $order['amount'], $order['fees'], $order['out_trade_no'], $order['out_trade_no'], 'api失败退款',1);
                
            }
            
            //收到 只处理成功和失败的回调
            $notify = new Notify();
            $res    = $notify->dealDfOrderNotify($order, $system_status, '浩南', $Comment);
            if (!$res) {
                return '处理失败';
            }
            

        }catch (Exception $e) {

            Log::write('浩南回调异常----'.$OrderNo.'----'.$e->getFile().'----'. $e->getLine() .'----' .$e->getMessage(),'thirdNotify');
            return '异常失败，请联系客服';
        }

        return 'ok';
    }
    
    //下单反查
    public function hnQueryOrder(){
        $Amount    = $this->request->post('Amount'); //用户支付实际金额
        $OrderNo   = $this->request->post('OrderNo');//我方订单号
        $PayeeNo   = $this->request->post('PayeeNo');//收款人人账号
        $Random    = $this->request->post('Random');//随机值
        $sign      = $this->request->post('Sign');//签名
        $post_data = $this->request->post();

        Log::write('浩南查单----' . request()->ip() . '----' . json_encode($post_data, JSON_UNESCAPED_UNICODE), 'thirdNotify');

        if (empty(Random) || empty($OrderNo) || empty($PayeeNo) || empty($Random) || empty($sign)) {
            return '参数缺少';
        }
        
        $order = Db::name('df_order')->where(['out_trade_no' => $OrderNo])->find();
        if (empty($order)) {
            return '信息不存在';
        }

        //根据用户添加的销卡平台，获取这个通道对应的核销配置
        $dfAcc = DB::name('df_acc')->where(['id' => $order['third_df_id']])->find();
        
        if (empty($dfAcc['merchant_no']) || empty($dfAcc['merchant_key'])) {
            $this->orderEerrorMsg($order['id'], '配置错误');
            return '配置错误';
        }
        
        unset($post_data['Sign']);
        $mysign = Utils::signV5($post_data, 'SecretKey', $dfAcc['merchant_key']);
        if ($mysign != $sign) {
            $this->orderEerrorMsg($order['id'], '签名错误');
            return '签名错误';
        }
        
        if($order['bank_user'] == $PayeeNo){
            return '姓名错误';
        }
        
        return 'ok';
        
        
    }
    
    
    //订单信息统一修改
    public function orderEerrorMsg($order_id, $msg){
        
        Db::name('df_order')->where('id', $order_id)->update(['error_msg'=>$msg]);
    }

}