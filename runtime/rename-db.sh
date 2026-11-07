#!/bin/bash
# 把数据库与数据库账号改成 shiri（旧名从 .env 里读，脚本里不写死）。
#
# 为什么需要你自己跑：MariaDB 的 root 只允许 unix_socket 认证（OS 用户 root），
# 沙箱里拿不到 sudo，所以这一步必须在你的终端里执行。
#
# 用法：bash runtime/rename-db.sh
#
# 做五件事，任何一步失败就停：
#   1. 建 shiri 库 → 把所有表 RENAME 过去（只动元数据，不搬数据，秒级）
#   2. 建 shiri 账号并授权
#   3. 改 app/config/.env 里的库名 / 用户名 / 口令
#   4. 清缓存，让新的 .env 生效
#   5. 验证 http://localhost:8080 仍是 200，最后才删旧库与旧账号
set -euo pipefail

RT="$(cd "$(dirname "$0")" && pwd)"
BASE="$(dirname "$RT")"
ENV_FILE="$BASE/app/config/.env"
M=/opt/homebrew/opt/mariadb/bin/mariadb

read_env() {  # read_env KEY -> 取 .env 里 KEY 的值（去引号、去注释）
  sed -nE "s/^[[:space:]]*$1[[:space:]]*=[[:space:]]*'?([^'#]*[^'#[:space:]])'?.*/\1/p" "$ENV_FILE" | head -1
}

NEW_DB=shiri
NEW_USER=shiri
NEW_PASS=shiri_local_2026

OLD_DB=$(read_env LEAN_DB_DATABASE)
OLD_USER=$(read_env LEAN_DB_USER)

if [ -z "$OLD_DB" ] || [ -z "$OLD_USER" ]; then
  echo "读不到 .env 里的 LEAN_DB_DATABASE / LEAN_DB_USER，先检查 $ENV_FILE" >&2
  exit 1
fi
if [ "$OLD_DB" = "$NEW_DB" ] && [ "$OLD_USER" = "$NEW_USER" ]; then
  echo "数据库已经叫 $NEW_DB、账号已经是 $NEW_USER，不用改。"
  exit 0
fi

SOCK=/tmp/mariadb_shiri.sock
[ -S "$SOCK" ] || SOCK=$(ls -t /tmp/mariadb_*.sock 2>/dev/null | head -1)

ADMIN=("$M" --socket="$SOCK" -u root)
if ! "${ADMIN[@]}" -e "select 1" >/dev/null 2>&1; then
  echo "root 直连不行，改用 sudo（会要一次密码）..."
  ADMIN=(sudo "$M" --socket="$SOCK" -u root)
fi
"${ADMIN[@]}" -e "select 1" >/dev/null

echo "[1/5] $OLD_DB -> $NEW_DB ..."
if "${ADMIN[@]}" -N -e "show databases like '$NEW_DB';" | grep -q .; then
  echo "      $NEW_DB 已存在，跳过建库"
else
  "${ADMIN[@]}" -e "create database \`$NEW_DB\` character set utf8mb4 collate utf8mb4_unicode_ci;"
  SQL=$(mktemp)
  "${ADMIN[@]}" -N -e "select concat('rename table \`$OLD_DB\`.\`', table_name, '\` to \`$NEW_DB\`.\`', table_name, '\`;') from information_schema.tables where table_schema='$OLD_DB';" > "$SQL"
  echo "      搬 $(wc -l < "$SQL" | tr -d ' ') 张表"
  "${ADMIN[@]}" < "$SQL"
  rm -f "$SQL"
fi

echo "[2/5] 账号 $OLD_USER -> $NEW_USER ..."
"${ADMIN[@]}" <<SQL
create user if not exists '$NEW_USER'@'localhost' identified by '$NEW_PASS';
alter user '$NEW_USER'@'localhost' identified by '$NEW_PASS';
grant all privileges on \`$NEW_DB\`.* to '$NEW_USER'@'localhost';
flush privileges;
SQL
echo "      完成"

echo "[3/5] 改 app/config/.env ..."
# 备份放 runtime/tmp（不改名成功就删掉，免得旧凭据在磁盘上留两份）
BAK="$RT/tmp/env.bak.$(date +%Y%m%d%H%M%S)"
cp "$ENV_FILE" "$BAK"
sed -i '' \
  -e "s/^LEAN_DB_USER = .*/LEAN_DB_USER = '$NEW_USER'                          # Database username/" \
  -e "s/^LEAN_DB_PASSWORD = .*/LEAN_DB_PASSWORD = '$NEW_PASS'                              # Database password/" \
  -e "s/^LEAN_DB_DATABASE = .*/LEAN_DB_DATABASE = '$NEW_DB'                              # Database name/" \
  "$ENV_FILE"
grep -E "^LEAN_DB_(USER|DATABASE) " "$ENV_FILE"

echo "[4/5] 清缓存 ..."
rm -f "$BASE/app/bootstrap/cache/"*.php \
      "$BASE/app/storage/framework/viewPaths.php" \
      "$BASE/app/storage/framework/composerPaths.php" \
      "$BASE/app/storage/framework/views/"*.php
rm -rf "$BASE/app/storage/framework/cache/installation/data"

echo "[5/5] 验证 ..."
CODE=$(curl -s -o /dev/null -w "%{http_code}" --max-time 20 http://localhost:8080/calendar/showTodos || true)
echo -n "      /calendar/showTodos -> $CODE"
if [ "$CODE" = "200" ]; then
  echo "  ✓"
  "${ADMIN[@]}" -e "drop database if exists \`$OLD_DB\`; drop user if exists '$OLD_USER'@'localhost'; flush privileges;"
  rm -f "$BAK"
  echo "旧库、旧账号、旧 .env 备份都已删除。"
else
  echo "  ✗ 新库不通，旧库和旧账号都留着；.env 的备份在 $BAK"
  echo "    看 app/storage/logs/ 里的最新日志"
  exit 1
fi
