<?php

namespace app\admin\model\statistics;

use app\common\model\BaseModel;


class Merchantstatistics extends BaseModel
{
    // 表名
    protected $name = 'merchant';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    public function getIsFcList()
    {
        return ['0' => __('Is_fc 0'), '1' => __('Is_fc 1')];
    }

}
