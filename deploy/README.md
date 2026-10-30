# 任务打卡系统 · 一键部署

服务器自己从 GitHub（`gongpz/task-clockin`，私有）拉代码，不再手工上传文件。

## 新服务器安装

双击 `远程安装新服务器.bat`，填服务器地址和 root 登录方式，按提示回答问题。
也可以把 `deploy/install.sh` 传到服务器上执行 `bash install.sh`。

- 支持 Debian 11/12、Ubuntu 20.04+、CentOS/Rocky/Alma 8/9，宝塔服务器也能用（沿用宝塔的 PHP / Nginx / MySQL）
- 会装好 PHP 8.2、Nginx、MariaDB，建库建表（`deploy/schema.sql`，不含业务数据），生成 `.env`、管理员、Nginx 站点，可选 HTTPS
- 中途会显示一行公钥，要加到 GitHub 仓库 → Settings → Deploy keys（只读，不勾 Allow write access）
- 管理员密码留空会自动生成，只在安装结束时显示一次
- 可以重复运行：已有的数据库、`.env`、管理员、Nginx 站点都不会被覆盖

## 升级

双击 `一键部署.bat`，选服务器和操作（部署 / 回滚 / 看版本）。服务器清单在 `deploy/targets.txt`（第一次运行自动生成，不入库）。
在服务器上直接执行也一样：

```bash
task-deploy dkamj              # 部署默认分支（main）
task-deploy dkamj fix/xxx      # 部署指定分支或提交
task-deploy dkamj --rollback   # 回到上一次部署前的代码
task-deploy dkamj --status     # 当前版本和最近记录
```

每次部署：拉代码 → PHP 语法检查 → 备份数据库和代码 → 同步 `app/`、`index.php` → 执行新的 `deploy/migrations/*.sql`
→ 重载 PHP-FPM → 检查后台登录页，不正常就自动回滚代码。

- 不会动：`.env`、`.user.ini`、`storage/`（打卡图片、日志）、服务器上的 `*.bak*` 文件
- 备份和台账在 `/www/backup/task-deploy/<站点名>/`，保留最近 5 次
- 回滚只回代码；数据库要恢复时，用对应备份目录里的 `db.sql.gz`

## 已在运行的站点接入（如 dk.amjhly.com）

```bash
bash deploy/setup-server.sh dkamj /www/wwwroot/dkamj https://dk.amjhly.com
```

装好 `task-deploy`、生成只读 Deploy key 和站点配置 `/etc/task-deploy/dkamj.conf`，之后就能用上面的升级方式。
