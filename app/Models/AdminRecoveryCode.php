<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminRecoveryCode extends Model
{
    use HasUuids;

    protected $fillable = ['admin_id', 'code_hash', 'used_at'];

    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return ['used_at' => 'datetime'];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}
