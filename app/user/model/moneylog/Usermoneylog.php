<?php

namespace app\user\model\moneylog;

use app\common\model\BaseModel;


class Usermoneylog extends BaseModel
{

    // 表名
    protected $name = 'user_money_log';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    public function getTypeList()
    {
        return ['1' => __('Type 1')];
        //return ['0' => __('Type 0'), '1' => __('Type 1'), '3' => __('Type 3')];
    }

    public function getIsAutomaticList()
    {
        return ['0' => __('Is_automatic 0'), '1' => __('Is_automatic 1')];
    }

    public function user()
    {
        return $this->belongsTo('app\admin\model\user\User', 'user_id', 'id')->joinType('LEFT');
    }
}
