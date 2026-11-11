@props(['registration', 'target' => null, 'label', 'kind' => 'status', 'menu' => false])
<button type="button" {{ $attributes->class(['crm-action-trigger', 'crm-menu-item' => $menu, 'button button-secondary' => !$menu]) }}
    @if($menu) role="menuitem" @endif
    data-crm-action="{{ $kind }}"
    data-action-url="{{ $kind === 'resend' ? route('admin.confirmation.resend', $registration) : route('admin.status.update', $registration) }}"
    data-record-name="{{ $registration->business_name }}" data-current-status="{{ \App\Models\PreRegistration::STATUS_LABELS[$registration->status] }}"
    data-status-version="{{ $registration->status_version }}" data-target-status="{{ $target }}"
    data-target-label="{{ $target ? \App\Models\PreRegistration::STATUS_LABELS[$target] : 'Reenvio de confirmação' }}"
    data-action-label="{{ $label }}">{{ $label }}</button>
