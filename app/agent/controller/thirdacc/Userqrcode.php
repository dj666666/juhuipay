<?php

namespace app\agent\controller\thirdacc;

use app\admin\model\GroupQrcode;
use app\admin\model\order\Order;
use app\common\controller\AgentBackend;
use think\facade\Db;
use think\facade\Request;
use app\common\library\Utils;
use think\facade\Config;
use fast\Random;
use fast\Http;
use app\common\library\Accutils;
use app\common\library\AlipaySdk;

/**
 * 收款码管理
 *
 * @icon fa fa-circle-o
 */
class Userqrcode extends AgentBackend
{
    
    /**
     * Userqrcode模型对象
     * @var \app\admin\model\thirdacc\Userqrcode
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\agent\model\thirdacc\Userqrcode;
        $this->view->assign("typeList", $this->model->getTypeList());
        $this->view->assign("statusList", $this->model->getStatusList());
        $this->view->assign("isUseList", $this->model->getIsUseList());
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
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $total = $this->model
                    ->withJoin(['user'=>['username','money','nickname']])
                    ->where(['userqrcode.agent_id'=>$this->auth->id])
                    ->where($where)
                    ->order($sort, $order)
                    ->count();

            $list = $this->model
                    ->withJoin(['user'=>['username','money','nickname']])
                    ->where(['userqrcode.agent_id'=>$this->auth->id])
                    ->where($where)
                    ->order($sort, $order)
                    ->limit($offset, $limit)
                    ->select();

            foreach ($list as $row) {
                
                if($row['user']){
                    $row->getRelation('user')->visible(['username','money','nickname']);
                }
                
                $row['acc_type'] = Db::name('acc')->where('code',$row['acc_code'])->cache(true,300)->value('name');

                //今日成功金额收款
                $today_suc_money = Order::where(['qrcode_id'=>$row->id,'status'=>1])->whereDay('createtime')->sum('amount');
                
                //今日金额
                $today_money = Order::where(['qrcode_id'=>$row->id])->whereDay('createtime')->sum('amount');
                

                //该通道总成功订单
                $successorder = Order::where(['status'=>1,'qrcode_id'=>$row->id])->count();
                
                //该通道总订单
                $allorder = Order::where(['qrcode_id'=>$row->id])->count();

                //该通道今日成功订单
                $todaysuccessorder = Order::where(['status'=>1,'qrcode_id'=>$row->id])->whereDay('createtime')->count();
                
                //今日失败订单
                $today_fail_order = Order::where(['status'=>3,'qrcode_id'=>$row->id])->whereDay('createtime')->count();

                //待支付订单
                $today_wait_order = Order::where(['status'=>2,'qrcode_id'=>$row->id])->whereDay('createtime')->count();
                
                
                //该通道今日订单
                $todayallorder = Order::where(['qrcode_id'=>$row->id])->whereDay('createtime')->count();


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
                
                /*$row['statistics'] = '今日笔数:<span style="color:#18BC9C;font-weight:bold;">' . $todaysuccessorder . '</span> / ' . '<span style="color:#18BC9C;font-weight:bold;">' . $todayallorder . '</span>' . '今日金额:<span style="color:red;font-weight:bold;">' . $today_suc_money . '</span> / <span style="color:red;font-weight:bold;">' . $today_money . '</span></br>' . '今日成功率:<span style="color:#007BFF;font-weight:bold;">' . $today_success_rate . '</span></br>' . '总笔数:</span><span style="color:#18BC9C;font-weight:bold;">' . $successorder . '</span> /<span style="color:#18BC9C;font-weight:bold;">' . $allorder . '</span>&nbsp&nbsp总金额:<span style="color:red;font-weight:bold;">' . $all_suc_money . '</span> / <span style="color:red;font-weight:bold;">' . $all_money . '</span></br>' .
                    '总成功率:</span>/<span style="color:#007BFF;font-weight:bold;">' . $success_rate . '</span>';*/
                /*$row['statistics'] = '今日金额:<span style="color:#18BC9C;font-weight:bold;">' . $today_suc_money . '</span> / 剩余金额<span style="color:red;font-weight:bold;">' . $today_money . '</span></br>' .
                    '今日成率:<span style="color:#007BFF;font-weight:bold;">' . $today_success_rate . '</span></br>' . '总金额:<span style="color:red;font-weight:bold;">' . $all_suc_money . '</span> / <span style="color:red;font-weight:bold;">' . $all_money . '</span></br>';*/

                /*$row['statistics'] = '今日笔数:<span style="color:#18BC9C;font-weight:bold;">' . $todaysuccessorder . '</span> / ' . '<span style="color:#18BC9C;font-weight:bold;">' . $todayallorder . '</span>' . '&nbsp&nbsp今日金额:<span style="color:red;font-weight:bold;">' . $today_suc_money . '</span>/ <span style="color:red;font-weight:bold;">' . $today_money . '</span></br>' . '今日成功率:<span style="color:#007BFF;font-weight:bold;">' . $today_success_rate . '</span></br>' . '昨日笔数:</span><span style="color:#18BC9C;font-weight:bold;">' . $yesterday_suc_order . '</span> /<span style="color:#18BC9C;font-weight:bold;">' . $yesterday_all_order . '</span>&nbsp&nbsp昨日金额:<span style="color:red;font-weight:bold;">' . $yesterday_suc_money . '</span> / <span style="color:red;font-weight:bold;">' . $yesterday_all_money . '</span>';*/
                
                $row['success_conf'] = '成功限制:'. '<span style="color:#1688f1;font-weight:bold;">' . $row['success_order_num'] . '</span>' .'笔</br>' . '已成功:' . '<span style="color:red;font-weight:bold;">' . $todaysuccessorder . '</span>' .'笔';
                $row['fail_conf'] = '失败限制:'.'<span style="color:#1688f1;font-weight:bold;">' . $row['fail_order_num'] . '</span>' .'笔</br>' . '已失败:' . '<span style="color:red;font-weight:bold;">' . $today_fail_order . '</span>' .'笔</br>' . '待支付:' . '<span style="color:red;font-weight:bold;">' . $today_wait_order . '</span>' .'笔';;
                $row['money_conf'] = '每日限额:'.'<span style="color:#1688f1;font-weight:bold;">' . $row['max_money'] . '</span>' .'</br>' . '今日收款:' . '<span style="color:red;font-weight:bold;">' . $today_suc_money . '</span>' .'</br>' . '昨日收款:' . '<span style="color:red;font-weight:bold;">' . $yesterday_suc_money . '</span>' ;
                
                
                $row['today_money'] = $today_suc_money;
                $row['today_order'] = $todaysuccessorder;

                //查询支付宝余额
                if(!in_array($row['acc_code'], ['1050','1051','1052','1053','1054','1055','1056','1057','1058'])){
                    continue;
                }
                if(empty($row['zfb_pid'] || empty($row['app_auth_token']))){
                    continue;    
                }
                
                /*$zhuti = Db::name('alipay_zhuti')->where('id', $row['zhuti_id'])->cache(true, 180)->find();
                $alipaySDK = new AlipaySdk();
                $balance = $alipaySDK->alipayQueryBalance($row['zfb_pid'], $row['app_auth_token'], $zhuti);
                $row['money'] = $balance;*/
                
            }
            
            $on_count = Db::name('group_qrcode')->where(['agent_id'=>$this->auth->id,'status'=>GroupQrcode::STATUS_ON])->count();
            $off_count = Db::name('group_qrcode')->where(['agent_id'=>$this->auth->id,'status'=>GroupQrcode::STATUS_OFF])->count();
            
            /*$agentAccList = Db::name('agent_acc')->where('agent_id',$this->auth->id)->select()->toArray();
        
            foreach ($agentAccList as $k => &$v){
                
                $acc_name = Db::name('acc')->where('code',$v['acc_code'])->cache(true,300)->value('name');
    
                $on_count = Db::name('group_qrcode')->where(['agent_id'=>$this->auth->id,'status'=>GroupQrcode::STATUS_ON,'acc_code'=>$v['acc_code']])->count();
                $off_count = Db::name('group_qrcode')->where(['agent_id'=>$this->auth->id,'status'=>GroupQrcode::STATUS_OFF,'acc_code'=>$v['acc_code']])->count();
                
                $v['acc_name'] = $acc_name;
                $v['on_num']   = $on_count;
                $v['off_num']  = $off_count;
            }*/
        
            $result = array("total" => $total, "rows" => $list,'extend'=>[
                'on_count'  => $on_count,
                'off_count' => $off_count,
                //'acc_list' => $agentAccList,
            ]);

            return json($result);
        }
        
        
        
        //halt($agentAccList);
        //$this->view->assign("acc_list", $agentAccList);
        
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

    //切换启用状态
    public function change($ids = ''){
        if($ids){
            $row        = $this->model->get($ids);
            $status     = $row['status'] == 1 ? 0 : 1;
            $updateData = ['status'=>$status,'update_time'=>time()];
            if($row['acc_code'] == '1008' && $row['status'] == 0){
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

}
