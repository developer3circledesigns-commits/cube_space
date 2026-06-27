<?php
declare(strict_types=1);

class JsonCache {
    private string $file;
    private int $ttl;
    private int $capacity;

    public function __construct(int $capacity = 100, int $ttl = 300) {
        $dir = sys_get_temp_dir() . '/cubespace_cache';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $this->file = $dir . '/cache.json';
        $this->ttl = $ttl;
        $this->capacity = $capacity;
    }

    public function get(string $key): mixed {
        $store = $this->read();
        $entry = $store[md5($key)] ?? null;
        if (!$entry) return null;
        if (time() - $entry['time'] > $this->ttl) {
            unset($store[md5($key)]);
            $this->write($store);
            return null;
        }
        $entry['time'] = time();
        $store[md5($key)] = $entry;
        $this->write($store);
        return $entry['value'];
    }

    public function set(string $key, mixed $value): void {
        $store = $this->read();
        $store[md5($key)] = ['value' => $value, 'time' => time()];
        if (count($store) > $this->capacity) {
            uasort($store, fn($a, $b) => $a['time'] - $b['time']);
            $store = array_slice($store, count($store) - $this->capacity, null, true);
        }
        $this->write($store);
    }

    private function read(): array {
        $fp = fopen($this->file, 'c+');
        if (!$fp) return [];
        flock($fp, LOCK_SH);
        $content = stream_get_contents($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        $data = $content ? json_decode($content, true) : null;
        return is_array($data) ? $data : [];
    }

    private function write(array $store): void {
        $fp = fopen($this->file, 'c+');
        if (!$fp) return;
        flock($fp, LOCK_EX);
        ftruncate($fp, 0);
        fwrite($fp, json_encode($store));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
    }

    public static function getCacheDir(): string {
        $dir = sys_get_temp_dir() . '/cubespace_cache';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }

    public static function getGlobalVersion(): int {
        $file = self::getCacheDir() . '/global_version';
        if (!file_exists($file)) {
            return 0;
        }
        return (int)file_get_contents($file);
    }

    public static function incrementGlobalVersion(): void {
        $file = self::getCacheDir() . '/global_version';
        $version = 0;
        if (file_exists($file)) {
            $version = (int)file_get_contents($file);
        }
        file_put_contents($file, (string)($version + 1), LOCK_EX);
    }
}