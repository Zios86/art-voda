<?php
declare(strict_types=1);

/** Защита админки: лимиты входа, закрытое состояние и обезличенный журнал событий. */

const APP_LOGIN_WINDOW_SECONDS = 900;
const APP_LOGIN_USER_LIMIT = 8;
const APP_LOGIN_IP_LIMIT = 25;
const APP_SECURITY_LOG_MAX_BYTES = 2097152;
const APP_SECURITY_ALERT_INTERVAL = 900;

function app_client_address(): string
{
    $address = filter_var((string) ($_SERVER['REMOTE_ADDR'] ?? ''), FILTER_VALIDATE_IP);
    return is_string($address) ? $address : 'unknown';
}

function app_normalize_login(string $username): string
{
    $username = trim($username);
    return function_exists('mb_strtolower') ? mb_strtolower($username, 'UTF-8') : strtolower($username);
}

function app_security_directory(): string
{
    static $directory;
    if (is_string($directory)) {
        return $directory;
    }

    $configured = trim((string) (app_config()['security']['storage_dir'] ?? getenv('APP_SECURITY_STORAGE_DIR')));
    $candidates = array_filter([
        $configured,
        dirname(__DIR__, 2) . '/private/runtime',
        rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'kioskvoda-security',
    ]);

    foreach ($candidates as $candidate) {
        if (!is_dir($candidate) && !@mkdir($candidate, 0700, true) && !is_dir($candidate)) {
            continue;
        }
        if (is_writable($candidate)) {
            @chmod($candidate, 0700);
            $directory = $candidate;
            return $directory;
        }
    }

    throw new RuntimeException('Security storage is unavailable');
}

function app_security_identity(string $value): string
{
    $config = app_config();
    $key = (string) ($config['admin']['audit_key'] ?? $config['security']['audit_key'] ?? $config['admin']['password_hash'] ?? 'kioskvoda-security');
    return substr(hash_hmac('sha256', $value, $key), 0, 24);
}

function app_admin_alert(string $event, string $details = ''): void
{
    // Уведомление не содержит пароль или открытый IP и не блокирует основную операцию при ошибке mail().
    $config = app_config();
    $mail = $config['mail'] ?? [];
    $recipient = filter_var((string) ($mail['security_recipient'] ?? $mail['recipient'] ?? ''), FILTER_VALIDATE_EMAIL);
    $sender = filter_var((string) ($mail['from'] ?? ''), FILTER_VALIDATE_EMAIL);
    if (!$recipient || !$sender || !app_security_alert_allowed($event)) return;

    $subject = '=?UTF-8?B?' . base64_encode('Киосквода: событие безопасности') . '?=';
    $message = "Событие: {$event}\nДетали: {$details}\nДата: " . date(DATE_ATOM) . "\n";
    @mail((string) $recipient, $subject, $message, "Content-Type: text/plain; charset=UTF-8\r\nFrom: Kioskvoda <{$sender}>");
}

function app_security_alert_allowed(string $event): bool
{
    try {
        $path = app_security_directory() . DIRECTORY_SEPARATOR . 'security-alerts.json';
        $handle = fopen($path, 'c+');
        if ($handle === false || !flock($handle, LOCK_EX)) {
            if (is_resource($handle)) fclose($handle);
            return false;
        }
        try {
            rewind($handle);
            $raw = stream_get_contents($handle);
            $state = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
            if (!is_array($state)) $state = [];
            $now = time();
            $state = array_filter($state, static fn ($timestamp): bool => is_int($timestamp) && $timestamp > $now - 86400);
            $key = hash('sha256', $event);
            $allowed = !isset($state[$key]) || (int) $state[$key] <= $now - APP_SECURITY_ALERT_INTERVAL;
            if ($allowed) $state[$key] = $now;
            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, json_encode($state, JSON_UNESCAPED_SLASHES) ?: '{}');
            fflush($handle);
            @chmod($path, 0600);
            return $allowed;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    } catch (Throwable $error) {
        error_log('kioskvoda_security alert_throttle_unavailable');
        return false;
    }
}

function app_admin_identity_is_new(string $address): bool
{
    $key=app_security_identity($address);$path=app_security_directory().'/known-admin-addresses.json';$handle=fopen($path,'c+');if($handle===false||!flock($handle,LOCK_EX))return false;
    try{rewind($handle);$raw=stream_get_contents($handle);$state=is_string($raw)&&$raw!==''?json_decode($raw,true):[];if(!is_array($state))$state=[];$isNew=!isset($state[$key]);$state[$key]=time();arsort($state);$state=array_slice($state,0,50,true);rewind($handle);ftruncate($handle,0);fwrite($handle,json_encode($state)?:'{}');return$isNew;}finally{flock($handle,LOCK_UN);fclose($handle);@chmod($path,0600);}
}

function app_with_login_state(callable $callback): mixed
{
    $path = app_security_directory() . DIRECTORY_SEPARATOR . 'login-attempts.json';
    $handle = fopen($path, 'c+');
    if ($handle === false || !flock($handle, LOCK_EX)) {
        if (is_resource($handle)) {
            fclose($handle);
        }
        throw new RuntimeException('Login attempt storage cannot be locked');
    }

    try {
        rewind($handle);
        $raw = stream_get_contents($handle);
        $state = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
        if (!is_array($state)) {
            $state = [];
        }
        $state['users'] = is_array($state['users'] ?? null) ? $state['users'] : [];
        $state['ips'] = is_array($state['ips'] ?? null) ? $state['ips'] : [];

        $now = time();
        $cutoff = $now - APP_LOGIN_WINDOW_SECONDS;
        foreach (['users', 'ips'] as $group) {
            foreach ($state[$group] as $key => $events) {
                $events = array_values(array_filter(
                    is_array($events) ? $events : [],
                    static fn ($timestamp): bool => is_int($timestamp) && $timestamp > $cutoff && $timestamp <= $now
                ));
                if ($events === []) {
                    unset($state[$group][$key]);
                } else {
                    $state[$group][$key] = array_slice($events, -APP_LOGIN_IP_LIMIT);
                }
            }
        }

        $result = $callback($state, $now);
        $encoded = json_encode($state, JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded)) {
            throw new RuntimeException('Login attempt storage cannot be encoded');
        }

        rewind($handle);
        ftruncate($handle, 0);
        fwrite($handle, $encoded);
        fflush($handle);
        @chmod($path, 0600);
        return $result;
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function app_login_guard(string $username, string $clientAddress): array
{
    $userKey = hash('sha256', 'user:' . app_normalize_login($username));
    $ipKey = hash('sha256', 'ip:' . $clientAddress);

    return app_with_login_state(static function (array &$state, int $now) use ($userKey, $ipKey): array {
        $userEvents = $state['users'][$userKey] ?? [];
        $ipEvents = $state['ips'][$ipKey] ?? [];
        $blocked = count($userEvents) >= APP_LOGIN_USER_LIMIT || count($ipEvents) >= APP_LOGIN_IP_LIMIT;
        $oldestRelevant = min(array_filter([
            $userEvents[0] ?? null,
            $ipEvents[0] ?? null,
        ], 'is_int') ?: [$now]);

        return [
            'blocked' => $blocked,
            'retry_after' => $blocked ? max(1, APP_LOGIN_WINDOW_SECONDS - ($now - $oldestRelevant)) : 0,
            'attempts' => max(count($userEvents), count($ipEvents)),
        ];
    });
}

function app_record_login_failure(string $username, string $clientAddress): int
{
    $userKey = hash('sha256', 'user:' . app_normalize_login($username));
    $ipKey = hash('sha256', 'ip:' . $clientAddress);

    return app_with_login_state(static function (array &$state, int $now) use ($userKey, $ipKey): int {
        $state['users'][$userKey] = $state['users'][$userKey] ?? [];
        $state['ips'][$ipKey] = $state['ips'][$ipKey] ?? [];
        $state['users'][$userKey][] = $now;
        $state['ips'][$ipKey][] = $now;
        return max(count($state['users'][$userKey]), count($state['ips'][$ipKey]));
    });
}

function app_clear_login_failures(string $username, string $clientAddress): void
{
    $userKey = hash('sha256', 'user:' . app_normalize_login($username));
    $ipKey = hash('sha256', 'ip:' . $clientAddress);

    app_with_login_state(static function (array &$state, int $_now) use ($userKey, $ipKey): null {
        unset($state['users'][$userKey], $state['ips'][$ipKey]);
        return null;
    });
}

function app_security_log(string $event, string $username, string $clientAddress, array $details = []): void
{
    try {
        $path = app_security_directory() . DIRECTORY_SEPARATOR . 'security.log';
        $handle = fopen($path, 'c+');
        if ($handle === false || !flock($handle, LOCK_EX)) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            throw new RuntimeException('Security log cannot be locked');
        }

        try {
            $fileInfo = fstat($handle);
            if (is_array($fileInfo) && ($fileInfo['size'] ?? 0) >= APP_SECURITY_LOG_MAX_BYTES) {
                rewind($handle);
                ftruncate($handle, 0);
            }
            fseek($handle, 0, SEEK_END);
            $entry = [
                'time' => gmdate('c'),
                'event' => $event,
                'user' => app_security_identity(app_normalize_login($username)),
                'ip' => app_security_identity($clientAddress),
            ] + $details;
            fwrite($handle, (json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}') . PHP_EOL);
            fflush($handle);
            @chmod($path, 0600);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    } catch (Throwable $error) {
        error_log('kioskvoda_security log_unavailable');
    }
}

function app_login_failure_delay(int $attempts): void
{
    $multiplier = 2 ** min(2, max(0, $attempts - 1));
    usleep((int) min(2000000, 500000 * $multiplier));
}
