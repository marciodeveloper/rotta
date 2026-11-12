@extends('layouts.app')
@section('title', $registration->business_name)
@section('content')
<article class="admin-shell crm-detail">
    <x-admin-navigation />
    <a class="crm-back" href="{{ route('admin.dashboard', $listContext) }}">← Voltar aos resultados</a>
    <header class="admin-heading"><div><div class="eyebrow"><span></span> Ficha do interessado</div><h1>{{ $registration->business_name }}</h1><p>{{ $registration->category }} · {{ $registration->city }}/{{ $registration->state }}</p></div><span class="crm-badge" data-status="{{ $registration->status }}">{{ \App\Models\PreRegistration::STATUS_LABELS[$registration->status] }}</span></header>
    <x-record-actions :registration="$registration" :context="$listContext" mode="detail" />
    @if(session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="alert alert-error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <nav class="crm-detail-nav" aria-label="Seções da ficha"><a href="#identificacao">Identificação</a><a href="#contato">Contato</a><a href="#localizacao">Localização</a><a href="#midias">Mídias</a><a href="#acompanhamento">Acompanhamento</a><a href="#privacidade">Privacidade</a></nav>
    <div class="crm-detail-grid">
        <section class="crm-card" id="identificacao"><h2>Identificação</h2><dl>
            <div><dt>Tipo de interessado</dt><dd>{{ $registration->applicant_type === 'company' ? 'Empresa' : 'Profissional' }}</dd></div>
            <div><dt>Nome comercial ou profissional</dt><dd>{{ $registration->business_name }}</dd></div><div><dt>Categoria</dt><dd>{{ $registration->category }}</dd></div><div><dt>Descrição</dt><dd class="crm-pre-wrap">{{ $registration->short_description }}</dd></div>
        </dl></section>
        <section class="crm-card" id="contato"><h2>Responsável e contato</h2><dl>
            <div><dt>Responsável</dt><dd>{{ $registration->responsible_name }}</dd></div><div><dt>E-mail</dt><dd>{{ $registration->email }}</dd></div><div><dt>Telefone</dt><dd>{{ $registration->phone }}</dd></div><div><dt>Website</dt><dd>@if($registration->website && in_array(strtolower(parse_url($registration->website, PHP_URL_SCHEME) ?? ''), ['http','https'], true))<a href="{{ $registration->website }}" target="_blank" rel="noopener noreferrer">{{ $registration->website }}</a>@else{{ $registration->website ?: 'Não informado' }}@endif</dd></div><div><dt>Instagram</dt><dd>{{ $registration->instagram ?: 'Não informado' }}</dd></div>
        </dl></section>
        <section class="crm-card" id="localizacao"><h2>Localização</h2><dl>
            <div><dt>Modalidade</dt><dd>{{ $registration->address_mode === 'public_address' ? 'Endereço público' : 'Área de atendimento' }}</dd></div>
            @if($registration->address_mode === 'public_address')
                @foreach(['postal_code'=>'CEP','street'=>'Logradouro','street_number'=>'Número','complement'=>'Complemento','neighborhood'=>'Bairro'] as $field => $label)<div><dt>{{ $label }}</dt><dd>{{ $registration->{$field} ?: 'Não informado' }}</dd></div>@endforeach
            @else<div><dt>Área de atendimento</dt><dd>{{ $registration->service_area ?: 'Não informada' }}</dd></div>@endif
            <div><dt>Cidade / UF</dt><dd>{{ $registration->city }}/{{ $registration->state }}</dd></div>
            @if($registration->latitude !== null && $registration->longitude !== null)<div><dt>Coordenadas informadas</dt><dd>{{ $registration->latitude }}, {{ $registration->longitude }}</dd></div>@endif
        </dl></section>
        <section class="crm-card" id="privacidade"><h2>Privacidade</h2><dl>
            <div><dt>Versão do aviso associada</dt><dd>{{ $registration->privacy_notice_version }}</dd></div>
            <div><dt>Registro do aceite</dt><dd>Versão associada ao pré-cadastro recebido em {{ $registration->created_at->format('d/m/Y H:i') }}. Não há data separada de aceite armazenada.</dd></div>
            <div><dt>Preferência de marketing</dt><dd>{{ $registration->marketing_opt_in ? 'Optou por receber novidades' : 'Não optou por receber novidades' }}</dd></div>
        </dl></section>
        <section class="crm-card crm-card-wide" id="midias"><h2>Mídias privadas</h2><div class="crm-media-grid">
            @forelse($registration->media as $media)
                <figure><a href="{{ route('admin.media.show', ['preRegistration'=>$registration,'media'=>$media->id]) }}" target="_blank" rel="noopener"><img src="{{ route('admin.media.show', ['preRegistration'=>$registration,'media'=>$media->id]) }}" alt="{{ $media->alt_text }}" loading="lazy" width="{{ $media->width }}" height="{{ $media->height }}"></a>
                    <figcaption><strong>{{ ['logo'=>'Logo','cover'=>'Capa','gallery'=>'Fotografia'][$media->type] }}{{ $media->type === 'gallery' ? ' '.($media->position + 1) : '' }}</strong><p>{{ $media->alt_text }}</p><span>{{ ['pending_review'=>'Aguardando análise','approved'=>'Aprovada','rejected'=>'Rejeitada','quarantined'=>'Em quarentena'][$media->status] }}</span><p>Direitos declarados em {{ $media->rights_confirmed_at->format('d/m/Y H:i') }}.</p></figcaption>
                </figure>
            @empty<p>Nenhuma imagem enviada.</p>@endforelse
        </div></section>
        <section class="crm-card crm-card-wide" id="acompanhamento"><h2>Acompanhamento</h2>
            <dl class="crm-dates"><div><dt>Recebido</dt><dd>{{ $registration->created_at->format('d/m/Y H:i') }}</dd></div><div><dt>Atualizado</dt><dd>{{ $registration->updated_at->format('d/m/Y H:i') }}</dd></div><div><dt>Confirmação do e-mail</dt><dd>{{ $registration->email_confirmed_at?->format('d/m/Y H:i') ?? 'Pendente' }}</dd></div></dl>
            <h3 id="historico" tabindex="-1">Histórico de alterações</h3><ol class="crm-history">@forelse($registration->statusHistory as $event)<li><time datetime="{{ $event->created_at->toIso8601String() }}">{{ $event->created_at->format('d/m/Y H:i') }}</time><strong>{{ \App\Models\PreRegistration::STATUS_LABELS[$event->to_status] ?? $event->to_status }}</strong>@if($event->from_status)<span>De {{ \App\Models\PreRegistration::STATUS_LABELS[$event->from_status] ?? $event->from_status }}</span>@endif<span>{{ $event->admin?->name ?? 'Sistema' }}</span>@if($event->reason)<p>{{ $event->reason }}</p>@endif</li>@empty<li>Nenhum histórico registrado.</li>@endforelse</ol>
        </section>
    </div>
</article>
<x-admin-action-dialog />
@endsection
