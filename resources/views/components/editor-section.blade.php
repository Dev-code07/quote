@props([
    'number',
    'title',
    'hint' => null,
])

{{--
    One numbered block of the template editor.

    Mirrors .section / .section-head / .step-num in
    docs/quoteflow_template_editor.html: a white card, a 15px 20px header
    carrying the step number and an optional right-aligned hint, and a 20px body.
--}}
<section {{ $attributes->merge(['class' => 'mb-5 overflow-hidden rounded-[8px] border border-app-border bg-app-surface']) }}>
    <header class="flex items-center justify-between gap-3 border-b border-app-border px-5 py-[15px]">
        <h2 class="flex items-center gap-2.5 text-[15px] font-bold text-app-text">
            <span class="inline-flex size-[22px] shrink-0 items-center justify-center rounded-[6px] bg-app-accent-soft text-[12px] font-bold text-app-accent">
                {{ $number }}
            </span>
            {{ $title }}
        </h2>

        @if ($hint)
            <span class="hidden text-[12.5px] text-app-faint sm:block">{{ $hint }}</span>
        @endif
    </header>

    <div class="p-5">
        {{ $slot }}
    </div>
</section>