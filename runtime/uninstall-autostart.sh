#!/bin/bash
# 取消拾日的开机自启，并停止服务
U=$(id -u)
for f in mariadb phpfpm nginx; do
  launchctl bootout "gui/$U/local.shiri.$f" 2>/dev/null && echo "$f 已移除"
done
echo "完成。plist 仍保留在 ~/Library/LaunchAgents，想彻底清除："
echo "  rm ~/Library/LaunchAgents/local.shiri.*.plist"
