<?php

namespace app\api\controller;

use app\common\controller\Api;
use think\facade\Db;
use think\facade\Config;
use app\common\library\Utils;
use app\common\library\Accutils;
use app\common\library\Rsa;
use think\cache\driver\Redis;
use think\facade\Log;
use think\facade\Queue;
use app\common\controller\Jobs;
use think\Request;
use fast\Random;

/**
 * 通道跳转服务处理
 */
class AccService extends Api
{
    
    
    public function handleAcc($acc_code){
        switch ($variable) {
            case '1007':
                $data = $this->alipayUid($order, $qrcode);
                break;
            case '1009':
                $data = $this->alipayXhb();
                break;
            case '1010':
                $data = $this->alipayFz();
                break;
            case '1011':
                $data = $this->weixinGm();
                break;
            case '1012':
                $data = $this->alipayHc();
                break;
            default:
                // code...
                break;
        }
        
        return $data;
    }
    
    
    /**
     * 
     * 支付宝uid 小额0-1000
     * 
     * $order 订单信息
     * $qrcode 通道挂码信息
     * 
     * 
     */
     
    public function alipayUid($order, $qrcode){
        
        return $data;
    }
    
    public function alipayXhb(){
        
    }
    
    public function weixinGm(){
        
    }
    
    public function alipayHc(){
        
    }
}