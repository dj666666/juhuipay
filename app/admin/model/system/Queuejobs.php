<?php

namespace app\admin\model\system;

use app\common\model\BaseModel;


class Queuejobs extends BaseModel
{

    

    

    // 表名
    protected $name = 'queue_jobs';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [
        'reserve_time_text',
        'available_time_text',
        'create_time_text'
    ];
    

    



    public function getReserveTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['reserve_time']) ? $data['reserve_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getAvailableTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['available_time']) ? $data['available_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }


    public function getCreateTimeTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['create_time']) ? $data['create_time'] : '');
        return is_numeric($value) ? date("Y-m-d H:i:s", $value) : $value;
    }

    protected function setReserveTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    protected function setAvailableTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }

    protected function setCreateTimeAttr($value)
    {
        return $value === '' ? null : ($value && !is_numeric($value) ? strtotime($value) : $value);
    }


}
