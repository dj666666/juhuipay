<?php

namespace app\admin\controller\order;

use app\admin\model\User;
use app\common\controller\Backend;
use think\exception\ValidateException;
use fast\Http;
use app\common\library\Accutils;
use app\common\controller\Jobs;
use app\common\controller\UserBackend;
use app\common\library\Notify;
use app\common\library\Utils;
use app\common\library\MoneyLog;
use think\facade\Config;
use think\facade\Db;
use think\facade\Queue;
use fast\Random;
use app\common\library\CheckOrderUtils;
use app\admin\model\user\Userhxacc;

/**
 * 订单管理
 *
 * @icon fa fa-circle-o
 */
class Order extends Backend
{

    /**
     * Order模型对象
     * @var \app\admin\model\order\Order
     */
    protected $model = null;
    
    protected $acc_hx_code = null;
    protected $hx_type     = null;
    
    public function _initialize() {
        parent::_initialize();
        $this->model = new \app\admin\model\order\Order;
        $this->view->assign("statusList", $this->model->getStatusList());
        $this->view->assign("orderTypeList", $this->model->getOrderTypeList());
        $this->view->assign("isCallbackList", $this->model->getIsCallbackList());
        $this->view->assign("isResetorderList", $this->model->getIsResetorderList());
        $this->view->assign("thirdHxList", $this->model->getThirdHxList());
        
        $this->acc_hx_code = Config::get('mchconf.acc_hx_code');
        $this->hx_type     = Config::get('mchconf.hx_type');
    }

    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */


    /**
     * 查看
     */
    public function index() {
        //当前是否为关联查询
        $this->relationSearch = true;
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }

            $filter = json_decode($this->request->get('filter'), true);

            $map  = [];
            $map2 = [];

            if (isset($filter['createtime'])) {
                $createtime = explode(' - ', $filter['createtime']);
                $timeStr    = strtotime($createtime[0]) . ',' . strtotime($createtime[1]);
                $map[]      = ['createtime', 'between', $timeStr];
                $map2[]     = ['createtime', 'between', $timeStr];
            }

            if (isset($filter['user.username'])) {
                //找出用户 获取用户id
                $userId = Db::name('user')->where('username', $filter['user.username'])->value('id');
                $map[]  = ['user_id', '=', $userId];

            }

            if (isset($filter['merchant.username'])) {
                //找出商户 获取商户id
                $merchantId = Db::name('merchant')->where('username', $filter['merchant.username'])->value('id');
                $map[]      = ['mer_id', '=', $merchantId];

            }

            if (isset($filter['agent.username'])) {
                //找出代理 获取代理id
                $agentId = Db::name('agent')->where('username', $filter['agent.username'])->value('id');
                $map[]   = ['agent_id', '=', $agentId];

            }

            if (isset($filter['status'])) {
                $map[] = ['status', '=', $filter['status']];
            }

            if (isset($filter['out_trade_no'])) {
                $map[] = ['out_trade_no', '=', $filter['out_trade_no']];

            }

            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $total = $this->model
                ->withJoin(['user' => ['username'], 'merchant' => ['username']], 'left')
                ->where($where)
                ->order($sort, $order)
                ->count();

            $list = $this->model
                ->withJoin(['user' => ['username'], 'merchant' => ['username']], 'left')
                ->where($where)
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();

            foreach ($list as $row) {

                $row['user_ip_address'] = $row['user_ip_from'] . $row['user_ip_address'];
                
                if(is_null($row['xl_pay_data'])){
                    $row['xl_pay_data'] = '';
                }
                $cache_time = 3600*8;
                $acc_name = Db::name('acc')->where('code', $row['pay_type'])->cache(true, $cache_time)->value('name');
                $row['pay_type'] = $acc_name . '('.$row['pay_type'].')';
                
            }


            //总金额
            $allmoney = $this->model->withJoin(['user', 'merchant'])->where($where)->sum('amount');
            //总手续费
            $allfees = $this->model->withJoin(['user', 'merchant'])->where($where)->sum('fees');
            //总订单数量
            $allorder = $this->model->withJoin(['user', 'merchant'])->where($where)->count();
            //成功订单数
            $success_order = $this->model->withJoin(['user', 'merchant'])->where(['order.status' => 1])->where($where)->count();

            /*$success_order = Db::name('order')->where(['status' => 1])->where($map2)->count();
            if ($allorder == 0) {
                $success_rate = '0%';
            } else {
                $success_rate = (bcdiv($success_order, $allorder, 4) * 100) . "%";
            }*/
            $success_rate = $allorder == 0 ? '0%' : (bcdiv($success_order, $allorder, 4) * 100) . "%";

            $todayorder      = $this->model->where($map)->whereDay('createtime')->count();
            $todayordermoney = $this->model->where($map)->whereDay('createtime')->sum('amount');

            $today_success_num  = $this->model->where(['status' => 1])->whereDay('createtime')->count();
            $today_num          = $this->model->whereDay('createtime')->count();
            $today_success_rate = $today_success_num == 0 ? '0%' : (bcdiv($today_success_num, $today_num, 4) * 100) . "%";


            $result = array("total" => $total, "rows" => $list, "extend" => [
                'allmoney'           => $allmoney,
                'allorder'           => $allorder,
                'allfees'            => $allfees,
                'todayorder'         => $todayorder,
                'todayordermoney'    => $todayordermoney,
                'success_rate'       => $success_rate,
                'today_success_rate' => $today_success_rate,
            ]);

            return json($result);
        }
        return $this->view->fetch();
    }

    /**
     * 编辑
     */
    public function edit($ids = null) {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds)) {
            if (!in_array($row[$this->dataLimitField], $adminIds)) {
                $this->error(__('You have no permission'));
            }
        }
        $this->success('成功');
        if ($this->request->isPost()) {
            $params = $this->request->post('row/a');
            if ($params) {
                $params = $this->preExcludeFields($params);
                $result = false;
                Db::startTrans();

                try {
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name     = str_replace('\\model\\', '\\validate\\', get_class($this->model));
                        $validate = is_bool($this->modelValidate) ? $name : $this->modelValidate;
                        $pk       = $row->getPk();
                        if (!isset($params[$pk])) {
                            $params[$pk] = $row->$pk;
                        }
                        validate($validate)->scene($this->modelSceneValidate ? 'edit' : $name)->check($params);
                    }

                    $updateParams['status']          = $params['status'];
                    $updateParams['ordertime']       = time();
                    $updateParams['deal_username']   = $row['deal_username'] . '-管理员';
                    $updateParams['deal_ip_address'] = request()->ip();
                    $updateParams['remark']          = $row['remark'] . '-修改';
                    $updateParams['zfb_code']        = $params['zfb_code'];

                    $result = $row->save($updateParams);
                    Db::commit();
                } catch (ValidateException $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                } catch (\PDOException $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                if ($result !== false) {
                    $this->success();
                } else {
                    $this->error(__('No rows were updated'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $this->view->assign('row', $row);

        return $this->view->fetch();
    }

    /**
     * 删除
     */
    public function del($ids = '') {
        
        if ($ids) {
            $pk       = $this->model->getPk();
            $adminIds = $this->getDataLimitAdminIds();
            if (is_array($adminIds)) {
                $this->model->where($this->dataLimitField, 'in', $adminIds);
            }
            $list = $this->model->where($pk, 'in', $ids)->select();

            $count = 0;
            Db::startTrans();

            try {
                foreach ($list as $k => $v) {
                    $count += $v->delete();
                }
                Db::commit();
            } catch (\PDOException $e) {
                Db::rollback();
                $this->error($e->getMessage());
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            if ($count) {
                $this->success();
            } else {
                $this->error(__('No rows were deleted'));
            }
        }
        $this->error(__('Parameter %s can not be empty', 'ids'));
    }

    /**
     * 详情
     */
    public function detail($ids = null) {
        
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }

        $row                 = $row->toArray();
        $row['mer_username'] = Db::name('merchant')->where('id', $row['mer_id'])->value('username');
        $row['acc_name']     = Db::name('acc')->where('code', $row['pay_type'])->value('name');
        
        $alipayZhuTi = Db::name('alipay_zhuti')->where(['id'=>$row['xl_user_id']])->find();
        if($alipayZhuTi){
            $row['zhuti_name'] = $row['xl_user_id'] . '|' . $alipayZhuTi['name'];
        }else{
            $row['zhuti_name'] = '';
        }
        
        $logList = Db::name('callback_log')->where(['out_trade_no'=>$row['out_trade_no']])
            ->whereOr('trade_no', $row['trade_no'])->order('createtime','asc')->select()->toArray();
        
        $logs = '';
        
        if(!empty($logList)){
            
            foreach ($logList as $k => $v){
                
                
                if (strstr($v['data'], 'pay_time') != false) {
                    $logs .= date('Y-m-d H:i:s', $v['createtime']) .  "    " . $v['trade_no'] . "    回调给商户" . "\n" . $v['data'] . "\n\n";
                } else{
                    $logs .= date('Y-m-d H:i:s', $v['createtime']) .  "    " . $v['trade_no'] . "\n" . $v['data'] . "\n\n";
                }
                
            }
        }
        
        $this->view->assign("logs", $logs);
        
        $this->view->assign("row", $row);
        return $this->view->fetch();

    }

    //订单完成
    public function complete() {
        $this->success('success');
        $ids = $this->request->request('ids');

        if (!$ids) {
            $this->error(__('参数缺少'));
        }

        $order = Db::name('order')->where(['id' => $ids, 'status' => 2])->find();

        if (!$order) {
            $this->error('订单不存在');
        }


        $pay_time = time();
        //先修改订单状态 再发送回调

        $updata        = [
            'status'          => 1,
            'ordertime'       => $pay_time,
            'deal_ip_address' => request()->ip(),
            'deal_username'   => '管理员',

        ];
        $updateOrderRe = Db::name('order')->where('id', $order['id'])->update($updata);
        if (!$updateOrderRe) {
            $this->error('操作失败请重试');
        }

        $result = false;

        // 启动事务
        Db::startTrans();
        try {

            //发送回调
            $callback = new Notify();

            $callbackre = $callback->sendCallBack($order['id'], 1, $pay_time);

            if ($callbackre['code'] != 1) {

                $callbackarray = [
                    'is_callback'      => 2,
                    'callback_count'   => $order['callback_count'] + 1,
                    'callback_content' => $callbackre['content'],
                ];

                $msg = '回调失败未收到success：' . $callbackre['content'];

            } else {

                //回调成功
                $callbackarray = [
                    'is_callback'      => 1,
                    'callback_time'    => time(),
                    'callback_count'   => $order['callback_count'] + 1,
                    'callback_content' => $callbackre['content'],
                ];

                $msg = '，回调成功';
            }

            $result = Db::name('order')->where('id', $order['id'])->update($callbackarray);

            //商户余额记录
            MoneyLog::merchantMoneyChange($order['mer_id'], $order['amount'], 0, $order['trade_no'], $order['out_trade_no'], '补单扣除', 0, 0);

            //码商余额记录
            MoneyLog::userMoneyChange($order['user_id'], $order['amount'], 0, $order['trade_no'], $order['out_trade_no'], '补单扣除',0, 0);

            $findUser = User::find($this->auth->id);
            if ($findUser['is_commission'] == 1) {
                //返佣
                MoneyLog::userCommission($order['user_id'], $order['amount'], $order['out_trade_no'], $order['trade_no'], $order['pay_type']);
            }

            // 提交事务
            Db::commit();

        } catch (\Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }


        if ($result != false) {
            $this->success('处理成功' . $msg);
        }

        $this->error('处理失败，' . $msg);
    }

    //订单失败
    public function abnormal() {
$this->success('成功');
        $ids = $this->request->request('id');

        if (empty($ids)) {
            $this->error(__('参数缺少'));
        }

        $order = Db::name('order')->where(['id' => $ids, 'status' => 2])->find();

        if (!$order) {
            $this->error('订单不存在');
        }

        $status   = 3;
        $pay_time = time();

        // 启动事务
        Db::startTrans();
        try {

            $updata = [
                'status'          => $status,
                'ordertime'       => time(),
                'deal_ip_address' => request()->ip(),
                'deal_username'   => '管理员',
            ];
            $result = Db::name('order')->where('id', $ids)->update($updata);
            if (!$result) {
                $this->error('订单处理失败');
            }

            //发送回调
            $callback = new Notify();

            $callbackre = $callback->sendCallBack($order['id'], $status, $pay_time);

            if ($callbackre['code'] != 1) {

                $callbackarray = [
                    'is_callback'      => 2,
                    'callback_count'   => $order['callback_count'] + 1,
                    'callback_content' => $callbackre['content'],
                ];

                $msg = '回调失败，未收到success：' . $callbackre['content'];

            } else {

                //回调成功
                $callbackarray = [
                    'is_callback'      => 1,
                    'callback_time'    => time(),
                    'callback_count'   => $order['callback_count'] + 1,
                    'callback_content' => $callbackre['content'],
                ];

                $msg = '，回调成功';
            }

            $result1 = Db::name('order')->where('id', $order['id'])->update($callbackarray);


            //商户余额记录
            MoneyLog::merchantMoneyChange($order['mer_id'], $order['amount'], 0, $order['trade_no'], $order['out_trade_no'], '失败退款',1, 0);

            //码商余额记录
            MoneyLog::userMoneyChange($order['user_id'], $order['amount'], 0, $order['trade_no'], $order['out_trade_no'], '失败退款',1, 0);


            // 提交事务
            Db::commit();

        } catch (\Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }


        if ($result != false && $result1 != false) {
            $this->success('处理成功' . $msg);
        }

        $this->error('处理失败，请重试' . $msg);


    }

    //手动补单-只补失败的单
    public function resetOrder() {
$this->success('成功');
        $ids    = $this->request->post('id');
        $remark = $this->request->post('remark');

        if (empty($ids) || empty($remark)) {
            $this->error('参数缺少');
        }

        //超过5小时的不补单
        $time  = time() - (60 * 60 * 5);
        $order = Db::name('order')->where(['id' => $ids, 'status' => 3])->find();
        if (!$order) {
            $this->error('订单不存在');
        }

        if ($order['pay_type'] == '1032') {
            //检测一遍核销码是否存在
            $order_code = $this->model->where(['zfb_code' => $remark])->where('id','<>',$ids)->find();
            if($order_code){
                $this->error('该核销码已存在');
            }
            //把核销码更新到系统，系统自动核销
            Db::name('order')->where(['id' => $ids, 'status' => 3])->update(['zfb_code' => $remark]);
            $check_res = CheckOrderUtils::tbhxCheck($order);
            if ($check_res['is_exist'] == false) {
                $this->error('查单失败：' . $check_res['data']);
            }
        }


        $pay_time     = time();
        $status       = 1;

        //先修改订单状态 再发送回调
        $updata = [
            'status'          => $status,
            'ordertime'       => $pay_time,
            'deal_ip_address' => request()->ip(),
            'deal_username'   => $order['deal_username'] . '-管理员',
            'remark'          => '手动补单-' . $remark,
            'is_resetorder'   => 1,
        ];

        $result = Db::name('order')->where('id', $ids)->update($updata);
        if (!$result) {
            $this->error('手动补单失败，请重试');
        }

        $result = false;

        // 启动事务
        Db::startTrans();
        try {


            //开始回调
            $callback = new Notify();

            $callbackre = $callback->sendCallBack($order['id'], $status, $pay_time);

            if ($callbackre['code'] != 1) {

                $callbackarray = [
                    'is_callback'      => 2,
                    'callback_count'   => $order['callback_count'] + 1,
                    'callback_content' => $callbackre['content'],
                ];

                $msg = '回调失败，未收到success：' . $callbackre['content'];

            } else {
                //回调成功
                $callbackarray = [
                    'is_callback'      => 1,
                    'callback_time'    => time(),
                    'callback_count'   => $order['callback_count'] + 1,
                    'callback_content' => $callbackre['content'],
                ];

                $msg = '';
            }

            $result = Db::name('order')->where('id', $ids)->update($callbackarray);

            //商户余额记录
            //MoneyLog::merchantMoneyChange($order['mer_id'], $order['amount'], 0, $order['trade_no'], $order['out_trade_no'], '补单扣除',0, 0);

            //码商余额记录
            MoneyLog::userMoneyChange($order['user_id'], $order['amount'], 0, $order['trade_no'], $order['out_trade_no'], '补单扣除',0, 0);


            // 提交事务
            Db::commit();
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            $this->error('处理失败，' . $e->getMessage());
        }

        if ($result != false) {
            $this->success('手动补单成功' . $msg);
        }

        $this->error('手动补单失败');

    }

    //补发通知
    public function reissueNotice($ids = null) {
$this->success('成功');
        if (!$ids) {
            $this->error(__('参数缺少'));
        }

        //超过24小时的不补发通知
        $time = time() - (60 * 60 * 24);
        //->where('createtime','>',$time)
        $order = Db::name('order')->where(['id' => $ids, 'status' => 1])->find();

        if (!$order) {
            $this->error('订单不存在');
        }
        $status   = $order['status'];
        $pay_time = $order['ordertime'];

        //开始回调
        $callback = new Notify();

        $callbackre = $callback->sendCallBack($order['id'], $status, $pay_time);

        if ($callbackre['code'] != 1) {

            Db::name('order')->where('id', $order['id'])->inc('callback_count')->update();

            $this->error('通知失败-未收到success：' . $callbackre['content']);
        }

        //回调成功
        $updata = [
            'is_callback'      => 1,
            'callback_count'   => $order['callback_count'] + 1,
            'callback_time'    => time(),
            'callback_content' => $callbackre['content'],
            'remark'           => '补发通知',
        ];


        $re = Db::name('order')->where('id', $ids)->update($updata);

        if ($re) {
            $this->success('补发通知成功');
        }

        $this->error('补发通知失败');
    }

    //查单
    public function xlqueryorder($ids = null) {

        $order = Db::name('order')->where(['id' => $ids])->find();
        if (!$order) {
            $this->error('订单不存在');
        }
        
        //支付宝云端 $order['pay_type'] == '1008' || $order['pay_type'] == '1041'  || 
        if ($order['pay_type'] == '1025') {
            
            if($order['pay_type'] == '1008' || $order['pay_type'] == '1041' ){
                $is_uid = true;
            }else{
                $is_uid = false;
            }
            
            $check_res = CheckOrderUtils::zfbYdCheck($order,$is_uid);
            
            if ($check_res['is_exist'] == true) {
                $this->success('查单成功：' . $check_res['data']);
            }

            $this->error('查单失败，原因：' . $check_res['data']);
        }
        
        //支付宝主体模式 原生模式 
        if (in_array($order['pay_type'], ['1065'])) {
            
            $check_res = CheckOrderUtils::checkAlipayYsOrder($order);
            
            if ($check_res['is_exist'] == true) {
                $this->success('查单成功：' . $check_res['data']);
            }
            
            $this->error('查单失败：' . $check_res['data']);
        }
        
        //支付宝主体模式
        if (in_array($order['pay_type'], ['1050','1051','1052','1053','1054'])) {
            
            $check_res = CheckOrderUtils::checkAlipayAppOrder($order);
            
            if ($check_res['is_exist'] == true) {
                $this->success('查单成功：' . $check_res['data']);
            }

            $this->error('查单失败：' . $check_res['data']);
        }
        
        //支付宝主体模式-uid个码模式
        if (in_array($order['pay_type'], Config::get('mchconf.zhuti_acc_code'))) {
            
            $check_res = CheckOrderUtils::checkAlipayGmOrder($order);
            
            if ($check_res['is_exist'] == true) {
                $this->success('查单成功：' . $check_res['data']);
            }

            $this->error('查单失败：' . $check_res['data']);
        }
        
        //通用查单方法
        $check_res = CheckOrderUtils::commonQueryOrder($order);

        if ($check_res['is_exist'] == true) {
            $this->success('查单成功：' . $check_res['data']);
        }

        $this->error('查单失败，原因：' . $check_res['data']);
        
    }

    //取消订单
    public function closeOrder($ids = null) {

        $order = Db::name('order')->where(['id' => $ids])->find();
        if (!$order) {
            $this->error('订单不存在');
        }

        //g买卖支付宝
        if ($order['pay_type'] == '1028') {
            if (empty($order['xl_order_id'])) {
                $this->error('订单未发起gmm支付');
            }

            $check_res = CheckOrderUtils::gmmCloseOrder($order);

            if ($check_res['code'] == 200) {

                $this->success('取消成功' . $check_res['data']);

            }

            $this->error('取消失败：' . $check_res['data']);
        }

        $this->error('类型错误');
    }
    
    //单通道测试
    public function payTest() {
        $ids     = $this->request->request('id');
        $amount  = $this->request->request('amount');
        
        $findQrcode = Db::name('group_qrcode')->where(['id' => $ids])->find();
        
        $user_id = $findQrcode['user_id'];
        
        $findUser = User::find($user_id);
        if($findUser['money'] < $amount){
            $this->error('余额不足');
        }
        
        $merList  = Db::name('mer_user')->where(['user_id'=>$user_id])->select()->toArray();
        $count = count($merList);
        if ($count == 0){
            $this->error('请联系上级配置');
        }
        $mer_info = $merList[mt_rand(0, $count - 1)];
        $merchant = Db::name('merchant')->where(['id'=>$mer_info['mer_id'], 'status' => 'normal'])->field('id,agent_id,rate,userids')->find();

        
        $pay_type   = $findQrcode['acc_code'];

        if ($pay_type == '1030') {
            $amount_arr = ['6', '68', '118', '488', '998', '2888', '4188'];
            //$amount     = $amount_arr[mt_rand(0, 6 - 1)];
            if (!in_array($amount, $amount_arr)) {
                $this->error('金额错误，只能是：' . implode('、', $amount_arr));
            }
        }

        $out_trade_no = Utils::buildOutTradeNo();
        $trade_no     = 'T' . Utils::buildOutTradeNo();
        $now_time     = time();
        $creat_time   = $now_time;
        $expire_time  = $now_time + Config::get('site.expire_time');
        $qrcode_url   = empty($findQrcode['pay_url']) ? empty($findQrcode['image']) ? $findQrcode['image'] : Utils::imagePath($findQrcode['image'], false) : $findQrcode['pay_url'];
        $float_amount = $amount;//实付金额
        $time_s       = date('s', $now_time);//当前时间----秒

        //瀚银超时时间特殊处理
        if ($pay_type == '1017') {
            $expire_time = $expire_time - $time_s;
        }
        //我秀超时时间为3分钟 特殊处理
        if ($pay_type == '1033') {
            $expire_time = $expire_time - 120;
        }
        
        $acc = new Accutils();


        //指定通道金额浮动
        if (in_array($pay_type, ['1007', '1008', '1013', '1021', '1025'])) {
            $float_amount = $acc->getFloatMoney($findQrcode['id'], $amount);
        }

        $userAcc = Db::name('user_acc')->where(['user_id' => $user_id, 'acc_code' => $pay_type])->find();
        if (empty($userAcc)) {
            $this->error('码商通道配置错误');
        }

        $topay_url  = Utils::imagePath('/api/gateway/order/' . $out_trade_no, true);
        $notify_url = Utils::imagePath('/api/demo/getCallback', true);
        $return_url = Utils::imagePath('/api/demo/paysuccess', true);
        $mer_fees   = bcmul($amount, $merchant['rate'], 2);
        $user_fees  = bcmul($amount, $userAcc['rate'], 2);

        $pay_remark = date('d') . substr($out_trade_no, '-8');
        //$pay_remark = '商城购物'.Random::numeric(8);
        //$pay_remark = $out_trade_no;
        $pay_remark = Random::numeric(10);
        
        if ($pay_type == '1025' || $pay_type == '1007') {
            $order_num  = Db::name('order')->where('pay_type', $pay_type)->whereDay('createtime')->count();
            $pay_remark = 10000000 + $order_num;
        }
        
        if($pay_type == '1008'){
            $pay_remark = '流动早餐'. Random::numeric(8);
        }

        $orderData = [
            'user_id'      => $user_id,
            'mer_id'       => $merchant['id'],
            'agent_id'     => $merchant['agent_id'],
            'trade_no'     => $trade_no,
            'out_trade_no' => $out_trade_no,
            'pay_type'     => $pay_type,//通道编码
            'amount'       => $amount,
            'pay_amount'   => $float_amount,
            'fees'         => $user_fees,
            'mer_fees'     => $mer_fees,
            'qrcode_id'    => $findQrcode['id'],
            'qrcode_name'  => $findQrcode['name'],
            'return_type'  => 'html',
            //'pay_url'       => empty($qrcode_url) ? $topay_url : $qrcode_url,
            'pay_url'      => $topay_url,
            'notify_url'   => $notify_url,
            'return_url'   => $return_url,
            'createtime'   => $creat_time,
            'expire_time'  => $expire_time,
            'order_type'   => 1,
            'ip_address'   => request()->ip(),
            'status'       => 2,
            'pay_remark'   => $pay_remark,
        ];

        $result = Db::name('order')->insertGetId($orderData);
        if ($result) {
            
            //提单码商扣余额
            MoneyLog::userMoneyChange($user_id, $amount, $userAcc['rate'], $trade_no, $out_trade_no, '测试提单',0, 0);
                
            // 当前任务所需的业务数据，不能为 resource 类型，其他类型最终将转化为json形式的字符串
            $queueData = [
                'request_type' => 4,
                'order_id'     => $result,
                'out_trade_no' => $out_trade_no,
                'trade_no'     => $trade_no,
            ];

            //当前任务归属的队列名称，如果为新队列，会自动创建
            $queueName = 'checkorder';
            $delay     = Config::get('site.expire_time') + 1;

            //瀚银超时时间特殊处理
            if ($pay_type == '1017') {
                $delay = $delay - $time_s;
            }
            //我秀超时时间为3分钟 特殊处理
            if ($pay_type == '1033') {
                $delay = $delay - 120;
            }
            
            // 将该任务推送到消息队列，等待对应的消费者去执行
            //$isPushed = Queue::push(Jobs::class, $data, $queueName);//立即执行
            $isPushed = Queue::later($delay, Jobs::class, $queueData, $queueName);//延迟$delay秒后执行

            //g买卖增加10分钟后取消订单机制
            if ($pay_type == '1028') {
                $queueData = [
                    'request_type' => 6,
                    'order_id'     => $result,
                    'out_trade_no' => $out_trade_no,
                    'trade_no'     => $trade_no,
                ];
                $isPushed  = Queue::later(300, Jobs::class, $queueData, $queueName);//延迟$delay秒后执行
            }

            $this->success('success', $out_trade_no, $topay_url);
        }


        $this->error('测试失败');
    }
}
