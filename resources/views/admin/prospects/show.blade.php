@extends('layouts.app')
@section('title',$prospect->business_name)
@section('content')
<article class="admin-shell crm-detail">
    @php
        $oldVersion=old('lock_version',$prospect->lock_version);
        $oldToken=old('submission_token',$submissionToken);
    @endphp
    <x-admin-navigation />
    <a class="crm-back" href="{{ route('admin.prospects.index',$filters) }}">← Voltar aos resultados</a>
    <header class="admin-heading"><div><div class="eyebrow"><span></span> Ficha do prospect</div><h1>{{ $prospect->business_name }}</h1><p>{{ $prospect->city }}/{{ $prospect->state }} · Identificado</p></div>@can('update',$prospect)<a class="button button-primary" href="{{ route('admin.prospects.edit',['prospect'=>$prospect,...$filters]) }}">Editar dados</a>@endcan</header>
    <x-prospect-messages />
    <nav class="crm-detail-nav" aria-label="Seções do prospect"><a href="#identificacao">Identificação</a><a href="#localizacao">Localização</a><a href="#origens">Origens</a><a href="#historico">Histórico</a><a href="#contato">Bloqueio de contato</a></nav>
    <div class="crm-detail-grid">
        <section class="crm-card" id="identificacao"><h2>Informações comerciais</h2><dl>
            @foreach(['business_name','category','email','phone','website','instagram','notes'] as $field)<div><dt>{{ \App\Models\Prospect::FIELDS[$field] }}</dt><dd class="crm-pre-wrap">{{ $prospect->$field ?: 'Não informado' }}</dd></div>@endforeach
            <div><dt>Responsável</dt><dd>{{ $prospect->assignee?->name ?: 'Sem responsável' }}</dd></div><div><dt>Prioridade</dt><dd>{{ \App\Models\Prospect::PRIORITIES[$prospect->priority] }}</dd></div>
        </dl></section>
        <section class="crm-card" id="localizacao"><h2>Endereço e localização</h2><dl>@foreach(['postal_code','street','street_number','complement','neighborhood','city','state'] as $field)<div><dt>{{ \App\Models\Prospect::FIELDS[$field] }}</dt><dd>{{ $prospect->$field ?: 'Não informado' }}</dd></div>@endforeach</dl></section>
        <section class="crm-card"><h2>Campos confirmados</h2><ul>@forelse($prospect->confirmed_fields ?? [] as $field=>$confirmation)<li>{{ \App\Models\Prospect::FIELDS[$field] }} · {{ $confirmers[$confirmation['admin_id']] ?? 'Administrador' }} · {{ \Carbon\Carbon::parse($confirmation['at'])->format('d/m/Y H:i') }} @if($confirmation['source_id'] ?? null) · Origem {{ $confirmation['source_id'] }} @endif</li>@empty<li>Nenhum campo foi marcado como verificado.</li>@endforelse</ul></section>
        <section class="crm-card" id="contato"><h2>{{ $prospect->contact_blocked_at ? 'Contato bloqueado' : 'Contato permitido' }}</h2>
            @if($prospect->contact_blocked_at)<p role="status">Não realizar abordagens. Adicionar origens ou editar dados não remove o bloqueio.</p><p>Desde {{ $prospect->contact_blocked_at->format('d/m/Y H:i') }}</p><p>{{ $prospect->contact_block_reason }}</p>@else<p>Não há bloqueio operacional registrado. Isso não representa consentimento para marketing.</p>@endif
            @can('manageContact',$prospect)<form method="POST" action="{{ route('admin.prospects.contact',['prospect'=>$prospect,...$filters]) }}" data-prospect-form novalidate>@csrf<input type="hidden" name="lock_version" value="{{ is_scalar($oldVersion) ? $oldVersion : '' }}"><input type="hidden" name="blocked" value="{{ $prospect->contact_blocked_at ? 0 : 1 }}"><x-prospect-field name="reason" label="Justificativa (mínimo de 10 caracteres)" type="textarea" :maxlength="500" /><button class="button button-secondary" type="submit">{{ $prospect->contact_blocked_at ? 'Desbloquear contato com justificativa' : 'Bloquear contato' }}</button><p role="status" data-prospect-saving hidden>Salvando…</p></form>@endcan
        </section>
        <section class="crm-card" id="origens"><h2>Origens e referências</h2>
            @foreach($sources as $source)<article class="prospect-source"><h3>Registro manual · {{ $source->created_at->format('d/m/Y H:i') }}</h3><p>{{ $source->origin_reference ?: 'Informado diretamente pelo administrador' }}</p><p>Autor: {{ $source->author->name }}</p><p>Coleta: {{ $source->collected_at?->format('d/m/Y') ?: 'Data desconhecida' }} · Verificação: {{ $source->verified_at?->format('d/m/Y') ?: 'Não informada' }}</p><p>Restrições: {{ $source->usage_restrictions ?: 'Não informadas; não presumir autorização de reutilização' }}</p><p>Finalidade: {{ $source->purpose }}</p><p class="crm-muted">Referência interna: {{ $source->id }} · Informação manual; não constitui licença de conteúdo externo.</p></article>@endforeach
            {{ $sources->links('admin.pagination') }}
            @can('addSource',$prospect)<details @if($errors->hasAny(['origin_reference','usage_restrictions','collected_at','verified_at','submission_token'])) open @endif><summary>Adicionar referência manual</summary><form method="POST" action="{{ route('admin.prospects.sources.store',['prospect'=>$prospect,...$filters]) }}" data-prospect-form novalidate>@csrf<input type="hidden" name="lock_version" value="{{ is_scalar($oldVersion) ? $oldVersion : '' }}"><input type="hidden" name="submission_token" value="{{ is_string($oldToken) ? $oldToken : $submissionToken }}">@include('admin.prospects.source-fields')<button class="button button-secondary" type="submit">Adicionar origem sem alterar os dados</button><p role="status" data-prospect-saving hidden>Salvando…</p></form></details>@endcan
        </section>
        <section class="crm-card" id="historico"><h2>Histórico de relacionamento</h2><ol class="crm-history">@foreach($activities as $activity)<li><time>{{ $activity->created_at->format('d/m/Y H:i') }}</time><strong>{{ \App\Models\ProspectActivity::LABELS[$activity->event] ?? $activity->event }}</strong><p>{{ $activity->admin->name }}</p>@if($activity->decision_reasons)
                <dl>@foreach($activity->decision_reasons as $type=>$decision)<div><dt>{{ \App\Models\ProspectActivity::DECISION_LABELS[$type] ?? $type }}</dt><dd>{{ $decision }}</dd></div>@endforeach</dl>
            @elseif($activity->reason)<p>{{ $activity->reason }}</p>@endif @if($activity->metadata['fields'] ?? [])<p>Campos: {{ implode(', ',array_map(fn($field)=>\App\Models\Prospect::FIELDS[$field],$activity->metadata['fields'])) }}</p>@endif</li>@endforeach</ol>{{ $activities->links('admin.pagination') }}</section>
    </div>
    <p id="lock_version" class="field-error">{{ $errors->first('lock_version') }} @if($errors->has('lock_version'))<a href="{{ route('admin.prospects.show',['prospect'=>$prospect,...$filters]) }}">Recarregar ficha</a>@endif</p>
    <p id="submission_token" class="field-error">{{ $errors->first('submission_token') }}</p>
</article>
@endsection
