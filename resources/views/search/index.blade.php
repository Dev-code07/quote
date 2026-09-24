@php
    $statusStyles = [
        'draft' => 'bg-app-neutral-soft text-app-muted',
        'sent' => 'bg-app-accent-soft text-app-accent',
        'approved' => 'bg-app-success/10 text-app-success',
        'expired' => 'bg-app-warning/10 text-app-warning',
    ];
@endphp

<x-app-layout title="Search">
    <x-section-header
        title="Search"
        :description="$term === ''
            ? 'Search across quotations and clients.'
            : 'Results for “'.Illuminate\Support\Str::limit($term, 60).'”'"
    />

    @if ($term === '')
        <x-card>
            <x-empty-state
                title="Start typing to search"
                description="Search by quotation number or client name, GSTIN, email or phone."
                icon="search"
            />
        </x-card>
    @elseif ($quotes->isEmpty() && $clients->isEmpty())
        <x-card>
            <x-empty-state
                title="No matches found"
                :description="'Nothing matched “'.Illuminate\Support\Str::limit($term, 40).'”. Try a different term.'"
                icon="search"
            />
        </x-card>
    @else
        @if ($quotes->isNotEmpty())
            <x-card :padding="false" class="mb-5">
                <div class="border-b border-app-border px-6 py-3.5">
                    <h3 class="text-[13px] font-semibold text-app-text">
                        Quotations
                        <span class="ml-1 font-normal text-app-faint">({{ $quotes->count() }})</span>
                    </h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-[13.5px]">
                        <thead>
                            <tr class="border-b border-app-border text-xs uppercase tracking-wide text-app-faint">
                                <th class="px-4 py-3 font-semibold">Quote No.</th>
                                <th class="px-4 py-3 font-semibold">Client</th>
                                <th class="px-4 py-3 font-semibold">Date</th>
                                <th class="px-4 py-3 font-semibold">Status</th>
                                <th class="px-4 py-3 text-right font-semibold">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($quotes as $quote)
                                <tr class="border-b border-app-border last:border-0 transition-colors hover:bg-app-neutral-soft">
                                    <td class="px-4 py-3">
                                        <a href="{{ route('quotes.show', $quote) }}" class="font-semibold text-app-accent hover:underline">
                                            {{ $quote->quote_number }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-3 text-app-muted">{{ $quote->clientName() }}</td>
                                    <td class="px-4 py-3 text-app-muted">{{ $quote->quote_date?->format('d M Y') }}</td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $statusStyles[$quote->status->value] ?? $statusStyles['draft'] }}">
                                            {{ ucfirst($quote->status->value) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-semibold">{{ Illuminate\Support\Number::currency($quote->grand_total, 'INR') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>
        @endif

        @if ($clients->isNotEmpty())
            <x-card :padding="false">
                <div class="border-b border-app-border px-6 py-3.5">
                    <h3 class="text-[13px] font-semibold text-app-text">
                        Clients
                        <span class="ml-1 font-normal text-app-faint">({{ $clients->count() }})</span>
                    </h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-[13.5px]">
                        <thead>
                            <tr class="border-b border-app-border text-xs uppercase tracking-wide text-app-faint">
                                <th class="px-4 py-3 font-semibold">Client</th>
                                <th class="px-4 py-3 font-semibold">Contact</th>
                                <th class="px-4 py-3 font-semibold">Email</th>
                                <th class="px-4 py-3 font-semibold">Phone</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($clients as $client)
                                <tr class="border-b border-app-border last:border-0 transition-colors hover:bg-app-neutral-soft">
                                    <td class="px-4 py-3">
                                        <a href="{{ route('clients.show', $client) }}" class="font-semibold text-app-accent hover:underline">
                                            {{ $client->name }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-3 text-app-muted">{{ $client->contact_person ?: '—' }}</td>
                                    <td class="px-4 py-3 text-app-muted">{{ $client->email ?: '—' }}</td>
                                    <td class="px-4 py-3 text-app-muted">{{ $client->phone ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>
        @endif
    @endif
</x-app-layout>