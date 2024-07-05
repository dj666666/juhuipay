<?php

namespace app\common\event;
use app\common\library\Utils;
use app\admin\library\Auth as AdminAuth;
use app\user\library\Auth as UserAuth;
use app\merchant\library\Auth as MerchantAuth;
use app\agent\library\Auth as AgentAuth;


class SystemErrorLog{
    
    public function handle($data){
        
        $modulename = app()->http->getName();
        
        switch ($modulename) {
            case 'user':
                $auth = UserAuth::instance();
                $username  = $auth->isLogin() ? $auth->username : __('Unknown');
                break;
            case 'merchant':
                $auth = MerchantAuth::instance();
                $username  = $auth->isLogin() ? $auth->username : __('Unknown');
                break;
            case 'agent':
                $auth = AgentAuth::instance();
                $username  = $auth->isLogin() ? $auth->username : __('Unknown');
                break;
            case 'admin':
                $auth = AdminAuth::instance();
                $username  = $auth->isLogin() ? $auth->username : __('Unknown');
                break;
            case 'api':
                $username  ='api';
                break;
            default:
                $username  ='Unknown';
                break;
        }
        
        $useragent = substr(request()->server('HTTP_USER_AGENT'), 0, 255);
        $ip        = request()->ip();
        $path      = request()->url(true);
    
        Utils::systemErrorLog($username, $data, $ip, $useragent, $modulename, $path);
    }    
}
