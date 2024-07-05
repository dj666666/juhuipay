<?php

namespace app\merchant\controller\order;

use app\common\controller\MerchantBackend;
use think\facade\Db;

/**
 * 订单管理
 *
 * @icon fa fa-circle-o
 */
class Order extends MerchantBackend
{
    
    /**
     * Order模型对象
     * @var \app\merchant\model\order\Order
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\order\Order;
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

            $map = [];
            $map[]= ['mer_id','=', $this->auth->id];

            if(isset($filter['createtime'])){
                $createtime = explode(' - ',$filter['createtime']);
                $timeStr = strtotime($createtime[0]).','.strtotime($createtime[1]);

                $map[]= ['createtime','between', $timeStr];
            }

            if(isset($filter['status'])){
                $map[]= ['status','=',$filter['status']];
            }

            if(isset($filter['out_trade_no'])){
                $map[]= ['out_trade_no','=',$filter['out_trade_no']];

            }

            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $total = $this->model
                    //->withJoin(['user','merchant'])
                    ->where(['mer_id'=>$this->auth->id])
                    ->where($where)
                    ->order($sort, $order)
                    ->count();

            $list = $this->model
                    //->withJoin(['user','merchant'])
                    ->where(['mer_id'=>$this->auth->id])
                    ->where($where)
                    ->order($sort, $order)
                    ->limit($offset, $limit)
                    ->select();

            //foreach ($list as $row) {
                
                //$row->getRelation('user')->visible(['username']);
				//$row->getRelation('merchant')->visible(['username']);
				//$row->getRelation('agent')->visible(['username']);
            //}
            //总金额
            $allmoney = Db::name('order')->where($map)->sum('amount');
            //总金额
            $allfees = Db::name('order')->where($map)->sum('fees');
            //总订单数量
            $allorder = Db::name('order')->where($map)->count();
            //今日订单
            $todayorder = Db::name('order')->where($map)->whereDay('createtime')->count();
            //今日金额
            $todayordermoney = Db::name('order')->where($map)->whereDay('createtime')->sum('amount');

            $merchant = Db::name('merchant')->where(['id'=>$this->auth->id])->field('money')->find();

            $result = array("total" => $total, "rows" => $list, "extend" => [
                'money'         => $merchant['money'],
                'allmoney'      => $allmoney,
                'allorder'      => $allorder,
                'allfees'       => $allfees,
                'todayorder'    => $todayorder,
                'todayordermoney'    => $todayordermoney,
            ]);

            return json($result);
        }
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

        $row['createtime'] = $row['createtime'] ? date('Y-m-d H:i:s',$row['createtime']) : '';
        $row['ordertime'] = $row['ordertime'] ? date('Y-m-d H:i:s',$row['ordertime']) : '';
        $row['expire_time'] = $row['expire_time'] ? date('Y-m-d H:i:s',$row['expire_time']) : '';
        $row['callback_time'] = $row['callback_time'] ? date('Y-m-d H:i:s',$row['callback_time']) : '';
        $this->view->assign("row", $row);
        return $this->view->fetch();

    }
}
