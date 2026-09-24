<?php

namespace Tests\Feature\Quotes;

use App\Models\Client;
use App\Models\Quote;
use App\Models\QuoteTemplate;
use App\Models\User;
use App\Services\PdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_guests_cannot_download_a_pdf(): void
    {
        // Drop the user set in setUp so this really is an unauthenticated request.
        $this->app['auth']->forgetGuards();

        $this->get(route('quotes.pdf', $this->quote))->assertRedirect(route('login'));
    }
}
