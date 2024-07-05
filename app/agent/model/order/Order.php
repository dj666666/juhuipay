<?php

namespace app\agent\model\order;

use app\common\model\BaseModel;
use think\model\concern\SoftDelete;

class Order extends BaseModel
{

    use SoftDelete;

    // 表名
    protected $name = 'order';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
    protected $deleteTime = 'deletetime';
    
    
    public function getStatusList()
    {
        return ['1' => __('Status 1'), '2' => __('Status 2'), '3' => __('Status 3')];
    }

    public function getOrderTypeList()
    {
        return ['0' => __('Order_type 0'), '1' => __('Order_type 1')];
    }

    public function getIsCallbackList()
    {
        return ['0' => __('Is_callback 0'), '1' => __('Is_callback 1'), '2' => __('Is_callback 2')];
    }

    public function getIsResetorderList()
    {
        return ['0' => __('Is_resetorder 0'), '1' => __('Is_resetorder 1')];
    }

    public function user()
    {
        return $this->belongsTo('app\admin\model\User', 'user_id', 'id')->joinType('LEFT');
    }


    public function merchant()
    {
        return $this->belongsTo('app\admin\model\merchant\Merchant', 'mer_id', 'id')->joinType('LEFT');
    }


    public function agent()
    {
        return $this->belongsTo('app\admin\model\agent\Agent', 'agent_id', 'id')->joinType('LEFT');
    }
}
