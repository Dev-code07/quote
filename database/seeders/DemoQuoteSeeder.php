<?php

namespace Database\Seeders;

use App\Enums\QuoteStatus;
use App\Models\Client;
use App\Models\Quote;
use App\Models\QuoteTemplate;
use App\Models\User;
use App\Services\AmountInWordsService;
use App\Services\CalculationService;
use App\Services\QuoteNumberService;
use App\Services\SnapshotService;
use Illuminate\Database\Seeder;

/**
 * Realistic demo quotations so the dashboard and reports are not empty.
 */
class DemoQuoteSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->firstOrFail();
        $template = QuoteTemplate::query()->orderByDesc('is_default')->first()
            ?? QuoteTemplate::query()->first();

        if (! $template) {
            $this->command?->warn('No templates found; run DemoTemplateSeeder first.');

            return;
        }

        $calculator = app(CalculationService::class);
        $numbers = app(QuoteNumberService::class);
        $snapshots = app(SnapshotService::class);
        $words = app(AmountInWordsService::class);

        $clients = Client::query()->orderBy('id')->get();

        if ($clients->isEmpty()) {
            $this->command?->warn('No clients found; run DemoClientSeeder first.');

            return;
        }

        // Line items must use the associative shape CalculationService expects.
        $line = fn (string $description, float $qty, float $rate): array => [
            'description' => $description,
            'qty' => $qty,
            'rate' => $rate,
        ];

        $quotes = [
            [$clients[0], QuoteStatus::Sent, 0, '18', [
                $line('Business laptop - Core i5, 16 GB RAM, 512 GB SSD', 5, 72500),
                $line('Microsoft 365 Business Standard (per user, annual)', 25, 10500),
                $line('Next-gen firewall appliance with 1-year UTM subscription', 1, 64000),
                $line('Installation, configuration & user onboarding', 1, 12000),
            ]],
            [$clients[1] ?? $clients[0], QuoteStatus::Approved, 0, '18', [
                $line('Managed annual support contract', 1, 185000),
            ]],
            [$clients[2] ?? $clients[0], QuoteStatus::Draft, 5000, '12', [
                $line('LED panel board 2x4 ft', 12, 3400),
                $line('Installation and wiring', 1, 8500),
            ]],
            [$clients[3] ?? $clients[0], QuoteStatus::Expired, 2000, '18', [
                $line('Electrical wiring accessories - lot', 1, 46000),
            ]],
        ];

        foreach ($quotes as [$client, $status, $discount, $gstRate, $items]) {
            $totals = $calculator->compute($items, $discount, (float) $gstRate);
            $quoteDate = now()->subDays(random_int(1, 20))->startOfDay();
            $validUntil = $status === QuoteStatus::Expired
                ? now()->subDay()
                : $quoteDate->copy()->addDays(15);

            $quote = new Quote([
                'quote_number' => $numbers->next((int) $quoteDate->format('Y')),
                'quote_date' => $quoteDate,
                'valid_until' => $validUntil,
                'enquiry_no' => 'ENQ/'.$quoteDate->format('Y').'/'.str_pad((string) random_int(100, 999), 4, '0', STR_PAD_LEFT),
                'enquiry_date' => $quoteDate->copy()->subDays(2),
                'status' => $status,
                'client_id' => $client->id,
                'template_id' => $template->id,
                'subtotal' => $totals['subtotal'],
                'discount_amount' => $totals['discount'],
                'gst_rate' => $totals['gst_rate'],
                'gst_amount' => $totals['gst_amount'],
                'grand_total' => $totals['grand_total'],
                'amount_in_words' => $words->convert($totals['grand_total']),
                'created_by' => $admin->id,
            ]);

            $quote->client_snapshot = $snapshots->client($client);
            $quote->template_snapshot = $snapshots->template($template);
            $quote->terms = $snapshots->terms($template, $quote);
            $quote->save();

            foreach ($totals['items'] as $line) {
                $quote->items()->create([
                    'position' => $line['position'],
                    'description' => $line['description'],
                    'quantity' => $line['qty'],
                    'rate' => $line['rate'],
                    'amount' => $line['amount'],
                ]);
            }
        }
    }
}
