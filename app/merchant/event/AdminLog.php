<?php

namespace app\merchant\event;

class AdminLog
{
    public function handle()
    {
        if (request()->isPost()) {
            \app\merchant\model\AdminLog::record();
        }
    }
}
