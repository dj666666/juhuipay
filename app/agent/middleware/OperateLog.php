<?php
declare (strict_types = 1);

namespace app\agent\middleware;

use think\facade\Db;
use app\agent\library\Auth as AgentAuth;

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
        $adminAuth = new AgentAuth();
        if ($adminAuth->isLogin()){
            $adminInfo = $adminAuth->getUserInfo();
        }

        $title = [];
        $breadcrumb = AgentAuth::instance()->getBreadcrumb();
        foreach ($breadcrumb as $k => $v) {
            $title[] = $v['title'];
        }
        $title = implode(' ', $title);
        $url = $request->url();
        $pathinfo = $request->pathinfo();
        $action = substr($pathinfo,-5);

        /*if ($action == 'index'){
            return $next($request);
        }*/


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

        Db::name('agent_log')->insert($data);

        return $next($request);
    }

}