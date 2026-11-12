<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Prospect;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DetectProspectDuplicates
{
    public function __construct(private NormalizeProspect $normalizer, private ProspectIdentifiers $identifiers) {}

    public function find(array $data, ?string $except = null): Collection
    {
        $keys = $this->identifiers->fingerprints($data);
        if ($keys === []) {
            return new Collection;
        }
        $matching = function ($query) use ($keys): void {
            $query->where(function ($query) use ($keys): void {
                foreach ($keys as $kind => $fingerprint) {
                    $query->orWhere(fn ($query) => $query->where('kind', $kind)->where('fingerprint', $fingerprint));
                }
            });
        };
        $results = Prospect::query()->when($except, fn ($q) => $q->where('id', '!=', $except))
            ->where(function ($q) use ($data, $keys, $matching): void {
                $q->whereHas('identifiers', $matching);
                if (isset($keys['identity_location'])) {
                    $q->orWhere(fn ($q) => $q->where('normalized_name', $this->normalizer->key($data['business_name']))->where('normalized_city', $this->normalizer->key($data['city']))->where('state', $data['state']));
                }
                if (isset($keys['email'])) {
                    $q->orWhere('email_hash', $keys['email']);
                }
                if (isset($keys['phone'])) {
                    $q->orWhere('phone_hash', $keys['phone']);
                }
            })->with(['identifiers' => $matching])->orderBy('id')->get(['id', 'business_name', 'normalized_name', 'normalized_city', 'state', 'city', 'email_hash', 'phone_hash', 'contact_blocked_at', 'lock_version']);
        foreach ($results as $result) {
            $current = [
                'identity_location' => $this->identifiers->fingerprints(['business_name' => $result->normalized_name, 'city' => $result->normalized_city, 'state' => $result->state])['identity_location'],
                'email' => $result->email_hash, 'phone' => $result->phone_hash,
            ];
            $historical = $result->identifiers->contains(fn ($identifier) => ($current[$identifier->kind] ?? null) !== $identifier->fingerprint);
            $result->setAttribute('historical_match', $historical);
        }

        return $results;
    }

    public function phoneKey(string $phone): string
    {
        return $this->normalizer->phoneKey($phone);
    }

    public function guard(array $data, array $decisions, Admin $admin, ?string $except = null): array
    {
        $duplicates = $this->find($data, $except);
        $blocked = $duplicates->filter(fn ($p) => $p->contact_blocked_at !== null);
        if ($blocked->isNotEmpty()) {
            Gate::forUser($admin)->authorize('reviewBlockedMatch', Prospect::class);
            $versions = $decisions['blocked_match_versions'] ?? [];
            $reviewed = ! empty($decisions['blocked_match_acknowledged']) && ! empty($decisions['blocked_match_reason']);
            foreach ($blocked as $match) {
                $reviewed = $reviewed && isset($versions[$match->id]) && (int) $versions[$match->id] === $match->lock_version;
            }
            if (! $reviewed) {
                throw ValidationException::withMessages(['blocked_match_acknowledged' => 'Há correspondências atuais ou históricas com contatos bloqueados. Abra as fichas e faça uma revisão explícita, justificada e atualizada antes de declarar que são estabelecimentos distintos.']);
            }
        }
        if ($duplicates->isNotEmpty() && (empty($decisions['duplicate_acknowledged']) || empty($decisions['duplicate_reason']))) {
            throw ValidationException::withMessages(['duplicate_acknowledged' => 'Encontramos possíveis duplicidades. Consulte as fichas, confirme a revisão e justifique a criação ou alteração.']);
        }

        return ['candidate_ids' => $duplicates->modelKeys(), 'blocked_versions' => $blocked->mapWithKeys(fn ($p) => [$p->id => $p->lock_version])->all(), 'historical_ids' => $duplicates->filter(fn ($p) => $p->historical_match)->modelKeys()];
    }
}
