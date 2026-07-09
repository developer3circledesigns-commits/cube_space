(function () {
    'use strict';

    var modalEl, carouselEl, counterEl, thumbsContainer;
    var carouselInstance = null;

    function init() {
        modalEl = document.getElementById('imageCarouselModal');
        carouselEl = document.getElementById('imageCarousel');
        counterEl = document.getElementById('carouselCounter');
        thumbsContainer = document.getElementById('carouselThumbs');

        if (!modalEl || !carouselEl) return;

        carouselInstance = new bootstrap.Carousel(carouselEl, {
            ride: false,
            interval: false,
            wrap: true
        });

        carouselEl.addEventListener('slid.bs.carousel', function (e) {
            updateCounter(e.to);
            updateThumbs(e.to);
        });

        thumbsContainer.addEventListener('click', function (e) {
            var thumb = e.target.closest('.thumb');
            if (!thumb) return;
            var idx = parseInt(thumb.dataset.index, 10);
            if (!isNaN(idx)) goToSlide(idx);
        });

        modalEl.addEventListener('hidden.bs.modal', function () {
            var zoomed = carouselEl.querySelector('.carousel-item img.zoomed');
            if (zoomed) zoomed.classList.remove('zoomed');
        });
    }

    function openLightbox(index) {
        if (!modalEl || !carouselInstance) return;
        goToSlide(index);
        var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    }

    function closeLightbox() {
        if (!modalEl) return;
        var modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
    }

    function goToSlide(index) {
        if (!carouselInstance) return;
        carouselInstance.to(index);
    }

    function updateCounter(index) {
        if (!counterEl) return;
        var total = carouselEl.querySelectorAll('.carousel-item').length;
        counterEl.textContent = (index + 1) + ' / ' + total;
    }

    function updateThumbs(index) {
        if (!thumbsContainer) return;
        thumbsContainer.querySelectorAll('.thumb').forEach(function (t) {
            t.classList.toggle('active', parseInt(t.dataset.index, 10) === index);
        });
        var activeThumb = thumbsContainer.querySelector('.thumb.active');
        if (activeThumb) {
            activeThumb.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
        }
    }

    document.addEventListener('DOMContentLoaded', init);

    window.CubeSpaceLightbox = {
        open: openLightbox,
        close: closeLightbox,
        goToSlide: goToSlide
    };
})();
