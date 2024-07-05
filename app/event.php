<?php

// 事件定义文件
return [
    'bind' => [
    ],

    'listen' => [
        'AppInit'  => [],
        'HttpRun'  => [],
        'HttpEnd'  => [],
        'LogLevel' => [],
        'LogWrite' => [],
        'SysErrorLog' => [app\common\event\SystemErrorLog::class],
        'OrderError' => [app\common\event\OrderErrorLog::class],
        'RobotLog' => [app\common\event\RobotLog::class],
    ],

    'subscribe' => [
    ],
];
