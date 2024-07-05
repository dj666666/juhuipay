<?php

namespace app\user\model\thirdacc;

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

    // 追加属性
    protected $append = [
        'status_text',
        'pay_status_text',
        'expire_status_text',
        'create_time_text',
        'expire_time_text'
    ];
    

    public function getIsUseList()
    {
        return ['0' => __('Is_use 0'), '1' => __('Is_use 1')];
    }
    
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


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getPayStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['pay_status']) ? $data['pay_status'] : '');
        $list = $this->getPayStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getExpireStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['expire_status']) ? $data['expire_status'] : '');
        $list = $this->getExpireStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }


    public function getCreateTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['create_time']) ? $data['create_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getExpireTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['expire_time']) ? $data['expire_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    protected function setCreateTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    protected function setExpireTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }


    public function groupqrcode()
    {
        return $this->belongsTo('app\admin\model\GroupQrcode', 'group_qrcode_id', 'id')->joinType('LEFT');
    }
}
