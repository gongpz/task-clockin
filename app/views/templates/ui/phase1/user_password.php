<?php
$currentUser = $currentUser ?? null;
$csrf = (string) ($csrf ?? '');
$flash = is_array($flash ?? null) ? $flash : null;
?>
<div class="mx-auto w-full max-w-md space-y-5 pb-10">
    <?php if (!empty($flash['message'])): ?>
        <div class="rounded-xl border px-4 py-3 text-sm <?= ($flash['type'] ?? '') === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-rose-200 bg-rose-50 text-rose-700' ?>">
            <?= h((string) ($flash['message'] ?? '')) ?>
        </div>
    <?php endif; ?>

    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h1 class="mb-4 text-xl font-bold text-slate-900">修改密码</h1>
        <form method="post" action="/my/password" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
            <div>
                <label class="mb-1 block text-sm text-slate-600">当前密码</label>
                <input type="password" name="old_password" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
            </div>
            <div>
                <label class="mb-1 block text-sm text-slate-600">新密码</label>
                <input type="password" name="new_password" required minlength="6" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
            </div>
            <div>
                <label class="mb-1 block text-sm text-slate-600">确认新密码</label>
                <input type="password" name="confirm_password" required minlength="6" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
            </div>
            <div class="flex gap-3">
                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">确认修改</button>
                <a href="/my" class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">返回</a>
            </div>
        </form>
    </section>
</div>
