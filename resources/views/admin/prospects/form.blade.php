@extends('layouts.app')
@section('title', $prospect->exists ? 'Editar prospect' : 'Novo prospect')
@section('content')
<section class="admin-shell">
    @php
        $oldConfirmed=session()->hasOldInput() ? old('confirmed_fields',[]) : array_keys($prospect->confirmed_fields ?? []);
        $selectedConfirmed=is_array($oldConfirmed) ? array_filter($oldConfirmed,'is_string') : [];
        $oldVersion=old('lock_version',$prospect->lock_version);
        $oldToken=old('submission_token',$submissionToken);
    @endphp
    <x-admin-navigation />
    <a class="crm-back" href="{{ $prospect->exists ? route('admin.prospects.show', ['prospect'=>$prospect,...$filters]) : route('admin.prospects.index', $filters) }}">← Voltar sem salvar</a>
    <header class="admin-heading"><div><div class="eyebrow"><span></span> Prospecção comercial</div><h1>{{ $prospect->exists ? 'Editar estabelecimento' : 'Novo estabelecimento' }}</h1><p>Somente nome, cidade e UF são obrigatórios. Este cadastro não cria um pré-cadastro formal.</p></div></header>
    <x-prospect-messages />
    @if($prospect->contact_blocked_at)<div class="alert alert-error" role="status">Contato bloqueado. Editar os dados não remove esse bloqueio.</div>@endif
    @if($candidates->isNotEmpty())
        <aside class="crm-card" aria-label="Possíveis duplicidades"><h2>Revise os estabelecimentos semelhantes</h2><ul>
        @foreach($candidates as $candidate)<li><a href="{{ route('admin.prospects.show',$candidate) }}" target="_blank" rel="noopener noreferrer">{{ $candidate->business_name }} · {{ $candidate->city }}/{{ $candidate->state }}</a> — {{ $candidate->contact_blocked_at ? 'Contato bloqueado; exige revisão específica' : 'Abra a ficha antes de confirmar' }} @if($candidate->historical_match) · Correspondência histórica @endif</li>@endforeach
        </ul></aside>
    @endif
    <form class="prospect-form" method="POST" action="{{ $prospect->exists ? route('admin.prospects.update',['prospect'=>$prospect,...$filters]) : route('admin.prospects.store',$filters) }}" novalidate data-prospect-form>
        @csrf
        @if($prospect->exists) @method('PATCH') <input type="hidden" name="lock_version" value="{{ is_scalar($oldVersion) ? $oldVersion : '' }}">
        @else <input type="hidden" name="submission_token" value="{{ is_string($oldToken) ? $oldToken : $submissionToken }}"> @endif
        <div class="crm-detail-grid">
            <section class="crm-card"><h2>Identificação comercial</h2>
                @foreach(['business_name'=>180,'category'=>100,'city'=>100,'state'=>2] as $field=>$limit)
                    <x-prospect-field :name="$field" :label="\App\Models\Prospect::FIELDS[$field]" :value="$prospect->$field" :maxlength="$limit" :required="in_array($field,['business_name','city','state'],true)" />
                @endforeach
                <div class="prospect-field"><label for="priority">Prioridade</label><select id="priority" name="priority" aria-invalid="{{ $errors->has('priority') ? 'true' : 'false' }}" aria-describedby="error-priority">@foreach(\App\Models\Prospect::PRIORITIES as $value=>$label)<option value="{{ $value }}" @selected(old('priority',$prospect->priority ?? 'medium') === $value)>{{ $label }}</option>@endforeach</select><p id="error-priority" class="field-error">{{ $errors->first('priority') }}</p></div>
                <div class="prospect-field"><label for="assigned_to">Responsável interno</label><select id="assigned_to" name="assigned_to" aria-invalid="{{ $errors->has('assigned_to') ? 'true' : 'false' }}" aria-describedby="error-assigned_to"><option value="">Sem responsável</option>@foreach($admins as $admin)<option value="{{ $admin->id }}" @selected(old('assigned_to',$prospect->assigned_to) === $admin->id)>{{ $admin->name }}</option>@endforeach</select><p id="error-assigned_to" class="field-error">{{ $errors->first('assigned_to') }}</p></div>
            </section>
            <section class="crm-card"><h2>Contato e localização</h2>
                @foreach(['email'=>254,'phone'=>20,'website'=>500,'instagram'=>150,'postal_code'=>9,'street'=>200,'street_number'=>30,'complement'=>150,'neighborhood'=>100] as $field=>$limit)
                    <x-prospect-field :name="$field" :label="\App\Models\Prospect::FIELDS[$field]" :value="$prospect->$field" :maxlength="$limit" :type="$field === 'email' ? 'email' : ($field === 'phone' ? 'tel' : 'text')" />
                @endforeach
                <p class="crm-muted">Instagram: @perfil ou URL https://instagram.com/perfil. Telefone: DDD e número, com código 55 opcional.</p>
            </section>
            <section class="crm-card"><h2>Observações</h2><x-prospect-field name="notes" label="Observações comerciais" :value="$prospect->notes" type="textarea" :maxlength="3000" /><p class="crm-muted">Não registre documentos pessoais ou dados sensíveis desnecessários.</p></section>
            @unless($prospect->exists)<section class="crm-card"><h2>Origem manual</h2>@include('admin.prospects.source-fields')</section>@endunless
            <fieldset class="crm-card"><legend>Confirmação dos campos</legend><p>Marque somente os dados que você efetivamente verificou. A autoria e a data serão registradas.</p><div class="prospect-checks">
                @foreach(\App\Models\Prospect::FIELDS as $field=>$label)<label><input type="checkbox" name="confirmed_fields[]" value="{{ $field }}" @checked(in_array($field,$selectedConfirmed,true))> {{ $label }}</label>@endforeach
            </div><div id="confirmed_fields" tabindex="-1" class="field-error">{{ $errors->first('confirmed_fields') }}{{ $errors->first('confirmed_fields.*') }}</div></fieldset>
            <section class="crm-card"><h2>Decisões de revisão</h2>
                @include('admin.prospects.blocked-match-review')
                <label class="prospect-check"><input type="checkbox" name="override_confirmed" id="override_confirmed" value="1" @checked(in_array(old('override_confirmed'),[true,1,'1'],true))> Autorizo explicitamente alterar ou retirar a confirmação de campos já confirmados.</label>
                @error('override_confirmed')<p class="field-error">{{ $message }}</p>@enderror
                <x-prospect-field name="change_reason" label="Justificativa da alteração de campos confirmados" type="textarea" :maxlength="500" />
                <label class="prospect-check"><input type="checkbox" name="duplicate_acknowledged" id="duplicate_acknowledged" value="1" @checked(in_array(old('duplicate_acknowledged'),[true,1,'1'],true))> Revisei as possíveis duplicidades e confirmo que se trata de outro estabelecimento.</label>
                @error('duplicate_acknowledged')<p class="field-error">{{ $message }}</p>@enderror
                <x-prospect-field name="duplicate_reason" label="Justificativa para estabelecimento semelhante" type="textarea" :maxlength="500" />
            </section>
        </div>
        <div id="lock_version" class="field-error">{{ $errors->first('lock_version') }} @if($errors->has('lock_version'))<a href="{{ route('admin.prospects.edit',['prospect'=>$prospect,...$filters]) }}">Recarregar a versão atual (descarta alterações não salvas)</a>@endif</div>
        <p id="submission_token" class="field-error">{{ $errors->first('submission_token') }}</p>
        <div class="admin-actions"><button class="button button-primary" type="submit">Salvar estabelecimento</button><p role="status" data-prospect-saving hidden>Salvando… aguarde.</p></div>
    </form>
</section>
@endsection
