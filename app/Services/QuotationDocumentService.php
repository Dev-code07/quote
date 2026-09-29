<?php

namespace App\Services;

use App\Models\Client;
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

        // Sample rows are copied from the client prototype (SAMPLE.items) so the
        // preview shows exactly the layout that was signed off.
        $items = [
            ['position' => 1, 'description' => 'Desktop PC — Core i5, 8 GB RAM, 512 GB SSD, 19.5" monitor', 'qty' => 8, 'rate' => 54900, 'amount' => 439200],
            ['position' => 2, 'description' => 'Ink tank colour printer — print / scan / copy', 'qty' => 2, 'rate' => 14500, 'amount' => 29000],
            ['position' => 3, 'description' => 'Line-interactive UPS — 1 kVA', 'qty' => 8, 'rate' => 5200, 'amount' => 41600],
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
                'stamp_url' => $template->companyStampUrl(),
                'generated_seal' => (bool) $template->use_generated_seal,
                'authorized_person' => $template->authorized_person,
                'designation' => $template->designation,
            ],
            'client' => [
                'name' => 'Govt. Senior Secondary School',
                'address' => 'Sanjauli, Shimla (H.P.)',
                'gstin' => null,
                'email' => null,
                'phone' => null,
            ],
            'meta' => [
                'no' => 'QT-'.$today->format('Y').'-00001',
                'date' => $today->format('d/m/Y'),
                'valid' => $today->copy()->addDays(10)->format('d/m/Y'),
                'enquiry_no' => 'ENQ/2026/0142',
                'enquiry_date' => $today->copy()->subDays(3)->format('d/m/Y'),
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
                // Tokens are deliberately left unresolved here. A template has no
                // enquiry of its own, so the sheet draws {enquiry_no} and
                // {enquiry_date} as blank fill-ins and {client_name} as sample
                // data (see a4-sheet.blade.php). Quotes are resolved once, when
                // the snapshot is taken, so the printed quote shows real values.
                'intro' => $template->intro_message,
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
                // Absent on quotes created before company stamps existed; fall
                // back to the old rule (a seal whenever a stamp city was set).
                'stamp_url' => $template['stamp_url'] ?? null,
                'generated_seal' => $template['generated_seal'] ?? ! empty($template['stamp_place']),
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

    /**
     * Preview doc from UNSAVED builder input (live overlay). Lenient:
     * never validates, never 422s, never mutates. Half-typed rows render.
     */
    public function fromUnsavedInput(QuoteTemplate $template, ?Client $client, array $input, string $number): array
    {
        $totals = app(CalculationService::class)->compute(
            $this->previewLineItems($input['items'] ?? []),
            $input['discount_amount'] ?? 0,
            $input['gst_rate'] ?? $template->default_gst_rate,
        );

        $name = $client?->name;
        if (! is_string($name) || trim($name) === '') {
            $typed = $input['client_name'] ?? null;
            $name = is_string($typed) && trim($typed) !== '' ? trim($typed) : '—';
        }

        $enquiryCarbon = null;
        if (is_string($input['enquiry_date'] ?? null) && trim($input['enquiry_date']) !== '') {
            try {
                $enquiryCarbon = \Carbon\Carbon::parse($input['enquiry_date']);
            } catch (\Throwable) {
                $enquiryCarbon = null;
            }
        }

        $introRaw = is_string($input['terms']['intro'] ?? null) && trim($input['terms']['intro']) !== ''
            ? $input['terms']['intro']
            : $template->intro_message;

        $extraRaw = $input['terms']['extra'] ?? $template->extra_terms;
        $extra = is_array($extraRaw)
            ? array_values(array_filter(array_map('trim', $extraRaw), fn ($l) => $l !== ''))
            : TemplateTokens::lines(is_string($extraRaw) ? $extraRaw : null);

        $title = is_string($input['doc_title'] ?? null) && trim($input['doc_title']) !== ''
            ? trim($input['doc_title'])
            : ($template->doc_title ?? 'QUOTATION');

        return [
            'accent' => $template->inkColours(),
            'align' => $template->header_alignment?->css() ?? 'center',
            'doc_title' => $title,
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
                'stamp_url' => $template->companyStampUrl(),
                'generated_seal' => (bool) $template->use_generated_seal,
                'authorized_person' => $template->authorized_person,
                'designation' => $template->designation,
            ],
            'client' => [
                'name' => $name,
                'address' => $client?->address,
                'gstin' => $client?->gstin,
                'email' => $client?->email,
                'phone' => $client?->phone,
            ],
            'meta' => [
                'no' => $number,
                'date' => $this->previewDate($input['quote_date'] ?? null) ?? now()->format('d/m/Y'),
                'valid' => $this->previewDate($input['valid_until'] ?? null) ?? '—',
                'enquiry_no' => is_string($input['enquiry_no'] ?? null) ? $input['enquiry_no'] : null,
                'enquiry_date' => $this->previewDate($input['enquiry_date'] ?? null) ?? '—',
            ],
            'items' => $totals['items'],
            'totals' => [
                'subtotal' => $totals['subtotal'],
                'discount' => $totals['discount'],
                'gst_rate' => $totals['gst_rate'],
                'gst_amount' => $totals['gst_amount'],
                'grand_total' => $totals['grand_total'],
                'words' => app(AmountInWordsService::class)->convert($totals['grand_total']),
            ],
            'terms' => [
                'intro' => TemplateTokens::render($introRaw, $name === '—' ? null : $name, is_string($input['enquiry_no'] ?? null) ? $input['enquiry_no'] : null, $enquiryCarbon),
                'delivery' => $this->previewString($input['terms']['delivery'] ?? null) ?? $template->delivery_period,
                'warranty' => $this->previewString($input['terms']['warranty'] ?? null) ?? $template->warranty,
                'validity' => $this->previewString($input['terms']['validity'] ?? null) ?? $template->validity_text,
                'extra' => $extra,
                'notes' => $this->previewString($input['terms']['notes'] ?? null) ?? $template->notes,
            ],
        ];
    }

    private function previewLineItems(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return array_values(array_map(fn (mixed $row): array => [
            'description' => is_array($row) ? (string) ($row['description'] ?? '') : '',
            'qty' => is_array($row) ? ($row['qty'] ?? 0) : 0,
            'rate' => is_array($row) ? ($row['rate'] ?? 0) : 0,
        ], $items));
    }

    private function previewDate(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($value)->format('d/m/Y');
        } catch (\Throwable) {
            return null;
        }
    }

    private function previewString(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return $value;
    }

    /**
     * Format a date the way the A4 document shows it.
     */
    public function date(?CarbonInterface $date): string
    {
        return $date?->format('d/m/Y') ?? '—';
    }

    /**
     * Rupee-formatted value for display in summaries.
     */
    public function money(float $value): string
    {
        return Money::format($value);
    }
}