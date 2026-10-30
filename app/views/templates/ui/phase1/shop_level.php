<?php
$level = is_array($level ?? null) ? $level : [];
$plans = is_array($plans ?? null) ? $plans : [];
$csrf = (string) ($csrf ?? '');
$flash = is_array($flash ?? null) ? $flash : null;
$membership = is_array($membership ?? null) ? $membership : [];
$lid = (int) ($level['id'] ?? 1);
$curLevelId = (int) ($membership['id'] ?? 1);

$cfg = [
    1 => ['title' => '普通会员', 'sub' => '基础功能免费体验', 'grad' => 'from-rose-500 to-pink-600', 'light' => 'from-rose-50 to-pink-50', 'border' => 'border-rose-200', 'badge' => 'bg-rose-100 text-rose-700', 'btn' => 'bg-rose-600 hover:bg-rose-700', 'price' => 'text-rose-600', 'ring' => 'hover:ring-rose-300', 'icon' => 'star', 'iconColor' => 'text-rose-200'],
    2 => ['title' => '高级会员', 'sub' => '解锁更多功能', 'grad' => 'from-blue-500 to-indigo-600', 'light' => 'from-blue-50 to-indigo-50', 'border' => 'border-blue-200', 'badge' => 'bg-blue-100 text-blue-700', 'btn' => 'bg-blue-600 hover:bg-blue-700', 'price' => 'text-blue-600', 'ring' => 'hover:ring-blue-300', 'icon' => 'diamond', 'iconColor' => 'text-sky-200'],
    3 => ['title' => '超级会员', 'sub' => '全功能解锁，专属特权', 'grad' => 'from-amber-400 to-orange-500', 'light' => 'from-amber-50 to-orange-50', 'border' => 'border-amber-200', 'badge' => 'bg-amber-100 text-amber-700', 'btn' => 'bg-amber-500 hover:bg-amber-600', 'price' => 'text-amber-600', 'ring' => 'hover:ring-amber-300', 'icon' => 'crown', 'iconColor' => 'text-yellow-200'],
][$lid] ?? null;
$cfg = $cfg ?? ['title' => (string) ($level['name'] ?? '会员'), 'sub' => '', 'grad' => 'from-slate-500 to-slate-600', 'light' => 'from-slate-50 to-slate-50', 'border' => 'border-slate-200', 'badge' => 'bg-slate-100 text-slate-700', 'btn' => 'bg-slate-600 hover:bg-slate-700', 'price' => 'text-slate-600', 'ring' => 'hover:ring-slate-300', 'icon' => 'star', 'iconColor' => 'text-slate-200'];

function lv_icon(string $type, string $cls): string {
    if ($type === 'crown') return '<svg class="' . $cls . '" viewBox="0 0 24 24" fill="currentColor"><path d="M5 16L3 6l5.5 4L12 4l3.5 6L21 6l-2 10H5zm0 2h14v2H5v-2z"/></svg>';
    if ($type === 'diamond') return '<svg class="' . $cls . '" viewBox="0 0 24 24" fill="currentColor"><path d="M6 2h12l4 6-10 14L2 8l4-6zm.6 2L4.3 7.5h4.2L10 4H6.6zm6.8 0l1.5 3.5h4.2L16.4 4h-3zM8.4 9.5L12 18l3.6-8.5H8.4z"/></svg>';
    return '<svg class="' . $cls . '" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.9 6.1 6.6.8-4.9 4.6 1.3 6.5L12 17.3 6.1 20.6l1.3-6.5L2.5 8.9l6.6-.8L12 2z"/></svg>';
}

$num = static fn($v) => (int) $v >= 999999 ? '不限' : (string) (int) $v;
$benefits = [
    ['名单人数', $num($level['max_members'] ?? 0) . ' 人'],
    ['任务有效天数', $num($level['max_task_days'] ?? 0) . ' 天'],
    ['同时进行任务', $num($level['max_active_tasks'] ?? 0) . ' 个'],
    ['用户组数量', (int) ($level['max_groups'] ?? 0) > 0 ? $num($level['max_groups']) . ' 个' : '不支持'],
    ['问卷功能', (int) ($level['allow_survey'] ?? 0) === 1 ? '支持' : '不支持'],
    ['留言板', (int) ($level['allow_message_board'] ?? 0) === 1 ? '支持' : '不支持'],
    ['首页公告', (int) ($level['allow_announcement'] ?? 0) === 1 ? '支持' : '不支持'],
    ['自定义网站标题', (int) ($level['allow_custom_title'] ?? 0) === 1 ? '支持' : '不支持'],
    ['发展下级', (int) ($level['allow_sub_register'] ?? 0) === 1 ? '支持' : '不支持'],
];
?>
<div class="mx-auto w-full max-w-3xl space-y-5 pb-10">
    <?php if (!empty($flash['message'])): ?>
        <div class="rounded-xl border px-4 py-3 text-sm <?= ($flash['type'] ?? '') === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-rose-200 bg-rose-50 text-rose-700' ?>"><?= h((string) $flash['message']) ?></div>
    <?php endif; ?>

    <a href="/shop" class="inline-flex items-center gap-1 text-sm text-blue-600 hover:underline">← 返回商城</a>

    <!-- 等级 banner -->
    <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br <?= $cfg['grad'] ?> p-6 text-white shadow-lg">
        <div class="pointer-events-none absolute -right-8 -top-8 h-32 w-32 rounded-full bg-white/10"></div>
        <div class="pointer-events-none absolute -bottom-6 left-1/3 h-20 w-20 rounded-full bg-white/10"></div>
        <div class="relative flex items-center gap-4">
            <?= lv_icon($cfg['icon'], 'h-12 w-12 ' . $cfg['iconColor']) ?>
            <div>
                <div class="text-xl font-bold"><?= h((string) ($level['name'] ?? $cfg['title'])) ?></div>
                <div class="text-sm text-white/80"><?= h($cfg['sub']) ?></div>
            </div>
            <?php if ($curLevelId === $lid): ?>
                <span class="ml-auto rounded-full bg-white/20 px-3 py-1 text-xs font-semibold backdrop-blur">当前等级</span>
            <?php endif; ?>
        </div>
    </div>

    <!-- 权益清单 -->
    <div class="overflow-hidden rounded-2xl border <?= $cfg['border'] ?> bg-white shadow-sm">
        <div class="border-b border-slate-100 bg-gradient-to-r <?= $cfg['light'] ?> px-5 py-3">
            <h2 class="text-base font-bold text-slate-800">会员权益</h2>
        </div>
        <ul class="divide-y divide-slate-50">
            <?php foreach ($benefits as [$name, $val]): ?>
                <li class="flex items-center justify-between px-5 py-3 text-sm">
                    <span class="text-slate-600"><?= h($name) ?></span>
                    <span class="font-semibold <?= $val === '不支持' ? 'text-slate-400' : $cfg['price'] ?>"><?= h($val) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <!-- 套餐购买 -->
    <div class="overflow-hidden rounded-2xl border <?= $cfg['border'] ?> bg-gradient-to-br <?= $cfg['light'] ?> p-5 shadow-sm">
        <h2 class="mb-4 text-base font-bold text-slate-800">选择套餐</h2>
        <?php if (empty($plans)): ?>
            <p class="py-4 text-center text-sm text-slate-400">暂无套餐</p>
        <?php else: ?>
        <div class="grid gap-3 sm:grid-cols-3">
            <?php foreach ($plans as $plan):
                $isFree = (int) $plan['price_fen'] === 0;
                $days = (int) $plan['days'];
                $durLabel = $days % 365 === 0 ? ($days / 365) . '年' : ($days % 30 === 0 ? ($days / 30) . '个月' : $days . '天');
            ?>
            <form method="post" action="/shop/order">
                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                <input type="hidden" name="plan_id" value="<?= (int) $plan['id'] ?>">
                <button type="submit" class="group relative w-full overflow-hidden rounded-xl border-2 border-white bg-white p-4 text-center shadow-sm ring-2 ring-transparent transition hover:-translate-y-0.5 hover:shadow-lg <?= $cfg['ring'] ?> active:scale-[.98]">
                    <div class="inline-flex items-center gap-1 rounded-full <?= $cfg['badge'] ?> px-2.5 py-0.5 text-[11px] font-semibold"><?= h($durLabel) ?></div>
                    <div class="mt-2 flex items-baseline justify-center gap-0.5 <?= $cfg['price'] ?>">
                        <?php if ($isFree): ?>
                            <span class="text-2xl font-extrabold">免费</span>
                        <?php else: ?>
                            <span class="text-sm font-bold">¥</span><span class="text-2xl font-extrabold"><?= number_format((int) $plan['price_fen'] / 10, 2) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="mt-3 rounded-lg <?= $cfg['btn'] ?> py-2 text-xs font-bold text-white shadow-sm">立即购买</div>
                </button>
            </form>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
