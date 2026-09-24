@props(['padding' => true])

<div {{ $attributes->merge(['class' => 'rounded-[8px] border border-app-border bg-app-surface shadow-app-card' . ($padding ? ' p-5' : '')]) }}>
    {{ $slot }}
</div>