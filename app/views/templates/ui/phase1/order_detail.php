<?php
$order = is_array($order ?? null) ? $order : [];
$csrf = (string) ($csrf ?? '');
$flash = is_array($flash ?? null) ? $flash : null;
$userBalance = (int) ($userBalance ?? 0);
$transferInfo = trim((string) ($transferInfo ?? ''));
$status = (string) ($order['status'] ?? '');
$statusLabel = ['pending' => '待支付', 'paid' => '已完成', 'cancelled' => '已取消'][$status] ?? $status;
$statusColor = ['pending' => 'text-amber-600', 'paid' => 'text-emerald-600', 'cancelled' => 'text-slate-400'][$status] ?? '';
$amountFen = (int) ($order['amount_fen'] ?? 0);
$canPayBalance = $status === 'pending' && $userBalance >= $amountFen;
?>
<div class="mx-auto w-full max-w-lg space-y-4 pb-10">
    <?php if (!empty($flash['message'])): ?>
        <div class="rounded-xl border px-4 py-3 text-sm <?= ($flash['type'] ?? '') === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-rose-200 bg-rose-50 text-rose-700' ?>"><?= h((string) $flash['message']) ?></div>
    <?php endif; ?>

    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-3">
        <div class="flex items-center justify-between">
            <h1 class="text-lg font-bold text-slate-900">订单详情</h1>
            <span class="text-sm font-semibold <?= $statusColor ?>"><?= h($statusLabel) ?></span>
        </div>
        <div class="divide-y divide-slate-100 text-sm">
            <div class="flex justify-between py-2"><span class="text-slate-500">订单号</span><span class="font-mono text-slate-700"><?= h((string)$order['order_no']) ?></span></div>
            <div class="flex justify-between py-2"><span class="text-slate-500">套餐</span><span class="text-slate-700"><?= h((string)$order['plan_name']) ?></span></div>
            <div class="flex justify-between py-2"><span class="text-slate-500">有效天数</span><span class="text-slate-700"><?= (int)$order['days'] ?> 天</span></div>
            <div class="flex justify-between py-2"><span class="text-slate-500">金额</span><span class="text-xl font-bold text-blue-600">¥<?= number_format($amountFen / 10, 2) ?></span></div>
            <div class="flex justify-between py-2"><span class="text-slate-500">下单时间</span><span class="text-slate-700"><?= h((string)$order['created_at']) ?></span></div>
        </div>
    </div>

    <?php if ($status === 'pending'): ?>

    <?php if ($transferInfo !== ''): ?>
    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
        <div class="mb-1 font-semibold">购买提示</div>
        <div class="whitespace-pre-line leading-relaxed"><?= h($transferInfo) ?></div>
    </div>
    <?php endif; ?>

    <!-- 卡密兑换 -->
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-3">
        <div class="flex items-center gap-2">
            <svg class="h-5 w-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
            <h2 class="text-sm font-semibold text-slate-800">卡密兑换</h2>
        </div>
        <p class="text-xs text-slate-500">请输入与本套餐匹配的卡密，兑换后立即开通会员。</p>
        <form method="post" action="/shop/order/<?= (int)$order['id'] ?>/redeem" class="flex gap-2">
            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
            <input type="text" name="card_code" placeholder="输入16位卡密" maxlength="32" class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm font-mono uppercase tracking-widest" autocomplete="off">
            <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">兑换</button>
        </form>
    </div>

    <!-- 余额支付 -->
    <div class="rounded-2xl border <?= $canPayBalance ? 'border-emerald-200 bg-emerald-50' : 'border-slate-200 bg-slate-50' ?> p-5 shadow-sm space-y-3">
        <div class="flex items-center gap-2">
            <svg class="h-5 w-5 <?= $canPayBalance ? 'text-emerald-500' : 'text-slate-400' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
            <h2 class="text-sm font-semibold <?= $canPayBalance ? 'text-emerald-800' : 'text-slate-500' ?>">余额支付</h2>
            <span class="ml-auto text-xs <?= $canPayBalance ? 'text-emerald-700' : 'text-slate-400' ?>">当前余额 ¥<?= number_format($userBalance / 10, 2) ?></span>
        </div>
        <?php if ($canPayBalance): ?>
            <form method="post" action="/shop/order/<?= (int)$order['id'] ?>/pay-balance">
                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                <button type="submit" class="w-full rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">余额支付 ¥<?= number_format($amountFen / 10, 2) ?></button>
            </form>
        <?php else: ?>
            <p class="text-xs text-slate-400">余额不足（需 ¥<?= number_format($amountFen / 10, 2) ?>），可在<a href="/my" class="text-blue-500 hover:underline">个人中心</a>用积分兑换余额。</p>
        <?php endif; ?>
    </div>

    <?php elseif ($status === 'paid'): ?>
    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 text-center space-y-1">
        <div class="text-2xl">🎉</div>
        <div class="text-sm font-semibold text-emerald-700">会员已开通，感谢您的支持！</div>
        <?php if (!empty($order['admin_note'])): ?>
            <div class="text-xs text-emerald-600"><?= h((string)$order['admin_note']) ?></div>
        <?php endif; ?>
    </div>
    <?php elseif ($status === 'cancelled'): ?>
    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-500 text-center">订单已取消</div>
    <?php endif; ?>

    <div class="text-center"><a href="/shop" class="text-sm text-blue-600 hover:underline">← 返回商城</a></div>
</div>
