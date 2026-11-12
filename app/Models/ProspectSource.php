<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProspectSource extends Model
{
    use HasUuids;

    protected $fillable = ['prospect_id', 'created_by', 'submission_token', 'submission_digest', 'origin_reference', 'usage_restrictions', 'collected_at', 'verified_at'];

    protected $hidden = ['submission_token', 'submission_digest', 'origin_reference', 'usage_restrictions'];

    protected function casts(): array
    {
        return ['origin_reference' => 'encrypted', 'usage_restrictions' => 'encrypted', 'collected_at' => 'date', 'queried_at' => 'datetime', 'verified_at' => 'date'];
    }

    public function prospect(): BelongsTo
    {
        return $this->belongsTo(Prospect::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }
}
