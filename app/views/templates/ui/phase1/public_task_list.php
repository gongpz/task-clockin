<?php
$tasks = is_array($tasks ?? null) ? $tasks : [];
$latestTaskId = (int) ($latestTaskId ?? 0);
$currentUser = is_array($currentUser ?? null) ? $currentUser : null;
$csrf = (string) ($csrf ?? '');
$boardRefCode = (string) ($boardRefCode ?? '');
$publisherAnnouncements = is_array($publisherAnnouncements ?? null) ? $publisherAnnouncements : [];

$formatDate = static function (string $value): string {
    $value = trim($value);
    if ($value === '' || $value === '-') {
        return $value === '' ? '-' : $value;
    }
    $ts = strtotime($value);
    return $ts === false ? $value : date('Y-m-d', $ts);
};

$availableCount = count($tasks);
$readyCount = 0;
$longTermCount = 0;

foreach ($tasks as $task) {
    $statusTone = (string) ($task['status_tone'] ?? ((int) ($task['task_status'] ?? 1) === 1 ? 'ok' : 'off'));
    if ($statusTone === 'ok') {
        $readyCount++;
    }
    if ((int) ($task['is_long_term'] ?? 0) === 1 || trim((string) ($task['end_time'] ?? '')) === '') {
        $longTermCount++;
    }
}
?>

<div class="tasklist-page-shell mx-auto w-full max-w-7xl space-y-6 pb-10">
    <section class="tasklist-motion-card tasklist-hero overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="tasklist-hero-head px-5 py-6 sm:px-6 sm:py-7">
            <div class="tasklist-hero-copy">
                <div class="tasklist-hero-title-wrap">
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">任务列表</h1>
                </div>
            </div>

            <div class="tasklist-hero-actions flex flex-wrap items-center gap-3">
                <?php if ($currentUser === null): ?>
                <button
                    type="button"
                    id="open-login-modal"
                    class="tasklist-secondary-button inline-flex h-10 items-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700"
                >
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M10 2a8 8 0 1 0 0 16 8 8 0 0 0 0-16Zm-1.5 5a1.5 1.5 0 1 1 3 0 1.5 1.5 0 0 1-3 0ZM10 11a3 3 0 0 0-2.83 2h5.66A3 3 0 0 0 10 11Z" clip-rule="evenodd"/>
                    </svg>
                    注册 / 登录
                </button>
                <?php else: ?>
                <a
                    href="/my"
                    class="tasklist-secondary-button inline-flex h-10 items-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700"
                >
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M10 2a8 8 0 1 0 0 16 8 8 0 0 0 0-16Zm-1.5 5a1.5 1.5 0 1 1 3 0 1.5 1.5 0 0 1-3 0ZM10 11a3 3 0 0 0-2.83 2h5.66A3 3 0 0 0 10 11Z" clip-rule="evenodd"/>
                    </svg>
                    个人中心
                </a>
                <?php endif; ?>
                <a
                    href="/messages"
                    class="tasklist-secondary-button inline-flex h-10 items-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700"
                >
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M4.25 4.5A2.25 2.25 0 0 1 6.5 2.25h7A2.25 2.25 0 0 1 15.75 4.5v6A2.25 2.25 0 0 1 13.5 12.75H9.56l-2.95 2.46c-.73.61-1.86.09-1.86-.87v-1.59A2.25 2.25 0 0 1 2.5 10.5v-6a2.25 2.25 0 0 1 1.75-2Z"/>
                    </svg>
                    在线留言
                </a>
                <?php if ($boardRefCode !== ''): ?>
                <a
                    href="/u/<?= h(rawurlencode($boardRefCode)) ?>/messages"
                    class="tasklist-secondary-button inline-flex h-10 items-center gap-2 rounded-2xl border border-violet-200 bg-violet-50 px-4 text-sm font-semibold text-violet-700 transition hover:border-violet-300 hover:bg-violet-100"
                >
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M4.25 4.5A2.25 2.25 0 0 1 6.5 2.25h7A2.25 2.25 0 0 1 15.75 4.5v6A2.25 2.25 0 0 1 13.5 12.75H9.56l-2.95 2.46c-.73.61-1.86.09-1.86-.87v-1.59A2.25 2.25 0 0 1 2.5 10.5v-6a2.25 2.25 0 0 1 1.75-2Z"/>
                    </svg>
                    <?= h(get_message_board_name()) ?>
                </a>
                <?php endif; ?>
            </div>

            <p class="tasklist-hero-description max-w-3xl text-sm leading-7 text-slate-500 sm:text-[15px]">
                请选择需要完成的任务，点击“进入打卡”即可开始提交。最新任务会优先显示，方便你尽快完成本次打卡。
            </p>
        </div>

        <div class="tasklist-hero-stats grid grid-cols-3 gap-3 border-t border-slate-200/80 px-5 py-4 sm:px-6">
            <div class="rounded-2xl border border-slate-200 bg-white/90 px-4 py-3 shadow-sm shadow-slate-100/80">
                <div class="text-xs font-medium text-slate-600">当前任务数</div>
                <div class="mt-1 text-2xl font-bold text-slate-900"><?= $availableCount ?></div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white/90 px-4 py-3 shadow-sm shadow-slate-100/80">
                <div class="text-xs font-medium text-slate-600">可立即提交</div>
                <div class="mt-1 text-2xl font-bold text-slate-900"><?= $readyCount ?></div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white/90 px-4 py-3 shadow-sm shadow-slate-100/80">
                <div class="text-xs font-medium text-slate-600">长期有效</div>
                <div class="mt-1 text-2xl font-bold text-slate-900"><?= $longTermCount ?></div>
            </div>
        </div>
    </section>

    <?php if ($tasks === []): ?>
        <section class="tasklist-motion-card rounded-3xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center shadow-sm">
            <div class="mx-auto max-w-md space-y-3">
                <div class="mx-auto inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-500">
                    <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 7.75A2.75 2.75 0 0 1 6.75 5h10.5A2.75 2.75 0 0 1 20 7.75v8.5A2.75 2.75 0 0 1 17.25 19H6.75A2.75 2.75 0 0 1 4 16.25v-8.5Z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 10h8M8 14h5"/>
                    </svg>
                </div>
                <h2 class="text-xl font-semibold text-slate-900">暂时没有可打卡任务</h2>
                <p class="text-sm leading-7 text-slate-500">请稍后刷新页面，或联系管理员确认任务是否已经发布。</p>
            </div>
        </section>
    <?php else: ?>
        <section class="space-y-4 lg:hidden">
            <?php foreach ($tasks as $task): ?>
                <?php
                $id = (int) ($task['id'] ?? 0);
                $name = trim((string) ($task['task_name'] ?? '未命名任务'));
                $desc = trim((string) ($task['task_desc'] ?? ''));
                $linkCode = (string) ($task['task_link_code'] ?? '');
                $publishTime = $formatDate((string) ($task['start_time'] ?? '-'));
                $endTime = trim((string) ($task['end_time'] ?? ''));
                $isLongTerm = (int) ($task['is_long_term'] ?? 0) === 1;
                $isLatest = $latestTaskId > 0 ? ($id === $latestTaskId) : false;
                $statusText = (string) ($task['status_text'] ?? ((int) ($task['task_status'] ?? 1) === 1 ? '可提交' : '不可提交'));
                $statusTone = (string) ($task['status_tone'] ?? ((int) ($task['task_status'] ?? 1) === 1 ? 'ok' : 'off'));
                $statusClass = $statusTone === 'ok'
                    ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                    : ($statusTone === 'expired'
                        ? 'border-rose-200 bg-rose-50 text-rose-700'
                        : ($statusTone === 'pending'
                            ? 'border-amber-200 bg-amber-50 text-amber-700'
                            : 'border-slate-200 bg-slate-100 text-slate-600'));
                $expireText = $isLongTerm || $endTime === '' ? '长期有效' : $formatDate($endTime);
                $expireClass = $isLongTerm || $endTime === ''
                    ? 'border-blue-200 bg-blue-50 text-blue-700'
                    : 'border-amber-200 bg-amber-50 text-amber-700';
                $taskLink = '/task/' . rawurlencode($linkCode);
                ?>
                <article class="tasklist-motion-card tasklist-mobile-card rounded-3xl border <?= $isLatest ? 'border-blue-200' : 'border-slate-200' ?> bg-white p-5 shadow-sm sm:p-6">
                    <div class="tasklist-mobile-head">
                        <div class="tasklist-mobile-copy">
                            <div class="tasklist-mobile-title-row">
                                <h2 class="tasklist-mobile-title text-lg font-semibold text-slate-900"><?= h($name !== '' ? $name : '未命名任务') ?></h2>
                                <?php if ($isLatest): ?>
                                    <span class="tasklist-mobile-latest inline-flex items-center rounded-full border border-blue-200 bg-blue-50 px-2.5 py-0.5 text-[11px] font-semibold text-blue-700">最新</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <span class="tasklist-mobile-status inline-flex shrink-0 items-center rounded-full border px-2.5 py-1 text-xs font-semibold <?= $statusClass ?>">
                            <?= h($statusText) ?>
                        </span>
                    </div>

                    <p class="tasklist-mobile-description text-sm leading-7 text-slate-500">
                        <?= h($desc !== '' ? $desc : '点击下方按钮即可进入打卡页面，按任务要求完成提交。') ?>
                    </p>

                    <div class="mt-4 grid grid-cols-2 gap-3">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-3 py-3">
                            <div class="text-xs font-medium text-slate-400">发布时间</div>
                            <div class="mt-1 text-sm font-semibold text-slate-900"><?= h($publishTime) ?></div>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-3 py-3">
                            <div class="text-xs font-medium text-slate-400">有效期</div>
                            <div class="mt-1 inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold <?= $expireClass ?>"><?= h($expireText) ?></div>
                        </div>
                    </div>

                    <a
                        href="<?= h($taskLink) ?>"
                        class="tasklist-primary-button mt-4 inline-flex h-11 w-full items-center justify-center gap-2 rounded-2xl bg-blue-600 px-5 text-sm font-semibold text-white transition hover:bg-blue-700 sm:h-12 sm:text-base"
                    >
                        <span>进入打卡</span>
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M3.75 10a.75.75 0 0 1 .75-.75h8.69L10.97 7.03a.75.75 0 1 1 1.06-1.06l3.5 3.5a.75.75 0 0 1 0 1.06l-3.5 3.5a.75.75 0 0 1-1.06-1.06l2.22-2.22H4.5a.75.75 0 0 1-.75-.75Z" clip-rule="evenodd"/>
                        </svg>
                    </a>
                </article>
            <?php endforeach; ?>
        </section>

        <section class="hidden lg:block">
            <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 bg-slate-50 px-6 py-4">
                    <div class="grid grid-cols-[minmax(0,1.3fr)_190px_180px_170px] items-center gap-4 text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                        <span>任务信息</span>
                        <span>发布时间</span>
                        <span>有效期</span>
                        <span class="text-right">操作</span>
                    </div>
                </div>

                <div class="divide-y divide-slate-200">
                    <?php foreach ($tasks as $task): ?>
                        <?php
                        $id = (int) ($task['id'] ?? 0);
                        $name = trim((string) ($task['task_name'] ?? '未命名任务'));
                        $desc = trim((string) ($task['task_desc'] ?? ''));
                        $linkCode = (string) ($task['task_link_code'] ?? '');
                        $publishTime = $formatDate((string) ($task['start_time'] ?? '-'));
                        $endTime = trim((string) ($task['end_time'] ?? ''));
                        $isLongTerm = (int) ($task['is_long_term'] ?? 0) === 1;
                        $isLatest = $latestTaskId > 0 ? ($id === $latestTaskId) : false;
                        $statusText = (string) ($task['status_text'] ?? ((int) ($task['task_status'] ?? 1) === 1 ? '可提交' : '不可提交'));
                        $statusTone = (string) ($task['status_tone'] ?? ((int) ($task['task_status'] ?? 1) === 1 ? 'ok' : 'off'));
                        $statusClass = $statusTone === 'ok'
                            ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                            : ($statusTone === 'expired'
                                ? 'border-rose-200 bg-rose-50 text-rose-700'
                                : ($statusTone === 'pending'
                                    ? 'border-amber-200 bg-amber-50 text-amber-700'
                                    : 'border-slate-200 bg-slate-100 text-slate-600'));
                        $expireText = $isLongTerm || $endTime === '' ? '长期有效' : $formatDate($endTime);
                        $expireClass = $isLongTerm || $endTime === ''
                            ? 'border-blue-200 bg-blue-50 text-blue-700'
                            : 'border-amber-200 bg-amber-50 text-amber-700';
                        $taskLink = '/task/' . rawurlencode($linkCode);
                        ?>
                        <article class="tasklist-row group grid grid-cols-[minmax(0,1.3fr)_190px_180px_170px] items-center gap-4 px-6 py-5">
                            <div class="min-w-0 space-y-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="truncate text-lg font-semibold text-slate-900"><?= h($name !== '' ? $name : '未命名任务') ?></h2>
                                    <?php if ($isLatest): ?>
                                        <span class="inline-flex items-center rounded-full border border-blue-200 bg-blue-50 px-2.5 py-0.5 text-[11px] font-semibold text-blue-700">最新任务</span>
                                    <?php endif; ?>
                                    <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold <?= $statusClass ?>">
                                        <?= h($statusText) ?>
                                    </span>
                                </div>

                                <p class="line-clamp-2 text-sm leading-7 text-slate-500">
                                    <?= h($desc !== '' ? $desc : '点击右侧按钮即可进入打卡页，完成图片上传与提交。') ?>
                                </p>
                            </div>

                            <div>
                                <div class="text-xs font-medium text-slate-400">发布时间</div>
                                <div class="mt-1 text-sm font-semibold text-slate-900"><?= h($publishTime) ?></div>
                            </div>

                            <div>
                                <div class="text-xs font-medium text-slate-400">有效期</div>
                                <div class="mt-2 inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold <?= $expireClass ?>">
                                    <?= h($expireText) ?>
                                </div>
                            </div>

                            <div class="flex justify-end">
                                <a
                                    href="<?= h($taskLink) ?>"
                                    class="tasklist-primary-button inline-flex h-11 min-w-[132px] shrink-0 items-center justify-center gap-2 rounded-2xl bg-blue-600 px-5 text-sm font-semibold text-white transition hover:bg-blue-700"
                                >
                                    <span>进入打卡</span>
                                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M3.75 10a.75.75 0 0 1 .75-.75h8.69L10.97 7.03a.75.75 0 1 1 1.06-1.06l3.5 3.5a.75.75 0 0 1 0 1.06l-3.5 3.5a.75.75 0 0 1-1.06-1.06l2.22-2.22H4.5a.75.75 0 0 1-.75-.75Z" clip-rule="evenodd"/>
                                    </svg>
                                </a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>
</div>

<style>
.tasklist-page-shell {
    position: relative;
    isolation: isolate;
}

.tasklist-page-shell::before {
    content: '';
    position: absolute;
    top: -2.4rem;
    right: 1.6rem;
    width: 19rem;
    height: 19rem;
    border-radius: 9999px;
    background: radial-gradient(circle, rgba(59, 130, 246, 0.16) 0%, rgba(125, 211, 252, 0.08) 34%, rgba(255, 255, 255, 0) 72%);
    filter: blur(4px);
    animation: tasklistFloat 7.5s ease-in-out infinite;
    pointer-events: none;
    z-index: -1;
}

.tasklist-motion-card {
    animation: tasklistRise .55s cubic-bezier(.22, 1, .36, 1) both;
    transition: transform .22s ease, box-shadow .22s ease, border-color .22s ease;
}

.tasklist-motion-card:hover {
    transform: translateY(-2px);
    border-color: #cbd5e1;
    box-shadow: 0 18px 36px rgba(15, 23, 42, 0.08);
}

.tasklist-hero {
    background:
        radial-gradient(circle at top right, rgba(96, 165, 250, 0.18), rgba(96, 165, 250, 0) 34%),
        linear-gradient(135deg, #f8fbff 0%, #ffffff 52%, #eff6ff 100%);
}

.tasklist-hero-head {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: start;
    gap: 1rem 1.25rem;
}

.tasklist-hero-copy {
    min-width: 0;
    display: grid;
    align-content: start;
    gap: .24rem .3rem;
}

.tasklist-hero-copy > :where(:not([hidden])) ~ :where(:not([hidden])) {
    margin-top: 0 !important;
}

.tasklist-hero-title-wrap > :where(:not([hidden])) ~ :where(:not([hidden])) {
    margin-top: .22rem !important;
}

.tasklist-hero-actions {
    justify-self: end;
    align-self: start;
}

.tasklist-hero-description {
    grid-column: 1 / -1;
    width: 100%;
    margin: 0;
    max-width: none;
}

.tasklist-secondary-button {
    position: relative;
    isolation: isolate;
    overflow: hidden;
    will-change: transform, box-shadow;
    box-shadow: 0 8px 18px rgba(15, 23, 42, 0.04);
    transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease, background-color .18s ease;
}

.tasklist-secondary-button::after {
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

.tasklist-secondary-button:hover {
    transform: translateY(-1px);
    box-shadow: 0 12px 24px rgba(37, 99, 235, 0.08);
}

.tasklist-secondary-button:hover::after {
    opacity: 1;
    transform: translateX(520%) rotate(18deg);
}

.tasklist-secondary-button:active {
    transform: translateY(0) scale(.98);
}

.tasklist-secondary-button:focus-visible {
    outline: none;
    box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.12), 0 12px 24px rgba(37, 99, 235, 0.08);
    border-color: #93c5fd;
}

.tasklist-primary-button {
    position: relative;
    isolation: isolate;
    overflow: hidden;
    will-change: transform, box-shadow;
    background-image: linear-gradient(135deg, #2563eb 0%, #3b82f6 58%, #60a5fa 100%);
    box-shadow: 0 16px 26px rgba(37, 99, 235, 0.2);
    transition: transform .18s ease, box-shadow .18s ease, background-color .18s ease, filter .18s ease;
}

.tasklist-primary-button::before {
    content: '';
    position: absolute;
    inset: 1px;
    border-radius: inherit;
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.22), rgba(255, 255, 255, 0));
    pointer-events: none;
}

.tasklist-primary-button::after {
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

.tasklist-primary-button:hover {
    transform: translateY(-1px);
    filter: saturate(1.04);
    box-shadow: 0 20px 30px rgba(37, 99, 235, 0.26);
}

.tasklist-primary-button:hover::after {
    opacity: 1;
    transform: translateX(500%) rotate(20deg);
}

.tasklist-primary-button:active {
    transform: translateY(0) scale(.98);
    box-shadow: 0 10px 18px rgba(37, 99, 235, 0.22);
}

.tasklist-primary-button:focus-visible {
    outline: none;
    box-shadow: 0 0 0 4px rgba(96, 165, 250, 0.18), 0 18px 28px rgba(37, 99, 235, 0.24);
}

.tasklist-row {
    position: relative;
    transition: background-color .18s ease, transform .18s ease;
}

.tasklist-row::before {
    content: '';
    position: absolute;
    left: 0;
    top: 14%;
    bottom: 14%;
    width: 3px;
    border-radius: 9999px;
    background: transparent;
    transition: background-color .18s ease;
}

.tasklist-row:hover {
    background: linear-gradient(90deg, rgba(248, 250, 252, 0.9) 0%, rgba(239, 246, 255, 0.72) 100%);
}

.tasklist-row:hover::before {
    background: linear-gradient(180deg, #60a5fa 0%, #2563eb 100%);
}

.tasklist-mobile-head {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: start;
    gap: .42rem .54rem;
}

.tasklist-mobile-copy {
    min-width: 0;
}

.tasklist-mobile-title-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: .24rem .3rem;
}

.tasklist-mobile-title {
    min-width: 0;
    line-height: 1.22;
    overflow-wrap: anywhere;
}

.tasklist-mobile-latest {
    min-height: 1.2rem;
    padding-inline: .48rem;
    line-height: 1;
}

.tasklist-mobile-description {
    display: block;
    width: 100%;
    margin-top: .46rem;
}

.tasklist-mobile-status {
    align-self: start;
    white-space: nowrap;
    line-height: 1;
}

.line-clamp-2 {
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
    overflow: hidden;
}

@keyframes tasklistRise {
    from {
        opacity: 0;
        transform: translateY(16px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes tasklistFloat {
    0%, 100% {
        transform: translate3d(0, 0, 0);
    }
    50% {
        transform: translate3d(0, -10px, 0);
    }
}

@media (max-width: 640px) {
    .tasklist-page-shell {
        gap: 14px;
    }

    .tasklist-page-shell::before {
        right: -.5rem;
        width: 12rem;
        height: 12rem;
    }

    .tasklist-motion-card {
        border-radius: 24px;
    }

    .tasklist-hero-head {
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 8px 12px;
        padding: 18px 16px 14px;
    }

    .tasklist-hero h1 {
        font-size: 1.7rem;
        line-height: 1.2;
    }

    .tasklist-hero-actions {
        justify-self: end;
        align-self: start;
    }

    .tasklist-hero-description {
        width: 100%;
        margin-top: 2px;
        line-height: 1.68;
    }

    .tasklist-hero-stats {
        gap: 8px;
        padding: 14px 16px 16px;
    }

    .tasklist-hero-stats > div {
        border-radius: 18px;
        padding: 10px 10px 11px;
    }

    .tasklist-hero-stats .text-xs {
        font-size: .68rem;
        line-height: 1.2;
    }

    .tasklist-hero-stats .text-2xl {
        margin-top: 4px;
        font-size: 1.12rem;
        line-height: 1.1;
    }

    .tasklist-mobile-card {
        padding: 14px;
    }

    .tasklist-mobile-card h2 {
        font-size: .96rem;
        line-height: 1.2;
    }

    .tasklist-mobile-head {
        gap: .3rem .42rem;
    }

    .tasklist-mobile-description {
        width: 100%;
        margin-top: .4rem;
    }

    .tasklist-mobile-latest {
        min-height: 1.04rem;
        padding-inline: .38rem;
        font-size: .58rem;
    }

    .tasklist-mobile-status {
        padding-inline: .48rem;
        padding-block: .22rem;
        font-size: .68rem;
    }

    .tasklist-mobile-card p {
        line-height: 1.65;
    }

    .tasklist-mobile-card .tasklist-primary-button {
        width: 100%;
    }

    .tasklist-hero-actions .tasklist-secondary-button {
        width: auto;
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

    .tasklist-hero-actions .tasklist-secondary-button svg {
        width: .8rem;
        height: .8rem;
    }
}

@media (max-width: 430px) {
    .tasklist-hero-actions .tasklist-secondary-button {
        height: 32px;
        min-height: 32px;
        padding-inline: .64rem;
        font-size: .74rem;
    }

    .tasklist-hero-stats > div {
        padding: 9px 8px 10px;
    }

    .tasklist-hero-stats .text-2xl {
        font-size: 1rem;
    }
}

@media (prefers-reduced-motion: reduce) {
    .tasklist-motion-card,
    .tasklist-page-shell::before {
        animation: none !important;
    }

    .tasklist-secondary-button,
    .tasklist-secondary-button::after,
    .tasklist-primary-button,
    .tasklist-primary-button::after,
    .tasklist-row {
        transition: none !important;
    }
}
</style>

<!-- Popup Announcement Modal -->
<div id="popup-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(255,255,255,.15);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);align-items:center;justify-content:center;padding:16px;">
    <div style="background:rgba(255,255,255,.92);border-radius:16px;max-width:520px;width:100%;max-height:80vh;overflow-y:auto;padding:24px;box-shadow:0 20px 60px rgba(0,0,0,.15);border:1px solid rgba(255,255,255,.6);">
        <div id="popup-content" style="font-size:14px;line-height:1.7;color:#334155;"></div>
        <div style="display:flex;justify-content:flex-end;margin-top:16px;">
            <button id="popup-close" style="padding:5px 14px;border-radius:14px;border:1px solid #e2e8f0;background:#f8fafc;color:#64748b;font-size:12px;font-weight:500;cursor:pointer;">关闭</button>
        </div>
    </div>
</div>
<script>
(function(){
    var modal = document.getElementById('popup-modal');
    var closeBtn = document.getElementById('popup-close');
    var contentEl = document.getElementById('popup-content');
    if (!modal) return;

    function escapeHtml(s){return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
    function inlineToHtml(s){
        s = s.replace(/\*\*(.+?)\*\*/g,'<strong>$1</strong>');
        s = s.replace(/\*(.+?)\*/g,'<em>$1</em>');
        s = s.replace(/<u>(.+?)<\/u>/g,'<u>$1</u>');
        s = s.replace(/<p>(.+?)<\/p>/g,'<p class="text-sm leading-6 text-slate-700">$1</p>');
        s = s.replace(/<span style="color:([^"]+)">(.+?)<\/span>/g,'<span style="color:$1">$2</span>');
        s = s.replace(/\[([^\]]+)\]\(([^)]+)\)/g,'<a href="$2" class="text-blue-600 hover:text-blue-800" target="_blank">$1</a>');
        return s;
    }
    function renderMarkdown(md){
        var input = (md||'').replace(/\r\n?/g,'\n').trim();
        if(!input) return '';
        var lines = input.split('\n'), out=[];
        lines.forEach(function(line){
            var t = line.trim();
            if(!t) return;
            if(/^[-*]\s+(.+)$/.test(t)){out.push('<li style="margin-left:20px">'+inlineToHtml(t.replace(/^[-*]\s+/,''))+'</li>');return;}
            var hm=t.match(/^(#{1,6})\s+(.+)$/);
            if(hm){out.push('<h'+hm[1].length+' style="font-weight:600;margin:8px 0">'+inlineToHtml(hm[2])+'</h'+hm[1].length+'>');return;}
            if(/^>\s+/.test(t)){out.push('<blockquote style="border-left:3px solid #cbd5e1;padding:4px 12px;color:#475569">'+inlineToHtml(t.replace(/^>\s+/,''))+'</blockquote>');return;}
            if(/^(-{3}|\*{3}|_{3})$/.test(t)){out.push('<hr style="margin:8px 0;border-color:#e2e8f0">');return;}
            out.push('<p style="margin:4px 0;line-height:1.7">'+inlineToHtml(t)+'</p>');
        });
        return out.join('');
    }

    var localKey = 'popup_read_';
    var isLoggedIn = <?= $currentUser !== null ? 'true' : 'false' ?>;

    fetch('/api/user/popup/active').then(function(r){return r.json();}).then(function(res){
        if (!res.ok || !res.data) return;
        var d = res.data;
        var id = d.id;
        var audience = d.target_audience || 'all';
        var frequency = d.frequency || 'once_per_day';

        // Check target audience
        if (audience === 'logged_in' && !isLoggedIn) return;
        if (audience === 'guest' && isLoggedIn) return;

        // Check frequency
        if (frequency === 'once_per_day') {
            var todayKey = localKey + id + '_' + new Date().toISOString().slice(0,10);
            if (localStorage.getItem(todayKey) || d.read) return;
        } else if (frequency === 'once_only') {
            var onceKey = localKey + id + '_once';
            if (localStorage.getItem(onceKey) || d.read) return;
        }
        // frequency === 'every_time': always show

        contentEl.innerHTML = renderMarkdown(d.content);
        modal.style.display = 'flex';

        closeBtn.onclick = function(){
            modal.style.display = 'none';
            if (frequency === 'once_per_day') {
                localStorage.setItem(localKey + id + '_' + new Date().toISOString().slice(0,10), '1');
            } else if (frequency === 'once_only') {
                localStorage.setItem(localKey + id + '_once', '1');
            }
            fetch('/api/user/popup/read', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({popup_id:id})});
        };
    }).catch(function(){});
})();
</script>

<?php if ($currentUser === null): ?>
<!-- Login Modal -->
<div id="login-modal" style="display:none;position:fixed;inset:0;z-index:10000;background:rgba(0,0,0,.4);backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px);align-items:center;justify-content:center;padding:16px;">
    <div style="background:#fff;border-radius:16px;max-width:400px;width:100%;padding:28px;box-shadow:0 20px 60px rgba(0,0,0,.18);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
            <h3 style="font-size:18px;font-weight:700;color:#1e293b;">用户登录</h3>
            <button id="login-modal-close" style="width:28px;height:28px;border-radius:8px;border:1px solid #e2e8f0;background:#f8fafc;cursor:pointer;display:flex;align-items:center;justify-content:center;">✕</button>
        </div>
        <form method="post" action="/login" style="display:flex;flex-direction:column;gap:14px;">
            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
            <input type="text" name="account" required placeholder="手机号 / 邮箱" style="width:100%;padding:10px 14px;border:1px solid #e2e8f0;border-radius:10px;font-size:14px;outline:none;">
            <input type="password" name="password" required placeholder="密码" style="width:100%;padding:10px 14px;border:1px solid #e2e8f0;border-radius:10px;font-size:14px;outline:none;">
            <button type="submit" style="width:100%;padding:10px;background:#2563eb;color:#fff;border:none;border-radius:10px;font-size:14px;font-weight:600;cursor:pointer;">登 录</button>
        </form>
        <div style="margin-top:16px;display:flex;justify-content:space-between;font-size:13px;">
            <a href="/register" style="color:#2563eb;text-decoration:none;">注册账号</a>
            <a href="javascript:void(0)" onclick="alert('请联系管理员重置密码')" style="color:#64748b;text-decoration:none;">忘记密码？</a>
        </div>
    </div>
</div>
<script>
(function(){
    var btn = document.getElementById('open-login-modal');
    var modal = document.getElementById('login-modal');
    var closeBtn = document.getElementById('login-modal-close');
    if (!btn || !modal) return;
    btn.addEventListener('click', function(){ modal.style.display = 'flex'; });
    closeBtn.addEventListener('click', function(){ modal.style.display = 'none'; });
    modal.addEventListener('click', function(e){ if(e.target === modal) modal.style.display = 'none'; });
})();
</script>
<?php endif; ?>

<?php if ($publisherAnnouncements !== []): ?>
<div id="pub-announce-modal" style="position:fixed;inset:0;z-index:9998;background:rgba(0,0,0,.45);backdrop-filter:blur(4px);display:flex;align-items:center;justify-content:center;padding:16px;">
    <div style="background:#fff;border-radius:18px;max-width:460px;width:100%;max-height:80vh;overflow:auto;box-shadow:0 20px 60px rgba(0,0,0,.2);">
        <div style="background:linear-gradient(to right,#f59e0b,#f97316);padding:16px 20px;display:flex;align-items:center;justify-content:space-between;">
            <h3 style="color:#fff;font-size:16px;font-weight:700;margin:0;">📢 公告</h3>
            <button id="pub-announce-close" type="button" style="width:28px;height:28px;border-radius:8px;border:none;background:rgba(255,255,255,.25);color:#fff;cursor:pointer;font-size:14px;">✕</button>
        </div>
        <div style="padding:20px;display:flex;flex-direction:column;gap:14px;">
            <?php foreach ($publisherAnnouncements as $a): ?>
                <div style="border-bottom:1px solid #f1f5f9;padding-bottom:12px;">
                    <p style="white-space:pre-wrap;font-size:14px;line-height:1.7;color:#334155;margin:0;"><?= h(trim((string) ($a['content'] ?? ''))) ?></p>
                    <div style="font-size:11px;color:#94a3b8;margin-top:6px;"><?= h(substr((string) ($a['created_at'] ?? ''), 0, 10)) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<script>
(function(){
    var key = 'pub_announce_seen_<?= md5(json_encode(array_map(static fn($a)=>(int)($a['id']??0).':'.($a['updated_at']??''), $publisherAnnouncements))) ?>';
    var modal = document.getElementById('pub-announce-modal');
    var close = document.getElementById('pub-announce-close');
    if (!modal) return;
    try { if (sessionStorage.getItem(key)) { modal.style.display='none'; return; } } catch(e){}
    function dismiss(){ modal.style.display='none'; try{ sessionStorage.setItem(key,'1'); }catch(e){} }
    close.addEventListener('click', dismiss);
    modal.addEventListener('click', function(e){ if(e.target===modal) dismiss(); });
})();
</script>
<?php endif; ?>
