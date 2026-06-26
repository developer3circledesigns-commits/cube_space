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
})();
