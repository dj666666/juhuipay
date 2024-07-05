<?php

namespace app\user\model\order;

use app\common\model\BaseModel;


class Order extends BaseModel
{

    

    

    // 表名
    protected $name = 'order';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'status_text',
        'ordertime_text',
        'order_type_text',
        'is_callback_text',
        'callback_time_text',
        'expire_time_text',
        'is_resetorder_text'
    ];
    

    
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


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getOrdertimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['ordertime']) ? $data['ordertime'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getOrderTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['order_type']) ? $data['order_type'] : '');
        $list = $this->getOrderTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getIsCallbackTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['is_callback']) ? $data['is_callback'] : '');
        $list = $this->getIsCallbackList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getCallbackTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['callback_time']) ? $data['callback_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getExpireTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['expire_time']) ? $data['expire_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getIsResetorderTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['is_resetorder']) ? $data['is_resetorder'] : '');
        $list = $this->getIsResetorderList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    protected function setOrdertimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    protected function setCallbackTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    protected function setExpireTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
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
