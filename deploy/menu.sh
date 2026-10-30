#!/usr/bin/env bash
# =====================================================================
# 任务打卡系统 · 一键部署菜单（由 deploy/get.sh 拉取仓库后执行，命令说明见 get.sh）
# 放在仓库里而不是 get.sh 里：get.sh 经 CDN 分发有缓存，菜单改了也能立刻生效。
# =====================================================================
set -Eeuo pipefail
# 整个脚本放进函数，curl | bash 下载不完整时不会执行半截
main() {
REPO_URL="${REPO_URL:-https://github.com/gongpz/task-clockin.git}"
BRANCH="${BRANCH:-main}"
CONF_DIR=/etc/task-deploy
INSTALLER=/www/backup/task-deploy/_installer

die() { echo -e "\033[31m❌ $*\033[0m" >&2; exit 1; }
log() { echo -e "\033[36m[$(date '+%H:%M:%S')]\033[0m $*"; }

[ "$(id -u)" = "0" ] || die "请用 root 运行"
# curl | bash 时标准输入是管道，问答要从终端读
TTY=""; if [ -t 0 ]; then TTY=/dev/stdin; elif (exec </dev/tty) 2>/dev/null; then TTY=/dev/tty; fi
ask() { [ -n "$TTY" ] || die "没有可交互的终端，请用 bash -s -- 命令 的方式执行"; read -r -p "$1" REPLY < "$TTY"; }

SITES=(); for c in "$CONF_DIR"/*.conf; do [ -f "$c" ] && SITES+=("$(basename "$c" .conf)"); done
INSTALLED=0; [ "${#SITES[@]}" -gt 0 ] && command -v task-deploy >/dev/null && INSTALLED=1

ensure_git() {
  command -v git >/dev/null && return
  log "安装 git"
  if command -v apt-get >/dev/null; then DEBIAN_FRONTEND=noninteractive apt-get update -qq && apt-get install -y -qq git >/dev/null
  elif command -v dnf >/dev/null; then dnf install -y -q git >/dev/null
  elif command -v yum >/dev/null; then yum install -y -q git >/dev/null
  else die "请先安装 git"; fi
}

# get.sh 已经把仓库拉到 $INSTALLER 并从那里执行本脚本；单独运行本脚本时再拉一次
fetch_installer() {
  [ -n "${TASK_INSTALLER_READY:-}" ] && [ -f "$INSTALLER/deploy/install.sh" ] && return 0
  ensure_git
  mkdir -p "$(dirname "$INSTALLER")"; chmod 700 "$(dirname "$INSTALLER")"
  if [ -d "$INSTALLER/.git" ]; then
    git -C "$INSTALLER" fetch --quiet --depth 1 origin "$BRANCH" && git -C "$INSTALLER" checkout --quiet --force FETCH_HEAD
  else
    git clone --quiet --depth 1 --branch "$BRANCH" "$REPO_URL" "$INSTALLER"
  fi || die "下载不了 $REPO_URL，请检查服务器能否访问 github.com"
}

pick_site() { # pick_site [站点名] → 结果放在 SITE
  SITE="${1:-}"
  if [ -n "$SITE" ]; then [ -f "$CONF_DIR/$SITE.conf" ] || die "本服务器没有站点 $SITE（已有：${SITES[*]:-无}）"; return; fi
  [ "${#SITES[@]}" -gt 0 ] || die "本服务器还没有安装任务打卡系统"
  if [ "${#SITES[@]}" = "1" ]; then SITE="${SITES[0]}"; return; fi
  local i; for i in "${!SITES[@]}"; do echo "  $((i + 1)). ${SITES[$i]}"; done
  ask "选择站点（序号）: "
  [[ "$REPLY" =~ ^[0-9]+$ ]] && [ "$REPLY" -ge 1 ] && [ "$REPLY" -le "${#SITES[@]}" ] || die "序号不对"
  SITE="${SITES[$((REPLY - 1))]}"
}

do_install() {
  fetch_installer
  log "开始安装（已安装的站点再装一次等于修复：数据库、.env、管理员、Nginx 站点都不会被覆盖）"
  if [ "${1:-}" = "--config" ]; then
    bash "$INSTALLER/deploy/install.sh" --config "${2:?缺少配置文件路径}"
  else
    [ -n "$TTY" ] || die "安装需要回答问题，没有终端时请用：bash -s -- install --config 配置文件"
    bash "$INSTALLER/deploy/install.sh" < "$TTY"
  fi
}

# 用仓库里最新的部署脚本替换服务器上的 task-deploy（老版本可能已拉不动代码，比如仓库地址换过）
refresh_deployer() {
  fetch_installer
  tr -d '\r' < "$INSTALLER/deploy/deploy.sh" > /usr/local/bin/task-deploy.new
  chmod 755 /usr/local/bin/task-deploy.new && mv /usr/local/bin/task-deploy.new /usr/local/bin/task-deploy
}

do_upgrade() {
  pick_site "${1:-}"
  refresh_deployer
  local ref="${2:-}"
  [ -z "$ref" ] || [[ "$ref" =~ ^[A-Za-z0-9._/-]{1,100}$ ]] || die "分支名不合法"
  task-deploy "$SITE" ${ref:+"$ref"}
}

CMD="${1:-}"; [ $# -gt 0 ] && shift
if [ -z "$CMD" ]; then
  echo "================ 任务打卡系统 · 一键部署 ================"
  if [ "$INSTALLED" = "0" ]; then
    echo " 本服务器还没有安装。"
    echo "  1. 安装"
    echo "  0. 退出"
    ask "请选择: "
    case "$REPLY" in 1) CMD=install ;; *) exit 0 ;; esac
  else
    for s in "${SITES[@]}"; do
      echo " 站点 $s   当前版本 $(cat "/www/backup/task-deploy/$s/current_version" 2>/dev/null || echo 未知)"
    done
    echo "  1. 升级到最新版"
    echo "  2. 查看状态"
    echo "  3. 回滚到上一次部署前"
    echo "  4. 修复 / 再安装一个站点"
    echo "  0. 退出"
    ask "请选择: "
    case "$REPLY" in 1) CMD=upgrade ;; 2) CMD=status ;; 3) CMD=rollback ;; 4) CMD=install ;; *) exit 0 ;; esac
  fi
fi

case "$CMD" in
  install)  do_install "$@" ;;
  upgrade)  [ "$INSTALLED" = "1" ] || die "还没有安装，请先执行 install"; do_upgrade "$@" ;;
  status)   [ "$INSTALLED" = "1" ] || die "还没有安装"; pick_site "${1:-}"; task-deploy "$SITE" --status ;;
  rollback)
    [ "$INSTALLED" = "1" ] || die "还没有安装"; pick_site "${1:-}"; refresh_deployer
    if [ -n "$TTY" ] && [ "${YES:-0}" != "1" ]; then ask "确认把 $SITE 回滚到上一次部署前的代码？输入 yes 继续: "; [ "$REPLY" = "yes" ] || die "已取消"; fi
    task-deploy "$SITE" --rollback ;;
  *) die "不认识的命令 $CMD（可用：install / upgrade / rollback / status）" ;;
esac
}
main "$@"
