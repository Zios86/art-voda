<?php
declare(strict_types=1);

/** Понятное представление обезличенного журнала безопасности. */
require_once dirname(__DIR__) . '/include/app.php';
require_once dirname(__DIR__) . '/include/admin_security.php';
app_require_admin();
$path = app_security_directory() . '/security.log';
$entries = [];
if (is_file($path) && is_readable($path)) {
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach (array_slice($lines, -200) as $line) {
        $entry = json_decode($line, true);
        if (is_array($entry)) $entries[] = $entry;
    }
    $entries = array_reverse($entries);
}
$labels = [
    'admin_login_success'=>'Успешный вход', 'admin_login_failed'=>'Неверный пароль', 'admin_login_blocked'=>'Вход временно заблокирован',
    'logout'=>'Выход', 'session_revoked'=>'Сеанс отозван', 'photo_trashed'=>'Фото перемещено в корзину',
    'photo_restored'=>'Фото восстановлено', 'admin_reauth_failed'=>'Неверный повторный пароль',
];
?><!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Журнал безопасности — Киосквода</title><link rel="stylesheet" href="/css/admin.css?v=20260901-1"></head><body>
<header class="admin-header"><div><strong>Киосквода</strong><span>Журнал безопасности</span></div><nav><a href="/admin/">Панель владельца</a></nav></header>
<main class="admin-page"><div class="admin-page__heading"><div><p>Последние 200 событий</p><h1>Безопасность</h1></div><span>IP и пользователь обезличены</span></div><p class="admin-notice">Журнал помогает заметить перебор пароля, новые входы и операции с фотографиями. Полные IP-адреса и пароли здесь не хранятся.</p>
<?php if(!$entries):?><p>Событий пока нет.</p><?php else:?><div class="admin-table-wrap"><table><thead><tr><th>Дата UTC</th><th>Событие</th><th>Пользователь</th><th>Источник</th><th>Подробности</th></tr></thead><tbody><?php foreach($entries as $entry):?><tr><td><?=app_h(date('d.m.Y H:i:s',strtotime((string)($entry['time']??'now'))))?></td><td><?=app_h($labels[(string)($entry['event']??'')]??(string)($entry['event']??'Неизвестное событие'))?></td><td><code><?=app_h(substr((string)($entry['user']??''),0,12))?></code></td><td><code><?=app_h(substr((string)($entry['ip']??''),0,12))?></code></td><td><?=app_h(isset($entry['attempts'])?'Попыток: '.(int)$entry['attempts']:'—')?></td></tr><?php endforeach;?></tbody></table></div><?php endif;?></main></body></html>
