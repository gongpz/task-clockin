<?php
$csrf = (string) ($csrf ?? '');
$flash = is_array($flash ?? null) ? $flash : ['type' => '', 'message' => ''];
$oldNickname = trim((string) ($oldNickname ?? ''));
$oldContent = trim((string) ($oldContent ?? ''));
$flashMessage = trim((string) ($flash['message'] ?? ''));
$flashType = (string) ($flash['type'] ?? '');
$threads = is_array($threads ?? null) ? $threads : [];
$announcements = is_array($announcements ?? null) ? $announcements : [];
$emojiList = ['😀', '😁', '😂', '🤣', '😃', '😄', '😊', '😉', '😍', '😘', '😎', '👍', '👏', '❤️', '🎉', '🔥', '🌹', '🙏', '✨', '💪'];

$threadCount = count($threads);
$announcementCount = count($announcements);
$replyTotal = 0;

foreach ($threads as $thread) {
    $replyTotal += count(is_array($thread['replies'] ?? null) ? $thread['replies'] : []);
}
?>

<div class="message-page-shell mx-auto max-w-7xl space-y-6 pb-10">
    <section class="message-motion-card message-hero overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="message-hero-head px-5 py-6 sm:px-6 sm:py-7">
            <div class="message-hero-copy">
                <div class="message-hero-title-wrap">
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">留言互动区</h1>
                </div>
            </div>

            <a
                href="/tasks"
                class="message-hero-action message-secondary-button inline-flex h-10 items-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700"
            >
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M16.25 10a.75.75 0 0 1-.75.75H6.81l2.22 2.22a.75.75 0 1 1-1.06 1.06l-3.5-3.5a.75.75 0 0 1 0-1.06l3.5-3.5a.75.75 0 1 1 1.06 1.06L6.81 9.25h8.69a.75.75 0 0 1 .75.75Z" clip-rule="evenodd"/>
                </svg>
                返回任务列表
            </a>

            <p class="message-hero-description max-w-3xl text-sm leading-7 text-slate-500 sm:text-[15px]">
                无需登录，填写内容后就可以直接留言，也可以回复其他用户。上方公告会同步显示最新通知，方便你随时查看。
            </p>
        </div>

        <div class="message-hero-stats grid grid-cols-3 gap-3 border-t border-slate-200/80 px-5 py-4 sm:px-6">
            <div class="message-stat-card rounded-2xl border border-slate-200 bg-white/90 px-4 py-3 shadow-sm shadow-slate-100/80">
                <div class="message-stat-label text-xs font-medium text-slate-400">留言主题</div>
                <div class="message-stat-value mt-1 text-2xl font-bold text-slate-900"><?= $threadCount ?></div>
            </div>
            <div class="message-stat-card rounded-2xl border border-slate-200 bg-white/90 px-4 py-3 shadow-sm shadow-slate-100/80">
                <div class="message-stat-label text-xs font-medium text-slate-400">回复总数</div>
                <div class="message-stat-value mt-1 text-2xl font-bold text-slate-900"><?= $replyTotal ?></div>
            </div>
            <div class="message-stat-card rounded-2xl border border-slate-200 bg-white/90 px-4 py-3 shadow-sm shadow-slate-100/80">
                <div class="message-stat-label text-xs font-medium text-slate-400">已发公告</div>
                <div class="message-stat-value mt-1 text-2xl font-bold text-slate-900"><?= $announcementCount ?></div>
            </div>
        </div>
    </section>

    <?php if ($flashMessage !== ''): ?>
        <?php $isSuccess = $flashType === 'success'; ?>
        <section class="message-motion-card rounded-3xl border px-4 py-3 text-sm font-medium shadow-sm <?= $isSuccess ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-rose-200 bg-rose-50 text-rose-700' ?>">
            <?= h($flashMessage) ?>
        </section>
    <?php endif; ?>

    <section class="grid gap-6 xl:grid-cols-[minmax(0,360px)_minmax(0,1fr)]">
        <div class="space-y-6">
            <section class="message-motion-card rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="message-section-heading">
                    <div class="message-section-kicker">快速发布</div>
                    <h2 class="message-section-title text-lg font-semibold text-slate-900">发布留言</h2>
                    <p class="message-section-description text-sm leading-7 text-slate-500">支持匿名留言、插入表情，也可以对其他留言继续回复。</p>
                </div>

                <form method="post" action="/messages" class="message-form-shell mt-5 space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                    <input type="hidden" name="parent_id" value="">

                    <label class="block text-sm font-medium text-slate-700">
                        昵称（选填）
                        <input
                            type="text"
                            name="nickname"
                            maxlength="100"
                            value="<?= h($oldNickname) ?>"
                            placeholder="例如：张三"
                            class="mt-2 w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                        >
                    </label>

                    <label class="block text-sm font-medium text-slate-700">
                        留言内容
                        <textarea
                            name="content"
                            rows="6"
                            required
                            maxlength="2000"
                            placeholder="请输入留言内容（最多 2000 字）"
                            class="js-emoji-textarea mt-2 w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm leading-7 text-slate-900 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                        ><?= h($oldContent) ?></textarea>
                    </label>

                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-3 py-3">
                        <div class="flex flex-wrap gap-2">
                            <?php foreach ($emojiList as $emoji): ?>
                                <button
                                    type="button"
                                    data-emoji-btn="<?= h($emoji) ?>"
                                    class="message-emoji-btn inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-300 bg-white text-base transition hover:border-blue-200 hover:bg-blue-50"
                                ><?= h($emoji) ?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <button
                        type="submit"
                        class="message-primary-button inline-flex h-11 w-full items-center justify-center gap-2 rounded-2xl bg-blue-600 px-5 text-sm font-semibold text-white transition hover:bg-blue-700 sm:w-auto"
                    >
                        <span>提交留言</span>
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M3.394 8.517a1.25 1.25 0 0 1 .756-.756l10.5-4a1.25 1.25 0 0 1 1.654 1.653l-4 10.5a1.25 1.25 0 0 1-2.315.091L8.17 11.64l-4.365-1.819a1.25 1.25 0 0 1-.411-1.304Zm6.304 1.617 1.26 3.15 2.967-7.787-7.787 2.967 3.15 1.26a.75.75 0 0 1 .41.41Z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                </form>
            </section>

            <?php if ($announcements !== []): ?>
                <section class="message-motion-card message-motion-delay-1 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6 xl:hidden">
                    <div class="message-section-heading message-section-heading--compact">
                        <div class="message-section-kicker">管理通知</div>
                        <h2 class="message-section-title text-lg font-semibold text-slate-900">公告</h2>
                    </div>
                    <div class="message-announcement-list mt-5 space-y-4">
                        <?php foreach ($announcements as $item): ?>
                            <article class="message-announcement-card rounded-3xl border border-slate-200 bg-slate-50/70 p-4 shadow-sm shadow-slate-100/70">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <h3 class="text-base font-semibold text-slate-900"><?= h((string) ($item['title'] ?? '未命名公告')) ?></h3>
                                    <span class="inline-flex items-center rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-medium text-slate-500">
                                        <?= h((string) ($item['published_at'] ?? $item['created_at'] ?? '-')) ?>
                                    </span>
                                </div>
                                <div class="message-announcement prose prose-sm mt-3 max-w-none text-slate-700">
                                    <?= markdown_to_html((string) ($item['content_md'] ?? '')) ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>

        <div class="message-side-stack">
            <?php if ($announcements !== []): ?>
                <section class="message-motion-card message-motion-delay-1 hidden rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6 xl:block">
                    <div class="message-section-heading message-section-heading--compact">
                        <div class="message-section-kicker">管理通知</div>
                        <h2 class="message-section-title text-lg font-semibold text-slate-900">公告</h2>
                    </div>
                    <div class="message-announcement-list mt-5 space-y-4">
                        <?php foreach ($announcements as $item): ?>
                            <article class="message-announcement-card rounded-3xl border border-slate-200 bg-slate-50/70 p-4 shadow-sm shadow-slate-100/70">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <h3 class="text-base font-semibold text-slate-900"><?= h((string) ($item['title'] ?? '未命名公告')) ?></h3>
                                    <span class="inline-flex items-center rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-medium text-slate-500">
                                        <?= h((string) ($item['published_at'] ?? $item['created_at'] ?? '-')) ?>
                                    </span>
                                </div>
                                <div class="message-announcement prose prose-sm mt-3 max-w-none text-slate-700">
                                    <?= markdown_to_html((string) ($item['content_md'] ?? '')) ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <section class="message-motion-card message-motion-delay-2 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="message-section-heading message-section-heading--row flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <div class="message-section-kicker">互动记录</div>
                        <h2 class="message-section-title text-lg font-semibold text-slate-900">留言列表</h2>
                        <p class="message-section-description mt-1 text-sm text-slate-500">可以直接阅读留言，也可以展开回复。</p>
                    </div>
                    <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-600">
                        共 <?= $threadCount ?> 条主题
                    </span>
                </div>

                <div class="message-list-stack mt-5 space-y-4">
                    <?php if ($threads === []): ?>
                        <div class="message-empty-state rounded-3xl border border-dashed border-slate-300 bg-slate-50 px-4 py-10 text-center text-sm text-slate-500">
                            暂无留言，欢迎第一个发言。
                        </div>
                    <?php else: ?>
                        <?php foreach ($threads as $thread): ?>
                            <?php
                            $messageId = (int) ($thread['id'] ?? 0);
                            $name = trim((string) ($thread['nickname'] ?? ''));
                            $content = trim((string) ($thread['content'] ?? ''));
                            $createdAt = (string) ($thread['created_at'] ?? '-');
                            $replies = is_array($thread['replies'] ?? null) ? $thread['replies'] : [];
                            $replyCount = count($replies);
                            $displayName = $name !== '' ? $name : '匿名';
                            ?>
                            <article class="message-thread-card rounded-3xl border border-slate-200 bg-slate-50/50 p-4 sm:p-5">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <div class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-blue-100 text-sm font-bold text-blue-700">
                                                <?= h(mb_substr($displayName, 0, 1)) ?>
                                            </div>
                                            <div class="min-w-0">
                                                <div class="message-display-name text-sm font-semibold text-slate-900"><?= h($displayName) ?></div>
                                                <div class="mt-0.5 text-xs text-slate-500"><?= h($createdAt) ?></div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="inline-flex items-center rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-medium text-slate-500">
                                            <?= $replyCount ?> 条回复
                                        </span>
                                    </div>
                                </div>

                                <div class="mt-4 rounded-3xl border border-slate-200 bg-white px-4 py-4 shadow-sm shadow-slate-100/70">
                                    <p class="whitespace-pre-wrap text-sm leading-7 text-slate-700"><?= h($content) ?></p>
                                </div>

                                <?php if ($replies !== []): ?>
                                    <div class="mt-4 space-y-3 border-l-2 border-blue-100 pl-4">
                                        <?php foreach ($replies as $reply): ?>
                                            <?php
                                            $replyName = trim((string) ($reply['nickname'] ?? ''));
                                            $replyContent = trim((string) ($reply['content'] ?? ''));
                                            $replyAt = (string) ($reply['created_at'] ?? '-');
                                            $isAdminReply = (int) ($reply['is_admin_reply'] ?? 0) === 1;
                                            $replyDisplayName = $replyName !== '' ? $replyName : '匿名';
                                            ?>
                                            <div class="rounded-2xl border <?= $isAdminReply ? 'border-blue-200 bg-blue-50/80' : 'border-slate-200 bg-white' ?> px-4 py-3 shadow-sm shadow-slate-100/60">
                                                <div class="flex flex-wrap items-center justify-between gap-2">
                                                    <div class="text-xs font-semibold <?= $isAdminReply ? 'text-blue-700' : 'text-slate-700' ?>">
                                                        <?= h($replyDisplayName) ?>
                                                        <?php if ($isAdminReply): ?>
                                                            <span class="ml-1 inline-flex items-center rounded-full border border-blue-200 bg-white px-2 py-0.5 text-[10px] font-semibold text-blue-700">管理员</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="text-[11px] text-slate-500"><?= h($replyAt) ?></div>
                                                </div>
                                                <p class="mt-2 whitespace-pre-wrap text-sm leading-7 text-slate-700"><?= h($replyContent) ?></p>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <div class="mt-4">
                                    <button
                                        type="button"
                                        data-reply-toggle="reply-form-<?= $messageId ?>"
                                        class="message-reply-toggle inline-flex items-center gap-2 rounded-2xl border border-indigo-200 bg-indigo-50 px-3 py-2 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100"
                                    >
                                        <span>回复留言</span>
                                        <svg class="h-3.5 w-3.5 transition-transform duration-200" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M10 3.75a.75.75 0 0 1 .75.75v8.69l2.22-2.22a.75.75 0 1 1 1.06 1.06l-3.5 3.5a.75.75 0 0 1-1.06 0l-3.5-3.5a.75.75 0 1 1 1.06-1.06l2.22 2.22V4.5A.75.75 0 0 1 10 3.75Z" clip-rule="evenodd"/>
                                        </svg>
                                    </button>

                                    <form id="reply-form-<?= $messageId ?>" method="post" action="/messages" class="mt-3 hidden space-y-3 rounded-3xl border border-slate-200 bg-white p-4 shadow-sm shadow-slate-100/70">
                                        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                        <input type="hidden" name="parent_id" value="<?= $messageId ?>">

                                        <input
                                            type="text"
                                            name="nickname"
                                            maxlength="100"
                                            value="<?= h($oldNickname) ?>"
                                            placeholder="你的昵称（选填）"
                                            class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                                        >

                                        <textarea
                                            name="content"
                                            rows="3"
                                            required
                                            maxlength="2000"
                                            placeholder="输入回复内容"
                                            class="js-emoji-textarea w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm leading-7 text-slate-900 outline-none transition focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                                        ></textarea>

                                        <div class="flex flex-wrap gap-2">
                                            <?php foreach (array_slice($emojiList, 0, 10) as $emoji): ?>
                                                <button
                                                    type="button"
                                                    data-emoji-btn="<?= h($emoji) ?>"
                                                    class="message-emoji-btn inline-flex h-8 w-8 items-center justify-center rounded-xl border border-slate-300 bg-white text-sm transition hover:border-blue-200 hover:bg-blue-50"
                                                ><?= h($emoji) ?></button>
                                            <?php endforeach; ?>
                                        </div>

                                        <button
                                            type="submit"
                                            class="message-primary-button inline-flex h-10 w-full items-center justify-center gap-2 rounded-2xl bg-blue-600 px-4 text-sm font-semibold text-white transition hover:bg-blue-700 sm:w-auto"
                                        >
                                            提交回复
                                        </button>
                                    </form>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </section>
</div>

<style>
.message-page-shell {
    position: relative;
    isolation: isolate;
}

.message-page-shell::before {
    content: '';
    position: absolute;
    top: -2.4rem;
    right: 1.6rem;
    width: 19rem;
    height: 19rem;
    border-radius: 9999px;
    background: radial-gradient(circle, rgba(59, 130, 246, 0.16) 0%, rgba(125, 211, 252, 0.08) 34%, rgba(255, 255, 255, 0) 72%);
    filter: blur(4px);
    animation: messageFloat 7.5s ease-in-out infinite;
    pointer-events: none;
    z-index: -1;
}

.message-motion-card {
    animation: messageRise .55s cubic-bezier(.22, 1, .36, 1) both;
    transition: transform .22s ease, box-shadow .22s ease, border-color .22s ease;
}

.message-motion-card:hover {
    transform: translateY(-2px);
    border-color: #cbd5e1;
    box-shadow: 0 18px 36px rgba(15, 23, 42, 0.08);
}

.message-motion-delay-1 {
    animation-delay: .08s;
}

.message-motion-delay-2 {
    animation-delay: .16s;
}

.message-hero {
    background:
        radial-gradient(circle at top right, rgba(96, 165, 250, 0.18), rgba(96, 165, 250, 0) 34%),
        linear-gradient(135deg, #f8fbff 0%, #ffffff 52%, #eff6ff 100%);
}

.message-hero-head {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: start;
    gap: 1rem 1.25rem;
}

.message-hero-copy {
    min-width: 0;
    display: grid;
    align-content: start;
    gap: .45rem;
}

.message-hero-copy > :where(:not([hidden])) ~ :where(:not([hidden])) {
    margin-top: 0 !important;
}

.message-hero-title-wrap > :where(:not([hidden])) ~ :where(:not([hidden])) {
    margin-top: .22rem !important;
}

.message-hero-action {
    justify-self: end;
    align-self: start;
}

.message-hero-description {
    grid-column: 1 / -1;
    width: 100%;
    margin: 0;
    max-width: none;
}


.message-stat-card {
    position: relative;
    transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
}

.message-stat-card:hover {
    transform: translateY(-1px);
    border-color: #dbeafe;
    box-shadow: 0 14px 24px rgba(15, 23, 42, 0.06);
}

.message-stat-label {
    letter-spacing: .01em;
}

.message-stat-value {
    letter-spacing: -.02em;
}

.message-section-heading {
    display: grid;
    gap: .28rem;
}

.message-section-heading--compact {
    gap: .22rem;
}

.message-section-heading--row {
    display: flex;
}

.message-section-kicker {
    font-size: .72rem;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: #64748b;
}

.message-section-title {
    line-height: 1.25;
    letter-spacing: -.015em;
}

.message-section-description {
    max-width: 40rem;
}

.message-form-shell {
    gap: 1rem;
}

.message-side-stack {
    display: grid;
    gap: 1.5rem;
    align-content: start;
}

.message-announcement-list {
    display: grid;
    gap: 1rem;
}

.message-announcement-card {
    position: relative;
    overflow: hidden;
    background:
        linear-gradient(180deg, rgba(255, 255, 255, 0.92) 0%, rgba(248, 250, 252, 0.78) 100%);
    transition: transform .18s ease, border-color .18s ease, box-shadow .18s ease;
}

.message-announcement-card::before {
    content: '';
    position: absolute;
    inset: 0 auto 0 0;
    width: 3px;
    background: linear-gradient(180deg, rgba(37, 99, 235, 0.7), rgba(96, 165, 250, 0.18));
}

.message-announcement-card:hover {
    transform: translateY(-1px);
    border-color: #dbeafe;
    box-shadow: 0 18px 28px rgba(15, 23, 42, 0.06);
}

.message-secondary-button {
    position: relative;
    isolation: isolate;
    overflow: hidden;
    will-change: transform, box-shadow;
    box-shadow: 0 8px 18px rgba(15, 23, 42, 0.04);
    transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease, background-color .18s ease;
}

.message-secondary-button::after {
    content: '';
    position: absolute;
    top: -120%;
    left: -30%;
    width: 30%;
    height: 280%;
    background: linear-gradient(180deg, rgba(255, 255, 255, 0), rgba(255, 255, 255, 0.46), rgba(255, 255, 255, 0));
    transform: translateX(-260%) rotate(18deg);
    opacity: 0;
    transition: transform .55s ease, opacity .2s ease;
    pointer-events: none;
}

.message-secondary-button:hover {
    transform: translateY(-1px);
    box-shadow: 0 12px 24px rgba(37, 99, 235, 0.08);
}

.message-secondary-button:hover::after {
    opacity: 1;
    transform: translateX(520%) rotate(18deg);
}

.message-secondary-button:active {
    transform: translateY(0) scale(.98);
}

.message-secondary-button:focus-visible {
    outline: none;
    box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.12), 0 12px 24px rgba(37, 99, 235, 0.08);
    border-color: #93c5fd;
}

.message-primary-button {
    position: relative;
    isolation: isolate;
    overflow: hidden;
    will-change: transform, box-shadow;
    background-image: linear-gradient(135deg, #2563eb 0%, #3b82f6 58%, #60a5fa 100%);
    box-shadow: 0 16px 26px rgba(37, 99, 235, 0.2);
    transition: transform .18s ease, box-shadow .18s ease, background-color .18s ease, filter .18s ease;
}

.message-primary-button::before {
    content: '';
    position: absolute;
    inset: 1px;
    border-radius: inherit;
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.22), rgba(255, 255, 255, 0));
    pointer-events: none;
}

.message-primary-button::after {
    content: '';
    position: absolute;
    top: -120%;
    left: -34%;
    width: 32%;
    height: 300%;
    background: linear-gradient(180deg, rgba(255, 255, 255, 0) 0%, rgba(255, 255, 255, 0.34) 34%, rgba(255, 255, 255, 0) 72%);
    transform: translateX(-220%) rotate(20deg);
    opacity: 0;
    transition: transform .55s ease;
    pointer-events: none;
}

.message-primary-button:hover {
    transform: translateY(-1px);
    filter: saturate(1.04);
    box-shadow: 0 20px 30px rgba(37, 99, 235, 0.26);
}

.message-primary-button:hover::after {
    opacity: 1;
    transform: translateX(500%) rotate(20deg);
}

.message-primary-button:active {
    transform: translateY(0) scale(.98);
    box-shadow: 0 10px 18px rgba(37, 99, 235, 0.22);
}

.message-primary-button:focus-visible {
    outline: none;
    box-shadow: 0 0 0 4px rgba(96, 165, 250, 0.18), 0 18px 28px rgba(37, 99, 235, 0.24);
}

.message-thread-card {
    transition: border-color .18s ease, box-shadow .18s ease, background-color .18s ease;
}

.message-display-name {
    display: -webkit-box;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    word-break: break-all;
}

.message-thread-card:hover {
    border-color: #cbd5e1;
    background: linear-gradient(180deg, rgba(248, 250, 252, 0.7) 0%, rgba(255, 255, 255, 1) 100%);
    box-shadow: 0 16px 28px rgba(15, 23, 42, 0.05);
}

.message-list-stack {
    display: grid;
    gap: 1rem;
}

.message-emoji-btn {
    will-change: transform, box-shadow;
    box-shadow: 0 6px 14px rgba(15, 23, 42, 0.04);
    transition: transform .16s ease, border-color .16s ease, background-color .16s ease, box-shadow .16s ease;
}

.message-emoji-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 10px 18px rgba(59, 130, 246, 0.1);
}

.message-emoji-btn:active {
    transform: scale(.96);
}

.message-emoji-btn:focus-visible {
    outline: none;
    border-color: #93c5fd;
    box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.12), 0 10px 18px rgba(59, 130, 246, 0.1);
}

.message-reply-toggle {
    position: relative;
    isolation: isolate;
    overflow: hidden;
    will-change: transform, box-shadow;
    box-shadow: 0 8px 18px rgba(79, 70, 229, 0.08);
    transition: transform .18s ease, box-shadow .18s ease, background-color .18s ease, border-color .18s ease;
}

.message-reply-toggle::after {
    content: '';
    position: absolute;
    top: -120%;
    left: -34%;
    width: 32%;
    height: 300%;
    background: linear-gradient(180deg, rgba(255, 255, 255, 0), rgba(255, 255, 255, 0.34), rgba(255, 255, 255, 0));
    transform: translateX(-220%) rotate(20deg);
    opacity: 0;
    transition: transform .55s ease, opacity .2s ease;
    pointer-events: none;
}

.message-reply-toggle:hover {
    transform: translateY(-1px);
    box-shadow: 0 12px 22px rgba(79, 70, 229, 0.12);
}

.message-reply-toggle:hover::after {
    opacity: 1;
    transform: translateX(500%) rotate(20deg);
}

.message-reply-toggle:active {
    transform: translateY(0) scale(.98);
}

.message-reply-toggle:focus-visible {
    outline: none;
    box-shadow: 0 0 0 4px rgba(129, 140, 248, 0.16), 0 12px 22px rgba(79, 70, 229, 0.12);
    border-color: #a5b4fc;
}

.message-reply-toggle svg {
    transform: rotate(0deg);
}

.message-reply-toggle.is-open svg {
    transform: rotate(180deg);
}

.message-announcement :is(h1, h2, h3, h4, h5, h6) {
    margin-top: .75rem;
    margin-bottom: .35rem;
    color: #0f172a;
}

.message-announcement p {
    margin: .45rem 0;
    line-height: 1.8;
}

.message-announcement ul {
    margin: .45rem 0;
    padding-left: 1.2rem;
    list-style: disc;
}

.message-empty-state {
    background:
        linear-gradient(180deg, rgba(248, 250, 252, 0.9) 0%, rgba(255, 255, 255, 0.95) 100%);
}

@keyframes messageRise {
    from {
        opacity: 0;
        transform: translateY(16px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes messageFloat {
    0%, 100% {
        transform: translate3d(0, 0, 0);
    }
    50% {
        transform: translate3d(0, -10px, 0);
    }
}

@media (max-width: 640px) {
    .message-page-shell {
        gap: 14px;
    }

    .message-page-shell::before {
        right: -.5rem;
        width: 12rem;
        height: 12rem;
    }

    .message-motion-card {
        border-radius: 24px;
    }

    .message-hero-head {
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 8px 12px;
        padding: 18px 16px 14px;
    }

    .message-hero h1 {
        font-size: 1.7rem;
        line-height: 1.2;
    }

    .message-hero-action {
        justify-self: end;
        align-self: start;
    }

    .message-hero-description {
        width: 100%;
        margin-top: 2px;
        line-height: 1.68;
    }

    .message-section-kicker {
        font-size: .68rem;
        letter-spacing: .07em;
    }

    .message-hero-stats {
        gap: 8px;
        padding: 14px 16px 16px;
    }

    .message-hero-stats > div {
        border-radius: 18px;
        padding: 10px 10px 11px;
    }

    .message-hero-stats .text-xs {
        font-size: .68rem;
        line-height: 1.2;
    }

    .message-hero-stats .text-2xl {
        margin-top: 4px;
        font-size: 1.12rem;
        line-height: 1.1;
    }

    .message-side-stack {
        gap: 14px;
    }

    .message-announcement-list,
    .message-list-stack {
        gap: .9rem;
    }

    .message-emoji-btn {
        width: 34px;
        height: 34px;
        border-radius: 12px;
        font-size: .95rem;
    }

    .message-thread-card {
        padding: 16px;
    }

    .message-hero-action.message-secondary-button {
        height: 34px;
        min-height: 34px;
        padding-inline: .72rem;
        border-radius: .95rem;
        white-space: nowrap;
        gap: .38rem;
        font-size: .77rem;
        font-weight: 600;
        letter-spacing: .01em;
        line-height: 1;
    }

    .message-hero-action.message-secondary-button svg {
        width: .8rem;
        height: .8rem;
    }
}

@media (max-width: 430px) {
    .message-hero-action.message-secondary-button {
        height: 32px;
        min-height: 32px;
        padding-inline: .64rem;
        font-size: .74rem;
    }

    .message-hero-stats > div {
        padding: 9px 8px 10px;
    }

    .message-hero-stats .text-2xl {
        font-size: 1rem;
    }
}

@media (prefers-reduced-motion: reduce) {
    .message-motion-card,
    .message-page-shell::before {
        animation: none !important;
    }

    .message-secondary-button,
    .message-secondary-button::after,
    .message-primary-button,
    .message-primary-button::after,
    .message-emoji-btn,
    .message-reply-toggle,
    .message-reply-toggle::after {
        transition: none !important;
    }
}
</style>

<script>
(() => {
    let activeTextarea = null;
    const textareas = Array.from(document.querySelectorAll('.js-emoji-textarea'));

    textareas.forEach((el) => {
        el.addEventListener('focus', () => { activeTextarea = el; });
        el.addEventListener('click', () => { activeTextarea = el; });
    });

    document.querySelectorAll('[data-emoji-btn]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const emoji = btn.getAttribute('data-emoji-btn') || '';
            if (!emoji) return;
            const target = activeTextarea || document.querySelector('.js-emoji-textarea');
            if (!target) return;

            const start = target.selectionStart || 0;
            const end = target.selectionEnd || 0;
            const val = target.value || '';
            target.value = val.slice(0, start) + emoji + val.slice(end);
            target.focus();
            target.selectionStart = target.selectionEnd = start + emoji.length;
        });
    });

    document.querySelectorAll('[data-reply-toggle]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const id = btn.getAttribute('data-reply-toggle') || '';
            if (!id) return;

            const form = document.getElementById(id);
            if (!form) return;

            const willShow = form.classList.contains('hidden');
            form.classList.toggle('hidden', !willShow);
            btn.classList.toggle('is-open', willShow);

            if (willShow) {
                const textarea = form.querySelector('.js-emoji-textarea');
                if (textarea) {
                    activeTextarea = textarea;
                    setTimeout(() => textarea.focus(), 50);
                }
            }
        });
    });
})();
</script>

