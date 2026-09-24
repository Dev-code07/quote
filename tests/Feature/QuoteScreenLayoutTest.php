<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Quote;
use App\Models\QuoteTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the two layout faults reported against the quote screen:
 *
 *  1. The Summary / Details cards fell BELOW the A4 sheet instead of sitting
 *     beside it. The cause was a viewport breakpoint (`xl:` = 1280px) on a
 *     layout whose content column is 232px narrower than the window because of
 *     the sidebar, so it collapsed one viewport too early.
 *
 *  2. Text in the sheet ran into the frame rule on the right-hand edge because
 *     the bands used three different horizontal gutters (16px, 14px, 12px) and
 *     long single-token values (email addresses) could not wrap.
 */
class QuoteScreenLayoutTest extends TestCase
{
    use RefreshDatabase;

    private function quoteFor(User $user): Quote
    {
        $template = QuoteTemplate::factory()->create(['created_by' => $user->id]);
        $client = Client::factory()->create([
            'name' => 'Kapoor Traders',
            'email' => 'kapoor@example.com',
        ]);

        return Quote::factory()->create([
            'created_by' => $user->id,
            'template_id' => $template->id,
            'client_id' => $client->id,
            // A long, single-token address: the value that used to render
            // straight through the right-hand frame rule.
            'client_snapshot' => [
                'name' => 'Kapoor Traders',
                'email' => 'rahul.codexmatrix@gmail.com',
                'address' => 'Village Khanyara, Teh. D/Shala, HP',
                'phone' => '+91 78071 02986',
            ],
        ]);
    }

    public function test_split_uses_a_container_query_not_a_viewport_breakpoint(): void
    {
        $css = (string) file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('.quote-split', $css);
        $this->assertStringContainsString('container-type: inline-size', $css);
        $this->assertStringContainsString('@container (min-width:', $css);

        // The old, broken construct must not come back: a 3-column grid whose
        // split depends on the window rather than the available content.
        $view = (string) file_get_contents(resource_path('views/quotes/show.blade.php'));
        $this->assertStringNotContainsString('xl:grid-cols-3', $view);
        $this->assertStringNotContainsString('xl:col-span-2', $view);
        $this->assertStringContainsString('quote-split', $view);
    }

    public function test_summary_and_details_cards_are_present_beside_the_preview(): void
    {
        $user = User::factory()->create();
        $quote = $this->quoteFor($user);

        $this->actingAs($user)
            ->get(route('quotes.show', $quote))
            ->assertOk()
            ->assertSee('Summary')
            ->assertSee('Details')
            ->assertSee('Historical integrity', false)
            ->assertSee('A4 Preview');
    }

    public function test_every_sheet_band_shares_one_horizontal_gutter(): void
    {
        $css = (string) file_get_contents(resource_path('css/quotation.css'));

        preg_match('/\.q-pagefoot\s*\{[^}]*\}/s', $css, $foot);
        $this->assertNotEmpty($foot, '.q-pagefoot rule not found');
        $this->assertMatchesRegularExpression(
            '/padding:\s*6px\s+16px\s+7px/',
            $foot[0],
            'The page footer must use the sheet-wide 16px gutter.'
        );

        preg_match('/\.q-words\s*\{(?<body>[^}]*)\}/s', $css, $words);
        $this->assertNotEmpty($words, '.q-words rule not found');
        // The gutter is the horizontal padding. `font-size: 12px` is unrelated
        // and must not be mistaken for a 12px margin.
        preg_match('/padding:\s*[^;]*;/', $words['body'], $pad);
        $this->assertNotEmpty($pad, '.q-words has no padding declaration');
        $this->assertStringContainsString('16px', $pad[0]);
        $this->assertStringNotContainsString('12px', $pad[0]);

        // No band may be left on the old 14px/12px gutters.
        foreach (['.q-parties', '.q-foot'] as $selector) {
            preg_match('/'.preg_quote($selector, '/').'\s*\{(?<body>[^}]*)\}/s', $css, $band);
            $this->assertNotEmpty($band, "{$selector} rule not found");
            preg_match('/padding:\s*[^;]*;/', $band['body'], $bandPad);
            $this->assertNotEmpty($bandPad, "{$selector} has no padding declaration");
            $this->assertStringContainsString(
                '16px',
                $bandPad[0],
                "{$selector} must keep the 16px gutter."
            );
        }
    }

    public function test_no_rule_is_declared_twice_in_the_sheet_stylesheet(): void
    {
        // Twice now an edit appended a rule instead of replacing the old one,
        // and the stale copy silently won on cascade. A duplicated rule is
        // always a mistake, so fail the build on it.
        //
        // Whole selectors are compared, not just the leading class: `.q-table
        // th` and `.q-table td` are two different, legitimate rules.
        $css = (string) file_get_contents(resource_path('css/quotation.css'));

        // Strip comments so a selector named in prose is not counted.
        $css = preg_replace('#/\*.*?\*/#s', '', $css) ?? $css;

        // At-rule blocks are scoped overrides, not duplicates: `.page-label`
        // is hidden in @media print on purpose. Compare the top level only.
        $css = preg_replace('/@(media|supports)[^{]*\{(?:[^{}]*\{[^{}]*\})*[^{}]*\}/s', '', $css) ?? $css;

        preg_match_all('/([^{}]+?)\s*\{[^{}]*\}/s', $css, $m);
        $selectors = array_values(array_filter(array_map(
            fn (string $s): string => trim(preg_replace('/\s+/', ' ', $s) ?? $s),
            $m[1]
        )));

        $counts = array_count_values($selectors);
        $duplicates = array_keys(array_filter($counts, fn (int $n): bool => $n > 1));

        $this->assertSame(
            [],
            $duplicates,
            'Rule(s) declared more than once in quotation.css: '.implode(' | ', $duplicates)
        );
    }

    public function test_long_single_token_values_can_wrap_inside_the_sheet(): void
    {
        $css = (string) file_get_contents(resource_path('css/quotation.css'));

        // An email address is one unbreakable token; without these the text
        // renders straight through the right-hand frame rule.
        foreach (['.q-parties > div', '.q-pagefoot > span'] as $selector) {
            preg_match('/'.preg_quote($selector, '/').'\s*\{[^}]*\}/s', $css, $rule);
            $this->assertNotEmpty($rule, "{$selector} rule not found");
            $this->assertStringContainsString(
                'overflow-wrap',
                $rule[0],
                "{$selector} must allow long values to wrap."
            );
        }
    }

    public function test_quote_screen_renders_with_a_long_email_snapshot(): void
    {
        $user = User::factory()->create();
        $quote = $this->quoteFor($user);

        $this->actingAs($user)
            ->get(route('quotes.show', $quote))
            ->assertOk()
            ->assertSee('rahul.codexmatrix@gmail.com');
    }
}
