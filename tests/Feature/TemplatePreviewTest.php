<?php

namespace Tests\Feature;

use App\Models\QuoteTemplate;
use App\Models\User;
use App\Services\TemplateDraftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The template editor's right-hand panel must reflect the left-hand form.
 *
 * Regression: the preview used to be frozen server HTML, so only the accent
 * colour changed live and every text field waited for a page reload. It is now
 * re-rendered by QuoteTemplateController::preview through the same
 * QuotationDocumentService the PDF uses, which these tests pin down.
 */
class TemplatePreviewTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create();
    }

    private function preview(array $overrides = [], ?User $admin = null)
    {
        return $this->actingAs($admin ?? $this->admin())
            ->post(route('templates.preview'), $overrides)
            ->assertOk()
            ->getContent();
    }

    public function test_guests_cannot_reach_the_preview_endpoint(): void
    {
        $this->post(route('templates.preview'), [])->assertRedirect(route('login'));
    }

    public function test_each_editor_field_is_reflected_in_the_preview(): void
    {
        $html = $this->preview([
            'name' => 'Hardware Quote',
            'company_name' => 'Himalayan Computers',
            'letterhead_display_name' => 'Himalayan',
            'company_gstin' => '02AAHFH7781K1Z9',
            'email' => 'sales@himalayan.in',
            'mobile_1' => '94180-45521',
            'mobile_2' => '0177-2654410',
            'address' => 'Shop 7, Mall Road, Shimla',
            'tagline' => 'Laptops, Desktops & Printers',
            'doc_title' => 'PROFORMA INVOICE',
            'authorized_person' => 'Vikram Thakur',
            'designation' => 'Proprietor',
            'stamp_place' => 'Shimla (H.P.)',
            'delivery_period' => '30 days from purchase order',
            'warranty' => 'Three years onsite',
            'validity_text' => '10 days from the above date',
            'notes' => 'Freight extra as applicable',
            'use_generated_seal' => '1',
            'extra_terms' => 'Payment after installation against bill.',
            'default_gst_rate' => 12,
        ]);

        $this->assertStringContainsString('Himalayan Computers', $html, 'company name');
        $this->assertStringContainsString('Himalayan', $html, 'letterhead display name');
        $this->assertStringContainsString('02AAHFH7781K1Z9', $html, 'GSTIN moves into the top strip');
        $this->assertStringContainsString('94180-45521', $html, 'mobile moves into the top strip');
        $this->assertStringContainsString('0177-2654410', $html, 'alternate number');
        $this->assertStringContainsString('sales@himalayan.in', $html, 'email in the address line');
        $this->assertStringContainsString('Shop 7, Mall Road, Shimla', $html, 'address');
        $this->assertStringContainsString('Laptops, Desktops &amp; Printers', $html, 'tagline');
        $this->assertStringContainsString('PROFORMA INVOICE', $html, 'document title badge');
        $this->assertStringContainsString('Vikram Thakur', $html, 'authorised person');
        $this->assertStringContainsString('Proprietor', $html, 'designation');
        $this->assertStringContainsString('Shimla (H.P.)', $html, 'seal city');
        $this->assertStringContainsString('30 days from purchase order', $html, 'delivery period');
        $this->assertStringContainsString('Three years onsite', $html, 'warranty');
        $this->assertStringContainsString('10 days from the above date', $html, 'validity');
        $this->assertStringContainsString('Freight extra as applicable', $html, 'notes');
        $this->assertStringContainsString('Payment after installation against bill.', $html, 'extra terms');
    }

    public function test_gst_rate_changes_the_preview_totals(): void
    {
        $withGst = $this->preview(['company_name' => 'Acme', 'default_gst_rate' => 18]);
        $withoutGst = $this->preview(['company_name' => 'Acme', 'default_gst_rate' => 0]);

        // The prototype's sample items total 5,09,800.00; 18% is 91,764.00 on top.
        $this->assertStringContainsString('GST @ 18%', $withGst);
        $this->assertStringContainsString('91,764.00', $withGst);
        $this->assertStringContainsString('601,564.00', $withGst, 'exclusive GST on the grand total');

        $this->assertStringNotContainsString('GST @ 0%', $withoutGst);
        $this->assertStringContainsString('509,800.00', $withoutGst);
        $this->assertStringNotContainsString('91,764.00', $withoutGst);
    }

    public function test_accent_colour_and_alignment_reach_the_preview(): void
    {
        $maroon = $this->preview(['company_name' => 'Acme', 'accent_color' => 'maroon', 'header_alignment' => 'right']);
        $this->assertStringContainsString('--ink:#6e1f2a', $maroon, 'maroon ink variable');
        $this->assertStringContainsString('q-head right', $maroon);

        $navy = $this->preview(['company_name' => 'Acme', 'accent_color' => 'navy', 'header_alignment' => 'left']);
        $this->assertStringContainsString('--ink:#1f2f6b', $navy);
        $this->assertStringContainsString('q-head left', $navy);
    }

    public function test_partial_input_still_renders_and_is_never_validated(): void
    {
        // Mid-typing values must not blank the preview or raise a 422.
        $html = $this->preview([
            'company_name' => 'Hima',
            'company_gstin' => '02AA',
            'default_gst_rate' => 'not-a-number',
            'accent_color' => 'chartreuse',
        ]);

        $this->assertStringContainsString('Hima', $html);
        $this->assertStringContainsString('02AA', $html);
        $this->assertStringContainsString('--ink:#1f2f6b', $html, 'unknown palette falls back to navy');
    }

    public function test_generated_seal_can_be_turned_off(): void
    {
        $on = $this->preview(['company_name' => 'Acme', 'use_generated_seal' => '1', 'stamp_place' => 'Shimla']);
        $off = $this->preview(['company_name' => 'Acme', 'use_generated_seal' => '0', 'stamp_place' => 'Shimla']);

        $this->assertStringContainsString('q-stamp', $on);
        $this->assertStringContainsString('SIGNATORY', $on);
        $this->assertStringNotContainsString('q-stamp-ring', $off);
        $this->assertStringContainsString('No stamp', $off);
    }

    public function test_editing_keeps_the_stored_images_in_the_preview(): void
    {
        $template = QuoteTemplate::factory()->create([
            'company_name' => 'Acme',
            'logo_path' => 'templates/logos/kept.png',
        ]);

        Storage::fake('public');
        Storage::disk('public')->put('templates/logos/kept.png', 'x');

        $html = $this->preview([
            'template_id' => $template->getKey(),
            'company_name' => 'Acme Computers',
        ]);

        $this->assertStringContainsString('Acme Computers', $html, 'the new name is used');
        $this->assertStringContainsString('kept.png', $html, 'the unsaved logo is still shown');
    }

    public function test_preview_never_writes_to_the_database(): void
    {
        $before = QuoteTemplate::query()->count();

        $this->preview(['name' => 'Ghost', 'company_name' => 'Ghost Ltd', 'default_gst_rate' => 18]);

        $this->assertSame($before, QuoteTemplate::query()->count());
        $this->assertDatabaseMissing('quote_templates', ['name' => 'Ghost']);
    }

    public function test_intro_tokens_render_as_fill_ins_and_a_sample_client(): void
    {
        $html = $this->preview([
            'company_name' => 'Acme',
            'intro_message' => 'Thank you for enquiry {enquiry_no} dated {enquiry_date}, {client_name}.',
        ]);

        $this->assertSame(2, substr_count($html, 'class="q-fill"'), 'enquiry no. and date become fill-ins');
        $this->assertStringContainsString('q-sample', $html, 'the client name is shown as sample data');
        $this->assertStringNotContainsString('{enquiry_no}', $html, 'no raw token is left behind');
    }

    public function test_every_editor_form_field_is_honoured_by_the_draft_service(): void
    {
        $rendered = $this->actingAs($this->admin())
            ->get(route('templates.create'))
            ->assertOk()
            ->getContent();

        // Scope to the editor form: the layout also carries <meta name="viewport">.
        preg_match('/<form[^>]*id="template-form".*?<\/form>/s', $rendered, $form);
        $this->assertNotEmpty($form, 'the editor form must be present');

        preg_match_all('/name="([a-z_]+)"/', $form[0], $matches);
        $formFields = array_values(array_unique($matches[1]));

        // Fields the draft service deliberately does not own: the CSRF token, the
        // PUT verb override, the "set as default" flag, the remove-file flags and
        // the three file inputs (those are previewed from a local data URL, not
        // uploaded on every keystroke).
        $notEditorFields = ['_token', '_method', 'is_default'];

        $expected = array_values(array_diff(
            array_merge($formFields, TemplateDraftService::EDITOR_FIELDS),
            array_merge($notEditorFields, ['remove_logo', 'remove_signature', 'remove_company_stamp', 'template_id', 'logo', 'signature', 'company_stamp'])
        ));

        foreach ($expected as $field) {
            $this->assertContains(
                $field,
                TemplateDraftService::EDITOR_FIELDS,
                "\"{$field}\" appears in the editor but the preview would ignore it."
            );
        }
    }
}
