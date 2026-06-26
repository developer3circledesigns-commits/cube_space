<?php
require_once __DIR__ . '/layout.php';

$adminListPage = max(1, (int)($_GET['p'] ?? 1));
$adminPerPage = 50;
$adminOffset = ($adminListPage - 1) * $adminPerPage;

$activityTotal = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM activity_log"))['cnt'];
$result = mysqli_query($conn, "SELECT * FROM activity_log ORDER BY created_at DESC LIMIT $adminPerPage OFFSET $adminOffset");
?>
<div class="page-header">
    <h4>Activity Log</h4>
    <span class="badge bg-primary"><?= $activityTotal ?> entries</span>
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
                    <?php while ($row = mysqli_fetch_assoc($result)):
                        $details = json_decode($row['details'] ?? '{}', true);
                    ?>
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
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php if ($activityTotal > $adminPerPage): ?><div class="mt-3"><?php render_admin_pagination($activityTotal, $adminListPage, $adminPerPage, 'activity.php'); ?></div><?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>
