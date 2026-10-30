<?php
$csrf = (string) ($csrf ?? '');
$error = (string) ($error ?? '');
?>
<section class="mx-auto mt-4 w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
    <h2 class="mb-4 text-center text-xl font-bold text-slate-900">用户登录</h2>
    <?php if ($error !== ''): ?>
        <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
            <?= h($error) ?>
        </div>
    <?php endif; ?>

    <form method="post" action="/login" class="grid gap-4">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        <label class="text-sm font-medium text-slate-700">手机号或邮箱
            <input
                type="text"
                name="account"
                required
                autocomplete="username"
                placeholder="请输入手机号或邮箱"
                class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
            >
        </label>
        <label class="text-sm font-medium text-slate-700">密码
            <input
                type="password"
                name="password"
                required
                autocomplete="current-password"
                placeholder="请输入密码"
                class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
            >
        </label>
        <button
            type="submit"
            class="mt-1 inline-flex items-center justify-center rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700"
        >
            登录
        </button>
    </form>
    <p class="mt-4 text-center text-sm text-slate-500">
        还没有账号？<a href="/register" class="text-blue-600 hover:underline">立即注册</a>
    </p>
</section>
