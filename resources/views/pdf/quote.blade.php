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

        /* The sheet is a fixed A4 page; the screen shadow is meaningless on
           paper. Width/height/padding all come from the shared stylesheet so
           the PDF cannot drift from the preview.
           `min-height` is dropped: the sheet's own 297mm is exactly the page
           height, so the rounding of a single millimetre pushed a blank sheet
           out after every real one. The .q-frame's fixed height is what makes
           the page full, and the page background is already white. */
        .q-sheet {
            box-shadow: none;
            margin: 0 auto;
            min-height: 0;
            line-height: 12.2pt;
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