<?php

namespace App\Services;

/**
 * The single source of truth for every monetary figure (PRD FR-07, BR-02).
 *
 * The backend ALWAYS recomputes; values supplied by the browser are discarded.
 * GST applies to (subtotal - discount) and is added on top (decision #5).
 * Discount is a flat rupee amount (decision #6).
 */
class CalculationService
{
    /** Allowed GST rates (PRD FR-05). */
    public const GST_RATES = [0, 5, 12, 18, 28];

    /**
     * Compute line amounts, subtotal, discount, GST and grand total.
     *
     * @param  array<int, array{description?: string, qty?: mixed, rate?: mixed}>  $items
     * @return array{items: array<int, array<string, mixed>>, subtotal: float, discount: float, gst_rate: float, gst_amount: float, grand_total: float}
     */
    public function compute(array $items, float|int|string $discount = 0, float|int|string $gstRate = 0): array
    {
        $lines = [];
        $subtotal = 0.0;
        $position = 0;

        foreach ($items as $item) {
            // Skip fully blank rows rather than storing empty line items.
            $description = trim((string) ($item['description'] ?? ''));
            $qty = (float) ($item['qty'] ?? 0);
            $rate = (float) ($item['rate'] ?? 0);

            if ($description === '' && $qty === 0.0 && $rate === 0.0) {
                continue;
            }

            $position++;
            $amount = round($qty * $rate, 2);
            $subtotal += $amount;

            $lines[] = [
                'position' => $position,
                'description' => $description === '' ? '—' : $description,
                'qty' => round($qty, 2),
                'rate' => round($rate, 2),
                'amount' => $amount,
            ];
        }

        $subtotal = round($subtotal, 2);

        // A discount can never exceed the subtotal and is never negative.
        $discount = max(0.0, min((float) $discount, $subtotal));
        $discount = round($discount, 2);

        $discounted = round($subtotal - $discount, 2);

        // Compare as floats: a strict int comparison would reject 18.0 and silently
        // zero the GST on every quote.
        $gstRate = (float) $gstRate;
        $gstRate = in_array($gstRate, array_map('floatval', self::GST_RATES), true) ? $gstRate : 0.0;
        $gstAmount = round($discounted * $gstRate / 100, 2);

        $grandTotal = round($discounted + $gstAmount, 2);

        return [
            'items' => $lines,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'gst_rate' => $gstRate,
            'gst_amount' => $gstAmount,
            'grand_total' => $grandTotal,
        ];
    }
}
