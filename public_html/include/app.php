<?php
declare(strict_types=1);

/** Общая инфраструктура: закрытая конфигурация, PDO, admin-сессия, CSRF и вход. */

const APP_ADMIN_IDLE_TIMEOUT = 1800;
const APP_ADMIN_ABSOLUTE_TIMEOUT = 28800;
const APP_ADMIN_ROTATE_INTERVAL = 900;
const APP_ADMIN_REAUTH_WINDOW = 900;
const APP_ADMIN_REAUTH_LIMIT = 5;

function app_config(): array
{
    static $config;
    if (is_array($config)) {
        return $config;
    }
    $path = dirname(__DIR__, 2) . '/private/config.php';
    $config = is_file($path) ? require $path : [];
    return is_array($config) ? $config : [];
}

function app_pdo(): PDO
{
    static $pdo;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $db = app_config()['db'] ?? [];
    if (empty($db['host']) || empty($db['name']) || empty($db['user'])) {
        throw new RuntimeException('Database is not configured');
    }
    $pdo = new PDO(
        'mysql:host=' . $db['host'] . ';dbname=' . $db['name'] . ';charset=utf8mb4',
        (string) $db['user'],
        (string) ($db['password'] ?? ''),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    return $pdo;
}

function app_admin_no_store(): void
{
    header('Cache-Control: no-store, no-cache, must-revalidate, private, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

function app_session(): void
{
    app_admin_no_store();
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', '1');
    ini_set('session.cookie_samesite', 'Strict');
    session_name('kioskvoda_admin');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/admin',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

function app_csrf(): string
{
    app_session();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['csrf'];
}

function app_require_csrf(): void
{
    if (!hash_equals((string) ($_SESSION['csrf'] ?? ''), (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        exit('Проверка безопасности не пройдена. Обновите страницу.');
    }
}

function app_require_admin_password(): void
{
    // Опасные массовые операции требуют повторить пароль, даже если сессия уже открыта.
    if ((int) ($_SESSION['reauth_until'] ?? 0) >= time()) return;

    if (empty($_SESSION['reauth_identity'])) {
        $_SESSION['reauth_identity'] = bin2hex(random_bytes(24));
    }
    $identity = hash('sha256', (string) $_SESSION['reauth_identity']);
    $guard = app_with_reauth_state(static function (array &$state, int $now) use ($identity): array {
        $events = array_values(array_filter(
            is_array($state[$identity] ?? null) ? $state[$identity] : [],
            static fn ($timestamp): bool => is_int($timestamp) && $timestamp > $now - APP_ADMIN_REAUTH_WINDOW && $timestamp <= $now
        ));
        if ($events === []) unset($state[$identity]); else $state[$identity] = $events;
        $blocked = count($events) >= APP_ADMIN_REAUTH_LIMIT;
        return [
            'blocked' => $blocked,
            'retry_after' => $blocked ? max(1, APP_ADMIN_REAUTH_WINDOW - ($now - (int) $events[0])) : 0,
            'attempts' => count($events),
        ];
    });
    if ($guard['blocked']) {
        header('Retry-After: ' . $guard['retry_after']);
        http_response_code(429);
        exit('Слишком много неверных попыток. Повторите позже.');
    }

    $password = substr((string) ($_POST['admin_password'] ?? ''), 0, 1024);
    $hash = (string) (app_config()['admin']['password_hash'] ?? '');
    if ($hash === '' || !password_verify($password, $hash)) {
        $attempts = app_with_reauth_state(static function (array &$state, int $now) use ($identity): int {
            $state[$identity] = is_array($state[$identity] ?? null) ? $state[$identity] : [];
            $state[$identity][] = $now;
            $state[$identity] = array_slice($state[$identity], -APP_ADMIN_REAUTH_LIMIT);
            return count($state[$identity]);
        });
        error_log('kioskvoda_security admin_reauth_failed');
        usleep((int) min(2000000, 400000 * (2 ** min(2, max(0, $attempts - 1)))));
        http_response_code(403);
        exit('Для этой операции необходимо повторно ввести пароль администратора.');
    }
    app_with_reauth_state(static function (array &$state, int $_now) use ($identity): null {
        unset($state[$identity]);
        return null;
    });
    $_SESSION['reauth_until'] = time() + 300;
}

function app_with_reauth_state(callable $callback): mixed
{
    $directory = dirname(__DIR__, 2) . '/private/runtime';
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Reauthentication storage is unavailable');
    }
    $path = $directory . '/admin-reauth-attempts.json';
    $handle = fopen($path, 'c+');
    if ($handle === false || !flock($handle, LOCK_EX)) {
        if (is_resource($handle)) fclose($handle);
        throw new RuntimeException('Reauthentication storage cannot be locked');
    }
    try {
        rewind($handle);
        $raw = stream_get_contents($handle);
        $state = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
        if (!is_array($state)) $state = [];
        $now = time();
        foreach ($state as $key => $events) {
            $events = array_values(array_filter(
                is_array($events) ? $events : [],
                static fn ($timestamp): bool => is_int($timestamp) && $timestamp > $now - APP_ADMIN_REAUTH_WINDOW && $timestamp <= $now
            ));
            if ($events === []) unset($state[$key]); else $state[$key] = $events;
        }
        $result = $callback($state, $now);
        rewind($handle);
        ftruncate($handle, 0);
        fwrite($handle, json_encode($state, JSON_UNESCAPED_SLASHES) ?: '{}');
        fflush($handle);
        @chmod($path, 0600);
        return $result;
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function app_admin_sessions(callable $callback): mixed
{
    $directory = dirname(__DIR__, 2) . '/private/runtime';
    if (!is_dir($directory) && !mkdir($directory,0700,true) && !is_dir($directory)) throw new RuntimeException('Session registry unavailable');
    $path=$directory.'/admin-sessions.json';$handle=fopen($path,'c+');if($handle===false||!flock($handle,LOCK_EX))throw new RuntimeException('Session registry lock unavailable');
    try{rewind($handle);$raw=stream_get_contents($handle);$state=is_string($raw)&&$raw!==''?json_decode($raw,true):[];if(!is_array($state))$state=[];$state=array_filter($state,static fn($item):bool=>is_array($item)&&(int)($item['last_activity']??0)>time()-APP_ADMIN_ABSOLUTE_TIMEOUT);$result=$callback($state);rewind($handle);ftruncate($handle,0);fwrite($handle,json_encode($state,JSON_UNESCAPED_SLASHES)?:'{}');fflush($handle);return$result;}finally{flock($handle,LOCK_UN);fclose($handle);@chmod($path,0600);}
}

function app_admin_session_key(): string { return hash('sha256', session_id()); }

function app_register_admin_session(): void
{
    $key=app_admin_session_key();$config=app_config();$secret=(string)($config['admin']['audit_key']??$config['admin']['password_hash']??'session');
    app_admin_sessions(function(array &$state)use($key,$secret):void{$created=(int)($state[$key]['created_at']??time());$state[$key]=['created_at'=>$created,'last_activity'=>time(),'ip_hash'=>substr(hash_hmac('sha256',(string)($_SERVER['REMOTE_ADDR']??''),$secret),0,16),'device'=>substr((string)($_SERVER['HTTP_USER_AGENT']??'Неизвестное устройство'),0,180),'revoked'=>(bool)($state[$key]['revoked']??false)];});
}

function app_admin_session_is_revoked(): bool
{
    $key=app_admin_session_key();return app_admin_sessions(static function(array &$state)use($key):bool{return !empty($state[$key]['revoked']);});
}

function app_end_admin_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    // После обычного выхода сразу убираем устройство из списка активных входов.
    $sessionKey = app_admin_session_key();
    try {
        app_admin_sessions(static function (array &$state) use ($sessionKey): void {
            unset($state[$sessionKey]);
        });
    } catch (Throwable $error) {
        // Выход всё равно должен сработать, даже если служебный реестр временно недоступен.
        error_log('kioskvoda_admin session_registry_logout_failed');
    }

    $_SESSION = [];
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 3600,
        'path' => $params['path'] ?: '/admin',
        'domain' => $params['domain'] ?? '',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_destroy();
}

function app_mark_admin_authenticated(): void
{
    app_session();
    session_regenerate_id(true);
    $now = time();
    $_SESSION['admin'] = true;
    $_SESSION['authenticated_at'] = $now;
    $_SESSION['last_activity'] = $now;
    $_SESSION['rotated_at'] = $now;
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    $_SESSION['reauth_identity'] = bin2hex(random_bytes(24));
    app_register_admin_session();
}

function app_require_admin(): void
{
    app_session();
    $now = time();
    $authenticatedAt = (int) ($_SESSION['authenticated_at'] ?? 0);
    $lastActivity = (int) ($_SESSION['last_activity'] ?? 0);
    $expired = $authenticatedAt <= 0
        || $lastActivity <= 0
        || ($now - $lastActivity) > APP_ADMIN_IDLE_TIMEOUT
        || ($now - $authenticatedAt) > APP_ADMIN_ABSOLUTE_TIMEOUT;

    $wasAuthenticated = !empty($_SESSION['admin']);
    if ($wasAuthenticated && !$expired && app_admin_session_is_revoked()) $expired = true;
    if (!$wasAuthenticated || $expired) {
        app_end_admin_session();
        header('Location: /admin/login.php' . ($wasAuthenticated && $expired ? '?expired=1' : ''));
        exit;
    }

    if (($now - (int) ($_SESSION['rotated_at'] ?? 0)) > APP_ADMIN_ROTATE_INTERVAL) {
        $oldSessionKey = app_admin_session_key();
        session_regenerate_id(true);
        $_SESSION['rotated_at'] = $now;
        app_admin_sessions(static function (array &$state) use ($oldSessionKey): void {
            unset($state[$oldSessionKey]);
        });
    }
    $_SESSION['last_activity'] = $now;
    app_register_admin_session();
}

function app_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
