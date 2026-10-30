<?php
$safeTitle = h((string) ($title ?? '管理后台'));
$brandTitleValue = function_exists('get_site_name') ? get_site_name() : ((string) ($brandTitle ?? '打卡系统'));
$adminNameValue = (string) ($adminName ?? '管理员');
$adminCenterHrefValue = (string) ($adminCenterHref ?? '/admin/center');
$logoutHrefValue = (string) ($logoutHref ?? '/admin/logout');
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $safeTitle ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root { color-scheme: light; }

        body {
            font-family: "PingFang SC", "Microsoft YaHei UI", "Segoe UI", Arial, sans-serif;
        }

        .admin-content {
            display: grid;
            gap: 1rem;
        }

        .admin-content .tw-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .admin-content .tw-table th,
        .admin-content .tw-table td {
            vertical-align: middle;
        }

        .admin-content .button-link,
        .admin-content [data-btn="primary"],
        .admin-content .btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .3rem;
            border-radius: .68rem;
            border: 1px solid #bfdbfe;
            background: #2563eb;
            color: #fff !important;
            padding: .5rem .82rem;
            font-size: .82rem;
            line-height: 1.2;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: background-color .16s ease, border-color .16s ease;
        }

        .admin-content .button-link:hover,
        .admin-content [data-btn="primary"]:hover,
        .admin-content .btn-primary:hover {
            background: #1d4ed8;
            border-color: #93c5fd;
        }

        .admin-content .button-link:disabled,
        .admin-content [data-btn="primary"]:disabled,
        .admin-content .btn-primary:disabled {
            opacity: .6;
            cursor: not-allowed;
        }

        @media (max-width: 768px) {
            .admin-content .tw-table-wrap {
                overflow-x: auto;
            }
        }
    </style>
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">
<div class="min-h-screen">
    <?php $sidebarMode = 'desktop'; include __DIR__ . '/../partials/admin_sidebar.php'; ?>

    <div class="flex min-h-screen flex-col lg:pl-72">
        <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/90 backdrop-blur">
            <div class="flex h-16 items-center justify-between gap-3 px-4 sm:px-6 lg:px-8">
                <div class="flex min-w-0 items-center gap-3">
                    <button
                        id="admin-sidebar-toggle"
                        type="button"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-slate-300 text-slate-600 transition hover:bg-slate-50 lg:hidden"
                        aria-expanded="false"
                        aria-controls="admin-sidebar-overlay"
                        aria-label="展开侧边栏"
                    >
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>
                        </svg>
                    </button>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-slate-900"><?= $safeTitle ?></p>
                        <p class="truncate text-xs text-slate-500"><?= h($brandTitleValue) ?></p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <a href="<?= h($adminCenterHrefValue) ?>" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-600 transition hover:bg-slate-50">
                        <span class="inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
                        <?= h($adminNameValue) ?>
                    </a>
                    <a href="<?= h($logoutHrefValue) ?>" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-600 transition hover:bg-slate-50">
                        退出登录
                    </a>
                </div>
            </div>
        </header>

        <main class="flex-1 p-4 sm:p-6 lg:p-8">
            <div class="admin-content mx-auto w-full max-w-7xl">
                <?= (string) ($flashHtml ?? '') ?>
                <?= (string) ($content ?? '') ?>
            </div>
        </main>

        <?php include __DIR__ . '/../partials/admin_footer.php'; ?>
    </div>
</div>

<div id="admin-sidebar-overlay" class="fixed inset-0 z-50 hidden lg:hidden">
    <button id="admin-sidebar-backdrop" class="absolute inset-0 bg-slate-900/45" aria-label="关闭侧边栏"></button>
    <div class="relative h-full">
        <?php $sidebarMode = 'mobile'; include __DIR__ . '/../partials/admin_sidebar.php'; ?>
    </div>
</div>

<script>
    document.querySelectorAll('[data-auto-dismiss="true"]').forEach(function (node) {
        setTimeout(function () {
            node.style.transition = 'opacity .25s ease, transform .25s ease';
            node.style.opacity = '0';
            node.style.transform = 'translateY(-4px)';
            setTimeout(function () { node.remove(); }, 260);
        }, 3200);
    });

    var sidebarToggle = document.getElementById('admin-sidebar-toggle');
    var sidebarOverlay = document.getElementById('admin-sidebar-overlay');
    var sidebarBackdrop = document.getElementById('admin-sidebar-backdrop');

    var closeSidebar = function () {
        if (!sidebarOverlay || !sidebarToggle) {
            return;
        }
        sidebarOverlay.classList.add('hidden');
        sidebarToggle.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('overflow-hidden');
    };

    var openSidebar = function () {
        if (!sidebarOverlay || !sidebarToggle) {
            return;
        }
        sidebarOverlay.classList.remove('hidden');
        sidebarToggle.setAttribute('aria-expanded', 'true');
        document.body.classList.add('overflow-hidden');
    };

    if (sidebarToggle && sidebarOverlay) {
        sidebarToggle.addEventListener('click', function () {
            var expanded = sidebarToggle.getAttribute('aria-expanded') === 'true';
            if (expanded) {
                closeSidebar();
                return;
            }
            openSidebar();
        });
    }

    if (sidebarBackdrop) {
        sidebarBackdrop.addEventListener('click', closeSidebar);
    }

    document.querySelectorAll('[data-sidebar-close]').forEach(function (node) {
        node.addEventListener('click', closeSidebar);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeSidebar();
        }
    });

    var copyFeedback = function (message) {
        var toast = document.createElement('div');
        toast.className = 'fixed right-4 top-4 z-[100] rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-medium text-emerald-700 shadow';
        toast.textContent = message;
        document.body.appendChild(toast);
        setTimeout(function () {
            toast.style.transition = 'opacity .2s ease, transform .2s ease';
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-4px)';
            setTimeout(function () { toast.remove(); }, 220);
        }, 1800);
    };

    document.querySelectorAll('[data-copy-link]').forEach(function (node) {
        node.addEventListener('click', function () {
            var link = node.getAttribute('data-copy-link') || '';
            if (!link) {
                return;
            }
            var fullLink = link;
            if (!/^https?:\/\//i.test(fullLink)) {
                fullLink = window.location.origin + link;
            }

            var done = function () { copyFeedback('链接已复制'); };
            var fallback = function () {
                var input = document.createElement('input');
                input.value = fullLink;
                document.body.appendChild(input);
                input.select();
                document.execCommand('copy');
                document.body.removeChild(input);
                done();
            };

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(fullLink).then(done).catch(fallback);
            } else {
                fallback();
            }
        });
    });
</script>
</body>
</html>
