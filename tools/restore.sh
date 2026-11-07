#!/bin/bash
# 把上游发行包（3.13.2）铺到 app/，同时保留本仓库自己的文件。
# 铺完必须再跑 tools/whitelabel.py --from <旧品牌名>，把上游标识换回 shiri。
#
# 用法：
#   bash tools/restore.sh ~/Downloads/上游发行包.tar.gz
#   bash tools/restore.sh /path/to/已解压/源码目录
#
# 做法：解压官方包 → rsync --ignore-existing 到 app/。
#       已经存在的文件（= 本项目改过的）不会被覆盖，官方独有的文件（= 我们没动过的）补齐。
set -euo pipefail

if [[ $# -lt 1 ]]; then
    echo "用法：bash tools/restore.sh <官方 3.13.2 tarball 或解压目录>" >&2
    exit 1
fi

SRC=$1
REPO=$(cd "$(dirname "$0")/.." && pwd)
TARGET="$REPO/app"

[[ -d "$TARGET" ]] || { echo "找不到 $TARGET" >&2; exit 1; }

CLEANUP=""
if [[ -f "$SRC" ]]; then
    WORK=$(mktemp -d)
    CLEANUP=$WORK
    trap 'rm -rf "$CLEANUP"' EXIT

    echo "解压 $SRC …"
    tar -xzf "$SRC" -C "$WORK"
    SRC=$(find "$WORK" -maxdepth 1 -mindepth 1 -type d | head -1)
    [[ -n "$SRC" ]] || { echo "压缩包里没有目录" >&2; exit 1; }
fi

[[ -f "$SRC/artisan" || -d "$SRC/app/Domain" ]] || {
    echo "$SRC 看起来不是上游源码目录（缺 artisan / app/Domain）" >&2; exit 1
}

echo "从 $SRC 补齐到 $TARGET …"
# vendor / .env / 运行物都不从官方包带过来
rsync -a \
    --ignore-existing \
    --exclude 'vendor/' \
    --exclude 'storage/' \
    --exclude 'userfiles/' \
    --exclude 'config/.env' \
    --exclude 'bootstrap/cache/' \
    "$SRC"/ "$TARGET"/

cat <<'EOF'

✅ 文件已就位。还差三步：

  1) 数据库配置
     cp app/config/sample.env app/config/.env
     # 填 LEAN_DB_HOST / LEAN_DB_USER / LEAN_DB_PASSWORD / LEAN_DB_DATABASE

  2) 依赖
     (cd app && composer install --no-dev)

  3) 起服务
     bash runtime/start.sh

本项目自定义的表（zp_recurring / zp_recurring_log / zp_rb_prefs）由代码自建，不用导入。
EOF
