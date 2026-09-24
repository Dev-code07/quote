<?php

namespace Tests\Unit;

use App\Services\CalculationService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CalculationServiceTest extends TestCase
{
    private CalculationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CalculationService;
    }

    public function test_line_amount_is_quantity_times_rate(): void
    {
        $result = $this->service->compute([
            ['description' => 'Laptop', 'qty' => 5, 'rate' => 72500],
        ]);

        $this->assertSame(362500.0, $result['items'][0]['amount']);
        $this->assertSame(362500.0, $result['subtotal']);
    }

    public function test_gst_is_added_on_top_of_subtotal_minus_discount(): void
    {
        // Decision #5: GST = (subtotal - discount) x rate, added on top.
        $result = $this->service->compute([
            ['description' => 'Item', 'qty' => 2, 'rate' => 500],
        ], discount: 100, gstRate: 18);

        $this->assertSame(1000.0, $result['subtotal']);
        $this->assertSame(100.0, $result['discount']);
        $this->assertSame(162.0, $result['gst_amount']);
        $this->assertSame(1062.0, $result['grand_total']);
    }

    public function test_zero_gst_yields_grand_total_equal_to_discounted_subtotal(): void
    {
        $result = $this->service->compute([
            ['description' => 'Item', 'qty' => 1, 'rate' => 999.99],
        ], discount: 0, gstRate: 0);

        $this->assertSame(999.99, $result['subtotal']);
        $this->assertSame(0.0, $result['gst_amount']);
        $this->assertSame(999.99, $result['grand_total']);
    }

    public function test_discount_cannot_exceed_subtotal(): void
    {
        $result = $this->service->compute([
            ['description' => 'Item', 'qty' => 1, 'rate' => 500],
        ], discount: 999999, gstRate: 18);

        $this->assertSame(500.0, $result['discount']);
        $this->assertSame(0.0, $result['grand_total']);
    }

    public function test_negative_discount_is_clamped_to_zero(): void
    {
        $result = $this->service->compute([
            ['description' => 'Item', 'qty' => 1, 'rate' => 500],
        ], discount: -100, gstRate: 0);

        $this->assertSame(0.0, $result['discount']);
        $this->assertSame(500.0, $result['grand_total']);
    }

    public function test_disallowed_gst_rate_falls_back_to_zero(): void
    {
        $result = $this->service->compute([
            ['description' => 'Item', 'qty' => 1, 'rate' => 1000],
        ], discount: 0, gstRate: 7);

        $this->assertSame(0.0, $result['gst_rate']);
        $this->assertSame(1000.0, $result['grand_total']);
    }

    #[DataProvider('allowedGstRates')]
    public function test_all_allowed_gst_rates_are_accepted(int $rate): void
    {
        $result = $this->service->compute([
            ['description' => 'Item', 'qty' => 1, 'rate' => 1000],
        ], discount: 0, gstRate: $rate);

        $this->assertSame((float) $rate, $result['gst_rate']);
        $this->assertSame((float) (1000 * $rate / 100), $result['gst_amount']);
    }

    public static function allowedGstRates(): array
    {
        return [[0], [5], [12], [18], [28]];
    }

    public function test_amounts_are_rounded_to_two_decimals(): void
    {
        $result = $this->service->compute([
            ['description' => 'Fractional', 'qty' => 3, 'rate' => 33.333],
        ], discount: 0, gstRate: 12);

        $this->assertSame(100.0, $result['subtotal']);
        $this->assertSame(12.0, $result['gst_amount']);
        $this->assertSame(112.0, $result['grand_total']);
    }

    public function test_blank_rows_are_skipped_and_positions_are_sequential(): void
    {
        $result = $this->service->compute([
            ['description' => '', 'qty' => '', 'rate' => ''],
            ['description' => 'Real item', 'qty' => 1, 'rate' => 100],
            ['description' => '', 'qty' => 0, 'rate' => 0],
            ['description' => 'Another', 'qty' => 2, 'rate' => 50],
        ]);

        $this->assertCount(2, $result['items']);
        $this->assertSame(1, $result['items'][0]['position']);
        $this->assertSame(2, $result['items'][1]['position']);
        $this->assertSame(200.0, $result['subtotal']);
    }

    public function test_no_items_yields_zero_totals(): void
    {
        $result = $this->service->compute([], discount: 500, gstRate: 18);

        $this->assertSame([], $result['items']);
        $this->assertSame(0.0, $result['subtotal']);
        $this->assertSame(0.0, $result['discount']);
        $this->assertSame(0.0, $result['grand_total']);
    }
}
