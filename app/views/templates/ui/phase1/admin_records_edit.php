<?php
$csrf = (string) ($csrf ?? '');
$record = is_array($record ?? null) ? $record : [];

$recordId = (int) ($record['id'] ?? 0);
$taskName = (string) ($record['task_name'] ?? '-');
$memberName = (string) ($record['member_name'] ?? '-');
$memberEmployeeNo = trim((string) ($record['member_employee_no'] ?? ''));
$submitTime = (string) ($record['submit_time'] ?? '-');
$taskDate = (string) ($record['task_date'] ?? date('Y-m-d'));
$remark = (string) ($record['remark'] ?? '');
$isValid = (int) ($record['is_valid'] ?? 1) === 1 ? '1' : '0';
$isDeleted = !empty($record['deleted_at']);
?>
<div class="space-y-5">
    <?php if ($isDeleted): ?>
        <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
            该记录已在回收站中，如需重新参与统计，请先恢复记录。
        </div>
    <?php endif; ?>

    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <dl class="grid gap-3 text-sm text-slate-600 md:grid-cols-3">
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
                <dt class="text-xs uppercase tracking-wide text-slate-500">提交时间</dt>
                <dd class="mt-1 font-medium text-slate-900"><?= h($submitTime) ?></dd>
            </div>
        </dl>
    </section>

    <form method="post" action="/admin/records/edit" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        <input type="hidden" name="id" value="<?= $recordId ?>">

        <div class="grid gap-4 md:grid-cols-2">
            <label class="block text-sm font-medium text-slate-700">打卡日期
                <input
                    type="date"
                    name="task_date"
                    value="<?= h($taskDate) ?>"
                    required
                    class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                >
            </label>

            <label class="block text-sm font-medium text-slate-700">记录状态
                <select name="is_valid" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                    <option value="1" <?= $isValid === '1' ? 'selected' : '' ?>>正常</option>
                    <option value="0" <?= $isValid === '0' ? 'selected' : '' ?>>已覆盖</option>
                </select>
            </label>
        </div>

        <label class="mt-4 block text-sm font-medium text-slate-700">备注
            <textarea name="remark" rows="4" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200"><?= h($remark) ?></textarea>
        </label>

        <div class="mt-5 flex flex-wrap items-center gap-2">
            <button type="submit" class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700">保存修改</button>
            <a href="/admin/records/detail?id=<?= $recordId ?>" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50">返回详情</a>
        </div>
    </form>
</div>
