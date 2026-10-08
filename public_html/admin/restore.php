<?php
declare(strict_types=1);

/** Двухшаговое восстановление с подробным сравнением и полным аудитом. */
require_once dirname(__DIR__) . '/include/app.php';
require_once dirname(__DIR__) . '/include/kiosk_admin.php';
require_once dirname(__DIR__) . '/include/admin_security.php';
app_require_admin();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); exit; }
app_require_csrf();
$action = (string) ($_POST['action'] ?? 'preview');
app_require_admin_password();

function backup_path(string $name): string
{
    if (preg_match('/^kiosks-before-[a-z0-9-]+-\d{8}-\d{6}-[a-f0-9]{6}\.json$/', $name) !== 1) throw new InvalidArgumentException('Недопустимое имя копии.');
    $directory = realpath(dirname(__DIR__, 2) . '/private/runtime/backups');
    $path = $directory ? realpath($directory . '/' . $name) : false;
    if (!$directory || !$path || dirname($path) !== $directory || !is_file($path)) throw new InvalidArgumentException('Копия не найдена.');
    return $path;
}

if ($action === 'confirm') {
    $token = (string) ($_POST['restore_token'] ?? '');
    $state = $_SESSION['restore_preview'] ?? null;
    if (!is_array($state) || !hash_equals((string) ($state['token'] ?? ''), $token) || (int) ($state['created_at'] ?? 0) < time() - 600) {
        http_response_code(422); exit('Предпросмотр устарел.');
    }
    try {
        $body = json_decode((string) file_get_contents(backup_path((string) $state['name'])), true, 512, JSON_THROW_ON_ERROR);
        $rows = $body['kiosks'] ?? [];
        if (!is_array($rows) || !$rows) throw new InvalidArgumentException('В копии нет автоматов.');
        $pdo = app_pdo();
        $pdo->beginTransaction();
        app_kiosk_backup($pdo, 'before-restore');
        $currentIds = array_map('intval', $pdo->query('SELECT id FROM kiosks')->fetchAll(PDO::FETCH_COLUMN));
        $pdo->exec('DELETE FROM kiosks');
        $insert = $pdo->prepare('INSERT INTO kiosks (id,machine_number,address,area,latitude,longitude,status,schedule,metro,landmark,photo_url,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $restoredIds = [];
        foreach ($rows as $row) {
            $data = app_kiosk_form_data($row);
            $photo = (string) ($row['photo_url'] ?? '');
            if ($photo !== '' && (preg_match('~^/uploads/kiosks/kiosk-[a-f0-9]{24}\.(?:jpg|png|webp)$~', $photo) !== 1 || !is_file(dirname(__DIR__) . $photo))) {
                throw new InvalidArgumentException('В копии указана отсутствующая фотография: ' . $photo);
            }
            $status = in_array((string) ($row['status'] ?? ''), ['active','maintenance','planned','hidden'], true) ? (string) $row['status'] : 'active';
            $id = (int) $row['id'];
            $insert->execute([$id,$data['number'],$data['address'],$data['area'],$data['latitude'],$data['longitude'],$status,$data['schedule'],$data['metro'],$data['landmark'],$photo,(string)($row['created_at']??date('Y-m-d H:i:s')),(string)($row['updated_at']??date('Y-m-d H:i:s'))]);
            app_kiosk_store_version($pdo, $id, 'full-restore');
            app_kiosk_audit($pdo, $id, 'restore-row');
            $restoredIds[] = $id;
        }
        foreach (array_diff($currentIds, $restoredIds) as $removedId) app_kiosk_audit($pdo, (int) $removedId, 'restore-remove');
        app_kiosk_audit($pdo, null, 'restore-complete');
        $pdo->commit();
        app_kiosk_sync_public_json($pdo);
        unset($_SESSION['restore_preview']);
        app_admin_alert('Восстановление резервной копии завершено', 'Точек: ' . count($rows));
        header('Location: /admin/backups.php?restored=' . count($rows));
        exit;
    } catch (InvalidArgumentException $error) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
        http_response_code(422); exit(app_h($error->getMessage()));
    } catch (Throwable $error) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
        error_log('kioskvoda_admin restore_failed');
        http_response_code(500); exit('Восстановление не выполнено.');
    }
}

try {
    $name = (string) ($_POST['name'] ?? '');
    $body = json_decode((string) file_get_contents(backup_path($name)), true, 512, JSON_THROW_ON_ERROR);
    $backupRows = is_array($body['kiosks'] ?? null) ? $body['kiosks'] : [];
    $currentRows = app_pdo()->query('SELECT * FROM kiosks')->fetchAll();
    $old = []; foreach ($backupRows as $row) $old[(int) $row['id']] = $row;
    $now = []; foreach ($currentRows as $row) $now[(int) $row['id']] = $row;
    $details = [];
    foreach (array_diff_key($old, $now) as $id => $row) $details[] = ['id'=>$id,'type'=>'add','label'=>$row['machine_number']?'№'.$row['machine_number']:(string)$row['address'],'fields'=>[]];
    foreach (array_diff_key($now, $old) as $id => $row) $details[] = ['id'=>$id,'type'=>'remove','label'=>$row['machine_number']?'№'.$row['machine_number']:(string)$row['address'],'fields'=>[]];
    foreach (array_intersect_key($old, $now) as $id => $row) {
        $fields = app_kiosk_changed_fields($now[$id], $row);
        if ($fields) $details[] = ['id'=>$id,'type'=>'change','label'=>$row['machine_number']?'№'.$row['machine_number']:(string)$row['address'],'fields'=>$fields];
    }
    $added = count(array_filter($details, static fn(array $item):bool=>$item['type']==='add'));
    $removed = count(array_filter($details, static fn(array $item):bool=>$item['type']==='remove'));
    $changed = count(array_filter($details, static fn(array $item):bool=>$item['type']==='change'));
    $token = bin2hex(random_bytes(24));
    $_SESSION['restore_preview'] = ['token'=>$token,'name'=>$name,'created_at'=>time()];
} catch (Throwable $error) { http_response_code(422); exit('Не удалось прочитать резервную копию.'); }
$fieldLabels=['machine_number'=>'номер','address'=>'адрес','area'=>'район','latitude'=>'широта','longitude'=>'долгота','schedule'=>'режим','metro'=>'метро','landmark'=>'ориентир','photo_url'=>'фото'];
?><!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Восстановление — Киосквода</title><link rel="stylesheet" href="/css/admin.css?v=20260901-1"></head><body>
<header class="admin-header"><div><strong>Киосквода</strong><span>Предпросмотр восстановления</span></div><nav><a href="/admin/backups.php">Отменить</a></nav></header>
<main class="admin-page"><div class="admin-page__heading"><div><p>Копия <?=app_h((string)($body['created_at']??''))?></p><h1>Что именно изменится</h1></div><span><?=count($backupRows)?> автоматов в копии</span></div>
<div class="import-summary"><div><strong><?=$added?></strong><span>вернётся из копии</span></div><div><strong><?=$changed?></strong><span>будет заменено</span></div><div class="has-errors"><strong><?=$removed?></strong><span>будет удалено</span></div></div>
<?php if($details):?><div class="admin-table-wrap"><table><thead><tr><th>Точка</th><th>Действие</th><th>Поля</th></tr></thead><tbody><?php foreach($details as $item):?><tr><td><?=app_h($item['label'])?></td><td><?=app_h(['add'=>'Вернуть','remove'=>'Удалить','change'=>'Заменить'][$item['type']])?></td><td><?=app_h($item['fields']?implode(', ',array_map(static fn(string $field):string=>$fieldLabels[$field]??$field,$item['fields'])):'—')?></td></tr><?php endforeach;?></tbody></table></div><?php else:?><p class="admin-success">Текущая база уже совпадает с этой копией.</p><?php endif;?>
<form class="confirm-bar" method="post" data-confirm="Восстановить всю таблицу? Текущие данные сначала сохранятся в новую копию."><input type="hidden" name="csrf" value="<?=app_h(app_csrf())?>"><input type="hidden" name="action" value="confirm"><input type="hidden" name="restore_token" value="<?=app_h($token)?>"><a href="/admin/backups.php">Отменить</a><button class="danger"<?=$details?'':' disabled'?>>Восстановить копию</button></form></main><script src="/js/admin-confirm.js?v=20260831-1" defer></script></body></html>
