@props(['name', 'label', 'value' => null, 'type' => 'text', 'required' => false, 'maxlength' => null])
@php
    $previousValue=old($name,$value);
    $displayValue=is_scalar($previousValue) ? (string)$previousValue : '';
@endphp
<div class="prospect-field">
    <label for="{{ $name }}">{{ $label }}@if($required) <span aria-hidden="true">*</span>@endif</label>
    @if($type === 'textarea')
        <textarea id="{{ $name }}" name="{{ $name }}" rows="3" @if($maxlength) maxlength="{{ $maxlength }}" @endif aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}" @if($errors->has($name)) aria-describedby="error-{{ $name }}" @endif>{{ $displayValue }}</textarea>
    @else
        <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ $displayValue }}" @required($required) @if($maxlength) maxlength="{{ $maxlength }}" @endif aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}" @if($errors->has($name)) aria-describedby="error-{{ $name }}" @endif>
    @endif
    @error($name)<p id="error-{{ $name }}" class="field-error">{{ $message }}</p>@enderror
</div>
