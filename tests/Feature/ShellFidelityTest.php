<?php

namespace Tests\Feature;

use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The app shell must match the client prototypes in docs/*.html.
 *
 * All five prototypes share one topbar: 60px tall, 28px side padding, a
 * 340px search box, a notification bell with a red dot, and a Help button.
 * The sidebar carries a "Main" nav label, a quote-count badge and a Settings
 * item. Metrics are asserted here because these are the most-repeated numbers
 * in the whole UI and the easiest to drift.
 */
class ShellFidelityTest extends TestCase
{
    use RefreshDatabase;

    private function page(): string
    {
        $admin = User::factory()->create();

        return $this->actingAs($admin)
            ->get(route('clients.index'))
            ->assertOk()
            ->getContent();
    }

    public function test_topbar_matches_prototype_height_padding_and_gap(): void
    {
        $html = $this->page();

        $this->assertMatchesRegularExpression(
            '/<header[^>]*h-\[60px\][^>]*px-7[^>]*>/',
            $html,
            'Prototype .topbar is height 60px with 28px side padding.'
        );
        $this->assertStringContainsString('gap-3.5', $html, 'Prototype .topbar uses gap: 14px.');
    }

    public function test_topbar_search_is_present_and_wired_to_the_search_route(): void
    {
        $html = $this->page();

        $this->assertStringContainsString('placeholder="Search quotes, clients..."', $html);
        $this->assertStringContainsString('name="q"', $html);
        $this->assertStringContainsString('action="'.route('search').'"', $html);
        $this->assertStringContainsString('max-w-[340px]', $html, 'Prototype caps .topbar-search at 340px.');
        $this->assertStringContainsString('pl-[34px]', $html, 'Input reserves room for the 15px search icon.');
    }

    public function test_notifications_and_help_buttons_are_present(): void
    {
        $html = $this->page();

        $this->assertStringContainsString('aria-label="Notifications"', $html);
        $this->assertStringContainsString('aria-label="Help"', $html);

        // The unread indicator from the prototype's .icon-btn .dot.
        $this->assertMatchesRegularExpression(
            '/size-\[7px\][^>]*bg-app-danger/',
            $html,
            'The notification bell must carry the red unread dot.'
        );
    }

    public function test_topbar_hides_the_page_title_used_by_the_prototype(): void
    {
        $header = substr(
            $this->page(),
            strpos($this->page(), '<header'),
            strpos($this->page(), '</header>') - strpos($this->page(), '<header')
        );

        $this->assertStringNotContainsString(
            '<h1',
            $header,
            'The prototype topbar holds only the search and icon buttons; the title lives in the page head.'
        );
    }

    public function test_sidebar_has_main_label_quote_badge_and_settings(): void
    {
        // The badge is only rendered when quotes exist, so seed one.
        Quote::factory()->create();

        $html = $this->page();

        $this->assertMatchesRegularExpression(
            '/text-\[11px\][^>]*uppercase[^>]*tracking-\[0\.06em\][^>]*>\s*Main/',
            $html,
            'Prototype .nav-label is an 11px uppercase "Main" label.'
        );
        $this->assertMatchesRegularExpression(
            '/>\s*Settings\s*</',
            $html,
            'The prototype sidebar links to Settings.'
        );
        $this->assertMatchesRegularExpression(
            '/rounded-full[^>]*px-\[7px\][^>]*>\s*1\s*</',
            $html,
            'Quote count uses .nav-badge and reflects the real total.'
        );
    }

    public function test_nav_items_use_prototype_padding_and_radius(): void
    {
        $this->assertMatchesRegularExpression(
            '/rounded-\[6px\][^>]*py-\[9px\][^>]*pl-\[10px\][^>]*pr-\[10px\]/',
            $this->page(),
            'Prototype .nav-item is padding 9px 10px with a 6px radius.'
        );
    }

    public function test_content_area_and_page_head_match_prototype_spacing(): void
    {
        $html = $this->page();

        $this->assertStringContainsString('<main class="p-7">', $html, 'Prototype .content uses 28px padding.');
        $this->assertStringContainsString('mb-[22px]', $html, 'Prototype .page-head has margin-bottom 22px.');
        $this->assertStringContainsString('text-[24px]', $html, 'Prototype .page-head h1 is 24px.');
    }

    public function test_built_css_emits_the_shell_metrics(): void
    {
        $files = glob(public_path('build/assets/*.css')) ?: [];

        $this->assertNotEmpty($files, 'Compiled CSS missing. Run `npm run build`.');

        $css = (string) file_get_contents((string) end($files));

        $this->assertStringContainsString('.h-\\[60px\\]', $css);
        $this->assertStringContainsString('.px-7', $css);
        $this->assertStringContainsString('.py-\\[9px\\]', $css);
        $this->assertStringContainsString('.pl-\\[10px\\]', $css);
        $this->assertStringContainsString('.p-7', $css);
    }
}
