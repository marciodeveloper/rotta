@extends('layouts.app')
@section('title', 'Confirmação de e-mail')
@section('content')
<section class="message-page"><div class="message-card"><span class="message-icon">{{ $confirmed ? '✓' : 'i' }}</span><div class="eyebrow"><span></span> Confirmação de e-mail</div><h1>{{ $confirmed ? 'E-mail confirmado.' : 'Não foi possível confirmar este link.' }}</h1><p>{{ $confirmed ? 'Seu pré-cadastro está agora aguardando análise da equipe ROTTA.' : 'O link pode ter expirado ou já ter sido utilizado. Entre em contato pelo canal oficial se precisar de ajuda.' }}</p><a class="button button-primary" href="{{ route('pre-registration.create') }}">Voltar ao início</a></div></section>
@endsection
