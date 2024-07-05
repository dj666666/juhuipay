<?php

namespace app\user\controller\statistics;

use app\common\controller\UserBackend;
use think\facade\Db;
use app\admin\model\order\Order;
use app\common\library\Utils;

/**
 * 下级码商统计
 *
 * @icon fa fa-user
 */
class Secuserstatistics extends UserBackend
{
    
    /**
     * Userstatistics模型对象
     * @var \app\user\model\statistics\Userstatistics
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\user\model\statistics\Userstatistics;

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
            
            
            $user_id = $this->auth->id;
            
            //获取我的下级码商
            $userIds = Utils::getMySecUser($user_id);
            
            $map = [];
            $map2 = [];//用来统计今天的
            $map3 = [];//用来统计工具栏的
            
            $map3[] = ['status', '=', 1];
            $map3[] = ['user_id', 'in', $userIds];
            
            if(isset($filter['createtime'])){
                $createtime = explode(' - ',$filter['createtime']);
                $timeStr    = strtotime($createtime[0]).','.strtotime($createtime[1]);
                $map[]      = ['createtime', 'between', $timeStr];
                $map2[]     = ['createtime','between', $timeStr];//用来统计今天总单数的
                $map3[]     = ['createtime','between', $timeStr];//用来统计今天总单数的
            }
            
            
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $total = $this->model
                    //->where($where)
                    ->where('id', 'in', $userIds)
                    ->order($sort, $order)
                    ->count();

            $list = $this->model
                    //->where($where)
                    ->where('id', 'in', $userIds)
                    ->order($sort, $order)
                    ->limit($offset, $limit)
                    ->select();
            
            foreach ($list as $row) {

                $row->visible(['id','username','nickname','time','success_rate','all_money','all_success_money','all_fail_money','all_order','all_success_order','all_fail_order','all_fail_money']);
                $row['time'] = $filter['createtime'];
                
                
                //总金额 单量
                $all_info = Db::name('order')->where('user_id',$row['id'])->where($map2)
                    ->field('sum(amount) as allmoney, count(*) as allorder')
                    ->find();
                $row['all_money'] = is_null($all_info['allmoney']) ? 0 : $all_info['allmoney'];//总额
                $row['all_order'] = $all_info['allorder'];//总订单数量
                
                
                //总成功金额 单量
                $all_suc_info = Db::name('order')->where(['user_id'=>$row['id'],'status'=>1])
                    ->where($map2)
                    ->field('sum(amount) as allmoney, count(*) as allorder')
                    ->find();
                    
                $row['all_success_money'] = is_null($all_suc_info['allmoney']) ? 0 : $all_suc_info['allmoney'];//总成功金额
                $row['all_success_order'] = $all_suc_info['allorder'];//总成功订单数量
                
                
                //成功率
                if ($row['all_success_order'] == 0){
                    $row['success_rate'] = "0%";
                }else{
                    $row['success_rate'] = (bcdiv($row['all_success_order'],$row['all_order'],2) * 100) ."%";
                }
            }
            
            
            //总成功金额 单量
            $all_suc_info = Db::name('order')->where($map3)
                ->field('sum(amount) as allmoney, count(*) as allorder')
                ->find();
                
            $allsuccessmoney = is_null($all_suc_info['allmoney']) ? 0 : $all_suc_info['allmoney'];//总成功金额
            $allsuccessorder = $all_suc_info['allorder'];//总成功订单数量
            
            $result = array("total" => $total, "rows" => $list, "extend" => [
                'allsuccessmoney'   => $allsuccessmoney,
                'allsuccessorder'   => $allsuccessorder,
            ]);
            
            return json($result);

        }
        return $this->view->fetch();
    }

    //详情
    public function detail($ids = null){
        $where = ['user_id' => $ids,'status'=>1];

        $today_money = Db::name('order')->where($where)->whereTime('createtime', 'today')->sum('amount');                   //今日成功总额
        $today_order = Db::name('order')->where($where)->whereTime('createtime', 'today')->count();		                    //今日成功订单数量
        $today_faile = Db::name('order')->where(['user_id'=>$ids,'status'=>4])->whereTime('createtime', 'today')->count();	//今日失败订单数量

        $yesterday_money = Db::name('order')->where($where)->whereTime('createtime', 'yesterday')->sum('amount'); //昨日总额
        $yesterday_order = Db::name('order')->where($where)->whereTime('createtime', 'yesterday')->count();		  //昨日订单数量
        $yesterday_faile = Db::name('order')->where(['user_id'=>$ids,'status'=>4])->whereTime('createtime', 'yesterday')->count();	  //昨日失败订单数量

        $all_money = Db::name('order')->where($where)->sum('amount');                   //总成功金额
        $all_order = Db::name('order')->where($where)->count();	                        //总成功订单数量
        $all_faile = Db::name('order')->where(['user_id'=>$ids,'status'=>4])->count();	//总失败订单数量

        $today_fees = Db::name('order')->where($where)->whereTime('createtime', 'today')->sum('fees');            //今日手续费
        $yesterday_fees = Db::name('order')->where($where)->whereTime('createtime', 'yesterday')->sum('fees');    //昨日手续费
        $all_fees = Db::name('order')->where($where)->sum('fees');		                                          //总手续费

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
}
