<?php

namespace app\agent\controller\general;

use fast\Random;
use think\facade\Session;
use app\agent\model\Admin;
use app\agent\model\AdminLog;
use app\common\controller\AgentBackend;
use think\facade\Validate;

/**
 * 个人配置.
 *
 * @icon fa fa-user
 */
class Profile extends AgentBackend
{
    /**
     * 查看.
     */
    public function index()
    {
        //设置过滤方法
        $this->request->filter(['strip_tags']);
        if ($this->request->isAjax()) {
            $model = new AdminLog();
            [$where, $sort, $order, $offset, $limit] = $this->buildparams();

            $total = $model
                ->where($where)
                ->where('admin_id', $this->auth->id)
                ->order($sort, $order)
                ->count();

            $list = $model
                ->where($where)
                ->where('admin_id', $this->auth->id)
                ->order($sort, $order)
                ->limit($offset, $limit)
                ->select();

            $result = ['total' => $total, 'rows' => $list];

            return json($result);
        }

        return $this->view->fetch();
    }

    /**
     * 更新个人信息.
     */
    public function update()
    {
        if ($this->request->isPost()) {
            $this->token();
            $params = $this->request->post('row/a');
            $params = array_filter(array_intersect_key($params,
                array_flip(['email', 'nickname', 'password', 'avatar'])));
            unset($v);
            // if (!Validate::is($params['email'], "email")) {
            //     $this->error(__("Please input correct email"));
            // }
            if (isset($params['password'])) {
                if (!Validate::is($params['password'], "/^[\S]{6,16}$/")) {
                    $this->error(__("Please input correct password"));
                }
                $params['salt'] = Random::alnum();
                $params['password'] = md5(md5($params['password']).$params['salt']);
            }
            // $exist = Admin::where('email', $params['email'])->where('id', '<>', $this->auth->id)->find();
            // if ($exist) {
            //     $this->error(__("Email already exists"));
            // }
            if ($params) {
                $admin = Admin::find($this->auth->id);
                $admin->save($params);
                //因为个人资料面板读取的Session显示，修改自己资料后同时更新Session
                Session::set('agent', $admin->toArray());
                $this->success();
            }
            $this->error();
        }
    }
}
