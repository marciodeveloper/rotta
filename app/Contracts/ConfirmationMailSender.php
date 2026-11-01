<?php

namespace App\Contracts;

use App\Models\EmailOutboxMessage;

interface ConfirmationMailSender
{
    public function send(EmailOutboxMessage $message): void;
}
