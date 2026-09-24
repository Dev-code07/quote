<?php

namespace App\Policies;

use App\Models\QuoteTemplate;
use App\Models\User;

/**
 * Template authorization (rules.md section 6 - no IDOR).
 *
 * Single administrator user (decision #2), so every action is permitted. The
 * policy remains the single enforcement point for when roles are added.
 */
class QuoteTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, QuoteTemplate $quoteTemplate): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, QuoteTemplate $quoteTemplate): bool
    {
        return true;
    }

    public function delete(User $user, QuoteTemplate $quoteTemplate): bool
    {
        return true;
    }

    public function restore(User $user, QuoteTemplate $quoteTemplate): bool
    {
        return true;
    }

    public function forceDelete(User $user, QuoteTemplate $quoteTemplate): bool
    {
        return true;
    }
}
