<?php
// 任务打卡系统 · 初始化管理员（install.sh 调用）
// 参数经环境变量传入，避免账号、密码里的特殊字符被 shell 解释。
// 已有同名管理员时不改密码，输出 EXISTS。
declare(strict_types=1);

$root = getenv('TASK_SITE_ROOT') ?: '';
$username = getenv('TASK_ADMIN_USERNAME') ?: '';
$password = getenv('TASK_ADMIN_PASSWORD') ?: '';
$siteTitle = trim((string) getenv('TASK_SITE_TITLE'));
if ($root === '' || $username === '' || strlen($password) < 8) {
    fwrite(STDERR, "缺少 TASK_SITE_ROOT / TASK_ADMIN_USERNAME / TASK_ADMIN_PASSWORD（至少 8 位）\n");
    exit(1);
}

$env = [];
foreach (file($root . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
        continue;
    }
    [$k, $v] = explode('=', $line, 2);
    $env[trim($k)] = trim(trim($v), "\"'");
}
$socket = $env['DB_SOCKET'] ?? '';
$dsn = $socket !== ''
    ? sprintf('mysql:unix_socket=%s;dbname=%s;charset=utf8mb4', $socket, $env['DB_NAME'] ?? '')
    : sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $env['DB_HOST'] ?? '127.0.0.1', $env['DB_PORT'] ?? '3306', $env['DB_NAME'] ?? '');
$pdo = new PDO($dsn, $env['DB_USER'] ?? '', $env['DB_PASS'] ?? '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

if ($siteTitle !== '') {
    $pdo->prepare("INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES ('site_name', :v)")
        ->execute(['v' => $siteTitle]);
}

$stmt = $pdo->prepare('SELECT id FROM admins WHERE username = :u LIMIT 1');
$stmt->execute(['u' => $username]);
if ($stmt->fetchColumn()) {
    echo 'EXISTS';
    exit(0);
}
$pdo->prepare(
    'INSERT INTO admins (role_id, username, password_hash, real_name, status) VALUES (1, :u, :h, :n, 1)'
)->execute([
    'u' => $username,
    'h' => password_hash($password, PASSWORD_BCRYPT),
    'n' => '超级管理员',
]);
echo 'CREATED';
