@extends('layouts.app')
@section('title','Prospecção comercial')
@section('content')
<section class="admin-shell" data-admin-table-shell data-table-columns="{{ json_encode(\App\Http\Requests\Admin\ListProspectsRequest::COLUMNS) }}">
    <x-admin-navigation />
    <header class="admin-heading"><div><div class="eyebrow"><span></span> CRM ROTTA</div><h1>Prospecção comercial</h1><p>Estabelecimentos identificados para conhecer e acompanhar.</p></div><a class="button button-primary" href="{{ route('admin.prospects.create',$filters) }}">Novo estabelecimento</a></header>
    <div data-table-region>
        <x-prospect-messages />
        <form class="filter-panel crm-filters" method="GET" action="{{ route('admin.prospects.index') }}" data-table-filters>
            @foreach(['q'=>'Buscar estabelecimento','city'=>'Cidade','state'=>'UF','category'=>'Segmento'] as $field=>$label)<label>{{ $label }}<input name="{{ $field }}" value="{{ $filters[$field] ?? '' }}" maxlength="{{ $field === 'state' ? 2 : 100 }}"></label>@endforeach
            <label>Responsável<select name="assigned_to"><option value="">Todos</option>@foreach($admins as $admin)<option value="{{ $admin->id }}" @selected(($filters['assigned_to'] ?? '') === $admin->id)>{{ $admin->name }}</option>@endforeach</select></label>
            <label>Prioridade<select name="priority"><option value="">Todas</option>@foreach(\App\Models\Prospect::PRIORITIES as $value=>$label)<option value="{{ $value }}" @selected(($filters['priority'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></label>
            <label>Status comercial<select name="commercial_status"><option value="">Todos</option><option value="identified" @selected(($filters['commercial_status'] ?? '') === 'identified')>Identificado</option></select></label>
            <label>Contato<select name="blocked"><option value="">Todos</option><option value="yes" @selected(($filters['blocked'] ?? '') === 'yes')>Bloqueado</option><option value="no" @selected(($filters['blocked'] ?? '') === 'no')>Permitido</option></select></label>
            <label>Por página<select name="per_page" data-page-size>@foreach([10,25,50,100] as $size)<option value="{{ $size }}" @selected(($filters['per_page'] ?? 25) == $size)>{{ $size }}</option>@endforeach</select></label>
            <input type="hidden" name="sort" value="{{ $filters['sort'] ?? 'created_at' }}"><input type="hidden" name="direction" value="{{ $filters['direction'] ?? 'desc' }}">
            @foreach($filters['columns'] ?? [] as $column)<input type="hidden" name="columns[]" value="{{ $column }}">@endforeach
            <button type="submit" class="button button-primary">Aplicar filtros</button><a href="{{ route('admin.prospects.index') }}">Limpar</a>
        </form>
        @php($labels=['business_name'=>'Estabelecimento','category'=>'Segmento','city'=>'Cidade','state'=>'UF','priority'=>'Prioridade','assigned_to'=>'Responsável','commercial_status'=>'Status / contato','created_at'=>'Cadastro'])
        <div class="admin-actions crm-toolbar"><p id="table-result-count" tabindex="-1">{{ $prospects->total() }} resultado(s) · Página {{ $prospects->currentPage() }} de {{ $prospects->lastPage() }}</p><details class="crm-columns"><summary>Colunas visíveis</summary><div>@foreach($labels as $key=>$label)<label><input type="checkbox" data-column-toggle="{{ $key }}" @checked($key === 'business_name' || !isset($filters['columns']) || in_array($key,$filters['columns'],true)) @disabled($key === 'business_name')> {{ $label }}</label>@endforeach</div></details></div>
        <div class="table-wrap" data-table-container data-total="{{ $prospects->total() }}" data-page="{{ $prospects->currentPage() }}" data-page-size="{{ $prospects->perPage() }}" data-sort="{{ $filters['sort'] ?? 'created_at' }}" data-direction="{{ $filters['direction'] ?? 'desc' }}">
            <table class="crm-table"><caption class="sr-only">Estabelecimentos em prospecção. Abra uma ficha para revisar os dados.</caption><thead><tr>
            @foreach($labels as $key=>$label)<th scope="col" data-column="{{ $key }}" aria-sort="{{ ($filters['sort'] ?? 'created_at') === $key ? (($filters['direction'] ?? 'desc') === 'asc' ? 'ascending' : 'descending') : 'none' }}"><a data-sort-column="{{ $key }}" href="{{ route('admin.prospects.index',[...$filters,'sort'=>$key,'direction'=>($filters['sort'] ?? 'created_at') === $key && ($filters['direction'] ?? 'desc') === 'asc' ? 'desc' : 'asc','page'=>1]) }}">{{ $label }} ↕</a></th>@endforeach<th scope="col" class="crm-actions-column">Ações</th></tr></thead>
            <tbody>@forelse($prospects as $prospect)<tr data-record-row>
                <td data-column="business_name"><a class="crm-record-link" href="{{ route('admin.prospects.show',['prospect'=>$prospect,...$filters]) }}">{{ $prospect->business_name }}</a></td>
                <td data-column="category">{{ $prospect->category ?: 'Não informado' }}</td><td data-column="city">{{ $prospect->city }}</td><td data-column="state">{{ $prospect->state }}</td>
                <td data-column="priority">{{ \App\Models\Prospect::PRIORITIES[$prospect->priority] }}</td><td data-column="assigned_to">{{ $prospect->assignee?->name ?: 'Sem responsável' }}</td>
                <td data-column="commercial_status"><span class="crm-badge">Identificado</span>@if($prospect->contact_blocked_at)<strong class="field-error">Contato bloqueado</strong>@endif</td>
                <td data-column="created_at">{{ $prospect->created_at->format('d/m/Y') }}</td><td class="crm-actions-column prospect-row-actions" data-row-actions><a class="crm-context-link" href="{{ route('admin.prospects.show',['prospect'=>$prospect,...$filters]) }}" aria-label="Abrir ficha de {{ $prospect->business_name }}">Ver ficha</a><a class="crm-context-link" href="{{ route('admin.prospects.edit',['prospect'=>$prospect,...$filters]) }}" aria-label="Editar {{ $prospect->business_name }}">Editar</a></td>
            </tr>@empty<tr><td colspan="9">Nenhum estabelecimento encontrado. Cadastre o primeiro prospect ou ajuste os filtros.</td></tr>@endforelse</tbody></table>
        </div>
        <div data-table-pagination>{{ $prospects->links('admin.pagination') }}</div>
    </div>
    <p data-table-loading role="status" aria-live="polite" hidden>Atualizando resultados…</p><div data-table-error class="alert alert-error" role="alert" hidden><p>Não foi possível atualizar a lista. Seus resultados foram mantidos.</p><button type="button" data-table-retry class="button button-secondary">Tentar novamente</button></div>
</section>
@endsection
