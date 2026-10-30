<?php
$currentUser = $currentUser ?? null;
$member = is_array($member ?? null) ? $member : null;
$matchingMembers = is_array($matchingMembers ?? null) ? $matchingMembers : [];
$csrf = (string) ($csrf ?? '');
$flash = is_array($flash ?? null) ? $flash : null;
$membership = is_array($membership ?? null) ? $membership : [];
$userPoints = (int) ($userPoints ?? 0);
$userBalance = (int) ($userBalance ?? 0);
$referralCount = (int) ($referralCount ?? 0);
$userName = (string) ($currentUser['name'] ?? '');
$userMobile = (string) ($currentUser['mobile'] ?? '');
$userEmail = (string) ($currentUser['email'] ?? '');
$isPendingConfirm = $member && (string) ($member['source_type'] ?? '') === 'pending_confirm';
$reallyBound = $member && !$isPendingConfirm;
$memName = (string) ($membership['name'] ?? '普通会员');
$memLevelId = (int) ($membership['id'] ?? 1);
$memExpire = (string) ($membership['expire_at'] ?? '');
$memExpired = $memExpire !== '' && strtotime($memExpire) < time();
$ref = (string) ($currentUser['ref_code'] ?? '');
$baseUrl = (string) ($baseUrl ?? '');
$canPublish = (int) ($currentUser['can_publish'] ?? 0);
$siteName = (string) ($siteName ?? '打卡系统');
$siteFooterText = (string) ($siteFooterText ?? '任务打卡系统');
$messageBoardName = (string) ($messageBoardName ?? '留言板');
$canCustomTitle = (bool) ($canCustomTitle ?? false);
$allowBoard = (int) ($membership['allow_message_board'] ?? 0) === 1;

$levelColors = [
    1 => ['bg' => 'from-rose-500 to-pink-600', 'text' => 'text-rose-600'],
    2 => ['bg' => 'from-blue-500 to-indigo-600', 'text' => 'text-blue-600'],
    3 => ['bg' => 'from-amber-400 to-orange-500', 'text' => 'text-amber-600'],
];
$lc = $levelColors[$memLevelId] ?? $levelColors[1];
?>
<style>
:root { --public-accent: #2563eb; }
html[data-theme="dark"] .uc-card { background: #1e293b; border-color: #334155; }
html[data-theme="dark"] .uc-card-text { color: #cbd5e1; }
</style>
<div class="mx-auto w-full max-w-5xl pb-10">

    <?php if (!empty($flash['message'])): ?>
        <div class="mb-4 rounded-xl border px-4 py-3 text-sm <?= ($flash['type'] ?? '') === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-rose-200 bg-rose-50 text-rose-700' ?>">
            <?= h((string) ($flash['message'] ?? '')) ?>
        </div>
    <?php endif; ?>

    <?php if (!$reallyBound && $matchingMembers !== []): ?>
        <section class="mb-4 rounded-2xl border-2 border-amber-300 bg-amber-50 p-5 shadow-sm">
            <div class="mb-3 flex items-center gap-2">
                <svg class="h-5 w-5 text-amber-600" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.168 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495ZM10 6a.75.75 0 0 1 .75.75v3.5a.75.75 0 0 1-1.5 0v-3.5A.75.75 0 0 1 10 6Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/></svg>
                <h2 class="text-base font-bold text-amber-800">发现同名系统用户</h2>
            </div>
            <p class="mb-4 text-sm text-amber-700">系统中存在与您同名的成员，绑定后可查看该成员的历史打卡记录。</p>
            <?php foreach ($matchingMembers as $m): ?>
                <div class="mb-3 flex items-center justify-between rounded-xl border border-amber-200 bg-white p-4">
                    <div class="space-y-1">
                        <div class="text-sm font-semibold text-slate-900"><?= h((string) $m['name']) ?></div>
                        <div class="text-xs text-slate-500">手机：<?= h((string) ($m['mobile'] ?? '未设置')) ?><?php if (!empty($m['position'])): ?> · <?= h((string) $m['position']) ?><?php endif; ?></div>
                    </div>
                    <form method="post" action="/my/bind">
                        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                        <input type="hidden" name="member_id" value="<?= (int) $m['id'] ?>">
                        <button type="submit" class="rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-700" onclick="return confirm('确认绑定此成员？')">绑定</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <div class="grid gap-5 lg:grid-cols-[300px_1fr]">

        <!-- 左侧 -->
        <div class="space-y-4">

            <!-- 会员状态卡 -->
            <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br <?= $lc['bg'] ?> p-5 pb-14 text-white shadow-lg">
                <div class="pointer-events-none absolute -right-6 -top-6 h-28 w-28 rounded-full bg-white/10"></div>
                <div class="pointer-events-none absolute -bottom-8 -left-4 h-24 w-24 rounded-full bg-white/10"></div>
                <div class="relative">
                    <div class="flex items-center gap-3">
                        <?php if ($memLevelId === 3): ?>
                            <svg class="h-8 w-8 text-yellow-200" viewBox="0 0 24 24" fill="currentColor"><path d="M5 16L3 6l5.5 4L12 4l3.5 6L21 6l-2 10H5zm0 2h14v2H5v-2z"/></svg>
                        <?php elseif ($memLevelId === 2): ?>
                            <svg class="h-8 w-8 text-sky-200" viewBox="0 0 24 24" fill="currentColor"><path d="M6 2h12l4 6-10 14L2 8l4-6zm.6 2L4.3 7.5h4.2L10 4H6.6zm6.8 0l1.5 3.5h4.2L16.4 4h-3zM8.4 9.5L12 18l3.6-8.5H8.4z"/></svg>
                        <?php else: ?>
                            <svg class="h-8 w-8 text-rose-200" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.9 6.1 6.6.8-4.9 4.6 1.3 6.5L12 17.3 6.1 20.6l1.3-6.5L2.5 8.9l6.6-.8L12 2z"/></svg>
                        <?php endif; ?>
                        <div>
                            <div class="text-xs font-medium text-white/70">当前等级</div>
                            <div class="text-lg font-bold"><?= h($memName) ?></div>
                        </div>
                    </div>
                    <div class="mt-3 text-xs text-white/80">
                        <?php if ($memExpire === ''): ?>
                            <span class="inline-flex items-center gap-1">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                永久有效
                            </span>
                        <?php elseif ($memExpired): ?>
                            <span class="font-semibold text-rose-200">
                                <svg class="inline h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
                                已过期
                            </span>
                        <?php else: ?>
                            <span>有效期至 <?= h(substr($memExpire, 0, 10)) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <a href="/shop" class="absolute bottom-4 right-4 inline-flex items-center gap-1 rounded-lg bg-white/20 px-3 py-1.5 text-xs font-semibold text-white backdrop-blur hover:bg-white/30">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                    升级会员
                </a>
            </div>

            <!-- 个人信息卡 -->
            <div class="uc-card relative overflow-hidden rounded-2xl border border-amber-200 bg-gradient-to-br from-amber-50 to-orange-100/60 p-4 shadow-sm">
                <div class="pointer-events-none absolute -right-6 -top-6 h-20 w-20 rounded-full bg-amber-200/50"></div>
                <div class="pointer-events-none absolute right-6 top-8 h-10 w-10 rounded-full bg-orange-200/40"></div>
                <div class="relative">
                    <div class="mb-3 flex items-center gap-3">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-amber-500 to-orange-600 text-lg font-bold text-white shadow-sm"><?= h(mb_substr($userName, 0, 1)) ?></div>
                        <div class="min-w-0">
                            <div class="truncate text-sm font-bold text-slate-900"><?= h($userName) ?></div>
                            <div class="text-xs text-amber-700/70">个人资料</div>
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-2 text-xs text-slate-700">
                            <svg class="h-3.5 w-3.5 shrink-0 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                            <span class="break-all"><?= h($userMobile !== '' ? $userMobile : '未设置') ?></span>
                        </div>
                        <?php if ($userEmail !== ''): ?>
                        <div class="flex items-center gap-2 text-xs text-slate-700">
                            <svg class="h-3.5 w-3.5 shrink-0 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            <span class="break-all"><?= h($userEmail) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($reallyBound): ?>
                        <div class="flex items-center gap-2 border-t border-amber-200/60 pt-1.5 text-xs text-slate-700">
                            <svg class="h-3.5 w-3.5 shrink-0 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span>绑定 <strong class="font-semibold"><?= h((string) $member['name']) ?></strong><?php if (!empty($member['mobile'])): ?> · <?= h((string) $member['mobile']) ?><?php endif; ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- 积分 & 余额卡 -->
            <div class="uc-card relative overflow-hidden rounded-2xl border border-amber-100 bg-gradient-to-br from-amber-50/70 to-orange-50/40 p-4 shadow-sm">
                <div class="pointer-events-none absolute -left-5 -bottom-5 h-20 w-20 rounded-full bg-amber-100/50"></div>
                <div class="relative">
                <div class="mb-3 flex items-center justify-between">
                    <span class="text-sm font-semibold text-slate-800">积分 &amp; 余额</span>
                    <svg class="h-4 w-4 text-amber-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                </div>
                <div class="grid grid-cols-2 gap-2 text-center">
                    <div class="rounded-xl bg-amber-50 px-2 py-3">
                        <div class="text-xl font-bold text-amber-600"><?= $userPoints ?></div>
                        <div class="text-xs text-amber-500">积分</div>
                    </div>
                    <div class="rounded-xl bg-emerald-50 px-2 py-3">
                        <div class="text-xl font-bold text-emerald-600">¥<?= number_format($userBalance / 10, 2) ?></div>
                        <div class="text-xs text-emerald-500">余额</div>
                    </div>
                </div>
                <?php if ($userPoints >= 10): ?>
                <form method="post" action="/my/convert-points" class="mt-3 flex gap-2">
                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                    <input type="number" name="points" min="10" step="10" max="<?= $userPoints ?>" value="10"
                           class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-xs focus:border-amber-400 focus:outline-none">
                    <button type="submit" class="shrink-0 rounded-lg bg-amber-500 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-600">兑换</button>
                </form>
                <p class="mt-1 text-center text-xs text-slate-400">10积分 = ¥1 余额</p>
                <?php else: ?>
                <p class="mt-3 text-center text-xs text-slate-400">推广好友注册可获积分，10积分兑¥1余额</p>
                <?php endif; ?>
                </div>
            </div>

            <!-- 推广卡 -->
            <?php if ($ref !== ''): ?>
            <div class="uc-card relative overflow-hidden rounded-2xl border border-blue-100 bg-gradient-to-br from-blue-50/70 to-indigo-50/40 p-4 shadow-sm">
                <div class="pointer-events-none absolute -right-6 -bottom-6 h-20 w-20 rounded-full bg-blue-100/50"></div>
                <div class="pointer-events-none absolute right-8 -top-3 h-10 w-10 rounded-full bg-indigo-100/40"></div>
                <div class="relative">
                <div class="mb-2 flex items-center justify-between">
                    <span class="text-sm font-semibold text-slate-800">推广中心</span>
                    <span class="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-600">已推广 <?= $referralCount ?> 人</span>
                </div>
                <p class="mb-1 text-xs text-slate-500">累计获得 <strong class="text-amber-600"><?= $referralCount ?> 积分</strong>，每推广一人注册获 1 积分</p>
                <div class="mt-2 flex items-center gap-2">
                    <input id="ref-link" readonly value="<?= h($baseUrl) ?>/register?ref=<?= h($ref) ?>"
                           class="min-w-0 flex-1 rounded-lg border border-slate-200 bg-white/70 px-2 py-1.5 text-xs text-slate-600">
                    <button type="button"
                            onclick="navigator.clipboard.writeText(document.getElementById('ref-link').value).then(()=>{this.textContent='已复制';setTimeout(()=>this.textContent='复制',1500)})"
                            class="shrink-0 rounded-lg bg-blue-600 px-2.5 py-1.5 text-xs font-semibold text-white hover:bg-blue-700">复制</button>
                </div>
                </div>
            </div>
            <?php endif; ?>

        </div><!-- /左侧 -->

        <!-- 右侧 -->
        <div class="space-y-5">

            <!-- 快捷功能区 -->
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="mb-4 text-base font-bold text-slate-900">快捷功能</h2>
                <div class="grid grid-cols-3 gap-3">
                    <a href="/tasks" class="group relative flex flex-col items-center gap-2 overflow-hidden rounded-xl border border-blue-200 bg-gradient-to-br from-blue-100 to-sky-50 px-3 py-4 text-center transition hover:border-blue-400 hover:shadow-md">
                        <span class="pointer-events-none absolute -right-3 -top-3 h-12 w-12 rounded-full bg-blue-200/60"></span>
                        <svg class="relative h-7 w-7 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                        <span class="relative text-xs font-semibold text-slate-700">进入打卡</span>
                    </a>
                    <a href="/my/records" class="group relative flex flex-col items-center gap-2 overflow-hidden rounded-xl border border-violet-200 bg-gradient-to-br from-violet-100 to-purple-50 px-3 py-4 text-center transition hover:border-violet-400 hover:shadow-md">
                        <span class="pointer-events-none absolute -right-3 -top-3 h-12 w-12 rounded-full bg-violet-200/60"></span>
                        <svg class="relative h-7 w-7 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <span class="relative text-xs font-semibold text-slate-700">打卡记录</span>
                    </a>
                    <a href="/my/password" class="group relative flex flex-col items-center gap-2 overflow-hidden rounded-xl border border-teal-200 bg-gradient-to-br from-teal-100 to-emerald-50 px-3 py-4 text-center transition hover:border-teal-400 hover:shadow-md">
                        <span class="pointer-events-none absolute -right-3 -top-3 h-12 w-12 rounded-full bg-teal-200/60"></span>
                        <svg class="relative h-7 w-7 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                        <span class="relative text-xs font-semibold text-slate-700">修改密码</span>
                    </a>
                </div>
            </section>

            <!-- 发布者中心 -->
            <?php if ($canPublish === 1): ?>
            <section class="rounded-2xl border border-blue-200 bg-gradient-to-br from-blue-50 to-indigo-50 p-5 shadow-sm">
                <div class="mb-4 flex items-center gap-2">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-600 text-white">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    </div>
                    <h2 class="text-base font-bold text-slate-900">发布者中心</h2>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <a href="/my/tasks" class="flex items-center gap-3 rounded-xl border border-white bg-white/80 px-4 py-3 text-sm font-semibold text-slate-700 shadow-sm backdrop-blur hover:border-blue-300 hover:bg-white">
                        <svg class="h-5 w-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        我的发布
                    </a>
                    <a href="/my/members" class="flex items-center gap-3 rounded-xl border border-white bg-white/80 px-4 py-3 text-sm font-semibold text-slate-700 shadow-sm backdrop-blur hover:border-blue-300 hover:bg-white">
                        <svg class="h-5 w-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        我的名单
                    </a>
                    <?php if ((int) ($membership['max_groups'] ?? 0) > 0): ?>
                    <a href="/my/member-groups" class="flex items-center gap-3 rounded-xl border border-white bg-white/80 px-4 py-3 text-sm font-semibold text-slate-700 shadow-sm backdrop-blur hover:border-blue-300 hover:bg-white">
                        <svg class="h-5 w-5 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                        用户组
                        <span class="ml-auto rounded-full bg-blue-100 px-2 py-0.5 text-xs text-blue-600">配额 <?= (int) ($membership['max_groups'] ?? 0) ?></span>
                    </a>
                    <?php else: ?>
                    <a href="/shop" class="flex items-center gap-3 rounded-xl border border-dashed border-blue-200 bg-white/60 px-4 py-3 text-sm text-slate-400 hover:border-blue-400">
                        <svg class="h-5 w-5 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        用户组
                        <span class="ml-auto text-xs text-blue-500">升级解锁</span>
                    </a>
                    <?php endif; ?>
                    <?php if ((int) ($membership['allow_message_board'] ?? 0) === 1): ?>
                    <a href="/my/message-board" class="flex items-center gap-3 rounded-xl border border-white bg-white/80 px-4 py-3 text-sm font-semibold text-slate-700 shadow-sm backdrop-blur hover:border-violet-300 hover:bg-white">
                        <svg class="h-5 w-5 text-fuchsia-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                        我的<?= h($messageBoardName) ?>
                    </a>
                    <?php endif; ?>
                    <?php if ((int) ($membership['allow_announcement'] ?? 0) === 1): ?>
                    <a href="/my/announcements" class="flex items-center gap-3 rounded-xl border border-white bg-white/80 px-4 py-3 text-sm font-semibold text-slate-700 shadow-sm backdrop-blur hover:border-amber-300 hover:bg-white">
                        <svg class="h-5 w-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
                        我的公告
                    </a>
                    <?php endif; ?>
                </div>
            </section>

            <!-- 页面设置 -->
            <section class="rounded-2xl border border-emerald-200 bg-gradient-to-br from-emerald-50 to-teal-50 p-5 shadow-sm">
                <div class="mb-4 flex items-center gap-2">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-600 text-white">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </div>
                    <h2 class="text-base font-bold text-slate-900">页面设置</h2>
                </div>
                <form method="post" action="/my/page-settings" class="space-y-3">
                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                    <label class="block text-sm font-medium text-slate-700">网站名称
                        <input type="text" name="site_name" value="<?= h($siteName) ?>" maxlength="60" <?= $canCustomTitle ? '' : 'disabled' ?> class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 <?= $canCustomTitle ? 'bg-white' : 'cursor-not-allowed bg-slate-100 text-slate-400' ?>">
                        <?php if (!$canCustomTitle): ?><span class="mt-1 block text-xs text-amber-600">当前会员等级无权自定义网站标题，升级会员后可用。</span><?php endif; ?>
                    </label>
                    <label class="block text-sm font-medium text-slate-700">留言板名字
                        <input type="text" name="message_board_name" value="<?= h($messageBoardName) ?>" maxlength="30" <?= $allowBoard ? '' : 'disabled' ?> class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 <?= $allowBoard ? 'bg-white' : 'cursor-not-allowed bg-slate-100 text-slate-400' ?>">
                        <?php if (!$allowBoard): ?><span class="mt-1 block text-xs text-amber-600">开通留言板功能后可自定义名称。</span><?php endif; ?>
                    </label>
                    <label class="block text-sm font-medium text-slate-700">页脚信息
                        <input type="text" name="site_footer_text" value="<?= h($siteFooterText) ?>" maxlength="200" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200">
                    </label>
                    <button type="submit" class="inline-flex h-10 items-center justify-center rounded-lg bg-emerald-600 px-5 text-sm font-semibold text-white transition hover:bg-emerald-700">保存设置</button>
                </form>
            </section>
            <?php endif; ?>

        </div><!-- /右侧 -->

    </div>
</div>
