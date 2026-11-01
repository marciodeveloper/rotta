@extends('layouts.app')
@section('title', 'Verificação MFA')
@section('content')
<section class="auth-page"><form class="auth-card" method="POST" action="{{ route('admin.mfa.store') }}">@csrf<div class="eyebrow"><span></span> Segunda etapa</div><h1>Verificação MFA</h1><p>Digite o código de seis dígitos exibido no seu aplicativo autenticador.</p><label class="field"><span>Código</span><input name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required autofocus>@error('code')<b>{{ $message }}</b>@enderror</label><button class="button button-primary" type="submit">Verificar e entrar</button></form></section>
@endsection
