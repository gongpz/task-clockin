# 任务打卡系统

当前版本：**1.0.1**（版本号在根目录 `VERSION` 文件里）

原生 PHP 写的任务打卡系统，线上站点 https://dk.amjhly.com 。

## 一键部署

在服务器上用 root 执行，出菜单后按提示选：

```bash
curl -fsSL --retry 3 https://cdn.jsdelivr.net/gh/gongpz/task-clockin@main/deploy/get.sh | bash
```

- 没装过：菜单只有「安装」，会一步步提问，装好 PHP 8.2、Nginx、MariaDB，建库建表，生成管理员和 Nginx 站点
- 装过了：显示站点和当前版本，可选 升级 / 查看状态 / 回滚 / 修复

不出菜单、直接执行：

```bash
curl -fsSL --retry 3 https://cdn.jsdelivr.net/gh/gongpz/task-clockin@main/deploy/get.sh | bash -s -- upgrade
```

| 命令 | 作用 |
|---|---|
| `install [--config 文件]` | 安装；已安装时再装一次等于修复，数据不丢 |
| `upgrade [站点] [分支]` | 升级到最新版，失败自动回滚代码；`FORCE=1` 同版本也重新部署 |
| `rollback [站点]` | 回到上一次部署前的代码；`YES=1` 不再确认 |
| `status [站点]` | 当前版本和最近部署记录 |

服务器上只有一个站点时，站点名可以省略。装好后服务器上也可以直接用 `task-deploy dkamj`（`--rollback`、`--status`）。
支持 Debian 11/12、Ubuntu 20.04+、CentOS/Rocky/Alma 8/9 和宝塔面板。详细说明见 [deploy/README.md](deploy/README.md)。

## 功能

- 管理后台（`/admin/login`）：人员与分组、任务、打卡记录（筛选、补录、编辑、回收站）、留言与公告、弹窗、用户与会员等级、卡密和订单、统计与月度报表
- 前台（`/tasks`）：任务列表、单任务打卡（拍照上传、重复打卡处理）、留言板
- 用户中心（`/my`）：我的任务、成员与分组、打卡记录、公告、页面设置、修改密码
- 对外接口（`/api/...`）：管理员登录、人员、任务、打卡记录，以及公开的任务信息和打卡提交

## 目录

| 路径 | 内容 |
|---|---|
| `index.php` | 入口和全部路由（页面 + 接口） |
| `app/bootstrap.php` | 配置加载、会话、数据库连接、渲染工具 |
| `app/services.php` | 业务逻辑与数据访问 |
| `app/license.php` | 授权校验（`.env` 没配授权信息时按本地模式运行） |
| `app/views/` | 页面模板 |
| `deploy/` | 一键部署脚本、建表 SQL（`schema.sql`）、数据库迁移（`migrations/`） |
| `storage/` | 运行时目录：打卡图片、日志（不入库） |
| `VERSION` | 系统版本号 |

## 配置

`.env` 在站点根目录，安装时自动生成，不入库。主要配置项见 `.env.example`：数据库（`DB_*`）、时区、打卡图片大小上限，以及可选的授权中心配置（`LICENSE_*`）。

## 发版

1. 改完代码，把 `VERSION` 里的版本号加一（如 1.0.1 → 1.0.2）
2. 改了表结构的话，在 `deploy/migrations/` 加 SQL 文件，并同步改 `deploy/schema.sql`
3. 合进 `main` 并推送
4. 在服务器上执行一键部署命令选「升级」

## 版本记录

- **1.0.1**（2026-10-30）：接入一键部署（`deploy/`），版本号改由 `VERSION` 文件管理
- **1.0.0**：线上 dk.amjhly.com 运行的版本，2026-10-30 入库
