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
    static $cache = [];
    if (isset($cache[$office_id])) return $cache[$office_id];
    $s = mysqli_prepare($conn, "SELECT COUNT(*) as cnt FROM contacts WHERE office_id = ?");
    mysqli_stmt_bind_param($s, 'i', $office_id);
    mysqli_stmt_execute($s);
    $cnt = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($s))['cnt'];
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
    $listing = ['title'=>'', 'listing_type'=>$type, 'description'=>'', 'city'=>'', 'area'=>'', 'address'=>'', 'price'=>'', 'price_label'=>'', 'total_seats'=>'', 'total_area_sqft'=>'', 'amenities'=>'[]', 'images'=>'[]', 'status'=>'draft', 'featured'=>0, 'office_space_type'=>'rent', 'latitude'=>null, 'longitude'=>null, 'listing_code'=>'', 'slug'=>''];
    if ($mode === 'edit' && $editId) {
        $stmt = mysqli_prepare($conn, "SELECT * FROM $table WHERE id=?");
        mysqli_stmt_bind_param($stmt, 'i', $editId);
        mysqli_stmt_execute($stmt);
        $r = mysqli_stmt_get_result($stmt);
        $listing = mysqli_fetch_assoc($r);
        mysqli_stmt_close($stmt);
        if (!$listing) { echo '<div class="alert alert-warning">Listing not found.</div>'; require_once __DIR__ . '/footer.php'; exit; }
    }
    $amenities = json_decode($listing['amenities'] ?? '[]', true);
    $images = json_decode($listing['images'] ?? '[]', true);
    $cities = mysqli_query($conn, "SELECT DISTINCT city FROM $table WHERE city != '' ORDER BY city");
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
        <form id="listingForm" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="id" value="<?= $editId ?>">
            <input type="hidden" name="listing_type" value="<?= $type ?>">
            <input type="hidden" name="existing_images" id="existingImages" value='<?= htmlspecialchars(json_encode($images)) ?>'>
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="title" class="form-label small fw-semibold">Title <span class="text-danger">*</span></label>
                    <input type="text" name="title" id="title" class="form-control form-control-sm" required value="<?= htmlspecialchars($listing['title']) ?>" placeholder="e.g. DLF Downtown">
                </div>
                <div class="col-md-3">
                    <label for="city" class="form-label small fw-semibold">City <span class="text-danger">*</span></label>
                    <select name="city" id="city" class="form-select form-select-sm" required>
                        <option value="">- Select -</option>
                        <?php if ($cities): mysqli_data_seek($cities, 0); while ($c = mysqli_fetch_assoc($cities)): ?>
                        <option value="<?= htmlspecialchars($c['city']) ?>" <?= $listing['city']===$c['city']?'selected':'' ?>><?= htmlspecialchars(ucfirst($c['city'])) ?></option>
                        <?php endwhile; endif; ?>
                        <option value="chennai" <?= $listing['city']==='chennai'?'selected':'' ?>>Chennai</option>
                        <option value="bangalore" <?= $listing['city']==='bangalore'?'selected':'' ?>>Bangalore</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="area" class="form-label small fw-semibold">Area / Locality <span class="text-danger">*</span></label>
                    <input type="text" name="area" id="area" class="form-control form-control-sm" required value="<?= htmlspecialchars($listing['area']??'') ?>" placeholder="e.g. OMR">
                </div>
                <div class="col-md-3">
                    <label for="total_seats" class="form-label small fw-semibold">Total Seats</label>
                    <input type="number" name="total_seats" id="total_seats" class="form-control form-control-sm" value="<?= htmlspecialchars($listing['total_seats']??'') ?>" placeholder="e.g. 50">
                </div>
                <div class="col-md-3">
                    <label for="total_area_sqft" class="form-label small fw-semibold">Area (sq.ft) <span class="text-danger">*</span></label>
                    <input type="number" name="total_area_sqft" id="total_area_sqft" class="form-control form-control-sm" required value="<?= htmlspecialchars($listing['total_area_sqft']??'') ?>" placeholder="e.g. 2000">
                </div>
                <div class="col-md-3">
                    <label for="price" class="form-label small fw-semibold">Price</label>
                    <input type="number" step="0.01" name="price" id="price" class="form-control form-control-sm" value="<?= htmlspecialchars($listing['price']??'') ?>" placeholder="e.g. 150000">
                </div>
                <div class="col-md-3">
                    <label for="price_label" class="form-label small fw-semibold">Price Label</label>
                    <input type="text" name="price_label" id="price_label" class="form-control form-control-sm" value="<?= htmlspecialchars($listing['price_label']??'') ?>" placeholder="e.g. ₹1.5 Lakhs/mo">
                </div>
                <div class="col-md-3">
                    <label for="officeSpaceType" class="form-label small fw-semibold">Office Space Type</label>
                    <select name="office_space_type" class="form-select form-select-sm" id="officeSpaceType">
                        <option value="rent" <?= ($listing['office_space_type'] ?? 'rent') === 'rent' ? 'selected' : '' ?>>Rent (Monthly)</option>
                        <option value="lease" <?= ($listing['office_space_type'] ?? 'rent') === 'lease' ? 'selected' : '' ?>>Lease (Yearly)</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="latitude" class="form-label small fw-semibold">Latitude</label>
                    <input type="number" step="any" name="latitude" id="latitude" class="form-control form-control-sm" value="<?= htmlspecialchars($listing['latitude'] ?? '') ?>" placeholder="e.g. 12.9716">
                </div>
                <div class="col-md-3">
                    <label for="longitude" class="form-label small fw-semibold">Longitude</label>
                    <input type="number" step="any" name="longitude" id="longitude" class="form-control form-control-sm" value="<?= htmlspecialchars($listing['longitude'] ?? '') ?>" placeholder="e.g. 77.6412">
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label small fw-semibold">Status</label>
                    <select name="status" id="status" class="form-select form-select-sm">
                        <option value="draft" <?= $listing['status']==='draft'?'selected':'' ?>>Draft</option>
                        <option value="published" <?= $listing['status']==='published'?'selected':'' ?>>Published</option>
                        <option value="archived" <?= $listing['status']==='archived'?'selected':'' ?>>Archived</option>
                    </select>
                </div>
                <div class="col-12">
                    <label for="address" class="form-label small fw-semibold">Address <span class="text-danger">*</span></label>
                    <textarea name="address" id="address" class="form-control form-control-sm" rows="2" required placeholder="Full address"><?= htmlspecialchars($listing['address']??'') ?></textarea>
                </div>
                <div class="col-12">
                    <label for="description" class="form-label small fw-semibold">Description</label>
                    <textarea name="description" id="description" class="form-control form-control-sm" rows="3" placeholder="Describe the property"><?= htmlspecialchars($listing['description']??'') ?></textarea>
                </div>
                <div class="col-md-6">
                    <label for="amenities" class="form-label small fw-semibold">Amenities (comma separated)</label>
                    <input type="text" name="amenities" id="amenities" class="form-control form-control-sm" value="<?= htmlspecialchars(implode(', ', $amenities)) ?>" placeholder="WiFi, AC, Parking">
                </div>
                <div class="col-md-6">
                    <label for="feature_highlights" class="form-label small fw-semibold">Feature Highlights (one per line)</label>
                    <textarea name="feature_highlights" id="feature_highlights" class="form-control form-control-sm" rows="2" placeholder="Fully Furnished\u000a24/7 Power Backup"><?= htmlspecialchars(fmt_feature_highlights($listing['feature_highlights'] ?? '[]')) ?></textarea>
                </div>
                <div class="col-12">
                    <label for="seo_text" class="form-label small fw-semibold">SEO Text</label>
                    <textarea name="seo_text" id="seo_text" class="form-control form-control-sm" rows="3" placeholder="<h3>About this Workspace</h3>"><?= htmlspecialchars($listing['seo_text'] ?? '') ?></textarea>
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
                        <div class="position-relative" data-src="<?= htmlspecialchars($img) ?>">
                            <?php if ($imgExists): ?>
                            <img src="<?= htmlspecialchars($img) ?>" class="border" style="width: 70px; height: 40px; object-fit: cover;" loading="lazy" alt="Listing image">
                            <?php else: ?>
                            <div class="d-flex align-items-center justify-content-center border bg-light" style="width:70px;height:70px;"><i class="fa-solid fa-image text-muted"></i></div>
                            <?php endif; ?>
                            <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0" style="font-size: 10px; line-height: 1; padding: 1px 5px;" onclick="removeExistingImage(this)">&times;</button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input type="checkbox" name="featured" value="1" class="form-check-input" id="featuredCheck" <?= $listing['featured']?'checked':'' ?>>
                        <label class="form-check-label small" for="featuredCheck">Featured listing</label>
                    </div>
                </div>
            </div>
            <div class="mt-3 d-flex gap-2">
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

    $totalStmt = !empty($params)
        ? mysqli_prepare($conn, "SELECT COUNT(*) as cnt FROM $table$whereClause")
        : null;
    if ($totalStmt) {
        mysqli_stmt_bind_param($totalStmt, $types, ...$params);
        mysqli_stmt_execute($totalStmt);
        $total = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($totalStmt))['cnt'];
        mysqli_stmt_close($totalStmt);
    } else {
        $total = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM $table"))['cnt'];
    }
    $orderSql = " ORDER BY created_at DESC LIMIT $adminPerPage OFFSET $adminOffset";
    if (!empty($params)) {
        $stmt = mysqli_prepare($conn, "SELECT * FROM $table$whereClause$orderSql");
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
    } else {
        $result = mysqli_query($conn, "SELECT * FROM $table$whereClause$orderSql");
    }
    $cities = mysqli_query($conn, "SELECT DISTINCT city FROM $table WHERE city != '' ORDER BY city");

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
    <div class="col-md-5">
        <form method="get" class="d-flex gap-2">
            <input type="search" name="search" class="form-control form-control-sm" placeholder="Search by title, city, area, address..." value="<?= htmlspecialchars($searchQuery) ?>">
            <button type="submit" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-search"></i></button>
            <?php if ($searchQuery): ?>
            <a href="<?= mkUrl(['search'=>'']) ?>" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-times"></i></a>
            <?php endif; ?>
        </form>
    </div>
    <div class="col-md-7">
        <div class="d-flex gap-2 flex-wrap align-items-center">
            <span class="small text-muted">Filter:</span>
            <a href="<?= mkUrl(['status'=>'','city'=>'','featured'=>'']) ?>" class="btn btn-sm <?= !$statusFilter && !$cityFilter && !$featuredFilter ? 'btn-primary' : 'btn-outline-primary' ?>">All</a>
            <a href="<?= mkUrl(['status'=>'draft']) ?>" class="btn btn-sm <?= $statusFilter === 'draft' ? 'btn-primary' : 'btn-outline-primary' ?>">Draft</a>
            <a href="<?= mkUrl(['status'=>'published']) ?>" class="btn btn-sm <?= $statusFilter === 'published' ? 'btn-primary' : 'btn-outline-primary' ?>">Published</a>
            <a href="<?= mkUrl(['status'=>'archived']) ?>" class="btn btn-sm <?= $statusFilter === 'archived' ? 'btn-primary' : 'btn-outline-primary' ?>">Archived</a>
            <?php if ($cities): mysqli_data_seek($cities, 0); while ($c = mysqli_fetch_assoc($cities)): ?>
            <a href="<?= mkUrl(['city'=>$c['city']]) ?>" class="btn btn-sm <?= $cityFilter === $c['city'] ? 'btn-primary' : 'btn-outline-primary' ?>"><?= htmlspecialchars(ucfirst($c['city'])) ?></a>
            <?php endwhile; endif; ?>
            <a href="<?= mkUrl(['featured'=>'yes']) ?>" class="btn btn-sm <?= $featuredFilter === 'yes' ? 'btn-primary' : 'btn-outline-primary' ?>"><i class="fa-solid fa-star me-1"></i>Featured</a>
        </div>
    </div>
</div>
<div class="bulk-bar">
    <select id="bulkActionSelect" class="form-select form-select-sm" aria-label="Bulk actions">
        <option value="">-- Bulk Actions --</option>
        <option value="delete">Delete Selected</option>
        <option value="status-draft">Mark as Draft</option>
        <option value="status-published">Mark as Published</option>
        <option value="status-archived">Mark as Archived</option>
        <option value="featured-1">Mark as Featured</option>
        <option value="featured-0">Mark as Unfeatured</option>
    </select>
    <button class="btn btn-sm btn-secondary" onclick="applyBulkAction()">Apply</button>
</div>
<?php if (isset($_GET['deleted'])): ?><div class="alert alert-success py-2">Listing deleted.</div><?php endif; ?>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-success py-2">Listing saved.</div><?php endif; ?>
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
                    <?php while ($row = mysqli_fetch_assoc($result)):
                        $rowImages = json_decode($row['images'] ?? '[]', true);
                        $enqCnt = enquiries_count($row['id']);
                    ?>
                    <tr>
                        <td><input type="checkbox" class="form-check-input bulk-checkbox" value="<?= $row['id'] ?>"></td>
                        <td class="text-muted"><?= $row['id'] ?></td>
                        <td><code class="small"><?= htmlspecialchars($row['listing_code'] ?? '—') ?></code></td>
                        <td class="fw-medium"><?= htmlspecialchars($row['title']) ?></td>
                        <td><?= htmlspecialchars($row['city']) ?></td>
                        <td><?= htmlspecialchars($row['area'] ?? '—') ?></td>
                        <td><?= $row['total_area_sqft'] ? number_format($row['total_area_sqft']) : '—' ?></td>
                        <td><?= $row['total_seats'] ?? '—' ?></td>
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
                            <a href="/office_detail.php?slug=<?= htmlspecialchars($row['slug']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="View on site"><i class="fa-solid fa-eye"></i></a>
                            <?php endif; ?>
                            <a href="managed-office.php?mode=edit&id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Edit"><i class="fa-solid fa-pen-to-square"></i></a>
                            <a href="office-details.php?office_id=<?= $row['id'] ?>&tab=extras" class="btn btn-sm btn-outline-secondary" title="Details"><i class="fa-solid fa-list-check"></i></a>
                            <a href="javascript:void(0)" onclick="confirmDelete(<?= $row['id'] ?>, 'managed', '<?= htmlspecialchars($row['title'], ENT_QUOTES) ?>')" class="btn btn-sm btn-outline-danger" title="Delete"><i class="fa-solid fa-trash-can"></i></a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
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
