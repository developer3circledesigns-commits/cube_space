<?php
require_once __DIR__ . '/../../config/database.php';
$conn = mysqli_connect('127.0.0.1', 'root', 'rootpassword', 'u814177917_cubespace', 3307);
if (!$conn) { die('Connection failed: ' . mysqli_connect_error()); }
$r = mysqli_query($conn, "ALTER TABLE furnished_offices ADD COLUMN remarks TEXT DEFAULT NULL AFTER inventory_type");
if ($r) { echo "Column 'remarks' added successfully.\n"; }
else { echo 'Error: ' . mysqli_error($conn) . "\n"; }
mysqli_close($conn);
