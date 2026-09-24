<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The sidebar must be present on every authenticated screen.
 *
 * Regression: the <aside> originally carried x-show="open". Because `open`
 * starts as false, Alpine applied display:none and hid the sidebar at every
 * viewport width. The bug was invisible while Alpine itself was not booting.
 *
 * Visibility is therefore driven purely by transform utilities: closed means
 * off-canvas, and min-[901px] pins it open on desktop.
 */
class SidebarVisibilityTest extends TestCase
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

    /** The sidebar element on its own, without the rest of the shell. */
    private function aside(string $html): string
    {
        $start = strpos($html, '<aside');
        $this->assertNotFalse($start, 'The app shell must render a sidebar.');

        $end = strpos($html, '</aside>', $start);
        $this->assertNotFalse($end, 'The sidebar element is not closed.');

        return substr($html, $start, $end - $start);
    }

    public function test_sidebar_is_not_hidden_by_x_show(): void
    {
        $this->assertStringNotContainsString(
            'x-show',
            $this->aside($this->page()),
            'x-show="open" hides the sidebar on desktop because open starts false.'
        );
    }

    public function test_sidebar_is_pinned_open_above_900px(): void
    {
        $aside = $this->aside($this->page());

        $this->assertStringContainsString('min-[901px]:translate-x-0', $aside);
        $this->assertStringContainsString('min-h-screen min-[901px]:pl-[232px]', $this->page());
    }

    public function test_sidebar_contains_every_navigation_link(): void
    {
        $aside = $this->aside($this->page());

        foreach (['Dashboard', 'Clients', 'Templates', 'Quotes'] as $label) {
            $this->assertMatchesRegularExpression(
                '/>\s*'.preg_quote($label, '/').'\s*</',
                $aside,
                "The sidebar is missing the {$label} link."
            );
        }
    }

    public function test_built_css_emits_the_desktop_override_after_the_negative_utility(): void
    {
        $files = glob(public_path('build/assets/*.css')) ?: [];

        $this->assertNotEmpty($files, 'Compiled CSS missing. Run `npm run build`.');

        $css = (string) file_get_contents((string) end($files));

        $this->assertStringContainsString('min-width:901px', $css);

        $negative = strpos($css, '.-translate-x-full');
        $override = strpos($css, 'min-\[901px\]\:translate-x-0');

        $this->assertNotFalse($negative);
        $this->assertNotFalse($override);

        // Otherwise -translate-x-full wins the cascade and hides the sidebar.
        $this->assertGreaterThan(
            $negative,
            $override,
            'The 901px override must be emitted after -translate-x-full.'
        );
    }
}
