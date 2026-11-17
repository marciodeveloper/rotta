<?php

namespace App\Services;

use App\Exceptions\PlacesUnavailable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class PublicCompanyData
{
    public function lookup(string $document): array
    {
        $cnpj = (new DocumentValidator)->normalize($document);
        if (! ctype_digit($cnpj) || ! (new DocumentValidator)->isValidCnpj($cnpj)) {
            throw ValidationException::withMessages(['cnpj' => 'Informe um CNPJ numérico válido para consultar esta fonte.']);
        }
        $url = 'https://minhareceita.org/'.$cnpj;
        try {
            $response = Http::acceptJson()->connectTimeout(2)->timeout(8)
                ->withOptions(['allow_redirects' => false, 'stream' => true, 'read_timeout' => 3])->get($url);
            if (! $response->successful()) {
                $response->toPsrResponse()->getBody()->close();
                throw new PlacesUnavailable(match ($response->status()) {
                    404 => 'CNPJ não encontrado nesta base. Você pode continuar o cadastro manual.',
                    429 => 'A base de empresas está ocupada. Tente novamente mais tarde ou continue manualmente.',
                    default => 'A base de empresas está indisponível. Seus dados foram preservados.',
                }, 422);
            }
            $stream = $response->toPsrResponse()->getBody();
            try {
                $body = '';
                while (! $stream->eof() && strlen($body) <= 131072) {
                    $body .= $stream->read(min(8192, 131073 - strlen($body)));
                }
            } finally {
                $stream->close();
            }
            $data = strlen($body) <= 131072 ? json_decode($body, true, 32) : null;
            if (! is_array($data) || ! is_string($data['cnpj'] ?? null) || (new DocumentValidator)->normalize($data['cnpj']) !== $cnpj) {
                throw new PlacesUnavailable('A base retornou dados inválidos ou de outra empresa. Nenhum campo foi aplicado.', 422);
            }
            // Whitelist business data: never forward or retain partners, CPF or raw responses.
            $text = static fn (string $field): string => is_string($data[$field] ?? null) ? trim($data[$field]) : '';
            $values = ['business_name' => $text('nome_fantasia') ?: $text('razao_social'),
                'category' => $text('cnae_fiscal_descricao'), 'city' => $text('municipio'), 'state' => $text('uf'),
                'postal_code' => $text('cep'), 'street' => trim($text('descricao_tipo_de_logradouro').' '.$text('logradouro')),
                'street_number' => $text('numero'), 'complement' => $text('complemento'), 'neighborhood' => $text('bairro')];
            $values = (new NormalizeProspect)->handle($values);
            $fields = [];
            foreach (ReferenceFieldProvenance::fields() as $field => $label) {
                if (is_string($values[$field] ?? null) && $values[$field] !== '' && Validator::make([$field => $values[$field]], [$field => ProspectRules::fields()[$field]])->passes()) {
                    $fields[$field] = $values[$field];
                }
            }
            if (! isset($fields['business_name'], $fields['city'], $fields['state'])) {
                throw new PlacesUnavailable('A base não retornou identificação suficiente. Continue o cadastro manual.', 422);
            }

            return ['fields' => $fields, 'source' => $url.' | Dados abertos RFB via Minha Receita',
                'source_kind' => 'public_data', 'evidence' => hash('sha256', json_encode($fields, JSON_THROW_ON_ERROR)),
                'queried_at' => now()->toIso8601String(), 'reason_code' => null,
                'message' => 'Confira se este CNPJ corresponde ao estabelecimento e à unidade desejada. Dados cadastrais podem estar desatualizados.'];
        } catch (PlacesUnavailable $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new PlacesUnavailable('Não foi possível consultar a base de empresas. Você pode continuar manualmente.');
        }
    }
}
