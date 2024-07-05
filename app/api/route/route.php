<?php

use think\facade\Route;


Route::get('gateway/order/:order_sn', 'Gateway/order');
Route::get('gateway/topay/:order_sn', 'Gateway/toHnapay');

Route::post('notify/alipay', 'Notify/alipay');




