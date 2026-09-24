<?php

namespace App\Policies;

use App\Models\Quote;
use App\Models\User;

/**
 * Quote authorization (rules.md section 6 - no IDOR).
 *
 * Single administrator user (decision #2), so every action is permitted. The
 * policy stays the single enforcement point for when roles are added.
 */
class QuotePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Quote $quote): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Quote $quote): bool
    {
        return true;
    }

    public function delete(User $user, Quote $quote): bool
    {
        return true;
    }

    public function restore(User $user, Quote $quote): bool
    {
        return true;
    }

    public function forceDelete(User $user, Quote $quote): bool
    {
        return true;
    }
}
