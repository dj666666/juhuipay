<?php

namespace app\admin\model\user;

use app\common\model\BaseModel;


class Userrate extends BaseModel
{

    

    

    // 表名
    protected $name = 'user_rate';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [

    ];
    

    







    public function acc()
    {
        return $this->belongsTo('app\admin\model\thirdacc\Acc', 'acc_code', 'code')->joinType('LEFT');
    }
}
