<?php

namespace app\user\controller\thirdacc;

use app\admin\model\Acc;
use app\common\controller\UserBackend;
use app\admin\model\order\Order;
use app\admin\model\thirdacc\Useracc;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Request;
use app\common\library\Utils;
use think\facade\Config;
use fast\Random;
use fast\Http;
use app\common\library\Accutils;
use jianyan\excel\Excel;
use Zxing\QrReader;
use think\facade\Cache;
use app\admin\model\GroupQrcode;
use app\common\library\AlipaySdk;
use app\common\library\CheckOrderUtils;

/**
 * 收款码管理
 *
 * @icon fa fa-circle-o
 */
class Userqrcode extends UserBackend
{

    /**
     * Userqrcode模型对象
     * @var \app\user\model\thirdacc\Userqrcode
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\user\model\thirdacc\Userqrcode;
        $this->view->assign("typeList", $this->model->getTypeList());
        $this->view->assign("statusList", $this->model->getStatusList());
        $this->view->assign("isUseList", $this->model->getIsUseList());
        $this->view->assign("accList", $this->getUseracc());
        $this->view->assign("isthirdhxList", $this->model->getIsthirdhxList());
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
        $this->relationSearch = false;
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax())
        {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField'))
            {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $total = $this->model
                ->where(['user_id'=>$this->auth->id])
                ->where($where)
                ->order($sort, $order)
                ->count();

            $list = $this->model
                ->where(['user_id'=>$this->auth->id])
                ->where($where)
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();

            foreach ($list as $row) {

                $row['acc_type'] = Db::name('acc')->where('code',$row['acc_code'])->value('name');

                //今日成功金额收款
                $today_suc_money = Order::where(['qrcode_id'=>$row->id,'status'=>1])->whereDay('createtime')->sum('amount');

                //今日金额
                $today_money = Order::where(['qrcode_id'=>$row->id])->whereDay('createtime')->sum('amount');

                //总成功收款
                $all_suc_money = Order::where(['qrcode_id'=>$row->id,'status'=>1])->sum('amount');

                //总收款
                $all_money = Order::where(['qrcode_id'=>$row->id])->sum('amount');

                //该通道总成功订单
                $successorder = Order::where(['status'=>1,'qrcode_id'=>$row->id])->count();

                //该通道总订单
                $allorder = Order::where(['qrcode_id'=>$row->id])->count();

                //该通道今日成功订单
                $todaysuccessorder = Order::where(['status'=>1,'qrcode_id'=>$row->id])->whereDay('createtime')->count();


                //该通道今日订单
                $todayallorder = Order::where(['qrcode_id'=>$row->id])->whereDay('createtime')->count();


                //总成功率
                $success_rate = $allorder == 0 ? "0%" : (bcdiv($successorder,$allorder,4) * 100) ."%";

                //今日成功率
                $today_success_rate = $todayallorder == 0 ? "0%" : (bcdiv($todaysuccessorder,$todayallorder,4) * 100) ."%";

                //昨日成功金额
                $yesterday_suc_money = Order::where(['qrcode_id'=>$row->id,'status'=>1])->whereDay('createtime','yesterday')->sum('amount');

                //昨日成功笔数
                $yesterday_suc_order = Order::where(['qrcode_id'=>$row->id,'status'=>1])->whereDay('createtime','yesterday')->count();

                //昨日总金额
                $yesterday_all_money = Order::where(['qrcode_id'=>$row->id])->whereDay('createtime','yesterday')->sum('amount');

                //昨日总笔数
                $yesterday_all_order = Order::where(['qrcode_id'=>$row->id])->whereDay('createtime','yesterday')->count();

                /*$row['statistics'] = '今日笔数:<span style="color:#18BC9C;font-weight:bold;">' . $todaysuccessorder . '</span> / ' . '<span style="color:#18BC9C;font-weight:bold;">' . $todayallorder . '</span>' . '&nbsp&nbsp今日金额:<span style="color:red;font-weight:bold;">' . $today_suc_money . '</span>/ <span style="color:red;font-weight:bold;">' . $today_money . '</span></br>' . '今日成功率:<span style="color:#007BFF;font-weight:bold;">' . $today_success_rate . '</span></br>' . '昨日笔数:</span><span style="color:#18BC9C;font-weight:bold;">' . $yesterday_suc_order . '</span> /<span style="color:#18BC9C;font-weight:bold;">' . $yesterday_all_order . '</span>&nbsp&nbsp昨日金额:<span style="color:red;font-weight:bold;">' . $yesterday_suc_money . '</span> / <span style="color:red;font-weight:bold;">' . $yesterday_all_money . '</span>';*/

                $row['success_conf'] = '成功限制:'.$row['success_order_num'].'笔</br>' . '已成功' . $todaysuccessorder;
                
                
                
            }


            $result = array("total" => $total, "rows" => $list);

            return json($result);
        }

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
                $result = false;
                Db::startTrans();

                try {
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name     = str_replace('\\model\\', '\\validate\\', get_class($this->model));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name.'.add' : $name) : $this->modelValidate;
                        validate($validate)->scene($this->modelSceneValidate ? 'edit' : $name)->check($params);
                    }

                    $userRate = Db::name('user_acc')->where(['user_id'=>$this->auth->id,'acc_code'=>$params['acc_code']])->find();
                    if(empty($userRate)){
                        $this->error('请先配置通道费率');
                    }
                    $params['user_id']     = $this->auth->id;
                    $params['agent_id']    = $this->auth->agent_id;
                    $params['create_time'] = time();
                    $android_key           = Random::alnum(6);
                    $params['android_key'] = $android_key;

                    if(isset($params['zfb_pid'])){
                        $zfb_pid = str_replace(' ', '', trim($params['zfb_pid']));
                        $params['zfb_pid'] = $zfb_pid;
                    }


                    $findqrcode = Db::name('group_qrcode')->where('android_key',$android_key)->find();
                    if($findqrcode){
                        $this->error('设备值已存在，请重试');
                    }

                    //二维码解析
                    if ($params['acc_code'] == '1025' && !empty($params['image'])) {
                        //$image = app()->getRootPath() . substr($params['image'],1); 
                        $image = app()->getRootPath() . 'public' . $params['image'];
                        $qrcode = new QrReader($image);//绝对路径
                        $imgText = $qrcode->text(); //返回二维码的内容
                        $params['pay_url'] = $imgText;
                    }

                    /*//检测uid/迅雷id是否存在
                    $findqrcode = Db::name('group_qrcode')->where(['zfb_pid'=>$params['zfb_pid'],'status'=>1])->find();
                    if($findqrcode){
                        $this->error('uid已存在，请核实');
                    }*/

                    //迅雷的码 先获取uid
                    if($params['acc_code'] == '1014'){

                        $getParams = [];
                        $options = [];

                        $accutils = new Accutils();
                        $time = $accutils->getMsectime();
                        $sign = md5('1002_t='.$time.'&a=getroom&c=room&uuid='.$params['zfb_pid'].'&*%$7987321GKwq');
                        $url  = 'https://pc-live-ssl.xunlei.com/caller?c=room&a=getroom&uuid='.$params['zfb_pid'].'&_t='.$time.'&sign='.$sign;
                        $result  = json_decode(Http::get($url, $getParams, $options),true);

                        if(empty($result['data']['userInfo'])){
                            $this->error('迅雷用户不存在');
                        }

                        $params['xl_user_id'] = $result['data']['userInfo']['userid'];

                    }

                    //愿聊-迅雷之锤的码 先获取uid
                    if($params['acc_code'] == '1029'){

                        $url = 'https://svr-mozhi.xunleizhichui.com/melon.bizmember.s/v1/query';

                        $accutils = new Accutils();
                        $ts       = $accutils->getMsectime();

                        $postData = [
                            'phoneno'   => $params['zfb_pid'],
                            'base' => [
                                'app' => 'com.cn.xcub.make.h5',
                                'av'  => '1.1.0',
                                'dt'  => 3,
                                'did' => '19a3d6e1435da4aa0d829a719c5e06b9',
                                'ch'  => 'web_pay',
                                'ts'  => $ts,
                            ],
                        ];

                        $res    = Http::post($url, json_encode($postData), []);
                        $result = json_decode($res,true);

                        if(!isset($result['data'])){
                            $this->error('账号不存在'.$result['msg']);
                        }

                        $params['xl_user_id'] = $result['data']['userid'];

                    }

                    //愿聊-迅雷之锤的码 先获取uid
                    if($params['acc_code'] == '1030'){

                        $url = 'https://h5-api.neoclub.cn/v1/bff/web/user/login?account='.$params['zfb_pid'];

                        $res    = Http::get($url, [], []);
                        $result = json_decode($res,true);

                        if($result['code'] != 0){
                            $this->error('账号不存在'.$result['message']);
                        }

                        $params['xl_user_id'] = $result['data']['uid'];
                        $params['xl_cookie']  = $result['data']['token'];

                    }

                    //我秀 先获取user_id
                    if($params['acc_code'] == '1033'){

                        $url = 'https://www.woxiu.com/api/woxiu.php';
                        $t = '0.' . Random::numeric(16);
                        $postData = 'roomid='.$params['zfb_pid'].'&type=userinfo&_='.$t;
                        $res    = Http::post($url, $postData, []);

                        $result = json_decode($res,true);

                        if($result['status'] != 1){
                            $this->error('房间号不存在'.$result['msg']);
                        }

                        $params['xl_user_id'] = $result['user_id'];

                    }


                    $result = $this->model->save($params);
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
                    $this->error(__('No rows were inserted'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }

        return $this->view->fetch();
    }

    /**
     * 编辑
     */
    public function edit($ids = null)
    {
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
        if ($this->request->isPost()) {
            $params = $this->request->post('row/a');
            if ($params) {
                $params = $this->preExcludeFields($params);


                $findqrcode = Db::name('group_qrcode')->where('android_key',$params['android_key'])->where('id','<>',$ids)->find();
                if($findqrcode){
                    $this->error('设备值已存在，请重试');
                }

                /*//检测uid/迅雷id是否存在
                $findqrcode = Db::name('group_qrcode')->where(['zfb_pid'=>$params['zfb_pid'],'status'=>1])->where('id','<>',$ids)->find();
                if($findqrcode){
                    $this->error('uid已存在，请核实');
                }*/

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

                    if(!empty($params['zfb_pid'])){
                        $zfb_pid = str_replace(' ', '', trim($params['zfb_pid']));
                        $params['zfb_pid'] = $zfb_pid;
                    }


                    //二维码解析
                    /*if ($params['acc_code'] == '1025' && !empty($params['image'])) {
                        //$image = app()->getRootPath() . substr($params['image'],1); 
                        $image = app()->getRootPath() . 'public' . $params['image'];
                        $qrcode = new QrReader($image);//绝对路径
                        $imgText = $qrcode->text(); //返回二维码的内容
                        $params['pay_url'] = $imgText;
                    }*/

                    if($params['acc_code'] == '1028'){
                        //从ck中获取device_id
                        $pieces = array_filter(array_map('trim', explode(';', $params['xl_cookie'])));
                        foreach ($pieces as $part) {
                            $ck_arr = explode('=', $part, 2);
                            $key    = trim($ck_arr[0]);
                            $value  = trim($ck_arr[1]);
                            if ($key == 'deviceId' || $key == 'device_id') {
                                $params['business_url'] = $value;
                                break;
                            }
                        }

                    }

                    if($params['acc_code'] == '1032'){
                        //从ck中获取token
                        $pieces = array_filter(array_map('trim', explode(';', base64_decode($params['xl_cookie']))));
                        foreach ($pieces as $part) {
                            $ck_arr = explode('=', $part, 2);
                            $key    = trim($ck_arr[0]);
                            $value  = trim($ck_arr[1]);
                            if ($key == '_tb_token_') {
                                $params['token'] = $value;
                                break;
                            }
                        }

                    }

                    //我秀 先获取user_id
                    if($params['acc_code'] == '1033'){

                        $url = 'https://www.woxiu.com/api/woxiu.php';
                        $t = '0.' . Random::numeric(16);
                        $postData = 'roomid='.$params['zfb_pid'].'&type=userinfo&_='.$t;
                        $res    = Http::post($url, $postData, []);

                        $result = json_decode($res,true);

                        if($result['status'] != 1){
                            $this->error('房间号不存在'.$result['msg']);
                        }

                        $params['xl_user_id'] = $result['user_id'];

                    }
                    
                    if(isset($params['gd_alipay_json'])){
                        $params['gd_alipay_json'] = json_encode($params['gd_alipay_json']);
                    }
                    
                    $params['update_time'] = time();

                    $result = $row->save($params);
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
        
        $app_secret = Config::get('site.app_secret');
        $app_config = Utils::imagePath('/'.$app_secret,true).'/'.$row['android_key'];
        
        $datalist = $this->getMyAlipayConfig();
        
        $this->assign('zhuti_list', $datalist);
        
        $this->assignconfig('app_config',$app_config);

        return $this->view->fetch();
    }

    //切换启用状态
    public function change($ids = ''){
        if($ids){
            $row        = $this->model->get($ids);
            $status     = $row['status'] == 1 ? 0 : 1;
            $updateData = ['status'=>$status,'update_time'=>time()];
            if(in_array($row['acc_code'], ['1008','1050']) && $row['status'] == 0){
                $updateData['remark'] = '';
            }
            $re =$this->model->where('id',$ids)->update($updateData);

            if($re){
                $this->success("切换成功");
            }

            $this->error("切换失败");
        }

        $this->error("参数缺少");
    }

    //获取通道
    public function getUseracc(){

        $list = Db::name('user_acc')
            ->alias('a')
            ->join('acc b','a.acc_id = b.id','left')
            ->where(['a.user_id'=>$this->auth->id,'a.status'=>1])
            ->field('b.name,b.code')
            ->select();
        $datalist = [];
        foreach ($list as $index => $item) {
            $datalist[$item['code']] = $item['name'];
        }

        return $datalist;
    }

    //获取通道
    public function getAccForSelect(){

        $list = Db::name('user_acc')
            ->alias('a')
            ->join('acc b','a.acc_id = b.id','left')
            ->where(['a.user_id'=>$this->auth->id,'a.status'=>1])
            ->field('b.name,b.code')
            ->select();
        $datalist = [];
        foreach ($list as $index => $item) {
            $datalist[] = [
                'code' => $item['code'],
                'name' => $item['name'],
            ];
        }

        return ['list'=>$datalist,'total'=>count($datalist)];
    }

    //云端支付宝二维码页面
    public function ydqrcode(){
        return $this->view->fetch();
    }

    public function cloud()
    {//$type = '', $loginid = '', $qr_id = '', $qrlist_id = ''
        $type = $this->request->request('type');
        $loginid = $this->request->request('loginid');
        $useracc_id = $this->request->request('acc_id');//通道id
        $qr_id = $this->request->request('qr_id');
        $qrlist_id = $this->request->request('qrlist_id');

        $request = Request::instance();
        if ($type == 'getqrcode') {
            $option = array('ssl' => array('verify_peer' => false, 'verify_peer_name' => false));
            $res    = file_get_contents($request->domain() . '/alipayqr.php?act=' . $type, false, stream_context_create($option));
            $res    = json_decode($res, true);
            return $res;
        } else if ($type == 'getcookie') {
            $option = array('ssl' => array('verify_peer' => false, 'verify_peer_name' => false));
            $res    = file_get_contents($request->domain() . '/alipayqr.php?act=' . $type . '&loginid=' . $loginid, false, stream_context_create($option));
            $res    = json_decode($res, true);

            if($res['code'] == 1){

                //从cookie中获取uid
                $cookie  = base64_decode($res['cookie']);
                $zfb_pid = Utils::getSubstr($cookie, '"uid=', '";');

                //保存cookie
                Db::name('group_qrcode')->where('id',$useracc_id)->update(['zfb_pid'=>$zfb_pid,'cookie'=>$res['cookie'],'yd_is_diaoxian'=>1,'remark'=>'']);
            }
            return $res;
        } else if ($type == 'getqrpic') {
            $option = array('ssl' => array('verify_peer' => false, 'verify_peer_name' => false));
            $res    = file_get_contents($request->domain() . '/qqpayqr.php?do=' . $type, false, stream_context_create($option));
            $res    = json_decode($res, true);
            return json(['code' => 1, 'msg' => '获取成功!', 'id' => $res['qrsig'], 'qr_url' => 'data:image/png;base64,' . $res['data']]);
        } else if ($type == 'getWeChatQr') {
            $re  = Db::name('qrlist')->where('id', $qrlist_id)->find();
            $res = Jialan::wxGetLoginQrcode($re['cookie']);
            if ($res->code == 0) {
                return json(['code' => 0, 'msg' => '服务返回错误!']);
            }
            Db::name('qrlist')->where('id', $qrlist_id)->update(['cookie' => $res->guid]);
            return json(['code' => 1, 'msg' => '获取成功!', 'data' => $res, 'qr_url' => 'data:image/png;base64,' . $res->data->qrcode]);
        } else if ($type == 'WXCheckLoginQrcode') {
            $res = Jialan::wxCheckLoginQrcode($qr_id, $loginid);

            return json($res);
        } else {
            $option = array('ssl' => array('verify_peer' => false, 'verify_peer_name' => false));
            $res    = file_get_contents($request->root(true) . '/qqpayqr.php?do=' . $type . '&qrsig=' . $qr_id, false, stream_context_create($option));
            $res    = json_decode($res, true);
            return $res;
        }
    }

    //支付宝主体二维码页面
    public function alisqqrcode(){
        return $this->view->fetch();
    }

    public function alisq(){

        $type       = $this->request->request('type');
        $loginid    = $this->request->request('loginid');
        $useracc_id = $this->request->request('acc_id');//收款码列表的id
        $qr_id      = $this->request->request('qr_id');


        //找出对应的appid
        $findQrcode = Db::name('group_qrcode')->where('id',$useracc_id)->find();
        $zhuti = Db::name('alipay_zhuti')->where('id',$findQrcode['zhuti_id'])->find();
        $app_id = $zhuti['appid'];

        $request = Request::instance();

        $redict_url = Utils::alipayPath('/api/index/appPayCallBack', true);
        
        if ($type == 'getqrcode') {

            $loginid = mt_rand(1000000, 9999999);

            $qrcode = urlencode('https://openauth.alipay.com/oauth2/appToAppAuth.htm?app_id='.$app_id.'&state=10002&application_type=WEBAPP,MOBILEAPP,TINYAPP,PUBLICAPP,BASEAPP&redirect_uri='.$redict_url.'?adsd='.$useracc_id);

            //$qrcode = 'https://openauth.alipay.com/oauth2/publicAppAuthorize.htm?app_id='.$app_id.'&scope=auth_base&redirect_uri='.$redict_url.'?adsd='.$loginid;
            
            //if(in_array($findQrcode['acc_code'], ['1055','1056','1057','1058'])){
                
                $redict_url = Utils::alipayPath('/api/index/appPayCallBackV2', true);
                
                $qrcode = 'alipays://platformapi/startapp?appId=2021003130652097&page='.urlencode('pages/authorize/index?bizData={"platformCode":"O","taskType":"INTERFACE_AUTH","agentOpParam":{"redirectUri":"'.$redict_url.'","appTypes":["TINYAPP","WEBAPP","MOBILEAPP","PUBLICAPP","BASEAPP"],"isvAppId":"'.$app_id.'","state":"'.base64_encode($useracc_id).'"}}');
                
                //$qrcode = 'alipays://platformapi/startapp?appId=2021003130652097&page=pages%2Fauthorize%2Findex%3FbizData%3DUrlEncode({"platformCode":"O","taskType":"INTERFACE_AUTH","agentOpParam":{"redirectUri":"{'.$redict_url.'}","appTypes":{["TINYAPP","WEBAPP","MOBILEAPP","PUBLICAPP","BASEAPP"],},"isvAppId":"{'.$app_id.'}","state":"{'.base64_encode($useracc_id).'}"}})';
                
            //}
            
            $return = [
                "code"      => 1,
                "msg"       => "获取成功",
                "loginid"   => $loginid,
                "qrcodeurl" => $qrcode,
            ];

            return json($return);
        }

        if ($type == 'checksq') {

            $loginData = Cache::get($useracc_id);
            
            if(isset($loginData['user_id'])){
                $return = [
                    "code"      => 1,
                    "msg"       => "授权成功",
                ];
                Cache::delete($useracc_id);
                
            }else{
                $return = [
                    "code"      => 0,
                    "msg"       => "暂未授权",
                ];
            }

            
            return json($return);
        }


    }

    public function exportdemo(){
        
        $header = [
            ['通道编码', 'acc_code', 'text'], // 规则不填默认text
            ['主体ID', 'zhuti_id', 'text'], // 规则不填默认text
            ['别名1', 'name1', 'text'], // 规则不填默认text
            ['别名2', 'name2', 'text'], // 规则不填默认text
            ['别名3', 'name3', 'text'], // 规则不填默认text

        ];

        $list = [
        ];
        // 简单使用
        return Excel::exportData($list, $header , '导入模板', 'xlsx',);
    }

    //导入
    public function import(){

        $file   = $this->request->request('file');
        $file   = substr($file, 1);
        $import = Excel::import($file, 2);

        unset($import[0]);

        $user_id  = $this->auth->id;
        $agent_id = $this->auth->agent_id;
        
        foreach ($import as $key1 => $value1){

            if (empty($value1) || empty($value1[1])) {
                unset($import[$key1]);
                continue;
            }
            
            $qrcode_name = trim($value1[2]) . '-' . trim($value1[3]) . '-' . trim($value1[4]);
            
            //格式 通道编码-别名
            $temp['user_id']     = $user_id;
            $temp['agent_id']    = $agent_id;
            $temp['android_key'] = Random::numeric(9);
            $temp['acc_code']    = trim($value1[0]);
            $temp['zhuti_id']    = trim($value1[1]);
            $temp['name']        = $qrcode_name;
            $temp['create_time'] = time();
            $temp['status']      = 0;

            $insertdata[] = $temp;
        }
        
        $all_num    = count($insertdata);
        $success_num = 0;
        $fail_num    = 0;

        foreach ($insertdata as $k =>$v){

            $re = Db::name('group_qrcode')->insert($v);

            if ($re) {
                $success_num++;

            }else{
                $fail_num++;
            }

        }


        $this->success('导入成功，总数：'.$all_num.'，成功数：'.$success_num.'，失败数：'.$fail_num);

    }
    
    //导入 
    public function importV2(){

        $file   = $this->request->request('file');
        $file   = substr($file, 1);
        $import = Excel::import($file, 2);

        unset($import[0]);

        $user_id  = $this->auth->id;
        $agent_id = $this->auth->agent_id;
        $acc_code = 1028;

        foreach ($import as $key1 => $value1){

            if (empty($value1) || empty($value1[1])) {
                unset($import[$key1]);
                continue;
            }

            if(count($value1) != 7){
                $this->error('导入格式错误，请核实');
                break;
            }


            //格式 收款码别名	uid	每日笔数上限	每日金额上限	拉单间隔
            $temp['user_id']         = $user_id;
            $temp['agent_id']        = $agent_id;
            $temp['acc_code']        = $acc_code;
            $temp['android_key']     = Random::alnum(6);
            $temp['name']            = trim($value1[0]);
            $temp['zfb_pid']         = trim($value1[1]);
            $temp['max_order_num']   = trim($value1[2]);
            $temp['max_money']       = trim($value1[3]);
            $temp['qrcode_interval'] = trim($value1[4]);
            $temp['create_time']     = time();
            $temp['xl_cookie']       = $value1[5];
            $temp['cookie']          = $value1[6];

            $insertdata[] = $temp;
        }

        if($acc_code == '1007'){

            //判断是否有重复
            $uid_arr = array_column($insertdata, 'zfb_pid');

            $res = $this->model->where('zfb_pid', 'in', $uid_arr)->field('zfb_pid')->select()->toArray();

            if ($res) {
                $uids = implode(',', array_column($res, 'zfb_pid'));
                $this->error('重复uid，请检查后再导入：'. $uids);
            }
        }



        $all_num    = count($insertdata);
        $success_num = 0;
        $fail_num    = 0;

        foreach ($insertdata as $k =>$v){

            $re = Db::name('group_qrcode')->insert($v);

            if ($re) {
                $success_num++;

            }else{
                $fail_num++;
            }

        }


        $this->success('导入成功，总数：'.$all_num.'，成功数：'.$success_num.'，失败数：'.$fail_num);

    }


    //批量更新码子配置
    public function batchedit($qr_ids = null){
        
        $findUser = Db::name('user')->where('id',$this->auth->id)->find();
        
        if($this->request->isAjax()) {
            
            $qr_ids_arr = explode(',', $qr_ids);
            
            $params = $this->request->post('row/a');

            $updateData = [];
            $userData   = [];
            
            $zhuti_id = trim($params['zhuti_id']);//主体

            $success_order_num = trim($params['success_order_num']);//成功笔数上限
            $fail_order_num    = trim($params['fail_order_num']);//失败笔数上限
            //$max_order_num     = trim($params['max_order_num']);//总笔数上限
            $max_money         = trim($params['max_money']);//每日成功金额上限
            $qrcode_interval   = trim($params['qrcode_interval']);//拉单间隔
            $order_max_amount  = trim($params['order_max_amount']);//单笔最大进单金额
            $order_min_amount  = trim($params['order_min_amount']);//单笔最小进单金额
            $is_third_hx       = $params['is_third_hx'];

            $acc_code          = $params['acc_code'];

            
            if(!empty($zhuti_id)){
                $updateData['zhuti_id'] = $zhuti_id;
            }

            if(!empty($success_order_num)){
                $updateData['success_order_num'] = $success_order_num;
            }

            if(!empty($fail_order_num)){
                $updateData['fail_order_num'] = $fail_order_num;
            }

            /*if(!empty($max_order_num)){
                $updateData['max_order_num'] = $max_order_num;
            }*/

            if(!empty($max_money)){
                $updateData['max_money'] = $max_money;
            }

            if(!empty($qrcode_interval)){
                $updateData['qrcode_interval'] = $qrcode_interval;
            }

            if(!empty($order_max_amount)){
                $updateData['order_max_amount'] = $order_max_amount;
            }

            if(!empty($order_min_amount)){
                $updateData['order_min_amount'] = $order_min_amount;
            }

            if(!empty($acc_code)){
                $updateData['acc_code'] = $acc_code;
            }
            
            $userData['is_third_hx'] = $is_third_hx;
            
            if(empty($updateData) && empty($userData)){
                $this->error('无更新内容');
            }
            
            $updateData['update_time'] = time();
            
            $result = $this->model->where('user_id',$this->auth->id)->whereIn('id',$qr_ids_arr)->update($updateData);
            
            
            if($userData){
                $result = Db::name('user')->where('id',$this->auth->id)->update($userData);
            }
            
            if ($result !== false) {
                $this->success();
            }

            $this->error('更新失败，请重试');

        }
        
        $datalist = $this->getMyAlipayConfig();
        $this->assign('zhuti_list', $datalist);
        
        $this->view->assign('row', $findUser);
        return $this->view->fetch();
    }

    //挂码账号管理
    public function myqrcode($ids = null)
    {
        if(empty($ids)){
            $this->error('参数缺少');
        }

        $userAcc = Useracc::find($ids);
        if (!$userAcc){
            $this->error('参数缺少');
        }
        $systemAcc = Acc::where('code',$userAcc['acc_code'])->find();
        $findUser = Db::name('user')->where('id',$this->auth->id)->find();

        //当前是否为关联查询
        $this->relationSearch = false;
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax())
        {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField'))
            {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $total = $this->model
                ->where(['user_id' => $this->auth->id, 'acc_code' => $userAcc['acc_code']])
                ->where($where)
                ->order($sort, $order)
                ->count();

            $list = $this->model
                ->where(['user_id' => $this->auth->id, 'acc_code' => $userAcc['acc_code']])
                ->where($where)
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();

            foreach ($list as $row) {

                $row['acc_type'] = Db::name('acc')->where('code',$row['acc_code'])->value('name');

                //今日成功金额收款
                $today_suc_money = Order::where(['qrcode_id'=>$row->id,'status'=>Order::STATUS_COMPLETE])->whereDay('createtime')->sum('amount');
                
                //该通道今日成功订单
                $todaysuccessorder = Order::where(['status'=>Order::STATUS_COMPLETE,'qrcode_id'=>$row->id])->whereDay('createtime')->count();
                
                //今日失败订单 账单模式特殊统计
                if ($row['acc_code'] == '1061') {
                    $today_fail_order = Order::where(['status' => Order::STATUS_FAIL, 'qrcode_id' => $row->id])
                        //->where('zfb_nickname', '<>', '')
                        ->where('is_gmm_close', 1)
                        ->whereDay('createtime')
                        ->count();
                } else {
                    $today_fail_order = Order::where(['status'=>Order::STATUS_FAIL,'qrcode_id'=>$row->id])->whereDay('createtime')->count();
                }
                
                //待支付订单
                $today_wait_order = Order::where(['status'=>Order::STATUS_DEALING,'qrcode_id'=>$row->id])->whereDay('createtime')->count();
                
                //昨日成功金额
                $yesterday_suc_money = Order::where(['qrcode_id'=>$row->id,'status'=>Order::STATUS_COMPLETE])->whereDay('createtime','yesterday')->sum('amount');
                
                /*$row['statistics'] = '今日笔数:<span style="color:#18BC9C;font-weight:bold;">' . $todaysuccessorder . '</span> / ' . '<span style="color:#18BC9C;font-weight:bold;">' . $todayallorder . '</span>' . '&nbsp&nbsp今日金额:<span style="color:red;font-weight:bold;">' . $today_suc_money . '</span>/ <span style="color:red;font-weight:bold;">' . $today_money . '</span></br>' . '今日成功率:<span style="color:#007BFF;font-weight:bold;">' . $today_success_rate . '</span></br>' . '昨日笔数:</span><span style="color:#18BC9C;font-weight:bold;">' . $yesterday_suc_order . '</span> /<span style="color:#18BC9C;font-weight:bold;">' . $yesterday_all_order . '</span>&nbsp&nbsp昨日金额:<span style="color:red;font-weight:bold;">' . $yesterday_suc_money . '</span> / <span style="color:red;font-weight:bold;">' . $yesterday_all_money . '</span>';*/
                
                $row['success_conf'] = '成功限制:'.$row['success_order_num'].'笔</br>' . '已成功:' . $todaysuccessorder .'笔';
                $row['fail_conf'] = '失败限制:'.$row['fail_order_num'] .'笔</br>' . '已失败:' . $today_fail_order .'笔</br>' . '待支付:' . $today_wait_order .'笔';;
                $row['money_conf'] = '每日限额:'.$row['max_money'] .'</br>' . '今日收款:' . $today_suc_money .'</br>' . '昨日收款:' . $yesterday_suc_money ;
                
                
                
                //查询支付宝余额
                if(!in_array($row['acc_code'], Config::get('mchconf.zhuti_acc_code'))){
                    continue;
                }
                if(empty($row['zfb_pid'] || empty($row['app_auth_token']))){
                    continue;
                }
                
                
                if(in_array($row['acc_code'], Config::get('mchconf.zhuti_acc_code'))){
                    
                    //查询支付宝余额
                    $zhuti = Db::name('alipay_zhuti')->where('id', $row['zhuti_id'])->cache(true, 180)->find();
                    
                    if($findUser['is_third_hx'] == 1 && !empty($zhuti['appid'])){
                        $balance_res = CheckOrderUtils::alipayQueryBalance($row);
                        $row['money'] = $balance_res['data'];
                    }
                    
                    $zhuti_name = isset($zhuti['name']) ? $zhuti['name'] : '无主体';
                    $row['zfb_pid'] = $zhuti_name . '</br>' . $row['zfb_pid'];
                }
            }
            
            $on_count = Db::name('group_qrcode')->where(['user_id'=>$this->auth->id,'status'=>GroupQrcode::STATUS_ON])->count();
            $off_count = Db::name('group_qrcode')->where(['user_id'=>$this->auth->id,'status'=>GroupQrcode::STATUS_OFF])->count();

            $result = array("total" => $total, "rows" => $list,'extend'=>[
                'on_count'  => $on_count,
                'off_count' => $off_count,
            ]);


            return json($result);
        }
        
        $user_test_order = Config::get('site.user_test_order');
        
        $this->assignconfig('user_test_order', $user_test_order);
        $this->assignconfig('acc_id', $ids);
        $this->assign('acc_name', $systemAcc['name']);
        $this->assign('acc_code', $systemAcc['code']);
        return $this->view->fetch();
    }

    /**
     * 添加
     */
    public function myqrcodeadd($ids = null)
    {
        $userAcc = Useracc::find($ids);
        if (!$userAcc){
            $this->error('参数缺少');
        }

        if ($this->request->isPost()) {
            $params = $this->request->post('row/a');
            if ($params) {
                $params = $this->preExcludeFields($params);

                if ($this->dataLimit && $this->dataLimitFieldAutoFill) {
                    $params[$this->dataLimitField] = $this->auth->id;
                }
                $result = false;
                try {
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name     = str_replace('\\model\\', '\\validate\\', get_class($this->model));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name.'.add' : $name) : $this->modelValidate;
                        validate($validate)->scene($this->modelSceneValidate ? 'edit' : $name)->check($params);
                    }

                    $userRate = Db::name('user_acc')->where(['user_id'=>$this->auth->id,'acc_code'=>$params['acc_code']])->find();
                    if(empty($userRate)){
                        $this->error('请先配置通道费率');
                    }
                    $params['user_id']     = $this->auth->id;
                    $params['agent_id']    = $this->auth->agent_id;
                    $params['create_time'] = time();
                    $android_key           = Random::numeric(9);
                    $params['android_key'] = $android_key;
                    
                    if($params['acc_code'] == '1011' && isset($params['images'])){
                        return $this->wxgmbatchupload($params);
                    }
                    
                    if(isset($params['zfb_pid'])){
                        $zfb_pid = str_replace(' ', '', trim($params['zfb_pid']));
                        $params['zfb_pid'] = $zfb_pid;
                    }
                    
                    $findqrcode = Db::name('group_qrcode')->where('android_key',$android_key)->find();
                    if($findqrcode){
                        $this->error('设备值已存在，请重试');
                    }
                    
                    //二维码解析
                    if ($params['acc_code'] == '1025' && !empty($params['image'])) {
                        //$image = app()->getRootPath() . substr($params['image'],1);
                        $image = app()->getRootPath() . 'public' . $params['image'];
                        $qrcode = new QrReader($image);//绝对路径
                        $imgText = $qrcode->text(); //返回二维码的内容
                        $params['pay_url'] = $imgText;
                    }

                    /*//检测uid/迅雷id是否存在
                    $findqrcode = Db::name('group_qrcode')->where(['zfb_pid'=>$params['zfb_pid'],'status'=>1])->find();
                    if($findqrcode){
                        $this->error('uid已存在，请核实');
                    }*/

                    //迅雷的码 先获取uid
                    if($params['acc_code'] == '1014'){

                        $getParams = [];
                        $options = [];

                        $accutils = new Accutils();
                        $time = $accutils->getMsectime();
                        $sign = md5('1002_t='.$time.'&a=getroom&c=room&uuid='.$params['zfb_pid'].'&*%$7987321GKwq');
                        $url  = 'https://pc-live-ssl.xunlei.com/caller?c=room&a=getroom&uuid='.$params['zfb_pid'].'&_t='.$time.'&sign='.$sign;
                        $result  = json_decode(Http::get($url, $getParams, $options),true);

                        if(empty($result['data']['userInfo'])){
                            $this->error('迅雷用户不存在');
                        }

                        $params['xl_user_id'] = $result['data']['userInfo']['userid'];

                    }

                    //愿聊-迅雷之锤的码 先获取uid
                    if($params['acc_code'] == '1029'){

                        $url = 'https://svr-mozhi.xunleizhichui.com/melon.bizmember.s/v1/query';

                        $accutils = new Accutils();
                        $ts       = $accutils->getMsectime();

                        $postData = [
                            'phoneno'   => $params['zfb_pid'],
                            'base' => [
                                'app' => 'com.cn.xcub.make.h5',
                                'av'  => '1.1.0',
                                'dt'  => 3,
                                'did' => '19a3d6e1435da4aa0d829a719c5e06b9',
                                'ch'  => 'web_pay',
                                'ts'  => $ts,
                            ],
                        ];

                        $res    = Http::post($url, json_encode($postData), []);
                        $result = json_decode($res,true);

                        if(!isset($result['data'])){
                            $this->error('账号不存在'.$result['msg']);
                        }

                        $params['xl_user_id'] = $result['data']['userid'];

                    }

                    //愿聊-迅雷之锤的码 先获取uid
                    if($params['acc_code'] == '1030'){

                        $url = 'https://h5-api.neoclub.cn/v1/bff/web/user/login?account='.$params['zfb_pid'];

                        $res    = Http::get($url, [], []);
                        $result = json_decode($res,true);

                        if($result['code'] != 0){
                            $this->error('账号不存在'.$result['message']);
                        }

                        $params['xl_user_id'] = $result['data']['uid'];
                        $params['xl_cookie']  = $result['data']['token'];

                    }

                    //我秀 先获取user_id
                    if($params['acc_code'] == '1033'){

                        $url = 'https://www.woxiu.com/api/woxiu.php';
                        $t = '0.' . Random::numeric(16);
                        $postData = 'roomid='.$params['zfb_pid'].'&type=userinfo&_='.$t;
                        $res    = Http::post($url, $postData, []);

                        $result = json_decode($res,true);

                        if($result['status'] != 1){
                            $this->error('房间号不存在'.$result['msg']);
                        }

                        $params['xl_user_id'] = $result['user_id'];

                    }
                    
                    /*//微信个码解析二维码
                    if($params['acc_code'] == '1011' && !empty($params['image'])){
                        
                        //$image = app()->getRootPath() . substr($params['image'],1); 
                        $image = app()->getRootPath() . 'public' . $params['image'];
                        $qrcode = new QrReader($image);//绝对路径
                        $imgText = $qrcode->text(); //返回二维码的内容
                        if(empty($imgText)){
                            $this->error('二维码解析失败，请重试 或删除上传图片，手动解析');
                        }
                        $params['pay_url'] = $imgText;
                        
                    }*/
                    
                    //1057默认最小金额50
                    if($params['acc_code'] == '1057'){
                        $params['order_min_amount'] = 50;//单笔最小进单金额
                    }
                    
                    $result = $this->model->save($params);
                } catch (ValidateException $e) {
                    $this->error($e->getMessage());
                } catch (\PDOException $e) {
                    $this->error($e->getMessage());
                } catch (Exception $e) {
                    $this->error($e->getMessage());
                }
                if ($result !== false) {
                    $this->success();
                } else {
                    $this->error(__('No rows were inserted'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }

        switch ($userAcc['acc_code']) {
            case '1001':
                //微信群红包
                $view = 'wxgroup';
                break;
            case '1003':
                //抖音红包
                $view = 'dy';
                break;
            case '1008':
                //支付宝uid云端
                //$view = 'zfbuidyd';
                $view = 'zfbzhuti';
                break;
            case '1007':
                //支付宝uid
                $view = 'zfbuid';
                break;
            case '1009':
                //支付宝小荷包
                $view = 'common';
                break;
            case '1010':
                //支付宝房租
                $view = 'common';
                break;
            case '1011':
                //微信个码
                $view = 'wxgm';
                $view = 'wxgmbatch';
                break;
            case '1012':
                //汇潮支付宝
                $view = 'common';
                break;
            case '1013':
                //瀚银支付宝
                $view = 'common';
                break;
            case '1014':
                //迅雷直播支付宝 用迅雷的userid，不是用挂码的那个id
                $view = 'xlzb';
                break;
            case '1015':
                //迅雷直播微信
                $view = 'xlzb';
                break;
            case '1017':
                //支付宝转卡
                $view = 'zfbyhk';
                break;
            case '1018':
                //支付宝陌生人转账
                $view = 'zfbuid';
                break;
            case '1019':
                //快手支付宝
                $view = 'common';
                break;
            case '1020':
                //皮皮直播支付宝
                $view = 'common';
                break;
            case '1021':
                $view = 'common';
                break;
            case '1022':
                //YY支付宝
                $view = 'common';
                break;
            case '1023':
                //酷秀微信内付
                $view = 'common';
                break;
            case '1024':
                //快手微信h5
                $view = 'common';
                break;
            case '1025':
                //中额扫码
                $view = 'zfbgm';
                break;
            case '1026':
                //百战支付宝
                $view = 'common';
                break;
            case '1027':
                //百战微信
                $view = 'common';
                break;
            case '1028':
                //gmm支付宝
                $view = 'gmmadd';
                break;
            case '1029':
                //迅雷直播支付宝
                $view = 'xlzbadd';
                break;
            case '1030':
                //uki支付宝
                $view = 'common';
                break;
            case '1031':
                //淘宝直付
                $view = 'tbhx';
                break;
            case '1032':
                //淘宝核销
                $view = 'tbhx';
                break;
            case '1033':
                //我秀
                $view = 'common';
                break;
            case '1034':
                //骏网智充卡
                $view = 'common';
                break;
            case '1035':
                //pdd代付
                $view = 'common';
                break;
            case '1036':
                //数字人名币
                $view = 'szqbadd';
                break;
            case '1037':
                //骏网益享卡
                $view = 'common';
                break;
            case '1038':
                //沃尔玛
                $view = 'common';
                break;
            case '1039':
                //沃尔玛
                $view = 'common';
                break;
            case '1040':
                //京东e卡
                $view = 'common';
                break;
            case '1041':
                //支付宝uid 中额
                $view = 'zfbuidyd';
                break;
            case '1042':
                //支付宝uid 中额
                $view = 'zfbgm';
                break;
            case '1048':
                //支付宝AA收款
                $view = 'zfbgm';
                break;
            case '1066':
                //汇盈支付宝
                $view = 'common';
                break;
            case '1068':
                //微信原生
                $view = 'common';
                break;
            case '1045':
                //卡卡
                $view = 'kaka';
                break;
            case '1046':
                //咸鱼
                $view = 'xianyu';
                break;
            case '1047':
                //亲情卡
                $view = 'qinqingka';
                break;
            case '1050':
                //支付宝app主体模式
                $view = 'zfbzhuti';
                break;
            case '1051':
                //支付宝app主体模式
                $view = 'zfbzhuti';
                break;
            case '1052':
                //支付宝app主体模式
                $view = 'zfbzhuti';
                break;
            case '1053':
                //支付宝app主体模式
                $view = 'zfbzhuti';
                break;
            case '1054':
                //支付宝app主体模式
                $view = 'zfbzhuti';
                break;
            case '1055':
                //支付宝个码主体模式
                $view = 'zfbzhutigm';
                break;
            case '1056':
                //支付宝个码主体模式
                $view = 'zfbzhuti';
                break;
            case '1057':
                //支付宝个码主体模式
                $view = 'zfbzhutigm';
                break;
            case '1058':
                //支付宝 批量转账 主体模式
                $view = 'zfbzhutpl';
                break;
            case '1059':
                //支付宝极速报销
                $view = 'zfbzhutigm';
                break;
            case '1060':
                //汇付支付宝
                $view = 'common';
                break;
            case '1061':
                //支付宝账单收款
                $view = 'zfbzhutigm';
                break;
            case '1062':
                //支付宝个码指定金额
                $view = 'zfbgd';
                break;
            case '1063':
                //支付宝 收款名片
                $view = 'zfbzhutigm';
                break;
            case '1064':
                //支付宝 账单群收款
                $view = 'zfbzhutigm';
                break;
            case '1065':
                //支付宝 订单码
                $view = 'zfbzhutigm';
                break;
            case '1066':
                //支付宝 个码大额
                $view = 'zfbzhutigm';
                break;
            case '1067':
                //支付宝 经营码
                $view = 'zfbzhutigm';
                break;
            case '1080':
                //qq扫码
                $view = 'qqscan';
                break;
            case '1081':
                //云闪付扫码
                $view = 'ysfscan';
                break;
            case '1082':
                //手机网站原生
                $view = 'zfbsjwz';
                break;
            case '1083':
                //当面付原生
                $view = 'zfbsjwz';
                break;
            default:
                $this->error('系统通道错误，请检查');
                break;
        }


        $datalist = $this->getMyAlipayConfig();

        $this->assign('acc_code', $userAcc['acc_code']);
        $this->assign('zhuti_list', $datalist);


        return $this->view->fetch($view);
    }
    
    
    public function wxgmbatchupload($params){
        $num = 0;
        $image_arr = explode(',', $params['images']);
        unset($params['images']);
        
        foreach ($image_arr as $k => $v){
            
            // 使用正则表达式匹配中文字符
            preg_match('/[\x{4e00}-\x{9fa5}]+/u', $v, $matches);
            
            // $matches[0] 包含匹配到的中文名称
            $chineseName = $matches[0];
            
            $params['name']  = $chineseName;
            $params['image'] = $v;
            
            $result = Db::name('group_qrcode')->insert($params);
            if($result){
                $num++;
            }
        }
        
        $this->success('上传成功，数量：'.count($image_arr). '成功数量：'.$num);
        
    }
    
    public function getMyAlipayConfig(){

        $zhuti_list = Db::name('alipay_zhuti_user')->alias('a')->join('alipay_zhuti b', 'a.zhuti_id = b.id')->where(['a.user_id'=>$this->auth->id,'b.status'=>1])->field('b.*,a.user_id')->select();

        $datalist = [];
        foreach ($zhuti_list as $index => $item) {
            $datalist[$item['id']] = $item['name'];
        }

        return $datalist;
    }
    
    public function queryalibalance(){
        
        $id = $this->request->param('id');
        
        $findQrcode = Db::name('group_qrcode')->where(['id' => $id])->find();

        $zhuti = Db::name('alipay_zhuti')->where('id', $findQrcode['zhuti_id'])->find();
        
        $alipaySDK = new AlipaySdk();
        
        $balance_res = CheckOrderUtils::alipayQueryBalance($findQrcode);
        $balance     = $balance_res['data'];
        
        $this->success('成功', null, ['balance' => $balance]);
    }

    //一键开启关闭码子
    public function batchchangeqrcode(){

        $status = $this->request->param('status');

        if($status == 0){
            $result = Db::name('group_qrcode')->where(['user_id' => $this->auth->id, 'status' => 1])->update(['status' => 0]);
        }else{
            $result = Db::name('group_qrcode')->where(['user_id' => $this->auth->id, 'status' => 0])->update(['status' => 1]);
        }

        if($result){
            $this->success('操作成功');
        }

        $this->error('修改失败');
    }
    
    //退款
    public function orderRefund() {
        $ids      = $this->request->request('ids');
        $amount   = $this->request->request('amount');
        $order_no = $this->request->request('order_no');
        $user_id  = $this->auth->id;
        
        if(empty($amount) || empty($order_no)){
            $this->error('参数缺少');
        }
        
        $qrcode = Db::name('group_qrcode')->where(['id' => $ids])->find();
        
        if(empty($qrcode['app_auth_token'])){
            $this->error('未授权');
        }
        
        $check_res = CheckOrderUtils::alipayOrderRefund($order_no, $amount,$qrcode['app_auth_token'], $qrcode['zhuti_id']);
        
        
        if($check_res['is_exist'] == false){
            $this->error('退款失败：' . $check_res['data']);
        }
        
        $this->success($check_res['data']);
        
    }
}
