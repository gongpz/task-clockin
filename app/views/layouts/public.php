<?php
$safeTitle = h((string) ($title ?? '页面'));
$siteName = function_exists('get_site_name') ? get_site_name() : '任务打卡系统';
$brandTitle = $siteName;
$brandHref = (string) ($brandHref ?? '/tasks');
$headerActionLabel = '管理员登录';
$headerActionHref = (string) ($headerActionHref ?? '/admin/login');
$showHeader = isset($showHeader) ? (bool) $showHeader : true;
$themeToggleEnabled = isset($themeToggleEnabled) ? (bool) $themeToggleEnabled : true;
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= $safeTitle ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root {
            color-scheme: light;
            --public-bg: #f8fafc;
            --public-bg-soft: #eef4ff;
            --public-surface: rgba(255, 255, 255, 0.92);
            --public-surface-strong: #ffffff;
            --public-border: #e2e8f0;
            --public-border-strong: #cbd5e1;
            --public-text: #0f172a;
            --public-text-soft: #475569;
            --public-accent: #2563eb;
            --public-accent-strong: #1d4ed8;
            --public-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
        }

        html[data-theme="dark"] {
            color-scheme: dark;
            --public-bg: #09111f;
            --public-bg-soft: #0f1a2d;
            --public-surface: rgba(15, 23, 42, 0.9);
            --public-surface-strong: #111b2f;
            --public-border: #22314a;
            --public-border-strong: #334155;
            --public-text: #e2e8f0;
            --public-text-soft: #a8b8cf;
            --public-accent: #60a5fa;
            --public-accent-strong: #3b82f6;
            --public-shadow: 0 22px 46px rgba(2, 6, 23, 0.36);
        }

        * {
            box-sizing: border-box;
        }

        html {
            min-height: 100%;
            background: var(--public-bg);
            -webkit-text-size-adjust: 100%;
            text-size-adjust: 100%;
        }

        body {
            min-height: 100vh;
            margin: 0;
            font-family: "PingFang SC", "Microsoft YaHei UI", "Segoe UI", Arial, sans-serif;
            background:
                radial-gradient(circle at top right, rgba(96, 165, 250, 0.14), transparent 34%),
                radial-gradient(circle at left top, rgba(125, 211, 252, 0.1), transparent 28%),
                linear-gradient(180deg, var(--public-bg-soft) 0%, var(--public-bg) 24%, var(--public-bg) 100%);
            color: var(--public-text);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image:
                linear-gradient(rgba(148, 163, 184, 0.06) 1px, transparent 1px),
                linear-gradient(90deg, rgba(148, 163, 184, 0.06) 1px, transparent 1px);
            background-size: 24px 24px;
            mask-image: linear-gradient(180deg, rgba(255,255,255,0.3), rgba(255,255,255,0));
            pointer-events: none;
            opacity: .28;
        }

        a {
            color: inherit;
        }

        .public-wrap {
            position: relative;
            max-width: 1240px;
            margin: 0 auto;
            padding: calc(22px + env(safe-area-inset-top)) 16px calc(32px + env(safe-area-inset-bottom));
            display: grid;
            gap: 18px;
        }

        .public-wrap::before {
            content: '';
            position: absolute;
            top: 8px;
            right: 24px;
            width: 220px;
            height: 220px;
            border-radius: 9999px;
            background: radial-gradient(circle, rgba(37, 99, 235, 0.12) 0%, rgba(37, 99, 235, 0) 72%);
            filter: blur(6px);
            pointer-events: none;
            z-index: 0;
        }

        .public-head {
            position: relative;
            z-index: 1;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            border: 1px solid var(--public-border);
            border-radius: 24px;
            background: var(--public-surface);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            padding: 14px 16px;
            box-shadow: var(--public-shadow);
        }

        .public-head-title {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            min-width: 0;
        }

        .public-head-title:hover .logo {
            transform: translateY(-1px);
            box-shadow: 0 14px 24px rgba(37, 99, 235, 0.24);
        }

        .public-head-title .logo {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--public-accent) 0%, #38bdf8 100%);
            color: #fff;
            box-shadow: 0 12px 22px rgba(37, 99, 235, 0.22);
            transition: transform .18s ease, box-shadow .18s ease;
            flex: 0 0 auto;
        }

        .public-head-copy {
            min-width: 0;
        }

        .public-head-copy strong {
            display: block;
            font-size: 15px;
            line-height: 1.2;
            font-weight: 700;
            color: var(--public-text);
        }

        .public-head-copy span {
            display: block;
            margin-top: 4px;
            font-size: 12px;
            line-height: 1.4;
            color: var(--public-text-soft);
        }

        .button-link {
            position: relative;
            overflow: hidden;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 40px;
            padding: 0 16px;
            border-radius: 16px;
            border: 1px solid rgba(147, 197, 253, 0.9);
            background: linear-gradient(135deg, var(--public-accent) 0%, #38bdf8 100%);
            color: #fff !important;
            font-size: 13px;
            line-height: 1;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            box-shadow: 0 14px 24px rgba(37, 99, 235, 0.18);
            transition: transform .18s ease, box-shadow .18s ease, filter .18s ease;
        }

        .button-link::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(120deg, rgba(255, 255, 255, 0) 0%, rgba(255,255,255,0.18) 34%, rgba(255,255,255,0) 68%);
            transform: translateX(-140%);
            transition: transform .55s ease;
        }

        .button-link:hover {
            transform: translateY(-1px);
            box-shadow: 0 18px 30px rgba(37, 99, 235, 0.24);
            filter: saturate(1.05);
        }

        .button-link:hover::after {
            transform: translateX(140%);
        }

        .public-content {
            position: relative;
            z-index: 1;
            display: grid;
            gap: 18px;
            animation: publicContentFade .32s ease both;
            will-change: opacity;
        }

        .theme-toggle {
            position: fixed;
            right: calc(16px + env(safe-area-inset-right));
            bottom: calc(18px + env(safe-area-inset-bottom));
            z-index: 70;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            border-radius: 999px;
            border: 1px solid var(--public-border);
            background: rgba(255, 255, 255, 0.9);
            color: #334155;
            box-shadow: var(--public-shadow);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            cursor: pointer;
            transition: transform .16s ease, border-color .16s ease, background-color .16s ease, color .16s ease;
        }

        .theme-toggle:hover {
            transform: translateY(-1px);
            border-color: var(--public-border-strong);
            background: rgba(255, 255, 255, 0.98);
        }

        .theme-toggle svg {
            width: 18px;
            height: 18px;
        }

        html[data-theme="dark"] .theme-toggle {
            background: rgba(15, 23, 42, 0.86);
            color: #dbe5f2;
        }

        html[data-theme="dark"] .theme-toggle:hover {
            background: rgba(15, 23, 42, 0.96);
        }

        html[data-theme="dark"] .public-head,
        html[data-theme="dark"] section.rounded-xl,
        html[data-theme="dark"] section.rounded-2xl,
        html[data-theme="dark"] section.rounded-3xl,
        html[data-theme="dark"] article.rounded-xl,
        html[data-theme="dark"] article.rounded-2xl,
        html[data-theme="dark"] article.rounded-3xl,
        html[data-theme="dark"] div.rounded-2xl.bg-white,
        html[data-theme="dark"] div.rounded-3xl.bg-white {
            background: var(--public-surface-strong) !important;
            border-color: var(--public-border) !important;
            color: var(--public-text) !important;
        }

        html[data-theme="dark"] .bg-white,
        html[data-theme="dark"] .bg-slate-50,
        html[data-theme="dark"] .bg-slate-100,
        html[data-theme="dark"] .bg-blue-50,
        html[data-theme="dark"] .bg-emerald-50,
        html[data-theme="dark"] .bg-amber-50,
        html[data-theme="dark"] .bg-rose-50,
        html[data-theme="dark"] .bg-indigo-50 {
            background: var(--public-surface-strong) !important;
        }

        html[data-theme="dark"] .border-slate-200,
        html[data-theme="dark"] .border-slate-300,
        html[data-theme="dark"] .border-blue-100,
        html[data-theme="dark"] .border-blue-200,
        html[data-theme="dark"] .border-emerald-200,
        html[data-theme="dark"] .border-amber-200,
        html[data-theme="dark"] .border-rose-200,
        html[data-theme="dark"] .border-indigo-200 {
            border-color: var(--public-border) !important;
        }

        html[data-theme="dark"] .text-slate-900,
        html[data-theme="dark"] .text-slate-800 {
            color: #f1f5f9 !important;
        }

        html[data-theme="dark"] .text-slate-700,
        html[data-theme="dark"] .text-slate-600,
        html[data-theme="dark"] .text-slate-500,
        html[data-theme="dark"] .text-slate-400 {
            color: var(--public-text-soft) !important;
        }

        html[data-theme="dark"] .text-blue-700,
        html[data-theme="dark"] .text-emerald-700,
        html[data-theme="dark"] .text-indigo-700,
        html[data-theme="dark"] .text-amber-700,
        html[data-theme="dark"] .text-rose-700 {
            color: #93c5fd !important;
        }

        html[data-theme="dark"] input:not([type="checkbox"]):not([type="radio"]),
        html[data-theme="dark"] select,
        html[data-theme="dark"] textarea {
            background: #0f172a !important;
            border-color: var(--public-border-strong) !important;
            color: #e2e8f0 !important;
        }

        html[data-theme="dark"] input::placeholder,
        html[data-theme="dark"] textarea::placeholder {
            color: #8192aa !important;
        }

        @keyframes publicContentFade {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        @media (max-width: 640px) {
            .public-wrap {
                padding: calc(16px + env(safe-area-inset-top)) 12px calc(26px + env(safe-area-inset-bottom));
                gap: 14px;
            }

            .public-wrap::before {
                right: -12px;
                width: 140px;
                height: 140px;
            }

            .public-head {
                border-radius: 20px;
                padding: 12px 14px;
            }

            .public-head-title .logo {
                width: 38px;
                height: 38px;
                border-radius: 12px;
            }

            .button-link {
                min-height: 38px;
                padding: 0 14px;
                font-size: 12px;
            }

            .theme-toggle {
                right: calc(12px + env(safe-area-inset-right));
                bottom: calc(14px + env(safe-area-inset-bottom));
            }
        }
    </style>
</head>
<body class="antialiased">
    <div class="public-wrap">
        <?php if ($showHeader): ?>
            <header class="public-head">
                <a class="public-head-title" href="<?= h($brandHref) ?>">
                    <span class="logo">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path d="M10.75 2.5a.75.75 0 0 0-1.5 0V3h-1A2.75 2.75 0 0 0 5.5 5.75v8.5A2.75 2.75 0 0 0 8.25 17h3.5a2.75 2.75 0 0 0 2.75-2.75v-8.5A2.75 2.75 0 0 0 11.75 3h-1v-.5Z"/>
                        </svg>
                    </span>
                    <span class="public-head-copy">
                        <strong><?= h($brandTitle) ?></strong>
                        <span>任务发布与打卡提交</span>
                    </span>
                </a>
                <div style="display:flex;align-items:center;gap:8px;">
                    <?php if (isset($currentUser) && $currentUser !== null): ?>
                        <a class="button-link" href="/my">
                            <span>个人中心</span>
                        </a>
                        <a class="button-link" href="/logout" style="background:linear-gradient(135deg,#f43f5e 0%,#fb923c 100%);border-color:rgba(244,63,94,.6);font-size:12px;">退出</a>
                    <?php else: ?>
                        <a class="button-link" href="/register">
                            <span>注册</span>
                        </a>
                        <a class="button-link" href="/login">
                            <span>登录</span>
                        </a>
                    <?php endif; ?>
                </div>
            </header>
        <?php endif; ?>

        <main class="public-content">
            <?= (string) ($content ?? '') ?>
        </main>
    </div>

    <?php if ($themeToggleEnabled): ?>
        <button id="public-theme-toggle" type="button" class="theme-toggle" aria-label="切换显示模式" title="切换显示模式">
            <svg id="theme-icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <circle cx="12" cy="12" r="4"></circle>
                <path stroke-linecap="round" d="M12 2.5v2.2M12 19.3v2.2M4.93 4.93l1.56 1.56M17.51 17.51l1.56 1.56M2.5 12h2.2M19.3 12h2.2M4.93 19.07l1.56-1.56M17.51 6.49l1.56-1.56"></path>
            </svg>
            <svg id="theme-icon-moon" class="hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20.35 15.04A8.5 8.5 0 1 1 8.96 3.65a6.5 6.5 0 1 0 11.39 11.39Z"></path>
            </svg>
        </button>
    <?php endif; ?>

    <script>
        (function () {
            const toggleBtn = document.getElementById('public-theme-toggle');
            if (!toggleBtn) {
                return;
            }

            const sunIcon = document.getElementById('theme-icon-sun');
            const moonIcon = document.getElementById('theme-icon-moon');
            const storageKey = 'daka_public_theme_mode';

            const syncIcon = (mode) => {
                const dark = mode === 'dark';
                if (sunIcon) {
                    sunIcon.classList.toggle('hidden', dark);
                }
                if (moonIcon) {
                    moonIcon.classList.toggle('hidden', !dark);
                }
                toggleBtn.setAttribute('title', dark ? '切换到浅色模式' : '切换到深色模式');
                toggleBtn.setAttribute('aria-label', dark ? '切换到浅色模式' : '切换到深色模式');
            };

            const applyMode = (mode) => {
                const finalMode = mode === 'dark' ? 'dark' : 'light';
                document.documentElement.setAttribute('data-theme', finalMode);
                syncIcon(finalMode);
            };

            const saved = localStorage.getItem(storageKey);
            applyMode(saved === 'dark' ? 'dark' : 'light');

            toggleBtn.addEventListener('click', function () {
                const current = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
                const next = current === 'dark' ? 'light' : 'dark';
                applyMode(next);
                localStorage.setItem(storageKey, next);
            });
        })();
    </script>
</body>
</html>
