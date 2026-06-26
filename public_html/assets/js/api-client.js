(function() {
    'use strict';

    var CubeAPI = {
        BASE: '',
        ADMIN_BASE: '/admin',
        CSRF_TOKEN: null,
        ACCESS_TOKEN: null,
        REFRESHING: null
    };

    CubeAPI.init = function() {
        if (window.CubeBase && typeof CubeBase.path === 'function') {
            CubeAPI.BASE = CubeBase.path();
        } else {
            var meta = document.querySelector('meta[name="app-base"]');
            CubeAPI.BASE = meta ? (meta.getAttribute('content') || '').replace(/\/$/, '') : '';
        }
        CubeAPI.ADMIN_BASE = CubeAPI.BASE + '/admin';
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) CubeAPI.CSRF_TOKEN = meta.getAttribute('content');
        CubeAPI.ACCESS_TOKEN = sessionStorage.getItem('admin_access_token');
    };

    function normalizeData(data) {
        if (!data || typeof data !== 'object') return data;
        var nested = data.data;
        if (nested !== null && nested !== undefined) return nested;
        return data;
    }

    function jsonResponse(r) {
        return r.json().catch(function() {
            if (!r.ok) throw new Error('Server error (status ' + r.status + ')');
            throw new Error('Invalid JSON response');
        }).then(function(data) {
            if (!r.ok) {
                var msg = (data && (data.error || data.message)) || 'Request failed (status ' + r.status + ')';
                var err = new Error(msg);
                if (data && data.errors) err.errors = data.errors;
                throw err;
            }
            if (data && data.success === false) {
                var err = new Error(data.message || data.error || 'Request failed');
                if (data.errors) err.errors = data.errors;
                throw err;
            }
            return normalizeData(data);
        });
    }

    function buildUrl(path, params) {
        var url = CubeAPI.BASE + path;
        if (params) {
            var qs = [];
            for (var k in params) {
                if (params.hasOwnProperty(k)) qs.push(encodeURIComponent(k) + '=' + encodeURIComponent(params[k]));
            }
            if (qs.length) url += (url.indexOf('?') === -1 ? '?' : '&') + qs.join('&');
        }
        return url;
    }

    function adminHeaders() {
        var h = {};
        if (CubeAPI.ACCESS_TOKEN) h['Authorization'] = 'Bearer ' + CubeAPI.ACCESS_TOKEN;
        if (CubeAPI.CSRF_TOKEN) h['X-CSRF-Token'] = CubeAPI.CSRF_TOKEN;
        return h;
    }

    function tryRefresh() {
        if (CubeAPI.REFRESHING) return CubeAPI.REFRESHING;
        CubeAPI.REFRESHING = fetch(CubeAPI.ADMIN_BASE + '/token_refresh.php', { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                CubeAPI.REFRESHING = null;
                if (d.access_token) {
                    sessionStorage.setItem('admin_access_token', d.access_token);
                    CubeAPI.ACCESS_TOKEN = d.access_token;
                    return true;
                }
                sessionStorage.removeItem('admin_access_token');
                CubeAPI.ACCESS_TOKEN = null;
                return false;
            }).catch(function() {
                CubeAPI.REFRESHING = null;
                return false;
            });
        return CubeAPI.REFRESHING;
    }

    CubeAPI.get = function(path, params) {
        return fetch(buildUrl(path, params), {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        }).then(jsonResponse);
    };

    CubeAPI.post = function(path, body) {
        return fetch(CubeAPI.BASE + path, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        }).then(jsonResponse);
    };

    CubeAPI.postForm = function(path, formData) {
        return fetch(CubeAPI.BASE + path, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
            body: formData
        }).then(jsonResponse);
    };

    CubeAPI.request = function(method, path, options) {
        options = options || {};
        var config = {
            method: method,
            credentials: 'same-origin',
            headers: Object.assign({ 'Accept': 'application/json' }, options.headers || {})
        };
        if (options.body !== undefined) config.body = options.body;
        return fetch(CubeAPI.BASE + path, config).then(jsonResponse);
    };

    CubeAPI.adminGet = function(path, params) {
        var h = adminHeaders();
        h['Accept'] = 'application/json';
        return fetch(buildUrl(CubeAPI.ADMIN_BASE + path, params), {
            method: 'GET',
            credentials: 'same-origin',
            headers: h
        }).then(jsonResponse).catch(function(err) {
            if (err.message === 'Unauthorized' || err.message === 'Token expired') {
                return tryRefresh().then(function(refreshed) {
                    if (refreshed) return CubeAPI.adminGet(path, params);
                    if (window.CubeBase && typeof CubeBase.open === 'function') {
                        CubeBase.open(CubeAPI.ADMIN_BASE + '/');
                    } else if (window.cubeNavigate) {
                        cubeNavigate(CubeAPI.ADMIN_BASE + '/');
                    } else {
                        window.location.assign(CubeAPI.ADMIN_BASE + '/');
                    }
                    throw new Error('Session expired');
                });
            }
            throw err;
        });
    };

    CubeAPI.adminPost = function(path, formData) {
        var h = adminHeaders();
        h['Accept'] = 'application/json';
        return fetch(CubeAPI.ADMIN_BASE + path, {
            method: 'POST',
            credentials: 'same-origin',
            headers: h,
            body: formData
        }).then(jsonResponse).catch(function(err) {
            if (err.message === 'Unauthorized' || err.message === 'Token expired') {
                return tryRefresh().then(function(refreshed) {
                    if (refreshed) return CubeAPI.adminPost(path, formData);
                    if (window.CubeBase && typeof CubeBase.open === 'function') {
                        CubeBase.open(CubeAPI.ADMIN_BASE + '/');
                    } else if (window.cubeNavigate) {
                        cubeNavigate(CubeAPI.ADMIN_BASE + '/');
                    } else {
                        window.location.assign(CubeAPI.ADMIN_BASE + '/');
                    }
                    throw new Error('Session expired');
                });
            }
            throw err;
        });
    };

    CubeAPI.init();
    window.CubeAPI = CubeAPI;
})();
