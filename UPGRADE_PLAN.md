# 打卡系统升级改造开发计划

## 项目现状

- **技术栈**：纯 PHP 8.2 单文件架构（无框架），MySQL 数据库，Tailwind CSS CDN，服务端渲染
- **核心文件**：`index.php`（路由+控制器）、`app/services.php`（业务逻辑）、`app/bootstrap.php`（基础设施）
- **现有表**：admins, members, tasks, checkin_records, checkin_images, member_groups, member_group_members, task_members, message_announcements, online_messages, operation_logs, app_settings

---

## 开发阶段划分

### 第一阶段：数据库升级 + 用户账号体系

**新建表：**

1. `users` — 用户表
   - id, name, mobile, email, password_hash, nickname, avatar, status(1正常/0停用), role(admin/user), last_login_at, last_login_ip, wechat_openid(预留), wechat_unionid(预留), created_at, updated_at
   - 唯一索引：mobile, email

2. `user_login_logs` — 登录日志
   - id, user_id, login_ip, user_agent, created_at

3. `popup_announcements` — 公告弹窗表
   - id, content(Markdown), status(1启用/0停用), created_by, created_at, updated_at

4. `popup_read_logs` — 公告阅读记录
   - id, popup_id, user_id, read_date, created_at

**修改表：**

5. `members` 新增字段：
   - user_id (关联用户)
   - is_bound (是否已绑定)
   - bind_time (绑定时间)
   - source_type (来源：admin_create / user_register)

---

### 第二阶段：用户注册/登录 + JWT Token

**后端接口：**

- `POST /api/user/register` — 注册（姓名、手机、邮箱、密码）
- `POST /api/user/login` — 登录（手机号或邮箱 + 密码）
- `GET /api/user/profile` — 获取当前用户信息
- `POST /api/user/logout` — 退出

**安全措施：**
- 密码使用 `password_hash()` + bcrypt
- JWT token 机制（存于 cookie，httponly）
- 登录限流：同 IP 5分钟内最多尝试 5 次
- token 有效期 7 天

**前端页面：**
- `/login` — 用户登录页
- `/register` — 用户注册页

---

### 第三阶段：人员自动绑定

**逻辑实现（在 services.php 中）：**

用户首次登录成功后自动执行：

1. 按手机号精确匹配 members 表
2. 匹配成功 → 绑定 user_id，标记 is_bound=1
3. 匹配失败 → 自动创建新 member 记录（source_type=user_register），绑定 user_id
4. 姓名相同但手机不同 → 创建新记录但标记 source_type=pending_confirm，后台管理员确认

**防重复机制：**
- 绑定前检查该 member 是否已绑定其他 user
- 绑定前检查该 user 是否已绑定其他 member

---

### 第四阶段：用户端 - 我的打卡记录

**后端接口：**
- `GET /api/user/checkin-records` — 查询当前用户打卡记录（分页、时间筛选、任务筛选）

**前端页面：**
- `/my/records` — 历史打卡记录页面

**功能：**
- 查看任务名称、打卡时间、状态、备注、图片
- 时间范围筛选
- 任务筛选
- 分页（每页 20 条）
- 只能看自己绑定 member 的记录

---

### 第五阶段：任务页公告弹窗

**后端接口：**
- `GET /api/user/popup/active` — 获取当前启用的公告
- `POST /api/user/popup/read` — 标记已读

**前端实现（在 public_task_list.php 中）：**
- 用户每天首次进入 `/tasks` 页弹出公告
- 弹窗显示 Markdown 渲染内容（使用 marked.js + DOMPurify 防 XSS）
- 关闭后当天不再弹出（后端记录 popup_read_logs）
- 未登录用户使用 localStorage 控制

---

### 第六阶段：后台公告弹窗管理

**后端路由：**
- `GET /admin/popups` — 公告列表
- `POST /admin/popups` — 新建公告
- `POST /admin/popups/update` — 编辑公告
- `POST /admin/popups/toggle` — 启用/停用
- `POST /admin/popups/delete` — 删除

**前端页面：**
- `/admin/popups` — 公告弹窗管理页面
- Markdown 编辑器（简易 textarea + 实时预览）
- 与现有后台 UI 风格一致

---

### 第七阶段：后台用户管理

**后端路由：**
- `GET /admin/users` — 用户列表（搜索、筛选）
- `POST /admin/users/toggle` — 停用/启用
- `POST /admin/users/reset-password` — 重置密码
- `POST /admin/users/delete` — 删除用户

**前端页面：**
- `/admin/users` — 用户管理页面
- 显示：姓名、手机、邮箱、状态、绑定人员、最后登录时间、注册来源
- 搜索：姓名/手机/邮箱
- 筛选：正常/停用/未绑定
- 操作：查看详情、编辑、停用、重置密码

**停用逻辑：**
- 停用后 token 立即失效（检查 user.status）
- 历史数据保留

---

### 第八阶段：权限体系（预留）+ 导航集成

**RBAC 预留：**
- users 表已有 role 字段（admin/user）
- 后续可扩展为独立 roles 表 + permissions 表

**导航更新：**
- 后台侧边栏新增：用户管理、公告弹窗管理
- 公共页面 header 新增：登录/注册入口、个人中心入口
- 已登录用户显示姓名+退出

---

## 开发原则

1. **不改现有 UI 风格** — 所有新页面沿用 Tailwind CSS + 现有配色
2. **不重构框架** — 继续使用单文件路由 + services 模式
3. **不影响现有打卡流程** — 未登录用户仍可正常打卡（现有行为不变）
4. **平滑升级** — 数据库 ALTER 兼容旧数据，无破坏性变更
5. **安全第一** — 密码加密、XSS 防护、SQL 注入防护、CSRF 保护

---

## 文件修改预估

| 文件 | 修改内容 |
|------|---------|
| `index.php` | 新增用户端路由、后台路由、API 路由 |
| `app/services.php` | 新增用户服务、绑定逻辑、公告服务 |
| `app/bootstrap.php` | 新增 JWT 辅助函数、用户认证中间件 |
| `app/views/layouts/public.php` | header 增加登录/用户状态 |
| `app/views/partials/admin_sidebar.php` | 无需改（动态导航已支持） |
| 新增模板 | login_user.php, register_user.php, user_records.php, admin_users.php, admin_popups.php |

---

## 执行顺序建议

按阶段顺序执行，每阶段完成后可独立测试。第一阶段（数据库）是基础，必须先完成。第二、三阶段紧密关联建议连续完成。第四至七阶段相对独立可并行。
