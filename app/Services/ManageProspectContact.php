<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Prospect;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ManageProspectContact
{
    public function __construct(private ProspectAudit $audit, private NormalizeProspect $normalize, private ProspectIdentifiers $identifiers) {}

    public function handle(Prospect $prospect, Admin $admin, array $input): void
    {
        Gate::forUser($admin)->authorize('manageContact', $prospect);
        $input = $this->normalize->decisions($input);
        $data = Validator::make($input, ['reason' => ['required', 'string', 'min:10', 'max:500'], 'blocked' => ['required', 'boolean'], 'lock_version' => ['required', 'integer', 'min:1']], ProspectRules::messages(), ProspectRules::attributes())->validate();
        DB::transaction(function () use ($prospect, $admin, $data): void {
            DB::select('SELECT pg_advisory_xact_lock(731204, 1)');
            $locked = Prospect::lockForUpdate()->findOrFail($prospect->id);
            if ($locked->lock_version !== (int) $data['lock_version']) {
                throw ValidationException::withMessages(['lock_version' => 'O prospect foi atualizado. Recarregue a ficha antes de alterar o bloqueio.']);
            }
            $blocked = (bool) $data['blocked'];
            if (($locked->contact_blocked_at !== null) === $blocked) {
                throw ValidationException::withMessages(['blocked' => 'O contato já está nesse estado. Recarregue a ficha.']);
            }
            $this->identifiers->record($locked, $admin);
            $locked->update(['contact_blocked_at' => $blocked ? now() : null, 'contact_blocked_by' => $blocked ? $admin->id : null, 'contact_block_reason' => $blocked ? $data['reason'] : null, 'updated_by' => $admin->id, 'lock_version' => $locked->lock_version + 1]);
            $this->audit->record($locked, $admin, $blocked ? 'contact_blocked' : 'contact_unblocked', [], $data['reason'], ['contact' => $data['reason']]);
        }, 3);
    }
}
