<?php

namespace app\agent\controller\thirdacc;

use app\common\controller\AgentBackend;
use think\facade\Db;
use app\admin\model\GroupQrcode;

/**
 * 代理通道管理
 *
 * @icon fa fa-circle-o
 */
class Agentacc extends AgentBackend
{
    
    /**
     * Agentacc模型对象
     * @var \app\admin\model\thirdacc\Agentacc
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\thirdacc\Agentacc;
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
                    ->withJoin(['agent','acc'])
                    ->where(['agent_id'=>$this->auth->id, 'agentacc.status' => 1])
                    ->where($where)
                    ->order($sort, $order)
                    ->count();
            
            $list = $this->model
                    ->withJoin(['agent','acc'])
                    ->where(['agent_id'=>$this->auth->id, 'agentacc.status' => 1])
                    ->where($where)
                    ->order($sort, $order)
                    ->limit($offset, $limit)
                    ->select();

            foreach ($list as $v) {
                $v->visible(['id','agent_id','acc_id','acc_code','status','create_time','rate','on_num','off_num','today_rate']);
                if($v['acc']){
                    $v->getRelation('acc')->visible(['name']);
                }
                if($v['agent']){
                    $v->getRelation('agent')->visible(['username']);
                }
                
                //开启了接单的码商开启的码子数量
                $on_count = Db::name('group_qrcode')->alias('a')
                                ->join('user b', 'a.user_id = b.id')
                                ->where(['a.agent_id'=>$this->auth->id,'a.status'=>GroupQrcode::STATUS_ON,'a.acc_code'=>$v['acc_code'],'b.is_receive'=>1])
                                ->count();
                
                $off_count = Db::name('group_qrcode')->alias('a')
                                ->join('user b', 'a.user_id = b.id')
                                ->where(['a.agent_id'=>$this->auth->id,'a.status'=>GroupQrcode::STATUS_OFF,'a.acc_code'=>$v['acc_code'],'b.is_receive'=>1])
                                ->count();
                                
                //该通道今日成功单
                $acc_today_suc_num = Db::name('order')->where(['agent_id'=> $this->auth->id,'status'=>1,'pay_type'=>$v['acc_code']])->whereDay('createtime')->count();
                
                //该通道今日全部单
                $acc_today_all_num = Db::name('order')->where(['agent_id'=> $this->auth->id,'pay_type'=>$v['acc_code']])->whereDay('createtime')->count();
                
                //该通道今日成率
                $acc_today_rate = $acc_today_suc_num == 0 ? '0%' : (bcdiv($acc_today_suc_num, $acc_today_all_num, 4) * 100) . "%";
                
                $v['on_num']     = $on_count;
                $v['off_num']    = $off_count;
                $v['today_rate'] = $acc_today_rate;
                                
            }
            $result = array("total" => $total, "rows" => $list);

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
                    //是否采用模型验证
                    if ($this->modelValidate) {
                        $name     = str_replace('\\model\\', '\\validate\\', get_class($this->model));
                        $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name.'.add' : $name) : $this->modelValidate;
                        validate($validate)->scene($this->modelSceneValidate ? 'edit' : $name)->check($params);
                    }
                    
                    
                    $params['acc_code']    = \app\admin\model\thirdacc\Acc::where('id',$params['acc_id'])->value('code');
                    $params['create_time'] = time();
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
    public function getAccForSelect(){
        
        $page     = $this->request->request('pageNumber', 1, 'int');//当前页
        $pagesize = $this->request->request('pageSize');//分页大小
        
        $total = Db::name('agent_acc')
            ->alias('a')
            ->join('acc b','a.acc_code = b.code','left')
            ->where(['a.agent_id'=>$this->auth->id,'a.status'=>1])
            ->field('b.name,b.code')
            ->count();
            
        $list = Db::name('agent_acc')
            ->alias('a')
            ->join('acc b','a.acc_code = b.code','left')
            ->where(['a.agent_id'=>$this->auth->id,'a.status'=>1])
            ->field('b.name,b.code')
            ->page($page, $pagesize)
            ->select();
            
        $datalist = [];
        
        foreach ($list as $index => $item) {
            $datalist[] = [
                'code' => $item['code'],
                'name' => $item['name'],
                'pid'  => 0,
            ];
        }

        return ['list'=>$datalist,'total'=> $total];
    }
    
    //获取三方代收通道
    public function thirdpayacc(){
        
        $page     = $this->request->request('pageNumber', 1, 'int');//当前页
        $pagesize = $this->request->request('pageSize');//分页大小
        
        $total = Db::name('thirdpay_acc')
            ->where(['agent_id'=>$this->auth->id,'status'=>1])
            ->count();
            
        $list = Db::name('thirdpay_acc')
            ->where(['agent_id'=>$this->auth->id,'status'=>1])
            ->field('id,name')
            ->page($page, $pagesize)
            ->select();
            
        $datalist = [];
        
        foreach ($list as $index => $item) {
            $datalist[] = [
                'id' => $item['id'],
                'name' => $item['name'],
                'pid'  => 0,
            ];
        }

        return ['list'=>$datalist,'total'=> $total];
    }
    
}
