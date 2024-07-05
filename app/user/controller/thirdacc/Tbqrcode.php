<?php

namespace app\user\controller\thirdacc;

use app\common\controller\UserBackend;
use think\facade\Db;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use think\facade\Config;
use jianyan\excel\Excel;

/**
 * 淘宝代付码
 *
 * @icon fa fa-circle-o
 */
class Tbqrcode extends UserBackend
{
    
    /**
     * Tbqrcode模型对象
     * @var \app\admin\model\thirdacc\Tbqrcode
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\thirdacc\Tbqrcode;
        $this->view->assign("statusList", $this->model->getStatusList());
        $this->view->assign("payStatusList", $this->model->getPayStatusList());
        $this->view->assign("isUseList", $this->model->getIsUseList());
        $this->view->assign("expireStatusList", $this->model->getExpireStatusList());
        $this->view->assign("qrcodeList", $this->getQrcodeList());
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
                    ->withJoin(['groupqrcode'])
                    ->where(['tbqrcode.user_id'=>$this->auth->id])
                    ->where($where)
                    ->order($sort, $order)
                    ->count();

            $list = $this->model
                    ->withJoin(['groupqrcode'])
                    ->where(['tbqrcode.user_id'=>$this->auth->id])
                    ->where($where)
                    ->order($sort, $order)
                    ->limit($offset, $limit)
                    ->select();

            foreach ($list as $row) {
                if($row['groupqrcode']){
                    $row->getRelation('groupqrcode')->visible(['name']);
                }
                
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
                    
                    
                    $params['user_id'] = $this->auth->id;
                    $params['create_time'] = time();
                    $params['expire_time'] = time()+60*60*24;
                    
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
    
    
    //获取所挂的通道
    public function getQrcodeList(){

        $list = Db::name('group_qrcode')
            ->where(['user_id'=>$this->auth->id,'status'=>1])
            ->field('id,name')
            ->select()->toArray();
        $datalist = [];
        foreach ($list as $index => $item) {
            $datalist[$item['id']] = $item['name'];
        }
        
        return $datalist;
    }
    
    
    public function exportdemo(){

        $header = [
            ['收款码id', 'qrcode_id', 'text'], // 规则不填默认text
            ['商品名称', 'name', 'text'], // 规则不填默认text
            ['金额', 'amount', 'text'],
            ['链接', 'pay_url', 'text'],
        ];
        			
        $list = [
            [
                'qrcode_id' => '125(模板数据记得删除，但表头保留)',
                'name' => '苹果手机',
                'amount' => '100',
                'pay_url' => 'https://mobile.yangkeduo.com/friend_pay.html?_wv=41729&_wvx=10&fp_id=VMaFUurLn6VeWn2PotXeOw_9XGcfoX1NREfrEpk3wwU&refer_share_id=wu6edcwjzi6lf5344b71kufn97ax909e&refer_share_uin=4TR6YFEATVGHE753USAPGUGNEI_GEXDA&refer_share_channel=message',
            ]
        ];
        // 简单使用
        return Excel::exportData($list, $header , 'pdd导入模板', 'xlsx',);
    }
    
    /**
     * 导入 
     * 
     * 1035 导入 
     * 
     */
    public function import(){
        
        $file   = $this->request->request('file');
        $file   = substr($file, 1);
        $import = Excel::import($file, 2);
        
        unset($import[0]);
        
        $user_id  = $this->auth->id;
        $agent_id = $this->auth->agent_id;
        $acc_code = 1035;
        $insertdata = [];
        
        foreach ($import as $key1 => $value1){
            
            if (empty($value1) || empty($value1[1])) {
               unset($import[$key1]);
               continue;
            }
            
            if(count($value1) != 4){
                $this->error('导入格式错误，请核实');
                break;
            }
            
        
            //格式 收款码别名	uid	每日笔数上限	每日金额上限	拉单间隔
            $temp['user_id']         = $user_id;
            $temp['group_qrcode_id'] = trim($value1[0]);
            $temp['good_name']       = trim($value1[1]);
            $temp['amount']          = trim($value1[2]);
            $temp['pay_url']         = trim($value1[3]);
            $temp['create_time']     = time();
            

            $insertdata[] = $temp;
        }
        $group_qrcode_ids = array_column($insertdata,'group_qrcode_id');
        //去重
        $group_qrcode_ids = array_unique($group_qrcode_ids);
        foreach ($group_qrcode_ids as $k => $v){
            $findqid = Db::name('group_qrcode')->where('id', $v)->find();
            if(!$findqid){
                $this->error($v.'不存在');
            }
        }
        /*if($acc_code == '1007'){
            
           //判断是否有重复
            $uid_arr = array_column($insertdata, 'zfb_pid');
            
            $res = $this->model->where('zfb_pid', 'in', $uid_arr)->field('zfb_pid')->select()->toArray();
            
            if ($res) {
                $uids = implode(',', array_column($res, 'zfb_pid'));
                $this->error('重复uid，请检查后再导入：'. $uids);
            } 
        }*/
        
        
        
        $all_num    = count($insertdata);
        $success_num = 0;
        $fail_num    = 0;
       
        /*foreach ($insertdata as $k =>$v){

            $re = Db::name('group_qrcode')->insert($v);
            
            if ($re) {
                $success_num++;
                
            }else{
                $fail_num++;
            }
            
        }*/
        Db::name('tb_qrcode')->insertAll($insertdata);
        
        $this->success('导入成功，总数：'.$all_num);

    }
    
    
}
