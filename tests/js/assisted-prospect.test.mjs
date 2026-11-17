import test from 'node:test';
import assert from 'node:assert/strict';
import { parseIndependentDraft, fillIndependentFields } from '../../resources/js/assisted-prospect.js';

const options = { source: 'Contato direto com o estabelecimento, visita sintética', kind: 'direct_contact', confirmed: true };
function formFixture() {
    const inputs = { business_name: { value: 'Nome editado', maxLength: 180, dispatchEvent() {} }, city: { value: '', maxLength: 100, dispatchEvent() {} }, origin_reference: { value: '' } };
    const rows = ['business_name', 'city'].map((field) => {
        const controls = { '[data-provenance-kind]': { value: '' }, '[data-provenance-source]': { value: '' }, '[data-provenance-confirmed]': { checked: false } };
        return { dataset: { provenanceField: field }, controls, querySelector: (selector) => controls[selector] };
    });
    return { inputs, rows, elements: { namedItem: (name) => inputs[name] }, querySelectorAll: () => rows };
}

test('independent batch maps existing Portuguese labels and field keys, preserving colon in URLs', () => {
    assert.deepEqual(parseIndependentDraft('Nome do estabelecimento: Café independente\nCidade\tVassouras\nwebsite: https://example.invalid\nCNPJ: proibido', { business_name: 'Nome do estabelecimento', city: 'Cidade', website: 'Website' }), {
        values: { business_name: 'Café independente', city: 'Vassouras', website: 'https://example.invalid' }, unknown: ['CNPJ'],
    });
});
test('batch preserves edited fields and never accepts unlisted provider data', () => {
    const form = formFixture();
    assert.deepEqual(fillIndependentFields(form, { business_name: 'Outro nome', city: 'Vassouras', latitude: '123', raw_response: 'forbidden' }, options), { filled: ['city'], conflicts: ['business_name'] });
    assert.equal(form.inputs.business_name.value, 'Nome editado');
    assert.equal(form.inputs.city.value, 'Vassouras');
    assert.equal(form.rows[1].controls['[data-provenance-confirmed]'].checked, true);
    assert.equal(form.inputs.origin_reference.value, options.source);
});
test('overwriting requires explicit opt-in and field limits remain enforced', () => {
    const form = formFixture();
    assert.deepEqual(fillIndependentFields(form, { business_name: 'Nome autorizado', city: 'x'.repeat(101) }, { ...options, overwrite: true }), { filled: ['business_name'], conflicts: ['city'] });
    assert.equal(form.inputs.business_name.value, 'Nome autorizado');
    assert.equal(form.inputs.city.value, '');
});
test('Google provenance, missing rights confirmation and unsupported source types cannot fill fields', () => {
    for (const override of [{ source: 'https://maps.google.com/maps/123' }, { confirmed: false }, { kind: 'google' }, { source: '   ' }]) {
        const form = formFixture();
        assert.throws(() => fillIndependentFields(form, { city: 'Vassouras' }, { ...options, ...override }), /fonte independente/);
        assert.equal(form.inputs.city.value, '');
    }
});

test('authorized enrichment fills original fields and returns accurate pending and conflict counts', async () => {
    const { applyAuthorizedEnrichment } = await import('../../resources/js/assisted-prospect.js');
    const form = formFixture();
    const result = applyAuthorizedEnrichment(form, { business_name: 'Nome novo independente', city: 'Vassouras', latitude: '1', unknown: 'ignored' });
    assert.deepEqual(result.filled, ['city']);
    assert.deepEqual(result.conflicts, [{ field: 'business_name', current: 'Nome editado', value: 'Nome novo independente' }]);
    assert.equal(result.pending, 11);
    assert.equal(form.inputs.city.value, 'Vassouras');
    assert.equal(form.inputs.business_name.value, 'Nome editado');
});
test('enrichment never clears manual data when source is absent or returns invalid types', async () => {
    const { applyAuthorizedEnrichment } = await import('../../resources/js/assisted-prospect.js');
    const form = formFixture();
    assert.deepEqual(applyAuthorizedEnrichment(form, { city: { google: 'must not fill' }, business_name: null }), { filled: [], conflicts: [], pending: 12 });
    assert.equal(form.inputs.city.value, ''); assert.equal(form.inputs.business_name.value, 'Nome editado');
});
test('enrichment treats equal existing text as filled without overwriting and rejects oversized values', async () => {
    const { applyAuthorizedEnrichment } = await import('../../resources/js/assisted-prospect.js');
    const form = formFixture();
    const result = applyAuthorizedEnrichment(form, { city: 'x'.repeat(101), business_name: 'Nome editado' });
    assert.deepEqual(result.filled, []);assert.deepEqual(result.conflicts, []);assert.equal(form.inputs.city.value, '');
});

test('formatted phone and postal code do not produce false enrichment conflicts', async () => {
    const { equivalentCommercialValue } = await import('../../resources/js/assisted-prospect.js');
    assert.equal(equivalentCommercialValue('phone', '(24) 99999-0000', '24999990000'), true);
    assert.equal(equivalentCommercialValue('phone', '(24) 99999-0000', '+55 24 99999-0000'), true);
    assert.equal(equivalentCommercialValue('postal_code', '27700-000', '27700000'), true);
    assert.equal(equivalentCommercialValue('phone', '(24) 99999-0000', '24999991111'), false);
    assert.equal(equivalentCommercialValue('website', 'https://example.invalid/Foo', 'https://example.invalid/foo'), false);
});

test('zero available fields reports unavailability and never a successful update', async () => {
    const { enrichmentSummary } = await import('../../resources/js/assisted-prospect.js');
    const message=enrichmentSummary({filled:0,pending:13,conflicts:0,available:0,reason:'Fonte independente não configurada.'});
    assert.match(message,/Nenhum dado disponível para preenchimento automático/);
    assert.match(message,/Motivo: Fonte independente não configurada/);
    assert.doesNotMatch(message,/Cadastro atualizado|preenchimento realizado/);
});
test('existing values and conflicts do not falsely count as automatically filled fields', async () => {
    const { enrichmentSummary } = await import('../../resources/js/assisted-prospect.js');
    assert.match(enrichmentSummary({filled:0,pending:0,conflicts:0,available:13}),/Nenhum campo foi alterado/);
    assert.match(enrichmentSummary({filled:0,pending:4,conflicts:1,available:9}),/1 divergência aguarda sua revisão/);
    assert.equal(enrichmentSummary({filled:8,pending:4,conflicts:1,available:9}),'8 campos preenchidos, 4 pendentes e 1 divergência.');
});

test('international Brazilian enrichment is adapted before the existing national phone mask without dropping digits', async () => {
    const { enrichmentInputValue } = await import('../../resources/js/assisted-prospect.js');
    assert.equal(enrichmentInputValue('phone', '+55 24 99991-3651'), '24999913651');
    assert.equal(enrichmentInputValue('phone', '(24) 99991-3651'), '(24) 99991-3651');
    assert.equal(enrichmentInputValue('business_name', '  Empresa 55  '), 'Empresa 55');
});

test('public cadastral provenance can fill permitted fields while preserving existing manual values', () => {
    const form = formFixture();
    const result = fillIndependentFields(form, { business_name: 'Nome cadastral', city: 'Vassouras' }, { source: 'https://minhareceita.org/11222333000181 | Dados abertos RFB', kind: 'public_data', confirmed: true });
    assert.deepEqual(result, { filled: ['city'], conflicts: ['business_name'] });
    assert.equal(form.inputs.business_name.value, 'Nome editado');
    assert.equal(form.rows[1].controls['[data-provenance-kind]'].value, 'public_data');
});
