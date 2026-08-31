<?php
declare(strict_types=1);

/** Пакетные изменения выбранных автоматов с backup, транзакцией, аудитом и историей. */
require_once dirname(__DIR__) . '/include/app.php';
require_once dirname(__DIR__) . '/include/kiosk_admin.php';
require_once dirname(__DIR__) . '/include/admin_security.php';
app_require_admin();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); exit; }
app_require_csrf();
app_require_admin_password();
$ids = array_values(array_unique(array_filter(array_map('intval', explode(',', (string)($_POST['ids'] ?? ''))), static fn(int $id):bool => $id > 0)));
$action = (string)($_POST['action'] ?? '');
$value = trim((string)($_POST['value'] ?? ''));
if (!$ids || count($ids) > 500) { http_response_code(422); exit('Выберите от 1 до 500 автоматов.'); }
$allowed = ['coordinates','area','schedule','clear_landmark','delete_photos'];
if (!in_array($action,$allowed,true)) { http_response_code(422); exit('Неизвестное действие.'); }
try {
    $pdo=app_pdo(); $pdo->beginTransaction(); app_kiosk_backup($pdo,'batch-'.$action);
    $placeholders=implode(',',array_fill(0,count($ids),'?'));
    $affectedIds=$ids;
    if($action==='coordinates'){
        $decoded=json_decode((string)($_POST['coordinates']??''),true);
        if(!is_array($decoded))throw new InvalidArgumentException('Нет изменённых координат.');
        $currentStatement=$pdo->prepare("SELECT id,latitude,longitude FROM kiosks WHERE id IN ($placeholders)");
        $currentStatement->execute($ids);$current=[];foreach($currentStatement->fetchAll() as $row)$current[(int)$row['id']]=$row;
        $statement=$pdo->prepare('UPDATE kiosks SET latitude=?,longitude=?,updated_at=CURRENT_TIMESTAMP WHERE id=?');
        $affectedIds=[];foreach($ids as $id){$point=$decoded[(string)$id]??null;$lat=filter_var($point[0]??null,FILTER_VALIDATE_FLOAT);$lng=filter_var($point[1]??null,FILTER_VALIDATE_FLOAT);if($lat===false||$lng===false||$lat<59||$lat>61||$lng<29||$lng>32||!isset($current[$id]))continue;if(abs((float)$current[$id]['latitude']-(float)$lat)<0.0000001&&abs((float)$current[$id]['longitude']-(float)$lng)<0.0000001)continue;$statement->execute([$lat,$lng,$id]);$affectedIds[]=$id;}
        if(!$affectedIds)throw new InvalidArgumentException('Координаты не изменились.');
    }elseif($action==='area'){
        if($value===''||mb_strlen($value)>100)throw new InvalidArgumentException('Укажите район до 100 символов.');
        $pdo->prepare("UPDATE kiosks SET area=?,updated_at=CURRENT_TIMESTAMP WHERE id IN ($placeholders)")->execute([$value,...$ids]);
    }elseif($action==='schedule'){
        if($value===''||mb_strlen($value)>100)throw new InvalidArgumentException('Укажите режим работы до 100 символов.');
        $pdo->prepare("UPDATE kiosks SET schedule=?,updated_at=CURRENT_TIMESTAMP WHERE id IN ($placeholders)")->execute([$value,...$ids]);
    }elseif($action==='clear_landmark')$pdo->prepare("UPDATE kiosks SET landmark='',updated_at=CURRENT_TIMESTAMP WHERE id IN ($placeholders)")->execute($ids);
    elseif($action==='delete_photos')$pdo->prepare("UPDATE kiosks SET photo_url='',updated_at=CURRENT_TIMESTAMP WHERE id IN ($placeholders)")->execute($ids);
    $config=app_config();$auditKey=(string)($config['admin']['audit_key']??$config['admin']['password_hash']??'');$audit=$pdo->prepare('INSERT INTO kiosk_audit (kiosk_id,action,admin_name,ip_hash) VALUES (?,?,?,?)');
    foreach($affectedIds as $id){app_kiosk_store_version($pdo,$id,'batch-'.$action);$audit->execute([$id,'batch-'.$action,(string)($config['admin']['username']??'admin'),hash_hmac('sha256',(string)($_SERVER['REMOTE_ADDR']??''),$auditKey)]);}
    $pdo->commit();app_admin_alert('Пакетное изменение автоматов','Действие: '.$action.', точек: '.count($affectedIds));app_kiosk_clear_api_cache();header('Location: /admin/map.php?saved='.count($affectedIds));
}catch(InvalidArgumentException $error){if(isset($pdo)&&$pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();http_response_code(422);exit(app_h($error->getMessage()));}catch(Throwable $error){if(isset($pdo)&&$pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();error_log('kioskvoda_admin batch_failed');http_response_code(500);exit('Пакетное изменение не выполнено.');}
