<?php
/**
 * Download Workspace Images Script
 * This script downloads real workspace images from Unsplash to your uploads folder
 * and updates the database with local paths.
 * 
 * Usage: php download_workspace_images.php
 */

// Database configuration
require_once __DIR__ . '/public_html/api/db_config.php';

// Image source URLs from Unsplash (free to use, no attribution required)
$workspaceImages = [
    // Modern office spaces
    'https://images.unsplash.com/photo-1497366216548-37526070297c?w=800&q=80',
    'https://images.unsplash.com/photo-1497366811353-6870744d04b2?w=800&q=80',
    'https://images.unsplash.com/photo-1497366754035-f200968a6e72?w=800&q=80',
    'https://images.unsplash.com/photo-1497215728101-856f4ea42174?w=800&q=80',
    'https://images.unsplash.com/photo-1497366216548-37526070297c?w=800&q=80',
    'https://images.unsplash.com/photo-1519389950473-47ba0277781c?w=800&q=80',
    'https://images.unsplash.com/photo-1497215842964-222b430dc094?w=800&q=80',
    'https://images.unsplash.com/photo-1604328698692-f76ea9498e76?w=800&q=80',
    'https://images.unsplash.com/photo-1497366216548-37526070297c?w=800&q=80',
    'https://images.unsplash.com/photo-1497366811353-6870744d04b2?w=800&q=80',
];

// Create uploads directory if it doesn't exist
$uploadDir = __DIR__ . '/public_html/uploads/listings';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
    echo "Created directory: $uploadDir\n";
}

// Download images
$downloadedFiles = [];
$imageIndex = 1;

foreach ($workspaceImages as $url) {
    $filename = 'workspace_' . str_pad($imageIndex, 3, '0', STR_PAD_LEFT) . '.jpg';
    $filepath = $uploadDir . '/' . $filename;
    
    echo "Downloading: $filename...\n";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $imageData = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $imageData) {
        file_put_contents($filepath, $imageData);
        $downloadedFiles[] = '/uploads/listings/' . $filename;
        echo "  ✓ Downloaded successfully\n";
    } else {
        echo "  ✗ Failed to download (HTTP $httpCode)\n";
    }
    
    $imageIndex++;
}

echo "\nDownloaded " . count($downloadedFiles) . " images\n\n";

// Generate UPDATE queries with local paths
echo "=== SQL UPDATE QUERIES ===\n\n";

// Assign images to managed offices
$managedCodes = ['MFO001', 'MFO002', 'MFO003', 'MFO004', 'MFO005', 'MFO006', 'MFO007', 'MFO008', 'MFO009', 'MFO010',
                 'MFO011', 'MFO012', 'MFO013', 'MFO014', 'MFO015', 'MFO016', 'MFO017', 'MFO018', 'MFO019', 'MFO020',
                 'MFO021', 'MFO022', 'MFO023', 'MFO024', 'MFO025', 'MFO026', 'MFO027', 'MFO028', 'MFO029', 'MFO030'];

foreach ($managedCodes as $index => $code) {
    $img1 = $downloadedFiles[($index * 3) % count($downloadedFiles)];
    $img2 = $downloadedFiles[($index * 3 + 1) % count($downloadedFiles)];
    $img3 = $downloadedFiles[($index * 3 + 2) % count($downloadedFiles)];
    
    $imagesJson = json_encode([$img1, $img2, $img3]);
    echo "UPDATE managed_offices SET images = '$imagesJson' WHERE listing_code = '$code';\n";
}

echo "\n";

// Assign images to furnished offices
$furnishedCodes = ['FUO001', 'FUO002', 'FUO003', 'FUO004', 'FUO005', 'FUO006', 'FUO007', 'FUO008', 'FUO009', 'FUO010',
                   'FUO011', 'FUO012', 'FUO013', 'FUO014', 'FUO015', 'FUO016', 'FUO017', 'FUO018', 'FUO019', 'FUO020',
                   'FUO021', 'FUO022', 'FUO023', 'FUO024', 'FUO025', 'FUO026', 'FUO027', 'FUO028', 'FUO029', 'FUO030'];

foreach ($furnishedCodes as $index => $code) {
    $img1 = $downloadedFiles[($index * 2) % count($downloadedFiles)];
    $img2 = $downloadedFiles[($index * 2 + 1) % count($downloadedFiles)];
    
    $imagesJson = json_encode([$img1, $img2]);
    echo "UPDATE furnished_offices SET images = '$imagesJson' WHERE listing_code = '$code';\n";
}

echo "\n";

// Assign images to unfurnished offices
$unfurnishedCodes = ['UFO001', 'UFO002', 'UFO003', 'UFO004', 'UFO005', 'UFO006', 'UFO007', 'UFO008', 'UFO009', 'UFO010',
                     'UFO011', 'UFO012', 'UFO013', 'UFO014', 'UFO015', 'UFO016', 'UFO017', 'UFO018', 'UFO019', 'UFO020',
                     'UFO021', 'UFO022', 'UFO023', 'UFO024', 'UFO025', 'UFO026', 'UFO027', 'UFO028', 'UFO029', 'UFO030'];

foreach ($unfurnishedCodes as $index => $code) {
    $img1 = $downloadedFiles[($index * 2) % count($downloadedFiles)];
    $img2 = $downloadedFiles[($index * 2 + 1) % count($downloadedFiles)];
    
    $imagesJson = json_encode([$img1, $img2]);
    echo "UPDATE unfurnished_offices SET images = '$imagesJson' WHERE listing_code = '$code';\n";
}

echo "\n=== END OF QUERIES ===\n";
echo "\nCopy the queries above and run them in your database to update the image paths.\n";
