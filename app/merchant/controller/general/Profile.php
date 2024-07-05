<?php

namespace app\merchant\controller\general;

use fast\Random;
use think\facade\Session;
use app\merchant\model\Admin;
use app\merchant\model\AdminLog;
use app\common\controller\MerchantBackend;
use think\facade\Validate;

/**
 * 个人配置.
 *
 * @icon fa fa-user
 */
class Profile extends MerchantBackend
{
    /**
     * 查看.
     */
    public function index()
    {
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
                array_flip(['old_password', 'old_pay_password','pay_password','nickname', 'password', 'avatar'])));
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
            if(isset($params['pay_password'])){
                if (!Validate::is($params['pay_password'], "/^[\S]{6,16}$/")) {
                    $this->error(__("Please input correct password"));
                }

                if(md5($params['old_pay_password']) != $admin['pay_password']){
                    $this->error(__("旧支付密码错误"));
                }

                $params['pay_password'] = md5($params['pay_password']);
            }

            if ($params) {
                $admin = Admin::find($this->auth->id);
                $admin->save($params);
                //因为个人资料面板读取的Session显示，修改自己资料后同时更新Session
                Session::set('merchant', $admin->toArray());
                $this->success();
            }
            $this->error();
        }
    }
}
