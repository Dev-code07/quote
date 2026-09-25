<?php

namespace Tests\Feature;

use App\Enums\QuoteStatus;
use App\Models\Quote;
use App\Models\QuoteTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards resources/views/dashboard.blade.php — the Blade replica of
 * docs/quoteflow_dashboard.html.
 *
 * The screen is a real quote list: filters and pagination run through
 * DashboardController, so the assertions below exercise the QUERY, not just
 * the markup. A regression that only re-rendered every row while ignoring the
 * parameters would still "look" right in the browser.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_dashboard_renders_the_prototype_shell(): void
    {
        Quote::factory()->create();

        $this->actingAs($this->admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Create, manage and track your quotations.')
            ->assertSee('Create New Quote')
            ->assertSee('Quote No.')
            ->assertSee('Total Quotes')
            ->assertSee('Total Value');
    }

    public function test_summary_cards_report_real_counts(): void
    {
        Quote::factory()->count(2)->create();                       // drafts
        Quote::factory()->sent()->count(3)->create();
        // Outside the current month, so it counts towards Total but not Value.
        Quote::factory()->approved()->create(['quote_date' => now()->subDays(40)]);

        $html = $this->actingAs($this->admin)->get(route('dashboard'))->getContent();

        $this->assertMatchesRegularExpression('/>6\s*<\/p>\s*<p class="text-\[13px\] font-semibold text-app-muted">Total Quotes/', $html);
        $this->assertMatchesRegularExpression('/>2\s*<\/p>\s*<p class="text-\[13px\] font-semibold text-app-muted">Drafts/', $html);
        $this->assertMatchesRegularExpression('/>3\s*<\/p>\s*<p class="text-\[13px\] font-semibold text-app-muted">Sent/', $html);
        $this->assertStringContainsString('This month', $html, 'Total Value is scoped to the current month.');
    }

    public function test_search_filters_server_side_and_keeps_other_rows_out(): void
    {
        Quote::factory()->create(['quote_number' => 'QT-2026-00001']);
        Quote::factory()->create(['quote_number' => 'QT-2026-99999']);

        $this->actingAs($this->admin)
            ->get(route('dashboard', ['search' => 'QT-2026-00001']))
            ->assertOk()
            ->assertSee('QT-2026-00001')
            ->assertDontSee('QT-2026-99999');
    }

    public function test_status_filter_only_returns_that_status(): void
    {
        Quote::factory()->count(2)->create();   // draft
        Quote::factory()->sent()->count(2)->create();

        $this->actingAs($this->admin)
            ->get(route('dashboard', ['status' => 'sent']))
            ->assertOk()
            ->assertSee('Showing 1–2 of 2 quotes');
    }

    public function test_an_invalid_status_value_is_ignored_rather_than_failing(): void
    {
        Quote::factory()->create();

        $this->actingAs($this->admin)
            ->get(route('dashboard', ['status' => 'not-a-status']))
            ->assertOk()
            ->assertSee('Showing 1–1 of 1 quotes');
    }

    public function test_relative_date_filters_are_applied(): void
    {
        Quote::factory()->create(['quote_number' => 'QT-2026-OLD11', 'quote_date' => now()->subDays(30)]);
        Quote::factory()->create(['quote_number' => 'QT-2026-NEW11', 'quote_date' => now()]);

        $this->actingAs($this->admin)
            ->get(route('dashboard', ['date' => 'week']))
            ->assertOk()
            ->assertSee('QT-2026-NEW11')
            ->assertDontSee('QT-2026-OLD11');

        $this->actingAs($this->admin)
            ->get(route('dashboard', ['date' => 'today']))
            ->assertOk()
            ->assertSee('Showing 1–1 of 1 quotes');
    }

    public function test_pagination_is_five_per_page_and_links_carry_filters(): void
    {
        Quote::factory()->count(6)->create();

        $response = $this->actingAs($this->admin)->get(route('dashboard'));

        $response->assertOk()->assertSee('Showing 1–5 of 6 quotes');
        $response->assertSee(route('dashboard', ['page' => 2]), false);

        $this->actingAs($this->admin)
            ->get(route('dashboard', ['page' => 2]))
            ->assertOk()
            ->assertSee('Showing 6–6 of 6 quotes');
    }

    public function test_row_actions_point_at_the_quote_lifecycle_routes(): void
    {
        $quote = Quote::factory()->create();

        $this->actingAs($this->admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('quotes.show', $quote), false)
            ->assertSee(route('quotes.edit', $quote), false)
            ->assertSee(route('quotes.pdf', $quote), false)
            ->assertSee(route('quotes.duplicate', $quote), false)
            ->assertSee(route('quotes.destroy', $quote), false);
    }

    public function test_template_picker_lists_templates_and_links_into_the_builder(): void
    {
        $template = QuoteTemplate::factory()->create(['created_by' => $this->admin->id]);

        $this->actingAs($this->admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Step 1 of 3')
            ->assertSee('Choose a Quote Template')
            ->assertSee(route('quotes.build', ['template_id' => $template->id]), false);
    }

    public function test_empty_state_offers_the_create_action(): void
    {
        $this->actingAs($this->admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('No quotes found')
            ->assertSee('Create your first quotation to get started.');
    }

    public function test_expired_quotes_remain_selectable_in_the_filter(): void
    {
        // Expired is excluded from the admin's status FORM (BR-05), but the
        // dashboard dropdown is built from every enum case so historic rows
        // can still be found.
        $options = collect(QuoteStatus::cases())
            ->mapWithKeys(fn (QuoteStatus $status): array => [$status->value => $status->label()])
            ->all();

        $this->assertArrayHasKey('expired', $options);

        $this->actingAs($this->admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('<option value="expired"', false);
    }
}
