<?php

namespace App\Http\Controllers;

use App\Enums\QuoteStatus;
use App\Http\Requests\SaveQuoteRequest;
use App\Models\Client;
use App\Models\Quote;
use App\Models\QuoteTemplate;
use App\Services\AmountInWordsService;
use App\Services\CalculationService;
use App\Services\QuotationDocumentService;
use App\Services\QuoteNumberService;
use App\Services\SnapshotService;
use App\Support\Money;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class QuoteController extends Controller
{
    public function __construct(
        private readonly CalculationService $calculator,
        private readonly QuoteNumberService $numbers,
        private readonly SnapshotService $snapshots,
        private readonly AmountInWordsService $words,
        private readonly QuotationDocumentService $documents,
    ) {}

    /**
     * Paginated list with search, status filter and date range (PRD FR-10).
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Quote::class);

        $search = $request->string('search')->trim()->value();
        $status = $request->string('status')->trim()->value();
        $from = $request->date('from');
        $to = $request->date('to');

        $quotes = Quote::query()
            ->search($search)
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($from, fn ($query) => $query->whereDate('quote_date', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('quote_date', '<=', $to))
            ->withCount('items')
            ->latest('quote_date')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return view('quotes.index', [
            'quotes' => $quotes,
            'search' => $search,
            'status' => $status,
            'from' => $from?->format('Y-m-d'),
            'to' => $to?->format('Y-m-d'),
            'statusOptions' => QuoteStatus::selectableOptions(),
            'stats' => $this->stats(),
        ]);
    }

    /**
     * Step 1 of the builder: choose a template (quoteflow_dashboard overlay).
     */
    public function create(Request $request): View
    {
        $this->authorize('create', Quote::class);

        $templateId = $request->integer('template_id');

        return view('quotes.create', [
            'templates' => QuoteTemplate::query()
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(),
            'selected' => $templateId > 0 ? QuoteTemplate::find($templateId) : null,
        ]);
    }

    /**
     * The builder itself, pre-filled from the chosen template.
     */
    public function build(Request $request, ?Quote $quote = null): View
    {
        if ($quote) {
            $this->authorize('update', $quote);
        } else {
            $this->authorize('create', Quote::class);
        }

        $template = $quote?->template
            ?? QuoteTemplate::find($request->integer('template_id'))
            ?? QuoteTemplate::query()->default()->first()
            ?? QuoteTemplate::query()->orderBy('name')->first();

        if (! $template) {
            return Redirect::route('templates.create')
                ->with('error', 'Create a quotation template before creating quotations.');
        }

        $templates = QuoteTemplate::query()->orderByDesc('is_default')->orderBy('name')->get();

        return view('quotes.form', [
            'quote' => $quote ?? new Quote,
            'template' => $template,
            'templates' => $templates,
            'clients' => Client::query()->active()->orderBy('name')->get(),
            'nextNumber' => $quote
                ? $quote->quote_number
                : $this->numbers->format(now()->year, $this->peekNextNumber()),
            'builderSeed' => $this->builderSeed($quote, $template, $templates),
        ]);
    }

    /**
     * Edit an existing quote. Shares the builder with build().
     */
    public function edit(Quote $quote): View
    {
        return $this->build(request(), $quote);
    }

    /**
     * Persist a new quote. Totals are always recomputed (BR-02).
     */
    public function store(SaveQuoteRequest $request): RedirectResponse
    {
        $this->authorize('create', Quote::class);

        $quote = DB::transaction(function () use ($request) {
            return $this->persist(null, $request);
        });

        return Redirect::route('quotes.show', $quote)
            ->with('status', "Quotation {$quote->quote_number} saved.");
    }

    /**
     * Update an existing quote. The template may only be swapped while Draft
     * (assumption A1); the new template is re-snapshotted.
     */
    public function update(SaveQuoteRequest $request, Quote $quote): RedirectResponse
    {
        $this->authorize('update', $quote);

        $requestedTemplate = (int) $request->validated('template_id');
        $templateChanged = $requestedTemplate !== (int) $quote->template_id;

        if ($templateChanged && $quote->status !== QuoteStatus::Draft) {
            return Redirect::route('quotes.show', $quote)->with(
                'error',
                'The template cannot be changed once a quotation is no longer a draft. Duplicate it instead.'
            );
        }

        DB::transaction(function () use ($request, $quote): void {
            $this->persist($quote, $request);
        });

        return Redirect::route('quotes.show', $quote)
            ->with('status', "Quotation {$quote->quote_number} updated.");
    }

    /**
     * Full quote view with the A4 preview.
     */
    public function show(Quote $quote): View
    {
        $this->authorize('view', $quote);

        $quote->load('items');

        return view('quotes.show', [
            'quote' => $quote,
            'doc' => $this->documents->fromQuote($quote),
        ]);
    }

    /**
     * Live totals for the builder. Same service that saves the quote, so the
     * on-screen figure can never diverge from the stored one.
     */
    public function calculate(SaveQuoteRequest $request): JsonResponse
    {
        $totals = $this->calculator->compute(
            $request->lineItems(),
            $request->validated('discount_amount') ?? 0,
            $request->validated('gst_rate'),
        );

        return response()->json([
            ...$totals,
            'subtotal_label' => Money::format($totals['subtotal']),
            'discount_label' => Money::format($totals['discount']),
            'gst_label' => Money::format($totals['gst_amount']),
            'grand_total_label' => Money::format($totals['grand_total']),
            'words' => $this->words->convert($totals['grand_total']),
        ]);
    }

    /**
     * Move between Draft, Sent and Approved. Expired is never set here (BR-05).
     */
    public function updateStatus(Request $request, Quote $quote): RedirectResponse
    {
        $this->authorize('update', $quote);

        $validated = $request->validate([
            'status' => ['required', 'string'],
        ]);

        $target = QuoteStatus::tryFrom($validated['status']);

        if ($target === null) {
            return Redirect::route('quotes.show', $quote)->with('error', 'Unknown status.');
        }

        if ($target === QuoteStatus::Expired) {
            return Redirect::route('quotes.show', $quote)->with(
                'error',
                'Expired is set automatically once the valid-until date passes.'
            );
        }

        if (! $quote->status->canTransitionTo($target)) {
            return Redirect::route('quotes.show', $quote)
                ->with('error', "A quotation cannot move from {$quote->status->label()} to {$target->label()}.");
        }

        $quote->update(['status' => $target]);

        return Redirect::route('quotes.show', $quote)
            ->with('status', "Status updated to {$target->label()}.");
    }

    /**
     * Copy a quote: new number, Draft, today's date (PRD FR-10).
     */
    public function duplicate(Quote $quote): RedirectResponse
    {
        $this->authorize('create', Quote::class);

        $copy = DB::transaction(function () use ($quote) {
            $duplicate = $quote->replicate(['quote_number', 'status', 'quote_date', 'created_by']);
            $duplicate->quote_number = $this->numbers->next();
            $duplicate->status = QuoteStatus::Draft;
            $duplicate->quote_date = now()->startOfDay();
            $duplicate->created_by = auth()->id();

            // Validity restarts from today; keep the original length.
            $duplicate->valid_until = $quote->quote_date
                ? $quote->quote_date->copy()->addDays($quote->valid_until?->diffInDays($quote->quote_date) ?? 15)
                : now()->addDays(15);
            $duplicate->save();

            foreach ($quote->items as $item) {
                $duplicate->items()->create($item->only(['position', 'description', 'quantity', 'rate', 'amount']));
            }

            return $duplicate;
        });

        return Redirect::route('quotes.edit', $copy)
            ->with('status', "Duplicated as {$copy->quote_number} in Draft.");
    }

    /**
     * Move a quote to the trash (decision #8).
     */
    public function destroy(Quote $quote): RedirectResponse
    {
        $this->authorize('delete', $quote);

        $number = $quote->quote_number;
        $quote->delete();

        return Redirect::route('quotes.index')->with('status', "Quotation {$number} moved to trash.");
    }

    /**
     * Restore a quote from the trash.
     */
    public function restore(int $quote): RedirectResponse
    {
        $model = Quote::onlyTrashed()->findOrFail($quote);

        $this->authorize('restore', $model);

        $model->restore();

        return Redirect::route('quotes.trash')->with('status', "Quotation {$model->quote_number} restored.");
    }

    /**
     * Permanently delete a trashed quote.
     */
    public function forceDestroy(int $quote): RedirectResponse
    {
        $model = Quote::onlyTrashed()->findOrFail($quote);

        $this->authorize('forceDelete', $model);

        $number = $model->quote_number;
        $model->items()->delete();
        $model->forceDelete();

        return Redirect::route('quotes.trash')->with('status', "Quotation {$number} permanently deleted.");
    }

    public function trash(): View
    {
        $this->authorize('viewAny', Quote::class);

        return view('quotes.trash', [
            'quotes' => Quote::onlyTrashed()->latest('id')->paginate(10),
        ]);
    }

    /**
     * Create or update a quote inside one transaction.
     */
    /**
     * Initial state for the Alpine quote builder.
     *
     * @param  Collection<int, QuoteTemplate>  $templates
     * @return array<string, mixed>
     */
    private function builderSeed(?Quote $quote, QuoteTemplate $template, $templates): array
    {
        $snapshotTerms = $quote?->terms ?? [];
        $fallback = $this->snapshots->terms($template, $quote ?? new Quote);

        return [
            'templateId' => $quote?->template_id ?? $template->id,
            'clientId' => $quote?->client_id ?? '',
            'gstRate' => (float) ($quote?->gst_rate ?? $template->default_gst_rate),
            'discount' => (float) ($quote?->discount_amount ?? 0),
            'items' => $quote?->items->map(fn ($item): array => [
                'description' => $item->description,
                'qty' => (float) $item->quantity,
                'rate' => (float) $item->rate,
            ])->all() ?? [],
            'terms' => [
                'delivery' => $snapshotTerms['delivery'] ?? $template->delivery_period,
                'warranty' => $snapshotTerms['warranty'] ?? $template->warranty,
                'validity' => $snapshotTerms['validity'] ?? $template->validity_text,
                'extra' => $snapshotTerms['extra'] ?? $fallback['extra'],
                'notes' => $snapshotTerms['notes'] ?? $template->notes,
            ],
            'templates' => $templates->mapWithKeys(fn (QuoteTemplate $item): array => [
                $item->id => [
                    'gst_rate' => (float) $item->default_gst_rate,
                    'terms' => [
                        'delivery' => $item->delivery_period,
                        'warranty' => $item->warranty,
                        'validity' => $item->validity_text,
                        'extra' => $this->snapshots->terms($item, new Quote)['extra'],
                        'notes' => $item->notes,
                    ],
                ],
            ])->all(),
        ];
    }

    private function persist(?Quote $quote, SaveQuoteRequest $request): Quote
    {
        $template = QuoteTemplate::findOrFail($request->validated('template_id'));
        $client = Client::findOrFail($request->validated('client_id'));

        $totals = $this->calculator->compute(
            $request->lineItems(),
            $request->validated('discount_amount') ?? 0,
            $request->validated('gst_rate'),
        );

        $attributes = [
            'quote_date' => $request->validated('quote_date'),
            'valid_until' => $request->validated('valid_until'),
            'enquiry_no' => $request->validated('enquiry_no'),
            'enquiry_date' => $request->validated('enquiry_date'),
            'client_id' => $client->id,
            'template_id' => $template->id,
            'subtotal' => $totals['subtotal'],
            'discount_amount' => $totals['discount'],
            'gst_rate' => $totals['gst_rate'],
            'gst_amount' => $totals['gst_amount'],
            'grand_total' => $totals['grand_total'],
            'amount_in_words' => $this->words->convert($totals['grand_total']),
        ];

        if ($quote === null) {
            $quote = new Quote;
            $quote->quote_number = $this->numbers->next();
            $quote->status = QuoteStatus::Draft;
            $quote->created_by = $request->user()->id;
        }

        $quote->fill($attributes);

        // Snapshots are (re)built on every save, so a template change while
        // Draft is reflected immediately (BR-01).
        $quote->client_snapshot = $this->snapshots->client($client);
        $quote->template_snapshot = $this->snapshots->template($template);
        $quote->terms = $this->snapshots->terms($template, $quote, $request->validated('terms') ?? []);

        $quote->save();

        // Replace line items with the freshly computed set.
        $quote->items()->delete();

        foreach ($totals['items'] as $line) {
            $quote->items()->create([
                'position' => $line['position'],
                'description' => $line['description'],
                'quantity' => $line['qty'],
                'rate' => $line['rate'],
                'amount' => $line['amount'],
            ]);
        }

        $template->update(['last_used_at' => now()]);

        return $quote;
    }

    /**
     * Next number without consuming it, for display in the builder.
     */
    private function peekNextNumber(): int
    {
        $year = (int) now()->year;

        return ((int) DB::table('quote_sequences')->where('year', $year)->value('last_number')) + 1;
    }

    /**
     * Dashboard/list statistics (PRD FR-02).
     *
     * @return array<string, float|int>
     */
    private function stats(): array
    {
        return [
            'total' => Quote::query()->count(),
            'draft' => Quote::query()->where('status', QuoteStatus::Draft)->count(),
            'sent' => Quote::query()->where('status', QuoteStatus::Sent)->count(),
            'approved' => Quote::query()->where('status', QuoteStatus::Approved)->count(),
            'expired' => Quote::query()->where('status', QuoteStatus::Expired)->count(),
            'value' => (float) Quote::query()->sum('grand_total'),
        ];
    }
}
