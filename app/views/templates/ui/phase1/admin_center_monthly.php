<?php
$month = trim((string) ($month ?? date('Y-m')));
$stat = is_array($stat ?? null) ? $stat : [];
$title = (string) ($stat['title'] ?? '按月未打卡统计');
$description = (string) ($stat['description'] ?? '');
$taskCount = (int) ($stat['task_count'] ?? 0);
$count = (int) ($stat['count'] ?? 0);
$headers = is_array($stat['headers'] ?? null) ? $stat['headers'] : [];
$rows = is_array($stat['rows'] ?? null) ? $stat['rows'] : [];
$summary = is_array($stat['summary'] ?? null) ? $stat['summary'] : [];
$emptyText = (string) ($stat['empty_text'] ?? '暂无数据');
$exportUrl = '/admin/center/stats/monthly/export?month=' . urlencode($month);
// 主表：时间 / 任务名称 / 未打卡人员（最后一列为人员名单，需自动换行）。
$namesIdx = count($headers) - 1;
$columnWidths = ['170px', 'minmax(200px, 1.6fr)', 'minmax(240px, 3fr)'];
?>
<div class="space-y-5">
    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
            <div class="space-y-1">
                <h2 class="text-lg font-semibold text-slate-900">按月未打卡统计</h2>
                <p class="text-sm text-slate-500">按发布月份统计当月发布的打卡任务（含已停用），列出每个任务的未打卡人员及各成员合计次数。</p>
            </div>
            <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center sm:justify-end">
                <form method="get" action="/admin/center/stats/monthly" class="flex items-center gap-2">
                    <label class="text-sm font-medium text-slate-600">统计月份</label>
                    <input type="month" name="month" value="<?= h($month) ?>" onchange="this.form.submit()" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-700 shadow-sm outline-none transition focus:border-blue-400 focus:ring-2 focus:ring-blue-100">
                </form>
                <a href="/admin/center/stats" class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-medium text-slate-700 transition hover:bg-slate-50">返回统计中心</a>
            </div>
        </div>
        <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-4">
            <span class="inline-flex h-9 items-center rounded-full border border-slate-200 bg-slate-50 px-3 text-sm font-medium text-slate-700">当月发布任务：<?= $taskCount ?> 个</span>
            <span class="inline-flex h-9 items-center rounded-full border border-slate-200 bg-slate-50 px-3 text-sm font-medium text-slate-700">存在未打卡人数：<?= $count ?> 人</span>
        </div>
    </section>

    <section class="overflow-hidden rounded-xl border border-blue-200 bg-white p-5 shadow-sm ring-2 ring-blue-100">
        <div class="flex flex-col gap-3 border-b border-slate-100 pb-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h3 class="text-base font-semibold text-slate-900"><?= h($title) ?></h3>
                <?php if ($description !== ''): ?><p class="mt-1 text-sm text-slate-500"><?= h($description) ?></p><?php endif; ?>
            </div>
            <div class="flex flex-wrap items-center gap-2">
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
                <p class="mt-1 text-xs text-slate-500">切换上方月份可查看其它月份的统计。</p>
            </div>
        <?php else: ?>
            <div class="tw-table-wrap mt-4 overflow-x-auto rounded-xl border border-slate-200">
                <table class="tw-table min-w-full divide-y divide-slate-200 text-sm">
                    <colgroup>
                        <?php foreach ($headers as $idx => $header): ?>
                            <col style="width: <?= h((string) ($columnWidths[$idx] ?? 'auto')) ?>;">
                        <?php endforeach; ?>
                    </colgroup>
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
                                    <td class="px-4 py-3 align-top text-slate-700">
                                        <?php if ($idx === $namesIdx): ?>
                                            <div class="whitespace-normal leading-relaxed text-slate-600"><?= h((string) ($cells[$idx] ?? '-')) ?></div>
                                        <?php else: ?>
                                            <div class="whitespace-nowrap"><?= h((string) ($cells[$idx] ?? '-')) ?></div>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="mt-2 text-xs text-slate-400">「未打卡人员」列为该任务当次所有未打卡成员，多人以「、」分隔。</p>
        <?php endif; ?>
    </section>

    <?php if ($summary !== []): ?>
    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="border-b border-slate-100 pb-4">
            <h3 class="text-base font-semibold text-slate-900">本月未打卡合计</h3>
            <p class="mt-1 text-sm text-slate-500">统计每人本月累计未打卡次数（按次数由多到少）。</p>
        </div>
        <div class="mt-4 grid grid-cols-3 gap-2 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8">
            <?php foreach ($summary as $s): ?>
                <div class="truncate whitespace-nowrap border border-slate-200 px-3 py-1.5 text-center text-sm text-slate-700" title="<?= h((string) ($s['name'] ?? '')) ?> <?= (int) ($s['count'] ?? 0) ?>次"><?= h((string) ($s['name'] ?? '')) ?> <?= (int) ($s['count'] ?? 0) ?>次</div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
</div>
