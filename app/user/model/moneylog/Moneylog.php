<?php

namespace app\user\model\moneylog;

use app\common\model\BaseModel;


class Moneylog extends BaseModel
{

    

    

    // 表名
    protected $name = 'money_log';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'type_text',
        'create_time_text',
        'update_time_text',
        'is_automatic_text',
        'is_recharge_text'
    ];
    

    
    public function getTypeList()
    {
        return ['0' => __('Type 0'), '1' => __('Type 1'), '2' => __('Type 2'), '3' => __('Type 3')];
    }

    public function getIsAutomaticList()
    {
        return ['0' => __('Is_automatic 0'), '1' => __('Is_automatic 1')];
    }

    public function getIsRechargeList()
    {
        return ['0' => __('Is_recharge 0'), '1' => __('Is_recharge 1')];
    }


    public function getTypeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['type']) ? $data['type'] : '');
        $list = $this->getTypeList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getCreateTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['create_time']) ? $data['create_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getUpdateTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['update_time']) ? $data['update_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getIsAutomaticTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['is_automatic']) ? $data['is_automatic'] : '');
        $list = $this->getIsAutomaticList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getIsRechargeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['is_recharge']) ? $data['is_recharge'] : '');
        $list = $this->getIsRechargeList();
        return isset($list[$value]) ? $list[$value] : '';
    }

    protected function setCreateTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    protected function setUpdateTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }


    public function merchant()
    {
        return $this->belongsTo('app\admin\model\merchant\Merchant', 'mer_id', 'id')->joinType('LEFT');
    }
}
