<?php
require_once __DIR__ . '/layout.php';
admin_require_lib('config.php');


function os_fmt_feature_highlights($val) {
    $decoded = json_decode($val ?? '[]', true);
    if (!$decoded || !is_array($decoded)) return '';
    $lines = [];
    foreach ($decoded as $k => $v) {
        if (is_string($k) && !is_numeric($k)) {
            $lines[] = "$k: $v";
        } else {
            $lines[] = $v;
        }
    }
    return implode("\n", $lines);
}

$adminListPage = max(1, (int)($_GET['p'] ?? 1));
$adminPerPage = 50;
$adminOffset = ($adminListPage - 1) * $adminPerPage;
$mode = $_GET['mode'] ?? 'list';
$editId = (int)($_GET['id'] ?? 0);
$editType = trim($_GET['type'] ?? ($mode === 'add' ? 'furnished' : ''));
$editTable = get_listing_table($editType) ?: 'furnished_offices';

$searchQuery = trim($_GET['search'] ?? '');

if ($mode === 'add' || $mode === 'edit'):
    $listing = ['title'=>'', 'listing_type'=>'', 'description'=>'', 'city'=>'', 'area'=>'', 'address'=>'', 'price'=>'', 'price_label'=>'', 'total_seats'=>'', 'total_area_sqft'=>'', 'available_sqft'=>'', 'min_inventory'=>'', 'inventory_type'=>'', 'amenities'=>'[]', 'images'=>'[]', 'status'=>'active', 'featured'=>0, 'office_space_type'=>'rent', 'feature_highlights'=>'[]', 'seo_text'=>'', 'remarks'=>'', 'latitude'=>null, 'longitude'=>null, 'listing_code'=>'', 'slug'=>''];
    if ($mode === 'edit' && $editId && $editTable) {
        $stmt = mysqli_prepare($conn, "SELECT * FROM $editTable WHERE id=?");
        mysqli_stmt_bind_param($stmt, 'i', $editId);
        mysqli_stmt_execute($stmt);
        $r = mysqli_stmt_get_result($stmt);
        $listing = mysqli_fetch_assoc($r);
        mysqli_stmt_close($stmt);
        if (!$listing) { echo '<div class="alert alert-warning">Listing not found.</div>'; require_once __DIR__ . '/footer.php'; exit; }
    }
    $amenities = json_decode($listing['amenities'] ?? '[]', true);
    $images = array_values(array_filter(json_decode($listing['images'] ?? '[]', true) ?: [], function($img) {
        if (!is_string($img) || trim($img) === '') return false;
        if (parse_url($img, PHP_URL_HOST) || parse_url($img, PHP_URL_SCHEME)) return true;
        $path = parse_url($img, PHP_URL_PATH);
        return $path && file_exists(__DIR__ . '/..' . $path);
    }));
    $cities = mysqli_query($conn, "SELECT city FROM (SELECT city COLLATE utf8mb4_unicode_ci AS city FROM listing_cities UNION SELECT DISTINCT city COLLATE utf8mb4_unicode_ci AS city FROM furnished_offices WHERE city != '' UNION SELECT DISTINCT city COLLATE utf8mb4_unicode_ci AS city FROM unfurnished_offices WHERE city != '') AS c ORDER BY city");
    if (!$cities) $cities = false;
    $areas = mysqli_query($conn, "SELECT area, city FROM (SELECT area COLLATE utf8mb4_unicode_ci AS area, city COLLATE utf8mb4_unicode_ci AS city FROM listing_areas UNION SELECT DISTINCT area COLLATE utf8mb4_unicode_ci AS area, city COLLATE utf8mb4_unicode_ci AS city FROM furnished_offices WHERE area != '' AND area IS NOT NULL UNION SELECT DISTINCT area COLLATE utf8mb4_unicode_ci AS area, city COLLATE utf8mb4_unicode_ci AS city FROM unfurnished_offices WHERE area != '' AND area IS NOT NULL) AS a ORDER BY area");
    if (!$areas) $areas = false;
    $amenityList = ['WiFi','Air Conditioning','Power Backup','Parking','Security Guard','CCTV Surveillance','Elevator / Lift','Reception Area','Pantry / Cafeteria','Meeting Room','Visitor Parking','24/7 Access'];
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><?= $mode === 'add' ? 'Add' : 'Edit' ?> <?= $mode === 'edit' ? ucfirst($editType) : '' ?> Office Space</h4>
    <a href="office-space.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Back</a>
</div>
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <?php if ($mode === 'edit' && $listing['listing_code']): ?>
        <div class="mb-3 p-2 bg-light small">
            <span class="text-muted">Listing Code:</span> <strong><?= htmlspecialchars($listing['listing_code']) ?></strong>
            <?php if ($listing['slug']): ?><span class="ms-3 text-muted">Slug:</span> <code><?= htmlspecialchars($listing['slug']) ?></code><?php endif; ?>
        </div>
        <?php endif; ?>
        <form id="listingForm" class="row g-3 needs-validation" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="id" value="<?= $editId ?>">
            <input type="hidden" name="listing_type" value="<?= $editType ?>">
            <input type="hidden" name="existing_images" id="existingImages" value='<?= htmlspecialchars(json_encode($images)) ?>'>
            <input type="hidden" name="amenities" id="amenitiesInput" value="<?= htmlspecialchars($listing['amenities'] ?? '[]') ?>">
            <input type="hidden" name="office_space_type" value="rent">

            <div class="col-md-6 position-relative">
                <label for="title" class="form-label small fw-semibold">Title <span class="text-danger">*</span></label>
                <input type="text" name="title" id="title" class="form-control form-control-sm" required value="<?= htmlspecialchars($listing['title']) ?>" placeholder="e.g. RMZ Millenia">
                <div class="valid-tooltip">Looks good!</div>
                <div class="invalid-tooltip">Please enter a title.</div>
            </div>

            <div class="col-md-3 position-relative">
                <label for="city" class="form-label small fw-semibold">City <span class="text-danger">*</span></label>
                <select name="city" id="city" class="form-select form-select-sm" required onchange="filterAreasByCity()">
                    <option value="">- Select -</option>
                    <?php if ($cities && mysqli_num_rows($cities)): mysqli_data_seek($cities, 0); while ($c = mysqli_fetch_assoc($cities)): ?>
                    <option value="<?= htmlspecialchars($c['city']) ?>" <?= $listing['city']===$c['city']?'selected':'' ?>><?= htmlspecialchars(ucfirst($c['city'])) ?></option>
                    <?php endwhile; endif; ?>

                </select>
                <div class="invalid-tooltip">Please select a city.</div>
                <div class="d-flex gap-1 mt-1">
                    <input type="text" id="newCity" class="form-control form-control-sm" placeholder="Add city..." style="font-size:0.75rem;">
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="window.addNewCity()" style="font-size:0.7rem;white-space:nowrap;">Add</button>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="window.deleteCity()" style="font-size:0.7rem;white-space:nowrap;">Del</button>
                </div>
            </div>

            <div class="col-md-3 position-relative">
                <label for="area" class="form-label small fw-semibold">Area / Locality <span class="text-danger">*</span></label>
                <input type="text" id="areaSearch" class="form-control form-control-sm mb-1" placeholder="Type area to filter..." oninput="filterAreasByText(this)" style="font-size:0.75rem;">
                <select name="area" id="area" class="form-select form-select-sm" required>
                    <option value="">- Select -</option>
                    <?php if ($areas && mysqli_num_rows($areas)): mysqli_data_seek($areas, 0); while ($a = mysqli_fetch_assoc($areas)): ?>
                    <option value="<?= htmlspecialchars($a['area']) ?>" data-city="<?= htmlspecialchars(mb_strtolower($a['city']??'')) ?>" <?= ($listing['area']??'')===$a['area']?'selected':'' ?>><?= htmlspecialchars(ucfirst($a['area'])) ?></option>
                    <?php endwhile; endif; ?>
                </select>
                <div class="invalid-tooltip">Please select an area.</div>
                <div class="d-flex gap-1 mt-1">
                    <input type="text" id="newArea" class="form-control form-control-sm" placeholder="Add area..." style="font-size:0.75rem;">
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="window.addNewArea()" style="font-size:0.7rem;white-space:nowrap;">Add</button>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="window.deleteArea()" style="font-size:0.7rem;white-space:nowrap;">Del</button>
                </div>
            </div>

            <div class="col-md-3 position-relative">
                <label for="available_sqft" class="form-label small fw-semibold">Sq Ft <span class="text-danger">*</span></label>
                <select name="available_sqft" id="available_sqft" class="form-select form-select-sm" required>
                    <option value="">- Select -</option>
                    <option value="1000-5000" <?= ($listing['available_sqft'] ?? '') === '1000-5000' ? 'selected' : '' ?>>1000 - 5000 Sq.ft</option>
                    <option value="5000-10000" <?= ($listing['available_sqft'] ?? '') === '5000-10000' ? 'selected' : '' ?>>5000 - 10000 Sq.ft</option>
                    <option value="10000-20000" <?= ($listing['available_sqft'] ?? '') === '10000-20000' ? 'selected' : '' ?>>10000 - 20000 Sq.ft</option>
                    <option value="20000-" <?= ($listing['available_sqft'] ?? '') === '20000-' ? 'selected' : '' ?>>20000+ Sq.ft</option>
                </select>
                <div class="invalid-tooltip">Please select an area range.</div>
            </div>

            <div class="col-md-3 position-relative">
                <label for="total_area_sqft" class="form-label small fw-semibold">Current Available Rental Area</label>
                <input type="number" name="total_area_sqft" id="total_area_sqft" class="form-control form-control-sm" value="<?= htmlspecialchars($listing['total_area_sqft'] ?? '') ?>" placeholder="e.g. 5000">
                <div id="totalAreaSqftFeedback" class="small text-danger mt-1" style="display:none;"></div>
            </div>

            <div class="col-md-3 position-relative">
                <label for="inventory_type" class="form-label small fw-semibold">Current Status</label>
                <input type="text" name="inventory_type" id="inventory_type" class="form-control form-control-sm" value="<?= htmlspecialchars($listing['inventory_type'] ?? '') ?>" placeholder="e.g. Ready to move in" oninput="validateInventoryType(this)">
                <div id="inventoryTypeFeedback" class="invalid-feedback"></div>
            </div>

            <div class="col-12 position-relative">
                <label for="address" class="form-label small fw-semibold">Address <span class="text-danger">*</span></label>
                <textarea name="address" id="address" class="form-control form-control-sm" rows="2" required placeholder="Full address"><?= htmlspecialchars($listing['address']??'') ?></textarea>
                <div class="invalid-tooltip">Please enter an address.</div>
            </div>

            <div class="col-12 position-relative">
                <label for="description" class="form-label small fw-semibold">Description</label>
                <textarea name="description" id="description" class="form-control form-control-sm" rows="3" placeholder="Describe the property"><?= htmlspecialchars($listing['description']??'') ?></textarea>
            </div>

            <div class="col-12">
                <label class="form-label small fw-semibold">Amenities</label>
                <div class="row g-2" id="amenitiesContainer">
                    <?php foreach ($amenityList as $amenity): ?>
                    <div class="col-md-3 col-6">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input amenity-check" value="<?= htmlspecialchars($amenity) ?>" id="amenity_<?= preg_replace('/[^a-zA-Z0-9]/', '_', $amenity) ?>" <?= in_array($amenity, $amenities) ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="amenity_<?= preg_replace('/[^a-zA-Z0-9]/', '_', $amenity) ?>"><?= htmlspecialchars($amenity) ?></label>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="col-12 position-relative">
                <label for="seo_text" class="form-label small fw-semibold">SEO Text</label>
                <textarea name="seo_text" id="seo_text" class="form-control form-control-sm" rows="3" placeholder="&lt;h3&gt;About this Workspace&lt;/h3&gt;"><?= $listing['seo_text'] ?? '' ?></textarea>
                <small class="form-text text-muted">HTML allowed. Use ### for headings. Will be rendered on the public page.</small>
            </div>

            <div class="col-12">
                <label for="images" class="form-label small fw-semibold">Images</label>
                <input type="file" name="images[]" id="images" class="form-control form-control-sm" accept="image/*" multiple>
                <?php if (!empty($images)): ?>
                <div class="d-flex flex-wrap gap-2 mt-2" id="existingImagesContainer">
                    <?php foreach ($images as $img):
                        $imgPath = $_SERVER['DOCUMENT_ROOT'] . parse_url($img, PHP_URL_PATH);
                        $imgExists = file_exists($imgPath);
                    ?>
                    <div class="position-relative" data-src="<?= htmlspecialchars($img) ?>" style="width:70px;height:70px;">
                        <?php if ($imgExists): ?>
                        <img src="<?= htmlspecialchars($img) ?>" class="border" style="width:70px;height:70px;object-fit:cover;" loading="lazy" alt="Listing image">
                        <?php else: ?>
                        <div class="d-flex align-items-center justify-content-center border bg-light" style="width:70px;height:70px;"><i class="fa-solid fa-image text-muted"></i></div>
                        <?php endif; ?>
                        <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 p-0" style="width:18px;height:18px;font-size:10px;line-height:1;" onclick="removeExistingImage(this)">&times;</button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="col-md-6 position-relative">
                <label for="price" class="form-label small fw-semibold">Quoted Rent</label>
                <input type="text" name="price" id="price" class="form-control form-control-sm" value="<?= htmlspecialchars($listing['price']??'') ?>" placeholder="Enter quoted rent">
            </div>

            <div class="col-md-3 position-relative">
                <label for="status" class="form-label small fw-semibold">Status</label>
                <select name="status" id="status" class="form-select form-select-sm">
                    <option value="active" <?= ($listing['status']??'active')==='active'?'selected':'' ?>>Active</option>
                    <option value="inactive" <?= ($listing['status']??'active')==='inactive'?'selected':'' ?>>Inactive</option>
                </select>
            </div>

            <div class="col-12">
                <label for="remarks" class="form-label small fw-semibold">Remarks</label>
                <textarea name="remarks" id="remarks" class="form-control form-control-sm" rows="2" placeholder="Internal remarks..."><?= htmlspecialchars($listing['remarks']??'') ?></textarea>
            </div>

            <div class="col-12">
                <div class="form-check">
                    <input type="checkbox" name="featured" value="1" class="form-check-input" id="featuredCheck" <?= $listing['featured']?'checked':'' ?>>
                    <label class="form-check-label small" for="featuredCheck">Featured listing</label>
                </div>
            </div>

            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm"><?= $mode === 'add' ? 'Create Listing' : 'Update Listing' ?></button>
                <a href="office-space.php" class="btn btn-outline-secondary btn-sm">Cancel</a>
            </div>
        </form>
        <div id="formResult" class="alert d-none mt-2"></div>
    </div>
</div>
<?php else:
    $statusFilter = $_GET['status'] ?? '';
    $cityFilter = $_GET['city'] ?? '';
    $areaFilter = $_GET['area'] ?? '';
    $sqftFilter = $_GET['sqft'] ?? '';

    $conditions = [];
    $params = [];
    $types = '';

    if ($statusFilter && in_array($statusFilter, ['inactive','active'])) {
        $conditions[] = "status = ?";
        $params[] = $statusFilter;
        $types .= 's';
    }
    if ($cityFilter) {
        $conditions[] = "city = ?";
        $params[] = $cityFilter;
        $types .= 's';
    }
    if ($areaFilter) {
        $conditions[] = "area = ?";
        $params[] = $areaFilter;
        $types .= 's';
    }
    if ($sqftFilter && in_array($sqftFilter, ['1000-5000','5000-10000','10000-20000','20000-'])) {
        $conditions[] = "available_sqft = ?";
        $params[] = $sqftFilter;
        $types .= 's';
    }
    if ($searchQuery) {
        $conditions[] = "(id = ? OR listing_code LIKE ? OR title LIKE ? OR slug LIKE ? OR city LIKE ? OR area LIKE ? OR address LIKE ? OR description LIKE ? OR remarks LIKE ? OR status LIKE ?)";
        $idVal = is_numeric($searchQuery) ? (int)$searchQuery : 0;
        $sp = "%$searchQuery%";
        $params[] = $idVal; $params[] = $sp; $params[] = $sp; $params[] = $sp;
        $params[] = $sp; $params[] = $sp; $params[] = $sp; $params[] = $sp;
        $params[] = $sp; $params[] = $sp;
        $types .= 'isssssssss';
    }
    $whereClause = !empty($conditions) ? ' WHERE ' . implode(' AND ', $conditions) : '';

    $countSql = "SELECT SUM(cnt) as total FROM ((SELECT COUNT(*) as cnt FROM furnished_offices $whereClause) UNION ALL (SELECT COUNT(*) as cnt FROM unfurnished_offices $whereClause)) combined";
    $total = 0;
    $countStmt = mysqli_prepare($conn, $countSql);
    if ($countStmt) {
        $allCountParams = array_merge($params, $params);
        $allCountTypes = str_repeat('s', count($allCountParams));
        if (!empty($allCountParams)) {
            mysqli_stmt_bind_param($countStmt, $allCountTypes, ...$allCountParams);
        }
        mysqli_stmt_execute($countStmt);
        $cResult = mysqli_stmt_get_result($countStmt);
        if ($cResult) $total = (int)mysqli_fetch_assoc($cResult)['total'];
        mysqli_stmt_close($countStmt);
    }

    $columns = "id, title, slug, city, area, address, price, price_label, total_seats, total_area_sqft, available_sqft, min_inventory, inventory_type, office_space_type, amenities, images, featured, status, listing_code, created_at";
    $orderDir = " ORDER BY ";
    $orderParams = [];
    $orderTypes = '';
    if ($searchQuery) {
        $orderDir .= "listing_code = ? DESC, title = ? DESC, ";
        $prefix = "$searchQuery%";
        $orderDir .= "listing_code LIKE ? DESC, title LIKE ? DESC, ";
        $orderParams = [$searchQuery, $searchQuery, $prefix, $prefix];
        $orderTypes = 'ssss';
    }
    $orderDir .= "created_at DESC";
    $orderSql = "$orderDir LIMIT $adminPerPage OFFSET $adminOffset";

    $unionSql = "SELECT $columns, 'furnished' as listing_type_db FROM furnished_offices $whereClause UNION ALL SELECT $columns, 'unfurnished' as listing_type_db FROM unfurnished_offices $whereClause";
    $listSql = "SELECT t.*, (SELECT COUNT(*) FROM contacts c WHERE (c.listing_code != '' AND c.listing_code = t.listing_code) OR (c.office_id = t.id AND (c.listing_code IS NULL OR c.listing_code = ''))) as enq_cnt FROM ($unionSql) t $orderSql";
    
    $result = false;
    $dbError = '';
    $allListParams = array_merge($params, $params, $orderParams);
    $allListTypes = str_repeat('s', count($allListParams));
    $listStmt = mysqli_prepare($conn, $listSql);
    if ($listStmt) {
        if (!empty($allListParams)) {
            mysqli_stmt_bind_param($listStmt, $allListTypes, ...$allListParams);
        }
        mysqli_stmt_execute($listStmt);
        $result = mysqli_stmt_get_result($listStmt);
        if (!$result) $dbError = mysqli_error($conn);
    } else {
        $dbError = mysqli_error($conn);
    }

    $cities = mysqli_query($conn, "SELECT city FROM (SELECT city COLLATE utf8mb4_unicode_ci AS city FROM listing_cities UNION SELECT DISTINCT city COLLATE utf8mb4_unicode_ci AS city FROM furnished_offices WHERE city != '' UNION SELECT DISTINCT city COLLATE utf8mb4_unicode_ci AS city FROM unfurnished_offices WHERE city != '') AS c ORDER BY city");
    if (!$cities) $cities = false;
    $areas = mysqli_query($conn, "SELECT DISTINCT area FROM (SELECT area COLLATE utf8mb4_unicode_ci AS area FROM listing_areas UNION SELECT DISTINCT area COLLATE utf8mb4_unicode_ci AS area FROM furnished_offices WHERE area != '' AND area IS NOT NULL UNION SELECT DISTINCT area COLLATE utf8mb4_unicode_ci AS area FROM unfurnished_offices WHERE area != '' AND area IS NOT NULL) AS a WHERE area IS NOT NULL AND area != '' ORDER BY area");
    if (!$areas) $areas = false;

    function osMkUrl($extra) {
        $params = [];
        foreach (['status','city','area','sqft','search'] as $k) {
            $v = $_GET[$k] ?? '';
            if ($v && !isset($extra[$k])) $params[] = urlencode($k) . '=' . urlencode($v);
        }
        foreach ($extra as $k => $v) {
            if ($v !== '') $params[] = urlencode($k) . '=' . urlencode($v);
        }
        return 'office-space.php' . ($params ? '?' . implode('&', $params) : '');
    }

    $exportUrl = 'api/listing_crud.php?action=export&listing_type=office-space';
    foreach (['status','city','area','sqft','search'] as $k) {
        $v = $_GET[$k] ?? '';
        if ($v) $exportUrl .= '&' . urlencode($k) . '=' . urlencode($v);
    }
?>
<div class="page-header">
    <h4>Furnished / Unfurnished Office</h4>
    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-primary"><?= $total ?> listings</span>
        <a href="<?= $exportUrl ?>" class="btn btn-outline-success btn-sm" title="Export CSV"><i class="fa-solid fa-download"></i> CSV</a>
        <a href="office-space.php?mode=add" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus me-1"></i>Add</a>
    </div>
</div>
<button class="btn btn-sm btn-outline-primary admin-filter-toggle mb-2" type="button" data-bs-toggle="collapse" data-bs-target="#officeFilters" aria-expanded="true">
    <i class="fa-solid fa-sliders-h"></i> Filters
</button>
<div class="collapse show admin-filter-section" id="officeFilters">
    <form method="get" class="d-flex flex-wrap gap-2">
        <div>
            <div class="filter-label">Search</div>
            <input type="search" name="search" class="form-control form-control-sm" placeholder="Search..." value="<?= htmlspecialchars($searchQuery) ?>">
        </div>
        <div>
            <div class="filter-label">Status</div>
            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
            </select>
        </div>
        <div>
            <div class="filter-label">City</div>
            <select name="city" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">All Cities</option>
                <?php if ($cities): mysqli_data_seek($cities, 0); while ($c = mysqli_fetch_assoc($cities)): ?>
                <option value="<?= htmlspecialchars($c['city']) ?>" <?= $cityFilter === $c['city'] ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst($c['city'])) ?></option>
                <?php endwhile; endif; ?>
            </select>
        </div>
        <div>
            <div class="filter-label">Area</div>
            <select name="area" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">All Areas</option>
                <?php if ($areas): mysqli_data_seek($areas, 0); while ($a = mysqli_fetch_assoc($areas)): ?>
                <option value="<?= htmlspecialchars($a['area']) ?>" <?= $areaFilter === $a['area'] ? 'selected' : '' ?>><?= htmlspecialchars($a['area']) ?></option>
                <?php endwhile; endif; ?>
            </select>
        </div>
        <div>
            <div class="filter-label">Sq.ft Range</div>
            <select name="sqft" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">All Ranges</option>
                <option value="1000-5000" <?= $sqftFilter === '1000-5000' ? 'selected' : '' ?>>1000 - 5000 Sq.ft</option>
                <option value="5000-10000" <?= $sqftFilter === '5000-10000' ? 'selected' : '' ?>>5000 - 10000 Sq.ft</option>
                <option value="10000-20000" <?= $sqftFilter === '10000-20000' ? 'selected' : '' ?>>10000 - 20000 Sq.ft</option>
                <option value="20000-" <?= $sqftFilter === '20000-' ? 'selected' : '' ?>>20000+ Sq.ft</option>
            </select>
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-search"></i> Search</button>
            <?php if ($searchQuery || $statusFilter || $cityFilter || $areaFilter || $sqftFilter): ?>
            <a href="office-space.php" class="btn btn-sm btn-outline-secondary">&times; Clear</a>
            <?php endif; ?>
        </div>
        <hr class="my-1">
        <div class="bulk-bar <?= $total > 0 ? '' : 'd-none' ?> p-0 mb-0 border-0 bg-transparent">
            <select id="bulkActionSelect" class="form-select form-select-sm" aria-label="Bulk actions">
                <option value="">-- Bulk Actions --</option>
                <option value="delete">Delete Selected</option>
                <option value="status-inactive">Mark as Inactive</option>
                <option value="status-active">Mark as Active</option>
                <option value="featured-1">Mark as Featured</option>
                <option value="featured-0">Mark as Unfeatured</option>
            </select>
            <button type="button" class="btn btn-sm btn-secondary" onclick="applyBulkAction()">Apply</button>
        </div>
    </form>
</div>
<?php if (isset($_GET['deleted'])): ?><div class="alert alert-success py-2">Deleted.</div><?php endif; ?>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-success py-2">Saved.</div><?php endif; ?>
<div class="admin-card">
    <div class="table-wrap">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead>
                    <tr>
                        <th scope="col"><input type="checkbox" class="form-check-input checkAll" onchange="toggleAllCheckboxes(this)"></th>
                        <th scope="col">ID</th>
                        <th scope="col">Code</th>
                        <th scope="col">Title</th>
                        <th scope="col">City</th>
                        <th scope="col">Area</th>
                        <th scope="col">Sq.ft</th>
                        <th scope="col">Price</th>
                        <th scope="col">Type</th>
                        <th scope="col">Status</th>
                        <th scope="col">Enq.</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($dbError)): ?>
                        <tr><td colspan="12" class="text-center text-danger py-4">Database Error: <?= htmlspecialchars($dbError) ?></td></tr>
                    <?php elseif ($result && mysqli_num_rows($result) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($result)):
                            $rowImages = json_decode($row['images'] ?? '[]', true);
                            $enqCnt = (int)($row['enq_cnt'] ?? 0);
                        ?>
                        <tr>
                            <td><input type="checkbox" class="form-check-input bulk-checkbox" value="<?= $row['id'] ?>" data-type="<?= $row['listing_type_db'] ?>"></td>
                            <td class="text-muted"><?= $row['id'] ?></td>
                            <td><code class="small"><?= htmlspecialchars($row['listing_code'] ?? '—') ?></code></td>
                            <td class="fw-medium"><?= htmlspecialchars($row['title']) ?></td>
                            <td><?= htmlspecialchars($row['city']) ?></td>
                            <td><?= htmlspecialchars($row['area'] ?? '—') ?></td>
                            <td><?= $row['total_area_sqft'] ? number_format($row['total_area_sqft']) : '—' ?></td>
                            <td><?= $row['price'] ? ($row['price'] !== '' ? '₹' . (is_numeric($row['price']) ? number_format($row['price']) : $row['price']) . '<small class="text-muted ms-1">Sq Ft / Month</small>' : '—') : '—' ?></td>
                            <td><span class="badge bg-<?= ($row['office_space_type'] ?? 'rent') === 'lease' ? 'info' : 'secondary' ?>"><?= htmlspecialchars(($row['office_space_type'] ?? 'rent')) ?></span></td>
                            <td><span class="badge bg-<?= $row['status'] === 'active' ? 'success' : 'secondary' ?>"><?= $row['status'] ?></span></td>
                            <td class="text-center">
                                <?php if ($enqCnt > 0): ?>
                                <a href="contacts.php?search=<?= urlencode($row['title']) ?>" class="badge bg-info text-decoration-none" title="View enquiries"><?= $enqCnt ?></a>
                                <?php else: ?>
                                <span class="text-muted">0</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($row['status'] === 'active'): ?>
                                <a href="/office_detail.php?slug=<?= htmlspecialchars($row['slug']) ?>&type=<?= $row['listing_type_db'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="View on site"><i class="fa-solid fa-eye"></i></a>
                                <?php endif; ?>
                                <a href="office-space.php?mode=edit&id=<?= $row['id'] ?>&type=<?= $row['listing_type_db'] ?>" class="btn btn-sm btn-outline-secondary" title="Edit"><i class="fa-solid fa-pen-to-square"></i></a>
                                <a href="javascript:void(0)" onclick="confirmDelete(<?= $row['id'] ?>, '<?= $row['listing_type_db'] ?>', '<?= htmlspecialchars($row['title'], ENT_QUOTES) ?>')" class="btn btn-sm btn-outline-danger" title="Delete"><i class="fa-solid fa-trash-can"></i></a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="12" class="text-center text-muted py-4">No listings found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php if ($total > $adminPerPage):
$pagParams = [];
foreach (['status','city','area','sqft','search'] as $k) {
    $v = $_GET[$k] ?? '';
    if ($v) $pagParams[] = urlencode($k) . '=' . urlencode($v);
}
$pagUrl = 'office-space.php?' . implode('&', $pagParams);
?>
<div class="mt-3"><?php render_admin_pagination($total, $adminListPage, $adminPerPage, $pagUrl); ?></div>
<?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>
