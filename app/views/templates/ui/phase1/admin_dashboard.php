<?php
$stats = is_array($stats ?? null) ? $stats : [];
$taskCount = (int) ($stats['task_count'] ?? 0);
$memberCount = (int) ($stats['member_count'] ?? 0);
$newTaskCheckins = (int) ($stats['new_task_checkins'] ?? ($stats['today_checkins'] ?? 0));
$newTaskUncheckins = (int) ($stats['new_task_uncheckins'] ?? ($stats['today_uncheckins'] ?? 0));
$compareDiff = (int) ($stats['compare_diff'] ?? 0);
$latestTaskName = trim((string) ($stats['latest_task_name'] ?? ''));
$previousTaskName = trim((string) ($stats['previous_task_name'] ?? ''));
$recentRecords = is_array($stats['recent_records'] ?? null) ? $stats['recent_records'] : [];

$compareSign = $compareDiff > 0 ? '+' : '';
$compareTone = $compareDiff > 0 ? 'text-emerald-700' : ($compareDiff < 0 ? 'text-rose-700' : 'text-slate-700');
$cardLinks = [
    'task_count' => '/admin/center/stats?focus=task_count',
    'member_count' => '/admin/center/stats?focus=member_count',
    'new_task_checkins' => '/admin/center/stats?focus=new_task_checkins',
    'new_task_uncheckins' => '/admin/center/stats?focus=new_task_uncheckins',
    'compare_diff' => '/admin/center/stats?focus=compare_diff',
];
?>
<div class="space-y-5">
    <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
        <a href="<?= h($cardLinks['task_count']) ?>" class="group rounded-xl border border-blue-100 bg-blue-50/70 p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-blue-200 hover:shadow-md">
            <p class="text-sm font-medium text-slate-600">任务总数</p>
            <p class="mt-3 text-3xl font-bold text-slate-900"><?= $taskCount ?></p>
            <p class="mt-1 text-xs text-slate-500">点击查看任务明细并导出</p>
        </a>

        <a href="<?= h($cardLinks['member_count']) ?>" class="group rounded-xl border border-violet-100 bg-violet-50/70 p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-violet-200 hover:shadow-md">
            <p class="text-sm font-medium text-slate-600">成员总数</p>
            <p class="mt-3 text-3xl font-bold text-slate-900"><?= $memberCount ?></p>
            <p class="mt-1 text-xs text-slate-500">点击查看成员明细并导出</p>
        </a>

        <a href="<?= h($cardLinks['new_task_checkins']) ?>" class="group rounded-xl border border-emerald-100 bg-emerald-50/70 p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-emerald-200 hover:shadow-md">
            <p class="text-sm font-medium text-slate-600">任务已打卡人数</p>
            <p class="mt-3 text-3xl font-bold text-emerald-700"><?= $newTaskCheckins ?></p>
            <p class="mt-1 truncate text-xs text-slate-500" title="<?= h($latestTaskName !== '' ? $latestTaskName : '暂无最新任务') ?>">
                <?= h($latestTaskName !== '' ? ('任务：' . $latestTaskName) : '暂无最新任务') ?>
            </p>
        </a>

        <a href="<?= h($cardLinks['new_task_uncheckins']) ?>" class="group rounded-xl border border-amber-100 bg-amber-50/70 p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-amber-200 hover:shadow-md">
            <p class="text-sm font-medium text-slate-600">任务未打卡人数</p>
            <p class="mt-3 text-3xl font-bold text-amber-600"><?= $newTaskUncheckins ?></p>
            <p class="mt-1 text-xs text-slate-500">点击查看未打卡名单并导出</p>
        </a>

        <a href="<?= h($cardLinks['compare_diff']) ?>" class="group rounded-xl border border-cyan-100 bg-cyan-50/70 p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-cyan-200 hover:shadow-md">
            <p class="text-sm font-medium text-slate-600">与上期相比</p>
            <p class="mt-3 text-3xl font-bold <?= $compareTone ?>"><?= $compareSign . $compareDiff ?> 人</p>
            <p class="mt-1 truncate text-xs text-slate-500" title="<?= h($previousTaskName !== '' ? ('上期任务：' . $previousTaskName) : '暂无上期任务') ?>">
                <?= h($previousTaskName !== '' ? ('上期任务：' . $previousTaskName) : '暂无上期任务') ?>
            </p>
        </a>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="mb-4 flex items-center justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold text-slate-900">最近打卡记录</h2>
                <p class="text-sm text-slate-500">按提交时间倒序展示最近 10 条记录</p>
            </div>
            <a href="/admin/records" class="text-sm font-medium text-blue-600 transition hover:text-blue-700">查看全部</a>
        </div>

        <div class="tw-table-wrap overflow-x-auto">
            <table class="tw-table min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">提交时间</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">任务</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">人员</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    <?php if ($recentRecords === []): ?>
                        <tr>
                            <td colspan="3" class="px-4 py-8 text-center text-slate-500">暂无记录</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentRecords as $row): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="whitespace-nowrap px-4 py-3 text-slate-700"><?= h((string) ($row['submit_time'] ?? '-')) ?></td>
                                <td class="px-4 py-3 text-slate-700"><?= h((string) ($row['task_name'] ?? '-')) ?></td>
                                <td class="px-4 py-3 text-slate-700"><?= h((string) ($row['member_name'] ?? '-')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
