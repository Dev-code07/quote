<?php

namespace App\Services;

use App\Enums\AccentPalette;
use App\Models\Client;
use App\Models\Quote;
use App\Models\QuoteTemplate;
use App\Support\TemplateTokens;

/**
 * Copies client, letterhead and terms onto the quote itself (PRD FR-08, BR-01).
 *
 * After a quote is saved it renders exclusively from its own snapshot columns,
 * so editing or deleting the client or template can never alter history.
 */
class SnapshotService
{
    /**
     * Build the client snapshot.
     *
     * @return array<string, mixed>
     */
    public function client(Client $client): array
    {
        return [
            'name' => $client->name,
            'contact_person' => $client->contact_person,
            'email' => $client->email,
            'phone' => $client->phone,
            'gstin' => $client->gstin,
            'address' => $client->address,
        ];
    }

    /**
     * Build the letterhead snapshot from a template.
     *
     * Image URLs are resolved now so the quote still prints correctly if the
     * template (or its uploads) are later removed.
     *
     * @return array<string, mixed>
     */
    public function template(QuoteTemplate $template): array
    {
        return [
            'name' => $template->company_name,
            'display_name' => $template->displayName(),
            'company_gstin' => $template->company_gstin,
            'tagline' => $template->tagline,
            'address' => $template->address,
            'email' => $template->email,
            'mobile_1' => $template->mobile_1,
            'mobile_2' => $template->mobile_2,
            'stamp_place' => $template->stamp_place,
            'logo_url' => $template->logoUrl(),
            'signature_url' => $template->signatureUrl(),
            'authorized_person' => $template->authorized_person,
            'designation' => $template->designation,
            'accent_color' => $template->accent_color?->value,
            'header_alignment' => $template->header_alignment?->value,
            'doc_title' => $template->doc_title,
        ];
    }

    /**
     * Build the terms snapshot, resolving intro-message tokens.
     *
     * @param  array<string, mixed>  $overrides  Per-quote edits from the builder.
     * @return array<string, mixed>
     */
    public function terms(QuoteTemplate $template, Quote $quote, array $overrides = []): array
    {
        $client = $quote->client_snapshot ?? [];

        $intro = TemplateTokens::render(
            $overrides['intro'] ?? $template->intro_message,
            $client['name'] ?? null,
            $quote->enquiry_no,
            $quote->enquiry_date
        );

        return [
            'intro' => $intro,
            'delivery' => $overrides['delivery'] ?? $template->delivery_period,
            'warranty' => $overrides['warranty'] ?? $template->warranty,
            'validity' => $overrides['validity'] ?? $template->validity_text,
            'extra' => TemplateTokens::lines($overrides['extra'] ?? $template->extra_terms),
            'notes' => $overrides['notes'] ?? $template->notes,
        ];
    }

    /**
     * Render the accent palette for a snapshot (Architecture.md 5.4).
     *
     * @param  array<string, mixed>  $templateSnapshot
     * @return array{ink: string, ink2: string, soft: string, line: string}
     */
    public function accent(array $templateSnapshot): array
    {
        $palette = AccentPalette::tryFrom((string) ($templateSnapshot['accent_color'] ?? 'navy'))
            ?? AccentPalette::default();

        return $palette->colours();
    }
}
