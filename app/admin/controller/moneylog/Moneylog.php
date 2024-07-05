<?php

namespace app\admin\controller\moneylog;

use app\common\controller\Backend;
use app\common\library\Utils;
use think\facade\Config;
use think\facade\Db;

/**
 * 商户余额记录管理
 *
 * @icon fa fa-circle-o
 */
class Moneylog extends Backend
{
    
    /**
     * Moneylog模型对象
     * @var \app\admin\model\moneylog\Moneylog
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\moneylog\Moneylog;
        $this->view->assign("typeList", $this->model->getTypeList());
        $this->view->assign("isAutomaticList", $this->model->getIsAutomaticList());
        $this->view->assign("isRechargeList", $this->model->getIsRechargeList());
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
                    ->withJoin(['merchant'])
                    ->where($where)
                    ->order($sort, $order)
                    ->count();

            $list = $this->model
                    ->withJoin(['merchant'])
                    ->where($where)
                    ->order($sort, $order)
                    ->limit($offset, $limit)
                    ->select();

            foreach ($list as $row) {
                $row->visible(['id','mer_id','agent_id','out_trade_no','type','amount','before_amount','after_amount','create_time','remark','ip_address','is_automatic','fees','is_recharge']);
                $row->getRelation('merchant')->visible(['username']);
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


                $result = false;

                Db::startTrans();
                try {
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.add' : $name) : $this->modelValidate;
                        $this->model->validateFailException(true)->validate($validate);
                    }

                    //找出商户
                    $merchant = Db::name('merchant')->where('id', $params['mer_id'])->find();

                    if ($merchant['status'] == 'hidden') {
                        $this->error(__('账号已被禁用'));
                    }

                    //0支出 1增加 2冻结 3解冻
                    switch ($params['type']) {
                        case 0:
                            if ($merchant['money'] < $params['amount']) {
                                $this->error(__('商户余额不足'));
                            }

                            //减少余额
                            $new_money = bcsub($merchant['money'], $params['amount'], 2);
                            Db::name('merchant')->where(['id' => $merchant['id'], 'last_money_time' => $merchant['last_money_time']])->update(['money' => $new_money, 'last_money_time' => time()]);

                            if (!$params['remark']) {
                                $params['remark'] = '手动减少余额';
                            }
                            $params['before_amount'] = $merchant['money'];
                            $params['after_amount'] = $new_money;
                            break;

                        case 1:
                            //增加余额
                            //判断是否余额记录上分扣手续费
                            if (Config::get('site.moneylog_fees')) {
                                $fees = bcmul($params['amount'], $merchant['rate'], 2);//手续费
                                $params['fees'] = $fees;
                                $params['is_recharge'] = 1;
                                $real_money = bcsub($params['amount'], $fees, 2);//实际到账金额
                                $out_trade_no = Utils::buildOutTradeNo();
                                $params['out_trade_no'] = $out_trade_no;
                                //加入充值表
                                Db::name('applys')->insert([
                                    'user_id'       => 1,
                                    'mer_id'        => $merchant['id'],
                                    'agent_id'      => $merchant['agent_id'],
                                    'yhk_id'        => 1,
                                    'out_trade_no'  => $out_trade_no,
                                    'amount'        => $params['amount'],
                                    'status'        => 1,
                                    'createtime'    => time(),
                                    'updatetime'    => time(),
                                    'deal_username' => $this->auth->username,
                                    'deal_ip'       => request()->ip(),
                                    'fees'          => $fees,
                                    'real_money'    => $real_money,
                                ]);

                            } else {
                                $real_money = $params['amount'];
                            }

                            $params['amount'] = $real_money;

                            $new_money = bcadd($merchant['money'], $real_money, 2);
                            Db::name('merchant')->where(['id' => $merchant['id'], 'last_money_time' => $merchant['last_money_time']])->update(['money' => $new_money, 'last_money_time' => time()]);

                            if (!$params['remark']) {
                                $params['remark'] = '手动添加余额';
                            }

                            $params['before_amount'] = $merchant['money'];
                            $params['after_amount'] = $new_money;
                            break;

                        case 2:
                            //增加冻结金额减少余额
                            if ($merchant['money'] < $params['amount']) {
                                $this->error(__('商户余额不足'));
                            }
                            $new_money = bcsub($merchant['money'], $params['amount'], 2);
                            Db::name('merchant')->where(['id' => $merchant['id'], 'last_money_time' => $merchant['last_money_time']])->update(['money' => $new_money, 'last_money_time' => time(), 'block_money' => $params['amount']]);

                            if (!$params['remark']) {
                                $params['remark'] = '冻结余额';
                            }
                            $params['before_amount'] = $merchant['money'];
                            $params['after_amount'] = $new_money;

                            break;

                        case 3:
                            //减少冻结金额归到余额
                            if ($merchant['block_money'] < $params['amount']) {
                                $this->error(__('商户冻结余额不足'));
                            }
                            $newBlockMoney = bcsub($merchant['block_money'], $params['amount'], 2);
                            //修改商户余额
                            $new_money = bcadd($merchant['money'], $params['amount'], 2);
                            Db::name('merchant')->where(['id' => $merchant['id'], 'last_money_time' => $merchant['last_money_time']])->update(['money' => $new_money, 'last_money_time' => time(), 'block_money' => $newBlockMoney]);

                            if (!$params['remark']) {
                                $params['remark'] = '解冻余额';
                            }

                            $params['before_amount'] = $merchant['money'];
                            $params['after_amount'] = $new_money;

                            break;

                        default:
                            $this->error(__('类型错误'));
                            break;
                    }

                    $params['agent_id']     = $merchant['agent_id'];
                    $params['mer_id']       = $merchant['id'];
                    $params['create_time']  = time();
                    $params['is_automatic'] = 1;
                    $params['fees']         = 0;
                    $params['out_trade_no'] = 11111111;
                    $params['trade_no']     = 11111111;

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
