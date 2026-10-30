# 打卡系统一期（MVP 启动版）

当前版本已打通一期最小闭环：

1. 管理员登录/退出
2. 人员管理（新增、编辑、启停、搜索）
3. 任务管理（新增、编辑、前台链接、参与人员）
4. 前台任务列表 + 单任务打卡提交
5. 重复打卡处理（reject/prompt/override）
6. 后台打卡记录查看（筛选、详情、软删除、恢复）
7. 后台补录打卡
8. 后台编辑记录（任务日期/有效状态/备注）
7. 对应 API 接口基础版

## 目录说明

- `index.php`：入口路由（页面 + API）
- `app/bootstrap.php`：配置加载、会话、数据库、渲染工具
- `app/services.php`：业务逻辑与数据访问
- `database/phase1_core.sql`：一期核心建表与种子数据
- `storage/uploads/checkins/`：打卡图片存储目录

## 本地启动步骤

1. 复制环境文件
```powershell
Copy-Item .env.example .env
```

2. 修改 `.env` 的数据库配置

3. 在 MySQL 执行初始化脚本：
```sql
SOURCE /path/to/.local-server/www/database/phase1_core.sql;
```

如果服务器 MySQL 使用自定义 socket（例如 `/tmp/mysql-generic-5.7.sock`），可在 `.env` 中设置：
```env
DB_SOCKET=/tmp/mysql-generic-5.7.sock
```
设置后将优先走 socket 连接，`DB_HOST/DB_PORT` 可保留默认值。

4. 启动 PHP-CGI 与 Nginx
```powershell
.\.local-server\start-php-cgi.ps1
.\.local-server\start-nginx.ps1
```

5. 访问：
- 前台任务页：`http://127.0.0.1:8080/tasks`
- 管理后台登录：`http://127.0.0.1:8080/admin/login`
- 默认账号：`admin`
- 默认密码：安装时设置（`deploy/install.sh` 会提示，留空自动生成）

## 已提供 API（一期首批）

- `POST /api/admin/login`
- `GET /api/admin/members`
- `POST /api/admin/members`
- `PUT /api/admin/members/{id}`
- `GET /api/admin/tasks`
- `POST /api/admin/tasks`
- `PUT /api/admin/tasks/{id}`
- `GET /api/public/task/{taskLinkCode}`
- `GET /api/public/task/{taskLinkCode}/members`
- `POST /api/public/checkin/submit`
- `GET /api/public/checkin/last-record`
- `POST /api/public/checkin/override`

## 注意事项

- 文档中出现过服务器与数据库明文凭据，建议立即轮换密码并从对外文档移除。
- 当前是一期启动版，尚未覆盖报表导出、周期高级管理、二维码等完整功能。
