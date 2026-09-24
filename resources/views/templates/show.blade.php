<x-app-layout :title="$template->name">
    <x-section-header :title="$template->name" :description="$template->company_name" back="{{ route('templates.index') }}">
        <x-slot name="actions">
            <x-button :href="route('templates.edit', $template)" variant="ghost">Edit</x-button>

            <form method="POST" action="{{ route('templates.duplicate', $template) }}">
                @csrf
                <x-button type="submit" variant="subtle">Duplicate</x-button>
            </form>

            @unless ($template->is_default)
                <form method="POST" action="{{ route('templates.set-default', $template) }}">
                    @csrf
                    <x-button type="submit" variant="subtle">Set Default</x-button>
                </form>
            @endunless

            <form method="POST" action="{{ route('templates.destroy', $template) }}" onsubmit="return confirm('Move this template to trash?');">
                @csrf
                @method('DELETE')
                <x-button type="submit" variant="danger">Trash</x-button>
            </form>
        </x-slot>
    </x-section-header>

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
        {{-- Preview stage --}}
        <div class="xl:col-span-2">
            <x-card :padding="false">
                <div class="flex items-center justify-between gap-2 border-b border-app-border px-4 py-3">
                    <div class="flex items-center gap-2">
                        <h3 class="text-[13px] font-bold">A4 Preview</h3>
                        @if ($template->is_default)
                            <x-badge status="accent">Default</x-badge>
                        @endif
                    </div>

                    <div class="flex items-center gap-1" x-data="{ zoom: 60 }">
                        <button type="button" class="flex size-7 items-center justify-center rounded-[6px] border border-app-border text-app-muted hover:bg-app-neutral-soft"
                            x-on:click="zoom = Math.max(30, zoom - 10)" aria-label="Zoom out">&minus;</button>
                        <span class="w-11 text-center text-xs font-semibold tabular-nums" x-text="zoom + '%'">60%</span>
                        <button type="button" class="flex size-7 items-center justify-center rounded-[6px] border border-app-border text-app-muted hover:bg-app-neutral-soft"
                            x-on:click="zoom = Math.min(120, zoom + 10)" aria-label="Zoom in">+</button>
                        <button type="button" class="ml-2 rounded-[6px] border border-app-border px-2 py-1 text-xs font-semibold text-app-muted hover:bg-app-neutral-soft"
                            x-on:click="window.print()">Print</button>
                    </div>
                </div>

                <div class="overflow-auto bg-app-bg p-6" x-data>
                    <div class="mx-auto origin-top" x-bind:style="`transform: scale(${zoom / 100}); width: ${210 * 96 / 25.4}px; margin-bottom: calc((100% - 100%) * -1);`">
                        <x-a4-sheet :doc="$previewDoc" />
                    </div>
                </div>
            </x-card>
        </div>

        {{-- Details --}}
        <div class="space-y-4">
            <x-card>
                <h3 class="mb-3 text-[13px] font-bold uppercase tracking-wide text-app-faint">Details</h3>
                <dl class="space-y-2 text-[13px]">
                    @php
                        $rows = [
                            'Document title' => $template->doc_title,
                            'Letterhead name' => $template->letterhead_display_name ?: $template->company_name,
                            'Company GSTIN' => $template->company_gstin,
                            'Tagline' => $template->tagline,
                            'Email' => $template->email,
                            'Mobile' => collect([$template->mobile_1, $template->mobile_2])->filter()->join(' / '),
                            'Stamp place' => $template->stamp_place,
                            'Header alignment' => $template->header_alignment?->label(),
                            'Accent colour' => $template->accent_color?->label(),
                            'Default GST' => rtrim(rtrim(number_format((float) $template->default_gst_rate, 2), '0'), '.').'%',
                            'Authorised' => collect([$template->authorized_person, $template->designation])->filter()->join(', '),
                        ];
                    @endphp

                    @foreach ($rows as $label => $value)
                        <div class="flex justify-between gap-3">
                            <dt class="shrink-0 text-app-faint">{{ $label }}</dt>
                            <dd class="text-right font-medium">{{ $value ?: '—' }}</dd>
                        </div>
                    @endforeach

                    <div class="flex justify-between gap-3">
                        <dt class="text-app-faint">Address</dt>
                        <dd class="text-right font-medium">{{ $template->address ?: '—' }}</dd>
                    </div>
                </dl>
            </x-card>

            <x-card>
                <h3 class="mb-3 text-[13px] font-bold uppercase tracking-wide text-app-faint">Usage</h3>
                <dl class="space-y-2 text-[13px]">
                    <div class="flex justify-between gap-3">
                        <dt class="text-app-faint">Quotations created</dt>
                        <dd class="font-semibold">{{ $quoteCount }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-app-faint">Last used</dt>
                        <dd class="font-medium">{{ $template->last_used_at?->format('d M Y') ?? 'Never' }}</dd>
                    </div>
                </dl>

                <p class="mt-3 border-t border-app-border pt-3 text-xs text-app-faint">
                    Editing this template never changes quotations already created from it — each quote stores its own copy of the letterhead and terms.
                </p>
            </x-card>
        </div>
    </div>
</x-app-layout>