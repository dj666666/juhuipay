<?php

namespace app\user\controller\order;

use app\common\controller\Jobs;
use app\common\controller\UserBackend;
use app\common\library\Notify;
use app\common\library\Utils;
use app\common\library\MoneyLog;
use app\admin\model\merchant\Merchant;
use think\facade\Config;
use think\facade\Db;
use think\facade\Log;
use think\facade\Queue;
use app\common\library\Accutils;
use app\common\library\QrcodeService;
use fast\Random;
use app\common\library\CheckOrderUtils;
use app\admin\model\User;
use app\common\library\GoogleAuthenticator;

/**
 * 订单管理
 *
 * @icon fa fa-circle-o
 */
class Order extends UserBackend
{

    /**
     * Order模型对象
     * @var \app\admin\model\order\Order
     */
    protected $model = null;

    public function _initialize() {
        parent::_initialize();
        $this->model = new \app\admin\model\order\Order;
        $this->view->assign("statusList", $this->model->getStatusList());
        $this->view->assign("orderTypeList", $this->model->getOrderTypeList());
        $this->view->assign("isCallbackList", $this->model->getIsCallbackList());
        $this->view->assign("isResetorderList", $this->model->getIsResetorderList());
        $this->view->assign("thirdHxList", $this->model->getThirdHxList());
    }

    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */

    public function checkUser($remark, $type) {
        //检测账号状态
        $user = User::where(['id' => $this->auth->id, 'status' => 'normal'])->find();
        if (!$user) {
            $this->error('账号错误');
        }
        
        $ip = request()->ip();
        /*//检测接单状态1开启 3关闭
        if($user['is_receive'] == 2){
            $this->error('已关闭接单');
        }*/
        
        //检测登录ip
        if(Config::get('site.user_ordercheckip') == 1){
            $login_ip = explode(",",$user['login_ip']);
            if(!in_array(request()->ip(),$login_ip)){
                $this->error('权限不足');
            }
        }
        
        
        /*if($type == 'resetOrder'){
            $reissue_res = Utils::setResetOrderNum($this->auth->id);
            if($reissue_res['code'] == 0){
                Log::write('补单超过次数----' . request()->ip() . '----' . $reissue_res['msg'], 'info');
                $this->error('补单失败！！');
            }
            
            $reissue_res = Utils::setResetOrderNumByIp(request()->ip());
            if($reissue_res['code'] == 0){
                Log::write('单ip补单超过次数----' . request()->ip() . '----' . $reissue_res['msg'], 'info');
                $this->error('补单失败！！');
            }
            
        }*/
        
        if($type == 'reissueNotice'){
            
            if(!empty(Config::get('site.user_checkpwd'))){
                if($remark != Config::get('site.user_checkpwd')){
                    Log::write('补发密码错误----' . request()->ip() . '----' . $remark, 'info');
                    $this->error('补发通知失败！！');
                }
            }else{
                if(md5(md5($remark).$user['salt']) != $user['password']){
                    Log::write('补发密码错误----' . request()->ip() . '----' . $remark, 'info');
                    $this->error('补发通知失败！！');
                }
            }
            
        }else if($type == 'complete'){
            
            if(!empty(Config::get('site.user_checkpwd'))){
                if($remark != Config::get('site.user_checkpwd')){
                    Log::write('完成密码错误----' . request()->ip() . '----' . $remark, 'info');
                    $this->error('完成失败！！');
                }
                
            }else{
                $google = new GoogleAuthenticator();
                $result = $google->verifyCode($user['google_code'], $remark);
                if(!$result){
                    Log::write('完成谷歌错误----' . request()->ip() . '----' . $remark, 'info');
                    $this->error('补单失败！！');
                }
            }
            
            
        }else if($type == 'resetOrder'){
            
            if(!empty(Config::get('site.user_checkpwd'))){
                if($remark != Config::get('site.user_checkpwd')){
                    Log::write('补发密码错误----' . request()->ip() . '----' . $remark, 'info');
                    $this->error('补发通知失败！！');
                }
            }else{
                $google = new GoogleAuthenticator();
                $result = $google->verifyCode($user['google_code'], $remark);
                if(!$result){
                    Log::write('补单谷歌错误----' . request()->ip() . '----' . $remark, 'info');
                    $this->error('补单失败！！');
                }
            }
            
        }else{
            
            $google = new GoogleAuthenticator();
            $result = $google->verifyCode($user['google_code'], $remark);
            if(!$result){
                Log::write('补单谷歌错误----' . request()->ip() . '----' . $remark, 'info');
                $this->error('补单失败！！');
            }
            
        }
        
        
    }

    /**
     * 查看
     */
    public function index() {
        //当前是否为关联查询
        $this->relationSearch = true;
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);

        $user = User::where('id', $this->auth->id)->find();

        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }

            $filter = json_decode($this->request->get('filter'), true);

            $map   = [];
            $map[] = ['user_id', '=', $this->auth->id];
            $map[] = ['status', '=', 1];

            if (isset($filter['createtime'])) {
                $createtime = explode(' - ', $filter['createtime']);
                $timeStr    = strtotime($createtime[0]) . ',' . strtotime($createtime[1]);

                $map[] = ['createtime', 'between', $timeStr];
            }else{
                //默认显示当日的统计
                $stare_time = strtotime('today');//今日开始时间
                $end_time   = mktime(0,0,0,date('m'),date('d')+1,date('Y'))-1;//今日结束时间
                $map[]  = ['createtime', 'between', [$stare_time,$end_time]];
            }
            
            if (isset($filter['out_trade_no'])) {
                $map[] = ['out_trade_no', '=', $filter['out_trade_no']];
            }

            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $total = $this->model
                //->withJoin(['merchant' => ['username']], 'left')
                ->where(['user_id' => $this->auth->id])
                ->where($where)
                ->order($sort, $order)
                ->count();

            $list = $this->model
                //->withJoin(['merchant' => ['username']], 'left')
                ->where(['user_id' => $this->auth->id])
                ->where($where)
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();

            foreach ($list as $row) {
                
                $row->visible(['id','out_trade_no','trade_no','xl_order_id','qrcode_name','zfb_nickname','amount','trade_no','pay_amount','fees','pay_remark','zfb_code','status','is_callback','callback_count','createtime','ordertime','expire_time','callback_time','remark','pay_type','zfb_nickname','third_hx_status','pay_qrcode_image','user_ip_address','device_type','is_gmm_close']);

                if($row['merchant']){
                    $row->getRelation('merchant')->visible(['username']);
                }
                
                /*if($row['pay_type'] == '1034' && !empty($row['zfb_code']) && !empty($row['zfb_nickname']) ){
                    //$row['zfb_code'] = '<span>' . $row['zfb_code'] . '</span></br><span>' . $row['zfb_nickname'] . '</span>';
                     $row['zfb_code'] = $row['zfb_code'] . '</br>' . $row['zfb_nickname'];
                }
                */
                
                $check_black_res = Utils::checkBlackUid($row['user_ip_address']);
                if ($check_black_res) {
                    $row['user_ip_address'] = '<span style="color:#d01001;">' . $row['user_ip_address'] . '</span>';
                }
            }

            //金额
            $allmoney = $this->model->where($map)->sum('amount');
            //手续费
            $allfees = $this->model->where($map)->sum('fees');
            //订单数量
            $allorder = $this->model->where($map)->count();
            //待处理订单数量
            //$ordernum = $this->model->where(['user_id' => $this->auth->id, 'status' => 2])->count();

            //成功订单
            $successorder    = $this->model->where(['user_id' => $this->auth->id, 'status' => 1])->count();
            $allsuccessorder = $this->model->where(['user_id' => $this->auth->id])->count();

            //今日成功订单
            $todaysuccessorder = $this->model->where(['user_id' => $this->auth->id, 'status' => 1])->whereDay('createtime')->count();
            //今日订单
            $todayallorder = $this->model->where(['user_id' => $this->auth->id])->whereDay('createtime')->count();
            //今日成功率
            $today_success_rate = $todayallorder == 0 ? "0%" : (bcdiv($todaysuccessorder, $todayallorder, 4) * 100) . "%";

            //成功率
            if ($allsuccessorder == 0) {
                $success_rate = "0%";
            } else {
                $success_rate = (bcdiv($successorder, $allsuccessorder, 4) * 100) . "%";
            }

            
            

            $result = array("total" => $total, "rows" => $list, "extend" => [
                'allmoney'           => $allmoney,
                'allorder'           => $allorder,
                'allfees'            => $allfees,
                'todayorder'         => 0,
                'todayordermoney'    => 0,
                'success_rate'       => $success_rate,
                'ordernum'           => 0,
                'today_success_rate' => $today_success_rate,
                'balance'            => $user['money'],
            ]);

            return json($result);
        }
        
        $findUser = User::where(['id' => $this->auth->id])->find();
        
        $ordertime = $findUser['is_refresh'] == 1 ? 10000 : 86400000;
        
        $this->assignconfig('uid', $this->auth->id);
        $this->assignconfig('ordertime', $ordertime);
        $this->assignconfig('is_refresh', $findUser['is_refresh']);
        $this->assign('is_receive', $user['is_receive']);

        return $this->view->fetch();
    }

    public function edit($ids = '') {
        $this->success('success');
    }

    public function del($ids = '') {
        $this->success('success');
    }

    //订单完成
    public function complete() {
        
        $ids = $this->request->request('ids');
        $pay_remark = $this->request->request('pay_remark');
        
        if (!$ids) {
            $this->error(__('参数缺少'));
        }

        $order = $this->model->where(['id' => $ids, 'user_id' => $this->auth->id, 'status' => 2])->find();
        
        if (!$order) {
            $this->error('订单不存在');
        }
        
        $this->checkUser($pay_remark, 'complete');
        
        if ($order['pay_type'] == '1032') {
            if (empty($order['zfb_code'])) {
                $this->error('核销码不存在');
            }
        }
        
        $user = User::where(['id' => $this->auth->id, 'status' => 'normal'])->find();
        
        if (Config::get('site.user_rate') == '2') {
            if($user['money'] < $order['amount']){
                $this->error('余额不足');
            }
            
        }
        
        
        $pay_time = time();

        //先修改订单状态 再发送回调
        $updata        = [
            'status'          => 1,
            'ordertime'       => $pay_time,
            'deal_ip_address' => request()->ip(),
            'deal_username'   => '手动-' . $this->auth->username,
        ];
        
        if($order['pay_type'] == '1018' && $pay_remark){
            $updata['pay_remark'] = $pay_remark;
        }
        
        $updateOrderRe = $this->model->where('id', $order['id'])->update($updata);
        if (!$updateOrderRe) {
            $this->error('操作失败请重试');
        }
        
        //通知码商群
        $sendRes = Utils::sendTgBotGroupByUser($order['trade_no'], $order['amount'], 1, $this->auth->nickname, $user, 'gogo');
        $sendRes = Utils::sendTgBotGroupByMer($order, $this->auth->nickname, 1, 'gogo');
        
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
                    'callback_time'    => time(),
                    'callback_count'   => $order['callback_count'] + 1,
                    'callback_content' => $callbackre['content'],
                ];

                $msg = '，回调失败未收到success：' . $callbackre['content'];

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

            $result = Db::name('order')->where('id', $order['id'])->update($callbackarray);
            
            $findAgent = Db::name('agent')->where('id',$order['agent_id'])->cache(true,60)->find();
            
            //每个代理单独控制提单是否扣款， 0不扣 1扣
            if ($findAgent['sub_order_rate'] == '0'){
                
                //码商 统一判断走单费率规则
                MoneyLog::checkMoneyRateType($order['user_id'],$order['amount'], $order['fees'], $order['trade_no'], $order['out_trade_no'],'user');
                
                //商户 统一判断走单费率规则
                MoneyLog::checkMoneyRateType($order['mer_id'],$order['amount'], $order['mer_fees'], $order['trade_no'], $order['out_trade_no'],'merchant');
            }
            
            
            $findUser = User::find($this->auth->id);
            if ($findUser['is_commission'] == 1){
                //返佣
                MoneyLog::userCommission($order['user_id'], $order['amount'], $order['out_trade_no'], $order['trade_no'], $order['pay_type']);
            }

            // 提交事务
            Db::commit();

        } catch (\Exception $e) {
            Db::rollback();
            
            Log::write('订单完成错误----'.$ids.'----'.$e->getFile().'----'. $e->getLine() .'----' .$e->getMessage(),'info');
            
            $this->error('处理失败，' . $e->getMessage());
        }


        if ($result != false) {
            $this->success('处理成功' . $msg);
        }

        $this->error('处理失败' . $msg);
    }
    
    //订单失败
    public function abnormal() {
        $this->success('成功');
        $this->checkUser();

        $ids = $this->request->request('id');

        if (empty($ids)) {
            $this->error(__('参数缺少'));
        }

        $order = $this->model->where(['id' => $ids, 'user_id' => $this->auth->id, 'status' => 2])->find();

        if (!$order) {
            $this->error('订单不存在');
        }


        $status   = 3;
        $pay_time = time();
        $result1  = false;

        // 启动事务
        Db::startTrans();
        try {

            $updata  = [
                'status'          => $status,
                'ordertime'       => time(),
                'deal_ip_address' => request()->ip(),
                'deal_username'   => '手动-' . $this->auth->username,
            ];
            $result1 = $this->model->where('id', $ids)->update($updata);
            if (!$result1) {
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


                //回调失败 加入队列
                if (Config::get('site.is_queue_notify')) {

                    // 当前任务所需的业务数据，不能为 resource 类型，其他类型最终将转化为json形式的字符串
                    $queueData = [
                        'request_type' => 3,
                        'order_id'     => $order['id'],
                        'out_trade_no' => $order['out_trade_no'],
                    ];

                    //当前任务归属的队列名称，如果为新队列，会自动创建
                    $queueName = 'checkorder';
                    $delay     = 10;
                    // 将该任务推送到消息队列，等待对应的消费者去执行
                    //$isPushed = Queue::push(Jobs::class, $data, $queueName);//立即执行
                    $isPushed = Queue::later($delay, Jobs::class, $queueData, $queueName);//延迟$delay秒后执行

                }

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

            $result = $this->model->where('id', $order['id'])->update($callbackarray);

            //商户余额
            MoneyLog::merchantMoneyChange($order['mer_id'], $order['amount'], 0, $order['trade_no'], $order['out_trade_no'], '失败退款', 1, 0);

            //码商余额记录
            MoneyLog::userMoneyChange($order['user_id'], $order['amount'], 0, $order['trade_no'], $order['out_trade_no'], '失败退款',1, 0);

            // 提交事务
            Db::commit();


        } catch (\Exception $e) {
            Db::rollback();
            $this->error('处理失败，' . $e->getMessage());
        }


        if ($result != false) {
            $this->success('操作成功' . $msg);
        }

        $this->error('操作失败，请重试' . $msg);


    }

    //手动补单-只补失败的单
    public function resetOrder() {
        
        $ids    = $this->request->post('id');
        $remark = $this->request->post('remark');

        if (empty($ids) || empty($remark)) {
            $this->error('参数缺少');
        }

        //超过5小时的不补单
        $time  = time() - (60 * 60 * 3);
        $order = $this->model->where(['id' => $ids, 'user_id' => $this->auth->id, 'status' => 3])->find();
        if (!$order) {
            $this->error('订单不存在');
        }
        
        $this->checkUser($remark, 'resetOrder');
        
        
        if ($order['pay_type'] == '1032') {
            //if($remark != '111'){
                //检测一遍核销码是否存在
                $order_code = $this->model->where(['zfb_code' => $remark])->where('id','<>',$ids)->find();
                if($order_code){
                    $this->error('该核销码已存在');
                }
                //把核销码更新到系统，系统自动核销
                $this->model->where(['id' => $ids, 'user_id' => $this->auth->id, 'status' => 3])->update(['zfb_code' => $remark]);
                $check_res = CheckOrderUtils::tbhxCheck($order);
                if ($check_res['is_exist'] == false) {
                    $this->error('查单失败：' . $check_res['data']);
                }
            //}
        }

        $pay_time     = time();
        $status       = 1;

        //先修改订单状态 再发送回调
        $updata = [
            'status'          => $status,
            'ordertime'       => $pay_time,
            'deal_ip_address' => request()->ip(),
            'deal_username'   => $this->auth->username,
            'remark'          => '手动补单-' . $remark,
            'is_resetorder'   => 1,
        ];
        if($remark && $order['pay_type'] == '1018'){
            $updata['pay_remark'] = $remark;
        }
        $result = $this->model->where('id', $ids)->update($updata);
        if (!$result) {
            $this->error('操作失败请重试');
        }

        //通知码商群
        $user = User::where(['id' => $this->auth->id, 'status' => 'normal'])->find();
        $sendRes = Utils::sendTgBotGroupByUser($order['trade_no'], $order['amount'], 1, $this->auth->nickname, $user, 'gogo');
        $sendRes = Utils::sendTgBotGroupByMer($order, $this->auth->nickname, 1, 'gogo');
        
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
                    'callback_time'    => time(),
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

            $result = $this->model->where('id', $ids)->update($callbackarray);
            
            $findAgent = Db::name('agent')->where('id',$order['agent_id'])->cache(true,60)->find();
            
            //每个代理单独控制提单是否扣款， 0不扣 1扣
            if ($order['status'] == 3 && $findAgent['sub_order_rate'] == '0'){
                
                //码商 统一判断走单费率规则
                MoneyLog::checkMoneyRateType($order['user_id'],$order['amount'], $order['fees'], $order['trade_no'], $order['out_trade_no'],'user');
                
                //商户 统一判断走单费率规则
                MoneyLog::checkMoneyRateType($order['mer_id'],$order['amount'], $order['mer_fees'], $order['trade_no'], $order['out_trade_no'],'merchant');
            }
            
            $findUser = User::find($this->auth->id);
            if ($findUser['is_commission'] == 1){
                //返佣
                MoneyLog::userCommission($order['user_id'], $order['amount'], $order['out_trade_no'], $order['trade_no'], $order['pay_type']);
            }

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
    public function reissueNotice() {
        
        $ids    = $this->request->post('id');
        $remark = $this->request->post('remark');

        if (empty($ids) || empty($remark)) {
            $this->error('参数缺少');
        }
        
        //超过4小时的不补发通知
        $time  = time() - (60 * 60 * 4);
        $order = $this->model->where(['id' => $ids, 'user_id' => $this->auth->id, 'status' => 1])->where('createtime', '>', $time)->find();

        if (!$order) {
            $this->error('订单不存在');
        }
        
        $this->checkUser($remark, 'reissueNotice');
        
        //通知码商群
        $user = User::where(['id' => $this->auth->id, 'status' => 'normal'])->find();
        $sendRes = Utils::sendTgBotGroupByUser($order['trade_no'], $order['amount'], 1, $this->auth->nickname, $user, 'gogo');
        $sendRes = Utils::sendTgBotGroupByMer($order, $this->auth->nickname, 1, 'gogo');
        
        //开始回调
        $callback = new Notify();

        $callbackre = $callback->sendCallBack($order['id'], 1, $order['ordertime']);

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


        $re = $this->model->where('id', $ids)->update($updata);

        if ($re) {
            $this->success('补发通知成功');
        }

        $this->error('补发通知失败');


    }
    
    //查询有无新订单
    public function getOrder(){
        
        //自定义查询方法
        
        //找出这个用户的单
        $orderCount = Db::name('order')
            ->where(['user_id'=>$this->auth->id, 'status' => 2])
            ->count();
            
        $result = ['applynum' => 0, 'ordernum' => $orderCount];

        return json($result);
    }
    
    //单通道测试
    public function payTest() {
        $ids     = $this->request->request('id');
        $amount  = $this->request->request('amount');
        $user_id = $this->auth->id;
        
        $findUser = User::find($user_id);
        if($findUser['money'] < $amount){
            $this->error('码商余额不足');
        }
        
        $merList  = Db::name('mer_user')->where(['user_id'=>$user_id])->select()->toArray();
        $count = count($merList);
        if ($count == 0){
            $this->error('请联系上级配置');
        }
        $mer_info = $merList[mt_rand(0, $count - 1)];
        $merchant = Db::name('merchant')->where(['id'=>$mer_info['mer_id'], 'status' => 'normal'])->field('id,agent_id,rate,userids')->find();

        $findQrcode = Db::name('group_qrcode')->where(['id' => $ids])->find();
        $pay_type   = $findQrcode['acc_code'];

        if ($pay_type == '1030') {
            $amount_arr = ['6', '68', '118', '488', '998', '2888', '4188'];
            //$amount     = $amount_arr[mt_rand(0, 6 - 1)];
            if (!in_array($amount, $amount_arr)) {
                $this->error('金额错误，只能是：' . implode('、', $amount_arr));
            }
        }

        $out_trade_no = Utils::buildOutTradeNo();
        $trade_no     = 'Test' . Utils::buildOutTradeNo();
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
        
        $qrcodeService = new QrcodeService();
        
        //指定通道金额浮动
        if (in_array($pay_type, ['1007', '1008', '1013', '1021','1025','1045','1056','1057','1058'])) {
            $float_amount = $qrcodeService->getFloatMoney($findQrcode['id'], $amount);
        }
        
        if (in_array($pay_type, ['1036'])) {
            $float_amount = $qrcodeService->getFloatMoneyV2($merchant['agent_id'],$findQrcode['id'], $amount);
        }
        
        $userAcc = Db::name('user_acc')->where(['user_id' => $this->auth->id, 'acc_code' => $pay_type])->find();
        if (empty($userAcc)) {
            $this->error('人员错误');
        }
        
        $merAcc = Db::name('mer_acc')->where(['mer_id' => $merchant['id'], 'acc_code' => $pay_type])->find();

        $topay_url  = Utils::imagePath('/api/gateway/order/' . $out_trade_no, true);
        $notify_url = Utils::imagePath('/api/demo/getCallback', true);
        $return_url = Utils::imagePath('/api/demo/paysuccess', true);
        $mer_fees   = bcmul($amount, $merAcc['rate'], 2);
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
            'user_id'      => $this->auth->id,
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
            //MoneyLog::userMoneyChange($this->auth->id, $amount, $userAcc['rate'], $trade_no, $out_trade_no, '测试提单',0, 0);
                
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
            /*if ($pay_type == '1028') {
                $queueData = [
                    'request_type' => 6,
                    'order_id'     => $result,
                    'out_trade_no' => $out_trade_no,
                    'trade_no'     => $trade_no,
                ];
                $isPushed  = Queue::later(300, Jobs::class, $queueData, $queueName);//延迟$delay秒后执行
            }*/
            
            //6分钟后自动删除测试单
            if(Config::get('site.test_order_del') == 1){
                
                $queueDataDel = [
                    'request_type' => 7,
                    'order_id'     => $result,
                    'out_trade_no' => $out_trade_no,
                    'trade_no'     => $trade_no,
                ];
                $isPushed  = Queue::later(600, Jobs::class, $queueDataDel, $queueName);
                
            }
            
            $this->success('success', $out_trade_no, $topay_url);
        }


        $this->error('测试失败');
    }
    
    
    
}
