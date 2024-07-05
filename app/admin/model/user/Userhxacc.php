<?php

namespace app\admin\model\user;

use app\common\model\BaseModel;


class Userhxacc extends BaseModel
{

    // 表名
    protected $name = 'user_hx_acc';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'create_time';
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [

    ];

    public function user(){
        return $this->belongsTo('app\admin\model\user\User', 'user_id', 'id')->joinType('LEFT');
    }

    public function hxacc(){
        return $this->belongsTo('app\admin\model\thirdacc\Hxacc', 'hx_acc_id', 'id')->joinType('LEFT');
    }

}
