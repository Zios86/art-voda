<?php
declare(strict_types=1);

/** Общая карта админки: выбор точек, перетаскивание и пакетные действия. */
require_once dirname(__DIR__) . '/include/app.php';
app_require_admin();
$rows = app_pdo()->query('SELECT id,machine_number,address,area,latitude,longitude FROM kiosks ORDER BY machine_number IS NULL,machine_number,address')->fetchAll();
$json = json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_THROW_ON_ERROR);
?><!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Общая карта — Киосквода</title><link rel="stylesheet" href="/css/admin.css?v=20260816-4"></head><body>
<header class="admin-header"><div><strong>Киосквода</strong><span>Общая карта</span></div><nav><a href="/admin/">Панель владельца</a></nav></header>
<main class="bulk-map-page">
<section class="bulk-map-sidebar"><div><p>Массовое управление</p><h1>Все автоматы</h1><small>Перетащите метку или выберите точки для общего изменения.</small></div><label>Поиск<input id="bulk-search" type="search" placeholder="Номер или адрес"></label><div id="bulk-list" class="bulk-kiosk-list"></div></section>
<section class="bulk-map-workspace"><iframe id="bulk-map-frame" src="/admin-bulk-map-frame.html" title="Общая карта автоматов" sandbox="allow-scripts" referrerpolicy="strict-origin"></iframe><form id="bulk-form" action="/admin/batch.php" method="post"><input type="hidden" name="csrf" value="<?=app_h(app_csrf())?>"><input type="hidden" name="ids" id="bulk-ids"><input type="hidden" name="coordinates" id="bulk-coordinates"><div><strong id="bulk-selected">Ничего не выбрано</strong><select name="action" id="bulk-action"><option value="">Выберите действие</option><option value="coordinates">Сохранить передвинутые точки</option><option value="area">Изменить город или район</option><option value="schedule">Изменить режим работы</option><option value="clear_landmark">Удалить ориентир</option><option value="delete_photos">Удалить фотографии</option></select><input name="value" id="bulk-value" maxlength="150" placeholder="Новое значение"><input type="password" name="admin_password" required autocomplete="current-password" placeholder="Пароль администратора"><button type="submit">Применить</button></div><small>Перед изменением сервер создаст резервную копию.</small></form></section>
</main><script type="application/json" id="bulk-kiosks"><?=$json?></script><script src="/js/admin-bulk-map.js?v=20260816-4"></script></body></html>
