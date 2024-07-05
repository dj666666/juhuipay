<?php

namespace app\user\controller\user;

use app\common\controller\UserBackend;

/**
 * 会员组管理.
 *
 * @icon fa fa-users
 */
class Group extends UserBackend
{
    /**
     * @var \app\user\model\UserGroup
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\user\model\UserGroup();
        $this->view->assign('statusList', $this->model->getStatusList());
    }

    public function add()
    {
        $nodeList = \app\user\model\UserRule::getTreeList();
        $this->assign('nodeList', $nodeList);

        return parent::add();
    }

    public function edit($ids = null)
    {
        $row = $this->model->get($ids);
        if (! $row) {
            $this->error(__('No Results were found'));
        }
        $rules = explode(',', $row['rules']);
        $nodeList = \app\user\model\UserRule::getTreeList($rules);
        $this->assign('nodeList', $nodeList);

        return parent::edit($ids);
    }
}
