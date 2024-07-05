<?php

namespace app\admin\model\thirdacc;

use app\common\model\BaseModel;


class Userqrcode extends BaseModel
{

    // 表名
    protected $name = 'group_qrcode';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;
    
    const STATUS_ON  = 1; //开启
    const STATUS_OFF = 0; //关闭
    
    public function getTypeList()
    {
        return ['0' => __('Type 0'), '1' => __('Type 1')];
    }

    public function getStatusList()
    {
        return ['0' => __('Status 0'), '1' => __('Status 1')];
    }

    public function getIsUseList()
    {
        return ['0' => __('Is_use 0'), '1' => __('Is_use 1')];
    }


    


    public function user()
    {
        return $this->belongsTo('app\admin\model\User', 'user_id', 'id')->joinType('LEFT');
    }
}
