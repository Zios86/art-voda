<?php
declare(strict_types=1);

/** Транзакционный smoke-тест схемы v18 на отдельной CI-базе. */
function app_config(): array { return ['admin'=>['username'=>'ci','audit_key'=>'ci-only-test-key']]; }
require_once dirname(__DIR__) . '/public_html/include/kiosk_admin.php';

$pdo = new PDO(
    'mysql:host=' . (getenv('TEST_DB_HOST') ?: '127.0.0.1') . ';dbname=' . (getenv('TEST_DB_NAME') ?: 'kioskvody') . ';charset=utf8mb4',
    getenv('TEST_DB_USER') ?: 'root',
    getenv('TEST_DB_PASSWORD') ?: 'root',
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]
);
$column = $pdo->query("SELECT IS_NULLABLE,CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='kiosk_audit' AND COLUMN_NAME='kiosk_id'")->fetch();
if (!is_array($column) || $column['IS_NULLABLE'] !== 'YES') throw new RuntimeException('kiosk_audit.kiosk_id must be nullable');
$actionColumn = $pdo->query("SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='kiosk_audit' AND COLUMN_NAME='action'")->fetchColumn();
if ((int) $actionColumn < 40) throw new RuntimeException('kiosk_audit.action is too short');

$pdo->beginTransaction();
try {
    $insert = $pdo->prepare('INSERT INTO kiosks (machine_number,address,area,latitude,longitude,schedule) VALUES (?,?,?,?,?,?)');
    $insert->execute([999999,'CI тест','Санкт-Петербург',59.9,30.3,'Круглосуточно']);
    $id = (int) $pdo->lastInsertId();
    app_kiosk_store_version($pdo, $id, 'ci-create');
    app_kiosk_audit($pdo, $id, 'ci-create');
    app_kiosk_audit($pdo, null, 'ci-complete');
    if ((int) $pdo->query('SELECT COUNT(*) FROM kiosk_versions WHERE kiosk_id=' . $id)->fetchColumn() !== 1) throw new RuntimeException('Version was not stored');
    if ((int) $pdo->query("SELECT COUNT(*) FROM kiosk_audit WHERE action LIKE 'ci-%'")->fetchColumn() !== 2) throw new RuntimeException('Audit was not stored');
} finally {
    $pdo->rollBack();
}
echo "Database checks passed\n";
