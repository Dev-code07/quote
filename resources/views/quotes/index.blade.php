@use('App\Support\Money')

<x-app-layout title="Quotations">
    <x-section-header title="Quotations" description="Create, track and export quotations.">
        <x-slot name="actions">
            <a href="{{ route('quotes.trash') }}" class="text-[13px] font-medium text-app-muted hover:text-app-text">Trash</a>
            <x-button :href="route('quotes.create')" icon="plus">New Quotation</x-button>
        </x-slot>
    </x-section-header>

    {{-- Stat cards (PRD FR-02) --}}
    <div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-6">
        @php
            $cards = [
                ['Total', $stats['total'], 'neutral'],
                ['Draft', $stats['draft'], 'neutral'],
                ['Sent', $stats['sent'], 'accent'],
                ['Approved', $stats['approved'], 'success'],
                ['Expired', $stats['expired'], 'warning'],
            ];
        @endphp

        @foreach ($cards as [$label, $value, $tone])
            <x-card class="p-4">
                <p class="text-[11px] font-medium uppercase tracking-wide text-app-faint">{{ $label }}</p>
                <p class="mt-1 text-[22px] font-bold tabular-nums">{{ $value }}</p>
            </x-card>
        @endforeach

        <x-card class="p-4">
            <p class="text-[11px] font-medium uppercase tracking-wide text-app-faint">Total Value</p>
            <p class="mt-1 text-[22px] font-bold tabular-nums text-app-accent">{{ Money::format($stats['value']) }}</p>
        </x-card>
    </div>

    {{-- Filters --}}
    <x-card class="mb-4">
        <form method="GET" action="{{ route('quotes.index') }}" class="grid grid-cols-1 items-end gap-3 sm:grid-cols-2 lg:grid-cols-6">
            <div class="lg:col-span-2">
                <x-input name="search" label="Search" placeholder="Quote no. or client" :value="$search" />
            </div>

            <x-select name="status" label="Status" :options="['' => 'All statuses'] + $statusOptions" :value="$status" />

            <x-input name="from" type="date" label="From" :value="$from" />
            <x-input name="to" type="date" label="To" :value="$to" />

            <div class="flex gap-2">
                <x-button type="submit" variant="ghost" icon="filter">Filter</x-button>
                @if ($search !== '' || $status !== '' || $from || $to)
                    <a href="{{ route('quotes.index') }}" class="self-center pb-2 text-[13px] font-medium text-app-muted hover:text-app-text">Reset</a>
                @endif
            </div>
        </form>
    </x-card>

    <x-card :padding="false">
        @if ($quotes->isEmpty())
            <x-empty-state
                title="No quotations found"
                :description="$search !== '' || $status !== '' ? 'Try a different search or filter.' : 'Create your first quotation to get started.'"
            />
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-[13.5px]">
                    <thead>
                        <tr class="border-b border-app-border text-xs uppercase tracking-wide text-app-faint">
                            <th class="px-4 py-3 font-semibold">Quote No.</th>
                            <th class="px-4 py-3 font-semibold">Client</th>
                            <th class="px-4 py-3 font-semibold">Date</th>
                            <th class="px-4 py-3 text-right font-semibold">Items</th>
                            <th class="px-4 py-3 text-right font-semibold">Amount</th>
                            <th class="px-4 py-3 font-semibold">Status</th>
                            <th class="px-4 py-3 text-right font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($quotes as $quote)
                            <tr class="border-b border-app-border last:border-0 hover:bg-app-neutral-soft/60">
                                <td class="px-4 py-3">
                                    <a href="{{ route('quotes.show', $quote) }}" class="font-mono font-semibold hover:text-app-accent">{{ $quote->quote_number }}</a>
                                </td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('clients.show', $quote->client_id) }}" class="hover:text-app-accent">{{ $quote->clientName() }}</a>
                                </td>
                                <td class="px-4 py-3 text-app-muted">{{ $quote->quote_date?->format('d M Y') }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-app-muted">{{ $quote->items_count }}</td>
                                <td class="px-4 py-3 text-right font-semibold tabular-nums">{{ Money::format((float) $quote->grand_total) }}</td>
                                <td class="px-4 py-3"><x-badge :status="$quote->status->tone()">{{ $quote->status->label() }}</x-badge></td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        <a href="{{ route('quotes.show', $quote) }}" class="rounded-[6px] p-2 text-app-muted hover:bg-app-neutral-soft hover:text-app-text" title="View" aria-label="View {{ $quote->quote_number }}">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                        </a>
                                        <a href="{{ route('quotes.edit', $quote) }}" class="rounded-[6px] p-2 text-app-muted hover:bg-app-neutral-soft hover:text-app-text" title="Edit" aria-label="Edit {{ $quote->quote_number }}">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
                                        </a>
                                        <form method="POST" action="{{ route('quotes.duplicate', $quote) }}">
                                            @csrf
                                            <button type="submit" class="rounded-[6px] p-2 text-app-muted hover:bg-app-neutral-soft hover:text-app-text" title="Duplicate" aria-label="Duplicate {{ $quote->quote_number }}">
                                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('quotes.destroy', $quote) }}" onsubmit="return confirm('Move {{ $quote->quote_number }} to trash?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-[6px] p-2 text-app-muted hover:bg-app-danger-soft hover:text-app-danger" title="Move to trash" aria-label="Move {{ $quote->quote_number }} to trash">
                                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="border-t border-app-border px-4 py-3">{{ $quotes->links() }}</div>
        @endif
    </x-card>
</x-app-layout>