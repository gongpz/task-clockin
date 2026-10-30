#!/usr/bin/env bash
# =====================================================================
# 任务打卡系统 · 一键部署（安装 / 升级 / 回滚 / 状态 / 修复）
#
# 在服务器上用 root 执行：
#   curl -fsSL https://cdn.jsdelivr.net/gh/gongpz/task-clockin@main/deploy/get.sh | bash
# 不出菜单、直接执行：
#   curl -fsSL .../get.sh | bash -s -- install [--config 文件]   安装（已安装时等于修复，数据不丢）
#   curl -fsSL .../get.sh | bash -s -- upgrade [站点] [分支]     升级（FORCE=1 同版本也重新部署）
#   curl -fsSL .../get.sh | bash -s -- rollback [站点]           回到上一次部署前的代码（YES=1 不再确认）
#   curl -fsSL .../get.sh | bash -s -- status [站点]             当前版本和最近记录
#
# 本文件只负责从 GitHub 拉取仓库，再执行仓库里的 deploy/menu.sh。
# 它经 CDN 分发有缓存，所以要保持简单、尽量不改；功能都改 menu.sh。
# =====================================================================
set -Eeuo pipefail
# 整个脚本放进函数，curl | bash 下载不完整时不会执行半截
main() {
  local repo="${REPO_URL:-https://github.com/gongpz/task-clockin.git}" branch="${BRANCH:-main}"
  local dir=/www/backup/task-deploy/_installer i
  die() { echo -e "[31m❌ $*[0m" >&2; exit 1; }
  [ "$(id -u)" = "0" ] || die "请用 root 运行"
  if ! command -v git >/dev/null; then
    echo "安装 git……"
    if command -v apt-get >/dev/null; then DEBIAN_FRONTEND=noninteractive apt-get update -qq && apt-get install -y -qq git >/dev/null
    elif command -v dnf >/dev/null; then dnf install -y -q git >/dev/null
    elif command -v yum >/dev/null; then yum install -y -q git >/dev/null
    else die "请先安装 git"; fi
  fi
  mkdir -p "$(dirname "$dir")"; chmod 700 "$(dirname "$dir")"
  for i in 1 2 3; do   # GitHub 在国内偶尔连不上，重试 3 次
    if [ -d "$dir/.git" ]; then
      git -C "$dir" remote set-url origin "$repo"
      git -C "$dir" fetch --quiet --depth 1 origin "$branch" && git -C "$dir" checkout --quiet --force FETCH_HEAD && break
    else
      rm -rf "$dir"; git clone --quiet --depth 1 --branch "$branch" "$repo" "$dir" && break
    fi
    [ "$i" = "3" ] && die "下载不了 $repo，请检查服务器能否访问 github.com"
    echo "连接 GitHub 失败，第 $i 次重试……"; sleep 3
  done
  REPO_URL="$repo" BRANCH="$branch" TASK_INSTALLER_READY=1 exec bash "$dir/deploy/menu.sh" "$@"
}
main "$@"
