<?php

namespace app\user\controller\moneylog;

use app\admin\model\user\User;
use app\common\controller\UserBackend;
use app\common\library\MoneyLog;
use think\facade\Db;
use think\facade\Config;

/**
 * 商户余额变更记录管理
 *
 * @icon fa fa-circle-o
 */
class Usermoneylog extends UserBackend
{
    
    /**
     * Usermoneylog模型对象
     * @var \app\user\model\moneylog\Usermoneylog
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\user\model\moneylog\Usermoneylog;
        $this->view->assign("typeList", $this->model->getTypeList());
        $this->view->assign("isAutomaticList", $this->model->getIsAutomaticList());
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
                    ->withJoin(['user'])
                    ->where(['user_id'=>$this->auth->id])
                    ->where($where)
                    ->order($sort, $order)
                    ->count();

            $list = $this->model
                    ->withJoin(['user'])
                    ->where(['user_id'=>$this->auth->id])
                    ->where($where)
                    ->order($sort, $order)
                    ->limit($offset, $limit)
                    ->select();

            foreach ($list as $row) {
                $row->visible(['id','out_trade_no','type','fees','amount','before_amount','after_amount','create_time','update_time','remark','ip_address','is_automatic']);
                if (!empty($row['user'])){
                    $row->getRelation('user')->visible(['username']);
                }
            }
            $result = array("total" => $total, "rows" => $list);

            return json($result);
        }
        
        $is_send = Config::get('site.user_sendbalance');
        $this->view->assign('is_send', $is_send);
        
        return $this->view->fetch();
    }

    public function addmoney($ids = null){

        if ($this->request->isPost()) {
            if(empty($ids)){
                $this->error('码商错误');
            }

            $params = $this->request->post("row/a");
            if ($params) {
                $params = $this->preExcludeFields($params);

                if ($this->dataLimit && $this->dataLimitFieldAutoFill) {
                    $params[$this->dataLimitField] = $this->auth->id;
                }
                
                $myself = Db::name('user')->where('id', $this->auth->id)->find();
                $user = User::find($ids);

                if ($user['status'] == 'hidden') {
                    $this->error(__('账号已被禁用'));
                }
                
                
                
                
                
                $out_trade_no = '11111111';
                $trade_no     = '11111111';

                switch ($params['type']) { //0支出 1增加 2冻结 3解冻
                    case 0:
                        $this->error('开发中');
                        if ($user['money'] < $params['amount']) {
                            $this->error('余额不足本次操作');
                        }
                        $remark = empty($params['remark']) ? '手动扣除' : $params['remark'];
                        //码商余额记录
                        $result = MoneyLog::userMoneyChange($ids, $params['amount'], 0, $out_trade_no, $trade_no, $remark,0, 1);
                        break;
                    case 1:
                        
                        if($myself['money'] < $params['amount']){
                            $this->error('账户余额不足');
                        }
                        
                        $remark = '下级上分扣除';
                        
                        //扣掉本账户余额
                        $result = MoneyLog::userMoneyChange($myself['id'], $params['amount'], 0, $out_trade_no, $trade_no, $remark,0, 0);
                        
                        //给码商加余额
                        $remark = empty($params['remark']) ? '下级上分扣除' : $params['remark'];
                        $result = MoneyLog::userMoneyChange($ids, $params['amount'], 0, $out_trade_no, $trade_no, $remark,1, 1);
                        
                        break;
                    case 2:
                        $this->error('开发中');
                        break;

                    case 3:
                        $this->error('开发中');
                        break;

                    default:
                        // code...
                        break;
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
    
    public function sendbalance(){
        
        if ($this->request->isPost()) {
            
            $params = $this->request->post("row/a");
            if ($params) {
                $params = $this->preExcludeFields($params);

                if ($this->dataLimit && $this->dataLimitFieldAutoFill) {
                    $params[$this->dataLimitField] = $this->auth->id;
                }
                
                
                if(Config::get('site.user_sendbalance') == '0'){
                    $this->error('失败');
                }
                
                
                $myself = Db::name('user')->where('id', $this->auth->id)->find();
                if ($myself['status'] == 'hidden') {
                    $this->error(__('账号已被禁用'));
                }
                
                $user =  Db::name('user')->where(['agent_id'=>$myself['agent_id'], 'username'=>$params['username']])->find();
                
                if (empty($user)) {
                    $this->error('转移账号不存在');
                }
                
                
                $out_trade_no = date("Ymd") . mt_rand(10000, 99999) . time();
                $trade_no     = $out_trade_no;
                
                
                if($myself['money'] < $params['amount']){
                    $this->error('账户余额不足');
                }
                
                $remark = '余额转移';
                
                //扣掉本账户余额
                $result = MoneyLog::userMoneyChange($myself['id'], $params['amount'], 0, $out_trade_no, $trade_no, $remark,0, 0);
                
                //给码商加余额
                $result = MoneyLog::userMoneyChange($user['id'], $params['amount'], 0, $out_trade_no, $trade_no, $remark,1, 1);
                
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
    
}
