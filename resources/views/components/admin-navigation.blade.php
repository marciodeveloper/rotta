<nav class="admin-actions" aria-label="Módulos administrativos">
    <a class="button button-secondary" href="{{ route('admin.dashboard') }}" @if(request()->routeIs('admin.dashboard','admin.pre-registrations.*')) aria-current="page" @endif>Pré-cadastros</a>
    @can('viewAny', \App\Models\Prospect::class)
        <a class="button button-secondary" href="{{ route('admin.prospects.index') }}" @if(request()->routeIs('admin.prospects.*')) aria-current="page" @endif>Prospecção</a>
    @endcan
</nav>
