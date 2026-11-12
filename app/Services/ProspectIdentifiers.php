<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Prospect;

class ProspectIdentifiers
{
    public function __construct(private NormalizeProspect $normalize, private SensitiveHash $hash) {}

    public function fingerprints(array $data): array
    {
        $keys = [];
        if (is_string($data['business_name'] ?? null) && is_string($data['city'] ?? null) && is_string($data['state'] ?? null)) {
            $keys['identity_location'] = $this->hash->makeFor('prospect-identity', json_encode([$this->normalize->key($data['business_name']), $this->normalize->key($data['city']), $data['state']], JSON_THROW_ON_ERROR));
        }
        if (is_string($data['email'] ?? null) && $data['email'] !== '') {
            $keys['email'] = $this->hash->makeFor('prospect-email', $data['email']);
        }
        if (is_string($data['phone'] ?? null) && $data['phone'] !== '') {
            $keys['phone'] = $this->hash->makeFor('prospect-phone', $this->normalize->phoneKey($data['phone']));
        }

        return $keys;
    }

    public function record(Prospect $prospect, Admin $admin): void
    {
        foreach ($this->fingerprints($prospect->only(['business_name', 'city', 'state', 'email', 'phone'])) as $kind => $fingerprint) {
            $prospect->identifiers()->firstOrCreate(['kind' => $kind, 'fingerprint' => $fingerprint], ['recorded_by' => $admin->id]);
        }
    }
}
