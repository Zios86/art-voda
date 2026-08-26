# Локальный запуск на Windows и Open Server

## Рекомендуемое окружение

- Windows 10/11;
- Open Server Panel 6.5.x;
- Apache;
- PHP 8.4;
- MySQL 8.4;
- локальный адрес `https://kioskvody.local`.

## Структура

Распаковать проект так, чтобы рядом находились `public_html` и `private`. Web root задаётся именно как `public_html`, а не весь проект.

Пример `.osp/project.ini`:

```ini
[kioskvody.local]
project_enabled = on
bind_ip = 127.0.0.1
http_engine = Apache
php_engine = PHP-8.4
environment = MySQL-8.4
web_root = {base_dir}\public_html
tls_enabled = on
base_url = https://{host_decoded}
```

## База

Для стандартной локальной установки ожидаются host `MySQL-8.4`, база `kioskvody`, пользователь `root`, пустой пароль. Claude должен сверить это с установленной конфигурацией и не переносить пустой пароль на Timeweb.

Для чистой базы импортируется только `private/schema.sql`. Пароль администратора сохраняется как хеш, ключ аудита генерируется отдельно. Значения не помещаются в документацию или Git.

## Важные особенности

- открывать HTTPS: `.htaccess` может перенаправлять обычный HTTP;
- если Яндекс.Карта не работает, проверить разрешение домена `kioskvody.local` для ключа;
- после изменения Service Worker очищать старый кеш или менять версию;
- проверить расширения PHP: `pdo_mysql`, `mbstring`, `fileinfo`, `gd`;
- проверить `memory_limit`, `upload_max_filesize`, `post_max_size`, место на диске и отправку почты.

Подробная отдельная инструкция с изображениями ранее была сохранена как `Kioskvoda_OpenServer_Guide_v17.docx` в Библиотеке проекта.

