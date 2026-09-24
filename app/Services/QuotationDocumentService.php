<?php

namespace App\Services;

use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\QuoteTemplate;
use App\Support\Money;
use App\Support\TemplateTokens;
use Carbon\CarbonInterface;

/**
 * Normalises a template (or quote) into the `$doc` array consumed by the
 * x-a4-sheet component and the PDF template.
 *
 * Keeping this in one place means the on-screen preview and the PDF can never
 * disagree about what a quotation looks like (Architecture.md 5.4).
 */
class QuotationDocumentService
{
    public function __construct(private readonly SnapshotService $accents) {}

    /**
     * Build a preview document from a template, using sample client/items.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public function fromTemplate(QuoteTemplate $template, array $overrides = []): array
    {
        $today = now();

        $items = [
            ['position' => 1, 'description' => 'Business laptop — Core i5, 16 GB RAM, 512 GB SSD, 14" FHD', 'qty' => 5, 'rate' => 72500, 'amount' => 362500],
            ['position' => 2, 'description' => 'Software licence (per user, annual)', 'qty' => 25, 'rate' => 10500, 'amount' => 262500],
            ['position' => 3, 'description' => 'Installation, configuration &amp; user onboarding', 'qty' => 1, 'rate' => 12000, 'amount' => 12000],
        ];

        $subtotal = array_sum(array_column($items, 'amount'));
        $gstRate = (float) $template->default_gst_rate;
        $gst = round($subtotal * $gstRate / 100, 2);

        $doc = [
            'accent' => $template->inkColours(),
            'align' => $template->header_alignment?->css() ?? 'center',
            'doc_title' => $template->doc_title,
            'company' => [
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
            ],
            'client' => [
                'name' => 'Sample Client Pvt. Ltd.',
                'address' => 'Plot 14, Sector 18, Noida (U.P.)',
                'gstin' => '27AAACR5678E1ZT',
                'email' => 'purchase@sampleclient.in',
                'phone' => '+91-98110-22334',
            ],
            'meta' => [
                'no' => 'QT-'.$today->format('Y').'-00001',
                'date' => $today->format('d M Y'),
                'valid' => $today->copy()->addDays(15)->format('d M Y'),
                'enquiry_no' => 'ENQ/2026/0142',
                'enquiry_date' => $today->copy()->subDays(3)->format('d M Y'),
            ],
            'items' => $items,
            'totals' => [
                'subtotal' => $subtotal,
                'discount' => 0,
                'gst_rate' => $gstRate,
                'gst_amount' => $gst,
                'grand_total' => $subtotal + $gst,
                'words' => '',
            ],
            'terms' => [
                'intro' => TemplateTokens::render(
                    $template->intro_message,
                    'Sample Client Pvt. Ltd.',
                    'ENQ/2026/0142',
                    $today->copy()->subDays(3)
                ),
                'delivery' => $template->delivery_period,
                'warranty' => $template->warranty,
                'validity' => $template->validity_text,
                'extra' => TemplateTokens::lines($template->extra_terms),
                'notes' => $template->notes,
            ],
        ];

        $doc['totals']['words'] = app(AmountInWordsService::class)->convert($doc['totals']['grand_total']);

        return array_replace_recursive($doc, $overrides);
    }

    /**
     * Format a date the way the A4 document shows it.
     */
    /**
     * Build the printable document for a saved quote.
     *
     * Reads ONLY the quote's own columns and snapshots, never the live client
     * or template (BR-01).
     *
     * @return array<string, mixed>
     */
    public function fromQuote(Quote $quote): array
    {
        $template = $quote->template_snapshot ?? [];
        $client = $quote->client_snapshot ?? [];
        $terms = $quote->terms ?? [];

        $items = $quote->items->map(fn (QuoteItem $item): array => [
            'position' => $item->position,
            'description' => $item->description,
            'qty' => (float) $item->quantity,
            'rate' => (float) $item->rate,
            'amount' => (float) $item->amount,
        ])->all();

        return [
            'accent' => $this->accents->accent($template),
            'align' => $template['header_alignment'] ?? 'center',
            'doc_title' => $template['doc_title'] ?? 'QUOTATION',
            'company' => [
                'name' => $template['name'] ?? '',
                'display_name' => $template['display_name'] ?? ($template['name'] ?? ''),
                'company_gstin' => $template['company_gstin'] ?? null,
                'tagline' => $template['tagline'] ?? null,
                'address' => $template['address'] ?? null,
                'email' => $template['email'] ?? null,
                'mobile_1' => $template['mobile_1'] ?? null,
                'mobile_2' => $template['mobile_2'] ?? null,
                'stamp_place' => $template['stamp_place'] ?? null,
                'logo_url' => $template['logo_url'] ?? null,
                'signature_url' => $template['signature_url'] ?? null,
                'authorized_person' => $template['authorized_person'] ?? null,
                'designation' => $template['designation'] ?? null,
            ],
            'client' => [
                'name' => $client['name'] ?? '—',
                'address' => $client['address'] ?? null,
                'gstin' => $client['gstin'] ?? null,
                'email' => $client['email'] ?? null,
                'phone' => $client['phone'] ?? null,
            ],
            'meta' => [
                'no' => $quote->quote_number,
                'date' => $this->date($quote->quote_date),
                'valid' => $this->date($quote->valid_until),
                'enquiry_no' => $quote->enquiry_no,
                'enquiry_date' => $this->date($quote->enquiry_date),
            ],
            'items' => $items,
            'totals' => [
                'subtotal' => (float) $quote->subtotal,
                'discount' => (float) $quote->discount_amount,
                'gst_rate' => (float) $quote->gst_rate,
                'gst_amount' => (float) $quote->gst_amount,
                'grand_total' => (float) $quote->grand_total,
                'words' => $quote->amount_in_words,
            ],
            'terms' => [
                'intro' => $terms['intro'] ?? '',
                'delivery' => $terms['delivery'] ?? null,
                'warranty' => $terms['warranty'] ?? null,
                'validity' => $terms['validity'] ?? null,
                'extra' => $terms['extra'] ?? [],
                'notes' => $terms['notes'] ?? null,
            ],
        ];
    }

    public function date(?CarbonInterface $date): string
    {
        return $date?->format('d M Y') ?? '—';
    }

    /**
     * Rupee-formatted value for display in summaries.
     */
    public function money(float $value): string
    {
        return Money::format($value);
    }
}
