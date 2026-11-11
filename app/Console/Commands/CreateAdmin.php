<?php

namespace App\Console\Commands;

use App\Models\Admin;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

#[Signature('prelaunch:admin:create {email : Administrative email} {--name= : Display name} {--can-export : Grant the sensitive CSV export permission}')]
#[Description('Provision a password-authenticated administrator')]
class CreateAdmin extends Command
{
    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));
        $name = trim((string) ($this->option('name') ?: $this->ask('Name')));
        $password = (string) $this->secret('Password (minimum 14 characters)');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || $name === '' || strlen($password) < 14) {
            $this->error('Invalid name, email, or password length.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($name, $email, $password): void {
            Admin::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'totp_secret' => '',
                'totp_confirmed_at' => null,
                'is_active' => true,
                'can_export' => (bool) $this->option('can-export'),
            ]);

        }, 3);

        $this->info('Administrador criado. Acesse o painel com e-mail e senha.');

        return self::SUCCESS;
    }
}
