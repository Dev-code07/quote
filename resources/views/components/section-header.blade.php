@props(['title', 'description' => null, 'back' => null])

<div {{ $attributes->merge(['class' => 'mb-5 flex flex-wrap items-end justify-between gap-3']) }}>
    <div>
        @if ($back)
            <a href="{{ $back }}" class="mb-1.5 inline-flex items-center gap-1.5 text-[13px] font-semibold text-app-muted transition-colors hover:text-app-text">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5"/><path d="M12 19l-7-7 7-7"/></svg>
                Back
            </a>
        @endif
        <h2 class="text-[17px] font-bold tracking-tight text-app-text">{{ $title }}</h2>
        @if ($description)
            <p class="mt-0.5 text-[12.5px] text-app-muted">{{ $description }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>