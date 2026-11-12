<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ProspectIdentifier extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = ['kind', 'fingerprint', 'recorded_by'];

    protected $hidden = ['fingerprint'];
}
