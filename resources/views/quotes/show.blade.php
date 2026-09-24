@use('App\Enums\QuoteStatus')
@use('App\Support\Money')

<x-app-layout :title="$quote->quote_number">
    <x-section-header :title="$quote->quote_number" back="{{ route('quotes.index') }}">
        <x-slot name="description">{{ $quote->clientName() }} &middot; {{ $quote->quote_date?->format('d M Y') }}</x-slot>
        <x-slot name="actions">
            <x-badge :status="$quote->status->tone()">{{ $quote->status->label() }}</x-badge>
        </x-slot>
    </x-section-header>

    <div class="mb-4 flex flex-wrap items-center gap-2">
        @if ($quote->status !== QuoteStatus::Expired)
            <form method="POST" action="{{ route('quotes.status', $quote) }}">
                @csrf
                @method('PATCH')
                @foreach (QuoteStatus::selectableOptions() as $value => $label)
                    @continue($value === $quote->status->value)
                    <input type="hidden" name="status" value="{{ $value }}">
                    <x-button type="submit" variant="subtle" class="mt-0">
                        Mark as {{ $label }}
                    </x-button>
                @endforeach
            </form>
        @else
            <p class="rounded-[6px] bg-app-warning-soft px-3 py-2 text-[12.5px] text-app-warning">
                This quotation expired automatically on {{ $quote->valid_until?->format('d M Y') }}.
            </p>
        @endif

        <a href="{{ route('quotes.edit', $quote) }}">
            <x-button variant="ghost">Edit</x-button>
        </a>

        <a href="{{ route('quotes.pdf', $quote) }}" target="_blank" rel="noopener">
            <x-button variant="ghost">Download PDF</x-button>
        </a>

        <button type="button" x-on:click="window.print()">
            <x-button variant="ghost">Print</x-button>
        </button>

        <form method="POST" action="{{ route('quotes.duplicate', $quote) }}">
            @csrf
            <x-button type="submit" variant="subtle">Duplicate</x-button>
        </form>

        <form method="POST" action="{{ route('quotes.destroy', $quote) }}" class="ml-auto" onsubmit="return confirm('Move {{ $quote->quote_number }} to trash?');">
            @csrf
            @method('DELETE')
            <x-button type="submit" variant="danger">Move to Trash</x-button>
        </form>
    </div>

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
        <div class="xl:col-span-2">
            <x-card :padding="false">
                <div class="flex items-center justify-between gap-2 border-b border-app-border px-4 py-3">
                    <h3 class="text-[13px] font-bold">A4 Preview</h3>
                    <div class="flex items-center gap-1" x-data="{ zoom: 55 }">
                        <button type="button" class="flex size-7 items-center justify-center rounded-[6px] border border-app-border text-app-muted hover:bg-app-neutral-soft" x-on:click="zoom = Math.max(30, zoom - 10)" aria-label="Zoom out">&minus;</button>
                        <span class="w-11 text-center text-xs font-semibold tabular-nums" x-text="zoom + '%'">55%</span>
                        <button type="button" class="flex size-7 items-center justify-center rounded-[6px] border border-app-border text-app-muted hover:bg-app-neutral-soft" x-on:click="zoom = Math.min(120, zoom + 10)" aria-label="Zoom in">+</button>
                    </div>
                </div>

                <div class="overflow-auto bg-app-bg p-6">
                    <div class="mx-auto origin-top" x-bind:style="`transform: scale(${zoom / 100}); width: ${210 * 96 / 25.4}px;`">
                        <x-a4-sheet :doc="$doc" />
                    </div>
                </div>
            </x-card>
        </div>

        <div class="space-y-4">
            <x-card>
                <h3 class="mb-3 text-[13px] font-bold uppercase tracking-wide text-app-faint">Summary</h3>
                <dl class="space-y-2 text-[13px]">
                    <div class="flex justify-between gap-3">
                        <dt class="text-app-faint">Subtotal</dt>
                        <dd class="tabular-nums">{{ Money::format((float) $quote->subtotal) }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-app-faint">Discount</dt>
                        <dd class="tabular-nums text-app-danger">− {{ Money::format((float) $quote->discount_amount) }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-app-faint">GST @ {{ rtrim(rtrim(number_format((float) $quote->gst_rate, 2), '0'), '.') }}%</dt>
                        <dd class="tabular-nums">{{ Money::format((float) $quote->gst_amount) }}</dd>
                    </div>
                    <div class="flex justify-between gap-3 border-t border-app-border pt-2">
                        <dt class="font-bold">Grand Total</dt>
                        <dd class="text-[15px] font-bold tabular-nums text-app-accent">{{ Money::format((float) $quote->grand_total) }}</dd>
                    </div>
                </dl>
            </x-card>

            <x-card>
                <h3 class="mb-3 text-[13px] font-bold uppercase tracking-wide text-app-faint">Details</h3>
                <dl class="space-y-2 text-[13px]">
                    <div class="flex justify-between gap-3">
                        <dt class="text-app-faint">Quotation date</dt>
                        <dd>{{ $quote->quote_date?->format('d M Y') }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-app-faint">Valid until</dt>
                        <dd>{{ $quote->valid_until?->format('d M Y') }}</dd>
                    </div>
                    @if ($quote->enquiry_no)
                        <div class="flex justify-between gap-3">
                            <dt class="text-app-faint">Enquiry</dt>
                            <dd>{{ $quote->enquiry_no }}{{ $quote->enquiry_date ? ' · '.$quote->enquiry_date->format('d M Y') : '' }}</dd>
                        </div>
                    @endif
                    <div class="flex justify-between gap-3">
                        <dt class="text-app-faint">Items</dt>
                        <dd>{{ $quote->items->count() }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-app-faint">Template</dt>
                        <dd class="text-right">{{ $quote->template?->name ?? 'Removed' }}</dd>
                    </div>
                </dl>
            </x-card>

            <x-card>
                <h3 class="mb-2 text-[13px] font-bold uppercase tracking-wide text-app-faint">Historical integrity</h3>
                <p class="text-xs leading-relaxed text-app-faint">
                    This quotation prints from its own saved copy of the letterhead, client details and terms.
                    Editing the template or the client now will not change it.
                </p>
            </x-card>
        </div>
    </div>
</x-app-layout>