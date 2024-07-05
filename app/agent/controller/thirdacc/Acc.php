<?php

namespace app\agent\controller\thirdacc;

use app\common\controller\AgentBackend;
use think\exception\ValidateException;
use think\facade\Db;

/**
 * 通道管理
 *
 * @icon fa fa-circle-o
 */
class Acc extends AgentBackend
{
    
    /**
     * Acc模型对象
     * @var \app\admin\model\thirdacc\Acc
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\thirdacc\Acc;
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
        //设置过滤方法
        $this->request->filter(['strip_tags']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            [$where, $sort, $order, $offset, $limit] = $this->buildparams();
            $total = $this->model
                ->where($where)
                ->where('status', 1)
                ->order($sort, $order)
                ->count();

            $list = $this->model
                ->where($where)
                ->where('status', 1)
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();

            $list   = $list->toArray();
            $result = ['total' => $total, 'rows' => $list];

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
                    $result = $this->model->save($params);
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
    
    //获取系统开启的通道
    public function getAcc(){
        
        //当前页
        $page = $this->request->request('pageNumber', 1, 'int');
        //分页大小
        $pagesize = $this->request->request('pageSize');
        
        $list  = $this->model->where(['status'=>1])->page($page, $pagesize)->select();
        $total = $this->model->where(['status'=>1])->count();

        $datalist = [];
        
        foreach ($list as $index => $item) {
            $data['id'] = $item['id'];
            $data['name'] = $item['name'];
            $datalist[] = $data;
        }

        return json(['list' => $datalist, 'total' => $total]);
    }

}
