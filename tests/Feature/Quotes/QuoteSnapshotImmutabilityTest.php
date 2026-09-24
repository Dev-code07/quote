<?php

namespace Tests\Feature\Quotes;

use App\Models\Client;
use App\Models\Quote;
use App\Models\QuoteTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BR-01: a saved quotation must never change because the client or template
 * was edited or deleted afterwards.
 */
class QuoteSnapshotImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Client $client;

    private QuoteTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->client = Client::factory()->create([
            'name' => 'Original Client Ltd.',
            'gstin' => '27AABCA1234F1Z6',
            'created_by' => $this->admin->id,
        ]);
        $this->template = QuoteTemplate::factory()->create([
            'name' => 'Original Template',
            'company_name' => 'Original Company Pvt. Ltd.',
            'tagline' => 'Original tagline',
            'created_by' => $this->admin->id,
        ]);

        $this->createQuote();
    }

    private function createQuote(): Quote
    {
        $this->actingAs($this->admin)->post(route('quotes.store'), [
            'template_id' => $this->template->id,
            'client_id' => $this->client->id,
            'quote_date' => now()->toDateString(),
            'valid_until' => now()->addDays(15)->toDateString(),
            'gst_rate' => 18,
            'items' => [['description' => 'Widget', 'qty' => 2, 'rate' => 1000]],
        ]);

        return Quote::with('items')->firstOrFail();
    }

    public function test_editing_the_client_does_not_change_an_existing_quote(): void
    {
        $quote = $this->createQuote();

        $this->actingAs($this->admin)->put(route('clients.update', $this->client), [
            'name' => 'Renamed Client Ltd.',
            'gstin' => '29BBBBB9999C1Z2',
            'is_active' => '1',
        ]);

        $quote->refresh();

        $this->assertSame('Original Client Ltd.', $quote->client_snapshot['name']);
        $this->assertSame('27AABCA1234F1Z6', $quote->client_snapshot['gstin']);
        $this->assertSame('Original Client Ltd.', $quote->clientName());
    }

    public function test_editing_the_template_does_not_change_an_existing_quote(): void
    {
        $quote = $this->createQuote();

        $this->actingAs($this->admin)->put(route('templates.update', $this->template), [
            'name' => 'Renamed Template',
            'company_name' => 'Renamed Company Pvt. Ltd.',
            'tagline' => 'Renamed tagline',
            'accent_color' => 'maroon',
            'header_alignment' => 'left',
            'doc_title' => 'ESTIMATE',
            'default_gst_rate' => 5,
        ]);

        $quote->refresh();

        $this->assertSame('Original Company Pvt. Ltd.', $quote->template_snapshot['name']);
        $this->assertSame('Original tagline', $quote->template_snapshot['tagline']);
    }

    public function test_gst_rate_on_the_quote_is_frozen_even_if_the_template_default_changes(): void
    {
        $quote = $this->createQuote();

        $this->assertSame(18.0, (float) $quote->gst_rate);

        $this->template->update(['default_gst_rate' => 28]);

        $this->assertSame(18.0, (float) $quote->fresh()->gst_rate);
    }

    public function test_deleting_the_template_leaves_the_quote_intact(): void
    {
        $quote = $this->createQuote();

        $this->actingAs($this->admin)->delete(route('templates.destroy', $this->template));

        $quote->refresh();

        $this->assertSame('Original Company Pvt. Ltd.', $quote->template_snapshot['name']);
        $this->assertSame('Original Client Ltd.', $quote->client_snapshot['name']);
        $this->assertSame(2000.0, (float) $quote->subtotal);
    }

    public function test_permanently_deleting_the_template_keeps_quote_history(): void
    {
        $quote = $this->createQuote();

        $this->actingAs($this->admin)->delete(route('templates.destroy', $this->template));
        $templateId = $this->template->id;

        $this->actingAs($this->admin)->delete(route('templates.force-destroy', ['template' => $templateId]));

        $quote->refresh();

        $this->assertNull($quote->template_id);
        $this->assertSame('Original Company Pvt. Ltd.', $quote->template_snapshot['name']);
        $this->actingAs($this->admin)->get(route('quotes.show', $quote))->assertOk();
    }

    public function test_client_cannot_be_permanently_deleted_while_quotes_reference_it(): void
    {
        // Assumption A2: force delete is blocked while live quotes exist, so a
        // client can never be purged out from under its quotation history.
        $this->createQuote();

        $this->actingAs($this->admin)->delete(route('clients.destroy', $this->client));
        $clientId = $this->client->id;

        $this->actingAs($this->admin)
            ->delete(route('clients.force-destroy', ['client' => $clientId]))
            ->assertRedirect(route('clients.trash'))
            ->assertSessionHas('error');

        $this->assertSoftDeleted('clients', ['id' => $clientId]);
    }

    public function test_quote_snapshot_survives_deleting_the_template_row_entirely(): void
    {
        $quote = $this->createQuote();

        // Hard-delete the template behind the application's back.
        $this->template->forceDelete();

        $quote->refresh();

        $this->assertSame('Original Company Pvt. Ltd.', $quote->companyName());
        $this->actingAs($this->admin)->get(route('quotes.show', $quote))->assertOk();
    }
}
