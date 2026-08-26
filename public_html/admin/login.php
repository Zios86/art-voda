<?php
declare(strict_types=1);

/** Вход администратора с CSRF, ограничением попыток и одинаковым ответом при отказе. */

require_once dirname(__DIR__) . '/include/app.php';
require_once dirname(__DIR__) . '/include/admin_security.php';

app_session();
if (!empty($_SESSION['admin'])) {
    header('Location: /admin/');
    exit;
}

$error = isset($_GET['expired']) ? 'Сеанс завершён. Войдите снова.' : '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    app_require_csrf();
    $admin = app_config()['admin'] ?? [];
    $username = substr(trim((string) ($_POST['username'] ?? '')), 0, 200);
    $password = substr((string) ($_POST['password'] ?? ''), 0, 1024);
    $clientAddress = app_client_address();

    try {
        $guard = app_login_guard($username, $clientAddress);
    } catch (Throwable $storageError) {
        error_log('kioskvoda_security login_guard_unavailable');
        http_response_code(503);
        exit('Вход временно недоступен. Попробуйте позже.');
    }

    if ($guard['blocked']) {
        $retryAfter = (int) $guard['retry_after'];
        header('Retry-After: ' . $retryAfter);
        http_response_code(429);
        app_security_log('admin_login_blocked', $username, $clientAddress, ['retry_after' => $retryAfter]);
        app_admin_alert('Вход заблокирован из-за множества ошибок', 'Повтор через '.$retryAfter.' секунд');
        app_login_failure_delay((int) $guard['attempts']);
        $error = 'Слишком много попыток входа. Подождите несколько минут.';
    } else {
        $configuredUser = (string) ($admin['username'] ?? '');
        $configuredHash = (string) ($admin['password_hash'] ?? '');
        $dummyHash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
        $isConfigured = $configuredUser !== '' && $configuredHash !== '';
        $validUser = $isConfigured && hash_equals($configuredUser, $username);
        $validPassword = password_verify($password, $isConfigured ? $configuredHash : $dummyHash);

        if ($validUser && $validPassword) {
            app_clear_login_failures($username, $clientAddress);
            app_security_log('admin_login_success', $username, $clientAddress);
            if (app_admin_identity_is_new($clientAddress)) app_admin_alert('Вход с нового адреса', 'Устройство: '.substr((string)($_SERVER['HTTP_USER_AGENT']??'неизвестно'),0,120));
            app_mark_admin_authenticated();
            header('Location: /admin/');
            exit;
        }

        $attempts = app_record_login_failure($username, $clientAddress);
        app_security_log('admin_login_failed', $username, $clientAddress, ['attempts' => $attempts]);
        app_login_failure_delay($attempts);
        $error = 'Неверный логин или пароль.';
    }
}
?><!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Вход — управление автоматами</title><link rel="stylesheet" href="/css/admin.css"></head><body><main class="admin-login"><h1>Управление автоматами</h1><?php if($error):?><p class="admin-error"><?=app_h($error)?></p><?php endif;?><form method="post"><input type="hidden" name="csrf" value="<?=app_h(app_csrf())?>"><label>Логин<input name="username" required maxlength="200" autocomplete="username"></label><label>Пароль<input type="password" name="password" required maxlength="1024" autocomplete="current-password"></label><button type="submit">Войти</button></form></main></body></html>
