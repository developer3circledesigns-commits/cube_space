$sqlFile = "C:\xampp\htdocs\cubespace\insert_100_data.sql"
$output = New-Object System.Text.StringBuilder

function JsonArr([string[]]$items) {
    return "'[" + (($items | ForEach-Object { "`"$_`"" }) -join ",") + "]'"
}
function Slug($s) {
    $s = "$s".Replace("'","").Replace(" ","-")
    return $s.ToLower()
}

$cityData = @(
    @{city='Chennai';area='Perungudi';lat=12.9676;lng=80.2427;priceMin=15000;priceMax=35000},
    @{city='Chennai';area='Guindy';lat=13.0072;lng=80.2128;priceMin=20000;priceMax=40000},
    @{city='Chennai';area='Taramani';lat=12.9897;lng=80.2436;priceMin=25000;priceMax=45000},
    @{city='Chennai';area='Sholinganallur';lat=12.9063;lng=80.2283;priceMin=12000;priceMax=30000},
    @{city='Chennai';area='Porur';lat=13.0399;lng=80.1553;priceMin=10000;priceMax=25000},
    @{city='Chennai';area='Anna Nagar';lat=13.0850;lng=80.2101;priceMin=20000;priceMax=45000},
    @{city='Chennai';area='T Nagar';lat=13.0412;lng=80.2340;priceMin=18000;priceMax=35000},
    @{city='Chennai';area='Velachery';lat=12.9815;lng=80.2180;priceMin=15000;priceMax=30000},
    @{city='Chennai';area='Nungambakkam';lat=13.0568;lng=80.2448;priceMin=25000;priceMax=50000},
    @{city='Chennai';area='Adyar';lat=13.0013;lng=80.2573;priceMin=20000;priceMax=40000},
    @{city='Chennai';area='Egmore';lat=13.0764;lng=80.2591;priceMin=18000;priceMax=35000},
    @{city='Chennai';area='Mylapore';lat=13.0337;lng=80.2644;priceMin=20000;priceMax=38000},
    @{city='Chennai';area='Ambattur';lat=13.1161;lng=80.1592;priceMin=8000;priceMax=18000},
    @{city='Chennai';area='Tambaram';lat=12.9249;lng=80.1000;priceMin=8000;priceMax=15000},
    @{city='Chennai';area='Chromepet';lat=12.9535;lng=80.1429;priceMin=8000;priceMax=16000},
    @{city='Bangalore';area='MG Road';lat=12.9716;lng=77.5946;priceMin=25000;priceMax=55000},
    @{city='Bangalore';area='Electronic City';lat=12.8453;lng=77.6603;priceMin=15000;priceMax=35000},
    @{city='Bangalore';area='Whitefield';lat=12.9698;lng=77.7500;priceMin=18000;priceMax=40000},
    @{city='Bangalore';area='Koramangala';lat=12.9279;lng=77.6271;priceMin=20000;priceMax=45000},
    @{city='Bangalore';area='Indiranagar';lat=12.9784;lng=77.6408;priceMin=22000;priceMax=50000},
    @{city='Hyderabad';area='Hitech City';lat=17.4499;lng=78.3873;priceMin=20000;priceMax=45000},
    @{city='Hyderabad';area='Gachibowli';lat=17.4401;lng=78.3489;priceMin=18000;priceMax=40000},
    @{city='Hyderabad';area='Madhapur';lat=17.4344;lng=78.4033;priceMin=15000;priceMax=38000},
    @{city='Mumbai';area='BKC';lat=19.0760;lng=72.8777;priceMin=35000;priceMax=65000},
    @{city='Mumbai';area='Andheri';lat=19.1136;lng=72.8697;priceMin=20000;priceMax=45000},
    @{city='Mumbai';area='Powai';lat=19.1176;lng=72.9118;priceMin=25000;priceMax=50000},
    @{city='Mumbai';area='Lower Parel';lat=18.9943;lng=72.8258;priceMin=30000;priceMax=60000},
    @{city='Pune';area='Hinjawadi';lat=18.5782;lng=73.7397;priceMin=12000;priceMax=30000},
    @{city='Pune';area='Baner';lat=18.5600;lng=73.7867;priceMin=15000;priceMax=35000},
    @{city='Delhi';area='Nehru Place';lat=28.5494;lng=77.2533;priceMin=18000;priceMax=40000},
    @{city='Delhi';area='Dwarka';lat=28.5855;lng=77.0780;priceMin=12000;priceMax=28000},
    @{city='Gurgaon';area='Cyber Hub';lat=28.4956;lng=77.0925;priceMin=20000;priceMax=45000},
    @{city='Noida';area='Sector 62';lat=28.6176;lng=77.3666;priceMin=15000;priceMax=35000},
    @{city='Kolkata';area='Salt Lake';lat=22.5804;lng=88.4176;priceMin=12000;priceMax=30000},
    @{city='Ahmedabad';area='SG Highway';lat=23.0800;lng=72.5475;priceMin=10000;priceMax=25000},
    @{city='Jaipur';area='Malviya Nagar';lat=26.8536;lng=75.8152;priceMin=8000;priceMax=20000},
    @{city='Lucknow';area='Gomti Nagar';lat=26.8477;lng=80.9956;priceMin=8000;priceMax=18000},
    @{city='Chandigarh';area='Sector 17';lat=30.7333;lng=76.7794;priceMin=12000;priceMax=28000},
    @{city='Coimbatore';area='Peelamedu';lat=11.0168;lng=77.0029;priceMin=8000;priceMax=20000},
    @{city='Kochi';area='Infopark';lat=10.0081;lng=76.3372;priceMin=10000;priceMax=25000},
    @{city='Vizag';area='Madhurawada';lat=17.7642;lng=83.3376;priceMin=6000;priceMax=18000}
)

$amenitySets = @(
    @("WiFi","AC","Parking","Cafeteria","Security"),
    @("WiFi","AC","Parking","Security","Gym"),
    @("WiFi","AC","Parking","Cafeteria","Security","Gym","Reception"),
    @("WiFi","AC","Parking","Cafeteria"),
    @("WiFi","AC","Parking","Security","Power Backup","Cafeteria"),
    @("WiFi","AC","Parking","Cafeteria","Security","IT Support"),
    @("WiFi","AC","Parking","Cafeteria","Gym","Pool","Security"),
    @("WiFi","AC","Parking","Security"),
    @("WiFi","AC","Parking","Reception","Cafeteria","Security","Housekeeping"),
    @("WiFi","AC","Parking","Cafeteria","Meeting Rooms","Security")
)
function RandomAmenities {
    $idx = Get-Random -Minimum 0 -Maximum $amenitySets.Length
    return JsonArr $amenitySets[$idx]
}
function RandomImage($code, [int]$count = 1) {
    $imgs = @()
    for ($i = 1; $i -le $count; $i++) { $imgs += "/uploads/listings/$code`_$i.jpg" }
    return JsonArr $imgs
}

$titles = @("Managed Office","Business Center","Workspace Hub","Executive Suite","Corporate Space","Professional Office","Business Lounge","Smart Office","Tech Hub","Startup Space","Enterprise Suite","Premium Workspace","Flexi Office","Collaborative Space","Business Point","Office Pod","Work Station","Professional Hub","Corporate Hub","Innovation Lab")
function RandomTitle($city, $area) {
    $t = $titles[(Get-Random -Minimum 0 -Maximum $titles.Length)]
    return "'$city $area $t'"
}
function RandomDesc($city, $area) {
    $descs = @(
        "Premium managed office space in $city $area with modern amenities.",
        "Fully managed workspace located in $area, $city with 24/7 access.",
        "Professional managed office in $area, $city for growing businesses.",
        "Well-equipped managed office in $city $area with all amenities included.",
        "Prime managed workspace at $area, $city with high-speed internet and support staff.",
        "Contemporary managed office in $area, $city with flexible terms.",
        "Managed business center in $city $area offering plug-and-play workspace.",
        "Affordable managed office in $area, $city with great connectivity.",
        "Executive managed office space in $city $area with premium facilities.",
        "Managed workspace solution in $area, $city with full IT support."
    )
    return "'$($descs[(Get-Random -Minimum 0 -Maximum $descs.Length)])'"
}
function RandomSeoText($city, $area) {
    $seos = @(
        "Find best managed offices in $area, $city.",
        "Top managed workspace in $area, $city for startups.",
        "Premium managed offices available in $city $area.",
        "Affordable managed workspace in $area, $city.",
        "Prime managed office space in $city $area.",
        "Managed offices in $area, $city with best amenities."
    )
    return "'$($seos[(Get-Random -Minimum 0 -Maximum $seos.Length)])'"
}
function RandomHighlights {
    $sets = @(
        @("24/7 Access","High-speed Internet","Meeting Rooms","Pantry"),
        @("Power Backup","Parking","CCTV","Reception"),
        @("Prime Location","Fully Furnished","Pantry","Meeting Room"),
        @("IT Support","Reception","Power Backup","Cleaning"),
        @("Easy Connectivity","Parking","Cafeteria","Meeting Room"),
        @("Gym","Cafeteria","Parking","Security"),
        @("Rooftop","Valet","Gym","Premium Amenities")
    )
    return JsonArr $sets[(Get-Random -Minimum 0 -Maximum $sets.Length)]
}
function RandomBldgName {
    $names = @("Tower","Plaza","Building","Tech Park","Business Park","Corporate Centre","Chambers","Arcade","Enclave","Square")
    $prefix = @("DLF","RMZ","Prestige","Brigade","Lodha","Godrej","Sobha","Embassy","K Raheja","Birla","Raheja","Ascendas","Divyasree","Mindspace","Manyata","Bagmane","Omkar","Kaledonia","Akruti","Hiranandani")
    return "$($prefix[(Get-Random -Minimum 0 -Maximum $prefix.Length)]) $($names[(Get-Random -Minimum 0 -Maximum $names.Length)])"
}
function RandomPhone { $p = @(6,7,8,9) | Get-Random; return "'$p$(Get-Random -Minimum 100000000 -Maximum 999999999)'" }
function RandomEmail($name) {
    $doms = @("gmail.com","yahoo.com","outlook.com","company.com","cubespace.in","hotmail.com","rediffmail.com","zoho.com")
    return "'$name@$($doms[(Get-Random -Minimum 0 -Maximum $doms.Length)])'"
}

$indianNames = @(
    "Ravi Kumar","Priya Sharma","Arun Patel","Sneha Reddy","Vikram Singh","Anita Gupta","Karthik Nair",
    "Meena Iyer","Suresh Rao","Deepa Menon","Rajesh Verma","Kavita Joshi","Manoj Desai","Pooja Saxena",
    "Gopal Krishnan","Nisha Kapoor","Prakash Mishra","Geetha Pillai","Venkat Subramanian","Latha Nambiar",
    "Babu Rajan","Rekha Srinivasan","Dinesh Chowdhury","Usha Bhat","Mohan Shetty","Jyoti Agarwal",
    "Saravanan Murugan","Amrita Das","Hari Chandran","Sandhya Nair","Naveen Rao","Swathi Menon",
    "Gowtham Kesavan","Preethi Rangan","Ashok Kumar","Vidya Iyengar","Ramesh Babu","Ananya Ghosh",
    "Kishore Reddy","Bhavani Shankar","Chandru Selvam","Divya Prakash","Eka Saini","Farhan Ali",
    "Gayathri Devi","Hemant Thakur","Indu Bala","Jagan Mohan","Kalaivani Arun","Logesh Kannan",
    "Madhu Sudan","Narmadha Chandran","Om Prakash","Pallavi Mishra","Qureshi Alam","Radhika Krishnan",
    "Sakthi Vel","Thara Nair","Uday Shetty","Vasanthi Lakshmi","Yogesh Joshi","Zaheer Khan",
    "Abishek Singh","Bhuvana Raj","Chetan Shah","Devika Nair","Elango Pandian","Ganesh Kumar",
    "Harini Priya","Irfan Malik","Janani Sri","Kamal Rajan","Lavanya Krishnan","Murugan Velu",
    "Nithya Suresh","Omkar Joshi","Padma Lakshmi","Ruthra Devi","Shankar Narayan","Tanvi Shah",
    "Uma Devi","Varun Dhawan","Waheeda Rehman","Ajay Verma","Bindhu Madhavi","Chitra Devi",
    "Deepak Shetty","Eswari Kumar","Gana Prakash","Hema Malini","Ishwar Chandra","Jayanthi Lalitha",
    "Kalyani Devi","Lohit Raj","Malini Rao","Naren Das","Parthiban Raja","Queena D'Souza",
    "Raja Sekhar","Sowmya Narayan","Thirumalai Kumar"
)

# ============================================================================
# 1. MANAGED OFFICES rows 21-100
# ============================================================================
Write-Host "Building managed_offices rows 21-100..."
$output.AppendLine("") | Out-Null
$output.AppendLine("-- =============================================================") | Out-Null
$output.AppendLine("-- 2. managed_offices (rows 21-100)") | Out-Null
$output.AppendLine("-- =============================================================") | Out-Null
$output.Append("INSERT INTO managed_offices (listing_code, title, slug, description, listing_type, city, area, address, latitude, longitude, price, price_label, total_seats, total_area_sqft, office_space_type, amenities, images, featured, feature_highlights, seo_text, status, created_at, updated_at) VALUES") | Out-Null

for ($i = 21; $i -le 100; $i++) {
    $code = "MFO$( '{0:D3}' -f $i )"
    $cd = $cityData[(Get-Random -Minimum 0 -Maximum $cityData.Length)]
    $city = $cd.city; $area = $cd.area
    $lat = $cd.lat + (Get-Random -Minimum -50 -Maximum 50) / 1000
    $lng = $cd.lng + (Get-Random -Minimum -50 -Maximum 50) / 1000
    $price = [math]::Round((Get-Random -Minimum $cd.priceMin -Maximum $cd.priceMax) / 1000) * 1000
    $seats = @(5,8,10,12,15,18,20,22,25,28,30,35,40,45,50,55,60,65,70,75,80,90,100) | Get-Random
    $areaSqft = $seats * (Get-Random -Minimum 25 -Maximum 40)
    $title = RandomTitle $city $area
    $slug = "'$(Slug $title)'"
    $desc = RandomDesc $city $area
    $addr = "'$area, $city - $(Get-Random -Minimum 600001 -Maximum 600120)'"
    $feat = if ((Get-Random -Maximum 10) -lt 3) { 0 } else { 1 }
    $st = if ((Get-Random -Maximum 10) -eq 0) { "'draft'" } else { "'published'" }
    $spaceType = if ((Get-Random -Maximum 2) -eq 0) { "'rent'" } else { "'lease'" }
    $amen = RandomAmenities; $imgs = RandomImage $code (Get-Random -Minimum 1 -Maximum 4)
    $highlights = RandomHighlights; $seo = RandomSeoText $city $area
    $sep = if ($i -lt 100) { "," } else { ";" }
    $output.AppendLine() | Out-Null
    $output.Append("($code, $title, $slug, $desc, 'managed', '$city', '$area', $addr, $lat, $lng, $price.00, 'Per seat/month', $seats, $areaSqft, $spaceType, $amen, $imgs, $feat, $highlights, $seo, $st, '2026-01-$( Get-Random -Min 1 -Max 28 ):10:00:00', '2026-06-$( Get-Random -Min 1 -Max 25 ):10:00:00')$sep") | Out-Null
}

# ============================================================================
# 2. FURNISHED OFFICES
# ============================================================================
Write-Host "Building furnished_offices..."
$output.AppendLine("") | Out-Null
$output.AppendLine("") | Out-Null
$output.AppendLine("-- =============================================================") | Out-Null
$output.AppendLine("-- 3. furnished_offices (100 rows)") | Out-Null
$output.AppendLine("-- Columns: listing_code, title, slug, description, city, area, address, latitude, longitude, price, price_label, total_seats, available_sqft, min_inventory, inventory_type, total_area_sqft, office_space_type, amenities, images, featured, status, created_at, updated_at") | Out-Null
$output.AppendLine("-- =============================================================") | Out-Null
$output.Append("INSERT INTO furnished_offices (listing_code, title, slug, description, city, area, address, latitude, longitude, price, price_label, total_seats, available_sqft, min_inventory, inventory_type, total_area_sqft, office_space_type, amenities, images, featured, status, created_at, updated_at) VALUES") | Out-Null

for ($i = 1; $i -le 100; $i++) {
    $code = "FUO$( '{0:D3}' -f $i )"
    $cd = $cityData[(Get-Random -Minimum 0 -Maximum $cityData.Length)]
    $city = $cd.city; $area = $cd.area
    $lat = $cd.lat + (Get-Random -Minimum -50 -Maximum 50) / 1000
    $lng = $cd.lng + (Get-Random -Minimum -50 -Maximum 50) / 1000
    $price = [math]::Round((Get-Random -Minimum $cd.priceMin -Maximum $cd.priceMax) / 1000) * 1000
    $seats = @(5,8,10,12,15,18,20,22,25,28,30,35,40,45,50,60,75,100) | Get-Random
    $areaSqft = $seats * (Get-Random -Minimum 20 -Maximum 35)
    $availSqft = "$(Get-Random -Minimum 500 -Maximum 5000) sqft"
    $minInv = Get-Random -Minimum 1 -Maximum 10
    $invType = if ((Get-Random -Maximum 2) -eq 0) { "'sqft'" } else { "'seats'" }
    $title = RandomTitle $city $area
    $slug = "'$(Slug $title)'"
    $desc = RandomDesc $city $area
    $addr = "'$area, $city - $(Get-Random -Minimum 600001 -Maximum 600120)'"
    $feat = if ((Get-Random -Maximum 10) -lt 3) { 0 } else { 1 }
    $st = if ((Get-Random -Maximum 10) -eq 0) { "'draft'" } else { "'published'" }
    $spaceType = if ((Get-Random -Maximum 2) -eq 0) { "'rent'" } else { "'lease'" }
    $amen = RandomAmenities; $imgs = RandomImage $code (Get-Random -Minimum 1 -Maximum 3)
    $sep = if ($i -lt 100) { "," } else { ";" }
    $output.AppendLine() | Out-Null
    $output.Append("($code, $title, $slug, $desc, '$city', '$area', $addr, $lat, $lng, $price.00, 'Per seat/month', $seats, '$availSqft', '$minInv', $invType, $areaSqft, $spaceType, $amen, $imgs, $feat, $st, '2026-02-$( Get-Random -Min 1 -Max 28 ):10:00:00', '2026-06-$( Get-Random -Min 1 -Max 25 ):10:00:00')$sep") | Out-Null
}

# ============================================================================
# 3. UNFURNISHED OFFICES
# ============================================================================
Write-Host "Building unfurnished_offices..."
$output.AppendLine("") | Out-Null
$output.AppendLine("") | Out-Null
$output.AppendLine("-- =============================================================") | Out-Null
$output.AppendLine("-- 4. unfurnished_offices (100 rows)") | Out-Null
$output.AppendLine("-- =============================================================") | Out-Null
$output.Append("INSERT INTO unfurnished_offices (listing_code, title, slug, description, city, area, address, latitude, longitude, price, price_label, total_seats, available_sqft, min_inventory, inventory_type, total_area_sqft, office_space_type, amenities, images, featured, status, created_at, updated_at) VALUES") | Out-Null

for ($i = 1; $i -le 100; $i++) {
    $code = "UFU$( '{0:D3}' -f $i )"
    $cd = $cityData[(Get-Random -Minimum 0 -Maximum $cityData.Length)]
    $city = $cd.city; $area = $cd.area
    $lat = $cd.lat + (Get-Random -Minimum -50 -Maximum 50) / 1000
    $lng = $cd.lng + (Get-Random -Minimum -50 -Maximum 50) / 1000
    $price = [math]::Round((Get-Random -Minimum $cd.priceMin -Maximum $cd.priceMax) / 1000) * 1000
    $seats = @(5,8,10,12,15,18,20,22,25,28,30,35,40,45,50,60,75,100) | Get-Random
    $areaSqft = $seats * (Get-Random -Minimum 20 -Maximum 35)
    $availSqft = "$(Get-Random -Minimum 500 -Maximum 5000) sqft"
    $minInv = Get-Random -Minimum 1 -Maximum 10
    $invType = if ((Get-Random -Maximum 2) -eq 0) { "'sqft'" } else { "'seats'" }
    $title = RandomTitle $city $area
    $slug = "'$(Slug $title)'"
    $desc = RandomDesc $city $area
    $addr = "'$area, $city - $(Get-Random -Minimum 600001 -Maximum 600120)'"
    $feat = if ((Get-Random -Maximum 10) -lt 3) { 0 } else { 1 }
    $st = if ((Get-Random -Maximum 10) -eq 0) { "'draft'" } else { "'published'" }
    $spaceType = if ((Get-Random -Maximum 2) -eq 0) { "'rent'" } else { "'lease'" }
    $amen = RandomAmenities; $imgs = RandomImage $code (Get-Random -Minimum 1 -Maximum 3)
    $sep = if ($i -lt 100) { "," } else { ";" }
    $output.AppendLine() | Out-Null
    $output.Append("($code, $title, $slug, $desc, '$city', '$area', $addr, $lat, $lng, $price.00, 'Per seat/month', $seats, '$availSqft', '$minInv', $invType, $areaSqft, $spaceType, $amen, $imgs, $feat, $st, '2026-02-$( Get-Random -Min 1 -Max 28 ):10:00:00', '2026-06-$( Get-Random -Min 1 -Max 25 ):10:00:00')$sep") | Out-Null
}

# ============================================================================
# 4. OFFICE SPACES
# ============================================================================
Write-Host "Building office_spaces..."
$output.AppendLine("") | Out-Null
$output.AppendLine("") | Out-Null
$output.AppendLine("-- =============================================================") | Out-Null
$output.AppendLine("-- 5. office_spaces (100 rows)") | Out-Null
$output.AppendLine("-- listing_type = 'commercial'") | Out-Null
$output.AppendLine("-- =============================================================") | Out-Null
$output.Append("INSERT INTO office_spaces (listing_code, title, slug, description, listing_type, city, area, address, latitude, longitude, price, price_label, total_seats, total_area_sqft, office_space_type, amenities, images, featured, feature_highlights, seo_text, status, created_at, updated_at) VALUES") | Out-Null

for ($i = 1; $i -le 100; $i++) {
    $code = "OSP$( '{0:D3}' -f $i )"
    $cd = $cityData[(Get-Random -Minimum 0 -Maximum $cityData.Length)]
    $city = $cd.city; $area = $cd.area
    $lat = $cd.lat + (Get-Random -Minimum -50 -Maximum 50) / 1000
    $lng = $cd.lng + (Get-Random -Minimum -50 -Maximum 50) / 1000
    $price = [math]::Round((Get-Random -Minimum $cd.priceMin -Maximum $cd.priceMax) / 1000) * 1000
    $seats = @(5,8,10,12,15,18,20,22,25,28,30,35,40,45,50,55,60,65,70,75,80,90,100) | Get-Random
    $areaSqft = $seats * (Get-Random -Minimum 25 -Maximum 40)
    $title = RandomTitle $city $area
    $slug = "'$(Slug $title)'"
    $desc = RandomDesc $city $area
    $addr = "'$area, $city - $(Get-Random -Minimum 600001 -Maximum 600120)'"
    $feat = if ((Get-Random -Maximum 10) -lt 3) { 0 } else { 1 }
    $st = if ((Get-Random -Maximum 10) -eq 0) { "'draft'" } else { "'published'" }
    $spaceType = if ((Get-Random -Maximum 2) -eq 0) { "'rent'" } else { "'lease'" }
    $amen = RandomAmenities; $imgs = RandomImage $code (Get-Random -Minimum 1 -Maximum 4)
    $highlights = RandomHighlights; $seo = RandomSeoText $city $area
    $sep = if ($i -lt 100) { "," } else { ";" }
    $output.AppendLine() | Out-Null
    $output.Append("($code, $title, $slug, $desc, 'commercial', '$city', '$area', $addr, $lat, $lng, $price.00, 'Per seat/month', $seats, $areaSqft, $spaceType, $amen, $imgs, $feat, $highlights, $seo, $st, '2026-01-$( Get-Random -Min 1 -Max 28 ):10:00:00', '2026-06-$( Get-Random -Min 1 -Max 25 ):10:00:00')$sep") | Out-Null
}

# ============================================================================
# 5. CONTACTS
# ============================================================================
Write-Host "Building contacts..."
$interests = @("managed","furnished","unfurnished")
$seatRanges = @("10-50","51-100","101-200","200+")
$sources = @("website","referral","google","facebook","linkedin","twitter","whatsapp","direct")
$statuses = @("new","contacted","closed")
$companies = @("Tech Solutions","Innovate Corp","Digital Dynamics","Smart Systems","Prime Consulting","Vertex Technologies","NexGen Solutions","Blue Ocean Tech","Quantum Leap","DataBridge","CloudNine","StarTech","CodeCraft","WebWeavers","AppSquad","LogicLabs","BrainWave","AlphaTech","Sigma Solutions","Omega Systems")
$messages = @(
    "I am interested in this office space. Please share more details.",
    "Looking for a managed office for my team of 20 people.",
    "Please send me the pricing details and available inventory.",
    "I would like to schedule a visit to this property.",
    "Need a commercial office space in this area urgently.",
    "Interested in leasing options. Contact me at the earliest.",
    "Please share the brochure and floor plan.",
    "Looking for furnished office space for our startup.",
    "We need 30 seats starting next month. Please share availability.",
    "Interested in co-working space options in this location.",
    "Please provide more information about amenities and pricing.",
    "We are looking to expand our office. Need 50+ seats.",
    "Can you share the virtual tour link for this property?",
    "Need a short-term lease for 6 months.",
    "Interested in your managed office services. Please call me back."
)

$output.AppendLine("") | Out-Null
$output.AppendLine("") | Out-Null
$output.AppendLine("-- =============================================================") | Out-Null
$output.AppendLine("-- 6. contacts (100 rows)") | Out-Null
$output.AppendLine("-- =============================================================") | Out-Null
$output.Append("INSERT INTO contacts (name, phone, email, interest, company, seats, message, office_id, listing_code, source, submitted_ip, user_agent, status, admin_notes, created_at) VALUES") | Out-Null

for ($i = 1; $i -le 100; $i++) {
    $name = $indianNames[(Get-Random -Minimum 0 -Maximum $indianNames.Length)]
    $simpleName = ($name -replace ' ','.').ToLower()
    $phone = RandomPhone
    $email = RandomEmail $simpleName
    $interest = $interests[(Get-Random -Minimum 0 -Maximum $interests.Length)]
    $company = $companies[(Get-Random -Minimum 0 -Maximum $companies.Length)]
    $seats = $seatRanges[(Get-Random -Minimum 0 -Maximum $seatRanges.Length)]
    $msg = "'$($messages[(Get-Random -Minimum 0 -Maximum $messages.Length)])'"
    $officeId = Get-Random -Minimum 1 -Maximum 100
    $lcode = "MFO$( '{0:D3}' -f (Get-Random -Minimum 1 -Maximum 100) )"
    $src = $sources[(Get-Random -Minimum 0 -Maximum $sources.Length)]
    $ip = "192.168.$(Get-Random -Minimum 1 -Maximum 255).$(Get-Random -Minimum 1 -Maximum 255)"
    $ua = "'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'"
    $st = $statuses[(Get-Random -Minimum 0 -Maximum $statuses.Length)]
    $notes = if ((Get-Random -Maximum 10) -lt 4) { "'Follow up required'" } else { "NULL" }
    $sep = if ($i -lt 100) { "," } else { ";" }
    $output.AppendLine() | Out-Null
    $output.Append("('$name', $phone, $email, '$interest', '$company', '$seats', $msg, $officeId, '$lcode', '$src', '$ip', $ua, '$st', $notes, '2026-03-$( Get-Random -Min 1 -Max 28 ):$( Get-Random -Min 8 -Max 18 ):00:00')$sep") | Out-Null
}

# ============================================================================
# 6. OFFICE LEASING OPTIONS
# ============================================================================
Write-Host "Building office_leasing_options..."
$optTitles = @("Flexi Desk","Dedicated Desk","Private Cabin","Team Suite","Executive Office","Virtual Office","Day Pass","Conference Room","Training Room","Event Space")
$optDescs = @(
    "'Hot desk in shared workspace with all amenities included.'",
    "'Your own dedicated desk with locker storage.'",
    "'Fully enclosed private cabin for small teams.'",
    "'Suite for 4-6 people with meeting room access.'",
    "'Premium executive office with personal assistant support.'",
    "'Professional business address with mail handling services.'",
    "'Access to workspace for a single day with full amenities.'",
    "'Fully equipped conference room with AV facilities.'",
    "'Training room for workshops and corporate events.'",
    "'Space for events, parties, and corporate gatherings.'"
)

$output.AppendLine("") | Out-Null
$output.AppendLine("") | Out-Null
$output.AppendLine("-- =============================================================") | Out-Null
$output.AppendLine("-- 7. office_leasing_options (100 rows)") | Out-Null
$output.AppendLine("-- =============================================================") | Out-Null
$output.Append("INSERT INTO office_leasing_options (office_id, option_title, option_desc, option_price, option_image, sort_order, is_active, created_at) VALUES") | Out-Null

for ($i = 1; $i -le 100; $i++) {
    $oid = Get-Random -Minimum 1 -Maximum 100
    $ot = "'$($optTitles[(Get-Random -Minimum 0 -Maximum $optTitles.Length)])'"
    $od = $optDescs[(Get-Random -Minimum 0 -Maximum $optDescs.Length)]
    $op = Get-Random -Minimum 5000 -Maximum 200000
    $oi = "'/uploads/leasing/option_$i.jpg'"
    $so = Get-Random -Minimum 1 -Maximum 20
    $act = if ((Get-Random -Maximum 10) -lt 2) { 0 } else { 1 }
    $sep = if ($i -lt 100) { "," } else { ";" }
    $output.AppendLine() | Out-Null
    $output.Append("($oid, $ot, $od, $op.00, $oi, $so, $act, '2026-04-$( Get-Random -Min 1 -Max 28 ):10:00:00')$sep") | Out-Null
}

# ============================================================================
# 10. ACTIVITY LOG
# ============================================================================
Write-Host "Building activity_log..."
$actions = @("create","update","delete","login","logout","bulk_delete","bulk_update","status_change")
$tables = @("managed_offices","furnished_offices","unfurnished_offices","office_spaces","contacts","admins","office_leasing_options")

$output.AppendLine("") | Out-Null
$output.AppendLine("") | Out-Null
$output.AppendLine("-- =============================================================") | Out-Null
$output.AppendLine("-- 8. activity_log (100 rows)") | Out-Null
$output.AppendLine("-- =============================================================") | Out-Null
$output.Append("INSERT INTO activity_log (admin_id, admin_username, action, table_name, record_id, details, ip_address, created_at) VALUES") | Out-Null

for ($i = 1; $i -le 100; $i++) {
    $adminId = Get-Random -Minimum 1 -Maximum 10
    $adminUser = "'admin_$($indianNames[(Get-Random -Minimum 0 -Maximum $indianNames.Length)].Split(' ')[0].ToLower())'"
    $action = $actions[(Get-Random -Minimum 0 -Maximum $actions.Length)]
    $tbl = $tables[(Get-Random -Minimum 0 -Maximum $tables.Length)]
    $recId = Get-Random -Minimum 1 -Maximum 50
    $detail = "'{`"action`":`"$action`",`"table`":`"$tbl`",`"id`":$recId}'"
    $ip = "10.0.$(Get-Random -Minimum 1 -Maximum 255).$(Get-Random -Minimum 1 -Maximum 255)"
    $sep = if ($i -lt 100) { "," } else { ";" }
    $output.AppendLine() | Out-Null
    $output.Append("($adminId, $adminUser, '$action', '$tbl', $recId, $detail, '$ip', '2026-06-$( Get-Random -Min 1 -Max 25 ):$( Get-Random -Min 8 -Max 18 ):00:00')$sep") | Out-Null
}

# ============================================================================
# 11. REALTIME EVENTS
# ============================================================================
Write-Host "Building realtime_events..."
$eventTypes = @("listing_created","listing_updated","listing_deleted","contact_created","contact_updated","contact_deleted","admin_created","admin_updated","admin_deleted","bulk_operation","leasing_created")
$entityTypes = @("managed","furnished","unfurnished","commercial","contact","admins","leasing")

$output.AppendLine("") | Out-Null
$output.AppendLine("") | Out-Null
$output.AppendLine("-- =============================================================") | Out-Null
$output.AppendLine("-- 9. realtime_events (100 rows)") | Out-Null
$output.AppendLine("-- =============================================================") | Out-Null
$output.Append("INSERT INTO realtime_events (event_type, entity_type, entity_id, summary, created_at) VALUES") | Out-Null

for ($i = 1; $i -le 100; $i++) {
    $et = $eventTypes[(Get-Random -Minimum 0 -Maximum $eventTypes.Length)]
    $ent = $entityTypes[(Get-Random -Minimum 0 -Maximum $entityTypes.Length)]
    $eid = Get-Random -Minimum 1 -Maximum 100
    $summary = "'$et - $ent #$eid'"
    $sep = if ($i -lt 100) { "," } else { ";" }
    $output.AppendLine() | Out-Null
    $output.Append("('$et', '$ent', $eid, $summary, '2026-06-$( Get-Random -Min 1 -Max 25 ):$( Get-Random -Min 8 -Max 18 ):00:00')$sep") | Out-Null
}

# Final semicolon
$output.AppendLine() | Out-Null
$output.AppendLine() | Out-Null
$output.AppendLine("-- =============================================================") | Out-Null
$output.AppendLine("-- End of sample data") | Out-Null
$output.AppendLine("-- =============================================================") | Out-Null

# Write everything at once
Write-Host "Writing to file..."
[System.IO.File]::AppendAllText($sqlFile, $output.ToString(), [System.Text.UTF8Encoding]::new($false))
Write-Host "Done! All data appended successfully."
