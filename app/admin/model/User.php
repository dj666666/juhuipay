<?php

namespace app\admin\model;

use app\admin\model\moneylog\Userblockmoneylog;
use app\admin\model\moneylog\Usermoneylog;
use app\common\library\Token;
use app\common\model\MoneyLog;
use app\common\model\BaseModel;
use app\common\model\ScoreLog;

class User extends BaseModel
{
    // 表名
    protected $name = 'user';
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    //接单状态
    const RECEIVE_NO  = 1;  // 1接单
    const RECEIVE_OFF = 2; // 2不接单

    public function getOriginData()
    {
        return $this->origin;
    }

    /*public static function onBeforeUpdate($row)
    {
        $changed = $row->getChangedData();
        //如果有修改密码
        if (isset($changed['password'])) {
            if ($changed['password']) {
                $salt = \fast\Random::alnum();
                $row->password = \app\common\library\Auth::instance()->getEncryptPassword($changed['password'], $salt);
                $row->salt = $salt;
                Token::clear($row->id);
            } else {
                unset($row->password);
            }
        }

        $changedata = $row->getChangedData();
        if (isset($changedata['money'])) {
            $origin = $row->getOrigin();
            MoneyLog::create([
                'user_id' => $row['id'], 'money' => $changedata['money'] - $origin['money'],
                'before'  => $origin['money'], 'after' => $changedata['money'], 'memo' => '管理员变更金额',
            ]);
        }
        if (isset($changedata['score'])) {
            $origin = $row->getOrigin();
            ScoreLog::create(['user_id' => $row['id'], 'score' => $changedata['score'] - $origin['score'], 'before' => $origin['score'], 'after' => $changedata['score'], 'memo' => '管理员变更积分']);
        }
    }*/

    /**
     * 提单锁定余额 超时释放余额
     *
     * @param $user_id
     * @param $amount
     * @param $rate
     * @param $trade_no
     * @param $out_trade_no
     * @param $type
     * @return void
     */
    public function blockUserMoneyBySubOrder($user_id, $amount, $rate, $trade_no, $out_trade_no, $type = 0){
        //计算费率
        $user_fees = bcmul($amount, $rate, 2);

        //单子金额锁定
        $user = $this->where('id', $user_id)->find();

        //type=1订单未付释放 0进单锁定余额
        if ($type == 1){
            //锁定金额扣除 加入到余额中
            $remark          = '订单扣除';
            $new_money       = bcadd($user['money'], $amount, 2);
            $new_block_money = bcsub($user['block_money'], $amount, 2);
            $this->where('id', $user_id)->dec('block_money', $amount)->update();
        }else{
            //余额中扣除 加入到锁定金额
            $remark          = '订单增加';
            $new_money       = bcsub($user['money'], $amount, 2);
            $new_block_money = bcadd($user['block_money'], $amount, 2);
            $this->where('id', $user_id)->inc('block_money', $amount)->update();
        }

        $this->where('id', $user_id)->update(['money' => $new_money]);

        //添加押金余额记录
        $logData['agent_id']        = $user['agent_id'];
        $logData['user_id']         = $user_id;
        $logData['out_trade_no']    = $out_trade_no;
        $logData['amount']          = $amount;
        $logData['before_amount']   = $user['block_money'];
        $logData['after_amount']    = $new_block_money;
        $logData['type']            = $type;
        $logData['remark']          = $remark;
        $logData['ip_address']      = request()->ip();

        Userblockmoneylog::create($logData);

    }


    /**
     * 提单扣余额，超时退回余额
     * 直接操作余额
     *
     *
     * @param $user_id
     * @param $amount
     * @param $rate
     * @param $trade_no
     * @param $out_trade_no
     * @param $remark
     * @param $type 0减少 1增加
     * @return bool
     */
    public function blockUserMoneyBySubOrderV2($user_id, $amount, $rate, $trade_no, $out_trade_no, $remark, $type){
        
        //退款时，判断有无扣除记录
        if($type == 1) {
            $findLog = Usermoneylog::where(['user_id' => $user_id, 'out_trade_no' => $out_trade_no])->find();
            if (!$findLog) {
                return false;
            }
        }
        
        //计算费率
        $user_fees = bcmul($amount, $rate, 2);

        $user = $this->where('id', $user_id)->find();

        //type=1订单未付释放 0进单锁定余额
        if ($type == 0){
            $new_money = bcsub($user['money'], $amount, 2);
            $this->where('id', $user_id)->dec('money', $amount)->update();
        }else{
            $new_money = bcadd($user['money'], $amount, 2);
            $this->where('id', $user_id)->inc('money', $amount)->update();
        }

        //添加余额记录
        $logData['agent_id']      = $user['agent_id'];
        $logData['user_id']       = $user_id;
        $logData['out_trade_no']  = $out_trade_no;
        $logData['amount']        = $amount;
        $logData['before_amount'] = $user['money'];
        $logData['after_amount']  = $new_money;
        $logData['type']          = $type;
        $logData['remark']        = $remark;
        $logData['ip_address']    = request()->ip();
        $logData['create_time']   = time();

        Usermoneylog::create($logData);
        return true;
    }


    public function getGenderList()
    {
        return ['1' => __('Male'), '0' => __('Female')];
    }

    public function getStatusList()
    {
        return ['normal' => __('Normal'), 'hidden' => __('Hidden')];
    }

    public function group()
    {
        return $this->belongsTo('UserGroup', 'group_id', 'id')->joinType('LEFT');
    }
}
