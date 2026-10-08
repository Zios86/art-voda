<?php
declare(strict_types=1);

/** Перемещает свободную фотографию в закрытую корзину на 30 дней. */
require_once dirname(__DIR__) . '/include/app.php';
require_once dirname(__DIR__) . '/include/kiosk_admin.php';
require_once dirname(__DIR__) . '/include/admin_security.php';
app_require_admin();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit;
}
app_require_csrf();

$name = (string) ($_POST['name'] ?? '');
if (preg_match('/^kiosk-[a-f0-9]{24}\.(jpg|png|webp)$/', $name) !== 1) {
    http_response_code(422);
    exit('Недопустимое имя.');
}

$photoUrl = '/uploads/kiosks/' . $name;
$reference = app_kiosk_photo_reference(app_pdo(), $photoUrl);
if ($reference !== null) {
    http_response_code(409);
    exit($reference);
}

$directory = realpath(dirname(__DIR__) . '/uploads/kiosks');
$path = $directory ? realpath($directory . '/' . $name) : false;
if (!$directory || !$path || dirname($path) !== $directory || !is_file($path)) {
    http_response_code(404);
    exit('Файл не найден.');
}

try {
    app_kiosk_trash_photo($path, $name);
    app_security_log('photo_trashed', (string) (app_config()['admin']['username'] ?? 'admin'), app_client_address(), ['photo' => hash('sha256', $name)]);
} catch (Throwable $error) {
    error_log('kioskvoda_admin photo_trash_failed');
    http_response_code(500);
    exit('Не удалось переместить файл в корзину.');
}
header('Location: /admin/photos.php?deleted=1');
