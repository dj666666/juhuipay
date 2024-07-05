<?php

namespace app\api\controller;

use fast\Random;
use think\facade\Validate;
use app\common\library\Ems;
use app\common\library\Sms;
use app\common\controller\Api;
use think\facade\Db;
use think\facade\Config;

/**
 * 会员接口.
 */
class User extends Api
{
    protected $noNeedLogin = ['*'];
    protected $noNeedRight = '*';

    

    /**
     * 登录.
     */
    public function login()
    {
        $username   = $this->request->post('username');
        $password   = $this->request->post('password');
        
        
        //$ret = $this->auth->login($username, $password);
        $user = Db::name('user')->where(['username'=>$username,'status'=>'normal'])->find();
        if (!$user) {
            $this->error('账号或密码错误');
        }
         
        if ($user['password'] != $this->getEncryptPassword($password, $user['salt'])) {
            $this->error('账号或密码错误');
        }
 
        $this->success('登录成功');   
        
    }
    
    
    /**
     * 获取密码加密后的字符串.
     *
     * @param string $password 密码
     * @param string $salt     密码盐
     *
     * @return string
     */
    public function getEncryptPassword($password, $salt = '')
    {
        return md5(md5($password).$salt);
    }
    

}
