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

        $this->assertGreaterThanOrEqual(2, count($pageStreams), 'The fixture must exercise PDF pagination.');
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
     * Assert that the quotation frame itself remains inside the A4 page.
     *
     * A table can be wider than its containing frame without changing the PDF
     * page count. Dompdf then clips the rightmost cells at the page boundary,
     * which is why this check reads the frame rectangle from the content stream
     * rather than relying only on extracted text.
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
                $y = (float) $rectangle[2];
                $width = (float) $rectangle[3];
                $height = (float) $rectangle[4];

                // The page background is a full-page rectangle starting at x=0;
                // the quotation frame is the tall inset rectangle above it.
                if ($x > 0 && $height > 700) {
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
