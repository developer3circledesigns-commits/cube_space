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

    // Mobile menu toggle
    var menuBtn = document.querySelector('.mobile-menu-btn');
    var headerNav = document.querySelector('.header-nav');
    if (menuBtn && headerNav) {
        menuBtn.addEventListener('click', function() {
            headerNav.style.display = headerNav.style.display === 'flex' ? '' : 'flex';
        });
    }

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
            ? 'Read more <i class="fa-solid fa-chevron-down"></i>'
            : 'Show less <i class="fa-solid fa-chevron-up"></i>';
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
        btn.disabled = true;

        var request = window.CubeAPI && typeof CubeAPI.postForm === 'function'
            ? CubeAPI.postForm((window.CubeBase && CubeBase.url ? CubeBase.url('/api/contact.php') : '/api/contact.php'), formData)
            : fetch((window.CubeBase && CubeBase.url ? CubeBase.url('/api/contact.php') : '/api/contact.php'), { method: 'POST', body: formData }).then(function(res) { return res.json(); });

        request
            .then(function(data) {
                if (data.success) {
                    btn.innerHTML = '<i class="fa-solid fa-check"></i> Enquiry Sent!';
                    btn.style.background = '#08753f';
                    form.reset();
                    if (window.CubeToast) CubeToast.success('Enquiry sent!');
                } else {
                    btn.innerHTML = '<i class="fa-solid fa-exclamation-triangle"></i> Failed - Try Again';
                    btn.style.background = '#dc2626';
                    if (window.CubeToast) CubeToast.error(data.message || 'Failed to send');
                }
                setTimeout(function() {
                    btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Get Best Price';
                    btn.style.background = '#0d4ab4';
                    btn.disabled = false;
                }, 3000);
            })
            .catch(function(err) {
                btn.innerHTML = '<i class="fa-solid fa-exclamation-triangle"></i> Error - Try Again';
                btn.style.background = '#dc2626';
                if (window.CubeToast) CubeToast.error(err && err.message ? err.message : 'Network error');
                console.error('Sidebar form error:', err);
                setTimeout(function() {
                    btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Get Best Price';
                    btn.style.background = '#0d4ab4';
                    btn.disabled = false;
                }, 3000);
            });
    };

    window.scrollToForm = function() {
        var sidebar = document.getElementById('contactSidebar');
        if (sidebar) sidebar.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };
})();
