<?php
$csrf = (string) ($csrf ?? '');
$error = (string) ($error ?? '');
$old = is_array($old ?? null) ? $old : [];
$ref = (string) ($ref ?? '');
$refName = (string) ($refName ?? '');
?>
<section class="mx-auto mt-4 w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
    <h2 class="mb-4 text-center text-xl font-bold text-slate-900">用户注册</h2>
    <?php if ($refName !== ''): ?>
        <div class="mb-4 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-center text-sm text-blue-700">您正在通过「<?= h($refName) ?>」的邀请注册</div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
            <?= h($error) ?>
        </div>
    <?php endif; ?>

    <form method="post" action="/register" class="grid gap-4">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        <input type="hidden" name="ref" value="<?= h($ref) ?>">
        <label class="text-sm font-medium text-slate-700">姓名
            <input
                type="text"
                name="name"
                required
                value="<?= h((string) ($old['name'] ?? '')) ?>"
                placeholder="请输入真实姓名"
                class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
            >
        </label>
        <label class="text-sm font-medium text-slate-700">手机号
            <input
                type="tel"
                name="mobile"
                required
                value="<?= h((string) ($old['mobile'] ?? '')) ?>"
                placeholder="请输入手机号"
                pattern="1[3-9]\d{9}"
                class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
            >
        </label>
        <label class="text-sm font-medium text-slate-700">邮箱
            <input
                type="email"
                name="email"
                required
                value="<?= h((string) ($old['email'] ?? '')) ?>"
                placeholder="请输入邮箱"
                class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
            >
        </label>
        <label class="text-sm font-medium text-slate-700">密码
            <input
                type="password"
                name="password"
                required
                minlength="6"
                placeholder="至少6位密码"
                class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
            >
        </label>
        <label class="text-sm font-medium text-slate-700">确认密码
            <input
                type="password"
                name="password_confirm"
                required
                minlength="6"
                placeholder="请再次输入密码"
                class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
            >
        </label>
        <button
            type="submit"
            class="mt-1 inline-flex items-center justify-center rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700"
        >
            注册
        </button>
    </form>
    <p class="mt-4 text-center text-sm text-slate-500">
        已有账号？<a href="/login" class="text-blue-600 hover:underline">立即登录</a>
    </p>
</section>
