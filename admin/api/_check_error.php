<?php
$logFile = ini_get('error_log');
echo "Error log file: " . ($logFile ?: 'not set') . "\n";
echo "Display errors: " . ini_get('display_errors') . "\n";
echo "Log errors: " . ini_get('log_errors') . "\n";

// Check syslog
if (function_exists('openlog')) {
    echo "syslog available\n";
}

// Check if we can write
$testDir = __DIR__ . '/../logs';
if (!is_dir($testDir)) {
    mkdir($testDir, 0755, true);
    echo "Created logs dir at: $testDir\n";
}
$testFile = $testDir . '/test.log';
file_put_contents($testFile, date('c') . ' test' . "\n", FILE_APPEND);
echo "Test log written to: $testFile\n";
echo "File exists: " . (file_exists($testFile) ? 'yes' : 'no') . "\n";
