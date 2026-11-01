<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MediaStorageUsage extends Model
{
    public const SINGLETON_ID = 1;

    protected $fillable = ['used_bytes', 'free_bytes', 'level', 'checked_at'];

    protected function casts(): array
    {
        return [
            'used_bytes' => 'integer',
            'free_bytes' => 'integer',
            'checked_at' => 'datetime',
        ];
    }
}
