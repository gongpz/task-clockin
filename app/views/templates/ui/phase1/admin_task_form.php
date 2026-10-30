<?php
$csrf = (string) ($csrf ?? '');
$formAction = (string) ($formAction ?? '/admin/tasks');
$editing = is_array($editing ?? null) ? $editing : null;
$taskId = (int) ($editing['id'] ?? 0);

$title = $taskId > 0 ? '编辑任务' : '新建任务';
$taskName = (string) ($editing['task_name'] ?? '');
$taskDesc = (string) ($editing['task_desc'] ?? '');
$startTime = (string) ($startTime ?? ($editing['start_time'] ?? date('Y-m-d\TH:i')));
$taskStatus = (string) ($editing['task_status'] ?? '1');
$allowOverride = (string) ($editing['allow_override'] ?? '1');
$duplicateAction = (string) ($editing['duplicate_action'] ?? 'prompt');
$minImages = (string) ($editing['min_images'] ?? '1');
$maxImages = (string) ($editing['max_images'] ?? '3');
$allowSelectTaskDate = (string) ($editing['allow_select_task_date'] ?? '1');
$taskDateMode = (string) ($editing['task_date_mode'] ?? 'free_in_range');
$sortOrder = (string) ($editing['sort_order'] ?? '0');
$remark = (string) ($editing['remark'] ?? '');

$expireMode = (string) ($expireMode ?? 'three_days');
$customDays = (string) ($customDays ?? '3');
$currentMonth = (string) ($currentMonth ?? '0');

$members = is_array($members ?? null) ? $members : [];
$selectedMemberIds = is_array($selectedMemberIds ?? null) ? $selectedMemberIds : [];
$memberGroups = is_array($memberGroups ?? null) ? $memberGroups : [];
$selectedGroupIds = is_array($selectedGroupIds ?? null) ? $selectedGroupIds : [];
?>
<div class="space-y-5">
    <form method="post" action="<?= h($formAction) ?>" class="space-y-5">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        <?php if ($taskId > 0): ?>
            <input type="hidden" name="id" value="<?= $taskId ?>">
        <?php endif; ?>

        <section class="grid gap-5 xl:grid-cols-[1.05fr_1fr]">
            <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-base font-semibold text-slate-900">基础信息</h2>
                <div class="mt-4 space-y-4">
                    <label class="block text-sm font-medium text-slate-700">任务名称
                        <input
                            type="text"
                            name="task_name"
                            value="<?= h($taskName) ?>"
                            required
                            class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                        >
                    </label>

                    <label class="block text-sm font-medium text-slate-700">发布时间
                        <input
                            type="datetime-local"
                            name="start_time"
                            value="<?= h($startTime) ?>"
                            required
                            class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                        >
                    </label>

                    <label class="block text-sm font-medium text-slate-700">任务说明
                        <textarea
                            name="task_desc"
                            rows="4"
                            placeholder="可填写任务说明或提交要求"
                            class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                        ><?= h($taskDesc) ?></textarea>
                    </label>
                </div>
            </article>

            <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-base font-semibold text-slate-900">任务规则</h2>

                <div class="mt-4 space-y-3">
                    <div class="grid gap-3 md:grid-cols-3">
                        <label class="text-sm font-medium text-slate-700">有效期规则
                            <select id="task-expire-mode" name="expire_mode" class="mt-1 h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                                <option value="three_days" <?= $expireMode === 'three_days' ? 'selected' : '' ?>>三天</option>
                                <option value="five_days" <?= $expireMode === 'five_days' ? 'selected' : '' ?>>五天</option>
                                <option value="one_week" <?= $expireMode === 'one_week' ? 'selected' : '' ?>>一周</option>
                                <option value="custom_days" <?= $expireMode === 'custom_days' ? 'selected' : '' ?>>自定义天数</option>
                                <option value="long_term" <?= $expireMode === 'long_term' ? 'selected' : '' ?>>长期有效</option>
                            </select>
                        </label>

                        <label id="task-custom-days-wrap" class="text-sm font-medium text-slate-700">自定义天数
                            <input
                                id="task-custom-days"
                                type="number"
                                name="custom_days"
                                min="1"
                                step="1"
                                value="<?= h($customDays) ?>"
                                class="mt-1 h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                            >
                        </label>

                        <label class="text-sm font-medium text-slate-700">当月有效
                            <select id="task-current-month" name="current_month" class="mt-1 h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                                <option value="0" <?= $currentMonth === '1' ? '' : 'selected' ?>>否</option>
                                <option value="1" <?= $currentMonth === '1' ? 'selected' : '' ?>>是</option>
                            </select>
                        </label>
                    </div>

                    <div class="grid gap-3 md:grid-cols-3">
                        <label class="text-sm font-medium text-slate-700">任务状态
                            <select name="task_status" class="mt-1 h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                                <option value="1" <?= $taskStatus === '1' ? 'selected' : '' ?>>启用</option>
                                <option value="0" <?= $taskStatus === '0' ? 'selected' : '' ?>>停用</option>
                            </select>
                        </label>

                        <label class="text-sm font-medium text-slate-700">允许覆盖
                            <select name="allow_override" class="mt-1 h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                                <option value="1" <?= $allowOverride === '1' ? 'selected' : '' ?>>允许</option>
                                <option value="0" <?= $allowOverride === '0' ? 'selected' : '' ?>>禁止</option>
                            </select>
                        </label>

                        <label class="text-sm font-medium text-slate-700">重复处理策略
                            <select name="duplicate_action" class="mt-1 h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                                <option value="prompt" <?= $duplicateAction === 'prompt' ? 'selected' : '' ?>>提示是否覆盖</option>
                                <option value="reject" <?= $duplicateAction === 'reject' ? 'selected' : '' ?>>拒绝重复提交</option>
                                <option value="override" <?= $duplicateAction === 'override' ? 'selected' : '' ?>>自动覆盖旧记录</option>
                            </select>
                        </label>
                    </div>

                    <div class="grid gap-3 md:grid-cols-2">
                        <label class="text-sm font-medium text-slate-700">最少图片数
                            <input
                                type="number"
                                name="min_images"
                                min="1"
                                max="10"
                                value="<?= h($minImages) ?>"
                                class="mt-1 h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                            >
                        </label>

                        <label class="text-sm font-medium text-slate-700">最多图片数
                            <input
                                type="number"
                                name="max_images"
                                min="1"
                                max="10"
                                value="<?= h($maxImages) ?>"
                                class="mt-1 h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                            >
                        </label>
                    </div>

                    <div class="grid gap-3 md:grid-cols-3">
                        <label class="text-sm font-medium text-slate-700">打卡日期可选
                            <select name="allow_select_task_date" class="mt-1 h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                                <option value="1" <?= $allowSelectTaskDate === '1' ? 'selected' : '' ?>>允许</option>
                                <option value="0" <?= $allowSelectTaskDate === '0' ? 'selected' : '' ?>>不允许</option>
                            </select>
                        </label>

                        <label class="text-sm font-medium text-slate-700">打卡日期策略
                            <select name="task_date_mode" class="mt-1 h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                                <option value="free_in_range" <?= $taskDateMode === 'free_in_range' ? 'selected' : '' ?>>有效期内自由选择</option>
                                <option value="today_only" <?= $taskDateMode === 'today_only' ? 'selected' : '' ?>>仅当天</option>
                            </select>
                        </label>

                        <label class="text-sm font-medium text-slate-700">排序
                            <input
                                type="number"
                                name="sort_order"
                                step="1"
                                value="<?= h($sortOrder) ?>"
                                class="mt-1 h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                            >
                        </label>
                    </div>

                    <label class="block text-sm font-medium text-slate-700">备注
                        <textarea
                            name="remark"
                            rows="2"
                            placeholder="可选"
                            class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                        ><?= h($remark) ?></textarea>
                    </label>
                </div>
            </article>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="grid gap-4 lg:grid-cols-2">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">选择用户组</h2>
                    <p class="mt-1 text-sm text-slate-500">可多选，提交后会自动把组内成员加入任务</p>
                    <div class="mt-3 max-h-[280px] overflow-y-auto rounded-lg border border-slate-200 p-3">
                        <?php if ($memberGroups === []): ?>
                            <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50 px-3 py-6 text-sm text-slate-500">暂无分组</div>
                        <?php else: ?>
                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                <?php foreach ($memberGroups as $group): ?>
                                    <?php
                                    $gid = (int) ($group['id'] ?? 0);
                                    $groupName = (string) ($group['group_name'] ?? '未命名分组');
                                    if ($gid <= 0) {
                                        continue;
                                    }
                                    ?>
                                    <label class="inline-flex h-9 items-center gap-2 rounded-lg border border-slate-200 bg-white px-2.5 text-sm text-slate-700 transition hover:border-blue-200 hover:bg-blue-50">
                                        <input type="checkbox" name="group_ids[]" value="<?= $gid ?>" <?= in_array($gid, $selectedGroupIds, true) ? 'checked' : '' ?> class="h-4 w-4 shrink-0 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                        <span class="truncate"><?= h($groupName) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div>
                    <h2 class="text-base font-semibold text-slate-900">选择成员</h2>
                    <p class="mt-1 text-sm text-slate-500">可与“选择用户组”同时使用，后端自动去重</p>
                    <?php if ($members === []): ?>
                        <div class="mt-3 rounded-lg border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-sm text-slate-500">暂无可选成员</div>
                    <?php else: ?>
                        <div class="mt-3 max-h-[280px] overflow-y-auto rounded-lg border border-slate-200 p-3">
                            <div class="member-select-grid">
                                <?php foreach ($members as $member): ?>
                                    <?php
                                    $mid = (int) ($member['id'] ?? 0);
                                    $memberName = (string) ($member['name'] ?? '');
                                    if ($mid <= 0) {
                                        continue;
                                    }
                                    ?>
                                    <label class="member-select-item">
                                        <input type="checkbox" name="member_ids[]" value="<?= $mid ?>" <?= in_array($mid, $selectedMemberIds, true) ? 'checked' : '' ?> class="h-3.5 w-3.5 shrink-0 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                        <span class="min-w-0 truncate"><?= h($memberName) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <div class="flex flex-wrap items-center gap-2">
            <button type="submit" class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700">保存任务</button>
            <a href="/admin/tasks" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50">返回列表</a>
            <span class="text-sm text-slate-500"><?= h($title) ?></span>
        </div>
    </form>
</div>

<style>
.admin-content .member-select-grid {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: .5rem;
}

.admin-content .member-select-item {
    display: inline-flex;
    align-items: center;
    gap: .45rem;
    min-height: 2rem;
    margin: 0;
    border: 1px solid #e2e8f0;
    border-radius: .5rem;
    background: #fff;
    padding: .25rem .4rem;
    font-size: .75rem;
    font-weight: 500;
    line-height: 1;
    color: #334155;
}

.admin-content .member-select-item:hover {
    border-color: #bfdbfe;
    background: #eff6ff;
}

@media (max-width: 1280px) {
    .admin-content .member-select-grid {
        grid-template-columns: repeat(5, minmax(0, 1fr));
    }
}

@media (max-width: 1024px) {
    .admin-content .member-select-grid {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }
}

@media (max-width: 768px) {
    .admin-content .member-select-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 520px) {
    .admin-content .member-select-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
</style>

<script>
(function () {
    var expireMode = document.getElementById('task-expire-mode');
    var customInput = document.getElementById('task-custom-days');
    var currentMonth = document.getElementById('task-current-month');

    if (!expireMode || !customInput) {
        return;
    }

    var isCurrentMonth = function () {
        return currentMonth && currentMonth.value === '1';
    };

    var syncCustomDays = function () {
        var monthOn = isCurrentMonth();
        // 「当月有效」优先：开启时置灰并禁用有效期规则与自定义天数。
        expireMode.disabled = monthOn;
        expireMode.classList.toggle('bg-slate-100', monthOn);
        expireMode.classList.toggle('text-slate-400', monthOn);

        var isCustom = expireMode.value === 'custom_days';
        var customDisabled = monthOn || !isCustom;
        customInput.disabled = customDisabled;
        customInput.classList.toggle('bg-slate-100', customDisabled);
        customInput.classList.toggle('text-slate-400', customDisabled);
    };

    expireMode.addEventListener('change', syncCustomDays);
    if (currentMonth) {
        currentMonth.addEventListener('change', syncCustomDays);
    }
    syncCustomDays();
})();
</script>