(function() {
    'use strict';

    function onReady(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    onReady(function() {
        document.querySelectorAll('.navbar .offcanvas .nav-link:not(.dropdown-toggle), .navbar .offcanvas .dropdown-item').forEach(function(link) {
            link.addEventListener('click', function() {
                var panel = link.closest('.offcanvas.show');
                if (!panel || !window.bootstrap || !window.bootstrap.Offcanvas) return;
                window.bootstrap.Offcanvas.getOrCreateInstance(panel).hide();
            });
        });
    });

    // Promo banner close
    var promoClose = document.querySelector('.promo-close');
    if (promoClose) {
        promoClose.addEventListener('click', function() {
            var banner = document.getElementById('promoBanner');
            if (banner) banner.style.display = 'none';
        });
    }

    // FAQ accordion
    document.querySelectorAll('.faq-question').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var item = btn.parentElement;
            item.classList.toggle('open');
        });
    });

    // Overview read more
    window.toggleOverview = function() {
        var text = document.getElementById('overviewText');
        if (!text) return;
        var btn = text.nextElementSibling;
        text.classList.toggle('collapsed');
        btn.innerHTML = text.classList.contains('collapsed')
            ? 'Read more'
            : 'Show less';
    };

    // Sticky nav scroll
    var stickyNav = document.getElementById('stickyNav');
    var bottomSticky = document.getElementById('bottomSticky');
    if (stickyNav) {
        window.addEventListener('scroll', function() {
            stickyNav.classList.toggle('scrolled', window.scrollY > 200);
            if (bottomSticky) bottomSticky.classList.toggle('visible', window.scrollY > 600);
        });
    }

    // Tab active state with scroll tracking
    var sections = ['overview', 'amenities', 'pricing'];
    function updateActiveTab() {
        var current = sections[0];
        for (var i = 0; i < sections.length; i++) {
            var el = document.getElementById(sections[i]);
            if (el && el.getBoundingClientRect().top <= 140) current = sections[i];
        }
        document.querySelectorAll('.nav-tab').forEach(function(tab) {
            tab.classList.toggle('active', tab.getAttribute('href') === '#' + current);
        });
    }
    window.addEventListener('scroll', updateActiveTab);

    document.querySelectorAll('.nav-tab').forEach(function(tab) {
        tab.addEventListener('click', function(e) {
            document.querySelectorAll('.nav-tab').forEach(function(t) { t.classList.remove('active'); });
            tab.classList.add('active');
        });
    });

    // Sidebar form submission (via CubeAPI)
    window.handleSidebarForm = function(e) {
        var form = e.target;
        e.preventDefault();
        if (window.CSForms && !CSForms.validate(form)) {
            return;
        }
        var btn = form.querySelector('.btn-submit');
        var formData = new FormData(form);

        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Sending...';

        var request = window.CubeAPI && typeof CubeAPI.postForm === 'function'
            ? CubeAPI.postForm((window.CubeBase && CubeBase.url ? CubeBase.url('/api/contact.php') : '/api/contact.php'), formData)
            : fetch((window.CubeBase && CubeBase.url ? CubeBase.url('/api/contact.php') : '/api/contact.php'), { method: 'POST', body: formData }).then(function(res) { return res.json(); });

        request
            .then(function(data) {
                btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Get Best Price';
                btn.style.background = '#0d4ab4';
                if (data && (data.success || data.id)) {
                    form.reset();
                    if (window.showAlertModal) {
                        setTimeout(function() { showAlertModal((data && data.message) || 'Thank you! Your enquiry has been submitted successfully. Our workspace expert will get back to you with the best price shortly.', 'success'); }, 300);
                    } else if (window.CubeToast) {
                        CubeToast.success((data && data.message) || 'Enquiry sent!');
                    }
                } else {
                    if (window.showAlertModal) {
                        showAlertModal((data && data.message) || 'Failed to send enquiry. Please try again.', 'error');
                    } else if (window.CubeToast) {
                        CubeToast.error((data && data.message) || 'Failed to send');
                    }
                }
            })
            .catch(function(err) {
                btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Get Best Price';
                btn.style.background = '#0d4ab4';
                if (window.CubeToast) CubeToast.error(err && err.message ? err.message : 'Network error');
                console.error('Sidebar form error:', err);
            });
    };

    window.scrollToForm = function() {
        var sidebar = document.getElementById('contactSidebar');
        if (sidebar) sidebar.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    window.showAlertModal = function(message, type) {
        type = type || 'info';
        var modalEl = document.getElementById('alertModal');
        if (!modalEl) {
            modalEl = document.createElement('div');
            modalEl.className = 'modal fade';
            modalEl.id = 'alertModal';
            modalEl.tabIndex = -1;
            modalEl.innerHTML = 
                '<div class="modal-dialog modal-dialog-centered modal-sm">' +
                    '<div class="modal-content">' +
                        '<div class="modal-body text-center py-4">' +
                            '<i class="fa-solid fa-circle-check text-success mb-3 d-none" id="alertIconSuccess" style="font-size: 2rem;"></i>' +
                            '<i class="fa-solid fa-circle-exclamation text-danger mb-3 d-none" id="alertIconError" style="font-size: 2rem;"></i>' +
                            '<i class="fa-solid fa-circle-info text-primary mb-3 d-none" id="alertIconInfo" style="font-size: 2rem;"></i>' +
                            '<p class="mb-0 fw-medium" id="alertMessage">Message</p>' +
                        '</div>' +
                        '<div class="modal-footer border-0 pt-0 justify-content-center gap-2">' +
                            '<button type="button" class="btn btn-primary btn-sm px-3" data-bs-dismiss="modal">OK</button>' +
                        '</div>' +
                    '</div>' +
                '</div>';
            document.body.appendChild(modalEl);
        }

        var msgEl = document.getElementById('alertMessage');
        if (msgEl) msgEl.textContent = message;
        var iconSuccess = document.getElementById('alertIconSuccess');
        var iconError = document.getElementById('alertIconError');
        var iconInfo = document.getElementById('alertIconInfo');
        if (iconSuccess) iconSuccess.classList.add('d-none');
        if (iconError) iconError.classList.add('d-none');
        if (iconInfo) iconInfo.classList.add('d-none');
        var iconTarget = document.getElementById('alertIcon' + type.charAt(0).toUpperCase() + type.slice(1));
        if (iconTarget) iconTarget.classList.remove('d-none');

        if (window.bootstrap && window.bootstrap.Modal) {
            var modal = window.bootstrap.Modal.getOrCreateInstance ? window.bootstrap.Modal.getOrCreateInstance(modalEl) : (window.bootstrap.Modal.getInstance(modalEl) || new window.bootstrap.Modal(modalEl));
            modal.show();
        } else if (window.CubeToast) {
            window.CubeToast[type === 'error' ? 'error' : 'success'](message);
        } else {
            alert(message);
        }
    };
})();
