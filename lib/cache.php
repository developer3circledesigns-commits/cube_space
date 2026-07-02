<?php
declare(strict_types=1);

class JsonCache {
    private int $ttl;
    private string $dir;
    private static array $memory = [];
    private static bool $memoryEnabled = false;

    public function __construct(int $capacity = 100, int $ttl = 300) {
        $this->dir = self::getCacheDir();
        $this->ttl = $ttl;
        self::$memoryEnabled = true;
    }

    public function get(string $key): mixed {
        $hash = md5($key);
        $cacheFile = $this->dir . DIRECTORY_SEPARATOR . $hash . '.json';

        if (isset(self::$memory[$hash])) {
            $entry = self::$memory[$hash];
            if (time() - $entry['time'] <= $this->ttl) {
                return $entry['value'];
            }
            unset(self::$memory[$hash]);
        }

        if (!file_exists($cacheFile)) {
            return null;
        }

        $content = file_get_contents($cacheFile);
        if ($content === false) {
            return null;
        }

        $entry = json_decode($content, true);
        if (!is_array($entry) || !isset($entry['value'], $entry['time'])) {
            @unlink($cacheFile);
            return null;
        }

        if (time() - $entry['time'] > $this->ttl) {
            @unlink($cacheFile);
            unset(self::$memory[$hash]);
            return null;
        }

        self::$memory[$hash] = $entry;
        return $entry['value'];
    }

    public function set(string $key, mixed $value): void {
        $hash = md5($key);
        $entry = ['value' => $value, 'time' => time()];
        $cacheFile = $this->dir . DIRECTORY_SEPARATOR . $hash . '.json';

        self::$memory[$hash] = $entry;
        @file_put_contents($cacheFile, json_encode($entry), LOCK_EX);
    }

    public function clear(string $key): void {
        $hash = md5($key);
        $cacheFile = $this->dir . DIRECTORY_SEPARATOR . $hash . '.json';
        unset(self::$memory[$hash]);
        if (file_exists($cacheFile)) {
            @unlink($cacheFile);
        }
    }

    public static function getCacheDir(): string {
        $tempDir = sys_get_temp_dir();
        // Force Windows temp directory on Windows systems
        if (DIRECTORY_SEPARATOR === '\\') {
            $tempDir = getenv('TEMP') ?: getenv('TMP') ?: 'C:\\Windows\\Temp';
        }
        // Ensure temp directory exists and is writable
        if (!is_dir($tempDir)) {
            @mkdir($tempDir, 0777, true);
        }
        $dir = $tempDir . DIRECTORY_SEPARATOR . 'cubespace_cache_v2';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir;
    }

    public static function clearMemory(): void {
        self::$memory = [];
    }

    public static function getGlobalVersion(): int {
        $tempDir = sys_get_temp_dir();
        // Force Windows temp directory on Windows systems
        if (DIRECTORY_SEPARATOR === '\\') {
            $tempDir = getenv('TEMP') ?: getenv('TMP') ?: 'C:\\Windows\\Temp';
        }
        // Ensure temp directory exists and is writable
        if (!is_dir($tempDir)) {
            @mkdir($tempDir, 0777, true);
        }
        $dir = $tempDir . DIRECTORY_SEPARATOR . 'cubespace_cache';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $file = $dir . DIRECTORY_SEPARATOR . 'global_version';
        if (!file_exists($file)) {
            return 0;
        }
        $val = @file_get_contents($file);
        return $val !== false ? (int)$val : 0;
    }

    public static function incrementGlobalVersion(): void {
        $tempDir = sys_get_temp_dir();
        // Force Windows temp directory on Windows systems
        if (DIRECTORY_SEPARATOR === '\\') {
            $tempDir = getenv('TEMP') ?: getenv('TMP') ?: 'C:\\Windows\\Temp';
        }
        // Ensure temp directory exists and is writable
        if (!is_dir($tempDir)) {
            @mkdir($tempDir, 0777, true);
        }
        $dir = $tempDir . DIRECTORY_SEPARATOR . 'cubespace_cache';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $file = $dir . DIRECTORY_SEPARATOR . 'global_version';
        $version = 0;
        if (file_exists($file)) {
            $version = (int)@file_get_contents($file);
        }
        @file_put_contents($file, (string)($version + 1), LOCK_EX);
    }
}
