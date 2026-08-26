<?php
declare(strict_types=1);

/** Двухшаговый импорт: сначала показывает план, затем применяет подтверждённые строки. */
require_once dirname(__DIR__) . '/include/app.php';
require_once dirname(__DIR__) . '/include/kiosk_admin.php';
require_once dirname(__DIR__) . '/include/admin_security.php';
app_require_admin();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); exit; }
app_require_csrf();
$action = (string) ($_POST['action'] ?? 'preview');

if ($action === 'confirm') {
    app_require_admin_password();
    $token = (string) ($_POST['preview_token'] ?? '');
    $preview = $_SESSION['csv_preview'] ?? null;
    if (!is_array($preview) || !hash_equals((string) ($preview['token'] ?? ''), $token) || (int) ($preview['created_at'] ?? 0) < time() - 900) {
        http_response_code(422); exit('Предпросмотр устарел. Загрузите CSV ещё раз.');
    }
    $rows = is_array($preview['rows'] ?? null) ? $preview['rows'] : [];
    if (!$rows) { http_response_code(422); exit('Нет строк для импорта.'); }
    try {
        $pdo = app_pdo();
        $pdo->beginTransaction();
        app_kiosk_backup($pdo, 'import');
        $statement = $pdo->prepare(
            'INSERT INTO kiosks (machine_number,address,area,latitude,longitude,schedule,metro,landmark,photo_url) VALUES (?,?,?,?,?,?,?,?,?) '
            . 'ON DUPLICATE KEY UPDATE address=VALUES(address),area=VALUES(area),latitude=VALUES(latitude),longitude=VALUES(longitude),'
            . 'schedule=VALUES(schedule),metro=VALUES(metro),landmark=VALUES(landmark),photo_url=IF(VALUES(photo_url)="",photo_url,VALUES(photo_url))'
        );
        $findId = $pdo->prepare('SELECT id FROM kiosks WHERE machine_number = ?');
        foreach ($rows as $row) {
            $statement->execute([$row['number'],$row['address'],$row['area'],$row['latitude'],$row['longitude'],$row['schedule'],$row['metro'],$row['landmark'],$row['photo_url']]);
            $findId->execute([$row['number']]);
            app_kiosk_store_version($pdo, (int) $findId->fetchColumn(), 'import');
        }
        $pdo->commit();
        if (count($rows) >= 20) app_admin_alert('Массовый импорт CSV', 'Строк: '.count($rows));
        unset($_SESSION['csv_preview']);
        app_kiosk_clear_api_cache();
        header('Location: /admin/?imported=' . count($rows)); exit;
    } catch (Throwable $error) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
        error_log('kioskvoda_admin import_failed');
        http_response_code(500); exit('Импорт не выполнен. Проверьте миграцию базы.');
    }
}

$file = $_FILES['csv'] ?? null;
$validUpload = is_array($file)
    && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK
    && (int) ($file['size'] ?? 0) >= 1 && (int) $file['size'] <= 2 * 1024 * 1024
    && strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION)) === 'csv'
    && is_uploaded_file((string) ($file['tmp_name'] ?? ''));
if (!$validUpload) { http_response_code(422); exit('Выберите настоящий CSV-файл размером до 2 МБ.'); }

$handle = fopen((string) $file['tmp_name'], 'rb');
if (!$handle) { http_response_code(422); exit('Не удалось прочитать CSV.'); }
$header = fgetcsv($handle, 0, ';', '"', '');
if (!$header) exit('CSV пуст.');
$header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
$expected = ['Номер','Адрес','Город/район','Широта','Долгота','Режим работы'];
if (array_slice($header, 0, 6) !== $expected) { http_response_code(422); exit('Используйте CSV, скачанный из этой админки.'); }

$rows = []; $errors = []; $line = 1;
while (($values = fgetcsv($handle, 0, ';', '"', '')) !== false) {
    $line++;
    if (count($values) < 6) { $errors[]=['line'=>$line,'message'=>'Недостаточно колонок']; continue; }
    try {
        $data = app_kiosk_form_data([
            'machine_number'=>$values[0], 'address'=>$values[1], 'area'=>$values[2],
            'latitude'=>$values[3], 'longitude'=>$values[4], 'schedule'=>$values[5],
            'metro'=>$values[6] ?? '', 'landmark'=>$values[7] ?? '',
        ]);
        if ($data['number'] === null) throw new InvalidArgumentException('Не указан номер автомата');
        $photo = trim((string) ($values[8] ?? ''));
        if ($photo !== '' && preg_match('~^/uploads/kiosks/kiosk-[a-f0-9]{24}\.(?:jpg|png|webp)$~', $photo) !== 1) throw new InvalidArgumentException('Недопустимый путь фотографии');
        $rows[] = $data + ['photo_url'=>$photo,'line'=>$line];
    } catch (InvalidArgumentException $error) {
        $errors[]=['line'=>$line,'message'=>$error->getMessage()];
    }
    if (count($rows)+count($errors)>1000) { fclose($handle); http_response_code(422); exit('В одном файле допускается не более 1000 строк.'); }
}
fclose($handle);
if (!$rows) { http_response_code(422); exit('В CSV нет корректных строк.'); }

$existing=[];
foreach(app_pdo()->query('SELECT * FROM kiosks WHERE machine_number IS NOT NULL')->fetchAll() as $item) $existing[(int)$item['machine_number']]=$item;
$stats=['add'=>0,'update'=>0,'same'=>0];
$fields=['address','area','latitude','longitude','schedule','metro','landmark'];
foreach($rows as &$row){
    $old=$existing[(int)$row['number']]??null;
    if(!$old)$row['result']='add';
    else{
        $changed=false;
        foreach($fields as $field)if((string)$old[$field] !== (string)$row[$field])$changed=true;
        if($row['photo_url']!==''&&(string)$old['photo_url']!==$row['photo_url'])$changed=true;
        $row['result']=$changed?'update':'same';
    }
    $stats[$row['result']]++;
}
unset($row);
$token=bin2hex(random_bytes(24));
$_SESSION['csv_preview']=['token'=>$token,'created_at'=>time(),'rows'=>$rows];
?><!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Проверка CSV — Киосквода</title><link rel="stylesheet" href="/css/admin.css?v=20260816-3"></head><body>
<header class="admin-header"><div><strong>Киосквода</strong><span>Проверка CSV</span></div><nav><a href="/admin/">Отменить</a></nav></header>
<main class="admin-page"><div class="admin-page__heading"><div><p>Предпросмотр импорта</p><h1>Проверьте изменения</h1></div><span><?=count($rows)?> корректных строк</span></div>
<div class="import-summary"><div><strong><?=$stats['add']?></strong><span>будет добавлено</span></div><div><strong><?=$stats['update']?></strong><span>будет изменено</span></div><div><strong><?=$stats['same']?></strong><span>без изменений</span></div><div class="<?=count($errors)?'has-errors':''?>"><strong><?=count($errors)?></strong><span>ошибок</span></div></div>
<?php if($errors):?><details class="import-errors" open><summary>Строки с ошибками</summary><ul><?php foreach($errors as $error):?><li>Строка <?=(int)$error['line']?>: <?=app_h((string)$error['message'])?></li><?php endforeach;?></ul></details><?php endif;?>
<div class="admin-table-wrap"><table><thead><tr><th>Строка</th><th>Результат</th><th>№</th><th>Адрес</th><th>Район</th></tr></thead><tbody><?php foreach($rows as $row):?><tr><td><?=(int)$row['line']?></td><td><span class="result-badge result-badge--<?=app_h($row['result'])?>"><?=app_h(['add'=>'Добавить','update'=>'Изменить','same'=>'Без изменений'][$row['result']])?></span></td><td><?=(int)$row['number']?></td><td><?=app_h($row['address'])?></td><td><?=app_h($row['area'])?></td></tr><?php endforeach;?></tbody></table></div>
<form class="confirm-bar" method="post"><input type="hidden" name="csrf" value="<?=app_h(app_csrf())?>"><input type="hidden" name="action" value="confirm"><input type="hidden" name="preview_token" value="<?=app_h($token)?>"><label>Повторите пароль<input type="password" name="admin_password" required autocomplete="current-password"></label><a href="/admin/">Отменить</a><button type="submit">Применить <?=count($rows)?> строк</button></form></main></body></html>
