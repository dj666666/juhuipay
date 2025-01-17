<?php

namespace app\admin\controller\auth;

use fast\Tree;
use fast\Random;
use app\admin\model\AuthGroup;
use app\common\controller\Backend;
use app\admin\model\AuthGroupAccess;
use think\facade\Validate;

/**
 * 管理员管理.
 *
 * @icon fa fa-users
 * @remark 一个管理员可以有多个角色组,左侧的菜单根据管理员所拥有的权限进行生成
 */
class Admin extends Backend
{
    /**
     * @var \app\admin\model\Admin
     */
    protected $model = null;
    protected $childrenGroupIds = [];
    protected $childrenAdminIds = [];

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\Admin();

        $this->childrenAdminIds = $this->auth->getChildrenAdminIds(true);
        $this->childrenGroupIds = $this->auth->getChildrenGroupIds(true);

        $groupList = AuthGroup::where('id', 'in', $this->childrenGroupIds)->select()->toArray();

        Tree::instance()->init($groupList);
        $groupdata = [];
        if ($this->auth->isSuperAdmin()) {
            $result = Tree::instance()->getTreeList(Tree::instance()->getTreeArray(0));
            foreach ($result as $k => $v) {
                $groupdata[$v['id']] = $v['name'];
            }
        } else {
            $result = [];
            $groups = $this->auth->getGroups();
            foreach ($groups as $m => $n) {
                $childlist = Tree::instance()->getTreeList(Tree::instance()->getTreeArray($n['id']));
                $temp = [];
                foreach ($childlist as $k => $v) {
                    $temp[$v['id']] = $v['name'];
                }
                $result[__($n['name'])] = $temp;
            }
            $groupdata = $result;
        }

        $this->view->assign('groupdata', $groupdata);
        $this->assignconfig('admin', ['id' => $this->auth->id]);
    }

    /**
     * 查看.
     */
    public function index()
    {
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            $childrenGroupIds = $this->childrenGroupIds;
            $groupName = AuthGroup::where('id', 'in', $childrenGroupIds)
                ->column('name', 'id');
            $authGroupList = AuthGroupAccess::where('group_id', 'in', $childrenGroupIds)
                ->field('uid,group_id')
                ->select();

            $adminGroupName = [];
            foreach ($authGroupList as $k => $v) {
                if (isset($groupName[$v['group_id']])) {
                    $adminGroupName[$v['uid']][$v['group_id']] = $groupName[$v['group_id']];
                }
            }
            $groups = $this->auth->getGroups();
            foreach ($groups as $m => $n) {
                $adminGroupName[$this->auth->id][$n['id']] = $n['name'];
            }
            [$where, $sort, $order, $offset, $limit] = $this->buildparams();
            //halt($this->childrenAdminIds);
            $total = $this->model
                ->where($where)
                ->where('id', 'in', $this->childrenAdminIds)
                ->order($sort, $order)
                ->count();
            $list = $this->model
                ->where($where)
                ->where('id', 'in', $this->childrenAdminIds)
                ->hidden(['password', 'salt', 'token','google_code'])
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();
            foreach ($list as $k => &$v) {
                $groups = isset($adminGroupName[$v['id']]) ? $adminGroupName[$v['id']] : [];
                $v['groups'] = implode(',', array_keys($groups));
                $v['groups_text'] = implode(',', array_values($groups));
            }
            unset($v);
            $result = ['total' => $total, 'rows' => $list];

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
            $this->success('success');
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
                    validate('Admin.add')->check($params);
                } catch (\Exception $e) {
                    $this->error($e->getMessage());
                }
                $result = $this->model->save($params);
                if ($result === false) {
                    $this->error($this->model->getError());
                }
                $group = $this->request->post('group/a');

                //过滤不允许的组别,避免越权
                $group = array_intersect($this->childrenGroupIds, $group);
                $dataset = [];
                foreach ($group as $value) {
                    $dataset[] = ['uid' => $this->model->id, 'group_id' => $value];
                }
                //AuthGroupAccess::saveAll($dataset);
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
     * 删除.
     */
    public function del($ids = '')
    {
        if ($ids) {
            $ids = array_intersect($this->childrenAdminIds, array_filter(explode(',', $ids)));
            // 避免越权删除管理员
            $childrenGroupIds = $this->childrenGroupIds;
            $adminList = $this->model->where('id', 'in', $ids)->where('id', 'in',
                function ($query) use ($childrenGroupIds) {
                    $query->name('auth_group_access')->where('group_id', 'in', $childrenGroupIds)->field('uid');
                })->select();
            if ($adminList) {
                $deleteIds = [];
                foreach ($adminList as $k => $v) {
                    $deleteIds[] = $v->id;
                }
                $deleteIds = array_values(array_diff($deleteIds, [$this->auth->id]));
                if ($deleteIds) {
                    $this->model->destroy($deleteIds);
                    AuthGroupAccess::where('uid', 'in', $deleteIds)->delete();
                    $this->success();
                }
            }
        }
        $this->error(__('You have no permission'));
    }

    /**
     * 批量更新.
     *
     * @internal
     */
    public function multi($ids = '')
    {
        // 管理员禁止批量操作
        $this->error();
    }

    /**
     * 下拉搜索.
     */
    public function selectpage()
    {
        $this->dataLimit = 'auth';
        $this->dataLimitField = 'id';

        return parent::selectpage();
    }
}
