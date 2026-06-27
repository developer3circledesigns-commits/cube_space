<?php
$outputFile = __DIR__ . '/insert_100_data.sql';
$fh = fopen($outputFile, 'w');
if (!$fh) { die("Cannot write $outputFile\n"); }

// ---- helpers ----
function q($s) { return "'" . str_replace("'", "''", $s) . "'"; }
function qn($s) { return $s === null ? 'NULL' : q($s); }

function randJsonArr($pool, $min = 2, $max = 6) {
    $max = min($max, count($pool));
    $n = mt_rand($min, $max);
    $keys = array_rand($pool, $n);
    if (!is_array($keys)) $keys = [$keys];
    $items = array_map(fn($k) => $pool[$k], $keys);
    return q(json_encode($items));
}

function randImgArr($prefix, $min = 2, $max = 4) {
    $items = [];
    for ($i = 1; $i <= mt_rand($min, $max); $i++) {
        $items[] = "/uploads/listings/{$prefix}_{$i}.jpg";
    }
    return q(json_encode($items));
}

function randTime() {
    $ts = mt_rand(strtotime('2024-01-01'), strtotime('2025-06-01'));
    return date('Y-m-d H:i:s', $ts);
}

function randTimePast() {
    $offset = mt_rand(0, 60 * 24 * 365);
    return date('Y-m-d H:i:s', time() - $offset);
}

// data pools
$cities = ['Chennai','Bangalore','Hyderabad','Mumbai','Pune','Delhi','Kolkata','Ahmedabad','Noida','Gurgaon'];
$areas = ['Central','North','South','East','West','CBD','IT Corridor','MG Road','Banjara Hills','Koregaon Park','Connaught Place','Salt Lake','SG Highway','Sector 62','DLF Phase 2'];
$titles = ['Platinum','Diamond','Gold','Silver','Bronze','Elite','Premier','Grand','Royal','Supreme','Crystal','Emerald','Sapphire','Ruby','Pearl','Orchid','Lotus','Maple','Oak','Pine','Cedar','Ivy','Iris','Aster','Aura','Nova','Vega','Orion','Zen','Vibe'];
$suffixes = ['Business Hub','Tech Park','Workspace','Executive Centre','Office Suites','Cowork','Business Centre','Corporate Park','Workbay','Innovation Centre'];
$amenitiesPool = ['WiFi','UPS','Valet Parking','Library','Pantry','Gym','Conference Room','Breakout Area','Rooftop Terrace','24/7 Access','Security','Cafe','Parking','AC','Heating','Reception','Event Space','Game Room','Nap Pod','Shower','Bike Storage','Pet Friendly','Mother\'s Room','Prayer Room','Standing Desks','Printing Station','Mail Service','IT Support','Cleaning Service','Kitchenette'];
$featuresPool = ['High-speed internet','Power backup','Fully air-conditioned','Modern furniture','Ergonomic chairs','Meeting rooms','Video conference','Reception area','Visitor parking','Cafeteria','Breakout zone','24/7 CCTV','Access control','Fire safety','Green building','Natural light','Open terrace','Garden area','Smart lockers','Bike parking'];
$firstNames = ['Amit','Priya','Rajesh','Sneha','Vikram','Ananya','Arun','Deepa','Karthik','Lakshmi','Manoj','Nandini','Prakash','Rekha','Suresh','Uma','Venkat','Yamini','Ajay','Bhavana'];
$lastNames = ['Sharma','Verma','Patel','Reddy','Kumar','Singh','Gupta','Joshi','Nair','Menon','Iyer','Rao','Das','Bose','Choudhury','Sen','Banerjee','Mukherjee','Pillai','Nayar'];
$companies = ['TCS','Infosys','Wipro','HCL','Tech Mahindra','L&T','Cognizant','Accenture','Deloitte','KPMG','Amazon','Google','Microsoft','Flipkart','Zoho','Freshworks','Paytm','Swiggy','Zomato','Razorpay','Chargebee','Unacademy','BYJU\'S','Ola','Uber'];

$eventTypes = ['listing_created','listing_updated','listing_deleted','contact_created','contact_updated','contact_deleted','admin_created','admin_updated','admin_deleted','bulk_operation'];
$entityTypes = ['managed_offices','furnished_offices','unfurnished_offices','office_spaces','contacts','admins'];
$logActions = ['create','update','delete','bulk_delete','bulk_status'];
$logTables = ['managed_offices','furnished_offices','unfurnished_offices','office_spaces','contacts','admins','office_leasing_options'];

$interestOptions = ['managed','furnished','unfurnished','commercial'];
$statusList = ['draft','published','archived'];
$contactStatus = ['new','contacted','closed'];
$pageSources = ['website','google','linkedin','facebook','instagram','referral','direct','justdial'];

$spaceTypes = ['rent','lease'];
$priceLabelsManaged = ['Per seat/month','Per seat','Starting at ₹X/seat'];
$priceLabelsFurnished = ['₹X/sqft/month','₹X/sqft','Per sqft/month'];
$seoTexts = [
    'Premium managed office spaces in prime locations. Perfect for businesses looking for flexible workspace solutions.',
    'Fully furnished offices with modern amenities. Ideal for teams of all sizes.',
    'Flexible office spaces with world-class infrastructure and professional support.',
    'Unbeatable location with excellent connectivity and premium facilities.',
];
$inventoryTypes = ['seat','cabin','office','none'];

function writeInserts($fh, $table, $columns, $rows) {
    $colList = implode(', ', $columns);
    fwrite($fh, "INSERT INTO $table ($colList) VALUES\n");
    $last = count($rows) - 1;
    foreach ($rows as $i => $vals) {
        $comma = $i < $last ? ',' : ';';
        fwrite($fh, "($vals)$comma\n");
    }
    fwrite($fh, "\n");
}

function randomName() { global $firstNames, $lastNames; return $firstNames[array_rand($firstNames)] . ' ' . $lastNames[array_rand($lastNames)]; }

fwrite($fh, "-- =============================================================\n");
fwrite($fh, "-- CubeSpace - 100 Sample Rows Per Table\n");
fwrite($fh, "-- Generated: " . date('Y-m-d H:i:s') . "\n");
fwrite($fh, "-- =============================================================\n\n");

// ================================================================
// 1. admins
// ================================================================
$rows = [];
$adminNames = ['admin','editor','manager','superadmin','content'];
$roles = ['admin','editor','manager','superadmin','content'];
for ($i = 0; $i < 100; $i++) {
    $base = $adminNames[array_rand($adminNames)];
    $username = $base . ($i === 0 ? '' : $i);
    $email = $username . '@cubespace.com';
    $password = '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'; // "password"
    $role = $roles[array_rand($roles)];
    $isActive = mt_rand(0, 1);
    $resetToken = mt_rand(0, 1) ? 'NULL' : q(bin2hex(random_bytes(32)));
    $resetExpiry = $resetToken === 'NULL' ? 'NULL' : q(randTime());
    $lastLogin = mt_rand(0, 1) ? q(randTime()) : 'NULL';
    $created = q(randTime());
    $updated = q(randTime());
    $rows[] = implode(', ', [q($username), q($email), q($password), q($role), $isActive, $resetToken, $resetExpiry, $lastLogin, $created, $updated]);
}
writeInserts($fh, 'admins', ['username','email','password','role','is_active','reset_token','reset_token_expiry','last_login','created_at','updated_at'], $rows);

// ================================================================
// 2. managed_offices
// ================================================================
$rows = [];
for ($i = 0; $i < 100; $i++) {
    $code = sprintf('MFO%03d', $i + 1);
    $city = $cities[array_rand($cities)];
    $area = $areas[array_rand($areas)];
    $title = $titles[array_rand($titles)] . ' ' . $suffixes[array_rand($suffixes)];
    $slug = 'managed-' . strtolower(str_replace([' ','\'','/'], '-', $title)) . '-' . $code;
    $desc = "Managed office space in $city $area with premium amenities.";
    $addr = "$area Main Road, $city";
    $lat = round(mt_rand(80, 130) / 10, 7);
    $lng = round(mt_rand(70, 80) / 10, 7);
    $price = mt_rand(100, 250) * 100;
    $priceLabel = $priceLabelsManaged[array_rand($priceLabelsManaged)];
    $seats = mt_rand(5, 250);
    $minInv = mt_rand(1, 10) . ' ' . $inventoryTypes[array_rand($inventoryTypes)];
    $invType = $inventoryTypes[array_rand($inventoryTypes)];
    $sqft = mt_rand(200, 15000);
    $spaceType = $spaceTypes[array_rand($spaceTypes)];
    $amenities = randJsonArr($amenitiesPool);
    $images = randImgArr($code);
    $featured = mt_rand(0, 1);
    $highlights = randJsonArr($featuresPool, 2, 5);
    $seo = $seoTexts[array_rand($seoTexts)];
    $status = $statusList[array_rand($statusList)];
    $created = q(randTime());
    $updated = q(randTime());
    $rows[] = implode(', ', [q($code), q($title), q($slug), q($desc), q('managed'), q($city), q($area), q($addr), $lat, $lng, $price, q($priceLabel), $seats, q($minInv), q($invType), $sqft, q($spaceType), $amenities, $images, $featured, $highlights, q($seo), q($status), $created, $updated]);
}
writeInserts($fh, 'managed_offices', ['listing_code','title','slug','description','listing_type','city','area','address','latitude','longitude','price','price_label','total_seats','min_inventory','inventory_type','total_area_sqft','office_space_type','amenities','images','featured','feature_highlights','seo_text','status','created_at','updated_at'], $rows);

// ================================================================
// 3. furnished_offices
// ================================================================
$rows = [];
for ($i = 0; $i < 100; $i++) {
    $code = sprintf('FUO%03d', $i + 1);
    $city = $cities[array_rand($cities)];
    $area = $areas[array_rand($areas)];
    $title = $titles[array_rand($titles)] . ' ' . $suffixes[array_rand($suffixes)];
    $slug = 'furnished-' . strtolower(str_replace([' ','\'','/'], '-', $title)) . '-' . $code;
    $desc = "Fully furnished office space in $city $area. Ready to move in.";
    $addr = "$area Main Road, $city";
    $lat = round(mt_rand(80, 130) / 10, 7);
    $lng = round(mt_rand(70, 80) / 10, 7);
    $price = mt_rand(50, 200) * 100;
    $priceLabel = $priceLabelsFurnished[array_rand($priceLabelsFurnished)];
    $seats = mt_rand(5, 150);
    $availSqft = mt_rand(200, 10000) . ' - ' . mt_rand(10000, 50000) . ' sqft';
    $minInv = mt_rand(1, 5) . ' ' . $inventoryTypes[array_rand($inventoryTypes)];
    $invType = $inventoryTypes[array_rand($inventoryTypes)];
    $sqft = mt_rand(200, 30000);
    $spaceType = $spaceTypes[array_rand($spaceTypes)];
    $amenities = randJsonArr($amenitiesPool);
    $images = randImgArr($code);
    $featured = mt_rand(0, 1);
    $status = $statusList[array_rand($statusList)];
    $created = q(randTime());
    $updated = q(randTime());
    $rows[] = implode(', ', [q($code), q($title), q($slug), q($desc), q($city), q($area), q($addr), $lat, $lng, $price, q($priceLabel), $seats, q($availSqft), q($minInv), q($invType), $sqft, q($spaceType), $amenities, $images, $featured, q($status), $created, $updated]);
}
writeInserts($fh, 'furnished_offices', ['listing_code','title','slug','description','city','area','address','latitude','longitude','price','price_label','total_seats','available_sqft','min_inventory','inventory_type','total_area_sqft','office_space_type','amenities','images','featured','status','created_at','updated_at'], $rows);

// ================================================================
// 4. unfurnished_offices
// ================================================================
$rows = [];
for ($i = 0; $i < 100; $i++) {
    $code = sprintf('UFU%03d', $i + 1);
    $city = $cities[array_rand($cities)];
    $area = $areas[array_rand($areas)];
    $title = $titles[array_rand($titles)] . ' ' . $suffixes[array_rand($suffixes)];
    $slug = 'unfurnished-' . strtolower(str_replace([' ','\'','/'], '-', $title)) . '-' . $code;
    $desc = "Unfurnished office shell in $city $area. Customise to your needs.";
    $addr = "$area Main Road, $city";
    $lat = round(mt_rand(80, 130) / 10, 7);
    $lng = round(mt_rand(70, 80) / 10, 7);
    $price = mt_rand(30, 150) * 100;
    $priceLabel = $priceLabelsFurnished[array_rand($priceLabelsFurnished)];
    $seats = mt_rand(10, 300);
    $availSqft = mt_rand(500, 10000) . ' - ' . mt_rand(10000, 100000) . ' sqft';
    $minInv = mt_rand(1, 5) . ' ' . $inventoryTypes[array_rand($inventoryTypes)];
    $invType = $inventoryTypes[array_rand($inventoryTypes)];
    $sqft = mt_rand(500, 50000);
    $spaceType = $spaceTypes[array_rand($spaceTypes)];
    $amenities = randJsonArr($amenitiesPool);
    $images = randImgArr($code);
    $featured = mt_rand(0, 1);
    $status = $statusList[array_rand($statusList)];
    $created = q(randTime());
    $updated = q(randTime());
    $rows[] = implode(', ', [q($code), q($title), q($slug), q($desc), q($city), q($area), q($addr), $lat, $lng, $price, q($priceLabel), $seats, q($availSqft), q($minInv), q($invType), $sqft, q($spaceType), $amenities, $images, $featured, q($status), $created, $updated]);
}
writeInserts($fh, 'unfurnished_offices', ['listing_code','title','slug','description','city','area','address','latitude','longitude','price','price_label','total_seats','available_sqft','min_inventory','inventory_type','total_area_sqft','office_space_type','amenities','images','featured','status','created_at','updated_at'], $rows);

// ================================================================
// 5. office_spaces
// ================================================================
$rows = [];
for ($i = 0; $i < 100; $i++) {
    $code = sprintf('OSP%03d', $i + 1);
    $city = $cities[array_rand($cities)];
    $area = $areas[array_rand($areas)];
    $title = $titles[array_rand($titles)] . ' ' . $suffixes[array_rand($suffixes)];
    $slug = 'space-' . strtolower(str_replace([' ','\'','/'], '-', $title)) . '-' . $code;
    $desc = "Commercial office space in $city $area for lease.";
    $addr = "$area Main Road, $city";
    $lat = round(mt_rand(80, 130) / 10, 7);
    $lng = round(mt_rand(70, 80) / 10, 7);
    $price = mt_rand(80, 300) * 100;
    $priceLabel = $priceLabelsFurnished[array_rand($priceLabelsFurnished)];
    $seats = mt_rand(10, 500);
    $sqft = mt_rand(500, 50000);
    $spaceType = $spaceTypes[array_rand($spaceTypes)];
    $amenities = randJsonArr($amenitiesPool);
    $images = randImgArr($code);
    $featured = mt_rand(0, 1);
    $highlights = randJsonArr($featuresPool, 2, 5);
    $seo = $seoTexts[array_rand($seoTexts)];
    $status = $statusList[array_rand($statusList)];
    $created = q(randTime());
    $updated = q(randTime());
    $rows[] = implode(', ', [q($code), q($title), q($slug), q($desc), q('commercial'), q($city), q($area), q($addr), $lat, $lng, $price, q($priceLabel), $seats, $sqft, q($spaceType), $amenities, $images, $featured, $highlights, q($seo), q($status), $created, $updated]);
}
writeInserts($fh, 'office_spaces', ['listing_code','title','slug','description','listing_type','city','area','address','latitude','longitude','price','price_label','total_seats','total_area_sqft','office_space_type','amenities','images','featured','feature_highlights','seo_text','status','created_at','updated_at'], $rows);

// ================================================================
// 6. contacts
// ================================================================
$rows = [];
for ($i = 0; $i < 100; $i++) {
    $name = randomName();
    $phone = mt_rand(9000000000, 9999999999);
    $email = strtolower(str_replace(' ', '.', $name)) . '@example.com';
    $interest = $interestOptions[array_rand($interestOptions)];
    $company = $companies[array_rand($companies)];
    $seats = mt_rand(5, 200) . '+' . mt_rand(0, 50);
    $message = "Interested in " . $interestOptions[array_rand($interestOptions)] . " office space for $seats people.";
    $officeId = mt_rand(1, 100);
    $listingCode = strtoupper(substr($interest, 0, 3)) . sprintf('%03d', $officeId);
    $source = $pageSources[array_rand($pageSources)];
    $ip = mt_rand(10, 223) . '.' . mt_rand(0, 255) . '.' . mt_rand(0, 255) . '.' . mt_rand(1, 254);
    $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36';
    $status = $contactStatus[array_rand($contactStatus)];
    $notes = mt_rand(0, 1) ? q('Followed up via phone.') : 'NULL';
    $contacted = ($status === 'contacted' || $status === 'closed') ? q(randTime()) : 'NULL';
    $closed = $status === 'closed' ? q(randTime()) : 'NULL';
    $created = q(randTime());
    $updated = q(randTime());
    $rows[] = implode(', ', [q($name), q($phone), q($email), q($interest), q($company), q($seats), q($message), $officeId, q($listingCode), q($source), q($ip), q($ua), q($status), $notes, $contacted, $closed, $created, $updated]);
}
writeInserts($fh, 'contacts', ['name','phone','email','interest','company','seats','message','office_id','listing_code','source','submitted_ip','user_agent','status','admin_notes','contacted_at','closed_at','created_at','updated_at'], $rows);

// ================================================================
// 7. office_leasing_options
// ================================================================
$leasingTitles = ['Standard Desk','Premium Desk','Private Cabin','Executive Cabin','Team Suite','Corner Office','Conference Room','Virtual Office','Day Pass','Hot Desk'];
$leasingDescs = [
    'A dedicated desk in a shared workspace.',
    'A premium ergonomic desk with extra storage.',
    'Private lockable cabin for focused work.',
    'Spacious cabin for senior executives.',
    'Suite for teams of 4-6 members.',
    'Prime corner office with natural lighting.',
    'Fully equipped conference room.',
    'Prestigious business address with mail handling.',
    'Access to workspace for a single day.',
    'Flexible desk in a common area.'
];
$leasingPrices = ['₹8,000/month','₹12,000/month','₹25,000/month','₹40,000/month','₹75,000/month','₹1,20,000/month','₹3,000/hour','₹5,000/month','₹1,000/day','₹6,000/month'];

$rows = [];
for ($i = 0; $i < 100; $i++) {
    $officeId = mt_rand(1, 50);
    $idx = array_rand($leasingTitles);
    $optionTitle = q($leasingTitles[$idx]);
    $optionDesc = q($leasingDescs[$idx]);
    $optionPrice = q($leasingPrices[array_rand($leasingPrices)]);
    $optionImage = mt_rand(0, 1) ? q('/uploads/leasing/option_' . mt_rand(1, 20) . '.jpg') : 'NULL';
    $sortOrder = mt_rand(0, 10);
    $isActive = mt_rand(0, 1);
    $created = q(randTime());
    $updated = q(randTime());
    $rows[] = implode(', ', [$officeId, $optionTitle, $optionDesc, $optionPrice, $optionImage, $sortOrder, $isActive, $created, $updated]);
}
writeInserts($fh, 'office_leasing_options', ['office_id','option_title','option_desc','option_price','option_image','sort_order','is_active','created_at','updated_at'], $rows);

// ================================================================
// 8. activity_log
// ================================================================
$rows = [];
for ($i = 0; $i < 100; $i++) {
    $adminId = mt_rand(1, 5);
    $adminUser = q(['admin','editor','manager','superadmin','content'][array_rand(['admin','editor','manager','superadmin','content'])]);
    $action = q($logActions[array_rand($logActions)]);
    $tableName = q($logTables[array_rand($logTables)]);
    $recordId = mt_rand(1, 100);
    $detailsObj = ['field' => 'status', 'old' => 'draft', 'new' => 'published'];
    $details = q(json_encode($detailsObj));
    $ip = q(mt_rand(10, 223) . '.' . mt_rand(0, 255) . '.' . mt_rand(0, 255) . '.' . mt_rand(1, 254));
    $created = q(randTime());
    $rows[] = implode(', ', [$adminId, $adminUser, $action, $tableName, $recordId, $details, $ip, $created]);
}
writeInserts($fh, 'activity_log', ['admin_id','admin_username','action','table_name','record_id','details','ip_address','created_at'], $rows);

// ================================================================
// 9. realtime_events
// ================================================================
$rows = [];
for ($i = 0; $i < 100; $i++) {
    $eventType = q($eventTypes[array_rand($eventTypes)]);
    $entityType = q($entityTypes[array_rand($entityTypes)]);
    $entityId = mt_rand(1, 100);
    $summary = q('Event: ' . $eventType . ' on ' . $entityType . ' #' . $entityId);
    $created = q(randTime());
    $rows[] = implode(', ', [$eventType, $entityType, $entityId, $summary, $created]);
}
writeInserts($fh, 'realtime_events', ['event_type','entity_type','entity_id','summary','created_at'], $rows);

fclose($fh);
echo "Generated: $outputFile\n";
echo "Size: " . number_format(filesize($outputFile)) . " bytes\n";
