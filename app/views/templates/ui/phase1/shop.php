<?php
$plans = is_array($plans ?? null) ? $plans : [];
$levels = is_array($levels ?? null) ? $levels : [];
$csrf = (string) ($csrf ?? '');
$flash = is_array($flash ?? null) ? $flash : null;
$membership = is_array($membership ?? null) ? $membership : [];
$userPoints = (int) ($userPoints ?? 0);
$userBalance = (int) ($userBalance ?? 0);
$curLevelId = (int) ($membership['id'] ?? 1);
$curName = (string) ($membership['name'] ?? '普通会员');
$expireAt = (string) ($membership['expire_at'] ?? '');
$expired = $expireAt !== '' && strtotime($expireAt) < time();

// 按分区分组：体验套餐归体验区(1)，其余按等级
$groups = [];
foreach ($plans as $p) {
    $g = (int) ($p['is_trial'] ?? 0) === 1 ? 1 : (int) $p['level_id'];
    $groups[$g][] = $p;
}

// 等级视觉配置 + 彩色图标（体验=星 / 高级=钻石 / 超级=皇冠）
$levelCfg = [
    1 => ['title' => '普通会员', 'sub' => '基础功能免费体验', 'grad' => 'from-rose-500 to-pink-600', 'light' => 'from-rose-50 to-pink-50', 'border' => 'border-rose-200', 'badge' => 'bg-rose-100 text-rose-700', 'btn' => 'bg-rose-600 hover:bg-rose-700', 'price' => 'text-rose-600', 'ring' => 'hover:ring-rose-300', 'icon' => 'star', 'iconColor' => 'text-rose-200'],
    2 => ['title' => '高级会员', 'sub' => '解锁更多功能', 'grad' => 'from-blue-500 to-indigo-600', 'light' => 'from-blue-50 to-indigo-50', 'border' => 'border-blue-200', 'badge' => 'bg-blue-100 text-blue-700', 'btn' => 'bg-blue-600 hover:bg-blue-700', 'price' => 'text-blue-600', 'ring' => 'hover:ring-blue-300', 'icon' => 'diamond', 'iconColor' => 'text-sky-300'],
    3 => ['title' => '超级会员', 'sub' => '全功能解锁，专属特权', 'grad' => 'from-amber-400 to-orange-500', 'light' => 'from-amber-50 to-orange-50', 'border' => 'border-amber-200', 'badge' => 'bg-amber-100 text-amber-700', 'btn' => 'bg-amber-500 hover:bg-amber-600', 'price' => 'text-amber-600', 'ring' => 'hover:ring-amber-300', 'icon' => 'crown', 'iconColor' => 'text-yellow-200'],
];

// 彩色图标渲染（star/diamond/crown），$cls 控制尺寸/颜色
function shop_level_icon(string $type, string $cls): string {
    if ($type === 'crown') {
        return '<svg class="' . $cls . '" viewBox="0 0 24 24" fill="currentColor"><path d="M5 16L3 6l5.5 4L12 4l3.5 6L21 6l-2 10H5zm0 2h14v2H5v-2z"/></svg>';
    }
    if ($type === 'diamond') {
        return '<svg class="' . $cls . '" viewBox="0 0 24 24" fill="currentColor"><path d="M6 2h12l4 6-10 14L2 8l4-6zm.6 2L4.3 7.5h4.2L10 4H6.6zm6.8 0l1.5 3.5h4.2L16.4 4h-3zM8.4 9.5L12 18l3.6-8.5H8.4z"/></svg>';
    }
    return '<svg class="' . $cls . '" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.9 6.1 6.6.8-4.9 4.6 1.3 6.5L12 17.3 6.1 20.6l1.3-6.5L2.5 8.9l6.6-.8L12 2z"/></svg>';
}
?>
<style>:root { --public-accent: #2563eb; }</style>
<div class="mx-auto w-full max-w-5xl pb-10">

    <?php if (!empty($flash['message'])): ?>
        <div class="mb-4 rounded-xl border px-4 py-3 text-sm <?= ($flash['type'] ?? '') === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-rose-200 bg-rose-50 text-rose-700' ?>"><?= h((string) $flash['message']) ?></div>
    <?php endif; ?>

    <div class="grid gap-5 lg:grid-cols-[300px_1fr]">

        <!-- 左栏 -->
        <div class="space-y-4">
            <?php $lc = $levelCfg[$curLevelId] ?? $levelCfg[1]; ?>
            <!-- 会员状态卡 -->
            <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br <?= $lc['grad'] ?> p-5 text-white shadow-lg">
                <div class="pointer-events-none absolute -right-6 -top-6 h-28 w-28 rounded-full bg-white/10"></div>
                <div class="pointer-events-none absolute -bottom-8 -left-4 h-24 w-24 rounded-full bg-white/10"></div>
                <div class="relative">
                    <div class="flex items-center gap-3">
                        <?= shop_level_icon($lc['icon'], 'h-9 w-9 ' . $lc['iconColor']) ?>
                        <div>
                            <div class="text-xs font-medium text-white/70">当前等级</div>
                            <div class="text-lg font-bold"><?= h($curName) ?></div>
                        </div>
                    </div>
                    <div class="mt-3 text-xs text-white/80">
                        <?php if ($expireAt === ''): ?>
                            永久有效
                        <?php elseif ($expired): ?>
                            <span class="font-semibold text-rose-200">已过期</span>
                        <?php else: ?>
                            有效期至 <?= h(substr($expireAt, 0, 10)) ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- 卡密兑换卡 -->
            <div class="relative overflow-hidden rounded-2xl border border-blue-100 bg-gradient-to-br from-blue-50 to-indigo-50/50 p-4 shadow-sm">
                <div class="pointer-events-none absolute -right-5 -top-5 h-16 w-16 rounded-full bg-blue-100/50"></div>
                <div class="relative">
                    <div class="mb-2 flex items-center gap-2">
                        <svg class="h-4 w-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                        <span class="text-sm font-semibold text-slate-800">卡密兑换</span>
                    </div>
                    <p class="mb-2 text-xs text-slate-500">输入卡密后点兑换，验证通过立即开通。</p>
                    <form method="post" action="/shop/redeem" class="space-y-2">
                        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                        <input type="text" name="card_code" placeholder="输入卡密" maxlength="32" autocomplete="off" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-mono uppercase tracking-widest outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200">
                        <button type="submit" class="w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">兑换</button>
                    </form>
                </div>
            </div>

            <!-- 积分余额卡 -->
            <div class="relative overflow-hidden rounded-2xl border border-amber-100 bg-gradient-to-br from-amber-50/70 to-orange-50/40 p-4 shadow-sm">
                <div class="pointer-events-none absolute -left-5 -bottom-5 h-20 w-20 rounded-full bg-amber-100/50"></div>
                <div class="relative">
                    <div class="mb-3 flex items-center gap-2">
                        <svg class="h-4 w-4 text-amber-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        <span class="text-sm font-semibold text-slate-800">积分 &amp; 余额</span>
                    </div>
                    <div class="grid grid-cols-2 gap-2 text-center">
                        <div class="rounded-xl bg-amber-50 px-2 py-3"><div class="text-xl font-bold text-amber-600"><?= $userPoints ?></div><div class="text-xs text-slate-500">积分</div></div>
                        <div class="rounded-xl bg-orange-50 px-2 py-3"><div class="text-xl font-bold text-orange-600">¥<?= number_format($userBalance / 10, 2) ?></div><div class="text-xs text-slate-500">余额</div></div>
                    </div>
                    <a href="/my" class="mt-2 block text-center text-xs text-blue-500 hover:underline">去个人中心用积分换余额 →</a>
                </div>
            </div>
        </div><!-- /左栏 -->

        <!-- 右栏 -->
        <div class="space-y-5">

            <!-- 三个会员展示卡 -->
            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="grid grid-cols-3 gap-3">
                    <?php foreach ([1, 2, 3] as $lid): $c = $levelCfg[$lid]; ?>
                        <a href="/shop/level/<?= $lid ?>" class="group relative flex items-center justify-center gap-2 overflow-hidden rounded-xl bg-gradient-to-br <?= $c['grad'] ?> px-3 py-2.5 text-center text-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg">
                            <span class="pointer-events-none absolute -right-2 -top-2 h-10 w-10 rounded-full bg-white/15"></span>
                            <?= shop_level_icon($c['icon'], 'relative h-5 w-5 ' . $c['iconColor']) ?>
                            <span class="relative text-sm font-bold"><?= h($c['title']) ?></span>
                            <?php if ($curLevelId === $lid): ?>
                                <span class="relative rounded-full bg-white/25 px-1.5 py-0.5 text-[10px] font-semibold backdrop-blur">当前</span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- 套餐购买 -->
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="space-y-4">
                    <?php foreach ([1, 2, 3] as $lid):
                        $c = $levelCfg[$lid];
                        $lp = $groups[$lid] ?? [];
                        $groupTitle = $lid === 1 ? '推荐商品' : $c['title'];
                    ?>
                    <div class="overflow-hidden rounded-2xl border <?= $c['border'] ?>">
                        <div class="flex items-center gap-2 bg-gradient-to-r <?= $c['grad'] ?> px-4 py-2.5 text-white">
                            <?= shop_level_icon($c['icon'], 'h-5 w-5 ' . $c['iconColor']) ?>
                            <span class="text-sm font-bold"><?= h($groupTitle) ?></span>
                        </div>
                        <div class="bg-gradient-to-br <?= $c['light'] ?> p-4">
                            <?php if (empty($lp)): ?>
                                <p class="py-3 text-center text-sm text-slate-400">暂无套餐</p>
                            <?php else: ?>
                            <div class="grid gap-3 sm:grid-cols-3">
                                <?php foreach ($lp as $plan):
                                    $isFree = (int) $plan['price_fen'] === 0;
                                    $days = (int) $plan['days'];
                                    $durLabel = $days % 365 === 0 ? ($days / 365) . '年' : ($days % 30 === 0 ? ($days / 30) . '个月' : $days . '天');
                                    $planLevelName = plan_effective_level_name($plan);
                                    $nc = $levelCfg[plan_effective_level_id($plan)] ?? $c;
                                ?>
                                <form method="post" action="/shop/order">
                                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                    <input type="hidden" name="plan_id" value="<?= (int) $plan['id'] ?>">
                                    <button type="submit" class="group relative w-full overflow-hidden rounded-xl border-2 border-white bg-white p-3.5 text-center shadow-sm ring-2 ring-transparent transition hover:-translate-y-0.5 hover:shadow-lg <?= $c['ring'] ?> active:scale-[.98]">
                                        <div class="pointer-events-none absolute -right-4 -top-4 h-12 w-12 rounded-full bg-gradient-to-br <?= $c['light'] ?> opacity-70"></div>
                                        <div class="relative">
                                            <div class="flex items-center justify-center gap-1.5">
                                                <?php if ($lid === 1): ?>
                                                    <span class="inline-flex items-center gap-1 rounded-full bg-gradient-to-r <?= $nc['grad'] ?> px-2 py-0.5 text-[11px] font-bold text-white shadow-sm"><?= shop_level_icon($nc['icon'], 'h-3 w-3 text-white/90') ?><?= h($planLevelName) ?></span>
                                                <?php endif; ?>
                                                <span class="inline-flex items-center rounded-full <?= $c['badge'] ?> px-2 py-0.5 text-[11px] font-semibold"><?= h($durLabel) ?></span>
                                            </div>
                                            <div class="mt-2 flex items-baseline justify-center gap-0.5 <?= $c['price'] ?>">
                                                <?php if ($isFree): ?>
                                                    <span class="text-xl font-extrabold">免费</span>
                                                <?php else: ?>
                                                    <span class="text-sm font-bold">¥</span><span class="text-xl font-extrabold"><?= number_format((int) $plan['price_fen'] / 10, 2) ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="mt-2.5 rounded-lg <?= $c['btn'] ?> py-1.5 text-xs font-bold text-white shadow-sm">立即购买</div>
                                        </div>
                                    </button>
                                </form>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- 权限对比表（数据库动态渲染） -->
            <?php
            $lvById = [];
            foreach ($levels as $lv) { $lvById[(int) $lv['id']] = $lv; }
            $okSvg = '<svg class="mx-auto h-4 w-4 text-emerald-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd"/></svg>';
            $noSvg = '<svg class="mx-auto h-4 w-4 text-rose-400" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/></svg>';
            $num = static fn($v) => (int) $v >= 999999 ? '不限' : (string) (int) $v;
            $rows = [
                ['名单人数', 'num', 'max_members'],
                ['任务天数', 'num', 'max_task_days'],
                ['同时任务数', 'num', 'max_active_tasks'],
                ['用户组', 'num', 'max_groups'],
                ['问卷功能', 'bool', 'allow_survey'],
                ['留言板', 'bool', 'allow_message_board'],
                ['首页公告', 'bool', 'allow_announcement'],
                ['自定义网站标题', 'bool', 'allow_custom_title'],
                ['发展下级', 'bool', 'allow_sub_register'],
            ];
            ?>
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 bg-slate-50 px-5 py-4">
                    <h2 class="text-base font-bold text-slate-800">权限对比</h2>
                    <p class="mt-0.5 text-xs text-slate-500">各等级功能权限一览</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-100">
                                <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">功能</th>
                                <?php foreach ([1, 2, 3] as $lid): $c = $levelCfg[$lid]; ?>
                                    <th class="px-4 py-3 text-center text-xs font-semibold text-slate-600">
                                        <div class="flex flex-col items-center gap-1">
                                            <?= shop_level_icon($c['icon'], 'h-4 w-4 ' . str_replace(['300', '200'], '500', $c['iconColor'])) ?>
                                            <?= h(str_replace('会员', '', $c['title'])) ?>
                                        </div>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <?php foreach ($rows as [$label, $type, $field]): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="px-5 py-3 text-sm text-slate-700"><?= h($label) ?></td>
                                <?php foreach ([1, 2, 3] as $lid): $lv = $lvById[$lid] ?? []; $val = $lv[$field] ?? 0; ?>
                                    <td class="px-4 py-3 text-center">
                                        <?php if ($type === 'bool'): ?>
                                            <?= (int) $val === 1 ? $okSvg : $noSvg ?>
                                        <?php else: $disp = $num($val); ?>
                                            <?php if ($disp === '0'): ?><?= $noSvg ?><?php else: ?><span class="text-sm font-semibold text-slate-700"><?= h($disp) ?></span><?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <p class="text-center text-xs text-slate-400">选择套餐后按提示完成转账，管理员核实后开通；卡密兑换即时生效。</p>

        </div><!-- /右栏 -->
    </div>
</div>

