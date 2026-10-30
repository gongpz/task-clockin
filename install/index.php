<?php
declare(strict_types=1);

// =====================================================================
// 任务打卡系统 · 网页安装向导
// 把安装包上传到宝塔 / AMH 等面板的网站根目录，浏览器打开 /install/ 按提示填写即可。
// 装好后写入 .env 和 storage/install.lock，本页面随即失效（不能重复安装、不能被别人重装）。
// =====================================================================

define('ROOT', dirname(__DIR__));
const MIN_PHP = '8.1.0';

$envFile = ROOT . '/.env';
$lockFile = ROOT . '/storage/install.lock';
$schemaFile = ROOT . '/deploy/schema.sql';
$rewriteFile = ROOT . '/伪静态-直接粘贴.txt';
$version = is_file(ROOT . '/VERSION') ? trim((string) file_get_contents(ROOT . '/VERSION')) : '';

function h(mixed $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

session_name('task_install');
@session_start();
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

$installed = is_file($envFile) || is_file($lockFile);
$errors = [];
$done = null;

// 环境检测：[名称, 是否通过, 说明, 是否必须]
$checks = [
    ['PHP ' . MIN_PHP . ' 以上', version_compare(PHP_VERSION, MIN_PHP, '>='), '当前 ' . PHP_VERSION, true],
    ['pdo_mysql 扩展', extension_loaded('pdo_mysql'), '连接数据库', true],
    ['mbstring 扩展', extension_loaded('mbstring'), '中文处理', true],
    ['gd 扩展', extension_loaded('gd'), '打卡图片压缩，没有也能用', false],
    ['curl 扩展', extension_loaded('curl'), '连接授权中心，没有也能用', false],
    ['zip 扩展', class_exists('ZipArchive'), '导入 Excel 名单，没有时只能导入 CSV', false],
    ['网站根目录可写', is_writable(ROOT), '要写入配置文件 .env', true],
    ['storage 目录可写', is_dir(ROOT . '/storage') && is_writable(ROOT . '/storage'), '存放打卡图片和日志', true],
    ['建表文件 deploy/schema.sql', is_file($schemaFile), '安装包是否完整', true],
];
$checksOk = !in_array(false, array_map(static fn ($c) => $c[1] || !$c[3], $checks), true);

$in = [
    'db_host' => '127.0.0.1', 'db_port' => '3306', 'db_name' => '', 'db_user' => '', 'db_pass' => '',
    'admin_user' => 'admin', 'site_name' => '任务打卡系统', 'reuse' => '',
];

if ($installed) {
    http_response_code(403);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($in as $k => $v) {
        $in[$k] = trim((string) ($_POST[$k] ?? ''));
    }
    $in['db_pass'] = (string) ($_POST['db_pass'] ?? '');
    $adminPass = (string) ($_POST['admin_pass'] ?? '');

    if (!hash_equals((string) $_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
        $errors[] = '页面已过期，请刷新后重新提交';
    }
    if (!$checksOk) {
        $errors[] = '环境检测有必须项没通过，请先处理';
    }
    if (!preg_match('#^(/[A-Za-z0-9._/-]+|[A-Za-z0-9._-]+)$#', $in['db_host'])) {
        $errors[] = '数据库地址格式不对（填 127.0.0.1、localhost，或 socket 路径如 /tmp/mysql.sock）';
    }
    if (!preg_match('/^\d{1,5}$/', $in['db_port'])) {
        $errors[] = '数据库端口只能是数字';
    }
    if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $in['db_name']) || !preg_match('/^[A-Za-z0-9_.-]{1,80}$/', $in['db_user'])) {
        $errors[] = '数据库名、用户名只能用字母、数字、下划线';
    }
    if ($in['db_pass'] === '' || preg_match('/[\r\n]/', $in['db_pass']) || $in['db_pass'] !== trim($in['db_pass'], " \t\"'")) {
        $errors[] = '数据库密码不能为空，不能换行，首尾不能是空格或引号';
    }
    if (!preg_match('/^[A-Za-z0-9_.@-]{3,50}$/', $in['admin_user'])) {
        $errors[] = '管理员账号只能是 3～50 位字母、数字和 _ . @ -';
    }
    if (strlen($adminPass) < 8 || $adminPass !== (string) ($_POST['admin_pass2'] ?? '')) {
        $errors[] = '管理员密码至少 8 位，且两次输入要一致';
    }
    if ($in['site_name'] === '' || mb_strlen($in['site_name']) > 50 || preg_match('/[\r\n]/', $in['site_name'])) {
        $errors[] = '系统名称 1～50 个字';
    }

    if (!$errors) {
        try {
            $done = run_install($in, $adminPass, $schemaFile, $envFile, $lockFile, $version);
            $installed = true;
            unset($_SESSION['csrf']);
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}

require __DIR__ . '/view.php';

function run_install(array $in, string $adminPass, string $schemaFile, string $envFile, string $lockFile, string $version): array
{
    $socket = str_starts_with($in['db_host'], '/') ? $in['db_host'] : '';
    $dsn = $socket !== ''
        ? sprintf('mysql:unix_socket=%s;dbname=%s;charset=utf8mb4', $socket, $in['db_name'])
        : sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $in['db_host'], $in['db_port'], $in['db_name']);
    try {
        $pdo = new PDO($dsn, $in['db_user'], $in['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 8,
        ]);
    } catch (PDOException $e) {
        throw new RuntimeException('连不上数据库：' . $e->getMessage() . '。请核对数据库名、用户名、密码（面板「数据库」页可以看到）');
    }

    // 库里已经有本系统的数据时，必须明确勾选「沿用」，防止误装到别人的库上
    $hasAdmins = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admins'")->fetchColumn() > 0;
    $adminCount = $hasAdmins ? (int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn() : 0;
    if ($adminCount > 0 && $in['reuse'] !== '1') {
        throw new RuntimeException('数据库「' . $in['db_name'] . '」里已经有任务打卡系统的数据（' . $adminCount . ' 个管理员）。要沿用这些数据，请勾选下面的「沿用库里已有的数据」再提交；否则请换一个空数据库。');
    }

    // 建表：schema.sql 全部是 CREATE TABLE IF NOT EXISTS / INSERT IGNORE，重复执行不会破坏已有数据
    $sql = implode("\n", array_filter(
        explode("\n", (string) file_get_contents($schemaFile)),
        static fn ($l) => !str_starts_with(ltrim($l), '--')
    ));
    foreach (preg_split('/;\s*(?:\n|$)/', $sql) as $stmt) {
        if (trim($stmt) !== '') {
            $pdo->exec($stmt);
        }
    }

    $pdo->prepare("INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES ('site_name', :v)")
        ->execute(['v' => $in['site_name']]);

    $stmt = $pdo->prepare('SELECT id FROM admins WHERE username = :u LIMIT 1');
    $stmt->execute(['u' => $in['admin_user']]);
    $adminExisted = (bool) $stmt->fetchColumn();
    if (!$adminExisted) {
        $pdo->prepare('INSERT INTO admins (role_id, username, password_hash, real_name, status) VALUES (1, :u, :h, :n, 1)')
            ->execute(['u' => $in['admin_user'], 'h' => password_hash($adminPass, PASSWORD_BCRYPT), 'n' => '超级管理员']);
    }

    $env = "APP_DEBUG=0\nAPP_TIMEZONE=Asia/Shanghai\nADMIN_PAGE_SHOW_HEADER=1\nSESSION_COOKIE_SECURE=auto\nCHECKIN_MAX_IMAGE_MB=5\n\n"
        . 'DB_HOST=' . ($socket !== '' ? '127.0.0.1' : $in['db_host']) . "\nDB_PORT=" . $in['db_port']
        . "\nDB_NAME=" . $in['db_name'] . "\nDB_USER=" . $in['db_user'] . "\nDB_PASS=" . $in['db_pass']
        . "\nDB_CHARSET=utf8mb4\nDB_SOCKET=" . $socket . "\n";
    if (@file_put_contents($envFile . '.tmp', $env, LOCK_EX) === false || !@rename($envFile . '.tmp', $envFile)) {
        throw new RuntimeException('表已建好，但写不了配置文件 .env：请把网站根目录的所有者设为 PHP 运行用户（宝塔一般是 www），再提交一次');
    }
    @chmod($envFile, 0640);
    @file_put_contents($lockFile, json_encode([
        'installed_at' => date('Y-m-d H:i:s'),
        'version' => $version,
    ], JSON_UNESCAPED_UNICODE) . "\n");

    return ['admin_user' => $in['admin_user'], 'admin_existed' => $adminExisted];
}
