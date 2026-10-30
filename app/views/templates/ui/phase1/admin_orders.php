<?php
$orders = is_array($orders ?? null) ? $orders : [];
$filters = is_array($filters ?? null) ? $filters : [];
$csrf = (string) ($csrf ?? '');
$flash = is_array($flash ?? null) ? $flash : null;
$statusLabel = ['pending' => '待支付', 'confirming' => '待确认', 'paid' => '已完成', 'cancelled' => '已取消'];
$statusColor = ['pending' => 'text-amber-600', 'confirming' => 'text-blue-600', 'paid' => 'text-emerald-600', 'cancelled' => 'text-slate-400'];
$filterStatus = ['confirming' => '待确认', 'paid' => '已完成', 'cancelled' => '已取消'];
?>
<div class="admin-content">
    <h1 class="text-xl font-semibold text-slate-800">订单管理</h1>
    <?php if (!empty($flash['message'])): ?>
        <div class="mt-3 rounded-xl border px-4 py-3 text-sm <?= ($flash['type'] ?? '') === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-rose-200 bg-rose-50 text-rose-700' ?>"><?= h((string) $flash['message']) ?></div>
    <?php endif; ?>

    <form method="get" action="/admin/orders" class="mt-4 flex flex-wrap gap-2">
        <input type="text" name="keyword" value="<?= h((string)($filters['keyword'] ?? '')) ?>" placeholder="姓名/手机/订单号" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm w-48">
        <select name="status" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm">
            <option value="">全部状态</option>
            <?php foreach ($filterStatus as $v => $l): ?>
                <option value="<?= $v ?>" <?= ($filters['status'] ?? '') === $v ? 'selected' : '' ?>><?= $l ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn-primary px-4 py-1.5 text-sm">筛选</button>
    </form>

    <div class="mt-4 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">订单号</th>
                    <th class="px-4 py-3">用户</th>
                    <th class="px-4 py-3">套餐</th>
                    <th class="px-4 py-3">金额</th>
                    <th class="px-4 py-3">状态</th>
                    <th class="px-4 py-3">下单时间</th>
                    <th class="px-4 py-3">操作</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($orders)): ?>
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-400">暂无订单</td></tr>
                <?php else: foreach ($orders as $o): $st = (string)$o['status']; ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-mono text-xs text-slate-600"><?= h((string)$o['order_no']) ?></td>
                        <td class="px-4 py-3">
                            <div class="font-medium text-slate-800"><?= h((string)$o['user_name']) ?></div>
                            <div class="text-xs text-slate-400"><?= h((string)$o['user_mobile']) ?></div>
                        </td>
                        <td class="px-4 py-3 text-slate-700"><?= h((string)$o['plan_name']) ?></td>
                        <td class="px-4 py-3 font-semibold text-blue-600">¥<?= number_format((int)$o['amount_fen']/10,2) ?></td>
                        <td class="px-4 py-3 font-semibold <?= $statusColor[$st] ?? '' ?>"><?= $statusLabel[$st] ?? $st ?></td>
                        <td class="px-4 py-3 text-xs text-slate-500"><?= h(substr((string)$o['created_at'],0,16)) ?></td>
                        <td class="px-4 py-3">
                            <?php if ($st === 'pending' || $st === 'confirming'): ?>
                                <button type="button" class="order-confirm-btn text-xs text-emerald-600 hover:underline mr-2" data-id="<?= (int)$o['id'] ?>" data-name="<?= h((string)$o['user_name']) ?>" data-plan="<?= h((string)$o['plan_name']) ?>">确认到账</button>
                                <button type="button" class="order-cancel-btn text-xs text-rose-500 hover:underline" data-id="<?= (int)$o['id'] ?>">取消</button>
                            <?php elseif (!empty($o['transfer_note'])): ?>
                                <span class="text-xs text-slate-400">备注：<?= h((string)$o['transfer_note']) ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- 确认到账弹窗 -->
<div id="confirm-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.4);backdrop-filter:blur(4px);align-items:center;justify-content:center;padding:16px;">
    <div style="width:100%;max-width:360px;background:#fff;border-radius:16px;padding:24px;">
        <h3 id="confirm-modal-title" style="font-size:16px;font-weight:700;color:#1e293b;margin-bottom:16px;">确认到账</h3>
        <form method="post" action="/admin/orders/confirm">
            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
            <input type="hidden" name="id" id="confirm-order-id" value="">
            <label style="display:block;font-size:13px;color:#475569;margin-bottom:4px;">备注（选填）</label>
            <input type="text" name="admin_note" placeholder="如：已核实转账截图" style="width:100%;padding:8px 12px;border:1px solid #e2e8f0;border-radius:10px;font-size:14px;margin-bottom:12px;">
            <button type="submit" style="width:100%;padding:10px;background:#059669;color:#fff;border:none;border-radius:10px;font-size:14px;font-weight:600;cursor:pointer;">确认到账并升级会员</button>
        </form>
        <button id="confirm-modal-close" type="button" style="margin-top:8px;width:100%;padding:8px;background:#f1f5f9;border:none;border-radius:10px;font-size:14px;cursor:pointer;">取消</button>
    </div>
</div>

<!-- 取消订单弹窗 -->
<div id="cancel-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.4);backdrop-filter:blur(4px);align-items:center;justify-content:center;padding:16px;">
    <div style="width:100%;max-width:360px;background:#fff;border-radius:16px;padding:24px;">
        <h3 style="font-size:16px;font-weight:700;color:#1e293b;margin-bottom:16px;">取消订单</h3>
        <form method="post" action="/admin/orders/cancel">
            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
            <input type="hidden" name="id" id="cancel-order-id" value="">
            <label style="display:block;font-size:13px;color:#475569;margin-bottom:4px;">取消原因（选填）</label>
            <input type="text" name="admin_note" style="width:100%;padding:8px 12px;border:1px solid #e2e8f0;border-radius:10px;font-size:14px;margin-bottom:12px;">
            <button type="submit" style="width:100%;padding:10px;background:#dc2626;color:#fff;border:none;border-radius:10px;font-size:14px;font-weight:600;cursor:pointer;">确认取消</button>
        </form>
        <button id="cancel-modal-close" type="button" style="margin-top:8px;width:100%;padding:8px;background:#f1f5f9;border:none;border-radius:10px;font-size:14px;cursor:pointer;">关闭</button>
    </div>
</div>
<script>
(function(){
    var cm = document.getElementById('confirm-modal');
    var xm = document.getElementById('cancel-modal');
    document.querySelectorAll('.order-confirm-btn').forEach(function(b){
        b.addEventListener('click', function(){
            document.getElementById('confirm-order-id').value = b.dataset.id;
            document.getElementById('confirm-modal-title').textContent = '确认「' + b.dataset.name + '」的「' + b.dataset.plan + '」到账';
            cm.style.display = 'flex';
        });
    });
    document.querySelectorAll('.order-cancel-btn').forEach(function(b){
        b.addEventListener('click', function(){
            document.getElementById('cancel-order-id').value = b.dataset.id;
            xm.style.display = 'flex';
        });
    });
    document.getElementById('confirm-modal-close').addEventListener('click', function(){ cm.style.display='none'; });
    document.getElementById('cancel-modal-close').addEventListener('click', function(){ xm.style.display='none'; });
    cm.addEventListener('click', function(e){ if(e.target===cm) cm.style.display='none'; });
    xm.addEventListener('click', function(e){ if(e.target===xm) xm.style.display='none'; });
})();
</script>
