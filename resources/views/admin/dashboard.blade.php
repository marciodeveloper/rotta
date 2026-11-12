@extends('layouts.app')
@section('title', 'Pré-cadastros')
@section('content')
<section class="admin-shell" data-admin-table-shell>
    <x-admin-navigation />
    <header class="admin-heading">
        <div><div class="eyebrow"><span></span> CRM ROTTA</div><h1>Pré-cadastros</h1><p>Encontre interessados e acompanhe cada análise.</p></div>
        <form method="POST" action="{{ route('admin.logout') }}">@csrf<button class="button button-secondary" type="submit">Sair</button></form>
    </header>
    <div data-table-region>
        @if(session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="alert alert-error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <div class="metric-grid crm-metrics" aria-label="Totais globais por status">
            @foreach(\App\Models\PreRegistration::STATUS_LABELS as $status => $label)<div><span>{{ $label }}</span><strong>{{ $counts[$status] ?? 0 }}</strong></div>@endforeach
        </div>
        <form class="filter-panel crm-filters" method="GET" action="{{ route('admin.dashboard') }}" data-table-filters>
            <label>Buscar negócio<input name="q" type="search" value="{{ $filters['q'] ?? '' }}" maxlength="100" placeholder="Nome comercial ou profissional"></label>
            <label>Status<select name="status"><option value="">Todos</option>@foreach(\App\Models\PreRegistration::STATUS_LABELS as $status => $label)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $label }}</option>@endforeach</select></label>
            <label>UF<input name="state" value="{{ $filters['state'] ?? '' }}" maxlength="2" placeholder="Ex.: RJ"></label>
            <label>Categoria<select name="category"><option value="">Todas</option>@foreach($categories as $category)<option @selected(($filters['category'] ?? '') === $category)>{{ $category }}</option>@endforeach</select></label>
            <label>Por página<select name="per_page" data-page-size>@foreach([10,25,50,100] as $size)<option value="{{ $size }}" @selected(($filters['per_page'] ?? 25) == $size)>{{ $size }}</option>@endforeach</select></label>
            <input type="hidden" name="sort" value="{{ $filters['sort'] ?? 'created_at' }}"><input type="hidden" name="direction" value="{{ $filters['direction'] ?? 'desc' }}">
            @foreach($filters['columns'] ?? [] as $column)<input type="hidden" name="columns[]" value="{{ $column }}">@endforeach
            <button class="button button-primary" type="submit">Aplicar filtros</button>
            <a href="{{ route('admin.dashboard') }}" class="crm-clear">Limpar</a>
        </form>
        <div class="admin-actions crm-toolbar">
            <p id="table-result-count" tabindex="-1">{{ $registrations->total() }} resultado(s) · Página {{ $registrations->currentPage() }} de {{ $registrations->lastPage() }}</p>
            <details class="crm-columns"><summary>Colunas visíveis</summary><div>@foreach(['business_name'=>'Negócio','category'=>'Categoria','city'=>'Cidade','state'=>'UF','status'=>'Status','created_at'=>'Recebido'] as $key => $label)
                <label><input type="checkbox" data-column-toggle="{{ $key }}" @checked($key === 'business_name' || !isset($filters['columns']) || in_array($key,$filters['columns'],true)) @disabled($key === 'business_name')> {{ $label }}</label>
            @endforeach</div></details>
        </div>
        <div class="table-wrap" data-table-container data-total="{{ $registrations->total() }}" data-page="{{ $registrations->currentPage() }}" data-page-size="{{ $registrations->perPage() }}" data-sort="{{ $filters['sort'] ?? 'created_at' }}" data-direction="{{ $filters['direction'] ?? 'desc' }}">
            <table class="crm-table"><caption class="sr-only">Pré-cadastros encontrados. Abra um negócio para consultar os detalhes.</caption>
                <thead><tr>@foreach(['business_name'=>'Negócio','category'=>'Categoria','city'=>'Cidade','state'=>'UF','status'=>'Status','created_at'=>'Recebido'] as $key => $label)
                    <th scope="col" data-column="{{ $key }}" aria-sort="{{ ($filters['sort'] ?? 'created_at') === $key ? (($filters['direction'] ?? 'desc') === 'asc' ? 'ascending' : 'descending') : 'none' }}"><a data-sort-column="{{ $key }}" href="{{ route('admin.dashboard', [...$filters, 'sort'=>$key, 'direction'=>($filters['sort'] ?? 'created_at') === $key && ($filters['direction'] ?? 'desc') === 'asc' ? 'desc' : 'asc', 'page'=>1]) }}">{{ $label }} <span aria-hidden="true">↕</span></a></th>
                @endforeach<th scope="col" class="crm-actions-column">Ações</th></tr></thead>
                <tbody>@forelse($registrations as $registration)
                    <tr data-record-row>
                        <td data-column="business_name"><a class="crm-record-link" href="{{ route('admin.pre-registrations.show', ['preRegistration'=>$registration, ...$filters]) }}">{{ $registration->business_name }} <span aria-hidden="true">↗</span></a></td>
                        <td data-column="category">{{ $registration->category }}</td><td data-column="city">{{ $registration->city }}</td><td data-column="state">{{ $registration->state }}</td>
                        <td data-column="status"><span class="crm-badge" data-status="{{ $registration->status }}">{{ \App\Models\PreRegistration::STATUS_LABELS[$registration->status] }}</span></td>
                        <td data-column="created_at"><time datetime="{{ $registration->created_at->toIso8601String() }}">{{ $registration->created_at->format('d/m/Y H:i') }}</time></td>
                        <td class="crm-actions-column"><x-record-actions :registration="$registration" :context="$filters" /></td>
                    </tr>
                @empty<tr><td colspan="7" class="crm-empty">Nenhum pré-cadastro encontrado. Ajuste os filtros para tentar novamente.</td></tr>@endforelse</tbody>
            </table>
        </div>
        <div class="crm-pagination" data-table-pagination>{{ $registrations->onEachSide(1)->links('admin.pagination') }}</div>
        @can('export-pre-registrations')
            <details class="crm-export"><summary>Exportar resultados em CSV</summary>
                <p>A exportação inclui todos os resultados dos filtros e da busca, independentemente da página ou das colunas visíveis.</p>
                <form method="POST" action="{{ route('admin.export') }}">@csrf
                    @foreach(array_intersect_key($filters,array_flip(['q','status','state','category'])) as $name => $value)<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endforeach
                    <label class="field"><span>Justificativa da exportação</span><input name="reason" value="{{ old('reason') }}" minlength="20" maxlength="500" required></label>
                    <button class="button button-secondary" type="submit">Exportar CSV v1.1</button>
                    <a href="{{ route('admin.reauthenticate') }}">Confirmar senha novamente</a>
                </form>
            </details>
        @endcan
    </div>
    <p data-table-loading role="status" aria-live="polite" hidden>Atualizando resultados…</p>
    <div data-table-error class="alert alert-error" role="alert" hidden><p>Não foi possível atualizar a lista. Os últimos resultados foram mantidos.</p><button class="button button-secondary" type="button" data-table-retry>Tentar novamente</button></div>
</section>
<x-admin-action-dialog />
@endsection
