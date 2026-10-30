<?php
$csrf = (string) ($csrf ?? '');
$flash = is_array($flash ?? null) ? $flash : null;
$announcements = is_array($announcements ?? null) ? $announcements : [];
$refCode = (string) ($refCode ?? '');
$baseUrl = (string) ($baseUrl ?? '');
$pageUrl = $baseUrl . '/u/' . rawurlencode($refCode);
?>
<div class="mx-auto w-full max-w-3xl space-y-5 pb-10">
    <?php if (!empty($flash['message'])): ?>
        <div class="rounded-xl border px-4 py-3 text-sm <?= ($flash['type'] ?? '') === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-rose-200 bg-rose-50 text-rose-700' ?>"><?= h((string) $flash['message']) ?></div>
    <?php endif; ?>

    <section class="overflow-hidden rounded-2xl bg-gradient-to-r from-amber-400 to-orange-500 p-5 text-white shadow-lg">
        <h1 class="text-xl font-bold">我的公告</h1>
        <p class="mt-1 text-sm text-white/85">已上线的公告会在你的任务页 <a href="<?= h($pageUrl) ?>" target="_blank" class="underline">弹出展示</a>。</p>
        <a href="/my" class="mt-3 inline-block text-xs text-white/85 underline">← 返回个人中心</a>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <form method="post" action="/my/announcements" class="space-y-3">
            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
            <label class="block text-sm font-semibold text-slate-800">发布新公告</label>
            <textarea name="content" rows="4" required maxlength="2000" placeholder="输入公告内容…" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-amber-400 focus:outline-none"></textarea>
            <button type="submit" class="rounded-lg bg-amber-500 px-5 py-2 text-sm font-semibold text-white hover:bg-amber-600">发布</button>
        </form>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="mb-3 text-sm font-semibold text-slate-800">公告列表（共 <?= count($announcements) ?> 条）</div>
        <?php if ($announcements === []): ?>
            <p class="py-8 text-center text-sm text-slate-400">暂无公告</p>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($announcements as $a): ?>
                    <?php $aid = (int) ($a['id'] ?? 0); $on = (int) ($a['status'] ?? 0) === 1; ?>
                    <div class="rounded-xl border border-slate-200 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <p class="min-w-0 flex-1 whitespace-pre-wrap text-sm leading-7 text-slate-700"><?= h(trim((string) ($a['content'] ?? ''))) ?></p>
                            <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium <?= $on ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' ?>"><?= $on ? '已上线' : '已下线' ?></span>
                        </div>
                        <div class="mt-2 flex items-center gap-4 text-xs">
                            <span class="text-slate-400"><?= h((string) ($a['created_at'] ?? '-')) ?></span>
                            <form method="post" action="/my/announcements/toggle" class="inline">
                                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                <input type="hidden" name="id" value="<?= $aid ?>">
                                <input type="hidden" name="status" value="<?= $on ? 0 : 1 ?>">
                                <button type="submit" class="text-amber-600 hover:underline"><?= $on ? '下线' : '上线' ?></button>
                            </form>
                            <form method="post" action="/my/announcements/delete" class="inline" onsubmit="return confirm('删除此公告？')">
                                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                <input type="hidden" name="id" value="<?= $aid ?>">
                                <button type="submit" class="text-rose-500 hover:underline">删除</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
