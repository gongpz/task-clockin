<?php
$task = is_array($task ?? null) ? $task : [];
$members = is_array($members ?? null) ? $members : [];
$csrf = (string) ($csrf ?? '');
$linkCode = (string) ($linkCode ?? ($task['task_link_code'] ?? ''));
$blockReason = $blockReason ?? null;
$boundMemberId = (int) ($boundMemberId ?? 0);
$flash = is_array($flash ?? null) ? $flash : ['type' => '', 'message' => '', 'meta' => []];
$flashMessage = trim((string) ($flash['message'] ?? ''));
$flashType = (string) ($flash['type'] ?? '');
$flashMeta = is_array($flash['meta'] ?? null) ? $flash['meta'] : [];

$translateMessage = static function (string $text): string {
    $text = trim($text);
    if ($text === '') {
        return '';
    }

    $map = [
        'Check-in Failed' => '打卡失败',
        'Check-in Success' => '打卡成功',
        'Check-in failed. Please try again.' => '打卡失败，请稍后重试。',
        'Check-in failed. Please try again later.' => '打卡失败，请稍后重试。',
        'Check-in succeeded.' => '打卡成功。',
        'Task is invalid.' => '任务无效。',
        'Task not found.' => '任务不存在。',
        'Task is not open for submission.' => '当前任务不可提交。',
        'Please select a valid member.' => '请选择有效成员。',
        'This member is not allowed for this task.' => '该成员不在任务可提交名单中。',
        'Please select a valid check-in date.' => '请选择有效的打卡日期。',
        'Image count is invalid.' => '上传图片数量不符合要求。',
        'Override is not allowed for this task.' => '当前任务不允许覆盖提交。',
        'Duplicate submission is not allowed for this task.' => '当前任务不允许重复提交。',
        'Duplicate check-in detected. Please confirm whether to override.' => '检测到重复打卡，请确认是否覆盖提交。',
        'Submission is not allowed now.' => '当前时间不可提交。',
    ];

    if (isset($map[$text])) {
        return $map[$text];
    }

    if (preg_match('/Duplicate check-in detected\. Last submit time:\s*(.+)$/i', $text, $m)) {
        return '检测到重复打卡，上次提交时间：' . trim((string) $m[1]);
    }

    if (preg_match('/Image count must be between\s*(\d+)\s*and\s*(\d+)/i', $text, $m)) {
        return '上传图片数量需在 ' . $m[1] . ' 到 ' . $m[2] . ' 张之间。';
    }

    return $text;
};

$flashMessage = $translateMessage($flashMessage);
$blockReasonText = $blockReason === null ? null : $translateMessage((string) $blockReason);

$taskName = trim((string) ($task['task_name'] ?? '打卡任务'));
$taskDesc = trim((string) ($task['task_desc'] ?? ''));
$taskStart = trim((string) ($task['start_time'] ?? ''));
$taskEnd = trim((string) ($task['end_time'] ?? ''));
$minImages = max(0, (int) ($task['min_images'] ?? 1));
$maxImages = max(1, (int) ($task['max_images'] ?? 3));
$defaultTaskDate = date('Y-m-d');

$formatDate = static function (string $value): string {
    $value = trim($value);
    if ($value === '') {
        return '-';
    }
    $ts = strtotime($value);
    return $ts === false ? $value : date('Y-m-d', $ts);
};

$formatDateTime = static function (string $value): string {
    $value = trim($value);
    if ($value === '') {
        return '-';
    }
    $ts = strtotime($value);
    return $ts === false ? $value : date('Y-m-d H:i', $ts);
};

$taskWindowText = $formatDate($taskStart) . ' ~ ' . ($taskEnd === '' ? '长期有效' : $formatDate($taskEnd));
$taskImageRuleText = $minImages === $maxImages
    ? ('需上传 ' . $minImages . ' 张图片')
    : ('需上传 ' . $minImages . ' - ' . $maxImages . ' 张图片');
$taskStatusText = $blockReasonText === null ? '可提交' : $blockReasonText;
$taskStatusClass = $blockReasonText === null
    ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
    : 'border-slate-200 bg-slate-100 text-slate-600';
$memberCount = count($members);
$memberGridClass = $memberCount >= 10 ? 'md:grid-cols-5' : 'md:grid-cols-4';

$showModal = $flashMessage !== '' && ((bool) ($flashMeta['show_modal'] ?? true));
$isSuccessModal = $flashType === 'success';
$modalTitle = trim((string) ($flashMeta['modal_title'] ?? ($isSuccessModal ? '打卡成功' : '打卡失败')));
$modalTitle = $translateMessage($modalTitle);
$modalTaskName = trim((string) ($flashMeta['task_name'] ?? $taskName));
$modalMemberName = trim((string) ($flashMeta['member_name'] ?? ''));
$modalTaskDate = trim((string) ($flashMeta['task_date'] ?? ''));
$modalSubmitTime = trim((string) ($flashMeta['submit_time'] ?? date('Y-m-d H:i:s')));
$modalImageCount = (int) ($flashMeta['image_count'] ?? 0);
$modalCheckinCount = (int) ($flashMeta['checkin_count'] ?? 0);
$modalIsOverride = (bool) ($flashMeta['is_override'] ?? false);
?>


<div class="checkin-page-shell mx-auto max-w-7xl space-y-6 pb-10">
    <section class="checkin-main-grid grid gap-6 xl:grid-cols-[minmax(0,1fr)_300px]">
        <article class="checkin-motion-card overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="checkin-hero border-b border-slate-200 bg-slate-50 px-5 py-5 sm:px-6 sm:py-6">
            <div class="checkin-hero-top">
                <div class="checkin-hero-copy space-y-2">
                    <div class="text-sm font-semibold text-blue-600">打卡任务</div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                        <?= h($taskName !== '' ? $taskName : '打卡任务') ?>
                    </h1>
                    <p class="max-w-3xl text-sm leading-7 text-slate-500">
                        <?= h($taskDesc !== '' ? $taskDesc : '请按任务要求完成打卡上传。支持一次选择多张图片，也支持分次逐张追加上传。') ?>
                    </p>
                </div>

                <div class="checkin-hero-right">
                    <div class="checkin-hero-status checkin-hero-status--desktop rounded-2xl border border-slate-200 bg-white px-3 py-2">
                        <span class="text-xs font-medium text-slate-400">当前状态</span>
                        <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold <?= $taskStatusClass ?>">
                            <?= h($taskStatusText) ?>
                        </span>
                    </div>
                    <a
                        href="/tasks"
                        class="checkin-back-link checkin-back-link--desktop inline-flex h-10 items-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M16.25 10a.75.75 0 0 1-.75.75H6.81l2.22 2.22a.75.75 0 1 1-1.06 1.06l-3.5-3.5a.75.75 0 0 1 0-1.06l3.5-3.5a.75.75 0 1 1 1.06 1.06L6.81 9.25h8.69a.75.75 0 0 1 .75.75Z" clip-rule="evenodd"/>
                        </svg>
                        返回任务列表
                    </a>
                </div>

                <div class="checkin-hero-actions">
                    <a
                        href="/tasks"
                        class="checkin-back-link checkin-back-link--mobile inline-flex h-10 items-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700"
                    >
                        <span>&larr;</span>
                        <span>返回任务列表</span>
                    </a>
                    <div class="checkin-hero-status checkin-hero-status--mobile rounded-2xl border border-slate-200 bg-white px-3 py-2">
                        <span class="text-xs font-medium text-slate-400">当前状态</span>
                        <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold <?= $taskStatusClass ?>">
                            <?= h($taskStatusText) ?>
                        </span>
                    </div>
                </div>
            </div>

                <div class="checkin-hero-meta mt-4 grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                    <div class="rounded-2xl border border-slate-200 bg-white px-4 py-2.5">
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <div class="shrink-0 text-xs font-medium text-slate-400">有效时间</div>
                            <div class="min-w-0 flex-1 truncate text-right font-semibold text-slate-900"><?= h($taskWindowText) ?></div>
                        </div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white px-4 py-2.5">
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <div class="shrink-0 text-xs font-medium text-slate-400">图片规则</div>
                            <div class="min-w-0 flex-1 truncate text-right font-semibold text-slate-900"><?= h($taskImageRuleText) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="space-y-5 px-5 py-5 sm:px-6 sm:py-6">
                <?php if ($blockReasonText !== null): ?>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4 text-sm leading-7 text-slate-600">
                        当前任务暂不可提交。请联系管理员，或稍后再试。
                    </div>
                <?php else: ?>
                    <form id="checkin-form" method="post" action="/task/<?= h(rawurlencode($linkCode)) ?>/checkin" enctype="multipart/form-data" class="space-y-5">
                        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                        <input type="hidden" name="member_id" id="checkin-member-id" value="">
                        <input type="hidden" name="task_date" value="<?= h($defaultTaskDate) ?>">

                        <section class="rounded-3xl border border-slate-200 bg-slate-50 px-5 py-5 sm:px-6">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <h2 class="text-lg font-semibold text-slate-900">选择姓名</h2>
                                </div>
                            </div>
                            <?php if ($memberCount === 0): ?>
                                <div class="mt-4 rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-6 text-sm text-slate-500">
                                    当前任务暂无可提交成员，请联系管理员处理。
                                </div>
                            <?php else: ?>
                                <div class="checkin-member-grid mt-4 <?= h($memberGridClass) ?>">
                                    <?php foreach ($members as $member): ?>
                                        <?php
                                        $memberId = (int) ($member['id'] ?? 0);
                                        $memberName = trim((string) ($member['name'] ?? ''));
                                        if ($memberId <= 0 || $memberName === '') {
                                            continue;
                                        }
                                        ?>
                                        <button type="button" class="checkin-member-pill" data-member-id="<?= $memberId ?>">
                                            <span class="checkin-member-pill-text"><?= h($memberName) ?></span>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </section>

                        <div class="grid gap-4 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
                            <section class="order-2 rounded-3xl border border-slate-200 bg-white px-5 py-4 shadow-sm sm:px-6 lg:order-1">
                                <label class="checkin-remark-field block text-sm font-medium text-slate-700">
                                    <span class="text-lg font-semibold text-slate-900">备注说明</span>
                                    <textarea
                                        name="remark"
                                        rows="4"
                                        placeholder="选填，可补充本次打卡的简单说明。"
                                        class="mt-1.5 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm leading-7 text-slate-900 outline-none transition focus:border-blue-400 focus:bg-white focus:ring-4 focus:ring-blue-50"
                                    ></textarea>
                                </label>
                            </section>

                            <section
                                id="checkin-image-field"
                                data-min-images="<?= $minImages ?>"
                                data-max-images="<?= $maxImages ?>"
                                class="checkin-upload-shell order-1 rounded-3xl border border-slate-200 bg-white px-5 py-4 shadow-sm sm:px-6 lg:order-2"
                            >
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <h2 class="text-lg font-semibold text-slate-900">上传图片</h2>
                                    </div>
                                    <span
                                        id="checkin-image-count"
                                        class="inline-flex items-center rounded-full border border-blue-200 bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700"
                                    >
                                        已选 0 / <?= $maxImages ?> 张
                                    </span>
                                </div>

                                <div id="checkin-image-picker-host" class="mt-3.5"></div>
                                <div class="mt-3.5 overflow-hidden rounded-2xl border border-slate-200 bg-slate-50/90">
                                    <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-2.5 text-xs sm:text-sm">
                                        <div id="checkin-image-progress-text" class="font-semibold text-slate-700">请选择图片后提交</div>
                                        <div id="checkin-image-progress-meta" class="inline-flex items-center rounded-full bg-white px-3 py-1 font-medium text-slate-600 shadow-sm shadow-slate-200/70">还差 <?= $minImages ?> 张</div>
                                    </div>
                                    <div class="px-4 pb-4">
                                        <div class="checkin-image-progress-track">
                                            <div id="checkin-image-progress-fill" class="checkin-image-progress-fill" style="width: 0%"></div>
                                        </div>
                                    </div>
                                </div>
                                <div id="checkin-image-preview" class="mt-4 grid grid-cols-3 gap-3 sm:grid-cols-3 xl:grid-cols-4"></div>
                                <div id="checkin-image-inputs" class="hidden"></div>
                                <p id="checkin-image-error" class="mt-2.5 hidden text-sm font-medium text-rose-600"></p>

                                <noscript>
                                    <label class="mt-4 block text-sm font-medium text-slate-700">
                                        上传图片
                                        <input
                                            type="file"
                                            name="images[]"
                                            accept="image/*"
                                            multiple
                                            required
                                            class="mt-2 block w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 file:mr-3 file:rounded-xl file:border-0 file:bg-blue-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-blue-700 hover:file:bg-blue-100"
                                        >
                                    </label>
                                </noscript>
                            </section>
                        </div>

                        <section class="rounded-3xl border border-slate-200 bg-white px-5 py-4 shadow-sm sm:px-6">
                            <div class="checkin-submit-layout">
                                <div class="checkin-submit-stack space-y-2.5">
                                    <label class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                                        <input type="checkbox" name="force_override" value="1" class="mt-1 h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                        <span>
                                            <span class="block font-semibold text-slate-800">允许覆盖旧记录</span>
                                            <span class="mt-1 block text-xs leading-6 text-slate-500">如果同一天已经提交过打卡，勾选后会用这次内容覆盖旧记录。</span>
                                        </span>
                                    </label>

                                    <button
                                        type="submit"
                                        data-submit-label="提交打卡"
                                        data-loading-label="图片处理中，请稍候..."
                                        class="checkin-submit-button inline-flex h-11 w-full items-center justify-center rounded-2xl bg-blue-600 px-5 text-sm font-semibold text-white transition hover:bg-blue-700 sm:h-12 sm:text-base"
                                    >
                                        提交打卡
                                    </button>

                                    <p id="checkin-member-error" class="hidden text-sm font-medium leading-6 text-rose-600">请先选择姓名后再提交。</p>
                                    <p id="checkin-submit-status" class="hidden text-sm font-medium leading-6 text-blue-600">图片上传后正在处理中，请稍候，不要重复点击。</p>

                                </div>
                            </div>
                        </section>
                    </form>
                <?php endif; ?>
            </div>
        </article>

        <aside class="space-y-4">
            <section class="checkin-motion-card checkin-motion-delay-1 rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h2 class="text-lg font-semibold text-slate-900">提交说明</h2>
                </div>
                <div class="space-y-3 px-5 py-5 text-sm leading-7 text-slate-600">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        支持一次选择多张图片，也支持分次逐张追加上传。
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        提交时会统一校验图片数量，至少 <strong class="text-slate-900"><?= $minImages ?></strong> 张，最多 <?= $maxImages ?> 张。
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        如需覆盖同日期旧记录，请先勾选“允许覆盖旧记录”后再提交。
                    </div>
                </div>
            </section>
        </aside>
    </section>
</div>
<?php if ($showModal): ?>
    <div id="checkin-result-modal" class="checkin-result-modal fixed inset-0 z-[80] flex items-center justify-center bg-slate-950/45 px-4 py-6" role="dialog" aria-modal="true" aria-hidden="false" aria-labelledby="checkin-modal-title">
        <div class="checkin-result-panel w-full overflow-hidden rounded-3xl border <?= $isSuccessModal ? 'border-emerald-200' : 'border-slate-200' ?> bg-white shadow-xl">
            <div class="checkin-modal-header flex items-start justify-between gap-3 border-b border-slate-200 px-5 py-4 sm:px-6">
                <div>
                    <div class="checkin-modal-eyebrow"><?= $isSuccessModal ? '提交结果' : '错误提醒' ?></div>
                    <h3 id="checkin-modal-title" class="checkin-modal-title <?= $isSuccessModal ? 'checkin-modal-title--success' : 'checkin-modal-title--error' ?>"><?= h($modalTitle) ?></h3>
                </div>
                <button
                    id="checkin-modal-close"
                    type="button"
                    data-modal-close="1"
                    onclick="return window.closeCheckinResultModal ? window.closeCheckinResultModal(event) : false;"
                    onpointerup="return window.closeCheckinResultModal ? window.closeCheckinResultModal(event) : false;"
                    aria-label="关闭弹窗"
                    class="checkin-modal-close inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-100"
                >×</button>
            </div>

            <?php if ($isSuccessModal): ?>
                <div class="checkin-modal-body px-4 py-4 sm:px-5 sm:py-5">
                    <div class="checkin-modal-stack">
                        <div class="checkin-modal-card">
                            <div class="checkin-modal-card-label">打卡任务</div>
                            <div class="checkin-modal-card-value"><?= h($modalTaskName !== '' ? $modalTaskName : '-') ?></div>
                        </div>

                        <div class="checkin-modal-card">
                            <div class="checkin-modal-card-label">打卡时间</div>
                            <div class="checkin-modal-card-value"><?= h($formatDateTime($modalSubmitTime)) ?></div>
                        </div>

                        <div class="checkin-modal-grid">
                            <div class="checkin-modal-card">
                                <div class="checkin-modal-card-label">打卡成员</div>
                                <div class="checkin-modal-card-value"><?= h($modalMemberName !== '' ? $modalMemberName : '-') ?></div>
                            </div>
                            <div class="checkin-modal-card">
                                <div class="checkin-modal-card-label">打卡日期</div>
                                <div class="checkin-modal-card-value"><?= h($modalTaskDate !== '' ? $modalTaskDate : '-') ?></div>
                            </div>
                        </div>

                        <div class="checkin-modal-grid">
                            <div class="checkin-modal-card checkin-modal-card--accent">
                                <div class="checkin-modal-card-label checkin-modal-card-label--accent">本次图片</div>
                                <div class="checkin-modal-card-value checkin-modal-card-value--accent"><?= $modalImageCount ?> 张</div>
                            </div>
                            <div class="checkin-modal-card">
                                <div class="checkin-modal-card-label">累计打卡</div>
                                <div class="checkin-modal-card-value"><?= $modalCheckinCount ?> 次</div>
                            </div>
                        </div>

                        <?php if ($modalIsOverride): ?>
                            <div class="checkin-modal-note rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm leading-7 text-slate-600">
                                本次提交覆盖了同日期的旧记录，后台会保留最新一次内容。
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="checkin-modal-body px-4 py-4 sm:px-5 sm:py-5">
                    <div class="checkin-modal-stack">
                        <div class="checkin-modal-card checkin-modal-card--danger">
                            <div class="checkin-modal-card-label checkin-modal-card-label--danger">错误原因</div>
                            <div class="checkin-modal-card-value checkin-modal-card-value--danger">
                                <?= h($flashMessage !== '' ? $flashMessage : '打卡失败，请稍后重试。') ?>
                            </div>
                        </div>
                        <div class="checkin-modal-note rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm leading-7 text-slate-600">
                            请检查姓名、图片数量或网络状态后重新提交；如果仍然失败，请联系管理员处理。
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="checkin-modal-footer flex justify-end border-t border-slate-200 px-4 py-3.5 sm:px-5 sm:py-4">
                <button
                    id="checkin-modal-confirm"
                    type="button"
                    data-modal-close="1"
                    onclick="return window.closeCheckinResultModal ? window.closeCheckinResultModal(event) : false;"
                    onpointerup="return window.closeCheckinResultModal ? window.closeCheckinResultModal(event) : false;"
                    class="checkin-modal-confirm inline-flex items-center justify-center rounded-2xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700"
                >我知道了</button>
            </div>
        </div>
    </div>
<?php endif; ?>

<div id="checkin-image-lightbox" class="checkin-image-lightbox hidden" hidden aria-hidden="true">
    <div class="checkin-image-lightbox-backdrop" data-lightbox-close="1"></div>
    <div class="checkin-image-lightbox-dialog" role="dialog" aria-modal="true" aria-label="????">
        <button type="button" class="checkin-image-lightbox-close" data-lightbox-close="1" aria-label="??????">&times;</button>
        <img id="checkin-image-lightbox-image" src="" alt="????">
    </div>
</div>

<div id="checkin-processing-overlay" class="checkin-processing-overlay hidden" hidden aria-hidden="true">
    <div class="checkin-processing-overlay__backdrop"></div>
    <div class="checkin-processing-overlay__panel" role="status" aria-live="polite">
        <div class="checkin-processing-overlay__spinner"></div>
        <div class="checkin-processing-overlay__title">?????????</div>
        <div class="checkin-processing-overlay__desc">?????????????????????????</div>
    </div>
</div>

<style>
.checkin-page-shell {
    position: relative;
    isolation: isolate;
}

.checkin-page-shell::before {
    content: '';
    position: absolute;
    top: -2.2rem;
    right: 2rem;
    width: 18rem;
    height: 18rem;
    border-radius: 9999px;
    background: radial-gradient(circle, rgba(59, 130, 246, 0.16) 0%, rgba(96, 165, 250, 0.1) 34%, rgba(255, 255, 255, 0) 72%);
    filter: blur(4px);
    animation: checkinFloat 7.5s ease-in-out infinite;
    pointer-events: none;
    z-index: -1;
}

.checkin-motion-card {
    animation: checkinRise .55s cubic-bezier(.22, 1, .36, 1) both;
    transition: transform .22s ease, box-shadow .22s ease, border-color .22s ease;
}

.checkin-motion-card:hover {
    transform: translateY(-2px);
    border-color: #cbd5e1;
    box-shadow: 0 18px 36px rgba(15, 23, 42, 0.08);
}

.checkin-motion-delay-1 {
    animation-delay: .08s;
}

.checkin-motion-delay-2 {
    animation-delay: .16s;
}

.checkin-hero {
    background:
        radial-gradient(circle at top right, rgba(96, 165, 250, 0.16), rgba(96, 165, 250, 0) 34%),
        linear-gradient(135deg, #f8fbff 0%, #ffffff 48%, #eff6ff 100%);
}

.checkin-hero-top {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: start;
    gap: 1rem 1.25rem;
}

.checkin-hero-right {
    display: none;
    flex-direction: row;
    align-items: center;
    gap: 0.75rem;
}

.checkin-hero-title-row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
}

.checkin-hero-title-row h1 {
    min-width: 0;
    flex: 1 1 auto;
}

.checkin-hero-actions {
    display: none;
}

.checkin-back-link--desktop {
    display: none;
}

.checkin-hero-status {
    display: inline-flex;
    align-items: center;
    justify-content: flex-end;
    gap: .55rem;
    text-align: right;
    box-shadow: 0 8px 18px rgba(15, 23, 42, 0.04);
    flex: 0 0 auto;
}

.checkin-hero-status--desktop {
    min-width: fit-content;
}

.checkin-hero-status--mobile {
    display: none;
}

.checkin-submit-layout {
    display: flex;
    justify-content: flex-end;
}

.checkin-remark-field textarea {
    min-height: 10rem;
}

.checkin-upload-shell {
    position: relative;
    overflow: hidden;
}

.checkin-upload-shell::after {
    content: '';
    position: absolute;
    inset: auto -15% -60% auto;
    width: 11rem;
    height: 11rem;
    border-radius: 9999px;
    background: radial-gradient(circle, rgba(14, 165, 233, 0.12) 0%, rgba(14, 165, 233, 0) 72%);
    pointer-events: none;
}

.checkin-image-progress-track {
    overflow: hidden;
    height: .6rem;
    border-radius: 9999px;
    background: linear-gradient(90deg, #dbeafe 0%, #e2e8f0 100%);
}

.checkin-image-progress-fill {
    height: 100%;
    width: 0;
    border-radius: inherit;
    background: linear-gradient(90deg, #2563eb 0%, #38bdf8 100%);
    box-shadow: 0 8px 18px rgba(37, 99, 235, 0.24);
    transition: width .28s ease, background .22s ease, box-shadow .22s ease;
}

.checkin-upload-shell.is-ready .checkin-image-progress-fill {
    background: linear-gradient(90deg, #0ea5e9 0%, #14b8a6 100%);
    box-shadow: 0 8px 18px rgba(20, 184, 166, 0.24);
}

.checkin-upload-shell.is-maxed .checkin-image-progress-fill {
    background: linear-gradient(90deg, #2563eb 0%, #14b8a6 50%, #22c55e 100%);
}

.checkin-member-grid {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: .65rem;
}

.checkin-member-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 2.85rem;
    border: 1px solid #dbe2ea;
    border-radius: 1rem;
    background: #fff;
    color: #1e293b;
    font-size: clamp(.74rem, 1.15vw, .84rem);
    font-weight: 600;
    line-height: 1.18;
    padding: .5rem .42rem;
    text-align: center;
    box-shadow: 0 1px 0 rgba(15, 23, 42, 0.03);
    transition: transform .16s ease, border-color .16s ease, background-color .16s ease, color .16s ease, box-shadow .16s ease;
}

.checkin-member-pill-text {
    display: -webkit-box;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    word-break: break-all;
    text-align: center;
}

.checkin-member-pill:hover {
    transform: translateY(-1px);
    border-color: #bfdbfe;
    background: #eff6ff;
    color: #1d4ed8;
    box-shadow: 0 10px 18px rgba(59, 130, 246, 0.12);
}

.checkin-member-pill.is-active {
    border-color: #2563eb;
    background: #2563eb;
    color: #fff;
    transform: translateY(-1px);
    box-shadow: 0 14px 24px rgba(37, 99, 235, 0.2);
}

.checkin-image-picker {
    position: relative;
    display: flex;
    min-height: 4rem;
    align-items: center;
    justify-content: center;
    gap: .6rem;
    border: 1px dashed #93c5fd;
    border-radius: 1.2rem;
    background: #f8fbff;
    padding: .95rem 1rem;
    cursor: pointer;
    overflow: hidden;
    -webkit-tap-highlight-color: transparent;
    touch-action: manipulation;
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.7);
    transition: transform .18s ease, border-color .18s ease, background-color .18s ease, box-shadow .18s ease;
}

.checkin-image-picker::before {
    content: '';
    position: absolute;
    top: -55%;
    left: -25%;
    width: 28%;
    height: 210%;
    background: linear-gradient(180deg, rgba(255, 255, 255, 0), rgba(255, 255, 255, 0.55), rgba(255, 255, 255, 0));
    transform: translateX(0) rotate(18deg);
    opacity: 0;
    transition: transform .52s ease, opacity .2s ease;
}

.checkin-image-picker:hover {
    transform: translateY(-1px);
    border-color: #60a5fa;
    background: #f0f7ff;
    box-shadow: 0 16px 28px rgba(59, 130, 246, 0.08);
}

.checkin-image-picker:hover::before {
    opacity: 1;
    transform: translateX(420%) rotate(18deg);
}

.checkin-image-picker.is-disabled {
    border-style: solid;
    border-color: #dbe2ea;
    background: #f8fafc;
    color: #64748b;
    cursor: not-allowed;
}

.checkin-image-picker input[type="file"] {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    cursor: pointer;
}

.checkin-image-empty {
    display: flex;
    min-height: 6rem;
    align-items: center;
    justify-content: center;
    border: 1px dashed #dbe2ea;
    border-radius: 1.25rem;
    background: #f8fafc;
    padding: 1rem;
    color: #64748b;
    font-size: .84rem;
    line-height: 1.7;
    text-align: center;
}

.checkin-image-card {
    position: relative;
    overflow: hidden;
    border: 1px solid #dbe2ea;
    border-radius: 1.25rem;
    background: #fff;
    box-shadow: 0 14px 26px rgba(15, 23, 42, 0.06);
    animation: checkinPop .28s cubic-bezier(.22, 1, .36, 1) both;
    transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
}

.checkin-image-card:hover {
    transform: translateY(-2px);
    border-color: #bfdbfe;
    box-shadow: 0 18px 32px rgba(59, 130, 246, 0.12);
}

.checkin-image-card img {
    display: block;
    width: 100%;
    height: 6.8rem;
    object-fit: cover;
    background: #e2e8f0;
    cursor: zoom-in;
}

.checkin-image-card-body {
    padding: .78rem .9rem .9rem;
}

.checkin-image-card-title {
    color: #0f172a;
    font-size: .78rem;
    font-weight: 600;
    line-height: 1.25;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.checkin-image-card-meta {
    margin-top: .28rem;
    color: #64748b;
    font-size: .72rem;
    font-weight: 500;
}

.checkin-image-remove {
    position: absolute;
    top: .55rem;
    right: .55rem;
    display: inline-flex;
    width: 1.85rem;
    height: 1.85rem;
    align-items: center;
    justify-content: center;
    border: 0;
    border-radius: 9999px;
    background: rgba(15, 23, 42, 0.76);
    color: #fff;
    font-size: 1rem;
    font-weight: 700;
    cursor: pointer;
    transition: all .16s ease;
}

.checkin-image-remove:hover {
    background: rgba(225, 29, 72, 0.9);
}

.checkin-submit-button {
    position: relative;
    gap: .55rem;
    overflow: hidden;
    -webkit-tap-highlight-color: transparent;
    touch-action: manipulation;
    box-shadow: 0 16px 26px rgba(37, 99, 235, 0.22);
    transition: transform .18s ease, box-shadow .18s ease, background-color .18s ease;
}

.checkin-submit-button::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(120deg, rgba(255, 255, 255, 0) 0%, rgba(255, 255, 255, 0.18) 35%, rgba(255, 255, 255, 0) 70%);
    transform: translateX(-140%);
    transition: transform .55s ease;
}

.checkin-submit-button:hover {
    transform: translateY(-1px);
    box-shadow: 0 20px 30px rgba(37, 99, 235, 0.26);
}

.checkin-submit-button:hover::after {
    transform: translateX(140%);
}

.checkin-submit-button.is-loading {
    pointer-events: none;
    cursor: wait;
    background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%);
    box-shadow: 0 18px 30px rgba(37, 99, 235, 0.2);
}

.checkin-submit-button.is-loading::before {
    content: '';
    width: 1rem;
    height: 1rem;
    border: 2px solid rgba(255, 255, 255, 0.32);
    border-top-color: #fff;
    border-radius: 9999px;
    animation: checkinSpin .78s linear infinite;
}

.checkin-image-lightbox {
    position: fixed;
    inset: 0;
    z-index: 90;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1.25rem;
}

.checkin-image-lightbox.hidden,
.checkin-image-lightbox[hidden] {
    display: none !important;
}

.checkin-image-lightbox-backdrop {
    position: absolute;
    inset: 0;
    background: rgba(15, 23, 42, 0.78);
    backdrop-filter: blur(3px);
}

.checkin-image-lightbox-dialog {
    position: relative;
    z-index: 1;
    width: min(100%, 58rem);
    max-height: calc(100dvh - 2.5rem);
    display: flex;
    align-items: center;
    justify-content: center;
}

.checkin-image-lightbox-dialog img {
    display: block;
    max-width: 100%;
    max-height: calc(100dvh - 2.5rem);
    border-radius: 1.4rem;
    background: #fff;
    box-shadow: 0 24px 50px rgba(15, 23, 42, 0.35);
}

.checkin-image-lightbox-close {
    position: absolute;
    top: .9rem;
    right: .9rem;
    z-index: 2;
    display: inline-flex;
    width: 2.35rem;
    height: 2.35rem;
    align-items: center;
    justify-content: center;
    border: 0;
    border-radius: 9999px;
    background: rgba(15, 23, 42, 0.78);
    color: #fff;
    font-size: 1.2rem;
    cursor: pointer;
    transition: background-color .16s ease, transform .16s ease;
}

.checkin-image-lightbox-close:hover {
    background: rgba(225, 29, 72, 0.92);
    transform: scale(1.04);
}

.checkin-processing-overlay {
    position: fixed;
    inset: 0;
    z-index: 95;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1.25rem;
}

.checkin-processing-overlay.hidden,
.checkin-processing-overlay[hidden] {
    display: none !important;
}

.checkin-processing-overlay__backdrop {
    position: absolute;
    inset: 0;
    background: rgba(15, 23, 42, 0.42);
    backdrop-filter: blur(6px);
}

.checkin-processing-overlay__panel {
    position: relative;
    z-index: 1;
    width: min(100%, 23rem);
    border: 1px solid rgba(219, 234, 254, 0.95);
    border-radius: 1.6rem;
    background: rgba(255, 255, 255, 0.96);
    box-shadow: 0 28px 60px rgba(15, 23, 42, 0.18);
    padding: 1.35rem 1.25rem 1.2rem;
    text-align: center;
}

.checkin-processing-overlay__spinner {
    width: 2.4rem;
    height: 2.4rem;
    margin: 0 auto 1rem;
    border-radius: 9999px;
    border: 3px solid rgba(59, 130, 246, 0.18);
    border-top-color: #2563eb;
    animation: checkinSpin .78s linear infinite;
}

.checkin-processing-overlay__title {
    color: #0f172a;
    font-size: 1rem;
    font-weight: 700;
    line-height: 1.35;
}

.checkin-processing-overlay__desc {
    margin-top: .48rem;
    color: #475569;
    font-size: .86rem;
    line-height: 1.65;
}

html.checkin-modal-open,
body.checkin-modal-open {
    overflow: hidden;
    overscroll-behavior: none;
}

.checkin-result-modal {
    position: fixed;
    inset: 0;
    z-index: 80;
    display: flex;
    align-items: center;
    justify-content: center;
    padding-top: calc(16px + env(safe-area-inset-top));
    padding-right: calc(16px + env(safe-area-inset-right));
    padding-bottom: calc(16px + env(safe-area-inset-bottom));
    padding-left: calc(16px + env(safe-area-inset-left));
    background: rgba(2, 6, 23, 0.45);
    -webkit-tap-highlight-color: transparent;
}

.checkin-result-modal.hidden,
.checkin-result-modal[hidden] {
    display: none !important;
    pointer-events: none;
}

.checkin-result-panel {
    position: relative;
    width: min(100%, 28rem);
    max-height: calc(100dvh - 52px - env(safe-area-inset-top) - env(safe-area-inset-bottom));
    display: flex;
    flex-direction: column;
    animation: checkinPop .24s ease both;
    pointer-events: auto;
}

.checkin-modal-header,
.checkin-modal-footer {
    position: relative;
    z-index: 1;
}

.checkin-modal-header {
    position: relative;
    overflow: hidden;
    background: linear-gradient(180deg, rgba(248, 250, 252, 0.92) 0%, #ffffff 100%);
}

.checkin-modal-header::after {
    content: '';
    position: absolute;
    left: 1.25rem;
    right: 1.25rem;
    bottom: 0;
    height: 1px;
    background: linear-gradient(90deg, rgba(37, 99, 235, 0.16), rgba(148, 163, 184, 0.08), rgba(15, 23, 42, 0));
    transform-origin: left center;
    animation: checkinModalLineIn .42s ease .08s both;
}

.checkin-modal-eyebrow {
    font-size: .76rem;
    font-weight: 700;
    letter-spacing: .02em;
    color: #94a3b8;
    animation: checkinModalEyebrowIn .34s cubic-bezier(.22, 1, .36, 1) both;
}

.checkin-modal-title {
    position: relative;
    display: inline-block;
    margin-top: .35rem;
    font-size: 1.72rem;
    line-height: 1.15;
    font-weight: 800;
    letter-spacing: -.02em;
    animation: checkinModalTitleIn .42s cubic-bezier(.22, 1, .36, 1) .05s both;
}

.checkin-modal-title::after {
    content: '';
    position: absolute;
    left: 0;
    bottom: -.32rem;
    width: 2.65rem;
    height: .22rem;
    border-radius: 9999px;
    transform-origin: left center;
    animation: checkinModalUnderlineIn .45s cubic-bezier(.22, 1, .36, 1) .12s both;
}

.checkin-modal-title--success {
    color: #047857;
    text-shadow: 0 10px 24px rgba(4, 120, 87, 0.08);
}

.checkin-modal-title--success::after {
    background: linear-gradient(90deg, rgba(16, 185, 129, 0.95), rgba(110, 231, 183, 0.55));
}

.checkin-modal-title--error {
    color: #be123c;
    text-shadow: 0 10px 24px rgba(190, 18, 60, 0.08);
}

.checkin-modal-title--error::after {
    background: linear-gradient(90deg, rgba(244, 63, 94, 0.95), rgba(253, 164, 175, 0.55));
}

.checkin-modal-body {
    flex: 1 1 auto;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
}

.checkin-modal-footer {
    background: #fff;
}

.checkin-modal-close,
.checkin-modal-confirm {
    cursor: pointer;
    touch-action: manipulation;
    -webkit-tap-highlight-color: transparent;
}

.checkin-modal-close {
    width: 2.55rem;
    height: 2.55rem;
    border-color: #dbe2ea;
    color: #64748b;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    transition: transform .18s ease, box-shadow .18s ease, background-color .18s ease, border-color .18s ease;
}

.checkin-modal-close:hover {
    transform: translateY(-1px) rotate(6deg);
    border-color: #cbd5e1;
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
}

.checkin-modal-close:active {
    transform: scale(.96);
}

.checkin-modal-confirm {
    position: relative;
    overflow: hidden;
    min-width: 7.25rem;
    min-height: 2.8rem;
    padding: 0 1.3rem;
    font-size: .95rem;
    background-image: linear-gradient(135deg, #2563eb 0%, #3b82f6 55%, #60a5fa 100%);
    box-shadow: 0 10px 22px rgba(37, 99, 235, 0.18);
    transition: transform .18s ease, box-shadow .2s ease, filter .2s ease;
    animation: checkinModalButtonIn .42s cubic-bezier(.22, 1, .36, 1) .1s both, checkinModalButtonPulse 5.6s ease-in-out 1s infinite;
}

.checkin-modal-confirm::before {
    content: '';
    position: absolute;
    inset: 1px;
    border-radius: inherit;
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.22), rgba(255, 255, 255, 0));
    pointer-events: none;
}

.checkin-modal-confirm::after {
    content: '';
    position: absolute;
    top: -120%;
    left: -35%;
    width: 32%;
    height: 300%;
    background: linear-gradient(180deg, rgba(255, 255, 255, 0), rgba(255, 255, 255, 0.5), rgba(255, 255, 255, 0));
    transform: translateX(-220%) rotate(20deg);
    opacity: 0;
    transition: transform .55s ease, opacity .22s ease;
    pointer-events: none;
}

.checkin-modal-confirm:hover {
    transform: translateY(-1px);
    filter: saturate(1.04);
    box-shadow: 0 14px 26px rgba(37, 99, 235, 0.24);
}

.checkin-modal-confirm:hover::after {
    opacity: 1;
    transform: translateX(500%) rotate(20deg);
}

.checkin-modal-confirm:active {
    transform: translateY(0) scale(.98);
    box-shadow: 0 8px 18px rgba(37, 99, 235, 0.2);
}

.checkin-modal-stack {
    display: grid;
    gap: .8rem;
}

.checkin-modal-grid {
    display: grid;
    gap: .8rem;
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.checkin-modal-card {
    min-height: 5.7rem;
    border: 1px solid #e2e8f0;
    border-radius: 1.25rem;
    background: #f8fafc;
    padding: .95rem 1rem;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.6);
}

.checkin-modal-card--accent {
    border-color: #bfdbfe;
    background: #eff6ff;
}

.checkin-modal-card--danger {
    border-color: #fecdd3;
    background: #fff1f2;
}

.checkin-modal-card-label {
    font-size: .74rem;
    font-weight: 700;
    letter-spacing: .01em;
    color: #94a3b8;
}

.checkin-modal-card-label--accent {
    color: #2563eb;
}

.checkin-modal-card-label--danger {
    color: #e11d48;
}

.checkin-modal-card-value {
    margin-top: .42rem;
    font-size: 1rem;
    line-height: 1.42;
    font-weight: 700;
    color: #0f172a;
    word-break: break-word;
}

.checkin-modal-card-value--accent {
    font-size: 1.36rem;
    line-height: 1.1;
    color: #1d4ed8;
}

.checkin-modal-card-value--danger {
    font-size: 1rem;
    line-height: 1.6;
    color: #9f1239;
}

.checkin-modal-note {
    margin-top: .05rem;
}

@keyframes checkinRise {
    from {
        opacity: 0;
        transform: translateY(16px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes checkinPop {
    from {
        opacity: 0;
        transform: translateY(10px) scale(.98);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

@keyframes checkinModalEyebrowIn {
    from {
        opacity: 0;
        transform: translateY(6px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes checkinModalTitleIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes checkinModalUnderlineIn {
    from {
        opacity: 0;
        transform: scaleX(.2);
    }
    to {
        opacity: 1;
        transform: scaleX(1);
    }
}

@keyframes checkinModalLineIn {
    from {
        opacity: 0;
        transform: scaleX(.4);
    }
    to {
        opacity: 1;
        transform: scaleX(1);
    }
}

@keyframes checkinModalButtonIn {
    from {
        opacity: 0;
        transform: translateY(8px) scale(.98);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

@keyframes checkinModalButtonPulse {
    0%, 100% {
        box-shadow: 0 10px 22px rgba(37, 99, 235, 0.18);
    }
    50% {
        box-shadow: 0 14px 30px rgba(37, 99, 235, 0.24);
    }
}

@keyframes checkinFloat {
    0%, 100% {
        transform: translate3d(0, 0, 0);
    }
    50% {
        transform: translate3d(0, -10px, 0);
    }
}

@media (max-width: 1024px) {
    .checkin-member-grid {
        grid-template-columns: repeat(5, minmax(0, 1fr));
    }
}

@media (max-width: 768px) {
    .checkin-member-grid {
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: .55rem;
    }

    .checkin-member-pill {
        min-height: 2.7rem;
        font-size: clamp(.63rem, 2.15vw, .74rem);
        padding: .42rem .28rem;
    }

    .checkin-image-card img {
        height: 6rem;
    }
}

@media (max-width: 640px) {
    .checkin-page-shell {
        gap: 14px;
    }

    .checkin-main-grid {
        gap: 1rem;
    }

    .checkin-motion-card {
        border-radius: 24px;
    }

    .checkin-hero {
        padding: 15px 16px 12px;
    }

    .checkin-hero-top {
        grid-template-columns: 1fr;
        gap: .55rem;
    }

    .checkin-hero-title-row {
        display: block;
    }

    .checkin-hero h1 {
        font-size: 1.7rem;
        line-height: 1.2;
    }

    .checkin-hero-copy > :not([hidden]) ~ :not([hidden]) {
        margin-top: .25rem;
    }

    .checkin-hero-copy p {
        line-height: 1.5;
    }

    .checkin-back-link--desktop {
        display: none;
    }

    .checkin-hero-right {
        display: none;
    }

    .checkin-hero-actions {
        width: 100%;
        display: flex;
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
        gap: .55rem;
        margin-top: 0;
    }

    .checkin-hero-status {
        padding: .38rem .6rem;
    }

    .checkin-hero-status--desktop {
        display: none;
    }

    .checkin-hero-status--mobile {
        display: inline-flex;
    }

    .checkin-hero-meta {
        margin-top: .55rem;
        gap: .45rem;
    }

    .checkin-hero-meta > div {
        padding: .52rem .72rem;
    }

    .checkin-hero-meta .text-sm {
        font-size: .8rem;
        line-height: 1.25;
    }

    .checkin-submit-stack {
        gap: .45rem;
    }

    .checkin-submit-layout {
        display: block;
    }

    .checkin-remark-field textarea {
        min-height: 8rem;
    }
    .checkin-image-picker {
        min-height: 3.65rem;
        padding: .85rem .9rem;
    }

    .checkin-image-empty {
        min-height: 5rem;
        padding: .85rem;
        font-size: .78rem;
        line-height: 1.6;
    }

    .checkin-image-card img {
        height: 5.4rem;
    }

    .checkin-image-lightbox {
        padding: .85rem;
    }

    .checkin-image-lightbox-dialog img {
        max-height: calc(100dvh - 1.7rem);
        border-radius: 1rem;
    }

    .checkin-image-lightbox-close {
        top: .55rem;
        right: .55rem;
        width: 2.05rem;
        height: 2.05rem;
    }

    .checkin-processing-overlay {
        padding: .95rem;
    }

    .checkin-processing-overlay__panel {
        width: min(100%, 19.5rem);
        border-radius: 1.35rem;
        padding: 1.15rem 1rem 1rem;
    }

    .checkin-processing-overlay__spinner {
        width: 2.15rem;
        height: 2.15rem;
        margin-bottom: .8rem;
    }

    .checkin-processing-overlay__title {
        font-size: .96rem;
    }

    .checkin-processing-overlay__desc {
        font-size: .8rem;
        line-height: 1.6;
    }

    .checkin-image-progress-track {
        height: .5rem;
    }

    .checkin-result-modal {
        align-items: center;
        padding: 14px 14px calc(14px + env(safe-area-inset-bottom));
    }

    .checkin-result-panel {
        width: min(100%, 25.75rem);
        max-height: calc(100dvh - 58px - env(safe-area-inset-top) - env(safe-area-inset-bottom));
        border-radius: 24px;
        display: flex;
        flex-direction: column;
    }

    .checkin-modal-body {
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
    }

    .checkin-modal-footer {
        padding-bottom: calc(14px + env(safe-area-inset-bottom));
        background: #fff;
    }

    .checkin-modal-title {
        font-size: 1.54rem;
    }

    .checkin-modal-close {
        width: 2.35rem;
        height: 2.35rem;
    }

    .checkin-modal-confirm {
        min-width: 6.9rem;
        min-height: 2.62rem;
        font-size: .92rem;
        padding: 0 1.1rem;
    }

    .checkin-modal-card {
        min-height: 5.1rem;
        padding: .85rem .9rem;
    }

    .checkin-modal-card-label {
        font-size: .72rem;
    }

    .checkin-modal-card-value {
        margin-top: .4rem;
        font-size: .94rem;
    }

    .checkin-modal-card-value--accent {
        font-size: 1.2rem;
    }

    .checkin-modal-card-value--danger {
        font-size: .95rem;
    }

    .checkin-modal-grid,
    .checkin-modal-stack {
        gap: .7rem;
    }
}

@media (min-width: 641px) {
    .checkin-hero-actions,
    .checkin-back-link--mobile {
        display: none;
    }

    .checkin-back-link--desktop {
        display: inline-flex;
    }

    .checkin-hero-status--desktop {
        display: inline-flex;
    }

    .checkin-hero-right {
        display: flex;
    }

    .checkin-hero-status--mobile {
        display: none;
    }

    .checkin-submit-layout {
        justify-content: flex-end;
    }
}

@media (prefers-reduced-motion: reduce) {
    .checkin-result-panel,
    .checkin-modal-eyebrow,
    .checkin-modal-title,
    .checkin-modal-title::after,
    .checkin-modal-header::after,
    .checkin-modal-confirm {
        animation: none !important;
    }

    .checkin-modal-close,
    .checkin-modal-confirm,
    .checkin-modal-confirm::after,
    .checkin-image-lightbox-close,
    .checkin-submit-button::before {
        transition: none !important;
    }
}

@media (max-width: 360px) {
    .checkin-modal-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 430px) {
    .checkin-page-shell::before {
        right: -.75rem;
        width: 12rem;
        height: 12rem;
    }

    .checkin-member-grid {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }
}
</style>

<script>
(function () {
    var form = document.getElementById('checkin-form');
    var memberInput = document.getElementById('checkin-member-id');
    var memberError = document.getElementById('checkin-member-error');
    var memberPills = Array.prototype.slice.call(document.querySelectorAll('.checkin-member-pill'));
    var imageField = document.getElementById('checkin-image-field');
    var imagePickerHost = document.getElementById('checkin-image-picker-host');
    var imagePreview = document.getElementById('checkin-image-preview');
    var imageInputs = document.getElementById('checkin-image-inputs');
    var imageCount = document.getElementById('checkin-image-count');
    var imageError = document.getElementById('checkin-image-error');
    var imageProgressText = document.getElementById('checkin-image-progress-text');
    var imageProgressMeta = document.getElementById('checkin-image-progress-meta');
    var imageProgressFill = document.getElementById('checkin-image-progress-fill');
    var submitButton = form ? form.querySelector('.checkin-submit-button') : null;
    var submitStatus = document.getElementById('checkin-submit-status');
    var processingOverlay = document.getElementById('checkin-processing-overlay');
    var lightbox = document.getElementById('checkin-image-lightbox');
    var lightboxImage = document.getElementById('checkin-image-lightbox-image');
    var imageMin = imageField ? parseInt(imageField.getAttribute('data-min-images') || '1', 10) : 1;
    var imageMax = imageField ? parseInt(imageField.getAttribute('data-max-images') || '3', 10) : 3;
    var selectedImages = [];
    var imageUid = 0;
    var submitLocked = false;

    if (!isFinite(imageMin) || imageMin < 0) {
        imageMin = 0;
    }
    if (!isFinite(imageMax) || imageMax <= 0) {
        imageMax = 9;
    }

    var canSplitFiles = function () {
        try {
            var dt = new DataTransfer();
            return !!dt.items;
        } catch (error) {
            return false;
        }
    };

    var createSingleFileInput = function (file) {
        if (!canSplitFiles()) {
            return null;
        }
        try {
            var syntheticInput = document.createElement('input');
            syntheticInput.type = 'file';
            syntheticInput.name = 'images[]';
            syntheticInput.accept = 'image/*';
            syntheticInput.className = 'hidden';
            var dt = new DataTransfer();
            dt.items.add(file);
            syntheticInput.files = dt.files;
            return syntheticInput;
        } catch (error) {
            return null;
        }
    };

    var setMember = function (memberId) {
        if (!memberInput) {
            return;
        }
        memberInput.value = memberId;
        memberPills.forEach(function (pill) {
            var active = (pill.getAttribute('data-member-id') || '') === memberId;
            pill.classList.toggle('is-active', active);
            pill.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
        if (memberError) {
            memberError.classList.add('hidden');
        }
    };

    memberPills.forEach(function (pill) {
        pill.addEventListener('click', function () {
            setMember(pill.getAttribute('data-member-id') || '');
        });
    });

    // Auto-select bound member if logged in
    <?php if ($boundMemberId > 0): ?>
    (function(){
        var autoId = '<?= $boundMemberId ?>';
        var found = memberPills.some(function(p){ return p.getAttribute('data-member-id') === autoId; });
        if (found) setMember(autoId);
    })();
    <?php endif; ?>

    var hideImageError = function () {
        if (!imageError) {
            return;
        }
        imageError.textContent = '';
        imageError.classList.add('hidden');
    };

    var showImageError = function (message) {
        if (!imageError) {
            return;
        }
        imageError.textContent = message;
        imageError.classList.remove('hidden');
    };

    var syncImageCount = function () {
        var selectedCount = selectedImages.length;
        var requiredBase = imageMin > 0 ? imageMin : imageMax;
        var remainingRequired = Math.max(imageMin - selectedCount, 0);
        var remainingSlots = Math.max(imageMax - selectedCount, 0);
        var progressPercent = requiredBase > 0 ? Math.min(selectedCount / requiredBase, 1) * 100 : 100;

        if (imageCount) {
            imageCount.textContent = '已选 ' + selectedCount + ' / ' + imageMax + ' 张';
        }

        if (imageField) {
            imageField.classList.toggle('is-ready', selectedCount >= imageMin);
            imageField.classList.toggle('is-maxed', selectedCount >= imageMax);
        }

        if (imageProgressFill) {
            imageProgressFill.style.width = progressPercent + '%';
        }

        if (imageProgressText) {
            if (selectedCount <= 0) {
                imageProgressText.textContent = '当前还未选择图片';
            } else if (selectedCount < imageMin) {
                imageProgressText.textContent = '已选第 ' + selectedCount + ' 张，继续添加即可';
            } else if (selectedCount < imageMax) {
                imageProgressText.textContent = '已满足提交要求，还可以继续补充';
            } else {
                imageProgressText.textContent = '已选满 ' + imageMax + ' 张，可以直接提交';
            }
        }

        if (imageProgressMeta) {
            if (selectedCount <= 0) {
                imageProgressMeta.textContent = '还差 ' + imageMin + ' 张';
            } else if (remainingRequired > 0) {
                imageProgressMeta.textContent = '还差 ' + remainingRequired + ' 张';
            } else if (remainingSlots > 0) {
                imageProgressMeta.textContent = '还可再选 ' + remainingSlots + ' 张';
            } else {
                imageProgressMeta.textContent = '已满额';
            }
        }
    };

    var getImageCountMessage = function () {
        if (imageMin > 0 && imageMin === imageMax && selectedImages.length !== imageMin) {
            return '请上传 ' + imageMin + ' 张图片后再提交。';
        }
        if (selectedImages.length < imageMin) {
            return '至少上传 ' + imageMin + ' 张图片后再提交。';
        }
        if (selectedImages.length > imageMax) {
            return '最多只能上传 ' + imageMax + ' 张图片。';
        }
        return '';
    };

    var removeImageItem = function (uid) {
        selectedImages = selectedImages.filter(function (item) {
            if (item.uid !== uid) {
                return true;
            }
            if (item.url && window.URL && typeof window.URL.revokeObjectURL === 'function') {
                try {
                    window.URL.revokeObjectURL(item.url);
                } catch (error) {}
            }
            if (item.input && item.input.parentNode) {
                item.input.parentNode.removeChild(item.input);
            }
            return false;
        });
        hideImageError();
        renderImagePreview();
        renderImagePicker();
    };

    var renderImagePicker = function () {
        if (!imagePickerHost) {
            return;
        }

        imagePickerHost.innerHTML = '';

        var picker = document.createElement('div');
        picker.className = 'checkin-image-picker';

        if (selectedImages.length >= imageMax) {
            picker.classList.add('is-disabled');
            picker.innerHTML = '<span class="text-sm font-semibold">已达图片上限</span><span class="text-xs text-slate-500">可先删除已选图片，再继续添加</span>';
            imagePickerHost.appendChild(picker);
            return;
        }

        var title = document.createElement('span');
        title.className = 'text-sm font-semibold text-blue-700';
        title.textContent = '选择图片';
        picker.appendChild(title);


        var input = document.createElement('input');
        input.type = 'file';
        input.name = 'images[]';
        input.accept = 'image/*';
        input.multiple = true;
        input.addEventListener('change', function () {
            handleImageSelected(input);
        });
        picker.appendChild(input);

        imagePickerHost.appendChild(picker);
    };

    var renderImagePreview = function () {
        if (!imagePreview) {
            return;
        }

        imagePreview.innerHTML = '';

        if (selectedImages.length === 0) {
            var empty = document.createElement('div');
            empty.className = 'checkin-image-empty col-span-full';
            empty.textContent = '尚未添加图片，可以一次选择多张，也可以分次选择。提交时会统一检查总数量。';
            imagePreview.appendChild(empty);
            syncImageCount();
            return;
        }

        selectedImages.forEach(function (item, index) {
            var card = document.createElement('div');
            card.className = 'checkin-image-card';

            var previewImage = document.createElement('img');
            previewImage.src = item.url;
            previewImage.alt = item.name || ('image-' + (index + 1));
            previewImage.addEventListener('click', function () {
                openLightbox(item.url, previewImage.alt);
            });
            card.appendChild(previewImage);

            var removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'checkin-image-remove';
            removeBtn.setAttribute('aria-label', '删除图片');
            removeBtn.innerHTML = '&times;';
            removeBtn.addEventListener('click', function () {
                removeImageItem(item.uid);
            });
            card.appendChild(removeBtn);

            imagePreview.appendChild(card);
        });

        syncImageCount();
    };

    var appendSingleFile = function (file, inputNode) {
        if (!file) {
            return;
        }
        if (selectedImages.length >= imageMax) {
            return;
        }

        if (file.type && file.type.indexOf('image/') !== 0) {
            return;
        }

        var objectUrl = '';
        if (window.URL && typeof window.URL.createObjectURL === 'function') {
            try {
                objectUrl = window.URL.createObjectURL(file);
            } catch (error) {
                objectUrl = '';
            }
        }

        if (imageInputs && inputNode && inputNode.parentNode !== imageInputs) {
            imageInputs.appendChild(inputNode);
        }

        selectedImages.push({
            uid: ++imageUid,
            input: inputNode,
            file: file,
            url: objectUrl,
            name: file.name || '',
            size: file.size || 0
        });
    };

    var appendSelectedImagesToFormData = function (formData) {
        if (!formData || typeof formData.delete !== 'function' || typeof formData.append !== 'function') {
            return;
        }

        formData.delete('images[]');
        selectedImages.forEach(function (item) {
            if (!item || !item.file) {
                return;
            }
            formData.append('images[]', item.file, item.name || item.file.name || 'image');
        });
    };

    var handleImageSelected = function (input) {
        if (!input || !input.files || !input.files.length) {
            return;
        }

        var originalFileCount = input.files.length;
        var files = Array.prototype.slice.call(input.files || []);
        if (files.length === 0) {
            return;
        }

        var availableSlots = imageMax - selectedImages.length;
        if (availableSlots <= 0) {
            showImageError('最多只能上传 ' + imageMax + ' 张图片，如需替换，请先删除已选图片。');
            input.value = '';
            renderImagePicker();
            return;
        }

        if (files.length > availableSlots) {
            files = files.slice(0, availableSlots);
            showImageError('本次最多再添加 ' + availableSlots + ' 张图片，其余图片已忽略。');
        } else {
            hideImageError();
        }

        if (files.length === 1 && originalFileCount === 1) {
            input.classList.add('hidden');
            appendSingleFile(files[0], input);
            renderImagePreview();
            renderImagePicker();
            return;
        }

        if (files.length === 1) {
            var replacementInput = createSingleFileInput(files[0]);
            if (!replacementInput) {
                showImageError('Browser does not support this multi-select mode. Please select one image each time.');
                input.value = '';
                renderImagePicker();
                return;
            }
            appendSingleFile(files[0], replacementInput);
            renderImagePreview();
            renderImagePicker();
            return;
        }

        if (!canSplitFiles()) {
            showImageError('当前浏览器不支持单独拆分本次多选图片，请改为每次选择 1 张。');
            input.value = '';
            renderImagePicker();
            return;
        }

        files.forEach(function (file) {
            var singleInput = createSingleFileInput(file);
            if (!singleInput) {
                return;
            }
            appendSingleFile(file, singleInput);
        });

        renderImagePreview();
        renderImagePicker();
    };

    if (imageField) {
        renderImagePreview();
        renderImagePicker();
    }

    // Whether we can reliably take over submission via fetch + manually built
    // FormData. This bypasses two mechanisms that are unreliable on iOS Safari
    // and in-app WebViews (WeChat etc.): the `formdata` event and assigning
    // files to a synthetic <input> via DataTransfer. On those browsers the
    // images selected in the UI would silently NOT be serialized into the POST
    // body, so the server received fewer files than the user picked and
    // rejected the submission with "上传图片数量不符合规则". Building FormData
    // directly from the in-memory File objects we already hold avoids that.
    var canFetchSubmit = function () {
        return typeof window.fetch === 'function'
            && typeof FormData === 'function'
            && typeof FormData.prototype.append === 'function';
    };

    // Build the multipart payload explicitly from the live File references in
    // `selectedImages`, plus the form's own text fields. We do NOT rely on the
    // synthetic file inputs in the DOM (which may not serialize on iOS).
    var buildCheckinFormData = function () {
        var fd = new FormData();
        fd.append('csrf_token', csrfFieldValue());
        fd.append('member_id', memberInput ? memberInput.value : '');

        var taskDateField = form.querySelector('[name="task_date"]');
        fd.append('task_date', taskDateField ? taskDateField.value : '');

        var remarkField = form.querySelector('[name="remark"]');
        if (remarkField) {
            fd.append('remark', remarkField.value || '');
        }

        var overrideField = form.querySelector('[name="force_override"]');
        if (overrideField && overrideField.checked) {
            fd.append('force_override', overrideField.value || '1');
        }

        selectedImages.forEach(function (item) {
            if (item && item.file) {
                fd.append('images[]', item.file, item.name || item.file.name || 'image');
            }
        });

        return fd;
    };

    var csrfFieldValue = function () {
        var field = form.querySelector('[name="csrf_token"]');
        return field ? field.value : '';
    };

    var replaceDocumentWith = function (html) {
        try {
            document.open();
            document.write(html);
            document.close();
        } catch (error) {
            // If rewriting the document fails for any reason, fall back to a
            // plain reload so the user at least sees a consistent state.
            window.location.reload();
        }
    };

    // ---- Layer 1: client-side image compression -------------------------
    // Targets: keep each image comfortably under the server per-image limit,
    // convert lossless PNG screenshots to JPEG, downscale very large photos,
    // and avoid touching already-small files. Pure canvas re-encode, works in
    // iOS Safari and WeChat WebView. Always resolves (never rejects) so a
    // compression hiccup can never block a submission — it just falls back to
    // the original file.
    var COMPRESS_MAX_EDGE = 2560;      // px, longest side cap
    var COMPRESS_QUALITY = 0.82;       // JPEG quality
    var COMPRESS_TRIGGER_BYTES = 1500 * 1024; // re-encode anything above ~1.5MB
    var COMPRESS_TARGET_BYTES = 4 * 1024 * 1024; // aim to land under ~4MB

    var canCompressImages = function () {
        return typeof document.createElement('canvas').toBlob === 'function'
            && (typeof window.createImageBitmap === 'function' || typeof window.FileReader === 'function')
            && typeof window.URL !== 'undefined';
    };

    var loadBitmap = function (file) {
        // Prefer createImageBitmap (handles EXIF orientation on modern iOS).
        if (typeof window.createImageBitmap === 'function') {
            try {
                return window.createImageBitmap(file, { imageOrientation: 'from-image' })
                    .catch(function () { return loadViaImageElement(file); });
            } catch (error) {
                return loadViaImageElement(file);
            }
        }
        return loadViaImageElement(file);
    };

    var loadViaImageElement = function (file) {
        return new Promise(function (resolve, reject) {
            var url = '';
            try { url = window.URL.createObjectURL(file); } catch (e) { reject(e); return; }
            var img = new Image();
            img.onload = function () {
                resolve(img);
                try { window.URL.revokeObjectURL(url); } catch (e) {}
            };
            img.onerror = function () {
                try { window.URL.revokeObjectURL(url); } catch (e) {}
                reject(new Error('image decode failed'));
            };
            img.src = url;
        });
    };

    var canvasToBlob = function (canvas, quality) {
        return new Promise(function (resolve) {
            try {
                canvas.toBlob(function (blob) { resolve(blob); }, 'image/jpeg', quality);
            } catch (error) {
                resolve(null);
            }
        });
    };

    // Compress a single File; resolves with a File (compressed or original).
    var compressOneFile = function (file) {
        if (!file || !file.type || file.type.indexOf('image/') !== 0) {
            return Promise.resolve(file);
        }
        // Skip GIF (could be animated) — re-encoding would break it.
        if (file.type === 'image/gif') {
            return Promise.resolve(file);
        }
        var isPng = file.type === 'image/png';
        // Small JPEGs are already fine; leave them untouched. PNGs always get
        // converted (screenshots are the main offender even when "smallish").
        if (!isPng && file.size <= COMPRESS_TRIGGER_BYTES) {
            return Promise.resolve(file);
        }

        return loadBitmap(file).then(function (bitmap) {
            var w = bitmap.width || bitmap.naturalWidth;
            var h = bitmap.height || bitmap.naturalHeight;
            if (!w || !h) {
                return file;
            }
            var scale = Math.min(1, COMPRESS_MAX_EDGE / Math.max(w, h));
            var tw = Math.max(1, Math.round(w * scale));
            var th = Math.max(1, Math.round(h * scale));

            var canvas = document.createElement('canvas');
            canvas.width = tw;
            canvas.height = th;
            var ctx = canvas.getContext('2d');
            if (!ctx) {
                return file;
            }
            // White background so transparent PNG screenshots don't turn black
            // when flattened to JPEG.
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, tw, th);
            ctx.drawImage(bitmap, 0, 0, tw, th);
            if (typeof bitmap.close === 'function') {
                try { bitmap.close(); } catch (e) {}
            }

            // Encode, stepping quality down if still too large.
            var attempt = function (quality) {
                return canvasToBlob(canvas, quality).then(function (blob) {
                    if (!blob) {
                        return file; // toBlob failed → keep original
                    }
                    if (blob.size > COMPRESS_TARGET_BYTES && quality > 0.5) {
                        return attempt(quality - 0.12);
                    }
                    // Only use the compressed result if it actually helps (or if
                    // the source was PNG, where JPEG is essentially always
                    // smaller and acceptable for check-in evidence).
                    if (blob.size < file.size || isPng) {
                        var newName = (file.name || 'image').replace(/\.(png|webp|heic|heif|jpeg|jpg)$/i, '') + '.jpg';
                        try {
                            return new File([blob], newName, { type: 'image/jpeg', lastModified: Date.now() });
                        } catch (e) {
                            // Older browsers: File constructor unavailable — tag
                            // the Blob so FormData still sends a filename.
                            blob.name = newName;
                            return blob;
                        }
                    }
                    return file;
                });
            };
            return attempt(COMPRESS_QUALITY);
        }).catch(function () {
            return file; // any failure → original file, never block submit
        });
    };

    // Compress every selected image in place. Always resolves.
    var compressAllSelected = function () {
        if (!canCompressImages() || !selectedImages.length) {
            return Promise.resolve();
        }
        var jobs = selectedImages.map(function (item) {
            if (!item || !item.file) {
                return Promise.resolve();
            }
            return compressOneFile(item.file).then(function (result) {
                if (result && result !== item.file) {
                    item.file = result;
                    item.name = result.name || item.name;
                    item.size = result.size || item.size;
                }
            }).catch(function () {});
        });
        return Promise.all(jobs).then(function () {});
    };

    var submitViaFetch = function () {
        setSubmitPending(true);

        // Layer 1: compress/normalize images in the browser BEFORE sending.
        // iPhone screenshots are PNG and can be 5-8MB+, which exceeded the
        // server's per-image limit and was (misleadingly) reported as a count
        // error. Re-encoding to JPEG and downscaling keeps every image well
        // under the limit, also saving the user's mobile data.
        compressAllSelected().then(function () {
            var formData;
            try {
                formData = buildCheckinFormData();
            } catch (error) {
                // Could not build the payload — let the native form submit instead.
                setSubmitPending(false);
                form.submit();
                return;
            }

            window.fetch(form.action, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                redirect: 'follow',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (response) {
                return response.text().then(function (html) {
                    return { html: html, ok: response.ok };
                });
            }).then(function (result) {
                // The POST issues a redirect to the task page, which fetch follows;
                // the returned HTML already contains the result modal (success or
                // error) rendered from the session flash. Render it as-is so all
                // existing modal behaviour is preserved without duplicating it here.
                if (result.html) {
                    replaceDocumentWith(result.html);
                    return;
                }
                window.location.reload();
            }).catch(function () {
                // Network failure: unlock and surface a recoverable error so the
                // user can retry without losing their selected images.
                setSubmitPending(false);
                showImageError('网络异常，提交未成功，请检查网络后重试。');
            });
        });
    };

    if (form && memberInput) {
        // Kept as a safety net for browsers that fall back to native submit
        // (see submit handler below): it still injects the real files.
        form.addEventListener('formdata', function (event) {
            appendSelectedImagesToFormData(event.formData);
        });

        form.addEventListener('submit', function (event) {
            var hasError = false;

            if (submitLocked) {
                event.preventDefault();
                return;
            }

            if (memberInput.value.trim() === '') {
                hasError = true;
                if (memberError) {
                    memberError.classList.remove('hidden');
                }
            }

            if (imageField) {
                var imageCountMessage = getImageCountMessage();
                if (imageCountMessage !== '') {
                    hasError = true;
                    showImageError(imageCountMessage);
                } else {
                    hideImageError();
                }
            }

            if (hasError) {
                event.preventDefault();
                setSubmitPending(false);
                return;
            }

            // Primary path: take over submission and post the real File objects
            // ourselves. Only fall through to native submit if fetch/FormData
            // are unavailable (very old browsers), where the formdata event +
            // DOM inputs remain the best available option.
            if (canFetchSubmit()) {
                event.preventDefault();
                submitViaFetch();
                return;
            }

            setSubmitPending(true);
        });
    }

    var setSubmitPending = function (pending) {
        submitLocked = pending;
        if (submitButton) {
            var defaultLabel = submitButton.getAttribute('data-submit-label') || '提交打卡';
            var loadingLabel = submitButton.getAttribute('data-loading-label') || '图片处理中，请稍候...';
            submitButton.classList.toggle('is-loading', pending);
            submitButton.disabled = pending;
            submitButton.textContent = pending ? loadingLabel : defaultLabel;
        }
        if (submitStatus) {
            submitStatus.classList.toggle('hidden', !pending);
        }
    };

    var openLightbox = function (src, alt) {
        if (!lightbox || !lightboxImage || !src) {
            return;
        }
        lightboxImage.src = src;
        lightboxImage.alt = alt || '图片预览';
        lightbox.hidden = false;
        lightbox.classList.remove('hidden');
        lightbox.setAttribute('aria-hidden', 'false');
        document.documentElement.classList.add('checkin-modal-open');
        document.body.classList.add('checkin-modal-open');
    };

    var closeLightbox = function () {
        if (!lightbox || !lightboxImage) {
            return;
        }
        lightbox.hidden = true;
        lightbox.classList.add('hidden');
        lightbox.setAttribute('aria-hidden', 'true');
        lightboxImage.src = '';
        document.documentElement.classList.remove('checkin-modal-open');
        document.body.classList.remove('checkin-modal-open');
    };

    if (lightbox) {
        lightbox.addEventListener('click', function (event) {
            if (event.target && event.target.getAttribute('data-lightbox-close') === '1') {
                closeLightbox();
            }
        });
    }

    var modal = document.getElementById('checkin-result-modal');
    var closeBtn = document.getElementById('checkin-modal-close');
    var confirmBtn = document.getElementById('checkin-modal-confirm');
    var modalPanel = modal ? modal.querySelector('.checkin-result-panel') : null;
    var rootEl = document.documentElement;
    var bodyEl = document.body;
    var modalCloseTimer = null;
    var modalIsClosing = false;
    var toggleModalState = function (open) {
        if (!rootEl || !bodyEl) {
            return;
        }
        rootEl.classList.toggle('checkin-modal-open', open);
        bodyEl.classList.toggle('checkin-modal-open', open);
    };
    var mountModal = function () {
        if (!modal || !bodyEl) {
            return;
        }
        if (modal.parentNode !== bodyEl) {
            bodyEl.appendChild(modal);
        }
        if (modalCloseTimer) {
            window.clearTimeout(modalCloseTimer);
            modalCloseTimer = null;
        }
        modalIsClosing = false;
        modal.style.display = '';
        modal.style.pointerEvents = 'auto';
        modal.style.opacity = '1';
        modal.hidden = false;
        modal.classList.remove('hidden');
        modal.setAttribute('aria-hidden', 'false');
        toggleModalState(true);
        window.setTimeout(function () {
            window.scrollTo(0, 0);
        }, 0);
    };
    var closeModal = function (event) {
        if (event) {
            if (typeof event.preventDefault === 'function') {
                event.preventDefault();
            }
            if (typeof event.stopPropagation === 'function') {
                event.stopPropagation();
            }
            if (typeof event.stopImmediatePropagation === 'function') {
                event.stopImmediatePropagation();
            }
        }
        if (!modal || modalIsClosing) {
            return false;
        }
        modalIsClosing = true;
        if (modalCloseTimer) {
            window.clearTimeout(modalCloseTimer);
        }
        modal.style.pointerEvents = 'none';
        modal.style.opacity = '0';
        modal.style.display = 'none';
        modal.hidden = true;
        modal.classList.add('hidden');
        modal.setAttribute('aria-hidden', 'true');
        toggleModalState(false);
        modalCloseTimer = window.setTimeout(function () {
            if (modal && modal.parentNode) {
                modal.parentNode.removeChild(modal);
            }
        }, 24);
        return false;
    };
    window.closeCheckinResultModal = closeModal;
    var bindModalClose = function (el) {
        if (!el) {
            return;
        }
        el.addEventListener('click', closeModal, true);
        el.addEventListener('pointerup', closeModal, true);
    };

    bindModalClose(closeBtn);
    bindModalClose(confirmBtn);
    if (modal) {
        mountModal();
        if (modalPanel) {
            modalPanel.addEventListener('click', function (event) {
                event.stopPropagation();
            });
            modalPanel.addEventListener('pointerup', function (event) {
                event.stopPropagation();
            }, true);
        }
        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeModal(event);
            }
        });
        modal.addEventListener('pointerup', function (event) {
            if (event.target === modal) {
                closeModal(event);
            }
        }, true);
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                if (lightbox && !lightbox.hidden) {
                    closeLightbox();
                    return;
                }
                closeModal(event);
            }
        });
    } else {
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && lightbox && !lightbox.hidden) {
                closeLightbox();
            }
        });
    }
})();
</script>
