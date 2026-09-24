@props(['name', 'label' => null, 'required' => false, 'hint' => null, 'rows' => 3])

{{-- `class` positions the field within a grid; control styling is fixed. --}}
<div class="flex flex-col gap-1.5 {{ $attributes->get('class') }}">
    @if ($label)
        <label for="{{ $name }}" class="text-xs font-medium text-app-muted">
            {{ $label }}
            @if ($required)<span class="text-app-danger" aria-hidden="true">*</span>@endif
        </label>
    @endif

    {{--
        A textarea cannot carry its content through a `value` attribute, so the
        value is rendered as the element's text. The previous version rendered
        old($name) only, which silently dropped the value passed by the caller
        and left every seeded text field looking empty.
    --}}
    <textarea
        id="{{ $name }}"
        name="{{ $name }}"
        rows="{{ $rows }}"
        @if ($required) required @endif
        @error($name) aria-invalid="true" @enderror
        {{ $attributes->except(['class', 'name', 'label', 'required', 'hint', 'rows', 'value'])->merge([
            'class' => 'block w-full rounded-[6px] border bg-white px-3 py-2 text-[13px] text-app-text placeholder:text-app-faint focus:border-app-accent focus:ring-2 focus:ring-app-accent/25'
                . ($errors->has($name) ? ' border-app-danger' : ' border-app-border'),
        ]) }}
    >{{ old($name, $attributes->get('value')) }}</textarea>

    @error($name)
        <p class="text-xs text-app-danger">{{ $message }}</p>
    @enderror

    @if ($hint && ! $errors->has($name))
        <p class="text-xs text-app-faint">{{ $hint }}</p>
    @endif
</div>