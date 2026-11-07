#!/bin/bash
# 拾日 停止脚本
# 已注册 launchd 时用 bootout（否则 KeepAlive 会立刻把进程拉回来，看起来像停不掉）
RT=/Users/carl/WorkBuddy/2026-11-06-12-16-22/runtime
U=$(id -u)

if launchctl print "gui/$U/local.shiri.nginx" >/dev/null 2>&1; then
  echo "模式：launchd 托管，正在 bootout（会同时取消开机自启）"
  for f in nginx phpfpm mariadb; do
    launchctl bootout "gui/$U/local.shiri.$f" 2>/dev/null && echo "  $f 已停止"
  done
  echo
  echo "重新启用：bash $RT/install-autostart.sh"
else
  echo "模式：手动进程，正在 kill"
  [ -f "$RT/run/nginx.pid" ] && /opt/homebrew/opt/nginx/bin/nginx -c "$RT/nginx.conf" -p "$RT" -s stop 2>/dev/null && echo "  nginx 已停止"
  [ -f "$RT/run/php-fpm.pid" ] && kill "$(cat "$RT/run/php-fpm.pid")" 2>/dev/null && echo "  php-fpm 已停止"
  [ -f "$RT/run/mariadb.pid" ] && kill "$(cat "$RT/run/mariadb.pid")" 2>/dev/null && echo "  mariadb 已停止"
fi
echo "数据保留在 /opt/homebrew/var/mysql 与 $RT/logs"
