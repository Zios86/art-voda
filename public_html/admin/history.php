<?php
declare(strict_types=1);

/** История одной карточки: показывает снимки, отличия и предлагает безопасный откат. */
require_once dirname(__DIR__) . '/include/app.php';
app_require_admin();

$kioskId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
if ($kioskId < 1) {
    http_response_code(400);
    exit('Не выбран автомат.');
}

try {
    $pdo = app_pdo();
    $kioskQuery = $pdo->prepare('SELECT id,machine_number,address FROM kiosks WHERE id = ?');
    $kioskQuery->execute([$kioskId]);
    $kiosk = $kioskQuery->fetch();
    if (!$kiosk) {
        http_response_code(404);
        exit('Автомат не найден.');
    }
    $versionQuery = $pdo->prepare('SELECT id,action,snapshot_json,admin_name,created_at FROM kiosk_versions WHERE kiosk_id = ? ORDER BY id DESC LIMIT 100');
    $versionQuery->execute([$kioskId]);
    $versions = $versionQuery->fetchAll();
} catch (Throwable $error) {
    http_response_code(503);
    exit('История недоступна. Выполните миграцию v15 → v16.');
}

$labels = [
    'machine_number' => 'Номер', 'address' => 'Адрес', 'area' => 'Город/район',
    'latitude' => 'Широта', 'longitude' => 'Долгота', 'schedule' => 'Режим работы',
    'metro' => 'Метро', 'landmark' => 'Ориентир', 'photo_url' => 'Фотография',
];
$decoded = [];
foreach ($versions as $version) {
    $snapshot = json_decode((string) $version['snapshot_json'], true);
    $decoded[] = is_array($snapshot) ? $snapshot : [];
}
?><!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>История автомата — Киосквода</title>
    <link rel="stylesheet" href="/css/admin.css?v=20260816-3">
</head>
<body>
<header class="admin-header"><div><strong>Киосквода</strong><span>История изменений</span></div><nav><a href="/admin/?edit=<?=$kioskId?>">Вернуться к карточке</a></nav></header>
<main class="admin-page">
    <div class="admin-page__heading"><div><p>История карточки</p><h1><?=app_h($kiosk['machine_number'] ? 'Автомат №'.$kiosk['machine_number'] : (string) $kiosk['address'])?></h1></div><span><?=count($versions)?> версий</span></div>
    <?php if (!$versions): ?>
        <p class="admin-notice">История начнёт заполняться после следующего сохранения или импорта.</p>
    <?php else: ?>
        <div class="history-list">
        <?php foreach ($versions as $index => $version):
            $snapshot = $decoded[$index];
            $previous = $decoded[$index + 1] ?? [];
            $changes = [];
            foreach ($labels as $field => $label) {
                $value = (string) ($snapshot[$field] ?? '');
                $old = (string) ($previous[$field] ?? '');
                if ($previous === [] || $value !== $old) $changes[$label] = [$old, $value];
            }
        ?>
            <article class="history-card">
                <header><div><strong><?=app_h(date('d.m.Y H:i', strtotime((string) $version['created_at'])))?></strong><small><?=app_h((string) $version['admin_name'])?> · <?=app_h((string) $version['action'])?></small></div><?php if ($index > 0): ?><form action="/admin/rollback.php" method="post" data-confirm="Вернуть данные этой версии? Перед откатом будет создана резервная копия."><input type="hidden" name="csrf" value="<?=app_h(app_csrf())?>"><input type="hidden" name="version_id" value="<?=(int) $version['id']?>"><input type="password" name="admin_password" required autocomplete="current-password" placeholder="Пароль"><button type="submit" class="secondary">Вернуть эту версию</button></form><?php endif; ?></header>
                <dl><?php foreach ($changes as $label => [$old, $value]): ?><div><dt><?=app_h($label)?></dt><?php if ($previous !== []): ?><dd><del><?=app_h($old !== '' ? $old : 'не заполнено')?></del><span aria-hidden="true">→</span><ins><?=app_h($value !== '' ? $value : 'не заполнено')?></ins></dd><?php else: ?><dd><ins><?=app_h($value !== '' ? $value : 'не заполнено')?></ins></dd><?php endif; ?></div><?php endforeach; ?></dl>
            </article>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
<script src="/js/admin-confirm.js?v=20260831-1" defer></script>
</body>
</html>
