<?php
declare(strict_types=1);

/** Закрытый endpoint: выгружает таблицу автоматов в JSON для ручной резервной копии. */
require_once dirname(__DIR__).'/include/app.php';
app_require_admin();
$rows=app_pdo()->query('SELECT * FROM kiosks ORDER BY id')->fetchAll();
$body=json_encode(['version'=>1,'created_at'=>date(DATE_ATOM),'kiosks'=>$rows],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
header('Content-Type: application/json; charset=UTF-8');
header('Content-Disposition: attachment; filename="kiosks-backup-'.date('Y-m-d-His').'.json"');
header('X-Content-Type-Options: nosniff');
echo $body;
