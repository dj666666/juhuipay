<?php

namespace app\admin\controller\statistics;

use app\common\controller\Backend;
use think\facade\Db;

/**
 * 数据管理
 *
 * @icon fa fa-user
 */
class Dbdata extends Backend
{
    

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

            $list = [
                ['id'=>1, 'name'=>'订单','createtime'=>time()],
                ['id'=>2, 'name'=>'提现','createtime'=>time()],
                ['id'=>3, 'name'=>'商户余额记录','createtime'=>time()],
                ['id'=>4, 'name'=>'码商余额记录','createtime'=>time()],
                ['id'=>5, 'name'=>'码商商户余额记录','createtime'=>time()],
                ['id'=>6, 'name'=>'平台余额记录','createtime'=>time()],
                ['id'=>7, 'name'=>'码商日志','createtime'=>time()],
                ['id'=>8, 'name'=>'商户日志','createtime'=>time()],
            ];

            $total = count($list);


            $result = array("total" => $total, "rows" => $list);

            return json($result);

        }
        return $this->view->fetch();
    }

    public function delData($ids = null,$time = null){
        if (empty($ids) || empty($time)){
            $this->error('参数缺少');
        }

        $timeArr = explode(' - ',$time);
        $timeStr = strtotime($timeArr[0]).','.strtotime($timeArr[1]);
        //$timeStr = $timeArr[0].','.$timeArr[1];

        $map[]= ['createtime','between', $timeStr];
        $where[]= ['create_time','between', $timeStr];

        foreach ($ids as $key){
            switch ($key){
                case 1:
                    //订单
                    Db::name('order')->where($map)->delete();
                    break;
                case 2:
                    //提现
                    Db::name('applys')->where($map)->delete();
                    break;
                case 3:
                    //提现
                    $where['create_time']= ['between', $timeStr];
                    Db::name('money_log')->where($where)->delete();
                    break;
                case 4:
                    //商户余额记录
                    Db::name('money_log')->where($where)->delete();
                    break;
                case 5:
                    //码商余额记录
                    Db::name('user_money_log')->where($where)->delete();
                    break;
                case 6:
                    //码商商户余额记录
                    Db::name('user_money_log')->where($where)->delete();
                    break;
                case 7:

                    //码商日志
                    Db::name('user_log')->where($map)->delete();
                    break;
                case 8:
                    //商户日志
                    Db::name('merchant_log')->where($map)->delete();
                    break;
                default:
                    $this->error('错误');
            }
        }


        $this->success('清除成功');
    }

}
