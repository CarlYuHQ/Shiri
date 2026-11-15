#!/bin/bash
# 拾日 启动脚本
# 已注册 launchd 时走 kickstart；未注册时手动拉起三个进程
BASE=/Users/carl/WorkBuddy/2026-11-06-12-16-22
RT=$BASE/runtime
U=$(id -u)
M=/opt/homebrew/opt/mariadb/bin
P=/opt/homebrew/opt/php@8.3
N=/opt/homebrew/opt/nginx/bin
mkdir -p "$RT/logs" "$RT/run"

if launchctl print "gui/$U/local.shiri.nginx" >/dev/null 2>&1; then
  echo "模式：launchd 托管"
  for f in mariadb phpfpm nginx; do
    if launchctl print "gui/$U/local.shiri.$f" >/dev/null 2>&1; then
      launchctl kickstart "gui/$U/local.shiri.$f" 2>/dev/null && echo "  $f 已启动/已在运行"
    fi
  done
else
  echo "模式：手动拉起（未注册 launchd）"
  echo "[1/3] MariaDB..."
  # 探活看 pid + socket 文件，不看端口：本机从 2026-11-15 起 127.0.0.1:3306 被
  # 整机拒连（内核直接回 RST，连接到不了数据库进程），用 /dev/tcp 探会永远判成
  # 「没在跑」，于是对着已经占着端口的实例再拉一个，报一堆看不懂的错。
  MDB_PID=$(cat "$RT/run/mariadb.pid" 2>/dev/null || true)
  if [ -n "$MDB_PID" ] && kill -0 "$MDB_PID" 2>/dev/null && [ -S /tmp/mariadb_shiri.sock ]; then
    echo "      已在运行"
  else
    nohup $M/mariadbd --datadir=/opt/homebrew/var/mysql --port=3306 --bind-address=127.0.0.1 \
      --socket=/tmp/mariadb_shiri.sock --pid-file="$RT/run/mariadb.pid" \
      --log-error="$RT/logs/mariadb-error.log" >/dev/null 2>&1 &
    for i in $(seq 1 30); do
      $M/mariadb-admin --socket=/tmp/mariadb_shiri.sock -u carl ping >/dev/null 2>&1 && break
      sleep 1
    done
    $M/mariadb-admin --socket=/tmp/mariadb_shiri.sock -u carl ping >/dev/null 2>&1 \
      && echo "      已启动" || { echo "      启动失败，见 $RT/logs/mariadb-error.log"; exit 1; }
  fi
  echo "[2/3] php-fpm..."
  if [ -f "$RT/run/php-fpm.pid" ] && kill -0 "$(cat "$RT/run/php-fpm.pid")" 2>/dev/null; then
    echo "      已在运行"
  else
    $P/sbin/php-fpm -y "$RT/php-fpm.conf" && echo "      已启动"
  fi
  echo "[3/3] nginx..."
  if (exec 3<>/dev/tcp/127.0.0.1/8080) 2>/dev/null; then
    echo "      已在运行"
  else
    $N/nginx -c "$RT/nginx.conf" -p "$RT" && echo "      已启动"
  fi
fi

echo
echo "拾日 -> http://localhost:8080"
