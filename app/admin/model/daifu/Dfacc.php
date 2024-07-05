<?php

namespace app\admin\model\daifu;

use app\common\model\BaseModel;


class Dfacc extends BaseModel
{

    

    

    // 表名
    protected $name = 'df_acc';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [

    ];
    

    public function getStatusList()
    {
        return ['0' => __('Status 0'), '1' => __('Status 1')];
    }







}
