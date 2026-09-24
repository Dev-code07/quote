<?php

namespace Database\Factories;

use App\Enums\QuoteStatus;
use App\Models\Client;
use App\Models\Quote;
use App\Models\QuoteTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quote>
 */
class QuoteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quoteDate = now()->startOfDay();

        return [
            'quote_number' => 'QT-'.$quoteDate->format('Y').'-'.str_pad((string) fake()->unique()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            'quote_date' => $quoteDate,
            'valid_until' => $quoteDate->copy()->addDays(15),
            'status' => QuoteStatus::Draft,
            'client_id' => Client::factory(),
            'template_id' => QuoteTemplate::factory(),
            'client_snapshot' => ['name' => fake()->company()],
            'template_snapshot' => ['name' => fake()->company(), 'accent_color' => 'navy', 'doc_title' => 'QUOTATION'],
            'terms' => ['intro' => 'Sample intro', 'extra' => []],
            'subtotal' => 0,
            'discount_amount' => 0,
            'gst_rate' => 18,
            'gst_amount' => 0,
            'grand_total' => 0,
            'created_by' => User::factory(),
        ];
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes) => ['status' => QuoteStatus::Sent]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => ['status' => QuoteStatus::Approved]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => QuoteStatus::Expired,
            'valid_until' => now()->subDay(),
        ]);
    }
}
