@props([
    'variant' => 'primary',
    'href' => null,
    'type' => 'button',
    'icon' => null,
    'iconSize' => 16,
])

@php
    $base = 'inline-flex items-center justify-center gap-[7px] whitespace-nowrap rounded-[6px] border border-transparent py-[9px] pl-[16px] pr-[16px] text-[13px] font-semibold leading-none transition-colors disabled:cursor-not-allowed disabled:opacity-50';
    $variants = [
        'primary' => 'bg-app-accent text-white hover:bg-app-accent-hover',
        'ghost' => 'border-app-border bg-white text-app-text hover:bg-app-neutral-soft',
        'danger' => 'bg-app-danger text-white hover:brightness-110',
        'subtle' => 'bg-app-neutral-soft text-app-text hover:bg-app-border',
    ];
    $classes = $base . ' ' . ($variants[$variant] ?? $variants['primary']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-icon :name="$icon" :size="$iconSize" class="shrink-0" />@endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-icon :name="$icon" :size="$iconSize" class="shrink-0" />@endif
        {{ $slot }}
    </button>
@endif