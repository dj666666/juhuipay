<?php

namespace app\user\controller\thirdacc;

use app\admin\model\GroupQrcode;
use app\common\controller\UserBackend;
use fast\Http;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Log;

/**
 * 淘宝店铺管理
 *
 * @icon fa fa-circle-o
 */
class Tbshop extends UserBackend
{

    /**
     * Tbshop模型对象
     * @var \app\admin\model\thirdacc\Tbshop
     */
    protected $model = null;

    protected $qrcode_num = 0;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\thirdacc\Tbshop;
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
                ->where('user_id', $this->auth->id)
                ->where($where)
                ->order($sort, $order)
                ->count();

            $list = $this->model
                ->where('user_id', $this->auth->id)
                ->where($where)
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();

            foreach ($list as $row) {
                $row->visible(['id','user_id','agent_id','name','status','remark','create_time','update_time','cookie','is_online']);
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
                    $params['agent_id'] = $this->auth->agent_id;
                    $params['acc_code'] = '1031';

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

    /**
     * 同步获取淘宝商品
     *
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function syncTbGood_qn(){
        $ids = $this->request->request('ids');
        if(!$ids){
            $this->error(__('参数缺少'));
        }

        $tb_shop = $this->model->find($ids);

        $url      = 'https://h5api.m.taobao.com/h5/mtop.taobao.sell.pc.manage.async/1.0/?jsv=2.6.1&appKey=12574478&t=1681016767707&sign=05eb3757d0d496b2eb5dd7e400ee26b3&api=mtop.taobao.sell.pc.manage.async&v=1.0&ttid=11320%40taobao_WEB_9.9.99&type=originaljson&dataType=json';
        $referer  = 'https://qn.taobao.com/';
        $postData = 'data={"url":"/taobao/manager/table.htm","jsonBody":"{\"tab\":\"on_sale\",\"pagination\":{\"current\":1,\"pageSize\":20},\"filter\":{},\"table\":{\"sort\":{}}}"}';

        $header_arr = [
            'Cookie: ' . $tb_shop['cookie'],
            'referer: ' . $referer,
        ];

        $options = [
            CURLOPT_HTTPHEADER => $header_arr,
        ];

        $res = Http::post($url, $postData, $options);
        $result = json_decode($res, true);

        if ($result['ret'][0] !== 'SUCCESS::调用成功'){
            Log::write('获取淘宝商品失败----'.$res,'waring');
            $this->error('失败，'.$result['ret'][0]);
        }

        $data = json_decode($result['data']['result'], true);
        $goodList = $data['data']['table']['dataSource'];
        $qrcode = [];
        foreach ($goodList as $k => $v){

            GroupQrcode::where(['user_id'=>$this->auth->id,'tb_good_id'=>$v['itemId']])->delete();

            $qrcode[] = [
                'acc_code'          => $tb_shop['acc_code'],
                'user_id'           => $this->auth->id,
                'agent_id'          => $this->auth->agent_id,
                'name'              => $v['itemDesc']['desc'][0]['text'],
                'pay_url'           => $v['itemDesc']['desc'][0]['href'],
                'zfb_pid'           => $v['itemId'],
                'tb_shop_id'        => (int)$ids,
                'tb_good_id'        => $v['itemId'],
                'create_time'       => time(),
                'xl_cookie'         => $tb_shop['cookie'],
                'order_max_amount'  => 100000,
                'success_order_num' => 1000,
                'qrcode_interval'   => 300,
            ];

        }

        $res = GroupQrcode::insertAll($qrcode);
        if ($res){
            $this->success('获取成功，数量：'.count($qrcode));
        }

        $this->error('获取失败，请重试');
    }

    public function getGoods(){
        $ids = $this->request->request('ids');
        if(!$ids){
            $this->error(__('参数缺少'));
        }

        $tb_shop = $this->model->find($ids);

        $url      = 'https://item.manager.taobao.com/taobao/manager/table.htm';
        $postData = 'jsonBody={"filter":{},"pagination":{"current":1,"pageSize":20},"table":{"sort":{"startDate_m":"desc"}},"tab":"on_sale"}';

        $header_arr = [
            'Cookie: ' . $tb_shop['cookie'],
            'x-requested-with: XMLHttpRequest',
            'x-xsrf-token: ' . $tb_shop['token'],
        ];

        $options = [
            CURLOPT_HTTPHEADER => $header_arr,
        ];

        $res = Http::post($url, $postData, $options);
        $result = json_decode($res, true);

        if (!isset($result['success']) || $result['success'] !== true){
            Log::write('获取淘宝商品失败----'.$res,'waring');
            $this->error('获取失败');
        }

        $total = $result['data']['pagination']['total'];
        $current = 1;
        $pageSize = 20;
        Log::write('获取数量----'.$total,'waring');
        //根据商户数量处理分页
        $count = ceil($total / $pageSize); //循环分页数
        
        for ($i = 1; $i <= $count; $i++) {
            $this->syncTbGoods($tb_shop, $i, $pageSize);
        }

        $this->success('获取成功，数量：'.$this->qrcode_num);

    }

    public function syncTbGoods($tb_shop, $current=1, $pageSize=20){

        $tb_shop_id = $tb_shop['id'];

        $url      = 'https://item.manager.taobao.com/taobao/manager/table.htm';
        $postData = 'jsonBody={"filter":{},"pagination":{"current":'.$current.',"pageSize":'.$pageSize.'},"table":{"sort":{"startDate_m":"desc"}},"tab":"on_sale"}';
        
        $header_arr = [
            'Cookie: ' . $tb_shop['cookie'],
            'x-requested-with: XMLHttpRequest',
            'x-xsrf-token: ' . $tb_shop['token'],
        ];

        $options = [
            CURLOPT_HTTPHEADER => $header_arr,
        ];

        $res = Http::post($url, $postData, $options);
        $result = json_decode($res, true);

        if ($result['success'] !== true){
            Log::write('获取淘宝商品失败----'.$current .'----'.$res,'waring');
            return;
        }


        $goodList = $result['data']['table']['dataSource'];
        $qrcode   = [];

        foreach ($goodList as $k => $v){

            $find = GroupQrcode::where(['user_id'=>$this->auth->id,'tb_good_id'=>$v['itemId']])->find();
            if($find){
                continue;
            }
            $qrcode[] = [
                'acc_code'          => $tb_shop['acc_code'],
                'user_id'           => $this->auth->id,
                'agent_id'          => $this->auth->agent_id,
                'name'              => $v['itemDesc']['desc'][0]['text'],
                'pay_url'           => $v['itemDesc']['desc'][0]['href'],
                'zfb_pid'           => $v['itemId'],
                'tb_shop_id'        => (int)$tb_shop_id,
                'tb_good_id'        => $v['itemId'],
                'tb_good_price'     => str_replace('¥ ','',$v['managerPrice']['currentPrice']),
                'create_time'       => time(),
                'xl_cookie'         => $tb_shop['cookie'],
                'order_max_amount'  => 100000,
                'success_order_num' => 1000,
                'qrcode_interval'   => 300,
            ];

        }
        
        
        if(!empty($qrcode)){
            $res = GroupQrcode::insertAll($qrcode);
            if($res){
                $this->qrcode_num += count($qrcode);
            }
        }
        
        

    }
}
