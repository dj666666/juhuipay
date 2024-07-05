<?php

namespace app\agent\controller\statistics;

use app\common\controller\AgentBackend;
use think\facade\Db;
use app\admin\model\order\Order;

/**
 * 会员管理
 *
 * @icon fa fa-user
 */
class Userstatistics extends AgentBackend
{
    
    /**
     * Userstatistics模型对象
     * @var \app\agent\model\statistics\Userstatistics
     */
    protected $model = null;
    protected $accModel = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model    = new \app\agent\model\statistics\Userstatistics;
        
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

            $op =   json_decode($this->request->get('op'),true);

            $map = [];
            $map[] = ['agent_id', '=', $this->auth->id];

            if(isset($filter['createtime'])){
                $createtime = explode(' - ',$filter['createtime']);
                $timeStr    = strtotime($createtime[0]).','.strtotime($createtime[1]);
                $map[]      = ['createtime','between', $timeStr];
            }

            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $total = $this->model
                    //->where($where)
                    ->where(['agent_id'=>$this->auth->id])
                    ->order($sort, $order)
                    ->count();

            $list = $this->model
                    //->where($where)
                    ->where(['agent_id'=>$this->auth->id])
                    ->order($sort, $order)
                    ->limit($offset, $limit)
                    ->select();

            foreach ($list as $row) {

                $row->visible(['id','username','nickname','time','success_rate','all_money','all_success_money','all_fail_money','all_order','all_success_order','all_fail_order','all_fail_money']);
                
                //$row['time'] = $filter['createtime'];
                //该用户总金额
                $row['all_money'] = Order::where(['user_id'=>$row['id']])->where($map)->sum('amount');
                //该用户成功总金额
                $row['all_success_money'] = Order::where(['user_id'=>$row['id'],'status'=>1])->where($map)->sum('amount');
                //该用户失败总金额
                $row['all_fail_money'] = Order::where(['user_id'=>$row['id']])->where('status',3)->where($map)->sum('amount');
                //该用户总订单数量
                $row['all_order'] = Order::where(['user_id'=>$row['id']])->where($map)->count();
                //该用户总成功订单数量
                $row['all_success_order'] = Order::where(['user_id'=>$row['id'],'status'=>1])->where($map)->count();
                //该用户总失败订单数量
                $row['all_fail_order'] = Order::where(['user_id'=>$row['id']])->where('status',3)->where($map)->count();

                //成功率
                if ($row['all_success_order'] == 0){
                    $row['success_rate'] = "0%";
                }else{
                    $row['success_rate'] = (bcdiv($row['all_success_order'],$row['all_order'],4) * 100) ."%";
                }
            }

            //总金额
            $allmoney = Order::where($map)->sum('amount');
            $allsuccessmoney = Order::where(['status'=>1])->where($map)->sum('amount');
            $allfailemoney = Order::where('status',3)->where($map)->sum('amount');
            //总订单数量
            $allorder = Order::where($map)->count();
            //总成功数量
            $allsuccessorder = Order::where(['status'=>1])->where($map)->count();
            //总异常数量
            $allfaileorder = Order::where('status',3)->count();
            //总手续费
            $allfees = Order::where(['status'=>1])->where($map)->sum('fees');


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
        return $this->view->fetch();
    }

    //详情
    public function detail($ids = null){
        $where = ['user_id' => $ids,'status'=>1];

        $today_money = Order::where($where)->whereTime('createtime', 'today')->sum('amount');                   //今日成功总额
        $today_order = Order::where($where)->whereTime('createtime', 'today')->count();		                    //今日成功订单数量
        $today_faile = Order::where(['user_id'=>$ids,'status'=>4])->whereTime('createtime', 'today')->count();	//今日失败订单数量

        $yesterday_money = Order::where($where)->whereTime('createtime', 'yesterday')->sum('amount'); //昨日总额
        $yesterday_order = Order::where($where)->whereTime('createtime', 'yesterday')->count();		  //昨日订单数量
        $yesterday_faile = Order::where(['user_id'=>$ids,'status'=>4])->whereTime('createtime', 'yesterday')->count();	  //昨日失败订单数量

        $all_money = Order::where($where)->sum('amount');                   //总成功金额
        $all_order = Order::where($where)->count();	                        //总成功订单数量
        $all_faile = Order::where(['user_id'=>$ids,'status'=>4])->count();	//总失败订单数量

        $today_fees = Order::where($where)->whereTime('createtime', 'today')->sum('fees');            //今日手续费
        $yesterday_fees = Order::where($where)->whereTime('createtime', 'yesterday')->sum('fees');    //昨日手续费
        $all_fees = Order::where($where)->sum('fees');		                                          //总手续费

        $apply_fees = Db::name('applys')->where($where)->sum('fees');		                                         //充值总手续费
        $today_apply_fees = Db::name('applys')->where($where)->whereTime('createtime', 'today')->sum('fees');		 //今日总手续费
        $yesterday_apply_fees = Db::name('applys')->where($where)->whereTime('createtime', 'yesterday')->sum('fees');//昨日总手续费

        $user = Db::name('user')->where('id',$ids)->field('id,username,money')->find();

        $this->view->assign([
            'merchant_name'=>$user['username'],
            'merchant_money'=>$user['money'],
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
    
    
    /**
     * 查看
     */
    public function useraccstatistics()
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
            $this->model = new \app\admin\model\thirdacc\Useracc;
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $total = $this->model
                    ->withJoin(['acc'])
                    ->where('user_id', $ids)
                    ->where($where)
                    ->order($sort, $order)
                    ->count();
            
            $list = $this->model
                    ->withJoin(['acc'])
                    ->where('user_id', $ids)
                    ->where($where)
                    ->order($sort, $order)
                    ->limit($offset, $limit)
                    ->select();
            
            foreach ($list as $row) {
                if (!empty($row['user'])){
                    $row->getRelation('user')->visible(['username']);
                }
                if (!empty($row['acc'])){
                    $row->getRelation('acc')->visible(['name']);
                }
                
                $all_info = Db::name('order')->where(['user_id'=>$ids,'status'=>1,'pay_type'=>$row['acc_code']])
                        ->field('sum(amount) as allmoney, count(*) as allorder')
                        ->whereDay('createtime')
                        ->find();
                
                $today_suc_money = is_null($all_info['allmoney']) ? 0 : $all_info['allmoney']; //今日成功金额收款
                $today_suc_order = $all_info['allorder'];                                      //今日成功订单数量
                
                
                //今日总订单
                $today_all_order = Db::name('order')->where(['user_id'=>$ids,'pay_type'=>$row['acc_code']])->whereDay('createtime')->count();
                        
                //昨日成功金额
                $yesterday_suc_money = Db::name('order')->where(['user_id'=>$ids,'status'=>1,'pay_type'=>$row['acc_code']])->whereDay('createtime','yesterday')->sum('amount');
                
                $row['today_suc_money']     = $today_suc_money;
                $row['yesterday_suc_money'] = $yesterday_suc_money;
                
                
                if ($today_suc_order == 0) {
                    $today_success_rate = '0%';
                } else {
                    $today_success_rate = (bcdiv($today_suc_order, $today_all_order, 4) * 100) . "%";
                }
                
                $row['today_rate']      = $today_success_rate;
                $row['today_suc_order'] = $today_suc_order;
                $row['today_all_order'] = $today_all_order;
                
            }
            $result = array("total" => $total, "rows" => $list);

            return json($result);
        }

        $this->assignconfig('user_id', $ids);
        $finduser = Db::name('user')->where('id', $ids)->find();
        $this->assign('username', $finduser['username']);

        return $this->view->fetch();
    }
    
}