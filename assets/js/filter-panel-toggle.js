/**
 * Collapsible filter panel for listing pages (mobile).
 * Desktop: filters always visible; toggle hidden.
 */
(function (global) {
    'use strict';

    var MOBILE_MQ = window.matchMedia('(max-width: 767.98px)');

    var FilterPanelToggle = {
        panel: null,
        toggle: null,
        body: null,
        badge: null,
        storageKey: 'cubespace_filters_open',

        init: function (config) {
            config = config || {};
            this.panel = document.getElementById('filtersPanel');
            this.toggle = document.getElementById('filtersPanelToggle');
            this.body = document.getElementById('filtersPanelBody');
            this.badge = document.getElementById('filtersPanelBadge');
            if (!this.panel || !this.toggle || !this.body) return;

            this.storageKey = config.storageKey || this.storageKey;
            this.loadState();
            this.bindEvents();
            this.applyResponsiveState();
            this.patchActiveFilters();
            MOBILE_MQ.addEventListener('change', this.applyResponsiveState.bind(this));
        },

        isMobile: function () {
            return MOBILE_MQ.matches;
        },

        loadState: function () {
            var open = false;
            try {
                open = sessionStorage.getItem(this.storageKey) === '1';
            } catch (e) { /* ignore */ }
            this.setOpen(open, false);
        },

        saveState: function () {
            try {
                sessionStorage.setItem(this.storageKey, this.panel.classList.contains('is-open') ? '1' : '0');
            } catch (e) { /* ignore */ }
        },

        setOpen: function (open, save) {
            if (typeof save === 'undefined') save = true;
            this.panel.classList.toggle('is-open', open);
            this.toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (save && this.isMobile()) this.saveState();
        },

        togglePanel: function () {
            if (!this.isMobile()) return;
            this.setOpen(!this.panel.classList.contains('is-open'));
        },

        applyResponsiveState: function () {
            if (!this.isMobile()) {
                this.panel.classList.add('is-open');
                this.toggle.setAttribute('aria-expanded', 'true');
                return;
            }
            var open = false;
            try {
                open = sessionStorage.getItem(this.storageKey) === '1';
            } catch (e) { /* ignore */ }
            this.setOpen(open, false);
        },

        bindEvents: function () {
            var self = this;
            this.toggle.addEventListener('click', function () {
                self.togglePanel();
            });

            // Close panel after search on mobile
            var searchBtn = this.panel.querySelector('.filter-bar .btn-primary');
            if (searchBtn) {
                searchBtn.addEventListener('click', function () {
                    if (self.isMobile()) {
                        setTimeout(function () { self.setOpen(false); }, 150);
                    }
                });
            }
        },

        syncBadge: function () {
            if (!this.badge) return;
            var count = document.querySelectorAll('#activeFilters .filter-tag').length;
            if (count > 0) {
                this.badge.textContent = String(count);
                this.badge.hidden = false;
            } else {
                this.badge.hidden = true;
            }
        },

        patchActiveFilters: function () {
            var self = this;
            if (typeof global.updateActiveFilters !== 'function') return;
            if (global.updateActiveFilters.__filterPanelPatched) {
                self.syncBadge();
                return;
            }
            var orig = global.updateActiveFilters;
            global.updateActiveFilters = function () {
                orig();
                self.syncBadge();
            };
            global.updateActiveFilters.__filterPanelPatched = true;
            self.syncBadge();
        }
    };

    global.FilterPanelToggle = FilterPanelToggle;
})(window);
