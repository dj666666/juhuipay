## 聚汇
- 部署时以下文件需要改名去掉dev
app_dev.php site_develop.php gateway_dev.php

- 云端协议监控

`*/1 * * * * cd /www/wwwroot/xiguathirdpay && /www/server/php/73/bin/php think checkydalipay >> /www/server/cron/xiguacheckydalipay.log 2>&1`

- 队列处理订单（超时，回调）

`php think queue:work --queue checkorder`

- 定时任务命令
  `php think checkalipay start`