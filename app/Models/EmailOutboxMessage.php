<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailOutboxMessage extends Model
{
    use HasFactory, HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'pre_registration_id', 'email_confirmation_token_id', 'confirmation_token', 'status', 'attempts',
        'available_at', 'claimed_at', 'sent_at', 'last_error',
    ];

    protected $hidden = ['confirmation_token'];

    protected function casts(): array
    {
        return [
            'confirmation_token' => 'encrypted',
            'attempts' => 'integer',
            'available_at' => 'datetime',
            'claimed_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function preRegistration(): BelongsTo
    {
        return $this->belongsTo(PreRegistration::class);
    }

    public function confirmationToken(): BelongsTo
    {
        return $this->belongsTo(EmailConfirmationToken::class, 'email_confirmation_token_id');
    }
}
