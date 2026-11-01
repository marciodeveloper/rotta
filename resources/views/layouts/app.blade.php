<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#17201b">
    <meta name="description" content="Pré-cadastro de empresas e profissionais na ROTTA.">
    <meta name="robots" content="noindex,nofollow">
    <link rel="icon" type="image/svg+xml" href="{{ asset('assets/brand/rotta-mark.svg') }}">
    <title>@yield('title', 'Pré-cadastro') — ROTTA</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body>
    <a class="skip-link" href="#conteudo">Pular para o conteúdo</a>
    <header class="site-header">
        <div class="header-inner">
            <a href="{{ route('pre-registration.create') }}" aria-label="ROTTA — início">
                <img class="brand-lockup" src="{{ asset('assets/brand/rotta-lockup.svg') }}" alt="ROTTA — Pessoas, lugares e histórias em movimento">
            </a>
            <span class="header-label">Pré-cadastro</span>
        </div>
    </header>
    <main id="conteudo">@yield('content')</main>
    <footer class="site-footer">
        <div><img src="{{ asset('assets/brand/rotta-mark.svg') }}" alt="" aria-hidden="true"><span>Pessoas, lugares e histórias em movimento.</span></div>
        <p>Pré-cadastro não garante aprovação ou publicação imediata.</p>
    </footer>
</body>
</html>
