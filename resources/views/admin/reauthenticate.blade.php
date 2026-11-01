@extends('layouts.app')
@section('title', 'Reautenticação administrativa')
@section('content')
<section class="auth-page"><form class="auth-card" method="POST" action="{{ route('admin.reauthenticate.store') }}">@csrf<div class="eyebrow"><span></span> Ação sensível</div><h1>Confirme sua identidade</h1><p>Informe novamente sua senha e um segundo fator novo.</p><label class="field"><span>Senha</span><input type="password" name="password" autocomplete="current-password" required></label><label class="field"><span>MFA ou recuperação</span><input name="code" maxlength="32" autocomplete="one-time-code" required>@error('code')<b>{{ $message }}</b>@enderror</label><button class="button button-primary" type="submit">Reautenticar</button></form></section>
@endsection
