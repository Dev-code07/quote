<?php

namespace Database\Factories;

use App\Enums\AccentPalette;
use App\Enums\HeaderAlignment;
use App\Models\QuoteTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuoteTemplate>
 */
class QuoteTemplateFactory extends Factory
{
    /**
     * Realistic Indian business data (rules.md section 4).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true).' Template',
            'is_default' => false,
            'accent_color' => fake()->randomElement(AccentPalette::cases()),
            'header_alignment' => HeaderAlignment::Center,
            'doc_title' => 'QUOTATION',
            'company_name' => fake()->company(),
            'company_gstin' => strtoupper(fake()->bothify('??#####?????##?')),
            'tagline' => fake()->sentence(3),
            'address' => fake()->streetAddress().', '.fake()->city().', '.fake()->stateAbbr().' '.fake()->numberBetween(110001, 799999),
            'email' => fake()->unique()->companyEmail(),
            'mobile_1' => fake()->numerify('+91-98###-#####'),
            'mobile_2' => fake()->numerify('0##-#######'),
            'stamp_place' => fake()->city(),
            'default_gst_rate' => fake()->randomElement([0, 5, 12, 18, 28]),
            'delivery_period' => '2-3 weeks from purchase order',
            'warranty' => 'One year (manufacturer)',
            'validity_text' => '15 days from the above date',
            'extra_terms' => 'Payment: 50% advance, balance before delivery.',
            'created_by' => User::factory(),
        ];
    }

    /**
     * The default template.
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }
}
