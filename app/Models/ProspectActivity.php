<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProspectActivity extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    public const DECISION_LABELS = ['change' => 'Alteração de campo confirmado', 'duplicate' => 'Aceitação de possível duplicidade', 'blocked_match' => 'Revisão de correspondência com contato bloqueado', 'contact' => 'Bloqueio ou desbloqueio de contato'];

    public const LABELS = ['created' => 'Cadastro criado', 'updated' => 'Dados atualizados', 'source_added' => 'Origem adicionada', 'contact_blocked' => 'Contato bloqueado', 'contact_unblocked' => 'Contato desbloqueado'];

    protected $fillable = ['prospect_id', 'admin_id', 'event', 'reason', 'metadata', 'decision_reasons'];

    protected $hidden = ['reason', 'decision_reasons'];

    protected function casts(): array
    {
        return ['reason' => 'encrypted', 'metadata' => 'array', 'decision_reasons' => 'encrypted:array'];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}
