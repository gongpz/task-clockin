<?php
// 安装向导页面模板（由 install/index.php 引入，不能单独访问）
if (!defined('ROOT')) {
    http_response_code(404);
    exit;
}
?><!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>安装 · 任务打卡系统</title>
<style>
  * { box-sizing: border-box; }
  body { margin: 0; background: #f3f5f8; color: #1f2937; font: 15px/1.6 -apple-system, "PingFang SC", "Microsoft YaHei", sans-serif; }
  .wrap { max-width: 720px; margin: 32px auto; padding: 0 16px; }
  .card { background: #fff; border-radius: 10px; box-shadow: 0 1px 3px rgba(0,0,0,.08); padding: 24px 28px; margin-bottom: 16px; }
  h1 { font-size: 22px; margin: 0 0 4px; }
  h2 { font-size: 16px; margin: 0 0 14px; }
  .muted { color: #6b7280; font-size: 13px; }
  table { width: 100%; border-collapse: collapse; }
  td { padding: 7px 4px; border-bottom: 1px solid #eef0f3; vertical-align: top; }
  .ok { color: #15803d; font-weight: 600; } .bad { color: #b91c1c; font-weight: 600; } .warn { color: #b45309; font-weight: 600; }
  label { display: block; margin: 12px 0 4px; font-weight: 600; font-size: 14px; }
  input[type=text], input[type=password] { width: 100%; padding: 9px 11px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 15px; }
  input:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,.15); }
  .row { display: flex; gap: 12px; } .row > div { flex: 1; }
  .hint { color: #6b7280; font-size: 12.5px; margin-top: 3px; }
  .check { display: flex; gap: 8px; align-items: flex-start; margin-top: 14px; font-weight: normal; }
  .check input { margin-top: 4px; }
  button { margin-top: 20px; width: 100%; padding: 12px; border: 0; border-radius: 6px; background: #2563eb; color: #fff; font-size: 16px; cursor: pointer; }
  button:disabled { background: #9ca3af; cursor: not-allowed; }
  .err { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; border-radius: 8px; padding: 12px 16px; margin-bottom: 16px; }
  .err li { margin: 2px 0; }
  .success { border-left: 4px solid #16a34a; }
  pre { background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 6px; padding: 10px 12px; overflow: auto; font-size: 13px; white-space: pre-wrap; }
  a.btn { display: inline-block; margin: 6px 8px 0 0; padding: 9px 18px; background: #2563eb; color: #fff; border-radius: 6px; text-decoration: none; }
  a.btn.gray { background: #64748b; }
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <h1>任务打卡系统 · 安装向导</h1>
    <div class="muted">版本 <?= h($version !== '' ? $version : '未知') ?>　·　适用于宝塔、AMH 等主机面板</div>
  </div>

<?php if ($done): ?>
  <div class="card success">
    <h2>✅ 安装完成</h2>
    <p>管理员账号：<b><?= h($done['admin_user']) ?></b><?= $done['admin_existed'] ? '（库里已有这个账号，密码没有改动）' : '' ?></p>
    <p><a class="btn" href="../admin/login">进入管理后台</a><a class="btn gray" href="../tasks">打开前台任务页</a></p>
    <p class="muted">安装向导已自动锁定。为了安全，建议在面板的「文件」里把网站根目录下的 <b>install</b> 目录删掉。</p>
  </div>
  <?php if (is_file($rewriteFile)): ?>
  <div class="card">
    <h2>别忘了伪静态</h2>
    <p class="muted">如果点「进入管理后台」显示 404，说明还没设伪静态：面板 → 网站 → 设置 → 伪静态，粘贴下面的规则后保存。</p>
    <pre><?= h(file_get_contents($rewriteFile)) ?></pre>
  </div>
  <?php endif; ?>

<?php elseif ($installed): ?>
  <div class="card">
    <h2>系统已经安装过</h2>
    <p>检测到 <code>.env</code> 或 <code>storage/install.lock</code>，安装向导已锁定，不能重复安装。</p>
    <p class="muted">确实要重新安装：在面板「文件」里删掉网站根目录的 <code>.env</code> 和 <code>storage/install.lock</code>，再打开本页面。已有数据不会被删除。</p>
    <p><a class="btn" href="../admin/login">进入管理后台</a></p>
  </div>

<?php else: ?>
  <?php if ($errors): ?>
  <div class="err"><b>没有安装成功：</b><ul><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>

  <div class="card">
    <h2>1. 环境检测</h2>
    <table>
      <?php foreach ($checks as [$name, $pass, $note, $required]): ?>
      <tr>
        <td><?= h($name) ?></td>
        <td class="<?= $pass ? 'ok' : ($required ? 'bad' : 'warn') ?>"><?= $pass ? '通过' : ($required ? '不通过' : '缺少') ?></td>
        <td class="muted"><?= h($note) ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php if (!$checksOk): ?>
    <p class="bad" style="margin-bottom:0">有必须项不通过。PHP 版本和扩展在面板「软件商店 → PHP → 设置」里调整；目录不可写时，把网站目录所有者改成 www（宝塔：网站 → 设置 → 网站目录 → 权限）。处理完刷新本页。</p>
    <?php endif; ?>
  </div>

  <form method="post" class="card" autocomplete="off">
    <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
    <h2>2. 数据库</h2>
    <div class="muted">先在面板「数据库」页面新建一个数据库（字符集选 utf8mb4），把信息填到这里。</div>
    <div class="row">
      <div><label>数据库地址</label><input type="text" name="db_host" value="<?= h($in['db_host']) ?>" required>
        <div class="hint">一般不用改；AMH 等用 socket 的填 socket 路径</div></div>
      <div style="flex:.5"><label>端口</label><input type="text" name="db_port" value="<?= h($in['db_port']) ?>" required></div>
    </div>
    <label>数据库名</label><input type="text" name="db_name" value="<?= h($in['db_name']) ?>" required>
    <div class="row">
      <div><label>数据库用户名</label><input type="text" name="db_user" value="<?= h($in['db_user']) ?>" required></div>
      <div><label>数据库密码</label><input type="password" name="db_pass" value="<?= h($in['db_pass']) ?>" required></div>
    </div>
    <label class="check"><input type="checkbox" name="reuse" value="1" <?= $in['reuse'] === '1' ? 'checked' : '' ?>>
      <span>沿用库里已有的数据（换服务器、重装时用；新装不用勾）</span></label>

    <h2 style="margin-top:24px">3. 管理员和系统名称</h2>
    <label>系统名称</label><input type="text" name="site_name" value="<?= h($in['site_name']) ?>" required>
    <label>管理员账号</label><input type="text" name="admin_user" value="<?= h($in['admin_user']) ?>" required>
    <div class="row">
      <div><label>管理员密码</label><input type="password" name="admin_pass" minlength="8" required><div class="hint">至少 8 位</div></div>
      <div><label>再输一次</label><input type="password" name="admin_pass2" minlength="8" required></div>
    </div>
    <button type="submit" <?= $checksOk ? '' : 'disabled' ?>>开始安装</button>
  </form>
<?php endif; ?>
</div>
</body>
</html>
