@extends('layouts.app')
@section('title', 'Pré-cadastro recebido')
@section('content')
<section class="message-page"><div class="message-card"><span class="message-icon">✓</span><div class="eyebrow"><span></span> Manifestação recebida</div><h1>Obrigado por querer fazer parte da ROTTA.</h1><p>Se os dados puderem ser processados, enviaremos uma confirmação ao e-mail informado. Por segurança, esta resposta é a mesma mesmo quando já existe um pré-cadastro.</p><p class="message-note">O envio não garante aprovação ou publicação imediata.</p><a class="button button-primary" href="{{ route('pre-registration.create') }}">Voltar ao início</a></div></section>
@endsection
