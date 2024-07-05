<?php

namespace app\admin\model\daifu;

use app\common\model\BaseModel;
use think\model\concern\SoftDelete;


class Dforder extends BaseModel
{
    use SoftDelete;

    // 表名
    protected $name = 'df_order';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
    protected $deleteTime = 'deletetime';

    const ORDER_STATUS_COMPLETE = 1; //完成
    const ORDER_STATUS_DEALING = 2; //处理中
    const ORDER_STATUS_FAIL = 3; //驳回
    const ORDER_STATUS_CZ = 4; //冲正

    public function getStatusList()
    {
        return ['1' => __('Status 1'), '2' => __('Status 2'), '3' => __('Status 3'), '4' => __('Status 4')];
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
        return $this->belongsTo('app\admin\model\User', 'user_id', 'id');
    }


    public function merchant()
    {
        return $this->belongsTo('app\admin\model\merchant\Merchant', 'mer_id', 'id');
    }


    public function agent()
    {
        return $this->belongsTo('app\admin\model\agent\Agent', 'agent_id', 'id');
    }
}
