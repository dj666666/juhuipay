<?php

namespace app\admin\model\thirdacc;

use app\common\model\BaseModel;


class Agentacc extends BaseModel
{

    

    

    // 表名
    protected $name = 'agent_acc';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = false;

    // 定义时间戳字段名
    protected $createTime = false;
    protected $updateTime = false;
    protected $deleteTime = false;

    public function getStatusList()
    {
        return ['0' => __('Status 0'), '1' => __('Status 1')];
    }

    public function agent()
    {
        return $this->belongsTo('app\admin\model\agent\Agent', 'agent_id', 'id')->joinType('LEFT');
    }
    
    public function acc()
    {
        return $this->belongsTo('app\admin\model\Acc', 'acc_code', 'code')->joinType('LEFT');
    }
    
    public function getAgentAcc($agent_id){
        $list = $this->where(['agent_id'=>$agent_id,'status'=>1])->select();
        return $list;
    }
}
