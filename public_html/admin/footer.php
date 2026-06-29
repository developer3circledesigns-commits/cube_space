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
    showConfirmDialog('Delete "' + title + '" (ID: ' + id + ')? This cannot be undone.', function() {
        const fd = new FormData();
        fd.append('id', id);
        fd.append('listing_type', type);
        const headers = {};
        const token = getToken();
        if (token) headers['Authorization'] = 'Bearer ' + token;
        fetch('/admin/api/listing_crud.php?action=delete', { method: 'POST', headers: headers, body: fd })
            .then(r => r.json()).then(d => { if (d.success) window.location.reload(); else showAlertModal(d.error, 'error'); });
    });
}
function confirmDeleteContact(id) {
    showConfirmDialog('Delete this contact submission?', function() {
        const fd = new FormData(); fd.append('id', id);
        const headers = {};
        const token = getToken();
        if (token) headers['Authorization'] = 'Bearer ' + token;
        fetch('/admin/api/contact_crud.php?action=delete', { method: 'POST', headers: headers, body: fd })
            .then(r => r.json()).then(d => { if (d.success) window.location.href = 'contacts.php?deleted=1'; else showAlertModal(d.error, 'error'); });
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
var lsToken = localStorage.getItem('admin_access_token');
if (lsToken && !getToken()) { sessionStorage.setItem('admin_access_token', lsToken); }
localStorage.removeItem('admin_access_token');
document.querySelectorAll('#listingForm').forEach(function(form) {
    form.addEventListener('submit', handleListingForm);
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
    const price = fd.get('price');
    const officeType = fd.get('office_space_type');
    if (price && parseFloat(price) <= 0) {
        result.className = 'alert alert-danger mt-2'; result.textContent = 'Price must be greater than 0'; result.classList.remove('d-none');
        return;
    }
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
                    const page = window.location.pathname.split('/').pop().replace('.php', '');
                    window.location.href = page + '.php?saved=1';
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
