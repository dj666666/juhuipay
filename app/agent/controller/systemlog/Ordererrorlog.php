<?php

namespace app\agent\controller\systemlog;

use app\common\controller\AgentBackend;
use think\facade\Db;

/**
 * 订单错误日志
 *
 * @icon fa fa-circle-o
 */
class Ordererrorlog extends AgentBackend
{
    
    /**
     * Ordererrorlog模型对象
     * @var \app\admin\model\systemlog\Ordererrorlog
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\systemlog\Ordererrorlog;

    }
    
    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */
    
    public function index()
    {
        //设置过滤方法
        $this->request->filter(['strip_tags']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            [$where, $sort, $order, $offset, $limit] = $this->buildparams();
            $total = $this->model
                ->where('agent_id', $this->auth->id)
                ->where($where)
                ->order($sort, $order)
                ->count();

            $list = $this->model
                ->where('agent_id', $this->auth->id)
                ->where($where)
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();

            foreach ($list as $row) {
                $row['content'] = mb_convert_encoding( substr($row['content'],0,70), 'UTF-8', 'UTF-8,GBK,GB2312,BIG5' );
                
                if($row['agent_id'] != 0){
                    $row['agent_name'] = Db::name('agent')->where('id',$row['agent_id'])->cache(true,3600)->value('nickname');
                }else{
                    $row['agent_name'] = $row['agent_id'];
                }
                

            }

            $result = ['total' => $total, 'rows' => $list];

            return json($result);
        }

        return $this->view->fetch();
    }

    //查看详情
    public function detail($ids = null){
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $this->view->assign("row", $row);
        return $this->view->fetch();
    }
    

}
