<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Admin extends Authenticatable
{
    use HasFactory, HasUuids, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'totp_secret', 'totp_confirmed_at', 'last_totp_time_step', 'is_active', 'can_export'];

    protected $hidden = ['password', 'totp_secret', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'totp_secret' => 'encrypted', 'totp_confirmed_at' => 'datetime', 'last_totp_time_step' => 'integer', 'is_active' => 'boolean', 'can_export' => 'boolean'];
    }

    public function recoveryCodes(): HasMany
    {
        return $this->hasMany(AdminRecoveryCode::class);
    }
}
