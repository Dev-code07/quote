<?php

namespace Tests\Feature\Security;

use App\Models\Client;
use App\Models\Quote;
use App\Models\QuoteTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * rules.md section 6: user-supplied values must never be rendered unescaped.
 */
class OutputEscapingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
    }

    public function test_intro_message_html_is_escaped_on_the_quote_page(): void
    {
        $client = Client::factory()->create(['created_by' => $this->admin->id]);
        $template = QuoteTemplate::factory()->create([
            'intro_message' => 'Hello <script>alert("xss-intro")</script> {client_name}',
            'created_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)->post(route('quotes.store'), [
            'template_id' => $template->id,
            'client_id' => $client->id,
            'quote_date' => now()->toDateString(),
            'valid_until' => now()->addDays(15)->toDateString(),
            'gst_rate' => 18,
            'items' => [['description' => 'Widget', 'qty' => 1, 'rate' => 100]],
        ]);

        $quote = Quote::firstOrFail();

        $html = $this->actingAs($this->admin)->get(route('quotes.show', $quote))->getContent();

        $this->assertStringNotContainsString('<script>alert("xss-intro")</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_client_name_html_is_escaped_on_the_quote_page(): void
    {
        $client = Client::factory()->create([
            'name' => '<script>alert("xss-client")</script> Ltd.',
            'created_by' => $this->admin->id,
        ]);
        $template = QuoteTemplate::factory()->create(['created_by' => $this->admin->id]);

        $this->actingAs($this->admin)->post(route('quotes.store'), [
            'template_id' => $template->id,
            'client_id' => $client->id,
            'quote_date' => now()->toDateString(),
            'valid_until' => now()->addDays(15)->toDateString(),
            'gst_rate' => 18,
            'items' => [['description' => 'Widget', 'qty' => 1, 'rate' => 100]],
        ]);

        $quote = Quote::firstOrFail();

        $html = $this->actingAs($this->admin)->get(route('quotes.show', $quote))->getContent();

        $this->assertStringNotContainsString('<script>alert("xss-client")</script>', $html);
    }

    public function test_item_description_html_is_escaped(): void
    {
        $client = Client::factory()->create(['created_by' => $this->admin->id]);
        $template = QuoteTemplate::factory()->create(['created_by' => $this->admin->id]);

        $this->actingAs($this->admin)->post(route('quotes.store'), [
            'template_id' => $template->id,
            'client_id' => $client->id,
            'quote_date' => now()->toDateString(),
            'valid_until' => now()->addDays(15)->toDateString(),
            'gst_rate' => 18,
            'items' => [['description' => 'Widget <img src=x onerror=alert(1)>', 'qty' => 1, 'rate' => 100]],
        ]);

        $quote = Quote::firstOrFail();

        $html = $this->actingAs($this->admin)->get(route('quotes.show', $quote))->getContent();

        // The payload may survive as escaped TEXT, but never as a live tag.
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringContainsString('&lt;img', $html);
    }

    public function test_client_name_html_is_escaped_on_the_client_page(): void
    {
        $client = Client::factory()->create([
            'name' => '<script>alert("xss-list")</script>',
            'created_by' => $this->admin->id,
        ]);

        $html = $this->actingAs($this->admin)->get(route('clients.index'))->getContent();

        $this->assertStringNotContainsString('<script>alert("xss-list")</script>', $html);
    }

    public function test_template_company_name_html_is_escaped(): void
    {
        QuoteTemplate::factory()->create([
            'company_name' => '<script>alert("xss-tpl")</script>',
            'created_by' => $this->admin->id,
        ]);

        $html = $this->actingAs($this->admin)->get(route('templates.index'))->getContent();

        $this->assertStringNotContainsString('<script>alert("xss-tpl")</script>', $html);
    }
}
