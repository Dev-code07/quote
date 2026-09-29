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
    /**
     * 1 / (Hind's natural height as Dompdf reads it). Hind's ascender and
     * descender are 0.950em and 0.300em, so its line box is 1.25em and
     * Dompdf's default ratio of 1.1 inflated every line by 37.5%. At 0.8 the
     * line box equals the declared line-height, exactly like the browser.
     *
     * @see resources/css/quotation.css for the full derivation.
     */
    private const FONT_HEIGHT_RATIO = 0.8;

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

        $this->forgetFontRegistryEntriesMissingHere();

        $doc = $this->documents->fromQuote($quote);
        $doc['company'] = $this->inlineImageAssets($doc['company'] ?? []);

        return PdfFacade::loadView('pdf.quote', [
            'doc' => $doc,
            'styles' => $this->stylesheet(),
        ])
            ->setPaper('a4')
            // Dompdf paints a line box of (line_height / font_size) *
            // getFontHeight(), and getFontHeight() is
            // (ascender - descender) * font_size * fontHeightRatio -- the
            // declared line-height is ignored. Hind's own metrics add up to
            // 1.25em, so the ratio must be 1 / 1.25 to make Dompdf honour the
            // stylesheet exactly as the browser does. This is what keeps the
            // PDF, the A4 preview and the printed page identical; see the
            // calibration note at the top of resources/css/quotation.css.
            ->setOption('fontHeightRatio', self::FONT_HEIGHT_RATIO)
            ->setOption('isRemoteEnabled', false)
            ->setOption('isHtml5ParserEnabled', true);
    }

    /**
     * Drop font registry entries that only resolve on the machine that wrote
     * them.
     *
     * Dompdf records every registered family in
     * storage/fonts/installed-fonts.json, and that registry is machine
     * specific: written on the Windows dev box it stores absolute paths such
     * as D:\...\quote\storage\fonts\hind_normal_4d0aa.... Carried to shared
     * hosting inside the deployment zip, every one of those lookups fails --
     * yet Dompdf still measures the text from the entry, embeds a font with no
     * usable descriptor and lays the document out against the wrong metrics,
     * which is what made labels collide with their values and spread glyphs
     * apart on the host while the same code was flawless locally.
     *
     * Pruning the unusable entries lets Dompdf re-register the self-hosted
     * TTFs from public/fonts and rewrite the registry with portable names, so
     * one zip behaves identically on Windows, Linux and shared hosting.
     */
    private function forgetFontRegistryEntriesMissingHere(): void
    {
        $path = storage_path('fonts/installed-fonts.json');

        if (! is_file($path)) {
            return;
        }

        $registry = json_decode((string) file_get_contents($path), true);

        if (! is_array($registry)) {
            @unlink($path);

            return;
        }

        $portable = [];

        foreach ($registry as $family => $weights) {
            if (! is_array($weights)) {
                continue;
            }

            foreach ($weights as $weight => $file) {
                if (is_string($file) && $this->fontFileResolvesHere($file)) {
                    $portable[$family][$weight] = $file;
                }
            }
        }

        if ($portable === $registry) {
            return;
        }

        if ($portable === []) {
            @unlink($path);

            return;
        }

        file_put_contents($path, json_encode($portable, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * A registry entry is either a bare name inside Dompdf's font directory --
     * portable, and how Dompdf writes families it discovers through @font-face
     * -- or an absolute path that only means something on the machine that
     * wrote it. Absolute entries survive only while the file is really there.
     */
    private function fontFileResolvesHere(string $file): bool
    {
        if ($file === '') {
            return false;
        }

        // Absolute path (Windows drive or POSIX root): usable only if present.
        if (preg_match('#^[A-Za-z]:[\\\\/]#', $file) === 1 || str_starts_with($file, '/')) {
            return is_file($file);
        }

        $directory = storage_path('fonts/');

        return is_file($directory.$file) || is_file($directory.$file.'.ttf');
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
