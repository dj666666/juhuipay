<?php

namespace app\agent\controller\daifu;

use app\admin\model\daifu\Dforder;
use app\common\controller\Jobs;
use app\common\controller\AgentBackend;
use app\common\library\Notify;
use app\common\library\Utils;
use think\facade\Config;
use think\facade\Db;
use think\facade\Log;
use think\facade\Queue;
use think\facade\Cache;

/**
 * 待抢单订单管理
 *
 * @icon fa fa-circle-o
 */
class Roborder extends AgentBackend
{

    /**
     * Order模型对象
     * @var \app\user\model\order\Order
     */
    protected $model = null;

    protected $redis = null;

    public function _initialize() {
        parent::_initialize();
        $this->model = new \app\admin\model\daifu\Dforder;
        $this->view->assign("statusList", $this->model->getStatusList());
        $this->view->assign("orderTypeList", $this->model->getOrderTypeList());
        $this->view->assign("isCallbackList", $this->model->getIsCallbackList());
        $this->view->assign("isResetorderList", $this->model->getIsResetorderList());
        $this->redis = Cache::store('redis');
    }
    
    /**
     * 查看
     */
    public function index() {
        //当前是否为关联查询
        $this->relationSearch = false;
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            
            //未抢的单
            $total = $this->model
                //->withJoin(['merchant'])
                ->where(['agent_id' => $this->auth->agent_id, 'status' => Dforder::ORDER_STATUS_DEALING, 'is_robbed' => 0,'is_third_df'=>'0'])
                ->where($where)
                ->order($sort, $order)
                ->count();
                
            $list = $this->model
                //->withJoin(['merchant'])
                ->where(['agent_id' => $this->auth->agent_id, 'status' => Dforder::ORDER_STATUS_DEALING, 'is_robbed' => 0,'is_third_df'=>'0'])
                ->where($where)
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();

            foreach ($list as $row) {
                $row->visible(['id','user_id','out_trade_no','trade_no','bank_user','bank_type','bank_number','createtime','remark','status','amount','is_robbed']);
            }

            $result = array("total" => $total, "rows" => $list, 'extend' => [
                'allmoney'   => 0,
                'allorder'   => 0,
                'todayMoney' => 0,
                'todayOrder' => 0,
            ]);

            return json($result);
        }

        $this->assignconfig('uid', $this->auth->id);
        $this->assignconfig('ordertime', 10000);

        return $this->view->fetch();
    }

    //抢单
    public function robOrder($ids = null) {

        if (!$ids) {
            $this->error('参数错误');
        }

        $user_id  = $this->auth->id;
        $username = $this->auth->username;

        Log::write('robOrder----' . request()->ip() . '----' . $ids . '----' . $username, 'info');

        //并发锁key
        $setnx_key = 'bw:user:' . $user_id . ':robOrder_setnx';

        $setnx_re = $this->set_mutex($setnx_key);
        if (!$setnx_re) {
            $this->error('抢单失败');
        }

        $count = $this->model::where(['user_id' => $user_id, 'status' => Dforder::ORDER_STATUS_DEALING])->count();
        if ($count >= 5) {
            $this->error('先处理订单');
        }

        Db::startTrans();
        try {

            $order = $this->model::where(['id' => $ids, 'status' => Dforder::ORDER_STATUS_DEALING])->lock(true)->field('id,user_id,status,is_robbed,out_trade_no,trade_no,amount')->find();
            if (empty($order)) {
                $this->error('订单不存在');
            }

            if (!empty($order['user_id']) || $order['is_robbed'] == 1) {
                $this->error('该订单已被抢，请刷新');
            }

            Log::write($order['out_trade_no'] . '----修改前数据' . json_encode($order, JSON_UNESCAPED_UNICODE), 'info');

            $re = $this->model::where('id', $ids)->update([
                'user_id'     => $user_id,
                'is_robbed'   => 1,
                'robbed_time' => time(),
            ]);

            Db::commit();


        } catch (\Exception $e) {
            Db::rollback();
            Log::debug('robOrderBug----' . request()->ip() . '----' . $ids . '----' . $username . '----' . $order['out_trade_no'] . '----' . $e->getLine() . '----' . $e->getMessage());
            $this->error('抢单错误');
        }

        if (!$re) {
            $this->del_mutex($setnx_key);
            $this->error('抢单失败');
        }

        Log::write(request()->ip() . '----' . $ids . '----' . $username . '----' . $order['out_trade_no'] . '----抢单成功', 'dforder');
        $this->del_mutex($setnx_key);
        $this->success('抢单成功');

    }

    //redis接口限流
    public function requestLimit($user_id, $key) {

        //$key = 'xc:user:'.$user_id.':robOrder_limit';

        //限制次数为5
        $limit = 6;
        $check = $this->redis->exists($key);
        if ($check) {
            $this->redis->incr($key);//键值递增
            $count = $this->redis->get($key);
            if ($count > $limit) {
                //关掉接单
                Db::name('user')->where('id', $user_id)->update(['is_receive' => 2, 'updatetime' => time(), 'remark' => '抢单太快']);;
                $this->error('request error');
            }
        } else {
            $this->redis->incr($key);
            //设置过期时间
            $this->redis->expire($key, 1);
        }

        //抢完一单后下一单得30秒后抢
        // if(!empty($this->redis->get($user_id.'rob'))){
        //     $this->error('抢单过快，请稍后');
        // }


        return true;
    }

    /**
     * 设置锁
     *
     * @param $uid
     * @param int $timeout
     * @return bool
     *
     */
    public function set_mutex($key, $timeout = 2) {
        $cur_time  = time();
        $mutex_res = $this->redis->setnx($key, $cur_time + $timeout);
        //加锁成功
        if ($mutex_res) {
            return true;
        }

        //就算意外退出，下次进来也会检查key，防止死锁
        $time = $this->redis->get($key);
        //如果当前时间 大于redis锁设置的时间那就说明被别人设置了key的过期时间
        if ($cur_time > $time) {
            $this->redis->del($key);
            return $this->redis->setnx($key, $cur_time + $timeout);
        }

        return false;
    }

    /**
     * 释放锁
     *
     * @param $uid
     *
     */
    public function del_mutex($key) {
        $this->redis->del($key);
    }
}
