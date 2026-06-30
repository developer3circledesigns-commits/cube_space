---
name: listing-grid-strip-images
description: Strip image containers, type badges, and "Nearby"/"Furnished/Unfurnished" badges from JS-templated listing-card grids in CubeSpace listing pages (furnished_offices.php, unfurnished_offices.php, managed_offices.php), preserving card body content (title, address, stats, price, CTA).
source: auto-skill
extracted_at: '2026-06-30T07:10:35.489Z'
---

## When to use

The user asks to remove image containers, "Furnished/Unfurnished" text badges, "Nearby" badges, or any combination thereof from a listing-grid page. The grid is rendered by client-side JS templating (`renderCards` / `renderNearest` functions), not by server-side PHP. The cards are in `public_html/furnished_offices.php`, `public_html/unfurnished_offices.php`, or `public_html/managed_offices.php`.

Examples that match this skill:
- "remove furnished and unfurnished text icons and every images containers in these pages"
- "remove all image containers and badges from the listing cards"
- "drop the photo carousel and the type badge from these cards"

The user's intent is a **text-only card** (title, address, stats, price, CTA). Do NOT redesign the card — only strip what they asked to remove.

## Procedure

### 1. Identify the render functions

Run `grep_search` for the function signatures in the target file:

```
grep_search pattern: "function renderCards|function renderNearest|function renderPagination"
```

There are always two render blocks to edit in these files:
- `renderCards(offices, container)` — main listing grid
- `renderNearest(nearest)` — "Nearest Workspaces" section

Both blocks build card markup from `o.images_arr` and `o.listing_type_db`.

### 2. Map the JS template variables you must remove

Open the function body and look for these variables — they are all part of the image / badge stack and must be deleted:

| Variable | Purpose | Where it appears |
|---|---|---|
| `carouselHtml` | the `<div class="card-carousel">` with all `<div class="carousel-slide"><img …>` | both functions |
| `carouselId` | the id used to wire carousel buttons | both functions |
| `imgCount` | the `<div class="img-count">…</div>` photo count badge | `renderCards` |
| `imgCountHtml` | same as `imgCount`, named differently | `renderNearest` |
| `typeBadge` | the "Furnished"/"Unfurnished" `<span class="badge-type">` | `renderCards` |
| `nTypeBadge` | same as `typeBadge`, named differently | `renderNearest` |

For `renderNearest`, also remove `<span class="badge-nearby"><i class="fa-regular fa-compass"></i> Nearby</span>` from the card template.

### 3. Apply this template transformation

**Before** (inside both `renderCards` and `renderNearest`, inside `offices.forEach((o) => { … })`):

```js
const carouselId = 'carousel-' + o.id;
let carouselHtml = '';
// … 30+ lines of if (o.images_arr && o.images_arr.length) { … } else { placeholder } …
const typeBadge = o.listing_type_db ? `…Furnished : Unfurnished…` : '';
const imgCount = o.images_arr && o.images_arr.length > 1 ? `…<div class="img-count">…</div>` : '';
```

**After**:

```js
// (delete everything above)
```

**Before** (card template — `renderCards` variant):

```html
<div class="card office-card flex-lg-row" …>
    <div class="card-img-top office-card-img">
        ${carouselHtml}
        ${typeBadge}
        ${imgCount}
    </div>
    <div class="card-body d-flex flex-column">
```

**After**:

```html
<div class="card office-card" …>
    <div class="card-body d-flex flex-column">
```

**Before** (card template — `renderNearest` variant):

```html
<div class="card office-card" …>
    <div class="card-img-top office-card-img position-relative overflow-hidden">
        <span class="badge-nearby"><i class="fa-regular fa-compass"></i> Nearby</span>
        ${carouselHtml}
        ${nTypeBadge}
        ${imgCountHtml}
    </div>
    <div class="card-body d-flex flex-column">
```

**After**:

```html
<div class="card office-card" …>
    <div class="card-body d-flex flex-column">
```

Note: `flex-lg-row` (the layout that put image left / body right on desktop) is dropped too — there is no longer a left-side image, so the row layout has no purpose.

### 4. Update the click / keyboard handlers

In both functions, the existing handler uses `e.target.closest('.carousel-btn')` and `.closest('.carousel-dots')` to avoid hijacking navigation when the user clicks a carousel control. With carousels gone, simplify:

**Before**:
```js
card.addEventListener('click', function(e) {
    if (e.target.closest('.carousel-btn') || e.target.closest('.carousel-dots') ||
        e.target.closest('.btn-get-price') || e.target.closest('.description-toggle')) return;
    navigateTo('office_detail.php?slug=' + this.dataset.slug);
});
```

**After**:
```js
card.addEventListener('click', function(e) {
    if (e.target.closest('.btn-get-price') || e.target.closest('.description-toggle')) return;
    navigateTo('office_detail.php?slug=' + this.dataset.slug);
});
```

Same simplification applies to the keydown handler.

### 5. Remove `initCarousels(...)` calls

Both `renderCards` and `renderNearest` end with a call like `initCarousels(container);` or `initCarousels(el);`. With no `.card-carousel` elements in the DOM, this is a no-op, but it's safer to delete the call so dead code doesn't accumulate. The `initCarousels` and `scrollCarousel`/`goCarouselSlide` function definitions higher in the file can stay (orphaned) — they're harmless and removing them risks breaking some other page that shares the script.

### 6. Don't touch the CSS (yet)

The page's `<style>` block still contains rules for `.office-card-img`, `.card-carousel`, `.placeholder-img`, `.img-count`, `.carousel-btn`, `.badge-type`, `.badge-nearby`, plus the responsive `@media` overrides. They are now **dead CSS** but they don't render anything. Leave them alone unless the user explicitly asks for a CSS cleanup pass — chasing them down inside ~150 lines of media queries is high churn for zero visible benefit, and the user can always request `/simplify` later.

### 7. Don't touch helper functions

`imgErrorToPlaceholder(img)` (line ~1735 in furnished_offices.php) and `initCarousels(container)` are still defined and referenced only from `initCarousels`. Since you removed the call sites, they become dead code but they don't error. Don't remove them — they may be reused on other pages.

### 8. Static text in PHP headers (e.g. meta description, dropdown options)

`$metaDesc`, `$subheading`, and the `<option value="commercial" selected>Furnished / Unfurnished Office</option>` in the filter dropdown contain the words "Furnished" / "Unfurnished" but those are page-level SEO copy and filter UI, not card badges. Leave them — they're not what the user asked to remove ("text icons and every images containers" refers to per-card UI, not page-level labels).

### 9. Verify

After the edits, run:

```
grep_search pattern: "card-carousel|carousel-btn|carousel-dots|placeholder-img|img-count|badge-type|badge-nearby"
```

in the target file. The remaining matches should be only:
- `<style>` rules (CSS)
- Orphan helper functions like `imgErrorToPlaceholder`
- `initCarousels(container)` if you didn't remove the call

NO matches should appear in the JS template literals (i.e. lines inside the `renderCards` / `renderNearest` bodies).

Also grep for `listing_type_db === 'furnished' ? 'Furnished' : 'Unfurnished'` — that ternary was the badge text and must be gone.

Then `php -l <file>` to confirm PHP validity, and load the page to confirm no broken cards or layout.

## Common pitfalls

- **`badge-type` text logic differs between pages** — `furnished_offices.php` emits the badge (`o.listing_type_db === 'furnished' ? 'Furnished' : 'Unfurnished'`); `unfurnished_offices.php` does NOT, because all rows are `unfurnished`. Don't add a removal step to unfurnished_offices.php that doesn't exist there — you'll fail to find the variable.
- **`renderCards` uses `flex-lg-row`, `renderNearest` uses plain `.card`** — the two card variants have different layouts. Drop `flex-lg-row` from `renderCards` because there is no longer an image on the left to justify a row layout; keep `renderNearest` as plain `.card` (no change needed there beyond removing the image div).
- **Don't refactor the price-formatting block or the stats block** — they live in the same `forEach` body but are not part of the request. The user asked to remove image containers and type text only.
- **Don't remove the `description-toggle` / `btn-get-price` clauses from the click handler** — those buttons are still on the card and still need to be excluded from the navigation-click.
- **The two render functions are 100+ lines each** — the `old_string` for the edit tool must include enough context to be unique. Don't try to edit one variable at a time; replace the whole `forEach((o) => { … })` body, or the whole function, in one edit.

## Verified on

- `cubespace/public_html/furnished_offices.php` (both `renderCards` at line ~1973 and `renderNearest` at line ~2067) — 2026-06-30
- `cubespace/public_html/unfurnished_offices.php` (same two functions, same pattern) — 2026-06-30