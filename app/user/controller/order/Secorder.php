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
use think\facade\Queue;
use app\common\library\Accutils;
use fast\Random;
use app\common\library\CheckOrderUtils;
use app\admin\model\User;

/**
 * 订单管理
 *
 * @icon fa fa-circle-o
 */
class Secorder extends UserBackend
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

            $user_id = $this->auth->id;
            //获取我的下级码商
            $userIds = Utils::getMySecUser($user_id);

            $filter = json_decode($this->request->get('filter'), true);

            $map   = [];
            $map[] = ['user_id', 'in', $userIds];
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

            if (isset($filter['trade_no'])) {
                $map[] = ['trade_no', '=', $filter['trade_no']];
            }

            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $total = $this->model
                ->withJoin(['user' => ['username']], 'left')
                ->where($where)
                ->where('user_id', 'in', $userIds)
                ->count();

            $list = $this->model
                ->withJoin(['user' => ['username']], 'left')
                ->where($where)
                ->where('user_id', 'in', $userIds)
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();

            foreach ($list as $row) {
                $row->visible(['id','out_trade_no','trade_no','xl_order_id','qrcode_name','zfb_nickname','amount','trade_no','pay_amount','fees','pay_remark','zfb_code','status','is_callback','callback_count','createtime','ordertime','expire_time','callback_time','remark','pay_type','zfb_nickname','third_hx_status']);

                if($row['user']){
                    $row->getRelation('user')->visible(['username']);
                }

            }

            //今日金额
            $today_success_amount = $this->model->where($map)->sum('amount');
            //今日手续费
            $today_fees           = $this->model->where($map)->sum('fees');
            //今日订单数量
            $today_success_order  = $this->model->where($map)->count();
            //今日总订单
            $today_all_order      = $this->model->where('user_id', 'in', $userIds)->whereDay('createtime')->count();
            //今日成功率
            $today_success_rate   = $today_all_order == 0 ? "0%" : (bcdiv($today_success_order, $today_all_order, 4) * 100) . "%";


            $result = array("total" => $total, "rows" => $list, "extend" => [
                'today_success_amount' => $today_success_amount,
                'today_success_order'  => $today_success_order,
                'today_fees'           => $today_fees,
                'today_success_rate'   => $today_success_rate,
            ]);

            return json($result);
        }

        return $this->view->fetch();
    }

    public function edit($ids = '') {
        $this->success('success');
    }

    public function del($ids = '') {
        $this->success('success');
    }



}
