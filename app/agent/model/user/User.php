<?php

namespace app\agent\model\user;

use app\common\model\BaseModel;


class User extends BaseModel
{
    // 表名
    protected $name = 'user';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    protected $deleteTime = false;

    public function getStatusList()
    {
        return ['normal' => __('Normal'), 'hidden' => __('Hidden')];
    }
    public function getRefreshList()
    {
        return ['0' => __('Is_refresh 0'), '1' => __('Is_refresh 1')];
    }
    public function getThirdHxList()
    {
        return ['0' => __('Is_third_hx 0'), '1' => __('Is_third_hx 1')];
    }
    
    public function agent()
    {
        return $this->belongsTo('app\admin\model\agent\Agent', 'agent_id', 'id')->joinType('LEFT');
    }

    public function group()
    {
        return $this->belongsTo('app\admin\model\UserGroup', 'group_id', 'id')->joinType('LEFT');
    }
}
