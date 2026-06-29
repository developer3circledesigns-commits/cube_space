<?php
require_once __DIR__ . '/layout.php';

$detailTab = $_GET['tab'] ?? 'leasing';
$selectedOffice = (int)($_GET['office_id'] ?? 0);
$allOffices = mysqli_query($conn, "SELECT id, title, slug FROM managed_offices WHERE status='published' ORDER BY title");
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold mb-0">Office Details Management</h4>
</div>
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <label for="detailOfficeSelect" class="form-label small fw-semibold">Select Office</label>
        <select id="detailOfficeSelect" class="form-select form-select-sm" onchange="window.location='office-details.php?office_id='+this.value+'&tab=<?= $detailTab ?>'">
            <option value="0">— Select an office —</option>
            <?php while ($o = mysqli_fetch_assoc($allOffices)): ?>
            <option value="<?= $o['id'] ?>" <?= $selectedOffice==$o['id']?'selected':'' ?>><?= htmlspecialchars($o['title']) ?> (ID: <?= $o['id'] ?>)</option>
            <?php endwhile; ?>
        </select>
    </div>
</div>
<?php if ($selectedOffice): ?>
<div class="d-flex flex-wrap gap-1 mb-3">
    <a href="office-details.php?office_id=<?= $selectedOffice ?>&tab=leasing" class="btn btn-sm <?= $detailTab==='leasing'?'btn-primary':'btn-outline-primary' ?>">Leasing</a>
    <a href="office-details.php?office_id=<?= $selectedOffice ?>&tab=extras" class="btn btn-sm <?= $detailTab==='extras'?'btn-primary':'btn-outline-primary' ?>">Extras</a>
</div>
<?php if ($detailTab === 'leasing'): ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h6 class="fw-bold mb-0">Leasing Options</h6>
    <button class="btn btn-primary btn-sm" onclick="showLeasingForm()"><i class="fa-solid fa-plus me-1"></i>Add Option</button>
</div>
<div id="leasingFormWrap" class="card border-0 shadow-sm mb-3 d-none">
    <div class="card-body">
        <h6 class="fw-bold mb-3">Add / Edit Leasing Option</h6>
        <form id="leasingForm" onsubmit="saveLeasing(event)">
            <input type="hidden" name="id" id="leasingId" value="">
            <div class="row g-2">
                <div class="col-md-6">
                    <label for="leasingTitle" class="form-label small">Title *</label>
                    <input type="text" name="option_title" class="form-control form-control-sm" id="leasingTitle" required>
                </div>
                <div class="col-md-3">
                    <label for="leasingPrice" class="form-label small">Price</label>
                    <input type="text" name="option_price" class="form-control form-control-sm" id="leasingPrice">
                </div>
                <div class="col-md-3">
                    <label for="leasingSort" class="form-label small">Sort Order</label>
                    <input type="number" name="sort_order" class="form-control form-control-sm" id="leasingSort" value="0">
                </div>
                <div class="col-12">
                    <label for="leasingDesc" class="form-label small">Description</label>
                    <textarea name="option_desc" class="form-control form-control-sm" id="leasingDesc" rows="2"></textarea>
                </div>
                <div class="col-md-6">
                    <label for="leasingActive" class="form-label small">Active</label>
                    <select name="is_active" class="form-select form-select-sm" id="leasingActive">
                        <option value="1">Yes</option><option value="0">No</option>
                    </select>
                </div>
            </div>
            <div class="mt-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm">Save</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="hideLeasingForm()">Cancel</button>
            </div>
        </form>
    </div>
</div>
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-dark">
                    <tr><th scope="col">ID</th><th scope="col">Title</th><th scope="col">Price</th><th scope="col">Order</th><th scope="col">Active</th><th scope="col">Actions</th></tr>
                </thead>
                <tbody id="leasingTableBody"></tbody>
            </table>
        </div>
    </div>
</div>
<?php elseif ($detailTab === 'extras'): ?>
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <h6 class="fw-bold mb-3">Feature Highlights & SEO</h6>
        <form id="extrasForm" onsubmit="saveExtras(event)">
            <div class="mb-2">
                <label for="extrasHighlights" class="form-label small">Feature Highlights (one per line)</label>
                <textarea name="feature_highlights" class="form-control form-control-sm" id="extrasHighlights" rows="4" placeholder="Fully Furnished\u000a24/7 Power Backup"></textarea>
            </div>
            <div class="mb-2">
                <label for="extrasSeo" class="form-label small">SEO Text (HTML allowed)</label>
                <textarea name="seo_text" class="form-control form-control-sm" id="extrasSeo" rows="6" placeholder="<h3>About this Workspace</h3>"></textarea>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Save Extras</button>
        </form>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>
<script>
const DETAIL_API = '/admin/api/detail_crud.php';
const OFFICE_ID = <?= $selectedOffice ?>;
async function apiPost(action, data) {
    showLoading(true);
    try {
        const fd = new FormData();
        fd.append('office_id', OFFICE_ID);
        for (const [k,v] of Object.entries(data)) fd.append(k, v);
        const headers = { 'Authorization': 'Bearer ' + getToken() };
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        if (csrfMeta) headers['X-CSRF-Token'] = csrfMeta.content;
        const r = await fetch(DETAIL_API + '?action=' + action, { method: 'POST', headers: headers, body: fd });
        const result = await r.json();
        return result;
    } catch (err) {
        if (window.CubeToast) {
            CubeToast.error(err.message || 'Network error');
        } else {
            showAlertModal(err.message || 'Network error', 'error');
        }
        return { success: false, error: err.message };
    } finally {
        showLoading(false);
    }
}
async function apiGet(action) {
    showLoading(true);
    try {
        const headers = { 'Authorization': 'Bearer ' + getToken() };
        const r = await fetch(DETAIL_API + '?action=' + action + '&office_id=' + OFFICE_ID, { headers: headers });
        return await r.json();
    } catch (err) {
        if (window.CubeToast) {
            CubeToast.error(err.message || 'Network error');
        } else {
            showAlertModal(err.message || 'Network error', 'error');
        }
        return { error: err.message };
    } finally {
        showLoading(false);
    }
}
function esc(s) { return document.createElement('div').appendChild(document.createTextNode(s||'')).parentNode.innerHTML; }
<?php if ($detailTab === 'leasing'): ?>
async function loadLeasing() {
    const d = await apiGet('list_leasing');
    const tb = document.getElementById('leasingTableBody');
    if (!d.leasing || !d.leasing.length) { tb.innerHTML = '<tr><td colspan="6" class="text-center py-4"><i class="fa-solid fa-inbox text-muted mb-2 d-block" style="font-size: 1.5rem;"></i><span class="text-muted fw-medium">No data found</span></td></tr>'; return; }
    tb.innerHTML = d.leasing.map(l => '<tr><td class="text-muted">'+l.id+'</td><td>'+esc(l.option_title)+'</td><td>'+esc(l.option_price||'—')+'</td><td>'+l.sort_order+'</td><td>'+(l.is_active?'<span class="badge bg-success">Yes</span>':'<span class="badge bg-secondary">No</span>')+'</td><td><a href="javascript:void(0)" onclick="editLeasing('+l.id+');return false;" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-pen-to-square"></i></a> <a href="javascript:void(0)" onclick="deleteLeasing('+l.id+');return false;" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash-can"></i></a></td></tr>').join('');
}
function showLeasingForm() { document.getElementById('leasingFormWrap').classList.remove('d-none'); document.getElementById('leasingId').value=''; document.getElementById('leasingTitle').value=''; document.getElementById('leasingPrice').value=''; document.getElementById('leasingDesc').value=''; document.getElementById('leasingSort').value='0'; document.getElementById('leasingActive').value='1'; }
function hideLeasingForm() { document.getElementById('leasingFormWrap').classList.add('d-none'); }
function editLeasing(id) { apiGet('list_leasing').then(d => { const l = d.leasing.find(x=>x.id==id); if(l){document.getElementById('leasingFormWrap').classList.remove('d-none');document.getElementById('leasingId').value=l.id;document.getElementById('leasingTitle').value=l.option_title;document.getElementById('leasingPrice').value=l.option_price||'';document.getElementById('leasingDesc').value=l.option_desc||'';document.getElementById('leasingSort').value=l.sort_order;document.getElementById('leasingActive').value=l.is_active;} }); }
async function saveLeasing(e) { e.preventDefault(); const fd=new FormData(document.getElementById('leasingForm')); const data={}; fd.forEach((v,k)=>data[k]=v); const action=data.id?'update_leasing':'create_leasing'; const d=await apiPost(action,data); if(d.success){loadLeasing();hideLeasingForm();}else{showAlertModal(d.error||'Error','error');} }
var _deletingLeasing = false;
async function deleteLeasing(id) { if (_deletingLeasing) return; showConfirmDialog('Delete this option?', async function(){ _deletingLeasing = true; try { const d=await apiPost('delete_leasing',{id:id}); if(d.success)loadLeasing(); } finally { _deletingLeasing = false; } }); }
loadLeasing();
<?php elseif ($detailTab === 'extras'): ?>
(async function() {
    const d = await apiGet('get_extras');
    if (d.extras) {
        document.getElementById('extrasHighlights').value = (Array.isArray(d.extras.feature_highlights) ? d.extras.feature_highlights.join('\n') : '');
        document.getElementById('extrasSeo').value = d.extras.seo_text || '';
    }
})();
async function saveExtras(e) {
    e.preventDefault();
    const highlights = document.getElementById('extrasHighlights').value.split('\n').map(s=>s.trim()).filter(Boolean);
    const seoText = document.getElementById('extrasSeo').value;
    const data = { feature_highlights: JSON.stringify(highlights), seo_text: seoText };
    const d = await apiPost('update_extras', data);
    if (d.success) { showAlertModal('Saved successfully!', 'success'); } else { showAlertModal(d.error || 'Error', 'error'); }
}
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
