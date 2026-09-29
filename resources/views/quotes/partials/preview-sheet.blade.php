{{--
    Live-preview fragment for the quote builder overlay.

    Returned by QuoteController::preview as raw HTML and swapped into the
    overlay by the quoteBuilder component in resources/js/app.js.
    It is the same x-a4-sheet the PDF is built from, so what the user sees
    in the preview is what gets printed.
--}}
<x-a4-sheet :doc="$doc" />