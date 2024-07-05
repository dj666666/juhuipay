<?php

namespace app\agent\event;

class AdminLog
{
    public function handle()
    {
        if (request()->isPost()) {
            \app\agent\model\AdminLog::record();
        }
    }
}
