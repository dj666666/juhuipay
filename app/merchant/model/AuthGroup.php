<?php

namespace app\merchant\model;

use app\common\model\BaseModel;

class AuthGroup extends BaseModel
{
    protected $name = 'merchant_auth_group';

    // 开启自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';
    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    public function getNameAttr($value, $data)
    {
        return __($value);
    }
}
