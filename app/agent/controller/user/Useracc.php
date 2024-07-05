<?php

namespace app\agent\controller\user;

use app\common\controller\AgentBackend;
use app\common\library\Utils;
use think\facade\Db;

/**
 * 
 *
 * @icon fa fa-circle-o
 */
class Useracc extends AgentBackend
{
    
    /**
     * Userrate模型对象
     * @var \app\admin\model\user\Userrate
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\user\Userrate;
        $user_id = $this->request->param("ids");
        
        $this->view->assign("accList", $this->getUseracc($user_id));

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
        $ids = $this->request->param("ids");

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
                    ->withJoin(['acc'])
                    ->where('user_id', $ids)
                    ->where($where)
                    ->order($sort, $order)
                    ->count();

            $list = $this->model
                    ->withJoin(['acc'])
                    ->where('user_id', $ids)
                    ->where($where)
                    ->order($sort, $order)
                    ->limit($offset, $limit)
                    ->select();

            foreach ($list as $row) {
                $row->visible(['id','user_id','rate','acc_code','createtime']);
                $row->getRelation('acc')->visible(['name']);
            }
            $result = array("total" => $total, "rows" => $list);

            return json($result);
        }
        $this->assignconfig('user_id', $ids);
        $finduser = Db::name('user')->where('id', $ids)->find();
        $this->assign('username', $finduser['username']);
        return $this->view->fetch();
    }
    
    
    /**
     * 添加
     */
    public function add($ids = null) {

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
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name     = str_replace('\\model\\', '\\validate\\', get_class($this->model));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name.'.add' : $name) : $this->modelValidate;
                        validate($validate)->scene($this->modelSceneValidate ? 'edit' : $name)->check($params);
                    }

                    $params['user_id'] = $ids;
                    
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
    
    //获取通道
    public function getUseracc($user_id){

        $list = Db::name('user_acc')
            ->alias('a')
            ->join('acc b','a.acc_id = b.id','left')
            ->where(['a.user_id'=>$user_id, 'a.status'=>1])
            ->field('b.name,b.code')
            ->select();
        $datalist = [];
        foreach ($list as $index => $item) {
            $datalist[$item['code']] = $item['name'];
        }

        return $datalist;
    }
}
