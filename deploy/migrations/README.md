# 数据库迁移

改表结构时，在这里加一个 SQL 文件，`task-deploy` 部署时按文件名顺序执行一次，执行过的记在服务器
`/www/backup/task-deploy/<站点名>/migrations.applied`，不会重复执行。

- 文件名用日期开头：`20261101_add_xxx.sql`
- 写成可重复执行的形式（`CREATE TABLE IF NOT EXISTS`、加列前先判断），万一中途失败可以再跑
- 同时把改动合进 `deploy/schema.sql`，新服务器安装时直接是最新结构
- 站点第一次接入 task-deploy 时，这里已有的文件都视为已执行
