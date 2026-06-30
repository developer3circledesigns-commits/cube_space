---
name: enterprise-sharp-corners
description: Convert any PHP/HTML page with custom CSS + Bootstrap into a fully sharp 90° enterprise interface by stripping every border-radius and applying a consistent 1px border system via CSS variables.
source: auto-skill
extracted_at: '2026-06-30T05:33:36.904Z'
---

## When to use

A user asks to make a page look "enterprise", "professional", "sharp 90° corners", or to remove all rounded corners / pill shapes / capsule buttons across an existing page that mixes custom CSS with Bootstrap (or any CSS framework that ships rounded defaults like `.rounded`, `.btn`, `.card`, `.modal-content`, `.form-control`, etc.).

The page already exists; do not redesign it — only restyle. Layout, grid, JS, and behaviour must be preserved.

## The technique (in order)

### 1. Add a global reset block at the very top of the page's `<style>` tag

```css
*, *::before, *::after {
    border-radius: 0 !important;
}
:root {
    --cs-border: #dcdcdc;             /* pick ONE border color and reuse */
    --cs-border-strong: #d0d0d0;
    --cs-card-shadow: 0 2px 6px rgba(0,0,0,0.05);
    --cs-card-shadow-hover: 0 3px 10px rgba(0,0,0,0.07);
}
```

The universal selector with `!important` is the safest way to defeat every Bootstrap / framework default without hunting each declaration.

### 2. Explicit Bootstrap class overrides (because the universal selector alone misses some library utilities in some bundlers)

```css
.rounded, .rounded-0, .rounded-1, .rounded-2, .rounded-3, .rounded-4, .rounded-5,
.rounded-circle, .rounded-pill,
.rounded-top, .rounded-bottom, .rounded-start, .rounded-end {
    border-radius: 0 !important;
}
.btn, .form-control, .form-select, .form-check-input,
.badge, .alert, .toast,
.modal-content, .modal-header, .modal-footer, .modal-body,
.dropdown-menu, .dropdown-item,
.nav-pills .nav-link, .nav-link, .breadcrumb-item,
.pagination .page-link, .pagination .page-item,
.list-group-item, .card,
.popover, .tooltip,
.progress, .progress-bar,
.img-thumbnail, .placeholder,
.placeholder-glow .placeholder, .placeholder-wave .placeholder {
    border-radius: 0 !important;
}
.modal-content { border: 1px solid var(--cs-border); box-shadow: var(--cs-card-shadow); }
.placeholder    { border: 1px solid var(--cs-border); }
.btn            { border-width: 1px; }
```

### 3. Strip inline `border-radius` from PHP heredocs / error pages / `style="..."` attributes

`grep` for `border-radius` and replace each literal value (8px, 12px, 50%, 999px, 100px 0 0 100px, etc.) with `0` or `0 !important`.

### 4. Replace every existing rounded declaration in the page's stylesheet

Search for the listed values and rewrite:

- `border-radius: 4px / 6px / 8px / 10px / 12px / 16px / 20px / 24px / 30px / 50% / 999px` → remove
- `border-radius: 100px 0 0 100px` (pill banner) → remove + add a regular `border: 1px solid <color>`
- `border-radius: 50%` on avatar circles — leave the element but reset radius to 0 (the element will become a square; that is the user's intent for "no exceptions")

### 5. Standardise borders to ONE color

Replace every `border: 1px solid #e0e0e0`, `border: 1px solid #dcdcdc`, `border: 1px solid #d2d3ee`, `border: 1px solid #f1f5f9` with `border: 1px solid var(--cs-border);` so a single change of the variable re-themes the whole page.

Components that previously had no border (cards, info strips, banners, sticky bars) should receive `border: 1px solid var(--cs-border);`.

### 6. Card design recipe (replicate per card)

```css
background: #fff;
border: 1px solid var(--cs-border);
border-radius: 0 !important;
box-shadow: var(--cs-card-shadow);            /* very subtle */
transition: box-shadow 0.2s;
```
Hover: `box-shadow: var(--cs-card-shadow-hover); border-color: <accent>;`

### 7. Form control recipe

```css
input, select, textarea {
    border: 1px solid var(--cs-border);
    border-radius: 0 !important;
    transition: border-color 0.15s, background 0.15s;
}
input:focus, textarea:focus, select:focus {
    border-color: <accent-color>;
    background: #fff;
}
```

### 8. Buttons

Keep existing colours, hover animations, icons, padding, typography. Only remove `border-radius`. Add `border-width: 1px;` if the button currently has `border: none` so the focus outline has a visible footprint.

### 9. Verify

1. `php -l <file>` — ensure PHP is still valid (inline `border-radius:0` substitutions in heredocs can break things if a quote escapes).
2. `grep -n "border-radius" <file>` — every remaining match must be either `0`, `0 !important`, or the global reset rule itself.
3. Load the page in a browser and tab through form controls / open every modal / expand every dropdown to confirm no element slipped through with rounded corners.

## Common pitfalls

- **Forgot the universal `*::before, *::after`** — pseudo-elements (`::after`, `::before`) used for gradients, dividers, and toast tails often have their own `border-radius`. Always include them.
- **Bootstrap JS-injected widgets** — Bootstrap's modal/dropdown/toast markup is added to the DOM at runtime. The universal selector still catches them because they match `*`.
- **Shadow + border conflict** — `box-shadow` and `border` together look heavier than expected; use a very light shadow (`0 2px 6px rgba(0,0,0,0.05)`) and the border does most of the visual work.
- **Pill banners that lose their meaning when squared** — banners that used `border-radius: 100px 0 0 100px` for a "ribbon" effect still look fine when squared if they have a background gradient; otherwise add a 1px border in the same hue family.
- **Inline styles in PHP heredocs** — easier to fix at the string level than to override with CSS, but both work. Prefer the string fix so the source is clean.

## Verified on

- `cubespace/public_html/office_detail.php` (PHP + Bootstrap 5.3.3 + custom CSS + Font Awesome + lightbox.js) — 2026-06-30.