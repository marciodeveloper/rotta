<?php

namespace App\Console\Commands;

use App\Jobs\DeliverConfirmationEmail;
use App\Models\EmailOutboxMessage;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('prelaunch:email-outbox:dispatch')]
#[Description('Dispatch due confirmation email outbox messages without sending inline')]
class DispatchPendingEmailOutbox extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $count = 0;
        EmailOutboxMessage::query()
            ->where(function ($query): void {
                $query->where(function ($pending): void {
                    $pending->where('status', EmailOutboxMessage::STATUS_PENDING)->where('available_at', '<=', now());
                })->orWhere(function ($processing): void {
                    $processing->where('status', EmailOutboxMessage::STATUS_PROCESSING)->where('claimed_at', '<=', now()->subMinutes(10));
                });
            })
            ->orderBy('available_at')
            ->limit(100)
            ->pluck('id')
            ->each(function (string $id) use (&$count): void {
                DeliverConfirmationEmail::dispatch($id);
                $count++;
            });

        $this->info("Dispatched {$count} confirmation email message(s).");

        return self::SUCCESS;
    }
}
