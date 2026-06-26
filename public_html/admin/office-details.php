<?php
require_once __DIR__ . '/layout.php';

$detailTab = $_GET['tab'] ?? 'reviews';
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
    <a href="office-details.php?office_id=<?= $selectedOffice ?>&tab=reviews" class="btn btn-sm <?= $detailTab==='reviews'?'btn-primary':'btn-outline-primary' ?>">Reviews</a>
    <a href="office-details.php?office_id=<?= $selectedOffice ?>&tab=faq" class="btn btn-sm <?= $detailTab==='faq'?'btn-primary':'btn-outline-primary' ?>">FAQ</a>
    <a href="office-details.php?office_id=<?= $selectedOffice ?>&tab=building" class="btn btn-sm <?= $detailTab==='building'?'btn-primary':'btn-outline-primary' ?>">Building</a>
    <a href="office-details.php?office_id=<?= $selectedOffice ?>&tab=leasing" class="btn btn-sm <?= $detailTab==='leasing'?'btn-primary':'btn-outline-primary' ?>">Leasing</a>
    <a href="office-details.php?office_id=<?= $selectedOffice ?>&tab=extras" class="btn btn-sm <?= $detailTab==='extras'?'btn-primary':'btn-outline-primary' ?>">Extras</a>
    <a href="office-details.php?office_id=<?= $selectedOffice ?>&tab=connectivity" class="btn btn-sm <?= $detailTab==='connectivity'?'btn-primary':'btn-outline-primary' ?>">Connectivity</a>
</div>
<?php if ($detailTab === 'reviews'): ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h6 class="fw-bold mb-0">Reviews</h6>
    <button class="btn btn-primary btn-sm" onclick="showReviewForm()"><i class="fa-solid fa-plus me-1"></i>Add Review</button>
</div>
<div id="reviewFormWrap" class="card border-0 shadow-sm mb-3 d-none">
    <div class="card-body">
        <h6 class="fw-bold mb-3">Add / Edit Review</h6>
        <form id="reviewForm" onsubmit="saveReview(event)">
            <input type="hidden" name="id" id="reviewId" value="">
            <div class="row g-2">
                <div class="col-md-6">
                    <label for="reviewName" class="form-label small">Name *</label>
                    <input type="text" name="reviewer_name" class="form-control form-control-sm" id="reviewName" required>
                </div>
                <div class="col-md-3">
                    <label for="reviewRating" class="form-label small">Rating *</label>
                    <select name="rating" class="form-select form-select-sm" id="reviewRating">
                        <option value="5">5</option><option value="4">4</option><option value="3">3</option><option value="2">2</option><option value="1">1</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="reviewStatus" class="form-label small">Status</label>
                    <select name="status" class="form-select form-select-sm" id="reviewStatus">
                        <option value="approved">Approved</option><option value="pending">Pending</option><option value="rejected">Rejected</option>
                    </select>
                </div>
                <div class="col-12">
                    <label for="reviewText" class="form-label small">Review Text</label>
                    <textarea name="review_text" class="form-control form-control-sm" id="reviewText" rows="2"></textarea>
                </div>
            </div>
            <div class="mt-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm">Save</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="hideReviewForm()">Cancel</button>
            </div>
        </form>
    </div>
</div>
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-dark">
                    <tr><th scope="col">ID</th><th scope="col">Name</th><th scope="col">Rating</th><th scope="col">Review</th><th scope="col">Status</th><th scope="col">Date</th><th scope="col">Actions</th></tr>
                </thead>
                <tbody id="reviewsTableBody"></tbody>
            </table>
        </div>
    </div>
</div>
<?php elseif ($detailTab === 'faq'): ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h6 class="fw-bold mb-0">FAQ</h6>
    <button class="btn btn-primary btn-sm" onclick="showFaqForm()"><i class="fa-solid fa-plus me-1"></i>Add FAQ</button>
</div>
<div id="faqFormWrap" class="card border-0 shadow-sm mb-3 d-none">
    <div class="card-body">
        <h6 class="fw-bold mb-3">Add / Edit FAQ</h6>
        <form id="faqForm" onsubmit="saveFaq(event)">
            <input type="hidden" name="id" id="faqId" value="">
            <div class="mb-2">
                <label for="faqQuestion" class="form-label small">Question *</label>
                <input type="text" name="question" class="form-control form-control-sm" id="faqQuestion" required>
            </div>
            <div class="mb-2">
                <label for="faqAnswer" class="form-label small">Answer *</label>
                <textarea name="answer" class="form-control form-control-sm" id="faqAnswer" rows="3" required></textarea>
            </div>
            <div class="row g-2">
                <div class="col-md-6">
                    <label for="faqSort" class="form-label small">Sort Order</label>
                    <input type="number" name="sort_order" class="form-control form-control-sm" id="faqSort" value="0">
                </div>
                <div class="col-md-6">
                    <label for="faqActive" class="form-label small">Active</label>
                    <select name="is_active" class="form-select form-select-sm" id="faqActive">
                        <option value="1">Yes</option><option value="0">No</option>
                    </select>
                </div>
            </div>
            <div class="mt-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm">Save</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="hideFaqForm()">Cancel</button>
            </div>
        </form>
    </div>
</div>
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-dark">
                    <tr><th scope="col">ID</th><th scope="col">Question</th><th scope="col">Answer</th><th scope="col">Order</th><th scope="col">Active</th><th scope="col">Actions</th></tr>
                </thead>
                <tbody id="faqTableBody"></tbody>
            </table>
        </div>
    </div>
</div>
<?php elseif ($detailTab === 'building'): ?>
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <h6 class="fw-bold mb-3">Building Details</h6>
        <form id="buildingForm" onsubmit="saveBuilding(event)">
            <div class="row g-2">
                <div class="col-md-4">
                    <label for="bldgName" class="form-label small">Building Name</label>
                    <input type="text" name="building_name" class="form-control form-control-sm" id="bldgName">
                </div>
                <div class="col-md-4">
                    <label for="bldgYear" class="form-label small">Year Built</label>
                    <input type="text" name="year_built" class="form-control form-control-sm" id="bldgYear">
                </div>
                <div class="col-md-4">
                    <label for="bldgFloors" class="form-label small">Total Floors</label>
                    <input type="number" name="total_floors" class="form-control form-control-sm" id="bldgFloors">
                </div>
                <div class="col-md-4">
                    <label for="bldgPlate" class="form-label small">Floor Plate Area</label>
                    <input type="text" name="floor_plate_area" class="form-control form-control-sm" id="bldgPlate">
                </div>
                <div class="col-md-4">
                    <label for="bldgElevators" class="form-label small">Elevators</label>
                    <input type="number" name="elevators" class="form-control form-control-sm" id="bldgElevators">
                </div>
                <div class="col-md-4">
                    <label for="bldgParking" class="form-label small">Parking</label>
                    <input type="text" name="parking" class="form-control form-control-sm" id="bldgParking">
                </div>
            </div>
            <h6 class="fw-bold mt-3 mb-2">Connectivity</h6>
            <div class="row g-2">
                <div class="col-md-3">
                    <label for="bldgMetro" class="form-label small">Nearest Metro</label>
                    <input type="text" name="nearest_metro" class="form-control form-control-sm" id="bldgMetro">
                </div>
                <div class="col-md-3">
                    <label for="bldgRailway" class="form-label small">Nearest Railway</label>
                    <input type="text" name="nearest_railway" class="form-control form-control-sm" id="bldgRailway">
                </div>
                <div class="col-md-3">
                    <label for="bldgAirport" class="form-label small">Airport</label>
                    <input type="text" name="airport" class="form-control form-control-sm" id="bldgAirport">
                </div>
                <div class="col-md-3">
                    <label for="bldgBus" class="form-label small">Bus Stop</label>
                    <input type="text" name="bus_stop" class="form-control form-control-sm" id="bldgBus">
                </div>
            </div>
            <div class="mt-3"><button type="submit" class="btn btn-primary btn-sm">Save Building Details</button></div>
        </form>
    </div>
</div>
<?php elseif ($detailTab === 'leasing'): ?>
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
<?php elseif ($detailTab === 'connectivity'): ?>
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <h6 class="fw-bold mb-3">Connectivity & Transit</h6>
        <form id="connectivityForm" onsubmit="saveConnectivity(event)">
            <div class="row g-2">
                <div class="col-md-6">
                    <label for="connMetro" class="form-label small">Nearest Metro</label>
                    <input type="text" name="nearest_metro" class="form-control form-control-sm" id="connMetro" placeholder="e.g. Anna Nagar Tower">
                </div>
                <div class="col-md-6">
                    <label for="connRailway" class="form-label small">Nearest Railway Station</label>
                    <input type="text" name="nearest_railway" class="form-control form-control-sm" id="connRailway" placeholder="e.g. Chennai Central">
                </div>
                <div class="col-md-6">
                    <label for="connAirport" class="form-label small">Airport</label>
                    <input type="text" name="airport" class="form-control form-control-sm" id="connAirport" placeholder="e.g. Chennai International Airport">
                </div>
                <div class="col-md-6">
                    <label for="connBus" class="form-label small">Bus Stop</label>
                    <input type="text" name="bus_stop" class="form-control form-control-sm" id="connBus" placeholder="e.g. Koyambedu Bus Terminus">
                </div>
            </div>
            <div class="mt-3"><button type="submit" class="btn btn-primary btn-sm">Save Connectivity</button></div>
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
<?php if ($detailTab === 'reviews'): ?>
async function loadReviews() {
    const d = await apiGet('list_reviews');
    const tb = document.getElementById('reviewsTableBody');
    if (!d.reviews || !d.reviews.length) { tb.innerHTML = '<tr><td colspan="7" class="text-center py-4"><i class="fa-solid fa-inbox text-muted mb-2 d-block" style="font-size: 1.5rem;"></i><span class="text-muted fw-medium">No data found</span></td></tr>'; return; }
    tb.innerHTML = d.reviews.map(r => '<tr><td class="text-muted">'+r.id+'</td><td class="fw-medium">'+esc(r.reviewer_name)+'</td><td>'+r.rating+'/5</td><td>'+esc((r.review_text||'').substring(0,80))+'</td><td><span class="badge bg-'+((r.status==='approved'?'success':r.status==='rejected'?'danger':'warning text-dark'))+'">'+r.status+'</span></td><td class="text-muted">'+r.created_at+'</td><td><a href="javascript:void(0)" onclick="editReview('+r.id+');return false;" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-pen-to-square"></i></a> <a href="javascript:void(0)" onclick="deleteReview('+r.id+');return false;" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash-can"></i></a></td></tr>').join('');
}
function showReviewForm() { document.getElementById('reviewFormWrap').classList.remove('d-none'); document.getElementById('reviewId').value=''; document.getElementById('reviewName').value=''; document.getElementById('reviewRating').value='5'; document.getElementById('reviewText').value=''; document.getElementById('reviewStatus').value='approved'; }
function hideReviewForm() { document.getElementById('reviewFormWrap').classList.add('d-none'); }
function editReview(id) { apiGet('list_reviews').then(d => { const r = d.reviews.find(x=>x.id==id); if(r){document.getElementById('reviewFormWrap').classList.remove('d-none');document.getElementById('reviewId').value=r.id;document.getElementById('reviewName').value=r.reviewer_name;document.getElementById('reviewRating').value=r.rating;document.getElementById('reviewText').value=r.review_text||'';document.getElementById('reviewStatus').value=r.status;} }); }
async function saveReview(e) { e.preventDefault(); const fd=new FormData(document.getElementById('reviewForm')); const data={}; fd.forEach((v,k)=>data[k]=v); const action=data.id?'update_review':'create_review'; const d=await apiPost(action,data); if(d.success){loadReviews();hideReviewForm();}else{showAlertModal(d.error||'Error','error');} }
async function deleteReview(id) { showConfirmDialog('Delete this review?', async function(){ const d=await apiPost('delete_review',{id:id}); if(d.success)loadReviews(); }); }
loadReviews();
<?php elseif ($detailTab === 'faq'): ?>
async function loadFaq() {
    const d = await apiGet('list_faq');
    const tb = document.getElementById('faqTableBody');
    if (!d.faq || !d.faq.length) { tb.innerHTML = '<tr><td colspan="6" class="text-center py-4"><i class="fa-solid fa-inbox text-muted mb-2 d-block" style="font-size: 1.5rem;"></i><span class="text-muted fw-medium">No data found</span></td></tr>'; return; }
    tb.innerHTML = d.faq.map(f => '<tr><td class="text-muted">'+f.id+'</td><td>'+esc(f.question)+'</td><td>'+esc((f.answer||'').substring(0,60))+'</td><td>'+f.sort_order+'</td><td>'+(f.is_active?'<span class="badge bg-success">Yes</span>':'<span class="badge bg-secondary">No</span>')+'</td><td><a href="javascript:void(0)" onclick="editFaq('+f.id+');return false;" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-pen-to-square"></i></a> <a href="javascript:void(0)" onclick="deleteFaq('+f.id+');return false;" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash-can"></i></a></td></tr>').join('');
}
function showFaqForm() { document.getElementById('faqFormWrap').classList.remove('d-none'); document.getElementById('faqId').value=''; document.getElementById('faqQuestion').value=''; document.getElementById('faqAnswer').value=''; document.getElementById('faqSort').value='0'; document.getElementById('faqActive').value='1'; }
function hideFaqForm() { document.getElementById('faqFormWrap').classList.add('d-none'); }
function editFaq(id) { apiGet('list_faq').then(d => { const f = d.faq.find(x=>x.id==id); if(f){document.getElementById('faqFormWrap').classList.remove('d-none');document.getElementById('faqId').value=f.id;document.getElementById('faqQuestion').value=f.question;document.getElementById('faqAnswer').value=f.answer;document.getElementById('faqSort').value=f.sort_order;document.getElementById('faqActive').value=f.is_active;} }); }
async function saveFaq(e) { e.preventDefault(); const fd=new FormData(document.getElementById('faqForm')); const data={}; fd.forEach((v,k)=>data[k]=v); const action=data.id?'update_faq':'create_faq'; const d=await apiPost(action,data); if(d.success){loadFaq();hideFaqForm();}else{showAlertModal(d.error||'Error','error');} }
async function deleteFaq(id) { showConfirmDialog('Delete this FAQ?', async function(){ const d=await apiPost('delete_faq',{id:id}); if(d.success)loadFaq(); }); }
loadFaq();
<?php elseif ($detailTab === 'building'): ?>
(async function() {
    const d = await apiGet('get_building');
    if (d.building) {
        const b = d.building;
        document.getElementById('bldgName').value = b.building_name||'';
        document.getElementById('bldgYear').value = b.year_built||'';
        document.getElementById('bldgFloors').value = b.total_floors||'';
        document.getElementById('bldgPlate').value = b.floor_plate_area||'';
        document.getElementById('bldgElevators').value = b.elevators||'';
        document.getElementById('bldgParking').value = b.parking||'';
        document.getElementById('bldgMetro').value = b.nearest_metro||'';
        document.getElementById('bldgRailway').value = b.nearest_railway||'';
        document.getElementById('bldgAirport').value = b.airport||'';
        document.getElementById('bldgBus').value = b.bus_stop||'';
    }
})();
async function saveBuilding(e) { e.preventDefault(); const fd=new FormData(document.getElementById('buildingForm')); const data={}; fd.forEach((v,k)=>data[k]=v); const d=await apiPost('save_building',data); if(d.success){showAlertModal('Saved successfully!','success');}else{showAlertModal(d.error||'Error','error');} }
<?php elseif ($detailTab === 'leasing'): ?>
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
async function deleteLeasing(id) { showConfirmDialog('Delete this option?', async function(){ const d=await apiPost('delete_leasing',{id:id}); if(d.success)loadLeasing(); }); }
loadLeasing();
<?php elseif ($detailTab === 'extras'): ?>
(async function() {
    const d = await apiGet('get_building');
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
<?php elseif ($detailTab === 'connectivity'): ?>
(async function() {
    const d = await apiGet('get_connectivity');
    if (d.connectivity) {
        const c = d.connectivity;
        document.getElementById('connMetro').value = c.nearest_metro||'';
        document.getElementById('connRailway').value = c.nearest_railway||'';
        document.getElementById('connAirport').value = c.airport||'';
        document.getElementById('connBus').value = c.bus_stop||'';
    }
})();
async function saveConnectivity(e) {
    e.preventDefault();
    const fd = new FormData(document.getElementById('connectivityForm'));
    const data = {};
    fd.forEach((v,k) => data[k] = v);
    const d = await apiPost('save_connectivity', data);
    if (d.success) { showAlertModal('Saved successfully!', 'success'); } else { showAlertModal(d.error || 'Error', 'error'); }
}
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
