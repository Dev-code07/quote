# memory.md — Project Memory

Living document for the Quotation & Proforma Invoice Management System. Update it whenever a decision, constraint, or environment fact changes. This is the first context file an agent or new developer should read.

**Last updated:** 2026-09-24 | **Version:** 1.2 | **Status:** Phases 0-7 complete; v1 feature-complete; hosting deploy pending

---

## 1. Project Snapshot

- **What:** Web-based quotation / proforma invoice manager — clients, branded templates, quote builder, A4 preview, Dompdf PDF, status tracking. First module of a larger business suite.
- **Who uses it:** One company, one admin user (decision #2). No roles in v1.
- **Hosting:** Shared hosting — PHP 8.3+, MySQL 8, Apache, Composer, SSL, cron. No Redis/queues/Docker/Supervisor/Node server.
- **Repo:** `d:\rahul\codemattrix_project\quote` (Windows/WAMP workstation).
- **Status:** Phases 0-7 complete. Clients, templates, quote builder, A4 preview, Dompdf PDF, lifecycle, auto-expire and dashboard all working. 138 tests green. Only live shared-hosting deployment remains.

---

## 2. Source Documents (read and analysed)

| File | What it provided |
|---|---|
| `Quotation_Management_Software_Development_Brief.docx` | Full 31-section requirements: scope, hosting, stack, flow, entities, calculation, statuses, PDF, dashboard, security, uploads, schema, duplication, UI, structure, phases, DoD. |
| `quoteflow_dashboard.html` | Quotes list: stat cards, filters, table, kebab actions, pagination, empty state, toast, template-selection overlay, drawer sidebar. |
| `quoteflow_quote_builder (1).html` | Create/Edit quote: stepped sections (client, items, pricing, terms), live totals, bottom action bar, A4 preview overlay. |
| `quoteflow_templates.html` | Template cards grid, create dialog (name/company/start-from/accent), delete confirm, A4 sheet styles. |
| `quoteflow_template_editor.html` | 4-step template editor: business info, branding (logo/display name/alignment/accent), quote settings (title/GST/intro tokens/terms), footer & signature. |
| `quote_preview.html` | A4 preview: zoom, multi-page pagination JS, repeated table header, continuation notes, totals/terms/signature. |

All six files remain in `docs/` as references.

---

## 3. Client Decisions (confirmed 2026-09-24)

| # | Decision |
|---|---|
| 1 | Upgrade to **latest stable Laravel (13.x, PHP ^8.3)** + Vite + Breeze + Tailwind, per the brief. |
| 2 | **Single user**, admin only — no roles. |
| 3 | **One company**, many clients; templates own their branding. |
| 4 | Quote uses a saved client **or** an inline one-off client (auto-saved). |
| 5 | GST = (subtotal − discount) x rate, **added on top**, 2 dp. |
| 6 | Discount = **flat Rs.** only. |
| 7 | Draft/Sent/Approved manual; **Expired automatic** via cron. |
| 8 | Any delete -> **trash/archive, restorable** (all entities). |
| 9 | Add optional **Enquiry No./Date** fields; keep `{enquiry_no}`/`{enquiry_date}` tokens. |
| 10 | Include prototype extras: accent colours, header alignment, letterhead name, start-from-existing. |
| 11 | Git: repository provided by the client — attach in Phase 1. |
| 12 | Docs live in `docs/`; file named `Architecture.md` (correct spelling). |

---

## 4. Environment Facts

- **Repository skeleton:** REPLACED. Was Laravel 8 + Mix; now **Laravel 13.33.0** with Breeze, Vite 7, Tailwind 4, Alpine 3.
- **Local PHP:** **8.4.26** installed at `C:\tools\php84` and added to the User PATH (takes precedence over WAMP PHP 7.4.33, which is untouched). Extensions: curl, fileinfo, gd, intl, mbstring, exif, openssl, pdo_mysql, pdo_sqlite, sqlite3, sodium, zip.
- **Composer:** 2.10.3 installed.
- **Database:** MySQL 8, database name `quote` (from `.env`).
- **Git:** initialised on `main`; remote `https://github.com/Dev-code07/quote.git` attached. Phase 0 pushed as `37e2a37`; Phase 1-2 pushed as the Phase 2 commit.
- **Laravel 13 fact (verified 2026-09-24):** latest 13.33.0, released 2026-03-17, requires PHP ^8.3 (8.3–8.5), active support to 2027-09-30, security to 2028-03-17. Laravel 12 reached end of active support 2026-08-13, confirming 13 as the correct target.

---

## 5. Key Technical Decisions

| # | Decision | Why |
|---|---|---|
| AD-1 | Laravel 13 monolith, server-rendered Blade | Brief: latest stable + shared hosting only |
| AD-2 | Alpine.js, no JS framework | Matches prototypes, tiny bundle, no Node server |
| AD-3 | JSON snapshot columns on `quotes` | Immutable history in one query (BR-01) |
| AD-4 | `quote_sequences` table for quote numbers | Gapless, collision-free, no Redis |
| AD-5 | Dedicated `pdf/quote.blade.php` + Dompdf | Brief forbids browser screenshots |
| AD-6 | Self-hosted Hind + Zilla Slab fonts | Dompdf must render without internet |
| AD-7 | File cache/session drivers | No Redis on shared hosting |
| AD-8 | `SoftDeletes` on clients/templates/quotes | Decision #8 — restore safety |

---

## 6. Assumptions in Force

- **A1** Template change allowed only while a quote is Draft; change re-snapshots.
- **A2** Force delete blocked while live quotes reference a client/template.
- **A3** Dates `d M Y`; INR only; Indian digit grouping.
- **A4** No email sending in v1.
- **A5** Fonts self-hosted in `public/fonts`.
- **A6** Auto-expire is daily, skips Approved quotes.
- **A7** Enquiry No./Date optional; tokens blank when absent.

---

## 7. Open Items

- [x] Git remote provided and attached (decision #11) — `https://github.com/Dev-code07/quote.git`.
- [x] PHP 8.4.26 installed on the workstation.
- [ ] Shared-hosting credentials for staging deployment (Phase 7).
- [ ] Final logo/branding assets from the client for default templates.

---

## 8. Document Map

| File | Purpose |
|---|---|
| `PRD.md` | Product requirements, scope, functional/non-functional requirements, DoD, decisions, assumptions. |
| `Architecture.md` | Stack, migration plan, directory layout, DB schema, services, security, deployment. |
| `rules.md` | Binding development conventions (stack, data, business, security, frontend, PDF, testing, git). |
| `phases.md` | Phase 0–7 plan with tasks, deliverables, exit criteria. |
| `design.md` | Design tokens, component catalogue, screen map, A4 document spec. |
| `memory.md` | This file — project memory and decision log. |

---

## 9. Build Log

| Phase | Status | Notes |
|---|---|---|
| 0 — Foundation | Done | PHP 8.4.26 installed; Laravel 8 -> 13.33.0; Breeze; Vite 7; Tailwind 4 (CSS-first `@theme`); Alpine 3; MySQL 8 InnoDB; 25 tests green. |
| 1 — Shell & components | Done | App shell (232px sidebar, topbar, mobile drawer <=900px), design tokens in `@theme`, component library (`x-button`, `x-card`, `x-input`, `x-select`, `x-textarea`, `x-badge`, `x-modal`, `x-toast`, `x-empty-state`, `x-pagination` via `links()`, `x-section-header`, `x-page-footer`, `x-module-placeholder`). |
| 2 — Client management | Done |
| 3 — Templates | Done | `quote_templates` (InnoDB, soft deletes, single default), `AccentPalette` + `HeaderAlignment` enums, `TemplateUploadService` (logo/signature, MIME+size validated), `QuotationDocumentService`, 4-step editor, live A4 preview, duplicate / set-default / trash / restore, `DemoTemplateSeeder`. |
| 4 — Quote builder | Done | `quotes`, `quote_items`, `quote_sequences`; `QuoteStatus` enum; `CalculationService` (BR-02), `QuoteNumberService` (gapless, `SELECT ... FOR UPDATE`), `SnapshotService` (BR-01), `AmountInWordsService` (Indian lakh/crore), `SaveQuoteRequest`, Alpine `quoteBuilder` with live totals, 4 quote views. |
| 5 — A4 preview + PDF | Done | `resources/css/quotation.css` as the single A4 stylesheet, injected into `resources/views/pdf/quote.blade.php` by `PdfService`; self-hosted Hind + Zilla Slab TTFs in `public/fonts`; verified real PDF bytes. |
| 6 — Lifecycle | Done | Status transitions (Expired never manual), `quotes:expire` command + hourly scheduler, duplicate, search/filters, quote trash, dashboard with live stats. |
| 7 — Security | Done | Policy checks on every record action, `OutputEscapingTest` regression suite, Pint clean, prod build, all screens smoke-tested at HTTP 200. | `clients` table (InnoDB, soft deletes, `is_active`, `created_by`, indexed); `Client` model with `scopeActive`/`scopeSearch`; `ClientFactory`; `ClientPolicy`; `StoreClientRequest` (GSTIN regex + normalisation) / `UpdateClientRequest`; `ClientController` (index, show, store, update, toggleStatus, destroy, restore, forceDestroy, trash); `DashboardController`; views `clients/{index,show,trash}` + `partials/form`; `DemoClientSeeder` (6 realistic Indian clients); 16 new feature tests. |

### Defects found and fixed during Phases 3-7
- **GST always resolved to 0** — `in_array((float) 18, [0,5,12,18,28], true)` is false (int vs float under strict comparison), so every quotation would have been saved with zero GST. Caught by `CalculationServiceTest`; fixed by comparing as floats.
- **Template mutations were silent no-ops** — routes used `{template}` while `QuoteTemplateController` methods took `$quoteTemplate`. Laravel binds implicit route models by *name*, so it injected an empty model and every update/delete/duplicate/set-default did nothing. Renamed the parameters to `$template`.
- **Broken relation** — `QuoteTemplate::quotes()` inferred the FK as `quote_template_id`, but the column is `template_id`, producing invalid SQL. Specified `'template_id'` explicitly.
- **`amount in words` crashed above 99** — the two-digit helper was used for the final 3-digit segment. Split into `underThousand()` / `underHundred()`; also added correct "Rupee" singular.
- **XSS in the A4 intro message** — the user-supplied intro rendered unescaped in both the preview and the PDF. Now escaped with `nl2br(e(...))`, covered by `OutputEscapingTest`.
- **Dompdf font cache missing** — `storage/fonts` must exist and be writable; committed with a `.gitignore` so shared hosting works.
- **`Pdf` vs `PDF` import collision** — PHP class names are case-insensitive, so the facade and wrapper cannot share their own names; aliased as `PdfFacade` / `Dompdf`.
- **Demo seeder used positional arrays** — `CalculationService` expects `description`/`qty`/`rate` keys, so seeded quotes had zero totals and no line items.
- **Tailwind version conflict** — Breeze 2.4.2 ships Tailwind 3 config while the Laravel 13 skeleton ships Tailwind 4. Standardised on Tailwind 4 CSS-first (`@theme`); `tailwind.config.js` and `postcss.config.js` were removed.
### Environment gotchas discovered
- **WAMP MySQL 8.0.31** was defaulting to **MyISAM** (`default_storage_engine=MYISAM` in `C:\wamp64\bin\mysql\mysql8.0.31\my.ini`), which caps index keys at 1000 bytes and breaks Laravel migrations. Fixed to **InnoDB** (runtime `SET GLOBAL` + persistent `my.ini` edit). The app depends on transactions and foreign keys, so InnoDB is mandatory.
- **Laravel 13 removed** `AuthorizesRequests` from the base controller; it was re-added to `app/Http/Controllers/Controller.php` so `$this->authorize()` works (Laravel 13's documented approach).
- **Breeze 2.4.2 ships Tailwind 3** config (`tailwind.config.js` + PostCSS) which conflicts with the Laravel 13 skeleton's **Tailwind 4** (`@tailwindcss/vite`). Standardised on **Tailwind 4** CSS-first config; `tailwind.config.js` and `postcss.config.js` were deleted.
- Tests use in-memory SQLite, so `pdo_sqlite` / `sqlite3` must stay enabled in `php.ini`.
- `vendor/bin/pint` is the style gate; run it before every commit.

---

## 10. Next Action

v1 is feature-complete. The remaining work is delivery and handover:

1. **Deploy to shared hosting** (Phase 7). Follow `Architecture.md` section 9: upload with `public/` as the web root, create the MySQL database, set `.env` (`APP_DEBUG=false`, HTTPS), run `php artisan migrate --force` and `php artisan storage:link`, then add the cron entry for `quotes:expire`.
2. **Walk the Definition of Done** (`PRD.md` section 9) with the client on the staging host.
3. **Collect remaining client inputs**: logo and brand assets, default admin password, and hosting credentials.
4. Optional post-v1: roles, email sending, percentage discounts, quote-number format options.

### Local development
```
php artisan serve
npm run dev          # or npm run build for production assets
php artisan test     # 138 tests
vendor/bin/pint      # style gate
```