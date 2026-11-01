<?php

namespace App\Jobs;

use App\Contracts\ConfirmationMailSender;
use App\Models\EmailOutboxMessage;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class DeliverConfirmationEmail implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 30;

    public int $uniqueFor = 900;

    public function __construct(public string $outboxId) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [30, 120, 600, 1800];
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(6);
    }

    public function uniqueId(): string
    {
        return $this->outboxId;
    }

    /**
     * Execute the job.
     */
    public function handle(ConfirmationMailSender $sender): void
    {
        $message = $this->claim();
        if ($message === null) {
            return;
        }

        try {
            $sender->send($message);
            EmailOutboxMessage::query()->whereKey($message->id)->update([
                'status' => EmailOutboxMessage::STATUS_SENT,
                'sent_at' => now(),
                'claimed_at' => null,
                'last_error' => null,
            ]);
        } catch (Throwable $exception) {
            EmailOutboxMessage::query()->whereKey($message->id)->update([
                'status' => EmailOutboxMessage::STATUS_PENDING,
                'available_at' => now()->addSeconds($this->backoff()[min($message->attempts - 1, count($this->backoff()) - 1)]),
                'claimed_at' => null,
                'last_error' => mb_substr($exception::class, 0, 160),
            ]);

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        EmailOutboxMessage::query()
            ->whereKey($this->outboxId)
            ->where('status', '!=', EmailOutboxMessage::STATUS_SENT)
            ->update([
                'status' => EmailOutboxMessage::STATUS_FAILED,
                'claimed_at' => null,
                'last_error' => $exception === null ? 'terminal_failure' : mb_substr($exception::class, 0, 160),
            ]);
    }

    private function claim(): ?EmailOutboxMessage
    {
        return DB::transaction(function (): ?EmailOutboxMessage {
            $message = EmailOutboxMessage::query()->lockForUpdate()->find($this->outboxId);
            if ($message === null || $message->status === EmailOutboxMessage::STATUS_SENT) {
                return null;
            }
            if ($message->available_at->isFuture()) {
                return null;
            }
            if ($message->status === EmailOutboxMessage::STATUS_PROCESSING && $message->claimed_at?->isAfter(now()->subMinutes(10))) {
                return null;
            }

            $message->update([
                'status' => EmailOutboxMessage::STATUS_PROCESSING,
                'attempts' => $message->attempts + 1,
                'claimed_at' => now(),
            ]);

            return $message->fresh(['preRegistration']);
        }, 3);
    }
}
