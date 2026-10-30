<?php
$csrf = (string) ($csrf ?? '');
$tasks = is_array($tasks ?? null) ? $tasks : [];
$members = is_array($members ?? null) ? $members : [];
$editing = is_array($editing ?? null) ? $editing : null;
$selectedMemberIds = is_array($selectedMemberIds ?? null) ? $selectedMemberIds : [];
$groups = is_array($groups ?? null) ? $groups : [];
$selectedGroupIds = is_array($selectedGroupIds ?? null) ? $selectedGroupIds : [];
$flash = is_array($flash ?? null) ? $flash : null;
$refCode = (string) ($refCode ?? '');
$maxDays = (int) ($maxDays ?? 7);
$membership = is_array($membership ?? null) ? $membership : [];
$maxActiveTasks = (int) ($membership['max_active_tasks'] ?? 3);
$activeTaskCount = (int) ($activeTaskCount ?? 0);
$UNL = 999999;
$levelName = (string) ($membership['name'] ?? '普通会员');
$taskId = (int) ($editing['id'] ?? 0);
$startTime = (string) ($startTime ?? date('Y-m-d\TH:i'));
$expireMode = (string) ($expireMode ?? 'three_days');
$customDays = (string) ($customDays ?? '3');
$currentMonth = (string) ($currentMonth ?? '0');
?>
<div class="mx-auto w-full max-w-3xl space-y-5 pb-10">
    <?php if (!empty($flash['message'])): ?>
        <div class="rounded-xl border px-4 py-3 text-sm <?= ($flash['type'] ?? '') === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-rose-200 bg-rose-50 text-rose-700' ?>"><?= h((string) $flash['message']) ?></div>
    <?php endif; ?>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex items-center justify-between">
            <h1 class="text-lg font-bold text-slate-900"><?= $taskId > 0 ? '编辑任务' : '发布新任务' ?></h1>
            <a href="/u/<?= h($refCode) ?>" target="_blank" class="text-xs text-blue-600 hover:underline">查看我的任务页 →</a>
        </div>
        <div class="mt-1 flex flex-wrap gap-3 text-xs text-slate-500">
            <span>等级：<strong class="text-slate-700"><?= h($levelName) ?></strong></span>
            <span>进行中任务：<strong class="<?= $activeTaskCount >= $maxActiveTasks ? 'text-rose-600' : 'text-slate-700' ?>"><?= $activeTaskCount ?>/<?= $maxActiveTasks >= $UNL ? '不限' : $maxActiveTasks ?></strong></span>
            <span>任务时长：最长 <strong class="text-slate-700"><?= $maxDays >= $UNL ? '不限' : $maxDays . ' 天' ?></strong></span>
        </div>
        <form method="post" action="<?= $taskId > 0 ? '/my/tasks/update' : '/my/tasks' ?>" class="mt-4 space-y-4">
            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
            <?php if ($taskId > 0): ?><input type="hidden" name="id" value="<?= $taskId ?>"><?php endif; ?>

            <label class="block text-sm font-medium text-slate-700">任务名称
                <input type="text" name="task_name" required value="<?= h((string) ($editing['task_name'] ?? '')) ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </label>
            <label class="block text-sm font-medium text-slate-700">任务说明
                <textarea name="task_desc" rows="3" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><?= h((string) ($editing['task_desc'] ?? '')) ?></textarea>
            </label>
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="block text-sm font-medium text-slate-700">发布时间
                    <input type="datetime-local" name="start_time" required value="<?= h($startTime) ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </label>
                <label class="block text-sm font-medium text-slate-700">有效期（最长 <?= $maxDays ?> 天）
                    <select id="pub-expire-mode" name="expire_mode" class="mt-1 h-[38px] w-full rounded-lg border border-slate-300 px-3 text-sm">
                        <option value="three_days" <?= $expireMode === 'three_days' ? 'selected' : '' ?>>三天</option>
                        <option value="five_days" <?= $expireMode === 'five_days' ? 'selected' : '' ?>>五天</option>
                        <option value="one_week" <?= $expireMode === 'one_week' ? 'selected' : '' ?>>一周</option>
                        <option value="custom_days" <?= $expireMode === 'custom_days' ? 'selected' : '' ?>>自定义天数</option>
                        <?php if ($maxDays >= $UNL): ?>
                        <option value="long_term" <?= $expireMode === 'long_term' ? 'selected' : '' ?>>长期（不过期）</option>
                        <?php endif; ?>
                    </select>
                </label>
            </div>
            <label id="pub-custom-wrap" class="block text-sm font-medium text-slate-700" style="display:none">自定义天数
                <input id="pub-custom-days" type="number" name="custom_days" min="1" max="<?= $maxDays ?>" value="<?= h($customDays) ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </label>
            <label class="block text-sm font-medium text-slate-700">当月有效
                <select id="pub-current-month" name="current_month" data-max-days="<?= $maxDays >= $UNL ? -1 : $maxDays ?>" class="mt-1 h-[38px] w-full rounded-lg border border-slate-300 px-3 text-sm">
                    <option value="0" <?= $currentMonth === '1' ? '' : 'selected' ?>>否</option>
                    <option id="pub-current-month-yes" value="1" <?= $currentMonth === '1' ? 'selected' : '' ?>>是（发布当月生效，下月自动结束）</option>
                </select>
                <span id="pub-current-month-hint" class="mt-1 hidden text-xs text-rose-500"></span>
            </label>
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="block text-sm font-medium text-slate-700">最少图片数
                    <input type="number" name="min_images" min="1" max="10" value="<?= h((string) ($editing['min_images'] ?? '1')) ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </label>
                <label class="block text-sm font-medium text-slate-700">最多图片数
                    <input type="number" name="max_images" min="1" max="10" value="<?= h((string) ($editing['max_images'] ?? '3')) ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </label>
            </div>

            <div>
                <?php if ($groups !== []): ?>
                <div class="mb-3">
                    <span class="text-sm font-medium text-slate-700">按用户组批量添加</span>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <?php foreach ($groups as $g): $gid = (int)$g['id']; ?>
                            <label class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs text-slate-700 cursor-pointer hover:bg-blue-50 hover:border-blue-300">
                                <input type="checkbox" name="group_ids[]" value="<?= $gid ?>" <?= in_array($gid, $selectedGroupIds, true) ? 'checked' : '' ?> class="h-3.5 w-3.5 rounded border-slate-300 text-blue-600">
                                <span><?= h((string)$g['group_name']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <p class="mt-1 text-xs text-slate-400">勾选用户组后，组内所有成员将自动加入任务</p>
                </div>
                <?php endif; ?>
                <span class="text-sm font-medium text-slate-700">选择参与成员</span>
                <?php if ($members === []): ?>
                    <div class="mt-2 rounded-lg border border-dashed border-slate-300 bg-slate-50 px-4 py-4 text-sm text-slate-500">暂无成员，请先到“我的名单”添加</div>
                <?php else: ?>
                    <div class="mt-2 grid max-h-52 grid-cols-2 gap-2 overflow-y-auto rounded-lg border border-slate-200 p-3 sm:grid-cols-3">
                        <?php foreach ($members as $m): $mid = (int) $m['id']; ?>
                            <label class="inline-flex items-center gap-1.5 text-xs text-slate-700">
                                <input type="checkbox" name="member_ids[]" value="<?= $mid ?>" <?= in_array($mid, $selectedMemberIds, true) ? 'checked' : '' ?> class="h-3.5 w-3.5 rounded border-slate-300 text-blue-600">
                                <span class="truncate"><?= h((string) $m['name']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="flex gap-2">
                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">保存任务</button>
                <?php if ($taskId > 0): ?><a href="/my/tasks" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">取消编辑</a><?php endif; ?>
            </div>
        </form>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="mb-3 text-base font-bold text-slate-900">我发布的任务</h2>
        <?php if ($tasks === []): ?>
            <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">还没有发布任务</div>
        <?php else: ?>
            <div class="space-y-2">
                <?php foreach ($tasks as $t): ?>
                    <div class="flex items-center justify-between rounded-lg border border-slate-200 px-4 py-3">
                        <div class="min-w-0">
                            <div class="truncate text-sm font-medium text-slate-900"><?= h((string) $t['task_name']) ?></div>
                            <div class="text-xs text-slate-500"><?= (int) ($t['task_status'] ?? 0) === 1 ? '启用' : '停用' ?> · <?= h((string) ($t['start_time'] ?? '')) ?></div>
                        </div>
                        <div class="flex shrink-0 gap-2 text-xs">
                            <a href="/task/<?= h((string) $t['task_link_code']) ?>" target="_blank" class="text-slate-500 hover:underline">链接</a>
                            <a href="/my/tasks?edit_id=<?= (int) $t['id'] ?>" class="text-blue-600 hover:underline">编辑</a>
                            <form method="post" action="/my/tasks/delete" class="inline" onsubmit="return confirm('确定删除该任务及其打卡记录？')">
                                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                                <button type="submit" class="text-rose-600 hover:underline">删除</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
<script>
(function(){
    var mode = document.getElementById('pub-expire-mode');
    var wrap = document.getElementById('pub-custom-wrap');
    var custom = document.getElementById('pub-custom-days');
    var currentMonth = document.getElementById('pub-current-month');
    var cmYes = document.getElementById('pub-current-month-yes');
    var cmHint = document.getElementById('pub-current-month-hint');
    var startInput = document.querySelector('input[name="start_time"]');
    if (!mode || !wrap) return;

    var maxDays = currentMonth ? parseInt(currentMonth.getAttribute('data-max-days') || '-1', 10) : -1;

    // 计算发布日期所在月份的总天数（用于判断当月有效是否超出会员权限）。
    var daysInStartMonth = function(){
        var v = startInput ? startInput.value : '';
        var d = v ? new Date(v) : new Date();
        if (isNaN(d.getTime())) { d = new Date(); }
        return new Date(d.getFullYear(), d.getMonth() + 1, 0).getDate();
    };

    // 权限不足以覆盖当月所有天数时，禁用「当月有效」的「是」选项。
    var syncCurrentMonthAvailability = function(){
        if (!currentMonth || !cmYes) { return; }
        var dim = daysInStartMonth();
        var blocked = maxDays >= 0 && dim > maxDays;
        cmYes.disabled = blocked;
        if (blocked) {
            if (currentMonth.value === '1') { currentMonth.value = '0'; }
            if (cmHint) {
                cmHint.textContent = '本月共 ' + dim + ' 天，超过您的最长 ' + maxDays + ' 天权限，无法使用当月有效';
                cmHint.classList.remove('hidden');
            }
        } else if (cmHint) {
            cmHint.classList.add('hidden');
        }
    };

    var sync = function(){
        syncCurrentMonthAvailability();
        var monthOn = currentMonth && currentMonth.value === '1';
        // 「当月有效」优先：开启时禁用有效期与自定义天数。
        mode.disabled = monthOn;
        mode.classList.toggle('bg-slate-100', monthOn);
        mode.classList.toggle('text-slate-400', monthOn);
        var showCustom = !monthOn && mode.value === 'custom_days';
        wrap.style.display = showCustom ? 'block' : 'none';
        if (custom) { custom.disabled = !showCustom; }
    };
    mode.addEventListener('change', sync);
    if (currentMonth) { currentMonth.addEventListener('change', sync); }
    if (startInput) { startInput.addEventListener('change', sync); }
    sync();
})();
</script>
