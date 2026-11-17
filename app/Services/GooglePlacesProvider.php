<?php

namespace App\Services;

use App\Contracts\BusinessSearchProvider;
use App\Exceptions\PlacesUnavailable;
use App\Models\GooglePlaceReference;
use GuzzleHttp\TransferStats;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class GooglePlacesProvider implements BusinessSearchProvider
{
    public const FIELDS = ['id', 'displayName', 'formattedAddress', 'primaryTypeDisplayName', 'googleMapsUri', 'attributions'];

    public const DETAIL_FIELDS = [...self::FIELDS, 'addressComponents', 'primaryType', 'types', 'businessStatus'];

    public const ENTERPRISE_FIELDS = ['nationalPhoneNumber', 'internationalPhoneNumber', 'websiteUri', 'regularOpeningHours', 'rating', 'userRatingCount'];

    public function configurationMessage(): ?string
    {
        if (! is_string(config('business_explorer.google_api_key')) || trim(config('business_explorer.google_api_key')) === '') {
            return 'Explorador indisponível: configure GOOGLE_PLACES_SERVER_API_KEY no servidor.';
        }
        foreach (['terms_url', 'privacy_url'] as $field) {
            if (! self::httpsUrl(config('business_explorer.'.$field))) {
                return 'Configure as URLs públicas dos termos de uso e da política de privacidade da aplicação, incluindo o uso do Google Maps, antes de pesquisar.';
            }
        }

        return null;
    }

    public function search(string $query, int $pageSize, ?string $pageToken = null): array
    {
        $data = $this->request('post', 'places:searchText', implode(',', array_map(fn ($field) => 'places.'.$field, self::FIELDS)).',nextPageToken', array_filter(['textQuery' => $query, 'languageCode' => 'pt-BR', 'regionCode' => 'BR', 'pageSize' => $pageSize, 'pageToken' => $pageToken], fn ($value) => $value !== null));
        if (isset($data['places']) && (! is_array($data['places']) || ! array_is_list($data['places']))) {
            throw new PlacesUnavailable('O Google retornou uma resposta inválida. Tente novamente mais tarde.', 502);
        }
        $places = [];
        foreach (array_slice($data['places'] ?? [], 0, $pageSize) as $place) {
            if (is_array($place) && is_string($place['id'] ?? null) && preg_match(GooglePlaceReference::ID_PATTERN, $place['id'])) {
                $places[] = $this->normalize($place);
            }
        }

        return ['places' => $places, 'next_page_token' => is_string($data['nextPageToken'] ?? null) && strlen($data['nextPageToken']) <= 4096 ? $data['nextPageToken'] : null];
    }

    public function details(string $placeId, bool $enterprise = false): array
    {
        if ($enterprise && ! config('business_explorer.enterprise_enabled')) {
            throw new PlacesUnavailable('A consulta ampliada Google está desabilitada na configuração administrativa.', 422);
        }
        $mask = $enterprise ? [...self::DETAIL_FIELDS, ...self::ENTERPRISE_FIELDS] : self::DETAIL_FIELDS;
        $data = $this->request('get', 'places/'.rawurlencode($placeId), implode(',', $mask), ['languageCode' => 'pt-BR', 'regionCode' => 'BR']);
        if (($data['id'] ?? null) !== $placeId) {
            throw new PlacesUnavailable('O Google retornou detalhes inválidos. Refaça a pesquisa.', 502);
        }

        $components = [];
        foreach (array_slice(is_array($data['addressComponents'] ?? null) ? $data['addressComponents'] : [], 0, 30) as $component) {
            if (! is_array($component)) {
                continue;
            }
            foreach (['route' => 'street', 'street_number' => 'street_number', 'subpremise' => 'complement', 'sublocality' => 'neighborhood', 'sublocality_level_1' => 'neighborhood', 'administrative_area_level_2' => 'city', 'locality' => 'locality', 'administrative_area_level_1' => 'state', 'postal_code' => 'postal_code', 'country' => 'country'] as $type => $field) {
                $value = $component[$field === 'state' ? 'shortText' : 'longText'] ?? null;
                if (in_array($type, is_array($component['types'] ?? null) ? $component['types'] : [], true) && is_string($value)) {
                    $components[$field] = mb_substr($value, 0, 200);
                }
            }
        }
        $components['city'] = $components['locality'] ?? $components['city'] ?? '';

        $extra = [];
        if ($enterprise) {
            foreach (['nationalPhoneNumber' => 'phone', 'internationalPhoneNumber' => 'international_phone'] as $key => $field) {
                $extra[$field] = is_string($data[$key] ?? null) ? mb_substr($data[$key], 0, 60) : '';
            }
            $extra['website'] = self::httpsUrl($data['websiteUri'] ?? null);
            $rating = $data['rating'] ?? null;
            $extra['rating'] = is_numeric($rating) && $rating >= 0 && $rating <= 5 ? (float) $rating : null;
            $count = $data['userRatingCount'] ?? null;
            $extra['rating_count'] = is_int($count) && $count >= 0 ? $count : null;
            $hours = $data['regularOpeningHours']['weekdayDescriptions'] ?? [];
            $extra['opening_hours'] = array_map(fn ($value) => mb_substr($value, 0, 200), array_filter(array_slice(is_array($hours) ? $hours : [], 0, 7), 'is_string'));
        }

        return [...$this->normalize($data), ...$extra, 'queried_at' => now()->toIso8601String(), 'sku' => $enterprise ? 'Enterprise' : 'Pro', 'address_fields' => $components,
            'primary_type' => is_string($data['primaryType'] ?? null) ? mb_substr($data['primaryType'], 0, 100) : '',
            'types' => array_values(array_filter(array_slice(is_array($data['types'] ?? null) ? $data['types'] : [], 0, 30), 'is_string')),
            'business_status' => match ($data['businessStatus'] ?? null) {
                'OPERATIONAL' => 'Em operação', 'CLOSED_TEMPORARILY' => 'Temporariamente fechado', 'CLOSED_PERMANENTLY' => 'Permanentemente fechado', default => 'Não informado',
            },
        ];
    }

    private function request(string $method, string $path, string $mask, array $data): array
    {
        $started = hrtime(true);
        $trace = ['correlation_id' => Context::get('places_correlation_id', (string) Str::uuid()),
            'operation' => $path === 'places:searchText' ? 'text_search' : 'details',
            'started_at' => now()->toISOString(), 'attempts' => 0,
            'quota' => Context::get('places_quota_status', 'not_managed_here')];
        $timeout = max(1, min(15, (int) config($path === 'places:searchText' ? 'business_explorer.search_timeout_seconds' : 'business_explorer.timeout_seconds')));
        $trace['timeout_seconds'] = $timeout;
        $trace['connect_timeout_seconds'] = 3;
        $stats = [];
        if ($message = $this->configurationMessage()) {
            $this->fail($message, 503, 'configuration_error', $trace, $started);
        }
        $trace['attempts'] = 1;
        Log::info('places.http.started', $trace);
        try {
            // Exactly one attempt: a retry could bill twice after an ambiguous timeout.
            $response = Http::acceptJson()->asJson()->connectTimeout(3)
                ->timeout($timeout)
                ->withOptions(['allow_redirects' => false, 'on_stats' => function (TransferStats $transfer) use (&$stats): void {
                    $stats = $transfer->getHandlerStats();
                    $error = $transfer->getHandlerErrorData();
                    if (is_int($error)) {
                        $stats['errno'] = $error;
                    }
                }])
                ->withHeaders(['X-Goog-Api-Key' => config('business_explorer.google_api_key'), 'X-Goog-FieldMask' => $mask])
                ->{$method}('https://places.googleapis.com/v1/'.$path, $data);
        } catch (ConnectionException $exception) {
            $context = [];
            for ($cause = $exception; $cause; $cause = $cause->getPrevious()) {
                if (method_exists($cause, 'getHandlerContext')) {
                    $context = $cause->getHandlerContext();
                    break;
                }
            }
            $stats = [...$stats, ...$context];
            $errno = $stats['errno'] ?? null;
            if (! is_int($errno) && preg_match('/cURL error (\d+)/', $exception->getMessage(), $match)) {
                $errno = (int) $match[1];
            }
            $stats['errno'] = $errno;
            $category = match ($errno) {
                5, 6 => 'dns_failure', 7 => 'connection_failure', 35, 51, 60, 77 => 'tls_failure',
                28 => ! array_key_exists('connect_time', $stats) ? 'transport_timeout' : (! empty($stats['appconnect_time']) ? 'response_timeout' : (! empty($stats['connect_time']) ? 'tls_timeout' : 'connection_timeout')),
                default => 'transport_failure',
            };
            $message = match ($category) {
                'dns_failure' => 'Não foi possível localizar o serviço Google. Sua pesquisa foi preservada; tente novamente mais tarde.',
                'tls_failure' => 'Não foi possível estabelecer uma conexão segura com o Google. Sua pesquisa foi preservada.',
                'connection_timeout', 'tls_timeout', 'response_timeout', 'transport_timeout' => 'A consulta ao Google excedeu o prazo. Sua pesquisa foi preservada; você pode tentar novamente.',
                default => 'Não foi possível conectar ao Google. Sua pesquisa foi preservada; você pode tentar novamente.',
            };
            $receivedStatus = is_numeric($stats['http_code'] ?? null) && (int) $stats['http_code'] >= 100 && (int) $stats['http_code'] <= 599 ? (int) $stats['http_code'] : null;
            $this->fail($message, $errno === 28 ? 504 : 503, $category, $trace, $started, $stats, $receivedStatus, $exception::class);
        } catch (Throwable $exception) {
            $this->fail('Não foi possível concluir a comunicação com o Google. Sua pesquisa foi preservada.', 502, 'unexpected_transport_failure', $trace, $started, $stats, null, $exception::class);
        }
        $upstream = $response->status();
        if (! $response->successful()) {
            [$message, $local, $category] = match ($upstream) {
                401, 403 => ['O Google recusou a credencial ou as permissões. Solicite a revisão da configuração ao administrador.', 502, 'upstream_authentication'],
                429 => ['A quota do Google foi atingida. Aguarde antes de tentar novamente.', 429, 'upstream_quota'],
                400, 404 => ['A consulta ou referência não está mais disponível. Revise a pesquisa e tente novamente.', 422, 'upstream_invalid_request'],
                default => ['O Google está temporariamente indisponível. Sua pesquisa foi preservada; tente novamente mais tarde.', 503, 'upstream_unavailable'],
            };
            $this->fail($message, $local, $category, $trace, $started, $stats, $upstream);
        }
        $body = $response->json();
        $invalidSearch = $path === 'places:searchText' && isset($body['places']) && (! is_array($body['places']) || ! array_is_list($body['places']));
        $invalidDetails = $path !== 'places:searchText' && ($body['id'] ?? null) !== rawurldecode(substr($path, strlen('places/')));
        if (! is_array($body) || isset($body['error']) || $invalidSearch || $invalidDetails) {
            $this->fail('O Google retornou uma resposta inválida. Sua pesquisa foi preservada.', 502, 'malformed_response', $trace, $started, $stats, $upstream);
        }
        Log::info('places.http.completed', [...$trace, ...$this->metrics($stats), 'duration_ms' => round((hrtime(true) - $started) / 1e6, 2), 'local_status' => 200, 'upstream_status' => $upstream, 'category' => 'success']);

        return $body;
    }

    private function metrics(array $stats): array
    {
        $metrics = ['curl_errno' => is_int($stats['errno'] ?? null) ? $stats['errno'] : null];
        foreach (['namelookup_time' => 'dns_ms', 'connect_time' => 'connect_ms', 'appconnect_time' => 'tls_ms', 'starttransfer_time' => 'first_byte_ms'] as $key => $label) {
            $metrics[$label] = is_numeric($stats[$key] ?? null) ? round((float) $stats[$key] * 1000, 2) : null;
        }

        foreach (['size_upload' => 'uploaded_bytes', 'size_download' => 'downloaded_bytes'] as $key => $label) {
            $metrics[$label] = is_numeric($stats[$key] ?? null) ? (int) $stats[$key] : null;
        }

        return $metrics;
    }

    private function fail(string $message, int $local, string $category, array $trace, int $started, array $stats = [], ?int $upstream = null, ?string $exceptionClass = null): never
    {
        $diagnostics = [...$trace, ...$this->metrics($stats), 'duration_ms' => round((hrtime(true) - $started) / 1e6, 2), 'local_status' => $local, 'upstream_status' => $upstream, 'category' => $category, 'exception_class' => $exceptionClass];
        Log::warning('places.http.completed', $diagnostics);

        throw new PlacesUnavailable($message, $local, $diagnostics);
    }

    private function normalize(array $place): array
    {
        $text = fn ($value) => is_string($value) ? mb_substr($value, 0, 800) : '';
        $maps = self::httpsUrl($place['googleMapsUri'] ?? null);
        if ($maps && (parse_url($maps, PHP_URL_HOST) !== 'maps.google.com' && (! in_array(parse_url($maps, PHP_URL_HOST), ['google.com', 'www.google.com'], true) || ! str_starts_with(parse_url($maps, PHP_URL_PATH) ?? '', '/maps')))) {
            $maps = null;
        }
        $attributions = [];
        foreach (array_slice(is_array($place['attributions'] ?? null) ? $place['attributions'] : [], 0, 20) as $attribution) {
            if (is_array($attribution) && is_string($attribution['provider'] ?? null)) {
                $attributions[] = ['name' => $text($attribution['provider']), 'url' => self::webUrl($attribution['providerUri'] ?? null)];
            }
        }

        return ['place_id' => $place['id'], 'name' => $text($place['displayName']['text'] ?? null), 'address' => $text($place['formattedAddress'] ?? null), 'category' => $text($place['primaryTypeDisplayName']['text'] ?? null), 'maps_url' => $maps, 'attributions' => $attributions];
    }

    public static function httpsUrl(mixed $value): ?string
    {
        return self::webUrl($value) && parse_url($value, PHP_URL_SCHEME) === 'https' ? $value : null;
    }

    private static function webUrl(mixed $value): ?string
    {
        return is_string($value) && strlen($value) <= 2048 && filter_var($value, FILTER_VALIDATE_URL) && in_array(parse_url($value, PHP_URL_SCHEME), ['http', 'https'], true) && ! parse_url($value, PHP_URL_USER) && ! parse_url($value, PHP_URL_PASS) ? $value : null;
    }
}
