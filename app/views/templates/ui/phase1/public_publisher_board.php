<?php
$csrf = (string) ($csrf ?? '');
$flash = is_array($flash ?? null) ? $flash : null;
$threads = is_array($threads ?? null) ? $threads : [];
$publisherName = (string) ($publisherName ?? '');
$boardName = (string) ($boardName ?? '留言板');
$refCode = (string) ($refCode ?? '');
?>
<div class="mx-auto w-full max-w-3xl space-y-5 pb-10">
    <?php if (!empty($flash['message'])): ?>
        <div class="rounded-xl border px-4 py-3 text-sm <?= ($flash['type'] ?? '') === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-rose-200 bg-rose-50 text-rose-700' ?>"><?= h((string) $flash['message']) ?></div>
    <?php endif; ?>

    <section class="overflow-hidden rounded-2xl bg-gradient-to-r from-blue-500 to-indigo-600 p-5 text-white shadow-lg">
        <h1 class="text-xl font-bold"><?= h($publisherName) ?> · <?= h($boardName) ?></h1>
        <p class="mt-1 text-sm text-white/80">欢迎留言，我们会尽快回复。</p>
        <a href="/u/<?= h(rawurlencode($refCode)) ?>" class="mt-3 inline-block text-xs text-white/85 underline">← 返回任务列表</a>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <form method="post" action="/u/<?= h(rawurlencode($refCode)) ?>/messages" class="space-y-3">
            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
            <input type="text" name="nickname" maxlength="100" placeholder="昵称（可选）" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none">
            <textarea name="content" rows="3" required maxlength="2000" placeholder="写下你的留言…" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-blue-400 focus:outline-none"></textarea>
            <button type="submit" class="rounded-lg bg-blue-600 px-5 py-2 text-sm font-semibold text-white hover:bg-blue-700">发表留言</button>
        </form>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="mb-3 text-sm font-semibold text-slate-800">留言（共 <?= count($threads) ?> 条）</div>
        <?php if ($threads === []): ?>
            <p class="py-8 text-center text-sm text-slate-400">还没有留言，来抢沙发吧</p>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($threads as $thread): ?>
                    <?php
                    $tid = (int) ($thread['id'] ?? 0);
                    $replies = is_array($thread['replies'] ?? null) ? $thread['replies'] : [];
                    ?>
                    <article class="rounded-xl border border-slate-200 bg-slate-50/60 p-4">
                        <div class="flex items-center justify-between">
                            <div class="text-sm font-semibold text-slate-900"><?= h(trim((string) ($thread['nickname'] ?? '')) ?: '访客') ?></div>
                            <span class="text-xs text-slate-400"><?= h((string) ($thread['created_at'] ?? '-')) ?></span>
                        </div>
                        <p class="mt-2 whitespace-pre-wrap text-sm leading-7 text-slate-700"><?= h(trim((string) ($thread['content'] ?? ''))) ?></p>

                        <?php foreach ($replies as $reply): ?>
                            <div class="mt-2 rounded-lg <?= (int) ($reply['is_admin_reply'] ?? 0) === 1 ? 'bg-blue-50' : 'bg-white' ?> border border-slate-100 p-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-semibold text-slate-700"><?= h(trim((string) ($reply['nickname'] ?? '')) ?: '访客') ?></span>
                                    <span class="text-xs text-slate-400"><?= h((string) ($reply['created_at'] ?? '-')) ?></span>
                                </div>
                                <p class="mt-1 whitespace-pre-wrap text-sm text-slate-600"><?= h(trim((string) ($reply['content'] ?? ''))) ?></p>
                            </div>
                        <?php endforeach; ?>

                        <form method="post" action="/u/<?= h(rawurlencode($refCode)) ?>/messages" class="mt-3 flex gap-2">
                            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                            <input type="hidden" name="parent_id" value="<?= $tid ?>">
                            <input type="text" name="content" required maxlength="2000" placeholder="回复…" class="min-w-0 flex-1 rounded-lg border border-slate-300 px-2 py-1.5 text-sm focus:border-blue-400 focus:outline-none">
                            <button type="submit" class="shrink-0 rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700">回复</button>
                        </form>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
