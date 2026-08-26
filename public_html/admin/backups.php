<?php
declare(strict_types=1);

/** Список автоматических backup-файлов вне web-корня. */
require_once dirname(__DIR__) . '/include/app.php';
app_require_admin();
$directory=dirname(__DIR__,2).'/private/runtime/backups';$files=[];
foreach(glob($directory.'/kiosks-before-*.json')?:[] as $path)if(is_file($path))$files[]=['name'=>basename($path),'size'=>(int)filesize($path),'time'=>(int)filemtime($path)];
usort($files,static fn(array $a,array $b):int=>$b['time']<=>$a['time']);
?><!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Резервные копии — Киосквода</title><link rel="stylesheet" href="/css/admin.css?v=20260816-7"></head><body>
<header class="admin-header"><div><strong>Киосквода</strong><span>Резервные копии</span></div><nav><a href="/admin/">Панель владельца</a><a href="/admin/backup.php">Скачать текущие данные</a></nav></header>
<main class="admin-page"><div class="admin-page__heading"><div><p>Закрытое хранилище</p><h1>Резервные копии</h1></div><span><?=count($files)?> копий</span></div><p class="admin-notice">Для сравнения повторите пароль. Подтверждение действует пять минут. Перед восстановлением система создаст ещё одну копию текущих данных.</p>
<div class="backup-list"><?php foreach($files as $file):?><article><div><strong><?=app_h(date('d.m.Y H:i',$file['time']))?></strong><small><?=app_h($file['name'])?> · <?=number_format($file['size']/1024,0,',',' ')?> КБ</small></div><form action="/admin/restore.php" method="post"><input type="hidden" name="csrf" value="<?=app_h(app_csrf())?>"><input type="hidden" name="action" value="preview"><input type="hidden" name="name" value="<?=app_h($file['name'])?>"><input type="password" name="admin_password" required autocomplete="current-password" placeholder="Пароль"><button class="secondary">Сравнить</button></form></article><?php endforeach;?></div></main></body></html>
