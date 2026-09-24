<x-app-layout title="Clients">
    <x-section-header title="Clients" description="Customers you send quotations to.">
        <x-slot name="actions">
            <x-button type="button" x-on:click="$dispatch('open-modal', 'client-form')" icon="plus">
                Add Client
            </x-button>
        </x-slot>
    </x-section-header>

    {{-- Filters --}}
    <x-card class="mb-4">
        <form method="GET" action="{{ route('clients.index') }}" class="flex flex-wrap items-end gap-3">
            <div class="min-w-[240px] flex-1">
                <x-input
                    name="search"
                    label="Search"
                    placeholder="Name, contact, email, phone or GSTIN"
                    :value="$search"
                />
            </div>

            <x-select
                name="status"
                label="Status"
                class="w-44"
                :options="[
                    '' => 'All ('.$counts['all'].')',
                    'active' => 'Active ('.$counts['active'].')',
                    'inactive' => 'Inactive ('.$counts['inactive'].')',
                ]"
                :value="$status"
            />

            <x-button type="submit" variant="ghost" icon="check">Apply</x-button>

            @if ($search !== '' || $status !== '')
                <a href="{{ route('clients.index') }}" class="pb-2 text-[13px] font-medium text-app-muted hover:text-app-text">Reset</a>
            @endif

            <a href="{{ route('clients.trash') }}" class="ml-auto pb-2 text-[13px] font-medium text-app-muted hover:text-app-text">
                Trash
            </a>
        </form>
    </x-card>

    {{-- Table --}}
    <x-card :padding="false">
        @if ($clients->isEmpty())
            <x-empty-state
                title="No clients found"
                :description="$search !== '' || $status !== '' ? 'Try a different search or filter.' : 'Add your first customer to start creating quotations.'"
                icon="user-plus"
            />
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-[13.5px]">
                    <thead>
                        <tr class="border-b border-app-border text-xs uppercase tracking-wide text-app-faint">
                            <th class="px-4 py-3 font-semibold">Client</th>
                            <th class="px-4 py-3 font-semibold">Contact</th>
                            <th class="px-4 py-3 font-semibold">GSTIN</th>
                            <th class="px-4 py-3 font-semibold">Status</th>
                            <th class="px-4 py-3 text-right font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($clients as $client)
                            <tr class="border-b border-app-border last:border-0 hover:bg-app-neutral-soft/60">
                                <td class="px-4 py-3">
                                    <a href="{{ route('clients.show', $client) }}" class="font-semibold text-app-text hover:text-app-accent">{{ $client->name }}</a>
                                    @if ($client->address)
                                        <div class="mt-0.5 max-w-xs truncate text-xs text-app-faint">{{ $client->address }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-app-muted">
                                    @if ($client->contact_person)
                                        <div>{{ $client->contact_person }}</div>
                                    @endif
                                    @if ($client->email)
                                        <div class="text-xs text-app-faint">{{ $client->email }}</div>
                                    @endif
                                    @if ($client->phone)
                                        <div class="text-xs text-app-faint">{{ $client->phone }}</div>
                                    @endif
                                    @if (! $client->contact_person && ! $client->email && ! $client->phone)
                                        <span class="text-app-faint">&mdash;</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 font-mono text-xs text-app-muted">{{ $client->gstin ?: '—' }}</td>
                                <td class="px-4 py-3">
                                    <x-badge :status="$client->is_active ? 'success' : 'neutral'">
                                        {{ $client->is_active ? 'Active' : 'Inactive' }}
                                    </x-badge>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        <a href="{{ route('clients.show', $client) }}" class="rounded-[6px] p-2 text-app-muted hover:bg-app-neutral-soft hover:text-app-text" title="View" aria-label="View {{ $client->name }}">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                        </a>

                                        <button type="button" x-on:click="$dispatch('open-modal', 'client-form'); $dispatch('fill-client', {{ Js::from(['id' => $client->id, 'name' => $client->name, 'contact_person' => $client->contact_person, 'email' => $client->email, 'phone' => $client->phone, 'gstin' => $client->gstin, 'address' => $client->address, 'is_active' => $client->is_active]) }})" class="rounded-[6px] p-2 text-app-muted hover:bg-app-neutral-soft hover:text-app-text" title="Edit" aria-label="Edit {{ $client->name }}">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
                                        </button>

                                        <form method="POST" action="{{ route('clients.toggle-status', $client) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="rounded-[6px] p-2 text-app-muted hover:bg-app-neutral-soft hover:text-app-text" title="{{ $client->is_active ? 'Deactivate' : 'Activate' }}" aria-label="{{ $client->is_active ? 'Deactivate' : 'Activate' }} {{ $client->name }}">
                                                @if ($client->is_active)
                                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="4.9" y1="4.9" x2="19.1" y2="19.1"/></svg>
                                                @else
                                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                                @endif
                                            </button>
                                        </form>

                                        <form method="POST" action="{{ route('clients.destroy', $client) }}" onsubmit="return confirm('Move &quot;{{ addslashes($client->name) }}&quot; to trash? You can restore it later.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-[6px] p-2 text-app-muted hover:bg-app-danger-soft hover:text-app-danger" title="Move to trash" aria-label="Move {{ $client->name }} to trash">
                                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="border-t border-app-border px-4 py-3">
                {{ $clients->links() }}
            </div>
        @endif
    </x-card>

    @include('clients.partials.form')
</x-app-layout>