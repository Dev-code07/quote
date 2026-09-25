<?php

namespace Tests\Feature\Quotes;

use App\Models\Client;
use App\Models\Quote;
use App\Models\QuoteTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteCreationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Client $client;

    private QuoteTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->client = Client::factory()->create(['name' => 'IIT Mandi', 'created_by' => $this->admin->id]);
        $this->template = QuoteTemplate::factory()->create([
            'name' => 'Standard',
            'company_name' => 'ABC Technologies Pvt. Ltd.',
            'default_gst_rate' => 18,
            'created_by' => $this->admin->id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'template_id' => $this->template->id,
            'client_id' => $this->client->id,
            'quote_date' => now()->toDateString(),
            'valid_until' => now()->addDays(15)->toDateString(),
            'gst_rate' => 18,
            'items' => [
                ['description' => 'Business laptop', 'qty' => 5, 'rate' => 72500],
                ['description' => 'Software licence', 'qty' => 25, 'rate' => 10500],
            ],
        ], $overrides);
    }

    public function test_quote_snapshot_includes_template_signature_and_stamp_urls(): void
    {
        $this->template->update([
            'signature_path' => 'templates/signatures/snapshot.png',
            'company_stamp_path' => 'templates/stamps/snapshot.png',
        ]);

        $this->actingAs($this->admin)->post(route('quotes.store'), $this->payload());

        $quote = Quote::firstOrFail();

        $this->assertStringContainsString(
            '/storage/templates/signatures/snapshot.png',
            $quote->template_snapshot['signature_url']
        );
        $this->assertStringContainsString(
            '/storage/templates/stamps/snapshot.png',
            $quote->template_snapshot['stamp_url']
        );
    }

    public function test_quote_can_be_created_with_computed_totals(): void
    {
        $this->actingAs($this->admin)
            ->post(route('quotes.store'), $this->payload(['discount_amount' => 5000]))
            ->assertRedirect();

        $quote = Quote::firstOrFail();

        // 5*72500 + 25*10500 = 625000
        $this->assertSame(625000.0, (float) $quote->subtotal);
        $this->assertSame(5000.0, (float) $quote->discount_amount);
        // (625000 - 5000) * 18% = 111600
        $this->assertSame(111600.0, (float) $quote->gst_amount);
        $this->assertSame(731600.0, (float) $quote->grand_total);
    }

    public function test_quote_number_is_generated_in_the_expected_format(): void
    {
        $this->actingAs($this->admin)->post(route('quotes.store'), $this->payload());

        $this->assertMatchesRegularExpression(
            '/^QT-'.now()->year.'-\d{5}$/',
            Quote::firstOrFail()->quote_number
        );
    }

    public function test_quote_numbers_are_unique_and_sequential(): void
    {
        $this->actingAs($this->admin)->post(route('quotes.store'), $this->payload());
        $this->actingAs($this->admin)->post(route('quotes.store'), $this->payload());

        $numbers = Quote::orderBy('id')->pluck('quote_number')->all();

        $this->assertCount(2, $numbers);
        $this->assertNotSame($numbers[0], $numbers[1]);
        $this->assertSame('QT-'.now()->year.'-00001', $numbers[0]);
        $this->assertSame('QT-'.now()->year.'-00002', $numbers[1]);
    }

    public function test_backend_ignores_submitted_totals(): void
    {
        // BR-02: the browser cannot dictate money.
        $this->actingAs($this->admin)->post(route('quotes.store'), $this->payload([
            'subtotal' => 1,
            'gst_amount' => 1,
            'grand_total' => 1,
        ]));

        $quote = Quote::firstOrFail();

        $this->assertSame(737500.0, (float) $quote->grand_total);
    }

    public function test_line_items_are_persisted_with_computed_amounts(): void
    {
        $this->actingAs($this->admin)->post(route('quotes.store'), $this->payload());

        $quote = Quote::with('items')->firstOrFail();

        $this->assertCount(2, $quote->items);
        $this->assertSame('Business laptop', $quote->items->first()->description);
        $this->assertSame(362500.0, (float) $quote->items->first()->amount);
    }

    public function test_snapshots_are_stored_on_the_quote(): void
    {
        $this->actingAs($this->admin)->post(route('quotes.store'), $this->payload());

        $quote = Quote::firstOrFail();

        $this->assertSame('IIT Mandi', $quote->client_snapshot['name']);
        $this->assertSame('ABC Technologies Pvt. Ltd.', $quote->template_snapshot['name']);
        $this->assertNotNull($quote->amount_in_words);
    }

    public function test_amount_in_words_is_stored(): void
    {
        $this->actingAs($this->admin)->post(route('quotes.store'), $this->payload());

        $this->assertStringContainsString('Rupees', Quote::firstOrFail()->amount_in_words);
    }

    public function test_quote_requires_a_template_client_and_items(): void
    {
        $this->actingAs($this->admin)
            ->post(route('quotes.store'), ['gst_rate' => 18])
            ->assertSessionHasErrors(['template_id', 'client_id', 'items', 'quote_date', 'valid_until']);

        $this->assertDatabaseCount('quotes', 0);
    }

    public function test_gst_rate_must_be_allowed(): void
    {
        $this->actingAs($this->admin)
            ->post(route('quotes.store'), $this->payload(['gst_rate' => 7]))
            ->assertSessionHasErrors('gst_rate');
    }

    public function test_valid_until_cannot_precede_quote_date(): void
    {
        $this->actingAs($this->admin)
            ->post(route('quotes.store'), $this->payload([
                'quote_date' => now()->toDateString(),
                'valid_until' => now()->subDay()->toDateString(),
            ]))
            ->assertSessionHasErrors('valid_until');
    }

    public function test_quantity_must_be_greater_than_zero(): void
    {
        $this->actingAs($this->admin)
            ->post(route('quotes.store'), $this->payload([
                'items' => [['description' => 'Bad qty', 'qty' => 0, 'rate' => 100]],
            ]))
            ->assertSessionHasErrors('items.0.qty');
    }

    public function test_calculate_endpoint_returns_live_totals(): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('quotes.calculate'), $this->payload(['discount_amount' => 1000]))
            ->assertOk()
            ->assertJsonPath('subtotal', 625000)
            ->assertJsonPath('discount', 1000)
            ->assertJsonPath('gst_amount', 112320)
            ->assertJsonPath('grand_total', 736320);
    }

    public function test_quote_can_be_updated(): void
    {
        $this->actingAs($this->admin)->post(route('quotes.store'), $this->payload());
        $quote = Quote::firstOrFail();

        $this->actingAs($this->admin)->put(route('quotes.update', $quote), $this->payload([
            'items' => [['description' => 'Only item', 'qty' => 2, 'rate' => 1000]],
        ]));

        $quote->refresh();

        $this->assertSame(2000.0, (float) $quote->subtotal);
        $this->assertSame(1, $quote->items()->count());
    }

    public function test_quote_can_be_viewed_and_edited(): void
    {
        $this->actingAs($this->admin)->post(route('quotes.store'), $this->payload());
        $quote = Quote::firstOrFail();

        $this->actingAs($this->admin)->get(route('quotes.show', $quote))->assertOk();
        $this->actingAs($this->admin)->get(route('quotes.edit', $quote))->assertOk();
    }

    public function test_quote_list_requires_authentication(): void
    {
        $this->get(route('quotes.index'))->assertRedirect(route('login'));
    }

    public function test_quote_list_can_be_searched_and_filtered(): void
    {
        $this->actingAs($this->admin)->post(route('quotes.store'), $this->payload());
        $this->actingAs($this->admin)->post(route('quotes.store'), $this->payload());

        $this->actingAs($this->admin)
            ->get(route('quotes.index', ['search' => 'IIT Mandi']))
            ->assertOk()
            ->assertSee('IIT Mandi');

        $this->actingAs($this->admin)
            ->get(route('quotes.index', ['status' => 'approved']))
            ->assertOk()
            ->assertDontSee('IIT Mandi');
    }

    public function test_guests_cannot_create_quotes(): void
    {
        $this->post(route('quotes.store'), $this->payload())->assertRedirect(route('login'));
        $this->assertDatabaseCount('quotes', 0);
    }
}
