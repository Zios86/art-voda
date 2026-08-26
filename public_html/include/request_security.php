<?php
declare(strict_types=1);

/** Файловый rate limit формы и API для обычного хостинга без Redis. */

function app_private_runtime_directory(): string
{
    $directory = dirname(__DIR__, 2) . '/private/runtime';
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) throw new RuntimeException('Security storage is unavailable');
    @chmod($directory, 0700);
    return $directory;
}

function app_rate_limit(string $namespace, string $clientKey, int $limit, int $windowSeconds): bool
{
    $safeNamespace = preg_replace('/[^a-z0-9_-]/i', '-', $namespace) ?: 'request';
    $directory = app_private_runtime_directory();
    $path = $directory . '/' . $safeNamespace . '.json';
    $handle = fopen($path, 'c+');
    if ($handle === false || !flock($handle, LOCK_EX)) {
        if (is_resource($handle)) fclose($handle);
        throw new RuntimeException('Security lock is unavailable');
    }
    try {
        rewind($handle);
        $raw = stream_get_contents($handle);
        $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
        $state = is_array($decoded) ? $decoded : [];
        $now = time();

        // Один файл на тип запроса вместо бесконечного количества файлов по IP.
        // Заодно удаляем клиентов, у которых окно ограничения уже закончилось.
        foreach ($state as $key => $timestamps) {
            $fresh = array_values(array_filter(
                is_array($timestamps) ? $timestamps : [],
                static fn ($timestamp): bool => is_int($timestamp) && $timestamp > ($now - $windowSeconds)
            ));
            if ($fresh) $state[$key] = $fresh;
            else unset($state[$key]);
        }

        $clientHash = hash('sha256', $clientKey);
        $events = $state[$clientHash] ?? [];
        $unknownClient = !isset($state[$clientHash]);
        $blocked = count($events) >= $limit || ($unknownClient && count($state) >= 10000);
        if (!$blocked) {
            $events[] = $now;
            $state[$clientHash] = $events;
        }
        rewind($handle);
        ftruncate($handle, 0);
        if (fwrite($handle, json_encode($state, JSON_UNESCAPED_SLASHES) ?: '{}') === false) throw new RuntimeException('Security state cannot be written');
        fflush($handle);
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
        @chmod($path, 0600);
    }

    // Удаляем файлы старого формата после перехода на единое хранилище.
    foreach (glob($directory . '/' . $safeNamespace . '-' . str_repeat('[a-f0-9]', 64) . '.json') ?: [] as $legacyPath) {
        @unlink($legacyPath);
    }
    return $blocked;
}
