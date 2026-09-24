# memory.md — Project Memory

Living document for the Quotation & Proforma Invoice Management System. Update it whenever a decision, constraint, or environment fact changes. This is the first context file an agent or new developer should read.

**Last updated:** 2026-09-24 | **Version:** 1.0 | **Status:** Planning complete, build not started

---

## 1. Project Snapshot

- **What:** Web-based quotation / proforma invoice manager — clients, branded templates, quote builder, A4 preview, Dompdf PDF, status tracking. First module of a larger business suite.
- **Who uses it:** One company, one admin user (decision #2). No roles in v1.
- **Hosting:** Shared hosting — PHP 8.3+, MySQL 8, Apache, Composer, SSL, cron. No Redis/queues/Docker/Supervisor/Node server.
- **Repo:** `d:\rahul\codemattrix_project\quote` (Windows/WAMP workstation).
- **Status:** Documentation complete; application code not yet written.

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

- **Repository skeleton:** Laravel **8** (`laravel/framework ^8.75`), Laravel Mix, no auth, no app code — must be replaced by Laravel 13 (Phase 0).
- **Local PHP:** 7.4.33 (WAMP `C:\wamp64\bin\php\php7.4.33\php.exe`) — **PHP 8.3+ required** before any build.
- **Composer:** 2.10.3 installed.
- **Database:** MySQL 8, database name `quote` (from `.env`).
- **Git:** not initialised yet; client to provide the remote.
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

- [ ] Client to provide the git remote URL (decision #11).
- [ ] Install PHP 8.3+ on the workstation.
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

## 9. Next Action

Begin **Phase 0** (`phases.md`): install PHP 8.3+, replace the Laravel 8 skeleton with Laravel 13, add Breeze + Vite + Tailwind, and attach the client git remote.
