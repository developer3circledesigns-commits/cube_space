<?php
declare(strict_types=1);

function load_env(string $path = null): void {
    static $loaded = false;

    if ($loaded) {
        return;
    }

    if ($path === null) {
        $path = dirname(__DIR__) . '/.env';
    }

    if (!is_file($path)) {
        $loaded = true;
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            if ($key !== '' && getenv($key) === false) {
                $value = preg_replace('/^["\'](.*)["\']$/', '$1', $value);
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }
    }

    $loaded = true;
}
