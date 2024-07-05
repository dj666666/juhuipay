<?php

namespace app\admin\model\thirdacc;

use app\common\model\BaseModel;


class Tbqrcode extends BaseModel
{

    

    

    // 表名
    protected $name = 'tb_qrcode';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    
    public function getStatusList()
    {
        return ['0' => __('Status 0'), '1' => __('Status 1')];
    }

    public function getPayStatusList()
    {
        return ['0' => __('Pay_status 0'), '1' => __('Pay_status 1')];
    }

    public function getExpireStatusList()
    {
        return ['0' => __('Expire_status 0'), '1' => __('Expire_status 1')];
    }
    
    public function getIsUseList()
    {
        return ['0' => __('Is_use 0'), '1' => __('Is_use 1')];
    }

    public function groupqrcode()
    {
        return $this->belongsTo('app\admin\model\GroupQrcode', 'group_qrcode_id', 'id')->joinType('LEFT');
    }
}
