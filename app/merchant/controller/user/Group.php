<?php

namespace app\merchant\controller\user;

use app\common\controller\MerchantBackend;

/**
 * 会员组管理.
 *
 * @icon fa fa-users
 */
class Group extends MerchantBackend
{
    /**
     * @var \app\merchant\model\UserGroup
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\merchant\model\UserGroup();
        $this->view->assign('statusList', $this->model->getStatusList());
    }

    public function add()
    {
        $nodeList = \app\merchant\model\UserRule::getTreeList();
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
        $nodeList = \app\merchant\model\UserRule::getTreeList($rules);
        $this->assign('nodeList', $nodeList);

        return parent::edit($ids);
    }
}
