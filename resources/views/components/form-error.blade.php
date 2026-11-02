@props(['field', 'target' => null])

@error($field)
    <p
        id="server-error-{{ str_replace(['.', '*'], '-', $field) }}"
        class="field-error"
        data-server-error
        data-server-error-for="{{ $target ?? $field }}"
        aria-live="polite"
    >{{ $message }}</p>
@enderror
