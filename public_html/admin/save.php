<?php
declare(strict_types=1);

/** Создание/обновление автомата вместе с backup, обработкой фото и записью аудита. */

require_once dirname(__DIR__) . '/include/app.php';
require_once dirname(__DIR__) . '/include/kiosk_admin.php';
app_require_admin();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit;
}
app_require_csrf();

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0;

try {
    $data = app_kiosk_form_data($_POST);
    $pdo = app_pdo();
    $pdo->beginTransaction();
    app_kiosk_backup($pdo, $id ? 'update' : 'create');

    $currentPhoto = '';
    if ($id) {
        $lookup = $pdo->prepare('SELECT photo_url FROM kiosks WHERE id = ? FOR UPDATE');
        $lookup->execute([$id]);
        $currentPhoto = (string) ($lookup->fetchColumn() ?: '');
    }
    $photo = app_kiosk_store_photo($_FILES['photo'] ?? [], $currentPhoto);

    $values = [
        $data['number'], $data['address'], $data['area'], $data['latitude'],
        $data['longitude'], $data['schedule'], $data['metro'], $data['landmark'], $photo,
    ];
    if ($id) {
        $statement = $pdo->prepare(
            'UPDATE kiosks SET machine_number=?,address=?,area=?,latitude=?,longitude=?,schedule=?,metro=?,landmark=?,photo_url=?,updated_at=CURRENT_TIMESTAMP WHERE id=?'
        );
        $statement->execute([...$values, $id]);
        $action = 'update';
    } else {
        $statement = $pdo->prepare(
            'INSERT INTO kiosks (machine_number,address,area,latitude,longitude,schedule,metro,landmark,photo_url) VALUES (?,?,?,?,?,?,?,?,?)'
        );
        $statement->execute($values);
        $id = (int) $pdo->lastInsertId();
        $action = 'create';
    }

    $config = app_config();
    $auditKey = (string) ($config['admin']['audit_key'] ?? $config['admin']['password_hash'] ?? '');
    $audit = $pdo->prepare('INSERT INTO kiosk_audit (kiosk_id,action,admin_name,ip_hash) VALUES (?,?,?,?)');
    $audit->execute([
        $id,
        $action,
        (string) ($config['admin']['username'] ?? 'admin'),
        hash_hmac('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? ''), $auditKey),
    ]);

    // Снимок создаётся внутри той же транзакции: история не расходится с карточкой.
    app_kiosk_store_version($pdo, $id, $action);

    $pdo->commit();
    app_kiosk_clear_api_cache();
    header('Location: /admin/?saved=1&edit=' . $id);
    exit;
} catch (InvalidArgumentException $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(422);
    exit(app_h($error->getMessage()));
} catch (Throwable $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('kioskvoda_admin save_failed');
    http_response_code(500);
    exit('Не удалось сохранить. Проверьте, что миграция базы выполнена.');
}
