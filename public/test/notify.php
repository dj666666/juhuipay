<?php
//测试时，将来源请求写入到txt文件，方便分析查看



   $entityBody = file_get_contents('php://input');
        
    $sign = $_SERVER["HTTP_X_QF_SIGN"] ? : "";
        
        
        
file_put_contents("callback_log.txt", $entityBody);
echo "success";