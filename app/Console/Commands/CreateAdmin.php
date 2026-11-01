<?php

namespace App\Console\Commands;

use App\Models\Admin;
use App\Services\TotpService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

#[Signature('prelaunch:admin:create {email : Administrative email} {--name= : Display name} {--can-export : Grant the sensitive CSV export permission}')]
#[Description('Provision an administrator and print the one-time TOTP enrollment URI')]
class CreateAdmin extends Command
{
    public function handle(TotpService $totp): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));
        $name = trim((string) ($this->option('name') ?: $this->ask('Name')));
        $password = (string) $this->secret('Password (minimum 14 characters)');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || $name === '' || strlen($password) < 14) {
            $this->error('Invalid name, email, or password length.');

            return self::FAILURE;
        }

        $secret = $totp->generateSecret();
        $admin = Admin::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'totp_secret' => $secret,
            'totp_confirmed_at' => now(),
            'is_active' => true,
            'can_export' => (bool) $this->option('can-export'),
        ]);

        $label = rawurlencode('ROTTA Admin:'.$admin->email);
        $issuer = rawurlencode('ROTTA');
        $this->warn('Store this URI in the administrator authenticator now. It will not be shown again:');
        $this->line("otpauth://totp/{$label}?secret={$secret}&issuer={$issuer}&algorithm=SHA1&digits=6&period=30");

        return self::SUCCESS;
    }
}
