# 个人中心新增「页面设置」板块

在个人中心（`/my`）的「发布者中心」板块下方，新增一个「页面设置」板块，供发布者修改：网站名称、留言板名字、页脚信息。

## 关键事实（已核实）
- `app_settings` 表按 `setting_key` 全局存储（非按发布者隔离），`site_name` / `site_footer_text` 是全站单值。`updated_by` 仅审计用。
- `set_site_name($adminId,...)`、`set_site_footer_text($adminId,...)` 已存在；`$adminId` 用发布者隐藏 admin id（`get_publisher_admin_id($userId)`）。
- 网站标题权限：`admin_can_custom_title($admin)` 按发布者所属会员等级的 `allow_custom_title` 判定。
- 留言板当前标题硬编码为「{发布者名} · 留言板」/「我的留言板」/按钮「留言板」，无自定义名设置。
- 用户决策：页面设置含 网站名称+留言板名字+页脚信息；留言板名字 **仅在已开通留言板（allow_message_board=1）时可改**，否则禁用并提示。

## 改动清单

### 1. services.php — 新增留言板名设置的读写
- `get_message_board_name(): string`（默认「留言板」，仿 `get_site_footer_text` 的空值/乱码兜底）。
- `set_message_board_name(int $adminId, string $name): void`（trim、空则回默认、长度上限 30 字）。

### 2. index.php — `/my` GET 路由补传变量
给 `user_center.php` 增加：
- `'siteName' => get_site_name()`
- `'siteFooterText' => get_site_footer_text()`
- `'messageBoardName' => get_message_board_name()`
- `'canCustomTitle' => admin_can_custom_title(find_admin_by_id(get_publisher_admin_id($user['id'])))`（仅在 `can_publish=1` 时计算，否则 false，避免无权限抛异常）

### 3. index.php — 新增 `POST /my/page-settings` 路由
- `require_user(false)` + `verify_csrf_or_fail()`。
- 校验 `can_publish=1`，取 `$adminId = get_publisher_admin_id($user['id'])`、`$admin = find_admin_by_id($adminId)`。
- `site_name`：仅当 `admin_can_custom_title($admin)` 为真才 `set_site_name`。
- `message_board_name`：仅当该发布者 `allow_message_board=1` 才 `set_message_board_name`。
- `site_footer_text`：始终 `set_site_footer_text`。
- flash 成功提示后 `redirect('/my')`。

### 4. user_center.php — 新增「页面设置」板块（发布者中心 section 之后、`<?php endif; ?>` 之前/之后）
- 顶部新增变量默认值：`$siteName`、`$siteFooterText`、`$messageBoardName`、`$canCustomTitle`、`$allowBoard`（由 `membership['allow_message_board']` 得出）。
- 仅 `$canPublish === 1` 显示。
- 一个 `form method=post action=/my/page-settings`，含 csrf：
  - 网站名称 input（`$canCustomTitle` 为假时 `disabled` + 琥珀色提示「升级会员后可用」）。
  - 留言板名字 input（`$allowBoard` 为假时 `disabled` + 提示「开通留言板后可用」）。
  - 页脚信息 input。
  - 保存按钮。
- 风格与现有 section 一致（圆角卡片 + 图标标题 + 输入框 tailwind 类）。

### 5. 留言板自定义名生效（3 处）
- `index.php` 公开留言板路由（`/u/{ref}/messages`）：`render_public_shell` 标题与传给视图的名称改用 `get_message_board_name()`；给 `public_publisher_board.php` 传 `'boardName' => get_message_board_name()`。
- `public_publisher_board.php`：标题 `{publisherName} · {boardName}`（用新变量，默认「留言板」）。
- `public_task_list.php`：入口按钮文案「留言板」改为输出 `get_message_board_name()`（该视图能直接调用全局函数）。
- `user_message_board.php`（发布者管理页）：标题「我的留言板」改为「我的{boardName}」或直接用 `get_message_board_name()`。

## 验证
- `php -l` 所有改动文件。
- 服务器 harness：设置 `message_board_name`，断言 `get_message_board_name()` 返回新值；渲染 `user_center.php` 断言含「页面设置」「网站名称」「留言板名字」「页脚信息」；渲染 `public_publisher_board.php` 标题含新名；权限矩阵（无 allow_custom_title → 网站名称 disabled；无 allow_message_board → 留言板名字 disabled）。测试后回滚设置。
- 部署 + reload php-fpm + curl 冒烟。

## 不改动
- 后台中心（`/admin/center`）原有的网站名称/页脚设置保留不动（同一全局值，两处都能改）。
- 权限模型、数据库表结构（本需求无需新增列，仅新增 1 个 app_settings key）。
