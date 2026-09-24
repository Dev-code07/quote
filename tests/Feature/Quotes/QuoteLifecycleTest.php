<?php

namespace Tests\Feature\Quotes;

use App\Enums\QuoteStatus;
use App\Models\Client;
use App\Models\Quote;
use App\Models\QuoteTemplate;
use App\Models\User;
use App\Services\ExpireQuotesService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Client $client;

    private QuoteTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->client = Client::factory()->create(['created_by' => $this->admin->id]);
        $this->template = QuoteTemplate::factory()->create(['created_by' => $this->admin->id]);
    }

    private function createQuote(array $overrides = []): Quote
    {
        $this->actingAs($this->admin)->post(route('quotes.store'), array_merge([
            'template_id' => $this->template->id,
            'client_id' => $this->client->id,
            'quote_date' => now()->toDateString(),
            'valid_until' => now()->addDays(15)->toDateString(),
            'gst_rate' => 18,
            'items' => [['description' => 'Widget', 'qty' => 1, 'rate' => 1000]],
        ], $overrides));

        return Quote::firstOrFail();
    }

    public function test_new_quote_starts_as_draft(): void
    {
        $this->assertSame(QuoteStatus::Draft, $this->createQuote()->status);
    }

    public function test_quote_can_be_marked_as_sent(): void
    {
        $quote = $this->createQuote();

        $this->actingAs($this->admin)->patch(route('quotes.status', $quote), ['status' => 'sent']);

        $this->assertSame(QuoteStatus::Sent, $quote->fresh()->status);
    }

    public function test_quote_can_be_approved(): void
    {
        $quote = $this->createQuote();

        $this->actingAs($this->admin)->patch(route('quotes.status', $quote), ['status' => 'approved']);

        $this->assertSame(QuoteStatus::Approved, $quote->fresh()->status);
    }

    public function test_admin_cannot_set_expired_manually(): void
    {
        // BR-05: Expired is only ever set by the scheduler.
        $quote = $this->createQuote();

        $this->actingAs($this->admin)
            ->patch(route('quotes.status', $quote), ['status' => 'expired'])
            ->assertRedirect(route('quotes.show', $quote))
            ->assertSessionHas('error');

        $this->assertSame(QuoteStatus::Draft, $quote->fresh()->status);
    }

    public function test_template_cannot_change_after_draft(): void
    {
        // Assumption A1.
        $quote = $this->createQuote();
        $this->actingAs($this->admin)->patch(route('quotes.status', $quote), ['status' => 'sent']);

        $other = QuoteTemplate::factory()->create(['created_by' => $this->admin->id]);

        $this->actingAs($this->admin)
            ->put(route('quotes.update', $quote), [
                'template_id' => $other->id,
                'client_id' => $this->client->id,
                'quote_date' => now()->toDateString(),
                'valid_until' => now()->addDays(15)->toDateString(),
                'gst_rate' => 18,
                'items' => [['description' => 'Widget', 'qty' => 1, 'rate' => 1000]],
            ])
            ->assertRedirect(route('quotes.show', $quote))
            ->assertSessionHas('error');

        $this->assertSame($this->template->id, $quote->fresh()->template_id);
    }

    public function test_template_can_change_while_draft_and_resnapshots(): void
    {
        $quote = $this->createQuote();
        $other = QuoteTemplate::factory()->create([
            'company_name' => 'Brand New Co.',
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)->put(route('quotes.update', $quote), [
            'template_id' => $other->id,
            'client_id' => $this->client->id,
            'quote_date' => now()->toDateString(),
            'valid_until' => now()->addDays(15)->toDateString(),
            'gst_rate' => 18,
            'items' => [['description' => 'Widget', 'qty' => 1, 'rate' => 1000]],
        ]);

        $quote->refresh();

        $this->assertSame($other->id, $quote->template_id);
        $this->assertSame('Brand New Co.', $quote->template_snapshot['name']);
    }

    public function test_past_validity_draft_is_expired_by_the_service(): void
    {
        $quote = $this->createQuote([
            'quote_date' => now()->subDays(30)->toDateString(),
            'valid_until' => now()->subDay()->toDateString(),
        ]);
        $this->assertSame(QuoteStatus::Draft, $quote->fresh()->status);

        (new ExpireQuotesService)->run();

        $this->assertSame(QuoteStatus::Expired, $quote->fresh()->status);
    }

    public function test_past_validity_sent_quote_is_expired(): void
    {
        $quote = $this->createQuote([
            'quote_date' => now()->subDays(30)->toDateString(),
            'valid_until' => now()->subDay()->toDateString(),
        ]);
        $this->actingAs($this->admin)->patch(route('quotes.status', $quote), ['status' => 'sent']);

        (new ExpireQuotesService)->run();

        $this->assertSame(QuoteStatus::Expired, $quote->fresh()->status);
    }

    public function test_approved_quote_is_never_auto_expired(): void
    {
        // Assumption A6.
        $quote = $this->createQuote([
            'quote_date' => now()->subDays(30)->toDateString(),
            'valid_until' => now()->subDay()->toDateString(),
        ]);
        $this->actingAs($this->admin)->patch(route('quotes.status', $quote), ['status' => 'approved']);

        (new ExpireQuotesService)->run();

        $this->assertSame(QuoteStatus::Approved, $quote->fresh()->status);
    }

    public function test_future_validity_quote_is_untouched(): void
    {
        $quote = $this->createQuote();

        (new ExpireQuotesService)->run();

        $this->assertSame(QuoteStatus::Draft, $quote->fresh()->status);
    }

    public function test_trashed_quote_is_not_expired(): void
    {
        $quote = $this->createQuote([
            'quote_date' => now()->subDays(30)->toDateString(),
            'valid_until' => now()->subDay()->toDateString(),
        ]);
        $quote->delete();

        (new ExpireQuotesService)->run();

        $this->assertSame(QuoteStatus::Draft, $quote->fresh()->status);
    }

    public function test_expire_command_reports_success(): void
    {
        $this->createQuote([
            'quote_date' => now()->subDays(30)->toDateString(),
            'valid_until' => now()->subDay()->toDateString(),
        ]);

        $this->artisan('quotes:expire')->assertSuccessful();
    }

    public function test_scheduler_registers_the_expire_command(): void
    {
        $events = app(Schedule::class)->events();

        $this->assertTrue(
            collect($events)->contains(fn ($event) => str_contains((string) $event->command, 'quotes:expire')),
            'The quotes:expire command must be scheduled for shared-hosting cron.'
        );
    }

    public function test_duplicate_creates_a_new_number_in_draft(): void
    {
        $original = $this->createQuote();

        $this->actingAs($this->admin)->post(route('quotes.duplicate', $original));

        $copy = Quote::orderByDesc('id')->first();

        $this->assertNotSame($original->quote_number, $copy->quote_number);
        $this->assertSame(QuoteStatus::Draft, $copy->status);
        $this->assertTrue($copy->quote_date->isToday());
        $this->assertSame($original->items->count(), $copy->items->count());
    }

    public function test_quote_delete_is_reversible(): void
    {
        $quote = $this->createQuote();

        $this->actingAs($this->admin)->delete(route('quotes.destroy', $quote));
        $this->assertSoftDeleted('quotes', ['id' => $quote->id]);

        $this->actingAs($this->admin)->post(route('quotes.restore', ['quote' => $quote->id]));
        $this->assertNotNull(Quote::find($quote->id));
    }

    public function test_quote_can_be_permanently_deleted_from_trash(): void
    {
        $quote = $this->createQuote();
        $quote->delete();

        $this->actingAs($this->admin)->delete(route('quotes.force-destroy', ['quote' => $quote->id]));

        $this->assertDatabaseMissing('quotes', ['id' => $quote->id]);
        $this->assertDatabaseMissing('quote_items', ['quote_id' => $quote->id]);
    }

    public function test_trashed_quote_is_hidden_from_the_list(): void
    {
        $quote = $this->createQuote();
        $quote->delete();

        // First request consumes the flash message from the store call.
        $this->actingAs($this->admin)->get(route('quotes.index'))->assertOk();

        $this->actingAs($this->admin)->get(route('quotes.index'))->assertOk()->assertDontSee($quote->quote_number);
        $this->actingAs($this->admin)->get(route('quotes.trash'))->assertOk()->assertSee($quote->quote_number);
    }
}
