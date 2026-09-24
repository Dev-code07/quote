@props(['title', 'description' => null, 'back' => null])

<div {{ $attributes->merge(['class' => 'mb-[22px] flex flex-wrap items-end justify-between gap-4']) }}>
    <div>
        @if ($back)
            <a href="{{ $back }}" class="mb-1.5 inline-flex items-center gap-1.5 text-[13px] font-semibold text-app-muted transition-colors hover:text-app-text">
                <x-icon name="chevron-left" size="14" />
                Back
            </a>
        @endif
        <h2 class="text-[24px] font-bold tracking-[-0.3px] text-app-text">{{ $title }}</h2>
        @if ($description)
            <p class="mt-0.5 text-[12.5px] text-app-muted">{{ $description }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>