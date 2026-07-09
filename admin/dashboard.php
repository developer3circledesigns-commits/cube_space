<?php
require_once __DIR__ . '/layout.php';

$stats = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT
        (SELECT COUNT(*) FROM contacts) as total_contacts,
        (SELECT COUNT(*) FROM admins) as total_admins,
        (SELECT COUNT(*) FROM managed_offices) as total_managed,
        (SELECT COUNT(*) FROM furnished_offices) + (SELECT COUNT(*) FROM unfurnished_offices) as total_office,
        (SELECT COUNT(*) FROM activity_log) as total_activity,
        (SELECT COUNT(DISTINCT ip_address) FROM visitors_log) as total_visitors"
));
$totalContacts = (int)($stats['total_contacts'] ?? 0);
$totalAdmins   = (int)($stats['total_admins'] ?? 0);
$totalManaged  = (int)($stats['total_managed'] ?? 0);
$totalOffice   = (int)($stats['total_office'] ?? 0);
$totalActivity = (int)($stats['total_activity'] ?? 0);
$totalVisitors = (int)($stats['total_visitors'] ?? 0);
$recentContacts = mysqli_query($conn, "SELECT id, name, phone, email, interest, status, created_at FROM contacts ORDER BY created_at DESC LIMIT 5");
$recentVisitors = mysqli_query($conn, "SELECT ip_address, page_url, activity, is_vpn, vpn_detected_method, country, city, created_at FROM visitors_log ORDER BY created_at DESC LIMIT 8");
?>

<div class="page-header">
    <h4>Dashboard</h4>
    <small class="text-muted">Welcome back, <?= htmlspecialchars($adminUser) ?></small>
</div>
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-lg col-xl">
        <div class="stat-card card p-3 text-center">
            <div class="stat-icon text-primary"><i class="fa-solid fa-message"></i></div>
            <div class="stat-value"><?= $totalContacts ?></div>
            <div class="stat-label">Contacts</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg col-xl">
        <div class="stat-card card p-3 text-center">
            <div class="stat-icon text-info"><i class="fa-solid fa-briefcase"></i></div>
            <div class="stat-value"><?= $totalManaged ?></div>
            <div class="stat-label">Managed</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg col-xl">
        <div class="stat-card card p-3 text-center">
            <div class="stat-icon text-warning"><i class="fa-solid fa-building"></i></div>
            <div class="stat-value"><?= $totalOffice ?></div>
            <div class="stat-label">Office Spaces</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg col-xl">
        <div class="stat-card card p-3 text-center">
            <div class="stat-icon text-secondary"><i class="fa-solid fa-clock-rotate-left"></i></div>
            <div class="stat-value"><?= $totalActivity ?></div>
            <div class="stat-label">Activity</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg col-xl">
        <div class="stat-card card p-3 text-center">
            <div class="stat-icon text-success"><i class="fa-solid fa-eye"></i></div>
            <div class="stat-value"><?= $totalVisitors ?></div>
            <div class="stat-label">Total Visitors</div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg col-xl">
        <div class="stat-card card p-3 text-center">
            <div class="stat-icon text-danger"><i class="fa-solid fa-user-shield"></i></div>
            <div class="stat-value"><?= $totalAdmins ?></div>
            <div class="stat-label">Admins</div>
        </div>
    </div>
</div>
<div class="container-fluid px-0">
    <div class="row g-3">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center py-3">
                    <h6 class="fw-bold mb-0">Recent Contacts</h6>
                    <a href="contacts.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr><th scope="col">Name</th><th scope="col">Phone</th><th scope="col">Interest</th><th scope="col">Status</th><th scope="col">Date</th></tr>
                        </thead>
                        <tbody>
                            <?php if ($recentContacts): while ($c = mysqli_fetch_assoc($recentContacts)): ?>
                            <tr>
                                <td class="fw-medium"><?= htmlspecialchars($c['name']) ?></td>
                                <td><?= htmlspecialchars($c['phone']) ?></td>
                                <td><?= htmlspecialchars($c['interest'] ?? '—') ?></td>
                                <td><span class="badge bg-<?= $c['status'] === 'new' ? 'danger' : ($c['status'] === 'contacted' ? 'warning text-dark' : 'success') ?>"><?= $c['status'] ?></span></td>
                                <td class="text-muted"><?= date('d M', strtotime($c['created_at'])) ?></td>
                            </tr>
                            <?php endwhile; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center py-3">
                    <h6 class="fw-bold mb-0"><i class="fa-solid fa-eye me-2 text-success"></i>Visitors Logs</h6>
                    <span class="badge bg-success"><i class="fa-solid fa-eye me-1"></i>Recent</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr><th scope="col">IP Address</th><th scope="col">Activity</th><th scope="col">VPN</th><th scope="col">Date / Time</th></tr>
                        </thead>
                        <tbody>
                            <?php if ($recentVisitors): while ($v = mysqli_fetch_assoc($recentVisitors)): ?>
                            <tr>
                                <td class="font-monospace small"><?= htmlspecialchars($v['ip_address']) ?></td>
                                <td><?= htmlspecialchars($v['activity'] ?? '—') ?></td>
                                <td>
                                    <?php if ($v['is_vpn']): ?>
                                        <span class="badge bg-warning text-dark" title="<?= htmlspecialchars($v['vpn_detected_method'] ?? '') ?>">
                                            <i class="fa-solid fa-shield-halved me-1"></i>VPN
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><i class="fa-solid fa-globe me-1"></i>Direct</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted small"><?= date('d M Y h:i A', strtotime($v['created_at'])) ?></td>
                            </tr>
                            <?php endwhile; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
