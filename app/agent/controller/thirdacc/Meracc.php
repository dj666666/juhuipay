<?php

namespace app\agent\controller\thirdacc;

use app\common\controller\AgentBackend;
use app\common\library\Utils;
use think\Exception;
use think\exception\ValidateException;
use think\facade\Db;

/**
 * 码商通道管理
 *
 * @icon fa fa-circle-o
 */
class Meracc extends AgentBackend
{

    /**
     * Useracc模型对象
     * @var \app\admin\model\thirdacc\Useracc
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\thirdacc\Meracc;
        $this->view->assign("statusList", $this->model->getStatusList());
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
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $total = $this->model
                ->withJoin(['acc'])
                ->where('meracc.agent_id', $this->auth->id)
                ->where('mer_id', $ids)
                ->where($where)
                ->order($sort, $order)
                ->count();

            $list = $this->model
                ->withJoin(['acc'])
                ->where('meracc.agent_id', $this->auth->id)
                ->where('mer_id', $ids)
                ->where($where)
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();

            foreach ($list as $row) {
                if (!empty($row['merchant'])){
                    $row->getRelation('merchant')->visible(['username']);
                }
                if (!empty($row['acc'])){
                    $row->getRelation('acc')->visible(['name']);
                }

            }
            $result = array("total" => $total, "rows" => $list);

            return json($result);
        }

        $this->assignconfig('user_id', $ids);
        $finduser = Db::name('merchant')->where('id', $ids)->find();
        $this->assign('username', $finduser['username']);

        return $this->view->fetch();
    }

    /**
     * 添加
     */
    public function add() {

        if ($this->request->isPost()) {
            $params = $this->request->post('row/a');
            if ($params) {
                $params = $this->preExcludeFields($params);

                if ($this->dataLimit && $this->dataLimitFieldAutoFill) {
                    $params[$this->dataLimitField] = $this->auth->id;
                }

                $result = false;
                Db::startTrans();

                try {
                    $find = $this->model->where(['mer_id'=>$params['mer_id'], 'acc_id'=>$params['acc_id']])->find();
                    if($find){
                        $this->error('该通道已添加');
                    }

                    $params['agent_id']    = $this->auth->id;
                    $params['acc_code']    = $params['acc_id'];
                    $params['acc_id']    = \app\admin\model\thirdacc\Acc::where('code',$params['acc_id'])->value('id');
                    $params['create_time'] = time();

                    $result = $this->model->save($params);
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
                    $this->error(__('No rows were inserted'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }

        $this->view->assign('row', ['agent_id'=>$this->auth->id]);

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
                    $result = $row->save($params);
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


    //切换启用状态
    public function change($ids = ''){
        if($ids){
            $row = $this->model->get($ids);
            $status = $row['status'] == 1 ? 0 : 1;
            $re = $this->model->where('id',$ids)->update(['status'=>$status]);
            if($re){
                $this->success("切换成功");
            }
            $this->error("切换失败");
        }

        $this->error("参数缺少");

    }


}
