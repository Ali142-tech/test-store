@props(['name', 'label', 'type' => 'text', 'value' => null, 'required' => false, 'hint' => null])
@php $key = str_replace(['[', ']'], ['.', ''], $name); $id = str_replace(['[', ']'], ['-', ''], $name); @endphp
<div>
    <label for="{{ $id }}" class="text-sm font-medium">{{ $label }}@if ($required) <span class="text-danger" aria-hidden="true">*</span>@endif</label>
    <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" value="{{ $type === 'password' ? '' : old($key, $value) }}" @required($required)
           {{ $attributes->merge(['class' => 'field mt-1.5']) }} @error($key) aria-invalid="true" @enderror>
    @if ($hint) <p class="mt-1 text-xs text-ink-soft">{{ $hint }}</p> @endif
    @error($key) <p class="mt-1 text-xs text-danger" role="alert">{{ $message }}</p> @enderror
</div>
