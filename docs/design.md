# design.md — UI & A4 Document Design System

Derived from the five client prototypes: `quoteflow_dashboard.html`, `quoteflow_quote_builder (1).html`, `quoteflow_templates.html`, `quoteflow_template_editor.html`, `quote_preview.html`.

**Version:** 1.0 | **Date:** 2026-09-24 | **Status:** Draft

The prototypes are the visual source of truth. Port them to Blade + Tailwind + Alpine using the tokens below — do not paste their inline CSS/JS.

---

## 1. Design Tokens

### 1.1 Colour — application shell
| Token | Hex | Tailwind name |
|---|---|---|
| Background | `#f4f5f7` | `app-bg` |
| Surface | `#ffffff` | `app-surface` |
| Border | `#e4e7ec` | `app-border` |
| Text | `#1f2937` | `app-text` |
| Text muted | `#6b7280` | `app-muted` |
| Text faint | `#9ca3af` | `app-faint` |
| Accent | `#2563eb` | `app-accent` |
| Accent hover | `#1d4ed8` | `app-accent-hover` |
| Accent soft | `#eff6ff` | `app-accent-soft` |
| Success | `#15803d` | `app-success` |
| Warning | `#b45309` | `app-warning` |
| Danger | `#b91c1c` | `app-danger` |

### 1.2 Colour — A4 document ("ink")
Six accent palettes; the first (Navy) is the default.

| Palette | ink | ink-2 | soft | line |
|---|---|---|---|---|
| Navy (default) | `#1f2f6b` | `#3b4a82` | `#eef1fa` | `#c9d0e6` |
| Teal | `#134e6f` | `#3a6b88` | `#ebf3f8` | `#c3d8e5` |
| Maroon | `#6e1f2a` | `#8a3b45` | `#f8eef0` | `#e3c8cc` |
| Forest | `#1d5445` | `#3c6d5f` | `#ecf4f1` | `#c3dad2` |
| Slate | `#2e3a4e` | `#4d5a70` | `#eff1f5` | `#cfd5df` |
| Plum | `#4b2f7a` | `#65508f` | `#f2eef9` | `#d6cbe8` |

Fixed document colours: paper text `#1b2340`, stamp `#5140b0`, subtle text `#7a819c`.

### 1.3 Typography
- Shell: Segoe UI / Inter stack, 14px base, 13px controls, 12.5px secondary, 12px footer.
- A4 document: **Hind** for body (13px / 1.45), **Zilla Slab** for company name (40–42px) and continuation heading (20px).
- Fonts self-hosted in `public/fonts` (Dompdf cannot use a CDN) — assumption A5.

### 1.4 Shape, spacing, elevation
- Radius: 8px cards/buttons, 6px inputs, 999px chips, 3px A4 sheet.
- Card shadow: `0 1px 2px rgba(15,23,42,.08)`, `0 12px 32px rgba(15,23,42,.16)` (A4 sheet).
- Section padding 22–24px; grid gap 18–20px; field gap 14–16px.
- Sidebar width 232px; collapsed into an off-canvas drawer <=900px.

### 1.5 Tailwind mapping
Define the palette in `tailwind.config.js` under `theme.extend.colors` (`app-*`) and expose the ink palettes as CSS custom properties (`--ink`, `--ink-2`, `--ink-soft`, `--ink-line`) set per template. No arbitrary hex values in Blade.

---

## 2. Layout & Screens

### 2.1 App shell
- Fixed left sidebar (232px): logo, nav (Dashboard, Quotes, Clients, Templates), user block + logout.
- Topbar: page title, search (hidden <=900px), menu button on mobile.
- Content area with section cards; footer strip with muted text.
- Breakpoints: <=1080px two-column stat/grid; <=900px drawer sidebar; <=640px stacked forms.

### 2.2 Screen map (prototype -> Blade)
| Prototype | Route | Blade view |
|---|---|---|
| `quoteflow_dashboard.html` (quotes list) | `/quotes` | `quotes/index` |
| template selection overlay | `/quotes/create` step 1 | `quotes/form` (overlay) |
| `quoteflow_quote_builder (1).html` | `/quotes/create`, `/quotes/{id}/edit` | `quotes/form` |
| A4 preview overlay / `quote_preview.html` | `/quotes/{id}/preview` | `quotes/preview` |
| `quoteflow_templates.html` | `/templates` | `templates/index` |
| `quoteflow_template_editor.html` | `/templates/create`, `/templates/{id}/edit` | `templates/form` |
| clients (per brief) | `/clients` | `clients/index`, `clients/form`, `clients/show` |
| dashboard stats | `/` | `dashboard/index` |

---

## 3. Component Catalogue

| Component | Spec |
|---|---|
| Button | Accent primary (filled), ghost (bordered), danger (red). 36–40px height, 8px radius, 13px. |
| Card | White, 1px border, 8px radius, 22–24px padding. |
| Stat card | Label (12.5px muted), value (24–28px bold), small trend/icon. |
| Data table | Header 12px uppercase muted; rows 13.5px, 1px row dividers; hover tint. |
| Kebab menu | Three-dot trigger, right-aligned dropdown with icons; View/Edit/PDF/Duplicate/Delete. |
| Badge / status chip | Pill with soft background + dot; colours per status. |
| Form field | Label 12px, required `*`, 6px-radius input, inline error text, helper note. |
| Segmented control | Header-alignment radio group with `role="radiogroup"`. |
| Swatch row | Six accent circles, selected ring. |
| Upload dropzone | Dashed border, drag & drop, preview thumbnail, 2 MB note. |
| Modal / dialog | Overlay scrim, rounded panel, head + body + foot (Cancel / primary). |
| Toast | Bottom-right dark pill with check icon, auto-dismiss. |
| Empty state | 64px icon tile, title, hint, primary action. |
| Pagination | "Showing X of Y", numbered buttons, prev/next. |
| Bottom action bar | Sticky; total on left, buttons on right. |
| Preview toolbar | Zoom out/in + percentage, status chip. |

Status colours: Draft neutral, Sent info, Approved success, Expired warning, Trashed danger.

---

## 4. Builder (per `quoteflow_quote_builder (1).html`)

Numbered sections:
1. **Template** — selected-template card (change/replace).
2. **Client** — saved-client picker + inline one-off entry (auto-save).
3. **Items** — table: Sr. | Description | Qty | Rate (Rs.) | Amount | delete; "Add Item" button; empty state.
4. **Pricing Summary** — GST select (0/5/12/18/28), flat discount, live summary box (Subtotal, GST, Discount, Grand Total).
5. **Terms & Conditions** — delivery, warranty, validity, extra terms, notes.

Bottom bar: total + Cancel / Save Draft / Preview / Generate Quote.

---

## 5. A4 Document (per `quote_preview.html` + builder overlay)

Physical page: 210 x 297mm, 9mm padding, white, drop shadow. Double-rule border frame.

Zones top-to-bottom:
1. **Strip** — contact left, document-title chip centre, mobile right.
2. **Letterhead** — optional logo (52px) + company name (Zilla Slab), double rule, "Deals in: ..." tagline, address/e-mail.
3. **Reference** — Ref. No. and Dated (soft background band).
4. **Parties** — To (name + address + GSTIN) left; Phone / Email / Valid until right.
5. **Intro** — "Dear Sir/Madam," with dotted-fill client name, enquiry no/date.
6. **Items table** — repeating header; columns Sr. 62px, Description, Qty 62px, Rate 104px, Amount 122px; footer totals.
7. **Closing** — Amount in words, terms list, signature and stamp.
8. **Page footer** — company name left, "Page N" right, hairline top border.

Multi-page: page 1 carries the letterhead; continuation pages repeat the company header with "Continued from page N"; the table header repeats; totals/terms/signature stay together on the last page.

Print: `@page { size: A4; margin: 9mm; }`, `print-color-adjust: exact`, no JavaScript.

---

## 6. Accessibility & Responsive Summary

- Labelled inputs, visible `:focus-visible` outlines, `aria-modal` dialogs, Esc to close, focus trap.
- Status conveyed by text + colour, never colour alone.
- Desktop-first; two-column stats <=1080px; drawer sidebar <=900px; stacked forms <=640px; builder bottom bar wraps.
- Contrast >= 4.5:1 for body text (ink `#1f2f6b` on white qualifies).
