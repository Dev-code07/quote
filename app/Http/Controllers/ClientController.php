<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class ClientController extends Controller
{
    /**
     * Paginated list of clients with search and Active/Inactive filter.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Client::class);

        $search = $request->string('search')->trim()->value();
        $status = $request->string('status')->trim()->value();

        $clients = Client::query()
            ->search($search)
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('clients.index', [
            'clients' => $clients,
            'search' => $search,
            'status' => $status,
            'counts' => [
                'all' => Client::query()->count(),
                'active' => Client::query()->where('is_active', true)->count(),
                'inactive' => Client::query()->where('is_active', false)->count(),
            ],
        ]);
    }

    /**
     * Show a single client with their quote history.
     */
    public function show(Client $client): View
    {
        $this->authorize('view', $client);

        return view('clients.show', [
            'client' => $client,
        ]);
    }

    /**
     * Persist a new client.
     */
    public function store(StoreClientRequest $request): RedirectResponse
    {
        $this->authorize('create', Client::class);

        Client::query()->create([
            ...$request->safe()->only([
                'name', 'contact_person', 'email', 'phone', 'gstin', 'address', 'is_active',
            ]),
            'created_by' => $request->user()->id,
        ]);

        return Redirect::route('clients.index')
            ->with('status', 'Client created successfully.');
    }

    /**
     * Persist changes to an existing client.
     */
    public function update(UpdateClientRequest $request, Client $client): RedirectResponse
    {
        $this->authorize('update', $client);

        $client->update($request->safe()->only([
            'name', 'contact_person', 'email', 'phone', 'gstin', 'address', 'is_active',
        ]));

        return Redirect::route('clients.index')
            ->with('status', 'Client updated successfully.');
    }

    /**
     * Toggle Active/Inactive without losing history.
     */
    public function toggleStatus(Client $client): RedirectResponse
    {
        $this->authorize('update', $client);

        $client->update(['is_active' => ! $client->is_active]);

        return Redirect::route('clients.index')->with(
            'status',
            $client->is_active ? 'Client activated.' : 'Client deactivated.'
        );
    }

    /**
     * Move a client to the trash (decision #8 - always reversible).
     */
    public function destroy(Client $client): RedirectResponse
    {
        $this->authorize('delete', $client);

        $name = $client->name;
        $client->delete();

        return Redirect::route('clients.index')
            ->with('status', "\"{$name}\" moved to trash.");
    }

    /**
     * Restore a client from the trash.
     */
    public function restore(int $client): RedirectResponse
    {
        $model = Client::onlyTrashed()->findOrFail($client);

        $this->authorize('restore', $model);

        $model->restore();

        return Redirect::route('clients.trash')
            ->with('status', "\"{$model->name}\" restored.");
    }

    /**
     * Permanently delete a client from the trash.
     *
     * Blocked while live quotes reference the client (assumption A2). The quotes
     * table is introduced in Phase 4; until then the guard is simply absent and
     * every trashed client can be purged.
     */
    public function forceDestroy(int $client): RedirectResponse
    {
        $model = Client::onlyTrashed()->findOrFail($client);

        $this->authorize('forceDelete', $model);

        if ($this->hasLiveQuotes($model)) {
            return Redirect::route('clients.trash')->with(
                'error',
                'This client has quotes. Archive or move those quotes to trash first.'
            );
        }

        $name = $model->name;
        $model->forceDelete();

        return Redirect::route('clients.trash')
            ->with('status', "\"{$name}\" permanently deleted.");
    }

    /**
     * Trash listing with restore / permanent delete.
     */
    public function trash(): View
    {
        $this->authorize('viewAny', Client::class);

        return view('clients.trash', [
            'clients' => Client::onlyTrashed()->orderBy('name')->paginate(10),
        ]);
    }

    /**
     * Whether live (non-deleted) quotes still reference this client.
     *
     * Guarded so Phase 2 works before the quotes table exists.
     */
    private function hasLiveQuotes(Client $client): bool
    {
        if (! Schema::hasTable('quotes')) {
            return false;
        }

        return DB::table('quotes')
            ->where('client_id', $client->getKey())
            ->whereNull('deleted_at')
            ->exists();
    }
}
