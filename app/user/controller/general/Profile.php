<?php

namespace app\user\controller\general;

use fast\Random;
use think\facade\Session;
use app\user\model\Admin;
use app\user\model\AdminLog;
use app\common\controller\UserBackend;
use think\facade\Validate;
use app\common\library\Utils;

/**
 * 个人配置.
 *
 * @icon fa fa-user
 */
class Profile extends UserBackend
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
        
        $alipayurl = Utils::imagePath('/api/demo/alipayurl' , true);
        
        $this->assignconfig('alipayurl', $alipayurl);
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
                array_flip(['nickname', 'password', 'old_password', 'avatar'])));
            unset($v);

            $admin = Admin::get($this->auth->id);

            if (isset($params['password'])) {
                if (!Validate::is($params['password'], "/^[\S]{6,16}$/")) {
                    $this->error(__("Please input correct password"));
                }

                if(md5(md5($params['old_password']) . $admin['salt']) != $admin['password']){
                    $this->error(__("旧密码错误"));
                }

                $params['salt'] = Random::alnum();
                $params['password'] = md5(md5($params['password']).$params['salt']);
            }
            unset($params['old_password']);

            if ($params) {
                $admin = Admin::find($this->auth->id);
                $admin->save($params);
                //因为个人资料面板读取的Session显示，修改自己资料后同时更新Session
                Session::set('user', $admin->toArray());
                $this->success();
            }
            $this->error();
        }
    }
}
