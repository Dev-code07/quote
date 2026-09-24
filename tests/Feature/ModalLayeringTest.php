<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The modal panel must paint above its own backdrop.
 *
 * Regression: the panel was missing Breeze's `relative` utility, so it stayed
 * position:static. The backdrop is position:fixed, and CSS paints positioned
 * elements above unpositioned ones regardless of DOM order. The dimmed
 * backdrop therefore covered the panel, making every field look disabled and
 * swallowing each click as a click-outside-to-close.
 */
class ModalLayeringTest extends TestCase
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

    /** The modal component markup only, without the surrounding page shell. */
    private function modal(string $html): string
    {
        $start = strpos($html, 'fixed inset-0 overflow-y-auto');
        $this->assertNotFalse($start, 'The clients page must render the create/edit modal.');

        return substr($html, $start);
    }

    public function test_modal_panel_is_positioned_so_it_stacks_above_the_backdrop(): void
    {
        $this->assertStringContainsString(
            'relative mb-6 bg-white',
            $this->modal($this->page()),
            'The panel needs `relative`; without it the fixed backdrop paints on top and blocks input.'
        );
    }

    public function test_backdrop_is_present_and_precedes_the_panel(): void
    {
        $modal = $this->modal($this->page());

        $backdrop = strpos($modal, 'absolute inset-0 bg-gray-500 opacity-75');
        $panel = strpos($modal, 'relative mb-6 bg-white');

        $this->assertNotFalse($backdrop, 'The modal must render a dimmed backdrop.');
        $this->assertNotFalse($panel);

        $this->assertLessThan(
            $panel,
            $backdrop,
            'The backdrop must come first so the positioned panel wins the paint order.'
        );
    }

    public function test_backdrop_click_handler_does_not_cover_the_panel(): void
    {
        $modal = $this->modal($this->page());

        // The click-to-close lives on the backdrop wrapper, which is a sibling
        // of the panel. Only a positioned panel can sit above it.
        $this->assertStringContainsString('x-on:click="show = false"', $modal);
        $this->assertStringContainsString('rounded-lg overflow-hidden shadow-xl', $modal);
    }

    public function test_built_css_emits_the_relative_utility(): void
    {
        $files = glob(public_path('build/assets/*.css')) ?: [];

        $this->assertNotEmpty($files, 'Compiled CSS missing. Run `npm run build`.');

        $css = (string) file_get_contents((string) end($files));

        $this->assertMatchesRegularExpression(
            '/\.relative\s*\{[^}]*position:\s*relative/',
            $css,
            'Tailwind must emit `.relative` for the modal panel to stack correctly.'
        );
    }
}
