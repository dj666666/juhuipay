<?php
namespace app\common\library;

use app\admin\model\agent\Agent;
use app\admin\model\merchant\Merchant;
use app\admin\model\moneylog\Agentmoneylog;
use app\admin\model\moneylog\Usermoneylog;
use app\admin\model\moneylog\Moneylog as merchantMoneyLog;
use app\admin\model\thirdacc\Useracc;
use app\admin\model\user\User;
use fast\Random;
use think\facade\Config;
use think\facade\Db;
use think\Exception;
use app\user\model\user\Userrelation;
use think\facade\Log;


class MoneyLog
{
    /**
     * 码商返佣
     * 给这个用户的每个上级都返佣
     *
     * @param $user_id
     * @param $amount
     * @param $out_trade_no
     * @param $trade_no
     * @param $acc_code
     * @return true|void
     */
    public static function userCommission($user_id, $amount, $out_trade_no, $trade_no, $acc_code){
        
        //判断是否开启返佣
        if (Config::get('site.referral') != 1) {
            return;
        }

        $userRelation = Userrelation::where('user_id',$user_id)->find();
        
        //自己是父级不返佣
        if(empty($userRelation) || $userRelation['parent_id'] == 0){
            return;
        }
        
        if ($amount == 0){
            return;
        }
        
        //当前用户的费率
        $userRate = Useracc::where(['user_id' => $user_id, 'acc_code' => $acc_code])->find();

		$preg = "/\d+/";
		preg_match_all($preg,$userRelation['parent_id_path'],$matchArr);
        $parent_id_arr = array_reverse($matchArr[0]);
        foreach ($parent_id_arr as $k => $v){
            
            //上级的费率
            $rate = Useracc::where(['user_id' => $v, 'acc_code' => $acc_code])->find();
            if($k == 0){
                //层级点位差距
                $diff_rate = $rate['rate'] - $userRate['rate'];
            }else{
                
                //找出和前一等级的差距 165找166 1找165
                $sec_rate = Useracc::where(['user_id' => $parent_id_arr[$k-1], 'acc_code' => $acc_code])->find();
                
                //层级点位差距
                $diff_rate = $rate['rate'] - $sec_rate['rate'];
                
            }
            
            //上级佣金
            $commissionAmount = bcmul($amount, $diff_rate, 2);
            
            //为0不返佣
            if ($commissionAmount == 0){
                continue;
            }
            //返佣
            self::userMoneyChange($v, $commissionAmount, 0, $trade_no, $out_trade_no, '佣金收入', 1, 0, true);
        }
        
        return true;
    }

    /**
     * 码商余额变更以及记录
     * 直接操作余额 提单扣余额，超时退回余额
     *
     *
     * @param $user_id
     * @param $amount
     * @param $rate 费率
     * @param $trade_no
     * @param $out_trade_no
     * @param $remark
     * @param $type 0减少 1增加
     * @param $is_automatic 0自动 1手动
     * @param $is_commission String 是否返佣 true是 false 否
     * @return bool
     */
    public static function userMoneyChange($user_id, $amount, $rate, $trade_no, $out_trade_no, $remark, $type, $is_automatic, $is_commission = false){
        
        if ($amount == 0){
            return false;
        }
        
        //退款时，判断有无扣除记录
        /*if($type == 1 && $is_automatic == 0 && $is_commission == false) {
            $findLog = Usermoneylog::where(['user_id' => $user_id, 'out_trade_no' => $out_trade_no])->find();
            if (!$findLog) {
                return false;
            }
        }*/
        
        
        
        Db::startTrans();
        try {

            $user = User::where('id', $user_id)->lock(true)->find();

            //type=1增加余额 0减少余额
            if ($type == 0){
                $new_money = bcsub($user['money'], $amount, 2);
                User::where('id', $user_id)->dec('money', $amount)->update();
            }else{
                $new_money = bcadd($user['money'], $amount, 2);
                User::where('id', $user_id)->inc('money', $amount)->update();
            }

            //添加余额记录
            $logData['agent_id']      = $user['agent_id'];
            $logData['user_id']       = $user_id;
            $logData['out_trade_no']  = $out_trade_no;
            $logData['trade_no']      = $trade_no;
            $logData['amount']        = $amount;
            $logData['before_amount'] = $user['money'];
            $logData['after_amount']  = $new_money;
            $logData['type']          = $type;
            $logData['remark']        = $remark;
            $logData['ip_address']    = request()->ip();
            $logData['create_time']   = time();
            $logData['is_automatic']  = $is_automatic;
            $logData['fy_user_id']    = $is_commission == true ? $user_id : 0;
            $logData['is_commission'] = $is_commission == true ? 1 : 0;

            Usermoneylog::create($logData);

            Db::commit();

            return true;

        } catch (\Exception $e) {
            Db::rollback();
            Log::debug('userMoneyChange----'. $e->getLine() . '----'.$e->getMessage());
            return false;
        }

    }

    /**
     * 码商余额变更以及记录
     * 直接操作余额 提单扣余额，超时退回余额
     *
     *
     * @param $user_id
     * @param $amount
     * @param $rate
     * @param $trade_no
     * @param $out_trade_no
     * @param $remark
     * @param $type 0减少 1增加
     * @param $is_automatic 0自动 1手动
     * @param $is_commission String 是否返佣 true是 false 否
     * @return bool
     */
    public static function userMoneyChangeByDf($user_id, $amount, $rate, $trade_no, $out_trade_no, $remark, $type, $is_automatic = 0, $is_commission = false){

        if ($amount == 0){
            return false;
        }

        Db::startTrans();
        try {
            
            $user = Db::name('user')->where('id', $user_id)->lock(true)->find();
            
            //type=1增加余额 0减少余额
            if ($type == 1 ){
                $new_money = bcadd($user['money'], $amount, 2);
            }else{
                $new_money = bcsub($user['money'], $amount, 2);
            }
            
            //修改码商余额
            $result = Db::name('user')->where(['id'=>$user_id])->update(['money'=>$new_money]);
            
            //添加余额记录
            $logData['agent_id']      = $user['agent_id'];
            $logData['user_id']       = $user_id;
            $logData['out_trade_no']  = $out_trade_no;
            $logData['trade_no']      = $trade_no;
            $logData['amount']        = $amount;
            $logData['before_amount'] = $user['money'];
            $logData['after_amount']  = $new_money;
            $logData['type']          = $type;
            $logData['remark']        = $remark;
            $logData['ip_address']    = request()->ip();
            $logData['create_time']   = time();
            $logData['is_automatic']  = $is_automatic;
            $logData['fy_user_id']    = $is_commission == true ? $user_id : 0;
            $logData['is_commission'] = $is_commission == true ? 1 : 0;
            $logData['is_df']         = 1;
            
            Usermoneylog::create($logData);
            
            Db::commit();
            
        } catch (\Exception $e) {
            Db::rollback();
            Log::debug('码商代付余额----'. $e->getLine() . '----'.$e->getMessage());
        }
        
        if($result !== false){
            return true;
        }else{
            return false;
        }
        
    }

    /**
     * 商户余额变更以及记录
     * 直接操作余额 提单扣余额，超时退回余额
     * 目前商户和余额都只有扣款和不扣款2个规则
     *
     * @param $mer_id
     * @param $amount
     * @param $rate
     * @param $trade_no
     * @param $out_trade_no
     * @param $remark
     * @param $type 操作类型 0减少 1增加
     * @param $is_automatic 0自动 1手动
     * @param $is_rate_type 订单费率控制 ["0不开启","1费率增加","2扣除费率"]
     * @return bool
     */
    public static function merchantMoneyChange($mer_id, $amount, $rate, $trade_no, $out_trade_no, $remark, $type, $is_automatic, $is_rate_type = 0){
        
        $mer_rate = Config::get('site.merchant_rate'); //商户费率 ["0不开启","1费率增加","2扣除费率"]
        if ($mer_rate == '0' && $is_rate_type == 0){
            return false;
        }
        
        
        /*//退款操作 且 自动类型时判断有无扣除记录
        if($type == 1 && $is_automatic == 0) {
            $findLog = merchantMoneyLog::where(['mer_id' => $mer_id, 'out_trade_no' => $out_trade_no])->find();
            if (!$findLog) {
                return false;
            }
        }*/
        
        if ($amount == 0){
            return false;
        }
        
        
        Db::startTrans();
        try {

            $user = Merchant::where('id', $mer_id)->lock(true)->find();

            //type=1增加余额 0减少余额
            if ($type == 0){
                $new_money = bcsub($user['money'], $amount, 2);
                Merchant::where('id', $mer_id)->dec('money', $amount)->update();
            }else{
                $new_money = bcadd($user['money'], $amount, 2);
                Merchant::where('id', $mer_id)->inc('money', $amount)->update();
            }

            //添加余额记录
            $logData['agent_id']      = $user['agent_id'];
            $logData['mer_id']        = $mer_id;
            $logData['out_trade_no']  = $out_trade_no;
            $logData['trade_no']      = $trade_no;
            $logData['amount']        = $amount;
            $logData['before_amount'] = $user['money'];
            $logData['after_amount']  = $new_money;
            $logData['type']          = $type;
            $logData['remark']        = $remark;
            $logData['ip_address']    = request()->ip();
            $logData['create_time']   = time();
            $logData['is_automatic']  = $is_automatic;

            merchantMoneyLog::create($logData);

            Db::commit();

            return true;

        } catch (\Exception $e) {
            Db::rollback();
            Log::debug('商户余额异常----'. $e->getLine() . '----'.$e->getMessage());
            return false;
        }

    }

    /**
     * 商户扣除余额并写入记录 代付用得
     *
     * @param $mer_id
     * @param $amount
     * @param $rate
     * @param $trade_no
     * @param $out_trade_no
     * @param $remark
     * @param $type
     * @param $is_automatic
     */
    public static function merchantMoneyChangeByDf($mer_id, $amount, $rate, $trade_no, $out_trade_no, $remark, $type, $is_automatic = 0){

        if ($amount == 0){
            return false;
        }
        
        $rate_type = Config::get('site.merchant_df_rate');
        if ($rate_type == '0'){
            return false;
        }
        
        Db::startTrans();
        try {
            
            $user = Db::name('merchant')->where('id', $mer_id)->lock(true)->find();
            
            //type=1增加余额 0减少余额
            $new_money = $type == 0 ? bcsub($user['money'], $amount, 2) : bcadd($user['money'], $amount, 2);
            
            Db::name('merchant')->where('id', $mer_id)->update(['money' => $new_money]);
            
            //添加余额记录
            $logData['agent_id']      = $user['agent_id'];
            $logData['mer_id']        = $mer_id;
            $logData['out_trade_no']  = $out_trade_no;
            $logData['trade_no']      = $trade_no;
            $logData['amount']        = $amount;
            $logData['before_amount'] = $user['money'];
            $logData['after_amount']  = $new_money;
            $logData['type']          = $type;
            $logData['remark']        = $remark;
            $logData['ip_address']    = request()->ip();
            $logData['create_time']   = time();
            $logData['is_automatic']  = $is_automatic;
            $logData['is_df']         = 1;
            
            Db::name('money_log')->insert($logData);
            
            Db::commit();

            return true;

        } catch (\Exception $e) {
            Db::rollback();
            Log::debug('商户代付余额----'. $e->getLine() . '----'.$e->getMessage());
            return false;
        }

    }

    /**
     * 代理余额变更以及记录
     * 直接操作余额 提单扣余额，超时退回余额
     * 目前商户和余额都只有扣款和不扣款2个规则
     *
     * @param $agent_id
     * @param $amount
     * @param $rate
     * @param $trade_no
     * @param $out_trade_no
     * @param $remark
     * @param $type 0减少 1增加
     * @param $is_automatic 0自动 1手动
     * @return bool
     */
    public static function agentMoneyChange($agent_id, $amount, $rate, $trade_no, $out_trade_no, $remark, $type, $is_automatic){

        $rate_type = Config::get('site.agent_rate');
        if ($rate_type == '0'){
            return false;
        }

        //退款时，判断有无扣除记录
        if($type == 1 && $is_automatic == 0) {
            $findLog = Agentmoneylog::where(['agent_id' => $agent_id, 'out_trade_no' => $out_trade_no])->find();
            if (!$findLog) {
                return false;
            }
        }
        
        if ($amount == 0){
            return false;
        }
        
        Db::startTrans();
        try {

            $user = Agent::where('id', $agent_id)->lock(true)->find();

            //type=1增加余额 0减少余额
            if ($type == 0){
                $new_money = bcsub($user['money'], $amount, 2);
                Agent::where('id', $agent_id)->dec('money', $amount)->update();
            }else{
                $new_money = bcadd($user['money'], $amount, 2);
                Agent::where('id', $agent_id)->inc('money', $amount)->update();
            }

            //添加余额记录
            $logData['agent_id']      = $agent_id;
            $logData['out_trade_no']  = $out_trade_no;
            //$logData['trade_no']      = $trade_no;
            $logData['amount']        = $amount;
            $logData['before_amount'] = $user['money'];
            $logData['after_amount']  = $new_money;
            $logData['type']          = $type;
            $logData['remark']        = $remark;
            $logData['ip_address']    = request()->ip();
            $logData['create_time']   = time();
            $logData['is_automatic']  = $is_automatic;

            Agentmoneylog::create($logData);

            Db::commit();

            return true;

        } catch (\Exception $e) {
            Db::rollback();
            Log::debug('agentMoneyChange----'. $e->getLine() . '----'.$e->getMessage());
            return false;
        }

    }


    /**
     * 统一判断扣款规则
     *
     *
     * @param $user_id
     * @param $amount
     * @param $rate
     * @param $trade_no
     * @param $out_trade_no
     * @param $remark
     * @param $user_ype
     * @return bool|void
     */
    public static function checkMoneyRule($user_id, $amount, $rate, $trade_no, $out_trade_no, $remark, $user_ype, $is_automatic){
        if (!in_array($user_ype, ['user', 'merchant', 'agent'])) {
            return false;
        }

        if ($user_ype == 'user') {
            $user_rate_type = Config::get('site.user_rate');
            if ($user_rate_type == '0') {
                return;
            }
            $change_type = $user_rate_type == 1 ? 1 : 0; //1增加 0减少

            $res = self::userMoneyChange($user_id, $amount, $rate, $trade_no, $out_trade_no, $remark, $change_type, $is_automatic);

        }

        if ($user_ype == 'merchant') {
            $user_rate_type = Config::get('site.merchant_rate');
            if ($user_rate_type == '0') {
                return;
            }
            $change_type = $user_rate_type == 1 ? 1 : 0; //1增加 0减少

            $res = self::merchantMoneyChange($user_id, $amount, $rate, $trade_no, $out_trade_no, $remark, $change_type,$is_automatic, 1);

        }

        if ($user_ype == 'agent') {
            $user_rate_type = Config::get('site.agent_rate');
            if ($user_rate_type == '0') {
                return;
            }
            $change_type = $user_rate_type == 1 ? 1 : 0; //1增加 0减少

            $res = self::agentMoneyChange($user_id, $amount, $rate, $trade_no, $out_trade_no, $remark, $change_type, $is_automatic);
        }

        return $res;

    }

    /**
     * 判断订单成功后费率规则
     *
     *
     * @param $user_id
     * @param $amount
     * @param $rate
     * @param $trade_no
     * @param $out_trade_no
     * @param $remark
     * @param $user_type
     * @return bool|void
     */
    public static function checkMoneyRateType($user_id, $amount, $rate, $trade_no, $out_trade_no, $user_type){
        if (!in_array($user_type, ['user', 'merchant', 'agent'])) {
            return false;
        }
        
        if ($user_type == 'user') {
            
            $user_rate = Config::get('site.rate_type');
            $rate_type = Config::get('site.user_rate_type');
            if ($user_rate == '0') {
                return;
            }
            
            $change_type = $user_rate == 1 ? 1 : 0; //1增加 0减少
            $remark      = $user_rate == 1 ? '增加费率' : '代收完成';
            
            if($rate_type == 0){
                $change_amount = $amount;
            }else if($rate_type == 1 ){
                $change_amount = bcadd($amount, $rate, 2);
            }else{
                $change_amount = bcsub($amount, $rate, 2);
            }
            
            $res = self::userMoneyChange($user_id, $change_amount, $rate, $trade_no, $out_trade_no, $remark, $change_type, 0, true);
        }

        if ($user_type == 'merchant') {
            
            $mer_rate  = Config::get('site.merchant_rate'); //[0"不开启",1"增加余额",2"扣除余额"]
            $rate_type = Config::get('site.merchant_rate_type');//[0"不开启",1"增加余额",2"扣除余额"]
            
            if ($mer_rate == 0) {
                return;
            }
            
            $change_type = $mer_rate == 1 ? 1 : 0; //1增加 0减少
            $remark      = $mer_rate == 1 ? '费率收入' : '扣除费率';
            
            if($rate_type == 0){
                $change_amount = $amount;
            }else if($rate_type == 1 ){
                $change_amount = bcadd($amount, $rate, 2);
            }else{
                $change_amount = bcsub($amount, $rate, 2);
            }
            
            $res = self::merchantMoneyChange($user_id, $change_amount, $rate, $trade_no, $out_trade_no, $remark, $change_type,0,1);

        }

        if ($user_type == 'agent') {
            $rate_type = Config::get('site.agent_rate_type');
            if ($rate_type == '0') {
                return;
            }
            $change_type = $rate_type == 1 ? 1 : 0; //1增加 0减少
            $remark      = $rate_type == 1 ? '费率收入' : '扣除费率';
            $res = self::agentMoneyChange($user_id, $amount, $rate, $trade_no, $out_trade_no, $remark, $change_type, 0);
        }

        return $res;

    }

    /**
     * 码商对应商户余额修改
     *
     * @param $user_id
     * @param $amount
     * @param $out_trade_no
     * @param $type
     * @param $remark
     * @param $is_automatic
     * @return bool
     */
    public static function userMerMoneyLog($user_id,$amount,$out_trade_no,$type,$remark,$is_automatic){


        $result = false;
        $result1 = false;

        Db::startTrans();
        try {

            $user = Db::name('user')->where('id',$user_id)->lock(true)->find();

            if ($type == 1 ){
                //增加
                $new_money = bcadd($user['mer_money'],$amount,2);
            }else{
                //减少
                $new_money = bcsub($user['mer_money'],$amount,2);
            }

            //修改码商对应商户余额
            $result = Db::name('user')->where(['id'=>$user_id])->update(['mer_money'=>$new_money]);

            $logData['user_id'] = $user_id;
            $logData['out_trade_no'] = $out_trade_no;
            $logData['amount'] = $amount;
            $logData['before_amount'] = $user['mer_money'];
            $logData['after_amount'] = $new_money;
            $logData['type'] = $type;
            $logData['create_time'] = time();
            $logData['remark'] = $remark;
            $logData['ip_address'] = request()->ip();
            $logData['is_automatic'] = $is_automatic;

            $result1 = Db::name('user_mer_money_log')->insert($logData);


            Db::commit();

        } catch (\Exception $e) {
            Db::rollback();

        }

        if($result !== false && $result1 !== false){
            return true;
        }else{
            return false;
        }

    }
    
    
}