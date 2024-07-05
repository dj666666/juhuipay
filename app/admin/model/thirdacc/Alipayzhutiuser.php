<?php

namespace app\admin\model\thirdacc;

use app\common\model\BaseModel;


class Alipayzhutiuser extends BaseModel
{

    

    

    // 表名
    protected $name = 'alipay_zhuti_user';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [

    ];
    

    







    public function alipayzhuti()
    {
        return $this->belongsTo('app\admin\model\AlipayZhuti', 'zhuti_id', 'id')->joinType('LEFT');
    }


    public function user()
    {
        return $this->belongsTo('app\admin\model\User', 'user_id', 'id')->joinType('LEFT');
    }
}
