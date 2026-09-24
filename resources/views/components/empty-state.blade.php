@props([
    'title',
    'description' => null,
    'icon' => null,
    'action' => null,
])

<div class="flex flex-col items-center justify-center gap-3 px-6 py-14 text-center">
    @if ($icon)
        <span class="flex size-16 items-center justify-center rounded-[14px] bg-app-accent-soft text-app-accent">
            <x-icon :name="$icon" :size="28" />
        </span>
    @endif

    <div>
        <h3 class="text-base font-bold text-app-text">{{ $title }}</h3>
        @if ($description)
            <p class="mt-1 text-[12.5px] text-app-muted">{{ $description }}</p>
        @endif
    </div>

    @if ($action)
        <div class="mt-2">{{ $action }}</div>
    @endif
</div>