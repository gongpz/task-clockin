<?php
$users = is_array($users ?? null) ? $users : [];
$csrf = (string) ($csrf ?? '');
$filters = is_array($filters ?? null) ? $filters : [];
$newPassword = (string) ($newPassword ?? '');
$allMembers = is_array($allMembers ?? null) ? $allMembers : [];
$levels = is_array($levels ?? null) ? $levels : [];
$levelMap = [];
foreach ($levels as $lv) { $levelMap[(int) $lv['id']] = (string) $lv['name']; }
$flash = is_array($flash ?? null) ? $flash : null;
?>
<div class="admin-content">
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold text-slate-800">用户管理</h1>
    </div>

    <?php if (!empty($flash['message']) && ($flash['type'] ?? '') !== 'info'): ?>
        <div class="mt-3 rounded-xl border px-4 py-3 text-sm <?= ($flash['type'] ?? '') === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-rose-200 bg-rose-50 text-rose-700' ?>"><?= h((string) $flash['message']) ?></div>
    <?php endif; ?>

    <?php if ($newPassword !== ''): ?>
        <div class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
            密码已重置为：<code class="rounded bg-white px-2 py-0.5 font-mono text-blue-900"><?= h($newPassword) ?></code>（请通知用户）
        </div>
    <?php endif; ?>

    <section class="rounded-xl border border-slate-200 bg-white p-5">
        <form method="get" action="/admin/users" class="flex flex-wrap items-end gap-3">
            <label class="text-sm text-slate-600">
                搜索
                <input type="text" name="keyword" value="<?= h((string) ($filters['keyword'] ?? '')) ?>" placeholder="姓名/手机/邮箱" class="mt-1 block rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
            </label>
            <label class="text-sm text-slate-600">
                状态
                <select name="status" class="mt-1 block rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                    <option value="all" <?= ($filters['status'] ?? '') === 'all' ? 'selected' : '' ?>>全部</option>
                    <option value="1" <?= ($filters['status'] ?? '') === '1' ? 'selected' : '' ?>>正常</option>
                    <option value="0" <?= ($filters['status'] ?? '') === '0' ? 'selected' : '' ?>>停用</option>
                </select>
            </label>
            <label class="text-sm text-slate-600">
                绑定
                <select name="bind" class="mt-1 block rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                    <option value="">全部</option>
                    <option value="unbound" <?= ($filters['bind'] ?? '') === 'unbound' ? 'selected' : '' ?>>未绑定</option>
                </select>
            </label>
            <button type="submit" class="btn-primary">筛选</button>
        </form>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white">
        <div class="tw-table-wrap">
            <table class="tw-table w-full text-sm">
                <thead class="border-b border-slate-200 bg-slate-50 text-left text-xs font-semibold text-slate-500">
                    <tr>
                        <th class="px-4 py-3">ID</th>
                        <th class="px-4 py-3">姓名</th>
                        <th class="px-4 py-3">手机号</th>
                        <th class="px-4 py-3">邮箱</th>
                        <th class="px-4 py-3">绑定成员</th>
                        <th class="px-4 py-3">会员等级</th>
                        <th class="px-4 py-3">状态</th>
                        <th class="px-4 py-3">最后登录</th>
                        <th class="px-4 py-3">操作</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if ($users === []): ?>
                        <tr><td colspan="9" class="px-4 py-8 text-center text-slate-400">暂无用户</td></tr>
                    <?php endif; ?>
                    <?php foreach ($users as $u): ?>
                        <?php $bound = is_array($u['bound_member'] ?? null) ? $u['bound_member'] : null; ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3"><?= (int) $u['id'] ?></td>
                            <td class="px-4 py-3 font-medium text-slate-900"><?= h((string) $u['name']) ?></td>
                            <td class="px-4 py-3"><?= h((string) $u['mobile']) ?></td>
                            <td class="px-4 py-3"><?= h((string) $u['email']) ?></td>
                            <td class="px-4 py-3">
                                <?php if ($bound): ?>
                                    <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700"><?= h((string) $bound['member_name']) ?></span>
                                <?php else: ?>
                                    <span class="text-xs text-slate-400">未绑定</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3">
                                <?php $lid = (int) ($u['membership_level_id'] ?? 1); $exp = (string) ($u['membership_expire_at'] ?? ''); $expired = $exp !== '' && strtotime($exp) < time(); ?>
                                <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium <?= $lid > 1 && !$expired ? 'border-amber-200 bg-amber-50 text-amber-700' : 'border-slate-200 bg-slate-50 text-slate-600' ?>"><?= h($levelMap[$lid] ?? ('等级' . $lid)) ?></span>
                                <?php if ($exp !== ''): ?><div class="mt-0.5 text-[11px] <?= $expired ? 'text-rose-500' : 'text-slate-400' ?>"><?= $expired ? '已过期' : '至 ' . h(substr($exp, 0, 10)) ?></div><?php endif; ?>
                            </td>
                            <td class="px-4 py-3">
                                <?php if ((int) $u['status'] === 1): ?>
                                    <span class="rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">正常</span>
                                <?php else: ?>
                                    <span class="rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700">停用</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-500"><?= h((string) ($u['last_login_at'] ?? '-')) ?></td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" class="user-edit-btn text-xs text-emerald-600 hover:underline"
                                        data-user-id="<?= (int) $u['id'] ?>"
                                        data-user-name="<?= h((string) $u['name']) ?>"
                                        data-mobile="<?= h((string) $u['mobile']) ?>"
                                        data-email="<?= h((string) $u['email']) ?>"
                                        data-status="<?= (int) $u['status'] ?>"
                                        data-points="<?= (int) ($u['points'] ?? 0) ?>"
                                        data-balance="<?= number_format((int) ($u['balance_fen'] ?? 0) / 10, 2, '.', '') ?>"
                                        data-level="<?= (int) ($u['membership_level_id'] ?? 1) ?>"
                                        data-expire="<?= h(substr((string) ($u['membership_expire_at'] ?? ''), 0, 10)) ?>"
                                        data-bound="<?= $bound ? '1' : '0' ?>">编辑</button>
                                    <form method="post" action="/admin/users/delete" class="inline" onsubmit="return confirm('确定删除该用户？此操作不可恢复。')">
                                        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                        <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                        <button type="submit" class="text-xs text-rose-600 hover:underline">删除</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<!-- Rebind Modal -->
<div id="rebind-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.4);backdrop-filter:blur(4px);align-items:center;justify-content:center;padding:16px;">
    <div style="background:#fff;border-radius:16px;max-width:420px;width:100%;padding:24px;box-shadow:0 20px 60px rgba(0,0,0,.15);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h3 id="rebind-modal-title" style="font-size:16px;font-weight:700;color:#1e293b;">绑定成员</h3>
            <button id="rebind-modal-close" type="button" style="width:28px;height:28px;border-radius:8px;border:1px solid #e2e8f0;background:#f8fafc;cursor:pointer;display:flex;align-items:center;justify-content:center;">✕</button>
        </div>
        <form id="rebind-form" method="post" action="/admin/users/rebind">
            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
            <input type="hidden" name="user_id" id="rebind-user-id" value="">
            <div style="margin-bottom:12px;">
                <label style="display:block;font-size:13px;color:#475569;margin-bottom:4px;">选择要绑定的成员</label>
                <select name="member_id" id="rebind-member-select" required style="width:100%;padding:8px 12px;border:1px solid #e2e8f0;border-radius:10px;font-size:14px;">
                    <option value="">请选择成员</option>
                    <?php foreach ($allMembers as $m): ?>
                        <option value="<?= (int) $m['id'] ?>"><?= h((string) $m['name']) ?> - <?= h((string) ($m['mobile'] ?? '无手机')) ?><?= !empty($m['department']) ? ' (' . h((string) $m['department']) . ')' : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" style="width:100%;padding:10px;background:#2563eb;color:#fff;border:none;border-radius:10px;font-size:14px;font-weight:600;cursor:pointer;">确认绑定</button>
        </form>
    </div>
</div>

<!-- Set Level Modal -->
<div id="level-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.4);backdrop-filter:blur(4px);align-items:center;justify-content:center;padding:16px;">
    <div style="width:100%;max-width:380px;background:#fff;border-radius:16px;padding:24px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h3 id="level-modal-title" style="font-size:16px;font-weight:700;color:#1e293b;">设置会员等级</h3>
            <button id="level-modal-close" type="button" style="width:28px;height:28px;border-radius:8px;border:1px solid #e2e8f0;background:#f8fafc;cursor:pointer;">✕</button>
        </div>
        <form method="post" action="/admin/users/set-level">
            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
            <input type="hidden" name="id" id="level-user-id" value="">
            <label style="display:block;font-size:13px;color:#475569;margin-bottom:4px;">会员等级</label>
            <select name="level_id" id="level-select" style="width:100%;padding:8px 12px;border:1px solid #e2e8f0;border-radius:10px;font-size:14px;margin-bottom:12px;">
                <?php foreach ($levels as $lv): ?>
                    <option value="<?= (int) $lv['id'] ?>"><?= h((string) $lv['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <label style="display:block;font-size:13px;color:#475569;margin-bottom:4px;">到期时间（留空 = 永久 / 不过期）</label>
            <input type="date" name="expire_at" id="level-expire" style="width:100%;padding:8px 12px;border:1px solid #e2e8f0;border-radius:10px;font-size:14px;margin-bottom:8px;">
            <p style="font-size:12px;color:#94a3b8;margin-bottom:12px;">降级或到期后，已有超出配额的名单/任务保留不变，但不能再新增，直到降至配额以下。</p>
            <button type="submit" style="width:100%;padding:10px;background:#2563eb;color:#fff;border:none;border-radius:10px;font-size:14px;font-weight:600;cursor:pointer;">保存</button>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div id="edit-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.4);backdrop-filter:blur(4px);align-items:center;justify-content:center;padding:16px;overflow:auto;">
    <div style="width:100%;max-width:440px;background:#fff;border-radius:16px;padding:24px;max-height:92vh;overflow:auto;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h3 id="edit-modal-title" style="font-size:16px;font-weight:700;color:#1e293b;">编辑用户</h3>
            <button id="edit-modal-close" type="button" style="width:28px;height:28px;border-radius:8px;border:1px solid #e2e8f0;background:#f8fafc;cursor:pointer;">✕</button>
        </div>
        <form method="post" action="/admin/users/update">
            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
            <input type="hidden" name="id" id="edit-user-id" value="">
            <label style="display:block;font-size:13px;color:#475569;margin-bottom:4px;">姓名</label>
            <input type="text" name="name" id="edit-name" required maxlength="50" style="width:100%;padding:8px 12px;border:1px solid #e2e8f0;border-radius:10px;font-size:14px;margin-bottom:12px;">
            <label style="display:block;font-size:13px;color:#475569;margin-bottom:4px;">手机号</label>
            <input type="text" name="mobile" id="edit-mobile" required maxlength="20" style="width:100%;padding:8px 12px;border:1px solid #e2e8f0;border-radius:10px;font-size:14px;margin-bottom:12px;">
            <label style="display:block;font-size:13px;color:#475569;margin-bottom:4px;">邮箱</label>
            <input type="email" name="email" id="edit-email" required maxlength="100" style="width:100%;padding:8px 12px;border:1px solid #e2e8f0;border-radius:10px;font-size:14px;margin-bottom:12px;">
            <div style="display:flex;gap:12px;">
                <div style="flex:1;">
                    <label style="display:block;font-size:13px;color:#475569;margin-bottom:4px;">积分</label>
                    <input type="number" name="points" id="edit-points" min="0" style="width:100%;padding:8px 12px;border:1px solid #e2e8f0;border-radius:10px;font-size:14px;margin-bottom:12px;">
                </div>
                <div style="flex:1;">
                    <label style="display:block;font-size:13px;color:#475569;margin-bottom:4px;">余额（元）</label>
                    <input type="number" name="balance_yuan" id="edit-balance" min="0" step="0.01" style="width:100%;padding:8px 12px;border:1px solid #e2e8f0;border-radius:10px;font-size:14px;margin-bottom:12px;">
                </div>
            </div>
            <label style="display:block;font-size:13px;color:#475569;margin-bottom:4px;">状态</label>
            <select name="status" id="edit-status" style="width:100%;padding:8px 12px;border:1px solid #e2e8f0;border-radius:10px;font-size:14px;margin-bottom:16px;">
                <option value="1">正常</option>
                <option value="0">停用</option>
            </select>
            <button type="submit" style="width:100%;padding:10px;background:#2563eb;color:#fff;border:none;border-radius:10px;font-size:14px;font-weight:600;cursor:pointer;">保存基础信息</button>
        </form>

        <div style="border-top:1px solid #e2e8f0;margin:16px 0;padding-top:16px;display:grid;gap:10px;">
            <!-- 设置等级 -->
            <form method="post" action="/admin/users/set-level" style="display:flex;gap:8px;align-items:end;">
                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                <input type="hidden" name="id" id="el-user-id" value="">
                <div style="flex:1;">
                    <label style="display:block;font-size:12px;color:#475569;margin-bottom:4px;">会员等级</label>
                    <select name="level_id" id="el-level" style="width:100%;padding:7px 10px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;">
                        <?php foreach ($levels as $lv): ?><option value="<?= (int) $lv['id'] ?>"><?= h((string) $lv['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div style="flex:1;">
                    <label style="display:block;font-size:12px;color:#475569;margin-bottom:4px;">到期(空=永久)</label>
                    <input type="date" name="expire_at" id="el-expire" style="width:100%;padding:6px 8px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;">
                </div>
                <button type="submit" style="padding:8px 12px;background:#f59e0b;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;white-space:nowrap;">设等级</button>
            </form>
            <!-- 绑定成员 -->
            <form method="post" action="/admin/users/rebind" style="display:flex;gap:8px;align-items:end;">
                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                <input type="hidden" name="user_id" id="eb-user-id" value="">
                <div style="flex:1;">
                    <label style="display:block;font-size:12px;color:#475569;margin-bottom:4px;">绑定成员</label>
                    <select name="member_id" required style="width:100%;padding:7px 10px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;">
                        <option value="">请选择成员</option>
                        <?php foreach ($allMembers as $m): ?><option value="<?= (int) $m['id'] ?>"><?= h((string) $m['name']) ?> - <?= h((string) ($m['mobile'] ?? '无手机')) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" style="padding:8px 12px;background:#4f46e5;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;white-space:nowrap;">绑定</button>
            </form>
            <!-- 其他操作 -->
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <form method="post" action="/admin/users/toggle" style="flex:1;">
                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                    <input type="hidden" name="id" id="et-user-id" value="">
                    <button type="submit" style="width:100%;padding:8px;background:#fff;color:#d97706;border:1px solid #fcd34d;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">启用/停用</button>
                </form>
                <form method="post" action="/admin/users/reset-password" style="flex:1;" onsubmit="return confirm('确定重置密码？')">
                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                    <input type="hidden" name="id" id="ep-user-id" value="">
                    <button type="submit" style="width:100%;padding:8px;background:#fff;color:#2563eb;border:1px solid #bfdbfe;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">重置密码</button>
                </form>
                <form method="post" action="/admin/users/unbind" style="flex:1;" id="eu-form" onsubmit="return confirm('确定解除绑定？')">
                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                    <input type="hidden" name="id" id="eu-user-id" value="">
                    <button type="submit" style="width:100%;padding:8px;background:#fff;color:#ea580c;border:1px solid #fed7aa;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">解除绑定</button>
                </form>
            </div>
        </div>
    </div>
</div>
<script>
(function(){
    var modal = document.getElementById('rebind-modal');
    var closeBtn = document.getElementById('rebind-modal-close');
    var userIdInput = document.getElementById('rebind-user-id');
    var titleEl = document.getElementById('rebind-modal-title');

    document.querySelectorAll('.user-rebind-btn').forEach(function(btn){
        btn.addEventListener('click', function(){
            userIdInput.value = btn.getAttribute('data-user-id');
            titleEl.textContent = '为「' + btn.getAttribute('data-user-name') + '」绑定成员';
            modal.style.display = 'flex';
        });
    });
    closeBtn.addEventListener('click', function(){ modal.style.display = 'none'; });
    modal.addEventListener('click', function(e){ if(e.target===modal) modal.style.display='none'; });

    var lm = document.getElementById('level-modal');
    var lmClose = document.getElementById('level-modal-close');
    document.querySelectorAll('.user-level-btn').forEach(function(btn){
        btn.addEventListener('click', function(){
            document.getElementById('level-user-id').value = btn.getAttribute('data-user-id');
            document.getElementById('level-select').value = btn.getAttribute('data-level') || '1';
            document.getElementById('level-expire').value = btn.getAttribute('data-expire') || '';
            document.getElementById('level-modal-title').textContent = '为「' + btn.getAttribute('data-user-name') + '」设置等级';
            lm.style.display = 'flex';
        });
    });
    lmClose.addEventListener('click', function(){ lm.style.display = 'none'; });
    lm.addEventListener('click', function(e){ if(e.target===lm) lm.style.display='none'; });

    var em = document.getElementById('edit-modal');
    var emClose = document.getElementById('edit-modal-close');
    document.querySelectorAll('.user-edit-btn').forEach(function(btn){
        btn.addEventListener('click', function(){
            var uid = btn.getAttribute('data-user-id');
            document.getElementById('edit-user-id').value = uid;
            document.getElementById('edit-name').value = btn.getAttribute('data-user-name') || '';
            document.getElementById('edit-mobile').value = btn.getAttribute('data-mobile') || '';
            document.getElementById('edit-email').value = btn.getAttribute('data-email') || '';
            document.getElementById('edit-status').value = btn.getAttribute('data-status') || '1';
            document.getElementById('edit-points').value = btn.getAttribute('data-points') || '0';
            document.getElementById('edit-balance').value = btn.getAttribute('data-balance') || '0';
            document.getElementById('el-user-id').value = uid;
            document.getElementById('el-level').value = btn.getAttribute('data-level') || '1';
            document.getElementById('el-expire').value = btn.getAttribute('data-expire') || '';
            document.getElementById('eb-user-id').value = uid;
            document.getElementById('et-user-id').value = uid;
            document.getElementById('ep-user-id').value = uid;
            document.getElementById('eu-user-id').value = uid;
            document.getElementById('eu-form').style.display = btn.getAttribute('data-bound') === '1' ? '' : 'none';
            document.getElementById('edit-modal-title').textContent = '编辑用户「' + (btn.getAttribute('data-user-name') || '') + '」';
            em.style.display = 'flex';
        });
    });
    emClose.addEventListener('click', function(){ em.style.display = 'none'; });
    em.addEventListener('click', function(e){ if(e.target===em) em.style.display='none'; });
})();
</script>
