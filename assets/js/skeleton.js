(function(window) {
    'use strict';

    function skeletonCards(count, cardMarkup) {
        count = count || 4;
        cardMarkup = cardMarkup || '<div class="skeleton-card"><div class="skeleton skeleton-img"></div><div class="skeleton-body"><div class="skeleton skeleton-line"></div><div class="skeleton skeleton-line short"></div><div class="skeleton skeleton-line stats"></div></div></div>';
        var html = '';
        for (var i = 0; i < count; i++) html += cardMarkup;
        return html;
    }

    function resolveTarget(container) {
        return typeof container === 'string' ? document.getElementById(container) : container;
    }

    function showListingsSkeleton(container, opts) {
        opts = opts || {};
        var target = resolveTarget(container);
        if (!target) return false;
        var count = opts.count || 4;
        var markup = opts.card;
        target.innerHTML = '<div class="listing-cards">' + skeletonCards(count, markup) + '</div>';
        var pagination = opts.pagination ? document.getElementById(opts.pagination) : null;
        if (pagination) pagination.innerHTML = '';
        return true;
    }

    // Renders the skeleton, then runs the loader while keeping the skeleton
    // visible for at least `minMs`. Resolves with the loader's result so the
    // page can render data exactly as before. Keeps the UX identical while the
    // skeleton is always shown for a consistent, believable loading period.
    function withSkeleton(container, loader, opts) {
        opts = opts || {};
        var target = resolveTarget(container);
        if (!target) {
            return Promise.resolve(loader ? loader() : undefined);
        }
        showListingsSkeleton(target, opts);
        var minMs = opts.minMs != null ? opts.minMs : 650;
        var startedAt = Date.now();
        var loaderPromise = Promise.resolve(loader ? loader() : null);
        return loaderPromise.then(function(result) {
            var elapsed = Date.now() - startedAt;
            if (elapsed < minMs) {
                return new Promise(function(resolve) {
                    setTimeout(function() { resolve(result); }, minMs - elapsed);
                });
            }
            return result;
        });
    }

    window.CubeSkeleton = {
        cards: skeletonCards,
        showListings: showListingsSkeleton,
        withSkeleton: withSkeleton
    };
})(window);
