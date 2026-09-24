# Architecture — Quotation & Proforma Invoice Management System

| | |
|---|---|
| **Version** | 1.0 |
| **Date** | 2026-09-24 |
| **Status** | Draft |
| **Scope** | Technical design for the v1 build described in `PRD.md` |

---

## 1. Architecture Summary

A single Laravel monolith rendered on the server, deployed to shared hosting. The browser receives HTML plus one compiled CSS bundle and one small JS bundle. All business logic (numbering, calculation, snapshotting, PDF rendering) runs server-side. Alpine.js handles only in-page interactivity — no separate front-end application, no API server.

```
Browser
  |  HTTPS (SSL)
  v
Apache (shared hosting)  ---- public/  (index.php front controller)
  |
Laravel 13
  |- routes/web.php
  |- app/Http/Controllers       (thin — validate, delegate, respond)
  |- app/Http/Requests         (FormRequest validation)
  |- app/Services              (QuoteNumberService, CalculationService,
  |                             SnapshotService, PdfService, ExpireQuotes)
  |- app/Models                (Eloquent)
  |- resources/views           (Blade: layouts, components, pages, pdf)
  |        ^
  |        +-- resources/css/app.css  (Tailwind source)
  |        +-- resources/js/app.js    (Alpine entry)
  |- public/build              (Vite output: manifest.css, manifest.js)
  |
MySQL 8  (database)          File storage (public disk: logos, signatures)
  |
Cron --> php artisan schedule:run  (daily: expire quotes)
```

**Why a monolith:** the brief forbids mandatory Redis/queues/Node servers and targets shared hosting. A modular monolith keeps deployment to "upload files + run migrations", which is what shared hosting allows.

---

## 2. Technology Stack

| Layer | Choice | Version / Notes |
|---|---|---|
| Language | PHP | **8.3+** (brief requirement; Laravel 13 supports 8.3–8.5) |
| Framework | Laravel | **13.x** — latest stable (13.33 as of 2026-09-24; released 2026-03-17, security support to 2028-03-17) |
| Auth | Laravel Breeze | Blade stack, no Vue/React |
| View layer | Blade | Server-rendered HTML |
| CSS | Tailwind CSS | Utility-first; custom tokens in `tailwind.config.js` |
| JS | Alpine.js | Loaded via Vite; small helper modules only |
| Bundler | Vite | Replaces the Laravel 8 Mix setup |
| Database | MySQL | 8.x on shared hosting |
| PDF | Dompdf | `barryvdh/laravel-dompdf` |
| Storage | Laravel Storage | `public` disk for logos/signatures |
| Scheduler | Laravel Scheduler | Daily cron, no queue worker |
| Sessions/Cache | `file` driver | No Redis dependency |
| Tests | PHPUnit | Feature tests for business rules |

### 2.1 Skeleton migration (decision #1)

The repository currently holds a **Laravel 8** skeleton (`laravel/framework ^8.75`, Laravel Mix, PHP 7.4 on the local WAMP install). The brief requires the latest stable Laravel with Vite. Migration approach:

1. Install **PHP 8.3+** and **Composer 2** locally (Composer 2.10.3 is already present).
2. Create a fresh Laravel 13 project in a temp directory; copy `app/`, `config/`, `database/`, `resources/`, `routes/`, `tests/`, `.env.example` into this repository, keeping the same `composer.json` name.
3. `composer install`, `npm install`.
4. Install Breeze (`php artisan breeze:install --blade`), then `npm run build`.
5. Remove the old `webpack.mix.js`, `package-lock.json` (v1) and Laravel-8-only config leftovers.
6. Confirm `php artisan about` reports Laravel 13 and PHP 8.3+.

Laravel 8 -> 13 is a major-version jump, so the skeleton is replaced rather than upgraded in place; there is no application code yet, so no business logic is lost.

---

## 3. Directory Layout

```
app/
  Enums/QuoteStatus.php            PHP enum: draft, sent, approved, expired
  Enums/AccentPalette.php          navy, teal, maroon, forest, slate, plum
  Http/
    Controllers/
      Auth/                        (from Breeze)
      DashboardController.php
      ClientController.php
      QuoteTemplateController.php
      QuoteController.php
      QuoteTemplatePreviewController.php
    Requests/
      StoreClientRequest.php  UpdateClientRequest.php
      StoreQuoteTemplateRequest.php  UpdateQuoteTemplateRequest.php
      SaveQuoteRequest.php           (create/update + items array)
      UpdateQuoteStatusRequest.php
  Models/
    User.php  Client.php  QuoteTemplate.php  Quote.php  QuoteItem.php
  Policies/
    ClientPolicy.php  QuoteTemplatePolicy.php  QuotePolicy.php
  Services/
    CalculationService.php     subtotal, discount, GST, grand total, rounding
    AmountInWordsService.php   Indian system (lakh/crore)
    QuoteNumberService.php     unique QT-YYYY-NNNNN allocation
    SnapshotService.php        copies client + template data onto a quote
    PdfService.php             Dompdf render + download
    ExpireQuotesService.php    marks past-validity quotes
  Support/
    Money.php                  rupee formatting helpers
database/
  migrations/                   one migration per table
  seeders/DemoDataSeeder.php    sample clients/templates/quotes
  factories/
resources/
  css/app.css                   Tailwind directives + print rules
  js/app.js  bootstrap.js       Alpine bootstrap
  views/
    layouts/app.blade.php       sidebar + topbar + footer shell
    layouts/guest.blade.php     auth pages
    components/                 button, card, input, select, modal, toast,
                                table, badge, pagination, empty-state, swatch
    dashboard/index.blade.php
    clients/{index,form,show}.blade.php
    templates/{index,form,show}.blade.php
    quotes/{index,form,show,preview}.blade.php
    pdf/quote.blade.php         Dompdf-only, @page A4, no JS
public/
  build/                        Vite output (committed for deployment)
  fonts/                        Hind + Zilla Slab woff2/ttf (assumption A5)
routes/web.php  console.php (schedule)  channels.php
tests/Feature/                  calculation, numbering, snapshot, status, trash, auth
phpunit.xml
```

---

## 4. Database Schema

All tables use `id` bigint PK, `timestamps()`, and soft deletes on the three business entities. Money columns are `DECIMAL`, never float.

### 4.1 users
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| name | string | |
| email | string unique | |
| email_verified_at | timestamp nullable | |
| password | string | bcrypt hash |
| remember_token | rememberToken | |
| timestamps | | |

Single-user installation (decision #2). No `role` column.

### 4.2 clients
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| name | string | required |
| contact_person | string nullable | |
| email | string nullable | |
| phone | string nullable | |
| gstin | string(15) nullable | |
| address | text nullable | billing address |
| is_active | boolean default true | Active/Inactive (deactivate instead of delete) |
| created_by | FK users.id nullable | audit |
| timestamps, softDeletes | | |

Indexes: `name`, `is_active`, `deleted_at`.

### 4.3 quote_templates
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| name | string | template name |
| is_default | boolean default false | max one true (BR-04) |
| accent_color | enum/string | navy, teal, maroon, forest, slate, plum (decision #10) |
| header_alignment | enum/string | left, center, right |
| letterhead_display_name | string nullable | stylized name; blank = company name |
| doc_title | string | QUOTATION / PROFORMA INVOICE / ESTIMATE |
| company_name | string | |
| company_gstin | string(15) nullable | |
| tagline | string nullable | printed as "Deals in: ..." |
| address | text nullable | |
| email | string nullable | |
| mobile_1, mobile_2 | string nullable | |
| stamp_place | string nullable | |
| logo_path | string nullable | Storage public disk |
| signature_path | string nullable | |
| authorized_person | string nullable | |
| designation | string nullable | |
| default_gst_rate | decimal(5,2) default 18 | |
| intro_message | text nullable | supports `{client_name}`, `{enquiry_no}`, `{enquiry_date}` |
| delivery_period | string nullable | |
| warranty | string nullable | |
| validity_text | string nullable | |
| extra_terms | text nullable | one per line |
| notes | text nullable | |
| last_used_at | timestamp nullable | shown on template cards |
| created_by | FK users.id nullable | |
| timestamps, softDeletes | | |

Indexes: `name`, `is_default`, `deleted_at`.

### 4.4 quotes
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| quote_number | string(20) **unique** | `QT-2026-00001` (BR-03) |
| quote_date | date | |
| valid_until | date | drives auto-expire |
| enquiry_no | string nullable | optional (decision #9) |
| enquiry_date | date nullable | optional |
| status | enum/string default draft | draft, sent, approved, expired |
| client_id | FK clients.id nullable | set null on client permanent delete |
| template_id | FK quote_templates.id nullable | reference only; content is snapshotted |
| client_snapshot | JSON | name, contact, email, phone, gstin, address |
| template_snapshot | JSON | company + branding + terms as printed |
| terms | JSON | delivery, warranty, validity, extra terms, notes, intro |
| subtotal | decimal(15,2) | |
| discount_amount | decimal(15,2) default 0 | flat Rs. (decision #6) |
| gst_rate | decimal(5,2) default 0 | 0, 5, 12, 18, 28 |
| gst_amount | decimal(15,2) | |
| grand_total | decimal(15,2) | |
| amount_in_words | string | computed at save |
| created_by | FK users.id nullable | |
| timestamps, softDeletes | | |

Indexes: `quote_number` (unique), `status`, `quote_date`, `client_id`, `valid_until`, `deleted_at`.

### 4.5 quote_items
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| quote_id | FK quotes.id cascade | |
| position | smallint | Sr. No. order |
| description | text | |
| quantity | decimal(12,2) | |
| rate | decimal(15,2) | |
| amount | decimal(15,2) | qty x rate, computed server-side |
| timestamps | | |

Index: `quote_id`.

### 4.6 Relationships
```
User      1---* Client / QuoteTemplate / Quote (created_by)
Client    1---* Quote            (nullable once client is purged)
Template  1---* Quote            (nullable, snapshot keeps history)
Quote     1---* QuoteItem        (cascade delete)
```

---

## 5. Key Subsystems

### 5.1 Calculation (FR-07, BR-02)
`CalculationService::compute(array $items, float $discount, float $gstRate): array`
1. For each item: `amount = round(quantity * rate, 2)`.
2. `subtotal = round(sum(amount), 2)`.
3. `discount = min(max(discount, 0), subtotal)` — cannot exceed subtotal.
4. `discounted = round(subtotal - discount, 2)`.
5. `gst = round(discounted * gstRate / 100, 2)`.
6. `grand = round(discounted + gst, 2)`.
7. `AmountInWordsService::convert($grand)` — Indian grouping.

The service is called **only** on the server. The request-supplied `subtotal`, `gst_amount` and `grand_total` are discarded and recomputed; a mismatch is a validation error. The same service backs the live builder summary via a lightweight JSON endpoint, so on-screen and saved numbers can never diverge.

### 5.2 Quote numbering (FR-06, BR-03)
`QuoteNumberService::next(): string`
- Pattern `QT-{Y}-{NNNNN}`; yearly sequence (`QT-2026-00001`, `QT-2027-00001`).
- Implemented with a dedicated `quote_sequences` row (`year`, `last_number`) updated inside a DB transaction with `SELECT ... FOR UPDATE`, so concurrent requests cannot collide. The unique index on `quote_number` is the final safety net.
- Drafts also consume a number, so numbering is gapless and never reused — simpler to audit, which suits a quotation register.

### 5.3 Snapshotting (FR-08, BR-01)
`SnapshotService` runs on quote create (and on template change while Draft):
- copies client fields into `client_snapshot`;
- copies the template's company, branding, signature and default terms into `template_snapshot`;
- resolves `{client_name}`, `{enquiry_no}`, `{enquiry_date}` in the intro message and stores the rendered text in `terms.intro`;
- stores final terms and totals.

After that the quote renders **exclusively** from its own columns. Editing the template or client afterwards changes nothing historical. A template may be permanently deleted and the quote still prints correctly.

### 5.4 PDF (FR-11)
`PdfService::render(Quote $quote): \Barryvdh\DomPDF\PDF`
- Dedicated `resources/views/pdf/quote.blade.php`, `@page { size: A4; margin: 9mm; }`, no JavaScript.
- Accent colours are injected as CSS variables (`--ink`, `--ink-2`, `--ink-soft`, `--ink-line`) from `template_snapshot.accent_color`.
- Self-hosted Hind + Zilla Slab in `public/fonts` (assumption A5) — Dompdf cannot depend on a CDN at render time.
- Long item tables paginate naturally; `thead` repeats on every page, totals/terms/signature flow to the final page via `page-break-inside: avoid` on the closing block.
- No browser screenshotting anywhere.

### 5.5 Status & auto-expire (FR-09)
- `QuoteStatus` enum: `Draft`, `Sent`, `Approved`, `Expired`.
- Admin may move Draft -> Sent -> Approved from the UI; Expired is never set by the user.
- `routes/console.php` schedules `quotes:expire` daily (hosting cron: `* * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1`).
- `ExpireQuotesService` updates quotes where `valid_until < today`, `status in (draft, sent)`, `deleted_at is null`. Approved quotes are skipped (assumption A6). Running hourly instead of daily is supported with no code change.

### 5.6 Trash & restore (FR-12, decision #8)
- `SoftDeletes` on `clients`, `quote_templates`, `quotes`.
- Each index page has a Trash tab (Trashed scope) with Restore.
- Permanent delete (`forceDelete`) is exposed only from Trash, behind a confirmation dialog, and blocked with a clear error while live quotes reference the record (assumption A2).

### 5.7 Uploads (FR-13)
- `logos/` and `signatures/` on the `public` disk; stored as `uuid.ext` via `Storage::disk('public')->putFile()`.
- Validation: `mimes:png,jpg,jpeg,svg,webp`, `max:2048`, plus a server-side MIME check. Never trust the browser-provided name; never serve uploads as executable content.

---

## 6. Request Flow (create/update quote)

```
POST /quotes (web form + items[] + status=intent)
  -> auth middleware
  -> SaveQuoteRequest validation (items array shape, gst rate in list, date order)
  -> Policy: QuotePolicy::update (single admin, still enforced)
  -> DB transaction:
       1. CalculationService computes totals (client values ignored)
       2. QuoteNumberService::next() (only on create)
       3. SnapshotService fills client_snapshot / template_snapshot / terms
       4. quote upsert + quote_items replace
  -> redirect to quote show with flash toast
  -> view renders from snapshots only
```

---

## 7. Rendering & Performance

- Blade layouts with `@extends`/`@include`; repeated UI extracted into `resources/views/components` (button, modal, toast, table, badge).
- Alpine handles: item-row add/remove, live totals, modal open/close, template search, preview zoom. No framework, no `innerHTML` string building for user data.
- Tailwind content paths include Blade views so classes are purged correctly.
- Vite builds production assets to `public/build`; shared hosting runs **no** Node process.
- Query hygiene: `with()` eager loading of client/template, `withCount('items')` for the list column, `paginate()` on every list, indexed filter columns.
- Server config: `APP_DEBUG=false`, cached config/routes/views, `php artisan config:cache && route:cache && view:cache`, OPcache if the host offers it.

---

## 8. Security Architecture

| Threat | Control |
|---|---|
| Unauthorised access | `auth` middleware on all app routes; guests -> login |
| IDOR (guessing IDs in URLs) | Policies (`authorize`) on every show/edit/delete/restore action; queries always scoped through the policy, never raw `find()` in controllers |
| CSRF | `@csrf` in every form; `VerifyCsrfToken` on the `web` group |
| XSS | Blade `{{ }}` escaping everywhere; `{{-- --}}` comments; no `{!! !!}` for user-supplied data |
| SQL injection | Eloquent/query builder with bound parameters only; no string-concatenated SQL |
| Mass assignment | Explicit `$fillable`; never `$guarded = []` |
| Tampered totals | Server recomputation (5.1); request totals never persisted |
| Unsafe uploads | MIME + extension + size validation, hashed names, non-executable serving (5.7) |
| Login abuse | Breeze throttling on auth routes; hashed passwords; HTTPS-only cookies |
| Secret leakage | `.env` never committed; `.env.example` placeholders only; `APP_DEBUG=false` in production |
| Duplicate/trashed data bleed | `withoutTrashed()` on normal queries; Trash scope explicit |

---

## 9. Deployment (shared hosting)

1. `composer install --no-dev --optimize-autoloader` locally; `npm run build`.
2. Upload the repository to `public_html` (Laravel root one level **above** `public/`, with `public_html` as a symlink/alias — standard cPanel layout) or adapt `index.php` if the host forces the web root at project root.
3. Create the MySQL database/user on the host; set `.env` (APP_KEY via `php artisan key:generate`, APP_URL HTTPS, DB_*, `FILESYSTEM_DISK=public`, `APP_DEBUG=false`).
4. `php artisan migrate --force`; `php artisan storage:link`; ensure `storage/` and `bootstrap/cache/` are writable.
5. Copy `public/fonts` and `public/build` (already in the upload).
6. Cron: `* * * * * cd /home/user/quote && php artisan schedule:run >> /dev/null 2>&1`.
7. Force HTTPS and enable SSL; set `SESSION_SECURE_COOKIE=true`.

No Docker, Redis, queue worker, or Supervisor is required. If the host lacks `storage:link` (some shared hosts), serve uploads through a controller route that streams from the `local` disk.

---

## 10. Testing Strategy

Feature tests (PHPUnit) covering: CalculationService (incl. discount > subtotal, rounding, 0% GST), quote-number uniqueness and yearly reset, snapshot immutability (edit template -> old quote unchanged), status transitions and cron expiry, trash/restore/force-delete guards, authorization failures for every policy, PDF generation smoke test, upload validation, and number formatting/amount-in-words.

---

## 11. Architecture Decisions Log

| # | Decision | Rationale |
|---|---|---|
| AD-1 | Laravel 13 monolith, server-rendered Blade | Brief: latest stable, shared hosting only |
| AD-2 | Alpine.js instead of a JS framework | Matches prototypes, small bundle, no Node server |
| AD-3 | JSON snapshot columns on `quotes` | Guarantees immutable history (BR-01) in one query |
| AD-4 | `quote_sequences` table for numbering | Gapless, collision-free without Redis |
| AD-5 | Dedicated `pdf/quote.blade.php` | Brief mandates a real PDF template, not a screenshot |
| AD-6 | Self-hosted fonts | Dompdf must render offline |
| AD-7 | File cache/session drivers | No Redis on shared hosting |
| AD-8 | `SoftDeletes` everywhere (decision #8) | Restore safety for clients, templates, quotes |
