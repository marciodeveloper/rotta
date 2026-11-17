<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GooglePlaceReference extends Model
{
    use HasUuids;

    // Defensive application limit; Google does not define a fixed Place ID length.
    public const ID_PATTERN = '/^[A-Za-z0-9_-]{1,2048}$/D';

    protected $fillable = ['place_id', 'saved_by', 'queried_at', 'prospect_id', 'linked_by', 'linked_at'];

    protected function casts(): array
    {
        return ['queried_at' => 'immutable_datetime', 'linked_at' => 'immutable_datetime'];
    }

    public function prospect(): BelongsTo
    {
        return $this->belongsTo(Prospect::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'saved_by');
    }
}
