<?php

namespace app\user\model\user;

use app\common\model\BaseModel;


class Userrate extends BaseModel
{
    // 表名
    protected $name = 'user_rate';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    public function user()
    {
        return $this->belongsTo('app\user\model\user\User', 'user_id', 'id')->joinType('LEFT');
    }

    public function acc()
    {
        return $this->belongsTo('app\admin\model\thirdacc\acc', 'acc_code', 'acc_code')->joinType('LEFT');
    }
}
