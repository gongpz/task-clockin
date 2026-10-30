<?php
$batches = is_array($batches ?? null) ? $batches : [];
$viewBatch = (string) ($viewBatch ?? '');
$batchKeys = is_array($batchKeys ?? null) ? $batchKeys : [];
$plans = is_array($plans ?? null) ? $plans : [];
$csrf = (string) ($csrf ?? '');
$flash = is_array($flash ?? null) ? $flash : null;
$lookupCode = (string) ($lookupCode ?? '');
$lookupResult = is_array($lookupResult ?? null) ? $lookupResult : null;
$statusLabel = ['unused' => '未使用', 'used' => '已使用'];
$statusColor = ['unused' => 'text-emerald-600', 'used' => 'text-slate-400'];
$durLabel = static function (int $days): string {
    if ($days <= 0) return '—';
    if ($days % 365 === 0) return ($days / 365) . '年';
    if ($days % 30 === 0) return ($days / 30) . '个月';
    return $days . '天';
};
$fmtBatch = static function (string $b): string {
    // 批次号 ymdHis + Pxx[I] → 可读时间
    if (preg_match('/^(\d{12})/', $b, $m)) {
        $t = $m[1];
        return '20' . substr($t,0,2) . '-' . substr($t,2,2) . '-' . substr($t,4,2) . ' ' . substr($t,6,2) . ':' . substr($t,8,2) . ':' . substr($t,10,2);
    }
    return $b;
};
?>
<div class="admin-content space-y-5">
    <h1 class="text-xl font-semibold text-slate-800">卡密管理</h1>

    <?php if (!empty($flash['message'])): ?>
        <div class="rounded-xl border px-4 py-3 text-sm <?= ($flash['type'] ?? '') === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-rose-200 bg-rose-50 text-rose-700' ?>"><?= h((string) $flash['message']) ?></div>
    <?php endif; ?>

    <div class="grid gap-4 xl:grid-cols-3">

        <!-- 生成卡密 -->
        <form method="post" action="/admin/card-keys/generate" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm space-y-3">
            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
            <h2 class="text-sm font-semibold text-slate-800">批量生成卡密</h2>
            <label class="block text-xs font-medium text-slate-600">绑定套餐
                <select name="plan_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <?php foreach ($plans as $p): ?>
                        <option value="<?= (int)$p['id'] ?>"><?= h((string)$p['name']) ?> — ¥<?= number_format((int)$p['price_fen']/10,2) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="grid grid-cols-2 gap-2">
                <label class="block text-xs font-medium text-slate-600">数量（1-500）
                    <input type="number" name="count" min="1" max="500" value="10" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </label>
                <label class="block text-xs font-medium text-slate-600">长度（4-32）
                    <input type="number" name="length" min="4" max="32" value="16" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </label>
            </div>
            <label class="block text-xs font-medium text-slate-600">字符集
                <select name="charset" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="alphanum">字母+数字（推荐）</option>
                    <option value="numeric">纯数字</option>
                    <option value="alpha">纯字母</option>
                    <option value="full">字母+数字+符号</option>
                </select>
            </label>
            <button type="submit" class="w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">生成</button>
        </form>

        <!-- 导入卡密 -->
        <form method="post" action="/admin/card-keys/import" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm space-y-3">
            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
            <h2 class="text-sm font-semibold text-slate-800">导入已有卡密</h2>
            <label class="block text-xs font-medium text-slate-600">绑定套餐
                <select name="plan_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <?php foreach ($plans as $p): ?>
                        <option value="<?= (int)$p['id'] ?>"><?= h((string)$p['name']) ?> — ¥<?= number_format((int)$p['price_fen']/10,2) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="block text-xs font-medium text-slate-600">卡密列表（每行一个，或逗号/分号分隔，最多500条）
                <textarea name="codes" rows="6" placeholder="ABC123&#10;DEF456&#10;GHI789" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-mono"></textarea>
            </label>
            <button type="submit" class="w-full rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">导入</button>
        </form>

        <!-- 验证卡密 -->
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm space-y-3">
            <h2 class="text-sm font-semibold text-slate-800">验证卡密</h2>
            <form method="get" action="/admin/card-keys/lookup" class="flex gap-2">
                <input type="text" name="code" value="<?= h($lookupCode) ?>" placeholder="输入卡密查询" class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm font-mono uppercase" autocomplete="off">
                <button type="submit" class="rounded-lg bg-slate-700 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">查询</button>
            </form>
            <?php if ($lookupCode !== ''): ?>
                <?php if ($lookupResult): ?>
                    <?php $st = (string)$lookupResult['status']; ?>
                    <div class="rounded-lg border <?= $st === 'unused' ? 'border-emerald-200 bg-emerald-50' : 'border-slate-200 bg-slate-50' ?> p-4 text-sm space-y-1">
                        <div class="flex items-center gap-2">
                            <span class="font-mono font-semibold text-slate-800"><?= h((string)$lookupResult['code']) ?></span>
                            <span class="rounded-full px-2 py-0.5 text-xs font-semibold <?= $st === 'unused' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' ?>"><?= $statusLabel[$st] ?? $st ?></span>
                        </div>
                        <div class="text-xs text-slate-500">套餐：<?= h((string)$lookupResult['plan_name']) ?></div>
                        <div class="text-xs text-slate-500">生成时间：<?= h(substr((string)$lookupResult['created_at'],0,16)) ?></div>
                        <?php if ($st === 'used'): ?>
                            <div class="text-xs text-slate-500">使用者 UID：<?= (int)$lookupResult['used_by'] ?></div>
                            <div class="text-xs text-slate-500">使用时间：<?= h(substr((string)$lookupResult['used_at'],0,16)) ?></div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">未找到卡密「<?= h($lookupCode) ?>」</div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

    </div>

    <?php if ($viewBatch !== ''): ?>
    <!-- 批次详情 -->
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-4 py-3">
            <div class="text-sm font-semibold text-slate-800">批次 <?= h($fmtBatch($viewBatch)) ?> · 共 <?= count($batchKeys) ?> 张</div>
            <div class="flex gap-2">
                <a href="/admin/card-keys/export?batch=<?= rawurlencode($viewBatch) ?>" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">导出TXT</a>
                <a href="/admin/card-keys" class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-200">返回批次</a>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                    <tr><th class="px-4 py-3">#</th><th class="px-4 py-3">卡密</th><th class="px-4 py-3">套餐</th><th class="px-4 py-3">会员时间</th><th class="px-4 py-3">状态</th><th class="px-4 py-3">使用者</th><th class="px-4 py-3">使用时间</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($batchKeys as $idx => $k): $st = (string)$k['status']; ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 text-xs text-slate-400"><?= $idx + 1 ?></td>
                        <td class="px-4 py-3"><span class="font-mono text-xs text-slate-800 select-all"><?= h((string)$k['code']) ?></span></td>
                        <td class="px-4 py-3 text-slate-700"><?= h((string)$k['plan_name']) ?></td>
                        <td class="px-4 py-3 text-slate-600"><?= h($durLabel((int)($k['days'] ?? 0))) ?></td>
                        <td class="px-4 py-3 font-semibold <?= $statusColor[$st] ?? '' ?>"><?= $statusLabel[$st] ?? $st ?></td>
                        <td class="px-4 py-3 text-slate-500"><?= $k['used_by'] ? '#' . (int)$k['used_by'] : '—' ?></td>
                        <td class="px-4 py-3 text-xs text-slate-400"><?= $k['used_at'] ? h(substr((string)$k['used_at'],0,16)) : '—' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php else: ?>
    <!-- 批次列表 -->
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">批次时间</th>
                    <th class="px-4 py-3">套餐</th>
                    <th class="px-4 py-3">会员时间</th>
                    <th class="px-4 py-3">总数</th>
                    <th class="px-4 py-3">未使用</th>
                    <th class="px-4 py-3">已使用</th>
                    <th class="px-4 py-3">操作</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($batches)): ?>
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-400">暂无卡密批次</td></tr>
                <?php else: foreach ($batches as $b): ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 text-slate-700"><?= h($fmtBatch((string)$b['batch_no'])) ?></td>
                        <td class="px-4 py-3 text-slate-700"><?= h((string)$b['plan_name']) ?></td>
                        <td class="px-4 py-3 text-slate-600"><?= h($durLabel((int)($b['days'] ?? 0))) ?></td>
                        <td class="px-4 py-3 font-semibold text-slate-800"><?= (int)$b['total'] ?></td>
                        <td class="px-4 py-3 text-emerald-600"><?= (int)$b['unused'] ?></td>
                        <td class="px-4 py-3 text-slate-400"><?= (int)$b['used'] ?></td>
                        <td class="px-4 py-3">
                            <a href="/admin/card-keys?batch=<?= rawurlencode((string)$b['batch_no']) ?>" class="text-xs text-blue-600 hover:underline">查看</a>
                            <a href="/admin/card-keys/export?batch=<?= rawurlencode((string)$b['batch_no']) ?>" class="ml-3 text-xs text-emerald-600 hover:underline">导出TXT</a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <p class="text-xs text-slate-400">共 <?= count($batches) ?> 个批次（最多显示200个）</p>
    <?php endif; ?>
</div>
