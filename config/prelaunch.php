<?php

return [
    'data_pepper' => env('PRELAUNCH_DATA_PEPPER'),
    'confirmation_ttl_minutes' => (int) env('PRELAUNCH_CONFIRMATION_TTL', 60),
    'admin_export_mfa_ttl_minutes' => (int) env('PRELAUNCH_ADMIN_EXPORT_MFA_TTL', 15),
    'categories' => ['Artesanato', 'Bem-estar', 'Comércio', 'Cultura', 'Educação', 'Gastronomia', 'Hospedagem', 'Serviços profissionais', 'Turismo', 'Outros'],
    'privacy' => [
        'controller_name' => env('PRIVACY_CONTROLLER_NAME', 'ROTTA — controlador a confirmar'),
        'contact_email' => env('PRIVACY_CONTACT_EMAIL', 'privacidade@example.test'),
    ],
    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
        'verify_url' => env('TURNSTILE_VERIFY_URL', 'https://challenges.cloudflare.com/turnstile/v0/siteverify'),
    ],
    'google_places' => ['api_key' => env('GOOGLE_MAPS_API_KEY')],
    'cep' => ['base_url' => env('CEP_LOOKUP_BASE_URL', 'https://viacep.com.br/ws')],
];
