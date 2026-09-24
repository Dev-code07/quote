@use('App\Support\Money')

<x-app-layout title="Dashboard">
    <x-section-header
        title="Welcome back, {{ explode(' ', auth()->user()->name)[0] }}"
        description="Quotation &amp; Proforma Invoice Management System"
    >
        <x-slot name="actions">
            <x-button :href="route('quotes.create')">+ New Quotation</x-button>
        </x-slot>
    </x-section-header>

    {{-- Quote statistics (PRD FR-02) --}}
    <div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-6">
        @php
            $cards = [
                ['Total Quotes', $quoteStats['total'], ''],
                ['Draft', $quoteStats['draft'], ''],
                ['Sent', $quoteStats['sent'], ''],
                ['Approved', $quoteStats['approved'], ''],
                ['Expired', $quoteStats['expired'], ''],
            ];
        @endphp

        @foreach ($cards as [$label, $value, $tone])
            <x-card class="p-4">
                <p class="text-[11px] font-medium uppercase tracking-wide text-app-faint">{{ $label }}</p>
                <p class="mt-1 text-[22px] font-bold tabular-nums">{{ $value }}</p>
            </x-card>
        @endforeach

        <x-card class="p-4">
            <p class="text-[11px] font-medium uppercase tracking-wide text-app-faint">Total Quoted Value</p>
            <p class="mt-1 text-[20px] font-bold tabular-nums text-app-accent">{{ Money::format($quoteStats['value']) }}</p>
        </x-card>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        {{-- Recent quotations --}}
        <x-card class="lg:col-span-2" :padding="false">
            <div class="flex items-center justify-between gap-2 border-b border-app-border px-5 py-3.5">
                <h3 class="text-[13px] font-bold">Recent Quotations</h3>
                <a href="{{ route('quotes.index') }}" class="text-xs font-medium text-app-accent hover:underline">View all</a>
            </div>

            @if ($recentQuotes->isEmpty())
                <x-empty-state
                    title="No quotations yet"
                    description="Create your first quotation to get started."
                    :action="'<a href=\"'.route('quotes.create').'\" class=\"inline-flex items-center justify-center rounded-[8px] bg-app-accent px-4 py-2 text-[13px] font-semibold text-white hover:bg-app-accent-hover\">Create a quotation</a>'"
                />
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-[13px]">
                        <thead>
                            <tr class="border-b border-app-border text-xs uppercase tracking-wide text-app-faint">
                                <th class="px-5 py-2.5 font-semibold">Quote No.</th>
                                <th class="px-5 py-2.5 font-semibold">Client</th>
                                <th class="px-5 py-2.5 font-semibold">Date</th>
                                <th class="px-5 py-2.5 text-right font-semibold">Amount</th>
                                <th class="px-5 py-2.5 font-semibold">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recentQuotes as $quote)
                                <tr class="border-b border-app-border last:border-0 hover:bg-app-neutral-soft/60">
                                    <td class="px-5 py-2.5">
                                        <a href="{{ route('quotes.show', $quote) }}" class="font-mono font-semibold hover:text-app-accent">{{ $quote->quote_number }}</a>
                                    </td>
                                    <td class="px-5 py-2.5">{{ $quote->clientName() }}</td>
                                    <td class="px-5 py-2.5 text-app-muted">{{ $quote->quote_date?->format('d M Y') }}</td>
                                    <td class="px-5 py-2.5 text-right font-semibold tabular-nums">{{ Money::format((float) $quote->grand_total) }}</td>
                                    <td class="px-5 py-2.5"><x-badge :status="$quote->status->tone()">{{ $quote->status->label() }}</x-badge></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>

        <div class="space-y-4">
            {{-- Client summary --}}
            <x-card>
                <div class="mb-3 flex items-center justify-between gap-2">
                    <h3 class="text-[13px] font-bold">Clients</h3>
                    <a href="{{ route('clients.index') }}" class="text-xs font-medium text-app-accent hover:underline">Manage</a>
                </div>

                <dl class="space-y-2 text-[13px]">
                    <div class="flex justify-between gap-3">
                        <dt class="text-app-faint">Total</dt>
                        <dd class="font-semibold tabular-nums">{{ $clientCount }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-app-faint">Active</dt>
                        <dd class="font-semibold tabular-nums">{{ $activeClientCount }}</dd>
                    </div>
                </dl>

                @if ($recentClients->isEmpty())
                    <p class="mt-3 border-t border-app-border pt-3 text-xs text-app-faint">
                        No clients yet.
                        <a href="{{ route('clients.index') }}" class="text-app-accent hover:underline">Add your first customer.</a>
                    </p>
                @else
                    <ul class="mt-3 space-y-1.5 border-t border-app-border pt-3">
                        @foreach ($recentClients as $client)
                            <li class="flex items-center justify-between gap-2">
                                <a href="{{ route('clients.show', $client) }}" class="truncate text-[13px] hover:text-app-accent">{{ $client->name }}</a>
                                <x-badge :status="$client->is_active ? 'success' : 'neutral'">
                                    {{ $client->is_active ? 'Active' : 'Inactive' }}
                                </x-badge>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>

            <x-card>
                <h3 class="mb-3 text-[13px] font-bold">Quick actions</h3>
                <div class="space-y-2">
                    <x-button :href="route('quotes.create')" class="w-full">New Quotation</x-button>
                    <x-button :href="route('templates.create')" variant="ghost" class="w-full">New Template</x-button>
                    <x-button :href="route('clients.index')" variant="ghost" class="w-full">Manage Clients</x-button>
                </div>
            </x-card>
        </div>
    </div>
</x-app-layout>