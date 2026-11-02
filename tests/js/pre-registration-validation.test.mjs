import assert from 'node:assert/strict';
import test from 'node:test';

import {
    cnpjIsValid,
    cpfIsValid,
    stepForServerErrors,
    validateStepValues,
} from '../../resources/js/pre-registration-validation.js';

const validValues = () => ({
    applicant_type: 'company',
    document: '11.222.333/0001-81',
    business_name: 'Café do Vale',
    category: 'Gastronomia',
    short_description: 'Café regional com produtos artesanais.',
    responsible_name: 'Maria de Teste',
    email: 'contato@example.test',
    phone: '(24) 99999-8888',
    website: 'https://example.test',
    instagram: '@cafedovale',
    address_mode: 'public_address',
    postal_code: '27700-000',
    street: 'Rua de Teste',
    street_number: '100',
    neighborhood: 'Centro',
    city: 'Vassouras',
    state: 'RJ',
    service_area: '',
    logo_alt: '',
    cover_alt: '',
    gallery_alt: [],
    media_rights_confirmed: false,
    privacy_accepted: true,
    'cf-turnstile-response': 'token-sintético',
});

test('step one blocks a 19 character description and accepts 20 characters', () => {
    const invalid = { ...validValues(), short_description: '1234567890123456789' };
    const valid = { ...validValues(), short_description: '12345678901234567890' };

    assert.deepEqual(validateStepValues(1, invalid), {
        short_description: 'A descrição curta deve ter pelo menos 20 caracteres.',
    });
    assert.deepEqual(validateStepValues(1, valid), {});
});

test('CPF and numeric or alphanumeric CNPJ use their check digits', () => {
    assert.equal(cpfIsValid('529.982.247-25'), true);
    assert.equal(cpfIsValid('529.982.247-24'), false);
    assert.equal(cnpjIsValid('11.222.333/0001-81'), true);
    assert.equal(cnpjIsValid('12.ABC.345/01DE-35'), true);
    assert.equal(cnpjIsValid('11.222.333/0001-80'), false);
});

test('step two validates email, phone and optional URLs progressively', () => {
    const values = {
        ...validValues(),
        email: 'inválido',
        phone: '1234',
        website: 'example.test',
        instagram: '@perfil com espaço',
    };

    assert.deepEqual(validateStepValues(2, values), {
        email: 'Informe um endereço de e-mail válido.',
        phone: 'Informe um telefone brasileiro válido, com DDD.',
        website: 'Informe uma URL completa iniciada por http:// ou https://.',
        instagram: 'Informe um perfil do Instagram válido, com até 30 caracteres.',
    });
});

test('public address validates only public fields', () => {
    const values = {
        ...validValues(),
        postal_code: '',
        street: '',
        street_number: '',
        neighborhood: '',
        service_area: '',
    };

    assert.deepEqual(validateStepValues(3, values), {
        postal_code: 'Informe um CEP válido com oito dígitos.',
        street: 'Informe o logradouro do endereço público.',
        street_number: 'Informe o número do endereço público.',
        neighborhood: 'Informe o bairro do endereço público.',
    });
});

test('service area ignores disabled public address fields and requires its description', () => {
    const valid = {
        ...validValues(),
        address_mode: 'service_area',
        postal_code: '',
        street: '',
        street_number: '',
        neighborhood: '',
        service_area: 'Vassouras e região',
    };

    assert.deepEqual(validateStepValues(3, valid), {});
    assert.deepEqual(validateStepValues(3, { ...valid, service_area: '' }), {
        service_area: 'Descreva a área de atendimento.',
    });
});

test('media validation checks type, size, alt text, count and usage rights', () => {
    const files = {
        logo: [{ type: 'image/svg+xml', size: 100 }],
        cover: [{ type: 'image/webp', size: 5 * 1024 * 1024 + 1 }],
        gallery: Array.from({ length: 7 }, () => ({ type: 'image/jpeg', size: 100 })),
    };

    assert.deepEqual(validateStepValues(4, validValues(), files), {
        logo: 'A logo deve ser uma imagem JPEG, PNG ou WebP.',
        cover: 'A foto de capa deve ter no máximo 5 MB.',
        gallery: 'Envie no máximo seis fotografias e oito imagens no total.',
        logo_alt: 'Informe a descrição alternativa da logo.',
        cover_alt: 'Informe a descrição alternativa da foto de capa.',
        gallery_alt: 'Informe uma descrição alternativa para cada fotografia.',
        media_rights_confirmed: 'Confirme que possui autorização para usar as imagens enviadas.',
    });
});

test('privacy and Turnstile remain required on the final step', () => {
    const values = { ...validValues(), privacy_accepted: false, 'cf-turnstile-response': '' };

    assert.deepEqual(validateStepValues(4, values), {
        privacy_accepted: 'Você precisa aceitar o aviso de privacidade para enviar.',
        'cf-turnstile-response': 'Conclua a verificação de segurança.',
    });
});

test('the earliest server error restores its corresponding step', () => {
    assert.equal(stepForServerErrors(['privacy_accepted', 'email']), 2);
    assert.equal(stepForServerErrors(['street']), 3);
    assert.equal(stepForServerErrors(['gallery.0']), 4);
    assert.equal(stepForServerErrors(['document']), 1);
});
