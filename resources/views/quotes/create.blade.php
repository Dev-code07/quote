{{-- Step 1: pick a template before the builder opens (quoteflow_dashboard overlay). --}}
<x-app-layout title="New Quotation">
    <x-section-header title="Create Quotation" description="Choose a template to start from." back="{{ route('quotes.index') }}" />

    @if ($templates->isEmpty())
        <x-card>
            <x-empty-state
                title="No templates available"
                description="Create a quotation template first — it carries your letterhead, terms and branding."
                :action="'<a href=\"'.route('templates.create').'\" class=\"inline-flex items-center justify-center rounded-[8px] bg-app-accent px-4 py-2 text-[13px] font-semibold text-white hover:bg-app-accent-hover\">Create a template</a>'"
            />
        </x-card>
    @else
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($templates as $item)
                @php $ink = $item->inkColours(); @endphp

                <x-card :padding="false" class="flex flex-col overflow-hidden">
                    <div class="flex items-center gap-2 border-b border-app-border px-4 py-3">
                        <span class="size-3 rounded-full" style="background: {{ $ink['ink'] }}" aria-hidden="true"></span>
                        <div class="min-w-0">
                            <p class="truncate text-[13.5px] font-bold">{{ $item->name }}</p>
                            <p class="truncate text-xs text-app-faint">{{ $item->company_name }}</p>
                        </div>
                        @if ($item->is_default)
                            <x-badge status="accent" class="ml-auto">Default</x-badge>
                        @endif
                    </div>

                    <div class="flex-1 p-4">
                        <dl class="space-y-1 text-xs text-app-muted">
                            <div class="flex justify-between gap-2">
                                <dt class="text-app-faint">GST</dt>
                                <dd>{{ rtrim(rtrim(number_format((float) $item->default_gst_rate, 2), '0'), '.') }}%</dd>
                            </div>
                            <div class="flex justify-between gap-2">
                                <dt class="text-app-faint">Used</dt>
                                <dd>{{ $item->last_used_at?->format('d M Y') ?? 'Never' }}</dd>
                            </div>
                        </dl>

                        <x-button :href="route('quotes.build', ['template_id' => $item->id])" class="mt-4 w-full">
                            Use this template
                        </x-button>
                    </div>
                </x-card>
            @endforeach
        </div>
    @endif
</x-app-layout>