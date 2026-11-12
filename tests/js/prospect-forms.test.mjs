import test from 'node:test';
import assert from 'node:assert/strict';
import { installProspectForms } from '../../resources/js/prospect-forms.js';

test('manual prospect submits once, retains input data and can submit again after browser back navigation', () => {
    const previous = globalThis.window;
    const listeners = {};
    const pages = {};
    const button = { disabled: false };
    const status = { hidden: true };
    const attrs = new Map();
    const form = {
        addEventListener: (name, callback) => { listeners[name] = callback; },
        setAttribute: (key, value) => attrs.set(key, value),
        removeAttribute: (key) => attrs.delete(key),
        querySelectorAll: () => [button],
        querySelector: () => status,
    };
    let focused = false;
    globalThis.window = { addEventListener: (name, callback) => { pages[name] = callback; } };
    try {
        installProspectForms({
            querySelectorAll: () => [form],
            querySelector: () => ({ focus: () => { focused = true; } }),
        });
        let prevented = 0;
        const event = { preventDefault: () => { prevented += 1; } };
        listeners.submit(event);
        assert.equal(prevented, 0);
        assert.equal(button.disabled, true);
        assert.equal(status.hidden, false);
        assert.equal(attrs.get('aria-busy'), 'true');
        assert.equal(focused, true);
        listeners.submit(event);
        assert.equal(prevented, 1);
        pages.pageshow();
        assert.equal(button.disabled, false);
        assert.equal(status.hidden, true);
        assert.equal(attrs.has('aria-busy'), false);
        listeners.submit(event);
        assert.equal(prevented, 1);
    } finally { globalThis.window = previous; }
});
