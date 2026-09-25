<?php

namespace Tests\Feature\Templates;

use App\Enums\AccentPalette;
use App\Enums\HeaderAlignment;
use App\Models\QuoteTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class QuoteTemplateManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Standard Business Quote',
            'accent_color' => AccentPalette::Navy->value,
            'header_alignment' => HeaderAlignment::Center->value,
            'doc_title' => 'QUOTATION',
            'company_name' => 'ABC Technologies Pvt. Ltd.',
            'default_gst_rate' => 18,
        ], $overrides);
    }

    public function test_templates_index_requires_authentication(): void
    {
        $this->get(route('templates.index'))->assertRedirect(route('login'));
    }

    public function test_templates_index_lists_templates(): void
    {
        $admin = $this->admin();
        QuoteTemplate::factory()->create(['name' => 'Product Quotation', 'created_by' => $admin->id]);

        $this->actingAs($admin)
            ->get(route('templates.index'))
            ->assertOk()
            ->assertSee('Product Quotation');
    }

    public function test_template_can_be_created(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('templates.store'), $this->payload())
            ->assertRedirect();

        $this->assertDatabaseHas('quote_templates', [
            'name' => 'Standard Business Quote',
            'company_name' => 'ABC Technologies Pvt. Ltd.',
            'created_by' => $admin->id,
        ]);
    }

    public function test_template_name_and_company_are_required(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('templates.store'), ['accent_color' => 'navy', 'header_alignment' => 'center'])
            ->assertSessionHasErrors(['name', 'company_name', 'doc_title', 'default_gst_rate']);

        $this->assertDatabaseCount('quote_templates', 0);
    }

    public function test_invalid_accent_colour_is_rejected(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('templates.store'), $this->payload(['accent_color' => 'neon']))
            ->assertSessionHasErrors('accent_color');
    }

    public function test_disallowed_gst_rate_is_rejected(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('templates.store'), $this->payload(['default_gst_rate' => 7]))
            ->assertSessionHasErrors('default_gst_rate');
    }

    public function test_only_one_template_can_be_default(): void
    {
        $admin = $this->admin();
        $first = QuoteTemplate::factory()->default()->create(['created_by' => $admin->id]);
        $second = QuoteTemplate::factory()->create(['is_default' => false, 'created_by' => $admin->id]);

        $this->actingAs($admin)->post(route('templates.set-default', $second));

        $this->assertTrue($second->fresh()->is_default);
        $this->assertFalse($first->fresh()->is_default);
    }

    public function test_creating_a_default_template_unsets_the_previous_default(): void
    {
        $admin = $this->admin();
        $existing = QuoteTemplate::factory()->default()->create(['created_by' => $admin->id]);

        $this->actingAs($admin)
            ->post(route('templates.store'), $this->payload(['is_default' => '1', 'name' => 'New Default']));

        $this->assertFalse($existing->fresh()->is_default);
        $this->assertTrue(QuoteTemplate::where('name', 'New Default')->firstOrFail()->is_default);
    }

    public function test_template_can_be_updated(): void
    {
        $admin = $this->admin();
        $template = QuoteTemplate::factory()->create(['name' => 'Old Name', 'created_by' => $admin->id]);

        $this->actingAs($admin)
            ->put(route('templates.update', $template), $this->payload(['name' => 'Renamed Template']))
            ->assertRedirect();

        $this->assertSame('Renamed Template', $template->fresh()->name);
    }

    public function test_template_can_be_duplicated_without_becoming_default(): void
    {
        $admin = $this->admin();
        $template = QuoteTemplate::factory()->create([
            'name' => 'Original',
            'is_default' => true,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->post(route('templates.duplicate', $template));

        $copy = QuoteTemplate::where('name', 'Original (copy)')->firstOrFail();

        $this->assertFalse($copy->is_default);
        $this->assertTrue($template->fresh()->is_default);
        $this->assertSame($template->company_name, $copy->company_name);
    }

    public function test_template_can_be_previewed(): void
    {
        $admin = $this->admin();
        $template = QuoteTemplate::factory()->create(['created_by' => $admin->id]);

        $this->actingAs($admin)
            ->get(route('templates.show', $template))
            ->assertOk()
            ->assertSee($template->company_name);
    }

    public function test_template_delete_is_reversible(): void
    {
        $admin = $this->admin();
        $template = QuoteTemplate::factory()->create(['created_by' => $admin->id]);

        $this->actingAs($admin)->delete(route('templates.destroy', $template));

        $this->assertSoftDeleted('quote_templates', ['id' => $template->id]);

        $this->actingAs($admin)->post(route('templates.restore', ['template' => $template->id]));

        $this->assertNotNull(QuoteTemplate::find($template->id));
    }

    public function test_trashed_template_is_hidden_from_the_index(): void
    {
        $admin = $this->admin();
        $template = QuoteTemplate::factory()->create(['name' => 'Hidden Template', 'created_by' => $admin->id]);
        $template->delete();

        $this->actingAs($admin)->get(route('templates.index'))->assertOk()->assertDontSee('Hidden Template');
        $this->actingAs($admin)->get(route('templates.trash'))->assertOk()->assertSee('Hidden Template');
    }

    public function test_template_can_be_permanently_deleted_from_trash(): void
    {
        $admin = $this->admin();
        $template = QuoteTemplate::factory()->create(['created_by' => $admin->id]);
        $template->delete();

        $this->actingAs($admin)->delete(route('templates.force-destroy', ['template' => $template->id]));

        $this->assertDatabaseMissing('quote_templates', ['id' => $template->id]);
    }

    public function test_logo_upload_is_stored_on_the_public_disk(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('templates.store'), $this->payload([
            'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
        ]));

        $template = QuoteTemplate::where('name', 'Standard Business Quote')->firstOrFail();

        $this->assertNotNull($template->logo_path);
        Storage::disk('public')->assertExists($template->logo_path);
    }

    public function test_signature_and_company_stamp_uploads_are_stored_and_exposed(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('templates.store'), $this->payload([
            'signature' => UploadedFile::fake()->image('signature.png', 240, 80),
            'company_stamp' => UploadedFile::fake()->image('stamp.jpg', 160, 160),
        ]))->assertRedirect();

        $template = QuoteTemplate::where('name', 'Standard Business Quote')->firstOrFail();

        $this->assertNotNull($template->signature_path);
        $this->assertNotNull($template->company_stamp_path);
        Storage::disk('public')->assertExists($template->signature_path);
        Storage::disk('public')->assertExists($template->company_stamp_path);
        $this->assertStringContainsString('/storage/templates/signatures/', $template->signatureUrl());
        $this->assertStringContainsString('/storage/templates/stamps/', $template->companyStampUrl());
    }

    public function test_rejecting_a_non_image_upload(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('templates.store'), $this->payload([
                'logo' => UploadedFile::fake()->create('payload.php', 10),
            ]))
            ->assertSessionHasErrors('logo');

        $this->assertDatabaseCount('quote_templates', 0);
    }

    public function test_guests_cannot_manage_templates(): void
    {
        $template = QuoteTemplate::factory()->create();

        $this->post(route('templates.store'), $this->payload())->assertRedirect(route('login'));
        $this->put(route('templates.update', $template), $this->payload())->assertRedirect(route('login'));
        $this->delete(route('templates.destroy', $template))->assertRedirect(route('login'));
    }
}
