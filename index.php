<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/services.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($method === 'POST' && isset($_POST['_method'])) {
    $override = strtoupper((string) $_POST['_method']);
    if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
        $method = $override;
    }
}

// Allow health checks and crawlers to probe page routes with HEAD
// while keeping all mutating actions on their original methods.
$routeMethod = $method === 'HEAD' ? 'GET' : $method;

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = rtrim($path, '/');
$path = $path === '' ? '/' : $path;

if (str_starts_with($path, '/api/')) {
    handle_api($routeMethod, $path);
    exit;
}

try {
    route_web($routeMethod, $path);
} catch (Throwable $e) {
    $requestId = log_internal_exception('web', $e, [
        'path' => $path,
        'method' => $method,
    ]);
    http_response_code(500);
    render_public_shell(
        '系统繁忙',
        '<h1>系统繁忙</h1><p>服务暂时不可用，请稍后再试。</p><p class="text-xs text-slate-400">错误编号：' . h($requestId) . '</p>',
        ['showHeader' => false]
    );
}

function route_web(string $method, string $path): void
{
    // 宝塔这类伪静态环境在内部转发到 index.php 前可能已带入 404 状态，
    // 先恢复为 200，后续真正的 404/500 分支会再显式覆盖。
    http_response_code(200);

    if ($path === '/') {
        redirect('/tasks');
    }

    // ─── User Auth Routes ───

    if ($path === '/login' && $method === 'GET') {
        if (current_user_id() > 0) {
            redirect('/tasks');
        }
        $flash = pop_flash();
        $error = $flash['type'] === 'error' ? (string) ($flash['message'] ?? '') : '';
        $html = render_view('templates/ui/phase1/login_user.php', [
            'csrf' => csrf_token(),
            'error' => $error,
        ]);
        render_public_shell('用户登录', $html, ['showHeader' => false]);
        return;
    }

    if ($path === '/login' && $method === 'POST') {
        verify_csrf_or_fail();
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        if (!check_login_rate_limit($ip)) {
            push_flash('error', '登录尝试过于频繁，请5分钟后再试');
            redirect('/login');
        }
        $data = request_payload();
        $account = trim((string) ($data['account'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        if ($account === '' || $password === '') {
            push_flash('error', '请输入账号和密码');
            redirect('/login');
        }
        $user = attempt_user_login($account, $password);
        if ($user === null) {
            record_login_attempt($ip);
            push_flash('error', '账号或密码错误');
            redirect('/login');
        }
        reset_login_attempts($ip);
        set_user_session((int) $user['id']);
        redirect('/my');
    }

    if ($path === '/register' && $method === 'GET') {
        if (current_user_id() > 0) {
            redirect('/tasks');
        }
        $flash = pop_flash();
        $error = $flash['type'] === 'error' ? (string) ($flash['message'] ?? '') : '';
        $ref = trim((string) ($_GET['ref'] ?? ''));
        $refUser = $ref !== '' ? find_publisher_by_ref_code($ref) : null;
        $html = render_view('templates/ui/phase1/register_user.php', [
            'csrf' => csrf_token(),
            'error' => $error,
            'old' => $_SESSION['register_old'] ?? [],
            'ref' => $refUser ? $ref : '',
            'refName' => $refUser ? (string) $refUser['name'] : '',
        ]);
        unset($_SESSION['register_old']);
        render_public_shell('用户注册', $html, ['showHeader' => false]);
        return;
    }

    if ($path === '/register' && $method === 'POST') {
        verify_csrf_or_fail();
        $data = request_payload();
        $_SESSION['register_old'] = [
            'name' => trim((string) ($data['name'] ?? '')),
            'mobile' => trim((string) ($data['mobile'] ?? '')),
            'email' => trim((string) ($data['email'] ?? '')),
        ];
        try {
            $user = register_user($data);
            unset($_SESSION['register_old']);
            set_user_session((int) $user['id']);
            redirect('/my');
        } catch (InvalidArgumentException $e) {
            push_flash('error', $e->getMessage());
            $ref = trim((string) ($data['ref'] ?? ''));
            redirect('/register' . ($ref !== '' ? '?ref=' . rawurlencode($ref) : ''));
        }
    }

    if ($path === '/logout' && $method === 'GET') {
        logout_user();
        redirect('/tasks');
    }

    if ($path === '/my' && $method === 'GET') {
        $user = require_user(false);
        $member = get_user_bound_member((int) $user['id']);
        $matchingMembers = find_matching_members_for_bind((int) $user['id'], (string) $user['name']);
        $flash = pop_flash();
        $scheme = (($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https' : 'http';
        $baseUrl = $scheme . '://' . (string) ($_SERVER['HTTP_HOST'] ?? '');
        $canCustomTitle = false;
        if ((int) ($user['can_publish'] ?? 0) === 1) {
            $pubAdmin = find_admin_by_id(get_publisher_admin_id((int) $user['id']));
            $canCustomTitle = $pubAdmin !== null && admin_can_custom_title($pubAdmin);
        }
        $content = render_view('templates/ui/phase1/user_center.php', [
            'currentUser' => $user,
            'member' => $member,
            'matchingMembers' => $matchingMembers,
            'csrf' => csrf_token(),
            'flash' => $flash,
            'baseUrl' => $baseUrl,
            'membership' => get_user_membership((int) $user['id']),
            'userPoints' => get_user_points((int) $user['id']),
            'userBalance' => get_user_balance((int) $user['id']),
            'referralCount' => count_referrals((int) $user['id']),
            'siteName' => get_site_name(),
            'siteFooterText' => get_site_footer_text(),
            'messageBoardName' => get_message_board_name(),
            'canCustomTitle' => $canCustomTitle,
        ]);
        render_public_shell('个人中心', $content);
        return;
    }

    if ($path === '/my/bind' && $method === 'POST') {
        $user = require_user(false);
        verify_csrf_or_fail();
        $memberId = (int) (request_payload()['member_id'] ?? 0);
        if ($memberId > 0 && bind_user_to_member((int) $user['id'], $memberId)) {
            push_flash('success', '绑定成功，现在可以查看历史打卡记录');
        } else {
            push_flash('error', '绑定失败，请重试');
        }
        redirect('/my');
    }

    if ($path === '/my/page-settings' && $method === 'POST') {
        $user = require_user(false);
        verify_csrf_or_fail();
        if ((int) ($user['can_publish'] ?? 0) !== 1) {
            push_flash('error', '无发布权限');
            redirect('/my');
        }
        $p = request_payload();
        $adminId = get_publisher_admin_id((int) $user['id']);
        $admin = find_admin_by_id($adminId);
        $allowBoard = (int) (get_user_membership((int) $user['id'])['allow_message_board'] ?? 0) === 1;
        try {
            if ($admin !== null && admin_can_custom_title($admin)) {
                set_site_name($adminId, (string) ($p['site_name'] ?? ''));
            }
            if ($allowBoard) {
                set_message_board_name($adminId, (string) ($p['message_board_name'] ?? ''));
            }
            set_site_footer_text($adminId, (string) ($p['site_footer_text'] ?? ''));
            push_flash('success', '页面设置已保存');
        } catch (Throwable $e) {
            push_flash('error', '操作失败');
        }
        redirect('/my');
    }

    if ($path === '/my/password' && $method === 'GET') {
        $user = require_user(false);
        $flash = pop_flash();
        $content = render_view('templates/ui/phase1/user_password.php', [
            'currentUser' => $user,
            'csrf' => csrf_token(),
            'flash' => $flash,
        ]);
        render_public_shell('修改密码', $content);
        return;
    }

    if ($path === '/my/password' && $method === 'POST') {
        $user = require_user(false);
        verify_csrf_or_fail();
        $data = request_payload();
        $oldPwd = (string) ($data['old_password'] ?? '');
        $newPwd = (string) ($data['new_password'] ?? '');
        $confirmPwd = (string) ($data['confirm_password'] ?? '');
        if ($newPwd === '' || mb_strlen($newPwd) < 6) {
            push_flash('error', '新密码至少6位');
            redirect('/my/password');
        }
        if ($newPwd !== $confirmPwd) {
            push_flash('error', '两次输入的新密码不一致');
            redirect('/my/password');
        }
        if (change_user_password((int) $user['id'], $oldPwd, $newPwd)) {
            push_flash('success', '密码修改成功');
            redirect('/my');
        } else {
            push_flash('error', '旧密码错误');
            redirect('/my/password');
        }
    }

    if ($path === '/my/records' && $method === 'GET') {
        $user = require_user(false);
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 20;
        $filters = [
            'task_id' => (int) ($_GET['task_id'] ?? 0),
            'date_from' => trim((string) ($_GET['date_from'] ?? '')),
            'date_to' => trim((string) ($_GET['date_to'] ?? '')),
        ];
        $records = list_user_checkin_records((int) $user['id'], $perPage + 1, $filters, ($page - 1) * $perPage);
        $hasMore = count($records) > $perPage;
        if ($hasMore) {
            array_pop($records);
        }
        $member = get_user_bound_member((int) $user['id']);
        $tasks = $member ? list_tasks_for_member((int) $member['id']) : [];
        $totalPages = $hasMore ? $page + 1 : $page;
        $content = render_view('templates/ui/phase1/user_records.php', [
            'records' => $records,
            'tasks' => $tasks,
            'filters' => $filters,
            'currentUser' => $user,
            'page' => $page,
            'totalPages' => $totalPages,
        ]);
        render_public_shell('我的打卡记录', $content);
        return;
    }

    // ─── Publisher Routes (第一期) ───
    if (str_starts_with($path, '/my/tasks') || str_starts_with($path, '/my/members') || str_starts_with($path, '/my/member-groups')) {
        $user = require_user(false);
        if ((int) ($user['can_publish'] ?? 0) !== 1) {
            push_flash('error', '您没有发布权限');
            redirect('/my');
        }
        $pubAdminId = get_publisher_admin_id((int) $user['id']);
        $membership = get_user_membership((int) $user['id']);
        $maxDays = (int) ($membership['max_task_days'] ?? 7);
        $maxMembers = (int) ($membership['max_members'] ?? 50);
        $maxActiveTasks = (int) ($membership['max_active_tasks'] ?? 3);

        if ($path === '/my/tasks' && $method === 'GET') {
            $editId = (int) ($_GET['edit_id'] ?? 0);
            $editing = $editId > 0 ? find_task_by_id($pubAdminId, $editId) : null;
            $expireMode = 'three_days';
            $customDays = '3';
            $currentMonth = '0';
            $startTime = to_datetime_local((string) ($editing['start_time'] ?? date('Y-m-d H:i:s')));
            if ($editing !== null && empty($editing['is_long_term'])) {
                $s = strtotime((string) ($editing['start_time'] ?? ''));
                $e = strtotime((string) ($editing['end_time'] ?? ''));
                // 「当月有效」识别：结束时间恰为起始月份最后一天 23:59:59。
                if ($s !== false && (string) ($editing['end_time'] ?? '') === date('Y-m-t 23:59:59', $s)) {
                    $currentMonth = '1';
                } elseif ($s !== false && $e !== false && $e >= $s) {
                    $d = (int) floor(($e - $s) / 86400);
                    $expireMode = $d === 5 ? 'five_days' : ($d === 7 ? 'one_week' : ($d === 3 ? 'three_days' : 'custom_days'));
                    $customDays = (string) max(1, $d);
                }
            }
            $content = render_view('templates/ui/phase1/user_tasks.php', [
                'csrf' => csrf_token(),
                'tasks' => list_tasks($pubAdminId),
                'members' => array_values(array_filter(list_members($pubAdminId, ''), static fn (array $m): bool => (int) ($m['status'] ?? 0) === 1)),
                'editing' => $editing,
                'selectedMemberIds' => $editing ? task_member_ids((int) $editing['id']) : [],
                'groups' => list_member_groups($pubAdminId),
                'selectedGroupIds' => [],
                'startTime' => $startTime,
                'expireMode' => $expireMode,
                'customDays' => $customDays,
                'currentMonth' => $currentMonth,
                'refCode' => (string) ($user['ref_code'] ?? ''),
                'maxDays' => $maxDays,
                'membership' => $membership,
                'activeTaskCount' => count_active_tasks($pubAdminId),
                'flash' => pop_flash(),
            ]);
            render_public_shell('我的发布', $content);
            return;
        }

        if ($path === '/my/tasks' && $method === 'POST') {
            verify_csrf_or_fail();
            $payload = request_payload();
            try {
                if ((int) ($payload['task_status'] ?? 1) === 1 && count_active_tasks($pubAdminId) >= $maxActiveTasks) {
                    throw new InvalidArgumentException('同时进行的任务数已达上限 ' . $maxActiveTasks . ' 个');
                }
                assert_task_duration_limit($payload, $maxDays);
                create_task($pubAdminId, $payload);
                push_flash('success', '任务发布成功');
            } catch (Throwable $e) {
                push_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : '发布失败');
            }
            redirect('/my/tasks');
        }

        if ($path === '/my/tasks/update' && $method === 'POST') {
            verify_csrf_or_fail();
            $id = (int) ($_POST['id'] ?? 0);
            $payload = request_payload();
            try {
                assert_task_duration_limit($payload, $maxDays);
                update_task($pubAdminId, $id, $payload);
                push_flash('success', '已保存');
            } catch (Throwable $e) {
                push_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : '保存失败');
            }
            redirect('/my/tasks');
        }

        if ($path === '/my/tasks/delete' && $method === 'POST') {
            verify_csrf_or_fail();
            try {
                delete_task_with_records($pubAdminId, (int) ($_POST['id'] ?? 0));
                push_flash('success', '已删除');
            } catch (Throwable $e) {
                push_flash('error', '删除失败');
            }
            redirect('/my/tasks');
        }

        if ($path === '/my/members' && $method === 'GET') {
            $editId = (int) ($_GET['edit_id'] ?? 0);
            $content = render_view('templates/ui/phase1/user_members.php', [
                'csrf' => csrf_token(),
                'members' => list_members($pubAdminId, ''),
                'editing' => $editId > 0 ? find_member_by_id($pubAdminId, $editId) : null,
                'maxMembers' => $maxMembers,
                'flash' => pop_flash(),
            ]);
            render_public_shell('我的名单', $content);
            return;
        }

        if ($path === '/my/members' && $method === 'POST') {
            verify_csrf_or_fail();
            if (count(list_members($pubAdminId, '')) >= $maxMembers) {
                push_flash('error', '名单已达上限 ' . $maxMembers . ' 人');
                redirect('/my/members');
            }
            try {
                create_member($pubAdminId, request_payload());
                push_flash('success', '已添加');
            } catch (Throwable $e) {
                push_flash('error', '添加失败，姓名不能为空');
            }
            redirect('/my/members');
        }

        if ($path === '/my/members/update' && $method === 'POST') {
            verify_csrf_or_fail();
            try {
                update_member($pubAdminId, (int) ($_POST['id'] ?? 0), request_payload());
                push_flash('success', '已保存');
            } catch (Throwable $e) {
                push_flash('error', '保存失败');
            }
            redirect('/my/members');
        }

        if ($path === '/my/members/delete' && $method === 'POST') {
            verify_csrf_or_fail();
            soft_delete_member($pubAdminId, (int) ($_POST['id'] ?? 0));
            push_flash('success', '已删除');
            redirect('/my/members');
        }

        // ---- 发布者用户组 ----

        $maxGroups = (int) ($membership['max_groups'] ?? 0);

        if ($path === '/my/member-groups' && $method === 'GET') {
            $groups = list_member_groups($pubAdminId);
            $allMembers = list_members($pubAdminId, '');
            $editGroupId = (int) ($_GET['edit_group_id'] ?? 0);
            $editingGroup = $editGroupId > 0 ? find_member_group_by_id($pubAdminId, $editGroupId) : null;
            $selectedGroupId = (int) ($_GET['group_id'] ?? 0);
            $selectedGroup = $selectedGroupId > 0 ? find_member_group_by_id($pubAdminId, $selectedGroupId) : null;
            $groupMemberIds = $selectedGroupId > 0 ? list_member_ids_in_group($pubAdminId, $selectedGroupId) : [];
            $groupMembers = $selectedGroupId > 0 ? list_members_by_group($pubAdminId, $selectedGroupId) : [];
            $memberKeyword = trim((string) ($_GET['member_keyword'] ?? ''));
            if ($memberKeyword !== '' && $groupMembers !== []) {
                $groupMembers = array_values(array_filter($groupMembers, static fn($m) => stripos(implode(' ', [(string)($m['name']??''), (string)($m['mobile']??'')]), $memberKeyword) !== false));
            }
            $content = render_view('templates/ui/phase1/user_member_groups.php', [
                'csrf' => csrf_token(), 'flash' => pop_flash(),
                'groups' => $groups, 'allMembers' => $allMembers,
                'editingGroup' => $editingGroup, 'selectedGroup' => $selectedGroup,
                'groupMemberIds' => $groupMemberIds, 'groupMembers' => $groupMembers,
                'memberKeyword' => $memberKeyword, 'maxGroups' => $maxGroups,
            ]);
            render_public_shell('用户组管理', $content);
            return;
        }

        if ($path === '/my/member-groups' && $method === 'POST') {
            verify_csrf_or_fail();
            $curCount = count(list_member_groups($pubAdminId));
            if ($maxGroups <= 0 || $curCount >= $maxGroups) {
                push_flash('error', '当前会员等级最多创建 ' . $maxGroups . ' 个用户组');
            } else {
                create_member_group($pubAdminId, request_payload());
                push_flash('success', '用户组已创建');
            }
            redirect('/my/member-groups');
        }

        if ($path === '/my/member-groups/update' && $method === 'POST') {
            verify_csrf_or_fail();
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) { update_member_group($pubAdminId, $id, request_payload()); push_flash('success', '已更新'); }
            redirect('/my/member-groups');
        }

        if ($path === '/my/member-groups/delete' && $method === 'POST') {
            verify_csrf_or_fail();
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) { try { delete_member_group($pubAdminId, $id); push_flash('success', '已删除'); } catch (Throwable $e) { push_flash('error', '删除失败'); } }
            redirect('/my/member-groups');
        }

        if ($path === '/my/member-groups/members/add' && $method === 'POST') {
            verify_csrf_or_fail();
            $gid = (int) ($_POST['group_id'] ?? 0);
            $mids = normalize_member_ids($_POST['member_ids'] ?? []);
            if ($gid > 0 && $mids !== []) { add_members_to_group($pubAdminId, $gid, $mids); push_flash('success', '已添加'); }
            redirect('/my/member-groups?group_id=' . $gid);
        }

        if ($path === '/my/member-groups/members/remove' && $method === 'POST') {
            verify_csrf_or_fail();
            $gid = (int) ($_POST['group_id'] ?? 0);
            $mids = normalize_member_ids($_POST['member_ids'] ?? ($_POST['member_id'] ?? []));
            if ($gid > 0 && $mids !== []) { remove_members_from_group($pubAdminId, $gid, $mids); push_flash('success', '已移除'); }
            redirect('/my/member-groups?group_id=' . $gid);
        }

        if ($path === '/my/member-groups/members/sync' && $method === 'POST') {
            verify_csrf_or_fail();
            $gid = (int) ($_POST['group_id'] ?? 0);
            if ($gid > 0) {
                sync_group_members($pubAdminId, $gid, normalize_member_ids($_POST['member_ids'] ?? []));
                push_flash('success', '已保存');
            }
            redirect('/my/member-groups?group_id=' . $gid);
        }
    }


    // ---- 发布者留言板 & 公告管理（按会员等级开通） ----
    if (str_starts_with($path, '/my/message-board') || str_starts_with($path, '/my/announcements')) {
        $user = require_user(false);
        $uid = (int) $user['id'];
        $pubAdminId = get_publisher_admin_id($uid);
        $membership = get_user_membership($uid);
        $allowBoard = (int) ($membership['allow_message_board'] ?? 0) === 1;
        $allowAnnounce = (int) ($membership['allow_announcement'] ?? 0) === 1;

        // 留言板管理
        if ($path === '/my/message-board' && $method === 'GET') {
            if (!$allowBoard) { push_flash('error', '当前会员等级未开通留言板功能'); redirect('/my'); }
            $content = render_view('templates/ui/phase1/user_message_board.php', [
                'csrf' => csrf_token(),
                'flash' => pop_flash(),
                'threads' => list_publisher_message_threads($pubAdminId, 200),
                'refCode' => (string) ($user['ref_code'] ?? ''),
                'baseUrl' => (($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' ? 'https' : 'http') . '://' . (string) ($_SERVER['HTTP_HOST'] ?? ''),
            ]);
            render_public_shell('我的' . get_message_board_name(), $content);
            return;
        }
        if ($path === '/my/message-board/reply' && $method === 'POST') {
            verify_csrf_or_fail();
            if ($allowBoard) {
                try {
                    create_publisher_message($pubAdminId, [
                        'nickname' => trim((string) ($user['name'] ?? '发布者')),
                        'content' => (string) ($_POST['content'] ?? ''),
                        'parent_id' => (int) ($_POST['parent_id'] ?? 0),
                    ]);
                    push_flash('success', '已回复');
                } catch (Throwable $e) { push_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : '操作失败'); }
            }
            redirect('/my/message-board');
        }
        if ($path === '/my/message-board/delete' && $method === 'POST') {
            verify_csrf_or_fail();
            if ($allowBoard) { delete_publisher_message($pubAdminId, (int) ($_POST['id'] ?? 0)); push_flash('success', '已删除'); }
            redirect('/my/message-board');
        }

        // 公告管理
        if ($path === '/my/announcements' && $method === 'GET') {
            if (!$allowAnnounce) { push_flash('error', '当前会员等级未开通公告功能'); redirect('/my'); }
            $content = render_view('templates/ui/phase1/user_announcements.php', [
                'csrf' => csrf_token(),
                'flash' => pop_flash(),
                'announcements' => list_publisher_announcements($pubAdminId, false),
                'refCode' => (string) ($user['ref_code'] ?? ''),
                'baseUrl' => (($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' ? 'https' : 'http') . '://' . (string) ($_SERVER['HTTP_HOST'] ?? ''),
            ]);
            render_public_shell('我的公告', $content);
            return;
        }
        if ($path === '/my/announcements' && $method === 'POST') {
            verify_csrf_or_fail();
            if ($allowAnnounce) {
                try { create_publisher_announcement($pubAdminId, (string) ($_POST['content'] ?? '')); push_flash('success', '公告已发布'); }
                catch (Throwable $e) { push_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : '操作失败'); }
            }
            redirect('/my/announcements');
        }
        if ($path === '/my/announcements/toggle' && $method === 'POST') {
            verify_csrf_or_fail();
            if ($allowAnnounce) { toggle_publisher_announcement($pubAdminId, (int) ($_POST['id'] ?? 0), (int) ($_POST['status'] ?? 0)); }
            redirect('/my/announcements');
        }
        if ($path === '/my/announcements/delete' && $method === 'POST') {
            verify_csrf_or_fail();
            if ($allowAnnounce) { delete_publisher_announcement($pubAdminId, (int) ($_POST['id'] ?? 0)); push_flash('success', '已删除'); }
            redirect('/my/announcements');
        }
    }


    if (preg_match('#^/u/([A-Za-z0-9_-]+)$#', $path, $m) && $method === 'GET') {
        $publisher = find_publisher_by_ref_code($m[1]);
        if (!$publisher || (int) ($publisher['publisher_admin_id'] ?? 0) <= 0) {
            http_response_code(404);
            render_public_shell('页面不存在', '<div class="empty-state"><h1>该发布者暂无任务页。</h1></div>', ['showHeader' => false]);
            return;
        }
        $tasks = list_public_tasks_by_owner((int) $publisher['publisher_admin_id']);
        $list = [];
        foreach ($tasks as $task) {
            $blockReason = validate_task_open_for_submit($task);
            $task['status_text'] = $blockReason === null ? '可提交' : (string) $blockReason;
            $task['status_tone'] = $blockReason === null ? 'ok' : 'off';
            $list[] = $task;
        }
        $pubMembership = get_user_membership((int) $publisher['id']);
        $pubAdminId = (int) $publisher['publisher_admin_id'];
        $allowBoard = (int) ($pubMembership['allow_message_board'] ?? 0) === 1;
        $allowAnnounce = (int) ($pubMembership['allow_announcement'] ?? 0) === 1;
        $content = render_view('templates/ui/phase1/public_task_list.php', [
            'tasks' => $list,
            'latestTaskId' => isset($list[0]) ? (int) ($list[0]['id'] ?? 0) : 0,
            'currentUser' => current_user(),
            'csrf' => csrf_token(),
            'boardRefCode' => $allowBoard ? (string) $publisher['ref_code'] : '',
            'publisherAnnouncements' => $allowAnnounce ? list_publisher_announcements($pubAdminId, true) : [],
        ]);
        render_public_shell((string) $publisher['name'] . ' · 任务打卡', $content, ['showHeader' => false]);
        return;
    }

    // 发布者留言板（公开，成员可留言/回复）
    if (preg_match('#^/u/([A-Za-z0-9_-]+)/messages$#', $path, $m)) {
        $publisher = find_publisher_by_ref_code($m[1]);
        if (!$publisher || (int) ($publisher['publisher_admin_id'] ?? 0) <= 0) {
            http_response_code(404);
            render_public_shell('页面不存在', '<div class="empty-state"><h1>该页面不存在。</h1></div>', ['showHeader' => false]);
            return;
        }
        $pubMembership = get_user_membership((int) $publisher['id']);
        if ((int) ($pubMembership['allow_message_board'] ?? 0) !== 1) {
            http_response_code(404);
            render_public_shell('页面不存在', '<div class="empty-state"><h1>该发布者未开通留言板。</h1></div>', ['showHeader' => false]);
            return;
        }
        $pubAdminId = (int) $publisher['publisher_admin_id'];
        if ($method === 'POST') {
            verify_csrf_or_fail();
            try {
                create_publisher_message($pubAdminId, request_payload());
                push_flash('success', '留言成功');
            } catch (Throwable $e) {
                push_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : '操作失败');
            }
            redirect('/u/' . rawurlencode($m[1]) . '/messages');
        }
        $content = render_view('templates/ui/phase1/public_publisher_board.php', [
            'csrf' => csrf_token(),
            'flash' => pop_flash(),
            'threads' => list_publisher_message_threads($pubAdminId, 200),
            'publisherName' => (string) $publisher['name'],
            'boardName' => get_message_board_name(),
            'refCode' => (string) $m[1],
        ]);
        render_public_shell((string) $publisher['name'] . ' · ' . get_message_board_name(), $content, ['showHeader' => false]);
        return;
    }

    // ─── Admin Routes ───

    if ($path === '/admin/login' && $method === 'GET') {
        $flash = pop_flash();
        $csrf = csrf_token();
        $error = $flash['type'] === 'error' ? (string) ($flash['message'] ?? '') : '';
        $html = render_view('templates/ui/phase1/login_admin.php', [
            'csrf' => $csrf,
            'error' => $error,
        ]);
        render_public_shell('管理员登录', $html, ['showHeader' => false]);
        return;
    }

    if ($path === '/admin/login' && $method === 'POST') {
        verify_csrf_or_fail();
        $data = request_payload();
        $username = trim((string) ($data['username'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        if ($username === '' || $password === '') {
            push_flash('error', '操作失败');
            redirect('/admin/login');
        }

        $admin = attempt_admin_login($username, $password);
        if ($admin === null) {
            push_flash('error', '操作失败');
            redirect('/admin/login');
        }

        push_flash('success', '操作成功');
        redirect('/admin');
    }

    if ($path === '/admin/logout' && $method === 'GET') {
        logout_admin();
        push_flash('success', '操作成功');
        redirect('/admin/login');
    }

    if ($path === '/admin' && $method === 'GET') {
        $admin = require_admin(false);
        $stats = dashboard_summary((int) $admin['id']);
        $content = render_view('templates/ui/phase1/admin_dashboard.php', [
            'stats' => $stats,
        ]);
        render_admin_shell('管理后台', $content, $admin);
        return;
    }

    if ($path === '/admin/members' && $method === 'GET') {
        $admin = require_admin(false);
        $adminId = (int) $admin['id'];
        $keyword = trim((string) ($_GET['keyword'] ?? ''));
        $members = list_members($adminId, $keyword);
        $groups = list_member_groups($adminId);

        $editId = isset($_GET['edit_id']) ? (int) $_GET['edit_id'] : 0;
        $editing = $editId > 0 ? find_member_by_id($adminId, $editId) : null;
        $editingGroupIds = [];
        if ($editing !== null) {
            $groupMap = list_member_group_labels_map($adminId, [(int) $editing['id']]);
            $editingGroupIds = $groupMap[(int) $editing['id']]['group_ids'] ?? [];
        }

        $content = render_view('templates/ui/phase1/admin_members.php', [
            'csrf' => csrf_token(),
            'keyword' => $keyword,
            'members' => $members,
            'groups' => $groups,
            'editing' => $editing,
            'editingGroupIds' => $editingGroupIds,
        ]);
        render_admin_shell('管理后台', $content, $admin);
        return;
    }

    if ($path === '/admin/members' && $method === 'POST') {
        $admin = require_admin(false);
        verify_csrf_or_fail();
        create_member((int) $admin['id'], request_payload());
        push_flash('success', '操作成功');
        redirect('/admin/members');
    }

    if ($path === '/admin/members/update' && $method === 'POST') {
        $admin = require_admin(false);
        verify_csrf_or_fail();
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            push_flash('error', '操作失败');
            redirect('/admin/members');
        }
        update_member((int) $admin['id'], $id, request_payload());
        push_flash('success', '操作成功');
        redirect('/admin/members');
    }

    if ($path === '/admin/members/toggle' && $method === 'POST') {
        $admin = require_admin(false);
        verify_csrf_or_fail();
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            push_flash('error', '操作失败');
            redirect('/admin/members');
        }
        toggle_member_status((int) $admin['id'], $id);
        push_flash('success', '操作成功');
        redirect('/admin/members');
    }

    if ($path === '/admin/members/delete' && $method === 'POST') {
        $admin = require_admin(false);
        verify_csrf_or_fail();
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            push_flash('error', '操作失败');
            redirect('/admin/members');
        }
        soft_delete_member((int) $admin['id'], $id);
        push_flash('success', '操作成功');
        redirect('/admin/members');
    }

    if ($path === '/admin/members/batch' && $method === 'POST') {
        $admin = require_admin(false);
        verify_csrf_or_fail();
        $adminId = (int) $admin['id'];
        $memberIds = normalize_member_ids((string) ($_POST['member_ids'] ?? ''));
        if ($memberIds === []) {
            push_flash('error', '操作失败');
            redirect('/admin/members');
        }

        $action = trim((string) ($_POST['batch_action'] ?? ''));
        try {
            switch ($action) {
                case 'enable':
                    $affected = batch_update_members_status($adminId, $memberIds, 1);
                    push_flash('success', '操作成功');
                    break;
                case 'disable':
                    $affected = batch_update_members_status($adminId, $memberIds, 0);
                    push_flash('success', '操作成功');
                    break;
                case 'delete':
                    $affected = batch_soft_delete_members($adminId, $memberIds);
                    push_flash('success', '操作成功');
                    break;
                case 'assign_group':
                    $groupId = (int) ($_POST['group_id'] ?? 0);
                    if ($groupId <= 0) {
                        push_flash('error', '操作失败');
                        redirect('/admin/members');
                    }
                    $affected = batch_assign_members_group($adminId, $memberIds, $groupId);
                    push_flash('success', '操作成功');
                    break;
                default:
                    push_flash('error', '操作失败');
                    break;
            }
        } catch (Throwable $e) {
            $message = trim((string) $e->getMessage());
            push_flash('error', '操作失败');
        }
        redirect('/admin/members');
    }

    if ($path === '/admin/members/import' && $method === 'POST') {
        $admin = require_admin(false);
        verify_csrf_or_fail();
        try {
            $duplicateMode = (string) ($_POST['duplicate_mode'] ?? 'skip');
            $result = import_members_from_excel((int) $admin['id'], $_FILES['member_excel'] ?? [], $duplicateMode);
            $summary = sprintf(
                '导入完成：总计 %d 条，新增 %d 条，更新 %d 条，跳过 %d 条，失败 %d 条',
                (int) ($result['total'] ?? 0),
                (int) ($result['imported'] ?? 0),
                (int) ($result['updated'] ?? 0),
                (int) ($result['skipped'] ?? 0),
                (int) ($result['failed'] ?? 0)
            );            if (!empty($result['errors'])) {
                push_flash('error', '操作失败');
            } else {
                push_flash('success', '操作成功');
            }
        } catch (Throwable $e) {
            $message = trim((string) $e->getMessage());
            push_flash('error', '操作失败');
        }
        redirect('/admin/members');
    }

    if ($path === '/admin/members/import-template' && $method === 'GET') {
        require_admin(false);
        output_member_import_template_csv();
    }

    if ($path === '/admin/member-groups' && $method === 'GET') {
        $admin = require_admin(false);
        $adminId = (int) $admin['id'];
        $groups = list_member_groups($adminId);
        $allMembers = list_members($adminId, '');

        $editGroupId = isset($_GET['edit_group_id']) ? (int) $_GET['edit_group_id'] : 0;
        $editingGroup = $editGroupId > 0 ? find_member_group_by_id($adminId, $editGroupId) : null;

        $selectedGroupId = isset($_GET['group_id']) ? (int) $_GET['group_id'] : 0;
        $selectedGroup = $selectedGroupId > 0 ? find_member_group_by_id($adminId, $selectedGroupId) : null;
        $groupMemberIds = $selectedGroupId > 0 ? list_member_ids_in_group($adminId, $selectedGroupId) : [];
        $groupMembers = $selectedGroupId > 0 ? list_members_by_group($adminId, $selectedGroupId) : [];

        $memberKeyword = trim((string) ($_GET['member_keyword'] ?? ''));
        if ($memberKeyword !== '' && $groupMembers !== []) {
            $groupMembers = array_values(array_filter(
                $groupMembers,
                static function (array $member) use ($memberKeyword): bool {
                    $haystack = implode(' ', [
                        (string) ($member['name'] ?? ''),
                        (string) ($member['mobile'] ?? ''),
                        (string) ($member['employee_no'] ?? ''),
                        (string) ($member['department'] ?? ''),
                        (string) ($member['position'] ?? ''),
                    ]);
                    return stripos($haystack, $memberKeyword) !== false;
                }
            ));
        }

        $content = render_view('templates/ui/phase1/admin_member_groups.php', [
            'csrf' => csrf_token(),
            'groups' => $groups,
            'allMembers' => $allMembers,
            'editingGroup' => $editingGroup,
            'selectedGroup' => $selectedGroup,
            'groupMemberIds' => $groupMemberIds,
            'groupMembers' => $groupMembers,
            'memberKeyword' => $memberKeyword,
        ]);
        render_admin_shell('管理后台', $content, $admin);
        return;
    }

    if ($path === '/admin/member-groups' && $method === 'POST') {
        $admin = require_admin(false);
        verify_csrf_or_fail();
        create_member_group((int) $admin['id'], request_payload());
        push_flash('success', '操作成功');
        redirect('/admin/member-groups');
    }

    if ($path === '/admin/member-groups/update' && $method === 'POST') {
        $admin = require_admin(false);
        verify_csrf_or_fail();
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            push_flash('error', '操作失败');
            redirect('/admin/member-groups');
        }
        update_member_group((int) $admin['id'], $id, request_payload());
        push_flash('success', '操作成功');
        redirect('/admin/member-groups');
    }

    if ($path === '/admin/member-groups/delete' && $method === 'POST') {
        $admin = require_admin(false);
        verify_csrf_or_fail();
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            push_flash('error', '操作失败');
            redirect('/admin/member-groups');
        }
        try {
            delete_member_group((int) $admin['id'], $id);
            push_flash('success', '操作成功');
        } catch (Throwable $e) {
            $message = trim((string) $e->getMessage());
            push_flash('error', '操作失败');
        }
        redirect('/admin/member-groups');
    }

    if ($path === '/admin/member-groups/members/add' && $method === 'POST') {
        $admin = require_admin(false);
        verify_csrf_or_fail();
        $groupId = (int) ($_POST['group_id'] ?? 0);
        $memberIds = normalize_member_ids($_POST['member_ids'] ?? []);
        if ($groupId <= 0) {
            push_flash('error', '操作失败');
            redirect('/admin/member-groups');
        }
        if ($memberIds === []) {
            push_flash('error', '操作失败');
            redirect('/admin/member-groups?group_id=' . $groupId);
        }
        $affected = add_members_to_group((int) $admin['id'], $groupId, $memberIds);
        push_flash('success', '操作成功');
        redirect('/admin/member-groups?group_id=' . $groupId);
    }

    if ($path === '/admin/member-groups/members/remove' && $method === 'POST') {
        $admin = require_admin(false);
        verify_csrf_or_fail();
        $groupId = (int) ($_POST['group_id'] ?? 0);
        $memberIds = normalize_member_ids($_POST['member_ids'] ?? ($_POST['member_id'] ?? []));
        if ($groupId <= 0) {
            push_flash('error', '操作失败');
            redirect('/admin/member-groups');
        }
        if ($memberIds === []) {
            push_flash('error', '操作失败');
            redirect('/admin/member-groups?group_id=' . $groupId);
        }
        $affected = remove_members_from_group((int) $admin['id'], $groupId, $memberIds);
        push_flash('success', '操作成功');
        redirect('/admin/member-groups?group_id=' . $groupId);
    }

    if ($path === '/admin/member-groups/members/sync' && $method === 'POST') {
        $admin = require_admin(false);
        verify_csrf_or_fail();
        $groupId = (int) ($_POST['group_id'] ?? 0);
        if ($groupId <= 0) {
            push_flash('error', '操作失败');
            redirect('/admin/member-groups');
        }
        $memberIds = normalize_member_ids($_POST['member_ids'] ?? []);
        $count = sync_group_members((int) $admin['id'], $groupId, $memberIds);
        push_flash('success', '操作成功');
        redirect('/admin/member-groups?group_id=' . $groupId);
    }

    if ($path === '/admin/tasks' && $method === 'GET') {
        $admin = require_admin(false);
        $adminId = (int) $admin['id'];
        $keyword = trim((string) ($_GET['keyword'] ?? ''));
        $tasks = list_tasks($adminId);
        if ($keyword !== '') {
            $tasks = array_values(array_filter(
                $tasks,
                static function (array $task) use ($keyword): bool {
                    $haystack = implode(' ', [
                        (string) ($task['task_name'] ?? ''),
                        (string) ($task['task_desc'] ?? ''),
                        (string) ($task['task_link_code'] ?? ''),
                    ]);
                    return stripos($haystack, $keyword) !== false;
                }
            ));
        }

        $create = isset($_GET['create']) && (int) $_GET['create'] === 1;
        $editId = isset($_GET['edit_id']) ? (int) $_GET['edit_id'] : 0;
        $editing = $editId > 0 ? find_task_by_id($adminId, $editId) : null;

        if ($create || $editing !== null) {
            $members = array_values(array_filter(
                list_members($adminId, ''),
                static fn (array $member): bool => (int) ($member['status'] ?? 0) === 1
            ));
            $memberGroups = list_member_groups($adminId);
            $selectedMemberIds = $editing ? task_member_ids((int) $editing['id']) : [];

            $startRaw = (string) ($editing['start_time'] ?? date('Y-m-d H:i:s'));
            $startTime = to_datetime_local($startRaw);
            $expireMode = 'three_days';
            $customDays = '3';
            $currentMonth = '0';
            if ($editing !== null) {
                if ((int) ($editing['is_long_term'] ?? 0) === 1 || empty($editing['end_time'])) {
                    $expireMode = 'long_term';
                } else {
                    $startAt = strtotime((string) ($editing['start_time'] ?? ''));
                    $endAt = strtotime((string) ($editing['end_time'] ?? ''));
                    // 「当月有效」识别：结束时间恰为起始月份最后一天 23:59:59。
                    if ($startAt !== false && (string) ($editing['end_time'] ?? '') === date('Y-m-t 23:59:59', $startAt)) {
                        $currentMonth = '1';
                    } elseif ($startAt !== false && $endAt !== false && $endAt >= $startAt) {
                        $daysDiff = (int) floor(($endAt - $startAt) / 86400);
                        if ($daysDiff === 3) {
                            $expireMode = 'three_days';
                            $customDays = '3';
                        } elseif ($daysDiff === 5) {
                            $expireMode = 'five_days';
                            $customDays = '5';
                        } elseif ($daysDiff === 7) {
                            $expireMode = 'one_week';
                            $customDays = '7';
                        } else {
                            $expireMode = 'custom_days';
                            $customDays = (string) max(1, $daysDiff);
                        }
                    }
                }
            }

            $content = render_view('templates/ui/phase1/admin_task_form.php', [
                'csrf' => csrf_token(),
                'formAction' => $editing ? '/admin/tasks/update' : '/admin/tasks',
                'editing' => $editing,
                'startTime' => $startTime,
                'expireMode' => $expireMode,
                'customDays' => $customDays,
                'currentMonth' => $currentMonth,
                'members' => $members,
                'selectedMemberIds' => $selectedMemberIds,
                'memberGroups' => $memberGroups,
                'selectedGroupIds' => [],
            ]);
            render_admin_shell('管理后台', $content, $admin);
            return;
        }

        $content = render_view('templates/ui/phase1/admin_tasks_list.php', [
            'tasks' => $tasks,
            'keyword' => $keyword,
            'csrf' => csrf_token(),
        ]);
        render_admin_shell('管理后台', $content, $admin);
        return;
    }

    if ($path === '/admin/tasks' && $method === 'POST') {
        $admin = require_admin(false);
        verify_csrf_or_fail();
        try {
            create_task((int) $admin['id'], request_payload());
            push_flash('success', '操作成功');
        } catch (Throwable $e) {
            $message = trim((string) $e->getMessage());
            push_flash('error', '操作失败');
        }
        redirect('/admin/tasks');
    }

    if ($path === '/admin/tasks/update' && $method === 'POST') {
        $admin = require_admin(false);
        verify_csrf_or_fail();
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            push_flash('error', '操作失败');
            redirect('/admin/tasks');
        }
        try {
            update_task((int) $admin['id'], $id, request_payload());
            push_flash('success', '操作成功');
        } catch (Throwable $e) {
            $message = trim((string) $e->getMessage());
            push_flash('error', '操作失败');
        }
        redirect('/admin/tasks');
    }

    if ($path === '/admin/tasks/hide' && $method === 'POST') {
        $admin = require_admin(false);
        verify_csrf_or_fail();
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            push_flash('error', '操作失败');
            redirect('/admin/tasks');
        }
        set_task_hidden_status((int) $admin['id'], $id, true);
        push_flash('success', '操作成功');
        redirect('/admin/tasks');
    }

    if ($path === '/admin/tasks/show' && $method === 'POST') {
        $admin = require_admin(false);
        verify_csrf_or_fail();
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            push_flash('error', '操作失败');
            redirect('/admin/tasks');
        }
        set_task_hidden_status((int) $admin['id'], $id, false);
        push_flash('success', '操作成功');
        redirect('/admin/tasks');
    }

    if ($path === '/admin/tasks/delete' && $method === 'POST') {
        $admin = require_admin(false);
        verify_csrf_or_fail();
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            push_flash('error', '操作失败');
            redirect('/admin/tasks');
        }
        try {
            $result = delete_task_with_records((int) $admin['id'], $id);
            push_flash('success', '操作成功');
        } catch (Throwable $e) {
            $message = trim((string) $e->getMessage());
            push_flash('error', '操作失败');
        }
        redirect('/admin/tasks');
    }

    if ($path === '/admin/center' && $method === 'GET') {
        $admin = require_admin(false);
        $adminId = (int) $admin['id'];
        $autoPurgeDays = get_checkin_recycle_auto_purge_days();
        $autoPurgedCount = 0;
        if ($autoPurgeDays > 0) {
            $autoPurgedCount = purge_soft_deleted_checkin_records($adminId, $autoPurgeDays);
        }

        $content = render_view('templates/ui/phase1/admin_center.php', [
            'csrf' => csrf_token(),
            'imageCompressionEnabled' => is_checkin_image_compress_enabled(),
            'publicThemeToggleEnabled' => is_public_theme_toggle_enabled(),
            'recycleRows' => list_deleted_checkin_records($adminId, 300),
            'recycleTotal' => count_deleted_checkin_records($adminId),
            'recycleAutoPurgeDays' => $autoPurgeDays,
            'autoPurgedCount' => $autoPurgedCount,
            'siteName' => get_site_name(),
            'siteFooterText' => get_site_footer_text(),
            'canCustomTitle' => admin_can_custom_title($admin),
        ]);
        render_admin_shell('管理后台', $content, $admin);
        return;
    }

    if ($path === '/admin/center/stats' && $method === 'GET') {
        $admin = require_admin(false);
        $adminId = (int) $admin['id'];
        $focusType = trim((string) ($_GET['focus'] ?? 'task_count'));
        if (!is_dashboard_stat_type($focusType)) {
            $focusType = 'task_count';
        }
        $selectedTaskId = (int) ($_GET['task_id'] ?? 0);
        $context = dashboard_stat_context($adminId, $selectedTaskId > 0 ? $selectedTaskId : null);

        $content = render_view('templates/ui/phase1/admin_center_stats.php', [
            'focusType' => $focusType,
            'sections' => list_dashboard_stat_sections($adminId, $selectedTaskId > 0 ? $selectedTaskId : null),
            'taskOptions' => $context['task_options'] ?? [],
            'selectedTaskId' => (int) ($context['current_task_id'] ?? 0),
            'currentTask' => $context['current_task'] ?? null,
        ]);
        render_admin_shell('统计中心', $content, $admin);
        return;
    }

    if ($path === '/admin/center/stats/export' && $method === 'GET') {
        $admin = require_admin(false);
        $type = trim((string) ($_GET['type'] ?? ''));
        $selectedTaskId = (int) ($_GET['task_id'] ?? 0);
        if (!is_dashboard_stat_type($type)) {
            push_flash('error', '导出失败');
            redirect('/admin/center/stats');
        }

        try {
            $section = get_dashboard_stat_section((int) $admin['id'], $type, $selectedTaskId > 0 ? $selectedTaskId : null);
            $headers = is_array($section['headers'] ?? null) ? $section['headers'] : [];
            $rows = is_array($section['rows'] ?? null) ? $section['rows'] : [];
            $binary = build_simple_xlsx_binary($headers, $rows);
            $filename = 'dashboard_' . $type . '_' . date('Ymd_His') . '.xlsx';
            output_xlsx_download($binary, $filename);
        } catch (Throwable $e) {
            push_flash('error', '导出失败');
            $redirectUrl = '/admin/center/stats?focus=' . urlencode($type);
            if ($selectedTaskId > 0) {
                $redirectUrl .= '&task_id=' . $selectedTaskId;
            }
            redirect($redirectUrl);
        }
    }

    if ($path === '/admin/center/stats/monthly' && $method === 'GET') {
        $admin = require_admin(false);
        $month = normalize_stat_month((string) ($_GET['month'] ?? ''));
        $stat = build_monthly_uncheckin_stat((int) $admin['id'], $month);

        $content = render_view('templates/ui/phase1/admin_center_monthly.php', [
            'month' => $month,
            'stat' => $stat,
        ]);
        render_admin_shell('按月统计', $content, $admin);
        return;
    }

    if ($path === '/admin/center/stats/monthly/export' && $method === 'GET') {
        $admin = require_admin(false);
        $month = normalize_stat_month((string) ($_GET['month'] ?? ''));

        try {
            $stat = build_monthly_uncheckin_stat((int) $admin['id'], $month);
            $headers = is_array($stat['export_headers'] ?? null) ? $stat['export_headers'] : [];
            $rows = is_array($stat['export_rows'] ?? null) ? $stat['export_rows'] : [];
            $binary = build_simple_xlsx_binary($headers, $rows);
            $filename = 'monthly_uncheckin_' . $month . '_' . date('Ymd_His') . '.xlsx';
            output_xlsx_download($binary, $filename);
        } catch (Throwable $e) {
            push_flash('error', '导出失败');
            redirect('/admin/center/stats/monthly?month=' . urlencode($month));
        }
    }

    if ($path === '/admin/center/image-compression' && $method === 'POST') {
        $admin = require_admin(false);
        verify_csrf_or_fail();
        $enabled = parse_bool($_POST['enabled'] ?? false);
        set_checkin_image_compress_enabled((int) $admin['id'], $enabled);
        push_flash('success', '操作成功');
        redirect('/admin/center');
    }

    if ($path === '/admin/center/theme-toggle' && $method === 'POST') {
        $admin = require_admin(false);
        verify_csrf_or_fail();
        $enabled = parse_bool($_POST['enabled'] ?? false);
        set_public_theme_toggle_enabled((int) $admin['id'], $enabled);
        push_flash('success', '操作成功');
        redirect('/admin/center');
    }

    if ($path === '/admin/center/site-meta' && $method === 'POST') {
        $admin = require_admin(false);
        verify_csrf_or_fail();
        try {
            if (admin_can_custom_title($admin)) {
                set_site_name((int) $admin['id'], (string) ($_POST['site_name'] ?? ''));
            }
            set_site_footer_text((int) $admin['id'], (string) ($_POST['site_footer_text'] ?? ''));
            push_flash('success', admin_can_custom_title($admin) ? '操作成功' : '当前会员等级无权修改网站标题，其余设置已保存');
        } catch (Throwable $e) {
            push_flash('error', '操作失败');
        }
        redirect('/admin/center');
    }

    if ($path === '/admin/center/recycle/settings' && $method === 'POST') {
        $admin = require_admin(false);
        verify_csrf_or_fail();
        $days = (int) ($_POST['auto_purge_days'] ?? 0);
        set_checkin_recycle_auto_purge_days((int) $admin['id'], $days);
        push_flash('success', '操作成功');
        redirect('/admin/center');
    }

    if ($path === '/admin/center/recycle/purge-now' && $method === 'POST') {
        $admin = require_admin(false);
        verify_csrf_or_fail();
        $days = get_checkin_recycle_auto_purge_days();
        if ($days <= 0) {
            push_flash('error', '操作失败');
            redirect('/admin/center');
        }
        $deletedCount = purge_soft_deleted_checkin_records((int) $admin['id'], $days, 1000);
        push_flash('success', '操作成功');
        redirect('/admin/center');
    }

    if ($path === '/admin/center/recycle/delete' && $method === 'POST') {
        $admin = require_admin(false);
        verify_csrf_or_fail();
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            push_flash('error', '操作失败');
            redirect('/admin/center');
        }
        try {
            $result = delete_checkin_record_permanently((int) $admin['id'], $id);
            push_flash('success', '操作成功');
        } catch (Throwable $e) {
            push_flash('error', '操作失败');
        }
        redirect('/admin/center');
    }

    if ($path === '/admin/center/recycle/restore' && $method === 'POST') {
        $admin = require_admin(false);
        verify_csrf_or_fail();
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            push_flash('error', '操作失败');
            redirect('/admin/center');
        }
        try {
            restore_checkin_record((int) $admin['id'], $id);
            push_flash('success', '操作成功');
        } catch (Throwable $e) {
            push_flash('error', '操作失败');
        }
        redirect('/admin/center');
    }

    if ($path === '/admin/records' && $method === 'GET') {
        $admin = require_admin(false);
        $adminId = (int) $admin['id'];
        $filters = [
            'task_id' => (int) ($_GET['task_id'] ?? 0),
            'member_id' => (int) ($_GET['member_id'] ?? 0),
            'date_from' => trim((string) ($_GET['date_from'] ?? '')),
            'date_to' => trim((string) ($_GET['date_to'] ?? '')),
        ];
        $content = render_view('templates/ui/phase1/admin_records_list.php', [
            'filters' => $filters,
            'tasks' => list_tasks($adminId),
            'members' => list_members($adminId, ''),
            'rows' => list_checkin_records($adminId, 200, $filters),
            'csrf' => csrf_token(),
        ]);
        render_admin_shell('管理后台', $content, $admin);
        return;
    }

    if ($path === '/admin/records/export' && $method === 'POST') {
        $admin = require_admin(false);
        verify_csrf_or_fail();
        $recordIds = normalize_member_ids($_POST['record_ids'] ?? []);
        if ($recordIds === []) {
            push_flash('error', '操作失败');
            redirect('/admin/records');
        }
        try {
            $rows = list_checkin_records_for_export((int) $admin['id'], $recordIds);
            if ($rows === []) {
                push_flash('error', '操作失败');
                redirect('/admin/records');
            }
            output_checkin_records_xlsx(build_checkin_records_xlsx($rows));
        } catch (Throwable $e) {
            $message = trim((string) $e->getMessage());
            push_flash('error', '操作失败');
            redirect('/admin/records');
        }
    }

    if ($path === '/admin/records/batch-delete' && $method === 'POST') {
        $admin = require_admin(false);
        verify_csrf_or_fail();
        $recordIds = normalize_member_ids($_POST['record_ids'] ?? []);
        if ($recordIds === []) {
            push_flash('error', '操作失败');
            redirect('/admin/records');
        }
        try {
            $affected = batch_soft_delete_checkin_records((int) $admin['id'], $recordIds);
            if ($affected > 0) {
                push_flash('success', '操作成功');
            } else {
                push_flash('error', '操作失败');
            }
        } catch (Throwable $e) {
            push_flash('error', '操作失败');
        }
        redirect('/admin/records');
    }

    if ($path === '/admin/records/create' && $method === 'GET') {
        $admin = require_admin(false);
        $tasks = list_tasks((int) $admin['id']);
        $members = array_values(array_filter(
            list_members((int) $admin['id'], ''),
            static fn (array $member): bool => (int) ($member['status'] ?? 0) === 1
        ));
        $content = render_view('templates/ui/phase1/admin_records_create.php', [
            'csrf' => csrf_token(),
            'tasks' => $tasks,
            'members' => $members,
            'today' => date('Y-m-d'),
            'nowLocal' => to_datetime_local(date('Y-m-d H:i:s')),
        ]);
        render_admin_shell('管理后台', $content, $admin);
        return;
    }

    if ($path === '/admin/records/create' && $method === 'POST') {
        $admin = require_admin(false);
        verify_csrf_or_fail();
        try {
            $id = create_checkin_record_by_admin((int) $admin['id'], request_payload(), $_FILES);
            push_flash('success', '操作成功');
            redirect('/admin/records/detail?id=' . $id);
        } catch (Throwable $e) {
            $message = trim((string) $e->getMessage());
            push_flash('error', '操作失败');
            redirect('/admin/records/create');
        }
    }

    if ($path === '/admin/records/edit' && $method === 'GET') {
        $admin = require_admin(false);
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            push_flash('error', '操作失败');
            redirect('/admin/records');
        }
        $record = find_checkin_record_by_id((int) $admin['id'], $id, true);
        if ($record === null) {
            push_flash('error', '操作失败');
            redirect('/admin/records');
        }
        $content = render_view('templates/ui/phase1/admin_records_edit.php', [
            'csrf' => csrf_token(),
            'record' => $record,
        ]);
        render_admin_shell('管理后台', $content, $admin);
        return;
    }

    if ($path === '/admin/records/edit' && $method === 'POST') {
        $admin = require_admin(false);
        verify_csrf_or_fail();
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            push_flash('error', '操作失败');
            redirect('/admin/records');
        }
        try {
            update_checkin_record_basic((int) $admin['id'], $id, request_payload());
            push_flash('success', '操作成功');
            redirect('/admin/records/detail?id=' . $id);
        } catch (Throwable $e) {
            $message = trim((string) $e->getMessage());
            push_flash('error', '操作失败');
            redirect('/admin/records/edit?id=' . $id);
        }
    }

    if ($path === '/admin/records/detail' && $method === 'GET') {
        $admin = require_admin(false);
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            push_flash('error', '操作失败');
            redirect('/admin/records');
        }
        $record = find_checkin_record_by_id((int) $admin['id'], $id, true);
        if ($record === null) {
            push_flash('error', '操作失败');
            redirect('/admin/records');
        }

        $statusText = ((int) ($record['is_overridden'] ?? 0) === 1) ? '已覆盖' : '正常';
        if (!empty($record['deleted_at'])) {
            $statusText .= '（已删除）';
        }
        $content = render_view('templates/ui/phase1/admin_records_detail.php', [
            'record' => $record,
            'images' => list_checkin_record_images((int) $record['id']),
            'statusText' => $statusText,
            'submitSourceLabel' => label_submit_source((string) ($record['submit_source'] ?? 'web')),
        ]);
        render_admin_shell('管理后台', $content, $admin);
        return;
    }

    if ($path === '/admin/records/delete' && $method === 'POST') {
        $admin = require_admin(false);
        verify_csrf_or_fail();
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            push_flash('error', '操作失败');
            redirect('/admin/records');
        }
        try {
            soft_delete_checkin_record((int) $admin['id'], $id);
            push_flash('success', '操作成功');
        } catch (Throwable $e) {
            push_flash('error', '操作失败');
        }
        redirect('/admin/records');
    }

    if ($path === '/admin/records/restore' && $method === 'POST') {
        $admin = require_admin(false);
        verify_csrf_or_fail();
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            push_flash('error', '操作失败');
            redirect('/admin/records');
        }
        try {
            restore_checkin_record((int) $admin['id'], $id);
            push_flash('success', '操作成功');
        } catch (Throwable $e) {
            push_flash('error', '操作失败');
        }
        redirect('/admin/records');
    }

    if ($path === '/admin/messages' && $method === 'GET') {
        $admin = require_admin(false);
        $filters = [
            'keyword' => trim((string) ($_GET['keyword'] ?? '')),
            'status' => trim((string) ($_GET['status'] ?? '')),
        ];
        $rows = list_online_messages_threaded($filters, 200);
        $parentIds = array_map(static fn (array $row): int => (int) ($row['id'] ?? 0), $rows);
        $replyMap = list_online_message_replies_map($parentIds);
        $content = render_view('templates/ui/phase1/admin_messages.php', [
            'csrf' => csrf_token(),
            'filters' => $filters,
            'rows' => $rows,
            'replyMap' => $replyMap,
            'announcements' => list_message_announcements(200),
        ]);
        render_admin_shell('管理后台', $content, $admin);
        return;
    }

    if ($path === '/admin/messages/status' && $method === 'POST') {
        require_admin(false);
        verify_csrf_or_fail();
        $id = (int) ($_POST['id'] ?? 0);
        $status = (int) ($_POST['status'] ?? 0);
        if ($id <= 0) {
            push_flash('error', '操作失败');
            redirect('/admin/messages');
        }
        update_online_message_status($id, $status);
        push_flash('success', '操作成功');
        redirect('/admin/messages');
    }

    if ($path === '/admin/messages/reply' && $method === 'POST') {
        $admin = require_admin(false);
        verify_csrf_or_fail();
        $parentId = (int) ($_POST['parent_id'] ?? 0);
        $content = trim((string) ($_POST['content'] ?? ''));
        try {
            create_online_message_admin_reply((int) $admin['id'], $parentId, $content);
            push_flash('success', '操作成功');
        } catch (Throwable $e) {
            $message = trim((string) $e->getMessage());
            push_flash('error', '操作失败');
        }
        redirect('/admin/messages');
    }

    if ($path === '/admin/messages/announcement/create' && $method === 'POST') {
        $admin = require_admin(false);
        verify_csrf_or_fail();
        try {
            create_message_announcement((int) $admin['id'], request_payload());
            push_flash('success', '操作成功');
        } catch (Throwable $e) {
            $message = trim((string) $e->getMessage());
            push_flash('error', '操作失败');
        }
        redirect('/admin/messages');
    }

    if ($path === '/admin/messages/announcement/status' && $method === 'POST') {
        $admin = require_admin(false);
        verify_csrf_or_fail();
        $id = (int) ($_POST['id'] ?? 0);
        $isPublished = parse_bool($_POST['is_published'] ?? false);
        if ($id <= 0) {
            push_flash('error', '操作失败');
            redirect('/admin/messages');
        }
        update_message_announcement_status((int) $admin['id'], $id, $isPublished);
        push_flash('success', '操作成功');
        redirect('/admin/messages');
    }

    if ($path === '/admin/messages/announcement/delete' && $method === 'POST') {
        $admin = require_admin(false);
        verify_csrf_or_fail();
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            push_flash('error', '操作失败');
            redirect('/admin/messages');
        }
        delete_message_announcement((int) $admin['id'], $id);
        push_flash('success', '操作成功');
        redirect('/admin/messages');
    }

    if ($path === '/admin/messages/delete' && $method === 'POST') {
        require_admin(false);
        verify_csrf_or_fail();
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            push_flash('error', '操作失败');
            redirect('/admin/messages');
        }
        delete_online_message_with_replies($id);
        push_flash('success', '操作成功');
        redirect('/admin/messages');
    }

    // ─── Admin Popup Routes ───

    if ($path === '/admin/users' && $method === 'GET') {
        $admin = require_admin(false);
        $adminId = (int) $admin['id'];
        $filters = [
            'keyword' => trim((string) ($_GET['keyword'] ?? '')),
            'status' => (string) ($_GET['status'] ?? ''),
            'bind' => (string) ($_GET['bind'] ?? ''),
        ];
        $flash = pop_flash();
        $newPassword = $flash['type'] === 'info' ? (string) ($flash['meta']['password'] ?? '') : '';
        $users = list_all_users($filters);
        $allMembers = list_members($adminId, '');
        $content = render_view('templates/ui/phase1/admin_users.php', [
            'users' => $users,
            'csrf' => csrf_token(),
            'filters' => $filters,
            'newPassword' => $newPassword,
            'allMembers' => $allMembers,
            'levels' => list_membership_levels(),
            'flash' => $flash,
        ]);
        render_admin_shell('用户管理', $content, $admin);
        return;
    }

    if ($path === '/admin/users/set-level' && $method === 'POST') {
        require_admin(false);
        verify_csrf_or_fail();
        $p = request_payload();
        $uid = (int) ($p['id'] ?? 0);
        if ($uid > 0) {
            set_user_membership($uid, (int) ($p['level_id'] ?? 1), trim((string) ($p['expire_at'] ?? '')) ?: null);
            push_flash('success', '会员等级已更新');
        }
        redirect('/admin/users');
    }

    if ($path === '/admin/membership' && $method === 'GET') {
        $admin = require_admin(false);
        $content = render_view('templates/ui/phase1/admin_membership.php', [
            'levels' => list_membership_levels(),
            'csrf' => csrf_token(),
            'flash' => pop_flash(),
        ]);
        render_admin_shell('会员等级', $content, $admin);
        return;
    }

    if ($path === '/admin/membership' && $method === 'POST') {
        require_admin(false);
        verify_csrf_or_fail();
        $p = request_payload();
        $id = (int) ($p['id'] ?? 0);
        if ($id > 0) {
            db()->prepare('UPDATE membership_levels SET name = :name, max_members = :mm, max_task_days = :td, max_active_tasks = :at, allow_sub_register = :sr, allow_survey = :sv, allow_message_board = :mb, allow_announcement = :an, allow_custom_title = :ct, max_surveys = :ms, max_survey_responses = :mr WHERE id = :id')
                ->execute([
                    'name' => trim((string) ($p['name'] ?? '')) ?: '未命名',
                    'mm' => max(0, (int) ($p['max_members'] ?? 0)),
                    'td' => max(0, (int) ($p['max_task_days'] ?? 0)),
                    'at' => max(0, (int) ($p['max_active_tasks'] ?? 0)),
                    'sr' => isset($p['allow_sub_register']) ? 1 : 0,
                    'sv' => isset($p['allow_survey']) ? 1 : 0,
                    'mb' => isset($p['allow_message_board']) ? 1 : 0,
                    'an' => isset($p['allow_announcement']) ? 1 : 0,
                    'ct' => isset($p['allow_custom_title']) ? 1 : 0,
                    'ms' => max(0, (int) ($p['max_surveys'] ?? 0)),
                    'mr' => max(0, (int) ($p['max_survey_responses'] ?? 0)),
                    'id' => $id,
                ]);
            push_flash('success', '等级配额已保存');
        }
        redirect('/admin/membership');
    }

    // ---- 商城前台 ----

    if ($path === '/shop' && $method === 'GET') {
        $user = require_user(false);
        $content = render_view('templates/ui/phase1/shop.php', [
            'plans' => list_shop_plans(),
            'levels' => list_membership_levels(),
            'csrf' => csrf_token(),
            'membership' => get_user_membership((int) $user['id']),
            'userPoints' => get_user_points((int) $user['id']),
            'userBalance' => get_user_balance((int) $user['id']),
            'trialLevelId' => (int) get_setting('trial_level_id', '2'),
            'trialDays' => (int) get_setting('trial_days', '3'),
            'flash' => pop_flash(),
        ]);
        render_public_shell('升级会员', $content);
        return;
    }

    if (preg_match('#^/shop/level/(\d+)$#', $path, $m) && $method === 'GET') {
        $user = require_user(false);
        $levelId = (int) $m[1];
        $levels = list_membership_levels();
        $level = null;
        foreach ($levels as $lv) { if ((int) $lv['id'] === $levelId) { $level = $lv; break; } }
        if (!$level) { http_response_code(404); render_public_shell('404', '<h1>等级不存在</h1>', ['showHeader' => false]); return; }
        $content = render_view('templates/ui/phase1/shop_level.php', [
            'level' => $level,
            'plans' => array_values(array_filter(list_shop_plans(), static fn($p) => (int) $p['level_id'] === $levelId && (int) ($p['is_trial_member'] ?? 0) === 0)),
            'csrf' => csrf_token(),
            'membership' => get_user_membership((int) $user['id']),
            'flash' => pop_flash(),
        ]);
        render_public_shell((string) $level['name'], $content);
        return;
    }

    if ($path === '/shop/redeem' && $method === 'POST') {
        $user = require_user(false);
        verify_csrf_or_fail();
        try {
            redeem_card_key_direct(trim((string) (request_payload()['card_code'] ?? '')), (int) $user['id']);
            push_flash('success', '卡密验证成功，会员已开通！');
        } catch (Throwable $e) {
            push_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : '兑换失败');
        }
        redirect('/shop');
    }


    if ($path === '/shop/order' && $method === 'POST') {
        $user = require_user(false);
        verify_csrf_or_fail();
        $planId = (int) (request_payload()['plan_id'] ?? 0);
        try {
            $order = create_order((int) $user['id'], $planId);
            redirect('/shop/order/' . $order['id']);
        } catch (Throwable $e) {
            push_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : '下单失败');
            redirect('/shop');
        }
    }

    if (preg_match('#^/shop/order/(\d+)$#', $path, $m) && $method === 'GET') {
        $user = require_user(false);
        $order = get_user_order((int) $m[1], (int) $user['id']);
        if (!$order) { http_response_code(404); render_public_shell('404', '<h1>订单不存在</h1>', ['showHeader' => false]); return; }
        $content = render_view('templates/ui/phase1/order_detail.php', [
            'order' => $order,
            'transferInfo' => get_setting('transfer_info', '卡密请到下方地址购买。'),
            'userBalance' => get_user_balance((int) $user['id']),
            'csrf' => csrf_token(),
            'flash' => pop_flash(),
        ]);
        render_public_shell('订单详情', $content);
        return;
    }

    if (preg_match('#^/shop/order/(\d+)/confirm$#', $path, $m) && $method === 'POST') {
        $user = require_user(false);
        verify_csrf_or_fail();
        $p = request_payload();
        try {
            mark_order_confirming((int) $m[1], (int) $user['id'], trim((string) ($p['transfer_note'] ?? '')));
            push_flash('success', '已提交，等待管理员确认');
        } catch (Throwable $e) {
            push_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : '操作失败');
        }
        redirect('/shop/order/' . $m[1]);
    }

    // ---- 后台：订单管理 ----

    if ($path === '/admin/orders' && $method === 'GET') {
        $admin = require_admin(false);
        $filters = ['status' => (string) ($_GET['status'] ?? ''), 'keyword' => trim((string) ($_GET['keyword'] ?? ''))];
        $content = render_view('templates/ui/phase1/admin_orders.php', [
            'orders' => list_orders($filters),
            'filters' => $filters,
            'csrf' => csrf_token(),
            'flash' => pop_flash(),
        ]);
        render_admin_shell('订单管理', $content, $admin);
        return;
    }

    if ($path === '/admin/orders/confirm' && $method === 'POST') {
        require_admin(false);
        verify_csrf_or_fail();
        $p = request_payload();
        try {
            confirm_order((int) ($p['id'] ?? 0), trim((string) ($p['admin_note'] ?? '')));
            push_flash('success', '已确认到账，会员已升级');
        } catch (Throwable $e) {
            push_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : '操作失败');
        }
        redirect('/admin/orders');
    }

    if ($path === '/admin/orders/cancel' && $method === 'POST') {
        require_admin(false);
        verify_csrf_or_fail();
        $p = request_payload();
        try {
            cancel_order((int) ($p['id'] ?? 0), trim((string) ($p['admin_note'] ?? '')));
            push_flash('success', '订单已取消');
        } catch (Throwable $e) {
            push_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : '操作失败');
        }
        redirect('/admin/orders');
    }

    // ---- 后台：套餐管理 ----

    if ($path === '/admin/shop-plans' && $method === 'GET') {
        $admin = require_admin(false);
        $content = render_view('templates/ui/phase1/admin_shop_plans.php', [
            'plans' => list_all_shop_plans(),
            'transferInfo' => get_setting('transfer_info', '卡密请到下方地址购买。'),
            'levels' => list_membership_levels(),
            'trialLevelId' => (int) get_setting('trial_level_id', '2'),
            'csrf' => csrf_token(),
            'flash' => pop_flash(),
        ]);
        render_admin_shell('套餐管理', $content, $admin);
        return;
    }

    if ($path === '/admin/shop-plans' && $method === 'POST') {
        require_admin(false);
        verify_csrf_or_fail();
        $p = request_payload();
        // 保存购买提示
        set_setting('transfer_info', trim((string) ($p['transfer_info'] ?? '')));
        // 保存体验会员设置（仅等级，天数以套餐表为准）
        $tLevel = (int) ($p['trial_level_id'] ?? 2);
        if (!in_array($tLevel, [2, 3], true)) $tLevel = 2;
        set_setting('trial_level_id', (string) $tLevel);
        // 批量保存套餐
        $names = (array) ($p['name'] ?? []);
        $days  = (array) ($p['days'] ?? []);
        $prices = (array) ($p['price_fen'] ?? []);
        $active = (array) ($p['is_active'] ?? []);
        $trial = (array) ($p['is_trial'] ?? []);
        $trialMember = (array) ($p['is_trial_member'] ?? []);
        $ids   = (array) ($p['id'] ?? []);
        foreach ($ids as $i => $pid) {
            $pid = (int) $pid;
            if ($pid <= 0) continue;
            db()->prepare('UPDATE shop_plans SET name=:n, days=:d, price_fen=:p, is_active=:a, is_trial=:t, is_trial_member=:tm WHERE id=:id')
                ->execute(['n' => trim((string)($names[$i] ?? '')), 'd' => max(1,(int)($days[$i]??1)), 'p' => max(0,(int)($prices[$i]??0)), 'a' => isset($active[$i]) ? 1 : 0, 't' => isset($trial[$i]) ? 1 : 0, 'tm' => isset($trialMember[$i]) ? 1 : 0, 'id' => $pid]);
        }
        push_flash('success', '套餐已保存');
        redirect('/admin/shop-plans');
    }

    // ---- 后台：卡密管理 ----

    if ($path === '/admin/card-keys' && $method === 'GET') {
        $admin = require_admin(false);
        $viewBatch = (string) ($_GET['batch'] ?? '');
        $content = render_view('templates/ui/phase1/admin_card_keys.php', [
            'batches' => list_card_key_batches(),
            'viewBatch' => $viewBatch,
            'batchKeys' => $viewBatch !== '' ? list_card_keys_by_batch($viewBatch) : [],
            'plans' => list_all_shop_plans(),
            'csrf' => csrf_token(),
            'flash' => pop_flash(),
        ]);
        render_admin_shell('卡密管理', $content, $admin);
        return;
    }

    if ($path === '/admin/card-keys/export' && $method === 'GET') {
        require_admin(false);
        $batch = (string) ($_GET['batch'] ?? '');
        $keys = $batch !== '' ? list_card_keys_by_batch($batch) : [];
        $body = implode("\r\n", array_map(static fn($k) => (string) $k['code'], $keys));
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="cardkeys_' . ($batch !== '' ? $batch : 'all') . '.txt"');
        echo $body;
        return;
    }

    if ($path === '/admin/card-keys/generate' && $method === 'POST') {
        require_admin(false);
        verify_csrf_or_fail();
        $p = request_payload();
        try {
            $codes = generate_card_keys(
                (int) ($p['plan_id'] ?? 0),
                max(1, min(500, (int) ($p['count'] ?? 10))),
                (string) ($p['charset'] ?? 'alphanum'),
                max(4, min(32, (int) ($p['length'] ?? 16)))
            );
            push_flash('success', '已生成 ' . count($codes) . ' 张卡密');
        } catch (Throwable $e) {
            push_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : '生成失败');
        }
        redirect('/admin/card-keys');
    }

    if ($path === '/admin/card-keys/import' && $method === 'POST') {
        require_admin(false);
        verify_csrf_or_fail();
        $p = request_payload();
        try {
            $r = import_card_keys((int) ($p['plan_id'] ?? 0), (string) ($p['codes'] ?? ''));
            push_flash('success', "导入成功 {$r['ok']} 条，重复 {$r['dup']} 条，无效 {$r['invalid']} 条");
        } catch (Throwable $e) {
            push_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : '导入失败');
        }
        redirect('/admin/card-keys');
    }

    if ($path === '/admin/card-keys/lookup' && $method === 'GET') {
        $admin = require_admin(false);
        $code = strtoupper(trim((string) ($_GET['code'] ?? '')));
        $result = $code !== '' ? lookup_card_key($code) : null;
        $content = render_view('templates/ui/phase1/admin_card_keys.php', [
            'batches' => list_card_key_batches(),
            'viewBatch' => '',
            'batchKeys' => [],
            'plans' => list_all_shop_plans(),
            'csrf' => csrf_token(),
            'flash' => pop_flash(),
            'lookupCode' => $code,
            'lookupResult' => $result,
        ]);
        render_admin_shell('卡密管理', $content, $admin);
        return;
    }

    // ---- 订单：卡密兑换 + 余额支付 ----

    if (preg_match('#^/shop/order/(\d+)/redeem$#', $path, $m) && $method === 'POST') {
        $user = require_user(false);
        verify_csrf_or_fail();
        try {
            redeem_card_key(trim((string) (request_payload()['card_code'] ?? '')), (int) $user['id'], (int) $m[1]);
            push_flash('success', '卡密验证成功，会员已开通！');
        } catch (Throwable $e) {
            push_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : '兑换失败');
        }
        redirect('/shop/order/' . $m[1]);
    }

    if (preg_match('#^/shop/order/(\d+)/pay-balance$#', $path, $m) && $method === 'POST') {
        $user = require_user(false);
        verify_csrf_or_fail();
        try {
            pay_order_by_balance((int) $m[1], (int) $user['id']);
            push_flash('success', '余额支付成功，会员已开通！');
        } catch (Throwable $e) {
            push_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : '支付失败');
        }
        redirect('/shop/order/' . $m[1]);
    }

    // ---- 积分换余额 ----

    if ($path === '/my/convert-points' && $method === 'POST') {
        $user = require_user(false);
        verify_csrf_or_fail();
        try {
            convert_points_to_balance((int) $user['id'], (int) (request_payload()['points'] ?? 0));
            push_flash('success', '兑换成功');
        } catch (Throwable $e) {
            push_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : '兑换失败');
        }
        redirect('/my');
    }

    if ($path === '/admin/users/toggle' && $method === 'POST') {
        require_admin(false);
        verify_csrf_or_fail();
        $id = (int) (request_payload()['id'] ?? 0);
        if ($id > 0) {
            toggle_user_status($id);
        }
        redirect('/admin/users');
    }

    if ($path === '/admin/users/update' && $method === 'POST') {
        require_admin(false);
        verify_csrf_or_fail();
        $p = request_payload();
        $id = (int) ($p['id'] ?? 0);
        if ($id > 0) {
            try {
                admin_update_user($id, $p);
                push_flash('success', '用户信息已更新');
            } catch (Throwable $e) {
                push_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : '更新失败');
            }
        }
        redirect('/admin/users');
    }

    if ($path === '/admin/users/reset-password' && $method === 'POST') {
        require_admin(false);
        verify_csrf_or_fail();
        $id = (int) (request_payload()['id'] ?? 0);
        if ($id > 0) {
            $newPass = reset_user_password($id);
            push_flash('info', '密码已重置', ['password' => $newPass]);
        }
        redirect('/admin/users');
    }

    if ($path === '/admin/users/delete' && $method === 'POST') {
        require_admin(false);
        verify_csrf_or_fail();
        $id = (int) (request_payload()['id'] ?? 0);
        if ($id > 0) {
            delete_user($id);
            push_flash('success', '用户已删除');
        }
        redirect('/admin/users');
    }

    if ($path === '/admin/users/unbind' && $method === 'POST') {
        require_admin(false);
        verify_csrf_or_fail();
        $id = (int) (request_payload()['id'] ?? 0);
        if ($id > 0) {
            admin_unbind_user($id);
            push_flash('success', '已解除绑定');
        }
        redirect('/admin/users');
    }

    if ($path === '/admin/users/rebind' && $method === 'POST') {
        require_admin(false);
        verify_csrf_or_fail();
        $payload = request_payload();
        $userId = (int) ($payload['user_id'] ?? 0);
        $memberId = (int) ($payload['member_id'] ?? 0);
        if ($userId > 0 && $memberId > 0) {
            admin_rebind_user($userId, $memberId);
            push_flash('success', '绑定已更新');
        }
        redirect('/admin/users');
    }

    if ($path === '/admin/popups' && $method === 'GET') {
        $admin = require_admin(false);
        $editing = null;
        $editId = (int) ($_GET['edit'] ?? 0);
        if ($editId > 0) {
            $stmt = db()->prepare('SELECT * FROM popup_announcements WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $editId]);
            $editing = $stmt->fetch() ?: null;
        }
        $content = render_view('templates/ui/phase1/admin_popups.php', [
            'popups' => list_popup_announcements(),
            'csrf' => csrf_token(),
            'editing' => $editing,
        ]);
        render_admin_shell('公告弹窗管理', $content, $admin);
        return;
    }

    if ($path === '/admin/popups' && $method === 'POST') {
        $admin = require_admin(false);
        verify_csrf_or_fail();
        $data = request_payload();
        $contentText = trim((string) ($data['content'] ?? ''));
        $targetAudience = (string) ($data['target_audience'] ?? 'all');
        $frequency = (string) ($data['frequency'] ?? 'once_per_day');
        if ($contentText !== '') {
            create_popup((int) $admin['id'], $contentText, $targetAudience, $frequency);
            push_flash('success', '公告已创建');
        }
        redirect('/admin/popups');
    }

    if ($path === '/admin/popups/update' && $method === 'POST') {
        require_admin(false);
        verify_csrf_or_fail();
        $data = request_payload();
        $id = (int) ($data['id'] ?? 0);
        $contentText = trim((string) ($data['content'] ?? ''));
        $targetAudience = (string) ($data['target_audience'] ?? 'all');
        $frequency = (string) ($data['frequency'] ?? 'once_per_day');
        if ($id > 0 && $contentText !== '') {
            update_popup($id, $contentText, $targetAudience, $frequency);
            push_flash('success', '公告已更新');
        }
        redirect('/admin/popups');
    }

    if ($path === '/admin/popups/toggle' && $method === 'POST') {
        require_admin(false);
        verify_csrf_or_fail();
        $id = (int) (request_payload()['id'] ?? 0);
        if ($id > 0) {
            toggle_popup_status($id);
        }
        redirect('/admin/popups');
    }

    if ($path === '/admin/popups/delete' && $method === 'POST') {
        require_admin(false);
        verify_csrf_or_fail();
        $id = (int) (request_payload()['id'] ?? 0);
        if ($id > 0) {
            delete_popup($id);
            push_flash('success', '公告已删除');
        }
        redirect('/admin/popups');
    }

    if ($path === '/messages' && $method === 'GET') {
        $flash = pop_flash();
        $content = render_view('templates/ui/phase1/public_messages.php', [
            'csrf' => csrf_token(),
            'flash' => $flash,
            'oldNickname' => trim((string) ($_GET['nickname'] ?? '')),
            'oldContent' => '',
            'threads' => list_public_message_threads(120),
            'announcements' => list_published_message_announcements(20),
        ]);
        render_public_shell('任务打卡', $content, ['showHeader' => false]);
        return;
    }

    if ($path === '/messages' && $method === 'POST') {
        verify_csrf_or_fail();
        $payload = request_payload();
        try {
            create_online_message_threaded($payload);
            push_flash('success', '操作成功');
            redirect('/messages');
        } catch (Throwable $e) {
            $message = trim((string) $e->getMessage());
            push_flash('error', '操作失败');
            $nickname = trim((string) ($payload['nickname'] ?? ''));
            redirect('/messages?nickname=' . rawurlencode($nickname));
        }
    }

    if ($path === '/tasks' && $method === 'GET') {
        $tasks = list_public_tasks();
        $latestTaskId = isset($tasks[0]) ? (int) ($tasks[0]['id'] ?? 0) : 0;
        $list = [];
        foreach ($tasks as $task) {
            $blockReason = validate_task_open_for_submit($task);
            $task['status_text'] = $blockReason === null ? '可提交' : (string) $blockReason;
            $task['status_tone'] = $blockReason === null ? 'ok' : 'off';
            $list[] = $task;
        }
        $content = render_view('templates/ui/phase1/public_task_list.php', [
            'tasks' => $list,
            'latestTaskId' => $latestTaskId,
            'currentUser' => current_user(),
            'csrf' => csrf_token(),
        ]);
        render_public_shell('任务打卡', $content, ['showHeader' => false]);
        return;
    }

    if (preg_match('#^/task/([A-Za-z0-9_-]+)$#', $path, $matches) && $method === 'GET') {
        $linkCode = $matches[1];
        $task = find_task_by_link_code($linkCode, true);
        if (!$task) {
            http_response_code(404);
            render_public_shell('任务不存在', '<div class="empty-state"><h1>任务不存在或已下线。</h1></div>', ['showHeader' => false]);
            return;
        }
        $cu = current_user();
        $boundMember = $cu ? get_user_bound_member((int) $cu['id']) : null;
        $content = render_view('templates/ui/phase1/public_task_checkin.php', [
            'task' => $task,
            'members' => list_task_members_for_public($task),
            'linkCode' => $linkCode,
            'flash' => pop_flash(),
            'csrf' => csrf_token(),
            'blockReason' => validate_task_open_for_submit($task),
            'boundMemberId' => $boundMember ? (int) $boundMember['id'] : 0,
        ]);
        render_public_shell('任务打卡', $content, ['showHeader' => false]);
        return;
    }

    if (preg_match('#^/task/([A-Za-z0-9_-]+)/checkin$#', $path, $matches) && $method === 'POST') {
        verify_csrf_or_fail();
        $linkCode = $matches[1];
        $payload = request_payload();
        $forceOverride = parse_bool($payload['force_override'] ?? false);
        try {
            $result = submit_checkin_by_link($linkCode, $payload, $_FILES, $forceOverride);
        } catch (Throwable $e) {
            push_flash('error', '打卡失败，请稍后重试。', [
                'show_modal' => true,
                'modal_title' => '打卡失败',
            ]);
            redirect('/task/' . $linkCode);
        }

        if (($result['ok'] ?? false) === true) {
            $taskName = (string) (($result['task']['task_name'] ?? '') !== '' ? $result['task']['task_name'] : '任务');
            $memberName = (string) ($result['member']['name'] ?? '');
            $taskDate = (string) ($result['task_date'] ?? '');
            $submitTime = (string) ($result['submit_time'] ?? date('Y-m-d H:i:s'));
            $imageCount = (int) ($result['image_count'] ?? 0);
            $checkinCount = (int) ($result['checkin_count'] ?? 1);
            $isOverride = (bool) ($result['overrode'] ?? false);

            push_flash('success', '打卡成功。', [
                'show_modal' => true,
                'modal_title' => '打卡成功',
                'task_name' => $taskName,
                'member_name' => $memberName,
                'task_date' => $taskDate,
                'submit_time' => $submitTime,
                'image_count' => $imageCount,
                'checkin_count' => $checkinCount,
                'is_override' => $isOverride,
            ]);
            redirect('/task/' . $linkCode);
        }

        $errorMessage = map_checkin_error_message($result);
        push_flash('error', $errorMessage, [
            'show_modal' => true,
            'modal_title' => '打卡失败',
        ]);
        redirect('/task/' . $linkCode);
    }

    http_response_code(404);
    render_public_shell('404', '<h1>页面不存在</h1>', ['showHeader' => false]);
}

function map_checkin_error_message(array $result): string
{
    $code = (string) ($result['code'] ?? '');
    $rawError = trim((string) ($result['error'] ?? ''));

    if ($code === 'duplicate_prompt') {
        $last = is_array($result['duplicate'] ?? null) ? $result['duplicate'] : [];
        $lastTime = trim((string) ($last['submit_time'] ?? ''));
        return $lastTime !== ''
            ? '检测到重复打卡，上次提交时间：' . $lastTime
            : '检测到重复打卡，请确认是否覆盖提交。';
    }

    // For image validation failures, prefer the precise reason carried in the
    // raw error (e.g. "单张图片不能超过 10MB", "图片数量需在 1 到 3 张之间").
    // Previously every image error was flattened to "上传图片数量不符合规则"，
    // which misled users into thinking it was a count problem when it was
    // actually an oversized single image (iPhone PNG screenshots > limit).
    if ($code === 'invalid_images' && $rawError !== '') {
        return $rawError;
    }

    $map = [
        'invalid_task' => '任务无效。',
        'task_not_found' => '任务不存在。',
        'task_closed' => '当前任务不可提交。',
        'invalid_member' => '请选择有效成员。',
        'member_not_allowed' => '该成员不在任务可提交名单中。',
        'invalid_task_date' => '请选择有效的打卡日期。',
        'invalid_images' => '上传图片数量不符合规则。',
        'override_forbidden' => '当前任务不允许覆盖提交。',
        'duplicate_reject' => '当前任务不允许重复提交。',
        'forbidden' => '当前时段不可提交。',
    ];

    if (isset($map[$code])) {
        return $map[$code];
    }

    if ($rawError !== '') {
        if (preg_match('/Image count must be between\s*(\d+)\s*and\s*(\d+)/i', $rawError, $m)) {
            return '上传图片数量需在 ' . $m[1] . ' 到 ' . $m[2] . ' 张之间。';
        }
        if (preg_match('/duplicate/i', $rawError) === 1) {
            return '检测到重复打卡，请确认是否覆盖提交。';
        }
        return '打卡失败：' . $rawError;
    }

    return '打卡失败，请稍后重试。';
}

function label_submit_source(string $source): string
{
    return match (strtolower(trim($source))) {
        'admin', 'admin_web' => '后台补录',
        'api' => '接口提交',
        'mobile', 'wechat', 'weixin' => '移动端提交',
        default => '前台提交',
    };
}

function output_member_import_template_csv(): never
{
    $filename = 'member_import_template.csv';
    $rows = [
        ['Name', 'Mobile', 'Position', 'EmployeeNo', 'Department', 'Remark'],
        ['Zhang San', '13800000001', 'Nurse', 'A001', 'Nursing', 'Sample'],
        ['Li Si', '13800000002', 'Doctor', 'A002', 'Outpatient', ''],
    ];

    $content = "\xEF\xBB\xBF";
    foreach ($rows as $row) {
        $escaped = array_map(
            static function (string $value): string {
                $value = str_replace('"', '""', $value);
                return '"' . $value . '"';
            },
            $row
        );
        $content .= implode(',', $escaped) . "\r\n";
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo $content;
    exit;
}

function output_checkin_records_xlsx(string $binary): never
{
    $filename = 'checkin_records_' . date('Ymd_His') . '.xlsx';
    output_xlsx_download($binary, $filename);
}

function output_xlsx_download(string $binary, string $filename): never
{
    $safeFilename = trim($filename) !== '' ? $filename : ('export_' . date('Ymd_His') . '.xlsx');
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $safeFilename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Content-Length: ' . strlen($binary));
    echo $binary;
    exit;
}

function log_internal_exception(string $scope, Throwable $e, array $context = []): string
{
    try {
        $requestId = date('YmdHis') . '-' . bin2hex(random_bytes(4));
    } catch (Throwable) {
        $requestId = date('YmdHis') . '-fallback';
    }
    $payload = [
        'request_id' => $requestId,
        'scope' => $scope,
        'method' => (string) ($_SERVER['REQUEST_METHOD'] ?? ''),
        'uri' => (string) ($_SERVER['REQUEST_URI'] ?? ''),
        'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
        'context' => $context,
    ];
    error_log('[app-exception] ' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    return $requestId;
}

function handle_api(string $method, string $path): void
{
    try {
        // ─── User Popup API ───
        if ($path === '/api/user/popup/active' && $method === 'GET') {
            $popup = get_active_popup();
            if (!$popup) {
                json_response(['ok' => true, 'data' => null]);
            }
            $userId = current_user_id();
            $read = false;
            $frequency = (string) ($popup['frequency'] ?? 'once_per_day');
            if ($userId > 0 && $frequency !== 'every_time') {
                $read = has_user_read_popup_today($userId, (int) $popup['id']);
            }
            json_response(['ok' => true, 'data' => [
                'id' => (int) $popup['id'],
                'content' => (string) $popup['content'],
                'read' => $read,
                'target_audience' => (string) ($popup['target_audience'] ?? 'all'),
                'frequency' => $frequency,
            ]]);
        }

        if ($path === '/api/user/popup/read' && $method === 'POST') {
            $userId = current_user_id();
            if ($userId <= 0) {
                json_response(['ok' => true]);
            }
            $data = request_payload();
            $popupId = (int) ($data['popup_id'] ?? 0);
            if ($popupId > 0) {
                mark_popup_read($userId, $popupId);
            }
            json_response(['ok' => true]);
        }

        if ($path === '/api/admin/login' && $method === 'POST') {
            $data = request_payload();
            $username = trim((string) ($data['username'] ?? ''));
            $password = (string) ($data['password'] ?? '');
            $admin = attempt_admin_login($username, $password);
            if ($admin === null) {
                json_response(['ok' => false, 'message' => '请求失败'], 401);
            }
            json_response(['ok' => true, 'admin' => $admin]);
        }

        if (preg_match('#^/api/public/task/([A-Za-z0-9_-]+)$#', $path, $m) && $method === 'GET') {
            $task = find_task_by_link_code($m[1], true);
            if ($task === null) {
                json_response(['ok' => false, 'message' => '请求失败'], 404);
            }
            if (function_exists('append_task_api_labels')) {
                $task = append_task_api_labels($task);
            }
            json_response(['ok' => true, 'data' => $task]);
        }

        if (preg_match('#^/api/public/task/([A-Za-z0-9_-]+)/members$#', $path, $m) && $method === 'GET') {
            $task = find_task_by_link_code($m[1], true);
            if ($task === null) {
                json_response(['ok' => false, 'message' => '请求失败'], 404);
            }
            $members = list_task_members_for_public($task);
            json_response(['ok' => true, 'data' => $members]);
        }

        if ($path === '/api/public/checkin/submit' && $method === 'POST') {
            $payload = request_payload();
            $taskLinkCode = (string) ($payload['task_link_code'] ?? '');
            $result = submit_checkin_by_link($taskLinkCode, $payload, $_FILES, false);
            if (($result['ok'] ?? false) === false) {
                json_response($result, (($result['code'] ?? '') === 'duplicate_prompt') ? 409 : 422);
            }
            json_response($result, 201);
        }

        if ($path === '/api/public/checkin/override' && $method === 'POST') {
            $payload = request_payload();
            $taskLinkCode = (string) ($payload['task_link_code'] ?? '');
            $result = submit_checkin_by_link($taskLinkCode, $payload, $_FILES, true);
            if (($result['ok'] ?? false) === false) {
                json_response($result, 422);
            }
            json_response($result, 201);
        }

        if ($path === '/api/public/checkin/last-record' && $method === 'GET') {
            // 匿名用户不可查询历史记录，统一拒绝访问。
            json_response(['ok' => false, 'message' => '未授权访问'], 403);
        }

        $admin = require_admin(true);
        $adminId = (int) $admin['id'];

        if ($path === '/api/admin/members' && $method === 'GET') {
            $keyword = trim((string) ($_GET['keyword'] ?? ''));
            json_response(['ok' => true, 'data' => list_members($adminId, $keyword)]);
        }

        if ($path === '/api/admin/members' && $method === 'POST') {
            $id = create_member($adminId, request_payload());
            json_response(['ok' => true, 'id' => $id], 201);
        }

        if (preg_match('#^/api/admin/members/(\d+)$#', $path, $m) && in_array($method, ['PUT', 'PATCH'], true)) {
            update_member($adminId, (int) $m[1], request_payload());
            json_response(['ok' => true]);
        }

        if ($path === '/api/admin/tasks' && $method === 'GET') {
            $tasks = list_tasks($adminId);
            if (function_exists('append_task_api_labels')) {
                $tasks = array_map('append_task_api_labels', $tasks);
            }
            json_response(['ok' => true, 'data' => $tasks]);
        }

        if ($path === '/api/admin/tasks' && $method === 'POST') {
            $id = create_task($adminId, request_payload());
            json_response(['ok' => true, 'id' => $id], 201);
        }

        if (preg_match('#^/api/admin/tasks/(\d+)$#', $path, $m) && in_array($method, ['PUT', 'PATCH'], true)) {
            update_task($adminId, (int) $m[1], request_payload());
            json_response(['ok' => true]);
        }

        if ($path === '/api/admin/records' && $method === 'GET') {
            $filters = [
                'task_id' => (int) ($_GET['task_id'] ?? 0),
                'member_id' => (int) ($_GET['member_id'] ?? 0),
                'date_from' => trim((string) ($_GET['date_from'] ?? '')),
                'date_to' => trim((string) ($_GET['date_to'] ?? '')),
                'include_deleted' => (int) ($_GET['include_deleted'] ?? 0),
            ];
            $rows = list_checkin_records($adminId, 200, $filters);
            if (function_exists('append_record_api_labels')) {
                $rows = array_map('append_record_api_labels', $rows);
            }
            json_response(['ok' => true, 'data' => $rows]);
        }

        json_response(['ok' => false, 'message' => '请求失败'], 404);
    } catch (InvalidArgumentException $e) {
        $requestId = log_internal_exception('api_validation', $e, [
            'path' => $path,
            'method' => $method,
        ]);
        json_response(['ok' => false, 'message' => '请求参数不合法', 'request_id' => $requestId], 422);
    } catch (Throwable $e) {
        $requestId = log_internal_exception('api_fatal', $e, [
            'path' => $path,
            'method' => $method,
        ]);
        json_response(['ok' => false, 'message' => '服务器繁忙，请稍后再试', 'request_id' => $requestId], 500);
    }
}



