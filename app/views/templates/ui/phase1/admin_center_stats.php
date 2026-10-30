<?php
$sections = is_array($sections ?? null) ? $sections : [];
$focusType = trim((string) ($focusType ?? 'task_count'));
$taskOptions = is_array($taskOptions ?? null) ? $taskOptions : [];
$selectedTaskId = (int) ($selectedTaskId ?? 0);
$currentTask = is_array($currentTask ?? null) ? $currentTask : null;
$availableTypes = [];
foreach ($sections as $item) {
    $type = trim((string) ($item['type'] ?? ''));
    if ($type !== '') {
        $availableTypes[] = $type;
    }
}
if (!in_array($focusType, $availableTypes, true) && $availableTypes !== []) {
    $focusType = $availableTypes[0];
}
?>
<div class="space-y-5">
    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
            <div class="space-y-1">
                <h2 class="text-lg font-semibold text-slate-900">看板统计详情</h2>
                <p class="text-sm text-slate-500">支持切换任务查看明细，并导出当前统计结果。</p>
            </div>
            <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center sm:justify-end">
                <form method="get" action="/admin/center/stats" class="flex items-center">
                    <input type="hidden" name="focus" value="<?= h($focusType) ?>">
                    <select id="stats-task-id" name="task_id" onchange="this.form.submit()" class="h-10 min-w-[320px] rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm outline-none transition focus:border-blue-400 focus:ring-2 focus:ring-blue-100">
                        <?php if ($taskOptions === []): ?>
                            <option value="0">暂无任务</option>
                        <?php else: ?>
                            <?php foreach ($taskOptions as $task): ?>
                                <?php
                                $taskId = (int) ($task['id'] ?? 0);
                                $taskName = trim((string) ($task['task_name'] ?? ('#' . $taskId)));
                                $statusText = ((int) ($task['is_hidden'] ?? 0) === 1) ? '已隐藏' : '前台显示中';
                                $optionLabel = $taskName . ' · ' . $statusText;
                                ?>
                                <option value="<?= $taskId ?>" <?= $taskId === $selectedTaskId ? 'selected' : '' ?>><?= h($optionLabel) ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </form>
                <a href="/admin/center/stats/monthly" class="inline-flex h-10 items-center justify-center rounded-lg border border-blue-300 bg-blue-50 px-4 text-sm font-semibold text-blue-700 transition hover:bg-blue-100">按月统计</a>
                <a href="/admin" class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-medium text-slate-700 transition hover:bg-slate-50">返回首页</a>
            </div>
        </div>
        <?php if ($currentTask !== null): ?>
            <?php
            $currentTaskName = trim((string) ($currentTask['task_name'] ?? ('#' . (int) ($currentTask['id'] ?? 0))));
            $currentTaskValidity = function_exists('format_task_validity_label') ? format_task_validity_label($currentTask) : '-';
            $currentTaskStatus = function_exists('validate_task_open_for_submit') ? validate_task_open_for_submit($currentTask) : null;
            if ($currentTaskStatus === null) {
                $currentTaskStatusText = '可提交';
                $currentTaskStatusClass = 'border-emerald-200 bg-emerald-50 text-emerald-700';
            } elseif ($currentTaskStatus === 'not_started') {
                $currentTaskStatusText = '未开始';
                $currentTaskStatusClass = 'border-amber-200 bg-amber-50 text-amber-700';
            } elseif ($currentTaskStatus === 'expired') {
                $currentTaskStatusText = '已失效';
                $currentTaskStatusClass = 'border-rose-200 bg-rose-50 text-rose-700';
            } else {
                $currentTaskStatusText = '不可提交';
                $currentTaskStatusClass = 'border-slate-200 bg-slate-100 text-slate-700';
            }
            $currentTaskDisplayText = ((int) ($currentTask['is_hidden'] ?? 0) === 1) ? '已隐藏' : '前台显示中';
            $currentTaskDisplayClass = ((int) ($currentTask['is_hidden'] ?? 0) === 1)
                ? 'border-slate-200 bg-slate-100 text-slate-700'
                : 'border-blue-200 bg-blue-50 text-blue-700';
            ?>
            <div class="mt-4 rounded-xl border border-blue-100 bg-gradient-to-r from-blue-50 via-sky-50 to-white p-4">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-[0.12em] text-blue-600">当前任务</p>
                        <h3 class="mt-1 truncate text-lg font-semibold text-slate-900"><?= h($currentTaskName) ?></h3>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex h-8 items-center rounded-full border px-3 text-xs font-semibold <?= h($currentTaskStatusClass) ?>"><?= h($currentTaskStatusText) ?></span>
                        <span class="inline-flex h-8 items-center rounded-full border px-3 text-xs font-semibold <?= h($currentTaskDisplayClass) ?>"><?= h($currentTaskDisplayText) ?></span>
                        <span class="inline-flex h-8 items-center rounded-full border border-slate-200 bg-white px-3 text-xs font-medium text-slate-600">有效期：<?= h($currentTaskValidity) ?></span>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <?php if ($sections !== []): ?>
            <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-4">
                <?php foreach ($sections as $item): ?>
                    <?php
                    $type = (string) ($item['type'] ?? '');
                    if ($type === '') { continue; }
                    $title = (string) ($item['title'] ?? $type);
                    $isFocus = $type === $focusType;
                    $focusUrl = '/admin/center/stats?focus=' . urlencode($type);
                    if ($selectedTaskId > 0) { $focusUrl .= '&task_id=' . $selectedTaskId; }
                    $focusUrl .= '#stat-' . rawurlencode($type);
                    ?>
                    <a href="<?= h($focusUrl) ?>" class="inline-flex h-9 items-center rounded-full border px-3.5 text-sm font-medium transition <?= $isFocus ? 'border-blue-200 bg-blue-50 text-blue-700 shadow-sm' : 'border-slate-200 bg-slate-50 text-slate-600 hover:bg-slate-100' ?>"><?= h($title) ?></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    <?php foreach ($sections as $item): ?>
        <?php
        $type = (string) ($item['type'] ?? '');
        if ($type === '') { continue; }
        $title = (string) ($item['title'] ?? $type);
        $description = (string) ($item['description'] ?? '');
        $count = $item['count'] ?? 0;
        $headers = is_array($item['headers'] ?? null) ? $item['headers'] : [];
        $rows = is_array($item['rows'] ?? null) ? $item['rows'] : [];
        $emptyText = (string) ($item['empty_text'] ?? '暂无数据');
        $isFocus = $type === $focusType;
        $countText = is_numeric($count) ? (string) $count : (string) $count;
        $exportUrl = '/admin/center/stats/export?type=' . urlencode($type);
        if ($selectedTaskId > 0) { $exportUrl .= '&task_id=' . $selectedTaskId; }
        $headerCount = max(1, count($headers));
        $columnWidths = match ($type) {
            'task_count' => ['96px', 'minmax(220px, 2.2fr)', '168px', '176px', '128px', '120px'],
            'new_task_checkins' => ['96px', '132px', '132px', '160px', '182px', '110px'],
            'new_task_uncheckins' => ['96px', '132px', '132px', '180px', '132px'],
            'compare_diff' => ['96px', '132px', '132px', '180px', '96px', '200px'],
            default => [],
        };
        ?>
        <section id="stat-<?= h($type) ?>" class="overflow-hidden rounded-xl border bg-white p-5 shadow-sm <?= $isFocus ? 'border-blue-200 ring-2 ring-blue-100' : 'border-slate-200' ?>">
            <div class="flex flex-col gap-3 border-b border-slate-100 pb-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="text-base font-semibold text-slate-900"><?= h($title) ?></h3>
                        <?php if ($isFocus): ?>
                            <span class="inline-flex h-6 items-center rounded-full bg-blue-50 px-2.5 text-xs font-semibold text-blue-700">当前查看</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($description !== ''): ?><p class="mt-1 text-sm text-slate-500"><?= h($description) ?></p><?php endif; ?>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex h-9 items-center rounded-full border border-slate-200 bg-slate-50 px-3 text-sm font-medium text-slate-700">统计值：<?= h($countText) ?></span>
                    <a href="<?= h($exportUrl) ?>" class="inline-flex h-9 items-center rounded-lg bg-blue-600 px-3.5 text-sm font-semibold text-white transition hover:bg-blue-700">导出明细</a>
                </div>
            </div>
            <?php if ($rows === []): ?>
                <div class="mt-4 rounded-xl border border-dashed border-slate-200 bg-slate-50/70 px-6 py-10 text-center">
                    <div class="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-white text-slate-400 shadow-sm">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.75 6.75h14.5M4.75 12h14.5m-14.5 5.25h9.5"/>
                        </svg>
                    </div>
                    <p class="mt-3 text-sm font-medium text-slate-700"><?= h($emptyText) ?></p>
                    <p class="mt-1 text-xs text-slate-500">当前筛选条件下暂无可展示明细。</p>
                </div>
            <?php else: ?>
                <div class="tw-table-wrap mt-4 overflow-x-auto rounded-xl border border-slate-200">
                    <table class="tw-table min-w-full divide-y divide-slate-200 text-sm">
                        <?php if ($columnWidths !== []): ?>
                            <colgroup>
                                <?php foreach ($headers as $idx => $header): ?>
                                    <col style="width: <?= h((string) ($columnWidths[$idx] ?? 'auto')) ?>;">
                                <?php endforeach; ?>
                            </colgroup>
                        <?php endif; ?>
                        <thead class="bg-slate-50">
                            <tr>
                                <?php foreach ($headers as $header): ?>
                                    <th class="whitespace-nowrap px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"><?= h((string) $header) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <?php foreach ($rows as $row): ?>
                                <?php $cells = is_array($row) ? array_values($row) : []; ?>
                                <tr class="hover:bg-slate-50">
                                    <?php foreach ($headers as $idx => $header): ?>
                                        <td class="px-4 py-3 align-middle text-slate-700">
                                            <div class="max-w-[420px] truncate whitespace-nowrap" title="<?= h((string) ($cells[$idx] ?? '')) ?>">
                                                <?= h((string) ($cells[$idx] ?? '-')) ?>
                                            </div>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($headerCount >= 6): ?>
                    <p class="mt-2 text-xs text-slate-400">表格内容较多时可左右滚动查看完整字段。</p>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
</div>
