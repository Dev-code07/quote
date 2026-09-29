{{--
    Dedicated A4 PDF template (PRD FR-11, Architecture.md 5.4).

    Rendered by Dompdf. Rules:
      - No JavaScript, no external requests (isRemoteEnabled is off).
      - Fonts are self-hosted in public/fonts (assumption A5).
      - Accent colours arrive as CSS custom properties from the snapshot.
      - The stylesheet is injected by PdfService from resources/css/quotation.css
        so the PDF and the on-screen preview can never diverge.

    The document body is the SAME x-a4-sheet component the on-screen preview
    renders. It used to be a second, hand-maintained copy of the markup, which
    is precisely how the two drifted apart; there is now one sheet component
    and one stylesheet for both.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $doc['meta']['no'] ?? 'Quotation' }}</title>

    <style>
        /* The sheet carries its own 9mm padding, so the PDF page has none.
           This is what makes the PDF page-for-page identical to the preview. */
        @page { size: A4; margin: 0; }

        /* Self-hosted fonts: Dompdf cannot reach a CDN at render time. */
        @font-face { font-family: 'Hind'; font-style: normal; font-weight: 400; src: url('{{ public_path('fonts/Hind-Regular.ttf') }}') format('truetype'); }
        @font-face { font-family: 'Hind'; font-style: normal; font-weight: 600; src: url('{{ public_path('fonts/Hind-SemiBold.ttf') }}') format('truetype'); }
        @font-face { font-family: 'Hind'; font-style: normal; font-weight: 700; src: url('{{ public_path('fonts/Hind-Bold.ttf') }}') format('truetype'); }
        @font-face { font-family: 'Zilla Slab'; font-style: normal; font-weight: 700; src: url('{{ public_path('fonts/ZillaSlab-Bold.ttf') }}') format('truetype'); }

        /* Injected shared A4 stylesheet (single source of truth). */
        {!! $styles !!}

        body { margin: 0; }

        /* The sheet is content-height: a page ends after its content, and the
           .q-page break rules in the shared stylesheet put every page on its
           own A4 sheet (@page A4 above). The screen shadow is meaningless on
           paper. Width, padding AND line-height come from the shared
           stylesheet so the PDF cannot drift from the preview -- an earlier
           PDF-only `line-height: 12.2pt` here silently restyled every
           inherited line in the PDF while the preview kept the shared value,
           which is how the two engines ended up 26mm apart on page 1. */
        .q-sheet {
            box-shadow: none;
            margin: 0 auto;
            /* Dompdf adds a block's padding to a fixed `height` even when
               box-sizing says border-box, so the shared stylesheet's 296mm
               sheet would occupy 310mm here: each sheet was pushed onto the
               following page and left a BLANK page behind (visible as pages 1
               and 3 of a two-page quotation). The browser's 296mm already
               includes the 2 x 7mm padding, so the PDF must declare
               296mm - 2 x 7mm = 282mm to paint the very same 296mm sheet.
               The limit is exact, not approximate: 284mm pages four times,
               283mm pages twice. */
            height: 282mm;
        }
        .q-frame { margin: 4px; }

        /* Dompdf applies table-cell width as a CONTENT width and then adds
           horizontal padding. The shared stylesheet intentionally declares
           border-box column widths for the browser, so applying those values
           directly in Dompdf makes the table wider than the frame. Subtract the
           20px cell padding from each PDF column width; the resulting rendered
           widths sum to the shared 177.6mm table width and keep the Amount
           column inside the A4 page. */
        .q-table th.w-sr,
        .q-table td.w-sr { width: 8.9mm !important; }
        .q-table th.w-desc,
        .q-table td.w-desc { width: 74.6mm !important; }
        .q-table th.w-qty,
        .q-table td.w-qty { width: 8.9mm !important; }
        .q-table th.w-rate,
        .q-table td.w-rate { width: 24.9mm !important; }
        .q-table th.w-amt,
        .q-table td.w-amt { width: 33.8mm !important; }

        /* The "Page N" caption is a screen affordance. It is hidden here rather
           than in @media print because Dompdf does not evaluate media queries
           at all -- it honours @page and nothing else. Left in, this ~5mm
           caption pushed the 297mm sheet past the page and Dompdf split it,
           emitting up to two BLANK pages ahead of the real content. */
        .page-label { display: none; }

        tr { page-break-inside: avoid; }
    </style>
</head>
<body>
    <x-a4-sheet :doc="$doc" :preview="false" />
</body>
</html>