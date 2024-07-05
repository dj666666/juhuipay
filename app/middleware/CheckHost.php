<?php
declare (strict_types = 1);

namespace app\middleware;

use think\facade\Config;

class CheckHost
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
        //如果是api应用 判断域名是否是绑定api应用域名，如果不是则跳转
        $name = app('http')->getName();
        if ($name == 'api'){
            $domain = $request->host();
            if ($domain != Config::get('site.api_url_domain')){
                Header("Location:https://www.baidu.com");
            }
        }

        return $next($request);
    }
}
