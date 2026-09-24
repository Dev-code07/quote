<?php

namespace App\Http\Requests;

/**
 * Update validation for a client.
 *
 * Identical to StoreClientRequest, but declared as its own class so create and
 * update rules can diverge later without touching existing callers
 * (rules.md section 3). Inheriting guarantees they cannot drift apart now.
 */
class UpdateClientRequest extends StoreClientRequest
{
    //
}
