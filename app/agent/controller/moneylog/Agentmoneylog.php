<?php

namespace app\agent\controller\moneylog;

use app\common\controller\AgentBackend;
use think\facade\Db;

/**
 * 代理余额记录管理
 *
 * @icon fa fa-circle-o
 */
class Agentmoneylog extends AgentBackend
{
    
    /**
     * Agentmoneylog模型对象
     * @var \app\agent\model\moneylog\Agentmoneylog
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\agent\model\moneylog\Agentmoneylog;
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
                    ->withJoin(['agent'])
                    ->where(['agent_id' => $this->auth->id])
                    ->where($where)
                    ->order($sort, $order)
                    ->count();

            $list = $this->model
                    ->withJoin(['agent'])
                    ->where(['agent_id' => $this->auth->id])
                    ->where($where)
                    ->order($sort, $order)
                    ->limit($offset, $limit)
                    ->select();

            foreach ($list as $row) {
                
                $row->getRelation('agent')->visible(['username']);
            }
            $result = array("total" => $total, "rows" => $list);

            return json($result);
        }
        return $this->view->fetch();
    }

    /**
     * 添加
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                $params = $this->preExcludeFields($params);

                if ($this->dataLimit && $this->dataLimitFieldAutoFill) {
                    $params[$this->dataLimitField] = $this->auth->id;
                }

                Db::startTrans();
                try {
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.add' : $name) : $this->modelValidate;
                        $this->model->validateFailException(true)->validate($validate);
                    }

                    //找出代理
                    $agent = Db::name('agent')->where('id',$params['agent_id'])->field('id,money,last_money_time,status')->find();

                    if($agent['status'] == 'hidden'){
                        $this->error(__('账号已被禁用'));
                    }


                    switch ($params['type']) { //0支出 1增加 2冻结 3解冻
                        case 0:
                            if($agent['money'] < $params['amount']){
                                $this->error(__('代理余额不足'));
                            }

                            //减少代理余额
                            $new_money = bcsub($agent['money'],$params['amount'],2);
                            $updateagent = Db::name('agent')->where(['id'=>$agent['id'],'last_money_time'=>$agent['last_money_time']])->update(['money'=>$new_money,'last_money_time'=>time()]);

                            if(!$params['remark']){
                                $params['remark'] = '手动减少余额';
                            }
                            $params['before_amount'] = $agent['money'];
                            $params['after_amount'] = $new_money;
                            break;

                        case 1:
                            //增加代理余额
                            $new_money = bcadd($agent['money'],$params['amount'],2);
                            $updateagent = Db::name('agent')->where(['id'=>$agent['id'],'last_money_time'=>$agent['last_money_time']])->update(['money'=>$new_money,'last_money_time'=>time()]);

                            if(!$params['remark']){
                                $params['remark'] = '手动添加余额';
                            }

                            $params['before_amount'] = $agent['money'];
                            $params['after_amount'] = $new_money;
                            break;

                        case 2:
                            //增加冻结金额减少余额
                            if($agent['money'] < $params['amount']){
                                $this->error(__('商户余额不足'));
                            }
                            $new_money = bcsub($agent['money'],$params['amount'],2);
                            $updateagent = Db::name('agent')->where(['id'=>$agent['id'],'last_money_time'=>$agent['last_money_time']])->update(['money'=>$new_money,'last_money_time'=>time(),'block_money'=>$params['amount']]);

                            if(!$params['remark']){
                                $params['remark'] = '冻结余额';
                            }
                            $params['before_amount'] = $agent['money'];
                            $params['after_amount'] = $new_money;
                            $params['type'] = 0;
                            break;

                        case 3:
                            //减少冻结金额归到余额
                            if($agent['block_money'] < $params['amount']){
                                $this->error(__('商户冻结余额不足'));
                            }
                            $newBlockMoney = bcsub($agent['block_money'],$params['amount'],2);
                            //修改商户余额
                            $new_money = bcadd($agent['money'],$params['amount'],2);
                            $updateagent = Db::name('agent')->where(['id'=>$agent['id'],'last_money_time'=>$agent['last_money_time']])->update(['money'=>$new_money,'last_money_time'=>time(),'block_money'=>$newBlockMoney]);

                            if(!$params['remark']){
                                $params['remark'] = '解冻余额';
                            }

                            $params['before_amount'] = $agent['money'];
                            $params['after_amount'] = $new_money;
                            $params['type'] = 1;
                            break;

                        default:
                            // code...
                            break;
                    }

                    $params['agent_id'] = $agent['id'];
                    $params['create_time'] = time();
                    $params['is_automatic'] = 1;
                    $params['out_trade_no'] = $params['out_trade_no'] ?? 11111111;


                    $result = $this->model->save($params);
                    Db::commit();
                } catch (ValidateException $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                } catch (PDOException $e) {
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
}
