<?php

namespace Tests\Feature\Jobs;

use App\Contracts\ConfirmationMailSender;
use App\Jobs\DeliverConfirmationEmail;
use App\Models\EmailConfirmationToken;
use App\Models\EmailOutboxMessage;
use App\Models\PreRegistration;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class DeliverConfirmationEmailTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_transient_delivery_failure_is_retried_and_sent_idempotently(): void
    {
        $registration = PreRegistration::factory()->create();
        $token = EmailConfirmationToken::factory()->for($registration)->create();
        $outbox = EmailOutboxMessage::query()->create([
            'pre_registration_id' => $registration->id,
            'email_confirmation_token_id' => $token->id,
            'confirmation_token' => str_repeat('x', 43),
            'status' => EmailOutboxMessage::STATUS_PENDING,
            'available_at' => now(),
        ]);
        $sender = new RecoveringConfirmationMailSender;
        $job = new DeliverConfirmationEmail($outbox->id);

        try {
            $job->handle($sender);
            $this->fail('The first synthetic delivery must fail.');
        } catch (RuntimeException) {
            $this->assertSame(EmailOutboxMessage::STATUS_PENDING, $outbox->fresh()->status);
            $this->assertSame(1, $outbox->fresh()->attempts);
        }

        $this->travel(31)->seconds();
        $job->handle($sender);
        $job->handle($sender);

        $this->assertSame(EmailOutboxMessage::STATUS_SENT, $outbox->fresh()->status);
        $this->assertNotNull($outbox->fresh()->sent_at);
        $this->assertSame(2, $outbox->fresh()->attempts);
        $this->assertSame(2, $sender->calls);
    }

    public function test_recovery_command_dispatches_a_pending_outbox_message(): void
    {
        Queue::fake([DeliverConfirmationEmail::class]);
        $registration = PreRegistration::factory()->create();
        $token = EmailConfirmationToken::factory()->for($registration)->create();
        $outbox = EmailOutboxMessage::query()->create([
            'pre_registration_id' => $registration->id,
            'email_confirmation_token_id' => $token->id,
            'confirmation_token' => str_repeat('y', 43),
            'status' => EmailOutboxMessage::STATUS_PENDING,
            'available_at' => now(),
        ]);

        $this->artisan('prelaunch:email-outbox:dispatch')->assertSuccessful();

        Queue::assertPushed(DeliverConfirmationEmail::class, fn (DeliverConfirmationEmail $job): bool => $job->outboxId === $outbox->id);
    }

    public function test_queued_message_with_superseded_token_is_not_sent(): void
    {
        $registration = PreRegistration::factory()->create();
        $token = EmailConfirmationToken::factory()->for($registration)->create(['is_active' => false]);
        $outbox = EmailOutboxMessage::query()->create([
            'pre_registration_id' => $registration->id, 'email_confirmation_token_id' => $token->id,
            'confirmation_token' => str_repeat('x', 43), 'status' => 'pending', 'available_at' => now(),
        ]);
        $sender = new RecoveringConfirmationMailSender;
        (new DeliverConfirmationEmail($outbox->id))->handle($sender);
        $this->assertSame(0, $sender->calls);
        $this->assertDatabaseHas('email_outbox_messages', ['id' => $outbox->id, 'status' => 'failed', 'last_error' => 'confirmation_token_inactive']);
    }
}

class RecoveringConfirmationMailSender implements ConfirmationMailSender
{
    public int $calls = 0;

    public function send(EmailOutboxMessage $message): void
    {
        $this->calls++;
        if ($this->calls === 1) {
            throw new RuntimeException('Synthetic transient SMTP failure.');
        }
    }
}
