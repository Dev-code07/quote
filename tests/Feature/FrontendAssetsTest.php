<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Guards the client-side build.
 *
 * Regression: resources/js/app.js once imported the quote builder but never
 * imported Alpine or called Alpine.start(). Every interactive feature in the
 * app silently stopped working (Add Item, live totals, modals, zoom, the
 * template editor) while every server-rendered test still passed.
 */
class FrontendAssetsTest extends TestCase
{
    private function assetPath(string $pattern): string
    {
        $files = glob(public_path('build/assets/'.$pattern)) ?: [];

        $this->assertNotEmpty($files, 'Compiled asset missing. Run `npm run build`.');

        return (string) end($files);
    }

    public function test_manifest_points_at_built_assets(): void
    {
        $manifest = public_path('build/manifest.json');

        $this->assertFileExists($manifest, 'Run `npm run build` before deploying.');

        $decoded = json_decode((string) file_get_contents($manifest), true);

        $this->assertArrayHasKey('resources/js/app.js', $decoded);
        $this->assertArrayHasKey('resources/css/app.css', $decoded);
    }

    public function test_built_javascript_bundles_alpine(): void
    {
        $bundle = (string) file_get_contents($this->assetPath('*.js'));

        // A working Alpine build is ~100 kB; without it the bundle is roughly half.
        $this->assertStringContainsString('alpine', strtolower($bundle));
        $this->assertGreaterThan(80_000, strlen($bundle), 'Alpine appears to be missing from the bundle.');
    }

    public function test_built_javascript_registers_the_quote_builder(): void
    {
        $bundle = (string) file_get_contents($this->assetPath('*.js'));

        $this->assertStringContainsString('quoteBuilder', $bundle);
        $this->assertStringContainsString('addItem', $bundle);
    }

    public function test_source_entrypoint_starts_alpine(): void
    {
        $source = (string) file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString("import Alpine from 'alpinejs'", $source);
        $this->assertStringContainsString('Alpine.start()', $source);
    }
}
