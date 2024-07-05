<?php

namespace app\admin\model\thirdacc;

use app\common\model\BaseModel;


class Useracc extends BaseModel
{
    // 表名
    protected $name = 'user_acc';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'status_text',
        'create_time_text'
    ];
    

    
    public function getStatusList()
    {
        return ['0' => __('Status 0'), '1' => __('Status 1')];
    }


    public function user()
    {
        return $this->belongsTo('app\admin\model\User', 'user_id', 'id')->joinType('LEFT');
    }

    public function acc()
    {
        return $this->belongsTo('app\admin\model\thirdacc\Acc', 'acc_id', 'id')->joinType('LEFT');
    }
}
