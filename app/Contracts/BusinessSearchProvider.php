<?php

namespace App\Contracts;

interface BusinessSearchProvider
{
    public function configurationMessage(): ?string;

    /** @return array{places: array, next_page_token: ?string} Transient data, never persist this result. */
    public function search(string $query, int $pageSize, ?string $pageToken = null): array;

    /** @return array Transient display data; only the Place ID is eligible for storage. */
    public function details(string $placeId, bool $enterprise = false): array;

    /** @return array Transient reputation/summary, never stored in CRM or context tokens. */
    public function intelligence(string $placeId, bool $summary = false): array;
}
