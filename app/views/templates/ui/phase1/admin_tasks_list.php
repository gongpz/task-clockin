<?php
$tasks = is_array($tasks ?? null) ? $tasks : [];
$keyword = (string) ($keyword ?? '');
$csrf = (string) ($csrf ?? '');

$formatDate = static function (string $value): string {
    $value = trim($value);
    if ($value === '' || $value === '-') {
        return $value === '' ? '-' : $value;
    }
    $ts = strtotime($value);
    return $ts === false ? $value : date('Y-m-d H:i', $ts);
};
?>
<div class="space-y-5">
    <form method="get" action="/admin/tasks" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex flex-wrap items-center gap-2">
            <input
                type="text"
                name="keyword"
                value="<?= h($keyword) ?>"
                placeholder="搜索任务名称 / 描述 / 链接码"
                class="w-full min-w-[220px] flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
            >
            <button type="submit" class="inline-flex items-center rounded-lg bg-blue-600 px-3.5 py-2 text-sm font-medium text-white transition hover:bg-blue-700">筛选</button>
            <a href="/admin/tasks" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">重置</a>
            <a href="/admin/tasks?create=1" class="inline-flex items-center rounded-lg bg-blue-50 px-3.5 py-2 text-sm font-medium text-blue-700 transition hover:bg-blue-100">新建任务</a>
        </div>
    </form>

    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="tw-table-wrap overflow-x-auto">
            <table class="tw-table min-w-[980px] w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">编号</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">任务名称</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">发布时间</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">有效期</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">前台状态</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">外部链接</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">操作</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    <?php if ($tasks === []): ?>
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-slate-500">暂无任务</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($tasks as $task): ?>
                            <?php
                            $id = (int) ($task['id'] ?? 0);
                            $name = (string) ($task['task_name'] ?? '未命名任务');
                            $code = (string) ($task['task_link_code'] ?? '');
                            $createdAt = $formatDate((string) ($task['created_at'] ?? '-'));
                            $enabled = (int) ($task['task_status'] ?? 1) === 1;
                            $isLongTerm = (int) ($task['is_long_term'] ?? 0) === 1;
                            $endTimeRaw = (string) ($task['end_time'] ?? '');
                            $expireText = $isLongTerm || $endTimeRaw === '' ? '长期有效' : $formatDate($endTimeRaw);
                            $publicBadgeClass = $enabled
                                ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                : 'border-slate-200 bg-slate-100 text-slate-600';
                            $publicBadgeText = $enabled ? '前台显示中' : '已隐藏';
                            $path = $code !== '' ? '/task/' . $code : '';
                            ?>
                            <tr class="hover:bg-slate-50">
                                <td class="whitespace-nowrap px-4 py-3 text-slate-700">#<?= $id ?></td>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-slate-800"><?= h($name) ?></div>
                                    <div class="mt-1 text-xs text-slate-500">链接码：<?= h($code !== '' ? $code : '-') ?></div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-slate-700"><?= h($createdAt) ?></td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium <?= $isLongTerm || $endTimeRaw === '' ? 'border-blue-200 bg-blue-50 text-blue-700' : 'border-amber-200 bg-amber-50 text-amber-700' ?>"><?= h($expireText) ?></span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium <?= $publicBadgeClass ?>"><?= $publicBadgeText ?></span>
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-700">
                                    <?php if ($path === ''): ?>
                                        <span class="text-slate-400">-</span>
                                    <?php else: ?>
                                        <a href="<?= h($path) ?>" target="_blank" class="text-blue-600 hover:text-blue-700"><?= h($path) ?></a>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-nowrap items-center gap-2 whitespace-nowrap">
                                        <a href="/admin/tasks?edit_id=<?= $id ?>" class="inline-flex items-center rounded-md border border-slate-300 px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-100">编辑</a>
                                        <?php if ($path !== ''): ?>
                                            <button type="button" data-copy-link="<?= h($path) ?>" class="inline-flex items-center rounded-md border border-blue-200 bg-blue-50 px-2.5 py-1.5 text-xs font-medium text-blue-700 transition hover:bg-blue-100">复制链接</button>
                                        <?php endif; ?>

                                        <?php if ($enabled): ?>
                                            <form method="post" action="/admin/tasks/hide" class="inline-flex">
                                                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                                <input type="hidden" name="id" value="<?= $id ?>">
                                                <button type="submit" class="inline-flex items-center rounded-md border border-amber-200 bg-amber-50 px-2.5 py-1.5 text-xs font-medium text-amber-700 transition hover:bg-amber-100">隐藏任务</button>
                                            </form>
                                        <?php else: ?>
                                            <form method="post" action="/admin/tasks/show" class="inline-flex">
                                                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                                <input type="hidden" name="id" value="<?= $id ?>">
                                                <button type="submit" class="inline-flex items-center rounded-md border border-emerald-200 bg-emerald-50 px-2.5 py-1.5 text-xs font-medium text-emerald-700 transition hover:bg-emerald-100">恢复显示</button>
                                            </form>
                                        <?php endif; ?>

                                        <form
                                            method="post"
                                            action="/admin/tasks/delete"
                                            class="inline-flex"
                                            onsubmit="return confirm('确认删除该任务吗？') && confirm('删除后会一并清空该任务所有打卡记录和图片，且无法恢复，是否继续？');"
                                        >
                                            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                            <input type="hidden" name="id" value="<?= $id ?>">
                                            <button type="submit" class="inline-flex items-center rounded-md border border-rose-200 bg-rose-50 px-2.5 py-1.5 text-xs font-medium text-rose-700 transition hover:bg-rose-100">删除任务</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>