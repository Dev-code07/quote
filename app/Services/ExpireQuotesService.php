<?php

namespace App\Services;

use App\Enums\QuoteStatus;
use App\Models\Quote;

/**
 * Marks past-validity quotations as Expired (PRD FR-09, decision #7).
 *
 * Approved quotations are never auto-expired (assumption A6), and Expired is
 * never set by a user (BR-05).
 */
class ExpireQuotesService
{
    /**
     * @return int Number of quotations expired.
     */
    public function run(): int
    {
        return Quote::query()
            ->whereNull('deleted_at')
            ->whereDate('valid_until', '<', now()->toDateString())
            ->whereIn('status', [QuoteStatus::Draft->value, QuoteStatus::Sent->value])
            ->update(['status' => QuoteStatus::Expired->value]);
    }
}
