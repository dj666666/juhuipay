<?php

namespace app\common\library;

use app\admin\model\GroupQrcode;
use app\admin\model\order\Order;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Db;
use think\facade\Log;

class QrcodeService
{
    const SUC_USER_QRCODE = 200; //取码成功
    const EXCEPTION_ERROR = 500; //取码异常
    const NO_USER = 101; //没有合适的码商
    const NO_USER_QRCODE = 102; //码商没合适的码
    const NO_USER_BALANCE = 103; //码商余额不足

    protected $agent_id;
    protected $merchant;

    public function __construct($agent_id = 0, $merchant = null) {
        $this->agent_id = $agent_id;
        $this->merchant = $merchant;

    }

    //统一获取通道挂的码
    public function getAccQrcode($mer_id, $acc_code, $trade_no, $amount) {

        $qrcode = '';

        //走通用规则过滤码的通道
        $ty_acc_code = ['1001', '1002', '1003', '1004', '1005', '1006', '1007',
            '1008', '1009', '1010', '1011', '1014',
            '1015', '1016', '1017', '1018', '1019', '1020', '1021',
            '1022', '1023', '1024', '1025', '1026', '1027', '1028',
            '1029', '1030', '1033', '1034', '1036', '1037', '1038', '1039', '1040', '1041', '1042', '1043',
            '1045', '1046', '1047', '1050', '1051', '1052', '1053', '1054', '1055', '1056', '1057', '1058', '1059', '1060', '1061', '1062', '1063', '1064','1065', '1066','1067', '1080', '1081','1082','1083'
        ];

        //三方通道 无限制的通道
        $third_acc_code = [
            '1012', '1013', '1066', '1068', '1060'
        ];

        //特殊规则 取固定金额的码
        $special_acc_code = [
            '1031', '1032', '1070'
        ];

        //预产码库存规则 取固定金额的码
        $inventory_acc_code = [
            '1035'
        ];

        if (in_array($acc_code, $ty_acc_code)) {

            $qrcode = $this->getUserQrcode($mer_id, $acc_code, $trade_no, $amount);

        } elseif (in_array($acc_code, $special_acc_code)) {

            //$qrcode = $this->getQrcodeByAmount($mer_id, $acc_code, $amount, $trade_no);
            //$qrcode = $this->getQrcodeByAmountV2($userids, $acc_code, $amount);
            $qrcode = $this->getUserQrcode($mer_id, $acc_code, $trade_no, $amount, true);
            
            
        } elseif (in_array($acc_code, $inventory_acc_code)) {

            $qrcode = $this->getQrcodeByInventory($mer_id, $acc_code, $amount, $trade_no);

        } elseif (in_array($acc_code, $third_acc_code)) {

            $qrcode = $this->getThirdAccQrcode($mer_id, $acc_code, $amount, $trade_no);

        }

        return $qrcode;
    }

    public function getUserQrcode($mer_id, $acc_code, $trade_no, $amount, $is_specify_amount = false) {
        try {

            //获取取可用码商列表
            $user_ids = Utils::getMerUser($mer_id, $this->merchant);
            $userList = $this->filterUser($user_ids, $acc_code, $amount, $trade_no);

            if (empty($userList)) {
                return ['code' => self::NO_USER, 'msg' => '暂无可用码商', 'qrcode' => '', 'user' => ''];
            }

            $user_id_arr = array_column($userList, 'id');
            $userids     = implode(',', $user_id_arr);

            //取码商和码商的码
            $res = $this->getUserQrcodeByRecursion($mer_id, $userids, $userList, $acc_code, $amount, $trade_no, $is_specify_amount);

        } catch (\Exception $e) {
            // 这是进行异常捕获
            return ['code' => self::EXCEPTION_ERROR, 'msg' => $e->getFile() . '-' . $e->getLine() . '-' . $e->getMessage(), 'qrcode' => '', 'user' => ''];
        }

        return $res;
    }

    /**
     * 递归取符合规则的的码商和码商的码
     *
     * @param $mer_id string 商户id
     * @param $userids string 商户绑定的码商id
     * @param $userList array 可用码商列表
     * @param $acc_code string 通道编码
     * @param $amount string 订单金额
     * @param $trade_no string 商户订单号
     * @return array
     */
    public function getUserQrcodeByRecursion($mer_id, $userids, $userList, $acc_code, $amount, $trade_no, $is_specify_amount) {

        //取码商和码商的码

        if ($is_specify_amount) {
            $res = $this->getUserAndQrcodeByAmount($mer_id, $userids, $userList, $acc_code, $amount, $trade_no);
        } else {
            $res = $this->getUserWithQrcode($mer_id, $userids, $userList, $acc_code, $amount, $trade_no);
        }


        //没取到这个码商的码时，开始递归
        if ($res['code'] == self::NO_USER_QRCODE) {
            //记录码商无码日志
            $sub_error_data = [
                'agent_id'     => $this->agent_id,
                'out_trade_no' => 'suborder',
                'trade_no'     => $trade_no,
                'msg'          => '匹配码商【' . $res['user']['username'] . '】-暂无可用码',
                'content'      => '该码商无码取下一个',
            ];
            event('OrderError', $sub_error_data);

            $user_id_arr = explode(',', $userids);

            foreach ($user_id_arr as $k => $v) {
                //ids和useList列表去除没码的码商
                if ($v == $res['user']['id']) {
                    unset($user_id_arr[$k]);
                    unset($userList[$k]);
                }
            }

            //最后一个码商还是没码就跳出递归
            if (!empty($user_id_arr)) {
                $userids = implode(',', $user_id_arr);
                //键值对重新排序
                $userList = array_values($userList);

                $res = $this->getUserQrcodeByRecursion($mer_id, $userids, $userList, $acc_code, $amount, $trade_no, $is_specify_amount);
            }
        }

        return $res;
    }


    /**
     * 获取码商和码商的码
     *
     * @param $mer_id
     * @param $userids
     * @param $userList
     * @param $acc_code
     * @param $amount
     * @param $trade_no
     * @return array
     */
    public function getUserWithQrcode($mer_id, $userids, $userList, $acc_code, $amount, $trade_no) {

        $user     = '';
        $qrcode   = []; //返回的码
        $user_ids = explode(",", $userids);

        $returnData = [
            'code'   => self::NO_USER,
            'msg'    => '码商无码',
            'qrcode' => '',
            'user'   => ''
        ];

        $users_count = count($user_ids);

        //1.码商规则
        $user_robin_rule = Config::get('site.user_robin_rule');

        //1.1按随机算法模式
        if ($user_robin_rule == '1') {
            $user = $userList[mt_rand(0, $users_count - 1)];
        }

        //1.2按顺序算法
        if ($user_robin_rule == '2') {
            //找出这商户的单数
            $order_num = Order::where(['mer_id' => $mer_id])->whereDay('createtime')->count();
            $start     = $order_num % $users_count;
            $user      = $userList[$start];
        }
        if (empty($user)) {
            $returnData['code'] = self::NO_USER;
            $returnData['msg']  = '暂无可用码商';
            return $returnData;
        }

        //取到码商后，再取该码商的码
        $returnData['user'] = $user;


        //取码规则
        $acc_robin_rule = Config::get('site.acc_robin_rule');

        //1.取码随机模式
        if ($acc_robin_rule == '1') {

            $user_qrcode_list = GroupQrcode::where(['user_id' => $user['id'], 'acc_code' => $acc_code, 'status' => GroupQrcode::STATUS_ON])->select();
            if ($user_qrcode_list->isEmpty()) {
                $returnData['code'] = self::NO_USER_QRCODE;
                $returnData['msg']  = '失败-码商无码【' . $user['username'] . '】';
                return $returnData;
            }

            $qrcode_list_new = [];

            //过滤上限的码
            foreach ($user_qrcode_list as $k => $v) {
                $filter_res = $this->filterQrcode($user, $v, $amount, $trade_no, $acc_code);
                if ($filter_res) {
                    $qrcode_list_new[] = $v;
                }
            }
            $qrcode_count = count($qrcode_list_new);

            if ($qrcode_count <= 0) {
                $returnData['code'] = self::NO_USER_QRCODE;
                $returnData['msg']  = '失败-码商无可用码1-【' . $user['username'] . '】';
                return $returnData;
            }

            $qrcode = $qrcode_list_new[mt_rand(0, $qrcode_count - 1)];
        }

        //2.取码顺序模式
        if ($acc_robin_rule == '2') {

            $qrcode_list = GroupQrcode::where(['user_id' => $user['id'], 'status' => GroupQrcode::STATUS_ON, 'acc_code' => $acc_code,])->order('id asc')->select();
            if ($qrcode_list->isEmpty()) {
                $returnData['code'] = self::NO_USER_QRCODE;
                $returnData['msg']  = '失败-码商无码【' . $user['username'] . '】';
                return $returnData;
            }

            //查询今日总订单数
            $order_count_today = Order::where(['user_id' => $user['id'], 'pay_type' => $acc_code])->whereDay('createtime')->count();
            if ($order_count_today < 1) {
                $order_count_today = 1;
            }

            $qrcode_list_new = [];

            foreach ($qrcode_list as $k => $v) {
                $filter_res = $this->filterQrcode($user, $v, $amount, $trade_no, $acc_code);
                if ($filter_res) {
                    $qrcode_list_new[] = $v;
                }
            }

            $qrcode_count = count($qrcode_list_new);
            if ($qrcode_count < 1) {
                $returnData['code'] = self::NO_USER_QRCODE;
                $returnData['msg']  = '失败-码商无可用码2';
                return $returnData;
            }

            $qrcode_ids = array_column($qrcode_list_new, 'id');
            $start      = $order_count_today % $qrcode_count;
            $qrcode     = GroupQrcode::where('id', 'in', $qrcode_ids)->order('id asc')->limit($start, 1)->select()->toArray();
            $qrcode     = $qrcode[0];

        }

        if (empty($qrcode)) {
            $returnData['code'] = self::NO_USER_QRCODE;
            $returnData['msg']  = '失败-码商无可用码3';
            return $returnData;
        }

        //成功取到码
        $returnData['code'] = self::SUC_USER_QRCODE;
        $returnData['msg']  = '取码成功';

        $returnData['qrcode'] = $qrcode;

        return $returnData;
    }

    /**
     * 过滤不符合规则的码子
     *
     * @param $user_id
     * @param $qrcode
     * @return bool
     */
    public function filterQrcode($user, $qrcode, $amount, $trade_no, $acc_code = '') {

        if ($qrcode['order_max_amount'] != 0 && $amount > $qrcode['order_max_amount']) {
            //记录无码日志
            $sub_error_data = [
                'agent_id'     => $this->agent_id,
                'out_trade_no' => 'suborder',
                'trade_no'     => $trade_no,
                'msg'          => '失败-码商【' . $user['username'] . '】' . $qrcode['name'],
                'content'      => '本单金额' . $amount . '大于单笔最大金额' . $qrcode['order_max_amount'] . ',取下一个',
            ];
            event('OrderError', $sub_error_data);
            return false;
        }
        
        if ($qrcode['order_min_amount'] != 0 && $amount < $qrcode['order_min_amount']) {
            //记录无码日志
            $sub_error_data = [
                'agent_id'     => $this->agent_id,
                'out_trade_no' => 'suborder',
                'trade_no'     => $trade_no,
                'msg'          => '失败-码商【' . $user['username'] . '】' . $qrcode['name'],
                'content'      => '本单金额' . $amount . '小于单笔最小金额' . $qrcode['order_min_amount'] . ',取下一个',
            ];
            event('OrderError', $sub_error_data);
            return false;
        }

        /*$num = Order::where(['qrcode_id' => $qrcode['id']])
            ->whereDay('createtime')
            ->count();*/
        /*if($qrcode['max_order_num'] != 0 && $num >= $qrcode['max_order_num']){
            $remark = '达到笔数上限关停';
            //关停该码
            GroupQrcode::where('id',$qrcode['id'])->update(['status'=>GroupQrcode::STATUS_OFF,'remark'=>$remark,'update_time'=>time()]);

            return false;
        }*/

        $success_num = Order::where(['status' => Order::STATUS_COMPLETE, 'qrcode_id' => $qrcode['id']])
            ->whereDay('createtime')
            ->count();
        
        if ($qrcode['success_order_num'] != 0 && $success_num >= $qrcode['success_order_num']) {
            //$remark = '达到成功笔数关停';
            //关停该码
            GroupQrcode::where('id', $qrcode['id'])->update(['status' => GroupQrcode::STATUS_OFF, 'update_time' => time()]);
            return false;
        }

        //账单模式特殊规则，取到了码去发起账单，并且超时未付，才算一次失败
        if ($acc_code == '1061') {
            $fail_num = Order::where(['status' => Order::STATUS_FAIL, 'qrcode_id' => $qrcode['id']])
                //->where('zfb_nickname', '<>', '')
                ->where('is_gmm_close', 1)
                ->whereDay('createtime')
                ->count();
        } else {
            $fail_num = Order::where(['status' => Order::STATUS_FAIL, 'qrcode_id' => $qrcode['id']])
                ->whereDay('createtime')
                ->count();
        }
        
        if ($qrcode['fail_order_num'] != 0 && $fail_num >= $qrcode['fail_order_num']) {
            //$remark = '达到失败笔数关停';
            //关停该码
            GroupQrcode::where('id', $qrcode['id'])->update(['status' => GroupQrcode::STATUS_OFF, 'update_time' => time()]);
            return false;
        }


        //该码今日成功金额
        $money = Order::where(['status' => Order::STATUS_COMPLETE, 'qrcode_id' => $qrcode['id']])
            ->whereDay('createtime')
            ->sum('amount');
        if ($qrcode['max_money'] != 0 && $money >= $qrcode['max_money']) {
            //$remark = '达到成功金额关停';
            //关停该码
            GroupQrcode::where('id', $qrcode['id'])->update(['status' => GroupQrcode::STATUS_OFF, 'update_time' => time()]);
            return false;
        }
        
        
        //找这个码最后一单
        $order = Order::where(['user_id' => $user['id'], 'qrcode_id' => $qrcode['id']])->whereDay('createtime','today')->order('id desc')->find();
        if ($order && $qrcode['qrcode_interval'] > 0) {
            if ($order['status'] != Order::STATUS_COMPLETE) {
                //判断单码拉单间隔时间
                if ((time() - $order['createtime']) < $qrcode['qrcode_interval']) {
                    $n_time = time();
                    $it     = time() - $order['createtime'];
                    $str    = '间隔'.$it.'小于'.$qrcode['qrcode_interval'];
                    Log::write($qrcode['name'].'-'. $qrcode['id'].'-'. $n_time .'-'. $order['createtime'].'-'.$str, 'waring');
                    return false;
                }else{
                    $n_time = time();
                    $it     = time() - $order['createtime'];
                    $str    = '间隔'.$it.'大于'.$qrcode['qrcode_interval'];
                    Log::write($qrcode['name'].'-'. $qrcode['id'].'-'. $n_time.'-'. $order['createtime'].'-'.$str, 'waring');
                }
            }
        }

        //判断如果本单金额+该码已收金额 > 成功金额上限的话就跳过该码，给下一个满足能收这个金额的码
        $yushou_money = $money + $amount;
        if ($qrcode['max_money'] != 0 && $yushou_money > $qrcode['max_money']) {
            //记录无码日志
            $sub_error_data = [
                'agent_id'     => $this->agent_id,
                'out_trade_no' => 'suborder',
                'trade_no'     => $trade_no,
                'msg'          => '失败-码商【' . $user['username'] . '】' . $qrcode['name'],
                'content'      => '已收金额' . $money . '+本单金额' . $amount . '超出成功金额上限' . $qrcode['max_money'] . ',取下一个',
            ];
            event('OrderError', $sub_error_data);
            
            if (($qrcode['max_money'] - $money) < $qrcode['order_min_amount']) {
                //关停该码
                GroupQrcode::where('id', $qrcode['id'])->update(['status' => GroupQrcode::STATUS_OFF, 'update_time' => time()]);
            }
            
            return false;
        }

        return true;
    }

    /**
     * 无需过滤规则的码 指定金额
     * V1版本：按随机，顺序规则取指定金额的码，可用于相同金额挂了多个码
     *
     * @param $user_id
     * @param $pay_type
     * @param $acc_robin_rule
     * @param $amount
     * @param $trade_no
     * @return array
     */
    public function getQrcodeByAmount($mer_id, $pay_type, $amount, $trade_no) {

        //先取码商
        $getUserRes = $this->getMerchantUser($mer_id, $pay_type, $amount, $trade_no);
        if ($getUserRes['code'] != self::SUC_USER_QRCODE) {
            return $getUserRes;
        }

        $user = $getUserRes['user'];

        //成功取到码商
        $returnData['user']   = $user;
        $returnData['qrcode'] = '';

        $acc_robin_rule = Config::get('site.acc_robin_rule');

        //开始取码

        //随机模式
        if ($acc_robin_rule == 1) {

            $qrcode_list = GroupQrcode::where([
                'user_id'       => $user['id'],
                'acc_code'      => $pay_type,
                'status'        => GroupQrcode::STATUS_ON,
                'tb_good_price' => $amount
            ])->select();

            $qrcode_count = count($qrcode_list);
            if ($qrcode_count < 1) {
                $returnData['code'] = self::NO_USER_QRCODE;
                $returnData['msg']  = '失败-码商无码【' . $user['username'] . '】';
                return $returnData;
            }

            $qrcode = $qrcode_list[mt_rand(0, $qrcode_count - 1)];
        }

        //顺序模式
        if ($acc_robin_rule == 2) {

            //查询今日总订单数
            $order_count_today = Order::where([
                'user_id'    => $user['id'],
                'pay_type'   => $pay_type,
                'pay_amount' => $amount
            ])->whereDay('createtime')->count();

            // 查询该用户所有通道数
            $count_alipay = GroupQrcode::where([
                'user_id'       => $user['id'],
                'acc_code'      => $pay_type,
                'status'        => GroupQrcode::STATUS_ON,
                'tb_good_price' => $amount
            ])->count();

            if ($count_alipay < 1) {
                $returnData['code'] = self::NO_USER_QRCODE;
                $returnData['msg']  = '失败-码商无码【' . $user['username'] . '】';
                return $returnData;
            }

            if ($order_count_today < 1) {
                $order_count_today = 1;
            }

            $start = $order_count_today % $count_alipay;

            $qrcodes = GroupQrcode::where([
                'user_id'       => $user['id'],
                'acc_code'      => $pay_type,
                'status'        => GroupQrcode::STATUS_ON,
                'tb_good_price' => $amount
            ])
                ->order('id desc')
                ->limit($start, 1)
                ->select();

            $qrcode = isset($qrcodes[0]) ? $qrcodes[0] : '';

            if (empty($qrcode)) {
                $returnData['code'] = self::NO_USER_QRCODE;
                $returnData['msg']  = '失败-码商无码【' . $user['username'] . '】';
                return $returnData;
            }
        }

        //成功取到码
        $returnData['code']   = self::SUC_USER_QRCODE;
        $returnData['msg']    = '取码成功';
        $returnData['user']   = $user;
        $returnData['qrcode'] = $qrcode;

        return $returnData;
    }

    /**
     * 无需过滤规则的码 指定金额
     * v2版本：无模式区分，直接取指定金额的码，只适用于每个金额挂一个码
     *
     * @param $user_id
     * @param $pay_type
     * @param $amount
     * @return array
     */
    public function getQrcodeByAmountV2($user_id, $pay_type, $amount) {

        $user = Db::name('user')->where('id', $user_id)->field('id,username,money,is_receive,rate')->find();

        $returnData['user']   = $user;
        $returnData['qrcode'] = '';

        $qrcode_count = GroupQrcode::where([
            'user_id'       => $user_id,
            'acc_code'      => $pay_type,
            'status'        => GroupQrcode::STATUS_ON,
            'tb_good_price' => $amount
        ])->count();

        if ($qrcode_count < 1) {
            $returnData['code'] = self::NO_USER_QRCODE;
            $returnData['msg']  = '失败-码商无码【' . $user['username'] . '】';
            return $returnData;
        }

        //取指定金额码
        $qrcode = GroupQrcode::where([
            'user_id'       => $user_id,
            'acc_code'      => $pay_type,
            'status'        => GroupQrcode::STATUS_ON,
            'tb_good_price' => $amount
        ])
            ->find();

        if (!$qrcode) {
            $returnData['code'] = self::NO_USER_QRCODE;
            $returnData['msg']  = '失败-码商无该金额码【' . $user['username'] . '】';
            return $returnData;
        }


        //成功取到码
        $returnData['code']   = self::SUC_USER_QRCODE;
        $returnData['msg']    = '取码成功';
        $returnData['user']   = $user;
        $returnData['qrcode'] = $qrcode;

        return $returnData;
    }

    /**
     * 获取码商和码商的码 取指定金额得码
     */
    public function getUserAndQrcodeByAmount($mer_id, $userids, $userList, $acc_code, $amount, $trade_no) {

        $user     = '';
        $qrcode   = []; //返回的码
        $user_ids = explode(",", $userids);

        $returnData = [
            'qrcode' => '',
            'user'   => ''
        ];

        $users_count = count($user_ids);

        //1.码商规则
        $user_robin_rule = Config::get('site.user_robin_rule');

        //1.1按随机算法模式
        if ($user_robin_rule == '1') {
            $user = $userList[mt_rand(0, $users_count - 1)];
        }

        //1.2按轮训顺序算法
        if ($user_robin_rule == '2') {
            //找出这商户的单数
            $order_num = Order::where(['mer_id' => $mer_id])->whereDay('createtime')->count();
            $start     = $order_num % $users_count;
            $user      = $userList[$start];
        }

        if (empty($user)) {
            $returnData['code'] = self::NO_USER;
            $returnData['msg']  = '暂无可用码商';
            return $returnData;
        }

        //取到码商后，再取该码商的码
        $returnData['user'] = $user;

        //取码规则
        $acc_robin_rule = Config::get('site.acc_robin_rule');

        //1.取码随机模式
        if ($acc_robin_rule == '1') {

            $user_qrcode_list = GroupQrcode::where(['user_id' => $user['id'], 'acc_code' => $acc_code, 'status' => GroupQrcode::STATUS_ON, 'tb_good_price' => $amount])->select();
            if ($user_qrcode_list->isEmpty()) {
                $returnData['code'] = self::NO_USER_QRCODE;
                $returnData['msg']  = '失败-码商无' . $amount . '金额码【' . $user['username'] . '】';
                return $returnData;
            }

            $qrcode_list_new = [];

            //过滤上限的码
            foreach ($user_qrcode_list as $k => $v) {
                $filter_res = $this->filterQrcode($user, $v, $amount, $trade_no, $acc_code);
                if ($filter_res) {
                    $qrcode_list_new[] = $v;
                }
            }
            $qrcode_count = count($qrcode_list_new);

            if ($qrcode_count <= 0) {
                $returnData['code'] = self::NO_USER_QRCODE;
                $returnData['msg']  = '失败-码商无符合规则码-【' . $user['username'] . '】';
                return $returnData;
            }

            $qrcode = $qrcode_list_new[mt_rand(0, $qrcode_count - 1)];
        }

        //2.取码顺序模式
        if ($acc_robin_rule == '2') {

            $qrcode_list = GroupQrcode::where(['user_id' => $user['id'], 'acc_code' => $acc_code, 'status' => GroupQrcode::STATUS_ON, 'tb_good_price' => $amount])->order('id asc')->select();
            if ($qrcode_list->isEmpty()) {
                $returnData['code'] = self::NO_USER_QRCODE;
                $returnData['msg']  = '失败-码商无' . $amount . '金额码【' . $user['username'] . '】';
                return $returnData;
            }

            //查询今日总订单数
            $order_count_today = Order::where(['user_id' => $user['id'], 'pay_type' => $acc_code])->whereDay('createtime')->count();
            if ($order_count_today < 1) {
                $order_count_today = 1;
            }

            $qrcode_list_new = [];

            foreach ($qrcode_list as $k => $v) {
                $filter_res = $this->filterQrcode($user, $v, $amount, $trade_no, $acc_code);
                if ($filter_res) {
                    $qrcode_list_new[] = $v;
                }
            }

            $qrcode_count = count($qrcode_list_new);
            if ($qrcode_count < 1) {
                $returnData['code'] = self::NO_USER_QRCODE;
                $returnData['msg']  = '失败-码商无符合规则码';
                return $returnData;
            }

            $qrcode_ids = array_column($qrcode_list_new, 'id');
            $start      = $order_count_today % $qrcode_count;
            $qrcode     = GroupQrcode::where('id', 'in', $qrcode_ids)->order('id asc')->limit($start, 1)->select()->toArray();
            $qrcode     = $qrcode[0];

        }

        if (empty($qrcode)) {
            $returnData['code'] = self::NO_USER_QRCODE;
            $returnData['msg']  = '失败-码商无可用码3';
            return $returnData;
        }

        //成功取到码
        $returnData['code'] = self::SUC_USER_QRCODE;
        $returnData['msg']  = '取码成功';

        $returnData['qrcode'] = $qrcode;

        return $returnData;
    }

    /**
     * 获取商户下面的码商-单个
     * V2版本：修改为从商户码商绑定关系获取码商
     *
     * @param $mer_id
     * @param $pay_type
     * @param $amount
     * @param $trade_no
     * @return array
     */
    public function getMerchantUser($mer_id, $pay_type, $amount, $trade_no) {

        $returnData = [
            'code'   => self::NO_USER,
            'msg'    => '暂无可用码商',
            'qrcode' => '',
            'user'   => ''
        ];


        $user     = '';
        $userList = [];
        //$user_ids = explode(",", $userids);
        $user_ids = Utils::getMerUser($mer_id);

        //先过滤一遍码商
        $userList = $this->filterUser($user_ids, $pay_type, $amount, $trade_no);

        if (empty($userList)) {
            return $returnData;
        }

        $users_count = count($userList);

        //码商规则
        $user_robin_rule = Config::get('site.user_robin_rule');

        //1.按随机算法模式
        if ($user_robin_rule == '1') {
            $user = $userList[mt_rand(0, $users_count - 1)];
        }

        //2按顺序算法
        if ($user_robin_rule == '2') {
            //找出这商户的单数
            $order_num = Order::where(['mer_id' => $mer_id, 'pay_type' => $pay_type])->whereDay('createtime')->count();
            $start     = $order_num % $users_count;
            $user      = $userList[$start];
        }

        if (empty($user)) {
            return $returnData;
        }

        $returnData = [
            'code'   => self::SUC_USER_QRCODE,
            'msg'    => '获取码商成功',
            'qrcode' => '',
            'user'   => $user,
        ];

        return $returnData;
    }

    /**
     * 通用码商过滤规则
     *
     * @param $user_ids
     * @param $acc_code
     * @param $amount
     * @param $trade_no
     * @return array
     */
    public function filterUser($user_ids, $acc_code, $amount, $trade_no) {
        $userList = [];
        foreach ($user_ids as $k1 => $v1) {
            
            $findUser = Db::name('user')->where(['id' => $v1, 'status' => 'normal'])->field('id,username,money,is_receive')->find();
            
            if (!$findUser) {
                continue;
            }
            
            //筛选一遍接单状态 1接单 2不接单
            if ($findUser['is_receive'] == 2) {
                continue;
            }
            
            $user_min_balance = Config::get('site.user_min_balance');
            
            if ($findUser['money'] < $user_min_balance) {
                //记录码商无码日志
                $sub_error_data = [
                    'agent_id'     => $this->agent_id,
                    'out_trade_no' => 'suborder',
                    'trade_no'     => $trade_no,
                    'msg'          => '匹配码商【' . $findUser['username'] . '】-余额不足'.$user_min_balance,
                    'content'      => '余额' . $findUser['money'] . '不足'.$user_min_balance,
                ];
                event('OrderError', $sub_error_data);
                continue;
            }
                
            
            
            //判断是否给码商开启该通道 并且配置费率
            $findUserAcc = Db::name('user_acc')->where(['user_id' => $v1, 'acc_code' => $acc_code, 'status' => 1])->find();
            if (empty($findUserAcc)) {
                //记录码商无码日志
                $sub_error_data = [
                    'agent_id'     => $this->agent_id,
                    'out_trade_no' => 'suborder',
                    'trade_no'     => $trade_no,
                    'msg'          => '匹配码商【' . $findUser['username'] . '】-通道未配置',
                    //'content'      => '通道'.$acc_code.'费率'.$findUserAcc['rate'],
                    'content'      => '通道' . $acc_code,
                ];
                event('OrderError', $sub_error_data);
                continue;
            }
            
            //检测码商余额 o不开启1增加2扣除
            if (Config::get('site.user_rate') == '2') {
                $change_amount = bcadd($amount, bcmul($amount, $findUserAcc['rate'], 2), 2);
                if ($findUser['money'] < $change_amount) {
                    //记录码商无码日志
                    $sub_error_data = [
                        'agent_id'     => $this->agent_id,
                        'out_trade_no' => 'suborder',
                        'trade_no'     => $trade_no,
                        'msg'          => '匹配码商【' . $findUser['username'] . '】-余额不足',
                        'content'      => '余额' . $findUser['money'] . '不足' . $change_amount,
                    ];
                    event('OrderError', $sub_error_data);
                    continue;
                }
            }
            //再判断这个码商有无该通道的码 防止出现了取到了码商 该码商又没有码的情况
            $qrcode_count = Db::name('group_qrcode')->where(['user_id' => $v1, 'acc_code' => $acc_code, 'status' => 1])->count();
            if ($qrcode_count <= 0) {
                continue;
            }
            
            //把费率加进去
            $findUser['rate'] = $findUserAcc['rate'];

            array_push($userList, $findUser);
        }
        return $userList;
    }

    //获取指定金额的码，预产码模式，
    public function getQrcodeByInventory($mer_id, $pay_type, $amount, $trade_no) {
        //先取码商
        $getUserRes = $this->getMerchantUser($mer_id, $pay_type, $amount, $trade_no);
        if ($getUserRes['code'] != self::SUC_USER_QRCODE) {
            return $getUserRes;
        }

        $user = $getUserRes['user'];

        //成功取到码商
        $returnData['user']   = $user;
        $returnData['qrcode'] = '';

        $acc_robin_rule = Config::get('site.acc_robin_rule');

        //开始取码

        //随机模式
        if ($acc_robin_rule == 1) {

            $qrcode_list = GroupQrcode::where([
                'user_id'       => $user['id'],
                'acc_code'      => $pay_type,
                'status'        => GroupQrcode::STATUS_ON,
                'tb_good_price' => $amount
            ])->select();

            $qrcode_count = count($qrcode_list);
            if ($qrcode_count < 1) {
                $returnData['code'] = self::NO_USER_QRCODE;
                $returnData['msg']  = '失败-码商无码【' . $user['username'] . '】';
                return $returnData;
            }

            $qrcode = $qrcode_list[mt_rand(0, $qrcode_count - 1)];
        }

        //顺序模式
        if ($acc_robin_rule == 2) {

            //查询今日总订单数
            $order_count_today = Order::where([
                'user_id'    => $user['id'],
                'pay_type'   => $pay_type,
                'pay_amount' => $amount
            ])->whereDay('createtime')->count();

            // 查询该用户所有通道数
            $count_alipay = GroupQrcode::where([
                'user_id'       => $user['id'],
                'acc_code'      => $pay_type,
                'status'        => GroupQrcode::STATUS_ON,
                'tb_good_price' => $amount
            ])->count();

            if ($count_alipay < 1) {
                $returnData['code'] = self::NO_USER_QRCODE;
                $returnData['msg']  = '失败-码商无码【' . $user['username'] . '】';
                return $returnData;
            }

            if ($order_count_today < 1) {
                $order_count_today = 1;
            }

            $start = $order_count_today % $count_alipay;

            $qrcodes = GroupQrcode::where([
                'user_id'       => $user['id'],
                'acc_code'      => $pay_type,
                'status'        => GroupQrcode::STATUS_ON,
                'tb_good_price' => $amount
            ])
                ->order('id desc')
                ->limit($start, 1)
                ->select();

            $qrcode = isset($qrcodes[0]) ? $qrcodes[0] : '';

            if (empty($qrcode)) {
                $returnData['code'] = self::NO_USER_QRCODE;
                $returnData['msg']  = '失败-码商无码【' . $user['username'] . '】';
                return $returnData;
            }
        }

        //成功取到码

        //再去产码库存表里取这个码下的预产好的码
        $tbQrcodeCount = Db::name('tb_qrcode')->where(['user_id' => $user['id'], 'group_qrcode_id' => $qrcode['id'], 'amount' => $amount, 'status' => 1, 'pay_status' => 0, 'is_lock' => 0])->count();

        $findTbQrcode = Db::name('tb_qrcode')->where([
            'user_id'         => $user['id'],
            'group_qrcode_id' => $qrcode['id'],
            'amount'          => $amount,
            'status'          => 1,
            'pay_status'      => 0,
            'is_lock'         => 0
        ])
            ->order('id desc')
            ->limit($start, 1)
            ->select();

        $findTbQrcode = isset($findTbQrcode[0]) ? $findTbQrcode[0] : '';

        if (!$findTbQrcode) {
            $returnData['code'] = self::NO_USER_QRCODE;
            $returnData['msg']  = '失败-码商无预产码【' . $user['username'] . '】';
            return $returnData;
        }

        $qrcode['pay_url']   = $findTbQrcode['pay_url'];
        $qrcode['tb_qrcode'] = [
            'id'      => $findTbQrcode['id'],
            'amount'  => $findTbQrcode['amount'],
            'pay_url' => $findTbQrcode['pay_url'],
        ];

        //这个码更新为未锁定状态
        Db::name('tb_qrcode')->where(['id' => $findTbQrcode['id']])->update(['is_lock' => 1, 'update_time' => time(), 'use_num' => $findTbQrcode['use_num'] + 1]);

        $returnData['code']   = self::SUC_USER_QRCODE;
        $returnData['msg']    = '取码成功';
        $returnData['user']   = $user;
        $returnData['qrcode'] = $qrcode;

        return $returnData;
    }


    //微信群红包
    public function wxGroupReaPay($user_id, $pay_type, $acc_robin_rule) {

        $qrcode_list = Db::name('group_qrcode')->where(['user_id' => $user_id, 'acc_code' => $pay_type, 'status' => 1, 'is_use' => 0])->select();
        $qrcode      = '';

        foreach ($qrcode_list as $key => $value) {

            //找这个码最后一单
            $order = Db::name('order')->where(['user_id' => $user_id, 'qrcode_id' => $value['id']])->order('id desc')->find();
            if (empty($order)) {
                $qrcode = $value;
                break;
            }
            //进行中 则换一个码
            if ($order['status'] == 2) {
                continue;
            }
            //完成 则判断是否超过1分钟 一个码完成了一分钟后才能开始下一单
            if ($order['status'] == 1) {
                if (time() - $order['ordertime'] >= Config::get('site.success_order_time')) {
                    $qrcode = $value;
                    break;
                }/*else{
                     var_dump('1分钟内');
                }*/
            }

            //未完成 则5分钟后释放
            if ($order['status'] == 3) {
                if (time() - $order['createtime'] >= Config::get('site.fail_order_time')) {
                    $qrcode = $value;
                    break;
                }
            }
        }

        return $qrcode;
    }


    //口令红包 只需要挂一个码用来拉单就行，所以不需要轮询
    public function zfbCodePay($user_id, $pay_type, $acc_robin_rule) {

        $qrcode_list = Db::name('group_qrcode')->where(['user_id' => $user_id, 'acc_code' => $pay_type, 'status' => 1])->select();

        $qrcode_count = count($qrcode_list);
        if ($qrcode_count < 1) {

            return '';
        }

        $qrcode = $qrcode_list[mt_rand(0, $qrcode_count - 1)];

        return $qrcode;
    }

    //支付宝个码uid 监控/云端协议
    public function zfbgm($user_id, $pay_type, $acc_robin_rule, $amount) {


        //随机模式
        if ($acc_robin_rule == 1) {

            $qrcode_list = Db::name('group_qrcode')->where(['user_id' => $user_id, 'acc_code' => $pay_type, 'status' => 1])->select();

            $qrcode_count = count($qrcode_list);
            if ($qrcode_count < 1) {
                return '';
            }

            $qrcode = $qrcode_list[mt_rand(0, $qrcode_count - 1)];
            return $qrcode;
        }


        //顺序模式
        if ($acc_robin_rule == 2) {

            //查询今日总订单数
            $order_count_today = Db::name('order')->where(['user_id' => $user_id, 'pay_type' => $pay_type])->whereDay('createtime')->count();

            // 查询该用户所有通道数
            $count_alipay = Db::name('group_qrcode')->where(['user_id' => $user_id, 'acc_code' => $pay_type, 'status' => 1])->count();

            if ($count_alipay < 1) {
                return '';
            }

            if ($order_count_today < 1) {
                $order_count_today = 1;
            }


            $start = $order_count_today % $count_alipay;

            $qrcode = Db::name('group_qrcode')->where(['user_id' => $user_id, 'acc_code' => $pay_type, 'status' => 1])->order('id desc')->limit($start, 1)->select();
            $qrcode = $qrcode[0];

        }


        return $qrcode;
    }

    /**
     * 三方通道取码
     *
     * 三方通道 只需要挂一个码就行，不需要检测规则和轮询
     *
     * @param $mer_id
     * @param $pay_type
     * @param $acc_robin_rule
     * @param $amount
     */
    public function getThirdAccQrcode($mer_id, $pay_type, $amount, $trade_no) {

        $returnData = [
            'code'   => self::NO_USER,
            'msg'    => '码商无码',
            'qrcode' => '',
            'user'   => ''
        ];

        //获取取可用码商列表
        $user_ids    = Utils::getMerUser($mer_id);
        $userList    = $this->filterUser($user_ids, $pay_type, $amount, $trade_no);
        $users_count = count($userList);

        if (empty($userList)) {
            $returnData['code'] = self::NO_USER;
            $returnData['msg']  = '暂无可用码商';
            return $returnData;
        }

        $user = $userList[mt_rand(0, $users_count - 1)];

        $returnData['user'] = $user;

        $qrcode_list = GroupQrcode::where(['user_id' => $user['id'], 'acc_code' => $pay_type, 'status' => GroupQrcode::STATUS_ON])->select();

        $qrcode_count = count($qrcode_list);
        if ($qrcode_count < 1) {
            $returnData['code'] = self::NO_USER_QRCODE;
            $returnData['msg']  = '码商无码';
            return $returnData;
        }

        $qrcode = $qrcode_list[mt_rand(0, $qrcode_count - 1)];

        $returnData['code']   = self::SUC_USER_QRCODE;
        $returnData['msg']    = '取码成功';
        $returnData['qrcode'] = $qrcode;

        return $returnData;
    }


    /**
     * 根据订单递归减少实付金额，实际回调用发起金额
     *
     * @param $td_id  int 通道ID
     * @param $amount string 用户发起金额
     *
     */
    public function getFloatMoney($td_id, $amount, $float_amount = 0.01, $is_first = false) {

        $amount   = round($amount, 2);
        if($is_first){
            $amount   -= $float_amount;//一进来直接浮动
        }
        
        $now_time = time();
        $order = Db::name('order')->where(['qrcode_id' => $td_id, 'status' => 2, 'pay_amount' => $amount])->find();
        if (!empty($order)) {
            $amount -= $float_amount;
            return $this->getFloatMoney($td_id, $amount, $float_amount = 0.01, $is_first = false);
        } else {
            return $amount;
        }

    }

    public function getFloatMoneyV2($agent_id, $td_id, $amount) {

        $amount = round($amount, 2);
        //$amount-=0.01;//一进来直接浮动
        $now_time = time();

        $order = Db::name('order')->where(['agent_id' => $agent_id, 'status' => 2, 'pay_amount' => $amount])->find();
        //$order = Db::name('order')->where(['qrcode_id'=>$td_id, 'pay_amount'=>$amount])->find();
        //halt($td_id,$amount,$order);
        if (!empty($order)) {
            $amount -= 0.01;
            return $this->getFloatMoneyV2($agent_id, $td_id, $amount);
        } else {
            return $amount;
        }

    }

    public function getFloatMoneyByShuzi($agent_id, $td_id, $amount) {

        $amount = round($amount, 2);
        //$amount-=0.1;//一进来直接浮动
        $now_time = time();
        $limit_time = $now_time - 300;
        $order = Db::name('order')->where(['qrcode_id' => $td_id, 'pay_amount' => $amount])->where('createtime','>', $limit_time)->find();

        if (!empty($order)) {
            $amount -= 0.01;
            return $this->getFloatMoneyByShuzi($agent_id, $td_id, $amount);
        } else {
            return $amount;
        }

    }
    
    /**
     * 根据订单递归减少实付金额，实际回调用发起金额 随机金额
     *
     * @param $td_id  int 通道ID
     * @param $amount string 用户发起金额
     *
     */
    public function getFloatMoneyByRandomUp($td_id, $amount, $min, $max) {

        $float      = $this->randFloat($min, $max);
        $pay_amount = bcadd($amount, $float, 2);

        $order = Db::name('order')->where(['status' => 2, 'qrcode_id' => $td_id, 'pay_amount' => $pay_amount])->find();

        if (!empty($order)) {
            return $this->getFloatMoneyByRandomUp($td_id, $amount, $min, $max);
        } else {
            return $pay_amount;
        }
        
    }
    
    //向下区间浮动
    public function getFloatMoneyByRandomDown($td_id, $amount, $min, $max) {

        $float      = $this->randFloat($min, $max);
        $pay_amount = bcsub($amount, $float, 2);

        $order = Db::name('order')->where(['status' => 2, 'qrcode_id' => $td_id, 'pay_amount' => $pay_amount])->find();

        if (!empty($order)) {
            return $this->getFloatMoneyByRandomDown($td_id, $amount, $min, $max);
        } else {
            return $pay_amount;
        }

    }
    
    /**
     * 生成随机小数
     */
    public function randFloat($min, $max) {

        $rand  = $min + mt_rand() / mt_getrandmax() * ($max - $min);
        $float = floatval(sprintf('%.2f', $rand));
        return $float;
    }

    /**
     * 根据订单递归增加实付金额，实际回调用发起金额
     *
     * @param $td_id  int 通道ID
     * @param $amount string 用户发起金额
     *
     */
    public function getFloatMoneyByUp($td_id, $amount, $float_amount = 0.01, $is_first = false) {

        $amount   = round($amount, 2);
        if($is_first){
            $amount   += $float_amount;//一进来直接浮动
        }
        
        $now_time = time();

        $order = Db::name('order')->where(['qrcode_id' => $td_id, 'status' => 2, 'pay_amount' => $amount])->where('expire_time', '>', $now_time)->find();

        if (!empty($order)) {
            $amount += $float_amount;
            return $this->getFloatMoneyByUp($td_id, $amount, $float_amount = 0.01, $is_first = false);
        } else {
            return $amount;
        }

    }

    /**
     * 根据订单递归减少实付金额，实际回调用发起金额
     *
     * @param $td_id  int 通道ID
     * @param $amount string 用户发起金额
     * @param $float_limit string 浮动范围
     *
     */
    public function getFloatMoneyByAmountLimit($acc_code, $td_id, $amount, $float_limit = 0.1) {

        $redis = Cache::store('redis');
        $key   = $acc_code . '_float_amount:' . $td_id . ':' . $amount;
        $check = $redis->exists($key, $amount);
        if ($check) {

            $now_amount = $redis->get($key);

            //重新设置缓存
            if (($amount - $now_amount) >= $float_limit) {
                $redis->set($key, $amount);//达到浮动范围 重新开始浮动
            } else {
                $float_amount = $now_amount - 0.01;
                $redis->set($key, $float_amount);
            }
        } else {

            //设置缓存
            $redis->set($key, $amount);

        }

        //设置过期时间
        $redis->expire($key, 60 * 5);

        $pay_amount = $redis->get($key);

        return $pay_amount;
    }
    
    
    //通道配置统一获取浮动
    public function getAccFloatAmount($floatjson, $amount, $qrcode_id){
        
        if(empty($floatjson)){
            return $amount;
        }

        $floatjson = json_decode($floatjson, true);
        if(empty($floatjson)){
            return $amount;
        }
        
        $floatjson = $floatjson[0];
        
        $is_first = $floatjson['is_first'] == 1 ? true : false;
        
        if($floatjson['type'] == '-' && $floatjson['end_amount'] == 0){
            
            //向下浮动
            $amount = $this->getFloatMoney($qrcode_id, $amount, $floatjson['float_amount'], $is_first);
            
        }elseif($floatjson['type'] == '-' && $floatjson['end_amount'] != 0){
            
            //向下区间
            $amount = $this->getFloatMoneyByRandomDown($qrcode_id, $amount, $floatjson['float_amount'], $floatjson['end_amount']);
            
        }elseif($floatjson['type'] == '+' && $floatjson['end_amount'] == 0){
            
            //向上浮动
            $amount = $this->getFloatMoneyByUp($qrcode_id, $amount, $floatjson['float_amount'], $is_first);
            
        }elseif($floatjson['type'] == '+' && $floatjson['end_amount'] != 0){
            
            //向上区间浮动
            $amount = $this->getFloatMoneyByRandomUp($qrcode_id, $amount, $floatjson['float_amount'], $floatjson['end_amount']);
            
        }
        
        
        return $amount;
        
    }
}