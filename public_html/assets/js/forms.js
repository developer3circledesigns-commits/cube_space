(function() {
    'use strict';

    var PHONE_RE = /^[6-9]\d{9}$/;
    var EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    function isSkippable(field) {
        if (!field || field.disabled) return true;
        if (field.type === 'hidden' || field.type === 'button' || field.type === 'submit' || field.type === 'reset') return true;
        if (field.name === 'website') return true;
        return false;
    }

    function labelFor(field) {
        if (field.dataset.label) return field.dataset.label;
        var label = field.id ? document.querySelector('label[for="' + field.id + '"]') : null;
        if (!label) {
            var group = field.closest('.mb-3, .cs-field');
            label = group ? group.querySelector('label') : null;
        }
        var text = label ? label.textContent : (field.placeholder || field.name || 'This field');
        return text.replace(/\*/g, '').trim() || 'This field';
    }

    function rulesFor(field) {
        var rules = [];
        var fieldName = (field.name || field.id || '').toLowerCase();
        if (field.dataset.rules) {
            rules = rules.concat(field.dataset.rules.split('|').filter(Boolean));
        }
        if (field.required && rules.indexOf('required') === -1) rules.push('required');
        if (field.type === 'email' && rules.indexOf('email') === -1) rules.push('email');
        if ((field.type === 'tel' || /phone|mobile/i.test(field.name || '')) && rules.indexOf('phone') === -1) rules.push('phone');
        if (field.type === 'number' && rules.indexOf('number') === -1) rules.push('number');
        if (/email/.test(fieldName) && rules.indexOf('email') === -1) rules.push('email');
        if (/price|amount|rate/.test(fieldName) && rules.indexOf('number') === -1) rules.push('number');
        if (/total_seats|min_seats|max_seats|sort_order|total_area_sqft/.test(fieldName) && rules.indexOf('integer') === -1) rules.push('integer');
        if (/title|name|company|username/.test(fieldName) && !rules.some(function(rule) { return rule.indexOf('max:') === 0; })) rules.push('max:160');
        if (/question/.test(fieldName) && !rules.some(function(rule) { return rule.indexOf('max:') === 0; })) rules.push('max:255');
        if (/message|description|address|seo_text|feature_highlights/.test(fieldName) && !rules.some(function(rule) { return rule.indexOf('max:') === 0; })) rules.push('max:5000');
        if (field.pattern && !rules.some(function(rule) { return rule.indexOf('pattern:') === 0; })) {
            rules.push('pattern:' + field.pattern);
        }
        if (field.type === 'file') {
            if (field.required && rules.indexOf('fileRequired') === -1) rules.push('fileRequired');
            if (field.accept && !rules.some(function(rule) { return rule.indexOf('fileTypes:') === 0; })) {
                rules.push('fileTypes:' + field.accept);
            }
            if (!rules.some(function(rule) { return rule.indexOf('maxFileSize:') === 0; })) rules.push('maxFileSize:5242880');
        }
        return rules;
    }

    function filesFor(field) {
        return Array.prototype.slice.call(field.files || []);
    }

    function matchesFileType(file, acceptList) {
        if (!acceptList) return true;
        return acceptList.split(',').map(function(v) { return v.trim().toLowerCase(); }).some(function(accept) {
            if (!accept) return true;
            if (accept === 'image/*') return file.type.indexOf('image/') === 0;
            if (accept.charAt(0) === '.') return file.name.toLowerCase().endsWith(accept);
            if (accept.indexOf('/') > -1) return file.type.toLowerCase() === accept;
            return file.name.toLowerCase().endsWith('.' + accept.replace(/^\./, ''));
        });
    }

    function validateField(field) {
        if (isSkippable(field)) return '';
        var value = (field.value || '').trim();
        var label = labelFor(field);
        var rules = rulesFor(field);

        for (var i = 0; i < rules.length; i++) {
            var rule = rules[i];
            var parts = rule.split(':');
            var name = parts[0];
            var arg = parts.slice(1).join(':');

            if (name === 'required') {
                if (field.type === 'checkbox' && !field.checked) return label + ' is required.';
                if (field.type === 'file' && filesFor(field).length === 0) return label + ' is required.';
                if (!value) return label + ' is required.';
            }

            if (!value && field.type !== 'file') continue;

            if (name === 'email' && value && !EMAIL_RE.test(value)) return 'Enter a valid email address.';
            if (name === 'phone' && value && !PHONE_RE.test(value)) return 'Enter a valid 10-digit Indian mobile number.';
            if (name === 'number' && value && Number.isNaN(Number(value))) return label + ' must be a number.';
            if (name === 'integer' && value && !/^-?\d+$/.test(value)) return label + ' must be a whole number.';
            if (name === 'min' && value.length < Number(arg)) return label + ' must be at least ' + arg + ' characters.';
            if (name === 'max' && value.length > Number(arg)) return label + ' must be ' + arg + ' characters or fewer.';
            if (name === 'pattern' && value) {
                try {
                    if (!(new RegExp('^(?:' + arg + ')$')).test(value)) return field.title || (label + ' is invalid.');
                } catch (e) {}
            }
            if (name === 'fileRequired' && filesFor(field).length === 0) return label + ' is required.';
            if (name === 'maxFiles' && filesFor(field).length > Number(arg)) return 'Upload no more than ' + arg + ' files.';
            if (name === 'maxFileSize') {
                var tooLarge = filesFor(field).find(function(file) { return file.size > Number(arg); });
                if (tooLarge) return tooLarge.name + ' is too large.';
            }
            if (name === 'fileTypes') {
                var badFile = filesFor(field).find(function(file) { return !matchesFileType(file, arg); });
                if (badFile) return badFile.name + ' is not an accepted file type.';
            }
        }

        return '';
    }

    function errorElement(field) {
        var id = field.id || field.name;
        var existing = id ? document.querySelector('[data-error-for="' + id + '"]') : null;
        if (existing) return existing;

        var el = document.createElement('p');
        el.className = 'field-error';
        if (id) el.setAttribute('data-error-for', id);
        field.insertAdjacentElement('afterend', el);
        return el;
    }

    function setError(field, message) {
        var err = errorElement(field);
        field.classList.add('is-invalid');
        field.setAttribute('aria-invalid', 'true');
        if (err.id) field.setAttribute('aria-describedby', err.id);
        err.textContent = message;
        err.classList.add('is-visible');
    }

    function clearError(field) {
        var id = field.id || field.name;
        var err = id ? document.querySelector('[data-error-for="' + id + '"]') : null;
        field.classList.remove('is-invalid');
        field.setAttribute('aria-invalid', 'false');
        field.removeAttribute('aria-describedby');
        if (err) {
            err.textContent = '';
            err.classList.remove('is-visible');
        }
    }

    function fieldsFor(form) {
        return Array.prototype.slice.call(form.querySelectorAll('input, select, textarea')).filter(function(field) {
            return !isSkippable(field);
        });
    }

    function validateForm(form) {
        var firstInvalid = null;
        fieldsFor(form).forEach(function(field) {
            var message = validateField(field);
            if (message) {
                setError(field, message);
                if (!firstInvalid) firstInvalid = field;
            } else {
                clearError(field);
            }
        });
        if (firstInvalid) {
            firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
            firstInvalid.focus({ preventScroll: true });
            return false;
        }
        return true;
    }

    function enhanceField(field) {
        if (isSkippable(field)) return;
        field.classList.add('cs-control');
        if (!field.id && field.name) field.id = field.name + '-' + Math.random().toString(36).slice(2, 8);
        if (!field.autocomplete) {
            var fn = (field.name || '').toLowerCase();
            if (fn === 'name' || fn === 'fullname' || fn === 'firstname' || fn === 'lastname' || fn === 'first_name' || fn === 'last_name' || fn === 'establishmentname' || fn === 'establishmentname') field.autocomplete = 'name';
            else if (fn === 'email') field.autocomplete = 'email';
            else if (fn === 'phone' || fn === 'mobile' || fn === 'tel' || fn === 'phonenumber' || fn === 'mobilenumber') field.autocomplete = 'tel';
            else if (fn === 'company' || fn === 'companyname' || fn === 'organization') field.autocomplete = 'organization';
            else if (fn === 'address' || fn === 'completeaddress') field.autocomplete = 'street-address';
            else if (fn === 'city') field.autocomplete = 'address-level2';
            else if (fn === 'username' || fn === 'adminuser') field.autocomplete = 'username';
            else if (fn.indexOf('password') !== -1) field.autocomplete = 'current-password';
            else if (fn === 'message' || fn === 'question' || field.tagName === 'TEXTAREA') field.autocomplete = 'off';
        }
        field.addEventListener('input', function() {
            if (field.type === 'tel' || /phone|mobile/i.test(field.name || '')) {
                field.value = field.value.replace(/\D/g, '').slice(0, 10);
            }
            if (field.classList.contains('is-invalid')) {
                var message = validateField(field);
                if (!message) clearError(field);
            }
        });
        field.addEventListener('blur', function() {
            var message = validateField(field);
            if (message) setError(field, message);
            else clearError(field);
        });
        field.addEventListener('change', function() {
            var message = validateField(field);
            if (message) setError(field, message);
            else clearError(field);
        });
    }

    function enhanceForm(form) {
        form.classList.add('cs-form');
        form.setAttribute('novalidate', 'novalidate');
        fieldsFor(form).forEach(enhanceField);
    }

    ready(function() {
        document.querySelectorAll('form').forEach(enhanceForm);

        document.addEventListener('submit', function(event) {
            var form = event.target;
            if (!form || form.tagName !== 'FORM') return;
            if (!validateForm(form)) {
                event.preventDefault();
                event.stopImmediatePropagation();
            }
        }, true);
    });

    window.CSForms = {
        validate: validateForm,
        validateField: validateField,
        setError: setError,
        clearError: clearError,
        enhance: enhanceForm
    };
})();
