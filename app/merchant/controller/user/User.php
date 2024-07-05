<?php

namespace app\merchant\controller\user;

use app\common\controller\MerchantBackend;
use fast\Random;
use think\facade\Db;
use think\facade\Validate;
use app\user\model\AuthGroupAccess;

/**
 * 会员管理
 *
 * @icon fa fa-user
 */
class User extends MerchantBackend
{
    
    /**
     * User模型对象
     * @var \app\merchant\model\user\User
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\merchant\model\user\User;
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
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();
            $total = $this->model
                    ->withJoin(['group','agent'])
                    ->where($where)
                    ->order($sort, $order)
                    ->count();

            $list = $this->model
                    ->withJoin(['group','agent'])
                    ->where($where)
                    ->order($sort, $order)
                    ->limit($offset, $limit)
                    ->select();

            foreach ($list as $row) {
                $row->visible(['id','number','username','nickname','money','logintime','loginip','createtime','status','is_receive','rate']);
                $row->getRelation('agent')->visible(['username']);
                $row->getRelation('group')->visible(['name']);
            }
            $result = array("total" => $total, "rows" => $list);

            return json($result);
        }
        return $this->view->fetch();
    }

    /**
     * 添加.
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $this->token();
            $params = $this->request->post('row/a');
            if ($params) {
                if(!Validate::is($params['password'], '\S{6,16}')){
                    $this->error(__("Please input correct password"));
                }
                $params['salt'] = Random::alnum();
                $params['password'] = md5(md5($params['password']).$params['salt']);
                $params['avatar'] = '/assets/img/avatar.png'; //设置新管理员默认头像。
                try {
                    validate('User.add')->check($params);
                } catch (\Exception $e) {
                    $this->error($e->getMessage());
                }
                $result = $this->model->save($params);
                if ($result === false) {
                    $this->error($this->model->getError());
                }
                $group = $this->request->post('group/a');

                //过滤不允许的组别,避免越权
                $dataset = [];
                $dataset[] = ['uid' => $this->model->id, 'group_id' => 1];
                $model = new AuthGroupAccess();
                $model->saveAll($dataset);
                $this->success();
            }
            $this->error();
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
        if (!in_array($row->id, $this->childrenAdminIds)) {
            $this->error(__('You have no permission'));
        }
        if ($this->request->isPost()) {
            $this->token();
            $params = $this->request->post('row/a');
            if ($params) {
                if ($params['password']) {
                    if(!Validate::is($params['password'], '\S{6,16}')){
                        $this->error(__("Please input correct password"));
                    }
                    $params['salt'] = Random::alnum();
                    $params['password'] = md5(md5($params['password']).$params['salt']);
                } else {
                    unset($params['password'], $params['salt']);
                }
                //这里需要针对username和email做唯一验证
                $adminValidate = validate('Admin.edit', [], false, false);
                $adminValidate->rule([
                    'username' => 'require|regex:\w{3,12}|unique:admin,username,' . $row->id,
                    'email'    => 'require|email|unique:admin,email,' . $row->id,
                    'password' => 'regex:\S{32}',
                ]);
                $rs = $adminValidate->check($params);
                if (! $rs) {
                    $this->error($adminValidate->getError());
                }

                $result = $row->save($params);
                if ($result === false) {
                    $this->error($row->getError());
                }

                // 先移除所有权限
                AuthGroupAccess::where('uid', $row->id)->delete();

                $group = $this->request->post('group/a');

                // 过滤不允许的组别,避免越权
                $group = array_intersect($this->childrenGroupIds, $group);

                $dataset = [];
                foreach ($group as $value) {
                    $dataset[] = ['uid' => $row->id, 'group_id' => $value];
                }
                //AuthGroupAccess::saveAll($dataset);
                $model = new AuthGroupAccess();
                $model->saveAll($dataset);
                $this->success();
            }
            $this->error();
        }
        $grouplist = $this->auth->getGroups($row['id']);
        $groupids = [];
        foreach ($grouplist as $k => $v) {
            $groupids[] = $v['id'];
        }
        $this->view->assign('row', $row);
        $this->view->assign('groupids', $groupids);

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

}
