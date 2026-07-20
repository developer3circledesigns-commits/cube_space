<?php
require_once __DIR__ . '/layout.php';

$mode = $_GET['mode'] ?? 'list';
$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['p'] ?? 1));
$perPage = 50;

$successMsg = '';
$errorMsg = '';

if (isset($_GET['created'])) $successMsg = 'Admin created successfully.';
if (isset($_GET['saved'])) $successMsg = 'Admin updated successfully.';
if (isset($_GET['deleted'])) $successMsg = 'Admin deleted successfully.';
if (isset($_GET['error'])) $errorMsg = htmlspecialchars($_GET['error']);

if ($mode === 'edit') {
    $editId = (int)($_GET['id'] ?? 0);
    if (!$editId) { echo '<div class="alert alert-danger">Invalid admin ID.</div>'; require_once __DIR__ . '/footer.php'; exit; }
?>
<div class="page-header">
    <h4><i class="fa-solid fa-user-shield me-2"></i>Edit Admin</h4>
    <a href="admins.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Back to Admins</a>
</div>
<div class="row">
    <div class="col-md-6">
        <div class="admin-card">
            <div class="card-body">
                <form id="adminEditForm">
                    <input type="hidden" name="id" id="editAdminId" value="<?= $editId ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <div id="formResult" class="alert d-none mt-2"></div>
                    <div class="mb-2">
                        <label for="edit_username" class="form-label small fw-semibold">Username</label>
                        <input type="text" id="edit_username" name="username" required class="form-control form-control-sm">
                    </div>
                    <div class="mb-2">
                        <label for="edit_email" class="form-label small fw-semibold">Email</label>
                        <input type="email" id="edit_email" name="email" required class="form-control form-control-sm">
                    </div>
                    <div class="mb-2">
                        <label for="edit_password" class="form-label small fw-semibold">New Password <span class="text-muted">(leave blank to keep current)</span></label>
                        <input type="password" id="edit_password" name="password" class="form-control form-control-sm" placeholder="Min 8 chars" minlength="8">
                    </div>
                    <div class="mb-2">
                        <label for="edit_is_active" class="form-label small fw-semibold">Status</label>
                        <select id="edit_is_active" name="is_active" class="form-select form-select-sm">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
                    <a href="admins.php" class="btn btn-outline-secondary btn-sm">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    fetch('/admin/api/admin_crud.php?action=get&id=<?= $editId ?>', {
        headers: { 'Authorization': 'Bearer ' + (sessionStorage.getItem('admin_access_token') || '') }
    }).then(function(r) { return r.json(); }).then(function(d) {
        if (d.success && d.data) {
            document.getElementById('edit_username').value = d.data.username || '';
            document.getElementById('edit_email').value = d.data.email || '';
            document.getElementById('edit_is_active').value = d.data.is_active;
        } else {
            showAlertModal(d.error || 'Failed to load admin', 'error');
        }
    }).catch(function() { showAlertModal('Failed to load admin data', 'error'); });
});
</script>
<script>
var adminEditForm = document.getElementById('adminEditForm');
if (adminEditForm) {
    adminEditForm.addEventListener('submit', handleAdminForm);
}
function handleAdminForm(e) {
    e.preventDefault();
    var form = e.target;
    var btn = form.querySelector('button[type="submit"]');
    var result = document.getElementById('formResult');
    if (!result) return;
    var fd = new FormData(form);
    if (!fd.get('is_active')) fd.set('is_active', '0');
    btn.disabled = true;
    btn.textContent = 'Saving...';
    result.className = 'alert d-none mt-2';
    (async function() {
        try {
            var headers = { 'Authorization': 'Bearer ' + getToken(), 'X-CSRF-Token': (document.querySelector('meta[name="csrf-token"]') || {}).content || '' };
            var r = await fetch('/admin/api/admin_crud.php?action=update', { method: 'POST', headers: headers, credentials: 'same-origin', body: fd });
            if (r.status === 401) {
                var refreshRes = await fetch('/admin/token_refresh.php', { method: 'POST', credentials: 'same-origin' });
                if (refreshRes.ok) {
                    var refreshData = await refreshRes.json();
                    if (refreshData.access_token) {
                        sessionStorage.setItem('admin_access_token', refreshData.access_token);
                        headers['Authorization'] = 'Bearer ' + refreshData.access_token;
                        r = await fetch('/admin/api/admin_crud.php?action=update', { method: 'POST', headers: headers, credentials: 'same-origin', body: fd });
                    }
                }
            }
            if (r.status === 401) { window.location.reload(); return; }
            var d = await r.json();
            result.classList.remove('d-none');
            if (d.success) {
                result.className = 'alert alert-success mt-2';
                result.textContent = d.message;
                setTimeout(function() { window.location.href = 'admins.php?saved=1'; }, 800);
            } else {
                result.className = 'alert alert-danger mt-2';
                result.textContent = d.error || 'Operation failed';
            }
        } catch (err) {
            result.classList.remove('d-none');
            result.className = 'alert alert-danger mt-2';
            result.textContent = err.message || 'Network error';
        }
        btn.disabled = false;
        btn.textContent = 'Save Changes';
    })();
}
</script>
<?php
    require_once __DIR__ . '/footer.php';
    exit;
}

$where = [];
$params = [];
$types = '';

if ($search) {
    $where[] = "(username LIKE ? OR email LIKE ?)";
    $sp = "%$search%";
    $params[] = $sp; $params[] = $sp;
    $types .= 'ss';
}
if ($statusFilter === 'active') {
    $where[] = "is_active=1";
} elseif ($statusFilter === 'inactive') {
    $where[] = "is_active=0";
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = mysqli_prepare($conn, "SELECT COUNT(*) as cnt FROM admins $whereClause");
if ($params) mysqli_stmt_bind_param($countStmt, $types, ...$params);
mysqli_stmt_execute($countStmt);
$total = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($countStmt))['cnt'];

$offset = ($page - 1) * $perPage;
$stmt = mysqli_prepare($conn, "SELECT id, username, email, role, is_active, last_login, created_at FROM admins $whereClause ORDER BY created_at DESC LIMIT ? OFFSET ?");
$allParams = array_merge($params, [$perPage, $offset]);
$allTypes = $types . 'ii';
mysqli_stmt_bind_param($stmt, $allTypes, ...$allParams);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

function mkUrl($extra) {
    $params = [];
    if (!empty($_GET['search'])) $params['search'] = $_GET['search'];
    if (!empty($_GET['status'])) $params['status'] = $_GET['status'];
    if (!empty($_GET['p'])) $params['p'] = $_GET['p'];
    $params = array_merge($params, $extra);
    return 'admins.php' . ($params ? '?' . http_build_query($params) : '');
}

$exportUrl = 'api/admin_crud.php?action=export';
if ($search) $exportUrl .= '&search=' . urlencode($search);
if ($statusFilter) $exportUrl .= '&status=' . urlencode($statusFilter);
?>
<div class="page-header">
    <h4><i class="fa-solid fa-user-shield me-2"></i>Admins <span class="badge bg-primary"><?= $total ?></span></h4>
    <div class="d-flex align-items-center gap-2">
        <a href="<?= $exportUrl ?>" class="btn btn-outline-success btn-sm"><i class="fa-solid fa-download me-1"></i>CSV</a>
        <a href="admins.php?mode=add" class="btn btn-primary btn-sm" id="showCreateForm"><i class="fa-solid fa-plus me-1"></i>Add Admin</a>
    </div>
</div>
<?php if ($successMsg): ?>
<div class="alert alert-success py-2"><?= $successMsg ?></div>
<?php endif; if ($errorMsg): ?>
<div class="alert alert-danger py-2"><?= $errorMsg ?></div>
<?php endif; ?>
<div id="adminToastContainer"></div>
<script>
(function() {
    var msg = sessionStorage.getItem('adminToast');
    if (msg) {
        sessionStorage.removeItem('adminToast');
        if (window.CubeToast) CubeToast.success(msg);
    }
})();
</script>
<div class="row g-3">
    <div class="col-md-12">
        <div class="admin-card">
            <div class="card-body">
                <div id="addFormContainer" class="<?= ($_GET['mode'] ?? '') === 'add' ? '' : 'd-none' ?>">
                    <h6 class="fw-bold mb-3">Create New Admin</h6>
                    <form id="adminCreateForm">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <div id="createFormResult" class="alert d-none mt-2"></div>
                        <div class="row g-2 mb-2">
                            <div class="col-md-4">
                                <label for="new_username" class="form-label small fw-semibold">Username</label>
                                <input type="text" id="new_username" name="username" required class="form-control form-control-sm" placeholder="Username">
                            </div>
                            <div class="col-md-4">
                                <label for="new_email" class="form-label small fw-semibold">Email</label>
                                <input type="email" id="new_email" name="email" required class="form-control form-control-sm" placeholder="Email">
                            </div>
                            <div class="col-md-4">
                                <label for="new_password" class="form-label small fw-semibold">Password (min 8 chars)</label>
                                <input type="password" id="new_password" name="password" required class="form-control form-control-sm" placeholder="Password" minlength="8">
                            </div>
                        </div>
                        <div class="row g-2 align-items-end">
                            <div class="col-auto">
                                <div class="form-check">
                                    <input type="checkbox" id="new_is_active" name="is_active" value="1" checked class="form-check-input">
                                    <label for="new_is_active" class="form-check-label small">Active</label>
                                </div>
                            </div>
                            <div class="col-auto">
                                <button type="submit" class="btn btn-primary btn-sm">Create Admin</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="cancelCreateForm">Cancel</button>
                            </div>
                        </div>
                    </form>
                    <hr>
                </div>

                <button class="btn btn-sm btn-outline-primary admin-filter-toggle mb-2" type="button" data-bs-toggle="collapse" data-bs-target="#adminsFilters" aria-expanded="true">
                    <i class="fa-solid fa-sliders-h"></i> Filters
                </button>
                <div class="collapse show admin-filter-section" id="adminsFilters">
                    <div class="d-flex flex-wrap gap-2">
                        <div>
                            <div class="filter-label">Search</div>
                            <form method="GET" action="admins.php">
                                <div class="d-flex gap-2">
                                    <input type="search" name="search" class="form-control form-control-sm" placeholder="Search by username or email..." value="<?= htmlspecialchars($search) ?>">
                                    <button type="submit" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-search"></i></button>
                                    <?php if ($search): ?>
                                    <a href="admins.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-times"></i></a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                        <div>
                            <div class="filter-label">Status</div>
                            <div class="filter-btn-group">
                                <a href="<?= mkUrl(['status' => '', 'p' => 1]) ?>" class="btn btn-sm <?= !$statusFilter ? 'btn-primary' : 'btn-outline-primary' ?>">All</a>
                                <a href="<?= mkUrl(['status' => 'active', 'p' => 1]) ?>" class="btn btn-sm <?= $statusFilter === 'active' ? 'btn-primary' : 'btn-outline-primary' ?>">Active</a>
                                <a href="<?= mkUrl(['status' => 'inactive', 'p' => 1]) ?>" class="btn btn-sm <?= $statusFilter === 'inactive' ? 'btn-primary' : 'btn-outline-primary' ?>">Inactive</a>
                            </div>
                        </div>
                        <hr class="my-1">
                        <div class="bulk-bar <?= $total > 0 ? '' : 'd-none' ?> p-0 mb-0 border-0 bg-transparent">
                            <select id="bulkActionSelect" class="form-select form-select-sm">
                                <option value="">Bulk actions</option>
                                <option value="delete">Delete</option>
                                <option value="activate">Activate</option>
                                <option value="deactivate">Deactivate</option>
                            </select>
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="applyAdminBulkAction()">Apply</button>
                        </div>
                    </div>
                </div>

                <div class="table-wrap">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 small">
                            <thead>
                                <tr>
                                    <th scope="col" style="width:36px"><input type="checkbox" class="form-check-input checkAll" onchange="toggleAllCheckboxes(this)"></th>
                                    <th scope="col">ID</th>
                                    <th scope="col">Username</th>
                                    <th scope="col">Email</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Last Login</th>
                                    <th scope="col">Created</th>
                                    <th scope="col" style="width:120px">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (mysqli_num_rows($result) > 0): while ($row = mysqli_fetch_assoc($result)): ?>
                                <tr>
                                    <td><input type="checkbox" class="form-check-input bulk-checkbox" value="<?= $row['id'] ?>"></td>
                                    <td class="text-muted"><?= $row['id'] ?></td>
                                    <td class="fw-medium"><?= htmlspecialchars($row['username']) ?></td>
                                    <td><?= htmlspecialchars($row['email'] ?? '') ?></td>
                                    <td>
                                        <?php if ($row['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted"><?= $row['last_login'] ? date('d M Y H:i', strtotime($row['last_login'])) : 'Never' ?></td>
                                    <td class="text-muted"><?= date('d M Y', strtotime($row['created_at'])) ?></td>
                                    <td>
                                        <a href="admins.php?mode=edit&id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Edit"><i class="fa-solid fa-pen-to-square"></i></a>
                                        <button type="button" class="btn btn-sm btn-outline-<?= $row['is_active'] ? 'warning' : 'success' ?>" title="<?= $row['is_active'] ? 'Deactivate' : 'Activate' ?>" onclick="toggleAdminActive(<?= $row['id'] ?>, '<?= htmlspecialchars($row['username'], ENT_QUOTES) ?>')"><i class="fa-solid fa-<?= $row['is_active'] ? 'ban' : 'check' ?>"></i></button>
                                        <button type="button" class="btn btn-sm btn-outline-danger" title="Delete" onclick="confirmDeleteAdmin(<?= $row['id'] ?>, '<?= htmlspecialchars($row['username'], ENT_QUOTES) ?>')"><i class="fa-solid fa-trash-can"></i></button>
                                    </td>
                                </tr>
                                <?php endwhile; else: ?>
                                <tr><td colspan="8" class="text-center text-muted py-4">No admins found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php
                $pagParams = [];
                if ($search) $pagParams['search'] = $search;
                if ($statusFilter) $pagParams['status'] = $statusFilter;
                $pagBase = 'admins.php' . ($pagParams ? '?' . http_build_query($pagParams) : '');
                render_admin_pagination($total, $page, $perPage, $pagBase);
                ?>
            </div>
        </div>
    </div>
</div>
<script>
document.getElementById('showCreateForm').addEventListener('click', function(e) {
    e.preventDefault();
    document.getElementById('addFormContainer').classList.remove('d-none');
});
document.getElementById('cancelCreateForm').addEventListener('click', function() {
    document.getElementById('addFormContainer').classList.add('d-none');
    document.getElementById('adminCreateForm').reset();
    document.getElementById('createFormResult').className = 'alert d-none mt-2';
});
document.getElementById('adminCreateForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var form = this;
    var btn = form.querySelector('button[type="submit"]');
    var result = document.getElementById('createFormResult');
    var fd = new FormData(form);
    if (!fd.get('is_active')) fd.set('is_active', '0');
    btn.disabled = true;
    btn.textContent = 'Creating...';
    result.className = 'alert d-none mt-2';
    (async function() {
        try {
            var headers = { 'Authorization': 'Bearer ' + getToken(), 'X-CSRF-Token': (document.querySelector('meta[name="csrf-token"]') || {}).content || '' };
            var r = await fetch('/admin/api/admin_crud.php?action=create', { method: 'POST', headers: headers, credentials: 'same-origin', body: fd });
            if (r.status === 401) {
                var refreshRes = await fetch('/admin/token_refresh.php', { method: 'POST', credentials: 'same-origin' });
                if (refreshRes.ok) {
                    var refreshData = await refreshRes.json();
                    if (refreshData.access_token) {
                        sessionStorage.setItem('admin_access_token', refreshData.access_token);
                        headers['Authorization'] = 'Bearer ' + refreshData.access_token;
                        r = await fetch('/admin/api/admin_crud.php?action=create', { method: 'POST', headers: headers, credentials: 'same-origin', body: fd });
                    }
                }
            }
            if (r.status === 401) { window.location.reload(); return; }
            var d = await r.json();
            result.classList.remove('d-none');
            if (d.success) {
                result.className = 'alert alert-success mt-2';
                result.textContent = d.message;
                setTimeout(function() { window.location.href = 'admins.php?created=1'; }, 800);
            } else {
                result.className = 'alert alert-danger mt-2';
                result.textContent = d.error || 'Operation failed';
            }
        } catch (err) {
            result.classList.remove('d-none');
            result.className = 'alert alert-danger mt-2';
            result.textContent = err.message || 'Network error';
        }
        btn.disabled = false;
        btn.textContent = 'Create Admin';
    })();
});
function confirmDeleteAdmin(id, username) {
    showConfirmDialog('Delete admin "' + username + '" (ID: ' + id + ')? This cannot be undone.', function() {
        var fd = new FormData();
        fd.append('id', id);
        var headers = {};
        var token = getToken();
        if (token) headers['Authorization'] = 'Bearer ' + token;
        headers['X-CSRF-Token'] = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
        fetch('/admin/api/admin_crud.php?action=delete', { method: 'POST', headers: headers, body: fd })
            .then(function(r) { return r.json(); })
            .then(function(d) { if (d.success) window.location.href = 'admins.php?deleted=1'; else showAlertModal(d.error, 'error'); })
            .catch(function(err) { showAlertModal('Network error', 'error'); });
    });
}
function toggleAdminActive(id, username) {
    var fd = new FormData();
    fd.append('id', id);
    var headers = {};
    var token = getToken();
    if (token) headers['Authorization'] = 'Bearer ' + token;
    headers['X-CSRF-Token'] = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    fetch('/admin/api/admin_crud.php?action=toggle_active', { method: 'POST', headers: headers, body: fd })
        .then(function(r) { return r.json(); })
        .then(function(d) { if (d.success) { sessionStorage.setItem('adminToast', 'Status toggled successfully'); window.location.reload(); } else showAlertModal(d.error, 'error'); })
        .catch(function(err) { showAlertModal('Network error', 'error'); });
}

var adminEditForm = document.getElementById('adminEditForm');
if (adminEditForm) {
    adminEditForm.addEventListener('submit', handleAdminForm);
}
function handleAdminForm(e) {
    e.preventDefault();
    var form = e.target;
    var btn = form.querySelector('button[type="submit"]');
    var result = document.getElementById('formResult');
    if (!result) return;
    var fd = new FormData(form);
    if (!fd.get('is_active')) fd.set('is_active', '0');
    btn.disabled = true;
    btn.textContent = 'Saving...';
    result.className = 'alert d-none mt-2';
    (async function() {
        try {
            var headers = { 'Authorization': 'Bearer ' + getToken(), 'X-CSRF-Token': (document.querySelector('meta[name="csrf-token"]') || {}).content || '' };
            var r = await fetch('/admin/api/admin_crud.php?action=update', { method: 'POST', headers: headers, credentials: 'same-origin', body: fd });
            if (r.status === 401) {
                var refreshRes = await fetch('/admin/token_refresh.php', { method: 'POST', credentials: 'same-origin' });
                if (refreshRes.ok) {
                    var refreshData = await refreshRes.json();
                    if (refreshData.access_token) {
                        sessionStorage.setItem('admin_access_token', refreshData.access_token);
                        headers['Authorization'] = 'Bearer ' + refreshData.access_token;
                        r = await fetch('/admin/api/admin_crud.php?action=update', { method: 'POST', headers: headers, credentials: 'same-origin', body: fd });
                    }
                }
            }
            if (r.status === 401) { window.location.reload(); return; }
            var d = await r.json();
            result.classList.remove('d-none');
            if (d.success) {
                result.className = 'alert alert-success mt-2';
                result.textContent = d.message;
                setTimeout(function() { window.location.href = 'admins.php?saved=1'; }, 800);
            } else {
                result.className = 'alert alert-danger mt-2';
                result.textContent = d.error || 'Operation failed';
            }
        } catch (err) {
            result.classList.remove('d-none');
            result.className = 'alert alert-danger mt-2';
            result.textContent = err.message || 'Network error';
        }
        btn.disabled = false;
        btn.textContent = 'Save Changes';
    })();
}
function applyAdminBulkAction() {
    var bulkBar = document.querySelector('.bulk-bar:not(.d-none)') || document.querySelector('.bulk-bar');
    if (!bulkBar) return;
    var actionVal = bulkBar.querySelector('select').value;
    if (!actionVal) return;
    var checkedBoxes = document.querySelectorAll('.bulk-checkbox:checked');
    if (checkedBoxes.length === 0) {
        showAlertModal('Please select at least one record.', 'info');
        return;
    }
    var ids = Array.from(checkedBoxes).map(function(cb) { return parseInt(cb.value); });

    function doAction() {
        var fd = new FormData();
        fd.append('page', 'admins');
        ids.forEach(function(id) { fd.append('ids[]', id); });
        var url = '/admin/api/bulk_crud.php';
        var apiAction = '';
        if (actionVal === 'delete') {
            apiAction = 'bulk_delete';
        } else if (actionVal === 'activate') {
            apiAction = 'bulk_toggle_active';
            fd.append('is_active', '1');
        } else if (actionVal === 'deactivate') {
            apiAction = 'bulk_toggle_active';
            fd.append('is_active', '0');
        }
        (async function() {
            showLoading(true);
            try {
                var headers = { 'Authorization': 'Bearer ' + getToken(), 'X-CSRF-Token': (document.querySelector('meta[name="csrf-token"]') || {}).content || '' };
                var r = await fetch(url + '?action=' + apiAction, { method: 'POST', headers: headers, credentials: 'same-origin', body: fd });
                var d = await r.json();
                if (d.success) {
                    showAlertModal(d.message || 'Operation completed.', 'success');
                    window.location.reload();
                } else {
                    showAlertModal(d.error || 'Operation failed.', 'error');
                }
            } catch (err) {
                showAlertModal('Error: ' + err.message, 'error');
            } finally {
                showLoading(false);
            }
        })();
    }

    var confirmMsg = '';
    var confirmBtnText = 'Yes';
    var confirmBtnClass = 'btn-danger';
    if (actionVal === 'delete') {
        confirmMsg = 'Are you sure you want to delete ' + checkedBoxes.length + ' selected admin(s)?';
    } else if (actionVal === 'activate' || actionVal === 'deactivate') {
        var label = actionVal.charAt(0).toUpperCase() + actionVal.slice(1);
        confirmMsg = label + ' ' + checkedBoxes.length + ' selected admin(s)?';
        confirmBtnText = 'Apply';
        confirmBtnClass = 'btn-primary';
    }
    if (confirmMsg) {
        showConfirmDialog(confirmMsg, doAction, confirmBtnText, confirmBtnClass);
    } else {
        doAction();
    }
}
</script>
<?php require_once __DIR__ . '/footer.php'; ?>
