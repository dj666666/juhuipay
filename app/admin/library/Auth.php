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

namespace app\admin\library;

use app\common\library\GoogleAuthenticator;
use fast\Tree;
use fast\Random;
use think\facade\Config;
use think\facade\Cookie;
use think\facade\Db;
use think\facade\Event;
use think\facade\Request;
use think\facade\Session;
use app\admin\model\Admin;

class Auth extends \fast\Auth
{
    protected $_error = '';
    protected $requestUri = '';
    protected $breadcrumb = [];
    protected $logined = false; //登录状态

    public function __construct()
    {
        parent::__construct();
    }

    public function __get($name)
    {
        return Session::get('admin.'.$name);
    }

    /**
     * 管理员登录.
     *
     * @param string $username 用户名
     * @param string $password 密码
     * @param int    $keeptime 有效时长
     *
     * @return bool
     */
    public function login($username, $password, $googlecode, $keeptime = 0)
    {
        $admin = Admin::where(['username' => $username])->find();
        if (! $admin) {
            $this->setError('Username or password is incorrect');

            return false;
        }
        //判断是否开启谷歌验证
        if (Config::get('site.login_checkadmingoogle')) {
            if(!$googlecode){
                $this->setError('请输入谷歌验证码');
                return false;
            }
            $google = new GoogleAuthenticator();
            $result = $google->verifyCode($admin['google_code'],$googlecode);
            if(!$result){
                $this->setError('谷歌验证码错误');
                return false;
            }
        }
        //判断是否限制ip登入
        if (Config::get('site.loginip_checkadminlogin')) {
            $ip = request()->ip();
            $findip = Db::name('ippool')->where('ip',$ip)->find();
            if(!$findip){
                $this->setError('无登入权限');
                return false;
            }
        }
        if ($admin['status'] == 'hidden') {
            $this->setError('Admin is forbidden');

            return false;
        }
        if (Config::get('fastadmin.login_failure_retry') && $admin->loginfailure >= 10 && time() - $admin->updatetime < 86400) {
            $this->setError('Please try again after 1 day');

            return false;
        }
        if ($admin->password != md5(md5($password).$admin->salt)) {
            $admin->loginfailure++;
            $admin->save();
            $this->setError('Username or password is incorrect');

            return false;
        }
        $admin->loginfailure = 0;
        $admin->logintime = time();
        $admin->loginip = request()->ip();
        $admin->token = Random::uuid();
        $admin->save();
        Session::set('admin', $admin->toArray());
        $this->keeplogin($keeptime);

        return true;
    }

    /**
     * 注销登录.
     */
    public function logout()
    {
        $admin = Admin::find(intval($this->id));
        if ($admin) {
            $admin->token = '';
            $admin->save();
        }
        $this->logined = false; //重置登录状态
        Session::delete('admin');
        Cookie::delete('keeplogin');

        return true;
    }

    /**
     * 自动登录.
     *
     * @return bool
     */
    public function autologin()
    {
        $keeplogin = Cookie::get('keeplogin');
        if (! $keeplogin) {
            return false;
        }
        [$id, $keeptime, $expiretime, $key] = explode('|', $keeplogin);
        if ($id && $keeptime && $expiretime && $key && $expiretime > time()) {
            $admin = Admin::find($id);
            if (! $admin || ! $admin->token) {
                return false;
            }
            //token有变更
            if ($key != md5(md5($id).md5($keeptime).md5($expiretime).$admin->token)) {
                return false;
            }
            $ip = request()->ip();
            //IP有变动
            if ($admin->loginip != $ip) {
                return false;
            }
            Session::set('admin', $admin->toArray());
            //刷新自动登录的时效
            $this->keeplogin($keeptime);

            return true;
        } else {
            return false;
        }
    }

    /**
     * 刷新保持登录的Cookie.
     *
     * @param int $keeptime
     *
     * @return bool
     */
    protected function keeplogin($keeptime = 0)
    {
        if ($keeptime) {
            $expiretime = time() + $keeptime;
            $key = md5(md5($this->id).md5($keeptime).md5($expiretime).$this->token);
            $data = [$this->id, $keeptime, $expiretime, $key];
            Cookie::set('keeplogin', implode('|', $data), 86400 * 30);

            return true;
        }

        return false;
    }

    public function check($name, $uid = '', $relation = 'or', $mode = 'url')
    {
        $uid = $uid ? $uid : $this->id;
        return parent::check($name, $uid, $relation, $mode);
    }

    /**
     * 检测当前控制器和方法是否匹配传递的数组.
     *
     * @param array $arr 需要验证权限的数组
     *
     * @return bool
     */
    public function match($arr = [])
    {
        $request = Request::instance();
        $arr = is_array($arr) ? $arr : explode(',', $arr);
        if (! $arr) {
            return false;
        }

        $arr = array_map('strtolower', $arr);
        // 是否存在
        if (in_array(strtolower($request->action()), $arr) || in_array('*', $arr)) {
            return true;
        }

        // 没找到匹配
        return false;
    }

    /**
     * 检测是否登录.
     *
     * @return bool
     */
    public function isLogin()
    {
        if ($this->logined) {
            return true;
        }
        $admin = Session::get('admin');
        if (! $admin) {
            return false;
        }
        //判断是否同一时间同一账号只能在一个地方登录
        if (Config::get('site.login_unique_admin') == '1') {
            $my = Admin::find($admin['id']);
            if (! $my || $my['token'] != $admin['token']) {
                $this->logout();
                return false;
            }
        }
        //判断管理员IP是否变动
        if (Config::get('site.loginip_admin_check') == '1') {
            if (!isset($admin['loginip']) || $admin['loginip'] != request()->ip()) {
                $this->logout();
                return false;
            }
        }
        $this->logined = true;
        return true;
    }

    /**
     * 获取当前请求的URI.
     *
     * @return string
     */
    public function getRequestUri()
    {
        return $this->requestUri;
    }

    /**
     * 设置当前请求的URI.
     *
     * @param string $uri
     */
    public function setRequestUri($uri)
    {
        $this->requestUri = $uri;
    }

    public function getGroups($uid = null)
    {
        $uid = is_null($uid) ? $this->id : $uid;

        return parent::getGroups($uid);
    }

    public function getRuleList($uid = null)
    {
        $uid = is_null($uid) ? $this->id : $uid;

        return parent::getRuleList($uid);
    }

    public function getUserInfo($uid = null)
    {
        $uid = is_null($uid) ? $this->id : $uid;

        return $uid != $this->id ? Admin::find(intval($uid)) : Session::get('admin');
    }

    public function getRuleIds($uid = null)
    {
        $uid = is_null($uid) ? $this->id : $uid;

        return parent::getRuleIds($uid);
    }

    public function isSuperAdmin()
    {
        return in_array('*', $this->getRuleIds()) ? true : false;
    }

    /**
     * 获取管理员所属于的分组ID.
     *
     * @param int $uid
     *
     * @return array
     */
    public function getGroupIds($uid = null)
    {
        $groups = $this->getGroups($uid);
        $groupIds = [];
        foreach ($groups as $K => $v) {
            $groupIds[] = (int) $v['group_id'];
        }

        return $groupIds;
    }

    /**
     * 取出当前管理员所拥有权限的分组.
     *
     * @param bool $withself 是否包含当前所在的分组
     *
     * @return array
     */
    public function getChildrenGroupIds($withself = false)
    {
        //取出当前管理员所有的分组
        $groups = $this->getGroups();
        $groupIds = [];
        foreach ($groups as $k => $v) {
            $groupIds[] = $v['id'];
        }
        $originGroupIds = $groupIds;
        foreach ($groups as $k => $v) {
            if (in_array($v['pid'], $originGroupIds)) {
                $groupIds = array_diff($groupIds, [$v['id']]);
                unset($groups[$k]);
            }
        }
        // 取出所有分组
        $groupList = \app\admin\model\AuthGroup::where(['status' => 'normal'])->select();
        $objList = [];
        foreach ($groups as $k => $v) {
            if ($v['rules'] === '*') {
                $objList = $groupList;
                break;
            }
            // 取出包含自己的所有子节点
            $childrenList = Tree::instance()->init($groupList)->getChildren($v['id'], true);
            $obj = Tree::instance()->init($childrenList)->getTreeArray($v['pid']);
            $objList = array_merge($objList, Tree::instance()->getTreeList($obj));
        }
        $childrenGroupIds = [];
        foreach ($objList as $k => $v) {
            $childrenGroupIds[] = $v['id'];
        }
        if (! $withself) {
            $childrenGroupIds = array_diff($childrenGroupIds, $groupIds);
        }

        return $childrenGroupIds;
    }

    /**
     * 取出当前管理员所拥有权限的管理员.
     *
     * @param bool $withself 是否包含自身
     *
     * @return array
     */
    public function getChildrenAdminIds($withself = false)
    {
        $childrenAdminIds = [];
        if (! $this->isSuperAdmin()) {
            $groupIds = $this->getChildrenGroupIds(false);
            $authGroupList = \app\admin\model\AuthGroupAccess::
            field('uid,group_id')
                ->where('group_id', 'in', $groupIds)
                ->select();

            foreach ($authGroupList as $k => $v) {
                $childrenAdminIds[] = $v['uid'];
            }
        } else {
            //超级管理员拥有所有人的权限
            $childrenAdminIds = Admin::column('id');
        }
        if ($withself) {
            if (! in_array($this->id, $childrenAdminIds)) {
                $childrenAdminIds[] = $this->id;
            }
        } else {
            $childrenAdminIds = array_diff($childrenAdminIds, [$this->id]);
        }

        return $childrenAdminIds;
    }

    /**
     * 获得面包屑导航.
     *
     * @param string $path
     *
     * @return array
     */
    public function getBreadCrumb($path = '')
    {
        if ($this->breadcrumb || !$path) {
            return $this->breadcrumb;
        }
        $titleArr = [];
        $menuArr = [];
        $urlArr = explode('/', $path);
        foreach ($urlArr as $index => $item) {
            $pathArr[implode('/', array_slice($urlArr, 0, $index + 1))] = $index;
        }
        if (!$this->rules && $this->id) {
            $this->getRuleList();
        }
        foreach ($this->rules as $rule) {
            if (isset($pathArr[$rule['name']])) {
                $rule['title'] = __($rule['title']);
                $rule['url'] = url($rule['name']);
                $titleArr[$pathArr[$rule['name']]] = $rule['title'];
                $menuArr[$pathArr[$rule['name']]] = $rule;
            }

        }
        ksort($menuArr);
        $this->breadcrumb = $menuArr;
        return $this->breadcrumb;
    }

    /**
     * 获取左侧和顶部菜单栏.
     *
     * @param array  $params    URL对应的badge数据
     * @param string $fixedPage 默认页
     *
     * @return array
     */
    public function getSidebar($params = [], $fixedPage = 'dashboard')
    {
        // 边栏开始
        Event::trigger("admin_sidebar_begin", $params);
        $colorArr = ['red', 'green', 'yellow', 'blue', 'teal', 'orange', 'purple'];
        $colorNums = count($colorArr);
        $badgeList = [];
        $module = app()->http->getName();
        // 生成菜单的badge
        foreach ($params as $k => $v) {
            $url = $k;
            if (is_array($v)) {
                $nums = isset($v[0]) ? $v[0] : 0;
                $color = isset($v[1]) ? $v[1] : $colorArr[(is_numeric($nums) ? $nums : strlen($nums)) % $colorNums];
                $class = isset($v[2]) ? $v[2] : 'label';
            } else {
                $nums = $v;
                $color = $colorArr[(is_numeric($nums) ? $nums : strlen($nums)) % $colorNums];
                $class = 'label';
            }
            //必须nums大于0才显示
            if ($nums) {
                $badgeList[$url] = '<small class="'.$class.' pull-right bg-'.$color.'">'.$nums.'</small>';
            }
        }

        // 读取管理员当前拥有的权限节点
        $userRule = $this->getRuleList();
        $selected = $referer = [];
        $refererUrl = Session::get('referer');
        $pinyin = new \Overtrue\Pinyin\Pinyin('Overtrue\Pinyin\MemoryFileDictLoader');
        // 必须将结果集转换为数组
        $ruleList = \app\admin\model\AuthRule::where('status', 'normal')
            ->where('ismenu', 1)
            ->order('weigh', 'desc')
            ->cache('__menu__')
            ->select()->toArray();
        $indexRuleList = \app\admin\model\AuthRule::where('status', 'normal')
            ->where('ismenu', 0)
            ->where('name', 'like', '%/index')
            ->column('name,pid');
        $pidArr = array_filter(array_unique(array_map(function ($item) {
            return $item['pid'];
        }, $ruleList)));
        foreach ($ruleList as $k => &$v) {
            if (! in_array($v['name'], $userRule)) {
                unset($ruleList[$k]);
                continue;
            }
            $indexRuleName = $v['name'].'/index';
            if (isset($indexRuleList[$indexRuleName]) && ! in_array($indexRuleName, $userRule)) {
                unset($ruleList[$k]);
                continue;
            }
            $v['icon'] = $v['icon'].' fa-fw';
            //$v['url'] = '/' . $module . '/' . $v['name'];
            if (! empty($v['route'])) {
                $v['url'] = \request()->rootUrl().'/'.$v['route'];
            } else {
                $v['url'] = \request()->rootUrl().'/'.$v['name'];
            }
            $v['badge'] = isset($badgeList[$v['name']]) ? $badgeList[$v['name']] : '';
            $v['py'] = $pinyin->abbr($v['title'], '');
            $v['pinyin'] = $pinyin->permalink($v['title'], '');
            $v['title'] = __($v['title']);
            $selected = $v['name'] == $fixedPage ? $v : $selected;
            $referer = url($v['url']) == $refererUrl ? $v : $referer;
        }
        $lastArr = array_diff($pidArr, array_filter(array_unique(array_map(function ($item) {
            return $item['pid'];
        }, $ruleList))));
        foreach ($ruleList as $index => $item) {
            if (in_array($item['id'], $lastArr)) {
                unset($ruleList[$index]);
            }
        }
        if ($selected == $referer) {
            $referer = [];
        }
        $selected && $selected['url'] = url($selected['url']);
        $referer && $referer['url'] = url($referer['url']);

        $select_id = $selected ? $selected['id'] : 0;
        $menu = $nav = '';
        if (Config::get('fastadmin.multiplenav')) {
            $topList = [];
            foreach ($ruleList as $index => $item) {
                if (! $item['pid']) {
                    $topList[] = $item;
                }
            }
            $selectParentIds = [];
            $tree = Tree::instance();
            $tree->init($ruleList);
            if ($select_id) {
                $selectParentIds = $tree->getParentsIds($select_id, true);
            }
            foreach ($topList as $index => $item) {
                $childList = Tree::instance()->getTreeMenu($item['id'],
                    '<li class="@class" pid="@pid"><a href="@url@addtabs" addtabs="@id" url="@url" py="@py" pinyin="@pinyin"><i class="@icon"></i> <span>@title</span> <span class="pull-right-container">@caret @badge</span></a> @childlist</li>',
                    $select_id, '', 'ul', 'class="treeview-menu"');
                $current = in_array($item['id'], $selectParentIds);
                $url = $childList ? 'javascript:;' : url($item['url']);
                $addtabs = $childList || ! $url ? '' : (stripos($url, '?') !== false ? '&' : '?').'ref=addtabs';
                $childList = str_replace('" pid="'.$item['id'].'"',
                    ' treeview '.($current ? '' : 'hidden').'" pid="'.$item['id'].'"', $childList);
                $nav .= '<li class="'.($current ? 'active' : '').'"><a href="'.$url.$addtabs.'" addtabs="'.$item['id'].'" url="'.$url.'"><i class="'.$item['icon'].'"></i> <span>'.$item['title'].'</span> <span class="pull-right-container"> </span></a> </li>';
                $menu .= $childList;
            }
        } else {
            // 构造菜单数据
            Tree::instance()->init($ruleList);
            $menu = Tree::instance()->getTreeMenu(0,
                '<li class="@class"><a href="@url@addtabs" addtabs="@id" url="@url" py="@py" pinyin="@pinyin"><i class="@icon"></i> <span>@title</span> <span class="pull-right-container">@caret @badge</span></a> @childlist</li>',
                $select_id, '', 'ul', 'class="treeview-menu"');
            if ($selected) {
                $nav .= '<li role="presentation" id="tab_'.$selected['id'].'" class="'.($referer ? '' : 'active').'"><a href="#con_'.$selected['id'].'" node-id="'.$selected['id'].'" aria-controls="'.$selected['id'].'" role="tab" data-toggle="tab"><i class="'.$selected['icon'].' fa-fw"></i> <span>'.$selected['title'].'</span> </a></li>';
            }
            if ($referer) {
                $nav .= '<li role="presentation" id="tab_'.$referer['id'].'" class="active"><a href="#con_'.$referer['id'].'" node-id="'.$referer['id'].'" aria-controls="'.$referer['id'].'" role="tab" data-toggle="tab"><i class="'.$referer['icon'].' fa-fw"></i> <span>'.$referer['title'].'</span> </a> <i class="close-tab fa fa-remove"></i></li>';
            }
        }

        return [$menu, $nav, $selected, $referer];
    }

    /**
     * 设置错误信息.
     *
     * @param string $error 错误信息
     *
     * @return Auth
     */
    public function setError($error)
    {
        $this->_error = $error;

        return $this;
    }

    /**
     * 获取错误信息.
     *
     * @return string
     */
    public function getError()
    {
        return $this->_error ? __($this->_error) : '';
    }
}
