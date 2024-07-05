<?php


namespace app\common\library;

use app\admin\model\daifu\Dforder;
use app\admin\model\merchant\Merchant;
use app\admin\model\order\Order;
use app\admin\model\User;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Db;
use think\Exception;
use fast\Http;
use app\common\library\Utils;
use think\facade\Log;
use fast\Random;

class Notify
{
    //发送回调
    public function sendCallBack($orderid, $status, $pay_time) {
        
        $order     = Order::where(['id' => $orderid])->find();
        $merchant  = Merchant::where('id', $order['mer_id'])->find();
        $is_notify = Cache::get('is_notify');
        if(empty($is_notify)){
            return ['code' => 0, 'content' => '失败'];
        }
        
        //开始回调
        $postdata = [
            'mer_no'       => $merchant['number'],
            'amount'       => $order['amount'],
            'trade_no'     => $order['trade_no'],
            'out_trade_no' => $order['out_trade_no'],
            'order_status' => $status,
            'pay_time'     => $pay_time,
        ];

        $mysign = Utils::sign($postdata, $merchant['secret_key']);

        $postdata['sign'] = $mysign;

        //发送参数
        Utils::notifyLog($order['trade_no'], $order['out_trade_no'], $postdata);

        $options = [
            CURLOPT_HTTPHEADER => [
                'Content-Type:application/x-www-form-urlencoded',
            ]
        ];

        $result = Http::post($order['notify_url'], $postdata, $options);

        //回调回调
        Utils::notifyLog($order['trade_no'], $order['out_trade_no'], $result);

        if ($result == 'success') {
            return ['code' => 1, 'content' => $result];
        } else {
            return ['code' => 0, 'content' => $result];
        }

    }

    //代付发送回调
    public function sendDfCallBack($orderid, $status, $pay_time) {

        $order    = Dforder::where(['id' => $orderid])->find();
        $merchant = Merchant::where('id', $order['mer_id'])->find();

        //签名
        $post_data = [
            'mer_no'       => $merchant['number'],
            'amount'       => $order['amount'],
            'trade_no'     => $order['trade_no'],
            'out_trade_no' => $order['out_trade_no'],
            'status'       => $status,
            'order_time'   => $pay_time,
        ];

        if($merchant['secret_type'] == '1'){
            $mysign = Utils::sign($post_data, $merchant['secret_key']);
        }else{
            $sign_str = Utils::signStr($post_data,$merchant['secret_key']);
            $aes      = new Aes($merchant['secret_key'],'AES-256-ECB');//32位密钥
            $mysign   = $aes->encrypt($sign_str);
        }
        
        $post_data['sign'] = $mysign;

        //发送参数
        Utils::notifyLog($order['trade_no'], $order['out_trade_no'], $post_data);
        
        $result = Http::post($order['notify_url'], $post_data, []);

        //回调的返回
        Utils::notifyLog($order['trade_no'], $order['out_trade_no'], $result);
        
        if (trim($result) == 'success') {
            return ['code' => 1, 'content' => $result];
        } else {
            return ['code' => 0, 'content' => $result];
        }

    }

    /**
     * 代收 统一处理订单 发送回调
     *
     * @param $order
     * @param $status
     * @param $dealUserName
     * @param $options
     * @return Order|false
     */
    public function dealOrderNotify($order, $status, $dealUserName, $remark = '', $options=[]){

        //先修改订单状态 再发送回调
        $pay_time = time();
        $update_data = [
            'status'            =>  $status,
            'ordertime'         =>  $pay_time,
            'deal_ip_address'   =>  request()->ip(),
            'deal_username'     =>  $dealUserName,
        ];
        if (!empty($remark)){
            $update_data['remark'] = $remark;
        }

        if ($options){
            $update_data = array_merge($update_data, $options);
        }

        $result1 = Order::where('id', $order['id'])->update($update_data);
        if (!$result1) {
            Utils::notifyLog($order['trade_no'], $order['out_trade_no'], $dealUserName . '回调修改订单失败');
            return false;
        }

        //发送回调
        $callbackRes = $this->sendCallBack($order['id'], $status, $pay_time);

        if($callbackRes['code'] != 1){

            $callbackArray = [
                'is_callback'       => 2,
                'callback_time'     => time(),
                'callback_count'    => $order['callback_count']+1,
                'callback_content'  => $callbackRes['content'],
            ];
            
        }else{
            
            //回调成功
            $callbackArray = [
                'is_callback'       => 1,
                'callback_time'     => time(),
                'callback_count'    => $order['callback_count']+1,
                'callback_content'  => $callbackRes['content'],
            ];
        }
        
        $res = Db::name('order')->where('id', $order['id'])->update($callbackArray);
        
        $findAgent = Db::name('agent')->where('id',$order['agent_id'])->cache(true,60)->find();
        
        //每个代理单独控制提单是否扣款， 0不扣 1扣
        if ($findAgent['sub_order_rate'] == '0'){
            //统一码商判断走单费率规则
            MoneyLog::checkMoneyRateType($order['user_id'], $order['amount'], $order['fees'], $order['trade_no'], $order['out_trade_no'],'user');
            
            //商户 统一判断走单费率规则
            MoneyLog::checkMoneyRateType($order['mer_id'],$order['amount'], $order['mer_fees'], $order['trade_no'], $order['out_trade_no'],'merchant');
            
        }
        
        $findUser = User::find($order['user_id']);
        if ($findUser['is_commission'] == 1) {
            //返佣
            MoneyLog::userCommission($order['user_id'], $order['amount'], $order['out_trade_no'], $order['trade_no'], $order['pay_type']);
        }
        
        return $res;
    }
    
     /**
     * 代付 统一处理订单 发送回调
     *
     * @param $order
     * @param $status
     * @param $dealUserName
     * @param $options
     * @return Order|false
     */
    public function dealDfOrderNotify($order, $status, $dealUserName, $remark = '', $options=[]){

        //先修改订单状态 再发送回调
        $pay_time = time();
        $update_data = [
            'status'            =>  $status,
            'ordertime'         =>  $pay_time,
            'deal_ip_address'   =>  request()->ip(),
            'deal_username'     =>  $dealUserName,
        ];
        if (!empty($remark)){
            $update_data['remark'] = $remark;
        }

        if ($options){
            $update_data = array_merge($update_data, $options);
        }

        $result1 = Db::name('df_order')->where('id', $order['id'])->update($update_data);
        
        if (!$result1) {
            Utils::notifyLog($order['trade_no'], $order['out_trade_no'], $dealUserName . '回调修改订单失败');
            return false;
        }
        
        //订单类型:0=手动,1=自动
        if($order['order_type'] == '0'){
            return true;
        }
        
        //发送回调
        $callbackRes = $this->sendDfCallBack($order['id'], $status, $pay_time);

        if($callbackRes['code'] != 1){

            $callbackArray = [
                'is_callback'       => 2,
                'callback_count'    => $order['callback_count']+1,
                'callback_content'  => $callbackRes['content'],
            ];

        }else{

            //回调成功
            $callbackArray = [
                'is_callback'       => 1,
                'callback_time'     => time(),
                'callback_count'    => $order['callback_count']+1,
                'callback_content'  => $callbackRes['content'],
            ];

        }

        $res = Db::name('df_order')->where('id', $order['id'])->update($callbackArray);
        
        return $res;
    }
    
    
    private function curl_post($url, $postdata, $headerArr = '') {

        //初始化
        $curl = curl_init();
        //设置抓取的url
        curl_setopt($curl, CURLOPT_URL, $url);
        //设置头文件的信息作为数据流输出
        //curl_setopt($curl, CURLOPT_HEADER, 1);
        //设置获取的信息以文件流的形式返回，而不是直接输出。
        //curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        //设置post方式提交
        curl_setopt($curl, CURLOPT_POST, 1);
        //设置header参数格式是数组
        if ($headerArr) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headerArr);
        }
        //设置请求的cookie
        //curl_setopt($curl, CURLOPT_COOKIE, $Cookie);
        //设置请求的ua
        //curl_setopt($curl, CURLOPT_USERAGENT, $ua);
        //设置请求的referer
        //curl_setopt($curl, CURLOPT_REFERER, $refererurl);
        //post提交的数据
        curl_setopt($curl, CURLOPT_POSTFIELDS, $postdata);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        //执行命令
        $data = curl_exec($curl);
        //关闭URL请求
        curl_close($curl);
        //显示获得的数据
        return $data;

        /*return [
              'ret'  => 1,
              'msg'  => $data,
              'info' => '',
          ];*/


    }
}