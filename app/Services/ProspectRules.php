<?php

namespace App\Services;

use App\Models\Prospect;
use Illuminate\Validation\Rule;

class ProspectRules
{
    public const STATES = ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];

    public static function fields(): array
    {
        return [
            'business_name' => ['required', 'string', 'max:180'], 'city' => ['required', 'string', 'max:100'], 'state' => ['required', Rule::in(self::STATES)],
            'category' => ['nullable', 'string', 'max:100'], 'postal_code' => ['nullable', 'regex:/^\d{8}$/D'],
            'street' => ['nullable', 'string', 'max:200'], 'street_number' => ['nullable', 'string', 'max:30'],
            'complement' => ['nullable', 'string', 'max:150'], 'neighborhood' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email:rfc', 'max:254'], 'phone' => ['nullable', 'regex:/^(?:55)?[1-9]\d{9,10}$/D'],
            'website' => ['nullable', 'url:http,https', 'max:500'],
            'instagram' => ['nullable', 'string', 'max:150', 'regex:~^(?:@[A-Za-z0-9._]{1,30}|https://(?:www\.)?instagram\.com/[A-Za-z0-9._]{1,30}/?)$~D'],
            'assigned_to' => ['nullable', 'uuid', Rule::exists('admins', 'id')->where('is_active', true)], 'priority' => ['required', Rule::in(array_keys(Prospect::PRIORITIES))],
            'notes' => ['nullable', 'string', 'max:3000'],
        ];
    }

    public static function source(): array
    {
        return ['origin_reference' => ['nullable', 'string', 'max:500'], 'usage_restrictions' => ['nullable', 'string', 'max:1000'], 'collected_at' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'], 'verified_at' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today']];
    }

    public static function decisions(): array
    {
        return ['confirmed_fields' => ['sometimes', 'array', 'max:16'], 'confirmed_fields.*' => ['string', 'distinct', Rule::in(array_keys(Prospect::FIELDS))], 'duplicate_acknowledged' => ['sometimes', 'boolean'], 'duplicate_reason' => ['nullable', 'string', 'min:10', 'max:500'], 'override_confirmed' => ['sometimes', 'boolean'], 'change_reason' => ['nullable', 'string', 'min:10', 'max:500'], 'blocked_match_acknowledged' => ['sometimes', 'boolean'], 'blocked_match_reason' => ['nullable', 'string', 'min:10', 'max:500'], 'blocked_match_versions' => ['sometimes', 'array', 'max:500'], 'blocked_match_versions.*' => ['integer', 'min:1']];
    }

    public static function messages(): array
    {
        return ['required' => 'Informe :attribute.', 'string' => ':attribute deve ser um texto.', 'max.string' => ':attribute deve ter no máximo :max caracteres.', 'max.array' => ':attribute tem itens demais.', 'email' => 'Informe um e-mail comercial válido.', 'url' => 'Informe uma URL completa com http:// ou https://.', 'regex' => 'Confira o formato de :attribute.', 'in' => 'Selecione uma opção válida para :attribute.', 'exists' => 'Selecione um responsável ativo.', 'uuid' => 'Identificador inválido para :attribute.', 'integer' => ':attribute deve ser um número inteiro.', 'min.string' => ':attribute deve ter pelo menos :min caracteres.', 'min.numeric' => ':attribute deve ser no mínimo :min.', 'boolean' => 'Seleção inválida para :attribute.', 'array' => ':attribute deve ser uma lista.', 'distinct' => 'Não repita itens em :attribute.', 'date_format' => 'Informe :attribute no formato dia/mês/ano.', 'before_or_equal' => ':attribute não pode ser uma data futura.'];
    }

    public static function attributes(): array
    {
        return ['blocked_match_acknowledged' => 'revisão de correspondências bloqueadas', 'blocked_match_reason' => 'justificativa da revisão de bloqueio', 'blocked_match_versions' => 'versões dos registros revisados', 'blocked_match_versions.*' => 'versão do registro revisado', 'blocked' => 'estado do bloqueio', ...Prospect::FIELDS, 'submission_token' => 'identificador do envio', 'lock_version' => 'versão do cadastro', 'origin_reference' => 'referência da origem', 'usage_restrictions' => 'restrições de uso', 'collected_at' => 'data de coleta', 'verified_at' => 'data de verificação', 'confirmed_fields' => 'campos confirmados', 'confirmed_fields.*' => 'campo confirmado', 'change_reason' => 'justificativa da alteração', 'duplicate_reason' => 'justificativa para cadastro semelhante', 'reason' => 'justificativa'];
    }
}
