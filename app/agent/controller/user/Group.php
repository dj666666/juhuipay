<?php

namespace app\agent\controller\user;

use app\common\controller\AgentBackend;

/**
 * 会员组管理.
 *
 * @icon fa fa-users
 */
class Group extends AgentBackend
{
    /**
     * @var \app\agent\model\UserGroup
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\agent\model\UserGroup();
        $this->view->assign('statusList', $this->model->getStatusList());
    }
    
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
                    ->where(['agent_id' => $this->auth->id])
                    ->where($where)
                    ->order($sort, $order)
                    ->count();

            $list = $this->model
                    ->where(['agent_id' => $this->auth->id])
                    ->where($where)
                    ->order($sort, $order)
                    ->limit($offset, $limit)
                    ->select();

            foreach ($list as $row) {
                
                $row->getRelation('agent')->visible(['username']);
            }
            $result = array("total" => $total, "rows" => $list);

            return json($result);
        }
        return $this->view->fetch();
    }
    
    public function add()
    {
        $nodeList = \app\agent\model\UserRule::getTreeList();
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
        $nodeList = \app\agent\model\UserRule::getTreeList($rules);
        $this->assign('nodeList', $nodeList);

        return parent::edit($ids);
    }
}
