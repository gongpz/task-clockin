<?php
$csrf = (string) ($csrf ?? '');
$keyword = (string) ($keyword ?? '');
$members = is_array($members ?? null) ? $members : [];
$groups = is_array($groups ?? null) ? $groups : [];
$editing = is_array($editing ?? null) ? $editing : null;
$editingGroupIds = is_array($editingGroupIds ?? null) ? $editingGroupIds : [];

$editId = (int) ($editing['id'] ?? 0);
$isEditing = $editId > 0;
$formAction = $isEditing ? '/admin/members/update' : '/admin/members';
$formTitle = $isEditing ? '编辑人员' : '新增人员';

$nameValue = (string) ($editing['name'] ?? '');
$employeeNoValue = (string) ($editing['employee_no'] ?? '');
$departmentValue = (string) ($editing['department'] ?? '');
$mobileValue = (string) ($editing['mobile'] ?? '');
$positionValue = (string) ($editing['position'] ?? '');
$remarkValue = (string) ($editing['remark'] ?? '');
$statusValue = (string) ($editing['status'] ?? '1');

$quickPositions = ['医生', '护士', '护理员', '行政', '护理主管', '人事'];
$groupNameMap = [];
foreach ($groups as $group) {
    $gid = (int) ($group['id'] ?? 0);
    if ($gid <= 0) {
        continue;
    }
    $groupNameMap[$gid] = (string) ($group['group_name'] ?? '未命名分组');
}
$selectedGroupNames = [];
foreach ($editingGroupIds as $gid) {
    $gid = (int) $gid;
    if ($gid > 0 && isset($groupNameMap[$gid])) {
        $selectedGroupNames[] = $groupNameMap[$gid];
    }
}
$groupSummaryText = $selectedGroupNames === [] ? '请选择分组（可多选）' : implode('、', $selectedGroupNames);
?>
<div class="space-y-5">
    <section class="grid gap-4 lg:grid-cols-[0.56fr_1.44fr]">
        <form id="member-search-form" method="get" action="/admin/members" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <h2 class="text-sm font-semibold text-slate-900">搜索人员</h2>
            <div class="member-tools-row mt-3 flex flex-wrap items-center gap-2 sm:flex-nowrap">
                <input
                    type="text"
                    name="keyword"
                    value="<?= h($keyword) ?>"
                    placeholder="姓名 / 电话"
                    class="h-10 w-full min-w-[176px] flex-1 rounded-lg border border-slate-300 px-3 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                >
                <button type="submit" class="inline-flex h-10 items-center whitespace-nowrap rounded-lg bg-blue-600 px-3.5 text-sm font-medium text-white transition hover:bg-blue-700">搜索</button>
                <a href="/admin/members" class="inline-flex h-10 items-center whitespace-nowrap rounded-lg border border-slate-300 bg-white px-3.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50">重置</a>
            </div>
        </form>

        <form id="member-import-form" method="post" action="/admin/members/import" enctype="multipart/form-data" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
            <h2 class="text-sm font-semibold text-slate-900">上传名单</h2>

            <div class="member-tools-row mt-3 flex flex-wrap items-center gap-2 lg:flex-nowrap">
                <input
                    type="file"
                    name="member_excel"
                    accept=".xlsx,.csv"
                    required
                    class="h-10 w-full min-w-[250px] max-w-[352px] flex-1 rounded-lg border border-slate-300 px-3 text-sm text-slate-700 file:mr-3 file:h-full file:rounded-md file:border-0 file:bg-blue-50 file:px-3 file:text-sm file:font-medium file:text-blue-700 hover:file:bg-blue-100"
                    style="line-height:40px;"
                >

                <select name="duplicate_mode" class="h-10 min-w-[190px] rounded-lg border border-slate-300 px-3 pr-7 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                    <option value="skip">手机号重复时跳过</option>
                    <option value="update">手机号重复时更新</option>
                </select>

                <button id="member-import-submit" type="submit" class="inline-flex h-10 items-center whitespace-nowrap rounded-lg bg-blue-600 px-3.5 text-sm font-medium text-white transition hover:bg-blue-700">上传导入</button>
                <a href="/admin/members/import-template" class="inline-flex h-10 items-center whitespace-nowrap rounded-lg border border-slate-300 bg-white px-3.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50">下载模板</a>
                <button type="button" id="member-add-btn" class="inline-flex h-10 items-center whitespace-nowrap rounded-lg bg-emerald-600 px-3.5 text-sm font-medium text-white transition hover:bg-emerald-700">新增人员</button>
            </div>

            <p class="mt-2 text-xs text-slate-500">Excel 列顺序：第1列姓名（必填）、第2列部门、第3列职位、第4列电话。</p>
        </form>
    </section>

    <form id="member-batch-form" method="post" action="/admin/members/batch" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        <input type="hidden" name="member_ids" id="member-batch-member-ids" value="">

        <div class="member-tools-row flex flex-wrap items-center gap-2 lg:flex-nowrap">
            <select id="member-batch-action" name="batch_action" class="h-10 min-w-[230px] rounded-lg border border-slate-300 px-3 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                <option value="">请选择批量操作</option>
                <option value="enable">批量启用</option>
                <option value="disable">批量停用</option>
                <option value="delete">批量删除</option>
                <option value="assign_group">批量加入分组</option>
            </select>

            <select id="member-batch-group-id" name="group_id" class="h-10 min-w-[230px] rounded-lg border border-slate-300 px-3 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                <option value="">选择分组（加入分组时必选）</option>
                <?php foreach ($groups as $group): ?>
                    <?php $gid = (int) ($group['id'] ?? 0); ?>
                    <?php if ($gid <= 0) { continue; } ?>
                    <option value="<?= $gid ?>"><?= h((string) ($group['group_name'] ?? '未命名分组')) ?></option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="inline-flex h-10 items-center whitespace-nowrap rounded-lg bg-blue-600 px-3.5 text-sm font-medium text-white transition hover:bg-blue-700">执行批量操作</button>
            <span class="text-sm text-slate-500 whitespace-nowrap">已选 <strong id="member-selected-count" class="text-slate-700">0</strong> 人</span>
        </div>
    </form>

    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="tw-table-wrap overflow-x-auto">
            <table class="tw-table min-w-[1080px] w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="w-12 px-4 py-3 text-left"><input type="checkbox" id="member-check-all" class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"></th>
                        <th class="w-16 px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">编号</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">姓名</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">电话</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">职位</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">工号</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">部门</th>
                        <th class="w-[220px] px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">分组</th>
                        <th class="w-[100px] px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">状态</th>
                        <th class="w-[240px] px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">操作</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    <?php if ($members === []): ?>
                        <tr>
                            <td colspan="10" class="px-4 py-8 text-center text-slate-500">暂无人员</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($members as $member): ?>
                            <?php
                            $id = (int) ($member['id'] ?? 0);
                            $groupNames = trim((string) ($member['group_names'] ?? ''));
                            $groupList = $groupNames === '' ? [] : array_values(array_filter(array_map('trim', explode(',', $groupNames)), static fn (string $v): bool => $v !== ''));
                            $enabled = (int) ($member['status'] ?? 0) === 1;
                            $toggleLabel = $enabled ? '停用' : '启用';
                            ?>
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3"><input type="checkbox" class="member-row-checkbox h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500" value="<?= $id ?>"></td>
                                <td class="whitespace-nowrap px-4 py-3 text-slate-700">#<?= $id ?></td>
                                <td class="px-4 py-3 text-slate-700"><?= h((string) ($member['name'] ?? '-')) ?></td>
                                <td class="whitespace-nowrap px-4 py-3 text-slate-700"><?= h((string) ($member['mobile'] ?? '-')) ?></td>
                                <td class="whitespace-nowrap px-4 py-3 text-slate-700"><?= h((string) ($member['position'] ?? '-')) ?></td>
                                <td class="whitespace-nowrap px-4 py-3 text-slate-700"><?= h((string) ($member['employee_no'] ?? '-')) ?></td>
                                <td class="whitespace-nowrap px-4 py-3 text-slate-700"><?= h((string) ($member['department'] ?? '-')) ?></td>
                                <td class="px-4 py-3">
                                    <?php if ($groupList === []): ?>
                                        <span class="text-slate-400">未分组</span>
                                    <?php else: ?>
                                        <div class="flex flex-wrap gap-1">
                                            <?php foreach ($groupList as $groupName): ?>
                                                <span class="inline-flex items-center rounded-full border border-blue-200 bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700"><?= h($groupName) ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3">
                                    <?php if ($enabled): ?>
                                        <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">启用</span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">停用</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-nowrap items-center gap-2 whitespace-nowrap">
                                        <button type="button" class="member-edit-btn inline-flex items-center rounded-md border border-slate-300 px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-100" data-id="<?= $id ?>" data-name="<?= h((string) ($member['name'] ?? '')) ?>" data-mobile="<?= h((string) ($member['mobile'] ?? '')) ?>" data-employee-no="<?= h((string) ($member['employee_no'] ?? '')) ?>" data-department="<?= h((string) ($member['department'] ?? '')) ?>" data-position="<?= h((string) ($member['position'] ?? '')) ?>" data-status="<?= (int) ($member['status'] ?? 1) ?>" data-remark="<?= h((string) ($member['remark'] ?? '')) ?>" data-group-ids="<?= h(implode(',', array_map('intval', is_array($member['group_ids'] ?? null) ? $member['group_ids'] : []))) ?>">编辑</button>
                                        <form method="post" action="/admin/members/toggle" class="inline-flex">
                                            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                            <input type="hidden" name="id" value="<?= $id ?>">
                                            <button type="submit" class="inline-flex items-center rounded-md border border-slate-300 px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-100"><?= h($toggleLabel) ?></button>
                                        </form>
                                        <form method="post" action="/admin/members/delete" class="inline-flex" onsubmit="return confirm('确认删除该人员吗？删除后默认列表不显示。');">
                                            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                            <input type="hidden" name="id" value="<?= $id ?>">
                                            <button type="submit" class="inline-flex items-center rounded-md border border-rose-200 bg-rose-50 px-2.5 py-1.5 text-xs font-medium text-rose-700 transition hover:bg-rose-100">删除</button>
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

    <!-- Member Add/Edit Modal -->
    <div id="member-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm p-4" style="display:none;">
        <div class="w-full max-w-lg max-h-[90vh] overflow-y-auto rounded-2xl border border-slate-200 bg-white p-6 shadow-xl">
            <div class="flex items-center justify-between mb-4">
                <h2 id="member-modal-title" class="text-base font-semibold text-slate-900"><?= h($formTitle) ?></h2>
                <button type="button" id="member-modal-close" class="rounded-lg border border-slate-200 bg-slate-50 px-2 py-1 text-sm text-slate-500 hover:bg-slate-100">✕</button>
            </div>
            <form id="member-modal-form" method="post" action="<?= h($formAction) ?>" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                <input type="hidden" name="id" id="member-modal-id" value="<?= $editId ?>">

                <div class="grid gap-3 md:grid-cols-2">
                    <label class="block text-sm font-medium text-slate-700">姓名
                        <input type="text" name="name" id="member-modal-name" value="<?= h($nameValue) ?>" required class="mt-1 h-10 w-full rounded-lg border border-slate-300 px-3 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                    </label>
                    <label class="block text-sm font-medium text-slate-700">电话
                        <input type="text" name="mobile" id="member-modal-mobile" value="<?= h($mobileValue) ?>" class="mt-1 h-10 w-full rounded-lg border border-slate-300 px-3 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                    </label>
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                    <label class="block text-sm font-medium text-slate-700">工号
                        <input type="text" name="employee_no" id="member-modal-employee-no" value="<?= h($employeeNoValue) ?>" class="mt-1 h-10 w-full rounded-lg border border-slate-300 px-3 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                    </label>
                    <label class="block text-sm font-medium text-slate-700">部门
                        <input type="text" name="department" id="member-modal-department" value="<?= h($departmentValue) ?>" class="mt-1 h-10 w-full rounded-lg border border-slate-300 px-3 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                    </label>
                </div>

                <div class="grid gap-3 md:grid-cols-[1fr_160px]">
                    <label class="block text-sm font-medium text-slate-700">职位
                        <input id="member-modal-position" type="text" name="position" value="<?= h($positionValue) ?>" placeholder="医生 / 护士 / 护理员 / 行政" class="mt-1 h-10 w-full rounded-lg border border-slate-300 px-3 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                    </label>
                    <label class="block text-sm font-medium text-slate-700">状态
                        <select name="status" id="member-modal-status" class="mt-1 h-10 w-full rounded-lg border border-slate-300 px-3 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                            <option value="1">启用</option>
                            <option value="0">停用</option>
                        </select>
                    </label>
                </div>

                <div>
                    <p class="text-xs font-medium text-slate-500">常用职位快捷选择</p>
                    <div class="mt-1.5 flex flex-wrap gap-1.5">
                        <?php foreach ($quickPositions as $quickPosition): ?>
                            <button type="button" data-modal-position-option="<?= h($quickPosition) ?>" class="inline-flex h-8 items-center rounded-lg border border-slate-300 bg-white px-2.5 text-xs font-medium text-slate-700 transition hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700"><?= h($quickPosition) ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700">人员分组（可多选）</label>
                    <div class="mt-1 relative" data-member-group-dropdown>
                        <button id="member-modal-group-trigger" type="button" class="inline-flex h-10 w-full items-center justify-between rounded-lg border border-slate-300 bg-white px-3 text-sm text-slate-700 outline-none transition hover:border-blue-300 focus-visible:ring-2 focus-visible:ring-blue-500" aria-expanded="false">
                            <span id="member-modal-group-summary" class="block truncate text-left">请选择分组（可多选）</span>
                            <svg class="h-4 w-4 text-slate-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.938a.75.75 0 1 1 1.08 1.04l-4.25 4.51a.75.75 0 0 1-1.08 0l-4.25-4.51a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd"/></svg>
                        </button>
                        <div id="member-modal-group-panel" class="absolute z-20 mt-2 hidden w-full rounded-lg border border-slate-200 bg-white p-2 shadow-lg">
                            <div class="max-h-56 space-y-1 overflow-y-auto">
                                <?php if ($groups === []): ?>
                                    <div class="rounded-md px-2 py-2 text-sm text-slate-500">暂无可选分组</div>
                                <?php else: ?>
                                    <?php foreach ($groups as $group): ?>
                                        <?php $gid = (int) ($group['id'] ?? 0); if ($gid <= 0) continue; ?>
                                        <label class="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm text-slate-700 transition hover:bg-slate-50">
                                            <input type="checkbox" class="member-modal-group-option h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500" name="group_ids[]" value="<?= $gid ?>" data-group-name="<?= h((string) ($group['group_name'] ?? '未命名分组')) ?>">
                                            <span class="truncate"><?= h((string) ($group['group_name'] ?? '未命名分组')) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700">备注</label>
                    <textarea name="remark" id="member-modal-remark" rows="2" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200"></textarea>
                </div>

                <div class="flex gap-2">
                    <button type="submit" class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700">保存人员</button>
                    <button type="button" id="member-modal-cancel" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50">取消</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
@media (min-width: 1024px) {
    #member-search-form .member-tools-row,
    #member-import-form .member-tools-row,
    #member-batch-form .member-tools-row {
        flex-wrap: nowrap;
    }

    #member-search-form .member-tools-row > *,
    #member-import-form .member-tools-row > *,
    #member-batch-form .member-tools-row > * {
        flex-shrink: 0;
    }
}

#member-import-form input[type="file"]::file-selector-button {
    margin-block: 0;
    vertical-align: middle;
}
</style>

<script>
(function () {
    var importForm = document.getElementById('member-import-form');
    var importSubmit = document.getElementById('member-import-submit');
    if (importForm && importSubmit) {
        importForm.addEventListener('submit', function () {
            importSubmit.disabled = true;
            importSubmit.textContent = '上传中...';
        });
    }

    var checkAll = document.getElementById('member-check-all');
    var rowChecks = Array.prototype.slice.call(document.querySelectorAll('.member-row-checkbox'));
    var batchForm = document.getElementById('member-batch-form');
    var batchIds = document.getElementById('member-batch-member-ids');
    var batchAction = document.getElementById('member-batch-action');
    var batchGroup = document.getElementById('member-batch-group-id');
    var selectedCount = document.getElementById('member-selected-count');

    var getSelectedIds = function () {
        return rowChecks.filter(function (node) { return node.checked; }).map(function (node) { return node.value; });
    };

    var syncSelectedCount = function () {
        var selected = getSelectedIds();
        if (selectedCount) selectedCount.textContent = String(selected.length);
        if (checkAll) {
            checkAll.checked = rowChecks.length > 0 && selected.length === rowChecks.length;
            checkAll.indeterminate = selected.length > 0 && selected.length < rowChecks.length;
        }
    };

    if (checkAll) {
        checkAll.addEventListener('change', function () {
            rowChecks.forEach(function (node) { node.checked = checkAll.checked; });
            syncSelectedCount();
        });
    }
    rowChecks.forEach(function (node) { node.addEventListener('change', syncSelectedCount); });
    syncSelectedCount();

    if (batchForm && batchIds) {
        batchForm.addEventListener('submit', function (event) {
            var ids = getSelectedIds();
            if (ids.length === 0) { event.preventDefault(); alert('请先勾选要操作的人员'); return; }
            batchIds.value = ids.join(',');
            var action = batchAction ? batchAction.value : '';
            if (!action) { event.preventDefault(); alert('请选择批量操作'); return; }
            if (action === 'assign_group' && (!batchGroup || !batchGroup.value)) { event.preventDefault(); alert('请选择要加入的分组'); return; }
            if (action === 'delete' && !confirm('确认批量删除选中人员吗？')) event.preventDefault();
        });
    }

    // Modal logic
    var modal = document.getElementById('member-modal');
    var modalTitle = document.getElementById('member-modal-title');
    var modalForm = document.getElementById('member-modal-form');
    var modalId = document.getElementById('member-modal-id');
    var modalName = document.getElementById('member-modal-name');
    var modalMobile = document.getElementById('member-modal-mobile');
    var modalEmployeeNo = document.getElementById('member-modal-employee-no');
    var modalDepartment = document.getElementById('member-modal-department');
    var modalPosition = document.getElementById('member-modal-position');
    var modalStatus = document.getElementById('member-modal-status');
    var modalRemark = document.getElementById('member-modal-remark');
    var modalGroupOptions = Array.prototype.slice.call(document.querySelectorAll('.member-modal-group-option'));
    var modalGroupTrigger = document.getElementById('member-modal-group-trigger');
    var modalGroupPanel = document.getElementById('member-modal-group-panel');
    var modalGroupSummary = document.getElementById('member-modal-group-summary');

    function openModal(isEdit, data) {
        if (!modal) return;
        modalTitle.textContent = isEdit ? '编辑人员' : '新增人员';
        modalForm.action = isEdit ? '/admin/members/update' : '/admin/members';
        modalId.value = data.id || '';
        modalName.value = data.name || '';
        modalMobile.value = data.mobile || '';
        modalEmployeeNo.value = data.employee_no || '';
        modalDepartment.value = data.department || '';
        modalPosition.value = data.position || '';
        modalStatus.value = data.status || '1';
        modalRemark.value = data.remark || '';
        var gids = (data.group_ids || '').split(',').filter(function(v){return v!=='';});
        modalGroupOptions.forEach(function(opt){
            opt.checked = gids.indexOf(opt.value) !== -1;
        });
        updateModalGroupSummary();
        modal.style.display = 'flex';
    }

    function closeModal() {
        if (modal) modal.style.display = 'none';
    }

    function updateModalGroupSummary() {
        if (!modalGroupSummary) return;
        var names = modalGroupOptions.filter(function(n){return n.checked;}).map(function(n){return n.getAttribute('data-group-name')||'';}).filter(function(n){return n!=='';});
        modalGroupSummary.textContent = names.length === 0 ? '请选择分组（可多选）' : names.join('、');
    }

    document.getElementById('member-add-btn').addEventListener('click', function(){
        openModal(false, {});
    });

    document.querySelectorAll('.member-edit-btn').forEach(function(btn){
        btn.addEventListener('click', function(){
            openModal(true, {
                id: btn.getAttribute('data-id'),
                name: btn.getAttribute('data-name'),
                mobile: btn.getAttribute('data-mobile'),
                employee_no: btn.getAttribute('data-employee-no'),
                department: btn.getAttribute('data-department'),
                position: btn.getAttribute('data-position'),
                status: btn.getAttribute('data-status'),
                remark: btn.getAttribute('data-remark'),
                group_ids: btn.getAttribute('data-group-ids')
            });
        });
    });

    document.getElementById('member-modal-close').addEventListener('click', closeModal);
    document.getElementById('member-modal-cancel').addEventListener('click', closeModal);
    modal.addEventListener('click', function(e){ if(e.target===modal) closeModal(); });
    document.addEventListener('keydown', function(e){ if(e.key==='Escape') closeModal(); });

    // Modal group dropdown
    if (modalGroupTrigger && modalGroupPanel) {
        modalGroupTrigger.addEventListener('click', function(){
            modalGroupPanel.classList.toggle('hidden');
        });
        document.addEventListener('click', function(e){
            if (!modalGroupPanel.contains(e.target) && !modalGroupTrigger.contains(e.target)) modalGroupPanel.classList.add('hidden');
        });
    }
    modalGroupOptions.forEach(function(n){ n.addEventListener('change', updateModalGroupSummary); });

    // Modal position quick select
    document.querySelectorAll('[data-modal-position-option]').forEach(function(btn){
        btn.addEventListener('click', function(){
            if (modalPosition) modalPosition.value = btn.getAttribute('data-modal-position-option') || '';
        });
    });

    // Auto-open modal if editing via URL
    <?php if ($isEditing): ?>
    openModal(true, {
        id: '<?= $editId ?>',
        name: <?= json_encode($nameValue) ?>,
        mobile: <?= json_encode($mobileValue) ?>,
        employee_no: <?= json_encode($employeeNoValue) ?>,
        department: <?= json_encode($departmentValue) ?>,
        position: <?= json_encode($positionValue) ?>,
        status: '<?= $statusValue ?>',
        remark: <?= json_encode($remarkValue) ?>,
        group_ids: '<?= implode(',', array_map('intval', $editingGroupIds)) ?>'
    });
    <?php endif; ?>
})();
</script>
