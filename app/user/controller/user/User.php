<?php

namespace app\user\controller\user;

use app\common\controller\UserBackend;
use app\user\model\user\Userrelation;
use fast\Random;
use think\Exception;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Validate;
use app\user\model\AuthGroupAccess;
use app\common\library\Utils;
use app\common\library\MoneyLog;
use think\facade\Config;
use app\common\library\GoogleAuthenticator;

/**
 * 会员管理
 *
 * @icon fa fa-user
 */
class User extends UserBackend
{
    
    /**
     * User模型对象
     * @var \app\user\model\user\User
     */
    protected $model = null;
    
    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\user\model\user\User;
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
            
            //获取我的下级码商
            $userIds = Utils::getMySecUser($this->auth->id);

            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $total = $this->model
                    //->withJoin()
                    ->where($where)
                    ->where('id', 'in', $userIds)
                    ->order($sort, $order)
                    ->count();

            $list = $this->model
                    //->withJoin([])
                    ->where($where)
                    ->where('id', 'in', $userIds)
                    ->order($sort, $order)
                    ->limit($offset, $limit)
                    ->select();

            foreach ($list as $row) {
                $row->visible(['id','number','username','nickname','money','logintime','loginip','createtime','status','is_receive','rate','parent_id','parent_name','level']);

                $row['parent_name'] = $this->model->where('id', $row['parent_id'])->value('username');
                $userRelation = Userrelation::where('user_id', $row['id'])->find();
                
                $preg = "/\d+/";
        		preg_match_all($preg,$userRelation['id_path'],$id_arr);
                $row['level']       = count($id_arr[0]);
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
                    
                    $finduser = $this->model->where('username', $params['username'])->find();
                    if($finduser){
                        $this->error('该账号已存在');
                    }

                    $group_id                = 1;
                    $params['group_id']      = 1;
                    $params['agent_id']      = $this->auth->agent_id;
                    $params['salt']          = Random::alnum();
                    $params['password']      = md5(md5($params['password']) . $params['salt']);
                    $params['avatar']        = '/assets/img/avatar.png'; //设置新管理员默认头像。
                    $params['joinip']        = request()->ip();
                    $params['jointime']      = time();
                    $params['createtime']    = time();
                    $params['number']        = Utils::buildnumber('U');
                    //$google                  = new GoogleAuthenticator();
                    //$params['google_code']   = $google->createSecret();
                    $params['parent_id']     = $this->auth->id;
                    $params['is_commission'] = 1;//上级开下级号 默认开启返佣

                    $result = $this->model->save($params);
                    
                    //同步用户关系
                    $buildPath = Utils::buildPath($this->model->id, $this->auth->id);
                    $userRelation['user_id']        = $this->model->id;
                    $userRelation['parent_id']      = $this->auth->id;
                    $userRelation['id_path']        = $buildPath['id_path'];
                    $userRelation['parent_id_path'] = $buildPath['parent_id_path'];
                    $userRelation['level']          = 1;
                    $UserrelationModel = new Userrelation();
                    $UserrelationModel->save($userRelation);
                    
                    //过滤不允许的组别,避免越权
                    $dataset = [];
                    $dataset[] = ['uid' => $this->model->id, 'group_id' => $group_id];
                    $userAuthGroupAccess = new AuthGroupAccess();
                    $userAuthGroupAccess->saveAll($dataset);

                    //同步添加这个码商下的通道
                    Utils::syncUserAcc($this->auth->id, $this->model->id, false);

                    //同步绑定代理下的所有商户
                    Utils::syncMerUserByAddUser($params['agent_id'], $this->model->id);
                    
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
     * 编辑.
     */
    public function edit($ids = null)
    {
        $row = $this->model->find($ids);
        if (! $row) {
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
                if ($params['password']) {
                    
                    if(!Validate::is($params['password'], '\S{6,16}')){
                        $this->error(__("Please input correct password"));
                    }
                    
                    $params['salt'] = Random::alnum();
                    $params['password'] = md5(md5($params['password']).$params['salt']);
                } else {
                    unset($params['password']);
                }
                

                $result = $row->save($params);
                if ($result === false) {
                    $this->error($row->getError());
                }

                $this->success();
            }
            $this->error('参数缺少');
        }


        /*$googleAuthenticator = new GoogleAuthenticator();
        $qrcode = $googleAuthenticator->getQRCodeGoogleUrl($row['username'],$row['google_code']);
        $this->assignconfig('google_code_url', $qrcode);*/

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

                Db::name('user_auth_group_access')->where('uid', 'in', $ids)->delete();

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

    //切换是否接单
    public function change($ids = ''){
        if($ids){
            $row = $this->model->get($ids);
            $is_receive = $row['is_receive'] == 1 ? 2 : 1;
            $re = $this->model->where('id',$ids)->update(['is_receive'=>$is_receive]);
            if($re){
                $this->success("切换成功");
            }else{
                $this->error("切换失败");
            }
        }

        $this->error("参数缺少");

    }
    
    //更改用户接单状态
    public function changereceive(){
        
        $user = Db::name('user')->where('id',$this->auth->id)->find();
        if($user){

            $is_receive = $user['is_receive'] == 1 ? 2 : 1;

            $re = Db::name('user')->where('id', $user['id'])->update(['is_receive' => $is_receive]);

            if($re){
                $this->success('修改成功');
            }else{
                $this->error(__('修改失败，请联系管理员'));
            }
        }else{
            $this->error(__('非法操作'));
        }

    }
    
}
