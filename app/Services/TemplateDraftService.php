<?php

namespace App\Services;

use App\Enums\AccentPalette;
use App\Enums\HeaderAlignment;
use App\Models\QuoteTemplate;

/**
 * Builds an unsaved QuoteTemplate from the template editor's form input.
 *
 * The live preview in the editor has to reflect every field as it is typed, and
 * the only way to do that without forking the A4 markup into JavaScript is to
 * re-render the real component. This service turns posted form values back into
 * a template-shaped model so QuotationDocumentService can build the document
 * exactly as it does for a saved quote (Architecture.md 5.4).
 *
 * Nothing here is persisted: the preview endpoint never writes to the database.
 */
class TemplateDraftService
{
    /**
     * Every column the editor form owns.
     *
     * Kept as one list so the form, the preview and the store path cannot drift
     * apart; TemplatePreviewTest asserts each name is honoured here.
     *
     * @var list<string>
     */
    public const EDITOR_FIELDS = [
        'name', 'is_default', 'accent_color', 'header_alignment',
        'letterhead_display_name', 'doc_title', 'company_name', 'company_gstin',
        'tagline', 'address', 'email', 'mobile_1', 'mobile_2', 'stamp_place',
        'authorized_person', 'designation', 'default_gst_rate', 'intro_message',
        'delivery_period', 'warranty', 'validity_text', 'extra_terms', 'notes',
        'use_generated_seal',
    ];

    /**
     * Hydrate an unsaved template from raw editor input.
     *
     * @param  array<string, mixed>  $input
     */
    public function fromInput(array $input, ?QuoteTemplate $existing = null): QuoteTemplate
    {
        $draft = $existing ? $existing->replicate() : new QuoteTemplate;

        $value = function (string $key) use ($input) {
            $raw = $input[$key] ?? null;

            return is_string($raw) ? trim($raw) : $raw;
        };

        foreach (self::EDITOR_FIELDS as $field) {
            if ($field === 'accent_color' || $field === 'header_alignment' || $field === 'is_default') {
                continue;
            }

            if ($field === 'use_generated_seal') {
                // An unchecked checkbox is simply absent from the payload.
                $draft->{$field} = filter_var($input[$field] ?? false, FILTER_VALIDATE_BOOLEAN);

                continue;
            }

            if ($field === 'default_gst_rate') {
                $draft->{$field} = is_numeric($value($field)) ? (float) $value($field) : 18.0;

                continue;
            }

            $draft->{$field} = $value($field) ?: null;
        }

        // Enum casts reject unknown values, and the preview must survive a
        // half-typed or hand-crafted payload, so fall back to the defaults.
        $draft->accent_color = AccentPalette::tryFrom((string) $value('accent_color'))
            ?? AccentPalette::default();
        $draft->header_alignment = HeaderAlignment::tryFrom((string) $value('header_alignment'))
            ?? HeaderAlignment::default();
        $draft->is_default = filter_var($input['is_default'] ?? false, FILTER_VALIDATE_BOOLEAN);

        // Branding files are only ever replaced on save. The preview keeps
        // showing the current stored files, unless the editor has explicitly
        // requested that one be removed.
        $draft->logo_path = $this->pathAfterRemoval($input, 'remove_logo', $existing?->logo_path);
        $draft->signature_path = $this->pathAfterRemoval($input, 'remove_signature', $existing?->signature_path);
        $draft->company_stamp_path = $this->pathAfterRemoval($input, 'remove_company_stamp', $existing?->company_stamp_path);

        return $draft;
    }

    /**
     * Apply a remove-file flag without teaching the draft service about the
     * actual file inputs. Newly selected files are overlaid locally by Alpine
     * and are persisted only when the editor form is saved.
     *
     * @param  array<string, mixed>  $input
     */
    private function pathAfterRemoval(array $input, string $flag, ?string $path): ?string
    {
        return filter_var($input[$flag] ?? false, FILTER_VALIDATE_BOOLEAN) ? null : $path;
    }
}
