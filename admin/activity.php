<?php
// Handle CSV export BEFORE layout sends HTML headers
if (!empty($_GET['export'])) {
    require_once __DIR__ . '/init.php';
    admin_load_db();
    require_once __DIR__ . '/jwt_helper.php';

    $token = $_COOKIE['access_token'] ?? '';
    $payload = $token ? jwt_decode($token) : null;
    if (!$payload || ($payload['type'] ?? '') !== 'access') {
        http_response_code(401);
        die(json_encode(['error' => 'Unauthorized']));
    }

    $filterAction = trim($_GET['action'] ?? '');
    $filterTable = trim($_GET['table'] ?? '');
    $filterAdmin = trim($_GET['admin'] ?? '');
    $search = trim($_GET['search'] ?? '');

    $conditions = [];
    $params = [];
    $types = '';
    if ($filterAction) { $conditions[] = "action = ?"; $params[] = $filterAction; $types .= 's'; }
    if ($filterTable) { $conditions[] = "table_name = ?"; $params[] = $filterTable; $types .= 's'; }
    if ($filterAdmin) { $conditions[] = "admin_username = ?"; $params[] = $filterAdmin; $types .= 's'; }
    if ($search) {
        $conditions[] = "(admin_username LIKE ? OR table_name LIKE ? OR action LIKE ? OR ip_address LIKE ? OR details LIKE ?)";
        $sp = "%$search%";
        $params[] = $sp; $params[] = $sp; $params[] = $sp; $params[] = $sp; $params[] = $sp;
        $types .= 'sssss';
    }
    $whereClause = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="activity_log_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, "\xEF\xBB\xBF");
    fputcsv($out, ['ID', 'Admin', 'Action', 'Table', 'Record ID', 'IP Address', 'Details', 'Created At']);
    $sql = "SELECT * FROM activity_log$whereClause ORDER BY created_at DESC";
    if (!empty($params)) {
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
        } else { $result = false; }
    } else {
        $result = mysqli_query($conn, $sql);
    }
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            fputcsv($out, [
                $row['id'], $row['admin_username'], $row['action'],
                $row['table_name'] ?? '', $row['record_id'] ?? '',
                $row['ip_address'] ?? '', $row['details'] ?? '', $row['created_at']
            ]);
        }
    }
    fclose($out);
    exit;
}

require_once __DIR__ . '/layout.php';

$adminListPage = max(1, (int)($_GET['p'] ?? 1));
$adminPerPage = 50;
$adminOffset = ($adminListPage - 1) * $adminPerPage;

$filterAction = trim($_GET['action'] ?? '');
$filterTable = trim($_GET['table'] ?? '');
$filterAdmin = trim($_GET['admin'] ?? '');
$search = trim($_GET['search'] ?? '');

$conditions = [];
$params = [];
$types = '';

if ($filterAction) {
    $conditions[] = "action = ?";
    $params[] = $filterAction;
    $types .= 's';
}
if ($filterTable) {
    $conditions[] = "table_name = ?";
    $params[] = $filterTable;
    $types .= 's';
}
if ($filterAdmin) {
    $conditions[] = "admin_username = ?";
    $params[] = $filterAdmin;
    $types .= 's';
}
if ($search) {
    $conditions[] = "(admin_username LIKE ? OR table_name LIKE ? OR action LIKE ? OR ip_address LIKE ? OR details LIKE ?)";
    $sp = "%$search%";
    $params[] = $sp; $params[] = $sp; $params[] = $sp; $params[] = $sp; $params[] = $sp;
    $types .= 'sssss';
}

$whereClause = !empty($conditions) ? ' WHERE ' . implode(' AND ', $conditions) : '';

// Collect distinct values for filter dropdowns
$distinctActions = mysqli_query($conn, "SELECT DISTINCT action FROM activity_log WHERE action IS NOT NULL ORDER BY action");
$distinctTables = mysqli_query($conn, "SELECT DISTINCT table_name FROM activity_log WHERE table_name IS NOT NULL ORDER BY table_name");
$distinctAdmins = mysqli_query($conn, "SELECT DISTINCT admin_username FROM activity_log WHERE admin_username IS NOT NULL ORDER BY admin_username");

// Count with filters
$countSql = "SELECT COUNT(*) as cnt FROM activity_log$whereClause";
if (!empty($params)) {
    $countStmt = mysqli_prepare($conn, $countSql);
    mysqli_stmt_bind_param($countStmt, $types, ...$params);
    mysqli_stmt_execute($countStmt);
    $activityTotal = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($countStmt))['cnt'];
    mysqli_stmt_close($countStmt);
} else {
    $activityTotal = (int)mysqli_fetch_assoc(mysqli_query($conn, $countSql))['cnt'];
}

// Data with filters + pagination
$dataSql = "SELECT * FROM activity_log$whereClause ORDER BY created_at DESC LIMIT $adminPerPage OFFSET $adminOffset";
if (!empty($params)) {
    $dataStmt = mysqli_prepare($conn, $dataSql);
    if ($dataStmt) {
        mysqli_stmt_bind_param($dataStmt, $types, ...$params);
        mysqli_stmt_execute($dataStmt);
        $result = mysqli_stmt_get_result($dataStmt);
    } else {
        $result = false;
    }
} else {
    $result = mysqli_query($conn, $dataSql);
}

// Build pagination URL preserving filters
$pagParams = [];
foreach (['action', 'table', 'admin', 'search'] as $k) {
    $v = $_GET[$k] ?? '';
    if ($v) $pagParams[] = urlencode($k) . '=' . urlencode($v);
}
$pagUrl = 'activity.php?' . implode('&', $pagParams);

// Export URL preserves filters
$exportUrl = 'activity.php?export=1';
foreach (['action', 'table', 'admin', 'search'] as $k) {
    $v = $_GET[$k] ?? '';
    if ($v) $exportUrl .= '&' . urlencode($k) . '=' . urlencode($v);
}
?>
<div class="page-header">
    <h4>Activity Log</h4>
    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-primary"><?= $activityTotal ?> entries</span>
        <a href="<?= $exportUrl ?>" class="btn btn-outline-success btn-sm" title="Export CSV"><i class="fa-solid fa-download"></i> CSV</a>
    </div>
</div>
<div class="row g-2 mb-3">
    <div class="col-md-12">
        <form method="get" class="d-flex gap-2 flex-wrap align-items-center">
            <input type="search" name="search" class="form-control form-control-sm" style="width:150px" placeholder="Search..." value="<?= htmlspecialchars($search) ?>">
            <select name="action" class="form-select form-select-sm" style="width:110px;" onchange="this.form.submit()">
                <option value="">Action</option>
                <?php if ($distinctActions && mysqli_num_rows($distinctActions)): mysqli_data_seek($distinctActions, 0); while ($a = mysqli_fetch_assoc($distinctActions)): ?>
                <option value="<?= htmlspecialchars($a['action']) ?>" <?= $filterAction === $a['action'] ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst($a['action'])) ?></option>
                <?php endwhile; endif; ?>
            </select>
            <select name="table" class="form-select form-select-sm" style="width:140px;" onchange="this.form.submit()">
                <option value="">Table</option>
                <?php if ($distinctTables && mysqli_num_rows($distinctTables)): mysqli_data_seek($distinctTables, 0); while ($t = mysqli_fetch_assoc($distinctTables)): ?>
                <option value="<?= htmlspecialchars($t['table_name']) ?>" <?= $filterTable === $t['table_name'] ? 'selected' : '' ?>><?= htmlspecialchars(str_replace('_', ' ', ucfirst($t['table_name']))) ?></option>
                <?php endwhile; endif; ?>
            </select>
            <select name="admin" class="form-select form-select-sm" style="width:120px;" onchange="this.form.submit()">
                <option value="">Admin</option>
                <?php if ($distinctAdmins && mysqli_num_rows($distinctAdmins)): mysqli_data_seek($distinctAdmins, 0); while ($u = mysqli_fetch_assoc($distinctAdmins)): ?>
                <option value="<?= htmlspecialchars($u['admin_username']) ?>" <?= $filterAdmin === $u['admin_username'] ? 'selected' : '' ?>><?= htmlspecialchars($u['admin_username']) ?></option>
                <?php endwhile; endif; ?>
            </select>
            <button type="submit" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-search"></i></button>
            <?php if ($filterAction || $filterTable || $filterAdmin || $search): ?>
            <a href="activity.php" class="btn btn-sm btn-outline-secondary">&times;</a>
            <?php endif; ?>
        </form>
    </div>
</div>
<div class="admin-card">
    <div class="table-wrap">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead>
                    <tr>
                        <th scope="col">ID</th><th scope="col">Admin</th><th scope="col">Action</th><th scope="col">Table</th><th scope="col">Record</th><th scope="col">IP</th><th scope="col">Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && mysqli_num_rows($result) > 0): ?>
                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td class="text-muted"><?= $row['id'] ?></td>
                        <td class="fw-medium"><?= htmlspecialchars($row['admin_username']) ?></td>
                        <td><span class="badge bg-<?= $row['action'] === 'delete' ? 'danger' : ($row['action'] === 'create' ? 'success' : 'info') ?>"><?= htmlspecialchars($row['action']) ?></span></td>
                        <td><?= htmlspecialchars($row['table_name'] ?? '—') ?></td>
                        <td><?= $row['record_id'] ?? '—' ?></td>
                        <td class="text-muted"><?= htmlspecialchars($row['ip_address'] ?? '—') ?></td>
                        <td class="text-muted"><?= date('d M Y H:i', strtotime($row['created_at'])) ?></td>
                    </tr>
                    <?php endwhile; ?>
                    <?php else: ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No activity found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php if ($activityTotal > $adminPerPage): ?><div class="mt-3"><?php render_admin_pagination($activityTotal, $adminListPage, $adminPerPage, $pagUrl); ?></div><?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>
