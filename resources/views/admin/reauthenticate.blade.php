@extends('layouts.app')
@section('title', 'Reautenticação administrativa')
@section('content')
<section class="auth-page"><form class="auth-card" method="POST" action="{{ route('admin.reauthenticate.store') }}">
    @csrf
    <div class="eyebrow"><span></span> Ação sensível</div><h1>Confirme sua senha</h1>
    <p>Após confirmar, volte à listagem e solicite novamente a exportação.</p>
    @if(session('status'))<p role="status">{{ session('status') }}</p>@endif
    <label class="field"><span>Senha</span><input type="password" name="password" autocomplete="current-password" maxlength="1024" required @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
        @error('password')<b id="password-error" role="alert">{{ $message }}</b>@enderror
    </label>
    <button class="button button-primary" type="submit">Confirmar senha</button>
    <a href="{{ route('admin.dashboard') }}">Voltar à listagem</a>
</form></section>
@endsection
