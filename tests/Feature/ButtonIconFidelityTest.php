<?php

namespace Tests\Feature;

use App\Enums\QuoteStatus;
use App\Models\Client;
use App\Models\Quote;
use App\Models\QuoteTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Locks the button-icon and print behaviour of the quote screen against the
 * client prototypes in docs/quote_preview.html.
 *
 * Guards three regressions seen in review:
 *  1. x-button call sites rendering no icon at all (the :icon prop existed
 *     but was never used anywhere).
 *  2. A view naming an icon that is missing from the icon registry, which
 *     silently renders nothing.
 *  3. window.print() emitting the whole app page instead of the A4 sheet.
 */
class ButtonIconFidelityTest extends TestCase
{
    use RefreshDatabase;

    private function quote(): Quote
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();
        $template = QuoteTemplate::factory()->create();

        return Quote::factory()->create([
            'client_id' => $client->id,
            'template_id' => $template->id,
            'created_by' => $user->id,
            'status' => QuoteStatus::Draft,
        ]);
    }

    public function test_quote_screen_buttons_all_carry_an_icon(): void
    {
        $response = $this->actingAs(User::first() ?? User::factory()->create())
            ->get(route('quotes.show', $this->quote()));

        $response->assertOk();

        $html = $response->getContent();

        // The icon name is a consumed @props value, so it never reaches the
        // HTML. Assert on each icon's own SVG path data instead.
        $paths = [
            'edit' => 'M12 20h9',
            'download' => 'M21 15v4a2 2 0 0 1-2 2H5',
            'printer' => 'M6 18H4a2 2 0 0 1-2-2v-5',
            'copy' => 'M5 15H4a2 2 0 0 1-2-2V4',
            'trash' => 'M19 6v14a2 2 0 0 1-2 2H7',
        ];

        foreach ($paths as $icon => $path) {
            $this->assertStringContainsString(
                $path,
                $html,
                "The quote action bar is missing the [{$icon}] icon."
            );
        }
    }

    public function test_print_button_sets_the_document_title_and_prints(): void
    {
        $quote = $this->quote();

        $html = $this->actingAs(User::first() ?? User::factory()->create())
            ->get(route('quotes.show', $quote))->getContent();

        $this->assertStringContainsString('window.print()', $html);
        $this->assertStringContainsString('document.title', $html);
        $this->assertStringContainsString($quote->quote_number, $html);
    }

    public function test_app_chrome_is_hidden_when_printing(): void
    {
        $response = $this->actingAs(User::first() ?? User::factory()->create())
            ->get(route('quotes.show', $this->quote()));

        $html = $response->getContent();

        // The action bar, side cards and zoom toolbar must not print.
        $this->assertStringContainsString('no-print', $html);

        // The sheet itself opts in, and the scroll stage is neutralised.
        $this->assertStringContainsString('print-sheet', $html);
        $this->assertStringContainsString('print-stage', $html);
    }

    public function test_print_stylesheet_exists_in_the_compiled_css(): void
    {
        $css = collect(glob(public_path('build/assets/*.css')))
            ->map(fn ($f) => (string) file_get_contents($f))
            ->implode("\n");

        $this->assertNotSame('', $css, 'No compiled CSS found — run npm run build.');
        $this->assertStringContainsString('.print-sheet', $css);
        $this->assertStringContainsString('.print-stage', $css);
        $this->assertStringContainsString('.no-print', $css);
    }

    public function test_every_icon_named_by_a_view_exists_in_the_registry(): void
    {
        $iconView = (string) file_get_contents(
            resource_path('views/components/icon.blade.php')
        );

        preg_match_all("/'([a-z0-9-]+)' => '<(?:line|circle|rect|path|polyline|polygon)/", $iconView, $m);
        $known = $m[1];

        $this->assertContains('printer', $known, 'The Print icon is missing from the registry.');

        $unknown = [];
        foreach (glob(resource_path('views/**/*.blade.php')) ?: [] as $file) {
            $contents = (string) file_get_contents($file);
            preg_match_all('/(?::icon|icon)="([a-z0-9-]+)"/', $contents, $uses);
            foreach ($uses[1] as $name) {
                if (! in_array($name, $known, true)) {
                    $unknown[] = basename($file).': '.$name;
                }
            }
        }

        $this->assertSame([], $unknown, 'Views reference icons that do not exist: '.implode(', ', $unknown));
    }
}
