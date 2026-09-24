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
        @page { size: A4; margin: 9mm; }

        /* Self-hosted fonts: Dompdf cannot reach a CDN at render time. */
        @font-face { font-family: 'Hind'; font-style: normal; font-weight: 400; src: url('{{ public_path('fonts/Hind-Regular.ttf') }}') format('truetype'); }
        @font-face { font-family: 'Hind'; font-style: normal; font-weight: 600; src: url('{{ public_path('fonts/Hind-SemiBold.ttf') }}') format('truetype'); }
        @font-face { font-family: 'Hind'; font-style: normal; font-weight: 700; src: url('{{ public_path('fonts/Hind-Bold.ttf') }}') format('truetype'); }
        @font-face { font-family: 'Zilla Slab'; font-style: normal; font-weight: 700; src: url('{{ public_path('fonts/ZillaSlab-Bold.ttf') }}') format('truetype'); }

        /* Injected shared A4 stylesheet (single source of truth). */
        {!! $styles !!}

        body { margin: 0; }
        .q-sheet { box-shadow: none; margin: 0; width: 100%; min-height: 0; padding: 0; }
        .q-frame { margin: 0; }

        /* Repeat the column headings on every printed page. */
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
    </style>
</head>
<body>
    <x-a4-sheet :doc="$doc" :preview="false" />
</body>
</html>