@props(['status' => 'neutral'])

@php
    $tones = [
        'neutral' => 'bg-app-neutral-soft text-app-muted',
        'accent' => 'bg-app-accent-soft text-app-accent',
        'success' => 'bg-app-success-soft text-app-success',
        'warning' => 'bg-app-warning-soft text-app-warning',
        'danger' => 'bg-app-danger-soft text-app-danger',
    ];
    $class = $tones[$status] ?? $tones['neutral'];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold ' . $class]) }}>
    <span class="size-1.5 rounded-full bg-current" aria-hidden="true"></span>
    {{ $slot }}
</span>