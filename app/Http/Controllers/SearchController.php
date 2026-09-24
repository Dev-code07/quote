<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Quote;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Global search behind the topbar search box.
 *
 * Reuses the per-model scopeSearch() so the shell search and the Clients /
 * Quotes list filters can never drift apart in what they match.
 */
class SearchController extends Controller
{
    private const LIMIT = 25;

    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q', ''));

        $quotes = $term === ''
            ? Quote::query()->whereRaw('1 = 0')->get()
            : Quote::query()
                ->search($term)
                ->with('client')
                ->orderByDesc('quote_date')
                ->orderByDesc('id')
                ->limit(self::LIMIT)
                ->get();

        $clients = $term === ''
            ? Client::query()->whereRaw('1 = 0')->get()
            : Client::query()
                ->search($term)
                ->orderBy('name')
                ->limit(self::LIMIT)
                ->get();

        return view('search.index', [
            'term' => $term,
            'quotes' => $quotes,
            'clients' => $clients,
            'total' => $quotes->count() + $clients->count(),
        ]);
    }
}
