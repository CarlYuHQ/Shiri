#!/usr/bin/env python3
"""白标替换：把上游框架的品牌标识换成 Shiri。

本仓库的 PHP 后端是某个上游框架的修改版，代码里原本到处是原品牌名
（命名空间、JSON-RPC 前缀、JS 全局、帮助链接、翻译 key……）。这个脚本把它们统一换成
shiri，包括：

    <Brand>\\        -> Shiri\\           PHP 命名空间、类名、静态调用
    <brand>.rpc.    -> shiri.rpc.        JSON-RPC 方法前缀
    window.<brand>  -> window.shiri      JS 全局（PHP 注入 + 前端 bundle 两端同步）
    <brand>.io      -> shiri.local       帮助/文档链接（站内已无入口，指向本地免误跳）
    <brand>-*       -> shiri-*           日志文件名、id 前缀等

用法（旧品牌名必须自己传，脚本里不存这个字符串）：

    python3 tools/whitelabel.py --from 旧品牌名 --dry-run
    python3 tools/whitelabel.py --from 旧品牌名

升级上游后（tools/restore.sh 铺了一份干净的发行包）再跑一次即可，幂等。

不碰的地方：
    app/storage/**        运行期缓存/日志，清掉即可
    app/userfiles/**      用户上传
    app/config/.env       数据库凭据（库名/账号由 runtime/rename-db.sh 处理）
    *.png/*.woff/*.phar   二进制
    LICENSE / NOTICE.md   许可与署名
"""
import argparse
import os
import re
import sys

BASE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
APP = os.path.join(BASE, "app")

ROOTS = ["app", "bin", "bootstrap", "config", "public", "vendor/composer"]
EXTRA_FILES = ["qd.js"]

SKIP_DIRS = {"storage", "userfiles", ".git", "node_modules", "tmp"}
SKIP_FILES = {".env", "NOTICE.md"}
BINARY_EXT = {
    ".png", ".jpg", ".jpeg", ".gif", ".ico", ".woff", ".woff2", ".ttf",
    ".eot", ".phar", ".zip", ".gz", ".tgz", ".pdf", ".webp", ".mp4", ".mov",
}

NEW = "Shiri"


def build_rules(brand: str):
    """按顺序：先域名（否则 domain 会被后面拆开），再大小写敏感的，最后兜底小写。"""
    low = brand.lower()
    return [
        (re.compile(re.escape(low) + r"\.io"), "shiri.local"),
        (re.compile(re.escape(brand)), NEW),
        (re.compile(re.escape(brand.upper())), NEW.upper()),
        (re.compile(re.escape(low)), NEW.lower()),
    ]


def fix_autoload_prefix(text: str) -> str:
    """autoload_static.php 的 PSR-4 前缀表有两处会跟着命名空间变：
        1. 长度是硬编码的字符数（'Prefix\\' => N），换了前缀必须重算；
        2. 条目按首字母分桶，换了首字母要挪到对应的桶里。
    桶本身只是"这个首字母有没有前缀"的开关，放错桶时 classmap 里的类仍能命中，
    只有 classmap 之外的新类会 500 —— 所以这里必须修对。
    """
    new = NEW
    plen = len(new) + 1  # 含结尾反斜杠
    text = re.sub(r"('" + new + r"\\\\' => )\d+", lambda m: m.group(1) + str(plen), text)

    line = "            '" + new + "\\\\' => " + str(plen) + ",\n"
    anchor = "\n        '" + new[0].upper() + "' =>\n        array (\n"
    if line in text and anchor in text:
        text = text.replace(line, "", 1)
        text = text.replace(anchor, anchor + line, 1)
    return text


def iter_targets():
    for root in ROOTS:
        for dirpath, dirnames, filenames in os.walk(os.path.join(APP, root)):
            dirnames[:] = [d for d in dirnames if d not in SKIP_DIRS]
            for name in filenames:
                if name in SKIP_FILES:
                    continue
                if os.path.splitext(name)[1].lower() in BINARY_EXT:
                    continue
                yield os.path.join(dirpath, name)
    for name in EXTRA_FILES:
        p = os.path.join(APP, name)
        if os.path.exists(p):
            yield p


def main():
    ap = argparse.ArgumentParser(description="把上游品牌标识换成 Shiri")
    ap.add_argument("--from", dest="brand", required=True, metavar="旧品牌名")
    ap.add_argument("--dry-run", action="store_true")
    args = ap.parse_args()

    rules = build_rules(args.brand)
    changed_files = changed_hits = skipped_binary = 0

    for path in iter_targets():
        try:
            # surrogateescape：个别上游文件混着 latin-1 字节（© 之类），
            # 用它读进来还能原样写回去，不然会被当成二进制整份跳过。
            with open(path, "r", encoding="utf-8", errors="surrogateescape") as fh:
                text = fh.read()
        except IsADirectoryError:
            skipped_binary += 1
            continue

        new = text
        hits = 0
        for pattern, repl in rules:
            new, n = pattern.subn(repl, new)
            hits += n
        if "autoload_static.php" in path:
            fixed = fix_autoload_prefix(new)
            if fixed != new:
                new = fixed
                hits += 1

        if hits and new != text:
            changed_files += 1
            changed_hits += hits
            if not args.dry_run:
                with open(path, "w", encoding="utf-8", errors="surrogateescape") as fh:
                    fh.write(new)

    # 文件名/目录名里的品牌名也改（自深向浅，避免边走边改路径失效）
    renamed = []
    for dirpath, dirnames, filenames in os.walk(APP, topdown=False):
        if any(part in SKIP_DIRS for part in dirpath.split(os.sep)):
            continue
        for name in filenames + dirnames:
            if args.brand.lower() not in name.lower():
                continue
            src = os.path.join(dirpath, name)
            new_name = name
            for pattern, repl in rules:
                new_name = pattern.sub(repl, new_name)
            dst = os.path.join(dirpath, new_name)
            if src == dst or not os.path.exists(src):
                continue
            renamed.append((src, dst))
            if not args.dry_run:
                os.rename(src, dst)

    print(f"{'[dry-run] ' if args.dry_run else ''}改了 {changed_files} 个文件、{changed_hits} 处")
    if skipped_binary:
        print(f"跳过 {skipped_binary} 个非文本文件")
    for src, dst in renamed:
        print(f"  重命名 {os.path.relpath(src, BASE)} -> {os.path.relpath(dst, BASE)}")


if __name__ == "__main__":
    main()
