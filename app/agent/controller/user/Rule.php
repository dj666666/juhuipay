<?php

namespace app\agent\controller\user;

use fast\Tree;
use app\common\controller\AgentBackend;

/**
 * 会员规则管理.
 *
 * @icon fa fa-circle-o
 */
class Rule extends AgentBackend
{
    /**
     * @var \app\agent\model\UserRule
     */
    protected $model = null;
    protected $rulelist = [];
    protected $multiFields = 'ismenu,status';

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\agent\model\UserRule();
        $this->view->assign('statusList', $this->model->getStatusList());
        // 必须将结果集转换为数组
        $ruleList = $this->model->order('weigh', 'desc')->select()->toArray();
        foreach ($ruleList as $k => &$v) {
            $v['title'] = __($v['title']);
            $v['remark'] = __($v['remark']);
        }
        unset($v);
        Tree::instance()->init($ruleList);
        $this->rulelist = Tree::instance()->getTreeList(Tree::instance()->getTreeArray(0), 'title');
        $ruledata = [0 => __('None')];
        foreach ($this->rulelist as $k => &$v) {
            if (! $v['ismenu']) {
                continue;
            }
            $ruledata[$v['id']] = $v['title'];
        }
        $this->view->assign('ruledata', $ruledata);
    }

    /**
     * 查看.
     */
    public function index()
    {
        if ($this->request->isAjax()) {
            $list = $this->rulelist;
            $total = count($this->rulelist);

            $result = ['total' => $total, 'rows' => $list];

            return json($result);
        }

        return $this->view->fetch();
    }

    /**
     * 删除.
     */
    public function del($ids = '')
    {
        if ($ids) {
            $delIds = [];
            foreach (explode(',', $ids) as $k => $v) {
                $delIds = array_merge($delIds, Tree::instance()->getChildrenIds($v, true));
            }
            $delIds = array_unique($delIds);
            $count = $this->model->where('id', 'in', $delIds)->delete();
            if ($count) {
                $this->success();
            }
        }
        $this->error();
    }
}
