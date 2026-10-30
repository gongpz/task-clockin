#!/usr/bin/env bash
# =====================================================================
# 打网页安装包：上传到宝塔 / AMH 解压后打开 /install/ 即可安装
#   bash deploy/make-package.sh [输出目录]      默认输出到 dist/
# 只打 git 里已提交的文件（git archive），不会带上 .env、数据库导出等本地文件。
# 产物：task-clockin-<版本>.zip 和同名 .sha256
# =====================================================================
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUT="${1:-$ROOT/dist}"
cd "$ROOT"
VER="$(tr -d '[:space:]' < VERSION)"
[[ "$VER" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || { echo "VERSION 格式不对：$VER" >&2; exit 1; }
NAME="task-clockin-$VER"
mkdir -p "$OUT"
OUT="$(cd "$OUT" && pwd)"   # 转成绝对路径：后面会 cd 到临时目录再打 zip
TMP="$(mktemp -d)"; trap 'rm -rf "$TMP"' EXIT

# 安装包内容：程序 + 安装向导 + 建表 SQL + 伪静态 + 说明（部署脚本、文档、计划文件不进包）
git archive --format=tar HEAD -- \
  index.php VERSION .env.example .htaccess app install storage \
  deploy/schema.sql deploy/migrations "伪静态-直接粘贴.txt" "deploy/安装说明.txt" \
  | tar -x -C "$TMP"
mv "$TMP/deploy/安装说明.txt" "$TMP/【安装说明】请先看我.txt"
# 运行时目录（git 里只有 .gitkeep）
for d in storage/uploads storage/logs storage/backups; do mkdir -p "$TMP/$d"; done

cmp -s "$TMP/deploy/schema.sql" deploy/schema.sql || { echo "deploy/schema.sql 有未提交的改动，请先提交" >&2; exit 1; }
[ -f "$TMP/install/index.php" ] && [ -f "$TMP/app/bootstrap.php" ] || { echo "安装包不完整" >&2; exit 1; }
[ ! -e "$TMP/.env" ] || { echo "安装包里不能有 .env" >&2; exit 1; }

rm -f "$OUT/$NAME.zip"
( cd "$TMP" && zip -qr -X "$OUT/$NAME.zip" . )
( cd "$OUT" && sha256sum "$NAME.zip" > "$NAME.zip.sha256" )
echo "✔ $OUT/$NAME.zip（$(du -h "$OUT/$NAME.zip" | cut -f1)）"
cat "$OUT/$NAME.zip.sha256"
