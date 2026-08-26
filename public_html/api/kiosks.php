<?php
declare(strict_types=1);

/** Публичный read-only API карты: только видимые автоматы, rate limit, ETag и кеш. */

require_once dirname(__DIR__) . '/include/app.php';
require_once dirname(__DIR__) . '/include/request_security.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=60, stale-while-revalidate=300');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$clientAddress = filter_var((string) ($_SERVER['REMOTE_ADDR'] ?? ''), FILTER_VALIDATE_IP) ?: 'unknown';
try {
    if (app_rate_limit('api-kiosks', $clientAddress, 180, 60)) {
        http_response_code(429);
        header('Retry-After: 60');
        echo json_encode(['error' => 'Too many requests']);
        exit;
    }
} catch (Throwable $error) {
    error_log('kioskvoda_api security_storage_unavailable');
    http_response_code(503);
    echo json_encode(['error' => 'Service temporarily unavailable']);
    exit;
}

$cachePath = app_private_runtime_directory() . '/kiosks-api.json';
$body = '';
if (is_file($cachePath) && (time() - (int) filemtime($cachePath)) <= 60) {
    $cached = file_get_contents($cachePath);
    if (is_string($cached)) $body = $cached;
}

if ($body === '') {
    try {
        $rows = app_pdo()->query("SELECT id,machine_number,address,area,latitude,longitude,schedule,metro,landmark,photo_url,updated_at FROM kiosks WHERE status <> 'hidden' ORDER BY machine_number IS NULL,machine_number,address")->fetchAll();
        $body = json_encode(['version' => 2, 'source' => 'database', 'kiosks' => $rows], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $temporary = $cachePath . '.' . bin2hex(random_bytes(6)) . '.tmp';
        if (file_put_contents($temporary, $body, LOCK_EX) !== false) {
            @chmod($temporary, 0600);
            rename($temporary, $cachePath);
        }
    } catch (Throwable $error) {
        error_log('kioskvoda_api database_unavailable');
        $fallback = dirname(__DIR__) . '/data/kiosks.json';
        if (!is_file($fallback) || !is_readable($fallback)) {
            http_response_code(503);
            echo json_encode(['error' => 'Kiosk data unavailable']);
            exit;
        }
        $body = (string) file_get_contents($fallback);
    }
}

$etag = '"' . hash('sha256', $body) . '"';
header('ETag: ' . $etag);
if (trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
    http_response_code(304);
    exit;
}
echo $body;
