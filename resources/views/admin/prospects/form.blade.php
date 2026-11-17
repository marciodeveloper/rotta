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
    <a class="crm-back" @if($googleReference ?? null) data-explorer-back @endif href="{{ ($googleReference ?? null) ? $explorerReturn : ($prospect->exists ? route('admin.prospects.show', ['prospect'=>$prospect,...$filters]) : route('admin.prospects.index', $filters)) }}">← {{ ($googleReference ?? null) ? 'Voltar ao Explorador sem salvar' : 'Voltar sem salvar' }}</a>
    <header class="admin-heading"><div><div class="eyebrow"><span></span> Prospecção comercial</div><h1>{{ $prospect->exists ? 'Editar estabelecimento' : 'Novo estabelecimento' }}</h1><p>Somente nome, cidade e UF são obrigatórios. Este cadastro não cria um pré-cadastro formal.</p></div></header>
    <x-prospect-messages />
    @if($googleReference ?? null)
        @include('admin.explorer.assisted-reference')
        <h2 class="text-xl font-semibold mb-4">Cadastro comercial</h2>
    @endif
    @if($prospect->contact_blocked_at)<div class="alert alert-error" role="status">Contato bloqueado. Editar os dados não remove esse bloqueio.</div>@endif
    @if($candidates->isNotEmpty())
        <aside class="crm-card" aria-label="Possíveis duplicidades"><h2>Revise os estabelecimentos semelhantes</h2><ul>
        @foreach($candidates as $candidate)<li><a href="{{ route('admin.prospects.show',$candidate) }}" target="_blank" rel="noopener noreferrer">{{ $candidate->business_name }} · {{ $candidate->city }}/{{ $candidate->state }}</a> — {{ $candidate->contact_blocked_at ? 'Contato bloqueado; exige revisão específica' : 'Abra a ficha antes de confirmar' }} @if($candidate->historical_match) · Correspondência histórica @endif</li>@endforeach
        </ul></aside>
    @endif
    <form class="prospect-form" method="POST" action="{{ ($googleReference ?? null) ? route('admin.explorer.references.prospects.store', ['reference'=>$googleReference,...($explorerParams ?? [])]) : ($prospect->exists ? route('admin.prospects.update',['prospect'=>$prospect,...$filters]) : route('admin.prospects.store',$filters)) }}" novalidate data-prospect-form>
        @csrf
        @if($googleReference ?? null)
            @php $enrichmentToken=old('enrichment_token',''); @endphp
            @php $googleToken=old('google_enrichment_token',''); $googleFields=old('google_applied_fields','[]'); @endphp
            <input type="hidden" name="google_enrichment_token" value="{{ is_string($googleToken)?$googleToken:'' }}">
            <input type="hidden" name="google_applied_fields" value="{{ is_string($googleFields)?$googleFields:'[]' }}">
            <div class="crm-card mb-4" data-google-origin-review @if(!is_string($googleToken)||$googleToken==='') hidden @endif>
                <p>Conteúdo Google Places: a confirmação do administrador não concede licença de armazenamento permanente. Funcionalidade experimental; publicação e regularização contratual pendentes.</p>
                <label class="prospect-check"><input type="checkbox" name="google_origin_confirmed" value="1" @checked(old('google_origin_confirmed')==='1')> Revisei os campos e confirmo sua origem Google, ciente da restrição contratual.</label>
                <p class="field-error">{{ $errors->first('google_origin_confirmed') }} {{ $errors->first('google_enrichment_token') }}</p>
                @unless(config('business_explorer.google_persistence_enabled'))<p class="field-error">O salvamento de conteúdo Google está desabilitado neste ambiente.</p>@endunless
            </div>
            <input type="hidden" name="assisted_mode" value="simple">
            <input type="hidden" name="enrichment_token" value="{{ is_string($enrichmentToken)?$enrichmentToken:'' }}">
            <section class="crm-card mb-6" data-enrichment-actions>
                <div class="prospect-field mb-3"><label for="enrichment-cnpj">CNPJ do estabelecimento, se conhecido (opcional)</label><input id="enrichment-cnpj" data-enrichment-cnpj type="text" inputmode="text" maxlength="30" autocomplete="off" aria-describedby="enrichment-cnpj-help"><p id="enrichment-cnpj-help" class="crm-muted text-sm">Permite consultar dados cadastrais públicos sem website. Não adivinhamos o CNPJ pelo nome; confira a empresa e a unidade antes de usar os dados.</p></div>
                <div class="flex flex-wrap items-center gap-3">
                    <button class="button button-primary" type="button" data-enrichment-start>Buscar dados e preencher cadastro</button>
                    <button class="button button-secondary" type="button" data-enrichment-review>Continuar cadastro manual</button>
                </div>
                <p class="crm-muted text-sm">O preenchimento procura fontes autorizadas. Com CNPJ informado, consulta dados públicos da empresa; sem CNPJ, utiliza a fonte independente disponível. Você pode salvar manualmente a qualquer momento.</p>
                @if(config('business_explorer.enterprise_enabled'))
                    <label class="prospect-check"><input type="checkbox" data-enrichment-enterprise> Incluir telefone, website e reputação Google — consulta Enterprise com possível cobrança adicional.</label>
                @endif
                <p role="status" aria-live="polite" data-enrichment-summary></p>
                <div class="grid gap-3" data-enrichment-conflicts></div>
            </section>
        @endif
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
            @if(!$prospect->exists && !($googleReference ?? null))<section class="crm-card"><h2>Origem manual</h2>@include('admin.prospects.source-fields')</section>@endif
            @if($googleReference ?? null)
                @include('admin.explorer.field-provenance')
            @endif
            @unless($googleReference ?? null)<fieldset class="crm-card"><legend>Confirmação dos campos</legend><p>Marque somente os dados que você efetivamente verificou. A autoria e a data serão registradas.</p><div class="prospect-checks">
                @foreach(($googleReference ?? null) ? array_intersect_key(\App\Models\Prospect::FIELDS,array_flip(['notes','priority','assigned_to'])) : \App\Models\Prospect::FIELDS as $field=>$label)<label><input type="checkbox" name="confirmed_fields[]" value="{{ $field }}" @checked(in_array($field,$selectedConfirmed,true))> {{ $label }}</label>@endforeach
            </div><div id="confirmed_fields" tabindex="-1" class="field-error">{{ $errors->first('confirmed_fields') }}{{ $errors->first('confirmed_fields.*') }}</div></fieldset>@endunless
            @if(!($googleReference ?? null) || $candidates->isNotEmpty() || $errors->hasAny(['duplicate_acknowledged','duplicate_reason','blocked_match_acknowledged','blocked_match_reason','blocked_match_versions']))
            <section class="crm-card"><h2>Decisões de revisão</h2>
                @include('admin.prospects.blocked-match-review')
                @unless($googleReference ?? null)
                <label class="prospect-check"><input type="checkbox" name="override_confirmed" id="override_confirmed" value="1" @checked(in_array(old('override_confirmed'),[true,1,'1'],true))> Autorizo explicitamente alterar ou retirar a confirmação de campos já confirmados.</label>
                @error('override_confirmed')<p class="field-error">{{ $message }}</p>@enderror
                <x-prospect-field name="change_reason" label="Justificativa da alteração de campos confirmados" type="textarea" :maxlength="500" />
                @endunless
                <label class="prospect-check"><input type="checkbox" name="duplicate_acknowledged" id="duplicate_acknowledged" value="1" @checked(in_array(old('duplicate_acknowledged'),[true,1,'1'],true))> Revisei as possíveis duplicidades e confirmo que se trata de outro estabelecimento.</label>
                @error('duplicate_acknowledged')<p class="field-error">{{ $message }}</p>@enderror
                <x-prospect-field name="duplicate_reason" label="Justificativa para estabelecimento semelhante" type="textarea" :maxlength="500" />
            </section>
            @endif
        </div>
        <div id="lock_version" class="field-error">{{ $errors->first('lock_version') }} @if($errors->has('lock_version'))<a href="{{ route('admin.prospects.edit',['prospect'=>$prospect,...$filters]) }}">Recarregar a versão atual (descarta alterações não salvas)</a>@endif</div>
        <p id="submission_token" class="field-error">{{ $errors->first('submission_token') }}</p>
        @if($googleReference ?? null)
            <p class="crm-muted">Os dados digitados serão registrados como declarados por você. Somente dados de fontes autorizadas verificadas recebem confirmação de origem. O preenchimento automático é opcional.</p>
        @endif
        <div class="admin-actions"><button class="button button-primary" type="submit">Salvar estabelecimento</button><p role="status" data-prospect-saving hidden>Salvando… aguarde.</p></div>
    </form>
</section>
@endsection
