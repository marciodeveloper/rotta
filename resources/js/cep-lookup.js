const onlyDigits = (value) => value.replace(/\D/g, '');

const formatPostalCode = (value) => value.replace(/(\d{5})(\d{3})/, '$1-$2');

class CepLookupError extends Error {}

const validAddress = (value) => value
    && /^\d{8}$/.test(value.postal_code)
    && typeof value.street === 'string'
    && typeof value.neighborhood === 'string'
    && typeof value.city === 'string'
    && value.city.trim() !== ''
    && typeof value.state === 'string'
    && /^[A-Z]{2}$/.test(value.state);

export const installCepLookup = ({ root = document, fetcher = globalThis.fetch, timeoutMs = 8000 } = {}) => {
    const input = root.querySelector('[data-cep-input]');
    const button = root.querySelector('[data-cep-button]');
    const status = root.querySelector('[data-cep-status]');

    if (! input || ! button || ! status || typeof fetcher !== 'function') return null;

    const setStatus = (message) => {
        status.textContent = message;
    };

    const lookup = async (event) => {
        event?.preventDefault?.();
        event?.stopPropagation?.();

        const postalCode = onlyDigits(input.value ?? '');
        if (postalCode.length !== 8) {
            setStatus('Informe um CEP com 8 dígitos.');
            return;
        }

        input.value = formatPostalCode(postalCode);
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        setStatus('Buscando…');

        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), timeoutMs);

        try {
            const response = await fetcher(`/api/cep?postal_code=${encodeURIComponent(postalCode)}`, {
                method: 'GET',
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                signal: controller.signal,
            });

            let payload;
            try {
                payload = await response.json();
            } catch {
                throw new CepLookupError('A consulta retornou uma resposta inválida. Preencha o endereço manualmente.');
            }

            if (! response.ok) {
                throw new CepLookupError(
                    typeof payload?.message === 'string'
                        ? payload.message
                        : 'Não foi possível consultar o CEP. Preencha o endereço manualmente.',
                );
            }

            if (! validAddress(payload?.data)) {
                throw new CepLookupError('A consulta retornou um endereço incompleto. Preencha os campos manualmente.');
            }

            ['street', 'neighborhood', 'city', 'state'].forEach((name) => {
                const field = root.querySelector(`[data-address-field="${name}"]`);
                if (! field) return;
                field.value = payload.data[name];
                field.dispatchEvent(new Event('input', { bubbles: true }));
            });

            setStatus('Endereço preenchido. Revise antes de continuar.');
        } catch (error) {
            if (error?.name === 'AbortError') {
                setStatus('A consulta demorou demais. Tente novamente ou preencha o endereço manualmente.');
            } else if (error instanceof CepLookupError) {
                setStatus(error.message);
            } else {
                setStatus('Não foi possível consultar o CEP agora. Tente novamente ou preencha o endereço manualmente.');
            }
        } finally {
            clearTimeout(timeout);
            button.disabled = false;
            button.removeAttribute('aria-busy');
        }
    };

    button.addEventListener('click', lookup);
    input.addEventListener('keydown', async (event) => {
        if (event.key === 'Enter') await lookup(event);
    });

    return { lookup };
};
