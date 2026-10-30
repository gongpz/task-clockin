<?php
declare(strict_types=1);

function find_admin_by_id(int $adminId): ?array
{
    $stmt = db()->prepare('SELECT * FROM admins WHERE id = :id AND status = 1 LIMIT 1');
    $stmt->execute(['id' => $adminId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function attempt_admin_login(string $username, string $password): ?array
{
    $stmt = db()->prepare('SELECT * FROM admins WHERE username = :username LIMIT 1');
    $stmt->execute(['username' => $username]);
    $admin = $stmt->fetch();
    if (!$admin) {
        return null;
    }
    if ((int) $admin['status'] !== 1) {
        return null;
    }
    if (!password_verify($password, (string) $admin['password_hash'])) {
        return null;
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        @session_regenerate_id(true);
    }
    set_admin_session((int) $admin['id']);
    $update = db()->prepare('UPDATE admins SET last_login_at = NOW() WHERE id = :id');
    $update->execute(['id' => (int) $admin['id']]);
    create_operation_log((int) $admin['id'], 'login', 'admin', (int) $admin['id'], '');

    unset($admin['password_hash']);
    return $admin;
}

function dashboard_summary(int $adminId): array
{
    ensure_member_data_schema();

    $taskStmt = db()->prepare(
        'SELECT COUNT(*) FROM tasks
         WHERE admin_id = :admin_id
           AND task_status = 1'
    );
    $taskStmt->execute(['admin_id' => $adminId]);
    $taskCount = (int) $taskStmt->fetchColumn();

    $memberStmt = db()->prepare('SELECT COUNT(*) FROM members WHERE admin_id = :admin_id AND status = 1 AND deleted_at IS NULL');
    $memberStmt->execute(['admin_id' => $adminId]);
    $memberCount = (int) $memberStmt->fetchColumn();

    $hasHiddenColumn = schema_column_exists('tasks', 'is_hidden');
    $latestTaskSql = 'SELECT id, task_name
                      FROM tasks
                      WHERE admin_id = :admin_id
                        AND task_status = 1';
    if ($hasHiddenColumn) {
        $latestTaskSql .= ' AND is_hidden = 0';
    }
    $latestTaskSql .= ' ORDER BY created_at DESC, id DESC LIMIT 2';

    $latestTaskStmt = db()->prepare($latestTaskSql);
    $latestTaskStmt->execute(['admin_id' => $adminId]);
    $latestTasks = $latestTaskStmt->fetchAll() ?: [];

    $countTaskCheckins = static function (int $taskId): int {
        if ($taskId <= 0) {
            return 0;
        }
        $stmt = db()->prepare(
            'SELECT COUNT(DISTINCT member_id)
             FROM checkin_records
             WHERE task_id = :task_id
               AND deleted_at IS NULL
               AND is_valid = 1'
        );
        $stmt->execute(['task_id' => $taskId]);
        return (int) $stmt->fetchColumn();
    };

    $latestTaskId = (int) ($latestTasks[0]['id'] ?? 0);
    $previousTaskId = (int) ($latestTasks[1]['id'] ?? 0);
    $newTaskCheckins = $countTaskCheckins($latestTaskId);
    $previousTaskCheckins = $countTaskCheckins($previousTaskId);
    $newTaskUncheckins = max(0, $memberCount - $newTaskCheckins);
    $compareDiff = $newTaskCheckins - $previousTaskCheckins;

    $recentStmt = db()->prepare(
        'SELECT r.submit_time, t.task_name, m.name AS member_name
         FROM checkin_records r
         INNER JOIN tasks t ON t.id = r.task_id
         INNER JOIN members m ON m.id = r.member_id
         WHERE t.admin_id = :admin_id
           AND r.deleted_at IS NULL
           AND r.is_valid = 1
         ORDER BY r.submit_time DESC
         LIMIT 10'
    );
    $recentStmt->execute(['admin_id' => $adminId]);
    $recentRecords = $recentStmt->fetchAll();

    return [
        'task_count' => $taskCount,
        'member_count' => $memberCount,
        'today_checkins' => $newTaskCheckins,
        'today_uncheckins' => $newTaskUncheckins,
        'new_task_checkins' => $newTaskCheckins,
        'new_task_uncheckins' => $newTaskUncheckins,
        'previous_task_checkins' => $previousTaskCheckins,
        'compare_diff' => $compareDiff,
        'latest_task_name' => (string) ($latestTasks[0]['task_name'] ?? ''),
        'previous_task_name' => (string) ($latestTasks[1]['task_name'] ?? ''),
        'recent_records' => $recentRecords ?: [],
    ];
}

function dashboard_stat_types(): array
{
    return [
        'task_count',
        'new_task_uncheckins',
        'compare_diff',
        'new_task_checkins',
    ];
}

function is_dashboard_stat_type(string $type): bool
{
    return in_array($type, dashboard_stat_types(), true);
}

function dashboard_stat_context(int $adminId, ?int $taskId = null): array
{
    $hasHiddenColumn = schema_column_exists('tasks', 'is_hidden');
    $taskOptions = list_dashboard_selectable_tasks($adminId, 50, $hasHiddenColumn);
    $currentTask = resolve_dashboard_current_task($taskOptions, $taskId);
    $latestTasks = $taskOptions;
    if ($currentTask !== null) {
        $latestTasks = [$currentTask, ...array_values(array_filter($taskOptions, static fn (array $task): bool => (int) ($task['id'] ?? 0) !== (int) ($currentTask['id'] ?? 0)))];
    }

    return [
        'has_hidden_column' => $hasHiddenColumn,
        'latest_tasks' => array_slice($latestTasks, 0, 2),
        'task_options' => $taskOptions,
        'current_task' => $currentTask,
        'current_task_id' => (int) ($currentTask['id'] ?? 0),
    ];
}

function list_dashboard_latest_tasks(int $adminId, int $limit = 2): array
{
    return list_dashboard_selectable_tasks($adminId, $limit);
}

function list_dashboard_selectable_tasks(int $adminId, int $limit = 50, ?bool $hasHiddenColumn = null): array
{
    $limit = max(1, min(200, $limit));
    $hasHiddenColumn = $hasHiddenColumn ?? schema_column_exists('tasks', 'is_hidden');

    $sql = 'SELECT id, task_name, start_time, end_time, is_long_term, task_status';
    if ($hasHiddenColumn) {
        $sql .= ', is_hidden';
    } else {
        $sql .= ', 0 AS is_hidden';
    }
    $sql .= ' FROM tasks
              WHERE admin_id = :admin_id
                AND task_status = 1
              ORDER BY created_at DESC, id DESC LIMIT ' . $limit;

    $stmt = db()->prepare($sql);
    $stmt->execute(['admin_id' => $adminId]);
    return $stmt->fetchAll() ?: [];
}

function resolve_dashboard_current_task(array $taskOptions, ?int $taskId = null): ?array
{
    $taskId = (int) ($taskId ?? 0);
    if ($taskId > 0) {
        foreach ($taskOptions as $task) {
            if ((int) ($task['id'] ?? 0) === $taskId) {
                return $task;
            }
        }
    }

    return $taskOptions[0] ?? null;
}

function list_valid_checkin_member_ids_by_task(int $taskId): array
{
    if ($taskId <= 0) {
        return [];
    }

    $stmt = db()->prepare(
        'SELECT DISTINCT member_id
         FROM checkin_records
         WHERE task_id = :task_id
           AND deleted_at IS NULL
           AND is_valid = 1
         ORDER BY member_id ASC'
    );
    $stmt->execute(['task_id' => $taskId]);
    $rows = $stmt->fetchAll() ?: [];
    $ids = [];
    foreach ($rows as $row) {
        $id = (int) ($row['member_id'] ?? 0);
        if ($id > 0) {
            $ids[] = $id;
        }
    }
    return array_values(array_unique($ids));
}

function list_members_basic_map_by_ids(int $adminId, array $memberIds): array
{
    $memberIds = normalize_member_ids($memberIds);
    if ($memberIds === []) {
        return [];
    }

    $params = ['admin_id' => $adminId];
    $placeholders = [];
    foreach ($memberIds as $idx => $memberId) {
        $key = 'member_id_' . $idx;
        $placeholders[] = ':' . $key;
        $params[$key] = $memberId;
    }

    $sql = 'SELECT id, name, employee_no, department
            FROM members
            WHERE admin_id = :admin_id
              AND id IN (' . implode(',', $placeholders) . ')';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll() ?: [];

    $map = [];
    foreach ($rows as $row) {
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }
        $map[$id] = [
            'name' => (string) ($row['name'] ?? ''),
            'employee_no' => (string) ($row['employee_no'] ?? ''),
            'department' => (string) ($row['department'] ?? ''),
        ];
    }
    return $map;
}

function format_task_validity_label(array $task): string
{
    $isLongTerm = (int) ($task['is_long_term'] ?? 0) === 1;
    if ($isLongTerm) {
        return '长期有效';
    }

    $startTime = trim((string) ($task['start_time'] ?? ''));
    $endTime = trim((string) ($task['end_time'] ?? ''));
    if ($startTime === '' || $endTime === '') {
        return '-';
    }

    $startAt = strtotime($startTime);
    $endAt = strtotime($endTime);
    if ($startAt === false || $endAt === false) {
        return '-';
    }

    return date('Y-m-d', $startAt) . ' ~ ' . date('Y-m-d', $endAt);
}

function list_dashboard_stat_sections(int $adminId, ?int $taskId = null): array
{
    $context = dashboard_stat_context($adminId, $taskId);
    $sections = [];
    foreach (dashboard_stat_types() as $type) {
        $sections[] = build_dashboard_stat_section($adminId, $type, $context);
    }
    return $sections;
}

function get_dashboard_stat_section(int $adminId, string $type, ?int $taskId = null): array
{
    $type = trim($type);
    if (!is_dashboard_stat_type($type)) {
        throw new InvalidArgumentException('');
    }
    return build_dashboard_stat_section($adminId, $type, dashboard_stat_context($adminId, $taskId));
}

function build_dashboard_stat_section(int $adminId, string $type, ?array $context = null): array
{
    if (!is_dashboard_stat_type($type)) {
        throw new InvalidArgumentException('');
    }

    $context = is_array($context) ? $context : dashboard_stat_context($adminId);
    $hasHiddenColumn = (bool) ($context['has_hidden_column'] ?? schema_column_exists('tasks', 'is_hidden'));
    $latestTasks = is_array($context['latest_tasks'] ?? null) ? $context['latest_tasks'] : [];
    $currentTask = is_array($context['current_task'] ?? null) ? $context['current_task'] : ($latestTasks[0] ?? null);
    $previousTask = $latestTasks[1] ?? null;

    if ($type === 'task_count') {
        $sql = 'SELECT id, task_name, start_time, end_time, is_long_term, task_status, created_at';
        if ($hasHiddenColumn) {
            $sql .= ', is_hidden';
        } else {
            $sql .= ', 0 AS is_hidden';
        }
        $sql .= ' FROM tasks
                  WHERE admin_id = :admin_id
                    AND task_status = 1
                  ORDER BY created_at DESC, id DESC';

        $stmt = db()->prepare($sql);
        $stmt->execute(['admin_id' => $adminId]);
        $rawRows = $stmt->fetchAll() ?: [];

        $rows = [];
        foreach ($rawRows as $row) {
            $rows[] = [
                '#' . (int) ($row['id'] ?? 0),
                (string) ($row['task_name'] ?? ''),
                (string) ($row['created_at'] ?? '-'),
                format_task_validity_label($row),
                ((int) ($row['is_hidden'] ?? 0) === 1) ? '已隐藏' : '前台显示中',
                ((int) ($row['task_status'] ?? 0) === 1) ? '启用' : '停用',
            ];
        }

        return [
            'type' => $type,
            'title' => '任务总数',
            'description' => '当前启用任务明细列表',
            'count' => count($rows),
            'headers' => ['任务ID', '任务名称', '发布时间', '有效期', '前台状态', '任务状态'],
            'rows' => $rows,
            'empty_text' => '暂无任务数据',
        ];
    }

    if ($type === 'member_count') {
        $stmt = db()->prepare(
            'SELECT id, name, employee_no, department, position, mobile
             FROM members
             WHERE admin_id = :admin_id
               AND status = 1
               AND deleted_at IS NULL
             ORDER BY id DESC'
        );
        $stmt->execute(['admin_id' => $adminId]);
        $rawRows = $stmt->fetchAll() ?: [];

        $rows = [];
        foreach ($rawRows as $row) {
            $rows[] = [
                '#' . (int) ($row['id'] ?? 0),
                (string) ($row['name'] ?? ''),
                (string) ($row['employee_no'] ?? '-'),
                (string) ($row['department'] ?? '-'),
                (string) ($row['position'] ?? '-'),
                (string) ($row['mobile'] ?? '-'),
            ];
        }

        return [
            'type' => $type,
            'title' => '成员总数',
            'description' => '当前启用成员明细列表',
            'count' => count($rows),
            'headers' => ['成员ID', '姓名', '工号', '部门', '职位', '手机号'],
            'rows' => $rows,
            'empty_text' => '暂无成员数据',
        ];
    }

    if ($type === 'new_task_checkins') {
        if (!is_array($currentTask) || (int) ($currentTask['id'] ?? 0) <= 0) {
            return [
                'type' => $type,
                'title' => '任务已打卡人数',
                'description' => '暂无可统计的任务',
                'count' => 0,
                'headers' => ['成员ID', '姓名', '工号', '部门', '最近打卡时间', '打卡次数'],
                'rows' => [],
                'empty_text' => '暂无打卡记录',
            ];
        }

        $taskId = (int) $currentTask['id'];
        $taskName = (string) ($currentTask['task_name'] ?? '');
        $stmt = db()->prepare(
            'SELECT
                m.id,
                m.name,
                m.employee_no,
                m.department,
                MAX(r.submit_time) AS last_submit_time,
                COUNT(DISTINCT r.id) AS checkin_count
             FROM checkin_records r
             LEFT JOIN members m ON m.id = r.member_id
             WHERE r.task_id = :task_id
               AND r.deleted_at IS NULL
               AND r.is_valid = 1
             GROUP BY m.id, m.name, m.employee_no, m.department
             ORDER BY last_submit_time DESC, m.id DESC'
        );
        $stmt->execute(['task_id' => $taskId]);
        $rawRows = $stmt->fetchAll() ?: [];

        $rows = [];
        foreach ($rawRows as $row) {
            $rows[] = [
                '#' . (int) ($row['id'] ?? 0),
                (string) ($row['name'] ?? '未知成员'),
                (string) ($row['employee_no'] ?? '-'),
                (string) ($row['department'] ?? '-'),
                (string) ($row['last_submit_time'] ?? '-'),
                (string) ((int) ($row['checkin_count'] ?? 0)),
            ];
        }

        return [
            'type' => $type,
            'title' => '任务已打卡人数',
            'description' => '当前任务：' . ($taskName !== '' ? $taskName : ('#' . $taskId)),
            'count' => count($rows),
            'headers' => ['成员ID', '姓名', '工号', '部门', '最近打卡时间', '打卡次数'],
            'rows' => $rows,
            'empty_text' => '当前任务暂无已打卡成员',
        ];
    }

    if ($type === 'new_task_uncheckins') {
        if (!is_array($currentTask) || (int) ($currentTask['id'] ?? 0) <= 0) {
            return [
                'type' => $type,
                'title' => '任务未打卡人数',
                'description' => '暂无可统计的任务',
                'count' => 0,
                'headers' => ['成员ID', '姓名', '工号', '部门', '职位'],
                'rows' => [],
                'empty_text' => '暂无未打卡成员',
            ];
        }

        $taskId = (int) $currentTask['id'];
        $taskName = (string) ($currentTask['task_name'] ?? '');

        // 只把在任务有效期内已存在的成员算作"未打卡"。新加入的成员对于其加入
        // 之前就已结束的历史任务，不应显示为未打卡。判定基准：任务结束时间
        // (end_time)。成员的加入时间 (created_at) 晚于任务结束时间的，排除。
        // 长期任务或结束时间为空 (is_long_term / end_time IS NULL) 时不加此限制，
        // 因为这类任务新人仍需持续打卡，理应保留在未打卡名单中。
        $taskEndTime = trim((string) ($currentTask['end_time'] ?? ''));
        $isLongTerm = (int) ($currentTask['is_long_term'] ?? 0) === 1;
        $applyJoinCutoff = !$isLongTerm && $taskEndTime !== '' && $taskEndTime !== '0000-00-00 00:00:00';

        $joinCutoffSql = $applyJoinCutoff ? ' AND m.created_at <= :task_end_time' : '';

        $stmt = db()->prepare(
            'SELECT m.id, m.name, m.employee_no, m.department, m.position
             FROM members m
             WHERE m.admin_id = :admin_id
               AND m.status = 1
               AND m.deleted_at IS NULL' . $joinCutoffSql . '
               AND NOT EXISTS (
                   SELECT 1
                   FROM checkin_records r
                   WHERE r.task_id = :task_id
                     AND r.member_id = m.id
                     AND r.deleted_at IS NULL
                     AND r.is_valid = 1
               )
             ORDER BY m.id DESC'
        );
        $params = [
            'admin_id' => $adminId,
            'task_id' => $taskId,
        ];
        if ($applyJoinCutoff) {
            $params['task_end_time'] = $taskEndTime;
        }
        $stmt->execute($params);
        $rawRows = $stmt->fetchAll() ?: [];

        $rows = [];
        foreach ($rawRows as $row) {
            $rows[] = [
                '#' . (int) ($row['id'] ?? 0),
                (string) ($row['name'] ?? ''),
                (string) ($row['employee_no'] ?? '-'),
                (string) ($row['department'] ?? '-'),
                (string) ($row['position'] ?? '-'),
            ];
        }

        return [
            'type' => $type,
            'title' => '任务未打卡人数',
            'description' => '当前任务：' . ($taskName !== '' ? $taskName : ('#' . $taskId)),
            'count' => count($rows),
            'headers' => ['成员ID', '姓名', '工号', '部门', '职位'],
            'rows' => $rows,
            'empty_text' => '当前任务暂无未打卡成员',
        ];
    }

    if (!is_array($currentTask) || (int) ($currentTask['id'] ?? 0) <= 0) {
        return [
            'type' => $type,
            'title' => '与上期相比',
            'description' => '暂无可用于对比的任务',
            'count' => 0,
            'headers' => ['成员ID', '姓名', '工号', '部门', '变化', '说明'],
            'rows' => [],
            'empty_text' => '暂无对比数据',
        ];
    }
    if (!is_array($previousTask) || (int) ($previousTask['id'] ?? 0) <= 0) {
        return [
            'type' => $type,
            'title' => '与上期相比',
            'description' => '暂无上一期任务，无法计算对比差值',
            'count' => 0,
            'headers' => ['成员ID', '姓名', '工号', '部门', '变化', '说明'],
            'rows' => [],
            'empty_text' => '暂无上一期任务数据',
        ];
    }

    $currentTaskId = (int) $currentTask['id'];
    $previousTaskId = (int) $previousTask['id'];
    $currentTaskName = (string) ($currentTask['task_name'] ?? ('#' . $currentTaskId));
    $previousTaskName = (string) ($previousTask['task_name'] ?? ('#' . $previousTaskId));

    $currentIds = list_valid_checkin_member_ids_by_task($currentTaskId);
    $previousIds = list_valid_checkin_member_ids_by_task($previousTaskId);
    $addedIds = array_values(array_diff($currentIds, $previousIds));
    $reducedIds = array_values(array_diff($previousIds, $currentIds));
    $allMemberIds = normalize_member_ids(array_merge($addedIds, $reducedIds));
    $memberMap = list_members_basic_map_by_ids($adminId, $allMemberIds);

    $rows = [];
    foreach ($addedIds as $memberId) {
        $meta = $memberMap[$memberId] ?? [];
        $rows[] = [
            '#' . $memberId,
            (string) ($meta['name'] ?? '未知成员'),
            (string) ($meta['employee_no'] ?? '-'),
            (string) ($meta['department'] ?? '-'),
            '+1',
            '较上期新增打卡',
        ];
    }
    foreach ($reducedIds as $memberId) {
        $meta = $memberMap[$memberId] ?? [];
        $rows[] = [
            '#' . $memberId,
            (string) ($meta['name'] ?? '未知成员'),
            (string) ($meta['employee_no'] ?? '-'),
            (string) ($meta['department'] ?? '-'),
            '-1',
            '较上期减少打卡',
        ];
    }

    $diff = count($currentIds) - count($previousIds);
    $diffPrefix = $diff > 0 ? '+' : '';

    return [
        'type' => $type,
        'title' => '与上期相比',
        'description' => '本期：' . $currentTaskName . '；上期：' . $previousTaskName . '；差值：' . $diffPrefix . $diff,
        'count' => $diff,
        'headers' => ['成员ID', '姓名', '工号', '部门', '变化', '说明'],
        'rows' => $rows,
        'empty_text' => '本期与上期打卡人数没有差异',
    ];
}

/**
 * 规范化月份参数，返回 YYYY-MM。非法输入回退到当前月。
 */
function normalize_stat_month(?string $month): string
{
    $month = trim((string) ($month ?? ''));
    if ($month !== '' && preg_match('/^\d{4}-\d{2}$/', $month) === 1) {
        $ts = strtotime($month . '-01 00:00:00');
        if ($ts !== false) {
            return date('Y-m', $ts);
        }
    }
    return date('Y-m');
}

/**
 * 按月统计人员未打卡记录。
 *
 * 口径：统计该月「发布」(start_time) 的所有任务，不看任务状态、不看是否本月结束。
 * 对每个任务，确定应打卡成员（有 task_members 归属则只算归属成员，否则全体在职成员），
 * 沿用历史未打卡修复的截止逻辑：非长期任务且 end_time 非空时，成员加入时间
 * (created_at) 晚于任务结束时间的排除；再剔除已有效打卡者，剩余即该任务未打卡。
 * 最后按成员聚合：姓名 - 未打卡次数 - 未打卡时间与任务明细。
 *
 * 返回结构与 build_dashboard_stat_section 一致（headers/rows/count），供页面与导出共用。
 */
function build_monthly_uncheckin_stat(int $adminId, string $month): array
{
    $month = normalize_stat_month($month);
    $monthStart = $month . '-01 00:00:00';
    $startTs = strtotime($monthStart);
    $monthEnd = date('Y-m-t 23:59:59', $startTs !== false ? $startTs : time());

    $headers = ['时间', '任务名称', '未打卡人员'];

    // 当月发布的所有任务（按 start_time 过滤，含停用任务）。
    $taskStmt = db()->prepare(
        'SELECT id, task_name, start_time, end_time, is_long_term
         FROM tasks
         WHERE admin_id = :admin_id
           AND start_time >= :month_start
           AND start_time <= :month_end
         ORDER BY start_time ASC, id ASC'
    );
    $taskStmt->execute([
        'admin_id' => $adminId,
        'month_start' => $monthStart,
        'month_end' => $monthEnd,
    ]);
    $tasks = $taskStmt->fetchAll() ?: [];

    $monthLabel = date('Y 年 n 月', $startTs !== false ? $startTs : time());

    if ($tasks === []) {
        return [
            'month' => $month,
            'title' => '按月未打卡统计',
            'description' => $monthLabel . '：当月未发布任何任务',
            'task_count' => 0,
            'count' => 0,
            'headers' => $headers,
            'rows' => [],
            'export_headers' => $headers,
            'export_rows' => [],
            'summary' => [],
            'empty_text' => $monthLabel . '当月没有发布任务',
        ];
    }

    // 主表按任务组织：时间 - 任务名称 - 未打卡人员。
    $taskRows = [];
    // 合计表按成员聚合：member_id => ['name'=>..., 'count'=>int]。
    $agg = [];

    foreach ($tasks as $task) {
        $taskId = (int) ($task['id'] ?? 0);
        if ($taskId <= 0) {
            continue;
        }
        $taskName = trim((string) ($task['task_name'] ?? ''));
        if ($taskName === '') {
            $taskName = '#' . $taskId;
        }
        $taskStartTime = trim((string) ($task['start_time'] ?? ''));
        $taskEndTime = trim((string) ($task['end_time'] ?? ''));
        $isLongTerm = (int) ($task['is_long_term'] ?? 0) === 1;
        $applyJoinCutoff = !$isLongTerm && $taskEndTime !== '' && $taskEndTime !== '0000-00-00 00:00:00';

        // 该任务是否指定了归属成员。
        $assignedCountStmt = db()->prepare('SELECT COUNT(*) FROM task_members WHERE task_id = :task_id');
        $assignedCountStmt->execute(['task_id' => $taskId]);
        $assignedCount = (int) $assignedCountStmt->fetchColumn();

        // 应打卡成员：有归属则 INNER JOIN task_members，否则全体在职成员。
        if ($assignedCount > 0) {
            $expectedSql =
                'SELECT m.id, m.name
                 FROM members m
                 INNER JOIN task_members tm ON tm.member_id = m.id
                 WHERE tm.task_id = :task_id
                   AND m.admin_id = :admin_id
                   AND m.status = 1
                   AND m.deleted_at IS NULL';
            $expectedParams = ['task_id' => $taskId, 'admin_id' => $adminId];
        } else {
            $expectedSql =
                'SELECT m.id, m.name
                 FROM members m
                 WHERE m.admin_id = :admin_id
                   AND m.status = 1
                   AND m.deleted_at IS NULL';
            $expectedParams = ['admin_id' => $adminId];
        }
        if ($applyJoinCutoff) {
            $expectedSql .= ' AND m.created_at <= :task_end_time';
            $expectedParams['task_end_time'] = $taskEndTime;
        }
        $expectedStmt = db()->prepare($expectedSql);
        $expectedStmt->execute($expectedParams);
        $expectedMembers = $expectedStmt->fetchAll() ?: [];

        // 该任务已有效打卡的成员集合。
        $checkedIds = [];
        foreach (list_valid_checkin_member_ids_by_task($taskId) as $cid) {
            $checkedIds[$cid] = true;
        }

        $detailTime = $taskStartTime !== '' ? date('Y-m-d H:i', strtotime($taskStartTime)) : '-';

        // 收集该任务的未打卡人员名字。
        $uncheckNames = [];
        foreach ($expectedMembers as $member) {
            $memberId = (int) ($member['id'] ?? 0);
            if ($memberId <= 0 || isset($checkedIds[$memberId])) {
                continue;
            }
            $memberName = (string) ($member['name'] ?? '未知成员');
            $uncheckNames[] = $memberName;

            if (!isset($agg[$memberId])) {
                $agg[$memberId] = ['name' => $memberName, 'count' => 0];
            }
            $agg[$memberId]['count']++;
        }

        $taskRows[] = [
            'time' => $detailTime,
            'task_name' => $taskName,
            'uncheck_names' => $uncheckNames,
        ];
    }

    // 主表行（页面与导出共用）：时间 / 任务名称 / 未打卡人员。
    $rows = [];
    $exportRows = [];
    foreach ($taskRows as $tr) {
        $namesText = $tr['uncheck_names'] === [] ? '全部已打卡' : implode('、', $tr['uncheck_names']);
        $rows[] = [
            (string) $tr['time'],
            (string) $tr['task_name'],
            $namesText,
        ];
        $exportRows[] = [
            (string) $tr['time'],
            (string) $tr['task_name'],
            $namesText,
        ];
    }

    // 合计表：按未打卡次数降序，其次成员 ID 升序（agg 已按插入即 ID 序）。
    uasort($agg, static function (array $a, array $b): int {
        return $b['count'] <=> $a['count'];
    });
    $summary = [];
    foreach ($agg as $item) {
        $summary[] = [
            'name' => (string) $item['name'],
            'count' => (int) $item['count'],
        ];
    }

    return [
        'month' => $month,
        'title' => '按月未打卡统计',
        'description' => $monthLabel . '：当月共发布 ' . count($tasks) . ' 个任务，' . count($summary) . ' 人存在未打卡',
        'task_count' => count($tasks),
        'count' => count($summary),
        'headers' => $headers,
        'rows' => $rows,
        'export_headers' => $headers,
        'export_rows' => $exportRows,
        'summary' => $summary,
        'empty_text' => $monthLabel . '当月所有成员均已打卡',
    ];
}

function ensure_member_data_schema(): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    if (!schema_column_exists('members', 'position')) {
        db()->exec("ALTER TABLE members ADD COLUMN position VARCHAR(100) NULL COMMENT 'position' AFTER mobile");
    }

    if (!schema_column_exists('members', 'deleted_at')) {
        db()->exec("ALTER TABLE members ADD COLUMN deleted_at DATETIME NULL COMMENT 'soft delete time' AFTER remark");
    }

    if (!schema_index_exists('members', 'idx_admin_id_deleted_at')) {
        db()->exec('ALTER TABLE members ADD INDEX idx_admin_id_deleted_at (admin_id, deleted_at)');
    }

    db()->exec(
        "CREATE TABLE IF NOT EXISTS member_group_members (
            id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT COMMENT 'pk',
            admin_id BIGINT UNSIGNED NOT NULL COMMENT 'admin id',
            member_id BIGINT UNSIGNED NOT NULL COMMENT 'member id',
            group_id BIGINT UNSIGNED NOT NULL COMMENT 'group id',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'created at',
            UNIQUE KEY uk_member_group (member_id, group_id),
            KEY idx_admin_id (admin_id),
            KEY idx_admin_member_id (admin_id, member_id),
            KEY idx_admin_group_id (admin_id, group_id),
            KEY idx_group_id (group_id),
            CONSTRAINT fk_member_group_members_admin_id FOREIGN KEY (admin_id) REFERENCES admins(id),
            CONSTRAINT fk_member_group_members_member_id FOREIGN KEY (member_id) REFERENCES members(id),
            CONSTRAINT fk_member_group_members_group_id FOREIGN KEY (group_id) REFERENCES member_groups(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='member group mapping'"
    );

    if (!schema_index_exists('member_group_members', 'idx_admin_member_id')) {
        db()->exec('ALTER TABLE member_group_members ADD INDEX idx_admin_member_id (admin_id, member_id)');
    }
    if (!schema_index_exists('member_group_members', 'idx_admin_group_id')) {
        db()->exec('ALTER TABLE member_group_members ADD INDEX idx_admin_group_id (admin_id, group_id)');
    }

    if (schema_column_exists('members', 'group_id')) {
        db()->exec(
            'INSERT IGNORE INTO member_group_members (admin_id, member_id, group_id, created_at)
             SELECT admin_id, id, group_id, NOW()
             FROM members
             WHERE group_id IS NOT NULL'
        );
        // group_id is now a compatibility-only column.
        db()->exec('UPDATE members SET group_id = NULL WHERE group_id IS NOT NULL');
    }
}

function ensure_task_data_schema(): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    // 轻量版最终收口后，不在请求链路中自动变更任务表结构。
    // 若历史库仍保留 task_type 字段，create/update 会在服务层强制写入 normal。
}

function ensure_member_position_column(): void
{
    ensure_member_data_schema();
}

function schema_column_exists(string $tableName, string $columnName): bool
{
    $stmt = db()->prepare(
        'SELECT COUNT(*)
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name
           AND COLUMN_NAME = :column_name'
    );
    $stmt->execute([
        'table_name' => $tableName,
        'column_name' => $columnName,
    ]);
    return (int) $stmt->fetchColumn() > 0;
}

function schema_index_exists(string $tableName, string $indexName): bool
{
    $stmt = db()->prepare(
        'SELECT COUNT(*)
         FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name
           AND INDEX_NAME = :index_name'
    );
    $stmt->execute([
        'table_name' => $tableName,
        'index_name' => $indexName,
    ]);
    return (int) $stmt->fetchColumn() > 0;
}

function list_members(int $adminId, string $keyword = ''): array
{
    ensure_member_data_schema();

    if ($keyword === '') {
        $stmt = db()->prepare('SELECT * FROM members WHERE admin_id = :admin_id AND deleted_at IS NULL ORDER BY id DESC');
        $stmt->execute(['admin_id' => $adminId]);
        $rows = $stmt->fetchAll() ?: [];
        return attach_member_group_labels($adminId, $rows);
    }

    $stmt = db()->prepare(
        'SELECT * FROM members
         WHERE admin_id = :admin_id
           AND deleted_at IS NULL
           AND (
                name LIKE :kw_name
                OR mobile LIKE :kw_mobile
                OR position LIKE :kw_position
                OR employee_no LIKE :kw_employee_no
                OR department LIKE :kw_department
           )
         ORDER BY id DESC'
    );
    $kw = '%' . $keyword . '%';
    $stmt->execute([
        'admin_id' => $adminId,
        'kw_name' => $kw,
        'kw_mobile' => $kw,
        'kw_position' => $kw,
        'kw_employee_no' => $kw,
        'kw_department' => $kw,
    ]);
    $rows = $stmt->fetchAll() ?: [];
    return attach_member_group_labels($adminId, $rows);
}

function find_member_by_id(int $adminId, int $memberId, bool $includeDeleted = false): ?array
{
    ensure_member_data_schema();

    $sql = 'SELECT * FROM members WHERE admin_id = :admin_id AND id = :id';
    if (!$includeDeleted) {
        $sql .= ' AND deleted_at IS NULL';
    }
    $sql .= ' LIMIT 1';
    $stmt = db()->prepare($sql);
    $stmt->execute([
        'admin_id' => $adminId,
        'id' => $memberId,
    ]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function create_member(int $adminId, array $payload): int
{
    ensure_member_data_schema();

    $name = trim((string) ($payload['name'] ?? ''));
    if ($name === '') {
        throw new InvalidArgumentException('');
    }
    $groupIds = normalize_group_ids($payload['group_ids'] ?? []);

    db()->beginTransaction();
    try {
        $stmt = db()->prepare(
            'INSERT INTO members (admin_id, name, employee_no, department, mobile, position, status, remark, created_at, updated_at)
             VALUES (:admin_id, :name, :employee_no, :department, :mobile, :position, :status, :remark, NOW(), NOW())'
        );
        $stmt->execute([
            'admin_id' => $adminId,
            'name' => $name,
            'employee_no' => trim((string) ($payload['employee_no'] ?? '')),
            'department' => trim((string) ($payload['department'] ?? '')),
            'mobile' => trim((string) ($payload['mobile'] ?? '')),
            'position' => trim((string) ($payload['position'] ?? '')),
            'status' => parse_bool($payload['status'] ?? '1') ? 1 : 0,
            'remark' => trim((string) ($payload['remark'] ?? '')),
        ]);
        $id = (int) db()->lastInsertId();
        sync_member_groups($adminId, $id, $groupIds);
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }

    create_operation_log($adminId, 'create', 'member', $id, '');
    return $id;
}

function update_member(int $adminId, int $memberId, array $payload): void
{
    ensure_member_data_schema();

    $current = find_member_by_id($adminId, $memberId);
    if ($current === null) {
        throw new InvalidArgumentException('');
    }

    $name = trim((string) ($payload['name'] ?? ''));
    if ($name === '') {
        throw new InvalidArgumentException('');
    }
    $groupIds = normalize_group_ids($payload['group_ids'] ?? []);

    db()->beginTransaction();
    try {
        $stmt = db()->prepare(
            'UPDATE members
                SET name = :name,
                    employee_no = :employee_no,
                    department = :department,
                    mobile = :mobile,
                    position = :position,
                    status = :status,
                    remark = :remark,
                    updated_at = NOW()
              WHERE id = :id AND admin_id = :admin_id'
        );
        $stmt->execute([
            'id' => $memberId,
            'admin_id' => $adminId,
            'name' => $name,
            'employee_no' => trim((string) ($payload['employee_no'] ?? '')),
            'department' => trim((string) ($payload['department'] ?? '')),
            'mobile' => trim((string) ($payload['mobile'] ?? '')),
            'position' => trim((string) ($payload['position'] ?? '')),
            'status' => parse_bool($payload['status'] ?? '1') ? 1 : 0,
            'remark' => trim((string) ($payload['remark'] ?? '')),
        ]);
        sync_member_groups($adminId, $memberId, $groupIds);
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }
    create_operation_log($adminId, 'update', 'member', $memberId, '');
}

function sync_member_groups(int $adminId, int $memberId, mixed $groupIdsInput): void
{
    ensure_member_data_schema();
    $member = find_member_by_id($adminId, $memberId, true);
    if ($member === null) {
        throw new InvalidArgumentException('');
    }

    $groupIds = normalize_group_ids($groupIdsInput);
    $validGroupIds = [];
    if ($groupIds !== []) {
        $params = [
            'admin_id' => $adminId,
        ];
        $placeholders = [];
        foreach ($groupIds as $idx => $groupId) {
            $key = 'group_id_' . $idx;
            $placeholders[] = ':' . $key;
            $params[$key] = $groupId;
        }
        $sql = 'SELECT id FROM member_groups
                WHERE admin_id = :admin_id
                  AND id IN (' . implode(',', $placeholders) . ')';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $gid = (int) ($row['id'] ?? 0);
            if ($gid > 0) {
                $validGroupIds[] = $gid;
            }
        }
        $validGroupIds = array_values(array_unique($validGroupIds));
    }

    $deleteStmt = db()->prepare(
        'DELETE FROM member_group_members
         WHERE admin_id = :admin_id
           AND member_id = :member_id'
    );
    $deleteStmt->execute([
        'admin_id' => $adminId,
        'member_id' => $memberId,
    ]);

    if ($validGroupIds === []) {
        return;
    }

    $insertStmt = db()->prepare(
        'INSERT IGNORE INTO member_group_members (admin_id, member_id, group_id, created_at)
         VALUES (:admin_id, :member_id, :group_id, NOW())'
    );
    foreach ($validGroupIds as $groupId) {
        $insertStmt->execute([
            'admin_id' => $adminId,
            'member_id' => $memberId,
            'group_id' => $groupId,
        ]);
    }
}

function toggle_member_status(int $adminId, int $memberId): void
{
    ensure_member_data_schema();

    $member = find_member_by_id($adminId, $memberId);
    if ($member === null) {
        throw new InvalidArgumentException('');
    }
    $nextStatus = (int) $member['status'] === 1 ? 0 : 1;
    $stmt = db()->prepare('UPDATE members SET status = :status, updated_at = NOW() WHERE id = :id AND admin_id = :admin_id');
    $stmt->execute([
        'status' => $nextStatus,
        'id' => $memberId,
        'admin_id' => $adminId,
    ]);
    create_operation_log($adminId, 'toggle_status', 'member', $memberId, '');
}

function soft_delete_member(int $adminId, int $memberId): void
{
    ensure_member_data_schema();
    $member = find_member_by_id($adminId, $memberId);
    if ($member === null) {
        throw new InvalidArgumentException('');
    }

    $stmt = db()->prepare(
        'UPDATE members
            SET deleted_at = NOW(),
                status = 0,
                updated_at = NOW()
          WHERE id = :id
            AND admin_id = :admin_id
            AND deleted_at IS NULL'
    );
    $stmt->execute([
        'id' => $memberId,
        'admin_id' => $adminId,
    ]);
    create_operation_log($adminId, 'soft_delete', 'member', $memberId, '');
}

function attach_member_group_labels(int $adminId, array $rows): array
{
    if ($rows === []) {
        return [];
    }

    $memberIds = [];
    foreach ($rows as $row) {
        $memberIds[] = (int) ($row['id'] ?? 0);
    }
    $memberIds = array_values(array_unique(array_filter($memberIds, static fn (int $id): bool => $id > 0)));
    if ($memberIds === []) {
        return $rows;
    }

    $map = list_member_group_labels_map($adminId, $memberIds);
    foreach ($rows as &$row) {
        $mid = (int) ($row['id'] ?? 0);
        $row['group_names'] = $map[$mid]['group_names'] ?? '';
        $row['group_ids'] = $map[$mid]['group_ids'] ?? [];
    }
    unset($row);
    return $rows;
}

function list_member_group_labels_map(int $adminId, array $memberIds): array
{
    if ($memberIds === []) {
        return [];
    }

    $placeholders = [];
    $params = [
        'admin_id_join' => $adminId,
        'admin_id_where' => $adminId,
    ];
    foreach ($memberIds as $idx => $memberId) {
        $key = 'member_id_' . $idx;
        $placeholders[] = ':' . $key;
        $params[$key] = $memberId;
    }

    $sql = 'SELECT mgm.member_id, mg.id AS group_id, mg.group_name
            FROM member_group_members mgm
            INNER JOIN member_groups mg
                ON mg.id = mgm.group_id
               AND mg.admin_id = :admin_id_join
            WHERE mgm.admin_id = :admin_id_where
              AND mgm.member_id IN (' . implode(',', $placeholders) . ')
            ORDER BY mg.id ASC';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll() ?: [];

    $map = [];
    foreach ($rows as $row) {
        $memberId = (int) ($row['member_id'] ?? 0);
        if ($memberId <= 0) {
            continue;
        }
        if (!isset($map[$memberId])) {
            $map[$memberId] = [
                'group_ids' => [],
                'group_names' => [],
            ];
        }
        $groupId = (int) ($row['group_id'] ?? 0);
        $groupName = trim((string) ($row['group_name'] ?? ''));
        if ($groupId > 0 && !in_array($groupId, $map[$memberId]['group_ids'], true)) {
            $map[$memberId]['group_ids'][] = $groupId;
        }
        if ($groupName !== '' && !in_array($groupName, $map[$memberId]['group_names'], true)) {
            $map[$memberId]['group_names'][] = $groupName;
        }
    }

    foreach ($map as &$item) {
        $item['group_names'] = implode(', ', $item['group_names']);
    }
    unset($item);

    return $map;
}

function list_member_groups(int $adminId): array
{
    ensure_member_data_schema();
    $stmt = db()->prepare('SELECT * FROM member_groups WHERE admin_id = :admin_id ORDER BY id DESC');
    $stmt->execute(['admin_id' => $adminId]);
    return $stmt->fetchAll() ?: [];
}

function find_member_group_by_id(int $adminId, int $groupId): ?array
{
    ensure_member_data_schema();
    $stmt = db()->prepare('SELECT * FROM member_groups WHERE admin_id = :admin_id AND id = :id LIMIT 1');
    $stmt->execute([
        'admin_id' => $adminId,
        'id' => $groupId,
    ]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function create_member_group(int $adminId, array $payload): int
{
    ensure_member_data_schema();
    $groupName = trim((string) ($payload['group_name'] ?? ''));
    if ($groupName === '') {
        throw new InvalidArgumentException('');
    }

    $stmt = db()->prepare(
        'INSERT INTO member_groups (admin_id, group_name, remark, status, created_at, updated_at)
         VALUES (:admin_id, :group_name, :remark, :status, NOW(), NOW())'
    );
    $stmt->execute([
        'admin_id' => $adminId,
        'group_name' => $groupName,
        'remark' => trim((string) ($payload['remark'] ?? '')),
        'status' => parse_bool($payload['status'] ?? '1') ? 1 : 0,
    ]);
    $id = (int) db()->lastInsertId();
    create_operation_log($adminId, 'create', 'member_group', $id, '');
    return $id;
}

function update_member_group(int $adminId, int $groupId, array $payload): void
{
    ensure_member_data_schema();
    $group = find_member_group_by_id($adminId, $groupId);
    if ($group === null) {
        throw new InvalidArgumentException('');
    }

    $groupName = trim((string) ($payload['group_name'] ?? ''));
    if ($groupName === '') {
        throw new InvalidArgumentException('');
    }

    $stmt = db()->prepare(
        'UPDATE member_groups
         SET group_name = :group_name,
             remark = :remark,
             status = :status,
             updated_at = NOW()
         WHERE id = :id AND admin_id = :admin_id'
    );
    $stmt->execute([
        'id' => $groupId,
        'admin_id' => $adminId,
        'group_name' => $groupName,
        'remark' => trim((string) ($payload['remark'] ?? '')),
        'status' => parse_bool($payload['status'] ?? '1') ? 1 : 0,
    ]);
    create_operation_log($adminId, 'update', 'member_group', $groupId, '');
}

function delete_member_group(int $adminId, int $groupId): void
{
    ensure_member_data_schema();
    $group = find_member_group_by_id($adminId, $groupId);
    if ($group === null) {
        throw new InvalidArgumentException('');
    }

    db()->beginTransaction();
    try {
        $deleteLinks = db()->prepare(
            'DELETE FROM member_group_members
             WHERE admin_id = :admin_id AND group_id = :group_id'
        );
        $deleteLinks->execute([
            'admin_id' => $adminId,
            'group_id' => $groupId,
        ]);

        $deleteGroup = db()->prepare(
            'DELETE FROM member_groups
             WHERE admin_id = :admin_id AND id = :id'
        );
        $deleteGroup->execute([
            'admin_id' => $adminId,
            'id' => $groupId,
        ]);

        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }

    create_operation_log($adminId, 'delete', 'member_group', $groupId, '');
}

function list_member_ids_in_group(int $adminId, int $groupId): array
{
    ensure_member_data_schema();
    $group = find_member_group_by_id($adminId, $groupId);
    if ($group === null) {
        throw new InvalidArgumentException('');
    }

    $stmt = db()->prepare(
        'SELECT mgm.member_id
         FROM member_group_members mgm
         INNER JOIN members m
            ON m.id = mgm.member_id
           AND m.admin_id = :admin_id_member
           AND m.deleted_at IS NULL
         WHERE mgm.admin_id = :admin_id_map
           AND mgm.group_id = :group_id
         ORDER BY mgm.member_id DESC'
    );
    $stmt->execute([
        'admin_id_member' => $adminId,
        'admin_id_map' => $adminId,
        'group_id' => $groupId,
    ]);

    $ids = [];
    foreach ($stmt->fetchAll() ?: [] as $row) {
        $ids[] = (int) ($row['member_id'] ?? 0);
    }
    return array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
}

function list_group_members(int $adminId, int $groupId, string $keyword = ''): array
{
    ensure_member_data_schema();
    $group = find_member_group_by_id($adminId, $groupId);
    if ($group === null) {
        throw new InvalidArgumentException('');
    }

    $params = [
        'admin_id_member' => $adminId,
        'admin_id_map' => $adminId,
        'group_id' => $groupId,
    ];
    $sql = 'SELECT m.*
            FROM members m
            INNER JOIN member_group_members mgm
               ON mgm.member_id = m.id
              AND mgm.admin_id = :admin_id_map
              AND mgm.group_id = :group_id
            WHERE m.admin_id = :admin_id_member
              AND m.deleted_at IS NULL';
    if ($keyword !== '') {
        $sql .= ' AND (
                    m.name LIKE :kw_name
                    OR m.mobile LIKE :kw_mobile
                    OR m.employee_no LIKE :kw_employee_no
                    OR m.department LIKE :kw_department
                    OR m.position LIKE :kw_position
                 )';
        $kw = '%' . $keyword . '%';
        $params['kw_name'] = $kw;
        $params['kw_mobile'] = $kw;
        $params['kw_employee_no'] = $kw;
        $params['kw_department'] = $kw;
        $params['kw_position'] = $kw;
    }
    $sql .= ' ORDER BY m.id DESC';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll() ?: [];
    return attach_member_group_labels($adminId, $rows);
}

function list_members_by_group(int $adminId, int $groupId): array
{
    // Backward-compatible alias for historical route calls.
    return list_group_members($adminId, $groupId, '');
}

function add_members_to_group(int $adminId, int $groupId, array $memberIds): int
{
    return batch_assign_members_group($adminId, $memberIds, $groupId);
}

function remove_members_from_group(int $adminId, int $groupId, array $memberIds): int
{
    ensure_member_data_schema();
    $group = find_member_group_by_id($adminId, $groupId);
    if ($group === null) {
        throw new InvalidArgumentException('');
    }

    $memberIds = normalize_member_ids($memberIds);
    if ($memberIds === []) {
        return 0;
    }

    $params = [
        'admin_id' => $adminId,
        'group_id' => $groupId,
    ];
    $placeholders = [];
    foreach ($memberIds as $idx => $id) {
        $key = 'member_id_' . $idx;
        $placeholders[] = ':' . $key;
        $params[$key] = $id;
    }

    $sql = 'DELETE FROM member_group_members
            WHERE admin_id = :admin_id
              AND group_id = :group_id
              AND member_id IN (' . implode(',', $placeholders) . ')';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    $affected = $stmt->rowCount();
    create_operation_log($adminId, 'remove_group_members', 'member_group', $groupId, '');
    return $affected;
}

function sync_group_members(int $adminId, int $groupId, array $memberIds): int
{
    ensure_member_data_schema();
    $group = find_member_group_by_id($adminId, $groupId);
    if ($group === null) {
        throw new InvalidArgumentException('');
    }

    $memberIds = normalize_member_ids($memberIds);
    $validMemberIds = [];
    if ($memberIds !== []) {
        $params = [
            'admin_id' => $adminId,
        ];
        $placeholders = [];
        foreach ($memberIds as $idx => $id) {
            $key = 'id_' . $idx;
            $placeholders[] = ':' . $key;
            $params[$key] = $id;
        }
        $sql = 'SELECT id FROM members
                WHERE admin_id = :admin_id
                  AND deleted_at IS NULL
                  AND id IN (' . implode(',', $placeholders) . ')';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $validMemberIds[] = $id;
            }
        }
        $validMemberIds = array_values(array_unique($validMemberIds));
    }

    db()->beginTransaction();
    try {
        $deleteStmt = db()->prepare(
            'DELETE FROM member_group_members
             WHERE admin_id = :admin_id
               AND group_id = :group_id'
        );
        $deleteStmt->execute([
            'admin_id' => $adminId,
            'group_id' => $groupId,
        ]);

        if ($validMemberIds !== []) {
            $insertStmt = db()->prepare(
                'INSERT IGNORE INTO member_group_members (admin_id, member_id, group_id, created_at)
                 VALUES (:admin_id, :member_id, :group_id, NOW())'
            );
            foreach ($validMemberIds as $memberId) {
                $insertStmt->execute([
                    'admin_id' => $adminId,
                    'member_id' => $memberId,
                    'group_id' => $groupId,
                ]);
            }
        }

        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }

    create_operation_log($adminId, 'sync_group_members', 'member_group', $groupId, '');
    return count($validMemberIds);
}

function batch_update_members_status(int $adminId, array $memberIds, int $status): int
{
    ensure_member_data_schema();
    $memberIds = normalize_member_ids($memberIds);
    if ($memberIds === []) {
        return 0;
    }
    $status = $status === 1 ? 1 : 0;

    $params = [
        'admin_id' => $adminId,
        'status' => $status,
    ];
    $placeholders = [];
    foreach ($memberIds as $idx => $id) {
        $key = 'id_' . $idx;
        $placeholders[] = ':' . $key;
        $params[$key] = $id;
    }

    $sql = 'UPDATE members
            SET status = :status,
                updated_at = NOW()
            WHERE admin_id = :admin_id
              AND deleted_at IS NULL
              AND id IN (' . implode(',', $placeholders) . ')';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    create_operation_log($adminId, 'batch_update_status', 'member', 0, 'batch member status update');
    return $stmt->rowCount();
}

function batch_soft_delete_members(int $adminId, array $memberIds): int
{
    ensure_member_data_schema();
    $memberIds = normalize_member_ids($memberIds);
    if ($memberIds === []) {
        return 0;
    }

    $params = [
        'admin_id' => $adminId,
    ];
    $placeholders = [];
    foreach ($memberIds as $idx => $id) {
        $key = 'id_' . $idx;
        $placeholders[] = ':' . $key;
        $params[$key] = $id;
    }

    $sql = 'UPDATE members
            SET deleted_at = NOW(),
                status = 0,
                updated_at = NOW()
            WHERE admin_id = :admin_id
              AND deleted_at IS NULL
              AND id IN (' . implode(',', $placeholders) . ')';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    create_operation_log($adminId, 'batch_soft_delete', 'member', 0, '');
    return $stmt->rowCount();
}

function batch_assign_members_group(int $adminId, array $memberIds, int $groupId): int
{
    ensure_member_data_schema();
    $memberIds = normalize_member_ids($memberIds);
    if ($memberIds === []) {
        return 0;
    }

    $group = find_member_group_by_id($adminId, $groupId);
    if ($group === null) {
        throw new InvalidArgumentException('');
    }

    $params = [
        'admin_id' => $adminId,
        'group_id' => $groupId,
    ];
    $placeholders = [];
    foreach ($memberIds as $idx => $id) {
        $key = 'id_' . $idx;
        $placeholders[] = ':' . $key;
        $params[$key] = $id;
    }

    $insertSql = 'INSERT IGNORE INTO member_group_members (admin_id, member_id, group_id, created_at)
                  SELECT m.admin_id, m.id, :group_id, NOW()
                  FROM members m
                  WHERE m.admin_id = :admin_id
                    AND m.deleted_at IS NULL
                    AND m.id IN (' . implode(',', $placeholders) . ')';
    $insertStmt = db()->prepare($insertSql);
    $insertStmt->execute($params);

    $affected = $insertStmt->rowCount();
    create_operation_log($adminId, 'batch_assign_group', 'member_group', $groupId, '');
    return $affected;
}

function import_members_from_excel(int $adminId, array $file, string $duplicateMode = 'skip'): array
{
    ensure_member_data_schema();
    $duplicateMode = normalize_import_duplicate_mode($duplicateMode);

    $filename = (string) ($file['name'] ?? '');
    $tmpPath = (string) ($file['tmp_name'] ?? '');
    $uploadError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    $size = (int) ($file['size'] ?? 0);
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    log_member_import_debug([
        'stage' => 'start',
        'admin_id' => $adminId,
        'filename' => $filename,
        'tmp_path' => $tmpPath,
        'size' => $size,
        'ext' => $ext,
        'duplicate_mode' => $duplicateMode,
        'upload_error' => $uploadError,
    ]);

    if ($uploadError !== UPLOAD_ERR_OK) {
        log_member_import_debug([
            'stage' => 'upload_error',
            'admin_id' => $adminId,
            'filename' => $filename,
            'upload_error' => $uploadError,
        ]);
        throw new InvalidArgumentException(upload_error_message($uploadError));
    }

    if ($tmpPath === '' || !is_file($tmpPath)) {
        log_member_import_debug([
            'stage' => 'tmp_file_missing',
            'admin_id' => $adminId,
            'filename' => $filename,
            'tmp_path' => $tmpPath,
        ]);
        throw new InvalidArgumentException('上传临时文件不存在，请重试一次');
    }

    if (!in_array($ext, ['xlsx', 'csv'], true)) {
        log_member_import_debug([
            'stage' => 'ext_not_supported',
            'admin_id' => $adminId,
            'filename' => $filename,
            'ext' => $ext,
        ]);
        throw new InvalidArgumentException('仅支持 .xlsx 或 .csv 文件');
    }

    try {
        $rows = $ext === 'csv'
            ? parse_member_rows_from_csv($tmpPath)
            : parse_member_rows_from_xlsx($tmpPath);
    } catch (Throwable $e) {
        log_member_import_debug([
            'stage' => 'parse_exception',
            'admin_id' => $adminId,
            'filename' => $filename,
            'ext' => $ext,
            'message' => $e->getMessage(),
        ]);
        throw $e;
    }

    if ($rows === []) {
        log_member_import_debug([
            'stage' => 'empty_rows',
            'admin_id' => $adminId,
            'filename' => $filename,
            'ext' => $ext,
        ]);
        throw new InvalidArgumentException('导入文件内容为空，或格式不正确');
    }

    $insert = db()->prepare(
        'INSERT INTO members (
            admin_id, name, employee_no, department, mobile, position, status, remark, created_at, updated_at
         ) VALUES (
            :admin_id, :name, NULL, :department, :mobile, :position, 1, NULL, NOW(), NOW()
         )'
    );
    $update = db()->prepare(
        'UPDATE members
            SET name = :name,
                department = :department,
                mobile = :mobile,
                position = :position,
                updated_at = NOW()
          WHERE id = :id AND admin_id = :admin_id'
    );

    $total = 0;
    $imported = 0;
    $updated = 0;
    $skipped = 0;
    $failed = 0;
    $errors = [];
    foreach ($rows as $index => $row) {
        $total++;
        $lineNo = $index + 1;
        [$name, $department, $position, $mobile] = normalize_member_import_row($row);

        if ($lineNo === 1 && is_member_import_header_row($name, $department, $position, $mobile)) {
            $skipped++;
            continue;
        }
        if ($name === '') {
            $skipped++;
            continue;
        }

        $existing = $mobile === '' ? null : find_member_by_mobile_for_import($adminId, $mobile);
        try {
            if ($existing !== null) {
                if ($duplicateMode === 'skip') {
                    $skipped++;
                    continue;
                }
                $update->execute([
                    'id' => (int) $existing['id'],
                    'admin_id' => $adminId,
                    'name' => $name,
                    'department' => $department,
                    'mobile' => $mobile,
                    'position' => $position,
                ]);
                $updated++;
                continue;
            }
            $insert->execute([
                'admin_id' => $adminId,
                'name' => $name,
                'department' => $department,
                'mobile' => $mobile,
                'position' => $position,
            ]);
            $imported++;
        } catch (Throwable $e) {
            $failed++;
            if (count($errors) < 5) {
                $errors[] = 'line ' . $lineNo . ': ' . $e->getMessage();
            }
        }
    }

    create_operation_log(
        $adminId,
        'import',
        'member',
        0,
        sprintf(
            '成员导入：mode=%s,total=%d,imported=%d,updated=%d,skipped=%d,failed=%d',
            $duplicateMode,
            $total,
            $imported,
            $updated,
            $skipped,
            $failed
        )
    );

    $result = [
        'total' => $total,
        'imported' => $imported,
        'updated' => $updated,
        'skipped' => $skipped,
        'failed' => $failed,
        'errors' => $errors,
    ];

    log_member_import_debug([
        'stage' => 'finish',
        'admin_id' => $adminId,
        'filename' => $filename,
        'ext' => $ext,
        'duplicate_mode' => $duplicateMode,
        'result' => $result,
    ]);

    return $result;
}

function log_member_import_debug(array $context): void
{
    try {
        $logDir = dirname(APP_ROOT) . '/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }
        $logFile = $logDir . '/member_import.log';
        $line = '[' . date('Y-m-d H:i:s') . '] ' . json_encode(
            $context,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ) . PHP_EOL;
        file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    } catch (Throwable) {
        // never interrupt import flow when logging fails
    }
}


function normalize_import_duplicate_mode(string $duplicateMode): string
{
    $mode = strtolower(trim($duplicateMode));
    if (!in_array($mode, ['skip', 'update'], true)) {
        return 'skip';
    }
    return $mode;
}

function find_member_by_mobile_for_import(int $adminId, string $mobile): ?array
{
    $mobile = trim($mobile);
    if ($mobile === '') {
        return null;
    }
    $stmt = db()->prepare(
        'SELECT id, admin_id, mobile
         FROM members
         WHERE admin_id = :admin_id
           AND deleted_at IS NULL
           AND mobile = :mobile
         ORDER BY id DESC
         LIMIT 1'
    );
    $stmt->execute([
        'admin_id' => $adminId,
        'mobile' => $mobile,
    ]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function upload_error_message(int $error): string
{
    return match ($error) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => '文件过大，超过服务器上传限制',
        UPLOAD_ERR_PARTIAL => '文件上传不完整，请重试',
        UPLOAD_ERR_NO_FILE => '请选择要上传的文件',
        UPLOAD_ERR_NO_TMP_DIR => '服务器缺少临时目录，无法上传',
        UPLOAD_ERR_CANT_WRITE => '服务器写入文件失败，请稍后重试',
        UPLOAD_ERR_EXTENSION => '文件上传被服务器扩展拦截',
        default => '文件上传失败，请重试',
    };
}

function parse_member_rows_from_csv(string $filePath): array
{
    $handle = fopen($filePath, 'rb');
    if ($handle === false) {
        throw new InvalidArgumentException('CSV 文件读取失败，请确认文件可访问');
    }

    $rows = [];
    while (($data = fgetcsv($handle)) !== false) {
        $rows[] = [
            normalize_import_text($data[0] ?? ''),
            normalize_import_text($data[1] ?? ''),
            normalize_import_text($data[2] ?? ''),
            normalize_import_text($data[3] ?? ''),
        ];
    }
    fclose($handle);

    return $rows;
}

function parse_member_rows_from_xlsx(string $filePath): array
{
    if (!class_exists('ZipArchive')) {
        throw new InvalidArgumentException('服务器未启用 ZipArchive 扩展，无法解析 .xlsx，请改用 .csv，或联系管理员启用 php-zip');
    }

    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) {
        throw new InvalidArgumentException('Excel 文件无法打开，请确认文件未损坏并重新导出为 .xlsx');
    }

    $sheetPath = locate_first_xlsx_sheet($zip);
    if ($sheetPath === null) {
        $zip->close();
        throw new InvalidArgumentException('Excel 中未找到工作表，请确认文件格式');
    }

    $sharedStrings = parse_xlsx_shared_strings($zip);
    $sheetXml = $zip->getFromName($sheetPath);
    $zip->close();
    if (!is_string($sheetXml) || trim($sheetXml) === '') {
        throw new InvalidArgumentException('Excel 工作表为空，请检查文件内容');
    }

    $previous = libxml_use_internal_errors(true);
    $xml = simplexml_load_string($sheetXml);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    if (!$xml instanceof SimpleXMLElement) {
        throw new InvalidArgumentException('Excel 解析失败，请重新导出为 .xlsx 后再试');
    }
    $xlsxNs = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    $xml->registerXPathNamespace('x', $xlsxNs);

    $rows = [];
    $rowNodes = $xml->xpath('//x:sheetData/x:row');
    if ($rowNodes === false) {
        return $rows;
    }
    foreach ($rowNodes as $rowNode) {
        if (!$rowNode instanceof SimpleXMLElement) {
            continue;
        }
        $rowNode->registerXPathNamespace('x', $xlsxNs);

        $cells = ['', '', '', ''];
        $cellNodes = $rowNode->xpath('x:c');
        if ($cellNodes === false || $cellNodes === []) {
            continue;
        }
        $nextColumnIndex = 0;
        foreach ($cellNodes as $cell) {
            if (!$cell instanceof SimpleXMLElement) {
                continue;
            }
            $ref = (string) ($cell['r'] ?? '');
            $columnIndex = $nextColumnIndex;
            if ($ref !== '') {
                $column = preg_replace('/\d+/', '', $ref) ?? '';
                $parsedColumnIndex = excel_column_index((string) $column);
                if ($parsedColumnIndex >= 0) {
                    $columnIndex = $parsedColumnIndex;
                }
            }
            $nextColumnIndex = $columnIndex + 1;
            if ($columnIndex < 0 || $columnIndex > 3) {
                continue;
            }
            $cells[$columnIndex] = normalize_import_text(read_xlsx_cell_value($cell, $sharedStrings));
        }
        $rows[] = $cells;
    }

    return $rows;
}

function locate_first_xlsx_sheet(ZipArchive $zip): ?string
{
    if ($zip->locateName('xl/worksheets/sheet1.xml') !== false) {
        return 'xl/worksheets/sheet1.xml';
    }

    $sheets = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = (string) $zip->getNameIndex($i);
        if (preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name) === 1) {
            $sheets[] = $name;
        }
    }
    if ($sheets === []) {
        return null;
    }
    sort($sheets, SORT_NATURAL);
    return $sheets[0];
}

function parse_xlsx_shared_strings(ZipArchive $zip): array
{
    $content = $zip->getFromName('xl/sharedStrings.xml');
    if (!is_string($content) || trim($content) === '') {
        return [];
    }

    $previous = libxml_use_internal_errors(true);
    $xml = simplexml_load_string($content);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    if (!$xml instanceof SimpleXMLElement) {
        return [];
    }
    $xlsxNs = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    $xml->registerXPathNamespace('x', $xlsxNs);

    $strings = [];
    $items = $xml->xpath('//x:si');
    if ($items === false) {
        return [];
    }
    foreach ($items as $item) {
        if (!$item instanceof SimpleXMLElement) {
            continue;
        }
        $item->registerXPathNamespace('x', $xlsxNs);
        $textNodes = $item->xpath('.//x:t');
        if ($textNodes === false || $textNodes === []) {
            $strings[] = '';
            continue;
        }
        $text = '';
        foreach ($textNodes as $node) {
            $text .= (string) $node;
        }
        $strings[] = $text;
    }
    return $strings;
}

function read_xlsx_cell_value(SimpleXMLElement $cell, array $sharedStrings): string
{
    $cell->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    $type = (string) ($cell['t'] ?? '');

    if ($type === 's') {
        $valueNodes = $cell->xpath('x:v');
        $index = isset($valueNodes[0]) ? (int) $valueNodes[0] : -1;
        return $sharedStrings[$index] ?? '';
    }

    if ($type === 'inlineStr') {
        $textNodes = $cell->xpath('x:is//x:t');
        if ($textNodes === false || $textNodes === []) {
            return '';
        }
        $text = '';
        foreach ($textNodes as $node) {
            $text .= (string) $node;
        }
        return $text;
    }

    $valueNodes = $cell->xpath('x:v');
    return isset($valueNodes[0]) ? (string) $valueNodes[0] : '';
}

function excel_column_index(string $column): int
{
    $column = strtoupper(trim($column));
    if ($column === '') {
        return -1;
    }

    $index = 0;
    $length = strlen($column);
    for ($i = 0; $i < $length; $i++) {
        $char = $column[$i];
        if ($char < 'A' || $char > 'Z') {
            return -1;
        }
        $index = ($index * 26) + (ord($char) - 64);
    }
    return $index - 1;
}

function normalize_member_import_row(array $row): array
{
    $name = normalize_import_text($row[0] ?? '');
    $department = normalize_import_text($row[1] ?? '');
    $position = normalize_import_text($row[2] ?? '');
    $mobile = normalize_import_text($row[3] ?? '');

    // Backward compatibility: old templates may still be [姓名, 电话, 职位]
    if ($mobile === '' && looks_like_mobile_for_import($department)) {
        $mobile = $department;
        $department = '';
    }

    if (preg_match('/^[+-]?\d+(\.0+)?$/', $mobile) === 1) {
        $mobile = preg_replace('/\.0+$/', '', $mobile) ?? $mobile;
    } elseif (preg_match('/^[+-]?\d+(\.\d+)?[eE][+-]?\d+$/', $mobile) === 1) {
        $mobile = sprintf('%.0f', (float) $mobile);
    }

    return [$name, $department, $position, $mobile];
}

function looks_like_mobile_for_import(string $value): bool
{
    $value = trim($value);
    if ($value === '') {
        return false;
    }

    $normalized = preg_replace('/[\\s\\-()+]/', '', $value) ?? '';
    return preg_match('/^\\d{6,20}$/', $normalized) === 1;
}

function normalize_import_text(mixed $value): string
{
    $text = trim((string) $value);
    $text = preg_replace('/^\xEF\xBB\xBF/u', '', $text) ?? $text;
    if ($text === '') {
        return '';
    }

    if (function_exists('mb_check_encoding') && !mb_check_encoding($text, 'UTF-8')) {
        $converted = mb_convert_encoding($text, 'UTF-8', 'UTF-8,GBK,GB2312,BIG5');
        if (is_string($converted) && $converted !== '') {
            $text = $converted;
        }
    }

    $text = str_replace(["\r", "\n", "\t"], ' ', $text);
    return trim($text);
}

function is_member_import_header_row(string $name, string $department, string $position, string $mobile): bool
{
    $nameNorm = strtolower(str_replace(' ', '', trim($name)));
    $departmentNorm = strtolower(str_replace(' ', '', trim($department)));
    $positionNorm = strtolower(str_replace(' ', '', trim($position)));
    $mobileNorm = strtolower(str_replace(' ', '', trim($mobile)));

    $nameHeaders = ["\u{59D3}\u{540D}", "\u{540D}\u{5B57}", 'name', 'membername', 'username'];
    $departmentHeaders = ["\u{90E8}\u{95E8}", "\u{79D1}\u{5BA4}", 'department', 'dept'];
    $positionHeaders = ["\u{804C}\u{4F4D}", "\u{5C97}\u{4F4D}", "\u{804C}\u{52A1}", 'position', 'role', 'job'];
    $mobileHeaders = ["\u{7535}\u{8BDD}", "\u{624B}\u{673A}\u{53F7}", "\u{624B}\u{673A}", "\u{8054}\u{7CFB}\u{65B9}\u{5F0F}", 'mobile', 'phone', 'tel', 'phonenumber'];

    $nameIsHeader = in_array($nameNorm, $nameHeaders, true) || str_contains($nameNorm, 'name');
    $departmentIsHeader = in_array($departmentNorm, $departmentHeaders, true)
        || str_contains($departmentNorm, 'department')
        || str_contains($departmentNorm, 'dept');
    $positionIsHeader = in_array($positionNorm, $positionHeaders, true)
        || str_contains($positionNorm, 'position')
        || str_contains($positionNorm, 'role');
    $mobileIsHeader = in_array($mobileNorm, $mobileHeaders, true)
        || str_contains($mobileNorm, 'mobile')
        || str_contains($mobileNorm, 'phone')
        || $mobileNorm === 'tel';

    return $nameIsHeader && ($departmentIsHeader || $positionIsHeader || $mobileIsHeader);
}

function list_tasks(int $adminId): array
{
    ensure_task_data_schema();

    $stmt = db()->prepare(
        'SELECT * FROM tasks
         WHERE admin_id = :admin_id
         ORDER BY id DESC'
    );
    $stmt->execute(['admin_id' => $adminId]);
    return $stmt->fetchAll() ?: [];
}

function find_task_by_id(int $adminId, int $taskId): ?array
{
    ensure_task_data_schema();

    $stmt = db()->prepare('SELECT * FROM tasks WHERE admin_id = :admin_id AND id = :id LIMIT 1');
    $stmt->execute([
        'admin_id' => $adminId,
        'id' => $taskId,
    ]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function find_task_by_link_code(string $taskLinkCode, bool $mustPublic): ?array
{
    ensure_task_data_schema();

    $sql = 'SELECT * FROM tasks WHERE task_link_code = :code LIMIT 1';
    if ($mustPublic) {
        $sql = 'SELECT * FROM tasks
                WHERE task_link_code = :code
                  AND task_status = 1
                LIMIT 1';
    }
    $stmt = db()->prepare($sql);
    $stmt->execute(['code' => $taskLinkCode]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function list_top_level_tasks(int $adminId): array
{
    // 轻量版仅保留普通顶层任务，复用统一查询。
    return list_tasks($adminId);
}

function list_task_children(int $adminId, int $parentTaskId, bool $publicOnly = false): array
{
    // 轻量版已移除父子任务模型，保留函数仅做兼容。
    return [];
}

function set_task_subtask_status(int $adminId, int $taskId, int $loopSubtaskStatus): void
{
    throw new InvalidArgumentException('轻量版已移除子任务状态管理');
}

function create_task(int $adminId, array $payload): int
{
    ensure_task_data_schema();

    $data = normalize_task_payload($payload);
    validate_task_parent_relation($adminId, null, $data);
    $memberIds = resolve_task_member_ids_from_payload($adminId, $payload);
    $taskLinkCode = generate_task_link_code();
    $hasTaskTypeColumn = schema_column_exists('tasks', 'task_type');

    db()->beginTransaction();
    try {
        if ($hasTaskTypeColumn) {
            $stmt = db()->prepare(
                'INSERT INTO tasks (
                    admin_id, task_type, task_name, task_desc,
                    start_time, end_time, is_long_term,
                    allow_override, duplicate_action, min_images, max_images,
                    allow_select_task_date, task_date_mode,
                    task_status, task_link_code, sort_order, remark, created_at, updated_at
                ) VALUES (
                    :admin_id, :task_type, :task_name, :task_desc,
                    :start_time, :end_time, :is_long_term,
                    :allow_override, :duplicate_action, :min_images, :max_images,
                    :allow_select_task_date, :task_date_mode,
                    :task_status, :task_link_code, :sort_order, :remark, NOW(), NOW()
                )'
            );
            $stmt->execute([
                'admin_id' => $adminId,
                'task_type' => $data['task_type'],
                'task_name' => $data['task_name'],
                'task_desc' => $data['task_desc'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'is_long_term' => $data['is_long_term'],
                'allow_override' => $data['allow_override'],
                'duplicate_action' => $data['duplicate_action'],
                'min_images' => $data['min_images'],
                'max_images' => $data['max_images'],
                'allow_select_task_date' => $data['allow_select_task_date'],
                'task_date_mode' => $data['task_date_mode'],
                'task_status' => $data['task_status'],
                'task_link_code' => $taskLinkCode,
                'sort_order' => $data['sort_order'],
                'remark' => $data['remark'],
            ]);
        } else {
            $stmt = db()->prepare(
                'INSERT INTO tasks (
                    admin_id, task_name, task_desc,
                    start_time, end_time, is_long_term,
                    allow_override, duplicate_action, min_images, max_images,
                    allow_select_task_date, task_date_mode,
                    task_status, task_link_code, sort_order, remark, created_at, updated_at
                ) VALUES (
                    :admin_id, :task_name, :task_desc,
                    :start_time, :end_time, :is_long_term,
                    :allow_override, :duplicate_action, :min_images, :max_images,
                    :allow_select_task_date, :task_date_mode,
                    :task_status, :task_link_code, :sort_order, :remark, NOW(), NOW()
                )'
            );
            $stmt->execute([
                'admin_id' => $adminId,
                'task_name' => $data['task_name'],
                'task_desc' => $data['task_desc'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'is_long_term' => $data['is_long_term'],
                'allow_override' => $data['allow_override'],
                'duplicate_action' => $data['duplicate_action'],
                'min_images' => $data['min_images'],
                'max_images' => $data['max_images'],
                'allow_select_task_date' => $data['allow_select_task_date'],
                'task_date_mode' => $data['task_date_mode'],
                'task_status' => $data['task_status'],
                'task_link_code' => $taskLinkCode,
                'sort_order' => $data['sort_order'],
                'remark' => $data['remark'],
            ]);
        }
        $taskId = (int) db()->lastInsertId();
        sync_task_members($taskId, $memberIds);
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }

    create_operation_log($adminId, 'create', 'task', $taskId, '');
    return $taskId;
}

function update_task(int $adminId, int $taskId, array $payload): void
{
    ensure_task_data_schema();

    $existing = find_task_by_id($adminId, $taskId);
    if ($existing === null) {
        throw new InvalidArgumentException('');
    }

    $data = normalize_task_payload($payload, $existing);
    validate_task_parent_relation($adminId, $taskId, $data);
    $memberIds = resolve_task_member_ids_from_payload($adminId, $payload);
    $hasTaskTypeColumn = schema_column_exists('tasks', 'task_type');

    db()->beginTransaction();
    try {
        if ($hasTaskTypeColumn) {
            $stmt = db()->prepare(
                'UPDATE tasks SET
                    task_type = :task_type,
                    task_name = :task_name,
                    task_desc = :task_desc,
                    start_time = :start_time,
                    end_time = :end_time,
                    is_long_term = :is_long_term,
                    allow_override = :allow_override,
                    duplicate_action = :duplicate_action,
                    min_images = :min_images,
                    max_images = :max_images,
                    allow_select_task_date = :allow_select_task_date,
                    task_date_mode = :task_date_mode,
                    task_status = :task_status,
                    sort_order = :sort_order,
                    remark = :remark,
                    updated_at = NOW()
                 WHERE id = :id AND admin_id = :admin_id'
            );
            $stmt->execute([
                'id' => $taskId,
                'admin_id' => $adminId,
                'task_type' => $data['task_type'],
                'task_name' => $data['task_name'],
                'task_desc' => $data['task_desc'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'is_long_term' => $data['is_long_term'],
                'allow_override' => $data['allow_override'],
                'duplicate_action' => $data['duplicate_action'],
                'min_images' => $data['min_images'],
                'max_images' => $data['max_images'],
                'allow_select_task_date' => $data['allow_select_task_date'],
                'task_date_mode' => $data['task_date_mode'],
                'task_status' => $data['task_status'],
                'sort_order' => $data['sort_order'],
                'remark' => $data['remark'],
            ]);
        } else {
            $stmt = db()->prepare(
                'UPDATE tasks SET
                    task_name = :task_name,
                    task_desc = :task_desc,
                    start_time = :start_time,
                    end_time = :end_time,
                    is_long_term = :is_long_term,
                    allow_override = :allow_override,
                    duplicate_action = :duplicate_action,
                    min_images = :min_images,
                    max_images = :max_images,
                    allow_select_task_date = :allow_select_task_date,
                    task_date_mode = :task_date_mode,
                    task_status = :task_status,
                    sort_order = :sort_order,
                    remark = :remark,
                    updated_at = NOW()
                 WHERE id = :id AND admin_id = :admin_id'
            );
            $stmt->execute([
                'id' => $taskId,
                'admin_id' => $adminId,
                'task_name' => $data['task_name'],
                'task_desc' => $data['task_desc'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'is_long_term' => $data['is_long_term'],
                'allow_override' => $data['allow_override'],
                'duplicate_action' => $data['duplicate_action'],
                'min_images' => $data['min_images'],
                'max_images' => $data['max_images'],
                'allow_select_task_date' => $data['allow_select_task_date'],
                'task_date_mode' => $data['task_date_mode'],
                'task_status' => $data['task_status'],
                'sort_order' => $data['sort_order'],
                'remark' => $data['remark'],
            ]);
        }
        sync_task_members($taskId, $memberIds);
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }

    create_operation_log($adminId, 'update', 'task', $taskId, '');
}

function set_task_hidden_status(int $adminId, int $taskId, bool $hidden): void
{
    ensure_task_data_schema();

    $existing = find_task_by_id($adminId, $taskId);
    if ($existing === null) {
        throw new InvalidArgumentException('未找到任务');
    }

    $taskStatus = $hidden ? 0 : 1;
    $stmt = db()->prepare(
        'UPDATE tasks
         SET task_status = :task_status,
             updated_at = NOW()
         WHERE id = :id
           AND admin_id = :admin_id'
    );
    $stmt->execute([
        'task_status' => $taskStatus,
        'id' => $taskId,
        'admin_id' => $adminId,
    ]);

    create_operation_log($adminId, $hidden ? 'hide' : 'show', 'task', $taskId, '');
}

function delete_task_with_records(int $adminId, int $taskId): array
{
    ensure_task_data_schema();

    $existing = find_task_by_id($adminId, $taskId);
    if ($existing === null) {
        throw new InvalidArgumentException('未找到可删除的任务');
    }

    $recordCountStmt = db()->prepare('SELECT COUNT(*) FROM checkin_records WHERE task_id = :task_id');
    $recordCountStmt->execute(['task_id' => $taskId]);
    $recordCount = (int) $recordCountStmt->fetchColumn();

    $memberCountStmt = db()->prepare('SELECT COUNT(*) FROM task_members WHERE task_id = :task_id');
    $memberCountStmt->execute(['task_id' => $taskId]);
    $memberCount = (int) $memberCountStmt->fetchColumn();

    $imageStmt = db()->prepare(
        'SELECT ci.image_path, ci.image_thumb_path
         FROM checkin_images ci
         INNER JOIN checkin_records cr ON cr.id = ci.record_id
         WHERE cr.task_id = :task_id'
    );
    $imageStmt->execute(['task_id' => $taskId]);
    $imageRows = $imageStmt->fetchAll() ?: [];
    $imageCount = count($imageRows);

    $paths = [];
    foreach ($imageRows as $row) {
        foreach (['image_path', 'image_thumb_path'] as $field) {
            $value = trim((string) ($row[$field] ?? ''));
            if ($value !== '') {
                $paths[$value] = true;
            }
        }
    }
    $imagePaths = array_keys($paths);

    db()->beginTransaction();
    try {
        $deleteImagesStmt = db()->prepare(
            'DELETE ci
             FROM checkin_images ci
             INNER JOIN checkin_records cr ON cr.id = ci.record_id
             WHERE cr.task_id = :task_id'
        );
        $deleteImagesStmt->execute(['task_id' => $taskId]);

        $deleteRecordsStmt = db()->prepare('DELETE FROM checkin_records WHERE task_id = :task_id');
        $deleteRecordsStmt->execute(['task_id' => $taskId]);

        $deleteTaskMembersStmt = db()->prepare('DELETE FROM task_members WHERE task_id = :task_id');
        $deleteTaskMembersStmt->execute(['task_id' => $taskId]);

        $deleteTaskStmt = db()->prepare('DELETE FROM tasks WHERE id = :id AND admin_id = :admin_id');
        $deleteTaskStmt->execute([
            'id' => $taskId,
            'admin_id' => $adminId,
        ]);

        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }

    foreach ($imagePaths as $relativePath) {
        $path = trim((string) $relativePath);
        if ($path === '' || !str_starts_with($path, '/storage/uploads/')) {
            continue;
        }
        $absolutePath = APP_ROOT . $path;
        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }

    create_operation_log($adminId, 'delete', 'task', $taskId, 'delete task with records');

    return [
        'record_count' => $recordCount,
        'image_count' => $imageCount,
        'member_count' => $memberCount,
    ];
}

function normalize_task_payload(array $payload, ?array $existing = null): array
{
    $taskName = trim((string) ($payload['task_name'] ?? ($existing['task_name'] ?? '')));
    if ($taskName === '') {
        throw new InvalidArgumentException('');
    }

    $startTimeInput = (string) ($payload['start_time'] ?? ($existing['start_time'] ?? ''));
    $startTime = to_mysql_datetime($startTimeInput);
    if ($startTime === null) {
        throw new InvalidArgumentException('');
    }

    $expireMode = trim((string) ($payload['expire_mode'] ?? ''));
    $customDaysInput = (int) ($payload['custom_days'] ?? 0);

    $existingIsLongTerm = (int) ($existing['is_long_term'] ?? 0) === 1;
    $existingStartAt = strtotime((string) ($existing['start_time'] ?? ''));
    $existingEndAt = strtotime((string) ($existing['end_time'] ?? ''));

    if ($expireMode === '') {
        if ($existing !== null) {
            if ($existingIsLongTerm) {
                $expireMode = 'long_term';
            } elseif ($existingStartAt !== false && $existingEndAt !== false && $existingEndAt >= $existingStartAt) {
                $daysDiff = (int) floor(($existingEndAt - $existingStartAt) / 86400);
                if ($daysDiff === 3) {
                    $expireMode = 'three_days';
                } elseif ($daysDiff === 5) {
                    $expireMode = 'five_days';
                } elseif ($daysDiff === 7) {
                    $expireMode = 'one_week';
                } elseif ($daysDiff > 0) {
                    $expireMode = 'custom_days';
                    $customDaysInput = $daysDiff;
                }
            }
        }

        if ($expireMode === '') {
            $legacyIsLongTerm = parse_bool($payload['is_long_term'] ?? false);
            if ($legacyIsLongTerm) {
                $expireMode = 'long_term';
            } else {
                $legacyEndTime = to_mysql_datetime((string) ($payload['end_time'] ?? ''));
                if ($legacyEndTime !== null) {
                    $legacyStartAt = strtotime($startTime);
                    $legacyEndAt = strtotime($legacyEndTime);
                    if ($legacyStartAt !== false && $legacyEndAt !== false && $legacyEndAt >= $legacyStartAt) {
                        $daysDiff = (int) floor(($legacyEndAt - $legacyStartAt) / 86400);
                        if ($daysDiff === 3) {
                            $expireMode = 'three_days';
                        } elseif ($daysDiff === 5) {
                            $expireMode = 'five_days';
                        } elseif ($daysDiff === 7) {
                            $expireMode = 'one_week';
                        } else {
                            $expireMode = 'custom_days';
                            $customDaysInput = max(1, $daysDiff);
                        }
                    }
                }
            }
        }

        if ($expireMode === '') {
            $expireMode = 'three_days';
        }
    }

    // 「当月有效」优先级最高：任务发布当月生效，到发布月份最后一天 23:59:59 结束
    // （下月自动关闭）。end_time 恒为月末 23:59:59（秒=59），与按天模式（秒=00）
    // 天然可区分，无需数据库新增列即可可靠回显。
    $currentMonthValid = parse_bool($payload['current_month'] ?? false);

    $isLongTerm = 0;
    $endTime = null;
    if ($currentMonthValid) {
        $startTs = strtotime($startTime);
        if ($startTs === false) {
            throw new InvalidArgumentException('');
        }
        $endTime = date('Y-m-t 23:59:59', $startTs);
    } elseif ($expireMode === 'long_term') {
        $isLongTerm = 1;
    } else {
        $days = match ($expireMode) {
            'three_days' => 3,
            'five_days' => 5,
            'one_week' => 7,
            'custom_days' => max(1, $customDaysInput),
            default => 3,
        };

        $startTs = strtotime($startTime);
        if ($startTs === false) {
            throw new InvalidArgumentException('');
        }
        $endTime = date('Y-m-d H:i:s', strtotime('+' . $days . ' days', $startTs));
    }

    $minImages = max(1, min(10, (int) ($payload['min_images'] ?? ($existing['min_images'] ?? 1))));
    $maxImages = max(1, min(10, (int) ($payload['max_images'] ?? ($existing['max_images'] ?? 3))));
    if ($minImages > $maxImages) {
        throw new InvalidArgumentException('');
    }

    $duplicateAction = trim((string) ($payload['duplicate_action'] ?? ($existing['duplicate_action'] ?? 'prompt')));
    if (!in_array($duplicateAction, ['prompt', 'reject', 'override'], true)) {
        $duplicateAction = 'prompt';
    }

    $taskDateMode = trim((string) ($payload['task_date_mode'] ?? ($existing['task_date_mode'] ?? 'free_in_range')));
    if (!in_array($taskDateMode, ['today_only', 'free_in_range'], true)) {
        $taskDateMode = 'free_in_range';
    }

    return [
        'task_type' => 'normal',
        'task_name' => $taskName,
        'task_desc' => trim((string) ($payload['task_desc'] ?? ($existing['task_desc'] ?? ''))),
        'start_time' => $startTime,
        'end_time' => $endTime,
        'is_long_term' => $isLongTerm,
        'allow_override' => parse_bool($payload['allow_override'] ?? ($existing['allow_override'] ?? true)) ? 1 : 0,
        'duplicate_action' => $duplicateAction,
        'min_images' => $minImages,
        'max_images' => $maxImages,
        'allow_select_task_date' => parse_bool($payload['allow_select_task_date'] ?? ($existing['allow_select_task_date'] ?? true)) ? 1 : 0,
        'task_date_mode' => $taskDateMode,
        'task_status' => parse_bool($payload['task_status'] ?? ($existing['task_status'] ?? true)) ? 1 : 0,
        'sort_order' => (int) ($payload['sort_order'] ?? ($existing['sort_order'] ?? 0)),
        'remark' => trim((string) ($payload['remark'] ?? ($existing['remark'] ?? ''))),
    ];
}

function validate_task_parent_relation(int $adminId, ?int $taskId, array $taskData): void
{
    // 轻量版不再使用任务父子关系，保留函数避免老调用出错。
}

function normalize_member_ids(mixed $value): array
{
    if (is_string($value) && $value !== '') {
        $parts = array_filter(array_map('trim', explode(',', $value)), static fn ($v) => $v !== '');
        $ids = array_map('intval', $parts);
    } elseif (is_array($value)) {
        $ids = array_map('intval', $value);
    } else {
        $ids = [];
    }

    $ids = array_values(array_unique(array_filter($ids, static fn ($id) => $id > 0)));
    return $ids;
}

function list_member_ids_by_group_ids(int $adminId, array $groupIds): array
{
    ensure_member_data_schema();
    $groupIds = normalize_group_ids($groupIds);
    if ($groupIds === []) {
        return [];
    }

    $params = [
        'admin_id_map' => $adminId,
        'admin_id_member' => $adminId,
    ];
    $placeholders = [];
    foreach ($groupIds as $idx => $groupId) {
        $key = 'group_id_' . $idx;
        $placeholders[] = ':' . $key;
        $params[$key] = $groupId;
    }

    $sql = 'SELECT DISTINCT mgm.member_id
            FROM member_group_members mgm
            INNER JOIN members m
                ON m.id = mgm.member_id
               AND m.admin_id = :admin_id_member
               AND m.deleted_at IS NULL
            WHERE mgm.admin_id = :admin_id_map
              AND mgm.group_id IN (' . implode(',', $placeholders) . ')
            ORDER BY mgm.member_id DESC';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    $ids = [];
    foreach ($stmt->fetchAll() ?: [] as $row) {
        $id = (int) ($row['member_id'] ?? 0);
        if ($id > 0) {
            $ids[] = $id;
        }
    }
    return array_values(array_unique($ids));
}

function resolve_task_member_ids_from_payload(int $adminId, array $payload): array
{
    $memberIds = normalize_member_ids($payload['member_ids'] ?? []);
    $groupIds = normalize_group_ids($payload['group_ids'] ?? []);
    if ($groupIds !== []) {
        $memberIds = array_merge($memberIds, list_member_ids_by_group_ids($adminId, $groupIds));
    }
    return normalize_member_ids($memberIds);
}

function normalize_group_ids(mixed $value): array
{
    if (is_string($value) && $value !== '') {
        $parts = array_filter(array_map('trim', explode(',', $value)), static fn ($v) => $v !== '');
        $ids = array_map('intval', $parts);
    } elseif (is_array($value)) {
        $ids = array_map('intval', $value);
    } else {
        $ids = [];
    }

    $ids = array_values(array_unique(array_filter($ids, static fn ($id) => $id > 0)));
    return $ids;
}

function sync_task_members(int $taskId, array $memberIds): void
{
    $memberIds = normalize_member_ids($memberIds);
    $deleteStmt = db()->prepare('DELETE FROM task_members WHERE task_id = :task_id');
    $deleteStmt->execute(['task_id' => $taskId]);
    if ($memberIds === []) {
        return;
    }
    $insertStmt = db()->prepare('INSERT INTO task_members (task_id, member_id, created_at) VALUES (:task_id, :member_id, NOW())');
    foreach ($memberIds as $memberId) {
        $insertStmt->execute([
            'task_id' => $taskId,
            'member_id' => $memberId,
        ]);
    }
}

function task_member_ids(int $taskId): array
{
    $stmt = db()->prepare('SELECT member_id FROM task_members WHERE task_id = :task_id');
    $stmt->execute(['task_id' => $taskId]);
    $ids = [];
    foreach ($stmt->fetchAll() ?: [] as $row) {
        $ids[] = (int) $row['member_id'];
    }
    return $ids;
}

function list_public_tasks(): array
{
    ensure_task_data_schema();

    $stmt = db()->query(
        'SELECT t.* FROM tasks t
         JOIN admins a ON a.id = t.admin_id
         WHERE t.task_status = 1
           AND t.start_time <= NOW()
           AND a.owner_user_id IS NULL
         ORDER BY t.created_at DESC, t.id DESC'
    );

    $rows = $stmt->fetchAll() ?: [];
    foreach ($rows as &$row) {
        $blockReason = validate_task_open_for_submit($row);
        if ($blockReason === null) {
            $row['status_text'] = '可提交';
            $row['status_tone'] = 'ok';
            continue;
        }

        if ($blockReason === '任务已结束') {
            $row['status_text'] = '已失效';
            $row['status_tone'] = 'expired';
            continue;
        }

        if ($blockReason === '任务尚未开始') {
            $row['status_text'] = '未开始';
            $row['status_tone'] = 'pending';
            continue;
        }

        $row['status_text'] = '不可提交';
        $row['status_tone'] = 'off';
    }
    unset($row);

    return $rows;
}

function ensure_online_message_schema(): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    db()->exec(
        "CREATE TABLE IF NOT EXISTS online_messages (
            id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT COMMENT 'pk',
            parent_id BIGINT UNSIGNED NULL COMMENT 'parent message id',
            is_admin_reply TINYINT NOT NULL DEFAULT 0 COMMENT '1-admin reply',
            nickname VARCHAR(100) NULL COMMENT 'optional nickname',
            content TEXT NOT NULL COMMENT 'message content',
            status TINYINT NOT NULL DEFAULT 0 COMMENT '0-unread 1-read',
            client_ip VARCHAR(64) NULL COMMENT 'client ip',
            user_agent VARCHAR(500) NULL COMMENT 'user agent',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'created at',
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'updated at',
            KEY idx_status_created (status, created_at),
            KEY idx_created_at (created_at),
            KEY idx_parent_created (parent_id, created_at),
            KEY idx_parent_admin (parent_id, is_admin_reply)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='public online messages'"
    );

    if (!schema_column_exists('online_messages', 'parent_id')) {
        db()->exec('ALTER TABLE online_messages ADD COLUMN parent_id BIGINT UNSIGNED NULL AFTER id');
    }
    if (!schema_column_exists('online_messages', 'is_admin_reply')) {
        db()->exec('ALTER TABLE online_messages ADD COLUMN is_admin_reply TINYINT NOT NULL DEFAULT 0 AFTER parent_id');
    }

    if (!schema_index_exists('online_messages', 'idx_status_created')) {
        db()->exec('ALTER TABLE online_messages ADD INDEX idx_status_created (status, created_at)');
    }
    if (!schema_index_exists('online_messages', 'idx_created_at')) {
        db()->exec('ALTER TABLE online_messages ADD INDEX idx_created_at (created_at)');
    }
    if (!schema_index_exists('online_messages', 'idx_parent_created')) {
        db()->exec('ALTER TABLE online_messages ADD INDEX idx_parent_created (parent_id, created_at)');
    }
    if (!schema_index_exists('online_messages', 'idx_parent_admin')) {
        db()->exec('ALTER TABLE online_messages ADD INDEX idx_parent_admin (parent_id, is_admin_reply)');
    }
}

function ensure_app_settings_schema(): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    db()->exec(
        "CREATE TABLE IF NOT EXISTS app_settings (
            id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT COMMENT 'pk',
            setting_key VARCHAR(120) NOT NULL COMMENT 'setting key',
            setting_value TEXT NULL COMMENT 'setting value',
            updated_by BIGINT UNSIGNED NULL COMMENT 'last updater admin id',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'created at',
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'updated at',
            UNIQUE KEY uk_setting_key (setting_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='global app settings'"
    );

    if (!schema_index_exists('app_settings', 'uk_setting_key')) {
        db()->exec('ALTER TABLE app_settings ADD UNIQUE KEY uk_setting_key (setting_key)');
    }
}

function get_app_setting(string $key, ?string $defaultValue = null): ?string
{
    ensure_app_settings_schema();

    if (!isset($GLOBALS['__app_settings_cache']) || !is_array($GLOBALS['__app_settings_cache'])) {
        $GLOBALS['__app_settings_cache'] = [];
    }

    if (array_key_exists($key, $GLOBALS['__app_settings_cache'])) {
        $cached = $GLOBALS['__app_settings_cache'][$key];
        return $cached === null ? $defaultValue : (string) $cached;
    }

    $stmt = db()->prepare('SELECT setting_value FROM app_settings WHERE setting_key = :setting_key LIMIT 1');
    $stmt->execute(['setting_key' => $key]);
    $row = $stmt->fetch();
    $value = $row !== false ? (string) ($row['setting_value'] ?? '') : null;
    if ($value === '') {
        $value = null;
    }

    $GLOBALS['__app_settings_cache'][$key] = $value;
    return $value ?? $defaultValue;
}

function set_app_setting(string $key, string $value, ?int $adminId = null): void
{
    ensure_app_settings_schema();

    $stmt = db()->prepare(
        'INSERT INTO app_settings (setting_key, setting_value, updated_by, created_at, updated_at)
         VALUES (:setting_key, :setting_value, :updated_by, NOW(), NOW())
         ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value),
            updated_by = VALUES(updated_by),
            updated_at = NOW()'
    );
    $stmt->execute([
        'setting_key' => $key,
        'setting_value' => $value,
        'updated_by' => $adminId,
    ]);

    if (!isset($GLOBALS['__app_settings_cache']) || !is_array($GLOBALS['__app_settings_cache'])) {
        $GLOBALS['__app_settings_cache'] = [];
    }
    $GLOBALS['__app_settings_cache'][$key] = $value;
}

function is_checkin_image_compress_enabled(): bool
{
    $rawValue = strtolower(trim((string) get_app_setting('checkin_image_compress_enabled', '1')));
    return !in_array($rawValue, ['0', 'false', 'off', 'no'], true);
}

function set_checkin_image_compress_enabled(int $adminId, bool $enabled): void
{
    set_app_setting('checkin_image_compress_enabled', $enabled ? '1' : '0', $adminId);
}

function is_public_theme_toggle_enabled(): bool
{
    $rawValue = strtolower(trim((string) get_app_setting('public_theme_toggle_enabled', '1')));
    return !in_array($rawValue, ['0', 'false', 'off', 'no'], true);
}

function set_public_theme_toggle_enabled(int $adminId, bool $enabled): void
{
    set_app_setting('public_theme_toggle_enabled', $enabled ? '1' : '0', $adminId);
}

function get_checkin_recycle_auto_purge_days(): int
{
    $rawValue = trim((string) get_app_setting('checkin_recycle_auto_purge_days', '0'));
    $days = (int) $rawValue;
    if ($days < 0) {
        return 0;
    }
    return $days;
}

function set_checkin_recycle_auto_purge_days(int $adminId, int $days): void
{
    $days = max(0, min(3650, $days));
    set_app_setting('checkin_recycle_auto_purge_days', (string) $days, $adminId);
}

function get_site_name(): string
{
    $name = trim((string) get_app_setting('site_name', '打卡系统'));
    if ($name !== '' && preg_match('/^\?+$/', $name) === 1) {
        return '打卡系统';
    }
    return $name !== '' ? $name : '打卡系统';
}

function set_site_name(int $adminId, string $name): void
{
    $name = trim($name);
    if ($name === '') {
        $name = '打卡系统';
    }
    if (function_exists('mb_strlen') && mb_strlen($name, 'UTF-8') > 60) {
        $name = mb_substr($name, 0, 60, 'UTF-8');
    } elseif (strlen($name) > 120) {
        $name = substr($name, 0, 120);
    }
    set_app_setting('site_name', $name, $adminId);
}

function get_site_footer_text(): string
{
    $text = trim((string) get_app_setting('site_footer_text', '任务打卡系统'));
    if ($text !== '' && preg_match('/^\?+$/', $text) === 1) {
        return '任务打卡系统';
    }
    return $text !== '' ? $text : '任务打卡系统';
}

function set_site_footer_text(int $adminId, string $text): void
{
    $text = trim($text);
    if ($text === '') {
        $text = '任务打卡系统';
    }
    if (function_exists('mb_strlen') && mb_strlen($text, 'UTF-8') > 200) {
        $text = mb_substr($text, 0, 200, 'UTF-8');
    } elseif (strlen($text) > 400) {
        $text = substr($text, 0, 400);
    }
    set_app_setting('site_footer_text', $text, $adminId);
}

function get_message_board_name(): string
{
    $name = trim((string) get_app_setting('message_board_name', '留言板'));
    if ($name !== '' && preg_match('/^\?+$/', $name) === 1) {
        return '留言板';
    }
    return $name !== '' ? $name : '留言板';
}

function set_message_board_name(int $adminId, string $name): void
{
    $name = trim($name);
    if ($name === '') {
        $name = '留言板';
    }
    if (function_exists('mb_strlen') && mb_strlen($name, 'UTF-8') > 30) {
        $name = mb_substr($name, 0, 30, 'UTF-8');
    } elseif (strlen($name) > 90) {
        $name = substr($name, 0, 90);
    }
    set_app_setting('message_board_name', $name, $adminId);
}

function find_online_message_by_id(int $messageId): ?array
{
    ensure_online_message_schema();
    if ($messageId <= 0) {
        return null;
    }

    $stmt = db()->prepare('SELECT * FROM online_messages WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $messageId]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function create_online_message(array $payload): int
{
    ensure_online_message_schema();

    $nickname = trim((string) ($payload['nickname'] ?? ''));
    $content = trim((string) ($payload['content'] ?? ''));
    if ($content === '') {
        throw new InvalidArgumentException('留言内容不能为空');
    }

    if (function_exists('mb_strlen') && mb_strlen($content, 'UTF-8') > 2000) {
        throw new InvalidArgumentException('留言内容不能超过 2000 字');
    }

    if (function_exists('mb_strlen') && mb_strlen($nickname, 'UTF-8') > 100) {
        $nickname = mb_substr($nickname, 0, 100, 'UTF-8');
    } else {
        $nickname = substr($nickname, 0, 100);
    }

    $stmt = db()->prepare(
        'INSERT INTO online_messages (nickname, content, status, client_ip, user_agent, created_at, updated_at)
         VALUES (:nickname, :content, 0, :client_ip, :user_agent, NOW(), NOW())'
    );
    $stmt->execute([
        'nickname' => $nickname,
        'content' => $content,
        'client_ip' => trim((string) ($_SERVER['REMOTE_ADDR'] ?? '')),
        'user_agent' => substr(trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 500),
    ]);

    return (int) db()->lastInsertId();
}

function list_online_messages(array $filters = [], int $limit = 200): array
{
    ensure_online_message_schema();

    $limit = max(1, min(500, $limit));
    $keyword = trim((string) ($filters['keyword'] ?? ''));
    $statusRaw = (string) ($filters['status'] ?? '');
    $status = ($statusRaw === '0' || $statusRaw === '1') ? (int) $statusRaw : null;

    $where = [];
    $params = [];
    if ($keyword !== '') {
        $where[] = '(content LIKE :kw OR nickname LIKE :kw)';
        $params['kw'] = '%' . $keyword . '%';
    }
    if ($status !== null) {
        $where[] = 'status = :status';
        $params['status'] = $status;
    }

    $sql = 'SELECT * FROM online_messages';
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY id DESC LIMIT ' . $limit;

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll() ?: [];
}

function update_online_message_status(int $messageId, int $status): void
{
    ensure_online_message_schema();
    $status = $status === 1 ? 1 : 0;

    $stmt = db()->prepare(
        'UPDATE online_messages
         SET status = :status,
             updated_at = NOW()
         WHERE id = :id'
    );
    $stmt->execute([
        'status' => $status,
        'id' => $messageId,
    ]);
}

function delete_online_message(int $messageId): void
{
    ensure_online_message_schema();
    $stmt = db()->prepare('DELETE FROM online_messages WHERE id = :id');
    $stmt->execute(['id' => $messageId]);
}

function create_online_message_threaded(array $payload): int
{
    ensure_online_message_schema();

    $nickname = trim((string) ($payload['nickname'] ?? ''));
    $content = trim((string) ($payload['content'] ?? ''));
    $parentIdRaw = (int) ($payload['parent_id'] ?? 0);
    $parentId = $parentIdRaw > 0 ? $parentIdRaw : null;

    if ($parentId !== null && find_online_message_by_id($parentId) === null) {
        throw new InvalidArgumentException('回复目标不存在');
    }

    if ($content === '') {
        throw new InvalidArgumentException('留言内容不能为空');
    }
    if (function_exists('mb_strlen') && mb_strlen($content, 'UTF-8') > 2000) {
        throw new InvalidArgumentException('留言内容不能超过 2000 字');
    }

    if (function_exists('mb_strlen') && mb_strlen($nickname, 'UTF-8') > 100) {
        $nickname = mb_substr($nickname, 0, 100, 'UTF-8');
    } else {
        $nickname = substr($nickname, 0, 100);
    }

    $stmt = db()->prepare(
        'INSERT INTO online_messages (parent_id, is_admin_reply, nickname, content, status, client_ip, user_agent, created_at, updated_at)
         VALUES (:parent_id, 0, :nickname, :content, 0, :client_ip, :user_agent, NOW(), NOW())'
    );
    $stmt->execute([
        'parent_id' => $parentId,
        'nickname' => $nickname,
        'content' => $content,
        'client_ip' => trim((string) ($_SERVER['REMOTE_ADDR'] ?? '')),
        'user_agent' => substr(trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 500),
    ]);

    return (int) db()->lastInsertId();
}

function list_online_messages_threaded(array $filters = [], int $limit = 200): array
{
    ensure_online_message_schema();
    $limit = max(1, min(500, $limit));
    $keyword = trim((string) ($filters['keyword'] ?? ''));
    $statusRaw = (string) ($filters['status'] ?? '');
    $status = ($statusRaw === '0' || $statusRaw === '1') ? (int) $statusRaw : null;

    $where = ['m.parent_id IS NULL'];
    $params = [];
    if ($keyword !== '') {
        $where[] = '(m.content LIKE :kw OR m.nickname LIKE :kw)';
        $params['kw'] = '%' . $keyword . '%';
    }
    if ($status !== null) {
        $where[] = 'm.status = :status';
        $params['status'] = $status;
    }

    $sql = 'SELECT
                m.*,
                (SELECT COUNT(*) FROM online_messages r WHERE r.parent_id = m.id) AS reply_count
            FROM online_messages m
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY m.id DESC
            LIMIT ' . $limit;
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll() ?: [];
}

function list_online_message_replies_map(array $parentIds): array
{
    ensure_online_message_schema();
    $parentIds = normalize_member_ids($parentIds);
    if ($parentIds === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($parentIds), '?'));
    $stmt = db()->prepare('SELECT * FROM online_messages WHERE parent_id IN (' . $placeholders . ') ORDER BY id ASC');
    $stmt->execute($parentIds);

    $map = [];
    foreach ($stmt->fetchAll() ?: [] as $row) {
        $pid = (int) ($row['parent_id'] ?? 0);
        if ($pid <= 0) {
            continue;
        }
        if (!isset($map[$pid])) {
            $map[$pid] = [];
        }
        $map[$pid][] = $row;
    }
    return $map;
}

function list_public_message_threads(int $limit = 120): array
{
    $rows = list_online_messages_threaded([], $limit);
    if ($rows === []) {
        return [];
    }
    $parentIds = array_values(array_map(static fn (array $row): int => (int) ($row['id'] ?? 0), $rows));
    $replyMap = list_online_message_replies_map($parentIds);

    foreach ($rows as &$row) {
        $rowId = (int) ($row['id'] ?? 0);
        $row['replies'] = $replyMap[$rowId] ?? [];
    }
    unset($row);

    return $rows;
}

function create_online_message_admin_reply(int $adminId, int $parentId, string $content): int
{
    ensure_online_message_schema();
    if ($parentId <= 0 || find_online_message_by_id($parentId) === null) {
        throw new InvalidArgumentException('留言不存在');
    }

    $content = trim($content);
    if ($content === '') {
        throw new InvalidArgumentException('回复内容不能为空');
    }
    if (function_exists('mb_strlen') && mb_strlen($content, 'UTF-8') > 2000) {
        throw new InvalidArgumentException('回复内容不能超过 2000 字');
    }

    $stmt = db()->prepare(
        'INSERT INTO online_messages (parent_id, is_admin_reply, nickname, content, status, client_ip, user_agent, created_at, updated_at)
         VALUES (:parent_id, 1, :nickname, :content, 1, :client_ip, :user_agent, NOW(), NOW())'
    );
    $stmt->execute([
        'parent_id' => $parentId,
        'nickname' => '管理员',
        'content' => $content,
        'client_ip' => trim((string) ($_SERVER['REMOTE_ADDR'] ?? '')),
        'user_agent' => substr(trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 500),
    ]);

    db()->prepare('UPDATE online_messages SET status = 1, updated_at = NOW() WHERE id = :id')
        ->execute(['id' => $parentId]);

    create_operation_log($adminId, 'reply', 'online_message', $parentId, 'admin reply');
    return (int) db()->lastInsertId();
}

function delete_online_message_with_replies(int $messageId): void
{
    ensure_online_message_schema();
    $stmt = db()->prepare('DELETE FROM online_messages WHERE id = :id OR parent_id = :id');
    $stmt->execute(['id' => $messageId]);
}

function ensure_message_announcement_schema(): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    db()->exec(
        "CREATE TABLE IF NOT EXISTS message_announcements (
            id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT COMMENT 'pk',
            admin_id BIGINT UNSIGNED NOT NULL COMMENT 'creator admin id',
            title VARCHAR(180) NOT NULL COMMENT 'title',
            content_md MEDIUMTEXT NOT NULL COMMENT 'markdown content',
            is_published TINYINT NOT NULL DEFAULT 1 COMMENT '1-published',
            published_at DATETIME NULL COMMENT 'published at',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'created at',
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'updated at',
            KEY idx_published_created (is_published, created_at),
            KEY idx_admin_created (admin_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='public message announcements'"
    );

    if (!schema_index_exists('message_announcements', 'idx_published_created')) {
        db()->exec('ALTER TABLE message_announcements ADD INDEX idx_published_created (is_published, created_at)');
    }
    if (!schema_index_exists('message_announcements', 'idx_admin_created')) {
        db()->exec('ALTER TABLE message_announcements ADD INDEX idx_admin_created (admin_id, created_at)');
    }
}

function create_message_announcement(int $adminId, array $payload): int
{
    ensure_message_announcement_schema();

    $title = trim((string) ($payload['title'] ?? ''));
    $content = trim((string) ($payload['content_md'] ?? ''));
    $isPublished = parse_bool($payload['is_published'] ?? true);
    if ($title === '') {
        throw new InvalidArgumentException('公告标题不能为空');
    }
    if ($content === '') {
        throw new InvalidArgumentException('公告内容不能为空');
    }

    if (function_exists('mb_strlen') && mb_strlen($title, 'UTF-8') > 180) {
        $title = mb_substr($title, 0, 180, 'UTF-8');
    } else {
        $title = substr($title, 0, 180);
    }

    $stmt = db()->prepare(
        'INSERT INTO message_announcements (admin_id, title, content_md, is_published, published_at, created_at, updated_at)
         VALUES (:admin_id, :title, :content_md, :is_published, :published_at, NOW(), NOW())'
    );
    $stmt->execute([
        'admin_id' => $adminId,
        'title' => $title,
        'content_md' => $content,
        'is_published' => $isPublished ? 1 : 0,
        'published_at' => $isPublished ? date('Y-m-d H:i:s') : null,
    ]);

    $id = (int) db()->lastInsertId();
    create_operation_log($adminId, 'create', 'message_announcement', $id, $title);
    return $id;
}

function list_message_announcements(int $limit = 200): array
{
    ensure_message_announcement_schema();
    $limit = max(1, min(500, $limit));
    $stmt = db()->query('SELECT * FROM message_announcements ORDER BY id DESC LIMIT ' . $limit);
    return $stmt->fetchAll() ?: [];
}

function list_published_message_announcements(int $limit = 50): array
{
    ensure_message_announcement_schema();
    $limit = max(1, min(200, $limit));
    $stmt = db()->query('SELECT * FROM message_announcements WHERE is_published = 1 ORDER BY COALESCE(published_at, created_at) DESC, id DESC LIMIT ' . $limit);
    return $stmt->fetchAll() ?: [];
}

function update_message_announcement_status(int $adminId, int $announcementId, bool $isPublished): void
{
    ensure_message_announcement_schema();
    $stmt = db()->prepare(
        'UPDATE message_announcements
         SET is_published = :is_published,
             published_at = CASE WHEN :is_published = 1 THEN COALESCE(published_at, NOW()) ELSE NULL END,
             updated_at = NOW()
         WHERE id = :id'
    );
    $stmt->execute([
        'is_published' => $isPublished ? 1 : 0,
        'id' => $announcementId,
    ]);

    create_operation_log($adminId, $isPublished ? 'publish' : 'unpublish', 'message_announcement', $announcementId, '');
}

function delete_message_announcement(int $adminId, int $announcementId): void
{
    ensure_message_announcement_schema();
    $stmt = db()->prepare('DELETE FROM message_announcements WHERE id = :id');
    $stmt->execute(['id' => $announcementId]);
    create_operation_log($adminId, 'delete', 'message_announcement', $announcementId, '');
}

function markdown_inline_to_html(string $text): string
{
    $safe = h($text);
    $safe = preg_replace('/`([^`]+)`/', '<code class="rounded bg-slate-100 px-1 py-0.5 text-xs">$1</code>', (string) $safe);
    $safe = preg_replace('/\\*\\*([^*]+)\\*\\*/', '<strong>$1</strong>', (string) $safe);
    $safe = preg_replace('/\\*([^*]+)\\*/', '<em>$1</em>', (string) $safe);
    $safe = preg_replace('/\\[([^\\]]+)\\]\\((https?:\\/\\/[^\\s)]+)\\)/', '<a href="$2" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline">$1</a>', (string) $safe);
    $safe = preg_replace('/&lt;u&gt;([\\s\\S]*?)&lt;\\/u&gt;/i', '<u>$1</u>', (string) $safe);
    $safe = preg_replace_callback(
        '/&lt;span\\s+style=(?:&quot;|&#039;)color:\\s*([^&<>]+?)\\s*(?:&quot;|&#039;)&gt;([\\s\\S]*?)&lt;\\/span&gt;/i',
        static function (array $m): string {
            $color = trim((string) ($m[1] ?? ''));
            $color = preg_replace('/[^#a-zA-Z0-9(),.%\\s-]/', '', $color);
            if ($color === '') {
                $color = '#2563eb';
            }
            $content = (string) ($m[2] ?? '');
            return '<span style="color:' . h($color) . '">' . $content . '</span>';
        },
        (string) $safe
    );
    return (string) $safe;
}

function markdown_to_html(string $markdown): string
{
    $markdown = str_replace(["\r\n", "\r"], "\n", trim($markdown));
    if ($markdown === '') {
        return '<p class="text-sm text-slate-500">暂无内容</p>';
    }

    $lines = explode("\n", $markdown);
    $html = [];
    $inList = false;
    $inCode = false;
    $codeLines = [];

    foreach ($lines as $line) {
        if (preg_match('/^\s*```/', $line)) {
            if ($inCode) {
                $html[] = '<pre class="overflow-x-auto rounded-lg bg-slate-900 p-3 text-xs text-slate-100"><code>' . h(implode("\n", $codeLines)) . '</code></pre>';
                $codeLines = [];
                $inCode = false;
            } else {
                if ($inList) {
                    $html[] = '</ul>';
                    $inList = false;
                }
                $inCode = true;
            }
            continue;
        }

        if ($inCode) {
            $codeLines[] = $line;
            continue;
        }

        $trim = trim($line);
        if ($trim === '') {
            if ($inList) {
                $html[] = '</ul>';
                $inList = false;
            }
            continue;
        }

        if (preg_match('/^[-*]\s+(.+)$/', $trim, $m)) {
            if (!$inList) {
                $html[] = '<ul class="ml-5 list-disc space-y-1 text-sm text-slate-700">';
                $inList = true;
            }
            $html[] = '<li>' . markdown_inline_to_html((string) $m[1]) . '</li>';
            continue;
        }

        if ($inList) {
            $html[] = '</ul>';
            $inList = false;
        }

        if (preg_match('/^(#{1,6})\s+(.+)$/', $trim, $m)) {
            $level = min(6, max(1, strlen((string) $m[1])));
            $html[] = '<h' . $level . ' class="font-semibold text-slate-900">' . markdown_inline_to_html((string) $m[2]) . '</h' . $level . '>';
            continue;
        }

        if (preg_match('/^>\s+(.+)$/', $trim, $m)) {
            $html[] = '<blockquote class="border-l-4 border-slate-300 bg-slate-50 px-3 py-1.5 text-sm text-slate-700">' . markdown_inline_to_html((string) $m[1]) . '</blockquote>';
            continue;
        }

        if (preg_match('/^(-{3,}|\*{3,}|_{3,})$/', $trim) === 1) {
            $html[] = '<hr class="my-2 border-slate-200">';
            continue;
        }

        $html[] = '<p class="text-sm leading-6 text-slate-700">' . markdown_inline_to_html($trim) . '</p>';
    }

    if ($inCode) {
        $html[] = '<pre class="overflow-x-auto rounded-lg bg-slate-900 p-3 text-xs text-slate-100"><code>' . h(implode("\n", $codeLines)) . '</code></pre>';
    }
    if ($inList) {
        $html[] = '</ul>';
    }

    return implode("\n", $html);
}

function list_task_members_for_public(array $task): array
{
    ensure_member_data_schema();

    $taskId = (int) $task['id'];
    $adminId = (int) $task['admin_id'];

    $assignedStmt = db()->prepare('SELECT COUNT(*) FROM task_members WHERE task_id = :task_id');
    $assignedStmt->execute(['task_id' => $taskId]);
    $count = (int) $assignedStmt->fetchColumn();

    if ($count > 0) {
        $stmt = db()->prepare(
            'SELECT m.*
             FROM members m
             INNER JOIN task_members tm ON tm.member_id = m.id
             WHERE tm.task_id = :task_id
               AND m.status = 1
               AND m.deleted_at IS NULL
             ORDER BY m.id DESC'
        );
        $stmt->execute(['task_id' => $taskId]);
        return $stmt->fetchAll() ?: [];
    }

    $stmt = db()->prepare('SELECT * FROM members WHERE admin_id = :admin_id AND status = 1 AND deleted_at IS NULL ORDER BY id DESC');
    $stmt->execute(['admin_id' => $adminId]);
    return $stmt->fetchAll() ?: [];
}

function list_checkin_records(int $adminId, int $limit = 100, array $filters = []): array
{
    $sql = 'SELECT
                r.*,
                t.task_name,
                m.name AS member_name,
                m.employee_no AS member_employee_no,
                (SELECT COUNT(*) FROM checkin_images ci WHERE ci.record_id = r.id) AS image_count
            FROM checkin_records r
            INNER JOIN tasks t ON t.id = r.task_id
            INNER JOIN members m ON m.id = r.member_id
            WHERE t.admin_id = :admin_id';
    $params = [
        'admin_id' => $adminId,
    ];

    $includeDeleted = parse_bool($filters['include_deleted'] ?? false);
    if (!$includeDeleted) {
        $sql .= ' AND r.deleted_at IS NULL';
    }

    $taskId = (int) ($filters['task_id'] ?? 0);
    if ($taskId > 0) {
        $sql .= ' AND r.task_id = :task_id';
        $params['task_id'] = $taskId;
    }

    $memberId = (int) ($filters['member_id'] ?? 0);
    if ($memberId > 0) {
        $sql .= ' AND r.member_id = :member_id';
        $params['member_id'] = $memberId;
    }

    $keyword = trim((string) ($filters['keyword'] ?? ''));
    if ($keyword !== '') {
        $sql .= ' AND (
                    t.task_name LIKE :kw_task_name
                    OR m.name LIKE :kw_member_name
                    OR m.employee_no LIKE :kw_member_no
                 )';
        $kw = '%' . $keyword . '%';
        $params['kw_task_name'] = $kw;
        $params['kw_member_name'] = $kw;
        $params['kw_member_no'] = $kw;
    }

    $dateFrom = trim((string) ($filters['date_from'] ?? ''));
    if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
        $sql .= ' AND r.task_date >= :date_from';
        $params['date_from'] = $dateFrom;
    }

    $dateTo = trim((string) ($filters['date_to'] ?? ''));
    if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
        $sql .= ' AND r.task_date <= :date_to';
        $params['date_to'] = $dateTo;
    }

    $sql .= ' ORDER BY r.submit_time DESC LIMIT :limit_num';
    $stmt = db()->prepare($sql);
    foreach ($params as $key => $value) {
        if (is_int($value)) {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
    }
    $stmt->bindValue('limit_num', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll() ?: [];
}

function list_checkin_records_for_export(int $adminId, array $recordIds): array
{
    $recordIds = normalize_member_ids($recordIds);
    if ($recordIds === []) {
        return [];
    }

    $params = ['admin_id' => $adminId];
    $placeholders = [];
    foreach ($recordIds as $idx => $recordId) {
        $key = 'record_id_' . $idx;
        $placeholders[] = ':' . $key;
        $params[$key] = $recordId;
    }

    $sql = 'SELECT
                r.id,
                t.task_name,
                r.submit_time,
                m.name AS member_name,
                COUNT(ci.id) AS image_count,
                GROUP_CONCAT(ci.image_path ORDER BY ci.image_sort ASC SEPARATOR "\n") AS image_paths
            FROM checkin_records r
            INNER JOIN tasks t ON t.id = r.task_id
            INNER JOIN members m ON m.id = r.member_id
            LEFT JOIN checkin_images ci ON ci.record_id = r.id
            WHERE t.admin_id = :admin_id
              AND r.deleted_at IS NULL
              AND r.id IN (' . implode(',', $placeholders) . ')
            GROUP BY r.id, t.task_name, r.submit_time, m.name
            ORDER BY r.submit_time DESC, r.id DESC';

    $stmt = db()->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, (int) $value, PDO::PARAM_INT);
    }
    $stmt->execute();
    $rows = $stmt->fetchAll() ?: [];

    $result = [];
    foreach ($rows as $row) {
        $result[] = [
            'task_name' => (string) ($row['task_name'] ?? ''),
            'submit_time' => (string) ($row['submit_time'] ?? ''),
            'member_name' => (string) ($row['member_name'] ?? ''),
            'image_count' => (int) ($row['image_count'] ?? 0),
            'image_content' => normalize_export_image_content((string) ($row['image_paths'] ?? '')),
        ];
    }

    return $result;
}

function normalize_export_image_content(string $rawPaths): string
{
    $rawPaths = trim($rawPaths);
    if ($rawPaths === '') {
        return '';
    }

    $paths = array_values(array_filter(array_map('trim', explode("\n", $rawPaths)), static fn (string $v): bool => $v !== ''));
    if ($paths === []) {
        return '';
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));

    $lines = [];
    foreach ($paths as $path) {
        if ($host !== '' && str_starts_with($path, '/')) {
            $lines[] = $scheme . '://' . $host . $path;
        } else {
            $lines[] = $path;
        }
    }
    return implode("\n", array_values(array_unique($lines)));
}

function build_checkin_records_xlsx(array $rows): string
{
    $headers = ['任务名称', '打卡时间', '人员', '图片数量', '图片内容'];
    $sheetRows = [];
    foreach ($rows as $row) {
        $sheetRows[] = [
            (string) ($row['task_name'] ?? ''),
            (string) ($row['submit_time'] ?? ''),
            (string) ($row['member_name'] ?? ''),
            (string) ((int) ($row['image_count'] ?? 0)),
            (string) ($row['image_content'] ?? ''),
        ];
    }
    return build_simple_xlsx_binary($headers, $sheetRows);
}

function build_simple_xlsx_binary(array $headers, array $rows): string
{
    if (!class_exists('ZipArchive')) {
        throw new InvalidArgumentException('服务器未启用 ZipArchive 扩展，无法导出 XLSX');
    }

    $worksheetXml = build_simple_xlsx_sheet_xml($headers, $rows);
    $workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
        . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="打卡记录" sheetId="1" r:id="rId1"/></sheets>'
        . '</workbook>';
    $workbookRelsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '</Relationships>';
    $rootRelsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>';
    $contentTypesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '</Types>';

    $tmpFile = tempnam(sys_get_temp_dir(), 'xlsx_export_');
    if ($tmpFile === false) {
        throw new RuntimeException('创建导出临时文件失败');
    }

    $zip = new ZipArchive();
    $opened = $zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    if ($opened !== true) {
        @unlink($tmpFile);
        throw new RuntimeException('创建导出文件失败');
    }

    $zip->addFromString('[Content_Types].xml', $contentTypesXml);
    $zip->addFromString('_rels/.rels', $rootRelsXml);
    $zip->addFromString('xl/workbook.xml', $workbookXml);
    $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRelsXml);
    $zip->addFromString('xl/worksheets/sheet1.xml', $worksheetXml);
    $zip->close();

    $binary = file_get_contents($tmpFile);
    @unlink($tmpFile);
    if ($binary === false) {
        throw new RuntimeException('读取导出文件失败');
    }
    return $binary;
}

function build_simple_xlsx_sheet_xml(array $headers, array $rows): string
{
    $allRows = array_merge([$headers], $rows);
    $maxColumns = max(1, count($headers));
    foreach ($rows as $row) {
        if (is_array($row)) {
            $maxColumns = max($maxColumns, count($row));
        }
    }
    $rowCount = max(1, count($allRows));
    $dimensionRef = 'A1:' . xlsx_column_name($maxColumns) . $rowCount;

    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<dimension ref="' . $dimensionRef . '"/>'
        . '<sheetData>';

    foreach ($allRows as $rowIndex => $row) {
        $xml .= '<row r="' . ($rowIndex + 1) . '">';
        for ($colIndex = 0; $colIndex < $maxColumns; $colIndex++) {
            $cellRef = xlsx_column_name($colIndex + 1) . ($rowIndex + 1);
            $rawValue = is_array($row) ? (string) ($row[$colIndex] ?? '') : '';
            $value = escape_xlsx_cell_text($rawValue);
            $xml .= '<c r="' . $cellRef . '" t="inlineStr"><is><t>' . $value . '</t></is></c>';
        }
        $xml .= '</row>';
    }

    $xml .= '</sheetData></worksheet>';
    return $xml;
}

function escape_xlsx_cell_text(string $value): string
{
    if ($value !== '' && in_array($value[0], ['=', '+', '-', '@'], true)) {
        $value = "'" . $value;
    }
    $value = str_replace(["\r\n", "\r"], "\n", $value);
    $escaped = htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    return str_replace("\n", '&#10;', $escaped);
}

function xlsx_column_name(int $index): string
{
    $index = max(1, $index);
    $name = '';
    while ($index > 0) {
        $mod = ($index - 1) % 26;
        $name = chr(65 + $mod) . $name;
        $index = (int) floor(($index - 1) / 26);
    }
    return $name;
}

function count_deleted_checkin_records(int $adminId): int
{
    $stmt = db()->prepare(
        'SELECT COUNT(*)
         FROM checkin_records r
         INNER JOIN tasks t ON t.id = r.task_id
         WHERE t.admin_id = :admin_id
           AND r.deleted_at IS NOT NULL'
    );
    $stmt->execute(['admin_id' => $adminId]);
    return (int) $stmt->fetchColumn();
}

function list_deleted_checkin_records(int $adminId, int $limit = 200): array
{
    $limit = max(1, min(500, $limit));

    $sql = 'SELECT
                r.*,
                t.task_name,
                m.name AS member_name,
                m.employee_no AS member_employee_no,
                (SELECT COUNT(*) FROM checkin_images ci WHERE ci.record_id = r.id) AS image_count
            FROM checkin_records r
            INNER JOIN tasks t ON t.id = r.task_id
            INNER JOIN members m ON m.id = r.member_id
            WHERE t.admin_id = :admin_id
              AND r.deleted_at IS NOT NULL
            ORDER BY r.deleted_at DESC, r.id DESC
            LIMIT :limit_num';

    $stmt = db()->prepare($sql);
    $stmt->bindValue('admin_id', $adminId, PDO::PARAM_INT);
    $stmt->bindValue('limit_num', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll() ?: [];
}

function find_checkin_record_by_id(int $adminId, int $recordId, bool $includeDeleted = true): ?array
{
    $sql = 'SELECT
                r.*,
                t.task_name,
                t.task_link_code,
                m.name AS member_name,
                m.employee_no AS member_employee_no,
                (SELECT COUNT(*) FROM checkin_images ci WHERE ci.record_id = r.id) AS image_count
            FROM checkin_records r
            INNER JOIN tasks t ON t.id = r.task_id
            INNER JOIN members m ON m.id = r.member_id
            WHERE t.admin_id = :admin_id
              AND r.id = :id';
    if (!$includeDeleted) {
        $sql .= ' AND r.deleted_at IS NULL';
    }
    $sql .= ' LIMIT 1';

    $stmt = db()->prepare($sql);
    $stmt->execute([
        'admin_id' => $adminId,
        'id' => $recordId,
    ]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function list_checkin_images(int $recordId): array
{
    $stmt = db()->prepare('SELECT * FROM checkin_images WHERE record_id = :record_id ORDER BY image_sort ASC, id ASC');
    $stmt->execute(['record_id' => $recordId]);
    return $stmt->fetchAll() ?: [];
}

function list_checkin_record_images(int $recordId): array
{
    // Backward-compatible alias for historical route calls.
    return list_checkin_images($recordId);
}

function soft_delete_checkin_record(int $adminId, int $recordId): void
{
    $record = find_checkin_record_by_id($adminId, $recordId, true);
    if ($record === null) {
        throw new InvalidArgumentException('');
    }
    if (!empty($record['deleted_at'])) {
        return;
    }

    $stmt = db()->prepare(
        'UPDATE checkin_records
         SET deleted_at = NOW(),
             is_valid = 0,
             updated_at = NOW()
         WHERE id = :id'
    );
    $stmt->execute(['id' => $recordId]);

    create_operation_log($adminId, 'soft_delete', 'checkin_record', $recordId, '');
}

function batch_soft_delete_checkin_records(int $adminId, array $recordIds): int
{
    $recordIds = normalize_member_ids($recordIds);
    if ($recordIds === []) {
        return 0;
    }

    $params = ['admin_id' => $adminId];
    $placeholders = [];
    foreach ($recordIds as $idx => $recordId) {
        $key = 'record_id_' . $idx;
        $placeholders[] = ':' . $key;
        $params[$key] = $recordId;
    }

    $sql = 'UPDATE checkin_records r
            INNER JOIN tasks t ON t.id = r.task_id
            SET r.deleted_at = NOW(),
                r.is_valid = 0,
                r.updated_at = NOW()
            WHERE t.admin_id = :admin_id
              AND r.deleted_at IS NULL
              AND r.id IN (' . implode(',', $placeholders) . ')';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $affected = (int) $stmt->rowCount();

    if ($affected > 0) {
        create_operation_log($adminId, 'batch_soft_delete', 'checkin_record', 0, (string) $affected);
    }

    return $affected;
}

function restore_checkin_record(int $adminId, int $recordId): void
{
    $record = find_checkin_record_by_id($adminId, $recordId, true);
    if ($record === null) {
        throw new InvalidArgumentException('');
    }
    if (empty($record['deleted_at'])) {
        return;
    }

    $nextValid = (int) $record['is_overridden'] === 1 ? 0 : 1;
    $stmt = db()->prepare(
        'UPDATE checkin_records
         SET deleted_at = NULL,
             is_valid = :is_valid,
             updated_at = NOW()
         WHERE id = :id'
    );
    $stmt->execute([
        'id' => $recordId,
        'is_valid' => $nextValid,
    ]);

    create_operation_log($adminId, 'restore', 'checkin_record', $recordId, '');
}

function delete_checkin_record_permanently(int $adminId, int $recordId): array
{
    $record = find_checkin_record_by_id($adminId, $recordId, true);
    if ($record === null) {
        throw new InvalidArgumentException('');
    }
    if (empty($record['deleted_at'])) {
        throw new InvalidArgumentException('');
    }

    $images = list_checkin_images($recordId);

    db()->beginTransaction();
    try {
        $deleteImagesStmt = db()->prepare('DELETE FROM checkin_images WHERE record_id = :record_id');
        $deleteImagesStmt->execute(['record_id' => $recordId]);

        $deleteRecordStmt = db()->prepare('DELETE FROM checkin_records WHERE id = :id');
        $deleteRecordStmt->execute(['id' => $recordId]);

        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }

    $deletedFileCount = 0;
    foreach ($images as $image) {
        $imagePath = trim((string) ($image['image_path'] ?? ''));
        if ($imagePath === '' || !str_starts_with($imagePath, '/storage/uploads/')) {
            continue;
        }
        $fullPath = APP_ROOT . $imagePath;
        if (is_file($fullPath) && @unlink($fullPath)) {
            $deletedFileCount++;
        }
    }

    create_operation_log($adminId, 'hard_delete', 'checkin_record', $recordId, '');

    return [
        'record_id' => $recordId,
        'image_count' => count($images),
        'deleted_file_count' => $deletedFileCount,
    ];
}

function purge_soft_deleted_checkin_records(int $adminId, int $days, int $limit = 300): int
{
    $days = max(0, $days);
    if ($days <= 0) {
        return 0;
    }

    $limit = max(1, min(1000, $limit));
    $cutoff = date('Y-m-d H:i:s', time() - ($days * 86400));

    $stmt = db()->prepare(
        'SELECT r.id
         FROM checkin_records r
         INNER JOIN tasks t ON t.id = r.task_id
         WHERE t.admin_id = :admin_id
           AND r.deleted_at IS NOT NULL
           AND r.deleted_at <= :cutoff
         ORDER BY r.deleted_at ASC, r.id ASC
         LIMIT :limit_num'
    );
    $stmt->bindValue('admin_id', $adminId, PDO::PARAM_INT);
    $stmt->bindValue('cutoff', $cutoff, PDO::PARAM_STR);
    $stmt->bindValue('limit_num', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll() ?: [];

    $deletedCount = 0;
    foreach ($rows as $row) {
        $recordId = (int) ($row['id'] ?? 0);
        if ($recordId <= 0) {
            continue;
        }
        try {
            delete_checkin_record_permanently($adminId, $recordId);
            $deletedCount++;
        } catch (Throwable $e) {
            continue;
        }
    }

    return $deletedCount;
}

function create_checkin_record_by_admin(int $adminId, array $payload, array $files): int
{
    $taskId = (int) ($payload['task_id'] ?? 0);
    $memberId = (int) ($payload['member_id'] ?? 0);
    if ($taskId <= 0 || $memberId <= 0) {
        throw new InvalidArgumentException('');
    }

    $task = find_task_by_id($adminId, $taskId);
    if ($task === null) {
        throw new InvalidArgumentException('');
    }

    $member = find_member_for_task($task, $memberId);
    if ($member === null) {
        throw new InvalidArgumentException('');
    }

    $taskDate = normalize_admin_task_date($task, (string) ($payload['task_date'] ?? ''));
    $submitTime = to_mysql_datetime((string) ($payload['submit_time'] ?? ''));
    if ($submitTime === null) {
        $submitTime = date('Y-m-d H:i:s');
    }

    $images = normalize_uploaded_images($files['images'] ?? null);
    validate_images_rule($task, $images);

    $forceOverride = parse_bool($payload['force_override'] ?? false);
    $existing = find_existing_record($taskId, $memberId, $taskDate);
    if ($existing !== null) {
        if ((int) $task['allow_override'] !== 1) {
            throw new InvalidArgumentException('');
        }
        if (!$forceOverride) {
            throw new InvalidArgumentException('');
        }
    }

    $remark = trim((string) ($payload['remark'] ?? ''));
    $clientIp = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $userAgent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);

    // Read setting before transaction to avoid implicit commit from lazy schema creation.
    $compressEnabled = is_checkin_image_compress_enabled();

    db()->beginTransaction();
    $uploadedPaths = [];
    try {
        $insert = db()->prepare(
            'INSERT INTO checkin_records (
                task_id, cycle_id, member_id, task_date, submit_time,
                is_valid, is_overridden, overridden_by_record_id,
                submit_source, client_ip, user_agent, remark, deleted_at, created_at, updated_at
             ) VALUES (
                :task_id, NULL, :member_id, :task_date, :submit_time,
                1, 0, NULL,
                :submit_source, :client_ip, :user_agent, :remark, NULL, NOW(), NOW()
             )'
        );
        $insert->execute([
            'task_id' => $taskId,
            'member_id' => $memberId,
            'task_date' => $taskDate,
            'submit_time' => $submitTime,
            'submit_source' => 'admin_web',
            'client_ip' => $clientIp,
            'user_agent' => $userAgent,
            'remark' => $remark,
        ]);
        $recordId = (int) db()->lastInsertId();

        if ($existing !== null) {
            $overrideOld = db()->prepare(
                'UPDATE checkin_records
                 SET is_valid = 0, is_overridden = 1, overridden_by_record_id = :new_id, updated_at = NOW()
                 WHERE id = :old_id'
            );
            $overrideOld->execute([
                'new_id' => $recordId,
                'old_id' => (int) $existing['id'],
            ]);
        }

        $insertImage = db()->prepare(
            'INSERT INTO checkin_images
            (record_id, image_path, image_thumb_path, image_sort, file_size, mime_type, created_at)
             VALUES (:record_id, :image_path, NULL, :image_sort, :file_size, :mime_type, NOW())'
        );
        foreach ($images as $index => $image) {
            $stored = store_uploaded_image($image, $recordId, $index + 1, $compressEnabled);
            $uploadedPaths[] = APP_ROOT . $stored['path'];
            $insertImage->execute([
                'record_id' => $recordId,
                'image_path' => $stored['path'],
                'image_sort' => $index + 1,
                'file_size' => $stored['size'],
                'mime_type' => $stored['mime'],
            ]);
        }

        db()->commit();
    } catch (Throwable $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        foreach ($uploadedPaths as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
        throw $e;
    }

    create_operation_log($adminId, 'manual_create', 'checkin_record', $recordId, '');
    return $recordId;
}

function update_checkin_record_basic(int $adminId, int $recordId, array $payload): void
{
    $record = find_checkin_record_by_id($adminId, $recordId, true);
    if ($record === null) {
        throw new InvalidArgumentException('');
    }

    $task = find_task_by_id($adminId, (int) $record['task_id']);
    if ($task === null) {
        throw new InvalidArgumentException('');
    }

    $taskDateInput = (string) ($payload['task_date'] ?? (string) $record['task_date']);
    $taskDate = normalize_admin_task_date($task, $taskDateInput);
    $remark = trim((string) ($payload['remark'] ?? (string) ($record['remark'] ?? '')));
    $isValid = parse_bool($payload['is_valid'] ?? ((int) $record['is_valid'] === 1 ? '1' : '0')) ? 1 : 0;

    if (!empty($record['deleted_at']) && $isValid === 1) {
        throw new InvalidArgumentException('');
    }

    $stmt = db()->prepare(
        'UPDATE checkin_records
         SET task_date = :task_date,
             remark = :remark,
             is_valid = :is_valid,
             updated_at = NOW()
         WHERE id = :id'
    );
    $stmt->execute([
        'id' => $recordId,
        'task_date' => $taskDate,
        'remark' => $remark,
        'is_valid' => $isValid,
    ]);

    create_operation_log($adminId, 'update', 'checkin_record', $recordId, '');
}

function normalize_admin_task_date(array $task, string $inputDate): string
{
    $taskDate = trim($inputDate);
    if ($taskDate === '') {
        $taskDate = date('Y-m-d');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $taskDate)) {
        throw new InvalidArgumentException('');
    }

    $startDate = date('Y-m-d', strtotime((string) $task['start_time']));
    if ($taskDate < $startDate) {
        throw new InvalidArgumentException('');
    }

    if ((int) $task['is_long_term'] !== 1 && !empty($task['end_time'])) {
        $endDate = date('Y-m-d', strtotime((string) $task['end_time']));
        if ($taskDate > $endDate) {
            throw new InvalidArgumentException('');
        }
    }

    return $taskDate;
}

function submit_checkin_by_link(string $taskLinkCode, array $payload, array $files, bool $forceOverride): array
{
    $taskLinkCode = trim($taskLinkCode);
    if ($taskLinkCode === '') {
        return ['ok' => false, 'error' => '', 'code' => 'invalid_task'];
    }

    $task = find_task_by_link_code($taskLinkCode, true);
    if ($task === null) {
        return ['ok' => false, 'error' => '', 'code' => 'task_not_found'];
    }

    $taskCheck = validate_task_open_for_submit($task);
    if ($taskCheck !== null) {
        return ['ok' => false, 'error' => $taskCheck, 'code' => 'task_closed'];
    }

    $memberId = (int) ($payload['member_id'] ?? 0);
    if ($memberId <= 0) {
        return ['ok' => false, 'error' => '', 'code' => 'invalid_member'];
    }

    $member = find_member_for_task($task, $memberId);
    if ($member === null) {
        return ['ok' => false, 'error' => '', 'code' => 'member_not_allowed'];
    }

    try {
        $taskDate = normalize_task_date($task, (string) ($payload['task_date'] ?? ''));
    } catch (InvalidArgumentException $e) {
        return ['ok' => false, 'error' => $e->getMessage(), 'code' => 'invalid_task_date'];
    }

    try {
        $images = normalize_uploaded_images($files['images'] ?? null);
        validate_images_rule($task, $images);
    } catch (InvalidArgumentException $e) {
        return ['ok' => false, 'error' => $e->getMessage(), 'code' => 'invalid_images'];
    }

    $existing = find_existing_record((int) $task['id'], $memberId, $taskDate);
    if ($existing !== null) {
        if ((int) $task['allow_override'] !== 1) {
            return ['ok' => false, 'error' => '', 'code' => 'override_forbidden'];
        }
        $duplicateAction = (string) $task['duplicate_action'];
        if (!$forceOverride && $duplicateAction === 'reject') {
            return ['ok' => false, 'error' => '', 'code' => 'duplicate_reject'];
        }
        if (!$forceOverride && $duplicateAction === 'prompt') {
            return [
                'ok' => false,
                'error' => '', 'code' => 'duplicate_prompt',
                'duplicate' => summarize_record($existing),
            ];
        }
    }

    $clientIp = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $userAgent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
    $submitSource = detect_submit_source($userAgent);

    // Read setting before transaction to avoid implicit commit from lazy schema creation.
    $compressEnabled = is_checkin_image_compress_enabled();

    db()->beginTransaction();
    $uploadedPaths = [];
    try {
        $insert = db()->prepare(
            'INSERT INTO checkin_records (
                task_id, cycle_id, member_id, task_date, submit_time,
                is_valid, is_overridden, overridden_by_record_id,
                submit_source, client_ip, user_agent, remark, deleted_at, created_at, updated_at
             ) VALUES (
                :task_id, NULL, :member_id, :task_date, NOW(),
                1, 0, NULL,
                :submit_source, :client_ip, :user_agent, :remark, NULL, NOW(), NOW()
             )'
        );
        $insert->execute([
            'task_id' => (int) $task['id'],
            'member_id' => $memberId,
            'task_date' => $taskDate,
            'submit_source' => $submitSource,
            'client_ip' => $clientIp,
            'user_agent' => $userAgent,
            'remark' => trim((string) ($payload['remark'] ?? '')),
        ]);
        $recordId = (int) db()->lastInsertId();

        if ($existing !== null) {
            $updateOld = db()->prepare(
                'UPDATE checkin_records
                 SET is_valid = 0, is_overridden = 1, overridden_by_record_id = :new_id, updated_at = NOW()
                 WHERE id = :old_id'
            );
            $updateOld->execute([
                'new_id' => $recordId,
                'old_id' => (int) $existing['id'],
            ]);
        }

        $insertImage = db()->prepare(
            'INSERT INTO checkin_images
            (record_id, image_path, image_thumb_path, image_sort, file_size, mime_type, created_at)
             VALUES (:record_id, :image_path, NULL, :image_sort, :file_size, :mime_type, NOW())'
        );
        foreach ($images as $index => $image) {
            $stored = store_uploaded_image($image, $recordId, $index + 1, $compressEnabled);
            $uploadedPaths[] = APP_ROOT . $stored['path'];
            $insertImage->execute([
                'record_id' => $recordId,
                'image_path' => $stored['path'],
                'image_sort' => $index + 1,
                'file_size' => $stored['size'],
                'mime_type' => $stored['mime'],
            ]);
        }

        db()->commit();
    } catch (Throwable $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        foreach ($uploadedPaths as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
        throw $e;
    }

    return [
        'ok' => true,
        'record_id' => $recordId,
        'task' => [
            'id' => (int) $task['id'],
            'task_name' => (string) $task['task_name'],
            'task_link_code' => (string) $task['task_link_code'],
        ],
        'member' => [
            'id' => (int) $member['id'],
            'name' => (string) $member['name'],
        ],
        'task_date' => $taskDate,
        'submit_time' => date('Y-m-d H:i:s'),
        'image_count' => count($images),
        'checkin_count' => (function () use ($task, $memberId): int {
            $stmt = db()->prepare(
                'SELECT COUNT(*)
                 FROM checkin_records
                 WHERE task_id = :task_id
                   AND member_id = :member_id
                   AND deleted_at IS NULL'
            );
            $stmt->execute([
                'task_id' => (int) $task['id'],
                'member_id' => $memberId,
            ]);
            return (int) $stmt->fetchColumn();
        })(),
        'overrode' => $existing !== null,
    ];
}

function validate_task_open_for_submit(array $task): ?string
{
    ensure_task_data_schema();

    if ((int) $task['task_status'] !== 1) {
        return '任务已停用';
    }

    $now = time();
    $start = strtotime((string) $task['start_time']);
    if ($start !== false && $now < $start) {
        return '任务尚未开始';
    }

    if ((int) $task['is_long_term'] !== 1 && !empty($task['end_time'])) {
        $end = strtotime((string) $task['end_time']);
        if ($end !== false && $now > $end) {
            return '任务已结束';
        }
    }

    return null;
}
function find_member_for_task(array $task, int $memberId): ?array
{
    ensure_member_data_schema();

    $adminId = (int) $task['admin_id'];
    $stmt = db()->prepare('SELECT * FROM members WHERE id = :id AND admin_id = :admin_id AND status = 1 AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([
        'id' => $memberId,
        'admin_id' => $adminId,
    ]);
    $member = $stmt->fetch();
    if (!$member) {
        return null;
    }

    $countStmt = db()->prepare('SELECT COUNT(*) FROM task_members WHERE task_id = :task_id');
    $countStmt->execute(['task_id' => (int) $task['id']]);
    $assignedCount = (int) $countStmt->fetchColumn();

    if ($assignedCount === 0) {
        return $member;
    }

    $assignStmt = db()->prepare('SELECT 1 FROM task_members WHERE task_id = :task_id AND member_id = :member_id LIMIT 1');
    $assignStmt->execute([
        'task_id' => (int) $task['id'],
        'member_id' => $memberId,
    ]);
    $allowed = (bool) $assignStmt->fetchColumn();
    return $allowed ? $member : null;
}

function normalize_task_date(array $task, string $inputDate): string
{
    $today = date('Y-m-d');
    if ((int) $task['allow_select_task_date'] !== 1) {
        return $today;
    }

    $taskDate = trim($inputDate) !== '' ? trim($inputDate) : $today;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $taskDate)) {
        throw new InvalidArgumentException('');
    }

    $mode = (string) $task['task_date_mode'];
    if ($mode === 'today_only' && $taskDate !== $today) {
        throw new InvalidArgumentException('');
    }

    $startDate = date('Y-m-d', strtotime((string) $task['start_time']));
    if ($taskDate < $startDate) {
        throw new InvalidArgumentException('');
    }

    if ((int) $task['is_long_term'] === 1) {
        if ($taskDate > $today) {
            throw new InvalidArgumentException('');
        }
    } else {
        $endRaw = (string) ($task['end_time'] ?? '');
        if ($endRaw !== '') {
            $endDate = date('Y-m-d', strtotime($endRaw));
            if ($taskDate > $endDate) {
                throw new InvalidArgumentException('');
            }
        }
    }

    return $taskDate;
}

function normalize_uploaded_images(mixed $imagesField): array
{
    if (!is_array($imagesField) || !isset($imagesField['name'])) {
        return [];
    }

    $normalized = [];
    if (is_array($imagesField['name'])) {
        $count = count($imagesField['name']);
        for ($i = 0; $i < $count; $i++) {
            $normalized[] = [
                'name' => $imagesField['name'][$i] ?? '',
                'type' => $imagesField['type'][$i] ?? '',
                'tmp_name' => $imagesField['tmp_name'][$i] ?? '',
                'error' => $imagesField['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size' => (int) ($imagesField['size'][$i] ?? 0),
            ];
        }
    } else {
        $normalized[] = [
            'name' => $imagesField['name'] ?? '',
            'type' => $imagesField['type'] ?? '',
            'tmp_name' => $imagesField['tmp_name'] ?? '',
            'error' => $imagesField['error'] ?? UPLOAD_ERR_NO_FILE,
            'size' => (int) ($imagesField['size'] ?? 0),
        ];
    }

    return array_values(array_filter($normalized, static fn (array $item) => ($item['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE));
}

function checkin_single_image_max_bytes(): int
{
    $raw = trim((string) env('CHECKIN_MAX_IMAGE_MB', '5'));
    $mb = (int) $raw;
    if ($mb <= 0) {
        $mb = 5;
    }
    $mb = max(1, min(20, $mb));
    return $mb * 1024 * 1024;
}

function validate_images_rule(array $task, array $images): void
{
    $count = count($images);
    $min = (int) $task['min_images'];
    $max = (int) $task['max_images'];
    if ($count < $min || $count > $max) {
        throw new InvalidArgumentException("图片数量需在 {$min} 到 {$max} 张之间");
    }

    $maxSingleBytes = checkin_single_image_max_bytes();
    $maxSingleMb = (int) max(1, floor($maxSingleBytes / (1024 * 1024)));
    foreach ($images as $image) {
        $error = (int) ($image['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('图片上传失败，请重新选择后再试');
        }
        $tmpName = (string) ($image['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new InvalidArgumentException('上传图片临时文件不存在，请重新上传');
        }
        if ((int) ($image['size'] ?? 0) <= 0) {
            throw new InvalidArgumentException('图片文件无效，请重新选择');
        }
        if ((int) ($image['size'] ?? 0) > $maxSingleBytes) {
            throw new InvalidArgumentException('单张图片不能超过 ' . $maxSingleMb . 'MB');
        }
    }
}

function store_uploaded_image(array $image, int $recordId, int $sort, ?bool $compressEnabled = null): array
{
    $tmpName = (string) $image['tmp_name'];
    $mime = detect_image_mime($image);

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
    if (!isset($allowed[$mime])) {
        throw new InvalidArgumentException('仅支持 JPG / PNG / GIF / WEBP 图片格式');
    }

    $datePath = date('Ymd');
    $dir = APP_ROOT . '/storage/uploads/checkins/' . $datePath;
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $ext = $allowed[$mime];
    $filename = sprintf('%d_%d_%s.%s', $recordId, $sort, bin2hex(random_bytes(5)), $ext);
    $fullPath = $dir . '/' . $filename;
    $relativePath = '/storage/uploads/checkins/' . $datePath . '/' . $filename;

    if (!move_uploaded_file($tmpName, $fullPath)) {
        throw new RuntimeException('图片保存失败，请稍后重试');
    }

    $finalMime = $mime;
    $finalSize = is_file($fullPath) ? (int) filesize($fullPath) : (int) ($image['size'] ?? 0);

    if ($compressEnabled === null) {
        $compressEnabled = is_checkin_image_compress_enabled();
    }

    if ($compressEnabled) {
        $compressed = compress_checkin_image_file($fullPath, $mime, 720, 100 * 1024);
        if (is_array($compressed)) {
            $finalMime = (string) ($compressed['mime'] ?? $finalMime);
            $finalSize = (int) ($compressed['size'] ?? $finalSize);
        }
    }

    return [
        'path' => $relativePath,
        'mime' => $finalMime,
        'size' => $finalSize,
    ];
}

function compress_checkin_image_file(string $fullPath, string $mime, int $maxHeightPx, int $maxBytes): ?array
{
    $mime = strtolower(trim($mime));
    if ($fullPath === '' || !is_file($fullPath)) {
        return null;
    }

    $gdReady = function_exists('getimagesize')
        && function_exists('imagecreatetruecolor')
        && function_exists('imagecopyresampled');
    if (!$gdReady) {
        return compress_checkin_image_file_with_imagick($fullPath, $maxHeightPx, $maxBytes);
    }

    $info = @getimagesize($fullPath);
    if (!is_array($info)) {
        return null;
    }

    $srcWidth = (int) ($info[0] ?? 0);
    $srcHeight = (int) ($info[1] ?? 0);
    if ($srcWidth <= 0 || $srcHeight <= 0) {
        return null;
    }

    $source = create_gd_image_from_file($fullPath, $mime);
    if (!is_valid_gd_image($source)) {
        return compress_checkin_image_file_with_imagick($fullPath, $maxHeightPx, $maxBytes);
    }

    $targetHeight = $srcHeight > $maxHeightPx ? $maxHeightPx : $srcHeight;
    $targetWidth = max(1, (int) round($srcWidth * ($targetHeight / $srcHeight)));
    $currentWidth = $targetWidth;
    $currentHeight = $targetHeight;

    $bestTempPath = '';
    $bestSize = PHP_INT_MAX;
    $maxAttempts = 18;

    try {
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $canvas = @imagecreatetruecolor($currentWidth, $currentHeight);
            if ($canvas === false) {
                break;
            }

            prepare_canvas_alpha($canvas, $mime);
            @imagecopyresampled(
                $canvas,
                $source,
                0,
                0,
                0,
                0,
                $currentWidth,
                $currentHeight,
                $srcWidth,
                $srcHeight
            );

            $tempPath = $fullPath . '.cmp.' . str_replace('.', '', uniqid('', true));
            $saved = save_gd_image_to_file($canvas, $tempPath, $mime, $attempt);
            destroy_gd_image($canvas);

            if (!$saved || !is_file($tempPath)) {
                @unlink($tempPath);
                break;
            }

            $tempSize = (int) filesize($tempPath);
            if ($tempSize < $bestSize) {
                if ($bestTempPath !== '' && is_file($bestTempPath)) {
                    @unlink($bestTempPath);
                }
                $bestTempPath = $tempPath;
                $bestSize = $tempSize;
            } else {
                @unlink($tempPath);
            }

            if ($tempSize <= $maxBytes) {
                break;
            }

            $currentWidth = max(1, (int) floor($currentWidth * 0.82));
            $currentHeight = max(1, (int) floor($currentHeight * 0.82));
        }

        if ($bestTempPath === '' || !is_file($bestTempPath)) {
            return null;
        }

        if (!@rename($bestTempPath, $fullPath)) {
            if (!@copy($bestTempPath, $fullPath)) {
                @unlink($bestTempPath);
                return null;
            }
            @unlink($bestTempPath);
        }

        return [
            'mime' => $mime,
            'size' => is_file($fullPath) ? (int) filesize($fullPath) : 0,
        ];
    } finally {
        if ($bestTempPath !== '' && is_file($bestTempPath)) {
            @unlink($bestTempPath);
        }
        destroy_gd_image($source);
    }
}

function is_valid_gd_image(mixed $image): bool
{
    if (is_resource($image)) {
        return true;
    }

    return is_object($image) && get_class($image) === 'GdImage';
}

function compress_checkin_image_file_with_imagick(string $fullPath, int $maxHeightPx, int $maxBytes): ?array
{
    if (!class_exists('Imagick')) {
        return null;
    }

    try {
        $origin = new Imagick($fullPath);
        if ($origin->getNumberImages() > 1) {
            $origin = $origin->coalesceImages();
        }

        $srcWidth = (int) $origin->getImageWidth();
        $srcHeight = (int) $origin->getImageHeight();
        if ($srcWidth <= 0 || $srcHeight <= 0) {
            $origin->clear();
            $origin->destroy();
            return null;
        }

        $baseHeight = $srcHeight > $maxHeightPx ? $maxHeightPx : $srcHeight;
        $baseWidth = max(1, (int) round($srcWidth * ($baseHeight / $srcHeight)));

        $bestTempPath = '';
        $bestSize = PHP_INT_MAX;
        $bestMime = '';

        for ($attempt = 0; $attempt < 18; $attempt++) {
            $scale = pow(0.82, $attempt);
            $targetWidth = max(1, (int) floor($baseWidth * $scale));
            $targetHeight = max(1, (int) floor($baseHeight * $scale));

            $work = clone $origin;
            if ($targetWidth !== $srcWidth || $targetHeight !== $srcHeight) {
                $work->resizeImage($targetWidth, $targetHeight, Imagick::FILTER_LANCZOS, 1);
            }

            $quality = max(20, 82 - ($attempt * 8));
            $work->setImageCompressionQuality($quality);
            if (defined('Imagick::COMPRESSION_JPEG')) {
                $work->setImageCompression(Imagick::COMPRESSION_JPEG);
            }
            $work->stripImage();

            $tempPath = $fullPath . '.cmp.' . str_replace('.', '', uniqid('', true));
            $work->writeImage($tempPath);
            $tempSize = is_file($tempPath) ? (int) filesize($tempPath) : PHP_INT_MAX;

            if ($tempSize < $bestSize) {
                if ($bestTempPath !== '' && is_file($bestTempPath)) {
                    @unlink($bestTempPath);
                }
                $bestTempPath = $tempPath;
                $bestSize = $tempSize;
                $bestMime = strtolower((string) $work->getImageMimeType());
            } else {
                @unlink($tempPath);
            }

            $work->clear();
            $work->destroy();

            if ($tempSize <= $maxBytes) {
                break;
            }
        }

        $origin->clear();
        $origin->destroy();

        if ($bestTempPath === '' || !is_file($bestTempPath)) {
            return null;
        }

        if (!@rename($bestTempPath, $fullPath)) {
            if (!@copy($bestTempPath, $fullPath)) {
                @unlink($bestTempPath);
                return null;
            }
            @unlink($bestTempPath);
        }

        return [
            'mime' => $bestMime !== '' ? $bestMime : 'image/jpeg',
            'size' => is_file($fullPath) ? (int) filesize($fullPath) : 0,
        ];
    } catch (Throwable $e) {
        return null;
    }
}

function create_gd_image_from_file(string $path, string $mime): mixed
{
    return match ($mime) {
        'image/jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($path) : null,
        'image/png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($path) : null,
        'image/gif' => function_exists('imagecreatefromgif') ? @imagecreatefromgif($path) : null,
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null,
        default => null,
    };
}

function save_gd_image_to_file(mixed $image, string $path, string $mime, int $attempt): bool
{
    return match ($mime) {
        'image/jpeg' => function_exists('imagejpeg')
            ? (bool) @imagejpeg($image, $path, max(25, 84 - ($attempt * 8)))
            : false,
        'image/png' => function_exists('imagepng')
            ? (bool) @imagepng($image, $path, min(9, 6 + (int) floor($attempt / 2)))
            : false,
        'image/gif' => function_exists('imagegif')
            ? (bool) @imagegif($image, $path)
            : false,
        'image/webp' => function_exists('imagewebp')
            ? (bool) @imagewebp($image, $path, max(20, 82 - ($attempt * 8)))
            : false,
        default => false,
    };
}

function prepare_canvas_alpha(mixed $canvas, string $mime): void
{
    if (!in_array($mime, ['image/png', 'image/gif', 'image/webp'], true)) {
        return;
    }

    if (function_exists('imagealphablending')) {
        @imagealphablending($canvas, false);
    }
    if (function_exists('imagesavealpha')) {
        @imagesavealpha($canvas, true);
    }
    if (function_exists('imagecolorallocatealpha') && function_exists('imagefill')) {
        $transparent = @imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        if ($transparent !== false) {
            @imagefill($canvas, 0, 0, $transparent);
        }
    }
}

function destroy_gd_image(mixed $image): void
{
    if (is_resource($image)) {
        @imagedestroy($image);
        return;
    }

    if (is_object($image) && get_class($image) === 'GdImage') {
        @imagedestroy($image);
    }
}

function detect_image_mime(array $image): string
{
    $tmpName = (string) ($image['tmp_name'] ?? '');
    $uploadType = strtolower(trim((string) ($image['type'] ?? '')));

    if ($tmpName !== '' && function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $detected = (string) finfo_file($finfo, $tmpName);
            finfo_close($finfo);
            if ($detected !== '') {
                return strtolower($detected);
            }
        }
    }

    if ($tmpName !== '' && function_exists('exif_imagetype')) {
        $imageType = @exif_imagetype($tmpName);
        $map = [
            IMAGETYPE_JPEG => 'image/jpeg',
            IMAGETYPE_PNG => 'image/png',
            IMAGETYPE_GIF => 'image/gif',
            IMAGETYPE_WEBP => 'image/webp',
        ];
        if (isset($map[$imageType])) {
            return $map[$imageType];
        }
    }

    if ($tmpName !== '' && function_exists('getimagesize')) {
        $info = @getimagesize($tmpName);
        if (is_array($info) && !empty($info['mime'])) {
            return strtolower((string) $info['mime']);
        }
    }

    if ($uploadType !== '') {
        return $uploadType;
    }

    return 'application/octet-stream';
}

function find_existing_record(int $taskId, int $memberId, string $taskDate): ?array
{
    $stmt = db()->prepare(
        'SELECT *
         FROM checkin_records
         WHERE task_id = :task_id
           AND member_id = :member_id
           AND task_date = :task_date
           AND deleted_at IS NULL
           AND is_valid = 1
         ORDER BY id DESC
         LIMIT 1'
    );
    $stmt->execute([
        'task_id' => $taskId,
        'member_id' => $memberId,
        'task_date' => $taskDate,
    ]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function summarize_record(array $record): array
{
    $countStmt = db()->prepare('SELECT COUNT(*) FROM checkin_images WHERE record_id = :record_id');
    $countStmt->execute(['record_id' => (int) $record['id']]);
    $imageCount = (int) $countStmt->fetchColumn();

    return [
        'id' => (int) $record['id'],
        'submit_time' => (string) $record['submit_time'],
        'task_date' => (string) $record['task_date'],
        'image_count' => $imageCount,
    ];
}

function detect_submit_source(string $userAgent): string
{
    $ua = strtolower($userAgent);
    if (str_contains($ua, 'micromessenger')) {
        return 'wechat_browser';
    }
    if (str_contains($ua, 'mobile')) {
        return 'mobile_browser';
    }
    if ($ua !== '') {
        return 'pc';
    }
    return 'web';
}

function last_checkin_record(string $taskLinkCode, int $memberId, ?string $taskDate = null): ?array
{
    if ($taskLinkCode === '' || $memberId <= 0) {
        return null;
    }
    $task = find_task_by_link_code($taskLinkCode, false);
    if ($task === null) {
        return null;
    }
    $sql = 'SELECT r.*
            FROM checkin_records r
            WHERE r.task_id = :task_id
              AND r.member_id = :member_id
              AND r.deleted_at IS NULL
            ';
    $params = [
        'task_id' => (int) $task['id'],
        'member_id' => $memberId,
    ];
    if ($taskDate !== null && $taskDate !== '') {
        $sql .= ' AND r.task_date = :task_date';
        $params['task_date'] = $taskDate;
    }
    $sql .= ' ORDER BY r.submit_time DESC LIMIT 1';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $record = $stmt->fetch();
    if (!$record) {
        return null;
    }
    return summarize_record($record);
}

function generate_task_link_code(): string
{
    for ($i = 0; $i < 10; $i++) {
        $code = bin2hex(random_bytes(6));
        $stmt = db()->prepare('SELECT COUNT(*) FROM tasks WHERE task_link_code = :code');
        $stmt->execute(['code' => $code]);
        if ((int) $stmt->fetchColumn() === 0) {
            return $code;
        }
    }
    return bin2hex(random_bytes(10));
}

function create_operation_log(int $adminId, string $operationType, string $targetType, int $targetId, string $content): void
{
    try {
        $stmt = db()->prepare(
            'INSERT INTO operation_logs
            (admin_id, operation_type, target_type, target_id, operation_content, ip_address, created_at)
             VALUES (:admin_id, :operation_type, :target_type, :target_id, :operation_content, :ip_address, NOW())'
        );
        $stmt->execute([
            'admin_id' => $adminId,
            'operation_type' => $operationType,
            'target_type' => $targetType,
            'target_id' => $targetId > 0 ? $targetId : null,
            'operation_content' => $content,
            'ip_address' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        ]);
    } catch (Throwable) {
        // Logging should not block main flow.
    }
}

// ─── User Services ───

function register_user(array $data): array
{
    $name = trim((string) ($data['name'] ?? ''));
    $mobile = trim((string) ($data['mobile'] ?? ''));
    $email = trim((string) ($data['email'] ?? ''));
    $password = (string) ($data['password'] ?? '');
    $passwordConfirm = (string) ($data['password_confirm'] ?? '');

    if ($name === '' || mb_strlen($name) > 50) {
        throw new InvalidArgumentException('姓名不能为空且不超过50字');
    }
    if (!preg_match('/^1[3-9]\d{9}$/', $mobile)) {
        throw new InvalidArgumentException('手机号格式不正确');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('邮箱格式不正确');
    }
    if (mb_strlen($password) < 6) {
        throw new InvalidArgumentException('密码至少6位');
    }
    if ($password !== $passwordConfirm) {
        throw new InvalidArgumentException('两次输入的密码不一致');
    }

    $exists = db()->prepare('SELECT id FROM users WHERE mobile = :mobile LIMIT 1');
    $exists->execute(['mobile' => $mobile]);
    if ($exists->fetch()) {
        throw new InvalidArgumentException('该手机号已注册');
    }

    $exists2 = db()->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $exists2->execute(['email' => $email]);
    if ($exists2->fetch()) {
        throw new InvalidArgumentException('该邮箱已注册');
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $refCode = 'U' . substr(md5($mobile . microtime()), 0, 8);
    $parentUser = find_publisher_by_ref_code(trim((string) ($data['ref'] ?? '')));
    $isSubUser = $parentUser !== null;
    $stmt = db()->prepare(
        'INSERT INTO users (name, mobile, email, password_hash, nickname, can_publish, ref_code, parent_user_id, referrer_id) VALUES (:name, :mobile, :email, :hash, :nickname, :can_publish, :ref_code, :parent_user_id, :referrer_id)'
    );
    $stmt->execute([
        'name' => $name,
        'mobile' => $mobile,
        'email' => $email,
        'hash' => $hash,
        'nickname' => $name,
        'can_publish' => $isSubUser ? 0 : 1,
        'ref_code' => $refCode,
        'parent_user_id' => $isSubUser ? (int) $parentUser['id'] : null,
        'referrer_id' => $isSubUser ? (int) $parentUser['id'] : null,
    ]);
    $userId = (int) db()->lastInsertId();

    if ($isSubUser) {
        $publisherAdminId = (int) ($parentUser['publisher_admin_id'] ?? 0);
        if ($publisherAdminId <= 0) {
            $publisherAdminId = get_publisher_admin_id((int) $parentUser['id']);
        }
        auto_bind_member_scoped($userId, $name, $mobile, $publisherAdminId);
        // 推荐人+1积分。手机号唯一(上方已校验)，重复注册会在插入前抛错，
        // 因此同一手机号只能注册一次，推广积分也只计一次。
        add_points((int) $parentUser['id'], 1, '推广注册', $userId);
    } else {
        auto_bind_member($userId, $name, $mobile);
    }

    return ['id' => $userId, 'name' => $name, 'mobile' => $mobile, 'email' => $email];
}

function attempt_user_login(string $account, string $password): ?array
{
    $account = trim($account);
    if ($account === '' || $password === '') {
        return null;
    }

    if (str_contains($account, '@')) {
        $stmt = db()->prepare('SELECT * FROM users WHERE email = :account LIMIT 1');
    } else {
        $stmt = db()->prepare('SELECT * FROM users WHERE mobile = :account LIMIT 1');
    }
    $stmt->execute(['account' => $account]);
    $user = $stmt->fetch();

    if (!$user) {
        return null;
    }
    if ((int) $user['status'] !== 1) {
        return null;
    }
    if (!password_verify($password, (string) $user['password_hash'])) {
        return null;
    }

    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    db()->prepare('UPDATE users SET last_login_at = NOW(), last_login_ip = :ip WHERE id = :id')
        ->execute(['ip' => $ip, 'id' => (int) $user['id']]);

    $logStmt = db()->prepare('INSERT INTO user_login_logs (user_id, login_ip, user_agent) VALUES (:uid, :ip, :ua)');
    $logStmt->execute([
        'uid' => (int) $user['id'],
        'ip' => $ip,
        'ua' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
    ]);

    unset($user['password_hash']);
    return $user;
}

function auto_bind_member(int $userId, string $name, string $mobile): void
{
    $already = db()->prepare('SELECT id FROM members WHERE user_id = :uid AND is_bound = 1 LIMIT 1');
    $already->execute(['uid' => $userId]);
    if ($already->fetch()) {
        return;
    }

    $match = db()->prepare(
        'SELECT * FROM members WHERE mobile = :mobile AND deleted_at IS NULL ORDER BY is_bound ASC, id ASC LIMIT 1'
    );
    $match->execute(['mobile' => $mobile]);
    $member = $match->fetch();

    if ($member) {
        if ((int) $member['is_bound'] === 1 && (int) $member['user_id'] !== $userId) {
            create_pending_member($userId, $name, $mobile);
            return;
        }
        db()->prepare('UPDATE members SET user_id = :uid, is_bound = 1, bind_time = NOW(), source_type = CASE WHEN source_type = "admin_create" THEN source_type ELSE "user_register" END WHERE id = :id')
            ->execute(['uid' => $userId, 'id' => (int) $member['id']]);
        return;
    }

    $sameName = db()->prepare('SELECT id FROM members WHERE name = :name AND deleted_at IS NULL LIMIT 1');
    $sameName->execute(['name' => $name]);
    if ($sameName->fetch()) {
        create_pending_member($userId, $name, $mobile);
        return;
    }

    $adminStmt = db()->prepare('SELECT id FROM admins WHERE status = 1 ORDER BY id ASC LIMIT 1');
    $adminStmt->execute();
    $adminRow = $adminStmt->fetch();
    $adminId = $adminRow ? (int) $adminRow['id'] : 1;

    $ins = db()->prepare(
        'INSERT INTO members (admin_id, user_id, name, mobile, status, is_bound, bind_time, source_type) VALUES (:aid, :uid, :name, :mobile, 1, 1, NOW(), "user_register")'
    );
    $ins->execute(['aid' => $adminId, 'uid' => $userId, 'name' => $name, 'mobile' => $mobile]);
}

// 下级用户仅在指定发布者名单内绑定/创建（完全隔离）
function auto_bind_member_scoped(int $userId, string $name, string $mobile, int $publisherAdminId): void
{
    $already = db()->prepare('SELECT id FROM members WHERE user_id = :uid AND is_bound = 1 LIMIT 1');
    $already->execute(['uid' => $userId]);
    if ($already->fetch()) {
        return;
    }
    $match = db()->prepare('SELECT * FROM members WHERE admin_id = :aid AND mobile = :mobile AND is_bound = 0 AND deleted_at IS NULL ORDER BY id ASC LIMIT 1');
    $match->execute(['aid' => $publisherAdminId, 'mobile' => $mobile]);
    $member = $match->fetch();
    if ($member) {
        db()->prepare('UPDATE members SET user_id = :uid, is_bound = 1, bind_time = NOW() WHERE id = :id')
            ->execute(['uid' => $userId, 'id' => (int) $member['id']]);
        return;
    }
    $ins = db()->prepare('INSERT INTO members (admin_id, user_id, name, mobile, status, is_bound, bind_time, source_type) VALUES (:aid, :uid, :name, :mobile, 1, 1, NOW(), "user_register")');
    $ins->execute(['aid' => $publisherAdminId, 'uid' => $userId, 'name' => $name, 'mobile' => $mobile]);
}

function create_pending_member(int $userId, string $name, string $mobile): void
{
    $adminStmt = db()->prepare('SELECT id FROM admins WHERE status = 1 ORDER BY id ASC LIMIT 1');
    $adminStmt->execute();
    $adminRow = $adminStmt->fetch();
    $adminId = $adminRow ? (int) $adminRow['id'] : 1;

    $ins = db()->prepare(
        'INSERT INTO members (admin_id, user_id, name, mobile, status, is_bound, bind_time, source_type) VALUES (:aid, :uid, :name, :mobile, 1, 1, NOW(), "pending_confirm")'
    );
    $ins->execute(['aid' => $adminId, 'uid' => $userId, 'name' => $name, 'mobile' => $mobile]);
}

function get_user_bound_member(int $userId): ?array
{
    $stmt = db()->prepare('SELECT * FROM members WHERE user_id = :uid AND is_bound = 1 AND deleted_at IS NULL LIMIT 1');
    $stmt->execute(['uid' => $userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function list_user_checkin_records(int $userId, int $limit, array $filters, int $offset = 0): array
{
    $member = get_user_bound_member($userId);
    if (!$member) {
        return [];
    }
    $memberId = (int) $member['id'];

    $where = ['cr.member_id = :member_id', 'cr.deleted_at IS NULL'];
    $params = ['member_id' => $memberId];

    $taskId = (int) ($filters['task_id'] ?? 0);
    if ($taskId > 0) {
        $where[] = 'cr.task_id = :task_id';
        $params['task_id'] = $taskId;
    }
    $dateFrom = trim((string) ($filters['date_from'] ?? ''));
    if ($dateFrom !== '') {
        $where[] = 'cr.checkin_date >= :date_from';
        $params['date_from'] = $dateFrom;
    }
    $dateTo = trim((string) ($filters['date_to'] ?? ''));
    if ($dateTo !== '') {
        $where[] = 'cr.checkin_date <= :date_to';
        $params['date_to'] = $dateTo;
    }

    $sql = 'SELECT cr.*, t.task_name FROM checkin_records cr LEFT JOIN tasks t ON t.id = cr.task_id WHERE ' . implode(' AND ', $where) . ' ORDER BY cr.created_at DESC LIMIT ' . $limit . ' OFFSET ' . $offset;
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll() ?: [];

    $recordIds = array_map(fn($r) => (int) $r['id'], $rows);
    $imageMap = [];
    if ($recordIds !== []) {
        $placeholders = implode(',', array_fill(0, count($recordIds), '?'));
        $imgStmt = db()->prepare("SELECT * FROM checkin_images WHERE record_id IN ($placeholders)");
        $imgStmt->execute($recordIds);
        foreach ($imgStmt->fetchAll() ?: [] as $img) {
            $imageMap[(int) $img['record_id']][] = $img;
        }
    }
    foreach ($rows as &$row) {
        $row['images'] = $imageMap[(int) $row['id']] ?? [];
    }
    return $rows;
}

function list_tasks_for_member(int $memberId): array
{
    $stmt = db()->prepare(
        'SELECT DISTINCT t.id, t.task_name FROM tasks t INNER JOIN task_members tm ON tm.task_id = t.id WHERE tm.member_id = :mid ORDER BY t.created_at DESC'
    );
    $stmt->execute(['mid' => $memberId]);
    return $stmt->fetchAll() ?: [];
}

function list_all_users(array $filters): array
{
    $where = ['1=1'];
    $params = [];

    $keyword = trim((string) ($filters['keyword'] ?? ''));
    if ($keyword !== '') {
        $where[] = '(u.name LIKE :kw OR u.mobile LIKE :kw OR u.email LIKE :kw)';
        $params['kw'] = '%' . $keyword . '%';
    }
    $status = ($filters['status'] ?? '');
    if ($status !== '' && $status !== 'all') {
        $where[] = 'u.status = :status';
        $params['status'] = (int) $status;
    }
    $bindFilter = (string) ($filters['bind'] ?? '');
    if ($bindFilter === 'unbound') {
        $where[] = 'NOT EXISTS (SELECT 1 FROM members m WHERE m.user_id = u.id AND m.is_bound = 1)';
    }

    $sql = 'SELECT u.* FROM users u WHERE ' . implode(' AND ', $where) . ' ORDER BY u.created_at DESC LIMIT 200';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll() ?: [];
    $userIds = [];
    foreach ($rows as &$row) {
        unset($row['password_hash']);
        $userIds[] = (int) $row['id'];
    }
    unset($row);

    // Attach binding info
    if ($userIds !== []) {
        $ph = implode(',', array_fill(0, count($userIds), '?'));
        $bStmt = db()->prepare("SELECT user_id, id AS member_id, name AS member_name, mobile AS member_mobile FROM members WHERE user_id IN ($ph) AND is_bound = 1 AND deleted_at IS NULL");
        $bStmt->execute($userIds);
        $bindMap = [];
        foreach ($bStmt->fetchAll() ?: [] as $b) {
            $bindMap[(int) $b['user_id']] = $b;
        }
        foreach ($rows as &$row) {
            $uid = (int) $row['id'];
            $row['bound_member'] = $bindMap[$uid] ?? null;
        }
        unset($row);
    }

    return $rows;
}

function toggle_user_status(int $userId): void
{
    $stmt = db()->prepare('UPDATE users SET status = IF(status=1, 0, 1) WHERE id = :id');
    $stmt->execute(['id' => $userId]);
}

// 后台编辑用户基础信息（姓名/手机/邮箱/状态），手机与邮箱唯一性校验
function admin_update_user(int $userId, array $payload): void
{
    $name = trim((string) ($payload['name'] ?? ''));
    $mobile = trim((string) ($payload['mobile'] ?? ''));
    $email = trim((string) ($payload['email'] ?? ''));
    $status = (int) ($payload['status'] ?? 1) === 1 ? 1 : 0;

    if ($name === '') {
        throw new InvalidArgumentException('姓名不能为空');
    }
    if ($mobile === '') {
        throw new InvalidArgumentException('手机号不能为空');
    }
    if ($email === '') {
        throw new InvalidArgumentException('邮箱不能为空');
    }
    $c = db()->prepare('SELECT COUNT(*) c FROM users WHERE mobile = :m AND id <> :id');
    $c->execute(['m' => $mobile, 'id' => $userId]);
    if ((int) ($c->fetch()['c'] ?? 0) > 0) {
        throw new InvalidArgumentException('手机号已被其他用户使用');
    }
    $c = db()->prepare('SELECT COUNT(*) c FROM users WHERE email = :e AND id <> :id');
    $c->execute(['e' => $email, 'id' => $userId]);
    if ((int) ($c->fetch()['c'] ?? 0) > 0) {
        throw new InvalidArgumentException('邮箱已被其他用户使用');
    }
    db()->prepare('UPDATE users SET name = :n, mobile = :m, email = :e, status = :s WHERE id = :id')
        ->execute(['n' => $name, 'm' => $mobile, 'e' => $email, 's' => $status, 'id' => $userId]);

    if (array_key_exists('points', $payload)) {
        $points = max(0, (int) $payload['points']);
        db()->prepare('UPDATE users SET points = :p WHERE id = :id')->execute(['p' => $points, 'id' => $userId]);
    }
    if (array_key_exists('balance_yuan', $payload)) {
        $balanceFen = (int) round((float) $payload['balance_yuan'] * 10);
        if ($balanceFen < 0) $balanceFen = 0;
        db()->prepare('UPDATE users SET balance_fen = :b WHERE id = :id')->execute(['b' => $balanceFen, 'id' => $userId]);
    }
}

function reset_user_password(int $userId): string
{
    $newPass = substr(bin2hex(random_bytes(4)), 0, 8);
    $hash = password_hash($newPass, PASSWORD_BCRYPT);
    db()->prepare('UPDATE users SET password_hash = :hash WHERE id = :id')->execute(['hash' => $hash, 'id' => $userId]);
    return $newPass;
}

function delete_user(int $userId): void
{
    db()->prepare('UPDATE members SET user_id = NULL, is_bound = 0, bind_time = NULL WHERE user_id = :uid')->execute(['uid' => $userId]);
    db()->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $userId]);
}

function find_matching_members_for_bind(int $userId, string $userName): array
{
    $bound = get_user_bound_member($userId);
    if ($bound && (string) ($bound['source_type'] ?? '') !== 'pending_confirm') {
        return [];
    }
    $stmt = db()->prepare(
        'SELECT id, name, mobile, position, department FROM members WHERE name = :name AND deleted_at IS NULL AND source_type != "pending_confirm" AND (user_id IS NULL OR user_id = 0 OR (user_id = :uid AND is_bound = 0))'
    );
    $stmt->execute(['name' => $userName, 'uid' => $userId]);
    return $stmt->fetchAll() ?: [];
}

function bind_user_to_member(int $userId, int $memberId): bool
{
    $member = db()->prepare('SELECT * FROM members WHERE id = :id AND deleted_at IS NULL LIMIT 1');
    $member->execute(['id' => $memberId]);
    $row = $member->fetch();
    if (!$row) {
        return false;
    }
    if ((int) ($row['user_id'] ?? 0) > 0 && (int) $row['user_id'] !== $userId && (int) $row['is_bound'] === 1) {
        return false;
    }
    // Remove any pending_confirm records for this user
    db()->prepare('DELETE FROM members WHERE user_id = :uid AND source_type = "pending_confirm"')->execute(['uid' => $userId]);
    // Bind
    db()->prepare('UPDATE members SET user_id = :uid, is_bound = 1, bind_time = NOW() WHERE id = :id')
        ->execute(['uid' => $userId, 'id' => $memberId]);
    return true;
}

function admin_unbind_user(int $userId): bool
{
    db()->prepare('UPDATE members SET user_id = 0, is_bound = 0, bind_time = NULL WHERE user_id = :uid AND is_bound = 1')
        ->execute(['uid' => $userId]);
    return true;
}

function admin_rebind_user(int $userId, int $newMemberId): bool
{
    admin_unbind_user($userId);
    $stmt = db()->prepare('SELECT id FROM members WHERE id = :id AND deleted_at IS NULL LIMIT 1');
    $stmt->execute(['id' => $newMemberId]);
    if (!$stmt->fetch()) return false;
    db()->prepare('UPDATE members SET user_id = :uid, is_bound = 1, bind_time = NOW() WHERE id = :id')
        ->execute(['uid' => $userId, 'id' => $newMemberId]);
    return true;
}

function get_user_binding_info(int $userId): ?array
{
    $stmt = db()->prepare('SELECT id, name, mobile FROM members WHERE user_id = :uid AND is_bound = 1 AND deleted_at IS NULL LIMIT 1');
    $stmt->execute(['uid' => $userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function change_user_password(int $userId, string $oldPassword, string $newPassword): bool
{
    $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $userId]);
    $row = $stmt->fetch();
    if (!$row || !password_verify($oldPassword, (string) $row['password_hash'])) {
        return false;
    }
    $hash = password_hash($newPassword, PASSWORD_BCRYPT);
    db()->prepare('UPDATE users SET password_hash = :hash WHERE id = :id')->execute(['hash' => $hash, 'id' => $userId]);
    return true;
}

function list_popup_announcements(): array
{
    $stmt = db()->query('SELECT * FROM popup_announcements ORDER BY created_at DESC');
    return $stmt->fetchAll() ?: [];
}

function get_active_popup(): ?array
{
    $stmt = db()->query('SELECT * FROM popup_announcements WHERE status = 1 ORDER BY updated_at DESC LIMIT 1');
    $row = $stmt->fetch();
    return $row ?: null;
}

function create_popup(int $adminId, string $content, string $targetAudience = 'all', string $frequency = 'once_per_day'): int
{
    $content = trim($content);
    if ($content === '') {
        throw new InvalidArgumentException('公告内容不能为空');
    }
    $validAudiences = ['all', 'logged_in', 'guest'];
    $validFreqs = ['every_time', 'once_per_day', 'once_only'];
    if (!in_array($targetAudience, $validAudiences, true)) { $targetAudience = 'all'; }
    if (!in_array($frequency, $validFreqs, true)) { $frequency = 'once_per_day'; }
    $stmt = db()->prepare('INSERT INTO popup_announcements (content, created_by, target_audience, frequency) VALUES (:content, :admin_id, :target_audience, :frequency)');
    $stmt->execute(['content' => $content, 'admin_id' => $adminId, 'target_audience' => $targetAudience, 'frequency' => $frequency]);
    return (int) db()->lastInsertId();
}

function update_popup(int $id, string $content, string $targetAudience = 'all', string $frequency = 'once_per_day'): void
{
    $content = trim($content);
    if ($content === '') {
        throw new InvalidArgumentException('公告内容不能为空');
    }
    $validAudiences = ['all', 'logged_in', 'guest'];
    $validFreqs = ['every_time', 'once_per_day', 'once_only'];
    if (!in_array($targetAudience, $validAudiences, true)) { $targetAudience = 'all'; }
    if (!in_array($frequency, $validFreqs, true)) { $frequency = 'once_per_day'; }
    db()->prepare('UPDATE popup_announcements SET content = :content, target_audience = :target_audience, frequency = :frequency WHERE id = :id')
        ->execute(['content' => $content, 'target_audience' => $targetAudience, 'frequency' => $frequency, 'id' => $id]);
}

function toggle_popup_status(int $id): void
{
    db()->prepare('UPDATE popup_announcements SET status = IF(status=1, 0, 1) WHERE id = :id')->execute(['id' => $id]);
}

function delete_popup(int $id): void
{
    db()->prepare('DELETE FROM popup_announcements WHERE id = :id')->execute(['id' => $id]);
}

function has_user_read_popup_today(int $userId, int $popupId): bool
{
    $stmt = db()->prepare('SELECT 1 FROM popup_read_logs WHERE user_id = :uid AND popup_id = :pid AND read_date = CURDATE() LIMIT 1');
    $stmt->execute(['uid' => $userId, 'pid' => $popupId]);
    return (bool) $stmt->fetch();
}

function mark_popup_read(int $userId, int $popupId): void
{
    $stmt = db()->prepare('INSERT IGNORE INTO popup_read_logs (popup_id, user_id, read_date) VALUES (:pid, :uid, CURDATE())');
    $stmt->execute(['pid' => $popupId, 'uid' => $userId]);
}

/* ============ 第一期：用户发布系统 ============ */

// 返回发布者关联的隐藏 admin 账号ID（首次调用时惰性创建）
function get_publisher_admin_id(int $userId): int
{
    $stmt = db()->prepare('SELECT publisher_admin_id, name, mobile, email, can_publish FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch();
    if (!$user || (int) $user['can_publish'] !== 1) {
        throw new InvalidArgumentException('无发布权限');
    }
    if ((int) ($user['publisher_admin_id'] ?? 0) > 0) {
        return (int) $user['publisher_admin_id'];
    }
    $username = 'pub_' . $userId;
    $ins = db()->prepare('INSERT INTO admins (username, password_hash, real_name, mobile, email, status, owner_user_id) VALUES (:u, :p, :rn, :m, :e, 1, :uid)');
    $ins->execute([
        'u' => $username,
        'p' => password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT),
        'rn' => (string) $user['name'],
        'm' => (string) ($user['mobile'] ?? ''),
        'e' => (string) ($user['email'] ?? ''),
        'uid' => $userId,
    ]);
    $adminId = (int) db()->lastInsertId();
    db()->prepare('UPDATE users SET publisher_admin_id = :aid WHERE id = :id')->execute(['aid' => $adminId, 'id' => $userId]);
    return $adminId;
}

// 发布者公开任务列表（按发布者隔离，逻辑同 list_public_tasks）
function list_public_tasks_by_owner(int $publisherAdminId): array
{
    ensure_task_data_schema();
    $stmt = db()->prepare(
        'SELECT * FROM tasks WHERE admin_id = :aid AND task_status = 1 AND start_time <= NOW() ORDER BY created_at DESC, id DESC'
    );
    $stmt->execute(['aid' => $publisherAdminId]);
    return $stmt->fetchAll() ?: [];
}

// 校验任务时长不超过上限（普通会员一周）
function assert_task_duration_limit(array $payload, int $maxDays = 7): void
{
    // 「当月有效」按自然月的日历规则：任务从发布日到发布当月最后一天。要求会员
    // 的最长天数权限能覆盖发布月份的总天数（28~31 天），否则不允许使用当月有效。
    if (parse_bool($payload['current_month'] ?? false)) {
        if ($maxDays >= MEMBERSHIP_UNLIMITED) {
            return;
        }
        $startTs = strtotime((string) ($payload['start_time'] ?? ''));
        $daysInMonth = $startTs !== false ? (int) date('t', $startTs) : 31;
        if ($daysInMonth > $maxDays) {
            throw new InvalidArgumentException('您的权限不支持当月有效（本月共 ' . $daysInMonth . ' 天，超过最长 ' . $maxDays . ' 天）');
        }
        return;
    }

    $expireMode = trim((string) ($payload['expire_mode'] ?? ''));
    if ($expireMode === 'long_term') {
        if ($maxDays >= MEMBERSHIP_UNLIMITED) {
            return; // 超级会员可发长期任务
        }
        throw new InvalidArgumentException('您的权限不支持长期任务，最长 ' . $maxDays . ' 天');
    }
    $days = match ($expireMode) {
        'three_days' => 3,
        'five_days' => 5,
        'one_week' => 7,
        'custom_days' => max(1, (int) ($payload['custom_days'] ?? 0)),
        default => 3,
    };
    if ($days > $maxDays) {
        throw new InvalidArgumentException('任务时长不能超过 ' . $maxDays . ' 天');
    }
}

// 通过 ref_code 查找发布者用户（需当前等级允许发展下级）
function find_publisher_by_ref_code(string $refCode): ?array
{
    if ($refCode === '') {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM users WHERE ref_code = :rc AND status = 1 AND can_publish = 1 LIMIT 1');
    $stmt->execute(['rc' => $refCode]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    $lv = get_user_membership((int) $row['id']);
    if ((int) ($lv['allow_sub_register'] ?? 0) !== 1) {
        return null; // 等级不允许发展下级 / 已过期降级
    }
    return $row;
}

/* ============ 第二期：会员分级 ============ */

const MEMBERSHIP_UNLIMITED = 999999;

// 返回用户的有效会员配额（过期则自动按普通会员/等级1计算）
function get_user_membership(int $userId): array
{
    $stmt = db()->prepare('SELECT membership_level_id, membership_expire_at FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch();
    $levelId = $user ? (int) $user['membership_level_id'] : 1;
    $expireAt = $user['membership_expire_at'] ?? null;
    if ($expireAt !== null && strtotime((string) $expireAt) < time()) {
        $levelId = 1; // 过期降级
    }
    $lvStmt = db()->prepare('SELECT * FROM membership_levels WHERE id = :id LIMIT 1');
    $lvStmt->execute(['id' => $levelId]);
    $level = $lvStmt->fetch();
    if (!$level) {
        $lvStmt->execute(['id' => 1]);
        $level = $lvStmt->fetch() ?: [];
    }
    $level['expire_at'] = ($expireAt !== null && strtotime((string)$expireAt) >= time()) ? (string)$expireAt : '';
    return $level;
}

function list_membership_levels(): array
{
    return db()->query('SELECT * FROM membership_levels ORDER BY sort_order ASC, id ASC')->fetchAll() ?: [];
}

// 该后台(发布者)所属会员等级是否允许自定义网站标题
function admin_can_custom_title(array $admin): bool
{
    $ownerId = (int) ($admin['owner_user_id'] ?? 0);
    if ($ownerId <= 0) return false;
    return (int) (get_user_membership($ownerId)['allow_custom_title'] ?? 0) === 1;
}

// 设置用户等级与到期时间，并对超限存量执行停用
function set_user_membership(int $userId, int $levelId, ?string $expireAt): void
{
    db()->prepare('UPDATE users SET membership_level_id = :lv, membership_expire_at = :exp WHERE id = :id')
        ->execute(['lv' => $levelId, 'exp' => $expireAt !== '' ? $expireAt : null, 'id' => $userId]);
}

// 当前进行中（启用且未过期）任务数
function count_active_tasks(int $adminId): int
{
    $stmt = db()->prepare('SELECT COUNT(*) c FROM tasks WHERE admin_id = :aid AND task_status = 1 AND (is_long_term = 1 OR end_time IS NULL OR end_time >= NOW())');
    $stmt->execute(['aid' => $adminId]);
    return (int) ($stmt->fetch()['c'] ?? 0);
}

/* ============ 第三期：商城 ============ */

function list_shop_plans(): array
{
    return db()->query('SELECT p.*, l.name level_name FROM shop_plans p JOIN membership_levels l ON l.id = p.level_id WHERE p.is_active = 1 ORDER BY p.sort_order ASC, p.id ASC')->fetchAll() ?: [];
}

function list_all_shop_plans(): array
{
    return db()->query('SELECT p.*, l.name level_name FROM shop_plans p JOIN membership_levels l ON l.id = p.level_id ORDER BY p.sort_order ASC, p.id ASC')->fetchAll() ?: [];
}

function get_shop_plan(int $id): ?array
{
    $s = db()->prepare('SELECT p.*, l.name level_name FROM shop_plans p JOIN membership_levels l ON l.id = p.level_id WHERE p.id = :id LIMIT 1');
    $s->execute(['id' => $id]);
    return $s->fetch() ?: null;
}

function get_setting(string $key, string $default = ''): string
{
    $s = db()->prepare('SELECT v FROM settings WHERE k = :k LIMIT 1');
    $s->execute(['k' => $key]);
    $row = $s->fetch();
    return $row ? (string) $row['v'] : $default;
}

function set_setting(string $key, string $value): void
{
    db()->prepare('INSERT INTO settings (k, v) VALUES (:k, :v) ON DUPLICATE KEY UPDATE v = :v2')
        ->execute(['k' => $key, 'v' => $value, 'v2' => $value]);
}

function create_order(int $userId, int $planId): array
{
    $plan = get_shop_plan($planId);
    if (!$plan || !(int) $plan['is_active']) {
        throw new InvalidArgumentException('套餐不存在或已下架');
    }
    // 体验套餐：授予等级以「体验会员设置」为准（覆盖套餐自身 level_id），天数仍用套餐天数
    $levelId = plan_effective_level_id($plan);
    $orderNo = date('YmdHis') . str_pad((string) $userId, 4, '0', STR_PAD_LEFT) . random_int(100, 999);
    db()->prepare('INSERT INTO orders (order_no, user_id, plan_id, plan_name, level_id, days, amount_fen, status) VALUES (:no, :uid, :pid, :pn, :lid, :days, :amt, \'pending\')')
        ->execute(['no' => $orderNo, 'uid' => $userId, 'pid' => $planId, 'pn' => $plan['name'], 'lid' => $levelId, 'days' => $plan['days'], 'amt' => $plan['price_fen']]);
    return get_order_by_no($orderNo);
}

// 套餐实际授予的会员等级：体验会员套餐取 trial_level_id 设置，否则取套餐自身 level_id
function plan_effective_level_id(array $plan): int
{
    if ((int) ($plan['is_trial_member'] ?? 0) === 1) {
        $tl = (int) get_setting('trial_level_id', '2');
        return in_array($tl, [2, 3], true) ? $tl : 2;
    }
    return (int) $plan['level_id'];
}

// 套餐实际授予等级的名称（供前台显示），体验会员套餐随设置变化
function plan_effective_level_name(array $plan): string
{
    $names = [1 => '普通会员', 2 => '高级会员', 3 => '超级会员'];
    if ((int) ($plan['is_trial_member'] ?? 0) === 1) {
        return $names[plan_effective_level_id($plan)] ?? '会员';
    }
    return (string) ($plan['level_name'] ?? ($names[(int) $plan['level_id']] ?? '会员'));
}

function get_order(int $id): ?array
{
    $s = db()->prepare('SELECT o.*, u.name user_name, u.mobile user_mobile FROM orders o JOIN users u ON u.id = o.user_id WHERE o.id = :id LIMIT 1');
    $s->execute(['id' => $id]);
    return $s->fetch() ?: null;
}

function get_order_by_no(string $no): ?array
{
    $s = db()->prepare('SELECT o.*, u.name user_name, u.mobile user_mobile FROM orders o JOIN users u ON u.id = o.user_id WHERE o.order_no = :no LIMIT 1');
    $s->execute(['no' => $no]);
    return $s->fetch() ?: null;
}

function get_user_order(int $orderId, int $userId): ?array
{
    $s = db()->prepare('SELECT * FROM orders WHERE id = :id AND user_id = :uid LIMIT 1');
    $s->execute(['id' => $orderId, 'uid' => $userId]);
    return $s->fetch() ?: null;
}

// 用户点"我已付款"
function mark_order_confirming(int $orderId, int $userId, string $transferNote): void
{
    $order = get_user_order($orderId, $userId);
    if (!$order || $order['status'] !== 'pending') {
        throw new InvalidArgumentException('订单状态不允许此操作');
    }
    db()->prepare('UPDATE orders SET status = \'confirming\', transfer_note = :note WHERE id = :id')
        ->execute(['note' => $transferNote, 'id' => $orderId]);
}

// 管理员确认到账 → 自动升级会员
function confirm_order(int $orderId, string $adminNote): void
{
    $order = get_order($orderId);
    if (!$order || !in_array($order['status'], ['pending', 'confirming'], true)) {
        throw new InvalidArgumentException('订单状态不允许确认');
    }
    db()->prepare('UPDATE orders SET status = \'paid\', admin_note = :note, paid_at = NOW() WHERE id = :id')
        ->execute(['note' => $adminNote, 'id' => $orderId]);
    // 续期：若当前等级相同且未过期则在到期时间上累加，否则从今天起算
    $uid = (int) $order['user_id'];
    $days = (int) $order['days'];
    $levelId = (int) $order['level_id'];
    $cur = db()->prepare('SELECT membership_level_id, membership_expire_at FROM users WHERE id = :id LIMIT 1');
    $cur->execute(['id' => $uid]);
    $u = $cur->fetch();
    $curLevel = (int) ($u['membership_level_id'] ?? 1);
    $curExpire = (string) ($u['membership_expire_at'] ?? '');
    if ($curLevel === $levelId && $curExpire !== '' && strtotime($curExpire) > time()) {
        $base = $curExpire;
    } else {
        $base = date('Y-m-d H:i:s');
    }
    $newExpire = date('Y-m-d H:i:s', strtotime($base) + $days * 86400);
    set_user_membership($uid, $levelId, $newExpire);
}

function cancel_order(int $orderId, string $adminNote = ''): void
{
    $order = get_order($orderId);
    if (!$order || $order['status'] === 'paid' || $order['status'] === 'cancelled') {
        throw new InvalidArgumentException('订单状态不允许取消');
    }
    db()->prepare('UPDATE orders SET status = \'cancelled\', admin_note = :note WHERE id = :id')
        ->execute(['note' => $adminNote, 'id' => $orderId]);
}

function list_orders(array $filters = []): array
{
    // 不再展示待支付(pending)订单：现已改为卡密/余额即时购买，无需转账确认
    $where = ['o.status <> \'pending\''];
    $params = [];
    if (!empty($filters['status'])) { $where[] = 'o.status = :status'; $params['status'] = $filters['status']; }
    if (!empty($filters['keyword'])) {
        $where[] = '(u.name LIKE :kw OR u.mobile LIKE :kw OR o.order_no LIKE :kw)';
        $params['kw'] = '%' . $filters['keyword'] . '%';
    }
    $sql = 'SELECT o.*, u.name user_name, u.mobile user_mobile FROM orders o JOIN users u ON u.id = o.user_id WHERE ' . implode(' AND ', $where) . ' ORDER BY o.id DESC LIMIT 200';
    $s = db()->prepare($sql);
    $s->execute($params);
    return $s->fetchAll() ?: [];
}

/* ============ 第四期：积分 / 余额 / 卡密 ============ */

function add_points(int $userId, int $change, string $reason, ?int $refUserId = null): void
{
    db()->prepare('UPDATE users SET points = points + :c WHERE id = :id')->execute(['c' => $change, 'id' => $userId]);
    db()->prepare('INSERT INTO point_logs (user_id, `change`, reason, ref_user_id) VALUES (:uid, :c, :r, :ref)')
        ->execute(['uid' => $userId, 'c' => $change, 'r' => $reason, 'ref' => $refUserId]);
}

function get_user_points(int $userId): int
{
    $s = db()->prepare('SELECT points FROM users WHERE id = :id LIMIT 1');
    $s->execute(['id' => $userId]);
    return (int) ($s->fetchColumn() ?? 0);
}

function get_user_balance(int $userId): int
{
    $s = db()->prepare('SELECT balance_fen FROM users WHERE id = :id LIMIT 1');
    $s->execute(['id' => $userId]);
    return (int) ($s->fetchColumn() ?? 0);
}

// 积分换余额：10积分=1元(10分)，最少10积分起换
function convert_points_to_balance(int $userId, int $points): void
{
    if ($points < 10 || $points % 10 !== 0) {
        throw new InvalidArgumentException('兑换积分必须是10的整数倍且不少于10');
    }
    $cur = get_user_points($userId);
    if ($cur < $points) {
        throw new InvalidArgumentException('积分不足');
    }
    $fenAdded = $points;
    db()->prepare('UPDATE users SET points = points - :p, balance_fen = balance_fen + :f WHERE id = :id')
        ->execute(['p' => $points, 'f' => $fenAdded, 'id' => $userId]);
    db()->prepare('INSERT INTO point_logs (user_id, `change`, reason) VALUES (:uid, :c, :r)')
        ->execute(['uid' => $userId, 'c' => -$points, 'r' => '兑换余额']);
}

// 余额支付订单（直接扣余额，无需卡密）
function pay_order_by_balance(int $orderId, int $userId): void
{
    $order = get_user_order($orderId, $userId);
    if (!$order || $order['status'] !== 'pending') {
        throw new InvalidArgumentException('订单状态不允许此操作');
    }
    $bal = get_user_balance($userId);
    if ($bal < (int) $order['amount_fen']) {
        throw new InvalidArgumentException('余额不足，当前余额 ¥' . number_format($bal / 10, 2));
    }
    db()->prepare('UPDATE users SET balance_fen = balance_fen - :f WHERE id = :id')
        ->execute(['f' => (int) $order['amount_fen'], 'id' => $userId]);
    confirm_order($orderId, '余额支付');
}

// 卡密：批量生成
function generate_card_keys(int $planId, int $count, string $charset = 'alphanum', int $length = 16): array
{
    $plan = get_shop_plan($planId);
    if (!$plan) throw new InvalidArgumentException('套餐不存在');
    if ($count < 1 || $count > 500) throw new InvalidArgumentException('数量须在1-500之间');
    if ($length < 4 || $length > 32) throw new InvalidArgumentException('长度须在4-32之间');

    $charsets = [
        'numeric'  => '0123456789',
        'alpha'    => 'ABCDEFGHJKLMNPQRSTUVWXYZ',
        'alphanum' => 'ABCDEFGHJKLMNPQRSTUVWXYZ0123456789',
        'full'     => 'ABCDEFGHJKLMNPQRSTUVWXYZ0123456789!@#$%^&*',
    ];
    $pool = $charsets[$charset] ?? $charsets['alphanum'];
    $poolLen = strlen($pool);

    $codes = [];
    $batchNo = date('ymdHis') . 'P' . $planId;
    $stmt = db()->prepare('INSERT IGNORE INTO card_keys (code, plan_id, batch_no) VALUES (:c, :p, :b)');
    $attempts = 0;
    while (count($codes) < $count && $attempts < $count * 5) {
        $attempts++;
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $pool[random_int(0, $poolLen - 1)];
        }
        $stmt->execute(['c' => $code, 'p' => $planId, 'b' => $batchNo]);
        if ($stmt->rowCount() > 0) $codes[] = $code;
    }
    return $codes;
}

function import_card_keys(int $planId, string $raw): array
{
    $plan = get_shop_plan($planId);
    if (!$plan) throw new InvalidArgumentException('套餐不存在');

    $lines = array_filter(array_map('trim', preg_split('/[\r\n,;]+/', $raw)));
    if (empty($lines)) throw new InvalidArgumentException('未检测到有效卡密');
    if (count($lines) > 500) throw new InvalidArgumentException('单次最多导入500条');

    $ok = $dup = $invalid = 0;
    $batchNo = date('ymdHis') . 'P' . $planId . 'I';
    $stmt = db()->prepare('INSERT IGNORE INTO card_keys (code, plan_id, batch_no) VALUES (:c, :p, :b)');
    foreach ($lines as $raw_code) {
        $code = strtoupper(preg_replace('/\s+/', '', $raw_code));
        if ($code === '' || strlen($code) > 32 || !preg_match('/^[A-Z0-9!@#$%^&*\-_]+$/i', $code)) {
            $invalid++;
            continue;
        }
        $stmt->execute(['c' => $code, 'p' => $planId, 'b' => $batchNo]);
        $stmt->rowCount() > 0 ? $ok++ : $dup++;
    }
    return ['ok' => $ok, 'dup' => $dup, 'invalid' => $invalid];
}

function lookup_card_key(string $code): ?array
{
    $code = strtoupper(trim($code));
    if ($code === '') return null;
    $s = db()->prepare('SELECT k.*, p.name plan_name FROM card_keys k JOIN shop_plans p ON p.id=k.plan_id WHERE k.code=:c LIMIT 1');
    $s->execute(['c' => $code]);
    return $s->fetch() ?: null;
}

function list_card_keys(array $filters = []): array
{
    $where = ['1=1'];
    $params = [];
    if (!empty($filters['plan_id'])) { $where[] = 'k.plan_id = :pid'; $params['pid'] = $filters['plan_id']; }
    if (!empty($filters['status'])) { $where[] = 'k.status = :st'; $params['st'] = $filters['status']; }
    $sql = 'SELECT k.*, p.name plan_name FROM card_keys k JOIN shop_plans p ON p.id = k.plan_id WHERE ' . implode(' AND ', $where) . ' ORDER BY k.id DESC LIMIT 500';
    $s = db()->prepare($sql);
    $s->execute($params);
    return $s->fetchAll() ?: [];
}

// 卡密批次列表（按 batch_no 聚合）
function list_card_key_batches(): array
{
    $sql = 'SELECT k.batch_no, p.name plan_name, p.days, MIN(k.created_at) created_at,
                   COUNT(*) total,
                   SUM(k.status = "unused") unused,
                   SUM(k.status = "used") used
            FROM card_keys k JOIN shop_plans p ON p.id = k.plan_id
            WHERE k.batch_no IS NOT NULL
            GROUP BY k.batch_no, p.name, p.days
            ORDER BY MIN(k.created_at) DESC LIMIT 200';
    return db()->query($sql)->fetchAll() ?: [];
}

// 取某批次全部卡密
function list_card_keys_by_batch(string $batchNo): array
{
    $s = db()->prepare('SELECT k.*, p.name plan_name, p.days FROM card_keys k JOIN shop_plans p ON p.id=k.plan_id WHERE k.batch_no=:b ORDER BY k.id ASC');
    $s->execute(['b' => $batchNo]);
    return $s->fetchAll() ?: [];
}

// 卡密兑换：验证卡密→确认订单→升级会员
function redeem_card_key(string $code, int $userId, int $orderId): void
{
    $code = strtoupper(trim($code));
    $s = db()->prepare('SELECT * FROM card_keys WHERE code = :c LIMIT 1');
    $s->execute(['c' => $code]);
    $key = $s->fetch();
    if (!$key) throw new InvalidArgumentException('卡密不存在');
    if ($key['status'] !== 'unused') throw new InvalidArgumentException('该卡密已被使用');
    $order = get_user_order($orderId, $userId);
    if (!$order || $order['status'] !== 'pending') throw new InvalidArgumentException('订单状态不允许兑换');
    if ((int) $key['plan_id'] !== (int) $order['plan_id']) throw new InvalidArgumentException('卡密与套餐不匹配');
    // 标记卡密已用
    db()->prepare('UPDATE card_keys SET status=\'used\', used_by=:uid, used_order_id=:oid, used_at=NOW() WHERE id=:id')
        ->execute(['uid' => $userId, 'oid' => $orderId, 'id' => (int) $key['id']]);
    confirm_order($orderId, '卡密兑换：' . $code);
}

// 卡密直接兑换：无需预先下单，验证卡密→按卡密绑定套餐自动建单并开通
function redeem_card_key_direct(string $code, int $userId): void
{
    $code = strtoupper(trim($code));
    if ($code === '') throw new InvalidArgumentException('请输入卡密');
    $s = db()->prepare('SELECT * FROM card_keys WHERE code = :c LIMIT 1');
    $s->execute(['c' => $code]);
    $key = $s->fetch();
    if (!$key) throw new InvalidArgumentException('卡密不存在');
    if ($key['status'] !== 'unused') throw new InvalidArgumentException('该卡密已被使用');
    $order = create_order($userId, (int) $key['plan_id']);
    $orderId = (int) $order['id'];
    db()->prepare('UPDATE card_keys SET status=\'used\', used_by=:uid, used_order_id=:oid, used_at=NOW() WHERE id=:id')
        ->execute(['uid' => $userId, 'oid' => $orderId, 'id' => (int) $key['id']]);
    confirm_order($orderId, '卡密兑换：' . $code);
}

// 推广统计：某用户推广了多少人
function count_referrals(int $userId): int
{
    $s = db()->prepare('SELECT COUNT(*) FROM users WHERE referrer_id = :id');
    $s->execute(['id' => $userId]);
    return (int) $s->fetchColumn();
}

// 积分流水列表
function list_point_logs(int $userId, int $limit = 50): array
{
    $s = db()->prepare('SELECT * FROM point_logs WHERE user_id = :uid ORDER BY id DESC LIMIT ' . $limit);
    $s->execute(['uid' => $userId]);
    return $s->fetchAll() ?: [];
}

/* ============ 发布者留言板 & 公告（按 publisher_admin_id 作用域） ============ */

// 发布者留言板：列出某发布者的留言主串 + 回复
function list_publisher_message_threads(int $pubAdminId, int $limit = 200): array
{
    $limit = max(1, min(500, $limit));
    $stmt = db()->prepare(
        'SELECT m.*, (SELECT COUNT(*) FROM online_messages r WHERE r.parent_id = m.id) AS reply_count
         FROM online_messages m
         WHERE m.parent_id IS NULL AND m.publisher_admin_id = :pid
         ORDER BY m.id DESC LIMIT ' . $limit
    );
    $stmt->execute(['pid' => $pubAdminId]);
    $rows = $stmt->fetchAll() ?: [];
    if ($rows === []) {
        return [];
    }
    $parentIds = array_map(static fn (array $r): int => (int) ($r['id'] ?? 0), $rows);
    $replyMap = list_online_message_replies_map($parentIds);
    foreach ($rows as &$row) {
        $row['replies'] = $replyMap[(int) ($row['id'] ?? 0)] ?? [];
    }
    unset($row);
    return $rows;
}

// 在某发布者留言板发帖/回复
function create_publisher_message(int $pubAdminId, array $payload): int
{
    $nickname = trim((string) ($payload['nickname'] ?? ''));
    $content = trim((string) ($payload['content'] ?? ''));
    $parentIdRaw = (int) ($payload['parent_id'] ?? 0);
    $parentId = $parentIdRaw > 0 ? $parentIdRaw : null;

    if ($content === '') {
        throw new InvalidArgumentException('留言内容不能为空');
    }
    if (function_exists('mb_strlen') && mb_strlen($content, 'UTF-8') > 2000) {
        throw new InvalidArgumentException('留言内容不能超过 2000 字');
    }
    if ($parentId !== null) {
        $p = find_online_message_by_id($parentId);
        if ($p === null || (int) ($p['publisher_admin_id'] ?? 0) !== $pubAdminId) {
            throw new InvalidArgumentException('回复目标不存在');
        }
    }
    $nickname = mb_substr($nickname, 0, 100, 'UTF-8');

    $stmt = db()->prepare(
        'INSERT INTO online_messages (publisher_admin_id, parent_id, is_admin_reply, nickname, content, status, client_ip, user_agent, created_at, updated_at)
         VALUES (:pid, :parent_id, 0, :nickname, :content, 1, :ip, :ua, NOW(), NOW())'
    );
    $stmt->execute([
        'pid' => $pubAdminId,
        'parent_id' => $parentId,
        'nickname' => $nickname,
        'content' => $content,
        'ip' => trim((string) ($_SERVER['REMOTE_ADDR'] ?? '')),
        'ua' => substr(trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 500),
    ]);
    return (int) db()->lastInsertId();
}

// 发布者删除自己留言板的留言（含回复），校验归属
function delete_publisher_message(int $pubAdminId, int $messageId): void
{
    $stmt = db()->prepare('DELETE FROM online_messages WHERE (id = :id1 OR parent_id = :id2) AND publisher_admin_id = :pid');
    $stmt->execute(['id1' => $messageId, 'id2' => $messageId, 'pid' => $pubAdminId]);
}

// 发布者公告：列表
function list_publisher_announcements(int $pubAdminId, bool $publishedOnly = false): array
{
    $sql = 'SELECT * FROM publisher_announcements WHERE publisher_admin_id = :pid';
    if ($publishedOnly) {
        $sql .= ' AND status = 1';
    }
    $sql .= ' ORDER BY id DESC';
    $stmt = db()->prepare($sql);
    $stmt->execute(['pid' => $pubAdminId]);
    return $stmt->fetchAll() ?: [];
}

function create_publisher_announcement(int $pubAdminId, string $content): int
{
    $content = trim($content);
    if ($content === '') {
        throw new InvalidArgumentException('公告内容不能为空');
    }
    if (mb_strlen($content, 'UTF-8') > 2000) {
        throw new InvalidArgumentException('公告内容不能超过 2000 字');
    }
    $stmt = db()->prepare('INSERT INTO publisher_announcements (publisher_admin_id, content, status, created_at, updated_at) VALUES (:pid, :c, 1, NOW(), NOW())');
    $stmt->execute(['pid' => $pubAdminId, 'c' => $content]);
    return (int) db()->lastInsertId();
}

function toggle_publisher_announcement(int $pubAdminId, int $id, int $status): void
{
    db()->prepare('UPDATE publisher_announcements SET status = :s, updated_at = NOW() WHERE id = :id AND publisher_admin_id = :pid')
        ->execute(['s' => $status === 1 ? 1 : 0, 'id' => $id, 'pid' => $pubAdminId]);
}

function delete_publisher_announcement(int $pubAdminId, int $id): void
{
    db()->prepare('DELETE FROM publisher_announcements WHERE id = :id AND publisher_admin_id = :pid')
        ->execute(['id' => $id, 'pid' => $pubAdminId]);
}


