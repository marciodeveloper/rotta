<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreRegistrationMedia extends Model
{
    use HasUuids;

    protected $table = 'pre_registration_media';

    protected $fillable = ['pre_registration_id', 'type', 'position', 'alt_text', 'checksum', 'mime_type', 'extension', 'size_bytes', 'width', 'height', 'status', 'storage_disk', 'storage_key', 'thumbnail_storage_key', 'rights_confirmed_at'];

    protected $hidden = ['storage_key', 'thumbnail_storage_key'];

    protected function casts(): array
    {
        return ['position' => 'integer', 'size_bytes' => 'integer', 'width' => 'integer', 'height' => 'integer', 'rights_confirmed_at' => 'datetime'];
    }

    public function preRegistration(): BelongsTo
    {
        return $this->belongsTo(PreRegistration::class);
    }
}
