<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Buttons must match the client prototypes in docs/*.html.
 *
 * The prototypes are the visual source of truth (design.md). Their .btn rule is:
 *   padding: 9px 16px; gap: 7px; border-radius: 6px; font-size: 13px;
 *   font-weight: 600; border: 1px solid transparent; white-space: nowrap;
 *
 * Regression: the component shipped with px-4 only (no vertical padding), an 8px
 * radius, and gap-2 (8px), so every button sat ~9px shorter and slightly wider
 * than the approved design.
 */
class ButtonSpacingTest extends TestCase
{
    use RefreshDatabase;

    private function markup(): string
    {
        $admin = User::factory()->create();

        return $this->actingAs($admin)
            ->get(route('clients.index'))
            ->assertOk()
            ->getContent();
    }

    /** The rendered <button> opening tag for the Add Client action. */
    private function buttonTag(string $html): string
    {
        $start = strpos($html, 'Add Client');
        $this->assertNotFalse($start, 'The clients page must render an Add Client button.');

        $open = strrpos(substr($html, 0, $start), '<button');
        $this->assertNotFalse($open, 'The Add Client label must sit inside a <button>.');

        $close = strpos($html, '>', $open);

        return substr($html, $open, $close - $open + 1);
    }

    public function test_button_matches_prototype_vertical_padding(): void
    {
        $this->assertStringContainsString(
            'py-[9px]',
            $this->buttonTag($this->markup()),
            'Prototype uses padding: 9px 16px; the vertical padding was missing entirely.'
        );
    }

    public function test_button_matches_prototype_horizontal_padding(): void
    {
        $tag = $this->buttonTag($this->markup());

        $this->assertStringContainsString('pl-[16px]', $tag);
        $this->assertStringContainsString('pr-[16px]', $tag);
    }

    public function test_button_matches_prototype_radius_gap_and_typography(): void
    {
        $tag = $this->buttonTag($this->markup());

        $this->assertStringContainsString('rounded-[6px]', $tag, 'Prototype uses --radius-sm (6px) for buttons.');
        $this->assertStringContainsString('gap-[7px]', $tag, 'Prototype uses gap: 7px.');
        $this->assertStringContainsString('text-[13px]', $tag);
        $this->assertStringContainsString('font-semibold', $tag);
    }

    public function test_button_has_transparent_border_and_no_wrap(): void
    {
        $tag = $this->buttonTag($this->markup());

        $this->assertStringContainsString('border-transparent', $tag, 'Base .btn sets a transparent 1px border.');
        $this->assertStringContainsString('whitespace-nowrap', $tag);
    }

    public function test_buttons_use_svg_icons_not_text_glyphs(): void
    {
        $html = $this->markup();

        $this->assertMatchesRegularExpression(
            '/<button[^>]*>.*?<svg.*?<\/svg>.*?Add Client/s',
            $html,
            'The Add Client button must carry an inline SVG icon.'
        );

        $this->assertDoesNotMatchRegularExpression(
            '/<span aria-hidden="true">\s*\+\s*<\/span>/',
            $html,
            'A bare "+" text glyph is not an icon; the prototype uses a stroked SVG.'
        );
    }

    public function test_button_icon_slot_sizes_the_svg_to_16px(): void
    {
        $html = $this->markup();

        $this->assertMatchesRegularExpression(
            '/<button[^>]*>\s*<svg[^>]*width="16"[^>]*height="16"/',
            $html,
            'The button icon slot must render a 16px SVG.'
        );
        $this->assertMatchesRegularExpression(
            '/<svg[^>]*viewBox="0 0 24 24"[^>]*stroke="currentColor"/',
            $html,
            'Icons must inherit the button colour via currentColor, as in the prototypes.'
        );
    }

    public function test_built_css_emits_the_button_geometry_utilities(): void
    {
        $files = glob(public_path('build/assets/*.css')) ?: [];

        $this->assertNotEmpty($files, 'Compiled CSS missing. Run `npm run build`.');

        $css = (string) file_get_contents((string) end($files));

        $this->assertStringContainsString('.py-\\[9px\\]', $css);
        $this->assertStringContainsString('.gap-\\[7px\\]', $css);
        $this->assertStringContainsString('.rounded-\\[6px\\]', $css);
    }

    /**
     * Icons must be referenced by name, never inlined into a component attribute.
     * An inline <svg> in an attribute makes Blade's component parser close the tag
     * at the first "/>", which unbalanced the generated if/endif and raised a
     * ParseError (HTTP 500) on the clients page.
     */
    public function test_no_view_passes_an_inline_svg_through_an_attribute(): void
    {
        $offenders = [];

        foreach (glob(resource_path('views/**/*.blade.php')) ?: [] as $file) {
            $contents = (string) file_get_contents($file);

            if (preg_match('/:\w[\w-]*="\s*\'?<svg/i', $contents)) {
                $offenders[] = basename($file);
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'Use <x-icon name="..." /> or icon="name" instead of an inline SVG in an attribute: '
                .implode(', ', $offenders)
        );
    }

    public function test_every_icon_name_referenced_by_a_view_exists(): void
    {
        $registry = (string) file_get_contents(
            base_path('resources/views/components/icon.blade.php')
        );

        preg_match_all('/\'([a-z0-9-]+)\'\s*=>/', $registry, $defined);
        $defined = $defined[1];

        $referenced = [];

        foreach (glob(resource_path('views/**/*.blade.php')) ?: [] as $file) {
            $contents = (string) file_get_contents($file);

            // icon="name" and :icon="'name'" style references in view source.
            preg_match_all('/:?\s*icon="\'?([a-z0-9-]+)"?/i', $contents, $matches);

            foreach ($matches[1] as $name) {
                $referenced[$name][] = basename($file);
            }
        }

        // The icon component's own registry must not count as a reference.
        unset($referenced['icon']);

        $this->assertNotEmpty($referenced, 'Views should reference icons by name.');

        foreach ($referenced as $name => $files) {
            $this->assertContains(
                $name,
                $defined,
                "Icon '{$name}' is used in ".implode(', ', array_unique($files))
                    .' but is missing from the x-icon registry.'
            );
        }
    }
}
