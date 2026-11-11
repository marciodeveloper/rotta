@props(['registration', 'context' => [], 'mode' => 'row'])
@php
    $detailUrl = route('admin.pre-registrations.show', ['preRegistration' => $registration, ...$context]);
    $labels = ['approved' => 'Aprovar', 'rejected' => 'Rejeitar', 'archived' => 'Arquivar', 'under_review' => 'Reabrir análise'];
    $pending = $registration->status === \App\Models\PreRegistration::STATUS_PENDING_EMAIL && $registration->email_confirmed_at === null;
@endphp
@if($mode === 'row')
<div class="crm-row-actions" data-row-actions>
    <a href="{{ $detailUrl }}" class="crm-icon-button crm-context-link" aria-label="Abrir detalhes de {{ $registration->business_name }}" title="Abrir detalhes">
        <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
    </a>
    <button type="button" class="crm-icon-button" aria-label="Mais ações para {{ $registration->business_name }}" aria-haspopup="menu" aria-expanded="false" aria-controls="actions-{{ $registration->id }}" data-menu-toggle>
        <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="12" cy="19" r="1.8"/></svg>
    </button>
    <div id="actions-{{ $registration->id }}" class="crm-action-menu" popover="auto" role="menu" aria-label="Ações do cadastro" data-action-menu>
        <a href="{{ $detailUrl }}" class="crm-menu-item crm-context-link" role="menuitem">Abrir ficha completa</a>
        <a href="{{ $detailUrl }}#historico" class="crm-menu-item crm-context-link" role="menuitem">Acessar histórico</a>
        @can('manage-pre-registration', $registration)
            @if($pending)<x-admin-action :registration="$registration" label="Reenviar confirmação" kind="resend" :menu="true" />@endif
            @foreach(\App\Models\PreRegistration::ALLOWED_TRANSITIONS[$registration->status] as $target)
                @if($target !== 'approved' || $registration->email_confirmed_at !== null)<x-admin-action :registration="$registration" :target="$target" :label="$labels[$target]" :menu="true" />@endif
            @endforeach
        @endcan
    </div>
</div>
@else
<div class="crm-detail-actions" role="group" aria-label="Ações administrativas">
    @can('manage-pre-registration', $registration)
        @if($pending)<x-admin-action :registration="$registration" label="Reenviar confirmação" kind="resend" />@endif
        @foreach(\App\Models\PreRegistration::ALLOWED_TRANSITIONS[$registration->status] as $target)
            @if($target !== 'approved' || $registration->email_confirmed_at !== null)<x-admin-action :registration="$registration" :target="$target" :label="$labels[$target]" />@endif
        @endforeach
    @endcan
    <a href="#historico" class="button button-secondary">Ver histórico</a>
</div>
@if($pending || ($registration->status === 'under_review' && $registration->email_confirmed_at === null))<p class="crm-action-hint">A aprovação ficará disponível após a confirmação do endereço de e-mail pelo interessado.</p>@endif
@endif
