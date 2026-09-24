<?php

namespace App\Services;

use App\Models\Quote;
// Aliases are required: PHP class names are case-insensitive, so importing
// both the facade (Pdf) and the wrapper (PDF) under their own names collides.
use Barryvdh\DomPDF\Facade\Pdf as PdfFacade;
use Barryvdh\DomPDF\PDF as Dompdf;

/**
 * Server-side PDF generation (PRD FR-11, AD-5).
 *
 * Rendered by Dompdf from a dedicated Blade template. Browser screenshots and
 * headless browsers are never used.
 */
class PdfService
{
    public function __construct(private readonly QuotationDocumentService $documents) {}

    /**
     * Stream a quote as a downloadable PDF.
     */
    public function download(Quote $quote)
    {
        return $this->pdf($quote)->download($this->filename($quote));
    }

    /**
     * Render a quote for inline viewing in the browser.
     */
    public function stream(Quote $quote)
    {
        return $this->pdf($quote)->stream($this->filename($quote));
    }

    /**
     * Raw PDF bytes. Used for verification and for future channels such as
     * email attachments.
     */
    public function render(Quote $quote): string
    {
        return $this->pdf($quote)->output();
    }

    /**
     * Build the configured Dompdf instance for a quote.
     *
     * Remote loading is disabled: every asset is local, which is both a
     * security control and a shared-hosting requirement.
     */
    private function pdf(Quote $quote): Dompdf
    {
        $quote->loadMissing('items');

        return PdfFacade::loadView('pdf.quote', [
            'doc' => $this->documents->fromQuote($quote),
            'styles' => $this->stylesheet(),
        ])
            ->setPaper('a4')
            ->setOption('isRemoteEnabled', false)
            ->setOption('isHtml5ParserEnabled', true);
    }

    /**
     * Filename such as QT-2026-00001-ABC-Technologies.pdf.
     */
    private function filename(Quote $quote): string
    {
        $company = trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', (string) ($quote->template_snapshot['name'] ?? 'Quotation')), '-');

        return $quote->quote_number.($company !== '' ? '-'.substr($company, 0, 40) : '').'.pdf';
    }

    /**
     * The A4 stylesheet, read from disk and inlined.
     *
     * Dompdf cannot process the Vite bundle, so the same stylesheet used on
     * screen is injected here. One source of truth for both renderings
     * (Architecture.md 5.4).
     */
    private function stylesheet(): string
    {
        $path = resource_path('css/quotation.css');

        return is_file($path) ? (string) file_get_contents($path) : '';
    }
}
