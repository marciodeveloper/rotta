<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
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

    #[Scope]
    protected function currentPublished(Builder $query): Builder
    {
        return $query
            ->where('is_current', true)
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->whereNotNull('legal_basis')
            ->where('legal_basis', '!=', '')
            ->whereNotNull('retention_days')
            ->where('retention_days', '>', 0);
    }

    public function preRegistrations(): HasMany
    {
        return $this->hasMany(PreRegistration::class);
    }
}
