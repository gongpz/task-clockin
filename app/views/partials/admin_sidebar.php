<?php
$sidebarMode = (string) ($sidebarMode ?? 'desktop');
$isMobileSidebar = $sidebarMode === 'mobile';
$brandHrefValue = (string) ($brandHref ?? '/admin');
$brandTitleValue = function_exists('get_site_name') ? get_site_name() : ((string) ($brandTitle ?? '打卡系统'));
$brandSubtitleValue = (string) ($brandSubtitle ?? '后台管理');
$adminNameValue = (string) ($adminName ?? '管理员');
$adminCenterHrefValue = (string) ($adminCenterHref ?? '/admin/center');
$logoutHrefValue = (string) ($logoutHref ?? '/admin/logout');
$navItems = is_array($adminNavItems ?? null) ? $adminNavItems : [];
$isNavActiveHandler = is_callable($isNavActive ?? null)
    ? $isNavActive
    : static function (string $href): bool {
        return $href === '';
    };

$sidebarClass = $isMobileSidebar
    ? 'flex h-full w-72 max-w-[86vw] flex-col border-r border-slate-200 bg-white shadow-xl'
    : 'fixed inset-y-0 left-0 z-40 hidden w-72 flex-col border-r border-slate-200 bg-white lg:flex';
?>
<aside class="<?= $sidebarClass ?>">
    <div class="flex h-16 items-center justify-between border-b border-slate-200 px-4">
        <a href="<?= h($brandHrefValue) ?>" class="inline-flex min-w-0 items-center gap-3 rounded-md py-1">
            <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-blue-600 text-white shadow-sm">
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M10.75 2.5a.75.75 0 0 0-1.5 0V3h-1A2.75 2.75 0 0 0 5.5 5.75v8.5A2.75 2.75 0 0 0 8.25 17h3.5a2.75 2.75 0 0 0 2.75-2.75v-8.5A2.75 2.75 0 0 0 11.75 3h-1v-.5Z"/>
                </svg>
            </span>
            <span class="grid leading-tight">
                <span class="text-sm font-semibold text-slate-900"><?= h($brandTitleValue) ?></span>
                <span class="text-xs text-slate-500"><?= h($brandSubtitleValue) ?></span>
            </span>
        </a>
        <?php if ($isMobileSidebar): ?>
            <button
                type="button"
                data-sidebar-close
                class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-300 text-slate-500 transition hover:bg-slate-50"
                aria-label="关闭侧边栏"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m6 6 12 12M18 6 6 18"/>
                </svg>
            </button>
        <?php endif; ?>
    </div>

    <div class="flex-1 overflow-y-auto px-3 py-4">
        <p class="px-3 text-xs font-semibold uppercase tracking-[0.08em] text-slate-400">导航菜单</p>
        <nav class="mt-2 space-y-1">
            <?php foreach ($navItems as $item): ?>
                <?php
                $href = (string) ($item['href'] ?? '#');
                $label = (string) ($item['label'] ?? '菜单');
                $target = (string) ($item['target'] ?? '');
                $active = (bool) $isNavActiveHandler($href);
                $targetAttr = $target !== '' ? ' target="' . h($target) . '" rel="noopener noreferrer"' : '';
                $baseClass = 'group flex items-center justify-between rounded-lg px-3 py-2.5 text-sm font-medium transition';
                $activeClass = 'bg-blue-50 text-blue-700';
                $inactiveClass = 'text-slate-700 hover:bg-slate-100 hover:text-slate-900';
                ?>
                <a class="<?= $baseClass . ' ' . ($active ? $activeClass : $inactiveClass) ?>" href="<?= h($href) ?>"<?= $targetAttr ?>>
                    <span><?= h($label) ?></span>
                    <?php if ($target === '_blank'): ?>
                        <svg class="h-4 w-4 opacity-70 transition group-hover:opacity-100" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H19m0 0v5.5M19 6l-7.5 7.5"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 10.5V18h7.5"/>
                        </svg>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>

    <div class="border-t border-slate-200 p-3">
        <a href="<?= h($adminCenterHrefValue) ?>" class="mb-2 block rounded-lg bg-slate-50 p-3 transition hover:bg-slate-100">
            <div class="flex items-center gap-2">
                <span class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-slate-200 text-slate-600">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.964 0a9 9 0 1 0-11.964 0"/>
                    </svg>
                </span>
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium text-slate-800"><?= h($adminNameValue) ?></p>
                </div>
            </div>
        </a>
        <a href="<?= h($logoutHrefValue) ?>" class="inline-flex w-full items-center justify-center rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
            退出登录
        </a>
    </div>
</aside>
