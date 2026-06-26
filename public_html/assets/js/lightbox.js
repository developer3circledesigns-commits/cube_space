(function() {
    'use strict';

    let currentIdx = 0;
    let touchStartX = 0;
    let isZoomed = false;
    let officeImages = [];
    let lb, track, thumbStrip, prevBtn, nextBtn, lbCounter, progressFill, slideArea;

    function init(images) {
        officeImages = images;
        if (!officeImages.length) return;

        lb = document.getElementById('lightbox');
        track = document.getElementById('slideTrack');
        thumbStrip = document.getElementById('thumbStrip');
        prevBtn = document.getElementById('prevBtn');
        nextBtn = document.getElementById('nextBtn');
        lbCounter = document.getElementById('lbCounter');
        progressFill = document.getElementById('progressFill');
        slideArea = document.getElementById('slideArea');

        buildLightbox();
        bindEvents();
    }

    function buildLightbox() {
        track.innerHTML = officeImages.map(function(url, i) {
            return '<div class="lb-slide" data-index="' + i + '">' +
                '<div class="lb-spinner"></div>' +
                '<img src="' + url + '" alt="Image ' + (i + 1) + '" loading="' + (i === 0 ? 'eager' : 'lazy') + '" onclick="CubeSpaceLightbox.toggleZoom(event)" draggable="false">' +
                '</div>';
        }).join('');

        thumbStrip.innerHTML = officeImages.map(function(url, i) {
            return '<div class="lb-thumb' + (i === 0 ? ' active' : '') + '" data-index="' + i + '" onclick="CubeSpaceLightbox.goToSlide(' + i + ')">' +
                '<img src="' + url + '" alt="" loading="lazy">' +
                '</div>';
        }).join('');
    }

    function openLightbox(i) {
        if (!lb || !officeImages.length) return;
        currentIdx = i;
        lb.classList.add('active');
        document.body.style.overflow = 'hidden';
        updateLightbox();
    }

    function closeLightbox() {
        if (!lb) return;
        lb.classList.remove('active');
        document.body.style.overflow = '';
        document.querySelectorAll('.lb-slide img').forEach(function(img) {
            img.classList.remove('zoomed');
        });
        isZoomed = false;
    }

    function goToSlide(index) {
        if (index < 0 || index >= officeImages.length) return;
        currentIdx = index;
        document.querySelectorAll('.lb-slide img').forEach(function(img) {
            img.classList.remove('zoomed');
        });
        isZoomed = false;
        updateLightbox();
    }

    function updateLightbox() {
        var w = slideArea.offsetWidth;
        track.style.transform = 'translateX(-' + (currentIdx * w) + 'px)';
        lbCounter.textContent = (currentIdx + 1) + ' / ' + officeImages.length;
        prevBtn.disabled = currentIdx === 0;
        nextBtn.disabled = currentIdx === officeImages.length - 1;
        progressFill.style.width = ((currentIdx + 1) / officeImages.length * 100) + '%';
        document.querySelectorAll('.lb-thumb').forEach(function(t) {
            t.classList.toggle('active', parseInt(t.dataset.index) === currentIdx);
        });
        var activeThumb = document.querySelector('.lb-thumb.active');
        if (activeThumb) {
            activeThumb.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
        }
    }

    function toggleZoom(e) {
        var img = e.currentTarget;
        img.classList.toggle('zoomed');
        isZoomed = img.classList.contains('zoomed');
    }

    function bindEvents() {
        if (prevBtn) {
            prevBtn.addEventListener('click', function() { goToSlide(currentIdx - 1); });
        }
        if (nextBtn) {
            nextBtn.addEventListener('click', function() { goToSlide(currentIdx + 1); });
        }

        document.addEventListener('keydown', function(e) {
            if (!lb || !lb.classList.contains('active')) return;
            if (e.key === 'Escape') closeLightbox();
            if (e.key === 'ArrowLeft') goToSlide(currentIdx - 1);
            if (e.key === 'ArrowRight') goToSlide(currentIdx + 1);
        });

        if (slideArea) {
            slideArea.addEventListener('touchstart', function(e) {
                touchStartX = e.changedTouches[0].screenX;
            }, { passive: true });

            slideArea.addEventListener('touchend', function(e) {
                if (isZoomed) return;
                var diff = touchStartX - e.changedTouches[0].screenX;
                if (Math.abs(diff) > 50) {
                    if (diff > 0) goToSlide(currentIdx + 1);
                    else goToSlide(currentIdx - 1);
                }
            }, { passive: true });
        }

        window.addEventListener('resize', function() {
            if (lb && lb.classList.contains('active')) updateLightbox();
        });
    }

    window.CubeSpaceLightbox = {
        init: init,
        open: openLightbox,
        close: closeLightbox,
        goToSlide: goToSlide,
        toggleZoom: toggleZoom
    };
})();
