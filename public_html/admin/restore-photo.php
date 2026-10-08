<?php
declare(strict_types=1);

/** Возвращает фотографию из закрытой 30-дневной корзины. */
require_once dirname(__DIR__) . '/include/app.php';
require_once dirname(__DIR__) . '/include/kiosk_admin.php';
require_once dirname(__DIR__) . '/include/admin_security.php';
app_require_admin();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); exit; }
app_require_csrf();

$trashName = (string) ($_POST['name'] ?? '');
if (preg_match('/^\d{8}-\d{6}-[a-f0-9]{6}-(kiosk-[a-f0-9]{24}\.(?:jpg|png|webp))$/', $trashName, $match) !== 1) {
    http_response_code(422); exit('Недопустимое имя файла.');
}
$trashDirectory = realpath(dirname(__DIR__, 2) . '/private/runtime/photo-trash');
$source = $trashDirectory ? realpath($trashDirectory . '/' . $trashName) : false;
$uploads = dirname(__DIR__) . '/uploads/kiosks';
if (!$trashDirectory || !$source || dirname($source) !== $trashDirectory || !is_file($source)) { http_response_code(404); exit('Фотография в корзине не найдена.'); }
if (!is_dir($uploads) && !mkdir($uploads, 0755, true) && !is_dir($uploads)) { http_response_code(500); exit('Каталог фотографий недоступен.'); }
$destination = $uploads . '/' . $match[1];
if (file_exists($destination)) { http_response_code(409); exit('Файл с таким именем уже существует.'); }
if (!rename($source, $destination)) { http_response_code(500); exit('Не удалось восстановить фотографию.'); }
@chmod($destination, 0644);
app_security_log('photo_restored', (string) (app_config()['admin']['username'] ?? 'admin'), app_client_address(), ['photo' => hash('sha256', $match[1])]);
header('Location: /admin/photos.php?restored=1');
