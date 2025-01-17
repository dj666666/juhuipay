<?php
/**
 * *
 *  * ============================================================================
 *  * Created by PhpStorm.
 *  * User: Ice
 *  * 邮箱: ice@sbing.vip
 *  * 网址: https://sbing.vip
 *  * Date: 2019/9/19 下午3:19
 *  * ============================================================================.
 */

namespace app\common\controller;

use fast\Tree;
use think\facade\Env;
use think\facade\Lang;
use think\facade\Validate;
use think\facade\View;
use think\facade\Event;
use think\facade\Config;
use think\facade\Session;
use app\agent\library\Auth;
use app\admin\model\user\User;

/**
 * 后台控制器基类.
 */
class AgentBackend extends BaseController
{
    /**
     * 无需登录的方法,同时也就不需要鉴权了.
     *
     * @var array
     */
    protected $noNeedLogin = [];

    /**
     * 无需鉴权的方法,但需要登录.
     *
     * @var array
     */
    protected $noNeedRight = [];

    /**
     * 布局模板
     *
     * @var string
     */
    protected $layout = 'default';

    /**
     * 权限控制类.
     *
     * @var Auth
     */
    protected $auth = null;

    /**
     * 模型对象
     *
     * @var \think\Model
     */
    protected $model = null;

    /**
     * 快速搜索时执行查找的字段.
     */
    protected $searchFields = 'id';

    /**
     * 是否是关联查询.
     */
    protected $relationSearch = false;

    /**
     * 是否开启数据限制
     * 支持auth/personal
     * 表示按权限判断/仅限个人
     * 默认为禁用,若启用请务必保证表中存在admin_id字段.
     */
    protected $dataLimit = false;

    /**
     * 数据限制字段.
     */
    protected $dataLimitField = 'admin_id';

    /**
     * 数据限制开启时自动填充限制字段值
     */
    protected $dataLimitFieldAutoFill = true;

    /**
     * 是否开启Validate验证
     */
    protected $modelValidate = false;

    /**
     * 是否开启模型场景验证
     */
    protected $modelSceneValidate = false;

    /**
     * Multi方法可批量修改的字段.
     */
    protected $multiFields = 'status';

    /**
     * Selectpage可显示的字段.
     */
    protected $selectpageFields = '*';

    /**
     * 前台提交过来,需要排除的字段数据.
     */
    protected $excludeFields = '';

    /**
     * 导入文件首行类型
     * 支持comment/name
     * 表示注释或字段名.
     */
    protected $importHeadType = 'comment';

    /*
     * 引入后台控制器的traits
     */
    use \app\agent\library\traits\Backend;

    public function _initialize()
    {
        $modulename = app()->http->getName();
        $controller = preg_replace_callback('/\.[A-Z]/', function ($d) {
            return strtolower($d[0]);
        }, $this->request->controller(), 1);

        $controllername = parseName($controller);
        $actionname = strtolower($this->request->action());

        $path = str_replace('.', '/', $controllername).'/'.$actionname;

        // 定义是否Addtabs请求
        ! defined('IS_ADDTABS') && define('IS_ADDTABS', input('addtabs') ? true : false);

        // 定义是否Dialog请求
        ! defined('IS_DIALOG') && define('IS_DIALOG', input('dialog') ? true : false);

        // 定义是否AJAX请求
        ! defined('IS_AJAX') && define('IS_AJAX', $this->request->isAjax());

        $this->auth = Auth::instance();
        // 设置当前请求的URI
        $this->auth->setRequestUri($path);
        // 检测是否需要验证登录
        if (! $this->auth->match($this->noNeedLogin)) {
            //检测是否登录
            if (! $this->auth->isLogin()) {
                Event::trigger('admin_nologin', $this);
                $url = Session::get('referer');
                $url = $url ? $url : $this->request->url();
                if ($url == '/') {
                    $this->redirect('index/login', [], 302, ['referer' => $url]);
                    exit;
                }
                $this->error(__('Please login first'), url('index/login', ['url' => $url]));
            }
            // 判断是否需要验证权限
            if (! $this->auth->match($this->noNeedRight)) {
                // 判断控制器和方法判断是否有对应权限
                if (! $this->auth->check($path)) {
                    Event::trigger('admin_nopermission', $this);
                    $this->error(__('You have no permission'), '');
                }
            }
        }

        // 非选项卡时重定向
        if (! $this->request->isPost() && ! IS_AJAX && ! IS_ADDTABS && ! IS_DIALOG && input('ref') == 'addtabs') {
            $url = preg_replace_callback("/([\?|&]+)ref=addtabs(&?)/i", function ($matches) {
                return $matches[2] == '&' ? $matches[1] : '';
            }, $this->request->url());
            if (Config::get('url_domain_deploy')) {
                if (stripos($url, $this->request->server('SCRIPT_NAME')) === 0) {
                    $url = substr($url, strlen($this->request->server('SCRIPT_NAME')));
                }
                $url = url($url, [], false);
            }
            $this->redirect(url('index/index'), [], 302, ['referer' => $url]);
            exit;
        }

        // 设置面包屑导航数据
        $breadcrumb = $this->auth->getBreadCrumb($path);
        array_pop($breadcrumb);
        $this->view->breadcrumb = $breadcrumb;

        // 如果有使用模板布局
        if ($this->layout) {
            View::engine()->layout('layout/'.$this->layout);
        }

        // 语言检测
        $lang = strip_tags(Lang::getLangSet());

        $site = Config::get('site');
        $upload = \app\common\model\Config::upload();
        // 上传信息配置后
        $event_upload_config = Event::trigger('upload_config_init', $upload,true);
        if($event_upload_config){
            $upload = array_merge($upload, $event_upload_config);
        }
        // 配置信息
        $config = [
            'app_debug'      => Env::get('APP_DEBUG'),
            'site'           => array_intersect_key($site,
                array_flip(['name', 'indexurl', 'cdnurl', 'version', 'timezone', 'languages'])),
            'upload'         => $upload,
            'modulename'     => $modulename,
            'controllername' => $controllername,
            'actionname'     => $actionname,
            'jsname'         => 'agent/'.str_replace('.', '/', $controllername),
            'moduleurl'      => rtrim(request()->root(), '/'),
            'language'       => $lang,
            'fastadmin'      => Config::get('fastadmin'),
            'referer'        => Session::get('referer'),
        ];
        $config = array_merge($config, Config::get("view_replace_str"));
        Config::set(array_merge(Config::get('upload'), $upload), 'upload');
        // 配置信息后
        $event_config = Event::trigger('config_init', $config,true);
        if($event_config){
            $config = array_merge($config, $event_config);
        }
        //加载当前控制器语言包
        $this->loadlang($this->request->controller());
        //渲染站点配置
        $this->assign('site', $site);
        //渲染配置信息
        $this->assign('config', $config);
        //渲染权限对象
        $this->assign('auth', $this->auth);
        //渲染管理员对象
        $this->assign('admin', Session::get('agent'));
    }

    /**
     * 加载语言文件.
     *
     * @param  string  $name
     */
    protected function loadlang($name)
    {
        if (strpos($name, '.')) {
            $_arr = explode('.', $name);
            if (count($_arr) == 2) {
                $path = $_arr[0].'/'.parseName($_arr[1]);
            } else {
                $path = strtolower($name);
            }
        } else {
            $path = parseName($name);
        }
        Lang::load(app()->getAppPath().'/lang/'.Lang::getLangset().'/'.$path.'.php');
    }

    /**
     * 渲染配置信息.
     *
     * @param  mixed  $name  键名或数组
     * @param  mixed  $value  值
     */
    protected function assignconfig($name, $value = '')
    {
        $this->view->config = array_merge($this->view->config ? $this->view->config : [],
            is_array($name) ? $name : [$name => $value]);
    }

    /**
     * 生成查询所需要的条件,排序方式.
     *
     * @param  mixed  $searchfields  快速查询的字段
     * @param  bool  $relationSearch  是否关联查询
     *
     * @return array
     */
    protected function buildparams($searchfields = null, $relationSearch = null)
    {
        $searchfields = is_null($searchfields) ? $this->searchFields : $searchfields;
        $relationSearch = is_null($relationSearch) ? $this->relationSearch : $relationSearch;
        $search = $this->request->get('search', '');
        $filter = $this->request->get('filter', '');
        $op = $this->request->get('op', '', 'trim');
        $sort = $this->request->get('sort',
            ! empty($this->model) && $this->model->getPk() ? $this->model->getPk() : 'id');
        $order = $this->request->get('order', 'DESC');
        $offset = $this->request->get('offset', 0);
        $limit = $this->request->get('limit', 0);
        $filter = (array) json_decode($filter, true);
        $op = (array) json_decode($op, true);
        $filter = $filter ? $filter : [];
        
        if(isset($filter['parent_name'])){
            $userModel = new User();
            $parent_id = $userModel->where('username',$filter['parent_name'])->value('id');
            $filter["parent_id"] = $parent_id;
            $op["parent_id"]     = '=';
            unset($filter['parent_name']);
            unset($op['parent_name']);
        }
        
        $where = [];
        $tableName = '';
        if ($relationSearch) {
            if (! empty($this->model)) {
                //$name = parseName(trim(basename(str_replace('\\', ' / ', get_class($this->model)))));
                $name = $this->model->getTable();
                $tableName = trim($name).'.';
            }
            $sortArr = explode(',', $sort);
            foreach ($sortArr as $index => &$item) {
                $item = stripos($item, '.') === false ? $tableName.trim($item) : $item;
            }
            unset($item);
            $sort = implode(',', $sortArr);
        }
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds)) {
            $where[] = [$tableName.$this->dataLimitField, 'in', $adminIds];
        }
        if ($search) {
            $searcharr = is_array($searchfields) ? $searchfields : explode(',', $searchfields);
            foreach ($searcharr as $k => &$v) {
                $v = stripos($v, '.') === false ? $tableName.$v : $v;
            }
            unset($v);
            $where[] = [implode('|', $searcharr), 'LIKE', "%{$search}%"];
        }
        foreach ($filter as $k => $v) {
            $sym = isset($op[$k]) ? $op[$k] : ' = ';
            if (stripos($k, '.') === false) {
                $k = $tableName.$k;
            }
            $v = ! is_array($v) ? trim($v) : $v;
            $sym = strtoupper(isset($op[$k]) ? $op[$k] : $sym);
            switch ($sym) {
                case ' = ':
                case '=':
                case ' <> ':
                case '<>':
                    $where[] = [$k, $sym, (string) $v];
                    break;
                case 'LIKE':
                case 'NOT LIKE':
                case 'LIKE %...%':
                case 'NOT LIKE %...%':
                    $where[] = [$k, trim(str_replace(' %...%', '', $sym)), "%{$v}%"];
                    break;
                case ' > ':
                case '>':
                case '>=':
                case ' < ':
                case '<':
                case '<=':
                    $where[] = [$k, $sym, intval($v)];
                    break;
                case 'FINDIN':
                case 'FINDINSET':
                case 'FIND_IN_SET':
                    $where[] = "FIND_IN_SET('$v', ".($relationSearch ? $k : '`'.str_replace('.', '` . `', $k).'`').')';
                    break;
                case 'IN':
                case 'IN(...)':
                case 'NOT IN':
                case 'NOT IN(...)':
                    $where[] = [$k, str_replace('(...)', '', $sym), is_array($v) ? $v : explode(',', $v)];
                    break;
                case 'BETWEEN':
                case 'NOT BETWEEN':
                    $arr = array_slice(explode(',', $v), 0, 2);
                    if (stripos($v, ',') === false || ! array_filter($arr)) {
                        continue 2;
                    }
                    //当出现一边为空时改变操作符
                    if ($arr[0] === '') {
                        $sym = $sym == 'BETWEEN' ? ' <= ' : '>';
                        $arr = $arr[1];
                    } elseif ($arr[1] === '') {
                        $sym = $sym == 'BETWEEN' ? ' >= ' : '<';
                        $arr = $arr[0];
                    }
                    $where[] = [$k, $sym, $arr];
                    break;
                case 'RANGE':
                case 'NOT RANGE':
                    $v = str_replace(' - ', ',', $v);
                    $arr = array_slice(explode(',', $v), 0, 2);
                    if (stripos($v, ',') === false || ! array_filter($arr)) {
                        continue 2;
                    }
                    //当出现一边为空时改变操作符
                    if ($arr[0] === '') {
                        $sym = $sym == 'RANGE' ? ' <= ' : '>';
                        $arr = $arr[1];
                    } elseif ($arr[1] === '') {
                        $sym = $sym == 'RANGE' ? ' >= ' : '<';
                        $arr = $arr[0];
                    }
                    $where[] = [$k, str_replace('RANGE', 'BETWEEN', $sym).' time', $arr];
                    break;
                case 'NULL':
                case 'IS NULL':
                case 'NOT NULL':
                case 'IS NOT NULL':
                    $where[] = [$k, strtolower(str_replace('IS ', '', $sym))];
                    break;
                default:
                    break;
            }
        }
        if (! empty($where)) {
            $where = function ($query) use ($where) {
                foreach ($where as $k => $v) {
                    if (is_array($v)) {
                        call_user_func_array([$query, 'where'], $v);
                    } else {
                        $query->where($v);
                    }
                }
            };
        }

        return [$where, trim($sort), trim($order), $offset, $limit];
    }

    /**
     * 获取数据限制的管理员ID
     * 禁用数据限制时返回的是null.
     *
     * @return mixed
     */
    protected function getDataLimitAdminIds()
    {
        if (! $this->dataLimit) {
            return;
        }
        if ($this->auth->isSuperAdmin()) {
            return;
        }
        $adminIds = [];
        if (in_array($this->dataLimit, ['auth', 'personal'])) {
            $adminIds = $this->dataLimit == 'auth' ? $this->auth->getChildrenAdminIds(true) : [$this->auth->id];
        }

        return $adminIds;
    }

    /**
     * Selectpage的实现方法.
     * 当前方法只是一个比较通用的搜索匹配,请按需重载此方法来编写自己的搜索逻辑,$where按自己的需求写即可
     * 这里示例了所有的参数，所以比较复杂，实现上自己实现只需简单的几行即可.
     */
    protected function selectpage()
    {
        //设置过滤方法
        $this->request->filter(['trim', 'strip_tags', 'htmlspecialchars']);

        //搜索关键词,客户端输入以空格分开,这里接收为数组
        $word = (array) $this->request->request('q_word/a');
        //当前页
        $page = $this->request->request('pageNumber', 1, 'int');
        //分页大小
        $pagesize = $this->request->request('pageSize');
        //搜索条件
        $andor = $this->request->request('andOr', 'and', 'strtoupper');
        //排序方式
        $orderby = (array) $this->request->request('orderBy/a');
        //显示的字段
        $field = $this->request->request('showField');
        //主键
        $primarykey = $this->request->request('keyField');
        //主键值
        $primaryvalue = $this->request->request('keyValue');
        //搜索字段
        $searchfield = (array) $this->request->request('searchField/a');
        //自定义搜索条件
        $custom = (array) $this->request->request('custom/a');
        //是否返回树形结构
        $istree = $this->request->request('isTree', 0);
        $ishtml = $this->request->request('isHtml', 0);
        if ($istree) {
            $word = [];
            $pagesize = 99999;
        }
        $order = [];
        foreach ($orderby as $k => $v) {
            $order[$v[0]] = $v[1];
        }
        $field = $field ? $field : 'name';

        //如果有primaryvalue,说明当前是初始化传值
        if ($primaryvalue !== null) {
            $where = [$primarykey => explode(',', $primaryvalue)];
            $pagesize = null;
        } else {
            $where = function ($query) use ($word, $andor, $field, $searchfield, $custom) {
                $logic = $andor == ' AND ' ? ' & ' : ' | ';
                $searchfield = is_array($searchfield) ? implode($logic, $searchfield) : $searchfield;
                foreach ($word as $k => $v) {
                    $query->where(str_replace(',', $logic, $searchfield), 'like', "%{$v}%");
                }
                if ($custom && is_array($custom)) {
                    foreach ($custom as $k => $v) {
                        if (is_array($v) && 2 == count($v)) {
                            $query->where($k, trim($v[0]), $v[1]);
                        } else {
                            $query->where($k, '=', $v);
                        }
                    }
                }
            };
        }
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds)) {
            $this->model->where($this->dataLimitField, 'in', $adminIds);
        }
        $list = [];
        $total = $this->model->where($where)->count();
        if ($total > 0) {
            if (is_array($adminIds)) {
                $this->model->where($this->dataLimitField, 'in', $adminIds);
            }
            $datalist = $this->model->where($where)
                ->order($order)
                ->page($page, $pagesize)
                ->field($this->selectpageFields)
                ->select()->toArray();
            foreach ($datalist as $index => $item) {
                unset($item['password'], $item['salt']);
                $list[] = [
                    $primarykey => isset($item[$primarykey]) ? $item[$primarykey] : '',
                    $field      => isset($item[$field]) ? $item[$field] : '',
                    'pid'       => isset($item['pid']) ? $item['pid'] : 0,
                ];
            }
            if ($istree && ! $primaryvalue) {
                $tree = Tree::instance();
                $tree->init($list, 'pid');
                $list = $tree->getTreeList($tree->getTreeArray(0), $field);
                if (! $ishtml) {
                    foreach ($list as &$item) {
                        $item = str_replace(' & nbsp;', ' ', $item);
                    }
                    unset($item);
                }
            }
        }
        //这里一定要返回有list这个字段,total是可选的,如果total<=list的数量,则会隐藏分页按钮
        return json(['list' => $list, 'total' => $total]);
    }

    /**
     * 刷新Token
     */
    protected function token()
    {
        $token = $this->request->post('__token__');

        //验证Token
        if (! Validate::is($token, "token", ['__token__' => $token])) {
            $this->error(__('Token verification error'), '', '', 3, ['__token__' => $this->request->buildToken()]);
        }

        //刷新Token
        $this->request->buildToken();
    }
}
