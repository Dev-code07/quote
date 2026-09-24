{{--
    Live-preview fragment for the template editor.

    Returned by QuoteTemplateController::preview as raw HTML and swapped into
    the right-hand panel by the templateEditor component in resources/js/app.js.
    It is the same x-a4-sheet the PDF is built from, so what the admin sees
    while editing is what gets printed.
--}}
<x-a4-sheet :doc="$doc" />