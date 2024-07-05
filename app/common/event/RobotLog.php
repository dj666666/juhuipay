<?php

namespace app\common\event;
use app\common\library\Utils;
use app\common\model\Config;


class RobotLog{
    
    public function handle($data){
        
        //登录提醒
        if (Config::get('site.login_send_robot') == 0){
            return false;
        }
        
        $modulename = app()->http->getName();

        switch ($modulename) {
            case 'user':
                $module  = '码商';
                break;
            case 'merchant':
                $module  = '商户';
                break;
            case 'agent':
                $module  = '代理';
                break;
            case 'admin':
                $module  = '后台';
                break;
            case 'api':
                $module  = '接口';
                break;
            default:
                $module  ='Unknown';
                break;
        }
        $data = $data->post();
        
        $ip = request()->ip();
        $address = Utils::getClientAddress($ip);
        
        
        $data['module']     = $module;
        $data['ip']         = $ip;
        $data['ip_address'] = $address;
        
        Utils::sendTgBotGroupNotice($data);
    }    
}
