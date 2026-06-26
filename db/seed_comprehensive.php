<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
require_once __DIR__ . '/../config/database.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Connected to database successfully.\n";

// ---- CONFIG ----
$targetManaged = 100;
$targetSpaces = 100;
$targetContacts = 100;

// ---- DATA POOLS ----
$cities = ['chennai', 'bangalore'];
$cityAreas = [
    'chennai'   => ['Adyar','Anna Nagar','Besant Nagar','Chromepet','Egmore','Guindy','Kotturpuram','Mylapore','Nungambakkam','OMR','Perungudi','Pallikaranai','Porur','Royapettah','Saidapet','Sholinganallur','T Nagar','Velachery','Alandur','Tambaram','Kodambakkam','Nandanam','Koyambedu','Ashok Nagar','Alwarpet'],
    'bangalore' => ['Koramangala','Indiranagar','Whitefield','Marathahalli','HSR Layout','JP Nagar','Jayanagar','MG Road','Bannerghatta Road','Yelahanka','Electronic City','Hebbal','BTM Layout','Rajajinagar','Sadashivanagar','Banashankari','Basavanagudi','Malleshwaram','Domlur','Vijayanagar']
];

$amenitiesPool = [
    'High-speed WiFi', 'Meeting Rooms', 'Cafeteria', '24/7 Access', 'Power Backup',
    'Security', 'Parking', 'AC', 'Pantry', 'Lounge Area', 'Cleaning Service',
    'Reception', 'Event Space', 'IT Support', 'Gym', 'Breakout Area',
    'Video Conferencing', 'Visitor Lounge', 'Coffee Bar', 'Phone Booths'
];

$highlightsPool = [
    'Fully furnished', 'Prime city location', 'Smart office automation',
    'Professional reception', 'Virtual office support', 'Premium business address',
    'Dedicated workstations', 'Flexible lease terms', 'Ample parking',
    'Green campus', 'Wellness lounge', 'Customer support', '24/7 security'
];

$statuses = ['published', 'draft'];
$listingTypes = ['rent', 'lease'];
$interestTypes = ['Co-working Space', 'Private Office', 'Virtual Office', 'Meeting Room', 'Office Space for Rent', 'Managed Office'];
$contactStatuses = ['new', 'contacted', 'qualified', 'converted', 'closed'];

// Unsplash image IDs for office/workspace (high-quality real photos)
$unsplashIds = [
    'https://images.unsplash.com/photo-1497366216548-37526070297c', // modern office lobby
    'https://images.unsplash.com/photo-1497366811353-6870744d04b2', // office meeting room
    'https://images.unsplash.com/photo-1504384308090-c894fdcc538d', // open plan office
    'https://images.unsplash.com/photo-1519389950473-47ba0277781c', // tech office
    'https://images.unsplash.com/photo-1522071820081-009f0129c71c', // team working
    'https://images.unsplash.com/photo-1531973576160-7125cd663d86', // office corridor
    'https://images.unsplash.com/photo-1556761175-b413da4baf72', // presentation
    'https://images.unsplash.com/photo-1560179707-f14e90ef3623', // building exterior
    'https://images.unsplash.com/photo-1571624436279-b272aff752b5', // modern desk
    'https://images.unsplash.com/photo-1577412647305-991150c7d163', // lounge area
    'https://images.unsplash.com/photo-1581091226825-a6a2a5aee158', // office chair
    'https://images.unsplash.com/photo-1600880292203-757bb62b4baf', // meeting table
    'https://images.unsplash.com/photo-1604328698692-f76ea9498e72', // coworking space
    'https://images.unsplash.com/photo-1616588589676-62b3bd4ff6d2', // modern building
    'https://images.unsplash.com/photo-1629904853893-c2c8981a1dc5', // office interior
    'https://images.unsplash.com/photo-1631217868264-e5b90bb7e133', // workspace desk
    'https://images.unsplash.com/photo-1644329900649-7d3f4b5d8d3a', // glass office
    'https://images.unsplash.com/photo-1644374146066-52e5270b3f4c', // creative office
    'https://images.unsplash.com/photo-1644454431461-3c2cb7c6b9e8', // office plants
    'https://images.unsplash.com/photo-1647561868041-0d3c4b6a8e7f', // conference room
];

function pick($arr) { return $arr[array_rand($arr)]; }

function pickN($arr, $n) {
    $n = min($n, count($arr));
    $keys = array_rand($arr, $n);
    $keys = is_array($keys) ? $keys : [$keys];
    return array_map(fn($k) => $arr[$k], $keys);
}

function slugify($str) {
    return strtolower(trim(preg_replace('/[^a-z0-9-]+/', '-', $str), '-'));
}

function unsplashImages() {
    global $unsplashIds;
    $selected = pickN($unsplashIds, rand(3, 5));
    return json_encode(array_map(fn($u) => $u . '?w=800&q=80', $selected));
}

function generateTitle($city, $area, $type, $num) {
    $prefixes = ['CubeSpace', 'WorkHub', 'OfficePro', 'SpaceNext', 'BizHive', 'CoWorks', 'GrowSpace'];
    $suffixes = ['Business Center', 'Workspace', 'Office Suites', 'Executive Center', 'Professional Hub'];
    if ($type === 'office_spaces') {
        $suffixes = ['Office Space', 'Commercial Hub', 'Corporate Suite', 'Business Bay'];
    }
    $prefix = pick($prefixes);
    $suffix = pick($suffixes);
    return "$prefix $area $suffix $num";
}

function generateDescription($city, $area) {
    $features = ['modern amenities', 'fast connectivity', 'a professional environment', 'flexible layouts', 'world-class infrastructure'];
    return "Premium workspace in $area, " . ucfirst($city) . ". Located in a prime business district, this center offers " . pick($features) . " ideal for growing businesses and established enterprises alike. " . pick(['Enjoy seamless operations with our fully managed services.', 'Experience productivity like never before.']);
}

function generateAddress($city, $area, $num) {
    $streets = ['Main Road', 'High Road', 'Cross Road', 'Ring Road', 'Boulevard', 'Street', 'Avenue', 'Layout'];
    if ($city === 'chennai') {
        $roadNames = ['Anna Salai', 'Mount Road', 'OMR', 'ECR', 'Nungambakkam High Road', 'Sardar Patel Road', 'Poonamallee High Road'];
    } else {
        $roadNames = ['MG Road', 'Brigade Road', 'Church Street', '100 Feet Road', 'Tumkur Road', 'Kanakapura Road', 'Old Airport Road'];
    }
    return "$num, " . pick($roadNames) . ", $area, " . ucfirst($city) . " - " . str_pad($num, 6, '0', STR_PAD_LEFT);
}

function generateLatLong($city, $area, $index) {
    if ($city === 'chennai') {
        return [13.0 + ($index % 50) * 0.004, 80.2 + ($index % 50) * 0.003];
    } else {
        return [12.9 + ($index % 50) * 0.003, 77.5 + ($index % 50) * 0.004];
    }
}

function generatePrice($baseMin, $baseMax) {
    return round(rand($baseMin, $baseMax) / 100) * 100;
}

function generateSeoText($city, $area) {
    return "Workspaces in $area, " . ucfirst($city) . " with meeting rooms, high-speed internet, parking, and concierge services. Prime business location with excellent connectivity.";
}

// ---- MAIN SEED FUNCTION ----
function seedTable($conn, $table, $count, $existingSlugs, $type) {
    global $cities, $cityAreas, $amenitiesPool, $highlightsPool, $statuses, $listingTypes;

    $inserted = 0;
    $stmt = null;

    if ($type === 'managed_offices' || $type === 'office_spaces') {
        $sql = "INSERT IGNORE INTO `$table` 
            (`title`, `slug`, `description`, `city`, `area`, `address`, `latitude`, `longitude`, 
             `price`, `price_label`, `total_seats`, `total_area_sqft`, `amenities`, `images`, 
             `featured`, `status`, `office_space_type`, `feature_highlights`, `seo_text`)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
    } else {
        echo "Unknown type: $type\n";
        return 0;
    }

    $attempt = 0;
    while ($inserted < $count && $attempt < $count * 3) {
        $attempt++;

        $city = pick($cities);
        $area = pick($cityAreas[$city]);

        $idx = $attempt + 500;
        $title = generateTitle($city, $area, $type, $idx);
        $slug = slugify($title);

        // unique slug
        $baseSlug = $slug;
        $suffix = 1;
        while (isset($existingSlugs[$slug])) {
            $slug = $baseSlug . '-' . ($suffix++);
        }

        $desc = generateDescription($city, $area);
        $address = generateAddress($city, $area, $idx);
        list($lat, $lng) = generateLatLong($city, $area, $idx);

        $seats = rand(10, 60);
        $sqft = $seats * rand(80, 140);

        if ($city === 'bangalore') {
            $basePrice = rand(10000, 22000);
        } else {
            $basePrice = rand(10000, 20000);
        }
        $price = generatePrice($basePrice, $basePrice + 6000);
        $priceLabel = '₹' . number_format($price) . '/seat/month';

        $amenities = pickN($amenitiesPool, rand(4, 8));
        $highlights = pickN($highlightsPool, rand(3, 5));
        $status = pick($statuses);
        $featured = ($status === 'published' && rand(1, 100) <= 20) ? 1 : 0;
        $listingType = pick($listingTypes);

        $imagesJson = unsplashImages();
        $seoText = generateSeoText($city, $area);

        $amenitiesJson = json_encode($amenities);
        $highlightsJson = json_encode($highlights);
        $stmt->bind_param('ssssssdddsiississss',
            $title, $slug, $desc, $city, $area, $address,
            $lat, $lng, $price, $priceLabel, $seats, $sqft,
            $amenitiesJson, $imagesJson,
            $featured, $status, $listingType,
            $highlightsJson, $seoText
        );

        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $inserted++;
            $existingSlugs[$slug] = true;
            if ($inserted % 10 === 0) {
                echo "  $table: inserted $inserted / $count\n";
            }
        }
    }

    $stmt->close();
    echo "  $table: DONE - inserted $inserted records\n";
    return $existingSlugs;
}

// =========================
// 1. SEED MANAGED_OFFICES
// =========================
echo "\n--- SEEDING managed_offices ---\n";

$result = $conn->query("SELECT slug FROM managed_offices");
$existingSlugs = [];
while ($row = $result->fetch_assoc()) {
    $existingSlugs[$row['slug']] = true;
}
$currentCount = count($existingSlugs);
echo "Current managed_offices: $currentCount\n";

if ($currentCount < $targetManaged) {
    $newSlugs = seedTable($conn, 'managed_offices', $targetManaged - $currentCount, $existingSlugs, 'managed_offices');
    if (is_array($newSlugs)) $existingSlugs = $newSlugs;
} else {
    echo "Already at or above target.\n";
}

// =========================
// 2. SEED OFFICE_SPACES
// =========================
echo "\n--- SEEDING office_spaces ---\n";

$result = $conn->query("SELECT slug FROM office_spaces");
$existingSlugs2 = [];
while ($row = $result->fetch_assoc()) {
    $existingSlugs2[$row['slug']] = true;
}
$currentCount2 = count($existingSlugs2);
echo "Current office_spaces: $currentCount2\n";

if ($currentCount2 < $targetSpaces) {
    seedTable($conn, 'office_spaces', $targetSpaces - $currentCount2, $existingSlugs2, 'office_spaces');
} else {
    echo "Already at or above target.\n";
}

// =========================
// 3. SEED CONTACTS
// =========================
echo "\n--- SEEDING contacts ---\n";

$result = $conn->query("SELECT COUNT(*) as cnt FROM contacts");
$currentContacts = (int)$result->fetch_assoc()['cnt'];
echo "Current contacts: $currentContacts\n";

if ($currentContacts < $targetContacts) {
    $need = $targetContacts - $currentContacts;
    $inserted = 0;
    $attempt = 0;

    $sql = "INSERT IGNORE INTO `contacts` 
            (`name`, `phone`, `email`, `interest`, `company`, `seats`, `message`, `source`, `status`, `created_at`)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);

    $firstNames = ['Rajesh','Priya','Arun','Divya','Suresh','Anita','Vijay','Kavita','Ravi','Meena',
                   'Amit','Sunita','Deepak','Pooja','Sanjay','Neha','Kumar','Lakshmi','Mohan','Geeta',
                   'Rahul','Anjali','Varun','Shreya','Vikram','Nandini','Siddharth','Aishwarya','Prakash','Swati',
                   'Ganesh','Radhika','Akash','Madhuri','Hari','Vasundhara','Karthik','Bhavana','Sriram','Indira',
                   'Manoj','Deepika','Ramesh','Shweta','Naveen','Sangeeta','Dinesh','Rekha','Harish','Uma'];
    $lastNames = ['Kumar','Sharma','Patel','Reddy','Gupta','Verma','Singh','Joshi','Rao','Menon',
                  'Iyer','Nair','Das','Sen','Choudhury','Bose','Mukherjee','Banerjee','Pillai','Naidu',
                  'Acharya','Desai','Shah','Mehta','Agarwal','Saxena','Trivedi','Pandey','Srivastava','Mishra'];
    $companies = ['TechSolutions','InnovateCorp','DataMatrix','CloudNine','ApexSystems','NexGen','PixelPerfect',
                  'QuantumLeap','StellarTech','VelocitySoft','PrimeMovers','GrowthAxis','BrightWave','ElevateTech',
                  'FusionWorks','CrestData','OrbitDigital','PioneerHive','SummitSoft','VertexGlobal'];

    while ($inserted < $need && $attempt < $need * 3) {
        $attempt++;
        $name = pick($firstNames) . ' ' . pick($lastNames);
        $email = strtolower($name[0] . pick($firstNames) . rand(10, 99)) . '@' . pick(['gmail.com','outlook.com','yahoo.com','company.com']) ;
        $email = str_replace(' ', '', $email);
        $phone = '+91 ' . rand(70000, 99999) . ' ' . rand(10000, 99999);
        $interest = pick($interestTypes);
        $company = pick($companies);
        $seats = rand(1, 50) . '+' ;
        $source = pick(['website','google','referral','instagram','facebook','linkedin','direct']);
        $status = pick($contactStatuses);
        $msg = rand(0, 1) ? "Interested in $interest for our team of $seats people. Please share more details." : null;
        $created = date('Y-m-d H:i:s', strtotime("-" . rand(1, 365) . " days"));

        $stmt->bind_param('ssssssssss', $name, $phone, $email, $interest, $company, $seats, $msg, $source, $status, $created);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $inserted++;
            if ($inserted % 10 === 0) echo "  contacts: inserted $inserted / $need\n";
        }
    }
    $stmt->close();
    echo "  contacts: DONE - inserted $inserted records\n";
}

// =========================
// 4. FIX ORPHANED REVIEWS & FAQ
// =========================
echo "\n--- CLEANING UP orphaned records ---\n";

// Get valid office IDs
$result = $conn->query("SELECT id FROM managed_offices ORDER BY id LIMIT 100");
$validIds = [];
while ($row = $result->fetch_assoc()) {
    $validIds[] = $row['id'];
}

if (count($validIds) > 0) {
    // Update orphaned reviews to point to next valid office
    $conn->query("UPDATE office_reviews SET office_id = {$validIds[0]} WHERE office_id NOT IN (SELECT id FROM managed_offices)");
    $conn->query("UPDATE office_faq SET office_id = {$validIds[0]} WHERE office_id NOT IN (SELECT id FROM managed_offices)");
    $conn->query("UPDATE office_building_details SET office_id = {$validIds[0]} WHERE office_id NOT IN (SELECT id FROM managed_offices)");
    $conn->query("UPDATE office_leasing_options SET office_id = {$validIds[0]} WHERE office_id NOT IN (SELECT id FROM managed_offices)");
    echo "  Updated orphaned reviews/FAQ/building/leasing to point to office_id = {$validIds[0]}\n";
} else {
    echo "  WARNING: No valid office IDs found to fix orphaned records.\n";
}

// =========================
// 5. ADD REVIEWS FOR NEW OFFICES (at least a few)
// =========================
echo "\n--- ADDING sample reviews for new offices ---\n";

$reviewerNames = ['Rajesh Kumar','Priya Sharma','Arun Patel','Divya Reddy','Suresh Gupta','Anita Verma','Vijay Singh','Kavita Joshi'];
$reviewTexts = [
    'Excellent workspace with great amenities. Highly recommended.',
    'Very professional environment. The staff is extremely helpful.',
    'Great location and beautiful office space. Perfect for our team.',
    'Clean, modern, and well-maintained facility. Love working here.',
    'Good value for money. All amenities work perfectly.',
    'The best coworking space in the area. Highly productive environment.',
];
$ratings = [4, 5, 4, 5, 3, 5, 4, 5];

// Add reviews for offices that don't have them yet
$sqlGetOffices = "SELECT id FROM managed_offices WHERE id NOT IN (SELECT DISTINCT office_id FROM office_reviews) LIMIT 50";
$result = $conn->query($sqlGetOffices);
$officeIds = [];
while ($row = $result->fetch_assoc()) {
    $officeIds[] = $row['id'];
}

$reviewStmt = $conn->prepare("INSERT IGNORE INTO office_reviews (office_id, reviewer_name, rating, review_text, status) VALUES (?, ?, ?, ?, 'approved')");
$added = 0;
foreach ($officeIds as $oid) {
    $reviewer = pick($reviewerNames);
    $text = pick($reviewTexts);
    $rating = pick($ratings);
    $reviewStmt->bind_param('isis', $oid, $reviewer, $rating, $text);
    if ($reviewStmt->execute() && $reviewStmt->affected_rows > 0) {
        $added++;
    }
}
$reviewStmt->close();
echo "  Added $added reviews for new offices.\n";

// =========================
// 6. ADD FAQ FOR NEW OFFICES
// =========================
$faqQuestions = [
    ['What are the operating hours?', 'The center is open 24/7 for all members.'],
    ['Is parking available?', 'Yes, we have ample parking space for all members and visitors.'],
    ['Can I get a virtual office address?', 'Yes, we offer virtual office packages starting from ₹2,000/month.'],
    ['What internet speed is provided?', 'We provide high-speed fiber internet with 100 Mbps dedicated connection.'],
    ['Are meeting rooms included?', 'Yes, all members get complimentary access to meeting rooms for up to 4 hours per month.'],
];

$faqStmt = $conn->prepare("INSERT IGNORE INTO office_faq (office_id, question, answer, sort_order, is_active) VALUES (?, ?, ?, ?, 1)");
$added = 0;
foreach ($officeIds as $oid) {
    $faq = pick($faqQuestions);
    $order = rand(1, 5);
    $faqStmt->bind_param('issi', $oid, $faq[0], $faq[1], $order);
    if ($faqStmt->execute() && $faqStmt->affected_rows > 0) {
        $added++;
    }
}
$faqStmt->close();
echo "  Added $added FAQs for new offices.\n";

$conn->close();
echo "\n=== SEEDING COMPLETE ===\n";
