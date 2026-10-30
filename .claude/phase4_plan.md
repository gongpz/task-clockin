# 第四期实施计划：会员体系增强 + 商城卡密 + 积分推广

## 已确认的需求理解

| # | 需求 | 确认结论 |
|---|------|---------|
| 1 | 注册用户独立任务页 | **已实现**（`/u/{ref_code}` 即发布者独立任务列表），本期只做外观美化 |
| 2 | 个人中心改左右布局 | 左窄栏=会员状态/个人信息/升级入口/积分；右宽栏=功能区 |
| 3 | 页面美化 | 升级页+个人中心增加色彩、图标(内联SVG)、背景装饰，更年轻时尚 |
| 4 | 推广积分 | 注册即计分(被邀请人注册成功→邀请人+1积分)；10积分=1元余额；余额可买商品 |
| 5 | 升级页分组+权限对比 | 体验/高级/超级各成一组，配色+图标+背景装饰，加三级权限对比表 |
| 6 | 体验会员体验等级可配 | 全局设置项：体验会员体验哪个等级(高级/超级)+体验天数 |
| 7 | 卡密购买 | 管理员生成卡密(绑定套餐)→用户在订单详情页输入卡密验证→开通会员。替换原转账方式 |
| 8 | 用户组(打卡人员分组) | 给发布者复刻管理员已有的 member_groups 功能；普通0组/高级1组/超级5组；发任务时可选组批量加人 |

## 现有架构关键点（已探查确认）
- 用户组表 `member_groups` + 中间表 `member_group_members` 已存在，全套 services 函数齐全（`list_member_groups`/`create_member_group`/`sync_group_members`/`list_member_ids_by_group_ids` 等），均按 `admin_id` 隔离
- 发布者通过隐藏 admin 账号(`publisher_admin_id`)操作，发任务走 `/my/tasks`，调 `resolve_task_member_ids_from_payload`（已支持 group_ids，但发布者表单未接入）
- 任务发布选组逻辑现成：`resolve_task_member_ids_from_payload` 已合并 member_ids+group_ids
- `membership_levels` 无 max_groups 字段，需新增
- 第三期已建 `shop_plans`/`orders`/`settings` 表；订单状态 pending→confirming→paid/cancelled
- 前台 Tailwind CDN + CSS变量主题(支持深浅色)，`render_public_shell` 渲染

---

## 分阶段交付（每阶段独立验证后再进下一阶段）

### 阶段A：数据库 + 积分/推广基础（需求4 + 6 + 8配额）
**迁移 `006_phase4.sql`：**
- `users` 加 `points int DEFAULT 0`（积分）、`balance_fen int DEFAULT 0`（余额/分）、`referrer_id bigint NULL`（推荐人）
- `membership_levels` 加 `max_groups int DEFAULT 0`（建组上限）；更新 普通0/高级1/超级5
- `settings` 加两条：`trial_level_id`(默认2)、`trial_days`(默认3)
- `point_logs` 表（积分流水：user_id, change, reason, ref_user_id, created_at）

**services.php 新增：**
- `add_points($userId,$n,$reason,$refUserId)`、`get_user_points`、`get_user_balance`
- `convert_points_to_balance($userId,$points)`（10积分=1元，写流水+改余额）
- 修改 `register_user`：注册成功且有有效 ref → 给 referrer +1 积分(调 add_points)，记 referrer_id

**验证**：CLI 测试注册带ref→邀请人加分；积分换余额；配额读取

### 阶段B：卡密系统（需求7）
**迁移并入 `006`：**
- `card_keys` 表（id, code唯一, plan_id, status[unused/used], used_by, used_order_id, created_at, used_at）

**services.php：**
- `generate_card_keys($planId,$count)`（批量生成，code=随机16位）
- `list_card_keys($filters)`、`redeem_card_key($code,$userId,$orderId)`（验证未用+套餐匹配→标记used→confirm_order升级）

**路由：**
- 后台 `GET/POST /admin/card-keys`（生成+列表+筛选）
- 订单详情页改：`POST /shop/order/{id}/redeem`（输入卡密→校验→开通）
- 移除/保留转账说明（卡密为主，转账说明降级为可选展示）

**视图**：`admin_card_keys.php`（生成表单+卡密列表+复制）；改 `order_detail.php`（卡密输入框替代"我已付款"）

**验证**：CLI 生成卡密→兑换→会员升级；错误卡密/已用卡密/套餐不匹配拒绝

### 阶段C：发布者用户组（需求8主体）
**复刻管理员 member_groups 给发布者（全部复用现有 services 函数，传 $pubAdminId）：**
- 路由：`/my/member-groups` GET/POST、`/my/member-groups/update`、`/delete`、`/members/add`、`/members/remove`（参照 `/admin/member-groups`，加 max_groups 配额校验）
- 视图：`user_member_groups.php`（复用 admin_member_groups.php 结构）
- 改 `user_tasks.php`：任务表单加"选择用户组"多选框（参照 admin_task_form.php），后端已支持无需改
- `/my` 导航/入口加"用户组"链接（普通会员隐藏或提示升级）

**验证**：HTTP 冒烟；CLI 建组→加成员→发任务选组→task_members 含组内成员；配额超限拒绝

### 阶段D：个人中心 + 升级页美化（需求2 + 3 + 5）
- **个人中心 `user_center.php`**：改左右布局。左窄栏：会员状态卡(等级+到期)、个人信息、升级按钮、积分余额卡(积分→余额兑换按钮)、推广卡(专属链接+已邀请人数+累计积分)；右宽栏：现有绑定/名单功能区
- **升级页 `shop.php`**：体验/高级/超级三组分区，每组独立配色(体验绿/高级蓝/超级金)+图标+背景装饰；底部加三级权限对比表(名单数/任务数/可建组/问卷/发展下级)
- 内联SVG图标，CSS变量配色，渐变/光晕背景装饰
- 推广统计入口：个人中心展示"已推广X人，获得Y积分"

**验证**：HTTP 截图核对布局；深浅色都正常；移动端响应式

---

## 部署/验证方式（每阶段）
- 本地 `php -l` 全部改动文件
- scp 部署到 `root@服务器IP:/www/wwwroot/dkamj/`
- 迁移 SQL 通过 ssh+mysql 执行并验证
- CLI 业务函数测试脚本（临时，跑完删除）
- HTTP 冒烟（curl 状态码 + 浏览器预览截图关键页）

## 关键风险点
- 卡密替换转账：保留 orders 表结构，confirm_order 复用，仅入口从"我已付款"改"输入卡密"
- 积分换余额、余额买商品：阶段A建基础，余额支付在阶段B的订单流程接入（订单可选"余额支付"或"卡密支付"）
- 用户组配额：发布者建组前校验 `count(已有组) < max_groups`

## 支付方式（已确认）
订单详情页**双入口**：「输入卡密兑换」+「余额支付」。余额(由积分兑换而来) ≥ 订单金额时可直接扣余额开通；否则用卡密。卡密绑定套餐，须与订单套餐匹配。
