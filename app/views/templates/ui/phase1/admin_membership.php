<?php
$levels = is_array($levels ?? null) ? $levels : [];
$csrf = (string) ($csrf ?? '');
$flash = is_array($flash ?? null) ? $flash : null;
$UNL = 999999;
$fmt = static fn (int $v): string => $v >= $UNL ? '不限' : (string) $v;
?>
<div class="admin-content">
    <h1 class="text-xl font-semibold text-slate-800">会员等级</h1>
    <p class="mt-1 text-sm text-slate-500">配额填 999999 表示“不限”。普通会员为所有用户的默认免费等级。</p>
    <?php if (!empty($flash['message'])): ?>
        <div class="mt-3 rounded-xl border px-4 py-3 text-sm <?= ($flash['type'] ?? '') === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-rose-200 bg-rose-50 text-rose-700' ?>"><?= h((string) $flash['message']) ?></div>
    <?php endif; ?>

    <div class="mt-4 grid gap-4 lg:grid-cols-3">
        <?php foreach ($levels as $lv): ?>
            <form method="post" action="/admin/membership" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                <input type="hidden" name="id" value="<?= (int) $lv['id'] ?>">
                <div class="flex items-center justify-between">
                    <input type="text" name="name" value="<?= h((string) $lv['name']) ?>" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-base font-semibold text-slate-900">
                </div>
                <div class="mt-3 space-y-3 text-sm">
                    <label class="block text-slate-600">名单上限
                        <input type="number" name="max_members" min="0" value="<?= (int) $lv['max_members'] ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-1.5">
                    </label>
                    <label class="block text-slate-600">任务最长天数
                        <input type="number" name="max_task_days" min="0" value="<?= (int) $lv['max_task_days'] ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-1.5">
                    </label>
                    <label class="block text-slate-600">同时进行任务数
                        <input type="number" name="max_active_tasks" min="0" value="<?= (int) $lv['max_active_tasks'] ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-1.5">
                    </label>
                    <label class="flex items-center gap-2 text-slate-700">
                        <input type="checkbox" name="allow_sub_register" <?= (int) $lv['allow_sub_register'] === 1 ? 'checked' : '' ?> class="h-4 w-4 rounded border-slate-300 text-blue-600"> 允许发展下级
                    </label>
                    <label class="flex items-center gap-2 text-slate-700">
                        <input type="checkbox" name="allow_survey" <?= (int) $lv['allow_survey'] === 1 ? 'checked' : '' ?> class="h-4 w-4 rounded border-slate-300 text-blue-600"> 允许问卷功能（三期）
                    </label>
                    <label class="flex items-center gap-2 text-slate-700">
                        <input type="checkbox" name="allow_message_board" <?= (int) ($lv['allow_message_board'] ?? 0) === 1 ? 'checked' : '' ?> class="h-4 w-4 rounded border-slate-300 text-blue-600"> 允许留言板
                    </label>
                    <label class="flex items-center gap-2 text-slate-700">
                        <input type="checkbox" name="allow_announcement" <?= (int) ($lv['allow_announcement'] ?? 0) === 1 ? 'checked' : '' ?> class="h-4 w-4 rounded border-slate-300 text-blue-600"> 允许首页公告
                    </label>
                    <label class="flex items-center gap-2 text-slate-700">
                        <input type="checkbox" name="allow_custom_title" <?= (int) ($lv['allow_custom_title'] ?? 0) === 1 ? 'checked' : '' ?> class="h-4 w-4 rounded border-slate-300 text-blue-600"> 允许自定义网站标题
                    </label>
                    <label class="block text-slate-600">问卷数量上限
                        <input type="number" name="max_surveys" min="0" value="<?= (int) $lv['max_surveys'] ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-1.5">
                    </label>
                    <label class="block text-slate-600">每问卷答卷数上限
                        <input type="number" name="max_survey_responses" min="0" value="<?= (int) $lv['max_survey_responses'] ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-1.5">
                    </label>
                </div>
                <button type="submit" class="btn-primary mt-4 w-full">保存</button>
            </form>
        <?php endforeach; ?>
    </div>
</div>
