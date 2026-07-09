<?php
/**
 * Execute Image Path Updates
 * Run this script to update database image paths to use local workspace images
 */

require_once __DIR__ . '/public_html/api/db_config.php';

echo "Starting database image path updates...\n\n";

// Read the SQL file
$sqlFile = __DIR__ . '/update_images_local.sql';
if (!file_exists($sqlFile)) {
    die("Error: update_images_local.sql not found\n");
}

$sqlContent = file_get_contents($sqlFile);

// Split by semicolon to get individual queries
$queries = array_filter(array_map('trim', explode(';', $sqlContent)));

$successCount = 0;
$failCount = 0;

foreach ($queries as $query) {
    // Skip empty queries and comments
    if (empty($query) || strpos(trim($query), '--') === 0 || strpos(trim($query), '/*') === 0) {
        continue;
    }
    
    if (strpos($query, 'UPDATE') === 0) {
        $result = mysqli_query($conn, $query);
        if ($result) {
            $successCount++;
            echo "✓ Executed: " . substr($query, 0, 60) . "...\n";
        } else {
            $failCount++;
            echo "✗ Failed: " . mysqli_error($conn) . "\n";
        }
    }
}

echo "\n=== Summary ===\n";
echo "Successful updates: $successCount\n";
echo "Failed updates: $failCount\n";

if ($failCount === 0) {
    echo "\n✓ All image paths updated successfully!\n";
    echo "Images should now display on furnished and unfurnished pages.\n";
} else {
    echo "\n✗ Some updates failed. Check the errors above.\n";
}
