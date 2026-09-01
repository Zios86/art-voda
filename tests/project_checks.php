<?php
declare(strict_types=1);

/** Быстрые автоматические проверки, не требующие MySQL. */
$root = dirname(__DIR__);
$failures = [];
$check = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};
$read = static function (string $relative) use ($root): string {
    $body = file_get_contents($root . '/' . $relative);
    if (!is_string($body)) throw new RuntimeException('Не удалось прочитать ' . $relative);
    return $body;
};

$offline = $read('public_html/offline.html');
$worker = $read('public_html/sw.js');
preg_match('~/css/offline\.css\?v=([0-9-]+)~', $offline, $offlineCss);
preg_match('~/js/offline\.js\?v=([0-9-]+)~', $offline, $offlineJs);
$check(isset($offlineCss[1]) && str_contains($worker, "/css/offline.css?v={$offlineCss[1]}"), 'Версия offline.css расходится с Service Worker');
$check(isset($offlineJs[1]) && str_contains($worker, "/js/offline.js?v={$offlineJs[1]}"), 'Версия offline.js расходится с Service Worker');

$offlineJsBody = $read('public_html/js/offline.js');
$check(!preg_match('/maintenance|planned|Временно не работает|Скоро открытие|Работает/u', $offlineJsBody), 'Офлайн-интерфейс снова показывает статусы');

$index = $read('public_html/index.php');
$check(str_contains($index, 'SELECT COUNT(*) FROM kiosks'), 'Главная не получает количество точек из базы');
$check(!preg_match('/<strong>136<\/strong>/', $index), 'На главной осталось вручную заданное число 136');

$admin = $read('public_html/include/kiosk_admin.php');
$check(str_contains($admin, 'app_kiosk_sync_public_json'), 'Нет генератора kiosks.json');
$check(str_contains($admin, 'filemtime($second)'), 'Очистка backup не сортируется по времени файла');

$mutations = ['save.php','batch.php','import.php','restore.php','rollback.php'];
foreach ($mutations as $file) {
    $check(str_contains($read('public_html/admin/' . $file), 'app_kiosk_sync_public_json'), $file . ' не обновляет kiosks.json');
}

$draft = $read('public_html/js/admin-tools.js');
$check(!str_contains($draft, "form.addEventListener('submit',function(){try{localStorage.removeItem"), 'Черновик удаляется до ответа сервера');
$check(str_contains($draft, 'kioskvoda:draft-restored'), 'Восстановление черновика не обновляет связанные виджеты');

$backup = $read('public_html/admin/backup.php');
$check(str_contains($backup, "'photos/'"), 'Полный backup не включает фотографии');
$check(str_contains($backup, "hash_file('sha256'"), 'Полный backup не содержит контрольных сумм фото');

$schema = $read('private/schema.sql');
$migration = $read('private/migrate_v18_audit.sql');
$check(str_contains($schema, 'kiosk_id INT UNSIGNED NULL'), 'Схема не поддерживает общие события аудита');
$check(str_contains($migration, 'MODIFY kiosk_id INT UNSIGNED NULL'), 'Нет миграции аудита для существующей базы');

$json = json_decode($read('public_html/data/kiosks.json'), true);
$check(is_array($json) && is_array($json['kiosks'] ?? null), 'kiosks.json имеет неверную структуру');

if ($failures) {
    fwrite(STDERR, "Ошибки проверок:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}
echo "Project checks passed\n";
