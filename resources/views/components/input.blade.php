{{-- Labelled text input with its validation error. --}}
@props(['name', 'label', 'type' => 'text', 'value' => null])
@php($id = $attributes->get('id', 'field-'.$name))
<div>
    @isset($labelAside)
        <div class="flex items-center justify-between">
            <label for="{{ $id }}" class="ag-label">{{ $label }}</label>
            {{ $labelAside }}
        </div>
    @else
        <label for="{{ $id }}" class="ag-label">{{ $label }}</label>
    @endisset
    <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}"
        @if ($type !== 'password') value="{{ old($name, $value) }}" @endif
        @error($name) aria-invalid="true" aria-describedby="{{ $id }}-error" @enderror
        {{ $attributes->except('id')->class(['ag-input', 'border-ag-danger' => $errors->has($name)]) }}>
    @error($name)
        <p id="{{ $id }}-error" class="mt-1.5 text-[13px] text-ag-danger-text">{{ $message }}</p>
    @enderror
</div>
