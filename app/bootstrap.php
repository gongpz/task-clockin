<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

load_env(APP_ROOT . '/.env');

$timezone = env('APP_TIMEZONE', 'Asia/Shanghai');
if ($timezone !== '') {
    date_default_timezone_set($timezone);
}

$debugRaw = strtolower(trim(env('APP_DEBUG', '0')));
$appDebug = in_array($debugRaw, ['1', 'true', 'yes', 'on'], true);
ini_set('display_errors', $appDebug ? '1' : '0');
ini_set('display_startup_errors', $appDebug ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');

    $secureCookieEnv = strtolower(trim(env('SESSION_COOKIE_SECURE', 'auto')));
    $isHttpsRequest = false;
    $httpsServer = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
    if ($httpsServer !== '' && $httpsServer !== 'off') {
        $isHttpsRequest = true;
    } elseif ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) {
        $isHttpsRequest = true;
    } elseif (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') {
        $isHttpsRequest = true;
    }
    $cookieSecure = match ($secureCookieEnv) {
        '1', 'true', 'yes', 'on' => true,
        '0', 'false', 'no', 'off' => false,
        default => $isHttpsRequest,
    };
    ini_set('session.cookie_secure', $cookieSecure ? '1' : '0');

    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => $cookieSecure,
        'cookie_samesite' => 'Lax',
    ]);
}

ensure_storage_paths();

function load_env(string $filePath): void
{
    if (!is_file($filePath)) {
        return;
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }
        $key = trim($parts[0]);
        $value = trim($parts[1]);
        $value = trim($value, "\"'");
        if ($key === '') {
            continue;
        }

        $exists = false;
        if (array_key_exists($key, $_ENV) || array_key_exists($key, $_SERVER)) {
            $exists = true;
        } elseif (function_exists('getenv')) {
            $exists = getenv($key) !== false;
        }

        if ($exists) {
            continue;
        }

        if (function_exists('putenv')) {
            @putenv($key . '=' . $value);
        }
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

function env(string $key, ?string $default = null): string
{
    if (array_key_exists($key, $_ENV)) {
        return (string) $_ENV[$key];
    }
    if (array_key_exists($key, $_SERVER)) {
        return (string) $_SERVER[$key];
    }

    $value = function_exists('getenv') ? getenv($key) : false;
    if ($value === false || $value === null) {
        return $default ?? '';
    }
    return (string) $value;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = env('DB_HOST', '127.0.0.1');
    $port = env('DB_PORT', '3306');
    $name = env('DB_NAME', 'cqku');
    $charset = env('DB_CHARSET', 'utf8mb4');
    $user = env('DB_USER', 'root');
    $pass = env('DB_PASS', '');
    $socket = env('DB_SOCKET', '');

    if ($socket !== '') {
        $dsn = sprintf('mysql:unix_socket=%s;dbname=%s;charset=%s', $socket, $name, $charset);
    } else {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $name, $charset);
    }

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function ensure_storage_paths(): void
{
    $paths = [
        APP_ROOT . '/storage',
        APP_ROOT . '/storage/logs',
        APP_ROOT . '/storage/uploads',
        APP_ROOT . '/storage/uploads/checkins',
    ];

    foreach ($paths as $path) {
        if (is_dir($path)) {
            continue;
        }

        $parent = dirname($path);
        if (!is_dir($parent) || !is_writable($parent)) {
            error_log('Storage path not writable: ' . $path);
            continue;
        }

        // Suppress runtime warning on locked-down hosts; actual failure is logged above.
        @mkdir($path, 0775, true);
        clearstatcache(true, $path);
        if (!is_dir($path)) {
            error_log('Failed to create storage path: ' . $path);
        }
    }
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function render_view(string $view, array $data = []): string
{
    $viewPath = APP_ROOT . '/app/views/' . ltrim($view, '/');
    if (!is_file($viewPath)) {
        throw new RuntimeException('View not found: ' . $view);
    }

    extract($data, EXTR_SKIP);
    ob_start();
    include $viewPath;
    return (string) ob_get_clean();
}

function redirect(string $url, int $statusCode = 302): never
{
    header('Location: ' . $url, true, $statusCode);
    exit;
}

function json_response(array $payload, int $statusCode = 200): never
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function request_payload(): array
{
    $contentType = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
    if (str_contains($contentType, 'application/json')) {
        $raw = file_get_contents('php://input');
        if ($raw === false || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    if (str_contains($contentType, 'application/x-www-form-urlencoded') && in_array($_SERVER['REQUEST_METHOD'] ?? '', ['PUT', 'PATCH', 'DELETE'], true)) {
        $raw = file_get_contents('php://input');
        $data = [];
        parse_str((string) $raw, $data);
        return is_array($data) ? $data : [];
    }

    return $_POST;
}

function parse_bool(mixed $value): bool
{
    if (is_bool($value)) {
        return $value;
    }
    if (is_int($value)) {
        return $value === 1;
    }
    $value = strtolower(trim((string) $value));
    return in_array($value, ['1', 'true', 'yes', 'on'], true);
}

function ini_size_to_bytes(string $value): int
{
    $value = trim($value);
    if ($value === '') {
        return 0;
    }

    $unit = strtolower(substr($value, -1));
    $number = (float) $value;
    $multiplier = match ($unit) {
        'g' => 1024 * 1024 * 1024,
        'm' => 1024 * 1024,
        'k' => 1024,
        default => 1,
    };

    return (int) round($number * $multiplier);
}

function push_flash(string $type, string $message, array $meta = []): void
{
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message,
        'meta' => $meta,
    ];
}

function pop_flash(): array
{
    $flash = $_SESSION['flash_message'] ?? ['type' => '', 'message' => '', 'meta' => []];
    unset($_SESSION['flash_message']);
    if (!is_array($flash)) {
        return ['type' => '', 'message' => '', 'meta' => []];
    }
    if (!isset($flash['meta']) || !is_array($flash['meta'])) {
        $flash['meta'] = [];
    }
    return $flash;
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_or_fail(): void
{
    $token = (string) ($_POST['csrf_token'] ?? '');
    $sessionToken = (string) ($_SESSION['csrf_token'] ?? '');
    if ($token === '' || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
        http_response_code(419);
        render_public_shell("\u{8868}\u{5355}\u{5DF2}\u{8FC7}\u{671F}", "<h1>&#34920;&#21333;&#24050;&#36807;&#26399;&#65292;&#35831;&#21047;&#26032;&#21518;&#37325;&#35797;</h1>");
        exit;
    }
}
function set_admin_session(int $adminId): void
{
    $_SESSION['admin_id'] = $adminId;
}

function current_admin_id(): int
{
    return (int) ($_SESSION['admin_id'] ?? 0);
}

function logout_admin(): void
{
    unset($_SESSION['admin_id']);
}

function require_admin(bool $asApi): array
{
    $adminId = current_admin_id();
    if ($adminId <= 0) {
        if ($asApi) {
            json_response(['ok' => false, 'message' => "\u{672A}\u{767B}\u{5F55}"], 401);
        }
        redirect('/admin/login');
    }

    $admin = find_admin_by_id($adminId);
    if ($admin === null) {
        logout_admin();
        if ($asApi) {
            json_response(['ok' => false, 'message' => "\u{767B}\u{5F55}\u{72B6}\u{6001}\u{5931}\u{6548}"], 401);
        }
        redirect('/admin/login');
    }

    return $admin;
}
function to_datetime_local(string $value): string
{
    if ($value === '') {
        return '';
    }
    $time = strtotime($value);
    if ($time === false) {
        return '';
    }
    return date('Y-m-d\TH:i', $time);
}

function to_mysql_datetime(?string $value): ?string
{
    if ($value === null) {
        return null;
    }
    $value = trim($value);
    if ($value === '') {
        return null;
    }
    $time = strtotime($value);
    if ($time === false) {
        throw new InvalidArgumentException("\u{65E5}\u{671F}\u{65F6}\u{95F4}\u{683C}\u{5F0F}\u{4E0D}\u{6B63}\u{786E}");
    }
    return date('Y-m-d H:i:s', $time);
}
function render_admin_shell(string $title, string $content, array $admin): void
{
    $flash = pop_flash();
    $flashHtml = '';
    if (($flash['message'] ?? '') !== '') {
        $cls = ($flash['type'] ?? '') === 'success'
            ? 'rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 shadow-sm'
            : 'rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700 shadow-sm';
        $flashHtml = '<div class="' . $cls . '" data-auto-dismiss="true">' . h((string) $flash['message']) . '</div>';
    }

    $adminName = (string) ($admin['real_name'] ?: $admin['username']);
    $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

    $isNavActive = static function (string $href) use ($currentPath): bool {
        return $href === '/admin'
            ? $currentPath === '/admin'
            : str_starts_with($currentPath, $href);
    };

    $adminNavItems = [
        ['label' => "\u{9996}\u{9875}", 'href' => '/admin'],
        ['label' => "\u{4EFB}\u{52A1}\u{7BA1}\u{7406}", 'href' => '/admin/tasks'],
        ['label' => "\u{6253}\u{5361}\u{8BB0}\u{5F55}", 'href' => '/admin/records'],
        ['label' => "\u{4EBA}\u{5458}\u{7BA1}\u{7406}", 'href' => '/admin/members'],
        ['label' => "\u{5206}\u{7EC4}\u{7BA1}\u{7406}", 'href' => '/admin/member-groups'],
        ['label' => "\u{7528}\u{6237}\u{7BA1}\u{7406}", 'href' => '/admin/users'],
        ['label' => "\u{4F1A}\u{5458}\u{7B49}\u{7EA7}", 'href' => '/admin/membership'],
        ['label' => "\u{8BA2}\u{5355}\u{7BA1}\u{7406}", 'href' => '/admin/orders'],
        ['label' => "\u{5957}\u{9910}\u{7BA1}\u{7406}", 'href' => '/admin/shop-plans'],
        ['label' => "\u{5361}\u{5BC6}\u{7BA1}\u{7406}", 'href' => '/admin/card-keys'],
        ['label' => "\u{516C}\u{544A}\u{5F39}\u{7A97}", 'href' => '/admin/popups'],
        ['label' => "\u{7559}\u{8A00}\u{7BA1}\u{7406}", 'href' => '/admin/messages'],
        ['label' => "\u{524D}\u{53F0}\u{4EFB}\u{52A1}\u{9875}", 'href' => '/tasks', 'target' => '_blank'],
    ];

    header('Content-Type: text/html; charset=utf-8');
    echo render_view('layouts/admin.php', [
        'title' => $title,
        'content' => $content,
        'flashHtml' => $flashHtml,
        'adminName' => $adminName,
        'isNavActive' => $isNavActive,
        'adminNavItems' => $adminNavItems,
        'brandHref' => '/admin',
        'brandTitle' => "\u{6253}\u{5361}\u{7CFB}\u{7EDF}",
        'brandSubtitle' => "\u{540E}\u{53F0}\u{7BA1}\u{7406}",
        'adminCenterHref' => '/admin/center',
        'quickActionHref' => '/admin/tasks',
        'quickActionLabel' => "\u{65B0}\u{5EFA}\u{4EFB}\u{52A1}",
        'logoutHref' => '/admin/logout',
    ]);
}
function render_public_shell(string $title, string $content, array $options = []): void
{
    $showHeader = true;
    if (array_key_exists('showHeader', $options)) {
        $showHeader = (bool) $options['showHeader'];
    }

    $currentUser = current_user();

    header('Content-Type: text/html; charset=utf-8');
    echo render_view('layouts/public.php', [
        'title' => $title,
        'content' => $content,
        'brandTitle' => '任务打卡系统',
        'brandHref' => '/tasks',
        'headerActionLabel' => '管理员登录',
        'headerActionHref' => '/admin/login',
        'showHeader' => $showHeader,
        'themeToggleEnabled' => is_public_theme_toggle_enabled(),
        'currentUser' => $currentUser,
    ]);
}

// ─── User Authentication ───

function generate_user_token(int $userId): string
{
    $header = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload = base64_encode(json_encode([
        'uid' => $userId,
        'exp' => time() + 7 * 86400,
        'iat' => time(),
    ]));
    $secret = env('DB_PASS') . '_user_token_secret';
    $signature = base64_encode(hash_hmac('sha256', "$header.$payload", $secret, true));
    return "$header.$payload.$signature";
}

function verify_user_token(string $token): ?int
{
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return null;
    }
    [$header, $payload, $signature] = $parts;
    $secret = env('DB_PASS') . '_user_token_secret';
    $expected = base64_encode(hash_hmac('sha256', "$header.$payload", $secret, true));
    if (!hash_equals($expected, $signature)) {
        return null;
    }
    $data = json_decode(base64_decode($payload), true);
    if (!is_array($data) || ($data['exp'] ?? 0) < time()) {
        return null;
    }
    return (int) ($data['uid'] ?? 0) ?: null;
}

function set_user_session(int $userId): void
{
    $_SESSION['user_id'] = $userId;
    $token = generate_user_token($userId);
    setcookie('user_token', $token, [
        'expires' => time() + 7 * 86400,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function current_user_id(): int
{
    if (isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0) {
        return (int) $_SESSION['user_id'];
    }
    $token = (string) ($_COOKIE['user_token'] ?? '');
    if ($token === '') {
        return 0;
    }
    $uid = verify_user_token($token);
    if ($uid !== null) {
        $_SESSION['user_id'] = $uid;
    }
    return $uid ?? 0;
}

function current_user(): ?array
{
    $uid = current_user_id();
    if ($uid <= 0) {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM users WHERE id = :id AND status = 1 LIMIT 1');
    $stmt->execute(['id' => $uid]);
    $user = $stmt->fetch();
    if (!$user) {
        logout_user();
        return null;
    }
    unset($user['password_hash']);
    return $user;
}

function require_user(bool $asApi): array
{
    $user = current_user();
    if ($user === null) {
        if ($asApi) {
            json_response(['ok' => false, 'message' => '请先登录'], 401);
        }
        redirect('/login');
    }
    return $user;
}

function logout_user(): void
{
    unset($_SESSION['user_id']);
    setcookie('user_token', '', ['expires' => 1, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
}

function check_login_rate_limit(string $ip): bool
{
    $key = 'login_attempts_' . md5($ip);
    $attempts = (int) ($_SESSION[$key . '_count'] ?? 0);
    $firstAt = (int) ($_SESSION[$key . '_time'] ?? 0);
    if ($firstAt > 0 && (time() - $firstAt) > 300) {
        $_SESSION[$key . '_count'] = 0;
        $_SESSION[$key . '_time'] = 0;
        return true;
    }
    return $attempts < 5;
}

function record_login_attempt(string $ip): void
{
    $key = 'login_attempts_' . md5($ip);
    if (!isset($_SESSION[$key . '_time']) || (int) $_SESSION[$key . '_time'] === 0) {
        $_SESSION[$key . '_time'] = time();
    }
    $_SESSION[$key . '_count'] = ((int) ($_SESSION[$key . '_count'] ?? 0)) + 1;
}

function reset_login_attempts(string $ip): void
{
    $key = 'login_attempts_' . md5($ip);
    $_SESSION[$key . '_count'] = 0;
    $_SESSION[$key . '_time'] = 0;
}

