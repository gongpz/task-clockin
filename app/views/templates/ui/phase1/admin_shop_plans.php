<?php
$plans = is_array($plans ?? null) ? $plans : [];
$transferInfo = (string) ($transferInfo ?? '');
$levels = is_array($levels ?? null) ? $levels : [];
$trialLevelId = (int) ($trialLevelId ?? 2);
$trialDays = (int) ($trialDays ?? 3);
$csrf = (string) ($csrf ?? '');
$flash = is_array($flash ?? null) ? $flash : null;
?>
<div class="admin-content">
    <h1 class="text-xl font-semibold text-slate-800">套餐管理</h1>
    <p class="mt-1 text-sm text-slate-500">修改套餐名称、天数、价格；取消勾选"上架"则前台不显示。</p>
    <?php if (!empty($flash['message'])): ?>
        <div class="mt-3 rounded-xl border px-4 py-3 text-sm <?= ($flash['type'] ?? '') === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-rose-200 bg-rose-50 text-rose-700' ?>"><?= h((string) $flash['message']) ?></div>
    <?php endif; ?>

    <form method="post" action="/admin/shop-plans" class="mt-4 space-y-6">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">

        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">套餐名称</th>
                        <th class="px-4 py-3">等级</th>
                        <th class="px-4 py-3">天数</th>
                        <th class="px-4 py-3">价格（分）</th>
                        <th class="px-4 py-3">显示价格</th>
                        <th class="px-4 py-3">推荐商品</th>
                        <th class="px-4 py-3">体验会员</th>
                        <th class="px-4 py-3">上架</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($plans as $i => $p): ?>
                        <input type="hidden" name="id[<?= $i ?>]" value="<?= (int)$p['id'] ?>">
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-2"><input type="text" name="name[<?= $i ?>]" value="<?= h((string)$p['name']) ?>" class="w-full rounded-lg border border-slate-300 px-2 py-1 text-sm"></td>
                            <td class="px-4 py-2 text-slate-500">
                                <?php if ((int) ($p['is_trial_member'] ?? 0) === 1): ?>
                                    <span class="text-emerald-600 font-medium"><?= h(plan_effective_level_name($p)) ?></span>
                                    <span class="ml-1 text-[11px] text-slate-400">(随体验设置)</span>
                                <?php else: ?>
                                    <?= h((string)$p['level_name']) ?>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-2"><input type="number" name="days[<?= $i ?>]" min="1" value="<?= (int)$p['days'] ?>" class="w-20 rounded-lg border border-slate-300 px-2 py-1 text-sm"></td>
                            <td class="px-4 py-2"><input type="number" name="price_fen[<?= $i ?>]" min="0" value="<?= (int)$p['price_fen'] ?>" class="w-24 rounded-lg border border-slate-300 px-2 py-1 text-sm"></td>
                            <td class="px-4 py-2 text-blue-600 font-semibold">¥<?= number_format((int)$p['price_fen']/10,2) ?></td>
                            <td class="px-4 py-2"><input type="checkbox" name="is_trial[<?= $i ?>]" value="1" <?= (int)($p['is_trial'] ?? 0) ? 'checked' : '' ?> class="h-4 w-4 rounded border-slate-300 text-emerald-600" title="勾选后在商城页归入「推荐商品」分区"></td>
                            <td class="px-4 py-2"><input type="checkbox" name="is_trial_member[<?= $i ?>]" value="1" <?= (int)($p['is_trial_member'] ?? 0) ? 'checked' : '' ?> class="h-4 w-4 rounded border-slate-300 text-amber-600" title="勾选后该套餐授予等级随「体验会员设置」变化，并从等级介绍页隐藏"></td>
                            <td class="px-4 py-2"><input type="checkbox" name="is_active[<?= $i ?>]" value="1" <?= (int)$p['is_active'] ? 'checked' : '' ?> class="h-4 w-4 rounded border-slate-300 text-blue-600"></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="grid gap-6 sm:grid-cols-2">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm space-y-3">
                <h2 class="text-sm font-semibold text-slate-800">体验会员设置</h2>
                <p class="text-xs text-slate-500">体验套餐授予以下等级；有效天数以套餐表中的天数为准。</p>
                <label class="block text-sm text-slate-600">体验等级
                    <select name="trial_level_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <?php foreach ($levels as $lv): if ((int) $lv['id'] < 2) continue; ?>
                            <option value="<?= (int) $lv['id'] ?>" <?= $trialLevelId === (int) $lv['id'] ? 'selected' : '' ?>><?= h((string) $lv['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm space-y-2">
                <label class="block text-sm font-semibold text-slate-700">购买提示：</label>
                <textarea name="transfer_info" rows="5" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><?= h($transferInfo) ?></textarea>
            </div>
        </div>

        <button type="submit" class="btn-primary px-6">保存所有更改</button>
    </form>
</div>
