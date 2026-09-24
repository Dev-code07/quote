<?php

namespace Database\Factories;

use App\Models\Quote;
use App\Models\QuoteItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuoteItem>
 */
class QuoteItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $qty = fake()->numberBetween(1, 10);
        $rate = fake()->numberBetween(100, 50000);

        return [
            'quote_id' => Quote::factory(),
            'position' => 1,
            'description' => fake()->sentence(4),
            'qty' => $qty,
            'rate' => $rate,
            'amount' => $qty * $rate,
        ];
    }
}
