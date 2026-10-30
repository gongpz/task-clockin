<?php
$csrf = (string) ($csrf ?? '');
$groups = is_array($groups ?? null) ? $groups : [];
$allMembers = is_array($allMembers ?? null) ? $allMembers : [];
$editingGroup = is_array($editingGroup ?? null) ? $editingGroup : null;
$selectedGroup = is_array($selectedGroup ?? null) ? $selectedGroup : null;
$groupMemberIds = is_array($groupMemberIds ?? null) ? $groupMemberIds : [];
$groupMembers = is_array($groupMembers ?? null) ? $groupMembers : [];
$memberKeyword = (string) ($memberKeyword ?? '');

$editGroupId = (int) ($editingGroup['id'] ?? 0);
$isEditingGroup = $editGroupId > 0;
$groupFormAction = $isEditingGroup ? '/admin/member-groups/update' : '/admin/member-groups';
$groupFormTitle = $isEditingGroup ? '编辑分组' : '新建分组';
$groupNameValue = (string) ($editingGroup['group_name'] ?? '');
$groupRemarkValue = (string) ($editingGroup['remark'] ?? '');
$groupStatusValue = (string) ($editingGroup['status'] ?? '1');

$selectedGroupId = (int) ($selectedGroup['id'] ?? 0);
$selectedGroupName = (string) ($selectedGroup['group_name'] ?? '');
?>
<div class="space-y-5">
    <section class="grid gap-4 xl:grid-cols-[360px_1fr]">
        <form method="post" action="<?= h($groupFormAction) ?>" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
            <?php if ($isEditingGroup): ?>
                <input type="hidden" name="id" value="<?= $editGroupId ?>">
            <?php endif; ?>

            <h2 class="text-base font-semibold text-slate-900"><?= h($groupFormTitle) ?></h2>
            <div class="mt-4 grid gap-3">
                <label class="block text-sm font-medium text-slate-700">分组名称
                    <input type="text" name="group_name" value="<?= h($groupNameValue) ?>" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                </label>

                <label class="block text-sm font-medium text-slate-700">备注
                    <input type="text" name="remark" value="<?= h($groupRemarkValue) ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                </label>

                <label class="block text-sm font-medium text-slate-700">状态
                    <select name="status" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                        <option value="1" <?= $groupStatusValue === '1' ? 'selected' : '' ?>>启用</option>
                        <option value="0" <?= $groupStatusValue === '0' ? 'selected' : '' ?>>停用</option>
                    </select>
                </label>
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-2">
                <button type="submit" class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700">保存分组</button>
                <?php if ($isEditingGroup): ?>
                    <a href="/admin/member-groups" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50">取消编辑</a>
                <?php endif; ?>
            </div>
        </form>

        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-semibold text-slate-900">分组列表</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="tw-table min-w-[760px] w-full table-fixed divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="w-16 px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">编号</th>
                            <th class="w-[220px] px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">分组名称</th>
                            <th class="w-[110px] px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">状态</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">操作</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <?php if ($groups === []): ?>
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-slate-500">暂无分组</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($groups as $group): ?>
                                <?php
                                $gid = (int) ($group['id'] ?? 0);
                                $enabled = (int) ($group['status'] ?? 0) === 1;
                                if ($gid <= 0) {
                                    continue;
                                }
                                ?>
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-3 text-slate-700">#<?= $gid ?></td>
                                    <td class="px-4 py-3 text-slate-700"><?= h((string) ($group['group_name'] ?? '-')) ?></td>
                                    <td class="px-4 py-3">
                                        <?php if ($enabled): ?>
                                            <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">启用</span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">停用</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-nowrap items-center gap-2">
                                            <a href="/admin/member-groups?group_id=<?= $gid ?>" class="inline-flex items-center rounded-md border border-slate-300 px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-100">查看成员</a>
                                            <a href="/admin/member-groups?edit_group_id=<?= $gid ?>" class="inline-flex items-center rounded-md border border-slate-300 px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-100">编辑</a>
                                            <form method="post" action="/admin/member-groups/delete" class="inline-flex" onsubmit="return confirm('确认删除该分组吗？分组成员关系会一并移除，但不会删除成员。');">
                                                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                                <input type="hidden" name="id" value="<?= $gid ?>">
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
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <?php if ($selectedGroupId <= 0): ?>
            <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                请先在上方列表中选择一个分组，进入“查看成员”后管理该分组成员。
            </div>
        <?php else: ?>
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">分组成员：<?= h($selectedGroupName) ?></h2>
                    <p class="mt-1 text-sm text-slate-500">可以添加成员、移除成员，或直接同步整个分组成员。</p>
                </div>
            </div>

            <form method="get" action="/admin/member-groups" class="mb-4 flex flex-wrap items-center gap-2">
                <input type="hidden" name="group_id" value="<?= $selectedGroupId ?>">
                <input
                    type="text"
                    name="member_keyword"
                    value="<?= h($memberKeyword) ?>"
                    placeholder="按姓名 / 手机号 / 工号 / 部门 / 职位筛选组内成员"
                    class="w-full min-w-[260px] flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                >
                <button type="submit" class="inline-flex items-center rounded-lg bg-blue-600 px-3.5 py-2 text-sm font-medium text-white transition hover:bg-blue-700">筛选</button>
                <a href="/admin/member-groups?group_id=<?= $selectedGroupId ?>" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">重置</a>
            </form>

            <div class="overflow-x-auto">
                <table class="tw-table min-w-[900px] w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="w-16 px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">编号</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">姓名</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">电话</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">职位</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">部门</th>
                            <th class="w-[120px] px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">操作</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        <?php if ($groupMembers === []): ?>
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-slate-500">该分组暂无成员</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($groupMembers as $member): ?>
                                <?php $mid = (int) ($member['id'] ?? 0); ?>
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-3 text-slate-700">#<?= $mid ?></td>
                                    <td class="px-4 py-3 text-slate-700"><?= h((string) ($member['name'] ?? '-')) ?></td>
                                    <td class="px-4 py-3 text-slate-700"><?= h((string) ($member['mobile'] ?? '-')) ?></td>
                                    <td class="px-4 py-3 text-slate-700"><?= h((string) ($member['position'] ?? '-')) ?></td>
                                    <td class="px-4 py-3 text-slate-700"><?= h((string) ($member['department'] ?? '-')) ?></td>
                                    <td class="px-4 py-3">
                                        <form method="post" action="/admin/member-groups/members/remove" onsubmit="return confirm('确认将该成员移出本分组吗？');" class="inline-flex">
                                            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                            <input type="hidden" name="group_id" value="<?= $selectedGroupId ?>">
                                            <input type="hidden" name="member_id" value="<?= $mid ?>">
                                            <button type="submit" class="inline-flex items-center rounded-md border border-rose-200 bg-rose-50 px-2.5 py-1.5 text-xs font-medium text-rose-700 transition hover:bg-rose-100">移除</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php
            $availableMembers = [];
            foreach ($allMembers as $member) {
                $mid = (int) ($member['id'] ?? 0);
                if ($mid <= 0 || in_array($mid, $groupMemberIds, true)) {
                    continue;
                }
                $availableMembers[] = $member;
            }
            ?>

            <div class="mt-4 grid gap-4 xl:grid-cols-2">
                <form method="post" action="/admin/member-groups/members/add" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                    <input type="hidden" name="group_id" value="<?= $selectedGroupId ?>">
                    <h3 class="text-sm font-semibold text-slate-900">添加成员到该分组</h3>
                    <label class="mt-3 block text-sm font-medium text-slate-700">可按住 Ctrl / Command 多选
                        <select name="member_ids[]" multiple size="8" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                            <?php if ($availableMembers === []): ?>
                                <option value="" disabled>暂无可添加成员</option>
                            <?php else: ?>
                                <?php foreach ($availableMembers as $member): ?>
                                    <?php
                                    $mid = (int) ($member['id'] ?? 0);
                                    $name = (string) ($member['name'] ?? '');
                                    ?>
                                    <option value="<?= $mid ?>"><?= h($name) ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </label>
                    <button type="submit" class="mt-3 inline-flex items-center rounded-lg bg-blue-600 px-3.5 py-2 text-sm font-medium text-white transition hover:bg-blue-700">添加到分组</button>
                </form>

                <form method="post" action="/admin/member-groups/members/sync" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                    <input type="hidden" name="group_id" value="<?= $selectedGroupId ?>">
                    <h3 class="text-sm font-semibold text-slate-900">同步分组成员</h3>
                    <p class="mt-1 text-xs text-slate-500">勾选后保存，未勾选成员将被移出本分组。</p>
                    <div class="mt-3 max-h-[260px] overflow-y-auto rounded-lg border border-slate-200 p-3">
                        <div class="grid gap-2 sm:grid-cols-2">
                            <?php if ($allMembers === []): ?>
                                <p class="text-sm text-slate-500">暂无可选成员</p>
                            <?php else: ?>
                                <?php foreach ($allMembers as $member): ?>
                                    <?php
                                    $mid = (int) ($member['id'] ?? 0);
                                    $name = (string) ($member['name'] ?? '');
                                    $checked = in_array($mid, $groupMemberIds, true);
                                    ?>
                                    <label class="inline-flex items-center gap-2 rounded-md border border-slate-200 bg-slate-50 px-2.5 py-2 text-sm text-slate-700">
                                        <input type="checkbox" name="member_ids[]" value="<?= $mid ?>" <?= $checked ? 'checked' : '' ?> class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                        <span class="truncate"><?= h($name) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <button type="submit" class="mt-3 inline-flex items-center rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">保存成员同步</button>
                </form>
            </div>
        <?php endif; ?>
    </section>
</div>
