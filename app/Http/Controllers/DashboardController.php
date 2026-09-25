<?php

namespace App\Http\Controllers;

use App\Enums\QuoteStatus;
use App\Models\Quote;
use App\Models\QuoteTemplate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Quote management dashboard (quoteflow_dashboard.html).
     *
     * The reference screen is a real quote list, not a mockup: filters and
     * pagination run in the database, and every row action points at the
     * existing quote lifecycle routes.
     */
    public function __invoke(Request $request): View
    {
        $this->authorize('viewAny', Quote::class);

        $search = $request->string('search')->trim()->value();
        $status = $request->string('status')->trim()->value();
        $date = $request->string('date')->trim()->value();

        $query = Quote::query()
            ->with('client')
            ->withCount('items')
            ->search($search);

        if (QuoteStatus::tryFrom($status) !== null) {
            $query->where('status', $status);
        }

        $this->applyDateFilter($query, $date);

        $quotes = $query
            ->latest('quote_date')
            ->latest('id')
            ->paginate(5)
            ->withQueryString();

        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        return view('dashboard', [
            'quotes' => $quotes,
            'templates' => QuoteTemplate::query()
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(),
            'search' => $search,
            'status' => $status,
            'date' => $date,
            'statusOptions' => collect(QuoteStatus::cases())
                ->mapWithKeys(fn (QuoteStatus $status): array => [$status->value => $status->label()])
                ->all(),
            'quoteStats' => [
                'total' => Quote::query()->count(),
                'draft' => Quote::query()->where('status', QuoteStatus::Draft)->count(),
                'sent' => Quote::query()->where('status', QuoteStatus::Sent)->count(),
                'monthValue' => (float) Quote::query()
                    ->whereBetween('quote_date', [$monthStart, $monthEnd])
                    ->sum('grand_total'),
            ],
        ]);
    }

    /**
     * Apply the prototype's relative date filter to a quote query.
     *
     * @param  Builder<Quote>  $query
     */
    private function applyDateFilter(Builder $query, string $date): void
    {
        match ($date) {
            'today' => $query->whereDate('quote_date', now()->toDateString()),
            'week' => $query->whereDate('quote_date', '>=', now()->subDays(6)->toDateString()),
            'month' => $query->whereBetween('quote_date', [now()->startOfMonth(), now()->endOfMonth()]),
            'quarter' => $query->whereDate('quote_date', '>=', now()->subDays(92)->toDateString()),
            default => null,
        };
    }
}
