<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PreRegistration extends Model
{
    use HasFactory, HasUuids;

    public const STATUS_PENDING_EMAIL = 'pending_email_confirmation';

    public const STATUS_UNDER_REVIEW = 'under_review';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    /** @var list<string> */
    public const STATUSES = [self::STATUS_PENDING_EMAIL, self::STATUS_UNDER_REVIEW, self::STATUS_APPROVED, self::STATUS_REJECTED];

    protected $fillable = [
        'applicant_type', 'document', 'document_hash', 'document_last_four', 'business_name', 'category',
        'short_description', 'responsible_name', 'email', 'email_hash', 'phone', 'phone_hash', 'website',
        'instagram', 'address_mode', 'postal_code', 'street', 'street_number', 'complement', 'neighborhood',
        'city', 'state', 'service_area', 'google_place_id', 'latitude', 'longitude', 'marketing_opt_in',
        'privacy_notice_id', 'privacy_notice_version', 'status', 'source', 'email_confirmed_at',
    ];

    protected $hidden = ['document', 'document_hash', 'responsible_name', 'email', 'email_hash', 'phone', 'phone_hash', 'postal_code', 'street', 'street_number', 'complement', 'neighborhood', 'service_area', 'google_place_id'];

    protected function casts(): array
    {
        return [
            'document' => 'encrypted', 'responsible_name' => 'encrypted', 'email' => 'encrypted', 'phone' => 'encrypted',
            'website' => 'encrypted', 'instagram' => 'encrypted', 'postal_code' => 'encrypted', 'street' => 'encrypted',
            'street_number' => 'encrypted', 'complement' => 'encrypted', 'neighborhood' => 'encrypted',
            'service_area' => 'encrypted', 'google_place_id' => 'encrypted', 'marketing_opt_in' => 'boolean',
            'latitude' => 'decimal:7', 'longitude' => 'decimal:7', 'email_confirmed_at' => 'datetime',
        ];
    }

    public function privacyNotice(): BelongsTo
    {
        return $this->belongsTo(PrivacyNotice::class);
    }

    public function confirmationTokens(): HasMany
    {
        return $this->hasMany(EmailConfirmationToken::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(PreRegistrationStatusHistory::class);
    }
}
