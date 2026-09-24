<x-app-layout title="Dashboard">
    <x-section-header
        title="Welcome back, {{ explode(' ', auth()->user()->name)[0] }}"
        description="Quotation &amp; Proforma Invoice Management System"
    />

    {{-- Stat cards (PRD FR-02). Quote figures populate in Phase 4/6. --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <x-card>
            <p class="text-xs font-medium uppercase tracking-wide text-app-faint">Total Clients</p>
            <p class="mt-1.5 text-[26px] font-bold tabular-nums">{{ $clientCount }}</p>
            <a href="{{ route('clients.index') }}" class="mt-1 inline-block text-xs font-medium text-app-accent hover:underline">Manage clients</a>
        </x-card>

        <x-card>
            <p class="text-xs font-medium uppercase tracking-wide text-app-faint">Active Clients</p>
            <p class="mt-1.5 text-[26px] font-bold tabular-nums">{{ $activeClientCount }}</p>
            <p class="mt-1 text-xs text-app-faint">Eligible for new quotations</p>
        </x-card>

        <x-card>
            <p class="text-xs font-medium uppercase tracking-wide text-app-faint">Total Quoted Value</p>
            <p class="mt-1.5 text-[26px] font-bold tabular-nums text-app-faint">—</p>
            <p class="mt-1 text-xs text-app-faint">Available once quotes are created</p>
        </x-card>
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
        <x-card>
            <h3 class="mb-3 text-[13px] font-bold uppercase tracking-wide text-app-faint">Recently Added Clients</h3>

            @if ($recentClients->isEmpty())
                <x-empty-state
                    title="No clients yet"
                    description="Add your first customer to start creating quotations."
                >
                    <x-slot name="action">
                        <x-button :href="route('clients.index')">Add Client</x-button>
                    </x-slot>
                </x-empty-state>
            @else
                <ul class="divide-y divide-app-border">
                    @foreach ($recentClients as $client)
                        <li class="flex items-center justify-between gap-3 py-2.5 first:pt-0 last:pb-0">
                            <div class="min-w-0">
                                <a href="{{ route('clients.show', $client) }}" class="truncate text-[13.5px] font-semibold hover:text-app-accent">{{ $client->name }}</a>
                                <p class="truncate text-xs text-app-faint">{{ $client->email ?: $client->phone ?: '—' }}</p>
                            </div>
                            <x-badge :status="$client->is_active ? 'success' : 'neutral'">
                                {{ $client->is_active ? 'Active' : 'Inactive' }}
                            </x-badge>
                        </li>
                    @endforeach
                </ul>

                <a href="{{ route('clients.index') }}" class="mt-4 inline-block text-xs font-medium text-app-accent hover:underline">View all clients</a>
            @endif
        </x-card>

        <x-card>
            <h3 class="mb-3 text-[13px] font-bold uppercase tracking-wide text-app-faint">Build Progress</h3>
            <ul class="space-y-2.5 text-[13px]">
                @php
                    $stages = [
                        ['Phase 0', 'Foundation — Laravel 13, Breeze, Vite, Tailwind', true],
                        ['Phase 1', 'App shell &amp; component library', true],
                        ['Phase 2', 'Client management', true],
                        ['Phase 3', 'Quote templates &amp; branding', false],
                        ['Phase 4', 'Quote builder &amp; calculations', false],
                        ['Phase 5', 'A4 preview &amp; PDF', false],
                        ['Phase 6', 'Quote management &amp; dashboard', false],
                        ['Phase 7', 'Security, performance &amp; deployment', false],
                    ];
                @endphp

                @foreach ($stages as [$phase, $label, $done])
                    <li class="flex items-start gap-2.5">
                        @if ($done)
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 shrink-0 text-app-success" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                        @else
                            <span class="mt-1 size-2 shrink-0 rounded-full bg-app-border" aria-hidden="true"></span>
                        @endif
                        <span class="{{ $done ? 'text-app-text' : 'text-app-faint' }}">
                            <span class="font-semibold">{{ $phase }}</span> — {!! $label !!}
                        </span>
                    </li>
                @endforeach
            </ul>
        </x-card>
    </div>
</x-app-layout>