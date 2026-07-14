    </main>
</div>

<div id="loadingOverlay" class="position-fixed top-0 start-0 w-100 h-100 d-none">
    <div class="d-flex flex-column align-items-center gap-2">
        <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
            <span class="visually-hidden">Loading...</span>
        </div>
        <span class="text-muted small fw-semibold">Loading...</span>
    </div>
</div>

<div class="modal fade" id="confirmModal" tabindex="-1" aria-label="Confirm deletion" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-body text-center py-4">
                <i class="fa-solid fa-triangle-exclamation text-warning mb-3" style="font-size: 2rem;"></i>
                <p class="mb-0 fw-medium" id="confirmMessage">Are you sure?</p>
            </div>
            <div class="modal-footer border-0 pt-0 justify-content-center gap-2">
                <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger btn-sm px-3" id="confirmYesBtn">Yes, Delete</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="alertModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-body text-center py-4">
                <i class="fa-solid fa-circle-check text-success mb-3 d-none" id="alertIconSuccess" style="font-size: 2rem;"></i>
                <i class="fa-solid fa-circle-exclamation text-danger mb-3 d-none" id="alertIconError" style="font-size: 2rem;"></i>
                <i class="fa-solid fa-circle-info text-primary mb-3 d-none" id="alertIconInfo" style="font-size: 2rem;"></i>
                <p class="mb-0 fw-medium" id="alertMessage">Message</p>
            </div>
            <div class="modal-footer border-0 pt-0 justify-content-center gap-2">
                <button type="button" class="btn btn-primary btn-sm px-3" data-bs-dismiss="modal">OK</button>
            </div>
        </div>
    </div>
</div>

<script src="<?= htmlspecialchars('/assets/js/site-nav.js', ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= htmlspecialchars('/assets/js/api-client.js', ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars('/assets/js/toast.js', ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars('/assets/js/realtime.js', ENT_QUOTES, 'UTF-8') ?>"></script>
<script>
(function syncToken() {
    var metaToken = (document.querySelector('meta[name="access-token"]') || {}).content || '';
    if (metaToken) sessionStorage.setItem('admin_access_token', metaToken);
})();
function getToken() {
    var meta = document.querySelector('meta[name="access-token"]');
    return (meta && meta.content) || sessionStorage.getItem('admin_access_token') || '';
}
function showLoading(show) {
    var el = document.getElementById('loadingOverlay');
    if (el) {
        if (show) el.classList.remove('d-none');
        else el.classList.add('d-none');
    }
}
function showConfirmDialog(message, callback, confirmText, confirmClass) {
    confirmText = confirmText || 'Yes, Delete';
    confirmClass = confirmClass || 'btn-danger';
    var btn = document.getElementById('confirmYesBtn');
    document.getElementById('confirmMessage').textContent = message;
    btn.textContent = confirmText;
    btn.className = 'btn btn-sm px-3 ' + confirmClass;
    btn.onclick = function() {
        var modalEl = document.getElementById('confirmModal');
        var modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
        callback();
    };
    var modalEl = document.getElementById('confirmModal');
    var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modalEl.addEventListener('hidden.bs.modal', function handler() {
        modalEl.removeEventListener('hidden.bs.modal', handler);
        document.getElementById('confirmYesBtn').onclick = null;
        document.getElementById('confirmYesBtn').textContent = 'Yes, Delete';
        document.getElementById('confirmYesBtn').className = 'btn btn-danger btn-sm px-3';
    }, { once: true });
    modal.show();
}
function showAlertModal(message, type) {
    type = type || 'info';
    document.getElementById('alertMessage').textContent = message;
    document.getElementById('alertIconSuccess').classList.add('d-none');
    document.getElementById('alertIconError').classList.add('d-none');
    document.getElementById('alertIconInfo').classList.add('d-none');
    document.getElementById('alertIcon' + type.charAt(0).toUpperCase() + type.slice(1)).classList.remove('d-none');
    var modalEl = document.getElementById('alertModal');
    var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
}
function confirmDelete(id, type, title) {
    showConfirmDialog('Delete "' + title + '" (ID: ' + id + ')? This cannot be undone.', async function() {
        const fd = new FormData();
        fd.append('id', id);
        fd.append('listing_type', type);
        let headers = {};
        let token = getToken();
        if (token) headers['Authorization'] = 'Bearer ' + token;
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        if (csrfMeta) headers['X-CSRF-Token'] = csrfMeta.content;
        let r = await fetch('/admin/api/listing_crud.php?action=delete', { method: 'POST', headers: headers, credentials: 'same-origin', body: fd });
        if (r.status === 401) {
            const refreshRes = await fetch('/admin/token_refresh.php', { method: 'POST', credentials: 'same-origin' });
            if (refreshRes.ok) {
                const refreshData = await refreshRes.json();
                if (refreshData.access_token) {
                    sessionStorage.setItem('admin_access_token', refreshData.access_token);
                    headers['Authorization'] = 'Bearer ' + refreshData.access_token;
                    r = await fetch('/admin/api/listing_crud.php?action=delete', { method: 'POST', headers: headers, credentials: 'same-origin', body: fd });
                }
            }
        }
        if (r.status === 401) { window.location.reload(); return; }
        const d = await r.json();
        if (d.success) window.location.reload(); else showAlertModal(d.error, 'error');
    });
}
function confirmDeleteContact(id) {
    showConfirmDialog('Delete this contact submission?', async function() {
        const fd = new FormData(); fd.append('id', id);
        let headers = {};
        let token = getToken();
        if (token) headers['Authorization'] = 'Bearer ' + token;
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        if (csrfMeta) headers['X-CSRF-Token'] = csrfMeta.content;
        let r = await fetch('/admin/api/contact_crud.php?action=delete', { method: 'POST', headers: headers, credentials: 'same-origin', body: fd });
        if (r.status === 401) {
            const refreshRes = await fetch('/admin/token_refresh.php', { method: 'POST', credentials: 'same-origin' });
            if (refreshRes.ok) {
                const refreshData = await refreshRes.json();
                if (refreshData.access_token) {
                    sessionStorage.setItem('admin_access_token', refreshData.access_token);
                    headers['Authorization'] = 'Bearer ' + refreshData.access_token;
                    r = await fetch('/admin/api/contact_crud.php?action=delete', { method: 'POST', headers: headers, credentials: 'same-origin', body: fd });
                }
            }
        }
        if (r.status === 401) { window.location.reload(); return; }
        const d = await r.json();
        if (d.success) window.location.href = 'contacts.php?deleted=1'; else showAlertModal(d.error, 'error');
    });
}
function toggleAllCheckboxes(master) {
    document.querySelectorAll('.bulk-checkbox').forEach(cb => cb.checked = master.checked);
}
document.querySelectorAll('[id^="officeSpaceType"]').forEach(sel => {
    sel.addEventListener('change', function() {
        const form = this.closest('form');
        if (!form) return;
        const priceInput = form.querySelector('input[name="price"]');
        const labelInput = form.querySelector('input[name="price_label"]');
        const isLease = this.value === 'lease';
        priceInput.placeholder = isLease ? 'e.g. 1500000 (per year)' : 'e.g. 150000 (per month)';
        if (priceInput.value && !labelInput.value) {
            const num = parseFloat(priceInput.value);
            if (num) {
                if (isLease) {
                    labelInput.value = '\u20B9' + (num >= 100000 ? (num / 100000).toFixed(1) + ' Lakhs/yr' : num.toLocaleString() + '/yr');
                } else {
                    labelInput.value = '\u20B9' + (num >= 100000 ? (num / 100000).toFixed(1) + ' Lakhs/mo' : num.toLocaleString() + '/mo');
                }
            }
        }
    });
    sel.dispatchEvent(new Event('change'));
});
function removeExistingImage(btn) {
    var container = btn.closest('[data-src]');
    if (!container) return;
    var src = container.getAttribute('data-src');
    container.remove();
    var input = document.getElementById('existingImages');
    if (input && src) {
        try {
            var imgs = JSON.parse(input.value || '[]');
            var idx = imgs.indexOf(src);
            if (idx > -1) imgs.splice(idx, 1);
            input.value = JSON.stringify(imgs);
        } catch(e) {}
    }
}
async function addNewCity() {
    var input = document.getElementById('newCity');
    var select = document.getElementById('city');
    if (!input || !select) return;
    var val = input.value.trim();
    if (!val) return;
    var lower = val.toLowerCase();
    for (var i = 0; i < select.options.length; i++) {
        if (select.options[i].value.toLowerCase() === lower) { select.value = lower; input.value = ''; return; }
    }
    var ok = await postOption('city', lower);
    if (!ok) return;
    var opt = document.createElement('option');
    opt.value = lower;
    opt.text = val.charAt(0).toUpperCase() + val.slice(1);
    select.add(opt);
    select.value = lower;
    input.value = '';
}
async function addNewArea() {
    var input = document.getElementById('newArea');
    var select = document.getElementById('area');
    var citySelect = document.getElementById('city');
    if (!input || !select) return;
    var val = input.value.trim();
    if (!val) return;
    var lower = val.toLowerCase();
    for (var i = 0; i < select.options.length; i++) {
        if (select.options[i].value.toLowerCase() === lower) { select.value = lower; input.value = ''; return; }
    }
    var selectedCity = citySelect ? citySelect.value : '';
    if (!selectedCity) { showAlertModal('Select a city first before adding an area.', 'info'); return; }
    var ok = await postOption('area', lower, selectedCity);
    if (!ok) return;
    var displayText = val.charAt(0).toUpperCase() + val.slice(1);
    if (allAreaOptions) allAreaOptions.push({ value: lower, text: displayText, city: selectedCity });
    filterAreasByCity();
    select.value = lower;
    input.value = '';
}
async function deleteCity() {
    var select = document.getElementById('city');
    if (!select || !select.value) return;
    var val = select.value;
    var ok = await deleteOption('city', val);
    if (!ok) return;
    for (var i = 0; i < select.options.length; i++) {
        if (select.options[i].value === val && i > 0) {
            select.remove(i);
            select.value = '';
            break;
        }
    }
    filterAreasByCity();
}
async function deleteArea() {
    var select = document.getElementById('area');
    if (!select || !select.value) return;
    var val = select.value;
    var ok = await deleteOption('area', val);
    if (!ok) return;
    if (allAreaOptions) {
        allAreaOptions = allAreaOptions.filter(function(a) { return a.value !== val; });
    }
    filterAreasByCity();
}
async function postOption(type, value, city) {
    var fd = new FormData();
    fd.append('type', type);
    fd.append('value', value);
    if (city) fd.append('city', city);
    var headers = { 'Authorization': 'Bearer ' + getToken() };
    var csrf = document.querySelector('meta[name="csrf-token"]');
    if (csrf) headers['X-CSRF-Token'] = csrf.content;
    try {
        var r = await fetch('/admin/api/managed_options_api.php?action=add', { method: 'POST', headers: headers, body: fd });
        var d = await r.json();
        if (!d.success) { showAlertModal(d.error || 'Failed to add ' + type, 'error'); return false; }
        return true;
    } catch (err) {
        showAlertModal('Error: ' + err.message, 'error'); return false;
    }
}
async function deleteOption(type, value) {
    var fd = new FormData();
    fd.append('type', type);
    fd.append('value', value);
    var headers = { 'Authorization': 'Bearer ' + getToken() };
    var csrf = document.querySelector('meta[name="csrf-token"]');
    if (csrf) headers['X-CSRF-Token'] = csrf.content;
    try {
        var r = await fetch('/admin/api/managed_options_api.php?action=delete', { method: 'POST', headers: headers, body: fd });
        var d = await r.json();
        if (!d.success) { showAlertModal(d.error || 'Failed to delete ' + type, 'error'); return false; }
        return true;
    } catch (err) {
        showAlertModal('Error: ' + err.message, 'error'); return false;
    }
}
var lsToken = localStorage.getItem('admin_access_token');
if (lsToken && !getToken()) { sessionStorage.setItem('admin_access_token', lsToken); }
localStorage.removeItem('admin_access_token');
document.querySelectorAll('#listingForm').forEach(function(form) {
    form.addEventListener('submit', function(e) {
        if (!form.checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
            form.classList.add('was-validated');
            return;
        }
        form.classList.add('was-validated');
        handleListingForm(e);
    });
});
document.querySelectorAll('.amenity-check').forEach(function(cb) {
    cb.addEventListener('change', syncAmenities);
});
function syncAmenities() {
    var checked = [];
    document.querySelectorAll('.amenity-check:checked').forEach(function(cb) { checked.push(cb.value); });
    var input = document.getElementById('amenitiesInput');
    if (input) input.value = JSON.stringify(checked);
}
syncAmenities();
var seatRanges = { '50': {min:10, max:50}, '100': {min:51, max:100}, '200': {min:101, max:200}, '500': {min:201, max:null} };
function updateBillableSeatsRange() {
    var cat = document.getElementById('total_seats');
    var inp = document.getElementById('billable_seats');
    var feedback = document.getElementById('billableSeatsFeedback');
    if (!cat || !inp) return;
    var range = seatRanges[cat.value];
    inp.placeholder = range ? range.min + (range.max ? ' - ' + range.max : '+') : 'e.g. 30';
    inp.setCustomValidity('');
    if (feedback) { feedback.style.display = 'none'; feedback.textContent = ''; }
    inp.classList.remove('is-invalid');
}
document.addEventListener('change', function(e) {
    if (e.target.id === 'total_seats') updateBillableSeatsRange();
});
document.addEventListener('DOMContentLoaded', updateBillableSeatsRange);
updateBillableSeatsRange();
function validateBillableSeats() {
    var inp = document.getElementById('billable_seats');
    var cat = document.getElementById('total_seats');
    var feedback = document.getElementById('billableSeatsFeedback');
    if (!inp || !cat || !feedback) return true;
    var val = inp.value.trim();
    if (!val || !cat.value) { feedback.style.display = 'none'; inp.classList.remove('is-invalid'); return true; }
    var range = seatRanges[cat.value];
    if (!range) { feedback.style.display = 'none'; inp.classList.remove('is-invalid'); return true; }
    var num = parseInt(val, 10);
    if (isNaN(num) || num < range.min || (range.max !== null && num > range.max)) {
        feedback.textContent = 'Billable seats must be between ' + range.min + (range.max ? ' and ' + range.max : '+') + ' for the selected category.';
        feedback.style.display = 'block';
        inp.classList.add('is-invalid');
        return false;
    }
    feedback.style.display = 'none';
    inp.classList.remove('is-invalid');
    return true;
}
document.addEventListener('input', function(e) {
    if (e.target.id === 'billable_seats') validateBillableSeats();
    if (e.target.id === 'total_area_sqft') validateTotalAreaSqft();
});

var sqftRanges = { '1000-5000': {min:1000, max:5000}, '5000-10000': {min:5000, max:10000}, '10000-20000': {min:10000, max:20000}, '20000-': {min:20000, max:null} };
function updateTotalAreaSqftRange() {
    var cat = document.getElementById('available_sqft');
    var inp = document.getElementById('total_area_sqft');
    var feedback = document.getElementById('totalAreaSqftFeedback');
    if (!cat || !inp) return;
    var range = sqftRanges[cat.value];
    inp.placeholder = range ? range.min + (range.max ? ' - ' + range.max : '+') + ' Sq Ft' : 'e.g. 5000';
    inp.setCustomValidity('');
    if (feedback) { feedback.style.display = 'none'; feedback.textContent = ''; }
    inp.classList.remove('is-invalid');
}
document.addEventListener('change', function(e) {
    if (e.target.id === 'available_sqft') updateTotalAreaSqftRange();
});
document.addEventListener('DOMContentLoaded', updateTotalAreaSqftRange);
updateTotalAreaSqftRange();
function validateTotalAreaSqft() {
    var inp = document.getElementById('total_area_sqft');
    var cat = document.getElementById('available_sqft');
    var feedback = document.getElementById('totalAreaSqftFeedback');
    if (!inp || !cat || !feedback) return true;
    var val = inp.value.trim();
    if (!val || !cat.value) { feedback.style.display = 'none'; inp.classList.remove('is-invalid'); return true; }
    var range = sqftRanges[cat.value];
    if (!range) { feedback.style.display = 'none'; inp.classList.remove('is-invalid'); return true; }
    var num = parseInt(val, 10);
    if (isNaN(num) || num < range.min || (range.max !== null && num > range.max)) {
        feedback.textContent = 'Value must be between ' + range.min + (range.max ? ' and ' + range.max : '+') + ' Sq Ft for the selected range.';
        feedback.style.display = 'block';
        inp.classList.add('is-invalid');
        return false;
    }
    feedback.style.display = 'none';
    inp.classList.remove('is-invalid');
    return true;
}

function validateInventoryType(input) {
    if (!input) input = document.getElementById('inventory_type');
    if (!input) return true;
    var val = input.value.trim();
    var feedback = document.getElementById('inventoryTypeFeedback');
    if (val && !isNaN(Number(val))) {
        input.setCustomValidity('Current Status must be a text value, cannot be a number');
        input.classList.add('is-invalid');
        if (feedback) { feedback.textContent = 'Current Status must be a text value, cannot be a number'; feedback.style.display = 'block'; }
        return false;
    }
    input.setCustomValidity('');
    input.classList.remove('is-invalid');
    if (feedback) { feedback.style.display = 'none'; feedback.textContent = ''; }
    return true;
}
document.addEventListener('input', function(e) {
    if (e.target.id === 'inventory_type') validateInventoryType(e.target);
});

function handleListingForm(e) {
    e.preventDefault();
    const form = e.target;
    const btn = form.querySelector('button[type="submit"]');
    const result = document.getElementById('formResult');
    if (!result) return;
    const fd = new FormData(form);
    const isEdit = parseInt(fd.get('id')) > 0;
    const action = isEdit ? 'update' : 'create';
    const title = fd.get('title');
    if (!title || !title.trim()) {
        result.className = 'alert alert-danger mt-2'; result.textContent = 'Title is required'; result.classList.remove('d-none');
        return;
    }
    if (!validateInventoryType()) return;
    const price = fd.get('price');
    const officeType = fd.get('office_space_type');
    if (!validateBillableSeats()) return;
    if (!validateTotalAreaSqft()) return;
    function proceedSubmit() {
        btn.disabled = true;
        btn.textContent = isEdit ? 'Updating...' : 'Creating...';
        result.className = 'alert d-none mt-2';
        (async function() {
        try {
            let headers = { 'Authorization': 'Bearer ' + getToken(), 'X-CSRF-Token': (document.querySelector('meta[name="csrf-token"]') || {}).content || '' };
            let r = await fetch('/admin/api/listing_crud.php?action=' + action, { method: 'POST', headers: headers, credentials: 'same-origin', body: fd });
            if (r.status === 401) {
                const refreshRes = await fetch('/admin/token_refresh.php', { method: 'POST', credentials: 'same-origin' });
                if (refreshRes.ok) {
                    const refreshData = await refreshRes.json();
                    if (refreshData.access_token) {
                        sessionStorage.setItem('admin_access_token', refreshData.access_token);
                        headers['Authorization'] = 'Bearer ' + refreshData.access_token;
                        r = await fetch('/admin/api/listing_crud.php?action=' + action, { method: 'POST', headers: headers, credentials: 'same-origin', body: fd });
                    }
                }
            }
            if (r.status === 401) { window.location.reload(); return; }
            const d = await r.json();
            result.classList.remove('d-none');
            if (d.success) {
                result.className = 'alert alert-success mt-2';
                result.textContent = d.message;
                setTimeout(function() {
                    var qs = window.location.search.replace(/[?&]?(mode|id)=[^&]*/g, '').replace(/^&/, '');
                    var page = window.location.pathname.split('/').pop().replace('.php', '');
                    window.location.href = page + '.php' + (qs ? '?' + qs : '') + (qs ? '&' : '?') + 'saved=1';
                }, 1000);
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
        btn.textContent = isEdit ? 'Update Listing' : 'Create Listing';
    })();
}
    if (!price && officeType === 'lease') {
        showConfirmDialog('No price set for Lease. The listing will show without a price. Continue?', proceedSubmit, 'Continue', 'btn-primary');
    } else {
        proceedSubmit();
    }
}
const contactDetailForm = document.getElementById('contactDetailForm');
if (contactDetailForm) {
    contactDetailForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = this.querySelector('button[type="submit"]');
        const result = document.getElementById('contactFormResult');
        if (!result) return;
        const fd = new FormData(this);
        btn.disabled = true; btn.textContent = 'Saving...';
        result.className = 'alert d-none mt-2';
        try {
            let headers = {
                'Authorization': 'Bearer ' + getToken(),
                'X-CSRF-Token': (document.querySelector('meta[name="csrf-token"]') || {}).content || ''
            };
            let r = await fetch('/admin/api/contact_crud.php?action=update', { method: 'POST', headers: headers, credentials: 'same-origin', body: fd });
            if (r.status === 401) {
                const refreshRes = await fetch('/admin/token_refresh.php', { method: 'POST', credentials: 'same-origin' });
                if (refreshRes.ok) {
                    const refreshData = await refreshRes.json();
                    if (refreshData.access_token) {
                        sessionStorage.setItem('admin_access_token', refreshData.access_token);
                        headers['Authorization'] = 'Bearer ' + refreshData.access_token;
                        r = await fetch('/admin/api/contact_crud.php?action=update', { method: 'POST', headers: headers, credentials: 'same-origin', body: fd });
                    }
                }
            }
            if (r.status === 401) { window.location.reload(); return; }
            const d = await r.json();
            result.classList.remove('d-none');
            if (d.success) {
                result.className = 'alert alert-success mt-2';
                result.textContent = d.message || 'Updated successfully';
                setTimeout(function() {
                    window.location.href = 'contacts.php?saved=1';
                }, 1000);
            } else {
                result.className = 'alert alert-danger mt-2';
                result.textContent = d.error || 'Operation failed';
            }
        } catch (err) {
            result.classList.remove('d-none');
            result.className = 'alert alert-danger mt-2';
            result.textContent = err.message || 'Network error';
        }
        btn.disabled = false; btn.textContent = 'Save Changes';
    });
}
async function applyBulkAction() {
    const bulkBar = document.querySelector('.bulk-bar:not(.d-none)') || document.querySelector('.bulk-bar');
    if (!bulkBar) return;
    const actionVal = bulkBar.querySelector('select').value;
    if (!actionVal) return;
    const checkedBoxes = document.querySelectorAll('.bulk-checkbox:checked');
    if (checkedBoxes.length === 0) {
        showAlertModal('Please select at least one record.', 'info');
        return;
    }
    const ids = Array.from(checkedBoxes).map(cb => parseInt(cb.value));
    const types = Array.from(checkedBoxes).map(cb => cb.dataset.type || '');
    const page = window.location.pathname.split('/').pop().replace('.php', '');
    let url = '/admin/api/bulk_crud.php';
    let fd = new FormData();
    fd.append('page', page);
    ids.forEach((id, i) => {
        fd.append('ids[]', id);
        if (types[i]) fd.append('types[]', types[i]);
    });
    let apiAction = '';
    if (actionVal === 'delete') {
        apiAction = 'bulk_delete';
    } else if (actionVal.startsWith('status-')) {
        apiAction = 'bulk_status';
        fd.append('status', actionVal.replace('status-', ''));
    } else if (actionVal.startsWith('featured-')) {
        apiAction = 'bulk_featured';
        fd.append('featured', actionVal.replace('featured-', ''));
    }
    const doAction = async function() {
        try {
            let headers = {
                'Authorization': 'Bearer ' + getToken(),
                'X-CSRF-Token': (document.querySelector('meta[name="csrf-token"]') || {}).content || ''
            };
            const r = await fetch(url + '?action=' + apiAction, { method: 'POST', headers: headers, body: fd });
            const d = await r.json();
            if (d.success) {
                showAlertModal(d.message || 'Bulk operation completed.', 'success');
                window.location.reload();
            } else {
                showAlertModal(d.error || 'Bulk operation failed.', 'error');
            }
        } catch (err) {
            showAlertModal('Error: ' + err.message, 'error');
        }
    };
    var confirmMsg = '';
    var confirmBtnText = 'Yes';
    var confirmBtnClass = 'btn-danger';
    if (apiAction === 'bulk_delete') {
        confirmMsg = 'Are you sure you want to delete ' + checkedBoxes.length + ' selected item(s)?';
    } else if (apiAction === 'bulk_status' || apiAction === 'bulk_featured') {
        var actionLabel = bulkBar.querySelector('select').options[bulkBar.querySelector('select').selectedIndex].text;
        confirmMsg = 'Apply "' + actionLabel + '" to ' + checkedBoxes.length + ' selected item(s)?';
        confirmBtnText = 'Apply';
        confirmBtnClass = 'btn-primary';
    }
    if (confirmMsg) {
        showConfirmDialog(confirmMsg, doAction, confirmBtnText, confirmBtnClass);
    } else {
        doAction();
    }
}
var allAreaOptions = null;
function collectAreaOptions() {
    if (allAreaOptions) return;
    var areaSelect = document.getElementById('area');
    if (!areaSelect) return;
    allAreaOptions = [];
    for (var i = 1; i < areaSelect.options.length; i++) {
        var o = areaSelect.options[i];
        allAreaOptions.push({ value: o.value, text: o.text, city: o.getAttribute('data-city') || '' });
    }
}
function rebuildAreaOptions(selectedCity, textFilter) {
    var areaSelect = document.getElementById('area');
    if (!areaSelect) return;
    collectAreaOptions();
    var currentVal = areaSelect.value;
    while (areaSelect.options.length > 1) areaSelect.remove(1);
    var q = (textFilter || '').toLowerCase().trim();
    for (var i = 0; i < allAreaOptions.length; i++) {
        var a = allAreaOptions[i];
        var optCity = a.city.toLowerCase().trim();
        if (selectedCity && optCity !== selectedCity) continue;
        if (q && a.text.toLowerCase().indexOf(q) === -1) continue;
        var opt = document.createElement('option');
        opt.value = a.value; opt.text = a.text;
        opt.setAttribute('data-city', a.city);
        areaSelect.add(opt);
    }
    areaSelect.value = (areaSelect.options.length > 1 && Array.from(areaSelect.options).some(function(o) { return o.value === currentVal; })) ? currentVal : '';
}
function filterAreasByCity() {
    var citySelect = document.getElementById('city');
    if (!citySelect) return;
    var searchInput = document.getElementById('areaSearch');
    rebuildAreaOptions(citySelect.value.toLowerCase().trim(), searchInput ? searchInput.value : '');
}
function filterAreasByText(input) {
    var citySelect = document.getElementById('city');
    if (!citySelect) return;
    rebuildAreaOptions(citySelect.value.toLowerCase().trim(), input.value);
}
// run on page load for edit mode
(function() { var c = document.getElementById('city'); if (c) { filterAreasByCity(); } })();

if (getToken()) {
    CubeRealtime.init({ adminMode: true, interval: 30000 });
    CubeRealtime.on('*', function(eventType, eventData) {
        if (eventType === 'contact_created' || eventType === 'partner_created') {
            if (window.CubeToast) {
                CubeToast.info('New ' + eventType.replace('_', ' ') + ': ' + (eventData.summary || ''));
            }
        }
    });
}
</script>
</body>
</html>
