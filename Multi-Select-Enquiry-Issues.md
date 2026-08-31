# Multi Select Enquiry — Issue Audit

> Generated: 2026-08-31  
> Scope: `assets/js/multi-select-enquiry.js`, `assets/css/multi-select-enquiry.css`, `assets/css/listing-pages-mobile.css`, `api/contact.php`, `managed_offices.php`, `furnished_offices.php`, `unfurnished_offices.php`, `admin/contacts.php`, `src/EmailService.php`

---

## 1. Critical — Feature Parity / Missing Integration

**1.1 `unfurnished_offices.php:1-1522` completely missing Multi Select Enquiry**
- No CSS include (`multi-select-enquiry.css`), no JS include (`multi-select-enquiry.js`), no `#btnToggleMultiSelect` button, no `MultiSelectEnquiry.init()` call.
- `managed_offices.php:93,1554,2490` and `furnished_offices.php:98,1559,2495` have it; `unfurnished_offices.php` is the only listing page without it. Users on Unfurnished cannot multi-enquire.

**1.2 `furnished_offices.php:2495-2498` shared storageKey for two product types**
- `interest: 'furnished'` + `storageKey: 'cubespace_multi_select_furnished'` stores both `furnished` and `unfurnished` (`o.listing_type_db` fallback at `furnished_offices.php:2090`) under one key. ID `5` in `furnished_offices` and ID `5` in `unfurnished_offices` collide (`managed:furnished:5` vs `unfurnished:5` share numeric part).

**1.3 Version cache bust drift**
- Hard-coded `?v=1` in `managed_offices.php:93,1554` and `furnished_offices.php:98,1559`. Fixing JS/CSS requires manual bump in multiple files; stale CDN cache risk.

---

## 2. Core JavaScript Logic — `assets/js/multi-select-enquiry.js`

### 2.1 Key / Identity
- **`56:60` `officeKey()` inconsistency** — uses `office.listing_type || office.listing_type_db || this.interest`. Elsewhere `officeFromCard:222` only reads `card.dataset.listingType`. `addOffice:94` normalizes to `listing_type` only. Types drift.
- **`61:68` `isSelected(id, listingType)` legacy fallback** — if `listingType` absent, checks `selections[String(id)]` then `k.split(':')[1] === String(id)`. Any type with same numeric ID returns `true` — wrong checkbox state across types.
- **`107:118` `removeOffice()` destructive without type** — `Object.keys(...).forEach(k => k.split(':')[1]===String(id) && delete ...)` deletes *all* types sharing numeric ID. Selecting `managed:5` and `furnished:5` then removing one deletes both.

### 2.2 State / Persistence
- **`13:49` `sessionStorage` only** — selections lost on tab close, not shared across tabs. Quota errors swallowed (`catch(e){/* ignore */}` at `48`), silent data loss.
- **`27:42` `load()` no validation** — `JSON.parse` result not checked for shape; malformed `{ "managed:1": null }` accepted, `getSelections:72` then maps `null.title` crash.
- **`392:489` `injectUI()` + `init()` double creation** — appends `#multiEnquiryBar` and `#multiEnquiryModal` to `body` each `init`. Soft navigation (`cubeNavigate`) leaves stale bar from previous `interest`/`storageKey` — ghost selections, duplicate IDs.
- **`483:486` `new bootstrap.Modal()` without singleton check** — multiple init calls create multiple modal instances, `modal.hide()` only hides last.
- **`392:417` `bindToggle()` `dataset.mseBound` guard** — after `injectUI` recreates bar, old guard persists on old button, new buttons not bound; after SPA navigation bindings broken.

### 2.3 Add / Toggle / Clear
- **`75:84` `toggleMode()` side effects** — calls `updateUI()` then `loadListings()` async. Checkboxes remain hidden until fetch returns — flash of wrong state. No `currentPage` reset; hidden-page selections still counted in bar.
- **`86:105` `addOffice()`** — `parseInt(office.id,10)` truncates non-numeric IDs, `title/listing_code` from DOM not sanitized beyond `esc` later. Limit toast at `91` is client-only, bypassable.
- **`120:125` `clearAll()`** — syncs visible cards but does not close modal if open — `multiEnquiryPreview` shows stale list until next `updateUI`.
- **`248:271` `updateUI()` bar visibility independent of mode** — `bar.visible = count>0` even when `enabled === false`. Bar with padding `body.multi-enquiry-bar-open` shows while checkboxes hidden — confusing.

### 2.4 Card Rendering / Interaction
- **`127:149` `renderCardShell()` string HTML** — `innerHtml` injected raw, `id` inserted without `esc()`, `ariaLabel` escaped but `data-office-id` raw. `tabindex=0 role=link` on wrapper while in select mode violates semantics.
- **`182:197` `bindCardNavigation()`** — card `click` + `keydown Enter/Space` always `navigateTo('office_detail.php?slug=...')` even when `multi-select-active`. Tapping card body (not checkbox) navigates away during selection. `Space` should toggle checkbox but triggers navigation.
- **`199:207` `shouldIgnoreClick()`** — ignores `ws-select-col/cb/label` but not `card-body` padding — near-miss click still navigates.
- **`209:224` `officeFromCard()` brittle** — extracts `listing_code` from `.card-title code` then `title.replace(listingCode,'')` does substring replace; code substring inside title (e.g., title contains code letters) corrupts title stored in selection.
- **`158:180` `bindCardCheckboxes()` stopPropagation on `click` + `change`** — double handler; `change` already stops, `click` redundant, but `label onclick="event.stopPropagation()"` inline JS in `141` violates CSP.

### 2.5 Sync
- **`226:246` `syncVisibleCards()` / `syncCardState()`** — queries `querySelector('.custom-card[data-office-id="'+id+'"]')` only first match. Same office appears in main list + `nearestSection` (duplicate ID) — second instance never synced. Selector not escaped for special characters.
- **`233:239` fallback DOM lookup** — `if(!card && listingType) querySelector([data-office-id][data-listing-type])` then fallback without type — picks wrong type if ID duplicates.

### 2.6 Selected List / Modal
- **`273:312` `renderSelectedList()`** — rebuilds `#multiEnquirySelectedList.innerHTML` each call then re-binds `[data-remove-id]` with `dataset.mseRemoveBound` guard. Since nodes are recreated, guard resets every render — re-binding every time, old listeners GC’d but wasteful. `listEl` vs `listEl2` duplicate query.
- **`277:298` preview rendering** — two separate DOM writes (`listEl` + `previewEl`) with duplicated `esc` logic; no deduplication.
- **`315:327` `openModal()` auto-enables** — `if(!enabled){ enabled=true; save(); updateUI(); }` silently flips mode, calls `updateUI()` but not `syncVisibleCards()` — checkboxes stay hidden behind modal.
- **`315:327` no validation of stale IDs** — modal shows selections even if underlying listings now `status!='active'` (deleted). User submits invalid IDs, backend silently filters at `api/contact.php:95-97`.

### 2.7 Submit
- **`329:370` `handleSubmit()` double-submit race** — replaces `btn.innerHTML` but never sets `btn.disabled`/`aria-disabled`. Rapid clicks fire parallel `postContact()` → duplicate DB rows.
- **`331` `CSForms.validate` bypass** — validation depends on global `CSForms`; if missing, no validation.
- **`340:346` FormData handling** — deletes `office_id`/`listing_code` but keeps single `interest` (`managed`/`furnished`) even when `selections` contains mixed `listing_type` — parent `interest` loses per-workspace granularity.
- **`343:344` `offices_json = JSON.stringify(selections)`** — client-supplied `title`/`listing_code` trusted; backend re-fetches but still stores client fallback if DB row missing.
- **`348,351,367` button restore** — on error restores innerHTML but never re-enables if disabled elsewhere.
- **`353:359` success sequence race** — `form.reset()` → `modal.hide()` (async animation) → `clearAll()` → `enabled=false` → `loadListings()`. Re-render happens while modal still animating — list flashes cleared behind backdrop.
- **`372:377` `postContact()` path divergence** — `CubeAPI.postForm('/api/contact.php')` vs `fetch(apiUrl('/api/contact.php'))` use different base resolution (`CubeBase.url` vs hard `/api/...`). Subfolder deployments diverge.

### 2.8 Utilities
- **`379:383` `toast()` fallback `alert()`** — blocking alert if `showToast/CubeToast` missing — jarring mobile UX.
- **`385:390` `esc()`** — creates `div` per call, no caching; returns '' for `0` (falsy) — `esc(0)` incorrectly empty.
- **`75:84` `toggleMode()` global leak** — relies on `global.loadListings` existence; if renamed, falls back to `syncVisibleCards` only — nearestSection not synced in fallback path (`document` root vs container).

---

## 3. Backend — `api/contact.php`

- **`32:43` validator `interest in:managed,furnished,unfurnished`** — frontend sends single `interest` (`managed`/`furnished`) even when `offices_json` contains mixed types — validated but semantically wrong.
- **`67:76` limit enforcement gap** — checks `count(decoded)>15` before filtering, then loop `if(!is_array) continue; if(oid<1||!isset(tableMap[otype])) continue; if(!officeRow) continue;` silently skips invalid/junk entries. Attacker sends 15 entries, 14 invalid → only 1 persisted, bypassing effective limit.
- **`78:82` `$tableMap` table name interpolation** — `SELECT ... FROM {$tableMap[$otype]}` uses string interpolation; safe only via whitelist `isset`, still not parameterized for table name, audit flag.
- **`92:97` N+1 queries** — loop prepares/executes 1 query per office (up to 15), no bulk `WHERE id IN (...)`.
- **`99:105` client data trust** — falls back to client `otitle/ocode` if `officeRow['title']` empty (`?:`). Tampered title persisted in `message`.
- **`112:121` `workspacesSummary` plain text** — concatenated `title [code] (ID: ..., type)` without sanitization, stored in `contacts.message`. Admin `admin/contacts.php:67` renders `source` badge only, but `message` displayed without `escHtml` → stored XSS if title contains HTML.
- **`122:125` overwrite semantics** — forces `$source='multi_select_enquiry'`, `$officeId=''`, `$listingCode='MULTI (n)'`, `$message = summary + "\n\nAdditional message:\n" + $message`. If `summary + message` exceeds DB `message TEXT` length, `mysqli_stmt_execute` fails → 500 with no user feedback.
- **`128:152` insert `office_id=null`** — `contacts.office_id` FK expects int; `null` allowed but downstream queries (`admin/contacts.php`, email) assume joinable ID — broken relations.
- **`122` honeypot bypass** — `$_POST['website']` checked but `multiEnquiryModal` form at `multi-select-enquiry.js:458-476` has no honeypot field — spam bots bypass easily vs other forms that include it.
- **No CSRF / no rate limit** — `api/contact.php:17` checks POST only, no token, no IP throttling. `MAX_SELECTIONS` toast at client is only defense.
- **`134:143` double validation of `office_id`** — even for multi-select, still runs `SELECT ... UNION ... WHERE id=?` if `officeIdVal !== null` path taken, but `officeId` forced to '' for multi, so dead code path confusion.
- **`154:201` response-then-email pattern** — `fastcgi_finish_request()` flushes response, then `EmailService->notifyAdminNewContact` runs background. If mail fails (`EmailService.php:155` logs but user already saw `success`), no retry, silent loss. If `fastcgi_finish_request` missing (Apache mod_php), fallback `ob_end_flush()+flush()` may not actually disconnect, user waits for SMTP.

---

## 4. Admin / Email

- **`admin/contacts.php:67`** only checks `if($row['source']==='multi_select_enquiry')` badge, does not render `workspacesSummary` or `selected_offices` — admin cannot see which workspaces were selected without opening `message` blob.
- **`src/EmailService.php:155` `logEmail` on failure** — logs `contact['name']` unsanitized into email log, potential log injection.
- Email template likely expects single `office_id`/`listing_code`; multi-select `listing_code='MULTI (n)'` breaks template assumptions (link to office detail broken).

---

## 5. CSS / Layout — `assets/css/multi-select-enquiry.css` + `listing-pages-mobile.css`

- **`multi-select-enquiry.css:29-47` `.ws-card-select-row` + `.ws-select-col {display:none}` toggle via `body.multi-select-active`** — adding 44px (32px mobile) column causes layout reflow; carousel `scrollLeft` initialized before toggle is misaligned, images shift.
- **`multi-select-enquiry.css:39-43` `.ws-select-col {flex:0 0 44px; background:#f8fafc; border-right:1px solid #e5e7eb}`** — full-height column stretches, covers carousel dots (`z-index` not set), reduces tap target for dots.
- **`multi-select-enquiry.css:72-97` `#multiEnquiryBar {z-index:1040; position:fixed; bottom:0}`** — `z-index 1040` equals navbar but below Bootstrap modal `1055`; bar can peek over modal backdrop overlay on iOS. `transform:translateY(100%)` transition not `will-change`, janks.
- **`multi-select-enquiry.css:125-131` `#multiEnquirySelectedList {max-height:120px; overflow-y:auto}`** — 120px truncates 15 items, no fade indicator. Mobile override `listing-pages-mobile.css:665-668` `max-height:72px` even smaller — user cannot see all selections without scrolling an unlabeled region.
- **`multi-select-enquiry.css:173-175` `body.multi-enquiry-bar-open {padding-bottom:72px}`** — fixed 72px not accounting for bar height when `flex-direction:column` on mobile (`listing-pages-mobile.css:188-194`). Mobile sets `calc(88px + env(safe-area-inset-bottom))` at `675` — desktop/mobile mismatch.
- **`listing-pages-mobile.css:646-677` mobile bar refinements** — duplicate `@media (max-width:767.98px)` block; `ws-select-col 32px` too narrow for 17px checkbox + label padding — hit area below 44px WCAG.
- **`multi-select-enquiry.css:9-27` `#btnToggleMultiSelect` styles** — `font-size:0.8rem; padding:5px 14px` but mobile `listing-pages-mobile.css:704` `font-size:0.68rem !important` — inconsistency, text truncates `Multi Select Enquiry` on 360px.
- **`managed_offices.php:93` + `furnished_offices.php:98` `listing-pages-mobile.css?v=2` loaded after `multi-select-enquiry.css?v=1`** — mobile overrides intended but order dependency fragile.

---

## 6. Accessibility (a11y)

- **Nested interactive** — `.custom-card` has `role=link tabindex=0` wrapping `button.btn-get-price`, `input.ws-select-cb`, and carousel `button.carousel-btn` — axe violation, screen reader confusion.
- **Checkbox label** — `assets/js/multi-select-enquiry.js:141-143` `<label><input aria-label="Select workspace">` generic label, no workspace name, SR announces only “Select workspace” without context.
- **Bar region** — `injectUI:423` `role=region aria-label=Selected workspaces` but list items `div.selected-item` not `role=list/listitem`, remove buttons not in accessible list.
- **Keyboard** — `bindCardNavigation:190` `keydown Space` calls `preventDefault` then `navigateTo` — Space should toggle checkbox in select mode, not navigate. No `Space` handling for checkbox column.
- **Focus** — `ws-select-cb` has no visible `:focus` ring (`multi-select-enquiry.css:59` only `accent-color`), keyboard focus invisible.
- **Inline handler** — `label onclick="event.stopPropagation()"` at `141` is inline JS, blocked by CSP `script-src 'self'`.
- **Aria-pressed sync** — `updateUI:254` sets `aria-pressed` correctly, but button `innerHTML` swap not announced (`aria-live` missing).

---

## 7. Data / Observability / Security Misc

- **Validation mismatch** — frontend `multiEnquiryForm` at `458:466` `data-rules="required|phone" maxlength=10` but backend `api/contact.php:34` `phone required|phone` allows longer, and `email` optional in modal but required elsewhere.
- **Phone maxlength 10** — multi-modal `mePhone` hard `maxlength=10` fails for `+91` international input, while `api/contact.php` validator may allow `+91`.
- **Deduplication / Spam** — same phone can submit 15 workspaces repeatedly, no deduplication, no captcha.
- **`sessionStorage` key not namespaced by user** — shared device: second user sees first user’s selections in same tab.
- **No analytics / no `publish_event` for multi-select specifically** — `publish_event('contact_created')` generic, cannot distinguish multi vs single in realtime dashboard without parsing `source`.
- **Stale selection blindness** — selecting on page 1, filtering to page 2 hides page-1 cards, user forgets selections, submits without reviewing hidden selections — no “Review 5 hidden” warning.

---

## 8. Summary Severity

| Severity | Count | Examples |
|---|---|---|
| **Critical** | 5 | Missing on unfurnished page, ID collision, N+1 + silent skip bypass, no rate limit/CSRF, bar over modal |
| **High** | 12 | Double-submit race, destructive `removeOffice`, navigation hijack in select mode, `isSelected` false positive, `sessionStorage` loss |
| **Medium** | 14 | Layout reflow, a11y nested interactive, honeypot missing, email silent fail, preview stale |
| **Low** | 6 | Version `?v=1` drift, duplicate CSS media blocks, `esc(0)` bug, `will-change` jank |

---

*End of audit — no fixes applied, issues only.*
