@if(session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
@if($errors->any())
    <div class="alert alert-error" role="alert" tabindex="-1" data-prospect-errors>
        <h2>Revise os dados informados</h2>
        <ul>@foreach($errors->getMessages() as $field => $messages)<li><a href="#{{ \Illuminate\Support\Str::before($field,'.') }}">{{ $messages[0] }}</a></li>@endforeach</ul>
    </div>
@endif
