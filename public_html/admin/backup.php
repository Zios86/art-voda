<?php
declare(strict_types=1);

/** Полная ручная копия: JSON базы, используемые фотографии и контрольные суммы. */
require_once dirname(__DIR__) . '/include/app.php';
app_require_admin();
if (!class_exists(ZipArchive::class)) {
    http_response_code(503);
    exit('На сервере не включено расширение ZipArchive. Полная копия не создана.');
}

$rows = app_pdo()->query('SELECT * FROM kiosks ORDER BY id')->fetchAll();
$database = json_encode(['version'=>2,'created_at'=>date(DATE_ATOM),'kiosks'=>$rows], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR) . PHP_EOL;
$temporary = tempnam(sys_get_temp_dir(), 'kioskvoda-backup-');
if (!is_string($temporary)) { http_response_code(500); exit('Не удалось подготовить архив.'); }
$zip = new ZipArchive();
if ($zip->open($temporary, ZipArchive::OVERWRITE) !== true) { @unlink($temporary); http_response_code(500); exit('Не удалось создать архив.'); }

$manifest = ['created_at'=>date(DATE_ATOM),'database_sha256'=>hash('sha256',$database),'photos'=>[]];
$zip->addFromString('database/kiosks.json', $database);
$uploads = realpath(dirname(__DIR__) . '/uploads/kiosks');
foreach ($rows as $row) {
    $url = (string) ($row['photo_url'] ?? '');
    if ($url === '') continue;
    $name = basename($url);
    if (preg_match('/^kiosk-[a-f0-9]{24}\.(jpg|png|webp)$/', $name) !== 1) continue;
    $path = $uploads ? realpath($uploads . '/' . $name) : false;
    if (!$path || dirname($path) !== $uploads || !is_file($path)) {
        $zip->close(); @unlink($temporary); http_response_code(409); exit('Полная копия остановлена: не найдена фотография ' . app_h($name));
    }
    $manifest['photos'][$name] = hash_file('sha256', $path);
    $zip->addFile($path, 'photos/' . $name);
}
$zip->addFromString('manifest.json', json_encode($manifest, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR) . PHP_EOL);
$zip->close();
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="kioskvoda-full-backup-' . date('Y-m-d-His') . '.zip"');
header('Content-Length: ' . filesize($temporary));
header('X-Content-Type-Options: nosniff');
readfile($temporary);
@unlink($temporary);
