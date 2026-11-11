<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\AdminAuditEvent;
use App\Models\EmailOutboxMessage;
use App\Models\PreRegistration;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminResendConfirmation
{
    public function __construct(private IssueConfirmationEmail $issue, private SensitiveHash $hash) {}

    public function handle(PreRegistration $registration, Admin $admin, int $expectedVersion, ?string $ip): void
    {
        DB::transaction(function () use ($registration, $admin, $expectedVersion, $ip): void {
            $locked = PreRegistration::query()->lockForUpdate()->findOrFail($registration->id);
            if ($locked->status_version !== $expectedVersion) {
                throw ValidationException::withMessages(['status' => 'O cadastro foi atualizado por outra operação. Recarregue a página.']);
            }
            if ($locked->status !== PreRegistration::STATUS_PENDING_EMAIL || $locked->email_confirmed_at !== null) {
                throw ValidationException::withMessages(['status' => 'O reenvio está disponível somente para cadastros com e-mail pendente.']);
            }
            $previous = AdminAuditEvent::query()->where('action', 'pre_registration.confirmation_resent')
                ->where('target_type', PreRegistration::class)->where('target_id', $locked->id);
            if ((clone $previous)->where('created_at', '>', now()->subMinute())->exists()) {
                throw ValidationException::withMessages(['status' => 'Aguarde pelo menos um minuto antes de solicitar outro reenvio.']);
            }
            if ((clone $previous)->where('created_at', '>', now()->subHour())->count() >= 3) {
                throw ValidationException::withMessages(['status' => 'O limite de três reenvios por hora foi atingido. Tente mais tarde.']);
            }

            $outbox = EmailOutboxMessage::query()->where('pre_registration_id', $locked->id)
                ->where('status', '!=', EmailOutboxMessage::STATUS_SENT)->orderBy('id')->lockForUpdate()->get();
            if ($outbox->contains(fn (EmailOutboxMessage $message): bool => $message->status === EmailOutboxMessage::STATUS_PROCESSING && $message->claimed_at?->isAfter(now()->subMinutes(10)))) {
                throw ValidationException::withMessages(['status' => 'Há um envio em processamento. Aguarde a conclusão antes de reenviar.']);
            }

            $this->issue->handle($locked);
            AdminAuditEvent::query()->create([
                'admin_id' => $admin->id, 'action' => 'pre_registration.confirmation_resent',
                'target_type' => PreRegistration::class, 'target_id' => $locked->id,
                'metadata' => ['status_version' => $locked->status_version],
                'ip_hash' => $this->hash->makeFor('ip-address', (string) $ip),
            ]);
        }, 3);
    }
}
