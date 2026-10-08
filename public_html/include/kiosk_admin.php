<?php
declare(strict_types=1);

/** Общие операции с автоматами: backup, кеш API, безопасные фото и валидация. */

function app_kiosk_backup(PDO $pdo, string $reason = 'change'): string
{
    $directory = dirname(__DIR__, 2) . '/private/runtime/backups';
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Backup directory unavailable');
    }

    $body = json_encode([
        'created_at' => date(DATE_ATOM),
        'reason' => $reason,
        'kiosks' => $pdo->query('SELECT * FROM kiosks ORDER BY id')->fetchAll(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);

    $path = $directory . '/kiosks-before-' . preg_replace('/[^a-z0-9-]/', '', strtolower($reason))
        . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.json';
    if (file_put_contents($path, $body, LOCK_EX) === false) {
        throw new RuntimeException('Backup failed');
    }
    chmod($path, 0600);
    if ($reason === 'before-restore' && function_exists('app_admin_alert')) app_admin_alert('Запущено восстановление резервной копии', 'Создана страховочная копия перед операцией');

    $files = glob($directory . '/kiosks-before-*.json') ?: [];
    usort($files, static function (string $first, string $second): int {
        return ((int) filemtime($second)) <=> ((int) filemtime($first));
    });
    foreach (array_slice($files, 30) as $oldFile) {
        if (!unlink($oldFile)) {
            error_log('kioskvoda_admin old_backup_cleanup_failed');
        }
    }
    return $path;
}

/** Возвращает только публичные поля, одинаковые для API и аварийного JSON. */
function app_kiosk_public_rows(PDO $pdo): array
{
    return $pdo->query(
        "SELECT id,machine_number,address,area,latitude,longitude,schedule,metro,landmark,photo_url,updated_at "
        . "FROM kiosks WHERE status <> 'hidden' ORDER BY machine_number IS NULL,machine_number,address"
    )->fetchAll();
}

/** Атомарно обновляет аварийный список после успешного изменения базы. */
function app_kiosk_sync_public_json(PDO $pdo): void
{
    $path = dirname(__DIR__) . '/data/kiosks.json';
    $body = json_encode([
        'version' => 2,
        'generated_at' => date(DATE_ATOM),
        'source' => 'database',
        'kiosks' => app_kiosk_public_rows($pdo),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    $temporary = $path . '.' . bin2hex(random_bytes(6)) . '.tmp';
    if (file_put_contents($temporary, $body . PHP_EOL, LOCK_EX) === false) {
        throw new RuntimeException('Public kiosk data cannot be written');
    }
    @chmod($temporary, 0644);
    if (!rename($temporary, $path)) {
        @unlink($temporary);
        throw new RuntimeException('Public kiosk data cannot be replaced');
    }
    app_kiosk_clear_api_cache();
}

/** Унифицированная запись событий изменения данных. */
function app_kiosk_audit(PDO $pdo, ?int $kioskId, string $action): void
{
    $config = app_config();
    $auditKey = (string) ($config['admin']['audit_key'] ?? $config['admin']['password_hash'] ?? 'audit');
    $statement = $pdo->prepare('INSERT INTO kiosk_audit (kiosk_id,action,admin_name,ip_hash) VALUES (?,?,?,?)');
    $statement->execute([
        $kioskId,
        substr($action, 0, 40),
        (string) ($config['admin']['username'] ?? 'admin'),
        hash_hmac('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? ''), $auditKey),
    ]);
}

function app_kiosk_changed_fields(array $before, array $after): array
{
    $fields = ['machine_number','address','area','latitude','longitude','schedule','metro','landmark','photo_url'];
    $changed = [];
    foreach ($fields as $field) {
        if ((string) ($before[$field] ?? '') !== (string) ($after[$field] ?? '')) $changed[] = $field;
    }
    return $changed;
}

function app_kiosk_clear_api_cache(): void
{
    $path = dirname(__DIR__, 2) . '/private/runtime/kiosks-api.json';
    if (is_file($path) && !unlink($path)) {
        error_log('kioskvoda_admin api_cache_cleanup_failed');
    }
}

function app_kiosk_photo_reference(PDO $pdo, string $photoUrl): ?string
{
    $current = $pdo->prepare('SELECT 1 FROM kiosks WHERE photo_url = ? LIMIT 1');
    $current->execute([$photoUrl]);
    if ($current->fetchColumn()) return 'Фотография используется текущей карточкой.';

    $history = $pdo->prepare('SELECT 1 FROM kiosk_versions WHERE snapshot_json LIKE ? LIMIT 1');
    $history->execute(['%' . $photoUrl . '%']);
    if ($history->fetchColumn()) return 'Фотография используется в истории карточки.';

    $backupDirectory = dirname(__DIR__, 2) . '/private/runtime/backups';
    foreach (glob($backupDirectory . '/kiosks-before-*.json') ?: [] as $backup) {
        $body = file_get_contents($backup);
        if (is_string($body) && str_contains($body, $photoUrl)) {
            return 'Фотография используется резервной копией.';
        }
    }
    return null;
}

function app_kiosk_trash_photo(string $source, string $name): void
{
    $directory = dirname(__DIR__, 2) . '/private/runtime/photo-trash';
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Photo trash is unavailable');
    }
    foreach (glob($directory . '/*') ?: [] as $oldFile) {
        if (is_file($oldFile) && filemtime($oldFile) !== false && filemtime($oldFile) < time() - 30 * 86400) {
            if (!unlink($oldFile)) error_log('kioskvoda_admin photo_trash_cleanup_failed');
        }
    }
    $destination = $directory . '/' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '-' . $name;
    if (!rename($source, $destination)) throw new RuntimeException('Photo move to trash failed');
    @chmod($destination, 0600);
}

function app_kiosk_store_version(PDO $pdo, int $kioskId, string $action): void
{
    // История хранит только поля карточки; служебные секреты и IP в снимок не попадают.
    $statement = $pdo->prepare('SELECT * FROM kiosks WHERE id = ?');
    $statement->execute([$kioskId]);
    $row = $statement->fetch();
    if (!is_array($row)) {
        throw new RuntimeException('Kiosk version source is missing');
    }
    $config = app_config();
    $insert = $pdo->prepare('INSERT INTO kiosk_versions (kiosk_id,action,snapshot_json,admin_name) VALUES (?,?,?,?)');
    $insert->execute([
        $kioskId,
        substr($action, 0, 30),
        json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        (string) ($config['admin']['username'] ?? 'admin'),
    ]);
}

function app_kiosk_normalize_address(string $address): string
{
    // Нормализация помогает заметить «ул. Ленина» и «улица Ленина» как похожие адреса.
    $value = function_exists('mb_strtolower') ? mb_strtolower(trim($address), 'UTF-8') : strtolower(trim($address));
    $value = preg_replace('/\b(улица|ул\.?|проспект|пр\.?|пр-т|дом|д\.?)\b/u', ' ', $value) ?? $value;
    return preg_replace('/[^\p{L}\p{N}]+/u', '', $value) ?? $value;
}

function app_kiosk_distance_meters(array $first, array $second): float
{
    // Приближённая формула достаточна для поиска почти совпадающих меток в пределах города.
    $lat = (((float) $first['latitude'] + (float) $second['latitude']) / 2) * M_PI / 180;
    $dx = ((float) $first['longitude'] - (float) $second['longitude']) * 111320 * cos($lat);
    $dy = ((float) $first['latitude'] - (float) $second['latitude']) * 110540;
    return sqrt($dx * $dx + $dy * $dy);
}

function app_kiosk_quality(array $rows): array
{
    $issues = [];
    $addressGroups = [];
    foreach ($rows as $row) {
        $id = (int) $row['id'];
        if (empty($row['machine_number'])) $issues[$id][] = 'Не указан номер';
        if (trim((string) ($row['photo_url'] ?? '')) === '') $issues[$id][] = 'Нет фотографии';
        $normalized = app_kiosk_normalize_address((string) $row['address']);
        if ($normalized !== '') $addressGroups[$normalized][] = $id;
    }
    foreach ($addressGroups as $ids) {
        if (count($ids) < 2) continue;
        foreach ($ids as $id) $issues[$id][] = 'Похожий адрес у нескольких точек';
    }
    $count = count($rows);
    for ($i = 0; $i < $count; $i++) {
        for ($j = $i + 1; $j < $count; $j++) {
            if (app_kiosk_distance_meters($rows[$i], $rows[$j]) > 25) continue;
            $firstId = (int) $rows[$i]['id'];
            $secondId = (int) $rows[$j]['id'];
            $issues[$firstId][] = 'Другая точка ближе 25 метров';
            $issues[$secondId][] = 'Другая точка ближе 25 метров';
        }
    }
    foreach ($issues as $id => $values) $issues[$id] = array_values(array_unique($values));
    return $issues;
}

function app_kiosk_store_photo(array $file, string $currentPhoto): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return $currentPhoto;
    }
    if ((int) ($file['error'] ?? -1) !== UPLOAD_ERR_OK
        || (int) ($file['size'] ?? 0) < 1
        || (int) $file['size'] > 5 * 1024 * 1024) {
        throw new InvalidArgumentException('Фотография должна быть не больше 5 МБ.');
    }

    $temporary = (string) ($file['tmp_name'] ?? '');
    if ($temporary === '' || !is_uploaded_file($temporary)) {
        throw new InvalidArgumentException('Сервер не подтвердил безопасную загрузку фотографии.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporary);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $imageInfo = getimagesize($temporary);
    if (!isset($extensions[$mime]) || $imageInfo === false) {
        throw new InvalidArgumentException('Разрешены только настоящие JPG, PNG или WebP.');
    }
    [$width, $height] = $imageInfo;
    if ($width < 1 || $height < 1 || $width > 6000 || $height > 6000 || ($width * $height) > 24000000) {
        throw new InvalidArgumentException('Разрешение фотографии слишком большое. Максимум — 24 мегапикселя.');
    }
    if (!function_exists('imagecreatetruecolor')) {
        throw new RuntimeException('На сервере не включено расширение GD для безопасной обработки фотографий.');
    }

    $loaders = [
        'image/jpeg' => 'imagecreatefromjpeg',
        'image/png' => 'imagecreatefrompng',
        'image/webp' => 'imagecreatefromwebp',
    ];
    if (!function_exists($loaders[$mime])) {
        throw new RuntimeException('Сервер не поддерживает обработку выбранного формата фотографии.');
    }

    $scale = min(1, 2000 / $width, 2000 / $height);
    $targetWidth = max(1, (int) round($width * $scale));
    $targetHeight = max(1, (int) round($height * $scale));
    $requiredMemory = (int) (($width * $height * 5) + ($targetWidth * $targetHeight * 5) + 8 * 1024 * 1024);
    $memoryLimit = app_kiosk_ini_bytes((string) ini_get('memory_limit'));
    if ($memoryLimit !== PHP_INT_MAX && $requiredMemory > max(0, $memoryLimit - memory_get_usage(true))) {
        throw new InvalidArgumentException('Фотография слишком большая для памяти сервера. Уменьшите её разрешение.');
    }

    $source = @$loaders[$mime]($temporary);
    if ($source === false) {
        throw new InvalidArgumentException('Не удалось безопасно прочитать фотографию.');
    }

    $target = imagecreatetruecolor($targetWidth, $targetHeight);
    if ($target === false) {
        imagedestroy($source);
        throw new RuntimeException('Не удалось подготовить фотографию.');
    }
    if ($mime !== 'image/jpeg') {
        imagealphablending($target, false);
        imagesavealpha($target, true);
        $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
        imagefilledrectangle($target, 0, 0, $targetWidth, $targetHeight, $transparent);
    }
    if (!imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height)) {
        imagedestroy($source);
        imagedestroy($target);
        throw new RuntimeException('Не удалось уменьшить фотографию.');
    }

    $directory = dirname(__DIR__) . '/uploads/kiosks';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Upload directory unavailable');
    }
    $name = 'kiosk-' . bin2hex(random_bytes(12)) . '.' . $extensions[$mime];
    $destination = $directory . '/' . $name;
    $saved = match ($mime) {
        'image/jpeg' => imagejpeg($target, $destination, 88),
        'image/png' => imagepng($target, $destination, 8),
        'image/webp' => imagewebp($target, $destination, 85),
        default => false,
    };
    imagedestroy($source);
    imagedestroy($target);
    if (!$saved) {
        throw new RuntimeException('Upload failed');
    }
    chmod($destination, 0644);
    return '/uploads/kiosks/' . $name;
}

function app_kiosk_ini_bytes(string $value): int
{
    $value = trim($value);
    if ($value === '' || $value === '-1') return PHP_INT_MAX;
    $unit = strtolower(substr($value, -1));
    $number = (float) $value;
    return match ($unit) {
        'g' => (int) ($number * 1024 * 1024 * 1024),
        'm' => (int) ($number * 1024 * 1024),
        'k' => (int) ($number * 1024),
        default => (int) $number,
    };
}

function app_kiosk_form_data(array $input): array
{
    $rawNumber = trim((string) ($input['machine_number'] ?? ''));
    $number = $rawNumber === '' ? null : filter_var($rawNumber, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $data = [
        'number' => $number,
        'address' => trim((string) ($input['address'] ?? '')),
        'area' => trim((string) ($input['area'] ?? '')),
        'latitude' => filter_var($input['latitude'] ?? null, FILTER_VALIDATE_FLOAT),
        'longitude' => filter_var($input['longitude'] ?? null, FILTER_VALIDATE_FLOAT),
        'schedule' => trim((string) ($input['schedule'] ?? '')),
        'metro' => trim((string) ($input['metro'] ?? '')),
        'landmark' => trim((string) ($input['landmark'] ?? '')),
    ];

    $invalid = ($rawNumber !== '' && $number === false)
        || $data['address'] === '' || mb_strlen($data['address']) > 255
        || $data['area'] === '' || mb_strlen($data['area']) > 100
        || $data['latitude'] === false || $data['latitude'] < 59 || $data['latitude'] > 61
        || $data['longitude'] === false || $data['longitude'] < 29 || $data['longitude'] > 32
        || mb_strlen($data['schedule']) > 100
        || mb_strlen($data['metro']) > 100
        || mb_strlen($data['landmark']) > 150;
    if ($invalid) {
        throw new InvalidArgumentException('Проверьте заполнение полей и координаты.');
    }
    return $data;
}
