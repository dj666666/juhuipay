<?php

namespace app\admin\controller\statistics;

use app\common\controller\Backend;
use think\facade\Db;
use app\admin\model\agent\Agent;
use app\admin\model\merchant\Merchant;
use app\admin\model\order\Order;

/**
 * 商户管理
 *
 * @icon fa fa-circle-o
 */
class Merchantstatistics extends Backend
{
    
    /**
     * Merchantstatistics模型对象
     * @var \app\admin\model\statistics\Merchantstatistics
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\statistics\Merchantstatistics;
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
            $filter =   json_decode($this->request->get('filter'),true);

            $map = [];

            if(isset($filter['createtime'])){
                $createtime = explode(' - ',$filter['createtime']);
                $timeStr = strtotime($createtime[0]).','.strtotime($createtime[1]);
                $map[]= ['createtime','between', $timeStr];
            }
            
            if(isset($filter['pay_type'])){
                $pay_type = $filter['pay_type'];
                $acc_name = Db::name('acc')->where('code',$pay_type)->value('name');
                //$acc_name = $acc_name.'('.$pay_type.')';
                $map[]= ['pay_type','=', $pay_type];
            }else{
                $pay_type = '全部';
                $acc_name = '全部';
            }
            
            $where_new = [];
            if(isset($filter['username'])){
                $merchantId = Db::name('merchant')->where('username',$filter['username'])->value('id');
                $map[]       = ['mer_id','=',$merchantId];
                $where_new[] = ['id','=', $merchantId];
            }
            
            if(isset($filter['agent_name'])){
                $agentId = Db::name('agent')->where('username',$filter['agent_name'])->value('id');
                $map[]       = ['agent_id','=',$agentId];
                $where_new[] = ['agent_id','=', $agentId];
            }
            
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $total = $this->model
                //->where($where)
                ->where($where_new)
                ->order($sort, $order)
                ->count();

            $list = $this->model
                //->where($where)
                ->where($where_new)
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();
                
            foreach ($list as $row) {

                $row->visible(['id','username','type','pay_type','time','success_rate','all_money','all_success_money','all_fail_money','all_order','all_success_order','all_wati_num','all_fail_order','all_fail_money','agent_id','agent_name']);
                $row['agent_name'] = Agent::where(['id'=>$row['agent_id']])->value('username');
                //该用户总金额
                $row['type']      = '全部';
                $row['pay_type']  = $acc_name;
                $row['all_money'] = Order::where(['mer_id'=>$row['id']])->where($map)->sum('amount');
                //该用户成功总金额
                $row['all_success_money'] = Order::where(['mer_id'=>$row['id'],'status'=>1])->where($map)->sum('amount');
                //该用户失败总金额
                $row['all_fail_money'] = Order::where(['mer_id'=>$row['id'],'status'=>3])->where($map)->sum('amount');
                //该用户总订单数量
                $row['all_order'] = Order::where(['mer_id'=>$row['id']])->where($map)->count();
                //该用户总成功订单数量
                $row['all_success_order'] = Order::where(['mer_id'=>$row['id'],'status'=>1])->where($map)->count();
                //该用户总失败订单数量
                $row['all_fail_order'] = Order::where(['mer_id'=>$row['id']])->where('status',3)->where($map)->count();
                
                $row['all_wati_num'] = $row['all_order'] - $row['all_success_order'] - $row['all_fail_order'];

                //成功率
                $row['success_rate'] = $row['all_success_order'] == 0 ? "0%" : (bcdiv($row['all_success_order'],$row['all_order'],4) * 100) ."%";
                
            }
            //总金额
            $allmoney        = Order::where($map)->sum('amount');
            $allsuccessmoney = Order::where(['status' => 1])->where($map)->sum('amount');
            $allfailemoney   = Order::where('status', 3)->where($map)->sum('amount');
            //总订单数量
            $allorder        = Order::where($map)->count();
            //总成功数量
            $allsuccessorder = Order::where(['status' => 1])->where($map)->count();
            //总异常数量
            $allfaileorder   = Order::where('status', 3)->count();
            //总手续费
            $allfees         = Order::where(['status' => 1])->where($map)->sum('fees');


            $result = array("total" => $total, "rows" => $list, "extend" => [
                'allmoney'        => $allmoney,
                'allsuccessmoney' => $allsuccessmoney,
                'allfailemoney'   => $allfailemoney,
                'allorder'        => $allorder,
                'allsuccessorder' => $allsuccessorder,
                'allfaileorder'   => $allfaileorder,
                'allfees'         => $allfees,
            ]);

            return json($result);

        }
        return $this->view->fetch();
    }


    public function detail($ids = null){
        $success_where = ['mer_id' => $ids, 'status' => 1];
        $fail_where    = ['mer_id' => $ids, 'status' => 3];

        //今日总额
        $today_money = Order::where($success_where)->whereDay('createtime')->sum('amount');
        //今日订单数量
        $today_order = Order::where($success_where)->whereDay('createtime')->count();
        //今日失败订单数量
        $today_fail = Order::where($fail_where)->whereDay('createtime')->count();

        //昨日总额
        $yesterday_money = Order::where($success_where)->whereDay('createtime', 'yesterday')->sum('amount');
        //昨日订单数量
        $yesterday_order = Order::where($success_where)->whereDay('createtime', 'yesterday')->count();
        //昨日失败订单数量
        $yesterday_fail = Order::where($fail_where)->whereDay('createtime', 'yesterday')->count();

        //总额
        $all_money = Order::where($success_where)->sum('amount');
        //总订单数量
        $all_order = Order::where($success_where)->count();
        //总失败订单数量
        $all_fail = Order::where($fail_where)->count();

        //今日手续费
        $today_fees = Order::where($success_where)->whereDay('createtime')->sum('fees');
        //昨日手续费
        $yesterday_fees = Order::where($success_where)->whereDay('createtime', 'yesterday')->sum('fees');
        //总手续费
        $all_fees = Order::where($success_where)->sum('fees');

        //总提现
        $all_apply = Db::name('applys')->where($success_where)->sum('fees');
        //今日提现
        $today_apply = Db::name('applys')->where($success_where)->whereDay('createtime')->sum('fees');
        //昨日提现
        $yesterday_apply = Db::name('applys')->where($success_where)->whereDay('createtime', 'yesterday')->sum('fees');

        $merchant = Db::name('merchant')->where('id',$ids)->field('id,username,money')->find();

        $this->view->assign([
            'merchant_name'=>$merchant['username'],
            'merchant_money'=>$merchant['money'],
            'today_money'=>$today_money,
            'today_order'=>$today_order,
            'today_fail'=>$today_fail,
            'yesterday_money'=>$yesterday_money,
            'yesterday_order'=>$yesterday_order,
            'yesterday_fail'=>$yesterday_fail,
            'all_money'=>$all_money,
            'all_order'=>$all_order,
            'all_fail'=>$all_fail,
            'today_fees'=>$today_fees,
            'yesterday_fees'=>$yesterday_fees,
            'all_fees'=>$all_fees,
            'all_apply'=>$all_apply,
            'today_apply_fees'=>$today_apply,
            'yesterday_apply_fees'=>$yesterday_apply,
        ]);
        return $this->view->fetch();
    }
    
    
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
            $row['all_money']         = Order::where($map)->sum('amount');
            //该用户成功总金额
            $row['all_success_money'] = Order::where($success_where)->sum('amount');
            //该用户失败总金额
            $row['all_fail_money']    = Order::where($fail_where)->sum('amount');
            //该用户总订单数量
            $row['all_order']         = Order::where($map)->count();
            //该用户总成功订单数量
            $row['all_success_order'] = Order::where($success_where)->where($map)->count();
            //该用户总失败订单数量
            $row['all_fail_order']    = Order::where($fail_where)->count();
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
}
