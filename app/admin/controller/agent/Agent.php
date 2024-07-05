<?php

namespace app\admin\controller\agent;

use app\admin\model\thirdacc\Agentacc;
use app\agent\model\AuthGroupAccess;
use app\common\controller\Backend;
use app\common\library\GoogleAuthenticator;
use app\common\library\Utils;
use fast\Random;
use think\exception\ValidateException;
use think\facade\Config;
use think\facade\Db;
use think\Validate;

/**
 * 会员管理
 *
 * @icon fa fa-circle-o
 */
class Agent extends Backend
{
    
    /**
     * Agent模型对象
     * @var \app\admin\model\agent\Agent
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\agent\Agent;
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
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $total = $this->model
                    
                    ->where($where)
                    ->order($sort, $order)
                    ->count();

            $list = $this->model
                    
                    ->where($where)
                    ->order($sort, $order)
                    ->limit($offset, $limit)
                    ->select();

            foreach ($list as $row) {
                $row->visible(['id','number','username','nickname','rate','money','logintime','loginip','joinip','jointime','status','last_money_time','block_money','sub_order_rate']);
                
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

                    $find = $this->model->where('username',$params['username'])->find();
                    if($find){
                        $this->error('该账号已存在');
                    }

                    $group_id              = 1;
                    $params['group_id']    = $group_id;
                    $params['salt']        = Random::alnum();
                    $params['password']    = md5(md5($params['password']) . $params['salt']);
                    /*$google                = new GoogleAuthenticator();
                    $params['google_code'] = $google->createSecret();*/
                    $params['avatar']      = '/assets/img/avatar.png'; //设置默认头像。
                    $params['joinip']      = request()->ip();
                    $params['jointime']    = time();
                    $params['createtime']  = time();
                    $params['number']      = Utils::buildNumber('A');


                    $result = $this->model->save($params);

                    if ($result === false) {
                        $this->error($this->model->getError());
                    }

                    //过滤不允许的组别,避免越权
                    $dataset = [];
                    $dataset[] = ['uid' => $this->model->id, 'group_id' => $group_id];
                    $agentAuthGroupAccess = new AuthGroupAccess();
                    $agentAuthGroupAccess->saveAll($dataset);

                    //同步添加系统商开启的通道给这个代理
                    $accList  = Db::name('acc')->where('status', 1)->select()->toArray();
                    foreach ($accList as $k => $v){
                        $insertData = [
                            'agent_id'    => (int)$this->model->id,
                            'acc_id'      => $v['id'],
                            'acc_code'    => $v['code'],
                            'status'      => 1,
                            'create_time' => time(),
                        ];
                        Agentacc::create($insertData);
                    }

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

                    if ($params['password']) {
                        if (!\think\facade\Validate::is($params['password'], '\S{6,16}')) {
                            $this->error(__("Please input correct password"));
                        }
                        $params['salt'] = Random::alnum();
                        $params['password'] = md5(md5($params['password']) . $params['salt']);
                    } else {
                        unset($params['password']);
                    }

                    if (isset($params['pay_password'])) {
                        $params['pay_password'] = md5($params['pay_password']);
                    }
                    
                    unset($params['username']);
                    unset($params['money']);

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

        $googleAuthenticator = new GoogleAuthenticator();
        $qrcode = $googleAuthenticator->getQRCodeGoogleUrl($row['username'],$row['google_code']);
        $this->assignconfig('google_code_url', $qrcode);

        $this->view->assign("row", $row);
        return $this->view->fetch();
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
                }
                
                Db::name('agent_auth_group_access')->where('uid', 'in', $ids)->delete();
                Db::name('agent_acc')->where('agent_id', 'in', $ids)->delete();
                Db::name('user_acc')->where('agent_id', 'in', $ids)->delete();
                Db::name('user')->where('agent_id', 'in', $ids)->delete();
                Db::name('group_qrcode')->where('agent_id', 'in', $ids)->delete();
                Db::name('mer_user')->where('agent_id', 'in', $ids)->delete();
                Db::name('merchant')->where('agent_id', 'in', $ids)->delete();
                Db::name('mer_acc')->where('agent_id', 'in', $ids)->delete();

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

    //重置谷歌密钥
    public function resetGoogleKey($ids = null){

        $row = $this->model->get($ids);
        if (!$row) {
            $this->error('账号不存在');
        }

        $google = new GoogleAuthenticator();
        $google_code = $google->createSecret();

        $data['google_code'] = $google_code;

        $result = $row->save($data);

        if($result !== false){
            $this->success('重置谷歌密钥成功');
        }

        $this->error('重置失败');
    }
    
    //切换启用状态
    public function change($ids = ''){
        if($ids){
            $row        = $this->model->get($ids);
            $status     = $row['sub_order_rate'] == '1' ? '0' : '1';
            $updateData = ['sub_order_rate'=>$status,'updatetime'=>time()];
            
            $re =$this->model->where('id',$ids)->update($updateData);

            if($re){
                $this->success("切换成功");
            }

            $this->error("切换失败");
        }

        $this->error("参数缺少");
    }
    
}