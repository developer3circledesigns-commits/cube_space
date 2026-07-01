<?php
require_once __DIR__ . '/layout.php';

function fmt_feature_highlights($val) {
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

function enquiries_count($office_id) {
    global $conn;
    if (!isset($conn) || !$conn) return 0;
    static $cache = [];
    if (isset($cache[$office_id])) return $cache[$office_id];
    $s = mysqli_prepare($conn, "SELECT COUNT(*) as cnt FROM contacts WHERE office_id = ?");
    if (!$s) return 0;
    mysqli_stmt_bind_param($s, 'i', $office_id);
    mysqli_stmt_execute($s);
    $r = mysqli_stmt_get_result($s);
    $cnt = $r ? (int)mysqli_fetch_assoc($r)['cnt'] : 0;
    $cache[$office_id] = $cnt;
    return $cnt;
}

$adminListPage = max(1, (int)($_GET['p'] ?? 1));
$adminPerPage = 50;
$adminOffset = ($adminListPage - 1) * $adminPerPage;
$mode = $_GET['mode'] ?? 'list';
$type = 'managed';
$table = 'managed_offices';
$typeLabel = 'Managed Office';
$searchQuery = trim($_GET['search'] ?? '');

if ($mode === 'add' || $mode === 'edit'):
    $editId = (int)($_GET['id'] ?? 0);
    $listing = ['title'=>'', 'listing_type'=>$type, 'description'=>'', 'city'=>'', 'area'=>'', 'address'=>'', 'price'=>'', 'price_label'=>'', 'total_seats'=>'', 'total_area_sqft'=>'', 'amenities'=>'[]', 'images'=>'[]', 'status'=>'published', 'featured'=>0, 'office_space_type'=>'rent', 'latitude'=>null, 'longitude'=>null, 'listing_code'=>'', 'slug'=>'', 'min_inventory'=>'', 'inventory_type'=>''];
    if ($mode === 'edit' && $editId) {
        $stmt = mysqli_prepare($conn, "SELECT * FROM $table WHERE id=?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $editId);
            mysqli_stmt_execute($stmt);
            $r = mysqli_stmt_get_result($stmt);
            $listing = mysqli_fetch_assoc($r);
            mysqli_stmt_close($stmt);
        }
        if (!$listing) { echo '<div class="alert alert-warning">Listing not found.</div>'; require_once __DIR__ . '/footer.php'; exit; }
    }
    $amenities = json_decode($listing['amenities'] ?? '[]', true);
    $images = json_decode($listing['images'] ?? '[]', true);
    $cities = mysqli_query($conn, "SELECT city FROM (SELECT city COLLATE utf8mb4_unicode_ci AS city FROM listing_cities UNION SELECT DISTINCT city COLLATE utf8mb4_unicode_ci AS city FROM $table WHERE city != '') AS c ORDER BY city");
    if (!$cities) $cities = false;
    $areas = mysqli_query($conn, "SELECT area, city FROM (SELECT area COLLATE utf8mb4_unicode_ci AS area, city COLLATE utf8mb4_unicode_ci AS city FROM listing_areas UNION SELECT DISTINCT area COLLATE utf8mb4_unicode_ci AS area, city COLLATE utf8mb4_unicode_ci AS city FROM $table WHERE area != '' AND area IS NOT NULL) AS a ORDER BY area");
    if (!$areas) $areas = false;
    $amenityList = ['WiFi','Air Conditioning','Power Backup','Parking','Security Guard','CCTV Surveillance','Elevator / Lift','Reception Area','Pantry / Cafeteria','Meeting Room','Visitor Parking','24/7 Access'];
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><?= $mode === 'add' ? 'Add' : 'Edit' ?> <?= $typeLabel ?></h4>
    <a href="managed-office.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Back</a>
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
            <input type="hidden" name="listing_type" value="<?= $type ?>">
            <input type="hidden" name="existing_images" id="existingImages" value='<?= htmlspecialchars(json_encode($images)) ?>'>
            <input type="hidden" name="amenities" id="amenitiesInput" value="<?= htmlspecialchars($listing['amenities'] ?? '[]') ?>">
            <input type="hidden" name="office_space_type" value="rent">

            <div class="col-md-6 position-relative">
                <label for="title" class="form-label small fw-semibold">Title <span class="text-danger">*</span></label>
                <input type="text" name="title" id="title" class="form-control form-control-sm" required value="<?= htmlspecialchars($listing['title']) ?>" placeholder="e.g. DLF Downtown">
                <div class="valid-tooltip">Looks good!</div>
                <div class="invalid-tooltip">Please enter a title.</div>
            </div>

            <div class="col-md-3 position-relative">
                <label for="city" class="form-label small fw-semibold">City <span class="text-danger">*</span></label>
                <select name="city" id="city" class="form-select form-select-sm" required>
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
                <label for="total_seats" class="form-label small fw-semibold">Available Seats <span class="text-danger">*</span></label>
                <select name="total_seats" id="total_seats" class="form-select form-select-sm" required>
                    <option value="">- Select -</option>
                    <option value="50" <?= $listing['total_seats']=='50'?'selected':'' ?>>10-50 Seats</option>
                    <option value="100" <?= $listing['total_seats']=='100'?'selected':'' ?>>51-100 Seats</option>
                    <option value="200" <?= $listing['total_seats']=='200'?'selected':'' ?>>101-200 Seats</option>
                    <option value="500" <?= $listing['total_seats']=='500'?'selected':'' ?>>200+ Seats</option>
                </select>
                <div class="invalid-tooltip">Please select seats range.</div>
            </div>

            <div class="col-md-3 position-relative">
                <label for="min_inventory" class="form-label small fw-semibold">Minimum Inventory</label>
                <input type="text" name="min_inventory" id="min_inventory" class="form-control form-control-sm" value="<?= htmlspecialchars($listing['min_inventory']??'') ?>" placeholder="e.g. 5 seats">
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
                        $imgPath = $_SERVER['DOCUMENT_ROOT'] . $img;
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
                <label for="price" class="form-label small fw-semibold">Price</label>
                <input type="number" step="0.01" name="price" id="price" class="form-control form-control-sm" value="<?= htmlspecialchars($listing['price']??'') ?>" placeholder="e.g. 150000">
            </div>

            <input type="hidden" name="status" value="published">

            <div class="col-12">
                <div class="form-check">
                    <input type="checkbox" name="featured" value="1" class="form-check-input" id="featuredCheck" <?= $listing['featured']?'checked':'' ?>>
                    <label class="form-check-label small" for="featuredCheck">Featured listing</label>
                </div>
            </div>

            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm"><?= $mode === 'add' ? 'Create Listing' : 'Update Listing' ?></button>
                <a href="managed-office.php" class="btn btn-outline-secondary btn-sm">Cancel</a>
            </div>
        </form>
        <div id="formResult" class="alert d-none mt-2"></div>
    </div>
</div>
<?php else:
    $statusFilter = $_GET['status'] ?? '';
    $cityFilter = $_GET['city'] ?? '';
    $featuredFilter = $_GET['featured'] ?? '';

    $where = [];
    $params = [];
    $types = '';
    $conditions = [];

    if ($statusFilter && in_array($statusFilter, ['draft','published','archived'])) {
        $conditions[] = "status = ?";
        $params[] = $statusFilter;
        $types .= 's';
    }
    if ($cityFilter) {
        $conditions[] = "city = ?";
        $params[] = $cityFilter;
        $types .= 's';
    }
    if ($featuredFilter === 'yes') {
        $conditions[] = "featured = 1";
    } elseif ($featuredFilter === 'no') {
        $conditions[] = "featured = 0";
    }
    if ($searchQuery) {
        $conditions[] = "(title LIKE ? OR city LIKE ? OR area LIKE ? OR address LIKE ?)";
        $sp = "%$searchQuery%";
        $params[] = $sp; $params[] = $sp; $params[] = $sp; $params[] = $sp;
        $types .= 'ssss';
    }
    $whereClause = !empty($conditions) ? ' WHERE ' . implode(' AND ', $conditions) : '';

    $total = 0;
    $totalStmt = !empty($params)
        ? mysqli_prepare($conn, "SELECT COUNT(*) as cnt FROM $table$whereClause")
        : null;
    if ($totalStmt) {
        mysqli_stmt_bind_param($totalStmt, $types, ...$params);
        mysqli_stmt_execute($totalStmt);
        $totalResult = mysqli_stmt_get_result($totalStmt);
        if ($totalResult) $total = (int)mysqli_fetch_assoc($totalResult)['cnt'];
        mysqli_stmt_close($totalStmt);
    } else {
        $countResult = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM $table");
        if ($countResult) $total = (int)mysqli_fetch_assoc($countResult)['cnt'];
    }
    $orderSql = " ORDER BY created_at DESC LIMIT $adminPerPage OFFSET $adminOffset";
    $result = false;
    $dbError = '';
    if (!empty($params)) {
        $stmt = mysqli_prepare($conn, "SELECT * FROM $table$whereClause$orderSql");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            if (!$result) $dbError = mysqli_error($conn);
        } else {
            $dbError = mysqli_error($conn);
        }
    } else {
        $qResult = mysqli_query($conn, "SELECT * FROM $table$whereClause$orderSql");
        if ($qResult) {
            $result = $qResult;
        } else {
            $dbError = mysqli_error($conn);
        }
    }
    $cities = mysqli_query($conn, "SELECT city FROM (SELECT city COLLATE utf8mb4_unicode_ci AS city FROM listing_cities UNION SELECT DISTINCT city COLLATE utf8mb4_unicode_ci AS city FROM $table WHERE city != '') AS c ORDER BY city");
    if (!$cities) $cities = false;

    function mkUrl($extra) {
        $params = [];
        foreach (['status','city','featured','search'] as $k) {
            $v = $_GET[$k] ?? '';
            if ($v && !isset($extra[$k])) $params[] = urlencode($k) . '=' . urlencode($v);
        }
        foreach ($extra as $k => $v) {
            if ($v !== '') $params[] = urlencode($k) . '=' . urlencode($v);
        }
        return 'managed-office.php' . ($params ? '?' . implode('&', $params) : '');
    }

    $exportUrl = 'api/listing_crud.php?action=export&listing_type=managed';
    foreach (['status','city','featured','search'] as $k) {
        $v = $_GET[$k] ?? '';
        if ($v) $exportUrl .= '&' . urlencode($k) . '=' . urlencode($v);
    }
?>
<div class="page-header">
    <h4><?= $typeLabel ?></h4>
    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-primary"><?= $total ?> listings</span>
        <a href="<?= $exportUrl ?>" class="btn btn-outline-success btn-sm" title="Export CSV"><i class="fa-solid fa-download"></i> CSV</a>
        <a href="managed-office.php?mode=add" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus me-1"></i>Add</a>
    </div>
</div>
<div class="row g-2 mb-3">
    <div class="col-md-12">
        <div class="d-flex gap-2 flex-wrap align-items-center">
            <span class="small text-muted">Filter:</span>
            <a href="<?= mkUrl(['status'=>'','city'=>'','featured'=>'']) ?>" class="btn btn-sm <?= !$statusFilter && !$cityFilter && !$featuredFilter ? 'btn-primary' : 'btn-outline-primary' ?>">All</a>
            <a href="<?= mkUrl(['status'=>'draft']) ?>" class="btn btn-sm <?= $statusFilter === 'draft' ? 'btn-primary' : 'btn-outline-primary' ?>">Draft</a>
            <a href="<?= mkUrl(['status'=>'published']) ?>" class="btn btn-sm <?= $statusFilter === 'published' ? 'btn-primary' : 'btn-outline-primary' ?>">Published</a>
            <a href="<?= mkUrl(['status'=>'archived']) ?>" class="btn btn-sm <?= $statusFilter === 'archived' ? 'btn-primary' : 'btn-outline-primary' ?>">Archived</a>
            <?php if ($cities && mysqli_num_rows($cities)): mysqli_data_seek($cities, 0); while ($c = mysqli_fetch_assoc($cities)): ?>
            <a href="<?= mkUrl(['city'=>$c['city']]) ?>" class="btn btn-sm <?= $cityFilter === $c['city'] ? 'btn-primary' : 'btn-outline-primary' ?>"><?= htmlspecialchars(ucfirst($c['city'])) ?></a>
            <?php endwhile; endif; ?>
            <a href="<?= mkUrl(['featured'=>'yes']) ?>" class="btn btn-sm <?= $featuredFilter === 'yes' ? 'btn-primary' : 'btn-outline-primary' ?>"><i class="fa-solid fa-star me-1"></i>Featured</a>
            <a href="<?= mkUrl(['featured'=>'no']) ?>" class="btn btn-sm <?= $featuredFilter === 'no' ? 'btn-primary' : 'btn-outline-primary' ?>"><i class="fa-solid fa-star-half-stroke me-1"></i>Unfeatured</a>
        </div>
    </div>
</div>
<?php if (isset($_GET['deleted'])): ?><div class="alert alert-success py-2">Listing deleted.</div><?php endif; ?>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-success py-2">Listing saved.</div><?php endif; ?>
<div class="bulk-bar d-flex align-items-center gap-2 mb-2 p-2 bg-light">
    <select id="bulkActionSelect" class="form-select form-select-sm" aria-label="Bulk actions" style="width:auto;">
        <option value="">-- Bulk Actions --</option>
        <option value="delete">Delete Selected</option>
        <option value="status-draft">Mark as Draft</option>
        <option value="status-published">Mark as Published</option>

    </select>
    <button class="btn btn-sm btn-secondary" onclick="applyBulkAction()">Apply</button>
</div>
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
                        <th scope="col">Seats</th>
                        <th scope="col">Price</th>
                        <th scope="col">Type</th>
                        <th scope="col">Status</th>
                        <th scope="col">Enq.</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($dbError)): ?>
                        <tr><td colspan="13" class="text-center text-danger py-4">Database Error: <?= htmlspecialchars($dbError) ?></td></tr>
                    <?php elseif ($result && mysqli_num_rows($result) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($result)):
                            $rowImages = json_decode($row['images'] ?? '[]', true);
                            $enqCnt = enquiries_count($row['id']);
                        ?>
                        <tr>
                            <td><input type="checkbox" class="form-check-input bulk-checkbox" value="<?= $row['id'] ?>" data-type="managed"></td>
                            <td class="text-muted"><?= $row['id'] ?></td>
                            <td><code class="small"><?= htmlspecialchars($row['listing_code'] ?? '—') ?></code></td>
                            <td class="fw-medium"><?= htmlspecialchars($row['title']) ?></td>
                            <td><?= htmlspecialchars($row['city']) ?></td>
                            <td><?= htmlspecialchars($row['area'] ?? '—') ?></td>
                            <td><?= $row['total_area_sqft'] ? number_format($row['total_area_sqft']) : '—' ?></td>
                            <td><?php
                                $ts = $row['total_seats'] ?? 0;
                                if ($ts <= 50) echo '10-50';
                                elseif ($ts <= 100) echo '51-100';
                                elseif ($ts <= 200) echo '101-200';
                                else echo '200+';
                            ?></td>
                            <td><?= $row['price'] ? '₹' . number_format($row['price']) . '<small class="text-muted ms-1">' . ($row['office_space_type'] === 'lease' ? '/yr' : '/mo') . '</small>' : '—' ?></td>
                            <td><span class="badge bg-<?= ($row['office_space_type'] ?? 'rent') === 'lease' ? 'info' : 'secondary' ?>"><?= htmlspecialchars(($row['office_space_type'] ?? 'rent')) ?></span></td>
                            <td><span class="badge bg-<?= $row['status'] === 'published' ? 'success' : ($row['status'] === 'draft' ? 'secondary' : 'warning text-dark') ?>"><?= $row['status'] ?></span></td>
                            <td class="text-center">
                                <?php if ($enqCnt > 0): ?>
                                <a href="contacts.php?search=<?= urlencode($row['title']) ?>" class="badge bg-info text-decoration-none" title="View enquiries"><?= $enqCnt ?></a>
                                <?php else: ?>
                                <span class="text-muted">0</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($row['status'] === 'published'): ?>
                                <a href="/office_detail.php?slug=<?= htmlspecialchars($row['slug']) ?>&type=managed" target="_blank" class="btn btn-sm btn-outline-secondary" title="View on site"><i class="fa-solid fa-eye"></i></a>
                                <?php endif; ?>
                                <a href="managed-office.php?mode=edit&id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Edit"><i class="fa-solid fa-pen-to-square"></i></a>
                                <a href="office-details.php?office_id=<?= $row['id'] ?>&tab=extras" class="btn btn-sm btn-outline-secondary" title="Details"><i class="fa-solid fa-list-check"></i></a>
                                <a href="javascript:void(0)" onclick="confirmDelete(<?= $row['id'] ?>, 'managed', '<?= htmlspecialchars($row['title'], ENT_QUOTES) ?>')" class="btn btn-sm btn-outline-danger" title="Delete"><i class="fa-solid fa-trash-can"></i></a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="13" class="text-center text-muted py-4">No listings found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php if ($total > $adminPerPage): ?>
<?php
$pagParams = [];
foreach (['status','city','featured','search'] as $k) {
    $v = $_GET[$k] ?? '';
    if ($v) $pagParams[] = urlencode($k) . '=' . urlencode($v);
}
$pagUrl = 'managed-office.php?' . implode('&', $pagParams);
?>
<div class="mt-3"><?php render_admin_pagination($total, $adminListPage, $adminPerPage, $pagUrl); ?></div>
<?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>
