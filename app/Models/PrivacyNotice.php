<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrivacyNotice extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['version', 'title', 'content', 'controller_name', 'contact_email', 'purpose', 'legal_basis', 'retention_days', 'is_current', 'status', 'published_at'];

    protected function casts(): array
    {
        return ['is_current' => 'boolean', 'published_at' => 'datetime'];
    }

    public function preRegistrations(): HasMany
    {
        return $this->hasMany(PreRegistration::class);
    }
}
