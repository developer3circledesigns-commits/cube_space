<?php
require_once __DIR__ . '/layout.php';

$stats = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT
        (SELECT COUNT(*) FROM contacts) as total_contacts,
        (SELECT COUNT(*) FROM admins) as total_admins,
        (SELECT COUNT(*) FROM managed_offices) as total_managed,
        (SELECT COUNT(*) FROM furnished_offices) + (SELECT COUNT(*) FROM unfurnished_offices) as total_office,
        (SELECT COUNT(*) FROM activity_log) as total_activity"
));
$totalContacts = (int)($stats['total_contacts'] ?? 0);
$totalAdmins   = (int)($stats['total_admins'] ?? 0);
$totalManaged  = (int)($stats['total_managed'] ?? 0);
$totalOffice   = (int)($stats['total_office'] ?? 0);
$totalActivity = (int)($stats['total_activity'] ?? 0);
$recentContacts = mysqli_query($conn, "SELECT id, name, phone, email, interest, status, created_at FROM contacts ORDER BY created_at DESC LIMIT 5");
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
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
