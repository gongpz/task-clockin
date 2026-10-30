# 多租户发布平台改造方案（分四期）

## 现状关键约束
- `tasks` 表挂在 `admin_id` 下，`members` 表也挂在 `admin_id` 下
- `list_public_tasks()` 返回**全局所有**激活任务（无归属过滤）
- 用户认证用 token+session，管理员独立 session，两套互不影响
- 用户注册后 `auto_bind_member()` 按姓名+手机自动绑定成员

## 总体架构决策
- 任务/名单挂到 `users` 下：新增 `owner_user_id` 字段，与现有 `admin_id` 并存（admin 发布的 `owner_user_id=NULL`）
- 名单完全隔离：每个发布者只能管理/选择自己的名单
- 下级通过发布者专属注册链接 `/register?ref=<发布者码>` 注册并绑定

---

## 第一期：用户发布系统

### 数据库（新建 `003_publisher_system.sql`）
```sql
ALTER TABLE users
  ADD COLUMN can_publish tinyint DEFAULT 1 COMMENT '是否有发布权限',
  ADD COLUMN ref_code varchar(20) UNIQUE COMMENT '专属注册码',
  ADD COLUMN parent_user_id bigint unsigned DEFAULT NULL COMMENT '上级发布者ID(下级用户)';
ALTER TABLE tasks
  ADD COLUMN owner_user_id bigint unsigned DEFAULT NULL COMMENT '发布者用户ID' AFTER admin_id,
  ADD KEY idx_owner_user (owner_user_id);
ALTER TABLE members
  ADD COLUMN owner_user_id bigint unsigned DEFAULT NULL COMMENT '所属发布者用户ID' AFTER user_id,
  ADD KEY idx_owner_user (owner_user_id);
```
- 给所有现有用户生成 `ref_code`（迁移脚本里 UPDATE）

### services.php 新增/改造函数
- `current_publisher_id()` — 返回当前发布者用户ID
- `list_user_tasks(int $userId)` — 发布者自己的任务列表
- `list_public_tasks_by_owner(?int $ownerUserId)` — 按归属过滤的公开列表（改造现有 list_public_tasks）
- `create_user_task($userId, $payload)` — 复用 create_task 逻辑，写入 owner_user_id，强制校验时长≤7天
- `list_user_members($userId)` / `create_user_member` / `update/delete` — 名单 CRUD（按 owner_user_id 隔离）
- `register_user` 改造 — 支持 `ref_code` 参数：有 ref 则注册为下级（can_publish=0，parent_user_id=发布者，绑定到该发布者名单）
- `auto_bind_member` 改造 — 下级用户只在指定发布者名单内匹配

### 路由（index.php 新增用户中心发布相关）
- `GET/POST /my/tasks` — 我发布的任务列表 + 创建
- `POST /my/tasks/update|delete|toggle`
- `GET/POST /my/members` — 我的名单管理 + 增删改
- `GET /my/members/import` — 批量导入（复用现有逻辑）
- `GET /u/{ref_code}` — 发布者专属公开任务列表页
- `/task/{code}` 详情页改造 — 识别归属，提交时校验归属
- `/register` 改造 — 接受 `?ref=` 参数

### 视图（新建模板）
- `user_tasks.php` — 发布者任务管理（参考 admin_tasks.php 精简版）
- `user_members.php` — 发布者名单管理（参考 admin_members.php 精简版）
- `user_task_list_public.php` — 发布者独立公开列表（参考 public_task_list.php）
- `user_center.php` 改造 — 增加"我的发布""我的名单""专属注册链接"入口
- 下级用户隐藏发布入口

---

## 第二期：会员权限体系

### 数据库（新建 `004_membership.sql`）
```sql
CREATE TABLE membership_levels (
  id, level_name, level_code,         -- 普通/钻石/星耀
  max_members int,                     -- 名单配额
  max_active_tasks int,                -- 同时进行任务数
  max_task_days int,                   -- 任务最长时长(天)
  allow_sub_register tinyint,          -- 是否允许下级注册
  allow_survey tinyint,                -- 是否允许问卷(第三期预留)
  sort_order, status, created_at
);
ALTER TABLE users
  ADD COLUMN membership_level_id bigint DEFAULT 1,
  ADD COLUMN membership_expire_at datetime DEFAULT NULL;
```
- 预置三个等级：普通(50名单/1周/不可下级注册)、钻石、星耀

### services.php
- `get_user_membership($userId)` — 返回用户当前等级及配额
- 在 `create_user_task` / `create_user_member` / `register_user(下级)` 加配额校验
- 任务时长校验改为读 `max_task_days`

### 后台（管理员）
- `GET/POST /admin/membership-levels` — 会员等级管理
- 视图 `admin_membership_levels.php`
- 后台菜单新增"会员等级"

---

## 第三期：积分与商城

### 数据库（新建 `005_points_shop.sql`）
```sql
ALTER TABLE users ADD COLUMN points int DEFAULT 0 COMMENT '积分余额';
CREATE TABLE point_logs (id, user_id, change_amount, balance_after, reason, admin_id, created_at);
CREATE TABLE shop_products (
  id, product_name, product_type,      -- membership(会员)/points(积分包)
  grant_level_id bigint,               -- 购买后授予的会员等级
  grant_duration_days int,             -- 会员时长
  price_points int,                    -- 积分价
  price_cash decimal(10,2),            -- 现金价(分)
  allow_points tinyint, allow_cash tinyint,
  status, sort_order, description, created_at
);
CREATE TABLE orders (
  id, order_no, user_id, product_id,
  pay_type,                            -- points/wechat/alipay
  amount_points int, amount_cash decimal,
  status,                              -- pending/paid/cancelled
  paid_at, created_at
);
```

### services.php
- `adjust_user_points($userId, $delta, $reason, $adminId)` — 加减积分+写明细
- `list_shop_products()` / 商品 CRUD
- `purchase_with_points($userId, $productId)` — 积分购买（扣分→发放会员/积分）
- `create_order($userId, $productId, $payType)` — 创建订单（在线支付预留）

### 路由
- 用户端：`GET /shop`（商品列表）、`POST /shop/buy`（积分购买）、`GET /my/points`（积分明细）、`GET /my/orders`
- 后台：`/admin/shop-products`（商品管理）、`/admin/users` 增加"编辑积分"操作、`/admin/orders`（订单管理）

### 视图
- `shop.php`、`user_points.php`、`user_orders.php`
- `admin_shop_products.php`、`admin_orders.php`
- `admin_users.php` 改造增加积分编辑

---

## 第四期：在线支付

### 数据库（新建 `006_payment.sql`）
- `orders` 表已有支付字段，新增 `transaction_id`、`pay_raw_json`
- 新增 `payment_configs` 表存商户配置（或写 .env）

### services.php / 路由
- `POST /pay/wechat/create` — 创建微信支付订单（JSAPI/Native二维码）
- `POST /pay/wechat/notify` — 微信支付回调（验签→更新订单→发放权益）
- 支付宝同理（`/pay/alipay/create`、`/pay/alipay/notify`）
- 复用 `purchase_with_points` 的发放逻辑封装为 `fulfill_order($orderId)`

### 依赖
- 需要微信支付商户号 / 支付宝商户号（你提供）
- 引入官方 SDK（wechatpay-php / alipay-sdk-php）

---

## 实施顺序与验证
1. 每期独立部署、独立验证，互不阻塞
2. 第一期是地基，优先做完并测试通过
3. 第二期依赖第一期的 owner_user_id
4. 第三期依赖第二期的会员等级（商品授予等级）
5. 第四期依赖第三期的订单表

## 风险提示
- 第四期在线支付涉及资金，需要你提供合法商户资质，且回调验签必须严格，上线前要充分测试
- 改造 `list_public_tasks()` 时要确保现有 `/tasks` 行为兼容（平台级任务仍可见）

## 建议：本轮先实现第一期
四期全做工作量很大。建议本轮先完整交付**第一期（用户发布系统）**并验证通过，再逐期推进。如需一次性推进多期请说明。
