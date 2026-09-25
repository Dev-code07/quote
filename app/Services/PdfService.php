<?php

namespace App\Services;

use App\Models\Quote;
// Aliases are required: PHP class names are case-insensitive, so importing
// both the facade (Pdf) and the wrapper (PDF) under their own names collides.
use Barryvdh\DomPDF\Facade\Pdf as PdfFacade;
use Barryvdh\DomPDF\PDF as Dompdf;
use Illuminate\Support\Facades\Storage;

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
     * Raw HTML for a quote, used by the client-side paginator.
     *
     * The browser is the only engine here that can measure text, so it decides
     * where the page breaks go; Dompdf then renders the same markup. Both read
     * the identical stylesheet and the identical x-a4-sheet component, so the
     * two cannot disagree about the document's appearance.
     */
    public function documentHtml(Quote $quote): string
    {
        $quote->loadMissing('items');

        $doc = $this->documents->fromQuote($quote);

        return view('pdf.quote', [
            'doc' => $doc,
            'styles' => $this->stylesheet(),
        ])->render();
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

        $doc = $this->documents->fromQuote($quote);
        $doc['company'] = $this->inlineImageAssets($doc['company'] ?? []);

        return PdfFacade::loadView('pdf.quote', [
            'doc' => $doc,
            'styles' => $this->stylesheet(),
        ])
            ->setPaper('a4')
            ->setOption('isRemoteEnabled', false)
            ->setOption('isHtml5ParserEnabled', true);
    }

    /**
     * Dompdf cannot fetch the public HTTP URL when remote loading is disabled.
     * Convert only local public-disk assets to data URIs so the PDF embeds the
     * actual uploaded logo/signature/stamp without opening remote file access.
     *
     * @param  array<string, mixed>  $company
     * @return array<string, mixed>
     */
    private function inlineImageAssets(array $company): array
    {
        foreach (['logo_url', 'signature_url', 'stamp_url'] as $key) {
            $url = $company[$key] ?? null;

            if (! is_string($url) || $url === '' || str_starts_with($url, 'data:')) {
                continue;
            }

            $path = $this->publicAssetPath($url);

            if ($path === null || ! Storage::disk('public')->exists($path)) {
                continue;
            }

            $contents = Storage::disk('public')->get($path);

            if ($contents === '') {
                continue;
            }

            $mime = Storage::disk('public')->mimeType($path) ?: 'application/octet-stream';
            $company[$key] = 'data:'.$mime.';base64,'.base64_encode($contents);
        }

        return $company;
    }

    /**
     * Extract a public-disk relative path from a local storage URL.
     */
    private function publicAssetPath(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $prefix = '/storage/';

        if (! is_string($path) || ! str_starts_with($path, $prefix)) {
            return null;
        }

        $relative = rawurldecode(substr($path, strlen($prefix)));

        return $relative !== '' && ! str_contains($relative, '..') ? $relative : null;
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
