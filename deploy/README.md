# 任务打卡系统 · 一键部署

服务器自己从 GitHub 公开仓库 `gongpz/task-clockin` 拉代码，不需要在本机操作，也不用登记 Deploy key。

## 一键部署命令

在服务器上用 root 执行，出菜单后按提示选：

```bash
curl -fsSL https://cdn.jsdelivr.net/gh/gongpz/task-clockin@main/deploy/get.sh | bash
```

- 没装过：菜单只有「安装」
- 装过了：显示站点和当前版本，可选 升级 / 查看状态 / 回滚 / 修复

不出菜单、直接执行：

```bash
curl -fsSL https://cdn.jsdelivr.net/gh/gongpz/task-clockin@main/deploy/get.sh | bash -s -- upgrade
```

菜单逻辑在 `deploy/menu.sh`，`get.sh` 只负责拉仓库再执行它。jsDelivr 会缓存 `get.sh`（最长约 12 小时），所以改功能请改 `menu.sh`，改完马上生效。

可用的命令：`install [--config 文件]`、`upgrade [站点] [分支]`、`rollback [站点]`、`status [站点]`。
服务器上只有一个站点时，站点名可以省略。jsDelivr 连不上时，也可以用 GitHub 原始地址 `https://raw.githubusercontent.com/gongpz/task-clockin/main/deploy/get.sh`（部分国内服务器连不上）。

## 安装做了什么

- 支持 Debian 11/12、Ubuntu 20.04+、CentOS/Rocky/Alma 8/9，宝塔服务器也能用（沿用宝塔的 PHP / Nginx / MySQL）
- 装好 PHP 8.2、Nginx、MariaDB，建库建表（`deploy/schema.sql`，不含业务数据），生成 `.env`、管理员、Nginx 站点，可选 HTTPS
- 管理员密码留空会自动生成，只在安装结束时显示一次
- 可以重复运行：已有的数据库、`.env`、管理员、Nginx 站点都不会被覆盖
- 无人值守安装：把问答写进配置文件（变量名见 `install.sh` 里 `ask` 的第一个参数），用 `install --config 文件`

## 升级做了什么

拉代码 → PHP 语法检查 → 备份数据库和代码 → 同步 `app/`、`index.php` → 执行新的 `deploy/migrations/*.sql`
→ 重载 PHP-FPM → 检查后台登录页，不正常就自动回滚代码。

- 不会动：`.env`、`.user.ini`、`storage/`（打卡图片、日志）、服务器上的 `*.bak*` 文件
- 备份和台账在 `/www/backup/task-deploy/<站点名>/`，保留最近 5 次
- 回滚只回代码；数据库要恢复时，用对应备份目录里的 `db.sql.gz`

装好后服务器上也有 `task-deploy` 命令，效果相同：

```bash
task-deploy dkamj              # 部署默认分支（main）
task-deploy dkamj fix/xxx      # 部署指定分支或提交
task-deploy dkamj --rollback   # 回到上一次部署前的代码
task-deploy dkamj --status     # 当前版本和最近记录
```

## 已在运行的站点接入（如 dk.amjhly.com）

```bash
git clone --depth 1 https://github.com/gongpz/task-clockin.git /tmp/task-clockin
bash /tmp/task-clockin/deploy/setup-server.sh dkamj /www/wwwroot/dkamj https://dk.amjhly.com
```

装好 `task-deploy` 和站点配置 `/etc/task-deploy/dkamj.conf`，之后就能用上面的一键部署命令升级。
