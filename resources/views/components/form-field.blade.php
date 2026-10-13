@props(['name', 'label', 'type' => 'text', 'value' => '', 'autocomplete' => 'off', 'required' => true])
@php($displayValue = old($name, $value))
<div>
    <label for="{{ $name }}" class="block text-sm font-semibold text-slate-700 mb-1">{{ $label }}</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ $type === 'password' || !is_scalar($displayValue) ? '' : $displayValue }}"
        autocomplete="{{ $autocomplete }}" @required($required)
        aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}" @if($errors->has($name)) aria-describedby="{{ $name }}-error" @endif
        {{ $attributes->merge(['class' => 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-slate-800']) }}>
    @error($name)<p id="{{ $name }}-error" class="mt-1 text-sm text-rose-700">{{ $message }}</p>@enderror
</div>
