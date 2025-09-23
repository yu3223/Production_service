# #!/bin/bash

# if [ ! -d "./vendor" ]; then
#     composer install
# fi

# if [ ! -f "./vendor/bin/rr_server" ]; then
#     php spark burner:init RoadRunner
# fi

# php spark burner:start

#!/bin/bash


# 確保 log 資料夾存在
mkdir -p /var/log/supervisor

# 顯示 Supervisor 設定是否存在（方便除錯）
if [ ! -f /etc/supervisor/conf.d/supervisord.conf ]; then
    echo "[錯誤] 找不到 supervisord 設定檔"
    exit 1
fi

echo "[start_service.sh] 啟動 Supervisor..."
# 啟動 supervisord（-n 表示不要背景化）
exec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/supervisord.conf

