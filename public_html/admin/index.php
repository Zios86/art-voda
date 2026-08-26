<?php
declare(strict_types=1);

/** Панель владельца: редактор, качество данных, расширенный поиск, импорт и аудит. */
require_once dirname(__DIR__) . '/include/app.php';
require_once dirname(__DIR__) . '/include/kiosk_admin.php';
app_require_admin();

try {
    $pdo = app_pdo();
    $rows = $pdo->query('SELECT * FROM kiosks ORDER BY machine_number IS NULL,machine_number,address')->fetchAll();
    $audit = $pdo->query('SELECT a.action,a.admin_name,a.created_at,k.machine_number,k.address FROM kiosk_audit a LEFT JOIN kiosks k ON k.id=a.kiosk_id ORDER BY a.id DESC LIMIT 12')->fetchAll();
} catch (Throwable $error) {
    http_response_code(503);
    exit('База не подключена. Проверьте private/config.php и импортируйте schema.sql.');
}

$areas = [];
foreach ($rows as $row) $areas[(string) $row['area']] = ($areas[(string) $row['area']] ?? 0) + 1;
arsort($areas);
$quality = app_kiosk_quality($rows);
$issueCount = count($quality);
$editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT) ?: 0;
$duplicateId = filter_input(INPUT_GET, 'duplicate', FILTER_VALIDATE_INT) ?: 0;
$current = ['id'=>'','machine_number'=>'','address'=>'','area'=>'','latitude'=>'','longitude'=>'','schedule'=>'Круглосуточно','metro'=>'','landmark'=>'','photo_url'=>''];
foreach ($rows as $row) if ((int) $row['id'] === $editId) $current = $row;
foreach ($rows as $row) if ((int) $row['id'] === $duplicateId) { $current=$row; $current['id']=''; $current['machine_number']=''; $current['address']=''; $current['latitude']=''; $current['longitude']=''; }
?><!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Панель владельца — Киосквода</title>
    <link rel="stylesheet" href="/css/admin.css?v=20260816-3">
    <link rel="stylesheet" href="/css/admin-security.css?v=20260816-3">
</head>
<body>
<header class="admin-header">
    <div><strong>Киосквода</strong><span>Панель владельца</span></div>
    <nav><a href="/" target="_blank" rel="noopener noreferrer">Открыть сайт</a><a href="/admin/map.php">Общая карта</a><a href="/admin/photos.php">Фотографии</a><a href="/admin/backups.php">Копии</a><a href="/admin/sessions.php">Входы</a><a href="/admin/diagnostics.php">Диагностика</a><a href="/admin/export.php">Скачать CSV</a><form action="/admin/logout.php" method="post"><input type="hidden" name="csrf" value="<?=app_h(app_csrf())?>"><button>Выйти</button></form></nav>
</header>
<main>
<section class="admin-dashboard">
    <div class="admin-dashboard__heading"><div><p>Сеть автоматов</p><h1><?=count($rows)?> автоматов</h1></div><small>Изменения появляются на карте не позднее минуты</small></div>
    <?php if (isset($_GET['imported'])): ?><p class="admin-success">Импортировано строк: <?=max(0,(int)$_GET['imported'])?>.</p><?php endif; ?>
    <div class="admin-metrics"><div><strong><?=count($rows)?></strong><span>всего точек</span></div><div><strong><?=count($areas)?></strong><span>городов и районов</span></div><div class="<?=$issueCount?'has-warning':''?>"><strong><?=$issueCount?></strong><span>карточек проверить</span></div><div><strong><?=count(array_filter($rows,static fn(array $row):bool => !empty($row['photo_url'])))?></strong><span>с фотографиями</span></div></div>
    <div class="admin-insights">
        <div><h2>Крупнейшие группы</h2><ol><?php foreach(array_slice($areas,0,6,true) as $area=>$amount):?><li><span><?=app_h($area)?></span><strong><?=$amount?></strong></li><?php endforeach;?></ol></div>
        <div><h2>Последние изменения</h2><?php if(!$audit):?><p>Изменений пока нет.</p><?php else:?><ul><?php foreach($audit as $item):?><li><span><?=app_h(['create'=>'Добавлен','update'=>'Изменён','rollback'=>'Восстановлен'][$item['action']] ?? (string)$item['action'])?>: <?=app_h($item['machine_number']?'№'.$item['machine_number']:(string)$item['address'])?></span><time><?=app_h(date('d.m.Y H:i',strtotime((string)$item['created_at'])))?></time></li><?php endforeach;?></ul><?php endif;?></div>
    </div>
    <?php if ($quality): ?><details class="quality-panel"><summary>Найдено карточек для проверки: <?=$issueCount?></summary><ul><?php foreach(array_slice($quality,0,20,true) as $id=>$issues): $item=null; foreach($rows as $row)if((int)$row['id']===(int)$id)$item=$row; if(!$item)continue; ?><li><a href="?edit=<?=(int)$id?>"><?=app_h($item['machine_number']?'Автомат №'.$item['machine_number']:(string)$item['address'])?></a><span><?=app_h(implode(' · ',$issues))?></span></li><?php endforeach;?></ul></details><?php endif; ?>
    <form class="admin-import" action="/admin/import.php" method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=app_h(app_csrf())?>"><input type="hidden" name="action" value="preview"><label><strong>Импорт CSV с предпросмотром</strong><input type="file" name="csv" accept=".csv,text/csv" required></label><button type="submit">Проверить файл</button><small>Сначала увидите добавления, изменения и ошибки. База не меняется без подтверждения.</small></form>
</section>

<div class="admin-layout">
<section>
    <h2><?=$editId?'Изменить автомат':'Добавить автомат'?></h2>
    <?php if(isset($_GET['saved'])):?><p class="admin-success">Изменения сохранены и появятся на карте не позднее минуты.</p><?php endif;?>
    <form class="admin-form" action="/admin/save.php" method="post" enctype="multipart/form-data" data-autosave-key="kiosk-form-<?=app_h((string)($current['id']?:'new'))?>">
        <input type="hidden" name="csrf" value="<?=app_h(app_csrf())?>"><input type="hidden" name="id" value="<?=app_h((string)$current['id'])?>">
        <label>Номер автомата<input id="machine-number" type="number" name="machine_number" min="1" value="<?=app_h((string)$current['machine_number'])?>"></label>
        <label>Адрес<input id="address" name="address" required maxlength="255" value="<?=app_h((string)$current['address'])?>"></label>
        <label>Город или район<input id="area" name="area" required maxlength="100" value="<?=app_h((string)$current['area'])?>"></label>
        <div class="admin-coordinates"><label>Широта<input id="latitude" name="latitude" type="number" step="0.000001" min="59" max="61" required value="<?=app_h((string)$current['latitude'])?>"></label><label>Долгота<input id="longitude" name="longitude" type="number" step="0.000001" min="29" max="32" required value="<?=app_h((string)$current['longitude'])?>"></label></div>
        <button type="button" id="find-address" class="secondary">Найти координаты по адресу</button>
        <iframe id="admin-map-frame" class="admin-map" src="/admin-map-frame.html" title="Выбор координат автомата" sandbox="allow-scripts" referrerpolicy="strict-origin"></iframe>
        <label>Режим работы<input id="schedule" name="schedule" maxlength="100" value="<?=app_h((string)$current['schedule'])?>"></label>
        <label>Ближайшее метро<input id="metro" name="metro" maxlength="100" value="<?=app_h((string)$current['metro'])?>" placeholder="Например: Дыбенко"></label>
        <label>Ориентир<input id="landmark" name="landmark" maxlength="150" value="<?=app_h((string)$current['landmark'])?>" placeholder="У входа в магазин"></label>
        <label>Фотография автомата<input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp"><small>JPG, PNG или WebP, до 5 МБ. После выбора можно повернуть и кадрировать.</small></label><div id="photo-editor" class="photo-editor" hidden><canvas id="photo-canvas" width="800" height="600"></canvas><div><button type="button" class="secondary" data-photo-rotate>Повернуть</button><label>Масштаб<input id="photo-zoom" type="range" min="1" max="2" step="0.05" value="1"></label><button type="button" data-photo-apply>Применить кадр</button></div></div>
        <div class="preview-switch"><button type="button" class="secondary is-active" data-preview-mode="desktop">Карточка</button><button type="button" class="secondary" data-preview-mode="mobile">Телефон</button><button type="button" class="secondary" data-preview-mode="balloon">На карте</button></div><article class="admin-preview" data-preview-card><img id="preview-photo" src="<?=app_h((string)$current['photo_url'])?>" alt=""<?=empty($current['photo_url'])?' hidden':''?>><div><h3 id="preview-title"><?=app_h($current['machine_number']?'Автомат №'.$current['machine_number']:'Новый автомат')?></h3><p id="preview-address"><?=app_h((string)($current['address']?:'Адрес появится здесь'))?></p><small id="preview-details"><?=app_h((string)($current['schedule']?:'Режим работы'))?></small></div></article>
        <p id="autosave-note" class="autosave-note" aria-live="polite"></p><div class="admin-actions"><button type="submit">Сохранить</button><a href="/admin/">Очистить форму</a><?php if($editId):?><a href="/admin/history.php?id=<?=$editId?>">История</a><a href="?duplicate=<?=$editId?>">Создать похожий</a><?php endif;?></div>
    </form>
</section>

<section>
    <div class="admin-list-heading"><h2>Все автоматы</h2><span id="visible-count"><?=count($rows)?> показано</span></div>
    <div class="admin-filters">
        <label>Поиск<input id="table-search" type="search" placeholder="Номер, адрес, метро или ориентир"></label>
        <label>Район<select id="filter-area"><option value="">Все районы</option><?php foreach(array_keys($areas) as $area):?><option value="<?=app_h($area)?>"><?=app_h($area)?></option><?php endforeach;?></select></label>
        <label>Фотография<select id="filter-photo"><option value="">Любая</option><option value="yes">Есть</option><option value="no">Нет</option></select></label>
        <label>Проверка<select id="filter-issue"><option value="">Все</option><option value="yes">Есть замечания</option><option value="no">Без замечаний</option></select></label>
    </div>
    <div class="admin-table-wrap"><table id="kiosk-table"><thead><tr><th>№</th><th>Адрес</th><th>Место</th><th>Проверка данных</th><th></th></tr></thead><tbody>
    <?php foreach($rows as $row): $issues=$quality[(int)$row['id']]??[]; ?><tr data-area="<?=app_h((string)$row['area'])?>" data-photo="<?=empty($row['photo_url'])?'no':'yes'?>" data-issue="<?=$issues?'yes':'no'?>"><td><?=app_h((string)$row['machine_number'])?></td><td><?=app_h((string)$row['address'])?></td><td><?=app_h((string)$row['area'])?><?=!empty($row['metro'])?'<small>Метро: '.app_h((string)$row['metro']).'</small>':''?></td><td><?php if($issues):?><span class="issue-label"><?=app_h(implode(' · ',$issues))?></span><?php else:?><span class="ok-label">Заполнено</span><?php endif;?></td><td><div class="row-actions"><a href="?edit=<?=(int)$row['id']?>">Изменить</a><a href="?duplicate=<?=(int)$row['id']?>">Создать похожий</a><a href="/admin/history.php?id=<?=(int)$row['id']?>">История</a></div></td></tr><?php endforeach;?>
    </tbody></table></div>
</section>
</div>
</main>
<script src="/js/admin-map.js?v=20260816-3"></script>
<script src="/js/admin-tools.js?v=20260816-3"></script>
</body></html>
