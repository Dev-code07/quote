@use('App\Support\Money')

{{--
    Quote list — the working replica of docs/quoteflow_dashboard.html.
    Every filter runs through DashboardController (server-side), so the URL is
    shareable and the pagination is real rather than a JS re-render.
--}}
<x-app-layout title="Quotes">
    @php
        $hasFilters = $search !== '' || $status !== '' || $date !== '';
    @endphp

    <div
        x-data="dashboardPage({ search: @js($search), status: @js($status), date: @js($date) })"
        x-on:keydown.escape.window="closeTemplatePicker()"
    >
        <x-section-header title="Quotes" description="Create, manage and track your quotations.">
            <x-slot name="actions">
                <a href="{{ route('quotes.trash') }}" class="text-[13px] font-medium text-app-muted transition-colors hover:text-app-text">
                    Trash
                </a>
                <x-button icon="plus" x-on:click="openTemplatePicker()">Create New Quote</x-button>
            </x-slot>
        </x-section-header>

        {{-- Summary cards (prototype .stats) --}}
        <div class="mb-[26px] grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @php
                $cards = [
                    ['label' => 'Total Quotes', 'value' => $quoteStats['total'], 'sub' => 'All time', 'icon' => 'file-text', 'tone' => 'bg-app-accent-soft text-app-accent'],
                    ['label' => 'Drafts', 'value' => $quoteStats['draft'], 'sub' => 'Needs attention', 'icon' => 'edit', 'tone' => 'bg-app-warning-soft text-app-warning'],
                    ['label' => 'Sent', 'value' => $quoteStats['sent'], 'sub' => 'Active quotes', 'icon' => 'send', 'tone' => 'bg-app-success-soft text-app-success'],
                ];
            @endphp

            @foreach ($cards as $card)
                <div class="flex items-start gap-3.5 rounded-[8px] border border-app-border bg-app-surface p-[18px] shadow-app-card">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-[8px] {{ $card['tone'] }}">
                        <x-icon :name="$card['icon']" :size="19" :stroke-width="1.8" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-[22px] font-bold tracking-[-0.4px] tabular-nums">{{ $card['value'] }}</p>
                        <p class="text-[13px] font-semibold text-app-muted">{{ $card['label'] }}</p>
                        <p class="text-[12px] text-app-faint">{{ $card['sub'] }}</p>
                    </div>
                </div>
            @endforeach

            <div class="flex items-start gap-3.5 rounded-[8px] border border-app-border bg-app-surface p-[18px] shadow-app-card">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-[8px] bg-app-neutral-soft text-app-muted">
                    <x-icon name="rupee" :size="19" :stroke-width="1.8" />
                </span>
                <div class="min-w-0">
                    <p class="text-[22px] font-bold tracking-[-0.4px] tabular-nums">{{ Money::format($quoteStats['monthValue']) }}</p>
                    <p class="text-[13px] font-semibold text-app-muted">Total Value</p>
                    <p class="text-[12px] text-app-faint">This month</p>
                </div>
            </div>
        </div>
        {{-- Filters (prototype .panel-head tools) --}}
        <x-card :padding="false">
            <form method="GET" action="{{ route('dashboard') }}" x-ref="filters" class="flex flex-wrap items-center gap-2.5 border-b border-app-border px-[18px] py-4">
                <div class="relative text-app-faint">
                    <x-icon name="search" size="14" class="pointer-events-none absolute left-[10px] top-1/2 -translate-y-1/2" />
                    <input
                        type="search"
                        name="search"
                        value="{{ $search }}"
                        placeholder="Search quotes..."
                        aria-label="Search quotes"
                        class="w-[240px] max-[640px]:w-full rounded-[6px] border border-app-border bg-white py-2 pl-[32px] pr-3 text-[13px] text-app-text outline-none transition-colors placeholder:text-app-faint focus:border-app-accent focus:outline-2 focus:outline-[rgba(37,99,235,0.25)]"
                        x-on:input.debounce.350ms="$event.target.form.requestSubmit()"
                    />
                </div>

                <select
                    name="status"
                    aria-label="Filter by status"
                    x-on:change="$event.target.form.requestSubmit()"
                    class="cursor-pointer rounded-[6px] border border-app-border bg-white px-3 py-2 text-[13px] text-app-text focus:border-app-accent focus:outline-2 focus:outline-[rgba(37,99,235,0.25)]"
                >
                    <option value="">All Statuses</option>
                    @foreach ($statusOptions as $value => $label)
                        <option value="{{ $value }}" @selected($status === (string) $value)>{{ $label }}</option>
                    @endforeach
                </select>

                <select
                    name="date"
                    aria-label="Filter by date"
                    x-on:change="$event.target.form.requestSubmit()"
                    class="cursor-pointer rounded-[6px] border border-app-border bg-white px-3 py-2 text-[13px] text-app-text focus:border-app-accent focus:outline-2 focus:outline-[rgba(37,99,235,0.25)]"
                >
                    <option value="">All Time</option>
                    @foreach (['today' => 'Today', 'week' => 'Last 7 days', 'month' => 'This month', 'quarter' => 'Last 3 months'] as $value => $label)
                        <option value="{{ $value }}" @selected($date === $value)>{{ $label }}</option>
                    @endforeach
                </select>

                <a
                    href="{{ route('dashboard') }}"
                    x-on:click="notify('Filters reset')"
                    class="inline-flex cursor-pointer items-center gap-[7px] rounded-[6px] border border-app-border bg-white px-3 py-2 text-[13px] font-medium text-app-text transition-colors hover:bg-app-neutral-soft"
                >
                    <x-icon name="refresh" size="14" />
                    Reset
                </a>

                {{-- Without JS the form still needs a submit path. --}}
                <button type="submit" class="sr-only">Apply filters</button>
            </form>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] border-collapse text-left">
                    <thead>
                        <tr class="border-b border-app-border bg-[#fafafa] text-[11px] uppercase tracking-[0.05em] text-app-faint">
                            <th class="whitespace-nowrap px-[18px] py-[11px] font-semibold">Quote No.</th>
                            <th class="px-[18px] py-[11px] font-semibold">Client</th>
                            <th class="whitespace-nowrap px-[18px] py-[11px] font-semibold">Date</th>
                            <th class="px-[18px] py-[11px] text-right font-semibold">Items</th>
                            <th class="px-[18px] py-[11px] text-right font-semibold">Amount</th>
                            <th class="px-[18px] py-[11px] font-semibold">Status</th>
                            <th class="px-[18px] py-[11px] text-right font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($quotes as $quote)
                            <tr class="border-b border-app-border transition-colors last:border-b-0 hover:bg-[#fafbfd]">
                                <td class="whitespace-nowrap px-[18px] py-[13px]">
                                    <a href="{{ route('quotes.show', $quote) }}" class="text-[13px] font-semibold text-app-accent hover:underline">
                                        {{ $quote->quote_number }}
                                    </a>
                                </td>
                                <td class="px-[18px] py-[13px]">
                                    <a href="{{ route('clients.show', $quote->client_id) }}" class="transition-colors hover:text-app-accent">
                                        <span class="block font-semibold">{{ $quote->clientName() }}</span>
                                        @if ($quote->companyName() !== '')
                                            <span class="block text-[12px] text-app-faint">{{ $quote->companyName() }}</span>
                                        @endif
                                    </a>
                                </td>
                                <td class="whitespace-nowrap px-[18px] py-[13px] text-[12.5px] text-app-faint">
                                    {{ $quote->quote_date?->format('d M Y') }}
                                </td>
                                <td class="px-[18px] py-[13px] text-right tabular-nums text-app-faint">{{ $quote->items_count }}</td>
                                <td class="whitespace-nowrap px-[18px] py-[13px] text-right font-semibold tabular-nums">
                                    {{ Money::format((float) $quote->grand_total) }}
                                </td>
                                <td class="px-[18px] py-[13px]">
                                    <x-badge :status="$quote->status->tone()">{{ $quote->status->label() }}</x-badge>
                                </td>
                                <td class="px-[18px] py-[13px]">
                                    {{-- Kebab dropdown (prototype .row-actions) --}}
                                    <div class="relative text-right" x-data="{ open: false }" @click.outside="open = false">
                                        <button
                                            type="button"
                                            x-on:click="open = !open"
                                            :aria-expanded="open"
                                            aria-haspopup="true"
                                            aria-label="Actions for {{ $quote->quote_number }}"
                                            class="inline-flex size-[30px] cursor-pointer items-center justify-center rounded-[6px] text-app-faint transition-colors hover:bg-app-neutral-soft hover:text-app-text"
                                        >
                                            <x-icon name="more-vertical" size="16" />
                                        </button>

                                        <div
                                            x-show="open"
                                            x-cloak
                                            x-transition:enter="transition ease-out duration-150"
                                            x-transition:enter-start="opacity-0 -translate-y-1"
                                            x-transition:enter-end="opacity-100 translate-y-0"
                                            x-transition:leave="ease-in duration-100"
                                            x-transition:leave-start="opacity-100"
                                            x-transition:leave-end="opacity-0"
                                            class="absolute right-0 top-[calc(100%+4px)] z-40 min-w-[170px] overflow-hidden rounded-[8px] border border-app-border bg-white shadow-[0_8px_24px_rgba(15,23,42,0.10)]"
                                            role="menu"
                                        >
                                            <a href="{{ route('quotes.show', $quote) }}" role="menuitem" class="flex w-full items-center gap-2.5 px-[14px] py-[9px] text-left text-[13px] text-app-text transition-colors hover:bg-app-neutral-soft">
                                                <x-icon name="eye" size="15" :stroke-width="1.8" /> View
                                            </a>
                                            <a href="{{ route('quotes.edit', $quote) }}" role="menuitem" class="flex w-full items-center gap-2.5 px-[14px] py-[9px] text-left text-[13px] text-app-text transition-colors hover:bg-app-neutral-soft">
                                                <x-icon name="edit" size="15" :stroke-width="1.8" /> Edit
                                            </a>
                                            <a href="{{ route('quotes.pdf', $quote) }}" role="menuitem" class="flex w-full items-center gap-2.5 px-[14px] py-[9px] text-left text-[13px] text-app-text transition-colors hover:bg-app-neutral-soft">
                                                <x-icon name="download" size="15" :stroke-width="1.8" /> Download PDF
                                            </a>
                                            <form method="POST" action="{{ route('quotes.duplicate', $quote) }}">
                                                @csrf
                                                <button type="submit" role="menuitem" class="flex w-full cursor-pointer items-center gap-2.5 px-[14px] py-[9px] text-left text-[13px] text-app-text transition-colors hover:bg-app-neutral-soft">
                                                    <x-icon name="copy" size="15" :stroke-width="1.8" /> Duplicate
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('quotes.destroy', $quote) }}" onsubmit="return confirm('Move {{ $quote->quote_number }} to trash?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" role="menuitem" class="flex w-full cursor-pointer items-center gap-2.5 border-t border-app-border px-[14px] py-[9px] text-left text-[13px] text-app-danger transition-colors hover:bg-app-danger-soft">
                                                    <x-icon name="trash" size="15" :stroke-width="1.8" /> Move to trash
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <div class="flex flex-col items-center gap-3 px-6 py-14 text-center">
                                        <span class="flex size-16 items-center justify-center rounded-[14px] bg-app-accent-soft text-app-accent">
                                            <x-icon name="file-plus" :size="28" :stroke-width="1.6" />
                                        </span>
                                        <div>
                                            <h3 class="text-base font-bold text-app-text">No quotes found</h3>
                                            <p class="mt-1 text-[12.5px] text-app-muted">
                                                {{ $hasFilters ? 'Try a different search or filter.' : 'Create your first quotation to get started.' }}
                                            </p>
                                        </div>
                                        <x-button icon="plus" class="mt-1" x-on:click="openTemplatePicker()">Create New Quote</x-button>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination footer (prototype .pagination) --}}
            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-app-border px-[18px] py-3">
                <span class="text-[12.5px] text-app-faint">
                    Showing {{ $quotes->firstItem() ?? 0 }}–{{ $quotes->lastItem() ?? 0 }} of {{ $quotes->total() }} quotes
                </span>

                @if ($quotes->hasPages())
                    <div class="flex flex-wrap items-center gap-1.5">
                        @for ($page = 1; $page <= $quotes->lastPage(); $page++)
                            <a
                                href="{{ $quotes->url($page) }}"
                                @if ($page === $quotes->currentPage()) aria-current="page" @endif
                                class="inline-flex size-8 items-center justify-center rounded-[6px] border text-[13px] font-medium transition-colors
                                    {{ $page === $quotes->currentPage()
                                        ? 'border-app-accent bg-app-accent text-white'
                                        : 'border-app-border bg-white text-app-muted hover:bg-app-neutral-soft hover:text-app-text' }}"
                            >{{ $page }}</a>
                        @endfor
                    </div>
                @endif
            </div>
        </x-card>
        {{-- Template picker (prototype #templatePage overlay) --}}
        <div
            x-show="templatePickerOpen"
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-[70] overflow-y-auto bg-app-text/40 px-4 py-8"
            role="dialog"
            aria-modal="true"
            aria-labelledby="template-picker-title"
        >
            <div class="mx-auto w-full max-w-3xl rounded-[8px] border border-app-border bg-white p-6 shadow-app-sheet">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <button
                        type="button"
                        x-on:click="closeTemplatePicker()"
                        class="inline-flex cursor-pointer items-center gap-2 text-[13px] font-semibold text-app-muted transition-colors hover:text-app-text"
                    >
                        <x-icon name="chevron-left" size="15" :stroke-width="2.2" />
                        Back to Quotes
                    </button>
                    <span class="text-[12.5px] text-app-faint">Step 1 of 3 — Template Selection</span>
                </div>

                <h2 id="template-picker-title" class="mt-4 text-[22px] font-bold tracking-[-0.3px]">Choose a Quote Template</h2>
                <p class="mt-1 text-[13px] text-app-muted">Select a template format to start building your quotation.</p>

                @if ($templates->isEmpty())
                    <div class="mt-5 flex flex-col items-center gap-3 rounded-[8px] border border-dashed border-app-border bg-app-bg px-6 py-10 text-center">
                        <p class="text-[13px] text-app-muted">No templates yet — create one to carry your letterhead and terms.</p>
                        <x-button :href="route('templates.create')" icon="plus">Create a template</x-button>
                    </div>
                @else
                    <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($templates as $template)
                            @php $ink = $template->inkColours(); @endphp

                            <a
                                href="{{ route('quotes.build', ['template_id' => $template->id]) }}"
                                class="group overflow-hidden rounded-[8px] border border-app-border bg-white transition-colors hover:border-app-accent"
                            >
                                <div class="flex flex-col gap-1.5 border-b border-app-border p-4" style="background: {{ $ink['soft'] }}">
                                    <span class="h-1.5 w-[55%] rounded-full" style="background: {{ $ink['ink'] }}"></span>
                                    <span class="h-1.5 w-[80%] rounded-full" style="background: {{ $ink['line'] }}"></span>
                                    <span class="h-1.5 w-[70%] rounded-full" style="background: {{ $ink['line'] }}"></span>
                                    <span class="h-1.5 w-[40%] rounded-full" style="background: {{ $ink['line'] }}"></span>
                                </div>
                                <div class="p-3.5">
                                    <div class="flex items-center gap-2">
                                        <span class="size-2.5 shrink-0 rounded-full" style="background: {{ $ink['ink'] }}" aria-hidden="true"></span>
                                        <p class="truncate text-[14px] font-bold">{{ $template->name }}</p>
                                        @if ($template->is_default)
                                            <x-badge status="accent" class="ml-auto">Default</x-badge>
                                        @endif
                                    </div>
                                    <p class="mt-0.5 truncate text-[12.5px] text-app-muted">
                                        {{ $template->doc_title ?: 'Classic quotation layout with GST summary.' }}
                                    </p>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- Toast (prototype #toast) --}}
        <div
            x-show="toastVisible"
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0 translate-y-2"
            class="fixed bottom-6 right-6 z-[200] flex items-center gap-2.5 rounded-[6px] bg-app-text px-[18px] py-[11px] text-[13px] font-medium text-white shadow-app-toast"
            role="status"
            aria-live="polite"
        >
            <x-icon name="check" size="15" :stroke-width="2.2" />
            <span x-text="toastMessage"></span>
        </div>
    </div>
</x-app-layout>
