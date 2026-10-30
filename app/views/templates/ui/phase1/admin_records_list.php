<?php
$filters = is_array($filters ?? null) ? $filters : [];
$tasks = is_array($tasks ?? null) ? $tasks : [];
$members = is_array($members ?? null) ? $members : [];
$rows = is_array($rows ?? null) ? $rows : [];
$csrf = (string) ($csrf ?? '');

$selectedTaskId = (int) ($filters['task_id'] ?? 0);
$selectedMemberId = (int) ($filters['member_id'] ?? 0);
$dateFrom = (string) ($filters['date_from'] ?? '');
$dateTo = (string) ($filters['date_to'] ?? '');
?>
<div class="space-y-5">
    <form id="records-filter-form" method="get" action="/admin/records" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="records-filter-row flex flex-wrap items-center gap-2 xl:flex-nowrap">
            <select name="task_id" class="h-10 min-w-[210px] rounded-lg border border-slate-300 px-3 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                <option value="0">全部任务</option>
                <?php foreach ($tasks as $task): ?>
                    <?php $taskId = (int) ($task['id'] ?? 0); ?>
                    <option value="<?= $taskId ?>" <?= $selectedTaskId === $taskId ? 'selected' : '' ?>>
                        <?= h((string) ($task['task_name'] ?? '未命名任务')) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="member_id" class="h-10 min-w-[180px] rounded-lg border border-slate-300 px-3 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                <option value="0">全部人员</option>
                <?php foreach ($members as $member): ?>
                    <?php $memberId = (int) ($member['id'] ?? 0); ?>
                    <option value="<?= $memberId ?>" <?= $selectedMemberId === $memberId ? 'selected' : '' ?>>
                        <?= h((string) ($member['name'] ?? '未命名人员')) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <input type="date" name="date_from" value="<?= h($dateFrom) ?>" class="h-10 min-w-[140px] rounded-lg border border-slate-300 px-3 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
            <input type="date" name="date_to" value="<?= h($dateTo) ?>" class="h-10 min-w-[140px] rounded-lg border border-slate-300 px-3 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200">

            <button type="submit" class="inline-flex h-10 items-center justify-center whitespace-nowrap rounded-lg bg-blue-600 px-3.5 text-sm font-medium text-white transition hover:bg-blue-700">筛选</button>
            <a href="/admin/records" class="inline-flex h-10 items-center justify-center whitespace-nowrap rounded-lg border border-slate-300 bg-white px-3.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50">重置</a>
        </div>
    </form>

    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <p class="text-sm text-slate-500">当前记录：<?= count($rows) ?> 条</p>
            <div class="inline-flex items-center gap-2 whitespace-nowrap">
                <form id="recordsBatchSoftDeleteForm" method="post" action="/admin/records/batch-delete" class="inline-flex items-center">
                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                    <input id="recordsBatchDeleteIds" type="hidden" name="record_ids" value="">
                    <button
                        id="recordsBatchDeleteButton"
                        type="submit"
                        class="inline-flex h-9 items-center rounded-lg border border-rose-200 bg-rose-50 px-3 text-xs font-semibold text-rose-700 transition hover:bg-rose-100 disabled:cursor-not-allowed disabled:border-slate-200 disabled:bg-slate-100 disabled:text-slate-400"
                        disabled
                    >
                        批量软删除
                    </button>
                </form>

                <form id="recordsBatchExportForm" method="post" action="/admin/records/export" class="inline-flex items-center">
                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                    <input id="recordsBatchExportIds" type="hidden" name="record_ids" value="">
                    <button
                        id="recordsBatchExportButton"
                        type="submit"
                        class="inline-flex h-9 items-center rounded-lg border border-blue-200 bg-blue-50 px-3 text-xs font-semibold text-blue-700 transition hover:bg-blue-100 disabled:cursor-not-allowed disabled:border-slate-200 disabled:bg-slate-100 disabled:text-slate-400"
                        disabled
                    >
                        批量导出 XLSX
                    </button>
                </form>
            </div>
        </div>

        <div class="tw-table-wrap overflow-x-auto">
            <table class="tw-table min-w-[1040px] w-full table-fixed divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="w-[44px] px-3 py-3 text-left"><input id="recordsSelectAll" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"></th>
                        <th class="w-[92px] whitespace-nowrap px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">记录编号</th>
                        <th class="w-[162px] whitespace-nowrap px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">提交时间</th>
                        <th class="w-[280px] whitespace-nowrap px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">任务</th>
                        <th class="w-[120px] whitespace-nowrap px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">人员</th>
                        <th class="w-[120px] whitespace-nowrap px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">打卡日期</th>
                        <th class="w-[74px] whitespace-nowrap px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">图片数</th>
                        <th class="w-[110px] whitespace-nowrap px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">状态</th>
                        <th class="w-[160px] whitespace-nowrap px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">操作</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    <?php if ($rows === []): ?>
                        <tr>
                            <td colspan="9" class="px-4 py-8 text-center text-slate-500">暂无记录</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <?php
                            $id = (int) ($row['id'] ?? 0);
                            $isOverride = (int) ($row['is_overridden'] ?? 0) === 1;
                            ?>
                            <tr class="hover:bg-slate-50">
                                <td class="px-3 py-3">
                                    <input type="checkbox" class="record-row-checkbox h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500" value="<?= $id ?>">
                                </td>
                                <td class="px-3 py-3 text-slate-700 whitespace-nowrap">#<?= $id ?></td>
                                <td class="px-3 py-3 text-slate-700 whitespace-nowrap"><div class="truncate"><?= h((string) ($row['submit_time'] ?? '-')) ?></div></td>
                                <td class="px-3 py-3 text-slate-700 whitespace-nowrap"><div class="truncate" title="<?= h((string) ($row['task_name'] ?? '-')) ?>"><?= h((string) ($row['task_name'] ?? '-')) ?></div></td>
                                <td class="px-3 py-3 text-slate-700 whitespace-nowrap"><div class="truncate" title="<?= h((string) ($row['member_name'] ?? '-')) ?>"><?= h((string) ($row['member_name'] ?? '-')) ?></div></td>
                                <td class="px-3 py-3 text-slate-700 whitespace-nowrap"><?= h((string) ($row['task_date'] ?? '-')) ?></td>
                                <td class="px-3 py-3 text-slate-700 whitespace-nowrap"><?= (int) ($row['image_count'] ?? 0) ?></td>
                                <td class="px-3 py-3 whitespace-nowrap">
                                    <?php if ($isOverride): ?>
                                        <span class="inline-flex items-center rounded-full border border-blue-200 bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700">已覆盖</span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">正常</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-3 py-2 align-middle whitespace-nowrap">
                                    <div class="flex flex-nowrap items-center gap-1.5">
                                        <a href="/admin/records/detail?id=<?= $id ?>" class="inline-flex h-7 items-center rounded-md border border-slate-300 px-2 text-xs font-medium leading-none text-slate-700 transition hover:bg-slate-100">详情</a>
                                        <a href="/admin/records/edit?id=<?= $id ?>" class="inline-flex h-7 items-center rounded-md border border-slate-300 px-2 text-xs font-medium leading-none text-slate-700 transition hover:bg-slate-100">编辑</a>
                                        <form method="post" action="/admin/records/delete" class="inline-flex items-center" onsubmit="return confirm('确认软删除该记录吗？');">
                                            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                            <input type="hidden" name="id" value="<?= $id ?>">
                                            <button type="submit" class="inline-flex h-7 items-center rounded-md border border-rose-200 bg-rose-50 px-2 text-xs font-medium leading-none text-rose-700 transition hover:bg-rose-100">软删除</button>
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

<style>
@media (min-width: 1280px) {
    #records-filter-form .records-filter-row {
        flex-wrap: nowrap;
    }

    #records-filter-form .records-filter-row > * {
        flex-shrink: 0;
    }
}
</style>

<script>
(() => {
    const selectAll = document.getElementById('recordsSelectAll');
    const exportForm = document.getElementById('recordsBatchExportForm');
    const exportIdsInput = document.getElementById('recordsBatchExportIds');
    const exportButton = document.getElementById('recordsBatchExportButton');
    const deleteForm = document.getElementById('recordsBatchSoftDeleteForm');
    const deleteIdsInput = document.getElementById('recordsBatchDeleteIds');
    const deleteButton = document.getElementById('recordsBatchDeleteButton');
    const checkboxes = Array.from(document.querySelectorAll('.record-row-checkbox'));

    const selectedIds = () =>
        checkboxes
            .filter((item) => item.checked)
            .map((item) => parseInt(item.value || '0', 10))
            .filter((id) => Number.isInteger(id) && id > 0);

    const refresh = () => {
        const checked = selectedIds();
        if (exportButton) {
            exportButton.disabled = checked.length === 0;
        }
        if (deleteButton) {
            deleteButton.disabled = checked.length === 0;
        }
        if (selectAll) {
            selectAll.checked = checkboxes.length > 0 && checked.length === checkboxes.length;
            selectAll.indeterminate = checked.length > 0 && checked.length < checkboxes.length;
        }
    };

    if (selectAll) {
        selectAll.addEventListener('change', () => {
            const next = selectAll.checked;
            checkboxes.forEach((item) => {
                item.checked = next;
            });
            refresh();
        });
    }

    checkboxes.forEach((item) => {
        item.addEventListener('change', refresh);
    });

    if (exportForm && exportIdsInput) {
        exportForm.addEventListener('submit', (event) => {
            const ids = selectedIds();
            if (ids.length === 0) {
                event.preventDefault();
                return;
            }
            exportIdsInput.value = ids.join(',');
        });
    }

    if (deleteForm && deleteIdsInput) {
        deleteForm.addEventListener('submit', (event) => {
            const ids = selectedIds();
            if (ids.length === 0) {
                event.preventDefault();
                return;
            }
            if (!window.confirm('确认批量软删除选中的打卡记录吗？')) {
                event.preventDefault();
                return;
            }
            deleteIdsInput.value = ids.join(',');
        });
    }

    refresh();
})();
</script>
