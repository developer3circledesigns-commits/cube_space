(function() {
    'use strict';

    function apiUrl(path) {
        if (window.CubeBase && typeof CubeBase.url === 'function') {
            return CubeBase.url(path);
        }
        var meta = document.querySelector('meta[name="app-base"]');
        var base = meta ? (meta.getAttribute('content') || '').replace(/\/$/, '') : '';
        if (path.charAt(0) === '/') {
            return base + path;
        }
        return (base ? base + '/' : '') + path;
    }

    var Realtime = {
        pollInterval: 15000,
        timer: null,
        lastTimestamp: Date.now(),
        adminMode: false,
        handlers: {},
        adminUrl: apiUrl('/api/updates.php'),
        publicUrl: apiUrl('/api/updates.php')
    };

    // exponential backoff controls
    Realtime.retryCount = 0;
    Realtime.maxRetries = 6;
    Realtime.baseBackoff = 2000; // ms

    Realtime.init = function(opts) {
        if (opts) {
            if (opts.adminMode) Realtime.adminMode = true;
            if (opts.interval) Realtime.pollInterval = opts.interval;
        }
        Realtime.lastTimestamp = Date.now();
        Realtime.poll();
    };

    Realtime.extractEnvelope = function(data) {
        if (!data || typeof data !== 'object') return { events: [], timestamp: Date.now() };
        if (data.data && typeof data.data === 'object') return data.data;
        return data;
    };

    Realtime.on = function(eventType, callback) {
        if (!Realtime.handlers[eventType]) Realtime.handlers[eventType] = [];
        Realtime.handlers[eventType].push(callback);
    };

    Realtime.off = function(eventType, callback) {
        var list = Realtime.handlers[eventType];
        if (!list) return;
        if (!callback) {
            delete Realtime.handlers[eventType];
            return;
        }
        Realtime.handlers[eventType] = list.filter(function(fn) { return fn !== callback; });
    };

    function emit(eventType, data) {
        var list = Realtime.handlers[eventType];
        if (list) {
            for (var i = 0; i < list.length; i++) {
                try { list[i](data); } catch (e) { console.warn('Realtime handler error:', e); }
            }
        }
        var wildcard = Realtime.handlers['*'];
        if (wildcard) {
            for (var j = 0; j < wildcard.length; j++) {
                try { wildcard[j](eventType, data); } catch (e2) { console.warn('Realtime wildcard error:', e2); }
            }
        }
    }

    Realtime.poll = function() {
        var url = Realtime.adminMode ? Realtime.adminUrl : Realtime.publicUrl;

        if (typeof EventSource !== 'undefined') {
            if (Realtime.eventSource) {
                try { Realtime.eventSource.close(); } catch (e) {}
            }
            var sseUrl = url + (url.indexOf('?') === -1 ? '?' : '&') + 'stream=true&since=' + Realtime.lastTimestamp;
            if (Realtime.adminMode) {
                var token = sessionStorage.getItem('admin_access_token');
                if (token) sseUrl += '&token=' + encodeURIComponent(token);
            }
            var es = new EventSource(sseUrl, { withCredentials: true });
            Realtime.eventSource = es;

            es.onmessage = function(e) {
                try {
                    var data = JSON.parse(e.data);
                    var payload = Realtime.extractEnvelope(data);
                    Realtime.lastTimestamp = payload.timestamp || Date.now();
                    Realtime.retryCount = 0;
                    if (payload.events && payload.events.length) {
                        for (var i = 0; i < payload.events.length; i++) {
                            emit(payload.events[i].event_type, payload.events[i]);
                        }
                    }
                } catch (err) {
                    console.warn('Error parsing SSE data:', err);
                }
            };

            es.onerror = function(err) {
                if (es.readyState === EventSource.CLOSED) {
                    console.log('SSE connection closed by server, reconnecting...');
                } else {
                    console.warn('SSE connection error, falling back to polling...', err);
                }
                es.close();
                Realtime.eventSource = null;
                Realtime.pollFallback();
            };
        } else {
            Realtime.pollFallback();
        }
    };

    Realtime.pollFallback = function() {
        function handleResponse(data) {
            var payload = Realtime.extractEnvelope(data);
            Realtime.lastTimestamp = payload.timestamp || Date.now();
            Realtime.retryCount = 0;
            if (payload.events && payload.events.length) {
                for (var i = 0; i < payload.events.length; i++) {
                    var ev = payload.events[i];
                    emit(ev.event_type, ev);
                }
            }
            Realtime.scheduleNext();
        }

        function handleError(err) {
            Realtime.retryCount = Math.min(Realtime.retryCount + 1, Realtime.maxRetries);
            var backoff = Realtime.baseBackoff * Math.pow(2, Realtime.retryCount - 1);
            Realtime.timer = setTimeout(function() { Realtime.poll(); }, Math.min(backoff, 300000));
        }

        var url = Realtime.adminMode ? Realtime.adminUrl : Realtime.publicUrl;
        url += (url.indexOf('?') === -1 ? '?' : '&') + 'since=' + Realtime.lastTimestamp;

        var request = window.CubeAPI && typeof CubeAPI.get === 'function' && !Realtime.adminMode
            ? CubeAPI.get(url)
            : fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } }).then(function(r) { return r.json(); });

        request.then(handleResponse).catch(handleError);
    };

    Realtime.scheduleNext = function() {
        Realtime.timer = setTimeout(function() {
            Realtime.poll();
        }, Realtime.pollInterval);
    };

    Realtime.stop = function() {
        if (Realtime.timer) {
            clearTimeout(Realtime.timer);
            Realtime.timer = null;
        }
        if (Realtime.eventSource) {
            try { Realtime.eventSource.close(); } catch (e) {}
            Realtime.eventSource = null;
        }
    };

    Realtime.reset = function() {
        Realtime.stop();
        Realtime.lastTimestamp = Date.now();
    };

    window.CubeRealtime = Realtime;
})();
