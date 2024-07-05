<?php

namespace app\admin\model\thirdacc;

use app\common\model\BaseModel;


class Tbshop extends BaseModel
{

    // 表名
    protected $name = 'tb_shop';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;

    // 定义时间戳字段名
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';
    protected $deleteTime = false;
    
    public function getStatusList()
    {
        return ['0' => __('Status 0'), '1' => __('Status 1')];
    }

    public function user()
    {
        return $this->belongsTo('app\admin\model\User', 'user_id', 'id')->joinType('LEFT');
    }
}
