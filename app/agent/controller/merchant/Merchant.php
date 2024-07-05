<?php

namespace app\agent\controller\merchant;

use app\common\controller\AgentBackend;
use app\common\library\GoogleAuthenticator;
use app\common\library\Utils;
use app\merchant\model\AuthGroupAccess;
use fast\Random;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Validate;
use think\facade\Cache;
use think\facade\Config;

/**
 * 商户管理
 *
 * @icon fa fa-circle-o
 */
class Merchant extends AgentBackend
{
    
    /**
     * Merchant模型对象
     * @var \app\agent\model\merchant\Merchant
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\merchant\Merchant;
        $this->view->assign("statusList", $this->model->getStatusList());
        $this->view->assign("isFcList", $this->model->getIsFcList());
        $this->view->assign("diyRateList", $this->model->getDiyRateList());
        $this->view->assign("callbackStatusList", $this->model->getCallBackList());
        $this->view->assign("secretTypeList", $this->model->getSecretTypeList());
        $this->view->assign("isThirdDfList", $this->model->getIsThirdDfList());
        $this->view->assign("isThirdPayList", $this->model->getIsThirdPayList());

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
                    //->withJoin(['agent'])
                    ->where(['agent_id' => $this->auth->id])
                    ->where($where)
                    ->order($sort, $order)
                    ->count();

            $list = $this->model
                    //->withJoin(['agent'])
                    ->where(['agent_id' => $this->auth->id])
                    ->where($where)
                    ->order($sort, $order)
                    ->limit($offset, $limit)
                    ->select();

            foreach ($list as $row) {
                
                $row->visible(['id','agent_id','number','username','nickname','rate','add_money','money','logintime','loginip','joinip','jointime','status','block_money','last_money_time','sub_order_status']);
                //$row->getRelation('agent')->visible(['username']);
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

                    $group_id               = 1;
                    $params['agent_id']     = $this->auth->id;
                    $params['group_id']     = $group_id;
                    $params['salt']         = Random::alnum();
                    $params['password']     = md5(md5($params['password']) . $params['salt']);
                    $params['pay_password'] = md5($params['pay_password']);
                    $google                 = new GoogleAuthenticator();
                    $params['google_code']  = $google->createSecret();
                    $params['avatar']       = '/assets/img/avatar.png'; //设置默认头像。
                    $params['joinip']       = request()->ip();
                    $params['jointime']     = time();
                    $params['createtime']   = time();
                    $params['number']       = Utils::buildNumber('M');
                    $params['secret_key']   = md5($params['password'] . time());


                    $result = $this->model->save($params);


                    if ($result === false) {
                        $this->error($this->model->getError());
                    }

                    //同步添加代理下的通道
                    Utils::syncMerAcc($params['agent_id'], $this->model->id);

                    //过滤不允许的组别,避免越权
                    $dataset = [];
                    $dataset[] = ['uid' => $this->model->id, 'group_id' => $group_id];
                    $merchantAuthGroupAccess = new AuthGroupAccess();
                    $merchantAuthGroupAccess->saveAll($dataset);

                    //代理下的所有码商同步绑定该商户
                    Utils::syncMerUserByAddMerchant($params['agent_id'], $this->model->id);
                    
                    //同步到机器人系统
                    Utils::syncTgBot($this->auth->username, $params['number']);
                    
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
        
        if ($this->request->isPost()) {
            $params = $this->request->post('row/a');
            if ($params) {
                $params = $this->preExcludeFields($params);
                $result = false;
                Db::startTrans();

                try {
                    
                    if (!empty((trim($params['password'])))) {
                        if (!Validate::is($params['password'], '\S{6,16}')) {
                            $this->error(__("Please input correct password"));
                        }
                        $params['salt'] = Random::alnum();
                        $params['password'] = md5(md5($params['password']) . $params['salt']);
                    } else {
                        unset($params['password']);
                    }
                    
                    if (!empty((trim($params['pay_password'])))) {
                        $params['pay_password'] = md5($params['pay_password']);
                    } else {
                        unset($params['pay_password']);
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
        
        $this->view->assign('row', $row);

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

                Utils::dealDataByUserDel($ids, 'merchant');

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

    /**
     * 单笔
     */
    public function rate($ids = null)
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
            $params = $this->request->post("row/a");
            if ($params) {
                $params = $this->preExcludeFields($params);
                $result = false;
                Db::startTrans();
                try {
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : $name) : $this->modelValidate;
                        $row->validateFailException(true)->validate($validate);
                    }

                    $result = $row->save($params);
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
                    $this->error(__('No rows were updated'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $this->view->assign("row", $row);
        return $this->view->fetch();
    }

    //重置谷歌密钥
    public function resetGoogleKey($ids = null){

        $row = $this->model->get($ids);
        if (!$row) {
            $this->error('商户不存在');
        }

        $google = new GoogleAuthenticator();
        $google_code = $google->createSecret();

        $data['google_code'] = $google_code;

        $result = $row->save($data);

        if($result !== false){
            $this->success('重置谷歌密钥成功');
        }else{
            $this->error('重置失败');
        }
    }

    //重置密钥
    public function resetKey($ids = null){

        $row = $this->model->get($ids);
        if (!$row) {
            $this->error('商户不存在');
        }

        $data['secret_key'] = md5(mt_rand(1000000,9999999).time());

        $result = $row->save($data);

        if($result !== false){
            $this->success('重置密钥成功');
        }else{
            $this->error('重置失败');
        }
    }
    
    //切换是否接单
    public function change($ids = ''){
        if($ids){
            $row = $this->model->get($ids);
            $sub_order_status = $row['sub_order_status'] == 1 ? 0 : 1;
            $re = $this->model->where('id',$ids)->update(['sub_order_status'=>$sub_order_status]);
            if($re){
                $this->success("切换成功");
            }

            $this->error("切换失败");
        }

        $this->error("参数缺少");

    }

}