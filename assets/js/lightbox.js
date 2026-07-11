(function () {
    'use strict';

    var el, slides, thumbs, counterEl;
    var currentIndex = 0;
    var total = 0;
    var touchStartX = 0;

    function init() {
        el = document.getElementById('csLightbox');
        if (!el) return;

        slides = el.querySelectorAll('.cs-lb-slide');
        thumbs = el.querySelectorAll('.cs-thumb');
        counterEl = document.getElementById('csCounter');
        total = slides.length;
        if (total === 0) return;

        var viewEl = el.querySelector('.cs-lb-view');
        if (viewEl) {
            viewEl.addEventListener('click', function (e) {
                if (e.target.tagName !== 'IMG') close();
            });
        }

        var thumbsContainer = document.getElementById('csThumbs');
        if (thumbsContainer) {
            thumbsContainer.addEventListener('click', function (e) {
                var t = e.target.closest('.cs-thumb');
                if (!t) return;
                var idx = parseInt(t.dataset.index, 10);
                if (!isNaN(idx)) goTo(idx);
            });
        }

        document.addEventListener('keydown', function (e) {
            if (!el.classList.contains('active')) return;
            if (e.key === 'Escape') { close(); return; }
            if (e.key === 'ArrowLeft') { prev(); return; }
            if (e.key === 'ArrowRight') { next(); return; }
        });

        el.addEventListener('touchstart', function (e) {
            touchStartX = e.changedTouches[0].screenX;
        }, { passive: true });

        el.addEventListener('touchend', function (e) {
            var diff = touchStartX - e.changedTouches[0].screenX;
            if (Math.abs(diff) > 50) {
                if (diff > 0) next(); else prev();
            }
        }, { passive: true });
    }

    function open(index) {
        if (!el || total === 0) return;
        currentIndex = Math.max(0, Math.min(parseInt(index, 10) || 0, total - 1));
        render();
        el.classList.add('active');
        document.body.style.overflow = 'hidden';
        document.body.style.marginRight = '0';
    }

    function close() {
        if (!el) return;
        el.classList.remove('active');
        document.body.style.overflow = '';
        document.body.style.marginRight = '';
    }

    function goTo(index) {
        if (index < 0 || index >= total) return;
        currentIndex = index;
        render();
    }

    function prev() {
        goTo(currentIndex > 0 ? currentIndex - 1 : total - 1);
    }

    function next() {
        goTo(currentIndex < total - 1 ? currentIndex + 1 : 0);
    }

    function render() {
        slides.forEach(function (s, i) {
            s.classList.toggle('active', i === currentIndex);
        });
        thumbs.forEach(function (t) {
            t.classList.toggle('active', parseInt(t.dataset.index, 10) === currentIndex);
        });
        if (counterEl) {
            counterEl.textContent = (currentIndex + 1) + ' / ' + total;
        }
        var activeThumb = el.querySelector('.cs-thumb.active');
        if (activeThumb) {
            activeThumb.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
        }
    }

    document.addEventListener('DOMContentLoaded', init);

    window.CubeSpaceLightbox = {
        open: open,
        close: close,
        goTo: goTo,
        prev: prev,
        next: next
    };
})();
