<x-app-layout title="Client Trash">
    <x-section-header title="Client Trash" back="{{ route('clients.index') }}">
        <x-slot name="description">Deleted clients stay here until you restore or permanently delete them.</x-slot>
    </x-section-header>

    <x-card :padding="false">
        @if ($clients->isEmpty())
            <x-empty-state
                title="Trash is empty"
                description="Clients you delete will appear here and can be restored at any time."
            />
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-[13.5px]">
                    <thead>
                        <tr class="border-b border-app-border text-xs uppercase tracking-wide text-app-faint">
                            <th class="px-4 py-3 font-semibold">Client</th>
                            <th class="px-4 py-3 font-semibold">Deleted</th>
                            <th class="px-4 py-3 text-right font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($clients as $client)
                            <tr class="border-b border-app-border last:border-0 hover:bg-app-neutral-soft/60">
                                <td class="px-4 py-3">
                                    <div class="font-semibold">{{ $client->name }}</div>
                                    @if ($client->email)
                                        <div class="text-xs text-app-faint">{{ $client->email }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-app-muted">{{ $client->deleted_at?->format('d M Y, h:i A') }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-2">
                                        <form method="POST" action="{{ route('clients.restore', ['client' => $client->id]) }}">
                                            @csrf
                                            <x-button type="submit" variant="subtle" icon="refresh">Restore</x-button>
                                        </form>

                                        <form method="POST" action="{{ route('clients.force-destroy', ['client' => $client->id]) }}" onsubmit="return confirm('Permanently delete &quot;{{ addslashes($client->name) }}&quot;? This cannot be undone.');">
                                            @csrf
                                            @method('DELETE')
                                            <x-button type="submit" variant="danger" icon="trash">Delete Permanently</x-button>
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
</x-app-layout>