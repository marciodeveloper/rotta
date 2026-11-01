<?php

namespace App\Contracts;

interface TurnstileVerifier
{
    public function verify(string $token, ?string $ipAddress): bool;
}
