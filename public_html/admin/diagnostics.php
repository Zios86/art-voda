<?php
declare(strict_types=1);

/** Read-only самопроверка окружения Timeweb без вывода секретных значений. */
require_once dirname(__DIR__) . '/include/app.php';app_require_admin();
$checks=[];function add_check(array &$checks,string $name,bool $ok,string $message):void{$checks[]=['name'=>$name,'ok'=>$ok,'message'=>$message];}
try{$pdo=app_pdo();$pdo->query('SELECT 1');add_check($checks,'База данных',true,'Соединение работает');try{$pdo->query('SELECT 1 FROM kiosk_versions LIMIT 1');add_check($checks,'История версий',true,'Таблица kiosk_versions доступна');}catch(Throwable $e){add_check($checks,'История версий',false,'Выполните migrate_v15_to_v16.sql');}}catch(Throwable $e){add_check($checks,'База данных',false,'Нет соединения');}
$runtime=dirname(__DIR__,2).'/private/runtime';$uploads=dirname(__DIR__).'/uploads/kiosks';
add_check($checks,'PHP',version_compare(PHP_VERSION,'8.4.0','>='),'Версия '.PHP_VERSION);
add_check($checks,'GD',extension_loaded('gd'),'Без GD фотографии нельзя безопасно пересохранять');
add_check($checks,'Fileinfo',extension_loaded('fileinfo'),'Проверка настоящего MIME-типа файлов');
add_check($checks,'Runtime',is_dir($runtime)&&is_writable($runtime),'Закрытое хранилище сессий, кеша и backup');
add_check($checks,'Фотографии',is_dir($uploads)&&is_writable($uploads),'Папка uploads/kiosks доступна для записи');
add_check($checks,'HTTPS',(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'),'Админка должна работать только по HTTPS');
$mail=app_config()['mail']??[];add_check($checks,'Почта',filter_var((string)($mail['recipient']??''),FILTER_VALIDATE_EMAIL)!==false&&filter_var((string)($mail['from']??''),FILTER_VALIDATE_EMAIL)!==false,'Проверены адреса recipient и from');
$apiCache=$runtime.'/kiosks-api.json';add_check($checks,'Кеш API',!is_file($apiCache)||is_readable($apiCache),is_file($apiCache)?'Кеш создан и читается':'Создастся после первого запроса');
$failed=count(array_filter($checks,static fn(array $item):bool=>!$item['ok']));
?><!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Диагностика — Киосквода</title><link rel="stylesheet" href="/css/admin.css?v=20260816-8"></head><body><header class="admin-header"><div><strong>Киосквода</strong><span>Диагностика</span></div><nav><a href="/admin/">Панель владельца</a></nav></header><main class="admin-page"><div class="admin-page__heading"><div><p>Состояние сайта</p><h1>Техническая проверка</h1></div><span><?=$failed?$failed.' проблем':'Всё хорошо'?></span></div><div class="diagnostic-list"><?php foreach($checks as $item):?><article class="<?=$item['ok']?'is-ok':'is-failed'?>"><span aria-hidden="true"><?=$item['ok']?'✓':'!'?></span><div><strong><?=app_h($item['name'])?></strong><small><?=app_h($item['message'])?></small></div></article><?php endforeach;?></div><p class="admin-notice">Проверка ничего не изменяет. Секреты, пароли и реквизиты базы на страницу не выводятся.</p></main></body></html>
