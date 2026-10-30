# 实施计划：卡密入口已修复 + 6项功能/美化

> 已完成并部署：
> - **套餐管理勾选错位 Bug 修复**（根因：`is_active[]` 复选框无 value 属性，浏览器只提交勾选项，后端按位置匹配导致取消第一个时整组上移 → 第一个仍选中、最后一个掉了。已改为 `is_active[$i]` 显式索引+value）
> - **卡密管理后台入口**（已加入侧边导航 `/admin/card-keys`）

下面是剩余 6 项，按依赖顺序实施。

---

## 1. 用户中心卡片美化（个人资料/积分余额/推广 + 快捷功能区）

文件：`app/views/templates/ui/phase1/user_center.php`

- **个人资料卡**：手机号前加电话 ICON 图标；邮箱图标与电话图标左对齐（统一 `flex items-center gap-1.5` 行结构，手机号始终显示一行带图标）。
- **个人资料卡**：加柔和背景色 + 装饰图案（参考会员状态卡的 `absolute rounded-full bg-white/10` 圆形底纹做法，改用浅色）。
- **积分 & 余额卡**：加背景渐变 + 装饰图形。
- **推广中心卡**：加背景渐变 + 装饰图形。
- **快捷功能区 3 个按钮**：每个按钮加背景色块 + 角标/底纹装饰，风格统一。

纯样式改动，不动逻辑。

---

## 2. 商城页改左右布局 + 套餐卡重设计（参考 WPS 会员页风格）

文件：`app/views/templates/ui/phase1/shop.php`

- 外层从 `max-w-3xl` 单列改为 `max-w-6xl` + `lg:grid-cols-[300px_1fr]` 左右两栏，与个人中心 `user_center.php` 一致。
  - **左栏**：当前等级状态卡（现有横幅移到左侧竖向卡）+ 权益摘要。
  - **右栏**：体验/高级/超级三个套餐分组（现有分组逻辑保留）。
- **套餐卡重设计**：名称与天数排版优化——大号套餐名、天数用"有效期 N 天"带日历图标的胶囊样式、价格更醒目（参考 WPS：突出价格、原价划线可选、推荐角标）。
- **体验会员归入体验框**：现有按 `level_id` 分组（体验套餐 `level_id=2` 名称含"体验"）。修正分组——体验会员套餐（`体验会员·3天`）应归入 `levelConfig[1]`（体验会员框），而非和高级会员混在 level 2。
  - 方案：在分组时按套餐名称含"体验"或单独标记归入体验组；或后台把体验套餐的 `level_id` 调整。**采用视图层判断**：名称含"体验"的套餐放入体验分组展示，避免改数据。

---

## 3. 后台用户管理：编辑用户

文件：`app/views/templates/ui/phase1/admin_users.php`、`index.php`、`app/services.php`

- 用户行操作区新增「编辑」按钮，弹出编辑 Modal（复用现有 Modal 模式）。
- 可编辑字段：姓名、手机号、邮箱、状态（正常/停用）。
- 新增后端：
  - 服务函数 `admin_update_user(int $userId, array $payload)`（更新 name/mobile/email/status，校验唯一性）。
  - 路由 `POST /admin/users/update`。
- 现有的"设置等级/重置密码/绑定"保持不变。

---

## 4. 公告/留言板权限：按会员等级分级（核心新功能）

> 架构（已与用户确认）：**每个发布者（开通该功能的会员）拥有自己的留言板和公告**，作用于其专属任务页 `/u/{refCode}`。
> - 留言板功能：**高级 + 超级**会员可开通。
> - 公告功能：**仅超级**会员可开通。
> - 成员访问发布者的 `/u/{refCode}` 任务页 → 点「留言板」进入该发布者的留言板留言/回复；公告在该任务页弹出。
> - 发布者在个人中心管理自己的留言板和公告。

### 4a. 数据库
- `membership_levels` 加两列：`allow_message_board tinyint default 0`、`allow_announcement tinyint default 0`。
  - 初始化：高级(2)→ board=1；超级(3)→ board=1, announce=1。
- `online_messages` 加列 `publisher_admin_id bigint unsigned NULL`（区分各发布者留言板；现有全局留言 publisher_admin_id=NULL 兼容）。
- 公告改用**发布者公告表** `publisher_announcements`（id, publisher_admin_id, content, status, created_at, updated_at）—— 与管理员的 `popup_announcements` 分离，避免污染全局首页弹窗。

### 4b. 后台会员等级页
文件：`admin_membership.php` —— 每个等级卡加两个复选框「允许留言板」「允许首页公告」，提交到现有 `/admin/membership` 路由（扩展字段保存）。

### 4c. 发布者个人中心
文件：`user_center.php` 发布者中心区 —— 按 membership 的 `allow_message_board`/`allow_announcement` 显示入口：
- 「我的留言板」→ `/my/message-board`（管理本发布者留言板）。
- 「我的公告」→ `/my/announcements`（管理在自己任务页弹出的公告）。
新增路由 + 两个管理视图（列表/发布/删除/上下线），复用 `online_messages`/`publisher_announcements`，按 `publisher_admin_id` 作用域过滤。

### 4d. 发布者任务页
文件：`public_task_list.php`（`/u/{refCode}` 渲染）—— 顶部加「留言板」入口（仅当该发布者 board=1）；进入 `/u/{refCode}/messages` 显示该发布者的留言板（成员可留言/回复）。公告若 announce=1 则在任务页弹出该发布者的公告。

---

## 5. 修复 `/my/member-groups/members/sync` 路由缺失（顺带）

排查中发现：用户端用户组页有「同步分组成员」表单 POST 到 `/my/member-groups/members/sync`，但该路由**未定义**（仅有 add/remove）。补上该路由（调用已存在的 `sync_group_members`），否则保存成员同步会 404/无效。

---

## 验证
- 每改一处运行 `php -l`。
- DB 迁移用 `ensure_*_schema()` 风格的幂等 ALTER（与现有 schema 函数一致），或直接在服务器跑一次 ALTER。
- 关键流程本地脚本测试：编辑用户、权限开通后发布者入口显隐、留言板按发布者隔离、套餐勾选保存不再错位（已验证逻辑）。
- 改完逐个 scp 部署到服务器并抽查页面。

## 不做（除非你要）
- 不改商城支付/下单逻辑。
- 不动全局留言板 `/messages` 与管理员公告弹窗的现有行为（向后兼容）。
