@extends('layouts.app')
@section('title', 'Administração')
@section('content')
<section class="auth-page"><form class="auth-card" method="POST" action="{{ route('admin.login.store') }}">@csrf<div class="eyebrow"><span></span> Área restrita</div><h1>Administração ROTTA</h1><p>Entre com seu e-mail e sua senha administrativa.</p><label class="field"><span>E-mail</span><input type="email" name="email" value="{{ old('email') }}" autocomplete="username" required>@error('email')<b>{{ $message }}</b>@enderror</label><label class="field"><span>Senha</span><input type="password" name="password" autocomplete="current-password" required>@error('password')<b>{{ $message }}</b>@enderror</label><button class="button button-primary" type="submit">Entrar</button></form></section>
@endsection
