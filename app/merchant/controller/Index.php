<?php
/**
 * *
 *  * ============================================================================
 *  * Created by PhpStorm.
 *  * User: Ice
 *  * 邮箱: ice@sbing.vip
 *  * 网址: https://sbing.vip
 *  * Date: 2019/9/19 下午3:33
 *  * ============================================================================.
 */

namespace app\merchant\controller;

use think\Validate;
use think\facade\Event;
use think\facade\Config;
use app\merchant\model\AdminLog;
use app\common\controller\MerchantBackend;
use app\common\library\GoogleAuthenticator;
use think\facade\Db;

/**
 * 后台首页.
 *
 * @internal
 */
class Index extends MerchantBackend
{
    protected $noNeedLogin = ['login'];
    protected $noNeedRight = ['index', 'logout'];
    protected $layout = '';

    public function _initialize()
    {
        parent::_initialize();
        //移除HTML标签
        $this->request->filter('trim,strip_tags,htmlspecialchars');
    }

    /**
     * 后台首页.
     */
    public function index()
    {
        //左侧菜单
        [$menulist, $navlist, $fixedmenu, $referermenu] = $this->auth->getSidebar([
            //            'dashboard' => 'hot',
            //            'addon'     => ['new', 'red', 'badge'],
            //            'auth/rule' => __('Menu'),
            //            'general'   => ['new', 'purple'],
        ], $this->view->site['fixedpage']);
        $action = $this->request->request('action');
        if ($this->request->isPost()) {
            if ($action == 'refreshmenu') {
                $this->success('', null, ['menulist' => $menulist, 'navlist' => $navlist]);
            }
        }
        
        $user = Db::name('merchant')->where('id', $this->auth->id)->find();
        if (empty($user['google_code'])) {
            
            $google      = new GoogleAuthenticator();
            $google_code = $google->createSecret();
            $google_rul  = $google->getQRCodeGoogleUrl($user['username'], $google_code);
            
            $this->assignconfig('google_code', $google_code);
            $this->assignconfig('google_code_url', $google_rul);
            $this->assignconfig('is_bind_google', 0);
            
            $this->view->assign('google_code', $google_code);
        }else{
            $this->view->assign('google_code', '');
            $this->assignconfig('is_bind_google', 1);
        }
        
        $this->view->assign('menulist', $menulist);
        $this->view->assign('navlist', $navlist);
        $this->view->assign('fixedmenu', $fixedmenu);
        $this->view->assign('referermenu', $referermenu);
        $this->view->assign('title', __('Home'));

        return $this->view->fetch();
    }
    
    //绑定谷歌
    public function bindGoogleAuth(){
        
        if ($this->request->isPost()) {
            
            $googleSecret = $this->request->post('google_secret');
            $googleCode   = $this->request->post('google_code');
            if(empty($googleCode) || empty($googleSecret)){
                $this->error('请输入谷歌验证码');
            }

            $google = new GoogleAuthenticator();
            $result = $google->verifyCode($googleSecret, $googleCode);
            
            if (!$result) {
                
                $this->error('谷歌验证码不正确');
                
            }

            $user = Db::name('merchant')->where('id', $this->auth->id)->update(['google_code' => $googleSecret]);
            $this->success('绑定成功');
        }
        
        $this->error('验证错误');
    }
    
    /**
     * 管理员登录.
     */
    public function login()
    {
        $url = $this->request->get('url', 'index/index');
        if ($this->auth->isLogin()) {
            $this->success(__("You've logged in, do not login again"), $url);
        }
        if ($this->request->isPost()) {
            $username  = $this->request->post('username');
            $password  = $this->request->post('password');
            $keeplogin = $this->request->post('keeplogin');
            $token     = $this->request->post('__token__');
            $googlecode = $this->request->post('googlecode');
            $rule      = [
                'username|'.__('Username') => 'require|length:3,30',
                'password|'.__('Password') => 'require|length:3,30',
                '__token__'                => 'require|token',
            ];
            $data      = [
                'username'  => $username,
                'password'  => $password,
                '__token__' => $token,
            ];
            if (Config::get('fastadmin.login_captcha')) {
                $rule['captcha|'.__('Captcha')] = 'require|captcha';
                $data['captcha']                = $this->request->post('captcha');
            }
            event('RobotLog', $this->request);
            $validate = validate($rule, [], false, false);
            $result   = $validate->check($data);
            if (!$result) {
                $this->error($validate->getError(), $url, ['token' => $this->request->buildToken()]);
            }
            AdminLog::setTitle(__('Login'));
            $result = $this->auth->login($username, $password, $googlecode,$keeplogin ? 86400 : 21600);
            if ($result === true) {
                Event::trigger('admin_login_after', $this->request);
                $this->success(__('Login successful'), $url,
                    ['url' => $url, 'id' => $this->auth->id, 'username' => $username, 'avatar' => $this->auth->avatar]);
            } else {
                $msg = $this->auth->getError();
                $msg = $msg ? $msg : __('Username or password is incorrect');
                $this->error($msg, $url, ['token' => $this->request->buildToken()]);
            }
        }

        // 根据客户端的cookie,判断是否可以自动登录
        if ($this->auth->autologin()) {
            $this->redirect($url);
        }
        $background = Config::get('site.login_background_img');
        $background = $background ? (stripos($background, 'http') === 0 ? $background : config('site.cdnurl') . $background) : '';
        $this->view->assign('background', $background);
        $this->view->assign('title', __('Login'));
        Event::trigger('admin_login_init', $this->request);
        
        $view = Config::get('site.login_html');
        
        return $this->view->fetch($view);
    }

    /**
     * 注销登录.
     */
    public function logout()
    {
        $this->auth->logout();
        Event::trigger('admin_logout_after', $this->request);
        $this->success(__('Logout successful'), 'index/login');
    }
}