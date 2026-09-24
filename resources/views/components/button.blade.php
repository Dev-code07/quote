@props([
    'variant' => 'primary',
    'href' => null,
    'type' => 'button',
    'icon' => null,
])

@php
    $base = 'inline-flex items-center justify-center gap-2 rounded-[8px] px-4 text-[13px] font-semibold transition-colors disabled:cursor-not-allowed disabled:opacity-50';
    $variants = [
        'primary' => 'bg-app-accent text-white hover:bg-app-accent-hover',
        'ghost' => 'border border-app-border bg-white text-app-muted hover:bg-app-neutral-soft hover:text-app-text',
        'danger' => 'bg-app-danger text-white hover:brightness-110',
        'subtle' => 'bg-app-neutral-soft text-app-text hover:bg-app-border',
    ];
    $classes = $base . ' ' . ($variants[$variant] ?? $variants['primary']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<span class="shrink-0" aria-hidden="true">{!! $icon !!}</span>@endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<span class="shrink-0" aria-hidden="true">{!! $icon !!}</span>@endif
        {{ $slot }}
    </button>
@endif