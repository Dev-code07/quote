# rules.md — Development Rules

Binding conventions for this codebase. If a rule here conflicts with a prototype's cosmetic detail, **the rule wins**; if it conflicts with a client decision, the client decision wins.

---

## 1. Source of Truth

1. The `.docx` brief and the 12 client decisions in `PRD.md` section 10 define **what** to build.
2. The five HTML layouts define **how it looks**. They are visual references, not production code — do not paste their inline `<style>`/`<script>` into the app.
3. `design.md` translates those references into Tailwind tokens and Blade components.
4. Any deviation must be recorded in `memory.md` before implementation.

---

## 2. Stack Rules (hard constraints)

- PHP **8.3+**, Laravel **13.x**. Do not install packages that require PHP < 8.3.
- Frontend: Blade + **Tailwind CSS v4** + Alpine.js only. **No React, Vue, Livewire, jQuery UI, Bootstrap, or CDN CSS frameworks.**
- Tailwind is configured CSS-first via `@theme` in `resources/css/app.css`. There is no `tailwind.config.js` and no PostCSS config; do not reintroduce them.
- PDF: Dompdf only. **Never** browser-screenshot-to-image, headless Chrome, or external PDF services.
- Database: MySQL 8. No raw SQL migrations with MySQL-only tricks that break `migrate:fresh` in tests (SQLite is the test driver — keep migrations portable).
- Infrastructure: file cache/session/sync queue. **No Redis, Horizon, Supervisor, Docker, or long-running Node process.**
- New Composer packages: only if (a) maintained, (b) PHP 8.3 compatible, (c) not avoidable with ~50 lines of app code. Record the choice in `memory.md`.

---

## 3. Code Organisation

- Controllers stay thin: `validate -> authorize -> delegate to service/model -> return view|redirect|json`. No calculation logic in controllers.
- Business logic lives in `app/Services` (`CalculationService`, `QuoteNumberService`, `SnapshotService`, `PdfService`, `ExpireQuotesService`).
- Validation lives in `app/Http/Requests` (FormRequest). No inline `$request->validate()` in controllers.
- Authorization lives in `app/Policies` and is invoked via `$this->authorize()` or `Gate::authorize()`.
- One concern per class. No "helpers" file for miscellaneous logic.
- Blade UI markup lives in `resources/views/components` as reusable components; pages compose components instead of copying markup.
- Filenames: `PascalCase.php` for classes, `kebab-case.blade.php` for views (`quote-template-form.blade.php` inside `templates/`).
- Imports: `use` statements at the top; fully-qualified names inline only for same-package classes.

---

## 4. Data & Database Rules

- One migration per table/change, named `create_xxx_table` / `add_xxx_to_yyy_table`. Never edit an already-merged migration.
- Money and quantity columns: `decimal(...)`, never `float`/`double`. Cast in models (`'rate' => 'decimal:2'`).
- Every business entity uses `SoftDeletes` (decision #8). Normal queries are implicitly non-deleted; the Trash view is the only place that sees trashed rows.
- Enums and status transitions use the `QuoteStatus` enum; never a free-text status string.
- `created_by` is set on create for clients, templates and quotes.
- Template defaults: at most one `is_default = true` (enforced in the service, not the UI).
- Eager-load relations (`with`) in controllers; `paginate()` on every list; index every column used for search/filter.
- Seeder/factory data must be realistic INR/Indian-format data and must not be required for tests to pass.

---

## 5. Business Rules (enforced server-side)

1. **The backend is the only calculator.** Never persist `subtotal`, `gst_amount` or `grand_total` from the request; recompute in `CalculationService` and ignore client values. If values arrive, discard them silently; if the shape is invalid, fail validation.
2. **Snapshots are immutable.** A saved quote renders only from `client_snapshot`, `template_snapshot`, `terms` and its own columns. Never read the live client/template at render time.
3. **Quote numbers are server-generated**, unique, yearly-sequenced, never reused, never user-editable.
4. **Discount is flat Rs. only**, clamped to `[0, subtotal]`. No percentage discounts in v1.
5. **GST** = `(subtotal - discount) * rate / 100`, added on top, rounded to 2 dp. Allowed rates: 0, 5, 12, 18, 28.
6. **Status**: admin may set Draft/Sent/Approved. `Expired` is set only by `quotes:expire`, only when `valid_until < today`, only for Draft/Sent. Never expose Expired as a user-selectable option.
7. **Template changes are Draft-only** and re-snapshot. A template change on a Sent/Approved quote must be rejected with a clear message.
8. **Delete means trash.** `delete()` only; `forceDelete()` only from the Trash view, behind a confirmation dialog, and only when no live quotes reference the record.
9. **Uploads** are validated by MIME + extension + size, stored with generated names, and never referenced by their original filename.

---

## 6. Security Rules

- Every app route is behind `auth`; every record action is authorized. A controller that fetches a record for edit/view/delete must authorize it in the same method.
- No IDOR shortcuts: never `Model::find($id)` and trust the URL — authorize first, or scope the query through the policy.
- CSRF token on every form (`@csrf`); AJAX requests send `X-CSRF-TOKEN`.
- Output user data only with `{{ }}`. `{!! !!}` is banned for user-supplied values (reviews: no unescaped user data in Blade).
- No raw SQL with concatenated input. Bind parameters only.
- Explicit `$fillable` on every model; never `$guarded = []`.
- Validation rules live in FormRequests, with clear, human-readable messages (the builder shows them inline).
- Rate-limit auth endpoints; never reveal whether an email exists in password-reset responses.
- `APP_DEBUG=false`, `APP_ENV=production`, HTTPS cookies in production. Secrets live only in `.env`; `.env` is git-ignored.
- Store uploaded logos/signatures outside any executable path and serve with a safe content type.

---

## 7. Frontend Rules

- Tailwind utilities only. Custom CSS lives in `resources/css/app.css` (or a component `<style>` block) and must reuse the tokens in `design.md`. No arbitrary hex values sprinkled in Blade.
- Alpine for interactivity only: item rows, live totals, modals, preview zoom, filters. Keep state in `x-data` on the page; no external stores.
- Every modal: `role="dialog"`, `aria-modal="true"`, labelled by its heading, closable with Esc, focus trapped.
- Forms: real `<label for>`, `required` markers, inline error text, `aria-invalid` on failure.
- No inline `onclick`/`onchange` handlers; use `x-on:` or listeners. No inline `<style>` blocks in pages (component-local excepted).
- Icons: inline SVG matching the prototypes' 24x24 stroke style. Keep them in components, not repeated in pages.
- Accessibility basics: visible `:focus-visible` outlines, contrast >= 4.5:1 for body text, buttons reachable by keyboard.

---

## 8. PDF Rules

- `resources/views/pdf/quote.blade.php` is the single source for PDF layout. It renders from snapshot columns only.
- `@page { size: A4; margin: 9mm; }`; no JavaScript, no external requests, no web fonts from a CDN.
- Accent colours enter as CSS variables from the snapshot; never hardcode navy in the PDF template.
- `thead` repeats across pages; totals/terms/signature block uses `page-break-inside: avoid`.
- Amount in words and rupee formatting come from the services, never re-derived in the template.
- Every PDF change is verified by generating a real file locally, not by reading the template.

---

## 9. Testing Rules

- Feature tests are required for: calculation, numbering, snapshot immutability, status transitions, cron expiry, trash/restore/force-delete, authorization, upload validation, and a PDF generation smoke test.
- Use factories; no hard-coded IDs in tests.
- Tests must pass with `php artisan test` and must not require a running server or Node process.
- Fix the cause, not the assertion. No skipped tests in a feature PR.

---

## 10. Git Rules (decision #11)

- The client-provided remote is attached in Phase 1; `main` is protected and deployable.
- Branch naming: `feature/<area>-<short-desc>`, `fix/<area>-<short-desc>` (e.g. `feature/quotes-pdf`, `fix/quote-number-gap`).
- Commit messages: imperative, scoped prefix — `feat(quotes): add Dompdf service`, `fix(templates): enforce single default`, `docs: record decision #8`.
- One logical change per commit. No `node_modules`, `.env`, `storage/logs/*`, or IDE files committed. `public/build` is committed because shared hosting has no Node.
- Before merge: `php artisan test`, `npm run build`, and a manual pass of the Definition of Done in `PRD.md`.

---

## 11. Definition-of-Done Checklist (per feature)

- [ ] FormRequest validation with friendly messages
- [ ] Policy/authorization in place
- [ ] Business logic in a service, not the controller
- [ ] Migration included (if schema changed)
- [ ] Feature tests written and passing
- [ ] UI matches `design.md` tokens; no hardcoded colours
- [ ] Trash/restore honoured for deletable records
- [ ] No secrets, no debug statements, no commented-out code
- [ ] `php artisan test` and `npm run build` pass
