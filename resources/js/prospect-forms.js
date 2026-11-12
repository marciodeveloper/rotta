export function installProspectForms(root = document) {
    root.querySelectorAll('[data-prospect-form]').forEach((form) => {
        let sending = false;
        form.addEventListener('submit', (event) => {
            if (sending) { event.preventDefault(); return; }
            sending = true;
            form.setAttribute('aria-busy', 'true');
            form.querySelectorAll('button[type=submit]').forEach((button) => { button.disabled = true; });
            const status = form.querySelector('[data-prospect-saving]');
            if (status) status.hidden = false;
        });
        window.addEventListener('pageshow', () => {
            sending = false;
            form.removeAttribute('aria-busy');
            form.querySelectorAll('button[type=submit]').forEach((button) => { button.disabled = false; });
            const status = form.querySelector('[data-prospect-saving]');
            if (status) status.hidden = true;
        });
    });
    root.querySelector('[data-prospect-errors]')?.focus();
}
