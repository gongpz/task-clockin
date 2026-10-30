<?php
$footerText = function_exists('get_site_footer_text') ? get_site_footer_text() : '任务打卡系统';
?>
<footer class="border-t border-slate-200 bg-white/80 backdrop-blur">
    <div class="mx-auto flex h-12 w-full max-w-7xl items-center justify-between px-4 text-xs text-slate-500 sm:px-6 lg:px-8">
        <span><?= h($footerText) ?></span>
        <span>&copy; <?= date('Y') ?></span>
    </div>
</footer>
