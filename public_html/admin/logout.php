<?php
declare(strict_types=1);

/** Безопасный выход: только POST-запрос авторизованного пользователя с CSRF. */

require_once dirname(__DIR__) . '/include/app.php';
app_require_admin();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit;
}
app_require_csrf();
app_end_admin_session();
header('Location: /admin/login.php');
