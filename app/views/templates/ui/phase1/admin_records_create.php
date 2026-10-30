<?php
$csrf = (string) ($csrf ?? '');
$tasks = is_array($tasks ?? null) ? $tasks : [];
$members = is_array($members ?? null) ? $members : [];
$today = (string) ($today ?? date('Y-m-d'));
$nowLocal = (string) ($nowLocal ?? to_datetime_local(date('Y-m-d H:i:s')));
?>
<div class="space-y-5">
    <form method="post" action="/admin/records/create" enctype="multipart/form-data" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">

        <div class="grid gap-4 lg:grid-cols-2">
            <label class="block text-sm font-medium text-slate-700">任务
                <select name="task_id" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                    <option value="">请选择任务</option>
                    <?php foreach ($tasks as $task): ?>
                        <?php $taskId = (int) ($task['id'] ?? 0); ?>
                        <?php if ($taskId <= 0) { continue; } ?>
                        <option value="<?= $taskId ?>"><?= h((string) ($task['task_name'] ?? '未命名任务')) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="block text-sm font-medium text-slate-700">人员
                <select name="member_id" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                    <option value="">请选择人员</option>
                    <?php foreach ($members as $member): ?>
                        <?php
                        $memberId = (int) ($member['id'] ?? 0);
                        if ($memberId <= 0) {
                            continue;
                        }
                        $memberName = (string) ($member['name'] ?? '');
                        ?>
                        <option value="<?= $memberId ?>"><?= h($memberName) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="block text-sm font-medium text-slate-700">打卡日期
                <input
                    type="date"
                    name="task_date"
                    value="<?= h($today) ?>"
                    required
                    class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                >
            </label>

            <label class="block text-sm font-medium text-slate-700">提交时间
                <input
                    type="datetime-local"
                    name="submit_time"
                    value="<?= h($nowLocal) ?>"
                    required
                    class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                >
            </label>
        </div>

        <label class="mt-4 block text-sm font-medium text-slate-700">上传图片
            <input
                type="file"
                name="images[]"
                accept="image/*"
                multiple
                required
                class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 file:mr-3 file:rounded-md file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-blue-700 hover:file:bg-blue-100"
            >
        </label>

        <label class="mt-4 block text-sm font-medium text-slate-700">备注
            <textarea name="remark" rows="3" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200"></textarea>
        </label>

        <label class="mt-4 inline-flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" name="force_override" value="1" class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
            如果存在同任务同日期记录，允许覆盖提交
        </label>

        <div class="mt-5 flex flex-wrap items-center gap-2">
            <button type="submit" class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700">提交补录</button>
            <a href="/admin/records" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-50">返回列表</a>
        </div>
    </form>
</div>
