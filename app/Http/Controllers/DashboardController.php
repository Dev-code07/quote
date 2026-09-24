<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Dashboard landing page (PRD FR-02).
     *
     * Quote statistics arrive with Phase 4/6; client figures are live now.
     */
    public function __invoke(): View
    {
        return view('dashboard', [
            'clientCount' => Client::query()->count(),
            'activeClientCount' => Client::query()->where('is_active', true)->count(),
            'recentClients' => Client::query()
                ->latest()
                ->limit(5)
                ->get(),
        ]);
    }
}
