@use('App\Enums\AccentPalette')
@use('Illuminate\Support\Str')

<x-app-layout title="Quote Templates">
    <x-section-header title="Quote Templates" description="Reusable, branded layouts for your quotations.">
        <x-slot name="actions">
            <a href="{{ route('templates.trash') }}" class="text-[13px] font-medium text-app-muted hover:text-app-text">Trash</a>
            <x-button :href="route('templates.create')" icon="file-plus">New Template</x-button>
        </x-slot>
    </x-section-header>

    <x-card class="mb-4">
        <form method="GET" action="{{ route('templates.index') }}" class="flex flex-wrap items-end gap-3">
            <div class="min-w-[240px] flex-1">
                <x-input name="search" label="Search" placeholder="Template or company name" :value="$search" />
            </div>
            <x-button type="submit" variant="ghost" icon="search">Search</x-button>
            @if ($search !== '')
                <a href="{{ route('templates.index') }}" class="pb-2 text-[13px] font-medium text-app-muted hover:text-app-text">Reset</a>
            @endif
        </form>
    </x-card>

    @if ($templates->isEmpty())
        <x-card>
            <x-empty-state
                title="No templates yet"
                description="Create a branded template to start generating quotations."
                :action="'<a href=\"'.route('templates.create').'\" class=\"inline-flex items-center justify-center gap-2 rounded-[8px] bg-app-accent px-4 py-2 text-[13px] font-semibold text-white hover:bg-app-accent-hover\">Create your first template</a>'"
            />
        </x-card>
    @else
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($templates as $template)
                @php $ink = $template->inkColours(); @endphp

                <x-card :padding="false" class="flex flex-col overflow-hidden">
                    {{-- A4 thumbnail: real markup, scaled down --}}
                    <div class="flex justify-center border-b border-app-border bg-app-bg p-4" style="height: 190px; overflow: hidden;">
                        <div style="transform: scale(0.42); transform-origin: top center; width: 210mm;">
                            <x-a4-sheet :doc="[
                                'accent' => $ink,
                                'align' => $template->header_alignment?->value ?? 'center',
                                'doc_title' => $template->doc_title,
                                'company' => [
                                    'name' => $template->company_name,
                                    'display_name' => $template->displayName(),
                                    'tagline' => $template->tagline,
                                    'address' => $template->address,
                                    'company_gstin' => $template->company_gstin,
                                    'mobile_1' => $template->mobile_1,
                                    'email' => $template->email,
                                    'stamp_place' => $template->stamp_place,
                                    'authorized_person' => $template->authorized_person,
                                ],
                                'client' => ['name' => 'Sample Client Pvt. Ltd.'],
                                'meta' => ['no' => 'QT-2026-00001', 'date' => '—', 'valid' => '—'],
                                'items' => [],
                                'totals' => [],
                                'terms' => [],
                            ]" :preview="false" />
                        </div>
                    </div>

                    <div class="flex flex-1 flex-col p-4">
                        <div class="flex items-start justify-between gap-2">
                            <h3 class="text-[14px] font-bold leading-snug">
                                <a href="{{ route('templates.show', $template) }}" class="hover:text-app-accent">{{ $template->name }}</a>
                            </h3>
                            @if ($template->is_default)
                                <x-badge status="accent">Default</x-badge>
                            @endif
                        </div>

                        <p class="mt-0.5 text-xs text-app-muted">{{ $template->company_name }}</p>
                        <p class="mt-1 text-xs text-app-faint">
                            {{ $template->quotes_count }} {{ Str::plural('quote', $template->quotes_count) }}
                            @if ($template->last_used_at)
                                &middot; used {{ $template->last_used_at->format('d M Y') }}
                            @else
                                &middot; never used
                            @endif
                        </p>

                        <div class="mt-3 flex flex-wrap gap-1.5">
                            @foreach (AccentPalette::cases() as $palette)
                                @if ($palette === $template->accent_color)
                                    <span class="size-4 rounded-full ring-2 ring-app-border" style="background: {{ $palette->colours()['ink'] }}" title="{{ $palette->label() }}"></span>
                                @endif
                            @endforeach
                            <span class="text-[11px] text-app-faint">{{ $template->header_alignment?->label() }} header</span>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2 border-t border-app-border pt-3">
                            <x-button :href="route('templates.show', $template)" variant="ghost" icon="eye">View</x-button>
                            <x-button :href="route('templates.edit', $template)" variant="subtle" icon="edit">Edit</x-button>

                            <form method="POST" action="{{ route('templates.duplicate', $template) }}">
                                @csrf
                                <x-button type="submit" variant="subtle" icon="copy">Duplicate</x-button>
                            </form>

                            @unless ($template->is_default)
                                <form method="POST" action="{{ route('templates.set-default', $template) }}">
                                    @csrf
                                    <x-button type="submit" variant="subtle" icon="star">Set Default</x-button>
                                </form>
                            @endunless

                            <form method="POST" action="{{ route('templates.destroy', $template) }}" class="ml-auto" onsubmit="return confirm('Move &quot;{{ addslashes($template->name) }}&quot; to trash?');">
                                @csrf
                                @method('DELETE')
                                <x-button type="submit" variant="danger" icon="trash">Trash</x-button>
                            </form>
                        </div>
                    </div>
                </x-card>
            @endforeach
        </div>

        <div class="mt-4">{{ $templates->links() }}</div>
    @endif
</x-app-layout>
