#!/usr/bin/env bash
# =====================================================================
# 任务打卡系统 · 新服务器一键安装
#
# 适用：全新的 Linux 服务器（云服务器或机房本地服务器均可），
#       Debian 11/12、Ubuntu 20.04/22.04/24.04、CentOS/Rocky/Alma 8/9，
#       已装宝塔面板的服务器也能用（会自动使用宝塔的 PHP / Nginx / MySQL）。
#
# 用法（root）：
#   bash install.sh                 交互式问答，一步步填
#   bash install.sh --config 文件    按配置文件无人值守安装（变量名同下面 ask 的第一个参数）
#
# 装完后：
#   - 站点目录、数据库（含表结构）、.env、管理员账号、Nginx 站点、HTTPS（可选）全部就绪
#   - /etc/task-deploy/<站点名>.conf 写好，以后升级只需 task-deploy <站点名>
#
# 本脚本可以重复运行：已有的数据库 / .env / 管理员 / Nginx 站点不会被覆盖。
# =====================================================================
set -Eeuo pipefail

die() { echo "❌ $*" >&2; exit 1; }
log() { echo -e "\033[36m[$(date '+%H:%M:%S')]\033[0m $*"; }
ok()  { echo -e "\033[32m✔\033[0m $*"; }

[ "$(id -u)" = "0" ] || die "请用 root 运行"

CONFIG_FILE=""
while [ $# -gt 0 ]; do
  case "$1" in
    --config) CONFIG_FILE="$2"; shift 2 ;;
    *) die "未知参数 $1" ;;
  esac
done

ask() { # ask 变量名 "提示" "默认值"
  local __var="$1" __prompt="$2" __def="${3:-}" __val
  if [ -n "${!__var:-}" ]; then return; fi
  if [ -n "$CONFIG_FILE" ]; then printf -v "$__var" '%s' "$__def"; return; fi
  read -r -p "$__prompt${__def:+ [$__def]}: " __val
  printf -v "$__var" '%s' "${__val:-$__def}"
}
ask_secret() {
  local __var="$1" __prompt="$2" __val
  if [ -n "${!__var:-}" ]; then return; fi
  if [ -n "$CONFIG_FILE" ]; then return; fi
  read -r -s -p "$__prompt（留空自动生成）: " __val; echo
  printf -v "$__var" '%s' "$__val"
}
randpw() { head -c 300 /dev/urandom | LC_ALL=C tr -dc 'A-Za-z0-9' | cut -c1-"${1:-20}"; }

if [ -n "$CONFIG_FILE" ]; then
  [ -f "$CONFIG_FILE" ] || die "配置文件不存在：$CONFIG_FILE"
  # shellcheck disable=SC1090
  source "$CONFIG_FILE"
fi

echo "================ 任务打卡系统 · 安装 ================"
ask SITE        "站点名（英文，用作目录名和配置名，如 dkamj）" "dkamj"
[[ "$SITE" =~ ^[A-Za-z0-9_-]+$ ]] || die "站点名只能是字母、数字、下划线、横线"
ask DOMAIN      "访问域名（没有域名就填服务器 IP）" "$(hostname -I 2>/dev/null | awk '{print $1}')"
[[ "$DOMAIN" =~ ^[A-Za-z0-9.-]+$ ]] || die "域名格式不对"
ask REPO_URL    "GitHub 仓库地址" "git@github.com:gongpz/task-clockin.git"
ask DEFAULT_REF "部署分支" "main"
[[ "$DEFAULT_REF" =~ ^[A-Za-z0-9._/-]{1,100}$ ]] || die "分支名不合法"
ask SITE_TITLE  "系统名称（显示在页面标题）" "任务打卡系统"
ask ADMIN_USERNAME "管理员登录账号" "admin"
[[ "$ADMIN_USERNAME" =~ ^[A-Za-z0-9_.@-]{3,50}$ ]] || die "管理员账号只能是 3～50 位字母、数字和 _ . @ -"
ask_secret ADMIN_PASSWORD "管理员密码"
ask DB_NAME     "数据库名" "task_${SITE//-/_}"
ask DB_USER     "数据库用户名" "task_${SITE//-/_}"
[[ "$DB_NAME$DB_USER" =~ ^[A-Za-z0-9_]+$ ]] || die "数据库名、用户名只能是字母、数字、下划线"
ask_secret DB_PASS "数据库密码"
ask HTTPS       "是否申请免费 HTTPS 证书（需要域名已解析到本机，填 y/n）" "n"
ask HTTPS_EMAIL "证书通知邮箱" "admin@$DOMAIN"
ask SITE_ROOT   "站点安装目录" "/www/wwwroot/$SITE"

GEN_ADMIN_PW=0; [ -z "${ADMIN_PASSWORD:-}" ] && GEN_ADMIN_PW=1
ADMIN_PASSWORD="${ADMIN_PASSWORD:-$(randpw 14)}"
[ "${#ADMIN_PASSWORD}" -ge 8 ] || die "管理员密码至少 8 位"
DB_PASS="${DB_PASS:-$(randpw 24)}"
[[ "$DB_PASS" =~ ^[A-Za-z0-9_.@%+=-]+$ ]] || die "数据库密码只能用字母、数字和 _ . @ % + = -（会写进 SQL 和 .env）"

# ---------------- 1. 环境检测 / 安装依赖 ----------------
log "检测服务器环境"
. /etc/os-release
OS_ID="$ID"; OS_LIKE="${ID_LIKE:-}"
BT=0; [ -d /www/server/panel ] && BT=1 && ok "检测到宝塔面板，使用宝塔的 PHP / Nginx / MySQL"

pick_php() {
  for p in /www/server/php/83/bin/php /www/server/php/82/bin/php /www/server/php/81/bin/php "$(command -v php 2>/dev/null || true)"; do
    [ -n "$p" ] && [ -x "$p" ] && "$p" -r 'exit(PHP_VERSION_ID >= 80100 ? 0 : 1);' 2>/dev/null && { echo "$p"; return; }
  done
}
PHP_BIN="$(pick_php || true)"
have_nginx() { command -v nginx >/dev/null || [ -x /www/server/nginx/sbin/nginx ]; }

if [ -z "$PHP_BIN" ] || ! have_nginx || ! command -v mysql >/dev/null; then
  [ "$BT" = "1" ] && die "宝塔上缺少 PHP 8.1+ / Nginx / MySQL，请先在宝塔「软件商店」安装：Nginx、MySQL 5.7 或 8.0、PHP 8.2"
  log "安装 PHP 8.2、Nginx、MariaDB、Git（首次约 2～5 分钟）"
  if [[ "$OS_ID" =~ (debian|ubuntu) || "$OS_LIKE" =~ debian ]]; then
    export DEBIAN_FRONTEND=noninteractive
    apt-get update -qq
    apt-get install -y -qq ca-certificates curl gnupg lsb-release git rsync >/dev/null
    if ! apt-cache show php8.2-fpm >/dev/null 2>&1; then
      if [ "$OS_ID" = "ubuntu" ]; then
        apt-get install -y -qq software-properties-common >/dev/null && add-apt-repository -y ppa:ondrej/php >/dev/null
      else
        curl -fsSL https://packages.sury.org/php/apt.gpg | gpg --dearmor -o /usr/share/keyrings/sury-php.gpg
        echo "deb [signed-by=/usr/share/keyrings/sury-php.gpg] https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/sury-php.list
      fi
      apt-get update -qq
    fi
    apt-get install -y -qq nginx mariadb-server php8.2-fpm php8.2-cli php8.2-mysql php8.2-mbstring php8.2-xml \
      php8.2-curl php8.2-zip php8.2-gd php8.2-intl >/dev/null
    PHP_FPM_SVC="php8.2-fpm"
  elif [[ "$OS_ID" =~ (centos|rocky|almalinux|rhel|ol) || "$OS_LIKE" =~ rhel ]]; then
    dnf install -y -q epel-release git rsync >/dev/null
    dnf install -y -q "https://rpms.remirepo.net/enterprise/remi-release-$(rpm -E %rhel).rpm" >/dev/null || true
    dnf module reset -y -q php >/dev/null; dnf module enable -y -q php:remi-8.2 >/dev/null
    dnf install -y -q nginx mariadb-server php php-fpm php-cli php-mysqlnd php-mbstring php-xml php-curl php-zip php-gd php-intl >/dev/null
    PHP_FPM_SVC="php-fpm"
  else
    die "不认识的系统 $PRETTY_NAME，请装好 PHP 8.1+、Nginx、MySQL/MariaDB 后再运行"
  fi
  systemctl enable --now nginx mariadb "$PHP_FPM_SVC" >/dev/null 2>&1 || true
  PHP_BIN="$(pick_php)"
fi
[ -n "$PHP_BIN" ] || die "找不到 PHP 8.1+"
ok "PHP：$PHP_BIN（$("$PHP_BIN" -r 'echo PHP_VERSION;')）"
for ext in pdo_mysql mbstring curl gd zip; do
  "$PHP_BIN" -m | grep -qi "^$ext$" || die "PHP 缺少扩展 $ext（宝塔：软件商店 → PHP → 设置 → 安装扩展）"
done
for c in git rsync flock curl; do command -v $c >/dev/null || die "缺少 $c，请先安装"; done

# Web 运行用户、PHP-FPM、Nginx 配置目录
if [ "$BT" = "1" ]; then
  WEB_USER=www
  PHPV=$(basename "$(dirname "$(dirname "$PHP_BIN")")")
  PHP_FPM_RELOAD="/etc/init.d/php-fpm-$PHPV reload"
  FPM_SOCK="/tmp/php-cgi-$PHPV.sock"
  NGINX_CONF_DIR=/www/server/panel/vhost/nginx
  NGINX_BIN=/www/server/nginx/sbin/nginx
  NGINX_RELOAD="/etc/init.d/nginx reload"
else
  WEB_USER=$(ps -o user= -C php-fpm8.2,php-fpm 2>/dev/null | grep -v root | head -n1 || true); WEB_USER="${WEB_USER:-www-data}"
  id "$WEB_USER" >/dev/null 2>&1 || { useradd -r -s /sbin/nologin www; WEB_USER=www; }
  PHP_FPM_RELOAD="systemctl reload ${PHP_FPM_SVC:-php8.2-fpm}"
  FPM_SOCK=$(ls /run/php/php8.2-fpm.sock /run/php-fpm/www.sock 2>/dev/null | head -n1 || true)
  [ -n "$FPM_SOCK" ] || die "找不到 PHP-FPM 的 socket（/run/php/php8.2-fpm.sock）"
  NGINX_CONF_DIR=/etc/nginx/conf.d
  NGINX_BIN=nginx
  NGINX_RELOAD="systemctl reload nginx"
fi

# ---------------- 2. 访问 GitHub 仓库的钥匙（私有仓库用只读 Deploy Key） ----------------
KEY_DIR=/etc/task-deploy/keys
mkdir -p "$KEY_DIR"; chmod 700 /etc/task-deploy "$KEY_DIR"
REPO_SSH_KEY=""
if [[ "$REPO_URL" == git@* ]]; then
  REPO_SSH_KEY="$KEY_DIR/github_repo"
  [ -f "$REPO_SSH_KEY" ] || ssh-keygen -q -t ed25519 -N '' -C "task-deploy@$(hostname)" -f "$REPO_SSH_KEY"
  export GIT_SSH_COMMAND="ssh -i $REPO_SSH_KEY -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new"
  if ! git ls-remote "$REPO_URL" HEAD >/dev/null 2>&1; then
    echo
    echo "────────────────────────────────────────────────────────────"
    echo " 本服务器还不能读取私有仓库。请把下面这行公钥加到 GitHub："
    echo "   仓库 → Settings → Deploy keys → Add deploy key（不要勾 Allow write access）"
    echo
    cat "$REPO_SSH_KEY.pub"
    echo "────────────────────────────────────────────────────────────"
    [ -n "$CONFIG_FILE" ] && die "添加 Deploy key 后重新运行本脚本"
    read -r -p "加好后按回车继续……" _
    git ls-remote "$REPO_URL" HEAD >/dev/null 2>&1 || die "仍然读不到仓库，请检查 Deploy key"
  fi
  ok "可以读取仓库"
fi

# ---------------- 3. 拉代码 ----------------
STATE_DIR="/www/backup/task-deploy/$SITE"
CACHE="$STATE_DIR/repo"
mkdir -p "$STATE_DIR"; chmod 700 "$STATE_DIR"
[ -d "$CACHE/.git" ] || git clone --quiet "$REPO_URL" "$CACHE"
git -C "$CACHE" fetch --quiet origin
git -C "$CACHE" -c advice.detachedHead=false checkout --quiet --force "origin/$DEFAULT_REF"
[ -f "$CACHE/deploy/schema.sql" ] || die "仓库里没有 deploy/schema.sql"

# ---------------- 4. 数据库 ----------------
log "准备数据库 $DB_NAME"
MYSQL_ROOT=(mysql)
if [ "$BT" = "1" ]; then
  BT_ROOT_PW=$(cd /www/server/panel && python3 -c "import sqlite3;c=sqlite3.connect('data/default.db');print(c.execute(\"select mysql_root from config\").fetchone()[0])" 2>/dev/null || true)
  [ -n "$BT_ROOT_PW" ] && MYSQL_ROOT=(env MYSQL_PWD="$BT_ROOT_PW" mysql -uroot)
fi
"${MYSQL_ROOT[@]}" -e "select 1" >/dev/null 2>&1 || die "无法用 root 连接 MySQL。宝塔用户请在「数据库」页面确认 root 密码已设置"
DB_EXISTS=$("${MYSQL_ROOT[@]}" -N -e "select count(*) from information_schema.schemata where schema_name='${DB_NAME}'")
if [ "$DB_EXISTS" = "0" ]; then
  "${MYSQL_ROOT[@]}" -e "CREATE DATABASE \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
  ok "已新建数据库"
else
  ok "数据库已存在，沿用（不会清空）"
fi
# 用户和授权每次都确保存在；已有用户不改密码（.env 里是旧密码）
for h in localhost 127.0.0.1; do
  "${MYSQL_ROOT[@]}" -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'${h}' IDENTIFIED BY '${DB_PASS}'; GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'${h}';"
done
"${MYSQL_ROOT[@]}" -e "FLUSH PRIVILEGES;"
"${MYSQL_ROOT[@]}" --default-character-set=utf8mb4 "$DB_NAME" < "$CACHE/deploy/schema.sql"
ok "表结构已就绪（$("${MYSQL_ROOT[@]}" -N -e "select count(*) from information_schema.tables where table_schema='${DB_NAME}'") 张表）"

# ---------------- 5. 站点目录 + .env ----------------
log "准备站点目录 $SITE_ROOT"
mkdir -p "$SITE_ROOT"/storage/{logs,uploads/checkins,backups}
if [ ! -f "$SITE_ROOT/.env" ]; then
  cat > "$SITE_ROOT/.env" <<ENV
APP_DEBUG=0
APP_TIMEZONE=Asia/Shanghai
ADMIN_PAGE_SHOW_HEADER=1
SESSION_COOKIE_SECURE=auto
CHECKIN_MAX_IMAGE_MB=5

DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=$DB_NAME
DB_USER=$DB_USER
DB_PASS=$DB_PASS
DB_CHARSET=utf8mb4
DB_SOCKET=
ENV
  ok ".env 已生成"
else
  ok ".env 已存在，沿用（不会覆盖）"
  envval() { grep -E "^$1=" "$SITE_ROOT/.env" | tail -n 1 | cut -d= -f2-; }
  DB_NAME="$(envval DB_NAME)"
fi
# open_basedir（宝塔习惯）：只允许访问本站目录和 /tmp。只用 .user.ini，不用 Nginx 的 PHP_VALUE（会污染共用的 PHP-FPM 池）
if [ ! -f "$SITE_ROOT/.user.ini" ]; then
  # Debian/Ubuntu 的 PHP 会话存在 /var/lib/php/sessions，不放进 open_basedir 会登录不上
  SESS_DIR="$("$PHP_BIN" -r 'echo ini_get("session.save_path");' 2>/dev/null | sed 's/.*;//')"
  BASEDIR="$SITE_ROOT/:/tmp/"
  [ -n "$SESS_DIR" ] && [ "$SESS_DIR" != "/tmp" ] && [ -d "$SESS_DIR" ] && BASEDIR="$BASEDIR:$SESS_DIR/"
  echo "open_basedir=$BASEDIR" > "$SITE_ROOT/.user.ini"
fi
chown -R "$WEB_USER:$WEB_USER" "$SITE_ROOT"
chown root:"$WEB_USER" "$SITE_ROOT/.env" "$SITE_ROOT/.user.ini"
chmod 640 "$SITE_ROOT/.env"; chmod 644 "$SITE_ROOT/.user.ini"

# ---------------- 6. 部署配置 + 首次部署 ----------------
log "写部署配置 /etc/task-deploy/$SITE.conf"
tr -d '\r' < "$CACHE/deploy/deploy.sh" > /usr/local/bin/task-deploy && chmod 755 /usr/local/bin/task-deploy
cat > "/etc/task-deploy/$SITE.conf" <<EOF2
# 由 install.sh 生成 $(date '+%F %T')
SITE_DIR="$SITE_ROOT"
REPO_URL="$REPO_URL"
REPO_SSH_KEY="$REPO_SSH_KEY"
DEFAULT_REF="$DEFAULT_REF"
PHP_BIN="$PHP_BIN"
WEB_USER="$WEB_USER"
PHP_FPM_RELOAD="$PHP_FPM_RELOAD"
HEALTH_URL="http://127.0.0.1"
HEALTH_HOST="$DOMAIN"
KEEP_BACKUPS=5
DB_BACKUP=1
EOF2
chmod 600 "/etc/task-deploy/$SITE.conf"

log "首次部署代码"
HEALTH_URL="" FORCE=1 /usr/local/bin/task-deploy "$SITE" "$DEFAULT_REF"

# ---------------- 7. 管理员 ----------------
INIT_OUT=$(env TASK_SITE_ROOT="$SITE_ROOT" TASK_ADMIN_USERNAME="$ADMIN_USERNAME" TASK_ADMIN_PASSWORD="$ADMIN_PASSWORD" \
  TASK_SITE_TITLE="$SITE_TITLE" "$PHP_BIN" "$CACHE/deploy/init-admin.php" 2>&1) || die "初始化管理员失败：$INIT_OUT"
if [ "$INIT_OUT" = "EXISTS" ]; then
  ok "管理员 $ADMIN_USERNAME 已存在，密码没有改动"
  GEN_ADMIN_PW=0; ADMIN_PASSWORD="（沿用原密码）"
else
  ok "管理员 $ADMIN_USERNAME 已创建"
fi

# ---------------- 8. Nginx 站点 ----------------
NGINX_FILE="$NGINX_CONF_DIR/$DOMAIN.conf"
if [ ! -f "$NGINX_FILE" ]; then
  log "生成 Nginx 站点 $NGINX_FILE"
  sed -e "s#__DOMAIN__#$DOMAIN#g" -e "s#__ROOT__#$SITE_ROOT#g" -e "s#__FPM__#unix:$FPM_SOCK#g" \
    "$CACHE/deploy/nginx-site.conf.tpl" > "$NGINX_FILE"
  # Debian 自带的 default 站点会抢 80 端口的默认请求；只在本脚本新装的 Nginx 上删除它
  [ "$BT" = "0" ] && [ -L /etc/nginx/sites-enabled/default ] && [ "$(ls /etc/nginx/conf.d | wc -l)" = "1" ] && rm -f /etc/nginx/sites-enabled/default
  "$NGINX_BIN" -t >/dev/null 2>&1 || { "$NGINX_BIN" -t || true; rm -f "$NGINX_FILE"; die "Nginx 配置检查失败，已撤回"; }
  eval "$NGINX_RELOAD"
  ok "Nginx 已生效"
else
  ok "Nginx 站点 $NGINX_FILE 已存在，沿用（不改动）"
fi

SCHEME=http
if [ "$HTTPS" = "y" ]; then
  if [ "$BT" = "1" ]; then
    echo "⚠ 宝塔服务器请在面板「网站 → $DOMAIN → SSL」一键申请证书"
  elif [ -d "/etc/letsencrypt/live/$DOMAIN" ]; then
    SCHEME=https; ok "证书已存在"
  else
    command -v certbot >/dev/null || { command -v apt-get >/dev/null && apt-get install -y -qq certbot python3-certbot-nginx >/dev/null || dnf install -y -q certbot python3-certbot-nginx >/dev/null; }
    if certbot --nginx -n --agree-tos -m "$HTTPS_EMAIL" -d "$DOMAIN" --redirect; then SCHEME=https; ok "HTTPS 已开启"
    else echo "⚠ 证书申请失败（域名可能还没解析到本机，或 443 没放行），之后可运行：certbot --nginx -d $DOMAIN"; fi
  fi
fi

# ---------------- 9. 健康检查 ----------------
CODE=$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 -H "Host: $DOMAIN" "http://127.0.0.1/admin/login" || echo 000)
[ "$CODE" = "200" ] && ok "后台登录页正常（200）" || echo "⚠ 后台登录页返回 $CODE，请检查 Nginx / PHP-FPM"
CODE=$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 -H "Host: $DOMAIN" "http://127.0.0.1/.env" || echo 000)
[ "$CODE" = "404" ] || echo "⚠ /.env 返回 $CODE（应为 404），请检查 Nginx 配置"

echo
echo "==================== 安装完成 ===================="
echo " 前台任务页：$SCHEME://$DOMAIN/tasks"
echo " 管理后台：  $SCHEME://$DOMAIN/admin/login"
echo " 管理员账号：$ADMIN_USERNAME"
[ "$GEN_ADMIN_PW" = "1" ] && echo " 管理员密码：$ADMIN_PASSWORD   ← 只显示这一次，请立即登录修改"
echo " 站点目录：  $SITE_ROOT"
echo " 数据库：    $DB_NAME（密码已写入 $SITE_ROOT/.env）"
echo " 以后升级：  task-deploy $SITE            （默认 $DEFAULT_REF 分支）"
echo " 回滚：      task-deploy $SITE --rollback"
echo "=================================================="
