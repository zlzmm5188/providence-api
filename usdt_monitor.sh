#!/bin/bash
# USDT监控脚本 - 每分钟由cron调用，内部每3秒执行一次（共20次）

cd /www/wwwroot/api.4kp3l0iq.top

# 循环20次，每次间隔3秒（共60秒）
for i in {1..20}; do
    /usr/bin/php -r "
require 'vendor/autoload.php';
\$app = new think\App();
\$app->initialize();
\app\common\service\UsdtService::monitorTransactions();
" >> /www/wwwlogs/usdt_monitor.log 2>&1

    # 最后一次不需要等待
    if [ $i -lt 20 ]; then
        sleep 3
    fi
done
