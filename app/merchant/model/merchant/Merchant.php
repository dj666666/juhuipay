<?php

namespace app\merchant\model\merchant;

use app\common\model\BaseModel;


class Merchant extends BaseModel
{
    // 表名
    protected $name = 'merchant';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    public function getCallBackList()
    {
        return ['0' => __('CallbackStatus 0'), '1' => __('CallbackStatus 1')];
    }

    public function getDiyRateList()
    {
        return ['0' => __('DiyRate 0'), '1' => __('DiyRate 1')];
    }

    public function getStatusList()
    {
        return ['normal' => __('Normal'), 'hidden' => __('Hidden')];
    }

    public function getIsFcList()
    {
        return ['0' => __('Is_fc 0'), '1' => __('Is_fc 1')];
    }


    public function agent()
    {
        return $this->belongsTo('app\admin\model\agent\Agent', 'agent_id', 'id')->joinType('LEFT');
    }
}
