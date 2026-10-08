<?php
declare(strict_types=1);

/** Пакетные изменения: меняет, версионирует и журналирует только реальные отличия. */
require_once dirname(__DIR__) . '/include/app.php';
require_once dirname(__DIR__) . '/include/kiosk_admin.php';
require_once dirname(__DIR__) . '/include/admin_security.php';
app_require_admin();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); exit; }
app_require_csrf();
app_require_admin_password();

$ids = array_values(array_unique(array_filter(array_map('intval', explode(',', (string) ($_POST['ids'] ?? ''))), static fn (int $id): bool => $id > 0)));
$action = (string) ($_POST['action'] ?? '');
$value = trim((string) ($_POST['value'] ?? ''));
if (!$ids || count($ids) > 500) { http_response_code(422); exit('Выберите от 1 до 500 автоматов.'); }
if (!in_array($action, ['coordinates','area','schedule','clear_landmark','delete_photos'], true)) { http_response_code(422); exit('Неизвестное действие.'); }
if ($action === 'area' && ($value === '' || mb_strlen($value) > 100)) { http_response_code(422); exit('Укажите район до 100 символов.'); }
if ($action === 'schedule' && ($value === '' || mb_strlen($value) > 100)) { http_response_code(422); exit('Укажите режим работы до 100 символов.'); }

try {
    $pdo = app_pdo();
    $pdo->beginTransaction();
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $select = $pdo->prepare("SELECT * FROM kiosks WHERE id IN ($placeholders) FOR UPDATE");
    $select->execute($ids);
    $current = [];
    foreach ($select->fetchAll() as $row) $current[(int) $row['id']] = $row;
    $coordinates = $action === 'coordinates' ? json_decode((string) ($_POST['coordinates'] ?? ''), true) : [];
    if ($action === 'coordinates' && !is_array($coordinates)) throw new InvalidArgumentException('Нет изменённых координат.');

    $changes = [];
    foreach ($ids as $id) {
        if (!isset($current[$id])) continue;
        $before = $current[$id];
        $after = $before;
        if ($action === 'coordinates') {
            $point = $coordinates[(string) $id] ?? null;
            $latitude = filter_var($point[0] ?? null, FILTER_VALIDATE_FLOAT);
            $longitude = filter_var($point[1] ?? null, FILTER_VALIDATE_FLOAT);
            if ($latitude === false || $longitude === false || $latitude < 59 || $latitude > 61 || $longitude < 29 || $longitude > 32) continue;
            $after['latitude'] = $latitude;
            $after['longitude'] = $longitude;
        } elseif ($action === 'area') $after['area'] = $value;
        elseif ($action === 'schedule') $after['schedule'] = $value;
        elseif ($action === 'clear_landmark') $after['landmark'] = '';
        elseif ($action === 'delete_photos') $after['photo_url'] = '';
        $fields = app_kiosk_changed_fields($before, $after);
        if ($fields) $changes[$id] = ['after' => $after, 'fields' => $fields];
    }
    if (!$changes) throw new InvalidArgumentException('Выбранные данные не изменились.');

    app_kiosk_backup($pdo, 'batch-' . $action);
    $update = $pdo->prepare('UPDATE kiosks SET area=?,latitude=?,longitude=?,schedule=?,landmark=?,photo_url=?,updated_at=CURRENT_TIMESTAMP WHERE id=?');
    foreach ($changes as $id => $change) {
        $after = $change['after'];
        $update->execute([$after['area'],$after['latitude'],$after['longitude'],$after['schedule'],$after['landmark'],$after['photo_url'],$id]);
        app_kiosk_store_version($pdo, $id, 'batch-' . $action);
        app_kiosk_audit($pdo, $id, 'batch-' . $action);
    }
    $pdo->commit();
    app_kiosk_sync_public_json($pdo);

    $changedIds = array_map('intval', array_keys($changes));
    $_SESSION['batch_result'] = [
        'created_at' => time(), 'action' => $action, 'ids' => $changedIds,
        'labels' => array_map(static function (int $id) use ($current): string {
            $row = $current[$id];
            return !empty($row['machine_number']) ? '№' . $row['machine_number'] : (string) $row['address'];
        }, $changedIds),
    ];
    app_admin_alert('Пакетное изменение автоматов', 'Действие: ' . $action . ', точек: ' . count($changes));
    header('Location: /admin/map.php?saved=' . count($changes));
    exit;
} catch (InvalidArgumentException $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(422); exit(app_h($error->getMessage()));
} catch (Throwable $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    error_log('kioskvoda_admin batch_failed');
    http_response_code(500); exit('Пакетное изменение не выполнено.');
}
