<?php
declare(strict_types=1);

/** Восстанавливает выбранный снимок карточки в транзакции с backup и аудитом. */
require_once dirname(__DIR__) . '/include/app.php';
require_once dirname(__DIR__) . '/include/kiosk_admin.php';
app_require_admin();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit;
}
app_require_csrf();
app_require_admin_password();
$versionId = filter_input(INPUT_POST, 'version_id', FILTER_VALIDATE_INT) ?: 0;

try {
    $pdo = app_pdo();
    $pdo->beginTransaction();
    $query = $pdo->prepare('SELECT kiosk_id,snapshot_json FROM kiosk_versions WHERE id = ? FOR UPDATE');
    $query->execute([$versionId]);
    $version = $query->fetch();
    $snapshot = $version ? json_decode((string) $version['snapshot_json'], true) : null;
    if (!is_array($snapshot)) throw new InvalidArgumentException('Версия не найдена.');
    $kioskId = (int) $version['kiosk_id'];
    $data = app_kiosk_form_data([
        'machine_number' => $snapshot['machine_number'] ?? '', 'address' => $snapshot['address'] ?? '',
        'area' => $snapshot['area'] ?? '', 'latitude' => $snapshot['latitude'] ?? '',
        'longitude' => $snapshot['longitude'] ?? '', 'schedule' => $snapshot['schedule'] ?? '',
        'metro' => $snapshot['metro'] ?? '', 'landmark' => $snapshot['landmark'] ?? '',
    ]);
    app_kiosk_backup($pdo, 'rollback');
    $update = $pdo->prepare('UPDATE kiosks SET machine_number=?,address=?,area=?,latitude=?,longitude=?,schedule=?,metro=?,landmark=?,photo_url=?,updated_at=CURRENT_TIMESTAMP WHERE id=?');
    $update->execute([$data['number'],$data['address'],$data['area'],$data['latitude'],$data['longitude'],$data['schedule'],$data['metro'],$data['landmark'],(string)($snapshot['photo_url'] ?? ''),$kioskId]);
    $config = app_config();
    $auditKey = (string) ($config['admin']['audit_key'] ?? $config['admin']['password_hash'] ?? '');
    $audit = $pdo->prepare('INSERT INTO kiosk_audit (kiosk_id,action,admin_name,ip_hash) VALUES (?,?,?,?)');
    $audit->execute([$kioskId,'rollback',(string)($config['admin']['username'] ?? 'admin'),hash_hmac('sha256',(string)($_SERVER['REMOTE_ADDR'] ?? ''),$auditKey)]);
    app_kiosk_store_version($pdo, $kioskId, 'rollback');
    $pdo->commit();
    app_kiosk_clear_api_cache();
    header('Location: /admin/history.php?id='.$kioskId.'&restored=1');
} catch (InvalidArgumentException $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(422);
    exit(app_h($error->getMessage()));
} catch (Throwable $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    error_log('kioskvoda_admin rollback_failed');
    http_response_code(500);
    exit('Откат не выполнен. Проверьте миграцию базы.');
}
