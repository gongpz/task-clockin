<?php
$csrf = (string) ($csrf ?? '');
$members = is_array($members ?? null) ? $members : [];
$editing = is_array($editing ?? null) ? $editing : null;
$flash = is_array($flash ?? null) ? $flash : null;
$maxMembers = (int) ($maxMembers ?? 50);
$editId = (int) ($editing['id'] ?? 0);
?>
<div class="mx-auto w-full max-w-3xl space-y-5 pb-10">
    <?php if (!empty($flash['message'])): ?>
        <div class="rounded-xl border px-4 py-3 text-sm <?= ($flash['type'] ?? '') === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-rose-200 bg-rose-50 text-rose-700' ?>"><?= h((string) $flash['message']) ?></div>
    <?php endif; ?>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h1 class="text-lg font-bold text-slate-900"><?= $editId > 0 ? '编辑成员' : '添加成员' ?></h1>
        <p class="mt-1 text-xs text-slate-500">名单配额：<?= count($members) ?> / <?= $maxMembers ?></p>
        <form method="post" action="<?= $editId > 0 ? '/my/members/update' : '/my/members' ?>" class="mt-4 grid gap-3 sm:grid-cols-2">
            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
            <?php if ($editId > 0): ?><input type="hidden" name="id" value="<?= $editId ?>"><?php endif; ?>
            <label class="block text-sm font-medium text-slate-700">姓名
                <input type="text" name="name" required value="<?= h((string) ($editing['name'] ?? '')) ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </label>
            <label class="block text-sm font-medium text-slate-700">手机号
                <input type="text" name="mobile" value="<?= h((string) ($editing['mobile'] ?? '')) ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </label>
            <div class="sm:col-span-2 flex gap-2">
                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">保存</button>
                <?php if ($editId > 0): ?><a href="/my/members" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">取消</a><?php endif; ?>
            </div>
        </form>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="mb-3 text-base font-bold text-slate-900">我的名单</h2>
        <?php if ($members === []): ?>
            <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">还没有成员</div>
        <?php else: ?>
            <div class="space-y-2">
                <?php foreach ($members as $m): ?>
                    <div class="flex items-center justify-between rounded-lg border border-slate-200 px-4 py-3">
                        <div class="min-w-0">
                            <div class="truncate text-sm font-medium text-slate-900"><?= h((string) $m['name']) ?></div>
                            <div class="text-xs text-slate-500"><?= h((string) ($m['mobile'] ?? '未填手机')) ?><?= (int) ($m['is_bound'] ?? 0) === 1 ? ' · 已绑定' : '' ?></div>
                        </div>
                        <div class="flex shrink-0 gap-2 text-xs">
                            <a href="/my/members?edit_id=<?= (int) $m['id'] ?>" class="text-blue-600 hover:underline">编辑</a>
                            <form method="post" action="/my/members/delete" class="inline" onsubmit="return confirm('确定删除该成员？')">
                                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                                <button type="submit" class="text-rose-600 hover:underline">删除</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
