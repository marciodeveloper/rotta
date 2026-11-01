<?php

namespace App\Services;

use App\Contracts\ConfirmationMailSender;
use App\Mail\ConfirmPreRegistrationMail;
use App\Models\EmailOutboxMessage;
use Illuminate\Support\Facades\Mail;

class LaravelConfirmationMailSender implements ConfirmationMailSender
{
    public function send(EmailOutboxMessage $message): void
    {
        $registration = $message->preRegistration;

        Mail::to($registration->email)->send(
            new ConfirmPreRegistrationMail($registration, $message->confirmation_token, $message->id),
        );
    }
}
