(function() {
    'use strict';

    var Toast = {
        container: null,
        defaults: { duration: 4000, type: 'info' }
    };

    Toast.init = function() {
        if (!Toast.container) {
            Toast.container = document.createElement('div');
            Toast.container.className = 'toast-container';
            document.body.appendChild(Toast.container);
        }
    };

    Toast.show = function(message, opts) {
        Toast.init();
        var options = {};
        for (var k in Toast.defaults) options[k] = Toast.defaults[k];
        if (typeof opts === 'object') {
            for (var k2 in opts) options[k2] = opts[k2];
        } else if (typeof opts === 'string') {
            options.type = opts;
        }

        var el = document.createElement('div');
        el.className = 'toast toast-' + options.type;
        el.innerHTML = message;

        Toast.container.appendChild(el);

        setTimeout(function() {
            el.classList.add('toast-hide');
            setTimeout(function() { if (el.parentNode) el.parentNode.removeChild(el); }, 300);
        }, options.duration);
    };

    Toast.success = function(msg, duration) {
        Toast.show(msg, { type: 'success', duration: duration || 4000 });
    };

    Toast.error = function(msg, duration) {
        Toast.show(msg, { type: 'error', duration: duration || 6000 });
    };

    Toast.info = function(msg, duration) {
        Toast.show(msg, { type: 'info', duration: duration || 4000 });
    };

    window.CubeToast = Toast;
})();
