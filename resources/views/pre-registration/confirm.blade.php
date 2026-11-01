@extends('layouts.app')

@section('title', 'Confirmar e-mail')

@section('content')
<main class="message-page">
    <section class="message-card">
        <span class="form-kicker">Confirmação segura</span>
        <h1>Confirme seu e-mail</h1>
        <p>Para concluir esta etapa, confirme abaixo. Apenas abrir esta página não utiliza o link.</p>
        <form method="POST" action="{{ route('pre-registration.confirm.store', ['token' => $token]) }}">
            @csrf
            <button class="button button-primary" type="submit">Confirmar e-mail</button>
        </form>
    </section>
</main>
@endsection
