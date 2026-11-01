<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Admin extends Authenticatable
{
    use HasFactory, HasUuids, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'totp_secret', 'totp_confirmed_at', 'is_active'];

    protected $hidden = ['password', 'totp_secret', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'totp_secret' => 'encrypted', 'totp_confirmed_at' => 'datetime', 'is_active' => 'boolean'];
    }
}
