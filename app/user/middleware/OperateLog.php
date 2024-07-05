<?php
declare (strict_types = 1);

namespace app\user\middleware;

use think\facade\Db;
use app\user\library\Auth as UserAuth;
use think\facade\Request;

class OperateLog
{
    /**
     * 处理请求
     *
     * @param \think\Request $request
     * @param \Closure       $next
     * @return Response
     */
    public function handle($request, \Closure $next)
    {

        $params = $request->post();

        $adminInfo = '';
        $adminAuth = new UserAuth();
        if ($adminAuth->isLogin()){
            $adminInfo = $adminAuth->getUserInfo();
        }

        $title = [];
        $breadcrumb = UserAuth::instance()->getBreadcrumb();
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
            'admin_id'   => empty($adminInfo) ? 0 : $adminInfo['id'],
            'username'  => empty($adminInfo) ? isset($params['username']) ? $params['username'] : 'Unknown' : $adminInfo['username'],
            'url'       => $url,
            'title'     => $title,
            'content'   => json_encode($params),
            'ip'        => request()->ip(),
            'useragent' => substr(request()->server('HTTP_USER_AGENT'), 0, 255),
            'createtime'=> time(),
        ];

        Db::name('user_log')->insert($data);

        return $next($request);
    }

}
