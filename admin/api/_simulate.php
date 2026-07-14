<?php
// Simulate the exact same request as the admin panel
$_GET = ['action' => 'update'];
$_POST = [
    'id' => '1',
    'listing_type' => 'furnished',
    'title' => 'Test Sample',
    'city' => 'chennai',
    'address' => 'Test Address',
    'price' => '100',
    'total_area_sqft' => '5000',
    'available_sqft' => '1000-5000',
    'inventory_type' => 'Ready to move in',
    'status' => 'inactive',
    'office_space_type' => 'rent',
    'description' => 'Test',
    'existing_images' => '[]',
    'amenities' => '[]',
];
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_X_CSRF_TOKEN'] = 'test';
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer test';
$_COOKIE = [];

ob_start();
try {
    require '/var/www/html/admin/api/listing_crud.php';
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
$output = ob_get_clean();
echo "Output: $output\n";
