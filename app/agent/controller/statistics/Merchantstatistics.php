<?php

namespace app\agent\controller\statistics;

use app\common\controller\AgentBackend;
use think\facade\Db;
use app\admin\model\order\Order;
use app\admin\model\merchant\Merchant;

/**
 * 商户管理
 *
 * @icon fa fa-circle-o
 */
class Merchantstatistics extends AgentBackend
{
    
    /**
     * Merchantstatistics模型对象
     * @var \app\agent\model\statistics\Merchantstatistics
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\agent\model\statistics\Merchantstatistics;
        $this->view->assign("isFcList", $this->model->getIsFcList());
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
            
            $mer_arr = Db::name('order')->where('agent_id', $this->auth->id)->field('mer_id')->group('mer_id')->select()->toArray();
            $mer_ids = array_column($mer_arr,'mer_id');
            
            $filter = json_decode($this->request->get('filter'),true);
            $op     = json_decode($this->request->get('op'),true);
            
            $map   = [];
            $map[] = ['agent_id', '=', $this->auth->id];
            $map2 = [];
            $map3 = [];
            
            if(isset($filter['createtime'])){
                $createtime = explode(' - ',$filter['createtime']);
                $timeStr = strtotime($createtime[0]).','.strtotime($createtime[1]);
                $map[]  = ['createtime','between', $timeStr];
                $map2[] = ['createtime','between', $timeStr];
                $map3[] = ['createtime','between', $timeStr];
            }
            
            if(isset($filter['pay_type'])){
                $pay_type = $filter['pay_type'];
                $map2[]   = ['pay_type', '=', $pay_type];
                $map3[]   = ['pay_type', '=', $pay_type];
            }
            
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $total = $this->model
                //->where($where)
                ->where(['agent_id'=>$this->auth->id])
                //->whereIn('id', $mer_ids)
                ->order($sort, $order)
                ->count();

            $list = $this->model
                //->where($where)
                ->where(['agent_id'=>$this->auth->id])
                //->whereIn('id', $mer_ids)
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();

            foreach ($list as $row) {

                $row->visible(['id','username','nickname','time','success_rate','all_money','all_success_money','all_fail_money','all_order','all_success_order','all_fail_order','all_fail_money']);
                
                
                //总金额 单量
                $all_info = Db::name('order')->where('mer_id',$row['id'])->where($map3)
                    ->field('sum(amount) as allmoney, count(*) as allorder')
                    ->find();
                $row['all_money'] = is_null($all_info['allmoney']) ? 0 : $all_info['allmoney'];//总额
                $row['all_order'] = $all_info['allorder'];//总订单数量
                
                //总成功金额 单量
                $all_suc_info = Db::name('order')->where(['mer_id'=>$row['id'],'status'=>1])
                    ->where($map2)
                    ->field('sum(amount) as allmoney, count(*) as allorder')
                    ->find();
                    
                $row['all_success_money'] = is_null($all_suc_info['allmoney']) ? 0 : $all_suc_info['allmoney'];//总成功金额
                $row['all_success_order'] = $all_suc_info['allorder'];//总成功订单数量
                
                
                //总成功金额 单量
                $all_fail_info = Db::name('order')->where(['mer_id'=>$row['id'],'status'=>3])
                    ->where($map2)
                    ->field('sum(amount) as allmoney, count(*) as allorder')
                    ->find();
                    
                $row['all_fail_money'] = is_null($all_fail_info['allmoney']) ? 0 : $all_fail_info['allmoney'];//总成功金额
                $row['all_fail_order'] = $all_fail_info['allorder'];//总成功订单数量
                
                
                //成功率
                if ($row['all_success_order'] == 0){
                    $row['success_rate'] = "0%";
                }else{
                    if($row['all_order'] == 0){
                        $row['success_rate'] ='0%';
                    }else{
                        $row['success_rate'] = (bcdiv($row['all_success_order'],$row['all_order'],4) * 100) ."%";
                    }
                }
                
            }


            //总金额
            $allmoney = Db::name('order')->where($map)->sum('amount');
            $allsuccessmoney = Db::name('order')->where($map)->where(['status'=>1])->sum('amount');
            $allfailemoney = Db::name('order')->where($map)->where('status',3)->sum('amount');
            //总订单数量
            $allorder = Db::name('order')->where($map)->count();
            //总成功数量
            $allsuccessorder = Db::name('order')->where($map)->where(['status'=>1])->count();
            //总异常数量
            $allfaileorder = Db::name('order')->where($map)->where('status',3)->count();
            //总手续费
            $allfees = Db::name('order')->where($map)->where(['status'=>1])->sum('fees');


            $result = array("total" => $total, "rows" => $list, "extend" => ['allmoney' => $allmoney,
                'allsuccessmoney'   => $allsuccessmoney,
                'allfailemoney'     => $allfailemoney,
                'allorder'          => $allorder,
                'allsuccessorder'   => $allsuccessorder,
                'allfaileorder'     => $allfaileorder,
                'allfees'           => $allfees,
            ]);

            return json($result);

        }
        
        
        $this->assignconfig('intervaltime',15000);

        return $this->view->fetch();
    }

    //详情
    public function detail($ids = null){
        $where = ['mer_id' => $ids,'status'=>1];

        $today_money = Db::name('order')->where($where)->whereTime('createtime', 'today')->sum('amount');                   //今日成功总额
        $today_order = Db::name('order')->where($where)->whereTime('createtime', 'today')->count();		                    //今日成功订单数量
        $today_faile = Db::name('order')->where(['mer_id'=>$ids,'status'=>4])->whereTime('createtime', 'today')->count();	//今日失败订单数量

        $yesterday_money = Db::name('order')->where($where)->whereTime('createtime', 'yesterday')->sum('amount'); //昨日总额
        $yesterday_order = Db::name('order')->where($where)->whereTime('createtime', 'yesterday')->count();		  //昨日订单数量
        $yesterday_faile = Db::name('order')->where(['mer_id'=>$ids,'status'=>4])->whereTime('createtime', 'yesterday')->count();	  //昨日失败订单数量

        $all_money = Db::name('order')->where($where)->sum('amount');                   //总成功金额
        $all_order = Db::name('order')->where($where)->count();	                        //总成功订单数量
        $all_faile = Db::name('order')->where(['mer_id'=>$ids,'status'=>4])->count();	//总失败订单数量

        $today_fees = Db::name('order')->where($where)->whereTime('createtime', 'today')->sum('fees');            //今日手续费
        $yesterday_fees = Db::name('order')->where($where)->whereTime('createtime', 'yesterday')->sum('fees');    //昨日手续费
        $all_fees = Db::name('order')->where($where)->sum('fees');		                                          //总手续费

        $apply_fees = Db::name('applys')->where($where)->sum('fees');		                                         //充值总手续费
        $today_apply_fees = Db::name('applys')->where($where)->whereTime('createtime', 'today')->sum('fees');		 //今日总手续费
        $yesterday_apply_fees = Db::name('applys')->where($where)->whereTime('createtime', 'yesterday')->sum('fees');//昨日总手续费

        $merchant = Db::name('merchant')->where('id',$ids)->field('id,username,money')->find();

        $this->view->assign([
            'merchant_name'=>$merchant['username'],
            'merchant_money'=>$merchant['money'],
            'today_money'=>$today_money,
            'today_order'=>$today_order,
            'today_faile'=>$today_faile,
            'yesterday_money'=>$yesterday_money,
            'yesterday_order'=>$yesterday_order,
            'yesterday_faile'=>$yesterday_faile,
            'all_money'=>$all_money,
            'all_order'=>$all_order,
            'all_faile'=>$all_faile,
            'today_fees'=>$today_fees,
            'yesterday_fees'=>$yesterday_fees,
            'all_fees'=>$all_fees,
            'apply_fees'=>$apply_fees,
            'today_apply_fees'=>$today_apply_fees,
            'yesterday_apply_fees'=>$yesterday_apply_fees,
        ]);
        return $this->view->fetch();
    }
    
    //商户时段统计
    public function timestatistics($ids = null){
        
        if ($this->request->isAjax()){
            
            $filter =   json_decode($this->request->get('filter'),true);

            $merchant = Merchant::where('id',$ids)->find();
            $map = [];

            $map[] = ['mer_id','=',$ids];
            
            $success_where = [];
            $fail_where    = [];
            
            $success_where[] = ['mer_id','=',$ids];
            $success_where[] = ['status','=',1];
            
            $fail_where[] = ['mer_id','=',$ids];
            $fail_where[] = ['status','=',3];
            
            if(isset($filter['pay_type'])){
                $pay_type        = $filter['pay_type'];
                $acc_name        = Db::name('acc')->where('code',$pay_type)->value('name');
                $map[]           = ['pay_type', '=', $pay_type];
                $success_where[] = ['pay_type', '=', $pay_type];
                $fail_where[]    = ['pay_type', '=', $pay_type];
            }else{
                $pay_type = '全部';
                $acc_name = '全部';
            }
            
            //结束时间
            $end_time = date('Y-m-d H:i:s', time());
            
            $list = [];
            
            for($i=0;$i<6;$i++){
                if($i == 0){
                    $sub_time = 10*60;
                    $type = '10分钟';
                }
                if($i == 1){
                    $sub_time = 20*60;
                    $type = '20分钟';
                }
                if($i == 2){
                    $sub_time = 30*60;
                    $type = '30分钟';
                }
                if($i == 3){
                    $sub_time = 60*60;
                    $type = '1小时';
                }
                if($i == 4){
                    $sub_time = 240*60;
                    $type = '4小时';
                }
                
                //开始时间
                $stare_time  = date('Y-m-d H:i:s', time() - $sub_time);
                
                if($i == 5){
                    $stare_time = strtotime('today');
                    $end_time   = mktime(0,0,0,date('m'),date('d')+1,date('Y'))-1;
                    $type       = '今日';
                }
               
            
                $all_money = Db::name('order')
                    ->where($map)
                    ->whereBetweenTime('createtime', $stare_time, $end_time)
                    ->sum('amount');
                    
                $all_success_money = Db::name('order')
                    ->where($success_where)
                    ->whereBetweenTime('createtime', $stare_time, $end_time)
                    ->sum('amount');
                    
                $all_fail_money = Db::name('order')
                    ->where($fail_where)
                    ->whereBetweenTime('createtime', $stare_time, $end_time)
                    ->sum('amount');
                
                $all_order = Db::name('order')
                    ->where($map)
                    ->whereBetweenTime('createtime', $stare_time, $end_time)
                    ->count();
                    
                $all_success_order = Db::name('order')
                    ->where($success_where)
                    ->whereBetweenTime('createtime', $stare_time, $end_time)
                    ->count();
                    
                $all_fail_order = Db::name('order')
                    ->where($fail_where)
                    ->whereBetweenTime('createtime', $stare_time, $end_time)
                    ->count();
                
                $all_wati_num = $all_order - $all_success_order - $all_fail_order;
                
                $success_rate = $all_order == 0 ?  "0%" : (bcdiv($all_success_order, $all_order, 4) * 100) ."%";
                
                $row1['id']                = $ids;
                $row1['username']          = $merchant['username'];
                $row1['type']              = $type;
                $row1['pay_type']          = $acc_name;
                $row1['all_money']         = $all_money;
                $row1['all_success_money'] = $all_success_money;
                $row1['all_fail_money']    = $all_fail_money;
                $row1['all_order']         = $all_order;
                $row1['all_success_order'] = $all_success_order;
                $row1['all_wati_num']      = $all_wati_num;
                $row1['all_fail_order']    = $all_fail_order;
                $row1['success_rate']      = $success_rate;
                
                $list[] = $row1;
                
            }
            
            
            $row['id']        = $ids;
            $row['username']  = $merchant['username'];;
            $row['type']      = '全部';
            $row['pay_type']  = $acc_name;
            $row['all_money']         = Db::name('order')->where($map)->sum('amount');
            //该用户成功总金额
            $row['all_success_money'] = Db::name('order')->where($success_where)->sum('amount');
            //该用户失败总金额
            $row['all_fail_money']    = Db::name('order')->where($fail_where)->sum('amount');
            //该用户总订单数量
            $row['all_order']         = Db::name('order')->where($map)->count();
            //该用户总成功订单数量
            $row['all_success_order'] = Db::name('order')->where($success_where)->where($map)->count();
            //该用户总失败订单数量
            $row['all_fail_order']    = Db::name('order')->where($fail_where)->count();
            //该用户全部等待支付订单数量
            $row['all_wati_num'] = $row['all_order'] - $row['all_success_order'] - $row['all_fail_order'];
            //成功率
            $row['success_rate'] = $row['all_success_order'] == 0 ? "0%" : (bcdiv($row['all_success_order'], $row['all_order'], 4) * 100) . "%";

            
            $list[] = $row;
            $total  = count($list);
            
            $result = array("total" => $total, "rows" => $list, "extend" => [
                'allmoney'        => 100,
                'allsuccessmoney' => 100,
                'allfailemoney'   => 100,
                'allorder'        => 100,
                'allsuccessorder' => 100,
                'allfaileorder'   => 100,
                'allfees'         => 100,
            ]);

            return json($result);

        }
        
        $this->assignconfig('ids', $ids);
        return $this->view->fetch();
    }
    
    /**
     * 商户通道数据统计
     */
    public function useraccstatisticsV1()
    {
        $ids = $this->request->param("ids");
        
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
            $this->model = new \app\admin\model\thirdacc\Agentacc;
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $total = $this->model
                    ->withJoin(['acc'])
                    ->where('agent_id', $this->auth->id)
                    ->where($where)
                    ->order($sort, $order)
                    ->count();
            
            $list = $this->model
                    ->withJoin(['acc'])
                    ->where('agent_id', $this->auth->id)
                    ->where($where)
                    ->order($sort, $order)
                    ->limit($offset, $limit)
                    ->select();
            
            foreach ($list as $row) {
                
                if (!empty($row['acc'])){
                    $row->getRelation('acc')->visible(['name']);
                }
                
                //今日成功金额收款
                $today_suc_money = Db::name('order')->where(['mer_id'=>$ids,'status'=>1,'pay_type'=>$row['acc_code']])->whereDay('createtime')->sum('amount');
                
                //昨日成功金额
                $yesterday_suc_money = Db::name('order')->where(['mer_id'=>$ids,'status'=>1,'pay_type'=>$row['acc_code']])->whereDay('createtime','yesterday')->sum('amount');
                
                $row['today_suc_money']     = $today_suc_money;
                $row['yesterday_suc_money'] = $yesterday_suc_money;
                
            }
            $result = array("total" => $total, "rows" => $list);

            return json($result);
        }

        $this->assignconfig('user_id', $ids);
        $finduser = Db::name('merchant')->where('id', $ids)->find();
        $this->assign('username', $finduser['username']);

        return $this->view->fetch();
    }
    
    //商户通道数据统计
    //ids用户id
    public function useraccstatistics($ids = null){
        
        $merchant = Db::name('merchant')->where('id',$ids)->field('username,nickname')->find();
        
        if ($this->request->isAjax()){
            
            $filter =   json_decode($this->request->get('filter'),true);
            
            $success_where = [];
            $fail_where    = [];
            
            $success_where[] = ['mer_id','=',$ids];
            $success_where[] = ['status','=','1'];
            
            $createtime      = explode(' - ',$filter['createtime']);
            $timeStr         = strtotime($createtime[0]).','.strtotime($createtime[1]);
            $success_where[] = ['callback_time', 'between', $timeStr];
            
            
            $fail_where[] = ['mer_id','=',$ids];
            $fail_where[] = ['status','=',3];
            
            $allfees    = 0;
            $allmoney   = 0;
            $allnum     = 0;
            $allbalance = 0;
            $suc_result = Db::name('order')
                        ->where($success_where)
                        ->field('pay_type, sum(amount) as amount, count(*) as count, sum(mer_fees) as fees')
                        ->group('pay_type')
                        ->select()->toArray();
            
            foreach ($suc_result as &$row){
                
                $row['acc_name'] = Db::name('acc')->where(['code'=>$row['pay_type']])->value('name');
                
                $allmoney += $row['amount'];
                $allnum   += $row['count'];
                
                
                /*$merAcc         = Db::name('mer_acc')->where(['mer_id'=>$ids,'acc_code'=>$row['pay_type']])->find();
                $row['rate']    = $merAcc['rate'];
                $row['balance'] = bcsub($row['amount'], $row['fees'], 2);
                $allbalance     = bcadd($allbalance, $row['balance'], 2);
                $allfees        = bcadd($allfees, $row['fees'], 2);*/
                
                $map[] = ['mer_id','=',$ids];
                
                //全部订单
                $all_order = Db::name('order')
                    ->where(['mer_id'=>$ids, 'pay_type' => $row['pay_type']])
                    ->whereTime('createtime', 'between', $createtime)
                    //->whereBetweenTime('createtime', $stare_time, $end_time)
                    ->count();
                
                $row['success_rate'] = $row['count'] == 0 ? '0%' : (bcdiv($row['count'], $all_order, 4) * 100) ."%";
                
                
            }
            
            $total = count($suc_result);
            
            $result = array("total" => $total, "rows" => $suc_result, "extend" => [
                'allmoney'   => $allmoney,
                'allnum'     => $allnum,
                //'allbalance' => $allbalance,
                //'allfees'    => $allfees,
                
            ]);
            
            return json($result);
            
        }
        
        $this->assign('username', $merchant['username'] . "(".$merchant['nickname'].")");
        $this->assignconfig('ids', $ids);
        return $this->view->fetch();
        
    }
    
}
