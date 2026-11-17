@extends('layouts.app')
@section('title', 'Explorador de Negócios')
@section('content')
<section class="admin-shell" data-business-explorer data-explorer-configured="{{ $configurationMessage === null ? 'true' : 'false' }}" data-search-url="{{ route('admin.explorer.search') }}" data-details-url="{{ route('admin.explorer.details') }}" data-save-url="{{ route('admin.explorer.references.store') }}" data-metadata-url="{{ route('admin.explorer.metadata') }}">
    <x-admin-navigation />
    <header class="admin-heading"><div><div class="eyebrow"><span></span> Prospecção comercial</div><h1>Explorador de Negócios</h1><p>Encontre estabelecimentos e revise cada referência antes de cadastrar.</p></div><a class="button button-primary" href="{{ route('admin.prospects.create') }}">Novo estabelecimento</a></header>
    @if($configurationMessage)<div class="alert alert-error" role="status">{{ $configurationMessage }}</div>@endif
    <form class="crm-card" data-explorer-search action="{{ route('admin.explorer.search') }}" method="POST">
        @csrf
        <label for="business-query">O que você procura?</label>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end mt-2">
            <input class="min-w-0 flex-1 rounded-lg border border-stone-300 bg-white px-4 py-3 text-base focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#173f38]" id="business-query" name="query" type="search" maxlength="200" minlength="3" required placeholder="Cafeterias em Vassouras, RJ" aria-describedby="query-help explorer-error" autocomplete="off">
            <button class="button button-primary" type="submit" disabled>Pesquisar</button>
        </div>
        <p id="query-help" class="crm-muted mt-3">A busca acontece somente ao pesquisar. Cada busca, próxima página e consulta de detalhes pode gerar cobrança na Google Maps Platform. Resultados temporários; salvar uma referência conserva somente o Place ID.</p>
        <noscript><p class="field-error">Ative o JavaScript para pesquisar sem navegar ou gravar resultados externos.</p></noscript>
    </form>
    <p data-explorer-status role="status" aria-live="polite" class="crm-muted my-4">Digite uma pesquisa para começar. Após recarregar a página, refaça a pesquisa; uma nova consulta pode consumir quota.</p>
    <p data-explorer-error id="explorer-error" class="alert alert-error" role="alert" tabindex="-1" hidden></p>
    <section data-explorer-results aria-label="Resultados temporários do Google Maps" hidden>
        <div class="flex flex-wrap items-center justify-between gap-3 mb-3"><h2>Resultados do Google Maps</h2><x-google-maps-attribution /></div>
        <div class="table-wrap explorer-table-wrap">
            <table class="crm-table explorer-table" role="table"><caption class="sr-only">Dados temporários do Google Maps. A coluna CRM ROTTA contém dados internos, exibidos separadamente.</caption>
                <thead><tr><th scope="col">Estabelecimento · Google Maps</th><th scope="col">Endereço · Google Maps</th><th scope="col">Categoria · Google Maps</th><th scope="col">CRM ROTTA · dados internos</th><th scope="col" class="crm-actions-column">Ações</th></tr></thead>
                <tbody data-explorer-rows></tbody>
            </table>
        </div>
        <nav class="admin-actions mt-4" aria-label="Páginas da pesquisa Google"><button class="button button-secondary" type="button" data-explorer-previous disabled>← Anterior</button><span data-explorer-page class="crm-muted"></span><button class="button button-secondary" type="button" data-explorer-next disabled>Próxima →</button></nav>
        <p class="crm-muted mt-4">Nomes e endereços apresentados nesta consulta não são copiados para o CRM. Uma semelhança de nome não identifica, por si só, o mesmo estabelecimento ou filial.</p>
    </section>
    <section class="crm-card mt-8" aria-labelledby="saved-references-title" data-saved-references>
        <h2 id="saved-references-title">Referências salvas para revisão</h2><x-google-maps-attribution /><p class="crm-muted">Nomes do CRM ou de fontes independentes autorizadas. Quando não houver nome permitido, consulte o Google explicitamente.</p>
        <form method="GET" action="{{ route('admin.explorer.index') }}" class="grid gap-3 sm:grid-cols-4 mt-4" data-reference-filters>
            <div class="prospect-field"><label for="reference-q">Nome ou Place ID</label><input id="reference-q" name="q" maxlength="100" value="{{ $referenceFilters['q'] ?? '' }}"></div>
            <div class="prospect-field"><label for="reference-status">Situação</label><select id="reference-status" name="status"><option value="">Todas</option>@foreach(['pending'=>'Revisão pendente','linked'=>'Vinculado ao CRM','possible_duplicate'=>'Possível duplicidade'] as $value=>$label)<option value="{{ $value }}" @selected(($referenceFilters['status']??'')===$value)>{{ $label }}</option>@endforeach</select></div>
            <div class="prospect-field"><label for="reference-size">Por página</label><select id="reference-size" name="per_page">@foreach([10,25,50] as $size)<option value="{{ $size }}" @selected(($referenceFilters['per_page']??10)==$size)>{{ $size }}</option>@endforeach</select></div>
            <div class="flex flex-wrap items-center gap-2"><button class="button button-secondary" type="submit">Filtrar referências</button><a class="crm-context-link" href="{{ route('admin.explorer.index') }}">Limpar</a></div>
        </form>
        <div class="table-wrap explorer-table-wrap mt-4"><table class="crm-table explorer-table" role="table"><caption class="sr-only">Referências persistidas no CRM, sem consulta automática ao Google</caption><thead><tr><th scope="col">Estabelecimento</th><th scope="col">Situação</th><th scope="col">Data de inclusão</th><th scope="col">Ações</th></tr></thead><tbody>
        @forelse($references as $reference)
            @php
                $name=$reference->prospect?->business_name ?? ($referenceNames[$reference->place_id]??null);
                $duplicate=!$reference->prospect_id && in_array($reference->place_id,$duplicateReferenceIds,true);
                $returnParams=[];foreach($referenceFilters as $key=>$value){$returnParams['explorer_'.$key]=$value;}
            @endphp
            <tr data-saved-reference="{{ $reference->id }}" data-saved-place-id="{{ $reference->place_id }}" data-name-origin="{{ $reference->prospect_id ? 'crm' : ($name ? 'independent' : 'none') }}">
                <td data-label="Estabelecimento"><strong data-reference-name>{{ $name ?: 'Estabelecimento não consultado' }}</strong><span data-reference-name-source class="crm-muted block text-sm">{{ $reference->prospect_id ? 'Dados do CRM' : ($name ? 'Fonte independente autorizada' : 'Consulte os detalhes para visualizar o nome Google') }}</span><div class="explorer-id"><code title="{{ $reference->place_id }}">{{ $reference->place_id }}</code><button type="button" class="crm-context-link" data-copy-place-id="{{ $reference->place_id }}" aria-label="Copiar Place ID de {{ $name ?: 'referência sem nome' }}">Copiar ID</button></div></td>
                <td data-label="Situação"><span class="explorer-badge">{{ $reference->prospect_id ? 'Vinculado ao CRM' : ($duplicate ? 'Possível duplicidade' : 'Revisão pendente') }}</span>@if($reference->prospect?->contact_blocked_at)<strong class="field-error block">Contato bloqueado</strong>@endif</td>
                <td data-label="Data de inclusão"><time datetime="{{ $reference->created_at->toIso8601String() }}">{{ $reference->created_at->format('d/m/Y H:i') }}</time></td>
                <td data-label="Ações"><div class="explorer-actions"><a class="button button-primary" href="{{ route('admin.explorer.references.create', ['reference'=>$reference,...$returnParams]) }}">{{ $reference->prospect_id ? 'Abrir ficha' : 'Iniciar cadastro' }}</a><button class="button button-secondary" type="button" data-reference-details="{{ $reference->id }}">Consultar no Google</button></div></td>
            </tr>
        @empty<tr><td colspan="4">Nenhuma referência encontrada para os filtros. Você também pode cadastrar um estabelecimento manualmente.</td></tr>@endforelse
        </tbody></table></div>
        <nav class="crm-page-nav" aria-label="Paginação das referências salvas">
            <span>{{ $references->total() }} referências · Página {{ $references->currentPage() }} de {{ $references->lastPage() }}</span>
            <div class="flex flex-wrap gap-2">
                @foreach(['Primeira'=>1,'Anterior'=>max(1,$references->currentPage()-1),'Próxima'=>min($references->lastPage(),$references->currentPage()+1),'Última'=>$references->lastPage()] as $label=>$number)
                    @if(($number===$references->currentPage()) || (!$references->hasMorePages() && in_array($label,['Próxima','Última'],true)))<span aria-disabled="true">{{ $label }}</span>@else<a href="{{ $references->url($number) }}">{{ $label }}</a>@endif
                @endforeach
            </div>
        </nav>
    </section>
    <aside class="crm-muted mt-6" aria-label="Uso dos dados e termos"><p>Use dados confirmados diretamente com o estabelecimento ou de fonte com licença apropriada para o cadastro definitivo.</p><div class="flex flex-wrap gap-4 mt-3">
        <a href="https://maps.google.com/help/terms_maps.html" target="_blank" rel="noopener noreferrer">Termos do Google Maps</a><a href="https://policies.google.com/privacy" target="_blank" rel="noopener noreferrer">Privacidade do Google</a>
        @foreach(['terms_url' => 'Termos da aplicação', 'privacy_url' => 'Privacidade da aplicação'] as $key => $label)@if(\App\Services\GooglePlacesProvider::httpsUrl(config('business_explorer.'.$key)))<a href="{{ config('business_explorer.'.$key) }}" target="_blank" rel="noopener noreferrer">{{ $label }}</a>@endif @endforeach
    </div></aside>
    <dialog class="m-auto w-[calc(100%-2rem)] max-w-2xl max-h-[85vh] overflow-y-auto rounded-2xl border border-stone-300 bg-[#fffaf2] p-6 backdrop:bg-black/50" data-explorer-dialog aria-labelledby="place-detail-title">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-4"><h2 id="place-detail-title">Detalhes do estabelecimento</h2><button type="button" class="button button-secondary" data-explorer-close>Fechar</button></div>
        <x-google-maps-attribution />
        <div data-explorer-detail></div><x-business-intelligence />
    </dialog>
</section>
@endsection
