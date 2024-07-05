<?php

namespace app\user\event;

class AdminLog
{
    public function handle()
    {
        if (request()->isPost()) {
            \app\user\model\AdminLog::record();
        }
    }
}
