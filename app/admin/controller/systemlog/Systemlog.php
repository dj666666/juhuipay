<?php

namespace app\admin\controller\systemlog;

use app\common\controller\Backend;

/**
 * 系统全局访问日志管理
 *
 * @icon fa fa-circle-o
 */
class Systemlog extends Backend
{
    
    /**
     * Systemlog模型对象
     * @var \app\admin\model\systemlog\Systemlog
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\systemlog\Systemlog;

    }
    
    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */
    
    /**
     * 查看.
     */
    public function index()
    {
        if ($this->request->isAjax()) {
            [$where, $sort, $order, $offset, $limit] = $this->buildparams();

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
                $content = json_decode($row['content'],true);
                $row['content'] = $content['message'];
                
            }
            
            $result = ['total' => $total, 'rows' => $list];
            
            return json($result);
        }

        return $this->view->fetch();
    }
    
    /**
     * 详情.
     */
    public function detail($ids)
    {
        $row = $this->model->get(['id' => $ids]);
        if (! $row) {
            $this->error(__('No Results were found'));
        }
        $this->view->assign('row', $row->toArray());

        return $this->view->fetch();
    }
}
