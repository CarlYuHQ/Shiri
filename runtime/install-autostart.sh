#!/bin/bash
# 注册拾日为登录/开机自启服务（launchd）
# 用法：在你自己的终端里执行  bash runtime/install-autostart.sh
#
# 做四件事：
#   1. 停掉手动拉起的三个进程（否则端口被占，launchd 拉起来的会一直失败）
#   2. 注销历史版本留下的 local.* 标签并归档它们的 plist（避免与新标签重复拉起）
#   3. 用 runtime/launchagents/ 模板生成 ~/Library/LaunchAgents/local.shiri.*.plist 并注册
#   4. 删掉改名期留下的兼容软链，验证 http://localhost:8080
set -u
RT="$(cd "$(dirname "$0")" && pwd)"
BASE="$(dirname "$RT")"
U=$(id -u)
N=/opt/homebrew/opt/nginx/bin
LA="$HOME/Library/LaunchAgents"
mkdir -p "$LA" "$RT/logs" "$RT/run"

stop_nginx() {
  if [ -f "$RT/run/nginx.pid" ] && $N/nginx -c "$RT/nginx.conf" -p "$RT" -s stop 2>/dev/null; then
    echo "      nginx 已停"
    return
  fi
  # 兜底：pid 文件丢了或对不上时，按「谁占着 8080」来杀（只会命中本项目这一个）
  local pids
  pids=$(lsof -nP -tiTCP:8080 -sTCP:LISTEN 2>/dev/null || true)
  if [ -n "$pids" ]; then
    kill $pids 2>/dev/null || true
    echo "      nginx 已强停（pid 文件对不上时的兜底）"
  else
    echo "      nginx 未在运行"
  fi
}

echo "[1/4] 停止当前手动启动的服务..."
stop_nginx
[ -f "$RT/run/php-fpm.pid" ] && kill "$(cat "$RT/run/php-fpm.pid")" 2>/dev/null && echo "      php-fpm 已停"
[ -f "$RT/run/mariadb.pid" ] && kill "$(cat "$RT/run/mariadb.pid")" 2>/dev/null && echo "      mariadb 已停"

echo "[2/4] 注销历史版本留下的 local.* 标签..."
# 不写死旧标签名：凡是以 local. 开头又不是 local.shiri. 的，都是本项目历史版本的。
for L in $(launchctl list 2>/dev/null | awk '{print $3}' | grep '^local\.'); do
  case "$L" in
    local.shiri.*) ;;
    *) launchctl bootout "gui/$U/$L" 2>/dev/null && echo "      旧标签 $L 已注销" ;;
  esac
done
for f in mariadb phpfpm nginx; do
  launchctl bootout "gui/$U/local.shiri.$f" 2>/dev/null
done
mkdir -p "$RT/tmp/legacy-launchagents"
for p in "$LA"/local.*.plist; do
  [ -e "$p" ] || continue
  case "$p" in
    *local.shiri.*) ;;
    *) mv "$p" "$RT/tmp/legacy-launchagents/" && echo "      已归档 $(basename "$p")" ;;
  esac
done
sleep 4

echo "[3/4] 注册 LaunchAgent（local.shiri.*）..."
for f in mariadb phpfpm nginx; do
  sed "s#__BASE__#$BASE#g" "$RT/launchagents/local.shiri.$f.plist" > "$LA/local.shiri.$f.plist"
  if launchctl bootstrap "gui/$U" "$LA/local.shiri.$f.plist" 2>/dev/null; then
    echo "      $f 已注册"
  else
    echo "      $f 注册失败（可查看 ~/Library/LaunchAgents/local.shiri.$f.plist）"
  fi
done

echo "[4/4] 清理改名期留下的兼容软链..."
for s in "$BASE"/*; do
  [ -L "$s" ] && rm -f "$s" && echo "      已删除软链 $(basename "$s")"
done

sleep 8
echo
echo -n "验证 http://localhost:8080 -> "
curl -s -o /dev/null -w "%{http_code}\n" --max-time 20 http://localhost:8080/
echo "完成。之后登录/开机自动启动，进程崩溃会被自动拉起。"
