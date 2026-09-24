<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;

/**
 * Client authorization (rules.md section 6 - no IDOR).
 *
 * The MVP has a single administrator user (client decision #2), so every
 * authenticated user may perform every action. The policy is still the single
 * enforcement point, so adding roles later requires no controller changes.
 */
class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Client $client): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Client $client): bool
    {
        return true;
    }

    /**
     * Move the client to trash (decision #8 - delete is reversible).
     */
    public function delete(User $user, Client $client): bool
    {
        return true;
    }

    /**
     * Restore a client from trash.
     */
    public function restore(User $user, Client $client): bool
    {
        return true;
    }

    /**
     * Permanent deletion from the trash view. Additional guards (for example
     * live quotes) live in ClientController::forceDestroy.
     */
    public function forceDelete(User $user, Client $client): bool
    {
        return true;
    }
}
