# PRD — Quotation & Proforma Invoice Management System

| | |
|---|---|
| **Version** | 1.0 |
| **Date** | 2026-09-24 |
| **Status** | Draft — client decisions confirmed |
| **Primary source** | `Quotation_Management_Software_Development_Brief.docx` (31 sections) |
| **Layout references** | `quoteflow_dashboard.html`, `quoteflow_quote_builder (1).html`, `quoteflow_templates.html`, `quoteflow_template_editor.html`, `quote_preview.html` |
| **Decision log** | See `memory.md` — 12 client-confirmed decisions |

---

## 1. Product Overview

A web-based **Quotation / Proforma Invoice Management System** for a single company. The admin logs in, manages customers and branded quotation templates, builds quotations from templates, previews them on A4, generates print-quality PDFs, and tracks quote history. It is the **first module** of a larger business-software suite to be built later on the same foundation.

Deployment target: **shared hosting** (PHP 8.3+, MySQL 8, Apache, Composer, SSL, cron).

---

## 2. Goals

1. **G1 — Accuracy:** every total is calculated and re-validated by the backend; a quote can never save with wrong math.
2. **G2 — Historical integrity:** editing a template or client never changes quotes already issued.
3. **G3 — Speed:** a complete quote (client + items + PDF) in under 3 minutes.
4. **G4 — Hosting fit:** runs on plain shared hosting — no mandatory queues, Redis, WebSockets or long-running servers.
5. **G5 — Professional output:** A4 preview and PDF match the client-approved layouts, including multi-page tables.
6. **G6 — Safe data:** nothing is lost by accident — deletes go to trash and can be restored.

---

## 3. Users

| Persona | Description |
|---|---|
| **Admin (only user)** | The company owner/operator. Creates clients, templates and quotes; downloads PDFs; reviews the dashboard. No admin/staff split in v1 (decision #2). |

Every record stores `created_by` for audit purposes even though there is only one user.

---

## 4. Scope

### In Scope (v1)
- Login / logout / password reset
- Dashboard with statistics and recent quotes
- Client management (CRUD, search, active/inactive, trash/restore)
- Quote template management (CRUD, branding, preview, duplicate, set-default, start-from-existing, trash/restore)
- Quote builder (template-first flow, dynamic line items, discount, GST, terms, live A4 preview)
- Auto quote numbering `QT-YYYY-NNNNN`
- Server-side calculation + amount in words (Indian numbering)
- A4 multi-page preview + PDF download (Dompdf)
- Quote list (search, status/date filters, view, edit, duplicate, print, trash/restore)
- Status workflow including automatic **Expired** via cron
- File uploads: logo and signature (validated, stored on disk)

### Out of Scope (v1)
- Multiple users / roles / permissions UI
- Multi-company tenancy — one login = one company
- Emailing quotes to clients
- Percentage-based discounts
- Accounting, inventory, payments, e-invoice/IRN/e-way bill
- Public API, mobile app, push notifications
- Multi-currency (INR only)

---

## 5. Functional Requirements

### FR-01 Authentication
Login, logout, forgot/reset password (Laravel Breeze, Blade stack). All application pages behind `auth`; guests redirect to login.

### FR-02 Dashboard
Stat cards: Total Quotes, Draft, Sent, Approved, Expired, Total Quoted Value (Rs.). Recent Quotes table (latest 10) with quick actions. Prominent **Create New Quote** button leading to template selection.

### FR-03 Client Management
Fields: name, contact person, email, phone, GSTIN, billing address. Status Active/Inactive. List with search + status filter + pagination; add/edit. Create-quote shortcut from a client row. Deactivate instead of delete for inactive clients; delete moves the client to **trash** (restorable).

### FR-04 Quote Template Management
Fields: template name; company name, GSTIN, "Deals in" tagline, address, email, two mobile numbers, stamp place; document title (QUOTATION / PROFORMA INVOICE / ESTIMATE); logo; letterhead display name; header alignment (left/center/right); accent colour (6 palettes); default GST rate; intro message with tokens `{client_name}`, `{enquiry_no}`, `{enquiry_date}`; delivery period; warranty; validity; extra terms; notes; authorized person and designation; signature/stamp image.

Actions: create, edit, live A4 preview, duplicate (name + " (copy)"), **set default** (only one at a time), delete to trash, restore. **Start from:** create a new template pre-filled from an existing template or a blank/business-only base.

### FR-05 Quote Creation (builder)
Flow: Dashboard -> Create New Quote -> **select template** -> enter details -> add items -> pricing -> terms -> preview -> save.

- Header: auto quote number (read-only), quote date, valid-until date, optional **Enquiry No. / Enquiry Date** (feeds intro tokens), status starts **Draft**.
- Client: pick a saved client **or** type a one-off inline (auto-saved as a new client) — decision #4.
- Items: Sr. No., description, qty, rate (Rs.), auto amount (= qty x rate), add/remove rows, empty state.
- Pricing: GST rate (0/5/12/18/28%), flat Rs. discount, live summary (Subtotal, GST, Discount, Grand Total).
- Terms: editable delivery, warranty, validity, extra terms, notes — pre-filled from the template, overridable per quote.
- Template may be changed only while status = Draft; changing it re-snapshots *(assumption A1)*.

### FR-06 Quote Numbering
Format `QT-2026-00001`; generated by the backend inside a DB transaction; globally unique; never reused; not user-editable.

### FR-07 Calculation (backend is the source of truth)
- `amount = qty x rate` (2 dp)
- `subtotal = sum(amount)`
- `discounted = subtotal - discount`
- `gst = discounted x gst_rate%` (2 dp, standard rounding)
- `grand_total = discounted + gst`
- Amount printed in words using the Indian numbering system (lakh/crore).
- Front-end totals are indicative only and are always recalculated on save.

### FR-08 Snapshot on Save
Saving a quote copies client fields, template/letterhead fields, terms and all calculated totals **into the quote row** (JSON snapshots + columns). Later edits to the client or template never alter saved quotes. Quote items are stored relationally in `quote_items`.

### FR-09 Status Workflow
Statuses: **Draft -> Sent -> Approved**, plus **Expired** (automatic). Manual transitions by the admin from the quote view/list. A daily cron marks quotes Expired when `valid_until` has passed and status is Draft or Sent; **Approved quotes are never auto-expired** *(assumption A6)*. Trashed/archived is an independent flag from status.

### FR-10 Quote List & Management
Columns: Quote No., Client, Date, Items, Amount, Status, actions (kebab menu). Search by quote number/client; filters: status, date range; pagination; empty state. Actions: View, Edit, Download PDF, Print, Duplicate (new number + Draft + today's date), Delete to trash.

### FR-11 Preview & PDF
On-screen A4 preview matching `quote_preview.html`: zoom controls, multi-page split, repeated table headers, "Continued from page N", totals + terms + amount in words + signature/stamp on the closing block. PDF rendered server-side by **Dompdf** from the dedicated `resources/views/pdf/quote.blade.php` — never a browser screenshot. Print uses the browser print dialog on the preview page.

### FR-12 Trash & Restore (decision #8)
Clients, templates and quotes use **soft delete**. List pages get a Trash view with Restore. Permanent delete is allowed only from trash, behind a confirmation dialog. A client with live (non-trashed) quotes cannot be permanently deleted — archive the quotes first; trashed quotes keep working because their data is snapshotted *(assumption A2)*.

### FR-13 File Uploads
Logo and signature/stamp: PNG/JPG/SVG/WebP, max 2 MB (per prototype), validated by MIME + extension + size; stored via Laravel Storage with hashed filenames; never executed.

### FR-14 Currency & Formatting
INR (Rs.), Indian digit grouping (`1,23,456.78`), dates displayed as `24 Sep 2026`.

---

## 6. Business Rules

| ID | Rule |
|---|---|
| BR-01 | Template/client edits **never** change existing quotes (snapshot). |
| BR-02 | The backend recalculates and validates every amount on save — client-side totals are never trusted. |
| BR-03 | Quote numbers `QT-YYYY-NNNNN` are unique and never reused. |
| BR-04 | Zero or one template is flagged Default — never more. |
| BR-05 | Expired status is set only by the system, never typed by the user. |
| BR-06 | Default delete = move to trash; restore must always be possible. |
| BR-07 | All money/quantity columns are `DECIMAL` — never float. |
| BR-08 | GST applies to (subtotal − flat discount) and is added on top. |

---

## 7. Non-Functional Requirements

| Area | Requirement |
|---|---|
| Hosting | PHP 8.3+, MySQL 8, Apache, Composer, SSL; file session/cache; **no mandatory** Redis/queues/WebSockets/Docker/Node server. |
| Security | CSRF everywhere, server-side validation, Eloquent (no raw SQL concatenation), XSS escaping of all user data, authorization on every record action (no IDOR), hashed passwords, rate-limited login, strict upload validation, secrets only in `.env`. |
| Performance | Paginated lists, eager-loaded relations, indexed search columns, `npm run build` assets, cached config/route/view, compressed images; pages under 2 s on shared hosting. |
| Responsiveness | Desktop-first (1280+), usable down to mobile per prototype breakpoints (1080/900 px). |
| Browsers | Latest Chrome, Edge, Firefox, Safari. |
| Testing | Feature tests for calculation, numbering, snapshots, status transitions, trash/restore, auth guards; PDF smoke test. |
| Accessibility | Labelled inputs, visible focus states, keyboard-operable dialogs, aria roles on overlays. |

---

## 8. Data Requirements (summary)

`users`, `clients`, `quote_templates`, `quotes` (client/template JSON snapshots + totals), `quote_items`. All master data soft-deletable. Full schema in `Architecture.md` section "Database".

---

## 9. Definition of Done (from brief section 30)

- [ ] Admin can log in securely and log out
- [ ] Dashboard shows correct statistics
- [ ] Client can be created, edited, searched, deactivated
- [ ] Template can be created, edited, duplicated, previewed, set as default
- [ ] Quote can be created from a selected template
- [ ] Quote number auto-generated and unique
- [ ] Line item amounts calculated correctly
- [ ] Subtotal / discount / GST / Grand Total validated by the backend
- [ ] A4 preview matches the provided layout
- [ ] PDF generated and downloadable
- [ ] Historical quotes remain unchanged after template/client edits
- [ ] Duplicate quote works with a new number and Draft status
- [ ] Quote list search and filters work
- [ ] Trash/restore works for clients, templates and quotes
- [ ] No unauthorized record access
- [ ] Migrations create the complete database
- [ ] Runs on shared hosting without special infrastructure
- [ ] Consistent UI across all screens
- [ ] Production assets built via `npm run build`
- [ ] No hardcoded secrets in source

---

## 10. Confirmed Decisions (client, 2026-09-24)

| # | Decision |
|---|---|
| 1 | Upgrade the skeleton to **latest stable Laravel (13.x, PHP ^8.3)** with Vite + Breeze + Tailwind, per the brief. |
| 2 | **Single user** (admin only) — no roles. |
| 3 | **One company**, many clients; each template carries its own branding. |
| 4 | A quote uses a saved client **or** an inline one-off client (auto-saved). |
| 5 | GST = (subtotal − discount) x rate, **added on top**, 2 dp. |
| 6 | Discount = **flat Rs.** only. |
| 7 | Draft/Sent/Approved manual; **Expired automatic** via cron. |
| 8 | Any delete goes to **trash/archive and is restorable later** (all entities). |
| 9 | Add optional **Enquiry No./Date** fields; keep `{enquiry_no}`/`{enquiry_date}` tokens. |
| 10 | Include prototype extras: accent colours, header alignment, letterhead name, start-from-existing. |
| 11 | Git: repository provided by the client — attach in Phase 1. |
| 12 | These docs live in `docs/`; the file is named `Architecture.md` (correct spelling). |

---

## 11. Assumptions

- **A1** A template can be changed on a quote only while it is Draft; changing it re-snapshots.
- **A2** Permanent delete is blocked while live quotes reference a client or template.
- **A3** Dates display as `d M Y`; currency is INR only.
- **A4** No email delivery of quotes in v1 — the PDF is downloaded and sent manually.
- **A5** Fonts (Hind, Zilla Slab) are **self-hosted** in `public/fonts` so Dompdf renders PDFs offline on shared hosting.
- **A6** Auto-expire runs daily via cron and skips Approved quotes.
- **A7** Enquiry No./Date are optional; tokens render blank when absent.

---

## 12. Future Enhancements (post-v1)

Roles and multi-user access, email sending, percentage and line-level discounts, custom quote-number formats, e-invoice integration, notifications, multi-language UI, API for the wider suite.
