<?php

// 容器Provider定义文件
use app\api\ExceptionHandle;

return [
    'think\exception\Handle' => ExceptionHandle::class,
];
