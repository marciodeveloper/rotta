<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\AdminAuditEvent;
use App\Models\PreRegistration;
use App\Models\PreRegistrationStatusHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransitionPreRegistrationStatus
{
    public function __construct(private SensitiveHash $hash) {}

    public function handle(
        PreRegistration $registration,
        Admin $admin,
        string $targetStatus,
        ?string $reason,
        int $expectedVersion,
        ?string $ipAddress,
    ): PreRegistration {
        return DB::transaction(function () use ($registration, $admin, $targetStatus, $reason, $expectedVersion, $ipAddress): PreRegistration {
            $locked = PreRegistration::query()->lockForUpdate()->findOrFail($registration->id);
            if ($locked->status_version !== $expectedVersion) {
                throw ValidationException::withMessages(['status' => 'O cadastro foi atualizado por outra operação. Recarregue a página.']);
            }
            if (! in_array($targetStatus, PreRegistration::ALLOWED_TRANSITIONS[$locked->status] ?? [], true)) {
                throw ValidationException::withMessages(['status' => 'Esta transição de status não é permitida.']);
            }
            if ($targetStatus === PreRegistration::STATUS_APPROVED && $locked->email_confirmed_at === null) {
                throw ValidationException::withMessages(['status' => 'O e-mail deve estar confirmado antes da aprovação.']);
            }

            $fromStatus = $locked->status;
            $locked->update(['status' => $targetStatus, 'status_version' => $locked->status_version + 1]);
            PreRegistrationStatusHistory::query()->create([
                'pre_registration_id' => $locked->id,
                'from_status' => $fromStatus,
                'to_status' => $targetStatus,
                'admin_id' => $admin->id,
                'reason' => $reason,
            ]);
            AdminAuditEvent::query()->create([
                'admin_id' => $admin->id,
                'action' => 'pre_registration.status_updated',
                'target_type' => PreRegistration::class,
                'target_id' => $locked->id,
                'metadata' => ['from' => $fromStatus, 'to' => $targetStatus, 'status_version' => $locked->status_version],
                'ip_hash' => $this->hash->make((string) $ipAddress),
            ]);

            return $locked;
        }, 3);
    }
}
