@props(['label'])

<div {{ $attributes->merge(['class' => 'text-xs text-app-faint']) }}>
    {{ $label }}
</div>