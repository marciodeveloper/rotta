import Alpine from '@alpinejs/csp';

Alpine.data('preRegistrationForm', () => ({
    step: 1,
    submitting: false,
    applicantType: 'company',
    addressMode: 'public_address',
    init() {
        this.applicantType = document.querySelector('input[name="applicant_type"]:checked')?.value ?? 'company';
        this.addressMode = document.querySelector('input[name="address_mode"]:checked')?.value ?? 'public_address';
    },
    get documentLabel() { return this.applicantType === 'company' ? 'CNPJ' : 'CPF'; },
    get hasPublicAddress() { return this.addressMode === 'public_address'; },
    get hasServiceArea() { return this.addressMode === 'service_area'; },
    get submitLabel() { return this.submitting ? 'Enviando…' : 'Enviar pré-cadastro'; },
    isStep(value) { return this.step === value; },
    stepClass(value) { return value === this.step ? 'is-active' : (value < this.step ? 'is-complete' : ''); },
    next() {
        const visibleStep = [...document.querySelectorAll('.form-step')]
            .find((element) => getComputedStyle(element).display !== 'none');
        const invalidField = visibleStep?.querySelector(':invalid');

        if (invalidField instanceof HTMLElement) {
            invalidField.reportValidity();
            invalidField.focus();

            return;
        }

        if (this.step < 4) {
            this.step += 1;
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    },
    previous() { if (this.step > 1) { this.step -= 1; window.scrollTo({ top: 0, behavior: 'smooth' }); } },
    startSubmitting() { this.submitting = true; },
}));

window.Alpine = Alpine;
Alpine.start();

const onlyDigits = (value) => value.replace(/\D/g, '');

document.querySelectorAll('input[name="phone"]').forEach((input) => {
    input.addEventListener('input', () => {
        const digits = onlyDigits(input.value).slice(0, 11);
        const parts = [];
        if (digits.length) parts.push(`(${digits.slice(0, 2)}` + (digits.length >= 2 ? ') ' : ''));
        if (digits.length > 2) parts.push(digits.slice(2, digits.length === 11 ? 7 : 6));
        if (digits.length > 6) parts.push(`-${digits.slice(digits.length === 11 ? 7 : 6)}`);
        input.value = parts.join('');
    });
});

const cepInput = document.querySelector('[data-cep-input]');
const cepButton = document.querySelector('[data-cep-button]');
const cepStatus = document.querySelector('[data-cep-status]');

cepButton?.addEventListener('click', async () => {
    const postalCode = onlyDigits(cepInput?.value ?? '');
    if (postalCode.length !== 8) {
        cepStatus.textContent = 'Informe um CEP com 8 dígitos.';
        return;
    }

    cepButton.disabled = true;
    cepStatus.textContent = 'Buscando…';
    try {
        const response = await fetch(`/api/cep?postal_code=${encodeURIComponent(postalCode)}`, { headers: { Accept: 'application/json' } });
        const payload = await response.json();
        if (! response.ok) throw new Error(payload.message ?? 'CEP não encontrado.');
        Object.entries(payload.data).forEach(([name, value]) => {
            const field = document.querySelector(`[data-address-field="${name}"]`);
            if (field) field.value = value;
        });
        if (cepInput) cepInput.value = postalCode.replace(/(\d{5})(\d{3})/, '$1-$2');
        cepStatus.textContent = 'Endereço preenchido. Revise antes de continuar.';
    } catch (error) {
        cepStatus.textContent = error instanceof Error ? error.message : 'Não foi possível buscar o CEP.';
    } finally {
        cepButton.disabled = false;
    }
});

const placeMount = document.querySelector('[data-google-place]');
if (placeMount?.dataset.googleKey) {
    const script = document.createElement('script');
    script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(placeMount.dataset.googleKey)}&libraries=places&loading=async`;
    script.async = true;
    script.addEventListener('load', async () => {
        const { PlaceAutocompleteElement } = await google.maps.importLibrary('places');
        const autocomplete = new PlaceAutocompleteElement({ includedRegionCodes: ['br'] });
        placeMount.append(autocomplete);
        autocomplete.addEventListener('gmp-select', async (event) => {
            const place = event.placePrediction.toPlace();
            await place.fetchFields({ fields: ['id', 'addressComponents', 'location'] });
            const component = (type) => place.addressComponents?.find((item) => item.types.includes(type));
            const values = {
                street: component('route')?.longText ?? '',
                neighborhood: component('sublocality_level_1')?.longText ?? component('sublocality')?.longText ?? '',
                city: component('administrative_area_level_2')?.longText ?? '',
                state: component('administrative_area_level_1')?.shortText ?? '',
                postal_code: component('postal_code')?.longText ?? '',
                google_place_id: place.id ?? '',
                latitude: place.location?.lat() ?? '',
                longitude: place.location?.lng() ?? '',
            };
            Object.entries(values).forEach(([name, value]) => {
                const field = name === 'postal_code' ? cepInput : document.querySelector(`[data-address-field="${name}"]`);
                if (field) field.value = value;
            });
        });
    });
    document.head.append(script);
}

document.querySelector('[data-error-summary]')?.focus();
