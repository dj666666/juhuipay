<?php

namespace app\user\controller\auth;

use fast\Tree;
use app\user\model\AuthRule;
use app\user\model\AuthGroup;
use app\common\controller\UserBackend;
use app\user\model\AuthGroupAccess;
use think\Exception;
use think\facade\Db;

/**
 * 角色组.
 *
 * @icon fa fa-group
 * @remark 角色组可以有多个,角色有上下级层级关系,如果子角色有角色组和管理员的权限则可以派生属于自己组别下级的角色组或管理员
 */
class Group extends UserBackend
{
    /**
     * @var \app\user\model\AuthGroup
     */
    protected $model = null;
    //当前登录管理员所有子组别
    protected $childrenGroupIds = [];
    //当前组别列表数据
    protected $groupdata = [];
    //无需要权限判断的方法
    protected $noNeedRight = ['roletree'];

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new AuthGroup();

        $this->childrenGroupIds = $this->auth->getChildrenGroupIds(true);

        $groupList = AuthGroup::where('id', 'in', $this->childrenGroupIds)->select()->toArray();

        Tree::instance()->init($groupList);
        $result = [];
        if ($this->auth->isSuperAdmin()) {
            $result = Tree::instance()->getTreeList(Tree::instance()->getTreeArray(0));
        } else {
            $groups = $this->auth->getGroups();
            foreach ($groups as $m => $n) {
                $result = array_merge($result,
                    Tree::instance()->getTreeList(Tree::instance()->getTreeArray($n['pid'])));
            }
        }
        $groupName = [];
        foreach ($result as $k => $v) {
            $groupName[$v['id']] = $v['name'];
        }

        $this->groupdata = $groupName;
        $this->assignconfig('admin', ['id' => $this->auth->id, 'group_ids' => $this->auth->getGroupIds()]);

        $this->view->assign('groupdata', $this->groupdata);
    }

    /**
     * 查看.
     */
    public function index()
    {
        if ($this->request->isAjax()) {
            $list = AuthGroup::select(array_keys($this->groupdata));
            $list = $list->toArray();
            $groupList = [];
            foreach ($list as $k => $v) {
                $groupList[$v['id']] = $v;
            }
            $list = [];
            foreach ($this->groupdata as $k => $v) {
                if (isset($groupList[$k])) {
                    $groupList[$k]['name'] = $v;
                    $list[] = $groupList[$k];
                }
            }
            $total = count($list);
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
            $this->token();
            $params = $this->request->post('row/a', [], 'strip_tags');
            $params['rules'] = explode(',', $params['rules']);
            if (! in_array($params['pid'], $this->childrenGroupIds)) {
                $this->error(__('The parent group exceeds permission limit'));
            }
            $parentmodel = AuthGroup::find($params['pid']);
            if (! $parentmodel) {
                $this->error(__('The parent group can not found'));
            }
            // 父级别的规则节点
            $parentrules = explode(',', $parentmodel->rules);
            // 当前组别的规则节点
            $currentrules = $this->auth->getRuleIds();
            $rules = $params['rules'];
            // 如果父组不是超级管理员则需要过滤规则节点,不能超过父组别的权限
            $rules = in_array('*', $parentrules) ? $rules : array_intersect($parentrules, $rules);
            // 如果当前组别不是超级管理员则需要过滤规则节点,不能超当前组别的权限
            $rules = in_array('*', $currentrules) ? $rules : array_intersect($currentrules, $rules);
            $params['rules'] = implode(',', $rules);
            if ($params) {
                $this->model->create($params);
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
        if (!in_array($ids, $this->childrenGroupIds)) {
            $this->error(__('You have no permission'));
        }
        $row = $this->model->find(['id' => $ids]);
        if (! $row) {
            $this->error(__('No Results were found'));
        }
        if ($this->request->isPost()) {
            $this->token();
            $params = $this->request->post('row/a', [], 'strip_tags');
            //父节点不能是非权限内节点
            if (! in_array($params['pid'], $this->childrenGroupIds)) {
                $this->error(__('The parent group exceeds permission limit'));
            }
            // 父节点不能是它自身的子节点或自己本身
            if (in_array($params['pid'], Tree::instance()->getChildrenIds($row->id,true))){
                $this->error(__('The parent group can not be its own child or itself'));
            }
            $params['rules'] = explode(',', $params['rules']);

            $parentmodel = AuthGroup::find($params['pid']);
            if (! $parentmodel) {
                $this->error(__('The parent group can not found'));
            }
            // 父级别的规则节点
            $parentrules = explode(',', $parentmodel->rules);
            // 当前组别的规则节点
            $currentrules = $this->auth->getRuleIds();
            $rules = $params['rules'];
            // 如果父组不是超级管理员则需要过滤规则节点,不能超过父组别的权限
            $rules = in_array('*', $parentrules) ? $rules : array_intersect($parentrules, $rules);
            // 如果当前组别不是超级管理员则需要过滤规则节点,不能超当前组别的权限
            $rules = in_array('*', $currentrules) ? $rules : array_intersect($currentrules, $rules);
            $params['rules'] = implode(',', $rules);
            if ($params) {
                Db::startTrans();
                try {
                    $row->save($params);
                    $children_auth_groups = AuthGroup::whereIn('id',implode(',',(Tree::instance()->getChildrenIds($row->id))));
                    $childparams = [];
                    foreach ($children_auth_groups as $key=>$children_auth_group) {
                        $childparams[$key]['id'] = $children_auth_group->id;
                        $childparams[$key]['rules'] = implode(',', array_intersect(explode(',', $children_auth_group->rules), $rules));
                    }
                    (new AuthGroup())->saveAll($childparams);
                    Db::commit();
                    $this->success();
                }catch (Exception $e){
                    Db::rollback();
                    $this->error($e->getMessage());
                }

            }
            $this->error();

            return;
        }
        $this->view->assign('row', $row);

        return $this->view->fetch();
    }

    /**
     * 删除.
     */
    public function del($ids = '')
    {
        if ($ids) {
            $ids = explode(',', $ids);
            $grouplist = $this->auth->getGroups();
            $group_ids = array_map(function ($group) {
                return $group['id'];
            }, $grouplist);
            // 移除掉当前管理员所在组别
            $ids = array_diff($ids, $group_ids);

            // 循环判断每一个组别是否可删除
            $grouplist = $this->model->where('id', 'in', $ids)->select();
            $groupaccessmodel = new AuthGroupAccess();
            foreach ($grouplist as $k => $v) {
                // 当前组别下有管理员
                $groupone = $groupaccessmodel->where(['group_id' => $v['id']])->find();
                if ($groupone) {
                    $ids = array_diff($ids, [$v['id']]);
                    continue;
                }
                // 当前组别下有子组别
                $groupone = $this->model->where(['pid' => $v['id']])->find();
                if ($groupone) {
                    $ids = array_diff($ids, [$v['id']]);
                    continue;
                }
            }
            if (! $ids) {
                $this->error(__('You can not delete group that contain child group and administrators'));
            }
            $count = $this->model->where('id', 'in', $ids)->delete();
            if ($count) {
                $this->success();
            }
        }
        $this->error();
    }

    /**
     * 批量更新.
     *
     * @internal
     */
    public function multi($ids = '')
    {
        // 组别禁止批量操作
        $this->error();
    }

    /**
     * 读取角色权限树.
     *
     * @internal
     */
    public function roletree()
    {
        $this->loadlang('auth/group');

        $model = new AuthGroup();
        $id = $this->request->post('id');
        $pid = $this->request->post('pid');
        $parentGroupModel = $model->find($pid);
        $currentGroupModel = null;
        if ($id) {
            $currentGroupModel = $model->find($id);
        }
        if (($pid || $parentGroupModel) && (! $id || $currentGroupModel)) {
            $id = $id ? $id : null;
            $ruleList = AuthRule::order('weigh', 'desc')->order('id', 'asc')->select()->toArray();
            //读取父类角色所有节点列表
            $parentRuleList = [];
            if (in_array('*', explode(',', $parentGroupModel->rules))) {
                $parentRuleList = $ruleList;
            } else {
                $parentRuleIds = explode(',', $parentGroupModel->rules);
                foreach ($ruleList as $k => $v) {
                    if (in_array($v['id'], $parentRuleIds)) {
                        $parentRuleList[] = $v;
                    }
                }
            }

            $ruleTree = new Tree();
            $groupTree = new Tree();
            //当前所有正常规则列表
            $ruleTree->init($parentRuleList);
            //角色组列表
            $groupTree->init(AuthGroup::where('id', 'in',
                $this->childrenGroupIds)->select()->toArray());

            //读取当前角色下规则ID集合
            $adminRuleIds = $this->auth->getRuleIds();
            //是否是超级管理员
            $superadmin = $this->auth->isSuperAdmin();
            //当前拥有的规则ID集合
            $currentRuleIds = $id ? explode(',', $currentGroupModel->rules) : [];

            if (! $id || ! in_array($pid, $this->childrenGroupIds) || ! in_array($pid,
                    $groupTree->getChildrenIds($id, true))) {
                $parentRuleList = $ruleTree->getTreeList($ruleTree->getTreeArray(0), 'name');
                $hasChildrens = [];
                foreach ($parentRuleList as $k => $v) {
                    if ($v['haschild']) {
                        $hasChildrens[] = $v['id'];
                    }
                }
                $parentRuleIds = array_map(function ($item) {
                    return $item['id'];
                }, $parentRuleList);
                $nodeList = [];
                foreach ($parentRuleList as $k => $v) {
                    if (! $superadmin && ! in_array($v['id'], $adminRuleIds)) {
                        continue;
                    }
                    if ($v['pid'] && ! in_array($v['pid'], $parentRuleIds)) {
                        continue;
                    }
                    $state = [
                        'selected' => in_array($v['id'], $currentRuleIds) && ! in_array($v['id'], $hasChildrens),
                    ];
                    $nodeList[] = [
                        'id'     => $v['id'],
                        'parent' => $v['pid'] ? $v['pid'] : '#',
                        'text'   => __($v['title']),
                        'type'   => 'menu',
                        'state'  => $state,
                    ];
                }
                $this->success('', null, $nodeList);
            } else {
                $this->error(__('Can not change the parent to child'));
            }
        } else {
            $this->error(__('Group not found'));
        }
    }
}
