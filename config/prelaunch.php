<?php

return [
    'data_pepper' => env('PRELAUNCH_DATA_PEPPER'),
    'confirmation_ttl_minutes' => (int) env('PRELAUNCH_CONFIRMATION_TTL', 60),
    'admin_export_password_ttl_minutes' => (int) env('PRELAUNCH_ADMIN_EXPORT_PASSWORD_TTL', 15),
    'media_disk' => env('PRELAUNCH_MEDIA_DISK', 'prelaunch_media'),
    'media' => [
        'max_pixels' => 16_000_000,
        'max_dimension' => 6_000,
        'optimized_max_dimension' => 2_400,
        'max_image_bytes' => (int) env('PRELAUNCH_MEDIA_MAX_IMAGE_BYTES', 5_242_880),
        'max_files_per_registration' => (int) env('PRELAUNCH_MEDIA_MAX_FILES', 8),
        'global_quota_bytes' => (int) env('PRELAUNCH_MEDIA_GLOBAL_QUOTA_BYTES', 5_368_709_120),
        'alert_percent' => (int) env('PRELAUNCH_MEDIA_ALERT_PERCENT', 70),
        'block_percent' => (int) env('PRELAUNCH_MEDIA_BLOCK_PERCENT', 80),
        'minimum_free_bytes' => (int) env('PRELAUNCH_MEDIA_MINIMUM_FREE_BYTES', 21_474_836_480),
    ],
    'backup' => [
        'root' => env('PRELAUNCH_BACKUP_ROOT', '/var/lib/rotta-prelaunch/backups'),
        'timeout_seconds' => (int) env('PRELAUNCH_BACKUP_TIMEOUT', 900),
    ],
    'categories' => ['Artesanato', 'Bem-estar', 'Comércio', 'Cultura', 'Educação', 'Gastronomia', 'Hospedagem', 'Serviços profissionais', 'Turismo', 'Outros'],
    'privacy' => [
        'controller_name' => env('PRIVACY_CONTROLLER_NAME', 'ROTTA — controlador a confirmar'),
        'contact_email' => env('PRIVACY_CONTACT_EMAIL', 'privacidade@example.test'),
    ],
    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
        'verify_url' => env('TURNSTILE_VERIFY_URL', 'https://challenges.cloudflare.com/turnstile/v0/siteverify'),
        'expected_hostname' => env('TURNSTILE_EXPECTED_HOSTNAME', 'rotta.serradev.com'),
        'expected_action' => env('TURNSTILE_EXPECTED_ACTION', 'pre_registration'),
        'max_challenge_age_seconds' => (int) env('TURNSTILE_MAX_CHALLENGE_AGE', 300),
    ],
    'google_places' => ['api_key' => env('GOOGLE_MAPS_API_KEY')],
    'cep' => ['base_url' => env('CEP_LOOKUP_BASE_URL', 'https://viacep.com.br/ws')],
];
