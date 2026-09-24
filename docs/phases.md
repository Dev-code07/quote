# phases.md — Development Phases

Seven phases, matching the brief's section 29, expanded with concrete deliverables and exit criteria. Each phase ends with a reviewable, runnable increment. No phase starts before the previous phase's exit criteria are met.

**Version:** 1.2 | **Date:** 2026-09-24 | **Status:** Phases 0-7 complete (v1 feature-complete)

---

## Phase 0 — Foundation & Toolchain (prerequisite) — **DONE**

The brief requires the latest stable Laravel on PHP 8.3 with Vite; the repository holds a Laravel 8 skeleton on PHP 7.4. This phase removes that gap.

### Tasks
1. Install PHP 8.3+ and confirm `php -v`; keep Composer 2.10+.
2. Create a fresh Laravel 13 project and port the repository onto it (see `Architecture.md` 2.1).
3. `composer install`; `npm install`.
4. Install Laravel Breeze (Blade stack) — auth scaffolding.
5. Configure Vite + Tailwind: `resources/css/app.css`, `resources/js/app.js`, Alpine.js.
6. `php artisan about` confirms Laravel 13 / PHP 8.3+; `php artisan test` and `npm run build` succeed.
7. Attach the client-provided git remote; initial commit of the Laravel 13 skeleton (decision #11).

### Deliverables
- Laravel 13 app booting locally, Breeze login/register/reset pages, Tailwind + Alpine pipeline building.
- `main` branch pushed to the client repo.

### Exit criteria
- Login page renders with Tailwind styles; `npm run build` emits `public/build`.
- No Laravel 8 or Mix remnants.

---

## Phase 1 — Project Setup, Auth & Layout — **DONE**

Brief phase 1. Establish structure, authentication, and the app shell.

### Tasks
1. App shell: `layouts/app.blade.php` with fixed 232px sidebar, topbar, footer, mobile drawer (<=900px) — per prototypes.
2. Sidebar navigation: Dashboard, Quotes, Clients, Templates. User block at bottom, logout.
3. Auth: Breeze login/logout/password reset/email verification; `auth` + `guest` middleware; seeded admin user.
4. Design tokens in `tailwind.config.js` and `resources/css/app.css` (`design.md`).
5. Base Blade components: `x-button`, `x-card`, `x-input`, `x-select`, `x-modal`, `x-badge`, `x-toast`, `x-empty-state`, `x-pagination`, `x-swatch`.
6. Toast flash-message wiring; Alpine modal helpers.
7. Error pages (403/404/500) branded.

### Deliverables
- Branded shell, working login/logout, reusable component library.

### Exit criteria
- Login -> dashboard shell renders on desktop and mobile widths; logout works; all routes require auth.

---

## Phase 2 — Client Management — **DONE**

Brief phase 2.

### Tasks
1. Migration + `Client` model (soft deletes, `is_active`, `created_by`).
2. Client index: search, Active/Inactive filter, pagination, kebab actions (view, edit, create quote, deactivate, delete to trash).
3. Client create/edit via Alpine modal/form with inline validation errors.
4. Client show: profile card + that client's quotes list.
5. Trash view: list, restore, force delete (blocked while live quotes exist).
6. Factory + seeder with realistic Indian data.

### Deliverables
- Full client CRUD, deactivation, trash/restore.

### Exit criteria
- Create, edit, search, filter, deactivate, trash, restore, guarded force delete — all with feature tests.

---

## Phase 3 — Quote Template Management — **DONE**

Brief phase 3.

### Tasks
1. Migration + `QuoteTemplate` model incl. accent_color, header_alignment, letterhead_display_name, intro_message, default_gst_rate, signature/author fields (decisions #9, #10).
2. Template index: card grid (name, company, quote count, last used, default badge), "New Template" dialog with **Start from** select (blank / existing template).
3. Template editor in 4 steps (per prototype): Business Information -> Company Branding (logo upload, display name, alignment, accent swatches) -> Quote Settings (doc title, default GST, intro message with token hints, delivery/warranty/validity/terms/notes) -> Footer & Signature (authorized person, designation, signature upload).
4. Accent swatch picker (6 palettes) writing the CSS variables used by the A4 sheet.
5. Actions: edit, live A4 preview, duplicate, set default (single-default enforcement in service), delete to trash, restore.
6. Upload validation (MIME/extension/2 MB) and `storage:link` check.

### Deliverables
- Templates CRUD, branding, live preview, default/duplicate/trash.

### Exit criteria
- A template can be created from scratch or copied, previewed in A4, set as default (only one), duplicated, trashed and restored.

---

## Phase 4 — Quote Creation & Builder — **DONE**

Brief phase 4. The core of the product.

### Tasks
1. Migrations: `quotes`, `quote_items`, `quote_sequences`; `Quote`, `QuoteItem` models; `QuoteStatus` enum.
2. `QuoteNumberService` — yearly `QT-YYYY-NNNNN` sequence, transactional, gapless, unique.
3. `CalculationService` — item amounts, subtotal, clamped flat discount, GST, grand total, 2 dp; JSON endpoint for live summary.
4. `SnapshotService` — client_snapshot, template_snapshot, rendered intro with tokens, terms.
5. Builder page per prototype: template selection overlay, client picker + inline one-off client, header (auto number, dates, valid-until, optional enquiry no/date), dynamic items table, pricing block, terms block, sticky bottom bar.
6. Alpine interactions: add/remove/reorder items, live totals, unsaved-changes guard.
7. Save Draft and final save; edit reuses the same form.

### Deliverables
- End-to-end quote creation from template with correct numbers, totals and snapshots.

### Exit criteria
- Quote created from a template: unique number, server-verified totals, snapshot stored; client edits afterwards do not change it (tested).

---

## Phase 5 — A4 Preview & PDF — **DONE**

Brief phase 5.

### Tasks
1. `resources/views/pdf/quote.blade.php`: A4 page setup, strip, letterhead, meta, parties, intro, items table, totals, terms, amount in words, signature/stamp, page footer.
2. Accent variables injected from snapshot; self-hosted Hind + Zilla Slab.
3. `PdfService` (Dompdf) — `Download PDF` action and a print stylesheet for the on-screen preview.
4. On-screen A4 preview matching `quote_preview.html`: zoom control, multi-page split, repeated `thead`, "Continued from page N", closing block on the final page.
5. `AmountInWordsService` (Indian lakh/crore).

### Deliverables
- Pixel-faithful A4 preview and downloadable, correct PDF.

### Exit criteria
- PDF generated server-side with correct totals, words, branding, and page breaks on a long quote; no client-side screenshotting.

---

## Phase 6 — Quote Management & Dashboard — **DONE**

Brief phase 6.

### Tasks
1. Quote index per prototype: stat cards (Total/Draft/Sent/Approved/Expired/Total Value), search, status + date filters, table (No., Client, Date, Items, Amount, Status), kebab actions, pagination, empty state.
2. Quote show: full preview, status actions, edit, duplicate, trash, archive.
3. Duplicate: new number, Draft, today's date, items + terms copied.
4. Status workflow: Draft -> Sent -> Approved; `QuoteStatus` transitions validated.
5. `quotes:expire` command + daily scheduler for auto-Expired (decision #7).
6. Dashboard: stats + recent quotes + Create New Quote.
7. Trash views for clients, templates, quotes.

### Deliverables
- Complete quote lifecycle: create, list, filter, status, duplicate, PDF, archive/restore.

### Exit criteria
- Cron expires eligible quotes; status never set manually to Expired; dashboard figures match the database.

---

## Phase 7 — Security, Performance & Deployment — **DONE (code); hosting deploy pending**

Brief phase 7.

### Tasks
1. Security pass: policies on every record action (no IDOR), CSRF audit, XSS audit (no unescaped user data), upload hardening, rate-limited login, `APP_DEBUG=false` for production.
2. Performance: eager loading, indexes on filter columns, pagination everywhere, `config/route/view` caching, compressed images, production Vite build.
3. Full Definition-of-Done walkthrough (`PRD.md` section 9) with the client.
4. Shared-hosting deployment runbook (`Architecture.md` section 9); deploy to a staging host and verify PDF + cron.
5. Final test suite green; hand over documentation.

### Deliverables
- Production-ready deployment on shared hosting, DoD signed off.

### Exit criteria
- Every DoD checkbox demonstrably passes on the staging host, including PDF download, auto-expire, and trash/restore.

---

## Summary

| Phase | Focus | Key exit test |
|---|---|---|
| 0 | Laravel 13 + Vite + Breeze foundation | **Done** — app boots, build passes |
| 1 | Auth + app shell + components | **Done** — shell renders at all breakpoints |
| 2 | Clients | **Done** — CRUD + search + filter + trash/restore, 16 tests |
| 3 | Templates | **Done** — CRUD, branding, preview, duplicate, default |
| 4 | Quote builder | **Done** — numbering, totals, snapshots |
| 5 | A4 preview + PDF | **Done** — real Dompdf PDF verified |
| 6 | Quote management + dashboard | **Done** — lifecycle, filters, auto-expire |
| 7 | Security + deploy | **Code done**; live hosting deploy still pending |
