@use('App\Enums\QuoteStatus')
@use('App\Support\Money')

<x-app-layout :title="$quote->quote_number">
    {{-- Hidden in print: only the A4 sheet prints (see print CSS in app.css). --}}
    <x-section-header :title="$quote->quote_number" back="{{ route('quotes.index') }}" class="no-print">
        <x-slot name="description">{{ $quote->clientName() }} &middot; {{ $quote->quote_date?->format('d M Y') }}</x-slot>
        <x-slot name="actions">
            <x-badge :status="$quote->status->tone()">{{ $quote->status->label() }}</x-badge>
        </x-slot>
    </x-section-header>

    <div class="no-print mb-4 flex flex-wrap items-center gap-2">
        @if ($quote->status !== QuoteStatus::Expired)
            <form method="POST" action="{{ route('quotes.status', $quote) }}">
                @csrf
                @method('PATCH')
                @foreach (QuoteStatus::selectableOptions() as $value => $label)
                    @continue($value === $quote->status->value)
                    <input type="hidden" name="status" value="{{ $value }}">
                    <x-button type="submit" variant="subtle" class="mt-0" :icon="$value === 'approved' ? 'check-circle' : 'send'">
                        Mark as {{ $label }}
                    </x-button>
                @endforeach
            </form>
        @else
            <p class="rounded-[6px] bg-app-warning-soft px-3 py-2 text-[12.5px] text-app-warning">
                This quotation expired automatically on {{ $quote->valid_until?->format('d M Y') }}.
            </p>
        @endif

        <x-button :href="route('quotes.edit', $quote)" variant="ghost" icon="edit">Edit</x-button>

        <x-button :href="route('quotes.pdf', $quote)" variant="ghost" icon="download" target="_blank" rel="noopener">
            Download PDF
        </x-button>

        {{--
            Prints the document only, and names the file after the quotation.
            Uses a plain onclick so printing works even when Alpine fails to
            load (e.g. `npm run dev` not running). The filename is set via
            document.title right before window.print(); browsers suggest it as
            the default name in the Save-as-PDF dialog.
        --}}
        {{-- Print button: lives OUTSIDE x-button so no nested <button> is rendered
             (invalid nested buttons swallow the click and nothing happens). --}}
        <button type="button" id="quote-print-btn"
            class="no-print inline-flex items-center justify-center gap-[7px] whitespace-nowrap rounded-[6px] border border-app-border bg-white py-[9px] pl-[16px] pr-[16px] text-[13px] font-semibold leading-none text-app-text transition-colors hover:bg-app-neutral-soft"
            data-print-title="{{ $quote->quote_number.' - '.$quote->clientName() }}">
            <x-icon name="printer" :size="16" class="shrink-0" />
            Print
        </button>

        <form method="POST" action="{{ route('quotes.duplicate', $quote) }}">
            @csrf
            <x-button type="submit" variant="subtle" icon="copy">Duplicate</x-button>
        </form>

        <form method="POST" action="{{ route('quotes.destroy', $quote) }}" class="ml-auto" onsubmit="return confirm('Move {{ $quote->quote_number }} to trash?');">
            @csrf
            @method('DELETE')
            <x-button type="submit" variant="danger" icon="trash">Move to Trash</x-button>
        </form>
    </div>

    {{-- The container query on .quote-split decides preview-beside-cards from
         the width actually available, not the window width. --}}
    <div class="quote-split">
    <div class="grid grid-cols-1 gap-4">
        <div>
            <x-card :padding="false">
                <div class="no-print flex items-center justify-between gap-2 border-b border-app-border px-4 py-3">
                    <h3 class="text-[13px] font-bold">A4 Preview</h3>
                    <div class="flex items-center gap-1" x-data="{ zoom: 55 }">
                        <button type="button" class="flex size-7 items-center justify-center rounded-[6px] border border-app-border text-app-muted hover:bg-app-neutral-soft" x-on:click="zoom = Math.max(30, zoom - 10)" aria-label="Zoom out">&minus;</button>
                        <span class="w-11 text-center text-xs font-semibold tabular-nums" x-text="zoom + '%'">55%</span>
                        <button type="button" class="flex size-7 items-center justify-center rounded-[6px] border border-app-border text-app-muted hover:bg-app-neutral-soft" x-on:click="zoom = Math.min(120, zoom + 10)" aria-label="Zoom in">+</button>
                    </div>
                </div>

                <div class="print-stage overflow-auto bg-app-bg p-6">
                    <div class="print-doc mx-auto origin-top" x-bind:style="`transform: scale(${zoom / 100}); width: ${210 * 96 / 25.4}px;`">
                        <x-a4-sheet :doc="$doc" />
                    </div>
                </div>
            </x-card>
        </div>

        <div class="no-print space-y-4">
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
    </div>

    {{-- Print handler: bound via addEventListener so it works even when Alpine fails
         to load and is never blocked by CSP `script-src` (no inline handler). --}}
    <script>
        (function () {
            function bindPrint() {
                var btn = document.getElementById('quote-print-btn');
                if (!btn || btn.dataset.printBound) return;
                btn.dataset.printBound = '1';
                btn.addEventListener('click', function () {
                    try {
                        var old = document.title;
                        var name = btn.getAttribute('data-print-title') || old;
                        document.title = name;
                        window.print();
                        window.setTimeout(function () { document.title = old; }, 500);
                    } catch (e) {
                        window.print();
                    }
                });
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', bindPrint);
            } else {
                bindPrint();
            }
        })();
    </script>
</x-app-layout>