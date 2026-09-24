<?php

namespace App\Support;

/**
 * Indian rupee formatting (PRD FR-14): 1,23,456.78 with the rupee sign.
 */
class Money
{
    /**
     * Format a value for display, e.g. "1,23,456.78".
     */
    public static function format(float|int|string $value, bool $withSymbol = true): string
    {
        $formatted = number_format((float) $value, 2);

        return $withSymbol ? '₹'.$formatted : $formatted;
    }

    /**
     * Format without decimals, e.g. "1,23,457" for chart labels.
     */
    public static function compact(float|int|string $value): string
    {
        return number_format((float) $value, 0);
    }
}
