<?php
// public/lib/ratelimit.php

class RateLimiter {
    private $limit;
    private $window;
    private $prefix;

    public function __construct($limit = 60, $window = 60, $prefix = 'api_') {
        $this->limit = $limit;
        $this->window = $window;
        $this->prefix = $prefix;
    }

    public function check($identifier) {
        $file = sys_get_temp_dir() . '/' . $this->prefix . md5($identifier);
        $now = time();
        $requests = [];

        if (file_exists($file)) {
            $data = @unserialize(file_get_contents($file));
            if (is_array($data)) {
                // Filter requests within current window
                $requests = array_filter($data, function($time) use ($now) {
                    return $time > ($now - $this->window);
                });
            }
        }

        if (count($requests) >= $this->limit) {
            return false;
        }

        $requests[] = $now;
        file_put_contents($file, serialize($requests), LOCK_EX);
        
        return true;
    }
}
