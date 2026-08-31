/**
 * Multi-select workspace enquiry — shared by listing pages.
 * Persists selections in localStorage (with sessionStorage fallback) across pagination.
 */
(function (global) {
    'use strict';

    var MAX_SELECTIONS = 15;
    var LEGACY_KEYS_MIGRATED = false;

    var MultiSelectEnquiry = {
        enabled: false,
        interest: 'managed',
        storageKey: 'cubespace_multi_select',
        selections: {},
        modal: null,
        _isSubmitting: false,
        _escDiv: null,

        init: function (config) {
            config = config || {};
            this.interest = config.interest || 'managed';
            this.storageKey = config.storageKey || ('cubespace_multi_select_' + this.interest);
            this.load();
            this.injectUI();
            this.bindToggle();
            this.updateUI();
            this._bindStorageSync();
        },

        // Central storage helper: prefers sessionStorage (per-tab, private for shared devices),
        // falls back to localStorage with TTL. Namespaced per storageKey.
        _getStorage: function () {
            try {
                if (typeof sessionStorage !== 'undefined' && sessionStorage) {
                    var probe = '__cubespace_probe__';
                    sessionStorage.setItem(probe, '1');
                    sessionStorage.removeItem(probe);
                    return sessionStorage;
                }
            } catch (e) { /* ignore */ }
            try {
                if (typeof localStorage !== 'undefined' && localStorage) {
                    var probe2 = '__cubespace_probe2__';
                    localStorage.setItem(probe2, '1');
                    localStorage.removeItem(probe2);
                    return localStorage;
                }
            } catch (e2) { /* ignore */ }
            return null;
        },
        _storageTTL: 30 * 60 * 1000, // 30 minutes

        _normalizeType: function (type) {
            type = (type || this.interest || 'managed').toString().trim().toLowerCase();
            if (type === 'commercial') type = 'furnished';
            if (type !== 'managed' && type !== 'furnished' && type !== 'unfurnished') type = this.interest;
            return type;
        },

        load: function () {
            var storage = this._getStorage();
            var raw = null;
            // TTL check for shared-device safety: expire selections after 30 min
            try {
                var tsRaw = storage ? storage.getItem(this.storageKey + '_ts') : null;
                if (tsRaw) {
                    var age = Date.now() - parseInt(tsRaw, 10);
                    if (!isNaN(age) && age > this._storageTTL) {
                        try { storage.removeItem(this.storageKey); storage.removeItem(this.storageKey + '_ts'); storage.removeItem(this.storageKey + '_mode'); } catch (ignore) {}
                        this.selections = {};
                        this.enabled = false;
                        return;
                    }
                }
            } catch (ignoreTs) {}
            try {
                raw = storage ? storage.getItem(this.storageKey) : null;
                this.selections = raw ? JSON.parse(raw) : {};
                if (typeof this.selections !== 'object' || this.selections === null || Array.isArray(this.selections)) {
                    this.selections = {};
                }
            } catch (e) {
                this.selections = {};
            }

            // Validate and sanitize shape; drop invalid entries; migrate legacy numeric keys
            var sanitized = {};
            var needsResave = false;
            try {
                var keys = Object.keys(this.selections);
                for (var i = 0; i < keys.length; i++) {
                    var k = keys[i];
                    var v = this.selections[k];
                    // Drop null/primitive entries
                    if (!v || typeof v !== 'object' || Array.isArray(v)) {
                        // Try to migrate legacy key like "123" without prefix
                        if (/^\d+$/.test(k)) {
                            needsResave = true;
                            continue;
                        }
                        needsResave = true;
                        continue;
                    }
                    if (!v.id || isNaN(parseInt(v.id, 10))) {
                        needsResave = true;
                        continue;
                    }
                    // Must have listing_type
                    var normalizedType = this._normalizeType(v.listing_type || v.listing_type_db);
                    // Rebuild key if mismatched or legacy numeric key
                    var expectedKey = normalizedType + ':' + String(parseInt(v.id, 10));
                    if (k !== expectedKey) {
                        // If k is numeric string (legacy) we migrate
                        if (/^\d+$/.test(k)) {
                            needsResave = true;
                        } else if (k !== expectedKey) {
                            // keep but normalize value
                            needsResave = true;
                        }
                    }
                    sanitized[expectedKey] = {
                        id: parseInt(v.id, 10),
                        title: (v.title || '').toString().slice(0, 200),
                        listing_code: (v.listing_code || '').toString().slice(0, 40),
                        listing_type: normalizedType
                    };
                }
                this.selections = sanitized;
                if (needsResave) this.save();
            } catch (e2) {
                this.selections = {};
            }

            try {
                var modeVal = storage ? storage.getItem(this.storageKey + '_mode') : null;
                this.enabled = modeVal === '1';
            } catch (err) {
                this.enabled = false;
            }
        },

        save: function () {
            var storage = this._getStorage();
            if (!storage) return;
            try {
                storage.setItem(this.storageKey, JSON.stringify(this.selections));
                storage.setItem(this.storageKey + '_mode', this.enabled ? '1' : '0');
                storage.setItem(this.storageKey + '_ts', String(Date.now()));
            } catch (e) {
                // Quota exceeded or private mode. Notify once.
                try { this.toast('Storage full: selections may not persist.', 'error'); } catch (ignore) {}
            }
        },

        _bindStorageSync: function () {
            var self = this;
            try {
                global.addEventListener('storage', function (e) {
                    if (!e || !e.key) return;
                    if (e.key === self.storageKey || e.key === self.storageKey + '_mode') {
                        self.load();
                        self.updateUI();
                        self.syncVisibleCards(document);
                    }
                });
            } catch (ignore) {}
        },

        count: function () {
            return Object.keys(this.selections).length;
        },

        officeKey: function (office) {
            var rawType = office.listing_type || office.listing_type_db || this.interest;
            var type = this._normalizeType(rawType);
            var idStr = String(office.id).trim();
            // Only allow numeric IDs; reject non-numeric to avoid injection
            if (!/^\d+$/.test(idStr)) idStr = String(parseInt(idStr, 10) || '');
            return type + ':' + idStr;
        },

        isSelected: function (id, listingType) {
            if (listingType) {
                var norm = this._normalizeType(listingType);
                return !!this.selections[this.officeKey({ id: id, listing_type: norm })];
            }
            // When listingType not provided, do NOT wildcard across types.
            // Return true only if any exact type-prefixed key matches id AND type equals current interest
            // This avoids false positives like managed:5 vs furnished:5.
            // For legacy migration, caller should always pass listingType.
            var idStr = String(id);
            // Fallback: check if any key ends with ':'+idStr
            // Only for backward compat with callers that omit type (e.g., old renderCardShell); treat as same-interest check
            var keys = Object.keys(this.selections);
            for (var i = 0; i < keys.length; i++) {
                var parts = keys[i].split(':');
                if (parts.length === 2 && parts[1] === idStr) {
                    // Only consider it selected if the type matches current interest (reduces cross-type false positive)
                    if (parts[0] === this._normalizeType(this.interest)) return true;
                    // If interest is furnished page that holds both furnished/unfurnished, allow either
                    if (this.storageKey.indexOf('furnished') !== -1 && (parts[0] === 'furnished' || parts[0] === 'unfurnished')) return true;
                }
            }
            return false;
        },

        getSelections: function () {
            var self = this;
            return Object.keys(this.selections).map(function (k) { return self.selections[k]; });
        },

        toggleMode: function () {
            this.enabled = !this.enabled;
            this.save();
            // Sync before UI to avoid stale hint
            this.syncVisibleCards(document);
            this.updateUI();
            if (typeof global.loadListings === 'function') {
                global.loadListings();
            }
        },

        addOffice: function (office) {
            if (!office || office.id === undefined || office.id === null || String(office.id).trim() === '') return false;
            var idStr = String(office.id).trim();
            if (!/^\d+$/.test(idStr)) return false;
            var key = this.officeKey(office);
            if (this.selections[key]) return true;
            if (this.count() >= MAX_SELECTIONS) {
                this.toast('You can select up to ' + MAX_SELECTIONS + ' workspaces at a time.', 'error');
                return false;
            }
            var listingType = this._normalizeType(office.listing_type || office.listing_type_db || this.interest);
            // Sanitize title/code (strip tags, limit length)
            var safeTitle = (office.title || '').toString().replace(/<[^>]*>/g, '').slice(0, 200).trim();
            var safeCode = (office.listing_code || '').toString().replace(/<[^>]*>/g, '').slice(0, 40).trim();
            this.selections[key] = {
                id: parseInt(idStr, 10),
                title: safeTitle,
                listing_code: safeCode,
                listing_type: listingType
            };
            this.save();
            this.syncCardState(office.id, null, listingType);
            this.updateUI();
            return true;
        },

        removeOffice: function (id, listingType) {
            if (id === undefined || id === null || String(id).trim() === '') return;
            if (listingType) {
                delete this.selections[this.officeKey({ id: id, listing_type: listingType })];
            } else {
                // Do NOT wildcard delete across types. If type missing, only delete the key that matches current interest.
                // This prevents deleting furnished:5 and unfurnished:5 together when one is removed.
                var fallbackType = this._normalizeType(this.interest);
                var fallbackKey = fallbackType + ':' + String(id).trim();
                if (this.selections[fallbackKey]) {
                    delete this.selections[fallbackKey];
                } else {
                    // Backward compat: if no fallback found but exactly one key matches numeric id, delete that single one
                    var matches = [];
                    var needle = ':' + String(id).trim();
                    Object.keys(this.selections).forEach(function (k) {
                        if (k.endsWith(needle)) matches.push(k);
                    });
                    if (matches.length === 1) {
                        delete MultiSelectEnquiry.selections[matches[0]];
                    } else if (matches.length > 1) {
                        // Ambiguous - do not delete anything to avoid destructive wipe; caller should provide listingType
                        // Optionally toast
                    }
                }
            }
            this.save();
            this.syncCardState(id, null, listingType);
            this.updateUI();
        },

        clearAll: function () {
            this.selections = {};
            this.save();
            // Close modal preview if open
            if (this.modal) {
                try {
                    var modalEl = document.getElementById('multiEnquiryModal');
                    if (modalEl && modalEl.classList.contains('show')) this.modal.hide();
                } catch (e) {}
            }
            this.syncVisibleCards(document);
            this.updateUI();
        },

        renderCardShell: function (opts) {
            var id = opts.id;
            var slug = opts.slug || '';
            var listingType = this._normalizeType(opts.listingType || this.interest);
            var innerHtml = opts.innerHtml || '';
            var ariaLabel = opts.ariaLabel || '';
            var selected = this.isSelected(id, listingType);
            var selectedClass = selected ? ' ws-selected' : '';
            var checked = selected ? ' checked' : '';
            // Use CSS.escape for selector safety later; escape HTML for attributes
            var escId = this.esc(String(id));
            var escSlug = this.esc(slug);
            var escType = this.esc(listingType);
            var escLabel = this.esc(ariaLabel);
            // Fix nested interactive: wrapper is always group (never link) to avoid link containing buttons/checkbox.
            // Navigation is handled via JS on .ws-card-content, not via link semantics.
            var safeTitle = this.esc(String(id) ? (ariaLabel.replace(/^View details for\s*/i, '') || slug) : '');
            var checkLabel = safeTitle ? 'Select workspace: ' + safeTitle : 'Select workspace';
            return (
                '<div class="card custom-card shadow-sm profile-card' + selectedClass + '" data-office-id="' + escId + '" data-slug="' + escSlug + '" data-listing-type="' + escType + '" role="group" aria-label="' + escLabel + '">' +
                    '<div class="ws-card-select-row">' +
                        '<div class="ws-select-col">' +
                            '<label class="ws-select-label" for="ws-cb-' + escId + '-' + escType + '">' +
                                '<input type="checkbox" class="ws-select-cb" id="ws-cb-' + escId + '-' + escType + '" data-office-id="' + escId + '" data-listing-type="' + escType + '"' + checked + ' aria-label="' + checkLabel + '">' +
                            '</label>' +
                        '</div>' +
                        '<div class="ws-card-content" tabindex="0" role="button" aria-label="' + escLabel + '">' + innerHtml + '</div>' +
                    '</div>' +
                '</div>'
            );
        },

        afterRender: function (container) {
            if (!container) return;
            this.syncVisibleCards(container);
            this.bindCardCheckboxes(container);
            this.bindCardNavigation(container);
            // Refresh hidden hint after listings re-render (filter/pagination may hide selections)
            this.updateUI();
        },

        bindCardCheckboxes: function (container) {
            var self = this;
            container.querySelectorAll('.ws-select-cb').forEach(function (cb) {
                if (cb.dataset.mseBound) return;
                cb.dataset.mseBound = '1';
                cb.addEventListener('change', function (e) {
                    e.stopPropagation();
                    var card = cb.closest('.custom-card');
                    if (!card) return;
                    var office = self.officeFromCard(card);
                    if (cb.checked) {
                        if (!self.addOffice(office)) {
                            cb.checked = false;
                        }
                    } else {
                        self.removeOffice(office.id, office.listing_type);
                    }
                });
                // click stopPropagation handled via shouldIgnoreClick; no extra listener needed
            });
        },

        bindCardNavigation: function (container) {
            container.querySelectorAll('.custom-card').forEach(function (card) {
                if (card.dataset.mseNavBound) return;
                card.dataset.mseNavBound = '1';
                var content = card.querySelector('.ws-card-content') || card;
                var navHandler = function (e) {
                    if (MultiSelectEnquiry.shouldIgnoreClick(e)) return;
                    // In multi-select mode, click toggles selection instead of navigating
                    if (MultiSelectEnquiry.enabled) {
                        var cb = card.querySelector('.ws-select-cb');
                        if (cb) {
                            cb.checked = !cb.checked;
                            cb.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                        return;
                    }
                    if (global.navigateTo) global.navigateTo('office_detail.php?slug=' + card.dataset.slug + '&type=' + card.dataset.listingType);
                    else global.location.href = 'office_detail.php?slug=' + card.dataset.slug + '&type=' + card.dataset.listingType;
                };
                // Click on content or card
                content.addEventListener('click', navHandler);
                // Keyboard on content (which is focusable) and also card for backwards compat
                var keyHandler = function (e) {
                    if (e.key !== 'Enter' && e.key !== ' ') return;
                    if (e.target.closest && e.target.closest('.ws-select-cb')) return;
                    if (e.target.closest && e.target.closest('.ws-select-label')) return;
                    if (MultiSelectEnquiry.shouldIgnoreClick(e)) return;
                    e.preventDefault();
                    if (MultiSelectEnquiry.enabled) {
                        var cb2 = card.querySelector('.ws-select-cb');
                        if (cb2) {
                            cb2.checked = !cb2.checked;
                            cb2.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                        return;
                    }
                    if (global.navigateTo) global.navigateTo('office_detail.php?slug=' + card.dataset.slug + '&type=' + card.dataset.listingType);
                    else global.location.href = 'office_detail.php?slug=' + card.dataset.slug + '&type=' + card.dataset.listingType;
                };
                content.addEventListener('keydown', keyHandler);
                // Also keep card keydown for focus that lands on card itself (legacy)
                card.addEventListener('keydown', function (e) {
                    if (e.target !== card) return;
                    keyHandler(e);
                });
            });
        },

        shouldIgnoreClick: function (e) {
            return !!(e.target.closest('.carousel-btn') ||
                e.target.closest('.carousel-dots') ||
                e.target.closest('.btn-get-price') ||
                e.target.closest('.description-toggle') ||
                e.target.closest('.ws-select-col') ||
                e.target.closest('.ws-select-cb') ||
                e.target.closest('.ws-select-label'));
        },

        officeFromCard: function (card) {
            var id = parseInt(card.dataset.officeId, 10);
            var titleEl = card.querySelector('.card-title, .property-name');
            var title = '';
            if (titleEl) {
                // Clone to avoid mutating DOM; remove code/badge children before extracting text
                var clone = titleEl.cloneNode(true);
                var codeEl2 = clone.querySelector('code');
                if (codeEl2) codeEl2.remove();
                title = clone.textContent.trim().replace(/\s+/g, ' ').slice(0, 200);
            }
            var codeEl = card.querySelector('.card-title code');
            if (!codeEl) codeEl = card.querySelector('[data-listing-code]');
            var listingCode = '';
            if (codeEl) {
                // Prefer data attribute if present
                listingCode = (codeEl.getAttribute('data-listing-code') || codeEl.textContent || '').trim().slice(0, 40);
                if (!listingCode) {
                    var raw = card.querySelector('.card-title code');
                    listingCode = raw ? raw.textContent.trim().slice(0, 40) : '';
                }
            } else {
                // Fallback: extract from dataset on button
                var btn = card.querySelector('[data-listing-code]');
                if (btn) listingCode = (btn.getAttribute('data-listing-code') || '').trim().slice(0, 40);
            }
            return {
                id: id,
                title: title,
                listing_code: listingCode,
                listing_type: this._normalizeType(card.dataset.listingType || this.interest)
            };
        },

        syncVisibleCards: function (root) {
            var self = this;
            // Handle case where root is document
            var cards = root.querySelectorAll ? root.querySelectorAll('.custom-card[data-office-id]') : [];
            cards.forEach(function (card) {
                self.syncCardState(card.dataset.officeId, card, card.dataset.listingType);
            });
        },

        _cssEscape: function (str) {
            if (global.CSS && typeof global.CSS.escape === 'function') return global.CSS.escape(str);
            return String(str).replace(/["\\]/g, '\\$&');
        },

        syncCardState: function (id, card, listingType) {
            var normType = listingType ? this._normalizeType(listingType) : null;
            var idStr = String(id);
            var escId = this._cssEscape(idStr);
            // Always sync ALL matching cards, not just first
            var selector = '.custom-card[data-office-id="' + escId + '"]';
            if (normType) selector += '[data-listing-type="' + this._cssEscape(normType) + '"]';
            var cards = document.querySelectorAll(selector);
            // If no exact type match but we searched with type, fallback to all ids for graceful degradation
            if (cards.length === 0 && normType) {
                cards = document.querySelectorAll('.custom-card[data-office-id="' + escId + '"]');
            }
            // If a specific card was passed, just sync that one plus any duplicates
            var targets = [];
            if (card) {
                targets.push(card);
                // Also include duplicates with same id+type
                cards.forEach(function (c) { if (c !== card) targets.push(c); });
            } else {
                cards.forEach(function (c) { targets.push(c); });
            }
            if (targets.length === 0) return;
            targets.forEach(function (c) {
                var type = c.dataset.listingType ? MultiSelectEnquiry._normalizeType(c.dataset.listingType) : normType;
                var useType = normType || type;
                var selected = MultiSelectEnquiry.isSelected(idStr, useType);
                c.classList.toggle('ws-selected', selected);
                try { c.setAttribute('aria-selected', selected ? 'true' : 'false'); } catch (ignore) {}
                var cb = c.querySelector('.ws-select-cb');
                if (cb) {
                    cb.checked = selected;
                    cb.setAttribute('aria-checked', selected ? 'true' : 'false');
                }
                var content = c.querySelector('.ws-card-content');
                if (content) content.setAttribute('aria-pressed', selected ? 'true' : 'false');
            });
        },

        updateUI: function () {
            document.body.classList.toggle('multi-select-active', this.enabled);

            var btn = document.getElementById('btnToggleMultiSelect');
            if (btn) {
                btn.classList.toggle('active', this.enabled);
                btn.setAttribute('aria-pressed', this.enabled ? 'true' : 'false');
                btn.innerHTML = this.enabled
                    ? '<i class="fa-solid fa-xmark me-1"></i> Exit Multi Select'
                    : '<i class="fa-solid fa-list-check me-1"></i> Multi Select Enquiry';
                // Announce state change for SR (aria-live)
                var live = document.getElementById('mseLiveRegion');
                if (!live) {
                    live = document.createElement('div');
                    live.id = 'mseLiveRegion';
                    live.setAttribute('aria-live', 'polite');
                    live.setAttribute('aria-atomic', 'true');
                    live.className = 'visually-hidden';
                    live.style.cssText = 'position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;';
                    document.body.appendChild(live);
                }
                live.textContent = this.enabled ? 'Multi select mode enabled. Use checkboxes to select workspaces.' : 'Multi select mode disabled.';
            }

            var count = this.count();
            var bar = document.getElementById('multiEnquiryBar');
            if (bar) {
                bar.classList.toggle('visible', count > 0);
                bar.setAttribute('aria-hidden', count > 0 ? 'false' : 'true');
                document.body.classList.toggle('multi-enquiry-bar-open', count > 0);
            }

            var countEl = document.getElementById('multiEnquiryCount');
            if (countEl) countEl.textContent = String(count);

            // Stale hidden selections warning: only show if some selections are not present in current DOM
            var hintEl = document.getElementById('multiEnquiryHiddenHint');
            if (hintEl) {
                try {
                    if (count === 0) {
                        hintEl.textContent = '';
                        hintEl.style.display = 'none';
                        var bar0 = document.getElementById('multiEnquiryBar');
                        if (bar0) bar0.setAttribute('aria-label', 'Selected workspaces');
                    } else {
                        var listingsContainer = document.getElementById('listingsContainer');
                        var isLoading = listingsContainer && (listingsContainer.querySelector('.skeleton-card') || listingsContainer.textContent.indexOf('Loading') !== -1);
                        if (isLoading) {
                            // Don't show false warning while listings are loading
                            hintEl.textContent = '';
                            hintEl.style.display = 'none';
                        } else {
                            // Count distinct selections that are currently rendered in DOM (visible on this page/filter)
                            var selections = this.getSelections();
                            var visibleDistinct = 0;
                            for (var h = 0; h < selections.length; h++) {
                                var s = selections[h];
                                var sel = '.custom-card[data-office-id="' + this._cssEscape(String(s.id)) + '"][data-listing-type="' + this._cssEscape(s.listing_type) + '"]';
                                if (document.querySelector(sel)) visibleDistinct++;
                                else {
                                    // Fallback without type (for legacy)
                                    var sel2 = '.custom-card[data-office-id="' + this._cssEscape(String(s.id)) + '"]';
                                    if (document.querySelector(sel2)) visibleDistinct++;
                                }
                            }
                            var hidden = count - visibleDistinct;
                            if (hidden > 0) {
                                var plural = hidden === 1 ? 'workspace is' : 'workspaces are';
                                hintEl.textContent = hidden + ' selected ' + plural + ' not visible due to current filters/pagination. Review before sending.';
                                hintEl.style.display = 'block';
                                var bar2 = document.getElementById('multiEnquiryBar');
                                if (bar2) bar2.setAttribute('aria-label', 'Selected workspaces (' + hidden + ' hidden by filters)');
                            } else {
                                hintEl.textContent = '';
                                hintEl.style.display = 'none';
                                var bar3 = document.getElementById('multiEnquiryBar');
                                if (bar3) bar3.setAttribute('aria-label', 'Selected workspaces');
                            }
                        }
                    }
                } catch (ignoreHint) {}
            }

            this.renderSelectedList();
        },

        renderSelectedList: function () {
            var listEl = document.getElementById('multiEnquirySelectedList');
            var previewEl = document.getElementById('multiEnquiryPreview');
            var selections = this.getSelections();
            var html = '';

            // Single-pass build for list
            selections.forEach(function (o) {
                var label = MultiSelectEnquiry.esc(o.title);
                if (o.listing_code) label += ' <span class="text-muted">(' + MultiSelectEnquiry.esc(o.listing_code) + ')</span>';
                // Use data attributes for delegation
                html += '<div class="selected-item" role="listitem"><span>' + label + '</span>' +
                    '<button type="button" class="remove-item" data-remove-id="' + MultiSelectEnquiry.esc(String(o.id)) + '" data-remove-type="' + MultiSelectEnquiry.esc(o.listing_type) + '" aria-label="Remove ' + MultiSelectEnquiry.esc(o.title) + '"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button></div>';
            });

            if (listEl) {
                // Use delegation: set innerHTML once, listener is on parent (bound once)
                listEl.innerHTML = html;
                listEl.setAttribute('role', 'list');
                // Bind delegated listener once
                if (!listEl.dataset.mseDelegated) {
                    listEl.dataset.mseDelegated = '1';
                    listEl.addEventListener('click', function (e) {
                        var btn = e.target.closest('[data-remove-id]');
                        if (!btn || !listEl.contains(btn)) return;
                        MultiSelectEnquiry.removeOffice(
                            btn.getAttribute('data-remove-id'),
                            btn.getAttribute('data-remove-type')
                        );
                    });
                }
            }

            if (previewEl) {
                if (!selections.length) {
                    previewEl.innerHTML = '<p class="text-muted mb-0 small">No workspaces selected.</p>';
                } else {
                    previewEl.innerHTML = '<ul class="mb-0 ps-3" role="list">' + selections.map(function (o) {
                        var t = MultiSelectEnquiry.esc(o.title);
                        if (o.listing_code) t += ' (' + MultiSelectEnquiry.esc(o.listing_code) + ')';
                        return '<li role="listitem">' + t + '</li>';
                    }).join('') + '</ul>';
                }
            }
        },

        openModal: function () {
            if (this.count() < 1) {
                this.toast('Please select at least one workspace.', 'error');
                return;
            }
            if (!this.enabled) {
                this.enabled = true;
                this.save();
                this.updateUI();
                this.syncVisibleCards(document);
            }
            this.renderSelectedList();
            // Set spam-timing token
            var tsEl = document.getElementById('mseTs');
            if (tsEl) tsEl.value = String(Date.now());
            if (this.modal) this.modal.show();
        },

        handleSubmit: function (event) {
            event.preventDefault();
            // Fallback validation if CSForms not present
            if (global.CSForms && typeof global.CSForms.validate === 'function') {
                if (!global.CSForms.validate(event.target)) return;
            } else if (typeof event.target.checkValidity === 'function' && !event.target.checkValidity()) {
                event.target.reportValidity();
                return;
            }

            var selections = this.getSelections();
            if (!selections.length) {
                this.toast('Please select at least one workspace.', 'error');
                return;
            }
            if (this._isSubmitting) return;

            var form = event.target;
            var btn = document.getElementById('meSubmitBtn');
            // Clear honeypot field in case browser autofilled it
            var honeypot = form.querySelector('[name="website"]');
            if (honeypot) honeypot.value = '';
            var formData = new FormData(form);
            // Derive interest: if mixed types, use 'commercial' for correct backend grouping
            var distinctTypes = {};
            selections.forEach(function (s) { distinctTypes[s.listing_type] = 1; });
            var typeKeys = Object.keys(distinctTypes);
            var derivedInterest = this.interest;
            if (typeKeys.length > 1) derivedInterest = 'commercial';
            else if (typeKeys.length === 1) derivedInterest = typeKeys[0];
            formData.set('interest', derivedInterest);
            formData.set('source', 'multi_select_enquiry');
            formData.set('offices_json', JSON.stringify(selections));
            formData.delete('office_id');
            formData.delete('listing_code');

            if (btn) {
                btn.disabled = true;
                btn.setAttribute('aria-busy', 'true');
                btn.dataset.mseOrigHtml = btn.innerHTML;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Sending...';
            }
            this._isSubmitting = true;

            var self = this;
            this.postContact(formData).then(function (data) {
                self._isSubmitting = false;
                if (btn) {
                    btn.disabled = false;
                    btn.removeAttribute('aria-busy');
                    btn.innerHTML = btn.dataset.mseOrigHtml || '<i class="fa-solid fa-paper-plane"></i> Send Multi Enquiry';
                }
                if (data && data.success) {
                    form.reset();
                    // Clear before hiding to avoid flash behind backdrop; wait for hidden event if possible
                    var doAfterHide = function () {
                        self.clearAll();
                        self.enabled = false;
                        self.save();
                        self.updateUI();
                        if (typeof global.loadListings === 'function') global.loadListings();
                    };
                    if (self.modal) {
                        var modalEl = document.getElementById('multiEnquiryModal');
                        var handler = function () {
                            try { modalEl.removeEventListener('hidden.bs.modal', handler); } catch (ignore) {}
                            doAfterHide();
                        };
                        try {
                            if (modalEl) modalEl.addEventListener('hidden.bs.modal', handler);
                            self.modal.hide();
                            // Fallback if event not fired (no bootstrap transition)
                            setTimeout(function () {
                                try { modalEl.removeEventListener('hidden.bs.modal', handler); } catch (ignore2) {}
                                // Only run if selections not already cleared
                                if (self.count() > 0) doAfterHide();
                            }, 600);
                        } catch (e) {
                            doAfterHide();
                        }
                    } else {
                        doAfterHide();
                    }
                    if (global.showAlertModal) {
                        global.showAlertModal('Thank you! Your enquiry for ' + selections.length + ' workspace(s) has been submitted. Our expert will get back to you shortly.', 'success');
                    }
                } else {
                    MultiSelectEnquiry.toast((data && data.message) || 'Failed to send enquiry.', 'error');
                }
            }).catch(function (err) {
                self._isSubmitting = false;
                if (btn) {
                    btn.disabled = false;
                    btn.removeAttribute('aria-busy');
                    btn.innerHTML = btn.dataset.mseOrigHtml || '<i class="fa-solid fa-paper-plane"></i> Send Multi Enquiry';
                }
                MultiSelectEnquiry.toast((err && err.message) || 'Network error. Please try again.', 'error');
            });
        },

        postContact: function (formData) {
            var path = '/api/contact.php';
            if (typeof global.apiUrl === 'function') path = global.apiUrl(path);
            // Prefer CubeAPI which handles base path correctly, else fallback to apiUrl
            if (global.CubeAPI && typeof global.CubeAPI.postForm === 'function') return global.CubeAPI.postForm(path, formData);
            return fetch(path, { method: 'POST', body: formData, credentials: 'same-origin' }).then(function (r) { return r.json(); });
        },

        toast: function (msg, type) {
            if (global.showToast) { global.showToast(msg, type || 'info'); return; }
            if (global.CubeToast) {
                var fn = type === 'error' ? 'error' : 'success';
                if (typeof global.CubeToast[fn] === 'function') { global.CubeToast[fn](msg); return; }
            }
            // Non-blocking fallback instead of alert
            try {
                var container = document.getElementById('toastContainer');
                if (!container) {
                    container = document.createElement('div');
                    container.id = 'toastContainer';
                    container.className = 'toast-container';
                    container.setAttribute('aria-live', 'polite');
                    document.body.appendChild(container);
                }
                var t = document.createElement('div');
                t.className = 'toast toast-' + (type || 'info');
                t.setAttribute('role', 'status');
                t.textContent = msg;
                container.appendChild(t);
                setTimeout(function () { if (t.parentNode) t.parentNode.removeChild(t); }, 4000);
            } catch (e) {
                // Last resort
                try { console.warn(msg); } catch (ignore2) {}
            }
        },

        esc: function (str) {
            if (str === null || str === undefined) return '';
            // Allow 0 and false to render correctly; empty string stays empty
            var s = String(str);
            if (s === '') return '';
            if (!this._escDiv) this._escDiv = document.createElement('div');
            this._escDiv.textContent = s;
            return this._escDiv.innerHTML;
        },

        bindToggle: function () {
            var self = this;
            // Idempotent per-element guard, but also supports recreation (delegated fallback)
            var btn = document.getElementById('btnToggleMultiSelect');
            if (btn && !btn.dataset.mseBound) {
                btn.dataset.mseBound = '1';
                btn.addEventListener('click', function () { self.toggleMode(); });
            }

            var sendBtn = document.getElementById('btnMultiEnquiry');
            if (sendBtn && !sendBtn.dataset.mseBound) {
                sendBtn.dataset.mseBound = '1';
                sendBtn.addEventListener('click', function () { self.openModal(); });
            }

            var clearBtn = document.getElementById('btnClearSelections');
            if (clearBtn && !clearBtn.dataset.mseBound) {
                clearBtn.dataset.mseBound = '1';
                clearBtn.addEventListener('click', function () { self.clearAll(); });
            }

            var form = document.getElementById('multiEnquiryForm');
            if (form && !form.dataset.mseBound) {
                form.dataset.mseBound = '1';
                form.addEventListener('submit', function (e) { self.handleSubmit(e); });
            }
        },

        injectUI: function () {
            // Idempotent: if bar exists, update its aria and ensure listeners are bound; handle SPA recreation case
            var existingBar = document.getElementById('multiEnquiryBar');
            if (!existingBar) {
                var bar = document.createElement('div');
                bar.id = 'multiEnquiryBar';
                bar.setAttribute('role', 'region');
                bar.setAttribute('aria-label', 'Selected workspaces');
                bar.setAttribute('aria-live', 'polite');
                bar.setAttribute('aria-hidden', 'true');
                bar.innerHTML =
                    '<div class="multi-enquiry-bar-inner">' +
                        '<div>' +
                            '<div class="multi-enquiry-bar-count"><strong id="multiEnquiryCount">0</strong> workspace(s) selected</div>' +
                            '<div id="multiEnquirySelectedList" role="list"></div>' +
                            '<div id="multiEnquiryHiddenHint" class="small text-warning mt-1" style="display:none" aria-live="polite"></div>' +
                        '</div>' +
                        '<div class="multi-enquiry-bar-actions">' +
                            '<button type="button" class="btn btn-sm btn-outline-secondary" id="btnClearSelections">Clear</button>' +
                            '<button type="button" class="btn btn-sm btn-primary" id="btnMultiEnquiry"><i class="fa-solid fa-paper-plane me-1"></i> Send Multi Enquiry</button>' +
                        '</div>' +
                    '</div>';
                document.body.appendChild(bar);
            }

            if (!document.getElementById('multiEnquiryModal')) {
                var modal = document.createElement('div');
                modal.className = 'modal fade';
                modal.id = 'multiEnquiryModal';
                modal.tabIndex = -1;
                modal.setAttribute('aria-labelledby', 'multiEnquiryModalLabel');
                modal.setAttribute('aria-hidden', 'true');
                modal.innerHTML =
                    '<div class="modal-dialog modal-dialog-centered modal-lg">' +
                        '<div class="modal-content border-0 shadow-lg">' +
                            '<div class="modal-header border-0 pb-0">' +
                                '<h3 class="modal-title fw-bold fs-5" id="multiEnquiryModalLabel"><i class="fa-solid fa-list-check text-primary me-2"></i>Multi Workspace Enquiry</h3>' +
                                '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>' +
                            '</div>' +
                            '<div class="modal-body pt-2">' +
                                '<p class="text-muted small mb-3">Submit one enquiry for all selected workspaces. Our expert will share the best options with you.</p>' +
                                '<div class="mb-3">' +
                                    '<label class="form-label fw-semibold small">Selected Workspaces</label>' +
                                    '<div id="multiEnquiryPreview" class="selected-workspaces-preview" role="region" aria-live="polite"></div>' +
                                '</div>' +
                                '<form id="multiEnquiryForm" novalidate>' +
                                    '<div class="mb-3">' +
                                        '<label for="meName" class="form-label fw-semibold small">Full Name *</label>' +
                                        '<input type="text" class="form-control" id="meName" name="name" required data-rules="required|max:120" placeholder="Enter your name" autocomplete="name">' +
                                    '</div>' +
                                    '<div class="mb-3">' +
                                        '<label for="mePhone" class="form-label fw-semibold small">Phone *</label>' +
                                        '<input type="tel" class="form-control" id="mePhone" name="phone" required data-rules="required|phone" maxlength="15" placeholder="10-digit mobile ( +91 allowed )" autocomplete="tel" inputmode="tel">' +
                                    '</div>' +
                                    '<div class="mb-3">' +
                                        '<label for="meEmail" class="form-label fw-semibold small">Email</label>' +
                                        '<input type="email" class="form-control" id="meEmail" name="email" data-rules="email|max:180" placeholder="email@example.com" autocomplete="email">' +
                                    '</div>' +
                                    '<div class="mb-3">' +
                                        '<label for="meMessage" class="form-label fw-semibold small">Message (optional)</label>' +
                                        '<textarea class="form-control" id="meMessage" name="message" data-rules="max:1000" rows="3" placeholder="Tell us about your requirements..."></textarea>' +
                                    '</div>' +
                                    '<input type="hidden" name="mse_ts" id="mseTs" value="">' +
                                    '<div aria-hidden="true" style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden;">' +
                                        '<label for="meWebsite">Leave this empty</label>' +
                                        '<input type="text" id="meWebsite" name="website" value="" tabindex="-1" autocomplete="new-password">' +
                                    '</div>' +
                                    '<button type="submit" class="btn btn-primary w-100" id="meSubmitBtn"><i class="fa-solid fa-paper-plane"></i> Send Multi Enquiry</button>' +
                                '</form>' +
                            '</div>' +
                        '</div>' +
                    '</div>';
                document.body.appendChild(modal);
            }

            var modalEl = document.getElementById('multiEnquiryModal');
            if (modalEl && global.bootstrap) {
                // Singleton: reuse existing instance if present (handles soft navigation)
                try {
                    var existing = global.bootstrap.Modal.getInstance ? global.bootstrap.Modal.getInstance(modalEl) : null;
                    this.modal = existing || new global.bootstrap.Modal(modalEl);
                } catch (e) {
                    try { this.modal = new global.bootstrap.Modal(modalEl); } catch (ignore2) {}
                }
            }

            this.bindToggle();
            // Phone sanitization: allow +, digits, spaces, dashes (normalized on submit)
            var phoneEl = document.getElementById('mePhone');
            if (phoneEl && !phoneEl.dataset.msePhoneBound) {
                phoneEl.dataset.msePhoneBound = '1';
                phoneEl.addEventListener('input', function () {
                    var v = this.value;
                    // Keep + at start, digits, spaces, dashes
                    var cleaned = v.replace(/[^0-9+\s\-]/g, '');
                    if (cleaned.length > 15) cleaned = cleaned.slice(0, 15);
                    if (cleaned !== v) this.value = cleaned;
                });
            }
        }
    };

    global.MultiSelectEnquiry = MultiSelectEnquiry;
})(window);
