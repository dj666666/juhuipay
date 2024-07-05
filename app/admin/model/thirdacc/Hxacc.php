<?php

namespace app\admin\model\thirdacc;
use app\common\model\BaseModel;

class Hxacc extends BaseModel {

    // 表名
    protected $name = 'hx_acc';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'create_time';
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [

    ];


    
}
