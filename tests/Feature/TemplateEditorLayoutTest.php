<?php

namespace Tests\Feature;

use App\Models\QuoteTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The template editor must match docs/quoteflow_template_editor.html.
 *
 * Regression: it was built as a four-step tab wizard, so only one section was
 * reachable at a time and there was no live preview. The prototype is a single
 * scrolling page — every section stacked in the left column beside a sticky A4
 * preview — with Cancel/Save in both the page head and a sticky bottom bar.
 */
class TemplateEditorLayoutTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): string
    {
        return $this->actingAs(User::factory()->create())
            ->get(route('templates.create'))
            ->assertOk()
            ->getContent();
    }

    public function test_all_four_sections_are_visible_at_once(): void
    {
        $html = $this->editor();

        foreach (['Template Information', 'Company Branding', 'Quote Settings', 'Footer &amp; Signature'] as $title) {
            $this->assertStringContainsString($title, $html);
        }

        $this->assertStringNotContainsString(
            'x-show="step ===',
            $html,
            'The editor is a scrolling page, not a step wizard.'
        );
        $this->assertStringNotContainsString('role="tablist"', $html);
    }

    public function test_editor_uses_the_prototype_two_column_grid(): void
    {
        $this->assertStringContainsString(
            'xl:grid-cols-[minmax(0,1fr)_minmax(440px,1.05fr)]',
            $this->editor(),
            'Prototype .editor is minmax(0,1fr) minmax(440px,1.05fr).'
        );
    }

    public function test_live_preview_is_present_and_sticky(): void
    {
        $html = $this->editor();

        $this->assertStringContainsString('Live Preview', $html);
        $this->assertStringContainsString('sample data', $html, 'Prototype notes the preview uses sample data.');
        $this->assertStringContainsString('xl:sticky', $html);
        $this->assertStringContainsString(
            'Govt. Senior Secondary School',
            $html,
            'The preview must render the real A4 document built from the template.'
        );
        $this->assertStringContainsString('--ink:', $html, 'The A4 sheet carries its ink custom properties.');
    }

    public function test_page_head_and_bottom_bar_both_offer_cancel_and_save(): void
    {
        $html = $this->editor();

        $this->assertSame(
            2,
            substr_count($html, 'Save Template'),
            'The prototype has Save in the page head and in the bottom bar.'
        );
        $this->assertSame(2, preg_match_all('/>\s*Cancel\s*</', $html), 'Cancel appears in the page head and the bottom bar.');
        $this->assertStringContainsString('form="template-form"', $html, 'Both save buttons submit the editor form.');
    }

    public function test_branding_controls_match_the_prototype(): void
    {
        $html = $this->editor();

        $this->assertStringContainsString('Header alignment', $html);
        $this->assertStringContainsString('role="radiogroup"', $html, 'Prototype .seg is a radiogroup.');
        $this->assertStringContainsString('Accent colour', $html);
        $this->assertSame(6, substr_count($html, 'x-on:click="accent ='), 'Six accent swatches, one per palette.');
    }

    public function test_quote_settings_include_title_gst_and_tokens(): void
    {
        $html = $this->editor();

        $this->assertStringContainsString('Default quotation title', $html);
        $this->assertStringContainsString('Default GST rate', $html);
        $this->assertStringContainsString('name="intro_message"', $html);

        foreach (['{client_name}', '{enquiry_no}', '{enquiry_date}'] as $token) {
            $this->assertStringContainsString($token, $html, "The insert chips must offer {$token}.");
        }
    }

    public function test_stamp_fields_added_by_the_prototype_are_present(): void
    {
        $html = $this->editor();

        $this->assertStringContainsString('name="company_stamp"', $html);
        $this->assertStringContainsString('name="use_generated_seal"', $html);
        $this->assertStringContainsString('Seal city / text', $html);
        $this->assertStringContainsString('name="stamp_place"', $html);
    }

    public function test_company_stamp_can_be_uploaded_and_persisted(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('templates.store'), [
                'name' => 'Stamp Template',
                'company_name' => 'Acme Computers',
                'doc_title' => 'QUOTATION',
                'default_gst_rate' => 18,
                'header_alignment' => 'center',
                'accent_color' => 'navy',
                'company_stamp' => UploadedFile::fake()->image('stamp.png'),
                'use_generated_seal' => '1',
            ])
            ->assertRedirect();

        $template = QuoteTemplate::query()->where('name', 'Stamp Template')->firstOrFail();

        $this->assertNotNull($template->company_stamp_path, 'The uploaded stamp must be stored.');
        $this->assertTrue($template->use_generated_seal);

        Storage::disk('public')->assertExists($template->company_stamp_path);
    }

    public function test_generated_seal_defaults_off_when_unchecked(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('templates.store'), [
                'name' => 'No Seal',
                'company_name' => 'Acme Computers',
                'doc_title' => 'QUOTATION',
                'default_gst_rate' => 18,
                'header_alignment' => 'center',
                'accent_color' => 'navy',
            ])
            ->assertRedirect();

        $this->assertFalse(
            QuoteTemplate::query()->where('name', 'No Seal')->firstOrFail()->use_generated_seal
        );
    }
}
