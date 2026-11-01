import assert from 'node:assert/strict';
import test from 'node:test';

import { installCepLookup } from '../../resources/js/cep-lookup.js';

class FakeElement {
    constructor(value = '') {
        this.value = value;
        this.textContent = '';
        this.disabled = false;
        this.attributes = new Map();
        this.listeners = new Map();
        this.dispatchedEvents = [];
    }

    addEventListener(name, listener) {
        this.listeners.set(name, listener);
    }

    setAttribute(name, value) {
        this.attributes.set(name, value);
    }

    removeAttribute(name) {
        this.attributes.delete(name);
    }

    dispatchEvent(event) {
        this.dispatchedEvents.push(event.type);
    }
}

const fixture = (postalCode = '12604250') => {
    const input = new FakeElement(postalCode);
    const button = new FakeElement();
    const status = new FakeElement();
    const fields = Object.fromEntries(['street', 'neighborhood', 'city', 'state'].map((name) => [name, new FakeElement()]));
    const selectors = new Map([
        ['[data-cep-input]', input],
        ['[data-cep-button]', button],
        ['[data-cep-status]', status],
        ...Object.entries(fields).map(([name, field]) => [`[data-address-field="${name}"]`, field]),
    ]);

    return {
        input,
        button,
        status,
        fields,
        root: { querySelector: (selector) => selectors.get(selector) ?? null },
    };
};

const event = (extra = {}) => ({
    prevented: false,
    stopped: false,
    preventDefault() { this.prevented = true; },
    stopPropagation() { this.stopped = true; },
    ...extra,
});

test('click uses fetch without submitting or navigating and fills the Lorena address', async () => {
    const page = fixture();
    let requestedUrl;
    let requestedOptions;
    const fetcher = async (url, options) => {
        requestedUrl = url;
        requestedOptions = options;

        return {
            ok: true,
            json: async () => ({
                data: {
                    postal_code: '12604250',
                    street: 'Rua Galante Giuseppe',
                    neighborhood: 'Vila Geny',
                    city: 'Lorena',
                    state: 'SP',
                },
            }),
        };
    };
    installCepLookup({ root: page.root, fetcher });
    const click = event();

    await page.button.listeners.get('click')(click);

    assert.equal(click.prevented, true);
    assert.equal(click.stopped, true);
    assert.equal(requestedUrl, '/api/cep?postal_code=12604250');
    assert.equal(requestedOptions.method, 'GET');
    assert.equal(requestedOptions.credentials, 'same-origin');
    assert.equal(page.input.value, '12604-250');
    assert.equal(page.fields.street.value, 'Rua Galante Giuseppe');
    assert.equal(page.fields.neighborhood.value, 'Vila Geny');
    assert.equal(page.fields.city.value, 'Lorena');
    assert.equal(page.fields.state.value, 'SP');
    assert.equal(page.status.textContent, 'Endereço preenchido. Revise antes de continuar.');
    assert.equal(page.button.disabled, false);
});

test('Enter in the CEP input is intercepted and performs the same asynchronous lookup', async () => {
    const page = fixture();
    let calls = 0;
    installCepLookup({
        root: page.root,
        fetcher: async () => {
            calls++;
            return {
                ok: true,
                json: async () => ({
                    data: { postal_code: '12604250', street: '', neighborhood: '', city: 'Lorena', state: 'SP' },
                }),
            };
        },
    });
    const keydown = event({ key: 'Enter' });

    await page.input.listeners.get('keydown')(keydown);

    assert.equal(keydown.prevented, true);
    assert.equal(keydown.stopped, true);
    assert.equal(calls, 1);
});

test('an incomplete CEP does not call the endpoint or submit the form', async () => {
    const page = fixture('12604');
    let calls = 0;
    installCepLookup({
        root: page.root,
        fetcher: async () => { calls++; },
    });
    const click = event();

    await page.button.listeners.get('click')(click);

    assert.equal(click.prevented, true);
    assert.equal(click.stopped, true);
    assert.equal(calls, 0);
    assert.equal(page.status.textContent, 'Informe um CEP com 8 dígitos.');
});

test('a timed out lookup leaves manual entry available and shows a friendly message', async () => {
    const page = fixture();
    installCepLookup({
        root: page.root,
        timeoutMs: 0,
        fetcher: async (_url, options) => new Promise((_resolve, reject) => {
            options.signal.addEventListener('abort', () => reject(new DOMException('synthetic timeout', 'AbortError')));
        }),
    });

    await page.button.listeners.get('click')(event());

    assert.equal(page.status.textContent, 'A consulta demorou demais. Tente novamente ou preencha o endereço manualmente.');
    assert.equal(page.button.disabled, false);
});

test('not found, invalid JSON, incomplete data and network failures show friendly messages', async (context) => {
    const cases = [
        {
            name: 'not found',
            fetcher: async () => ({ ok: false, json: async () => ({ message: 'CEP não encontrado. Use o preenchimento manual.' }) }),
            message: 'CEP não encontrado. Use o preenchimento manual.',
        },
        {
            name: 'invalid JSON',
            fetcher: async () => ({ ok: true, json: async () => { throw new SyntaxError('bad json'); } }),
            message: 'A consulta retornou uma resposta inválida. Preencha o endereço manualmente.',
        },
        {
            name: 'incomplete data',
            fetcher: async () => ({ ok: true, json: async () => ({ data: { postal_code: '12604250' } }) }),
            message: 'A consulta retornou um endereço incompleto. Preencha os campos manualmente.',
        },
        {
            name: 'network failure',
            fetcher: async () => { throw new TypeError('network details must not leak'); },
            message: 'Não foi possível consultar o CEP agora. Tente novamente ou preencha o endereço manualmente.',
        },
    ];

    for (const scenario of cases) {
        await context.test(scenario.name, async () => {
            const page = fixture();
            installCepLookup({ root: page.root, fetcher: scenario.fetcher });

            await page.button.listeners.get('click')(event());

            assert.equal(page.status.textContent, scenario.message);
            assert.equal(page.input.value, '12604-250');
            assert.equal(page.button.disabled, false);
        });
    }
});
