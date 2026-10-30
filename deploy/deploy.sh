#!/usr/bin/env bash
# =====================================================================
# 任务打卡系统 · 服务器端一键部署（升级）脚本
#
# 用法（在服务器上，root 执行；install.sh / setup-server.sh 会把它装成 task-deploy）：
#   task-deploy <站点名> [git 引用]      例：task-deploy dkamj   /  task-deploy dkamj fix/xxx
#   task-deploy <站点名> --rollback      回到上一次部署前的代码
#   task-deploy <站点名> --status        看当前部署版本和最近记录
#   环境变量：FORCE=1 同版本也重新部署；SKIP_HEALTH=1 跳过健康检查（install.sh 首次部署用）
#
# 站点配置在 /etc/task-deploy/<站点名>.conf（install.sh 生成，或照 deploy/sites/site.conf.example 手写）。
#
# 做的事情（任何一步失败都会停下来，不会只更新一半）：
#   1. 加锁，防止两个人同时部署
#   2. 拉取 GitHub 仓库到缓存目录，检出指定版本
#   3. PHP 语法检查（全部在缓存目录里，失败不影响线上）
#   4. 备份：数据库导出 + 将要被覆盖的代码（保留最近 KEEP_BACKUPS 份）
#   5. 同步代码：只覆盖 app/、index.php、VERSION 等程序文件；
#      不动 .env、.user.ini、storage（上传图片、日志）、服务器上的 .bak 文件
#   6. 执行 deploy/migrations/ 里还没执行过的 SQL → 重载 PHP-FPM 清 OPcache
#   7. 健康检查（后台登录页 200），失败自动回滚代码
#   8. 记录部署台账 /www/backup/task-deploy/<站点名>/deploy.log
# =====================================================================
set -Eeuo pipefail

SITE="${1:-}"
ARG2="${2:-}"
CONF_DIR="/etc/task-deploy"

die() { echo "❌ $*" >&2; exit 1; }
log() { echo "[$(date '+%H:%M:%S')] $*"; }

[ -n "$SITE" ] || die "用法：task-deploy <站点名> [git 引用 | --rollback | --status]"
[[ "$SITE" =~ ^[A-Za-z0-9_-]+$ ]] || die "站点名只能是字母、数字、下划线、横线"
CONF="$CONF_DIR/$SITE.conf"
[ -f "$CONF" ] || die "找不到站点配置 $CONF"
# shellcheck disable=SC1090
source "$CONF"

: "${SITE_DIR:?配置缺少 SITE_DIR}"
: "${REPO_URL:?配置缺少 REPO_URL}"
DEFAULT_REF="${DEFAULT_REF:-main}"
PHP_BIN="${PHP_BIN:-/usr/bin/php}"
WEB_USER="${WEB_USER:-www}"
PHP_FPM_RELOAD="${PHP_FPM_RELOAD:-}"
HEALTH_URL="${HEALTH_URL:-}"
HEALTH_HOST="${HEALTH_HOST:-}"
KEEP_BACKUPS="${KEEP_BACKUPS:-5}"
DB_BACKUP="${DB_BACKUP:-1}"
REPO_SSH_KEY="${REPO_SSH_KEY:-}"

STATE_DIR="/www/backup/task-deploy/$SITE"
CACHE_DIR="$STATE_DIR/repo"
LEDGER="$STATE_DIR/deploy.log"
VERSION_FILE="$STATE_DIR/current_version"
APPLIED_FILE="$STATE_DIR/migrations.applied"
mkdir -p "$STATE_DIR"
chmod 700 "$STATE_DIR"
[ -n "$REPO_SSH_KEY" ] && export GIT_SSH_COMMAND="ssh -i $REPO_SSH_KEY -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new"

# ---------- 只同步这些程序文件（来自仓库），其余一律不动 ----------
SYNC_DIRS=(app)                      # 整目录镜像（仓库里删掉的文件，线上也删）
SYNC_FILES=(index.php VERSION .env.example)  # 单个文件覆盖
# 镜像目录时排除：服务器上的各种手工备份（排除的文件不会被删）
RSYNC_EXCLUDES=(--exclude '*.bak' --exclude '*.bak-*' --exclude '*.bak_*' --exclude '*.bak.*')

ledger() { echo "$(date '+%F %T')  $*" >> "$LEDGER"; }
current_ver() { cat "$VERSION_FILE" 2>/dev/null || echo "未知"; }
envval() { grep -E "^$1=" "$SITE_DIR/.env" | tail -n 1 | cut -d= -f2- | sed -e 's/^["'\'']//' -e 's/["'\'']$//'; }

# 连接本站数据库（参数取自站点 .env；密码走环境变量，不出现在进程列表里）
db_cli() { # db_cli mysql|mysqldump 其他参数...
  local bin="$1"; shift
  local sock; sock="$(envval DB_SOCKET)"
  local conn=(-u"$(envval DB_USER)")
  if [ -n "$sock" ]; then conn+=(--socket="$sock"); else conn+=(-h"$(envval DB_HOST)" -P"$(envval DB_PORT)"); fi
  MYSQL_PWD="$(envval DB_PASS)" "$bin" "${conn[@]}" "$@"
}

if [ "$ARG2" = "--status" ]; then
  echo "站点：$SITE ($SITE_DIR)"
  echo "当前版本：$(current_ver)"
  echo "最近部署："; tail -n 8 "$LEDGER" 2>/dev/null || echo "（无记录）"
  exit 0
fi

# ---------- 1. 加锁 ----------
exec 9>"$STATE_DIR/.lock"
flock -n 9 || die "$SITE 正在被另一个部署任务使用，请稍后再试"

[ -f "$SITE_DIR/.env" ] || die "$SITE_DIR/.env 不存在：新服务器请先运行 install.sh"
[ -n "$(envval DB_NAME)" ] || die "$SITE_DIR/.env 缺少 DB_NAME"

reload_php() { [ -n "$PHP_FPM_RELOAD" ] && eval "$PHP_FPM_RELOAD" >/dev/null 2>&1 || true; }

restore_from() {
  local B="$1"
  [ -d "$B/code" ] || die "备份不完整：$B"
  log "回滚代码到 $B"
  for d in "${SYNC_DIRS[@]}"; do
    [ -d "$B/code/$d" ] && rsync -a --delete "${RSYNC_EXCLUDES[@]}" "$B/code/$d/" "$SITE_DIR/$d/"
  done
  for f in "${SYNC_FILES[@]}"; do [ -f "$B/code/$f" ] && cp -p "$B/code/$f" "$SITE_DIR/$f"; done
  [ -f "$B/version" ] && cp "$B/version" "$VERSION_FILE"
  reload_php
}

if [ "$ARG2" = "--rollback" ]; then
  LAST=$(ls -1d "$STATE_DIR"/backup_* 2>/dev/null | sort | tail -n 1 || true)
  [ -n "$LAST" ] || die "没有可回滚的备份"
  restore_from "$LAST"
  ledger "ROLLBACK  -> $(current_ver)  (from $LAST)  数据库未回滚，如需请手工导入 $LAST/db.sql.gz"
  log "✅ 已回滚到 $(current_ver)。数据库没有自动回滚，迁移改过的表结构如需恢复，用 $LAST/db.sql.gz"
  exit 0
fi

REF="${ARG2:-$DEFAULT_REF}"

# ---------- 2. 拉取代码 ----------
log "拉取 $REPO_URL ($REF)"
if [ ! -d "$CACHE_DIR/.git" ]; then
  git clone --quiet "$REPO_URL" "$CACHE_DIR"
fi
git -C "$CACHE_DIR" remote set-url origin "$REPO_URL"   # 配置里换了仓库地址（如私有改公开）时跟着换
git -C "$CACHE_DIR" fetch --quiet --prune origin '+refs/heads/*:refs/remotes/origin/*' '+refs/tags/*:refs/tags/*'
if git -C "$CACHE_DIR" rev-parse --verify --quiet "origin/$REF" >/dev/null; then
  TARGET="origin/$REF"
else
  TARGET="$REF"
fi
git -C "$CACHE_DIR" -c advice.detachedHead=false checkout --quiet --force "$TARGET"
git -C "$CACHE_DIR" clean -fdq
SHA=$(git -C "$CACHE_DIR" rev-parse --short HEAD)
SUBJECT=$(git -C "$CACHE_DIR" log -1 --format=%s)
APP_VER=$(tr -d '[:space:]' < "$CACHE_DIR/VERSION" 2>/dev/null || true)
VERSION="${APP_VER:+v$APP_VER }$REF@$SHA"
log "目标版本 $VERSION  $SUBJECT"
if [ "$(current_ver)" = "$VERSION" ] && [ "${FORCE:-0}" != "1" ]; then
  log "已经是 $VERSION，无需部署（要强制重新部署：FORCE=1 task-deploy $SITE $REF）"
  exit 0
fi

# 部署脚本自更新：仓库里的版本更新了，就先换成新版再从头执行（只换一次，防止循环）
REPO_SELF="$CACHE_DIR/deploy/deploy.sh"
SELF="$(readlink -f "${BASH_SOURCE[0]}")"
if [ -z "${TASK_SELF_UPDATED:-}" ] && [ -f "$REPO_SELF" ] && ! cmp -s <(tr -d '\r' < "$REPO_SELF") "$SELF"; then
  log "部署脚本有新版本，先更新自身"
  tr -d '\r' < "$REPO_SELF" > "$SELF.new" && chmod 755 "$SELF.new" && mv "$SELF.new" "$SELF"
  flock -u 9
  TASK_SELF_UPDATED=1 exec "$SELF" "$SITE" "$REF"
fi

SRC="$CACHE_DIR"
[ -f "$SRC/index.php" ] && [ -f "$SRC/app/bootstrap.php" ] || die "仓库里找不到 index.php / app/bootstrap.php，不像任务打卡系统"

# ---------- 3. 语法检查（在缓存目录里进行，失败不影响线上） ----------
log "PHP 语法检查"
BAD=$(cd "$SRC" && find app index.php -name '*.php' ! -name '*.bak*' -print0 | xargs -0 -n 50 -P 2 "$PHP_BIN" -l 2>&1 | grep -v '^No syntax errors' || true)
[ -z "$BAD" ] || { echo "$BAD"; die "PHP 语法错误，线上未改动"; }

# 待执行的数据库迁移：deploy/migrations/*.sql，按文件名顺序，执行过的记在 $APPLIED_FILE
MIG_DIR="$SRC/deploy/migrations"
if [ ! -f "$APPLIED_FILE" ]; then
  # 第一次用本脚本部署：现有迁移视为已执行（站点接入时数据库就是最新结构）
  ls -1 "$MIG_DIR"/*.sql 2>/dev/null | xargs -r -n1 basename > "$APPLIED_FILE" || true
  log "首次接入，已把现有 $(wc -l < "$APPLIED_FILE") 个迁移记为已执行"
fi
PENDING=()
for m in "$MIG_DIR"/*.sql; do
  [ -f "$m" ] || continue
  grep -qxF "$(basename "$m")" "$APPLIED_FILE" || PENDING+=("$m")
done

# ---------- 4. 备份 ----------
STAMP=$(date +%Y%m%d_%H%M%S)
BK="$STATE_DIR/backup_$STAMP"
mkdir -p "$BK/code"
log "备份到 $BK"
for d in "${SYNC_DIRS[@]}"; do [ -d "$SITE_DIR/$d" ] && cp -a "$SITE_DIR/$d" "$BK/code/"; done
for f in "${SYNC_FILES[@]}"; do [ -f "$SITE_DIR/$f" ] && cp -p "$SITE_DIR/$f" "$BK/code/$f"; done
current_ver > "$BK/version"
if [ "$DB_BACKUP" = "1" ]; then
  db_cli mysqldump --single-transaction --routines --no-tablespaces "$(envval DB_NAME)" | gzip > "$BK/db.sql.gz" \
    || { rm -rf "$BK"; die "数据库备份失败，已停止（线上未改动）"; }
  log "数据库 $(envval DB_NAME) 已备份 $(du -h "$BK/db.sql.gz" | cut -f1)"
fi
# 只保留最近 KEEP_BACKUPS 份
ls -1d "$STATE_DIR"/backup_* 2>/dev/null | sort | head -n -"$KEEP_BACKUPS" | xargs -r rm -rf

# ---------- 5. 上线 ----------
ROLLED=0
on_fail() {
  [ "$ROLLED" = "1" ] && return
  ROLLED=1
  echo "❌ 部署出错，自动回滚代码……" >&2
  restore_from "$BK"
  ledger "FAILED    $VERSION  已自动回滚到 $(current_ver)  备份 $BK"
  echo "已回滚到部署前的代码。数据库如有迁移改动，备份在 $BK/db.sql.gz" >&2
}
trap on_fail ERR

log "同步程序文件"
for d in "${SYNC_DIRS[@]}"; do
  rsync -a --checksum --delete "${RSYNC_EXCLUDES[@]}" --chown="$WEB_USER:$WEB_USER" "$SRC/$d/" "$SITE_DIR/$d/"
done
for f in "${SYNC_FILES[@]}"; do
  [ -f "$SRC/$f" ] && rsync -a --checksum --chown="$WEB_USER:$WEB_USER" "$SRC/$f" "$SITE_DIR/$f"
done
for d in storage storage/logs storage/uploads storage/uploads/checkins storage/backups; do
  [ -d "$SITE_DIR/$d" ] || install -d -o "$WEB_USER" -g "$WEB_USER" "$SITE_DIR/$d"
done

if [ "${#PENDING[@]}" -gt 0 ]; then
  for m in "${PENDING[@]}"; do
    log "数据库迁移 $(basename "$m")"
    db_cli mysql --default-character-set=utf8mb4 "$(envval DB_NAME)" < "$m"
    basename "$m" >> "$APPLIED_FILE"
  done
fi

log "重载 PHP-FPM（清 OPcache）"
reload_php
echo "$VERSION" > "$VERSION_FILE"

# ---------- 6. 健康检查 ----------
if [ -n "$HEALTH_URL" ] && [ "${SKIP_HEALTH:-0}" != "1" ]; then
  log "健康检查 $HEALTH_URL/admin/login"
  HDR=(); [ -n "$HEALTH_HOST" ] && HDR=(-H "Host: $HEALTH_HOST")
  HEALTHY=0
  for _ in 1 2 3 4 5 6 7 8 9 10; do   # PHP-FPM reload 是异步的，最多等 10 秒
    sleep 1
    CODE=$(curl -sk -o "$STATE_DIR/health.html" -w '%{http_code}' --max-time 20 "${HDR[@]}" "$HEALTH_URL/admin/login" || echo 000)
    [ "$CODE" = "200" ] && grep -q 'name="password"' "$STATE_DIR/health.html" && { HEALTHY=1; break; }
  done
  if [ "$HEALTHY" != "1" ]; then
    echo "后台登录页返回 $CODE，或页面里没有登录表单（期望 200 + 登录表单）" >&2
    false   # 触发 ERR → 自动回滚
  fi
  log "后台登录页 $CODE"
fi
trap - ERR

ledger "OK        $VERSION  $SUBJECT  备份 $BK"
log "✅ $SITE 已部署 $VERSION"
