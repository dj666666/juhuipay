<?php

namespace app\admin\controller\thirdacc;

use app\admin\model\order\Order;
use app\common\controller\Backend;
use think\facade\Db;
use think\facade\Request;
use app\common\library\Utils;
use think\facade\Config;
use fast\Random;
use fast\Http;
use app\common\library\Accutils;
/**
 * 收款码管理
 *
 * @icon fa fa-circle-o
 */
class Userqrcode extends Backend
{
    
    /**
     * Userqrcode模型对象
     * @var \app\admin\model\thirdacc\Userqrcode
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\admin\model\thirdacc\Userqrcode;
        $this->view->assign("typeList", $this->model->getTypeList());
        $this->view->assign("statusList", $this->model->getStatusList());
        $this->view->assign("isUseList", $this->model->getIsUseList());
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
                    ->withJoin(['user'])
                    ->where($where)
                    ->order($sort, $order)
                    ->count();

            $list = $this->model
                    ->withJoin(['user'])
                    ->where($where)
                    ->order($sort, $order)
                    ->limit($offset, $limit)
                    ->select();

            foreach ($list as $row) {
                
                if($row['user']){
                    $row->getRelation('user')->visible(['username']);
                }
                
                $row['acc_type'] = Db::name('acc')->where('code',$row['acc_code'])->value('name');

                //今日成功金额收款
                $today_suc_money = Order::where(['qrcode_id'=>$row->id,'status'=>1])->whereDay('createtime')->sum('amount');
                
                //今日金额
                $today_money = Order::where(['qrcode_id'=>$row->id])->whereDay('createtime')->sum('amount');
                
                //总成功收款
                $all_suc_money = Order::where(['qrcode_id'=>$row->id,'status'=>1])->sum('amount');
                
                //总收款
                $all_money = Order::where(['qrcode_id'=>$row->id])->sum('amount');
                
                //该通道总成功订单
                $successorder = Order::where(['status'=>1,'qrcode_id'=>$row->id])->count();
                
                //该通道总订单
                $allorder = Order::where(['qrcode_id'=>$row->id])->count();

                //该通道今日成功订单
                $todaysuccessorder = Order::where(['status'=>1,'qrcode_id'=>$row->id])->whereDay('createtime')->count();
                
                
                //该通道今日订单
                $todayallorder = Order::where(['qrcode_id'=>$row->id])->whereDay('createtime')->count();


                //总成功率
                $success_rate = $allorder == 0 ? "0%" : (bcdiv($successorder,$allorder,4) * 100) ."%";
                
                //今日成功率
                $today_success_rate = $todayallorder == 0 ? "0%" : (bcdiv($todaysuccessorder,$todayallorder,4) * 100) ."%";
                
                $row['statistics'] = '今日笔数:<span style="color:#18BC9C;font-weight:bold;">' . $todaysuccessorder . '</span> / ' . '<span style="color:#18BC9C;font-weight:bold;">' . $todayallorder . '</span>' . '&nbsp&nbsp今日金额:<span style="color:red;font-weight:bold;">' . $today_suc_money . '</span>/ <span style="color:red;font-weight:bold;">' . $today_money . '</span></br>' . '今日成功率:<span style="color:#007BFF;font-weight:bold;">' . $today_success_rate . '</span></br>' . '总笔数:</span><span style="color:#18BC9C;font-weight:bold;">' . $successorder . '</span> /<span style="color:#18BC9C;font-weight:bold;">' . $allorder . '</span>&nbsp&nbsp总金额:<span style="color:red;font-weight:bold;">' . $all_suc_money . '</span> / <span style="color:red;font-weight:bold;">' . $all_money . '</span></br>' .
                    '总成功率:</span>/<span style="color:#007BFF;font-weight:bold;">' . $success_rate . '</span>';

                
                
            }
            $result = array("total" => $total, "rows" => $list);

            return json($result);
        }
        return $this->view->fetch();
    }
    
    //切换启用状态
    public function change($ids = ''){
        if($ids){
            $row = $this->model->get($ids);
            $status = $row['status'] == 1 ? 0 : 1;
            $re =$this->model->where('id',$ids)->update(['status'=>$status,'update_time'=>time()]);

            if($re){
                $this->success("切换成功");
            }

            $this->error("切换失败");
        }

        $this->error("参数缺少");
    }
    
    //迅雷cookie查单
    public function xlqueryorder_old(){
        $id = $this->request->request('id');
        $trade_no = $this->request->request('trade_no');
        
        $row = $this->model->get($id);
        if (!$row) {
            $this->error('通道错误');
        }
        
        $order = Db::name('order')->where(['trade_no'=>$trade_no])->find();
        if(!$order){
            $this->error('单号不存在');
        }
        
        $accutils = new Accutils();
        $time     = $accutils->getMsectime();
        $params   = [];
        
        $options = [
            CURLOPT_HTTPHEADER =>[
                'Cookie:'.$row['xl_cookie'],
            ]
        ];
        
        $url = 'https://xluser-ssl.xunlei.com/tradingrecord/v1/GetTradingRecord?csrf_token=fff61cf90f6b183806aa527c57121625&appid=22003&starttime=2022-12-01&endtime=2022-12-31&paytype=&_='.$time;
        $result = json_decode(Http::get($url, $params, $options),true);
        
        if($result['code'] != 200){
            Db::name('group_qrcode')->where(['id' => $row['id']])->update(['status'=>0,'remark'=>'ck失效了']);
            $this->error('ck失效，请重新抓取');
        }
        
        foreach($result['data']['records'] as $k1 => $xl_order){
            
            if(in_array($order['xl_order_id'], $xl_order) && $order['amount'] == $xl_order['orderAmt']){
                $this->success('查单成功，订单已支付');
                break;
            }
        }
        
        $this->error('失败，未查到该订单');
    }
}
