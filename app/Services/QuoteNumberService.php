<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Gapless, collision-free quote numbering (PRD FR-06, BR-03, AD-4).
 *
 * Format: QT-{year}-{NNNNN}, e.g. QT-2026-00001. Drafts consume a number too,
 * which keeps the register simple to audit. Numbers are never reused.
 */
class QuoteNumberService
{
    public const PREFIX = 'QT-';

    /**
     * Allocate the next number for the given year.
     *
     * The sequence row is locked FOR UPDATE so two concurrent requests cannot
     * receive the same value; the unique index on quotes.quote_number remains
     * the final safety net.
     */
    public function next(?int $year = null): string
    {
        $year ??= (int) now()->year;

        $number = DB::transaction(function () use ($year): int {
            $row = DB::table('quote_sequences')
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                DB::table('quote_sequences')->insert([
                    'year' => $year,
                    'last_number' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return 1;
            }

            $next = (int) $row->last_number + 1;

            DB::table('quote_sequences')
                ->where('year', $year)
                ->update(['last_number' => $next, 'updated_at' => now()]);

            return $next;
        });

        return $this->format($year, $number);
    }

    /**
     * Build the printable number.
     */
    public function format(int $year, int $number): string
    {
        return self::PREFIX.$year.'-'.str_pad((string) $number, 5, '0', STR_PAD_LEFT);
    }
}
