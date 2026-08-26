<?php
declare(strict_types=1);

/** Закрытый экспорт CSV; опасные для Excel префиксы нейтрализуются перед выдачей. */
require_once dirname(__DIR__).'/include/app.php';
app_require_admin();
$rows=app_pdo()->query('SELECT machine_number,address,area,latitude,longitude,schedule,metro,landmark,photo_url,updated_at FROM kiosks ORDER BY machine_number IS NULL,machine_number,address')->fetchAll();
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="kiosks-'.date('Y-m-d').'.csv"');
echo "\xEF\xBB\xBF";
$output=fopen('php://output','wb');
function csv_safe(mixed $value): string { $text=(string)$value; return preg_match('/^[=+\-@]/u',$text)===1?"'".$text:$text; }
fputcsv($output,['Номер','Адрес','Город/район','Широта','Долгота','Режим работы','Метро','Ориентир','Фото','Обновлено'],';','"','');
foreach($rows as $row)fputcsv($output,array_map('csv_safe',array_values($row)),';','"','');
fclose($output);
