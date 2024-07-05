<?php

namespace app\admin\controller\daifu;

use app\common\controller\Backend;
use Exception;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Config;
use think\facade\Log;
use app\common\library\Notify;
use app\common\library\Utils;
use app\common\library\ThirdDf;

/**
 * 订单管理
 *
 * @icon fa fa-circle-o
 */
class Dforder extends Backend
{

    /**
     * Order模型对象
     * @var \app\admin\model\daifu\Dforder
     */
    protected $model = null;

    public function _initialize() {
        parent::_initialize();
        $this->model = new \app\admin\model\daifu\Dforder();
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

            $filter = json_decode($this->request->get('filter'), true);

            $op = json_decode($this->request->get('op'), true);

            $map = [];

            if (isset($filter['createtime'])) {
                $createtime = explode(' - ', $filter['createtime']);
                $timeStr    = strtotime($createtime[0]) . ',' . strtotime($createtime[1]);
                $map[]      = ['createtime', 'between', $timeStr];
            }

            if (isset($filter['ordertime'])) {
                $ordertime = explode(' - ', $filter['ordertime']);
                $timeStr   = strtotime($ordertime[0]) . ',' . strtotime($ordertime[1]);
                $map[]     = ['ordertime', 'between', $timeStr];
            }

            if (isset($filter['user.username'])) {
                //找出用户 获取用户id
                $userId = Db::name('user')->where('username', $filter['user.username'])->value('id');
                $map[]  = ['user_id', '=', $userId];
            }

            if (isset($filter['merchant.username'])) {
                //找出商户 获取商户id
                $merchantId = Db::name('merchant')->where('username', $filter['merchant.username'])->value('id');
                $map[]      = ['mer_id', '=', $merchantId];
            }

            if (isset($filter['agent.username'])) {
                //找出代理 获取代理id
                $agentId = Db::name('agent')->where('username', $filter['agent.username'])->value('id');
                $map[]   = ['agent_id', '=', $agentId];
            }

            if (isset($filter['status'])) {
                $map[] = ['status', '=', $filter['status']];
            }

            if (isset($filter['out_trade_no'])) {
                $map[] = ['out_trade_no', '=', $filter['out_trade_no']];

            }

            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $total = $this->model
                ->withJoin(['user' => ['username'], 'merchant' => ['username'], 'agent' => ['username']], 'left')
                ->where($where)
                ->order($sort, $order)
                ->count();

            $list = $this->model
                ->withJoin(['user' => ['username'], 'merchant' => ['username'], 'agent' => ['username']], 'left')
                ->where($where)
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();

            foreach ($list as $row) {
                if ($row['user']) {
                    $row->getRelation('user')->visible(['username']);
                }
                if ($row['merchant']) {
                    $row->getRelation('merchant')->visible(['username']);
                }
                if ($row['agent']) {
                    $row->getRelation('agent')->visible(['username']);
                }
            }

            $countlist = $this->model
                ->withJoin(['user' => ['username'], 'merchant' => ['username']], 'left')
                ->where($where)
                ->field('sum(amount) as allmoney, sum(fees) as allfees, count(*) as allorder')
                ->select()->toArray();
            $countlist = $countlist[0];

            $todayorder = $this->model->where($map)->whereDay('createtime')->count();

            $todayordermoney = $this->model->where($map)->whereDay('createtime')->sum('amount');

            $result = array("total" => $total, "rows" => $list, "extend" => [
                'allmoney'        => $countlist['allmoney'],
                'allorder'        => $countlist['allorder'],
                'allfees'         => $countlist['allfees'],
                'todayorder'      => $todayorder,
                'todayordermoney' => $todayordermoney,
            ]);

            return json($result);
        }
        return $this->view->fetch();
    }

    /**
     * 编辑
     */
    public function edit($ids = null) {
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

                    $updateParams['status']          = $params['status'];
                    $updateParams['ordertime']       = time();
                    $updateParams['deal_username']   = $this->auth->username;
                    $updateParams['deal_ip_address'] = request()->ip();
                    $updateParams['remark']          = '手动修改';

                    $result = $row->save($updateParams);
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

        return $this->view->fetch();
    }

    /**
     * 详情
     */
    public function detail($ids = null) {

        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $row = $row->toArray();

        $row['mer_username'] = Db::name('merchant')->where('id', $row['mer_id'])->value('username');

        $this->view->assign("row", $row);
        return $this->view->fetch();

    }

    //发送回调
    public function sendNotify() {

        $ids = $this->request->post('ids');

        if (empty($ids)) {
            $this->error('参数缺少');
        }

        $order = $this->model->where(['id' => $ids, 'is_callback' => 0])->find();

        if (!$order) {
            $this->error('订单不存在');
        }

        $status = $order['status'];

        //开始回调
        $notify     = new Notify();
        $callbackre = $notify->sendDfCallBack($order['id'], $status, $order['ordertime']);

        if ($callbackre['code'] != 1) {
            $msg = '回调失败，未收到success：' . $callbackre['content'];
            $this->error($msg);

        } else {

            $updata = [
                'is_callback'      => 1,
                'callback_time'    => time(),
                'callback_content' => $callbackre['content'],
                'deal_username'    => $order['deal_username'] . '-' . $this->auth->username . '回调',
            ];

        }

        $result = $this->model->where('id', $ids)->update($updata);

        /*//码商余额记录
        if (Config::get('site.user_rate') != 0) {
            $rateType = Config::get('site.user_rate') == 1 ? 1 : 0;
            $remark = $rateType == 1 ? '订单收益' : '订单扣除';
            Utils::userMoneyLogV2($order['user_id'], $order['amount'], $order['out_trade_no'], $rateType, $remark);
        }*/

        if (!$result) {
            $this->error('回调成功，处理失败');
        }

        $this->success('回调成功');

    }

    //驳回
    public function abnormal($ids = null) {

        //$ids = $this->request->post('id');

        if (empty($ids)) {
            $this->error(__('参数缺少'));
        }

        $order = $this->model->where(['id' => $ids, 'status' => 2])->find();

        if (!$order) {
            $this->error('订单不存在');
        }

        $pay_time = time();
        $status   = 3;

        $updata = [
            'status'          => $status,
            'ordertime'       => $pay_time,
            'deal_ip_address' => request()->ip(),
            'deal_user_id'    => $this->auth->id,
            'deal_username'   => $order['deal_username'] . '----' . $this->auth->username,
        ];

        $result1 = $this->model->where('id', $ids)->update($updata);

        if (!$result1) {
            $this->error('订单处理失败');
        }


        //开始回调 0手动 1自动
        if ($order['order_type'] == 1) {

            $notify     = new Notify();
            $callbackre = $notify->sendDfCallBack($order['id'], $status, $pay_time);

            if ($callbackre['code'] != 1) {
                $msg = '订单处理成功，回调失败，未收到success：' . $callbackre['content'];
                $this->error($msg);

            } else {

                $updata = [
                    'is_callback'      => 1,
                    'callback_time'    => time(),
                    'callback_content' => $callbackre['content'],
                ];

                $this->model->where('id', $ids)->update($updata);

            }

        }

        $this->success('驳回成功，回调成功');

    }

    //冲正
    public function reversalOrder($ids = null) {

        if (empty($ids)) {
            $this->error('参数缺少');
        }

        $order = $this->model->where(['id' => $ids, 'status' => 1])->find();

        if (!$order) {
            $this->error('订单不存在');
        }

        $status        = 4;
        $reversal_time = time();

        $updata = [
            'status'        => $status,
            'reversal_time' => $reversal_time,
            'reversal_ip'   => request()->ip(),
            'deal_username' => $order['deal_username'] . '----' . $this->auth->username,
        ];

        $result1 = $this->model->where('id', $ids)->update($updata);

        $msg = '';

        //开始回调 0手动 1自动
        if ($order['order_type'] == 1) {

            $notify     = new Notify();
            $callbackre = $notify->sendDfCallBack($order['id'], $status, $reversal_time);

            if ($callbackre['code'] != 1) {

                $msg = '回调失败，未收到success：' . $callbackre['content'];

            } else {

                $updata = [
                    'reversal_callback' => 1,
                    'reversal_content'  => $callbackre['content'],
                ];

                $this->model->where('id', $ids)->update($updata);
            }

        }


        //商户余额退回 TODO

        //代理余额退回 TODO
        if (Config::get('site.agent_rate') != 0) {
            Utils::agentMoneyLog($order['agent_id'], $order['amount'], $order['out_trade_no'], 0, '冲正退款');
        }

        //码商余额退回 TODO
        if (Config::get('site.user_rate') != 0) {
        }

        if (!empty($msg)) {
            $this->error('冲正成功，' . $msg);
        }

        $this->success('冲正成功，回调成功');
    }
    
    public function toThirdDf($ids = null){
        
        if (empty($ids)) {
            $this->error(__('参数缺少'));
        }

        $order = $this->model->where(['id' => $ids])->find();

        if (!$order) {
            $this->error('订单不存在');
        }
        
        $merchant = Db::name('merchant')->where('id',$order['mer_id'])->find();

        //判断是否转入三方代付
        if($merchant['is_third_df'] != 0){
            
            $df_res = ThirdDf::instance()->checkDfType($order['out_trade_no'], $order['bank_user'], $order['bank_type'], $order['bank_number'], $order['amount'], $merchant['df_acc_ids'], true);
            
            if($df_res['status']){
                $this->success($df_res['msg']);
            }else{
                $this->error($df_res['msg']);
            }
        }
        
        $this->error('该单无需转入三方');
        
    }
}
