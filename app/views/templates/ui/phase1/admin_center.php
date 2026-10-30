<?php
$csrf = (string) ($csrf ?? '');
$imageCompressionEnabled = (bool) ($imageCompressionEnabled ?? true);
$publicThemeToggleEnabled = (bool) ($publicThemeToggleEnabled ?? true);
$recycleRows = is_array($recycleRows ?? null) ? $recycleRows : [];
$recycleTotal = (int) ($recycleTotal ?? count($recycleRows));
$recycleAutoPurgeDays = (int) ($recycleAutoPurgeDays ?? 0);
$autoPurgedCount = (int) ($autoPurgedCount ?? 0);
$siteName = (string) ($siteName ?? '打卡系统');
$siteFooterText = (string) ($siteFooterText ?? '任务打卡系统');
$canCustomTitle = (bool) ($canCustomTitle ?? false);
?>
<div class="space-y-5">
    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-base font-semibold text-slate-900">全局设置</h2>
        <p class="mt-1 text-sm text-slate-500">统一管理系统级开关和管理员操作入口。</p>

        <div class="mt-4 grid gap-3 lg:grid-cols-[1fr_1fr_auto_auto]">
            <form method="post" action="/admin/center/image-compression" class="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                <input type="hidden" name="enabled" value="<?= $imageCompressionEnabled ? '0' : '1' ?>">
                <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium <?= $imageCompressionEnabled ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-white text-slate-600' ?>">
                    图片压缩：<?= $imageCompressionEnabled ? '已开启' : '已关闭' ?>
                </span>
                <button type="submit" class="inline-flex h-8 items-center whitespace-nowrap rounded-md border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 transition hover:bg-slate-100">
                    <?= $imageCompressionEnabled ? '关闭压缩' : '开启压缩' ?>
                </button>
            </form>

            <form method="post" action="/admin/center/theme-toggle" class="inline-flex min-h-[40px] items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                <input type="hidden" name="enabled" value="<?= $publicThemeToggleEnabled ? '0' : '1' ?>">
                <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium <?= $publicThemeToggleEnabled ? 'border-blue-200 bg-blue-50 text-blue-700' : 'border-slate-200 bg-white text-slate-600' ?>">
                    前台显示模式按钮：<?= $publicThemeToggleEnabled ? '显示' : '隐藏' ?>
                </span>
                <button type="submit" class="inline-flex h-8 items-center whitespace-nowrap rounded-md border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 transition hover:bg-slate-100">
                    <?= $publicThemeToggleEnabled ? '设为隐藏' : '设为显示' ?>
                </button>
            </form>

            <a href="/admin/records/create" class="inline-flex h-10 items-center justify-center whitespace-nowrap rounded-lg bg-blue-600 px-4 text-sm font-semibold text-white transition hover:bg-blue-700">
                后台补录打卡
            </a>
            <a href="/admin/center/stats" class="inline-flex h-10 items-center justify-center whitespace-nowrap rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                统计中心
            </a>
        </div>

        <form method="post" action="/admin/center/site-meta" class="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-3">
            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
            <div class="grid gap-3 lg:grid-cols-[1fr_1.4fr_auto]">
                <label class="block text-sm font-medium text-slate-700">系统名称
                    <input
                        type="text"
                        name="site_name"
                        value="<?= h($siteName) ?>"
                        maxlength="60"
                        <?= $canCustomTitle ? '' : 'disabled' ?>
                        class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200 <?= $canCustomTitle ? '' : 'cursor-not-allowed bg-slate-100 text-slate-400' ?>"
                    >
                    <?php if (!$canCustomTitle): ?>
                        <span class="mt-1 block text-xs text-amber-600">当前会员等级无权自定义网站标题，升级会员后可用。</span>
                    <?php endif; ?>
                </label>
                <label class="block text-sm font-medium text-slate-700">页脚信息
                    <input
                        type="text"
                        name="site_footer_text"
                        value="<?= h($siteFooterText) ?>"
                        maxlength="200"
                        class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                    >
                </label>
                <div class="flex items-end">
                    <button type="submit" class="inline-flex h-10 items-center rounded-lg bg-blue-600 px-4 text-sm font-semibold text-white transition hover:bg-blue-700">保存站点信息</button>
                </div>
            </div>
        </form>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold text-slate-900">回收站</h2>
                <p class="mt-1 text-sm text-slate-500">管理软删除打卡记录，可恢复或彻底删除。</p>
            </div>
            <div class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-600">
                当前回收站：<?= $recycleTotal ?> 条
            </div>
        </div>

        <div class="mt-4 overflow-x-auto rounded-lg border border-slate-200 bg-slate-50 p-3">
            <div class="flex min-w-[920px] flex-nowrap items-center gap-2">
                <form method="post" action="/admin/center/recycle/settings" class="inline-flex items-center gap-2">
                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                    <span class="whitespace-nowrap text-sm text-slate-700">自动清空软删除记录（天）</span>
                    <input
                        type="number"
                        name="auto_purge_days"
                        min="0"
                        step="1"
                        value="<?= $recycleAutoPurgeDays ?>"
                        class="h-8 w-14 rounded-md border border-slate-300 bg-white px-2 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                    >
                    <span class="whitespace-nowrap text-xs text-slate-500">0 表示关闭自动清理</span>
                    <button type="submit" class="inline-flex h-8 items-center whitespace-nowrap rounded-md bg-blue-600 px-3 text-xs font-semibold text-white transition hover:bg-blue-700">保存设置</button>
                </form>

                <form method="post" action="/admin/center/recycle/purge-now" class="inline-flex" onsubmit="return confirm('确认立即清空超期软删除记录吗？');">
                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                    <button type="submit" class="inline-flex h-8 items-center whitespace-nowrap rounded-md border border-rose-200 bg-rose-50 px-3 text-xs font-semibold text-rose-700 transition hover:bg-rose-100">立即清空超期记录</button>
                </form>
            </div>
        </div>

        <?php if ($autoPurgedCount > 0): ?>
            <div class="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-medium text-emerald-700">
                本次进入页面时，已自动清理超期回收站记录 <?= $autoPurgedCount ?> 条。
            </div>
        <?php endif; ?>

        <div class="tw-table-wrap mt-4 overflow-x-auto">
            <table class="tw-table min-w-[980px] w-full table-fixed divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="w-[90px] whitespace-nowrap px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">记录编号</th>
                        <th class="w-[162px] whitespace-nowrap px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">提交时间</th>
                        <th class="w-[250px] whitespace-nowrap px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">任务</th>
                        <th class="w-[120px] whitespace-nowrap px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">人员</th>
                        <th class="w-[120px] whitespace-nowrap px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">打卡日期</th>
                        <th class="w-[165px] whitespace-nowrap px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">删除时间</th>
                        <th class="w-[170px] whitespace-nowrap px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">操作</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    <?php if ($recycleRows === []): ?>
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-slate-500">回收站暂无记录</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recycleRows as $row): ?>
                            <?php $id = (int) ($row['id'] ?? 0); ?>
                            <tr class="hover:bg-slate-50">
                                <td class="px-3 py-3 whitespace-nowrap text-slate-700">#<?= $id ?></td>
                                <td class="px-3 py-3 whitespace-nowrap text-slate-700"><div class="truncate"><?= h((string) ($row['submit_time'] ?? '-')) ?></div></td>
                                <td class="px-3 py-3 whitespace-nowrap text-slate-700"><div class="truncate" title="<?= h((string) ($row['task_name'] ?? '-')) ?>"><?= h((string) ($row['task_name'] ?? '-')) ?></div></td>
                                <td class="px-3 py-3 whitespace-nowrap text-slate-700"><div class="truncate" title="<?= h((string) ($row['member_name'] ?? '-')) ?>"><?= h((string) ($row['member_name'] ?? '-')) ?></div></td>
                                <td class="px-3 py-3 whitespace-nowrap text-slate-700"><?= h((string) ($row['task_date'] ?? '-')) ?></td>
                                <td class="px-3 py-3 whitespace-nowrap text-slate-600"><div class="truncate"><?= h((string) ($row['deleted_at'] ?? '-')) ?></div></td>
                                <td class="px-3 py-2 whitespace-nowrap">
                                    <div class="flex flex-nowrap items-center gap-1.5">
                                        <form method="post" action="/admin/center/recycle/restore" class="inline-flex" onsubmit="return confirm('确认恢复该记录吗？');">
                                            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                            <input type="hidden" name="id" value="<?= $id ?>">
                                            <button type="submit" class="inline-flex h-6 items-center rounded-md border border-blue-200 bg-blue-50 px-1.5 text-[10px] font-medium text-blue-700 transition hover:bg-blue-100">恢复</button>
                                        </form>
                                        <form method="post" action="/admin/center/recycle/delete" class="inline-flex" onsubmit="return confirm('确认彻底删除该记录吗？该操作不可恢复。');">
                                            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                            <input type="hidden" name="id" value="<?= $id ?>">
                                            <button type="submit" class="inline-flex h-6 items-center rounded-md border border-rose-200 bg-rose-50 px-1.5 text-[10px] font-medium text-rose-700 transition hover:bg-rose-100">彻底删除</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
