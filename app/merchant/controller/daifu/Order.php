<?php

namespace app\merchant\controller\daifu;

use app\common\controller\MerchantBackend;
use app\common\library\GoogleAuthenticator;
use app\common\library\MoneyLog;
use app\common\library\Utils;
use app\merchant\model\merchant\Merchant;
use think\Exception;
use think\exception\ValidateException;
use think\facade\Config;
use think\facade\Db;
use think\facade\Log;
use jianyan\excel\Excel;
use app\common\library\ThirdDf;

/**
 * 订单管理
 *
 * @icon fa fa-circle-o
 */
class Order extends MerchantBackend
{

    /**
     * Order模型对象
     * @var \app\admin\model\daifu\Dforder
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\daifu\Dforder;
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

            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $total = $this->model
                ->where(['mer_id'=>$this->auth->id])
                ->where($where)
                ->order($sort, $order)
                ->count();

            $list = $this->model
                ->where(['mer_id'=>$this->auth->id])
                ->where($where)
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();

            //foreach ($list as $row) {

            //}

            $money = Merchant::where('id',$this->auth->id)->field('money,block_money')->find();

            $countlist = $this->model->where(['mer_id'=>$this->auth->id])->where($where)->field('sum(amount) as allmoney, sum(fees) as allfees, count(*) as allorder')->select()->toArray();
            $countlist = $countlist[0];

            //今日订单
            $todayorder = $this->model->where(['mer_id'=>$this->auth->id])->whereDay('createtime')->count();
            //今日金额
            $todayordermoney = $this->model->where(['mer_id'=>$this->auth->id])->whereDay('createtime')->sum('amount');

            $result = array("total" => $total, "rows" => $list, "extend" => [
                'block_money'   => $money['block_money'],
                'money'         => $money['money'],
                'allmoney'      => $countlist['allmoney'],
                'allorder'      => $countlist['allorder'],
                'allfees'       => $countlist['allfees'],
                'todayorder'    => $todayorder,
                'todayordermoney'    => $todayordermoney,
            ]);

            return json($result);
        }

        $this->assignconfig('uid',$this->auth->id);
        $this->assignconfig('refreshtime',10000);

        return $this->view->fetch();
    }

    /**
     * 添加
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $params = $this->request->post('row/a');
            if ($params) {
                $params = $this->preExcludeFields($params);

                if ($this->dataLimit && $this->dataLimitFieldAutoFill) {
                    $params[$this->dataLimitField] = $this->auth->id;
                }

                Log::write(request()->ip().'----'.json_encode($params,JSON_UNESCAPED_UNICODE),'info');

                $result = false;

                Db::startTrans();
                try {

                    //找出当前商户
                    $merchant = Db::name('merchant')->where('id',$this->auth->id)->find();
                    if($merchant['status'] == 'hidden'){
                        $this->error('账号已被禁用');
                    }

                    //判断是否开启谷歌验证
                    if (Config::get('site.order_checkmerchantgoogle')) {
                        $google = new GoogleAuthenticator();
                        $result = $google->verifyCode($merchant['google_code'],$params['google_captcha']);
                        if(!$result){
                            $this->error('谷歌验证码错误');
                        }
                    }

                    if (Config::get('site.order_checkmerchantpaypwd')) {
                        if($merchant['pay_password'] != md5($params['pay_pwd'])){
                            $this->error('支付密码错误');
                        }
                    }

                    //判断是否开启ip验证
                    if (Config::get('site.order_checkmerchantpayip')) {
                        $login_ip = explode(",",$merchant['login_ip']);
                        if(!in_array(request()->ip(),$login_ip)){
                            $this->error('无操作权限');

                        }
                    }

                    if ($params['amount'] < $merchant['min_money'] || $params['amount'] > $merchant['max_money']){
                        $this->error('单笔金额错误');
                    }

                    //判断是否有重复
                    $repeat_order_time = Config::get('site.repeat_order_time');
                    if ($repeat_order_time != 0){
                        $three_hour = time() - $repeat_order_time;
                        $findBankNum = Db::name('order')->where(['amount'=>$params['amount'],'bank_number'=>$params['bank_number']])->where('createtime','>',$three_hour)->find();
                        if($findBankNum){
                            $this->error('重复信息');
                        }
                    }

                    /*//获取下发
                    if (empty($merchant['userids'])){
                        $this->error('暂无可用人员');
                    }
                    $user = Utils::getUser($this->auth->id,$merchant['userids']);
                    if (empty($user)){
                        $this->error('暂无可用人员!');
                    }*/

                    //判断是否充值先扣手续费
                    if (Config::get('site.is_recharge_fee')) {
                        $fees = $merchant['add_money'];
                    }else{

                        if($merchant['is_diy_rate'] == 1){//TODO
                            /*if(empty($merchant['diyratejson'])){
                                $this->error('系统费率配置错误');
                            }
                            $fees = Utils::getFee($params['amount'],$merchant['diyratejson']);
                            if($fees == 0){
                                $this->error('系统费率配置错误');
                            }*/
                            $this->error('系统费率配置错误');
                        }else{
                            $fees = bcmul($params['amount'],$merchant['df_rate'],2);
                            $fees = bcadd($fees,$merchant['add_money'],2);
                        }

                    }


                    if($merchant['money'] < bcadd($fees,$params['amount'],2)){
                        $this->error('余额不足，请先充值');
                    }

                    $params['fees']         = $fees;
                    $params['status']       = 2;
                    //$params['user_id']      = $user['id'];
                    $params['mer_id']       = $this->auth->id;
                    $params['agent_id']     = $this->auth->agent_id;
                    $params['out_trade_no'] = Utils::buildOutTradeNo();
                    $params['ip_address']   = request()->ip();
                    
                    //商户扣除余额
                    //MoneyLog::merchantMoneyChangeByDf($this->auth->id, $params['amount'], 0, $params['out_trade_no'], $params['out_trade_no'], '代付扣除',0);

                    $result = $this->model->save($params);


                    Db::commit();

                } catch (Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }

                if ($result !== false) {
                    
                    
                    //判断是否转入三方代付
                    if($merchant['is_third_df'] == '1'){
                        
                        $df_res = ThirdDf::instance()->checkDfType($params['out_trade_no'], $params['bank_user'], $params['bank_type'], $params['bank_number'], $params['amount'], $merchant['df_acc_ids']);
                    }
                    
                    
                    $this->success('提交成功');
                } else {
                    $this->error(__('No rows were inserted'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }

        return $this->view->fetch();
    }


    /**
     * 批量添加
     */
    public function batch_add()
    {
        if ($this->request->isPost()) {
            $params = $this->request->post('row/a');
            if ($params) {
                $params = $this->preExcludeFields($params);

                if ($this->dataLimit && $this->dataLimitFieldAutoFill) {
                    $params[$this->dataLimitField] = $this->auth->id;
                }

                Log::write('batch_add----'.request()->ip().'----'.json_encode($params,JSON_UNESCAPED_UNICODE),'info');

                //找出当前商户
                $merchant = Db::name('merchant')->where('id',$this->auth->id)->find();

                if($merchant['status'] == 'hidden'){
                    $this->error('账号已被禁用');
                }

                //判断是否开启谷歌验证
                if (Config::get('site.order_checkmerchantgoogle')) {
                    $google = new GoogleAuthenticator();
                    $result = $google->verifyCode($merchant['google_code'],$params['google_captcha']);
                    if(!$result){
                        $this->error('谷歌验证码错误');
                    }
                }

                if (Config::get('site.order_checkmerchantpaypwd')) {
                    if($merchant['pay_password'] != md5($params['pay_pwd'])){
                        $this->error('支付密码错误');
                    }
                }

                //判断是否开启ip验证
                if (Config::get('site.order_checkmerchantpayip')) {
                    $login_ip = explode(",",$merchant['login_ip']);
                    if(!in_array(request()->ip(),$login_ip)){
                        $this->error('无操作权限');

                    }
                }

                $orderJson = $params['orderjson'];
                if(empty($orderJson)){
                    $this->error('请填写订单信息');
                }

                //判断是否充值先扣手续费
                if (Config::get('fastadmin.is_recharge_fee')) {
                    $is_recharge_fee = true; //充值先扣
                }else{
                    $is_recharge_fee = false;//提单一起扣
                }

                $orderJson = json_decode($orderJson,true);

                $orderMoney = 0;
                $orderFees  = 0;
                $repeat_order_time = Config::get('site.repeat_order_time');

                foreach ($orderJson as $key1 => $value1){

                    $orderMoney += $value1['amount'];

                    if ($value1['amount'] < $merchant['min_money'] || $value1['amount'] > $merchant['max_money']){
                        $this->error('单笔金额错误：'.$value1['amount']);
                    }

                    //判断是否有重复
                    if ($repeat_order_time != 0){
                        $three_hour = time() - $repeat_order_time;
                        $findBankNum = $this->model->where(['amount'=>$value1['amount'],'bank_number'=>$value1['bank_number']])->where('createtime','>',$three_hour)->find();
                        if($findBankNum){
                            $this->error('重复信息');
                        }
                    }


                    //计算全部费率 用于判断余额
                    if($is_recharge_fee){
                        $orderFees += $merchant['add_money'];//单笔
                    }else{

                        //判断是自定义费率还是固定费率
                        if($merchant['is_diy_rate'] == 1){//TODO
                            /*if(empty($merchant['diyratejson'])){
                                $this->error('系统费率配置错误');
                            }
                            $oneOrderFees = Utils::getFee($value1['amount'],$merchant['diyratejson']);*/

                        }else{
                            $oneOrderFees = bcmul($value1['amount'], $merchant['df_rate'], 2);//计算出手续费
                            $oneOrderFees = bcadd($oneOrderFees, $merchant['add_money'], 2);//再加上单笔
                        }

                        $orderFees += $oneOrderFees;
                    }

                }

                $orderNum = count($orderJson);

                if ($merchant['money'] < bcadd($orderMoney,$orderFees,2)) {
                    $this->error('余额不足支付此次订单');
                }


                /*//获取下发
                if (empty($merchant['userids'])){
                    $this->error('暂无可用人员');
                }
                $userList = Utils::getUser($this->auth->id,$merchant['userids'],false);
                if (empty($userList)){
                    $this->error('暂无可用人员');
                }*/

                //$users_count = count($userList);
                $success_num = 0;

                foreach ($orderJson as $key => $value){

                    if(trim($value['amount']) <= 0 || empty(trim($value['bank_number'])) || empty(trim($value['bank_user'])) ){
                        continue;
                    }

                    //随机取下发
                    //$user = $userList[mt_rand(0, $users_count - 1)];
                    $user = [];

                    //判断是否先扣手续费
                    if($is_recharge_fee){
                        $fees = $merchant['add_money'];//单笔
                    } else {

                        //判断是自定义费率还是固定费率
                        if($merchant['is_diy_rate'] == 1){
                            /*if(empty($merchant['diyratejson'])){
                                $this->error('系统费率配置错误');
                            }
                            $fees = Utils::getFee($value['amount'],$merchant['diyratejson']);*/

                        }else{
                            $fees = bcmul($value['amount'], $merchant['df_rate'], 2);//计算出手续费
                            $fees = bcadd($fees, $merchant['add_money'], 2);//再加上单笔
                        }
                    }


                    $bankInfo = [
                        'bank_number' => trim($value['bank_number']),
                        'bank_type'   => trim($value['bank_type']),
                        'bank_user'   => trim($value['bank_user']),
                        'amount'      => trim($value['amount']),
                        'fees'        => $fees,
                        'remark'      => trim($value['remark']),
                    ];


                    $result = $this->batch_add_order($bankInfo, $user, $merchant);

                    if($result){
                        $success_num++;
                    }

                }

                $fail_num = $orderNum - $success_num;

                $this->success('提交数量：'.$orderNum.'，成功数量：'.$success_num.'，失败数量：'.$fail_num);

            }
            $this->error(__('Parameter %s can not be empty', ''));
        }

        return $this->view->fetch();
    }

    public function batch_add_order($info, $user, $merchant){
        
        //生成订单号
        $out_trade_no               = Utils::buildOutTradeNo();
        $insertdata['out_trade_no'] = $out_trade_no;
        $insertdata['trade_no']     = $out_trade_no;
        //$insertdata['user_id']      = $user['id'];
        $insertdata['mer_id']       = $merchant['id'];
        $insertdata['agent_id']     = $merchant['agent_id'];
        $insertdata['amount']       = $info['amount'];
        $insertdata['fees']         = $info['fees'];
        $insertdata['bank_number']  = $info['bank_number'];
        $insertdata['bank_type']    = $info['bank_type'];
        $insertdata['bank_user']    = $info['bank_user'];
        $insertdata['ip_address']   = request()->ip();
        $insertdata['createtime']   = time();
        $insertdata['status']       = 2;
        $insertdata['remark']       = $info['remark'];

        $result = $this->model->insert($insertdata);

        //商户扣除余额
        MoneyLog::merchantMoneyChangeByDf($merchant['id'], $info['amount'], 0, $out_trade_no, $out_trade_no, '代付扣除',0);

        if($result){
            
            //判断是否转入三方代付
            if($merchant['is_third_df'] == '1'){
                $df_res = ThirdDf::instance()->checkDfType($out_trade_no, $info['amount'], $merchant['df_acc_ids']);
            }
            
            return true;
        }

        return false;

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

        $row['createtime']    = $row['createtime'] ? date('Y-m-d H:i:s', $row['createtime']) : '';
        $row['ordertime']     = $row['ordertime'] ? date('Y-m-d H:i:s', $row['ordertime']) : '';
        $row['expire_time']   = $row['expire_time'] ? date('Y-m-d H:i:s', $row['expire_time']) : '';
        $row['callback_time'] = $row['callback_time'] ? date('Y-m-d H:i:s', $row['callback_time']) : '';
        $this->view->assign("row", $row);
        return $this->view->fetch();

    }

    public function exportdemo(){

        /*$header = [
            ['ID', 'id', 'text'], // 规则不填默认text
            ['真实姓名', 'bank_user', 'text'], // 规则不填默认text
            ['银行地址', 'bank_address', 'text'], // 规则不填默认text
            ['银行卡号', 'bank_number', 'text'],
            ['提现金额', 'amount', 'text'],
            ['银行', 'bank_type', 'text'],
        ];*/

        $header = [
            ['收款银行户名', 'bank_user', 'text'], // 规则不填默认text
            ['收款银行名称', 'bank_type', 'text'], // 规则不填默认text
            ['收款银行账号', 'bank_number', 'text'],
            ['收款金额（元）', 'amount', 'text'],
        ];

        $list = [];
        // 简单使用
        return Excel::exportData($list, $header , '导入模板', 'xlsx');
    }

    /**
     * 导入1
     */
    public function import(){

        $file   = $this->request->request('file');
        $file   = substr($file, 1);
        $import = Excel::import($file, 2);

        unset($import[0]);

        $merchant = Db::name('merchant')->where(['id'=>$this->auth->id,'status'=>'normal'])->find();
        if(!$merchant){
            $this->error('账号不存在');
        }



        /*//获取下发
        if (empty($merchant['userids'])){
            $this->error('暂无可用人员');
        }

        $user = Utils::getUser($this->auth->id, $merchant['userids']);
        if (empty($user)){
            $this->error('暂无可用人员!');
        }*/


        //判断是否充值先扣手续费
        if (Config::get('fastadmin.is_recharge_fee')) {
            $is_recharge_fee = true; //充值先扣
        }else{
            $is_recharge_fee = false;//提单一起扣
        }

        $orderMoney = 0;
        $orderFees  = 0;
        $repeat_order_time = Config::get('site.repeat_order_time');

        foreach ($import as $key1 => $value1){
            if (empty($value1) || empty($value1[1])) {
                unset($import[$key1]);
                continue;
            }
            if(count($value1) != 4){
                $this->error('导入格式错误，请核实');
                break;
            }
            //格式1ID	真实姓名	银行地址	银行卡号	提现金额	银行
            /*$bank_user   = $value1[1];
            $bank_number = $value1[3];
            $amount      = $value1[4];
            $bank_type   = $value1[5];*/

            //格式2收款银行户名	收款银行名称	收款银行账号	收款金额（元）
            $bank_user   = $value1[0];
            $bank_number = $value1[2];
            $amount      = $value1[3];
            $bank_type   = $value1[1];


            $orderMoney += $amount;

            if ($amount < $merchant['min_money'] || $amount > $merchant['max_money']){
                $this->error('单笔金额错误：'.$amount);
            }

            //判断是否有重复
            if ($repeat_order_time != 0){
                $three_hour = time() - $repeat_order_time;
                $findBankNum = Db::name('order')->where(['bank_number'=>$bank_number])->where('createtime','>',$three_hour)->find();
                if($findBankNum){
                    $this->error('该订单信息重复出款，请确认'.$amount.'----'.$bank_user.'----'.$bank_number);
                }
            }


            //计算全部费率 用于判断余额
            if($is_recharge_fee){
                $orderFees += $merchant['add_money'];//单笔
            }else{

                //判断是自定义费率还是固定费率
                if($merchant['is_diy_rate'] == 1){
                    if(empty($merchant['diyratejson'])){
                        $this->error('系统费率配置错误');
                    }
                    $oneOrderFees = Utils::getFee($amount,$merchant['diyratejson']);

                }else{
                    $oneOrderFees = bcmul($amount, $merchant['df_rate'], 2);//计算出手续费
                    $oneOrderFees = bcadd($oneOrderFees, $merchant['add_money'], 2);//再加上单笔
                }

                $orderFees += $oneOrderFees;
            }

        }

        if ($merchant['money'] < bcadd($orderMoney,$orderFees,2)) {
            $this->error('余额不足支付此次订单');
        }

        $success_num = 0; //成功数量
        $fail_num    = 0; //失败数量
        $order_num   = count($import);//表格订单数量
        //halt($import);
        foreach ($import as $k => $v) {
            ;
            $insertdata = [];
            if (empty($v)) {
                continue;
            }

            //格式1
            /*$bank_user   = $v[1];
            $bank_number = $v[3];
            $amount      = $v[4];
            $bank_type   = $v[5];*/

            //格式2
            $bank_user   = $v[0];
            $bank_number = $v[2];
            $amount      = $v[3];
            $bank_type   = $v[1];

            //判断是否先扣手续费
            if($is_recharge_fee){
                $fees = $merchant['add_money'];//单笔
            } else {

                //判断是自定义费率还是固定费率
                if($merchant['is_diy_rate'] == 1){
                    if(empty($merchant['diyratejson'])){
                        $this->error('系统费率配置错误');
                    }
                    $fees = Utils::getFee($amount,$merchant['diyratejson']);

                }else{
                    $fees = bcmul($amount, $merchant['df_rate'], 2);//计算出手续费
                    $fees = bcadd($fees, $merchant['add_money'], 2);//再加上单笔
                }
            }



            //$user = Utils::getUser($this->auth->id, $merchant['userids']);
            //$insertdata['user_id']      = $user['id'];


            //生成订单号
            $out_trade_no               = Utils::buildOutTradeNo();
            $insertdata['out_trade_no'] = $out_trade_no;
            $insertdata['mer_id']       = $merchant['id'];
            $insertdata['agent_id']     = $merchant['agent_id'];
            $insertdata['amount']       = $amount;
            $insertdata['fees']         = $fees;
            $insertdata['bank_user']    = $bank_user;
            $insertdata['bank_number']  = $bank_number;
            $insertdata['bank_type']    = $bank_type;
            $insertdata['ip_address']   = request()->ip();
            $insertdata['createtime']   = time();
            $insertdata['status']       = 2;

            $re = Db::name('order')->insertGetId($insertdata);


            if ($re) {

                Utils::merchantMoneyLogV3($this->auth->id,$amount,$fees,$out_trade_no,'提单扣款',true,0);

                $success_num++;

            }else{
                $fail_num++;
            }

        }


        $this->success('导入成功，总数：'.$order_num.'，成功数：'.$success_num.'，失败数：'.$fail_num);

    }

    /**
     * 导入2
     */
    public function importsec(){

        $file   = $this->request->request('file');
        $file   = substr($file, 1);
        $import = Excel::import($file, 2);

        unset($import[0]);

        $merchant = Db::name('merchant')->where(['id'=>$this->auth->id,'status'=>'normal'])->find();
        if(!$merchant){
            $this->error('账号不存在');
        }



        /*//获取下发
        if (empty($merchant['userids'])){
            $this->error('暂无可用人员');
        }

        $user = Utils::getUser($this->auth->id, $merchant['userids']);
        if (empty($user)){
            $this->error('暂无可用人员!');
        }*/


        //判断是否充值先扣手续费
        if (Config::get('fastadmin.is_recharge_fee')) {
            $is_recharge_fee = true; //充值先扣
        }else{
            $is_recharge_fee = false;//提单一起扣
        }

        $orderMoney = 0;
        $orderFees  = 0;
        $repeat_order_time = Config::get('site.repeat_order_time');

        foreach ($import as $key1 => $value1){
            if (empty($value1) || empty($value1[1])) {
                unset($import[$key1]);
                continue;
            }
            if(count($value1) != 6){
                $this->error('导入格式错误，请核实');
                break;
            }

            //格式1ID	真实姓名	银行地址	银行卡号	提现金额	银行
            $bank_user   = $value1[1];
            $bank_number = $value1[3];
            $amount      = $value1[4];
            $bank_type   = $value1[5];

            /*//格式2收款银行户名	收款银行名称	收款银行账号	收款金额（元）
            $bank_user   = $value1[0];
            $bank_number = $value1[2];
            $amount      = $value1[3];
            $bank_type   = $value1[1];*/


            $orderMoney += $amount;

            if ($amount < $merchant['min_money'] || $amount > $merchant['max_money']){
                $this->error('单笔金额错误：'.$amount);
            }

            //判断是否有重复
            if ($repeat_order_time != 0){
                $three_hour = time() - $repeat_order_time;
                $findBankNum = Db::name('order')->where(['bank_number'=>$bank_number])->where('createtime','>',$three_hour)->find();
                if($findBankNum){
                    $this->error('该订单信息重复出款，请确认'.$amount.'----'.$bank_user.'----'.$bank_number);
                }
            }


            //计算全部费率 用于判断余额
            if($is_recharge_fee){
                $orderFees += $merchant['add_money'];//单笔
            }else{

                //判断是自定义费率还是固定费率
                if($merchant['is_diy_rate'] == 1){
                    if(empty($merchant['diyratejson'])){
                        $this->error('系统费率配置错误');
                    }
                    $oneOrderFees = Utils::getFee($amount,$merchant['diyratejson']);

                }else{
                    $oneOrderFees = bcmul($amount, $merchant['df_rate'], 2);//计算出手续费
                    $oneOrderFees = bcadd($oneOrderFees, $merchant['add_money'], 2);//再加上单笔
                }

                $orderFees += $oneOrderFees;
            }

        }

        if ($merchant['money'] < bcadd($orderMoney,$orderFees,2)) {
            $this->error('余额不足支付此次订单');
        }

        $success_num = 0; //成功数量
        $fail_num    = 0; //失败数量
        $order_num   = count($import);//表格订单数量
        //halt($import);
        foreach ($import as $k => $v) {
            ;
            $insertdata = [];
            if (empty($v)) {
                continue;
            }

            //格式1
            $bank_user   = $v[1];
            $bank_number = $v[3];
            $amount      = $v[4];
            $bank_type   = $v[5];

            //格式2
            /*$bank_user   = $v[0];
            $bank_number = $v[2];
            $amount      = $v[3];
            $bank_type   = $v[1];*/

            //判断是否先扣手续费
            if($is_recharge_fee){
                $fees = $merchant['add_money'];//单笔
            } else {

                //判断是自定义费率还是固定费率
                if($merchant['is_diy_rate'] == 1){
                    if(empty($merchant['diyratejson'])){
                        $this->error('系统费率配置错误');
                    }
                    $fees = Utils::getFee($amount,$merchant['diyratejson']);

                }else{
                    $fees = bcmul($amount, $merchant['df_rate'], 2);//计算出手续费
                    $fees = bcadd($fees, $merchant['add_money'], 2);//再加上单笔
                }
            }



            //$user = Utils::getUser($this->auth->id, $merchant['userids']);
            //$insertdata['user_id']      = $user['id'];


            //生成订单号
            $out_trade_no               = Utils::buildOutTradeNo();
            $insertdata['out_trade_no'] = $out_trade_no;
            $insertdata['mer_id']       = $merchant['id'];
            $insertdata['agent_id']     = $merchant['agent_id'];
            $insertdata['amount']       = $amount;
            $insertdata['fees']         = $fees;
            $insertdata['bank_user']    = $bank_user;
            $insertdata['bank_number']  = $bank_number;
            $insertdata['bank_type']    = $bank_type;
            $insertdata['ip_address']   = request()->ip();
            $insertdata['createtime']   = time();
            $insertdata['status']       = 2;

            $re = Db::name('order')->insertGetId($insertdata);


            if ($re) {

                //Utils::merchantMoneyLogV3($this->auth->id,$amount,$fees,$out_trade_no,'提单扣款',true,0);

                $success_num++;

            }else{
                $fail_num++;
            }

        }


        $this->success('导入成功，总数：'.$order_num.'，成功数：'.$success_num.'，失败数：'.$fail_num);

    }
}
