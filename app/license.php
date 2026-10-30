<?php
declare(strict_types=1);

// 系统版本：以仓库根目录的 VERSION 文件为准（随部署更新），没有时才用 .env 的 APP_VERSION
function app_version(): string
{
    $file = APP_ROOT . '/VERSION';
    $version = is_file($file) ? trim((string) file_get_contents($file)) : '';
    return $version !== '' ? $version : env('APP_VERSION', '1.0.0');
}

function license_configured(): bool
{
    return trim(env('LICENSE_KEY', '')) !== ''
        && trim(env('PRODUCT_CODE', '')) !== ''
        && trim(env('LICENSE_SERVER', '')) !== '';
}

function license_install_id(): string
{
    $configured = trim(env('LICENSE_INSTALL_ID', ''));
    if ($configured !== '') {
        return $configured;
    }

    $file = APP_ROOT . '/storage/license_install_id';
    if (is_file($file)) {
        $value = trim((string) file_get_contents($file));
        if ($value !== '') {
            return $value;
        }
    }

    $value = bin2hex(random_bytes(16));
    @file_put_contents($file, $value, LOCK_EX);

    return $value;
}

function license_current_domain(): string
{
    $configured = trim(env('LICENSE_CURRENT_DOMAIN', ''));
    if ($configured !== '') {
        return normalize_license_domain($configured);
    }

    return normalize_license_domain((string) ($_SERVER['HTTP_HOST'] ?? ''));
}

function normalize_license_domain(string $domain): string
{
    $domain = strtolower(trim($domain));
    $host = parse_url(str_contains($domain, '://') ? $domain : 'http://' . $domain, PHP_URL_HOST);

    return $host ?: $domain;
}

function license_server_ip(): string
{
    $configured = trim(env('LICENSE_SERVER_IP', ''));
    if ($configured !== '') {
        return $configured;
    }

    return (string) ($_SERVER['SERVER_ADDR'] ?? gethostbyname(gethostname()));
}

function license_fingerprint(): string
{
    return hash('sha256', implode('|', [
        env('LICENSE_KEY', ''),
        env('PRODUCT_CODE', ''),
        license_current_domain(),
        license_server_ip(),
        APP_ROOT,
        license_install_id(),
        env('DB_NAME', ''),
    ]));
}

function license_cache_file(): string
{
    return APP_ROOT . '/storage/license_cache.json';
}

function license_cache_ttl_seconds(): int
{
    return max(300, (int) env('LICENSE_CACHE_TTL_SECONDS', '86400'));
}

function license_read_cache(): ?array
{
    $file = license_cache_file();
    if (! is_file($file)) {
        return null;
    }

    $decoded = json_decode((string) file_get_contents($file), true);

    return is_array($decoded) ? $decoded : null;
}

function license_write_cache(array $result): void
{
    $payload = [
        'checked_at' => date('Y-m-d H:i:s'),
        'result' => $result,
    ];

    @file_put_contents(
        license_cache_file(),
        json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
        LOCK_EX
    );
}

function license_verify_online(): array
{
    $payload = [
        'license_key' => env('LICENSE_KEY', ''),
        'product_code' => env('PRODUCT_CODE', ''),
        'current_domain' => license_current_domain(),
        'server_ip' => license_server_ip(),
        'server_fingerprint' => license_fingerprint(),
        'install_id' => license_install_id(),
        'system_version' => app_version(),
        'client_time' => date('Y-m-d H:i:s'),
    ];

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $url = env('LICENSE_SERVER', '');
    $body = null;
    $httpCode = 0;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ]);
        $body = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
                'content' => $json,
                'timeout' => 10,
            ],
        ]);
        $body = @file_get_contents($url, false, $context);
        $httpCode = $body === false ? 0 : 200;
    }

    if ($httpCode < 200 || $httpCode >= 300 || ! is_string($body) || trim($body) === '') {
        throw new RuntimeException('授权中心暂时不可访问');
    }

    $decoded = json_decode($body, true);
    if (! is_array($decoded)) {
        throw new RuntimeException('授权中心返回内容无效');
    }

    return $decoded;
}

function license_state(bool $forceRefresh = false): array
{
    if (! license_configured()) {
        return [
            'valid' => true,
            'runtime_mode' => 'active',
            'message' => '未配置授权验证，系统按本地模式运行',
            'readonly' => false,
            'suspended' => false,
            'local_unconfigured' => true,
        ];
    }

    $cache = license_read_cache();
    $checkedAt = isset($cache['checked_at']) ? strtotime((string) $cache['checked_at']) : 0;
    if (! $forceRefresh && $cache && isset($cache['result']) && (time() - $checkedAt) < license_cache_ttl_seconds()) {
        return $cache['result'] + ['from_cache' => true];
    }

    try {
        $result = license_verify_online();
        license_write_cache($result);

        return $result + ['from_cache' => false];
    } catch (Throwable $e) {
        if ($cache && isset($cache['result']) && ($cache['result']['valid'] ?? false)) {
            return $cache['result'] + [
                'from_cache' => true,
                'message' => ($cache['result']['message'] ?? '授权正常') . '；授权中心暂时不可访问，已使用本地有效缓存',
            ];
        }

        return [
            'valid' => false,
            'runtime_mode' => 'invalid',
            'message' => $e->getMessage(),
            'readonly' => false,
            'suspended' => true,
            'from_cache' => false,
        ];
    }
}

function license_guard_request(string $method, string $path): void
{
    if (! license_configured()) {
        return;
    }

    if (in_array($path, ['/license-status', '/api/license/status'], true)) {
        return;
    }

    $state = license_state(false);
    $runtimeMode = (string) ($state['runtime_mode'] ?? 'invalid');
    $readonly = (bool) ($state['readonly'] ?? false);
    $suspended = (bool) ($state['suspended'] ?? false);
    $valid = (bool) ($state['valid'] ?? false);
    $isWrite = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);

    if ($valid && $readonly && $isWrite) {
        license_block_response($state, 423);
    }

    if (! $valid || $suspended || in_array($runtimeMode, ['suspended', 'trial_expired', 'invalid', 'banned'], true)) {
        license_block_response($state, 403);
    }
}

function license_block_response(array $state, int $statusCode): never
{
    $message = (string) ($state['message'] ?? '授权状态异常，请联系软件服务商');
    if (str_starts_with((string) ($_SERVER['REQUEST_URI'] ?? ''), '/api/')) {
        json_response([
            'success' => false,
            'message' => $message,
            'license' => [
                'valid' => (bool) ($state['valid'] ?? false),
                'runtime_mode' => $state['runtime_mode'] ?? 'invalid',
                'readonly' => (bool) ($state['readonly'] ?? false),
                'suspended' => (bool) ($state['suspended'] ?? true),
            ],
        ], $statusCode);
    }

    http_response_code($statusCode);
    $html = '<div class="empty-state"><h1>系统授权异常</h1><p>' . h($message) . '</p><p class="text-xs text-slate-400">请联系软件服务商处理授权续费、域名绑定或服务器指纹核验。</p><p class="mt-4"><a class="btn btn-primary" href="/license-status">查看授权状态</a></p></div>';
    render_public_shell('系统授权异常', $html, ['showHeader' => false]);
    exit;
}

function license_handle_status_route(string $method, string $path): bool
{
    if ($path !== '/license-status' || $method !== 'GET') {
        return false;
    }

    $state = license_state(isset($_GET['refresh']));
    $rows = [
        '授权 KEY' => mask_license_key(env('LICENSE_KEY', '')),
        '产品编码' => env('PRODUCT_CODE', ''),
        '当前域名' => license_current_domain(),
        '服务器 IP' => license_server_ip(),
        '安装码' => license_install_id(),
        '服务器指纹' => license_fingerprint(),
        '运行模式' => (string) ($state['runtime_mode'] ?? '-'),
        '验证结果' => ! empty($state['valid']) ? '通过' : '失败',
        '只读模式' => ! empty($state['readonly']) ? '是' : '否',
        '停用状态' => ! empty($state['suspended']) ? '是' : '否',
        '提示信息' => (string) ($state['message'] ?? '-'),
    ];

    $html = '<div class="card"><h1>授权状态</h1><div class="table-wrap"><table><tbody>';
    foreach ($rows as $label => $value) {
        $html .= '<tr><th>' . h($label) . '</th><td class="font-mono break-all">' . h((string) $value) . '</td></tr>';
    }
    $html .= '</tbody></table></div><p class="mt-4"><a class="btn btn-primary" href="/license-status?refresh=1">重新验证</a> <a class="btn" href="/tasks">返回系统</a></p></div>';

    render_public_shell('授权状态', $html, ['showHeader' => false]);

    return true;
}

function license_handle_api_status_route(string $method, string $path): bool
{
    if ($path !== '/api/license/status' || $method !== 'GET') {
        return false;
    }

    $state = license_state(isset($_GET['refresh']));
    json_response([
        'configured' => license_configured(),
        'domain' => license_current_domain(),
        'server_ip' => license_server_ip(),
        'install_id' => license_install_id(),
        'server_fingerprint' => license_fingerprint(),
        'state' => $state,
    ]);
}

function mask_license_key(string $key): string
{
    $key = trim($key);
    if (strlen($key) <= 8) {
        return $key === '' ? '-' : $key;
    }

    return substr($key, 0, 6) . '****' . substr($key, -4);
}
