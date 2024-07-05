<?php

namespace app\agent\controller\order;

use app\admin\model\GroupQrcode;
use app\admin\model\ippool\Blackippool;
use app\admin\model\merchant\Merchant;
use app\admin\model\User;
use app\common\controller\AgentBackend;
use think\facade\Request;
use fast\Http;
use app\common\library\Accutils;
use app\common\library\QrcodeService;
use app\common\controller\Jobs;
use app\common\controller\UserBackend;
use app\common\library\Notify;
use app\common\library\Utils;
use app\common\library\MoneyLog;
use app\common\library\ThirdHx;
use think\facade\Log;
use think\facade\Config;
use think\facade\Db;
use think\facade\Queue;
use fast\Random;
use app\common\library\CheckOrderUtils;
use app\admin\model\user\Userhxacc;
use app\common\library\GoogleAuthenticator;


/**
 * 订单管理
 *
 * @icon fa fa-circle-o
 */
class Order extends AgentBackend
{
    
    /**
     * Order模型对象
     * @var \app\admin\model\order\Order
     */
    protected $model = null;

    protected $acc_hx_code = null;
    protected $hx_type = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\order\Order;
        $this->acc_hx_code = Config::get('mchconf.acc_hx_code');
        $this->hx_type     = Config::get('mchconf.hx_type');
        $this->view->assign("statusList", $this->model->getStatusList());
        $this->view->assign("orderTypeList", $this->model->getOrderTypeList());
        $this->view->assign("isCallbackList", $this->model->getIsCallbackList());
        $this->view->assign("isResetorderList", $this->model->getIsResetorderList());
    }
    
    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */
    
    public function checkUser($remark, $type){
        
        $findAgent = Db::name('agent')->where(['id' => $this->auth->id])->find();
        
        if (Config::get('site.loginip_checkagentlogin')) {
            $login_ip = explode(",",$findAgent['login_ip']);
            if(!in_array(request()->ip(),$login_ip)){
                Log::write('补单ip错误----' . request()->ip(), 'info');
                $this->error('补单失败！！');
            }
        }
        
        
        if($type == 'resetOrder'){
            $reissue_res = Utils::setResetOrderNum($this->auth->id);
            if($reissue_res['code'] == 0){
                Log::write('补单超过次数----' . request()->ip() . '----' . $reissue_res['msg'], 'info');
                $this->error('补单失败！！');
            }
            
            /*$reissue_res = Utils::setResetOrderNumByIp(request()->ip());
            if($reissue_res['code'] == 0){
                Log::write('单ip补单超过次数----' . request()->ip() . '----' . $reissue_res['msg'], 'info');
                $this->error('补单失败！！');
            }*/
        }
        
        
        
        if($type == 'reissueNotice'){
            
            if(!empty(Config::get('site.user_checkpwd'))){
                if($remark != Config::get('site.user_checkpwd')){
                    Log::write('补发密码错误----' . request()->ip() . '----' . $remark, 'info');
                    $this->error('通知失败！！');
                }
                
            }else{
                if(md5(md5($remark).$findAgent['salt']) != $findAgent['password']){
                    Log::write('补发密码错误----' . request()->ip() . '----' . $remark, 'info');
                    $this->error('通知失败！！');
                }
            }
            
            
        }else{
            
            if(!empty(Config::get('site.user_checkpwd'))){
                if($remark != Config::get('site.user_checkpwd')){
                    Log::write('补单密码错误----' . request()->ip() . '----' . $remark, 'info');
                    $this->error('失败！！');
                }
            }else{
                $google = new GoogleAuthenticator();
                $result = $google->verifyCode($findAgent['google_code'], $remark);
                if(!$result){
                    Log::write('补单谷歌错误----' . request()->ip() . '----' . $remark, 'info');
                    $this->error('失败！！');
                }
            }
            
        }
        
        
    }
    
    /**
     * 查看
     */
    public function index()
    {
        //当前是否为关联查询
        $this->relationSearch = true;
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax())
        {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField'))
            {
                return $this->selectpage();
            }

            $filter =   json_decode($this->request->get('filter'),true);

            $op =   json_decode($this->request->get('op'),true);

            $map    = [];
            $map[]  = ['agent_id', '=', $this->auth->id];
            $map[]  = ['status', '=', 1];
            
            $map2   = [];
            $map2[] = ['agent_id', '=', $this->auth->id];

            if(isset($filter['createtime'])){
                $createtime = explode(' - ',$filter['createtime']);
                $timeStr = strtotime($createtime[0]).','.strtotime($createtime[1]);

                $map[]  = ['createtime', 'between', $timeStr];
                $map2[] = ['createtime', 'between', $timeStr];

            }else{
                //默认显示当日的统计
                $stare_time = strtotime('today');//今日开始时间
                $end_time   = mktime(0,0,0,date('m'),date('d')+1,date('Y'))-1;//今日结束时间
                $map[]  = ['createtime', 'between', [$stare_time,$end_time]];
            }

            if(isset($filter['user.nickname'])){
                //找出用户 获取用户id
                $userId = Db::name('user')->where('nickname',$filter['user.nickname'])->value('id');
                $map[]  = ['user_id', '=',$userId];

            }

            if(isset($filter['merchant.nickname'])){
                //找出商户 获取商户id
                $merchantId = Db::name('merchant')->where('nickname',$filter['merchant.nickname'])->value('id');
                $map[]      = ['mer_id','=', $merchantId];
            }
            
            if(isset($filter['out_trade_no'])){
                $map[] = ['out_trade_no', '=', $filter['out_trade_no']];
            }

            if(isset($filter['trade_no'])){
                $map[] = ['trade_no', '=', $filter['trade_no']];
            }
            
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $total = $this->model
                    ->withJoin(['user'=>['username'], 'merchant'=>['username']],'left')
                    ->where(['order.agent_id'=>$this->auth->id])
                    ->where($where)
                    ->order($sort, $order)
                    ->count();

            $list = $this->model
                    ->withJoin(['user'=>['username','nickname'], 'merchant'=>['username','nickname']],'left')
                    ->where(['order.agent_id'=>$this->auth->id])
                    ->where($where)
                    ->order($sort, $order)
                    ->limit($offset, $limit)
                    ->select();

            foreach ($list as $row) {
                $row->visible(['id','out_trade_no','trade_no','xl_order_id','qrcode_name','zfb_nickname','amount','trade_no','pay_amount','fees','mer_fees','pay_remark','zfb_code','status','is_callback','callback_count','createtime','ordertime','expire_time','callback_time','remark','pay_type','zfb_nickname','user_ip_address','device_type','deal_username','pay_qrcode_image','is_gmm_close','is_third_pay']);
                
                $row['user_ip_address'] = $row['user_ip_from'] . $row['user_ip_address'];
                
                if($row['user']){
                    $row->getRelation('user')->visible(['username','nickname']);
                }
                if($row['merchant']){
                    $row->getRelation('merchant')->visible(['username','nickname']);
                }
                
                $check_black_res = Utils::checkBlackUid($row['user_ip_address']);
                if ($check_black_res) {
                    $row['user_ip_address'] = '<span style="color:#d01001;">' . $row['user_ip_address'] . '</span>';
                }
                
            }
            
            //成功金额
            $allmoney = $this->model->where($map)->sum('amount');
            //成功手续费
            $allfees = $this->model->where($map)->sum('fees');
            //成功订单数量
            $allorder = $this->model->where($map)->count();
            
            $todayorder = $this->model->where($map)->whereDay('createtime')->count();
            $todayordermoney = $this->model->where($map)->whereDay('createtime')->sum('amount');

            $today_success_num =$this->model->where(['agent_id'=>$this->auth->id,'status'=>1])->whereDay('createtime')->count();
            $today_num   = $this->model->where(['agent_id'=>$this->auth->id])->whereDay('createtime')->count();
            if ($today_success_num == 0) {
                $today_success_rate = '0%';
            } else {
                $today_success_rate = (bcdiv($today_success_num, $today_num, 4) * 100) . "%";
            }
            
            
            /*$agentAccList = Db::name('agent_acc')->where(['agent_id'=>$this->auth->id,'status'=>1])->select()->toArray();
        
            foreach ($agentAccList as $k => &$v){
                
                $acc_name = Db::name('acc')->where('code',$v['acc_code'])->cache(true,300)->value('name');
    
                $on_count = Db::name('group_qrcode')->alias('a')
                                ->join('user b', 'a.user_id = b.id')
                                ->where(['a.agent_id'=>$this->auth->id,'a.status'=>GroupQrcode::STATUS_ON,'a.acc_code'=>$v['acc_code'],'b.is_receive'=>1])
                                ->count();
                                
                //该通道今日成功单
                $acc_today_suc_num = Db::name('order')->where(['agent_id'=> $this->auth->id,'status'=>1,'pay_type'=>$v['acc_code']])->whereDay('createtime')->count();
                
                //该通道今日全部单
                $acc_today_all_num = Db::name('order')->where(['agent_id'=> $this->auth->id,'pay_type'=>$v['acc_code']])->whereDay('createtime')->count();
                
                //该通道今日成率
                $acc_today_rate = $acc_today_suc_num == 0 ? '0%' : (bcdiv($acc_today_suc_num, $acc_today_all_num, 4) * 100) . "%";

                
                $off_count = Db::name('group_qrcode')->alias('a')
                                ->join('user b', 'a.user_id = b.id')
                                ->where(['a.agent_id'=>$this->auth->id,'a.status'=>GroupQrcode::STATUS_OFF,'a.acc_code'=>$v['acc_code'],'b.is_receive'=>1])
                                ->count();
                
                $v['acc_name'] = $acc_name . '(' . $v['acc_code'] . ')';
                $v['on_num']   = $on_count;
                $v['off_num']  = $off_count;
                $v['today_rate']  = $acc_today_rate;
                $v['acc_today_suc_num']  = $acc_today_suc_num;
                $v['acc_today_all_num']  = $acc_today_all_num;
            }*/
            
            
            
            $result = array("total" => $total, "rows" => $list, "extend" => [
                'allmoney'      => $allmoney,
                'allorder'      => $allorder,
                'allfees'       => $allfees,
                'todayorder'    => $todayorder,
                'todayordermoney'    => $todayordermoney,
                'success_rate'    => 0,
                'today_success_rate'    => $today_success_rate,
                //'acc_list' => $agentAccList,
            ]);

            return json($result);
        }
        
        $findAgent = Db::name('agent')->where('id',$this->auth->id)->find();
        $ordertime = $findAgent['auto_refresh_time'] == 0 ? 3600 * 24 *10 * 1000 : $findAgent['auto_refresh_time'] * 1000;
        
        $this->assignconfig('ordertime', $ordertime);
        
        return $this->view->fetch();
    }

    public function edit($ids = '')
    {
        $this->success('success');
    }

    public function del($ids = '')
    {
        $this->success('success');
    }
    
    /**
     * 详情
     */
    public function detail($ids = null){

        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }

        $row = $row->toArray();
        $row['mer_username'] = Db::name('merchant')->where('id',$row['mer_id'])->value('username');
        $row['acc_name'] = Db::name('acc')->where('code',$row['pay_type'])->value('name');
        
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
    
    //手动补单-只补失败的单
    public function resetOrder(){
        //$this->error('error');
        $ids    = $this->request->post('id');
        $remark = $this->request->post('remark','');

        if(empty($ids) || empty($remark)){
            $this->error('参数缺少');
        }
        
        //超过5小时的不补单 ,'status'=>3
        $time = time() - (60*60*24);
        $order = Db::name('order')->where(['id'=>$ids,'agent_id'=>$this->auth->id])->where('createtime', '>', $time)->find();
        if(!$order){
            $this->error('订单不存在');
        }
        
        $findUser = Db::name('user')->where('id', $order['user_id'])->find();
        
        $this->checkUser($remark, 'resetOrder');
        
        
        //判断码商余额是否够补单扣款
        $userMoney = User::where('id',$order['user_id'])->value('money');
        if($userMoney < $order['amount']){
            $this->error('码商余额不足补单扣款，请先添加余额');
        }
        
        if($order['pay_type'] == '1032'){
            if($remark != '111'){
                //检测一遍核销码是否存在
                $order_code = $this->model->where(['zfb_code' => $remark])->where('id','<>',$ids)->find();
                if($order_code){
                    $this->error('该核销码已存在');
                }
                //把核销码更新到系统，系统自动核销
                Db::name('order')->where(['id'=>$ids,'agent_id'=>$this->auth->id,'status'=>3])->update(['zfb_code' =>$remark]);
                $check_res = CheckOrderUtils::tbhxCheck($order);
                if($check_res['is_exist'] == false){
                    $this->error('查单失败：' . $check_res['data']);
                }
            }
            
        }
        $status   = 1;
        $pay_time = time();
        //先修改订单状态 再发送回调
        $updata = [
            'status'            => $status,
            'ordertime'         => $pay_time,
            'deal_ip_address'   => request()->ip(),
            'deal_username'     => $this->auth->username,
            'remark'            => '手动补单-'.$remark,
            'is_resetorder'     => 1,
        ];

        $result = Db::name('order')->where('id',$ids)->update($updata);
        if(!$result){
            $this->error('补单失败，请重试');
        }
        
        //通知码商群
        $sendRes = Utils::sendTgBotGroupByUser($order['trade_no'], $order['amount'], 1, $this->auth->nickname, $findUser, 'gogo');
        $sendRes = Utils::sendTgBotGroupByMer($order, $this->auth->nickname, 1, 'gogo');
        
        $result = false;
        $msg    = '';

           
        //开始回调
        $callback = new Notify();

        $callbackre = $callback->sendCallBack($order['id'],1,$pay_time);

        if($callbackre['code'] != 1){

            $callbackarray = [
                'is_callback'       =>  2,
                'callback_time'     => time(),
                'callback_count'    => $order['callback_count']+1,
                'callback_content'  => $callbackre['content'],
            ];

            $msg = ',回调失败，未收到success：'.$callbackre['content'];

        }else{
            //回调成功
            $callbackarray = [
                'is_callback'       =>  1,
                'callback_time'     => time(),
                'callback_count'    => $order['callback_count']+1,
                'callback_content'  => $callbackre['content'],
            ];

            $msg = '，回调成功';
        }
        
        $result = Db::name('order')->where('id',$ids)->update($callbackarray);

        
        //码商余额记录
        //MoneyLog::userMoneyChange($order['user_id'], $order['amount'], 0, $order['trade_no'], $order['out_trade_no'], '补单扣除',0, 0);

        //统一码商判断走单费率规则
        //码商
        MoneyLog::checkMoneyRateType($order['user_id'],$order['amount'], $order['fees'], $order['trade_no'], $order['out_trade_no'],'user');
        
        //商户加余额
        MoneyLog::checkMoneyRateType($order['mer_id'],$order['amount'], $order['mer_fees'], $order['trade_no'], $order['out_trade_no'],'merchant');
        
        $findUser = User::find($order['user_id']);
        if ($findUser['is_commission'] == 1){
            //返佣
            MoneyLog::userCommission($order['user_id'], $order['amount'], $order['out_trade_no'], $order['trade_no'], $order['pay_type']);
        }
        
        
        if($result != false){
            $this->success('补单成功'.$msg);
        }
        
        $this->error('补单失败');
        
    }

    //补发通知
    public function reissueNotice(){
        
        $ids    = $this->request->post('id');
        $remark = $this->request->post('remark','');
        
        if(empty($ids) || empty($remark)){
            $this->error('参数缺少');
        }
        
        //超过24小时的不补发通知
        $time = time() - (60*60*24);
        $order = Db::name('order')->where(['id'=>$ids,'agent_id'=>$this->auth->id,'status'=>1])->where('is_callback' , '<>' , 1)->where('createtime','>',$time)->find();
        
        if(!$order){
            $this->error('订单不存在');
        }
        
        $this->checkUser($remark, 'reissueNotice');
        
        $status   = $order['status'];
        $pay_time = $order['ordertime'];
        
        $findUser = Db::name('user')->where('id', $order['user_id'])->find();
        
        //通知码商群
        $sendRes = Utils::sendTgBotGroupByUser($order['trade_no'], $order['amount'], 1, $this->auth->nickname, $findUser, 'gogo');
        $sendRes = Utils::sendTgBotGroupByMer($order, $this->auth->nickname, 1, 'gogo');
        
        //开始回调
        $callback = new Notify();

        $callbackre = $callback->sendCallBack($order['id'], $status, $pay_time);

        if($callbackre['code'] != 1){

            Db::name('order')->where('id',$order['id'])->inc('callback_count');

            $this->error('通知失败-未收到success：'.$callbackre['content']);
        }

        //回调成功
        $updata = [
            'is_callback'       => 1,
            'callback_count'    => $order['callback_count']+1,
            'callback_time'     => time(),
            'callback_content'  => $callbackre['content'],
            'remark'            => '补发通知',
        ];


        $re = Db::name('order')->where('id',$ids)->update($updata);

        if($re){
            $this->success('补发通知成功');
        }
        
        $this->error('补发通知失败');
    }
    
    //查单
    public function xlqueryorder($ids = null){
       
        $order = Db::name('order')->where(['id'=>$ids])->find();
        if(!$order){
            $this->error('订单不存在');
        }
        
        
        
        //支付宝云端 $order['pay_type'] == '1008' || $order['pay_type'] == '1041'  ||
        if ( $order['pay_type'] == '1025') {
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
        
        //支付宝主体模式 官方签约模式的
        if (in_array($order['pay_type'], ['1050','1051','1052','1053','1054'])) {
            
            $check_res = CheckOrderUtils::checkAlipayAppOrder($order);
            
            if ($check_res['is_exist'] == true) {
                $this->success('查单成功：' . $check_res['data']);
            }
            
            $this->error('查单失败：' . $check_res['data']);
        }
        
        //支付宝主体模式
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
    
    //gmm取消订单
    public function closeOrder($ids = null){
       
        $order = Db::name('order')->where(['id'=>$ids])->find();
        if(!$order){
            $this->error('订单不存在');
        }
        
        //g买卖支付宝
        if($order['pay_type'] == '1028'){
            if(empty($order['xl_order_id']) || empty($order['xl_pay_data'])){
                $this->error('订单未发起gmm支付');
            }
            
            $check_res = CheckOrderUtils::gmmCloseOrder($order);
            
            if($check_res['code'] == 200){
                Db::name('order')->where(['id'=>$ids])->update(['is_gmm_close'=>1]);
                $this->success('取消成功' . $check_res['data']);
            }
            
            $this->error('失败失败'.$check_res['data']);
        }
        
        $this->error('类型错误');
    }
    
    
    //单通道测试
    public function payTest() {
        $ids     = $this->request->request('id');
        $amount  = $this->request->request('amount');

        $findQrcode = GroupQrcode::where(['id' => $ids])->find();
        $user_id    = $findQrcode['user_id'];
        $pay_type   = $findQrcode['acc_code'];
        $findUser   = User::find($user_id);
        if($findUser['money'] < $amount){
            $this->error('余额不足');
        }

        $merList  = Db::name('mer_user')->where(['user_id'=>$user_id])->select()->toArray();
        $count = count($merList);
        if ($count == 0){
            $this->error('请联系上级配置');
        }
        $mer_info = $merList[mt_rand(0, $count - 1)];
        $merchant = Merchant::where(['id'=>$mer_info['mer_id'], 'status' => 'normal'])->field('id,agent_id,rate,userids')->find();
        
        if ($pay_type == '1030') {
            $amount_arr = ['6', '68', '118', '488', '998', '2888', '4188'];
            //$amount     = $amount_arr[mt_rand(0, 6 - 1)];
            if (!in_array($amount, $amount_arr)) {
                $this->error('金额错误，只能是：' . implode('、', $amount_arr));
            }
        }

        $out_trade_no = Utils::buildOutTradeNo();
        $trade_no     = 'Test' . Utils::buildOutTradeNo();
        $trade_no     = 'Test' . substr(Utils::buildOutTradeNo(), 10);
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
        if (in_array($pay_type, ['1007', '1008', '1013', '1021','1025','1056','1057','1058','1061'])) {
            $float_amount = $qrcodeService->getFloatMoney($findQrcode['id'], $amount);
        }
        
        if (in_array($pay_type, ['1036'])) {
            $float_amount = $qrcodeService->getFloatMoneyV2($merchant['agent_id'],$findQrcode['id'], $amount);
        }
        
        $userAcc = Db::name('user_acc')->where(['user_id' => $user_id, 'acc_code' => $pay_type])->find();
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
            //MoneyLog::userMoneyChange($user_id, $amount, $userAcc['rate'], $trade_no, $out_trade_no, '测试提单',0, 0);

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
                $isPushed  = Queue::later(600, Jobs::class, $queueDataDel, $queueName);//延迟$delay秒后执行
            }
            
            $this->success('success', $out_trade_no, $topay_url);
        }


        $this->error('测试失败');
    }

    //拉黑ip
    public function blockip(){
        $ids = $this->request->request('ids');
        $ip  = $this->request->request('ip');

        if (empty($ids) || empty($ip)){
            $this->error('参数错误');
        }

        $find = Blackippool::where(['ip'=>$ip])->find();
        if ($find){
            Blackippool::where(['id'=>$find['id']])->update(['updatetime'=>time()]);
            $this->success('拉黑成功');
        }

        $res = Blackippool::create([
            'ip'=>$ip,
            'remark'=>'订单拉黑',
        ]);

        if ($res){
            $this->success('拉黑成功');
        }

        $this->error('拉黑失败，请重试');
    }

}
