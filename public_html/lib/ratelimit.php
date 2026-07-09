<?php

class RateLimiter {
    private $limit;
    private $window;
    private $prefix;
    private static $requestCache = [];

    public function __construct($limit = 60, $window = 60, $prefix = 'api_') {
        $this->limit = $limit;
        $this->window = $window;
        $this->prefix = $prefix;
    }

    public function check($identifier) {
        $key = $this->prefix . md5($identifier);

        if (isset(self::$requestCache[$key])) {
            return self::$requestCache[$key];
        }

        $tempDir = sys_get_temp_dir();
        // Force Windows temp directory on Windows systems
        if (DIRECTORY_SEPARATOR === '\\') {
            $tempDir = getenv('TEMP') ?: getenv('TMP') ?: 'C:\\Windows\\Temp';
        }
        // Ensure temp directory exists and is writable
        if (!is_dir($tempDir)) {
            @mkdir($tempDir, 0777, true);
        }
        $file = $tempDir . DIRECTORY_SEPARATOR . $key;
        $now = time();
        $windowStart = $now - $this->window;

        $requests = [];
        if (file_exists($file)) {
            $content = file_get_contents($file);
            if ($content !== false) {
                $data = @unserialize($content);
                if (is_array($data)) {
                    foreach ($data as $time) {
                        if ($time > $windowStart) {
                            $requests[] = $time;
                        }
                    }
                }
            }
        }

        if (count($requests) >= $this->limit) {
            self::$requestCache[$key] = false;
            return false;
        }

        $requests[] = $now;
        // Silently fail if file cannot be written - rate limiting will be per-request only
        @file_put_contents($file, serialize($requests), LOCK_EX);

        self::$requestCache[$key] = true;
        return true;
    }
}
