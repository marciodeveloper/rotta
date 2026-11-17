<?php

namespace App\Services;

use App\Exceptions\PlacesUnavailable;
use App\Models\Admin;
use App\Models\AdminAuditEvent;
use App\Models\GooglePlaceReference;
use App\Models\Prospect;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class EnrichProspectReference
{
    public function __construct(private BusinessExplorer $explorer, private AuthorizedBusinessWebsite $website, private PlacesContext $context) {}

    public function handle(Admin $admin, GooglePlaceReference $reference, bool $enterprise, array $options = []): array
    {
        Gate::forUser($admin)->authorize('useForProspect', $reference);
        if ($reference->prospect_id) {
            $prospect = $reference->prospect;
            Gate::forUser($admin)->authorize('view', $prospect);
            AdminAuditEvent::create(['admin_id' => $admin->id, 'target_type' => GooglePlaceReference::class, 'target_id' => $reference->id, 'action' => 'business_explorer.existing_crm_record', 'metadata' => ['prospect_id' => $prospect->id, 'outcome' => 'existing']]);

            return ['google' => null, 'fields' => [], 'enrichment_token' => '', 'source' => '', 'warnings' => [],
                'outcome' => 'existing', 'reason_code' => 'already_in_crm', 'existing_prospect_url' => route('admin.prospects.show', $prospect),
                'message' => 'O estabelecimento já possui ficha no CRM. Utilize o registro existente; nenhum novo prospect foi criado.'];
        }
        $normalizer = new NormalizeProspect;
        $identity = $normalizer->handle($options);
        if (! empty($identity['business_name']) && ! empty($identity['city']) && ! empty($identity['state'])) {
            $existing = Prospect::where('normalized_name', $normalizer->key($identity['business_name']))->where('normalized_city', $normalizer->key($identity['city']))->where('state', $identity['state'])->limit(2)->get();
            if ($existing->count() === 1 && Gate::forUser($admin)->allows('view', $existing->first())) {
                $prospect = $existing->first();

                return ['google' => null, 'fields' => [], 'enrichment_token' => '', 'source' => '', 'warnings' => [], 'outcome' => 'existing', 'reason_code' => 'possible_existing_crm',
                    'existing_prospect_url' => route('admin.prospects.show', $prospect), 'message' => 'Encontramos uma ficha com o mesmo nome, cidade e UF. Confira o registro existente antes de criar outro; não houve cópia nem fusão automática.'];
            }
        }
        if (! empty($options['company_token'])) {
            $candidate = $this->context->read($admin, 'public_company_candidate', $options['company_token']);
            if (($candidate['reference_id'] ?? null) !== $reference->id) {
                throw ValidationException::withMessages(['company_token' => 'Esta consulta pertence a outro estabelecimento.']);
            }
            AdminAuditEvent::create(['admin_id' => $admin->id, 'target_type' => GooglePlaceReference::class, 'target_id' => $reference->id, 'action' => 'business_explorer.company_identity_reviewed', 'metadata' => ['filled_fields' => array_keys($candidate['fields']), 'evidence' => $candidate['evidence']]]);

            return ['google' => null, 'fields' => $candidate['fields'], 'outcome' => 'available', 'reason_code' => null,
                'enrichment_token' => $this->context->issue($admin, 'independent_enrichment', $candidate), 'source' => $candidate['source'], 'warnings' => [], 'message' => 'Dados cadastrais disponíveis para preenchimento. Revise antes de salvar.'];
        }
        if (! empty($options['cnpj'])) {
            $document = (new DocumentValidator)->normalize($options['cnpj']);
            if (! ctype_digit($document) || ! (new DocumentValidator)->isValidCnpj($document)) {
                throw ValidationException::withMessages(['cnpj' => 'Informe um CNPJ numérico válido para consultar esta fonte.']);
            }
            app(PlacesQuota::class)->reserve($admin, 'public-company');
            $data = app(PublicCompanyData::class)->lookup($document);
            $candidate = ['reference_id' => $reference->id, ...$data];
            AdminAuditEvent::create(['admin_id' => $admin->id, 'target_type' => GooglePlaceReference::class, 'target_id' => $reference->id, 'action' => 'business_explorer.company_candidate', 'metadata' => ['evidence' => $data['evidence'], 'outcome' => 'review']]);

            return ['google' => null, 'fields' => [], 'review_fields' => $data['fields'], 'company_token' => $this->context->issue($admin, 'public_company_candidate', $candidate),
                'outcome' => 'review', 'reason_code' => 'identity_review_required', 'source' => $data['source'], 'enrichment_token' => '', 'warnings' => [], 'message' => $data['message']];
        }
        if ($enterprise && ! config('business_explorer.enterprise_enabled')) {
            throw new PlacesUnavailable('A consulta ampliada Google está desabilitada na configuração administrativa.', 422);
        }
        $warnings = [];
        $google = null;
        try {
            $independent = $this->website->lookup($reference->place_id);
        } catch (PlacesUnavailable $exception) {
            $warnings[] = $exception->getMessage();
            $independent = ['fields' => [], 'reason_code' => 'source_lookup_failed', 'message' => $exception->getMessage()];
        }
        if ($enterprise || $independent['fields']) {
            try {
                $google = $this->explorer->enrichmentDetails($admin, $reference, $enterprise);
            } catch (PlacesUnavailable $exception) {
                $warnings[] = $exception->getMessage();
            }
        }
        $data = ['reference_id' => $reference->id, 'fields' => $independent['fields'], 'source' => $independent['source'] ?? '', 'evidence' => $independent['evidence'] ?? null];
        AdminAuditEvent::create(['admin_id' => $admin->id, 'target_type' => GooglePlaceReference::class, 'target_id' => $reference->id, 'action' => 'business_explorer.independent_enrichment', 'metadata' => ['filled_fields' => array_keys($data['fields']), 'evidence' => $data['evidence'], 'outcome' => $data['fields'] ? 'available' : 'unavailable', 'reason_code' => $independent['reason_code'] ?? null]]);

        return ['google' => $google, 'fields' => $data['fields'], 'outcome' => $data['fields'] ? 'available' : 'unavailable', 'reason_code' => $independent['reason_code'] ?? null,
            'enrichment_token' => $data['fields'] ? $this->context->issue($admin, 'independent_enrichment', $data) : '',
            'source' => $data['source'], 'message' => $independent['message'], 'warnings' => $warnings];
    }
}
