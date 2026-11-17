<?php

namespace App\Services;

use App\Exceptions\PlacesUnavailable;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\IpUtils;
use Throwable;

class AuthorizedBusinessWebsite
{
    public function availability(string $placeId): array
    {
        $source = config('business_explorer.independent_sources.'.$placeId);
        if (! is_array($source)) {
            return ['available' => false, 'reason_code' => 'source_not_configured', 'message' => 'Não há website independente autorizado nem provedor licenciado configurado para esta referência. Dados Google estão disponíveis somente para consulta. Complete o cadastro manualmente. Instagram não identificado — adicionar manualmente.'];
        }
        if (($source['storage_permitted'] ?? false) !== true || ($source['identity_verified'] ?? false) !== true || ! is_string($source['permission_reference'] ?? null) || mb_strlen($source['permission_reference']) < 10 || ! is_string($source['expected_name'] ?? null) || trim($source['expected_name']) === '') {
            return ['available' => false, 'reason_code' => 'source_permission_unverified', 'message' => 'A fonte está cadastrada, mas sua identidade ou permissão de armazenamento ainda não foi verificada. Complete o cadastro manualmente.'];
        }

        return ['available' => true, 'reason_code' => null, 'message' => 'Fonte independente configurada.'];
    }

    public function lookup(string $placeId): array
    {
        $availability = $this->availability($placeId);
        if (! $availability['available']) {
            return ['fields' => [], ...$availability];
        }
        $source = config('business_explorer.independent_sources.'.$placeId);
        $url = $source['url'] ?? null;
        if (! GooglePlacesProvider::httpsUrl($url)) {
            throw new PlacesUnavailable('A fonte independente configurada tem endereço inválido.', 422);
        }
        $host = parse_url($url, PHP_URL_HOST);
        if (! ReferenceFieldProvenance::independentSource($host) || preg_match('~(^|\.)(instagram\.com|facebook\.com)$~i', $host)) {
            throw new PlacesUnavailable('Esta fonte não está habilitada para leitura independente.', 422);
        }
        try {
            $addresses = $this->resolveAddresses($host);
        } catch (Throwable) {
            throw new PlacesUnavailable('Não foi possível resolver o endereço da fonte independente. Tente novamente mais tarde.');
        }
        if (! $addresses || parse_url($url, PHP_URL_PORT) && parse_url($url, PHP_URL_PORT) !== 443 || filter_var($host, FILTER_VALIDATE_IP)) {
            throw new PlacesUnavailable('O endereço da fonte independente foi bloqueado por segurança.', 422);
        }
        foreach ($addresses as $ip) {
            $denied = str_contains($ip, ':') ? ['::/128', '::1/128', '::ffff:0:0/96', '64:ff9b::/96', '64:ff9b:1::/48', '100::/64', '2001:db8::/32', 'fc00::/7', 'fe80::/10', 'ff00::/8'] : ['100.64.0.0/10', '192.0.0.0/24', '192.0.2.0/24', '198.18.0.0/15', '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4'];
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) || IpUtils::checkIp($ip, $denied)) {
                throw new PlacesUnavailable('O destino da fonte independente foi bloqueado por segurança.', 422);
            }
        }
        usort($addresses, fn ($left, $right) => (int) str_contains($left, ':') <=> (int) str_contains($right, ':'));
        $pinned = str_contains($addresses[0], ':') ? '['.$addresses[0].']' : $addresses[0];
        $origin = 'https://'.$host;
        $robots = $this->fetch($origin.'/robots.txt', $host, $pinned, 32768, true);
        if (! $this->robotsAllow($robots, parse_url($url, PHP_URL_PATH) ?: '/')) {
            throw new PlacesUnavailable('O website não autoriza esta consulta automatizada.', 422);
        }
        $html = $this->fetch($url, $host, $pinned, 262144);
        $previous = libxml_use_internal_errors(true);
        try {
            $document = new DOMDocument;
            $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            $xpath = new DOMXPath($document);
            $business = null;
            $expected = (new NormalizeProspect)->key(is_string($source['expected_name'] ?? null) ? $source['expected_name'] : '');
            if ($expected === '') {
                throw new PlacesUnavailable('Verifique a identidade da fonte independente antes de habilitá-la.', 422);
            }
            foreach ($xpath->query('//script[@type="application/ld+json"]') as $script) {
                $data = json_decode($script->textContent, true, 32);
                foreach ($this->businessNodes(is_array($data) ? $data : []) as $node) {
                    if (is_string($node['name'] ?? null) && (new NormalizeProspect)->key($node['name']) === $expected) {
                        if ($business !== null) {
                            throw new PlacesUnavailable('A fonte contém identidades ambíguas; revise o estabelecimento.', 422);
                        }
                        $business = $node;
                    }
                }
            }
            if (! $business) {
                return ['fields' => [], 'reason_code' => 'identity_not_matched', 'message' => 'A identidade comercial não pôde ser confirmada na fonte autorizada. Instagram não identificado — adicionar manualmente.'];
            }
            $address = is_array($business['address'] ?? null) ? $business['address'] : [];
            $fields = ['business_name' => $business['name'], 'website' => $origin, 'phone' => $business['telephone'] ?? null, 'email' => $business['email'] ?? null,
                'street' => $address['streetAddress'] ?? null, 'city' => $address['addressLocality'] ?? null, 'state' => $address['addressRegion'] ?? null, 'postal_code' => $address['postalCode'] ?? null];
            if (is_string($fields['street']) && preg_match('/^(.+),\s*(\d+[a-zA-Z]?)$/u', $fields['street'], $parts)) {
                $fields['street'] = trim($parts[1]);
                $fields['street_number'] = $parts[2];
            }
            $propertyFields = ['Número' => 'street_number', 'Complemento' => 'complement', 'Bairro' => 'neighborhood', 'Segmento' => 'category'];
            foreach (is_array($business['additionalProperty'] ?? null) ? $business['additionalProperty'] : [] as $property) {
                if (is_array($property) && is_string($property['name'] ?? null) && isset($propertyFields[$property['name']]) && is_string($property['value'] ?? null)) {
                    $fields[$propertyFields[$property['name']]] = $property['value'];
                }
            }
            $social = [];
            foreach ((array) ($business['sameAs'] ?? []) as $link) {
                if ($instagram = $this->instagram($link)) {
                    $social[] = $instagram;
                }
            }
            foreach ($xpath->query('//a[@href]') as $anchor) {
                if ($instagram = $this->instagram($anchor->getAttribute('href'))) {
                    $social[] = $instagram;
                }
            }
            $social = array_values(array_unique($social));
            foreach (['phone' => 'tel:', 'email' => 'mailto:'] as $field => $prefix) {
                if (! empty($fields[$field])) {
                    continue;
                }
                $values = [];
                foreach ($xpath->query('//a[@href]') as $anchor) {
                    $href = $anchor->getAttribute('href');
                    if (str_starts_with(strtolower($href), $prefix)) {
                        $value = explode('?', substr($href, strlen($prefix)), 2)[0];
                        $normalized = (new NormalizeProspect)->handle([$field => rawurldecode($value)]);
                        if (Validator::make([$field => $normalized[$field]], [$field => ProspectRules::fields()[$field]])->passes() && ! empty($normalized[$field])) {
                            $values[] = $normalized[$field];
                        }
                    }
                }
                $values = array_values(array_unique($values));
                if (count($values) === 1) {
                    $fields[$field] = $values[0];
                }
            }
            if (count($social) === 1) {
                $fields['instagram'] = $social[0];
            }
            $fields = (new NormalizeProspect)->handle($fields);
            $allowed = [];
            foreach (ReferenceFieldProvenance::fields() as $field => $label) {
                if (is_string($fields[$field] ?? null) && $fields[$field] !== '' && Validator::make([$field => $fields[$field]], [$field => ProspectRules::fields()[$field]])->passes()) {
                    $allowed[$field] = $fields[$field];
                }
            }

            return ['fields' => $allowed, 'reason_code' => $allowed ? null : 'no_valid_fields', 'source' => mb_substr($url.' | '.$source['permission_reference'], 0, 500), 'evidence' => hash('sha256', $html),
                'message' => isset($allowed['instagram']) ? 'Instagram identificado no website autorizado e vinculado ao estabelecimento.' : 'Instagram não identificado — adicionar manualmente.'];
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /** Every answer is checked; public IPv4 is preferred and pinned to prevent rebinding. */
    public function resolveAddresses(string $host): array
    {
        $records = dns_get_record($host, DNS_A | DNS_AAAA);

        return array_values(array_filter(array_map(fn ($record) => $record['ip'] ?? $record['ipv6'] ?? null, $records ?: [])));
    }

    private function fetch(string $url, string $host, string $ip, int $limit, bool $robots = false): string
    {
        try {
            $response = Http::withHeaders(['User-Agent' => 'ROTTAProspectingBot/1.0', 'Accept' => $robots ? 'text/plain' : 'text/html', 'Accept-Encoding' => 'identity'])
                ->connectTimeout(2)->timeout(5)->withOptions(['allow_redirects' => false, 'decode_content' => false, 'proxy' => '', 'read_timeout' => 3,
                    'progress' => function ($total, $downloaded) use ($limit): void {
                        if ($downloaded > $limit || $total > $limit) {
                            throw new PlacesUnavailable('O conteúdo da fonte ultrapassa o limite de segurança.', 422);
                        }
                    },
                    'curl' => [CURLOPT_RESOLVE => [$host.':443:'.$ip]]])->get($url);
            if ($robots && $response->status() === 404) {
                return '';
            }
            $type = strtolower(explode(';', $response->header('Content-Type'))[0]);
            if (! $response->successful() || ! in_array(strtolower($response->header('Content-Encoding')), ['', 'identity'], true) || ! in_array($type, $robots ? ['text/plain'] : ['text/html', 'application/xhtml+xml'], true) || (int) $response->header('Content-Length') > $limit) {
                throw new PlacesUnavailable('A fonte independente recusou a consulta ou retornou conteúdo não permitido.', 422);
            }
            $stream = $response->toPsrResponse()->getBody();
            $body = '';
            while (! $stream->eof() && strlen($body) <= $limit) {
                $body .= $stream->read(min(8192, $limit + 1 - strlen($body)));
            }
            $stream->close();
            if (strlen($body) > $limit) {
                throw new PlacesUnavailable('O conteúdo da fonte ultrapassa o limite de segurança.', 422);
            }

            return $body;
        } catch (PlacesUnavailable $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new PlacesUnavailable('Não foi possível consultar a fonte independente no prazo esperado.');
        }
    }

    private function robotsAllow(string $robots, string $path): bool
    {
        foreach (preg_split('/\r?\n/', $robots) as $line) {
            $line = trim(explode('#', $line, 2)[0]);
            // Conservative: honour matching disallow rules even for other agents.
            if (preg_match('/^disallow:\s*(.+)$/i', $line, $match)) {
                $pattern = str_replace('\*', '.*', preg_quote(trim($match[1]), '~'));
                $pattern = preg_replace('/\\\\\$$/', '$', $pattern);
                if (preg_match('~^'.$pattern.'~', $path)) {
                    return false;
                }
            }
        }

        return true;
    }

    private function businessNodes(array $data, int $depth = 0): array
    {
        if ($depth > 8) {
            return [];
        }
        $types = array_map(fn ($type) => is_string($type) ? preg_replace('~^https?://schema.org/~', '', $type) : '', (array) ($data['@type'] ?? []));
        $nodes = isset($data['name']) && array_intersect($types, ['LocalBusiness', 'Restaurant', 'CafeOrCoffeeShop', 'Store', 'ProfessionalService', 'FoodEstablishment', 'Organization']) ? [$data] : [];
        foreach ($data as $value) {
            if (is_array($value)) {
                array_push($nodes, ...$this->businessNodes($value, $depth + 1));
            }
        }

        return $nodes;
    }

    private function instagram(mixed $url): ?string
    {
        if (! is_string($url) || ! preg_match('~^https://(?:www\.)?instagram\.com/([A-Za-z0-9._]{1,30})/?(?:\?[^#]*)?$~D', $url, $match) || in_array(strtolower($match[1]), ['p', 'reel', 'reels', 'stories', 'explore', 'accounts', 'direct'], true)) {
            return null;
        }

        return 'https://instagram.com/'.strtolower($match[1]);
    }
}
