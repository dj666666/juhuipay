<?php

namespace app\common\event;
use app\common\library\Utils;


class OrderErrorLog{
    
    public function handle($data){
        
        \app\admin\model\systemlog\Ordererrorlog::subOrderRecord($data);
        
    }    
}
