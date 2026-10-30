<?php
$records = is_array($records ?? null) ? $records : [];
$tasks = is_array($tasks ?? null) ? $tasks : [];
$filters = is_array($filters ?? null) ? $filters : [];
$currentUser = $currentUser ?? null;
$page = (int) ($page ?? 1);
$totalPages = (int) ($totalPages ?? 1);
?>
<div class="mx-auto w-full max-w-4xl space-y-4 pb-10">
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h1 class="text-xl font-bold text-slate-900">我的打卡记录</h1>
        <p class="mt-1 text-sm text-slate-500">查看您的历史打卡记录</p>

        <form method="get" action="/my/records" class="mt-4 flex flex-wrap items-end gap-3">
            <label class="text-sm text-slate-600">
                任务
                <select name="task_id" class="mt-1 block w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                    <option value="0">全部任务</option>
                    <?php foreach ($tasks as $t): ?>
                        <option value="<?= (int) $t['id'] ?>" <?= (int) ($filters['task_id'] ?? 0) === (int) $t['id'] ? 'selected' : '' ?>><?= h((string) $t['task_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="text-sm text-slate-600">
                开始日期
                <input type="date" name="date_from" value="<?= h((string) ($filters['date_from'] ?? '')) ?>" class="mt-1 block rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
            </label>
            <label class="text-sm text-slate-600">
                结束日期
                <input type="date" name="date_to" value="<?= h((string) ($filters['date_to'] ?? '')) ?>" class="mt-1 block rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
            </label>
            <button type="submit" class="rounded-lg bg-blue-600 px-4 py-1.5 text-sm font-semibold text-white hover:bg-blue-700">筛选</button>
        </form>
    </section>

    <?php if ($records === []): ?>
        <section class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
            <p class="text-slate-500">暂无打卡记录</p>
        </section>
    <?php else: ?>
        <section class="space-y-3">
            <?php foreach ($records as $r): ?>
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-semibold text-slate-900"><?= h((string) ($r['task_name'] ?? '')) ?></span>
                        <span class="rounded-full px-2 py-0.5 text-xs font-medium <?= (int) ($r['is_valid'] ?? 1) === 1 ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700' ?>">
                            <?= (int) ($r['is_valid'] ?? 1) === 1 ? '有效' : '无效' ?>
                        </span>
                    </div>
                    <div class="mt-2 grid grid-cols-2 gap-2 text-xs text-slate-500">
                        <span>打卡时间：<?= h((string) ($r['created_at'] ?? '')) ?></span>
                        <span>任务日期：<?= h((string) ($r['task_date'] ?? '')) ?></span>
                    </div>
                    <?php if (!empty($r['remark'])): ?>
                        <p class="mt-2 text-xs text-slate-600">备注：<?= h((string) $r['remark']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($r['images'])): ?>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <?php foreach ($r['images'] as $img): ?>
                                <img src="<?= h((string) $img['image_path']) ?>" alt="" class="h-16 w-16 rounded-lg object-cover border border-slate-200">
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </section>

        <?php if ($totalPages > 1): ?>
            <nav class="flex items-center justify-center gap-2 pt-2">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&task_id=<?= (int) ($filters['task_id'] ?? 0) ?>&date_from=<?= h((string) ($filters['date_from'] ?? '')) ?>&date_to=<?= h((string) ($filters['date_to'] ?? '')) ?>" class="rounded-lg border border-slate-300 px-3 py-1 text-sm hover:bg-slate-50">上一页</a>
                <?php endif; ?>
                <span class="text-sm text-slate-500"><?= $page ?> / <?= $totalPages ?></span>
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>&task_id=<?= (int) ($filters['task_id'] ?? 0) ?>&date_from=<?= h((string) ($filters['date_from'] ?? '')) ?>&date_to=<?= h((string) ($filters['date_to'] ?? '')) ?>" class="rounded-lg border border-slate-300 px-3 py-1 text-sm hover:bg-slate-50">下一页</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>
