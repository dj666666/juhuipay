<?php

namespace app\admin\model\systemlog;

use app\common\model\BaseModel;


class Ordererrorlog extends BaseModel
{

    // 表名
    protected $name = 'order_error_log';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [

    ];

    /**
     * 提单日志记录
     *
     * @param $data
     * @return void
     */
    public static function subOrderRecord($data){
        self::create([
            'agent_id'     => isset($data['agent_id']) ? $data['agent_id'] : 0,
            'out_trade_no' => $data['out_trade_no'],
            'trade_no'     => $data['trade_no'],
            'message'      => $data['msg'],
            'content'      => is_array($data['content']) ? json_encode($data['content'], JSON_UNESCAPED_UNICODE) : $data['content'],
            'ip'           => request()->ip(),
        ]);


    }






}
