<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Prospect;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CreateProspect
{
    public function __construct(private NormalizeProspect $normalize, private DetectProspectDuplicates $duplicates, private SensitiveHash $hash, private ProspectAudit $audit, private ProspectIdentifiers $identifiers) {}

    public function handle(array $input, Admin $admin): Prospect
    {
        Gate::forUser($admin)->authorize('create', Prospect::class);
        $input = $this->normalize->decisions($input);
        $data = $this->normalize->handle($input);
        $validated = Validator::make([...$input, ...$data, ...$this->normalize->source($input)], [...ProspectRules::fields(), ...ProspectRules::source(), ...ProspectRules::decisions(), 'submission_token' => ['required', 'uuid']], ProspectRules::messages(), ProspectRules::attributes())->validate();
        ksort($validated);
        if (isset($validated['confirmed_fields'])) {
            sort($validated['confirmed_fields']);
        }
        $digest = $this->hash->makeFor('prospect-create', json_encode($validated, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($admin, $data, $validated, $digest): Prospect {
            // Serialize this bounded manual workflow so duplicate checks and inserts are atomic.
            DB::select('SELECT pg_advisory_xact_lock(731204, 1)');
            $existing = Prospect::where('creation_token', $validated['submission_token'])->first();
            if ($existing) {
                if ($existing->created_by !== $admin->id || ! hash_equals($existing->creation_digest, $digest)) {
                    throw ValidationException::withMessages(['submission_token' => 'Este envio já foi utilizado com outros dados. Abra um novo formulário.']);
                }

                return $existing;
            }
            $review = $this->duplicates->guard($data, $validated, $admin);
            $prospect = Prospect::create([...$data, 'creation_token' => $validated['submission_token'], 'creation_digest' => $digest, 'normalized_name' => $this->normalize->key($data['business_name']), 'normalized_city' => $this->normalize->key($data['city']), 'email_hash' => $data['email'] ? $this->hash->makeFor('prospect-email', $data['email']) : null, 'phone_hash' => $data['phone'] ? $this->hash->makeFor('prospect-phone', $this->duplicates->phoneKey($data['phone'])) : null, 'created_by' => $admin->id, 'updated_by' => $admin->id, 'commercial_status' => 'identified', 'lock_version' => 1, 'confirmed_fields' => (object) []]);
            $source = $prospect->sources()->create([...array_intersect_key($validated, ProspectRules::source()), 'created_by' => $admin->id, 'submission_token' => $validated['submission_token'], 'submission_digest' => $digest]);
            $this->identifiers->record($prospect, $admin);
            $confirmed = [];
            foreach ($validated['confirmed_fields'] ?? [] as $field) {
                if ($data[$field] !== null) {
                    $confirmed[$field] = ['admin_id' => $admin->id, 'at' => now()->toIso8601String(), 'source_id' => $source->id];
                }
            }
            $prospect->update(['confirmed_fields' => $confirmed ?: (object) []]);
            $this->audit->record($prospect, $admin, 'created', ['confirmed_fields' => array_keys($confirmed), 'duplicate_reviewed' => ! empty($validated['duplicate_acknowledged']), 'duplicate_review' => $review], $validated['duplicate_reason'] ?? null, ['duplicate' => $validated['duplicate_reason'] ?? null, 'blocked_match' => $validated['blocked_match_reason'] ?? null]);

            return $prospect;
        }, 3);
    }
}
