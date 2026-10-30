<?php
$record = is_array($record ?? null) ? $record : [];
$images = is_array($images ?? null) ? $images : [];
$statusText = (string) ($statusText ?? '-');
$submitSourceLabel = (string) ($submitSourceLabel ?? '-');

$recordId = (int) ($record['id'] ?? 0);
$taskName = (string) ($record['task_name'] ?? '-');
$memberName = (string) ($record['member_name'] ?? '-');
$memberEmployeeNo = trim((string) ($record['member_employee_no'] ?? ''));
$taskDate = (string) ($record['task_date'] ?? '-');
$submitTime = (string) ($record['submit_time'] ?? '-');
$clientIp = (string) ($record['client_ip'] ?? '');
$remark = (string) ($record['remark'] ?? '');
$deletedAt = (string) ($record['deleted_at'] ?? '');
?>
<div class="space-y-5">
    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <h2 class="text-base font-semibold text-slate-900">记录详情 #<?= $recordId ?></h2>
            <div class="flex items-center gap-2">
                <a href="/admin/records/edit?id=<?= $recordId ?>" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">编辑</a>
                <a href="/admin/records" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">返回列表</a>
            </div>
        </div>

        <dl class="grid gap-3 text-sm text-slate-600 md:grid-cols-2 xl:grid-cols-3">
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500">任务</dt>
                <dd class="mt-1 font-medium text-slate-900"><?= h($taskName) ?></dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500">人员</dt>
                <dd class="mt-1 font-medium text-slate-900">
                    <?= h($memberName) ?>
                    <?php if ($memberEmployeeNo !== ''): ?>
                        <span class="ml-2 text-xs text-slate-500">工号：<?= h($memberEmployeeNo) ?></span>
                    <?php endif; ?>
                </dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500">打卡日期</dt>
                <dd class="mt-1 font-medium text-slate-900"><?= h($taskDate) ?></dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500">提交时间</dt>
                <dd class="mt-1 font-medium text-slate-900"><?= h($submitTime) ?></dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500">来源</dt>
                <dd class="mt-1 font-medium text-slate-900"><?= h($submitSourceLabel) ?></dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500">状态</dt>
                <dd class="mt-1 font-medium text-slate-900"><?= h($statusText) ?></dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500">IP 地址</dt>
                <dd class="mt-1 font-medium text-slate-900"><?= h($clientIp !== '' ? $clientIp : '-') ?></dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500">删除时间</dt>
                <dd class="mt-1 font-medium text-slate-900"><?= h($deletedAt !== '' ? $deletedAt : '-') ?></dd>
            </div>
            <div class="md:col-span-2 xl:col-span-1">
                <dt class="text-xs uppercase tracking-wide text-slate-500">备注</dt>
                <dd class="mt-1 break-words font-medium text-slate-900"><?= h($remark !== '' ? $remark : '-') ?></dd>
            </div>
        </dl>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-base font-semibold text-slate-900">打卡图片</h2>
        <?php if ($images === []): ?>
            <div class="mt-3 rounded-lg border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                暂无图片
            </div>
        <?php else: ?>
            <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                <?php foreach ($images as $img): ?>
                    <?php
                    $imagePath = (string) ($img['image_path'] ?? '');
                    if ($imagePath === '') {
                        continue;
                    }
                    ?>
                    <a href="<?= h($imagePath) ?>" target="_blank" class="group block overflow-hidden rounded-lg border border-slate-200 bg-slate-50">
                        <img src="<?= h($imagePath) ?>" alt="checkin-image" class="h-32 w-full object-cover transition group-hover:scale-[1.02]">
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
