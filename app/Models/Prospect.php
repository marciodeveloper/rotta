<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Prospect extends Model
{
    use HasFactory, HasUuids;

    public const FIELDS = ['business_name' => 'Nome do estabelecimento', 'category' => 'Segmento', 'city' => 'Cidade', 'state' => 'UF', 'postal_code' => 'CEP', 'street' => 'Logradouro', 'street_number' => 'Número', 'complement' => 'Complemento', 'neighborhood' => 'Bairro', 'email' => 'E-mail comercial', 'phone' => 'Telefone comercial', 'website' => 'Website', 'instagram' => 'Instagram', 'notes' => 'Observações', 'priority' => 'Prioridade', 'assigned_to' => 'Responsável'];

    public const PRIORITIES = ['low' => 'Baixa', 'medium' => 'Média', 'high' => 'Alta'];

    public const PRIVATE_FIELDS = ['postal_code', 'street', 'street_number', 'complement', 'neighborhood', 'email', 'phone', 'website', 'instagram', 'notes', 'contact_block_reason'];

    protected $fillable = ['creation_token', 'creation_digest', 'normalized_name', 'normalized_city', 'email_hash', 'phone_hash', 'commercial_status', 'lock_version', 'confirmed_fields', 'created_by', 'updated_by', 'contact_blocked_at', 'contact_blocked_by', 'contact_block_reason', 'business_name', 'category', 'city', 'state', 'postal_code', 'street', 'street_number', 'complement', 'neighborhood', 'email', 'phone', 'website', 'instagram', 'notes', 'priority', 'assigned_to'];

    protected $hidden = ['creation_token', 'creation_digest', 'email_hash', 'phone_hash', 'normalized_name', 'normalized_city', 'postal_code', 'street', 'street_number', 'complement', 'neighborhood', 'email', 'phone', 'website', 'instagram', 'notes', 'contact_block_reason'];

    protected function casts(): array
    {
        return [...array_fill_keys(self::PRIVATE_FIELDS, 'encrypted'), 'confirmed_fields' => 'array', 'lock_version' => 'integer', 'contact_blocked_at' => 'datetime'];
    }

    public function sources(): HasMany
    {
        return $this->hasMany(ProspectSource::class);
    }

    public function identifiers(): HasMany
    {
        return $this->hasMany(ProspectIdentifier::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ProspectActivity::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'assigned_to');
    }
}
