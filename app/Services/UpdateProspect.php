<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Prospect;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UpdateProspect
{
    public function __construct(private NormalizeProspect $normalize, private DetectProspectDuplicates $duplicates, private SensitiveHash $hash, private ProspectAudit $audit, private ProspectIdentifiers $identifiers) {}

    public function handle(Prospect $prospect, array $input, Admin $admin): Prospect
    {
        Gate::forUser($admin)->authorize('update', $prospect);
        $input = $this->normalize->decisions($input);
        $data = $this->normalize->handle($input);
        $validated = Validator::make([...$input, ...$data], [...ProspectRules::fields(), ...ProspectRules::decisions(), 'lock_version' => ['required', 'integer', 'min:1']], ProspectRules::messages(), ProspectRules::attributes())->validate();

        return DB::transaction(function () use ($prospect, $admin, $data, $validated): Prospect {
            DB::select('SELECT pg_advisory_xact_lock(731204, 1)');
            $locked = Prospect::lockForUpdate()->findOrFail($prospect->id);
            if ($locked->lock_version !== (int) $validated['lock_version']) {
                throw ValidationException::withMessages(['lock_version' => 'Este prospect foi atualizado por outra operação. Recarregue a ficha antes de salvar.']);
            }
            $changed = array_keys(array_filter($data, fn ($value, $field) => $value !== $locked->$field, ARRAY_FILTER_USE_BOTH));
            $previous = $locked->confirmed_fields ?? [];
            $requested = $validated['confirmed_fields'] ?? [];
            $protectedChanges = array_intersect(array_keys($previous), array_unique([...$changed, ...array_diff(array_keys($previous), $requested)]));
            if ($protectedChanges && (empty($validated['override_confirmed']) || empty($validated['change_reason']))) {
                throw ValidationException::withMessages(['override_confirmed' => 'A alteração ou retirada de confirmação de campos confirmados exige autorização explícita e justificativa.']);
            }
            $review = $this->duplicates->guard($data, $validated, $admin, $locked->id);
            $this->identifiers->record($locked, $admin);
            $confirmed = [];
            foreach ($requested as $field) {
                if ($data[$field] !== null) {
                    $confirmed[$field] = isset($previous[$field]) && ! in_array($field, $changed, true) ? $previous[$field] : ['admin_id' => $admin->id, 'at' => now()->toIso8601String(), 'source_id' => null];
                }
            }
            $locked->fill([...$data, 'normalized_name' => $this->normalize->key($data['business_name']), 'normalized_city' => $this->normalize->key($data['city']), 'email_hash' => $data['email'] ? $this->hash->makeFor('prospect-email', $data['email']) : null, 'phone_hash' => $data['phone'] ? $this->hash->makeFor('prospect-phone', $this->duplicates->phoneKey($data['phone'])) : null, 'confirmed_fields' => $confirmed ?: (object) [], 'updated_by' => $admin->id, 'lock_version' => $locked->lock_version + 1])->save();
            $this->identifiers->record($locked, $admin);
            $this->audit->record($locked, $admin, 'updated', ['fields' => $changed, 'confirmed_fields' => array_keys($confirmed), 'protected_fields' => array_values($protectedChanges), 'duplicate_reviewed' => ! empty($validated['duplicate_acknowledged']), 'duplicate_review' => $review], $validated['change_reason'] ?? $validated['duplicate_reason'] ?? null, ['change' => $validated['change_reason'] ?? null, 'duplicate' => $validated['duplicate_reason'] ?? null, 'blocked_match' => $validated['blocked_match_reason'] ?? null]);

            return $locked;
        }, 3);
    }
}
