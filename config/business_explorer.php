<?php

return [
    'google_api_key' => env('GOOGLE_PLACES_SERVER_API_KEY'),
    'terms_url' => env('GOOGLE_PLACES_APP_TERMS_URL'),
    'privacy_url' => env('GOOGLE_PLACES_APP_PRIVACY_URL'),
    'search_timeout_seconds' => (int) env('GOOGLE_PLACES_SEARCH_TIMEOUT_SECONDS', 15),
    'timeout_seconds' => (int) env('GOOGLE_PLACES_TIMEOUT_SECONDS', 8),
    'page_size' => (int) env('GOOGLE_PLACES_PAGE_SIZE', 5),
    'max_pages' => (int) env('GOOGLE_PLACES_MAX_PAGES', 2),
    'per_admin_minute' => (int) env('GOOGLE_PLACES_PER_ADMIN_MINUTE', 3),
    'per_admin_day' => (int) env('GOOGLE_PLACES_PER_ADMIN_DAY', 10),
    'global_day' => (int) env('GOOGLE_PLACES_GLOBAL_DAY', 20),
    'google_import_enabled' => (bool) env('GOOGLE_PLACES_IMPORT_ENABLED', false),
    'google_persistence_enabled' => (bool) env('GOOGLE_PLACES_PERSISTENCE_ENABLED', false),
    'enterprise_enabled' => (bool) env('GOOGLE_PLACES_ENTERPRISE_ENABLED', false),
    // Server-reviewed permission registry, keyed by Place ID; never infer a license from public access.
    'independent_sources' => json_decode(env('BUSINESS_ENRICHMENT_SOURCES', '{}'), true) ?: [],
];
