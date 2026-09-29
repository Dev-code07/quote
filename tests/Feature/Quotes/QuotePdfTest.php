<?php

namespace Tests\Feature\Quotes;

use App\Models\Client;
use App\Models\Quote;
use App\Models\QuoteTemplate;
use App\Models\User;
use App\Services\PdfService;
use App\Services\SnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * PRD FR-11 / Architecture.md 5.4: the PDF is rendered server-side by Dompdf
 * from resources/views/pdf/quote.blade.php.
 */
class QuotePdfTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Quote $quote;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $client = Client::factory()->create(['name' => 'IIT Mandi', 'created_by' => $this->admin->id]);
        $template = QuoteTemplate::factory()->create([
            'company_name' => 'ABC Technologies Pvt. Ltd.',
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)->post(route('quotes.store'), [
            'template_id' => $template->id,
            'client_id' => $client->id,
            'quote_date' => now()->toDateString(),
            'valid_until' => now()->addDays(15)->toDateString(),
            'gst_rate' => 18,
            'items' => [['description' => 'Business laptop', 'qty' => 2, 'rate' => 50000]],
        ]);

        $this->quote = Quote::with('items')->firstOrFail();
    }

    public function test_pdf_can_be_downloaded(): void
    {
        $response = $this->actingAs($this->admin)->get(route('quotes.pdf', $this->quote));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_builder_preview_renders_unsaved_input_as_a4_sheet(): void
    {
        $template = QuoteTemplate::query()->firstOrFail();
        $client = Client::query()->firstOrFail();

        $response = $this->actingAs($this->admin)->post(route('quotes.preview'), [
            'template_id' => $template->id,
            'client_id' => $client->id,
            'quote_date' => now()->toDateString(),
            'valid_until' => now()->addDays(15)->toDateString(),
            'gst_rate' => 18,
            'items' => [
                ['description' => 'Business laptop', 'qty' => 2, 'rate' => 50000],
                ['description' => 'Half-typed row', 'qty' => '', 'rate' => ''],
            ],
            'terms' => ['delivery' => 'Within 7 days'],
        ]);

        $response->assertOk();
        // The shared sheet, not a second copy of the markup.
        $response->assertSee('q-sheet', false);
        $response->assertSee('Business laptop', false);
        $response->assertSee('Within 7 days', false);
    }

    public function test_builder_preview_never_consumes_a_quote_number(): void
    {
        $template = QuoteTemplate::query()->firstOrFail();
        $client = Client::query()->firstOrFail();

        $this->actingAs($this->admin)->post(route('quotes.preview'), [
            'template_id' => $template->id,
            'client_id' => $client->id,
            'quote_date' => now()->toDateString(),
            'valid_until' => now()->addDays(15)->toDateString(),
            'gst_rate' => 18,
            'items' => [['description' => 'Business laptop', 'qty' => 2, 'rate' => 50000]],
        ])->assertOk();

        // Still only the one quote from setUp(): no number was allocated.
        $this->assertSame(1, Quote::query()->count());
    }

    public function test_builder_preview_requires_authentication(): void
    {
        // Drop the user set in setUp so this really is an unauthenticated request.
        $this->app['auth']->forgetGuards();

        $this->post(route('quotes.preview'), [])->assertRedirect(route('login'));
    }

    public function test_pdf_filename_contains_the_quote_number(): void
    {
        $response = $this->actingAs($this->admin)->get(route('quotes.pdf', $this->quote));

        $disposition = (string) $response->headers->get('content-disposition');

        $this->assertStringContainsString($this->quote->quote_number, $disposition);
    }

    public function test_pdf_streams_for_inline_viewing(): void
    {
        $this->actingAs($this->admin)
            ->get(route('quotes.pdf.view', $this->quote))
            ->assertOk();
    }

    public function test_generated_pdf_is_a_real_pdf_document(): void
    {
        $bytes = app(PdfService::class)->render($this->quote);

        $this->assertNotEmpty($bytes, 'The PDF service produced no output.');
        $this->assertStringStartsWith('%PDF-', substr($bytes, 0, 5));
    }

    public function test_pdf_embeds_uploaded_signature_and_stamp(): void
    {
        Storage::fake('public');

        $signature = UploadedFile::fake()->image('signature.png', 240, 80);
        $stamp = UploadedFile::fake()->image('stamp.jpg', 160, 160);

        Storage::disk('public')->put(
            'templates/signatures/signature.png',
            file_get_contents($signature->getRealPath())
        );
        Storage::disk('public')->put(
            'templates/stamps/stamp.jpg',
            file_get_contents($stamp->getRealPath())
        );

        $template = QuoteTemplate::findOrFail($this->quote->template_id);
        $template->update([
            'signature_path' => 'templates/signatures/signature.png',
            'company_stamp_path' => 'templates/stamps/stamp.jpg',
        ]);

        $quote = $this->quote->fresh()->load('items');
        $quote->update([
            'template_snapshot' => app(SnapshotService::class)->template($template->fresh()),
        ]);

        $bytes = app(PdfService::class)->render($quote->fresh()->load('items'));

        $this->assertGreaterThanOrEqual(
            2,
            preg_match_all('/\/Subtype\s*\/Image/', $bytes),
            'Both uploaded images must be embedded in the PDF.'
        );
    }

    /**
     * Dompdf's font registry (storage/fonts/installed-fonts.json) is machine
     * specific: written on the development box it holds absolute paths such as
     * D:\...\storage\fonts\hind_normal_.... Shipped to shared hosting inside
     * the deployment zip, none of those paths resolve there, yet Dompdf still
     * measures text from the entry and embeds a descriptor-less font -- which
     * is what made the hosted PDF overlap itself (labels colliding with their
     * values, glyphs spread apart, blank pages) while the identical code was
     * flawless locally. PdfService must drop entries this machine cannot
     * resolve so Dompdf re-registers the TTFs from public/fonts.
     */
    public function test_font_registry_drops_entries_that_do_not_resolve_here(): void
    {
        $path = storage_path('fonts/installed-fonts.json');
        $original = is_file($path) ? (string) file_get_contents($path) : null;

        // Absolute paths that exist on no machine but the one they were
        // written on (a POSIX deployment path and a foreign Windows drive).
        $foreignPosix = '/home/example/deploy/storage/fonts/hind_normal_00000000000000000000000000000000.ttf';
        $foreignWindows = 'Z:\\example\\deploy\\storage\\fonts\\hind_bold_11111111111111111111111111111111.ttf';

        file_put_contents($path, json_encode([
            'hind' => [
                'normal' => $foreignPosix,
                '600' => 'hind_600_d32d5558d780ddc89d3b583ef837131d',
                'bold' => $foreignWindows,
            ],
            'zilla slab' => ['bold' => 'zilla_slab_bold_8cad8266abacdb70074608cb0e9e98d0'],
        ], JSON_PRETTY_PRINT));

        try {
            $bytes = app(PdfService::class)->render($this->quote->fresh()->load('items'));

            $this->assertStringContainsString('/BaseFont', $bytes, 'The PDF must still embed its fonts.');

            $registry = (string) file_get_contents($path);

            $this->assertStringNotContainsString(
                'hind_normal_00000000000000000000000000000000',
                $registry,
                'An entry that cannot resolve on this machine must not survive a render.'
            );
            $this->assertStringNotContainsString(
                'hind_bold_11111111111111111111111111111111',
                $registry,
                'A foreign Windows path must not survive a render.'
            );
        } finally {
            if ($original === null) {
                @unlink($path);
            } else {
                file_put_contents($path, $original);
            }
        }
    }

    public function test_generated_pdf_has_no_blank_pages(): void
    {
        foreach (range(2, 6) as $position) {
            $this->quote->items()->create([
                'position' => $position,
                'description' => 'Additional engineering service and deployment package '.$position,
                'quantity' => 1,
                'rate' => 25000,
                'amount' => 25000,
            ]);
        }

        $this->quote->load('items');
        $bytes = app(PdfService::class)->render($this->quote);
        $pageStreams = $this->pdfPageContentStreams($bytes);

        $this->assertSame(
            2,
            count($pageStreams),
            'A six-row quote must page exactly twice: a fixed-height .q-sheet that no longer fits the '
            .'A4 page pushes itself, and a blank page, onto the following sheet (see the 282mm override '
            .'in resources/views/pdf/quote.blade.php).'
        );
        $this->assertPdfFrameStaysInsidePage($pageStreams);

        foreach ($pageStreams as $page => $stream) {
            $this->assertMatchesRegularExpression(
                '/\bBT\b/',
                $stream,
                "PDF page {$page} has no text object and is blank."
            );
            $this->assertMatchesRegularExpression(
                '/\b(?:Tj|TJ)\b/',
                $stream,
                "PDF page {$page} has no rendered text and is blank."
            );
        }
    }

    /**
     * The A4 grid is calibrated against the approved sample (QT-2026-00002).
     *
     * Dompdf ignores a declared line-height and paints
     * (line_height / font_size) * natural_height * fontHeightRatio instead
     * (FrameDecorator/Text.php + FontMetrics/Adapter). Hind's natural height is
     * 1.25em, so PdfService pins the ratio to 0.8 and the shared stylesheet
     * declares the sample's line boxes. If either side drifts, the A4 preview,
     * the printed page and the PDF stop agreeing -- the defect that put the
     * totals band on a page of its own.
     *
     * @see resources/css/quotation.css for the full derivation.
     */
    public function test_line_boxes_are_calibrated_to_the_approved_sample(): void
    {
        $method = new \ReflectionMethod(PdfService::class, 'pdf');
        $method->setAccessible(true);

        $dompdf = $method->invoke(app(PdfService::class), $this->quote->fresh()->load('items'))->getDomPDF();

        $this->assertSame(
            0.8,
            $dompdf->getOptions()->getFontHeightRatio(),
            'Dompdf must be told to honour the declared line-height.'
        );

        // Dompdf reports measured frames while it lays the document out; the
        // tree itself is disposed once render() returns.
        $heights = [];
        $dompdf->setCallbacks([[
            'event' => 'end_frame',
            'f' => function ($frame) use (&$heights): void {
                $node = $frame->get_node();

                if ($node instanceof \DOMElement) {
                    $heights[] = round((float) $frame->get_content_box()['h'], 2);
                }
            },
        ]]);

        $dompdf->render();

        $this->assertNotEmpty($heights, 'The PDF produced no measured frames.');

        // Dompdf measures in points, and the content box of a single-line
        // block or cell IS its line box. These three are the declared line
        // boxes the approved sample was measured from: sheet text 16.775pt,
        // the contact column 22.28pt and the item table 18.69pt. Before the
        // calibration the same declarations painted 23.07, 30.64 and 25.71pt,
        // and the browser painted 18.85, 21.6 and 18.13pt -- three grids.
        $observed = implode(', ', array_slice(array_unique($heights), 0, 40));

        foreach (['sheet text' => 16.775, 'contact column' => 22.28, 'table cell' => 18.69] as $band => $expected) {
            $matched = array_filter($heights, fn (float $height): bool => abs($height - $expected) < 0.03);

            $this->assertNotEmpty(
                $matched,
                "No {$band} line box measures {$expected}pt, so the PDF no longer matches the approved "
                ."sample. Measured: {$observed}"
            );
        }
    }

    /**
     * Assert that the quotation frame itself remains inside the A4 page.
     *
     * A table can be wider than its containing frame without changing the PDF
     * page count. Dompdf then clips the rightmost cells at the page boundary,
     * which is why this check reads the frame rectangle from the content stream
     * rather than relying only on extracted text.
     *
     * The frame is identified by WIDTH, not height: the sheet is content-height
     * by design (a short page ends after its content, it no longer fills the
     * 276.65mm frame), so a height threshold would stop matching on short
     * pages. The frame is 184mm = 521.6pt wide and inset from the page edge;
     * the page background rectangle starts at x = 0 and is 595.28pt wide.
     *
     * @param  list<string>  $streams
     */
    private function assertPdfFrameStaysInsidePage(array $streams): void
    {
        $a4Width = 595.28;

        foreach ($streams as $page => $stream) {
            preg_match_all(
                '/(-?[0-9.]+)\s+(-?[0-9.]+)\s+(-?[0-9.]+)\s+(-?[0-9.]+)\s+re\b/',
                $stream,
                $rectangles,
                PREG_SET_ORDER
            );

            $frameRightEdges = [];
            foreach ($rectangles as $rectangle) {
                $x = (float) $rectangle[1];
                $width = (float) $rectangle[3];

                // Inset, frame-width rectangle: the quotation frame itself.
                if ($x > 0 && $width > 480 && $width < $a4Width) {
                    $frameRightEdges[] = $x + $width;
                }
            }

            $this->assertNotEmpty($frameRightEdges, "PDF page {$page} has no measurable quotation frame.");
            $this->assertLessThanOrEqual(
                $a4Width,
                max($frameRightEdges),
                "PDF page {$page} frame extends beyond the A4 page and clips its right edge."
            );
        }
    }

    /**
     * Return the decoded content stream for every PDF page.
     *
     * Dompdf writes one indirect object per page and one compressed stream per
     * page content. Inspecting the streams catches an extra page even when the
     * PDF's total page count looks plausible.
     *
     * @return list<string>
     */
    private function pdfPageContentStreams(string $bytes): array
    {
        preg_match_all('/(\d+)\s+\d+\s+obj\s*(.*?)\s*endobj/s', $bytes, $objects, PREG_SET_ORDER);
        $this->assertNotEmpty($objects, 'The PDF contains no indirect objects.');

        $byId = [];
        foreach ($objects as $object) {
            $byId[(int) $object[1]] = $object[2];
        }

        $streams = [];
        foreach ($byId as $objectId => $body) {
            if (! preg_match('/\/Type\s*\/Page(?!s)/', $body)) {
                continue;
            }

            preg_match_all('/\/Contents\s+(?:\[)?\s*(\d+)\s+\d+\s+R/', $body, $references);
            $this->assertNotEmpty($references[1], "PDF page object {$objectId} has no content stream.");

            foreach ($references[1] as $reference) {
                $content = $byId[(int) $reference] ?? '';
                $this->assertNotSame('', $content, "PDF content object {$reference} is missing.");

                preg_match('/stream\r?\n(.*?)\r?\nendstream/s', $content, $stream);
                $this->assertArrayHasKey(1, $stream, "PDF content object {$reference} has no stream.");

                $decoded = $stream[1];
                if (str_contains($content, '/FlateDecode')) {
                    $inflated = @gzuncompress($decoded);
                    $this->assertNotFalse($inflated, "PDF content object {$reference} could not be inflated.");
                    $decoded = $inflated;
                }

                $streams[] = $decoded;
            }
        }

        $this->assertNotEmpty($streams, 'The PDF contains no page content streams.');

        return $streams;
    }

    public function test_guests_cannot_download_a_pdf(): void
    {
        // Drop the user set in setUp so this really is an unauthenticated request.
        $this->app['auth']->forgetGuards();

        $this->get(route('quotes.pdf', $this->quote))->assertRedirect(route('login'));
    }
}
