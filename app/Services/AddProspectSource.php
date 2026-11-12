<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Prospect;
use App\Models\ProspectSource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AddProspectSource
{
    public function __construct(private SensitiveHash $hash, private ProspectAudit $audit, private NormalizeProspect $normalize) {}

    public function handle(Prospect $prospect, Admin $admin, array $input): void
    {
        Gate::forUser($admin)->authorize('addSource', $prospect);
        $data = Validator::make([...$input, ...$this->normalize->source($input)], [...ProspectRules::source(), 'origin_reference' => ['required', 'string', 'max:500'], 'submission_token' => ['required', 'uuid'], 'lock_version' => ['required', 'integer', 'min:1']], ProspectRules::messages(), ProspectRules::attributes())->validate();
        ksort($data);
        $digest = $this->hash->makeFor('prospect-source', json_encode($data, JSON_THROW_ON_ERROR));
        DB::transaction(function () use ($prospect, $admin, $data, $digest): void {
            DB::select('SELECT pg_advisory_xact_lock(731204, 1)');
            $existing = ProspectSource::where('submission_token', $data['submission_token'])->first();
            if ($existing) {
                if ($existing->prospect_id !== $prospect->id || $existing->created_by !== $admin->id || ! hash_equals($existing->submission_digest, $digest)) {
                    throw ValidationException::withMessages(['submission_token' => 'Este identificador de envio já foi utilizado. Recarregue a ficha.']);
                }

                return;
            }
            $locked = Prospect::lockForUpdate()->findOrFail($prospect->id);
            if ($locked->lock_version !== (int) $data['lock_version']) {
                throw ValidationException::withMessages(['lock_version' => 'O prospect foi atualizado. Recarregue a ficha antes de adicionar uma origem.']);
            }
            $source = $locked->sources()->create([...array_intersect_key($data, ProspectRules::source()), 'submission_token' => $data['submission_token'], 'submission_digest' => $digest, 'created_by' => $admin->id]);
            $locked->update(['updated_by' => $admin->id, 'lock_version' => $locked->lock_version + 1]);
            $this->audit->record($locked, $admin, 'source_added', ['source_id' => $source->id]);
        }, 3);
    }
}
