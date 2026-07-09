<?php
require_once __DIR__ . '/layout.php';

function interest_label($val) {
    return $val === 'managed' ? 'Managed Furnished Office' : ($val === 'furnished' ? 'Furnished / Unfurnished Office' : ($val ?: '—'));
}

function office_link($office_id) {
    if (!$office_id) return '—';
    global $conn;
    static $cache = [];
    if (isset($cache[$office_id])) return $cache[$office_id];
    $stmt = mysqli_prepare($conn, "SELECT id, title, 'managed' as tbl FROM managed_offices WHERE id = ? AND status='published' UNION SELECT id, title, 'furnished' as tbl FROM furnished_offices WHERE id = ? AND status='published' UNION SELECT id, title, 'unfurnished' as tbl FROM unfurnished_offices WHERE id = ? AND status='published' LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'iii', $office_id, $office_id, $office_id);
    mysqli_stmt_execute($stmt);
    $r = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    if ($r) {
        $page = $r['tbl'] === 'managed' ? 'managed-office' : 'office-space';
        $link = "<a href=\"{$page}.php?mode=view&id={$r['id']}\">" . htmlspecialchars($r['title']) . " (#{$r['id']})</a>";
        $cache[$office_id] = $link;
    } else {
        $cache[$office_id] = "Office #{$office_id} <span class=\"text-muted\">(unpublished/deleted)</span>";
    }
    return $cache[$office_id];
}

$adminListPage = max(1, (int)($_GET['p'] ?? 1));
$adminPerPage = 50;
$adminOffset = ($adminListPage - 1) * $adminPerPage;
$mode = $_GET['mode'] ?? 'list';
$statusFilter = $_GET['status'] ?? '';
$interestFilter = $_GET['interest'] ?? '';
$searchQuery = trim($_GET['search'] ?? '');

if ($mode === 'view'):
    $id = (int)($_GET['id'] ?? 0);
    $stmt = mysqli_prepare($conn, "SELECT * FROM contacts WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    if (!$row) { echo '<div class="alert alert-warning">Contact not found.</div>'; require_once __DIR__ . '/footer.php'; exit; }
    $hasEmail = !empty($row['email']);
?>
<div class="page-header">
    <h4>Contact #<?= $row['id'] ?></h4>
    <a href="contacts.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i>Back</a>
</div>
<?php if (isset($_GET['saved'])): ?><div class="alert alert-success py-2">Updated successfully.</div><?php endif; ?>
<?php if (isset($_GET['sent'])): ?><div class="alert alert-success py-2">Email sent successfully.</div><?php endif; ?>
<div class="row g-3">
    <div class="col-md-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <form id="contactDetailForm">
                    <input type="hidden" name="id" value="<?= $row['id'] ?>">
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label small text-muted">Name</label><div class="fw-medium"><?= htmlspecialchars($row['name']) ?></div></div>
                        <div class="col-md-4"><label class="form-label small text-muted">Phone</label><div class="fw-medium"><?= htmlspecialchars($row['phone']) ?></div></div>
                        <div class="col-md-4"><label class="form-label small text-muted">Email</label><div class="fw-medium"><?= htmlspecialchars($row['email'] ?: '—') ?></div></div>
                        <div class="col-md-4"><label class="form-label small text-muted">Interest</label><div class="fw-medium"><?= interest_label($row['interest'] ?? '') ?></div></div>
                        <div class="col-md-4"><label class="form-label small text-muted">Company</label><div class="fw-medium"><?= htmlspecialchars($row['company'] ?? '—') ?></div></div>
                        <div class="col-md-4"><label class="form-label small text-muted">Seats</label><div class="fw-medium"><?= htmlspecialchars($row['seats'] ?? '—') ?></div></div>
                        <?php if ($row['message']): ?>
                        <div class="col-12"><label class="form-label small text-muted">Message</label><div class="fw-medium"><?= nl2br(htmlspecialchars($row['message'])) ?></div></div>
                        <?php endif; ?>
                        <div class="col-md-4"><label class="form-label small text-muted">Linked Office</label><div class="fw-medium"><?= office_link($row['office_id']) ?></div></div>
                        <div class="col-md-4"><label class="form-label small text-muted">Listing Code</label><div class="fw-medium"><?= htmlspecialchars($row['listing_code'] ?? '—') ?></div></div>
                        <div class="col-md-4"><label class="form-label small text-muted">Source</label><div class="fw-medium"><?= htmlspecialchars($row['source'] ?? '—') ?></div></div>
                        <div class="col-12"><label class="form-label small text-muted">Submitted</label><div class="fw-medium"><?= $row['created_at'] ?></div></div>
                        <?php if ($row['contacted_at']): ?>
                        <div class="col-md-4"><label class="form-label small text-muted">Contacted At</label><div class="fw-medium"><?= $row['contacted_at'] ?></div></div>
                        <?php endif; ?>
                        <?php if ($row['closed_at']): ?>
                        <div class="col-md-4"><label class="form-label small text-muted">Closed At</label><div class="fw-medium"><?= $row['closed_at'] ?></div></div>
                        <?php endif; ?>
                        <div class="col-md-4"><label class="form-label small text-muted">Submitted IP</label><div class="fw-medium"><code><?= htmlspecialchars($row['submitted_ip'] ?? '—') ?></code></div></div>
                        <div class="col-md-4"><label class="form-label small text-muted">User Agent</label><div class="fw-medium small text-muted" style="word-break:break-all"><?= htmlspecialchars($row['user_agent'] ?? '—') ?></div></div>
                        <div class="col-md-4">
                            <label for="contact_status" class="form-label small text-muted">Status</label>
                            <select name="status" id="contact_status" class="form-select form-select-sm">
                                <option value="new" <?= $row['status']==='new'?'selected':'' ?>>New</option>
                                <option value="contacted" <?= $row['status']==='contacted'?'selected':'' ?>>Contacted</option>
                                <option value="closed" <?= $row['status']==='closed'?'selected':'' ?>>Closed</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="admin_notes" class="form-label small text-muted">Admin Notes</label>
                            <textarea name="admin_notes" id="admin_notes" rows="2" class="form-control form-control-sm" placeholder="Internal notes..."><?= htmlspecialchars($row['admin_notes'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <div class="mt-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
                        <a href="javascript:void(0)" onclick="confirmDeleteContact(<?= $row['id'] ?>)" class="btn btn-outline-danger btn-sm">Delete</a>
                        <a href="contacts.php" class="btn btn-outline-secondary btn-sm">Cancel</a>
                    </div>
                </form>
                <div id="contactFormResult" class="alert d-none mt-2"></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <?php if ($hasEmail): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><i class="fa-solid fa-reply me-1"></i>Send Email Reply</h6>
                <form id="emailReplyForm">
                    <input type="hidden" name="id" value="<?= $row['id'] ?>">
                    <div class="mb-2">
                        <label class="form-label small text-muted">To</label>
                        <div class="fw-medium small"><?= htmlspecialchars($row['name']) ?> &lt;<?= htmlspecialchars($row['email']) ?>&gt;</div>
                    </div>
                    <div class="mb-2">
                        <input type="text" name="subject" class="form-control form-control-sm" placeholder="Subject" required value="Re: CubeSpace Enquiry">
                    </div>
                    <div class="mb-2">
                        <textarea name="body" rows="4" class="form-control form-control-sm" placeholder="Type your reply..." required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fa-solid fa-paper-plane me-1"></i>Send Reply</button>
                </form>
                <div id="emailReplyResult" class="alert d-none mt-2"></div>
            </div>
        </div>
        <?php else: ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-4">
                <i class="fa-solid fa-envelope text-muted mb-2" style="font-size:2rem;"></i>
                <p class="small text-muted mb-0">No email address available for this contact.</p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php else:
    $where = '';
    $params = [];
    $types = '';
    $conditions = [];

    if ($statusFilter && in_array($statusFilter, ['new','contacted','closed'])) {
        $conditions[] = "status = ?";
        $params[] = $statusFilter;
        $types .= 's';
    }
    if ($interestFilter && in_array($interestFilter, ['managed', 'furnished'])) {
        if ($interestFilter === 'managed') {
            $conditions[] = "(interest = 'managed' OR listing_code LIKE 'MO%')";
        } else {
            $conditions[] = "(interest = 'furnished' OR listing_code LIKE 'FO%')";
        }
    }
    if ($searchQuery) {
        $conditions[] = "(name LIKE ? OR phone LIKE ? OR email LIKE ? OR company LIKE ?)";
        $sp = "%$searchQuery%";
        $params[] = $sp; $params[] = $sp; $params[] = $sp; $params[] = $sp;
        $types .= 'ssss';
    }
    if (!empty($conditions)) {
        $where = " WHERE " . implode(' AND ', $conditions);
    }

    $totalStmt = !empty($params)
        ? mysqli_prepare($conn, "SELECT COUNT(*) as cnt FROM contacts$where")
        : null;
    if ($totalStmt) {
        mysqli_stmt_bind_param($totalStmt, $types, ...$params);
        mysqli_stmt_execute($totalStmt);
        $total = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($totalStmt))['cnt'];
        mysqli_stmt_close($totalStmt);
    } else {
        $total = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM contacts"))['cnt'];
    }
    $orderSql = " ORDER BY created_at DESC LIMIT $adminPerPage OFFSET $adminOffset";
    if (!empty($params)) {
        $stmt = mysqli_prepare($conn, "SELECT * FROM contacts$where$orderSql");
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
    } else {
        $result = mysqli_query($conn, "SELECT * FROM contacts$orderSql");
    }

    $exportUrl = 'api/contact_crud.php?action=export';
    if ($statusFilter) $exportUrl .= '&status=' . urlencode($statusFilter);
    if ($interestFilter) $exportUrl .= '&interest=' . urlencode($interestFilter);
    if ($searchQuery) $exportUrl .= '&search=' . urlencode($searchQuery);
?>
<div class="page-header">
    <h4>Contact Submissions</h4>
    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-primary"><?= $total ?> total</span>
        <a href="<?= $exportUrl ?>" class="btn btn-outline-success btn-sm" title="Export CSV"><i class="fa-solid fa-download"></i> CSV</a>
    </div>
</div>
<div class="row g-2 mb-3">
    <div class="col-md-5">
        <form method="get" class="d-flex gap-2">
            <input type="search" name="search" class="form-control form-control-sm" placeholder="Search by name, phone, email, company..." value="<?= htmlspecialchars($searchQuery) ?>">
            <button type="submit" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-search"></i></button>
            <?php if ($searchQuery): ?>
            <a href="contacts.php<?= $statusFilter ? '?status=' . urlencode($statusFilter) : '' ?>" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-times"></i></a>
            <?php endif; ?>
        </form>
    </div>
    <div class="col-md-7">
        <div class="d-flex gap-2 flex-wrap align-items-center">
            <span class="small text-muted">Filter:</span>
            <a href="contacts.php<?= $searchQuery ? '?search=' . urlencode($searchQuery) : '' ?>" class="btn btn-sm <?= !$statusFilter && !$interestFilter ? 'btn-primary' : 'btn-outline-primary' ?>">All</a>
            <a href="contacts.php?status=new<?= $interestFilter ? '&interest=' . urlencode($interestFilter) : '' ?><?= $searchQuery ? '&search=' . urlencode($searchQuery) : '' ?>" class="btn btn-sm <?= $statusFilter === 'new' ? 'btn-primary' : 'btn-outline-primary' ?>">New</a>
            <a href="contacts.php?status=contacted<?= $interestFilter ? '&interest=' . urlencode($interestFilter) : '' ?><?= $searchQuery ? '&search=' . urlencode($searchQuery) : '' ?>" class="btn btn-sm <?= $statusFilter === 'contacted' ? 'btn-primary' : 'btn-outline-primary' ?>">Contacted</a>
            <a href="contacts.php?status=closed<?= $interestFilter ? '&interest=' . urlencode($interestFilter) : '' ?><?= $searchQuery ? '&search=' . urlencode($searchQuery) : '' ?>" class="btn btn-sm <?= $statusFilter === 'closed' ? 'btn-primary' : 'btn-outline-primary' ?>">Closed</a>
            <span class="small text-muted ms-2">Type:</span>
            <a href="contacts.php<?= $statusFilter ? '?status=' . urlencode($statusFilter) : '' ?><?= $searchQuery ? ($statusFilter ? '&' : '?') . 'search=' . urlencode($searchQuery) : '' ?>" class="btn btn-sm <?= !$interestFilter ? 'btn-primary' : 'btn-outline-primary' ?>">All</a>
            <a href="contacts.php?interest=managed<?= $statusFilter ? '&status=' . urlencode($statusFilter) : '' ?><?= $searchQuery ? '&search=' . urlencode($searchQuery) : '' ?>" class="btn btn-sm <?= $interestFilter === 'managed' ? 'btn-primary' : 'btn-outline-primary' ?>">Managed</a>
            <a href="contacts.php?interest=furnished<?= $statusFilter ? '&status=' . urlencode($statusFilter) : '' ?><?= $searchQuery ? '&search=' . urlencode($searchQuery) : '' ?>" class="btn btn-sm <?= $interestFilter === 'furnished' ? 'btn-primary' : 'btn-outline-primary' ?>">Furnished</a>
        </div>
    </div>
</div>
<div class="bulk-bar">
    <select id="bulkActionSelect" class="form-select form-select-sm" aria-label="Bulk actions">
        <option value="">-- Bulk Actions --</option>
        <option value="delete">Delete Selected</option>
        <option value="status-new">Mark as New</option>
        <option value="status-contacted">Mark as Contacted</option>
        <option value="status-closed">Mark as Closed</option>
    </select>
    <button class="btn btn-sm btn-secondary" onclick="applyBulkAction()">Apply</button>
</div>
<?php if (isset($_GET['deleted'])): ?><div class="alert alert-success py-2">Deleted successfully.</div><?php endif; ?>
<div class="admin-card">
    <div class="table-wrap">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead>
                    <tr>
                        <th scope="col"><input type="checkbox" class="form-check-input checkAll" onchange="toggleAllCheckboxes(this)"></th>
                        <th scope="col">ID</th>
                        <th scope="col">Code</th>
                        <th scope="col">Name</th>
                        <th scope="col">Phone</th>
                        <th scope="col">Email</th>
                        <th scope="col">Interest</th>
                        <th scope="col">Source</th>
                        <th scope="col">Status</th>
                        <th scope="col">Actions</th>
                        <th scope="col">Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td><input type="checkbox" class="form-check-input bulk-checkbox" value="<?= $row['id'] ?>"></td>
                        <td class="text-muted"><?= $row['id'] ?></td>
                        <td><code class="small"><?= htmlspecialchars($row['listing_code'] ?? '—') ?></code></td>
                        <td class="fw-medium"><?= htmlspecialchars($row['name']) ?></td>
                        <td><?= htmlspecialchars($row['phone']) ?></td>
                        <td><?= htmlspecialchars($row['email'] ?: '—') ?></td>
                        <td><?= interest_label($row['interest'] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['source'] ?? '—') ?></td>
                        <td><span class="badge bg-<?= $row['status'] === 'new' ? 'danger' : ($row['status'] === 'contacted' ? 'warning text-dark' : 'success') ?>"><?= $row['status'] ?></span></td>
                        <td>
                            <a href="contacts.php?mode=view&id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-secondary" title="View"><i class="fa-solid fa-eye"></i></a>
                            <a href="javascript:void(0)" onclick="confirmDeleteContact(<?= $row['id'] ?>)" class="btn btn-sm btn-outline-danger" title="Delete"><i class="fa-solid fa-trash-can"></i></a>
                        </td>
                        <td class="text-muted"><?= date('d M Y', strtotime($row['created_at'])) ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php if ($total > $adminPerPage): ?>
<?php
$pagUrl = 'contacts.php?';
$pagParams = [];
if ($statusFilter) $pagParams[] = 'status=' . urlencode($statusFilter);
if ($interestFilter) $pagParams[] = 'interest=' . urlencode($interestFilter);
if ($searchQuery) $pagParams[] = 'search=' . urlencode($searchQuery);
$pagUrl .= implode('&', $pagParams);
?>
<div class="mt-3"><?php render_admin_pagination($total, $adminListPage, $adminPerPage, $pagUrl); ?></div>
<?php endif; ?>
<?php endif; ?>

<script>
<?php if ($mode === 'view'): ?>
document.getElementById('emailReplyForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = this.querySelector('button[type="submit"]');
    const result = document.getElementById('emailReplyResult');
    if (!result) return;
    const fd = new FormData(this);
    btn.disabled = true; btn.textContent = 'Sending...';
    result.className = 'alert d-none mt-2';
    try {
        let headers = {
            'Authorization': 'Bearer ' + getToken(),
            'X-CSRF-Token': (document.querySelector('meta[name="csrf-token"]') || {}).content || ''
        };
        let r = await fetch('/admin/api/contact_crud.php?action=reply', { method: 'POST', headers, body: fd });
        const d = await r.json();
        result.classList.remove('d-none');
        if (d.success) {
            result.className = d.warning ? 'alert alert-warning mt-2' : 'alert alert-success mt-2';
            result.textContent = d.message;
            if (!d.warning) setTimeout(() => window.location.href = 'contacts.php?mode=view&id=<?= $id ?>&sent=1', 1000);
        } else {
            result.className = 'alert alert-danger mt-2';
            result.textContent = d.error || 'Failed to send';
        }
    } catch (err) {
        result.classList.remove('d-none');
        result.className = 'alert alert-danger mt-2';
        result.textContent = err.message || 'Network error';
    }
    btn.disabled = false; btn.textContent = 'Send Reply';
});
<?php endif; ?>
</script>
<?php require_once __DIR__ . '/footer.php'; ?>
