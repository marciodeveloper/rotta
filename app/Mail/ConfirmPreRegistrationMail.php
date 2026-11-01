<?php

namespace App\Mail;

use App\Models\PreRegistration;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class ConfirmPreRegistrationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public PreRegistration $registration,
        public string $confirmationToken,
        public string $outboxId,
    ) {}

    public function headers(): Headers
    {
        return new Headers(messageId: $this->outboxId.'@rotta.serradev.com');
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Confirme seu pré-cadastro na ROTTA',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.pre-registration-confirmation',
            with: [
                'businessName' => $this->registration->business_name,
                'confirmationUrl' => route('pre-registration.confirm', ['token' => $this->confirmationToken]),
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
