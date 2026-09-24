<x-app-layout :title="$client->name">
    <x-section-header :title="$client->name" back="{{ route('clients.index') }}">
        <x-slot name="description">
            <span x-data class="inline-flex items-center gap-2">
                <x-badge :status="$client->is_active ? 'success' : 'neutral'">
                    {{ $client->is_active ? 'Active' : 'Inactive' }}
                </x-badge>
            </span>
        </x-slot>

        <x-slot name="actions">
            <x-button
                variant="ghost"
                icon="edit"
                type="button"
                x-on:click="$dispatch('open-modal', 'client-form'); $dispatch('fill-client', {{ Js::from(['id' => $client->id, 'name' => $client->name, 'contact_person' => $client->contact_person, 'email' => $client->email, 'phone' => $client->phone, 'gstin' => $client->gstin, 'address' => $client->address, 'is_active' => $client->is_active]) }})"
            >
                Edit
            </x-button>
        </x-slot>
    </x-section-header>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <x-card class="lg:col-span-2">
            <h3 class="mb-4 text-[13px] font-bold uppercase tracking-wide text-app-faint">Client Details</h3>

            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs text-app-faint">Company / Name</dt>
                    <dd class="mt-0.5 text-[13.5px] font-semibold">{{ $client->name }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-app-faint">Contact Person</dt>
                    <dd class="mt-0.5 text-[13.5px]">{{ $client->contact_person ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-app-faint">Email</dt>
                    <dd class="mt-0.5 text-[13.5px]">
                        @if ($client->email)
                            <a href="mailto:{{ $client->email }}" class="text-app-accent hover:underline">{{ $client->email }}</a>
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-app-faint">Phone</dt>
                    <dd class="mt-0.5 text-[13.5px]">{{ $client->phone ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-app-faint">GSTIN</dt>
                    <dd class="mt-0.5 font-mono text-[13px]">{{ $client->gstin ?: '—' }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-xs text-app-faint">Billing Address</dt>
                    <dd class="mt-0.5 text-[13.5px] leading-relaxed">{{ $client->address ?: '—' }}</dd>
                </div>
            </dl>
        </x-card>

        <div class="space-y-4">
            <x-card>
                <h3 class="mb-3 text-[13px] font-bold uppercase tracking-wide text-app-faint">Record</h3>
                <dl class="space-y-2 text-[13px]">
                    <div class="flex justify-between gap-3">
                        <dt class="text-app-faint">Added</dt>
                        <dd>{{ $client->created_at?->format('d M Y') }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-app-faint">Last updated</dt>
                        <dd>{{ $client->updated_at?->format('d M Y') }}</dd>
                    </div>
                </dl>
            </x-card>

            <x-card>
                <h3 class="mb-3 text-[13px] font-bold uppercase tracking-wide text-app-faint">Actions</h3>
                <div class="space-y-2">
                    <form method="POST" action="{{ route('clients.toggle-status', $client) }}">
                        @csrf
                        @method('PATCH')
                        <x-button type="submit" variant="ghost" :icon="$client->is_active ? 'x' : 'check'" class="w-full">
                            {{ $client->is_active ? 'Deactivate Client' : 'Activate Client' }}
                        </x-button>
                    </form>

                    <form method="POST" action="{{ route('clients.destroy', $client) }}" onsubmit="return confirm('Move this client to trash? You can restore them later.');">
                        @csrf
                        @method('DELETE')
                        <x-button type="submit" variant="danger" icon="trash" class="w-full">Move to Trash</x-button>
                    </form>
                </div>
            </x-card>
        </div>
    </div>

    <x-card class="mt-4" :padding="false">
        <h3 class="border-b border-app-border px-5 py-3.5 text-[13px] font-bold uppercase tracking-wide text-app-faint">
            Quotations
        </h3>

        <x-empty-state
            title="No quotations yet"
            description="Quotation creation arrives in Phase 4. This client's issued quotes will be listed here."
        />
    </x-card>

    @include('clients.partials.form')
</x-app-layout>