---
name: php-section-fix
description: When the user pastes a PHP/HTML snippet and asks to "fix all issues" in that section, use grep_search to locate the exact line in the target file (rather than guessing from cursor position), then check for HTML syntax errors, helper-function consistency, and matching CSS rules before editing.
source: auto-skill
extracted_at: '2026-06-30T06:19:55.658Z'
---

## When to use

A user pastes a PHP/HTML snippet and asks to "fix all issues" / "fix all bugs" / "clean up" in that section of a specific file. The file is a long mixed PHP/HTML page (typical for CMS-style detail pages).

## Procedure

### 1. Locate the section by grep, not by cursor position

The user's snippet is usually a copy from elsewhere (e.g. `managed_offices.php`) but they ask to fix it in a different file (e.g. `office_detail.php`). The active-file cursor line rarely corresponds to the snippet's actual line in the target file.

- Run `grep_search` on the target file for an unambiguous substring from the snippet (a class name, an `id`, a comment like `<!-- Amenities -->`).
- Do NOT assume the snippet appears at the cursor line — verify with `read_file` at the matched offset before editing.

### 2. Scan the snippet for these specific bug classes

In order of severity:

1. **Unclosed HTML attributes** — most common silent page-breaker. Look for:
   - `<div class="foo>` (missing closing `"`)
   - `<input name='x>` (mixed single/double quote issues)
   - Unclosed `<a href=`, `<img src=`, etc.
   These break the entire page rendering, not just the snippet.
2. **Stray `<` or `>`** in attribute values, e.g. `class="amenities-grid>`.
3. **Mismatched tag nesting** — every `<div>` must have a closing `</div>`, including the wrapper around PHP control structures like `<?php if (...): ?>` / `<?php endif; ?>`.

### 3. Check helper-function consistency with the surrounding file

Files in this project use an `e()` helper from `lib/config.php` for HTML escaping (it's `htmlspecialchars()` plus project conventions). When fixing a snippet, scan the rest of the file:

- If the file consistently uses `e($var)` for output, replace any stray `htmlspecialchars($var)` inside the snippet with `e($var)` for consistency.
- If the file consistently uses `htmlspecialchars()`, do the reverse.

Run a quick grep like `grep_search` for `echo htmlspecialchars` and `echo e(` to learn the local convention before deciding.

### 4. Check that referenced functions actually exist

Before assuming a helper like `e()` is available, `grep_search` for `function e` in the `lib/` directory. If it exists, the snippet can use it; if not, flag it to the user instead of silently leaving broken code.

### 5. CSS-side checks for "no hover / add background" requests

When the user asks to add background colors and remove hover effects on a CSS-driven section:

- `grep_search` for the class (e.g. `\.amenity-item`) to find every rule including `:hover` and `transition:`.
- The base rule, every `:hover` rule, and every `transition:` declaration are all candidates to remove/edit.
- Do NOT touch responsive `@media` overrides unless they reintroduce hover — they usually only adjust grid/border layout.
- Keep the border system intact (don't strip borders that define the grid lines).

### 6. Verify with read_file after every edit

Read 5-10 lines around the edit to confirm:
- No leftover stray characters from the old_string (e.g. a forgotten `</div>`).
- Surrounding PHP control structure still terminates correctly.
- Indentation matches the rest of the block.

### 7. Verify the section end

After the snippet ends (e.g. `<?php endif; ?>`), `read_file` 5 lines past it to confirm the next section starts cleanly and no closing tag was orphaned by the edit.

## Common pitfalls

- **The snippet's host file may differ from the active file** — always grep the target file the user named, not the file the editor currently has open.
- **`grep_search` with a literal phrase beats `read_file` line-offset guesses** — when the edit tool says "0 occurrences found", the file probably has different whitespace or the snippet was from a different file. Don't keep retrying with whitespace variations; re-grep for a unique substring first.
- **Don't over-fix** — the user asked to fix the snippet's issues, not to refactor the whole page. Limit changes to the section they pointed at unless something nearby is clearly broken too.
- **Background-color + hover removal are coupled concerns** — if you remove `:hover`, also remove `transition: ...` from the base rule, otherwise the transition still runs on focus/active states and looks broken.

## Verified on

- `cubespace/public_html/office_detail.php` — Amenities section (line 849 quote fix + `htmlspecialchars` → `e()` consistency) and CSS rules for `.amenity-item` (background tint, no hover) — 2026-06-30.