<?php

namespace App\Services;

class GoogleMapsLinks
{
    public static function safe(mixed $url): ?string
    {
        if (! GooglePlacesProvider::httpsUrl($url)) {
            return null;
        }
        $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');

        return $host === 'google.com' || str_ends_with($host, '.google.com') || $host === 'maps.app.goo.gl' ? $url : null;
    }

    /** Only universal Maps URLs are synthesized. Review/photo deep links must come from Google. */
    public static function forPlace(string $placeId, array $links = [], ?string $mapsUrl = null): array
    {
        $map = self::safe($links['placeUri'] ?? $mapsUrl) ?? 'https://www.google.com/maps/search/?'.http_build_query(['api' => 1, 'query' => 'Estabelecimento', 'query_place_id' => $placeId], '', '&', PHP_QUERY_RFC3986);

        return ['place' => $map,
            'directions' => self::safe($links['directionsUri'] ?? null) ?? 'https://www.google.com/maps/dir/?'.http_build_query(['api' => 1, 'destination' => 'Estabelecimento', 'destination_place_id' => $placeId], '', '&', PHP_QUERY_RFC3986),
            'reviews' => self::safe($links['reviewsUri'] ?? null), 'photos' => self::safe($links['photosUri'] ?? null)];
    }
}
