<?php

namespace app\admin\controller\thirdacc;

use app\common\controller\Backend;
use think\exception\ValidateException;
use think\facade\Db;

/**
 * 通道管理
 *
 * @icon fa fa-circle-o
 */
class Acc extends Backend
{
    
    /**
     * Acc模型对象
     * @var \app\admin\model\thirdacc\Acc
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\thirdacc\Acc;
        $this->view->assign("statusList", $this->model->getStatusList());
    }
    
    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */


    /**
     * 添加
     */
    public function add()
    {
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
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name     = str_replace('\\model\\', '\\validate\\', get_class($this->model));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name.'.add' : $name) : $this->modelValidate;
                        validate($validate)->scene($this->modelSceneValidate ? 'edit' : $name)->check($params);
                    }
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

        return $this->view->fetch();
    }
    
    /**
     * 编辑
     */
    public function edit($ids = null)
    {
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
            $re =$this->model->where('id',$ids)->update(['status'=>$status]);

            if($re){
                $this->success("切换成功");
            }

            $this->error("切换失败");
        }

        $this->error("参数缺少");
    }
    
    //同步代理通道
    public function syncagent($ids = ''){
        if($ids){
            $row = $this->model->get($ids);
            $re  = false;
            $agentList = Db::name('agent')->where('status','normal')->select()->toArray();
            
            foreach ($agentList as $k => $v){
                
                $find = Db::name('agent_acc')->where(['agent_id'=>$v['id'], 'acc_code'=>$row['code']])->find();
                if($find){
                    continue;
                }
                $data = [
                    'agent_id'    => $v['id'],
                    'acc_id'      => $ids,
                    'acc_code'    => $row['code'],
                    'status'      => 1,
                    'create_time' => time(),
                    'rate'        => 0,
                ];
                $re = Db::name('agent_acc')->insert($data);
                
            }
            
            if($re){
                $this->success("同步成功");
            }

            $this->error("同步失败");
        }

        $this->error("参数缺少");
    }
    
    //同步代理通道
    public function syncuseracc($ids = ''){
        if($ids){
            
            $agentAcc         = Db::name('agent_acc')->find($ids);
            $re1              = false;
            $re2              = false;
            $insert_data_user = [];
            $insert_data_mer  = [];
            $userList         = Db::name('user')->where('agent_id',$agentAcc['agent_id'])->select()->toArray();
            $merList          = Db::name('merchant')->where('agent_id',$agentAcc['agent_id'])->select()->toArray();
            
            foreach ($userList as $k => $v){
                
                $find = Db::name('user_acc')->where(['user_id'=>$v['id'], 'acc_code'=>$agentAcc['acc_code']])->find();
                
                if ($find){
                    continue;
                }
    
                $insert_data_user[] = [
                    'agent_id'    => $agentAcc['agent_id'],
                    'user_id'     => $v['id'],
                    'acc_id'      => $agentAcc['acc_id'],
                    'acc_code'    => $agentAcc['acc_code'],
                    'status'      => $agentAcc['status'],
                    'create_time' => time(),
                ];
                
                
            }
            
            foreach ($merList as $k => $v){
                
                $find = Db::name('mer_acc')->where(['mer_id'=>$v['id'], 'acc_code'=>$agentAcc['acc_code']])->find();
                
                if ($find){
                    continue;
                }
    
                $insert_data_mer[] = [
                    'agent_id'    => $agentAcc['agent_id'],
                    'mer_id'      => $v['id'],
                    'acc_id'      => $agentAcc['acc_id'],
                    'acc_code'    => $agentAcc['acc_code'],
                    'status'      => $agentAcc['status'],
                    'create_time' => time(),
                ];
                
                
            }
            
            
            if (!empty($insert_data_user)){
                $re1 = Db::name('user_acc')->insertAll($insert_data_user);
            }
            
            if (!empty($insert_data_mer)){
                $re2 = Db::name('mer_acc')->insertAll($insert_data_mer);
            }
            
            if($re1 || $re2){
                $this->success("同步成功");
            }
            
            if(empty($insert_data_user) && empty($insert_data_mer)){
                $this->error("码商和商户无需同步");
            }
            
            $this->error("同步失败");
        }
        
        $this->error("参数缺少");
    }
    
    /**
     * 删除
     */
    public function del($ids = '')
    {
        if ($ids) {
            $pk       = $this->model->getPk();
            $adminIds = $this->getDataLimitAdminIds();
            if (is_array($adminIds)) {
                $this->model->where($this->dataLimitField, 'in', $adminIds);
            }
            $list = $this->model->where($pk, 'in', $ids)->select();

            $count = 0;
            Db::startTrans();

            try {
                foreach ($list as $k => $v) {
                    $count += $v->delete();
                    Db::name('agent_acc')->where('acc_code',$v['code'])->delete();
                    Db::name('mer_acc')->where('acc_code',$v['code'])->delete();
                    Db::name('user_acc')->where('acc_code',$v['code'])->delete();
                }
                Db::commit();
            } catch (\PDOException $e) {
                Db::rollback();
                $this->error($e->getMessage());
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            if ($count) {
                $this->success();
            } else {
                $this->error(__('No rows were deleted'));
            }
        }
        $this->error(__('Parameter %s can not be empty', 'ids'));
    }
}
