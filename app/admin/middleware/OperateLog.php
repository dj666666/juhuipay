<?php
declare (strict_types = 1);

namespace app\admin\middleware;

use think\facade\Db;
use app\admin\library\Auth as AdminAuth;

class OperateLog
{
    public function handle($request, \Closure $next)
    {

        $params = $request->post();

        $adminInfo = '';
        $adminAuth = new AdminAuth();
        if ($adminAuth->isLogin()){
            $adminInfo = $adminAuth->getUserInfo();
        }

        $title = [];
        $breadcrumb = AdminAuth::instance()->getBreadcrumb();
        foreach ($breadcrumb as $k => $v) {
            $title[] = $v['title'];
        }
        $title = implode(' ', $title);
        $url = $request->url();

        $pathinfo = $request->pathinfo();

        $index = strrpos($pathinfo,'/');
        $action = substr($pathinfo,$index+1);
        $action = str_replace('.html','',$action);

        if ($action == 'index' || $action == 'lang'){
            return $next($request);
        }

        $data = [
            'admin_id'  => empty($adminInfo) ? 0 : $adminInfo['id'],
            'username'  => empty($adminInfo) ? isset($params['username']) ? $params['username'] : 'Unknown' : $adminInfo['username'],
            'url'       => $url,
            'title'     => $title,
            'content'   => json_encode($params),
            'ip'        => request()->ip(),
            'useragent' => substr(request()->server('HTTP_USER_AGENT'), 0, 255),
            'createtime'=> time(),
        ];

        Db::name('admin_log')->insert($data);

        return $next($request);
    }

}
