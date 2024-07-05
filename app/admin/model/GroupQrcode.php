<?php

namespace app\admin\model;

use think\Model;

class GroupQrcode extends Model
{
    // 表名
    protected $name = 'group_qrcode';
    
    const STATUS_ON  = 1; //开启
    const STATUS_OFF = 0; //关闭

    const ONlINE  = 1; //云端在线
    const OFFLINE = 0; //云端掉线

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = true;

    // 定义时间戳字段名
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

}
