(function() {
    'use strict';

    function getAppBase() {
        var meta = document.querySelector('meta[name="app-base"]');
        if (!meta) return '';
        var base = meta.getAttribute('content') || '';
        return base.replace(/\/$/, '');
    }

    function resolveUrl(path) {
        if (!path) return path;
        if (/^(https?:|mailto:|tel:|#)/i.test(path)) return path;
        var base = getAppBase();
        if (path.charAt(0) === '/') {
            return base + path;
        }
        return (base ? base + '/' : '') + path;
    }

    function inForeignFrame() {
        if (window.self === window.top) return false;
        try {
            return window.top.location.hostname !== window.location.hostname;
        } catch (e) {
            return true;
        }
    }

    function isAdminUrl(url) {
        return /\/admin\/?($|[?#])/i.test(url) || /\/admin\//i.test(url);
    }

    function safeOpen(url) {
        var target = resolveUrl(url);
        if (!target) return;

        var opened = window.open(target, '_blank', 'noopener,noreferrer');
        if (opened) {
            try { opened.opener = null; } catch (e) { /* ignore */ }
            return;
        }

        window.location.assign(target);
    }

    function navigate(url) {
        var target = resolveUrl(url);
        if (!target) return;

        if (inForeignFrame() || isAdminUrl(target)) {
            safeOpen(target);
            return;
        }

        window.location.assign(target);
    }

    window.CubeBase = {
        path: getAppBase,
        url: resolveUrl,
        navigate: navigate,
        open: safeOpen
    };

    window.cubeNavigate = navigate;

    document.addEventListener('click', function(e) {
        var link = e.target.closest('a[href]');
        if (!link || link.hasAttribute('download')) return;

        var href = link.getAttribute('href');
        if (!href || href.charAt(0) === '#') return;
        if (/^(mailto:|tel:|javascript:)/i.test(href)) return;
        if (link.hostname && link.hostname !== window.location.hostname) return;

        var absolute = link.href;
        var adminLink = link.classList.contains('admin-login-link') || isAdminUrl(absolute);

        if (inForeignFrame()) {
            e.preventDefault();
            safeOpen(absolute);
            return;
        }

        if (link.target === '_blank') {
            return;
        }
    }, true);

    var isMobileMql = window.matchMedia('(max-width: 992px)');

    function isMobileView() {
        return isMobileMql.matches;
    }

    function toggleBodyScroll(lock) {
        if (lock) {
            var scrollY = window.scrollY;
            document.body.style.top = -scrollY + 'px';
            document.body.classList.add('mobile-nav-open');
            document.body.dataset.scrollY = scrollY;
        } else {
            document.body.classList.remove('mobile-nav-open');
            var scrollY = parseInt(document.body.dataset.scrollY || '0');
            document.body.style.top = '';
            window.scrollTo(0, scrollY);
        }
    }

    function openMobileNav(button, nav) {
        nav.classList.add('active');
        button.setAttribute('aria-expanded', 'true');
        button.classList.add('active');
        button.style.display = 'none';
        toggleBodyScroll(true);
    }

    function closeMobileNav(button, nav) {
        nav.classList.remove('active');
        button.setAttribute('aria-expanded', 'false');
        button.classList.remove('active');
        button.style.display = '';
        toggleBodyScroll(false);
    }

    function closeAllMegaMenus() {
        document.querySelectorAll('.mega-parent.mega-open').forEach(function(p) {
            p.classList.remove('mega-open');
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        var nav = document.getElementById('mobileNav');
        var button = document.querySelector('.mobile-menu');
        var closeBtn = nav ? nav.querySelector('.mobile-nav-close') : null;
        var backdrop = document.querySelector('.mobile-backdrop');
        if (!nav || !button) return;

        function closeNav() {
            closeMobileNav(button, nav);
        }

        function toggleNav() {
            if (nav.classList.contains('active')) {
                closeNav();
            } else {
                openMobileNav(button, nav);
            }
        }

        button.addEventListener('click', function(e) {
            e.preventDefault();
            toggleNav();
        });

        if (closeBtn) {
            closeBtn.addEventListener('click', function(e) {
                e.preventDefault();
                closeNav();
            });
        }

        if (backdrop) {
            backdrop.addEventListener('click', function(e) {
                e.preventDefault();
                closeNav();
            });
        }

        nav.addEventListener('click', function(e) {
            var link = e.target.closest('a');
            if (link && isMobileView()) {
                closeNav();
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && nav.classList.contains('active')) {
                closeNav();
                button.focus();
            }
        });

    });

    var desktopMql = window.matchMedia('(min-width: 993px)');
    desktopMql.addEventListener('change', function(e) {
        if (e.matches) {
            var nav = document.getElementById('mobileNav');
            var button = document.querySelector('.mobile-menu');
            if (nav && nav.classList.contains('active')) {
                closeMobileNav(button, nav);
            }
            document.body.classList.remove('mobile-nav-open');
            document.body.style.top = '';
        }
    });
    if (desktopMql.matches) {
        var desktopNav = document.getElementById('mobileNav');
        if (desktopNav) {
            desktopNav.classList.remove('active');
        }
    }

    if (window.matchMedia('(hover: none), (pointer: coarse)').matches) {
        document.addEventListener('click', function(e) {
            var parent = e.target.closest('.mega-parent');
            if (parent) {
                var link = e.target.closest('a');
                if (link && link.parentElement === parent) {
                    e.preventDefault();
                    var wasOpen = parent.classList.contains('mega-open');
                    closeAllMegaMenus();
                    if (!wasOpen) {
                        parent.classList.add('mega-open');
                    }
                }
            } else {
                closeAllMegaMenus();
            }
        });
    }
})();
