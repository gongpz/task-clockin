#!/usr/bin/env bash
# =====================================================================
# 已经在运行的站点（比如 dk.amjhly.com）接入一键部署：
#   - 安装 /usr/local/bin/task-deploy
#   - 生成读取 GitHub 仓库用的只读 Deploy Key
#   - 生成 /etc/task-deploy/<站点名>.conf（已存在就不动）
#
# 用法（root，在服务器上）：
#   bash setup-server.sh <站点名> <站点目录> <健康检查地址>
#   例：bash setup-server.sh dkamj /www/wwwroot/dkamj https://dk.amjhly.com
# 全新服务器不用这个，直接用 install.sh。
# =====================================================================
set -euo pipefail
die() { echo "❌ $*" >&2; exit 1; }
[ "$(id -u)" = "0" ] || die "请用 root 运行"
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SITE="${1:-}"; SITE_DIR="${2:-}"; HEALTH="${3:-}"
[[ "$SITE" =~ ^[A-Za-z0-9_-]+$ ]] || die "用法：bash setup-server.sh <站点名> <站点目录> <健康检查地址>"
[ -f "$SITE_DIR/index.php" ] && [ -f "$SITE_DIR/.env" ] || die "$SITE_DIR 下没有 index.php / .env，不像已运行的任务打卡站点"
REPO_URL="${REPO_URL:-git@github.com:gongpz/task-clockin.git}"

for c in git rsync flock curl; do command -v $c >/dev/null || die "缺少 $c，请先安装"; done
tr -d '\r' < "$HERE/deploy.sh" > /usr/local/bin/task-deploy && chmod 755 /usr/local/bin/task-deploy
mkdir -p /etc/task-deploy/keys && chmod 700 /etc/task-deploy /etc/task-deploy/keys
echo "✔ 已安装 task-deploy"

KEY=/etc/task-deploy/keys/github_repo
[ -f "$KEY" ] || { ssh-keygen -q -t ed25519 -N '' -C "task-deploy@$(hostname)" -f "$KEY"; echo "✔ 已生成仓库只读钥匙"; }

CONF="/etc/task-deploy/$SITE.conf"
if [ -f "$CONF" ]; then
  echo "✔ $CONF 已存在，沿用"
else
  if [ -d /www/server/panel ]; then
    PHP_BIN=/www/server/php/82/bin/php; WEB_USER=www; RELOAD="/etc/init.d/php-fpm-82 reload"
  else
    PHP_BIN="$(command -v php)"; WEB_USER=www-data; RELOAD="systemctl reload php8.2-fpm"
  fi
  cat > "$CONF" <<CONF
# 由 setup-server.sh 生成 $(date '+%F %T')；字段说明见 deploy/sites/site.conf.example
SITE_DIR="$SITE_DIR"
REPO_URL="$REPO_URL"
REPO_SSH_KEY="$KEY"
DEFAULT_REF="main"
PHP_BIN="$PHP_BIN"
WEB_USER="$WEB_USER"
PHP_FPM_RELOAD="$RELOAD"
HEALTH_URL="$HEALTH"
HEALTH_HOST=""
KEEP_BACKUPS=5
DB_BACKUP=1
CONF
  chmod 600 "$CONF"
  echo "✔ 已生成 $CONF（PHP、运行用户如不对请手改）"
fi

if GIT_SSH_COMMAND="ssh -i $KEY -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new" git ls-remote "$REPO_URL" HEAD >/dev/null 2>&1; then
  echo "✔ 可以读取仓库。以后升级：task-deploy $SITE"
else
  echo
  echo "把下面这行加到 GitHub 仓库 → Settings → Deploy keys（不要勾 Allow write access），然后执行 task-deploy $SITE："
  cat "$KEY.pub"
fi
