<?php

namespace App\Http\Controllers;

use App\Enums\QuoteStatus;
use App\Models\Client;
use App\Models\Quote;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Dashboard landing page (PRD FR-02).
     */
    public function __invoke(): View
    {
        return view('dashboard', [
            'clientCount' => Client::query()->count(),
            'activeClientCount' => Client::query()->where('is_active', true)->count(),
            'recentClients' => Client::query()->latest()->limit(5)->get(),
            'recentQuotes' => Quote::query()->withCount('items')->latest('id')->limit(10)->get(),
            'quoteStats' => [
                'total' => Quote::query()->count(),
                'draft' => Quote::query()->where('status', QuoteStatus::Draft)->count(),
                'sent' => Quote::query()->where('status', QuoteStatus::Sent)->count(),
                'approved' => Quote::query()->where('status', QuoteStatus::Approved)->count(),
                'expired' => Quote::query()->where('status', QuoteStatus::Expired)->count(),
                'value' => (float) Quote::query()->sum('grand_total'),
            ],
        ]);
    }
}
