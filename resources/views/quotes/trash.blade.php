<x-app-layout title="Quotation Trash">
    <x-section-header title="Quotation Trash" back="{{ route('quotes.index') }}">
        <x-slot name="description">Deleted quotations stay here until you restore or permanently delete them.</x-slot>
    </x-section-header>

    <x-card :padding="false">
        @if ($quotes->isEmpty())
            <x-empty-state title="Trash is empty" description="Quotations you delete will appear here." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-[13.5px]">
                    <thead>
                        <tr class="border-b border-app-border text-xs uppercase tracking-wide text-app-faint">
                            <th class="px-4 py-3 font-semibold">Quote No.</th>
                            <th class="px-4 py-3 font-semibold">Client</th>
                            <th class="px-4 py-3 font-semibold">Deleted</th>
                            <th class="px-4 py-3 text-right font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($quotes as $quote)
                            <tr class="border-b border-app-border last:border-0 hover:bg-app-neutral-soft/60">
                                <td class="px-4 py-3 font-mono font-semibold">{{ $quote->quote_number }}</td>
                                <td class="px-4 py-3 text-app-muted">{{ $quote->clientName() }}</td>
                                <td class="px-4 py-3 text-app-muted">{{ $quote->deleted_at?->format('d M Y, h:i A') }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-2">
                                        <form method="POST" action="{{ route('quotes.restore', ['quote' => $quote->id]) }}">
                                            @csrf
                                            <x-button type="submit" variant="subtle">Restore</x-button>
                                        </form>
                                        <form method="POST" action="{{ route('quotes.force-destroy', ['quote' => $quote->id]) }}" onsubmit="return confirm('Permanently delete {{ $quote->quote_number }}? This cannot be undone.');">
                                            @csrf
                                            @method('DELETE')
                                            <x-button type="submit" variant="danger">Delete Permanently</x-button>
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