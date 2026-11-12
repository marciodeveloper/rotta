@if ($paginator->hasPages())
<nav class="crm-page-nav" aria-label="Paginação dos resultados">
    <span>Exibindo {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} de {{ $paginator->total() }} resultados</span>
    <div>
        @if($paginator->onFirstPage())<span aria-disabled="true">Anterior</span>@else<a href="{{ $paginator->previousPageUrl() }}" rel="prev">Anterior</a>@endif
        <div class="crm-page-numbers">@foreach($elements as $element)
            @if(is_string($element))<span>{{ $element }}</span>@else
                @foreach($element as $page => $url)
                    @if($page === $paginator->currentPage())<span aria-current="page">{{ $page }}</span>@else<a href="{{ $url }}" aria-label="Ir à página {{ $page }}">{{ $page }}</a>@endif
                @endforeach
            @endif
        @endforeach</div>
        @if($paginator->hasMorePages())<a href="{{ $paginator->nextPageUrl() }}" rel="next">Próxima</a>@else<span aria-disabled="true">Próxima</span>@endif
    </div>
</nav>
@endif
