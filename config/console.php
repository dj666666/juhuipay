<?php

// +----------------------------------------------------------------------
// | 控制台配置
// +----------------------------------------------------------------------
return [
    // 指令定义
    'commands' => [
        'app\admin\command\Crud',
        'app\admin\command\Menu',
        'app\admin\command\Min',
        'app\admin\command\Addon',
        'app\admin\command\Api',
        'app\command\CheckYdAlipay',
        'app\command\CheckAlipay',
        'app\command\DealData',
        'app\command\CheckPPorder',
    ],
];
