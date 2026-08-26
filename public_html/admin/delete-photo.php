<?php
declare(strict_types=1);

/** Удаляет только неиспользуемый файл с ожидаемым случайным именем внутри uploads/kiosks. */
require_once dirname(__DIR__) . '/include/app.php';
app_require_admin();
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);exit;}app_require_csrf();
$name=(string)($_POST['name']??'');if(preg_match('/^kiosk-[a-f0-9]{24}\.(jpg|png|webp)$/',$name)!==1){http_response_code(422);exit('Недопустимое имя.');}
$check=app_pdo()->prepare('SELECT COUNT(*) FROM kiosks WHERE photo_url=?');$check->execute(['/uploads/kiosks/'.$name]);if((int)$check->fetchColumn()>0){http_response_code(409);exit('Фотография используется карточкой.');}
$directory=realpath(dirname(__DIR__).'/uploads/kiosks');$path=$directory?realpath($directory.'/'.$name):false;if(!$directory||!$path||dirname($path)!==$directory||!is_file($path)){http_response_code(404);exit('Файл не найден.');}if(!unlink($path)){http_response_code(500);exit('Не удалось удалить файл.');}header('Location: /admin/photos.php?deleted=1');
