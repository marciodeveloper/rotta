import Alpine from '@alpinejs/csp';
import { installCepLookup } from './cep-lookup';

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
    get documentInputMode() { return this.applicantType === 'company' ? 'text' : 'numeric'; },
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
const documentInput = document.querySelector('[data-document-input]');
const documentFeedback = document.querySelector('[data-document-feedback]');

const cpfIsValid = (value) => {
    const digits = onlyDigits(value);
    if (digits.length !== 11 || /^(\d)\1{10}$/.test(digits)) return false;
    return [9, 10].every((position) => {
        let sum = 0;
        for (let index = 0; index < position; index += 1) sum += Number(digits[index]) * (position + 1 - index);
        return Number(digits[position]) === ((10 * sum) % 11) % 10;
    });
};

const cnpjIsValid = (value) => {
    const normalized = value.toUpperCase().replace(/[^A-Z0-9]/g, '');
    if (! /^[A-Z0-9]{12}[0-9]{2}$/.test(normalized) || /^(\d)\1{13}$/.test(normalized)) return false;
    const calculate = (source, weights) => {
        const sum = weights.reduce((total, weight, index) => total + (source.charCodeAt(index) - 48) * weight, 0);
        const remainder = sum % 11;
        return remainder < 2 ? 0 : 11 - remainder;
    };
    const first = calculate(normalized, [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
    const second = calculate(normalized, [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
    return Number(normalized[12]) === first && Number(normalized[13]) === second;
};

const formatCpf = (value) => onlyDigits(value).slice(0, 11)
    .replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d{1,2})$/, '$1-$2');
const formatCnpj = (value) => {
    const raw = value.toUpperCase().replace(/[^A-Z0-9]/g, '');
    const normalized = `${raw.slice(0, 12)}${raw.slice(12).replace(/\D/g, '').slice(0, 2)}`;
    let formatted = normalized.slice(0, 2);
    if (normalized.length > 2) formatted += `.${normalized.slice(2, 5)}`;
    if (normalized.length > 5) formatted += `.${normalized.slice(5, 8)}`;
    if (normalized.length > 8) formatted += `/${normalized.slice(8, 12)}`;
    if (normalized.length > 12) formatted += `-${normalized.slice(12, 14)}`;
    return formatted;
};

const updateDocument = () => {
    if (! documentInput || ! documentFeedback) return;
    const company = document.querySelector('input[name="applicant_type"]:checked')?.value === 'company';
    documentInput.value = company ? formatCnpj(documentInput.value) : formatCpf(documentInput.value);
    const normalizedLength = documentInput.value.replace(/[^A-Z0-9]/gi, '').length;
    const complete = normalizedLength === (company ? 14 : 11);
    const valid = complete && (company ? cnpjIsValid(documentInput.value) : cpfIsValid(documentInput.value));
    documentInput.setCustomValidity(complete && ! valid ? `${company ? 'CNPJ' : 'CPF'} inválido.` : '');
    documentFeedback.textContent = ! complete ? '' : (valid ? 'Dígitos verificadores válidos.' : 'Confira os dígitos verificadores.');
    documentFeedback.classList.toggle('is-valid', valid);
    documentFeedback.classList.toggle('is-invalid', complete && ! valid);
};
documentInput?.addEventListener('input', updateDocument);
document.querySelectorAll('input[name="applicant_type"]').forEach((input) => input.addEventListener('change', () => {
    if (documentInput) documentInput.value = '';
    updateDocument();
}));
updateDocument();

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
installCepLookup();

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

document.querySelectorAll('[data-media-input]').forEach((input) => {
    input.addEventListener('change', () => {
        const preview = document.querySelector(`[data-media-preview="${input.dataset.mediaInput}"]`);
        if (! preview) return;
        preview.replaceChildren();
        const file = input.files?.[0];
        if (! file) return;
        const image = document.createElement('img');
        const url = URL.createObjectURL(file);
        image.src = url;
        image.alt = 'Pré-visualização local';
        image.addEventListener('load', () => URL.revokeObjectURL(url), { once: true });
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.textContent = 'Remover';
        remove.addEventListener('click', () => { input.value = ''; preview.replaceChildren(); });
        preview.append(image, remove);
    });
});

const galleryInput = document.querySelector('[data-gallery-input]');
const galleryPreview = document.querySelector('[data-gallery-preview]');
const galleryDropzone = document.querySelector('[data-gallery-dropzone]');
let galleryFiles = [];

const syncGallery = () => {
    if (! galleryInput || ! galleryPreview) return;
    const transfer = new DataTransfer();
    galleryFiles.forEach((entry) => transfer.items.add(entry.file));
    galleryInput.files = transfer.files;
    galleryPreview.replaceChildren();
    galleryFiles.forEach((entry, position) => {
        const item = document.createElement('li');
        item.draggable = true;
        item.dataset.position = String(position);
        const image = document.createElement('img');
        const url = URL.createObjectURL(entry.file);
        image.src = url;
        image.alt = '';
        image.addEventListener('load', () => URL.revokeObjectURL(url), { once: true });
        const label = document.createElement('label');
        label.textContent = `Descrição da foto ${position + 1}`;
        const alt = document.createElement('input');
        alt.name = 'gallery_alt[]';
        alt.maxLength = 240;
        alt.required = true;
        alt.value = entry.alt;
        alt.addEventListener('input', () => { entry.alt = alt.value; });
        const controls = document.createElement('div');
        [['↑', -1], ['↓', 1]].forEach(([text, delta]) => {
            const button = document.createElement('button');
            button.type = 'button'; button.textContent = text;
            button.disabled = position + delta < 0 || position + delta >= galleryFiles.length;
            button.addEventListener('click', () => {
                [galleryFiles[position], galleryFiles[position + delta]] = [galleryFiles[position + delta], galleryFiles[position]];
                syncGallery();
            });
            controls.append(button);
        });
        const remove = document.createElement('button');
        remove.type = 'button'; remove.textContent = 'Remover';
        remove.addEventListener('click', () => { galleryFiles.splice(position, 1); syncGallery(); });
        controls.append(remove);
        label.append(alt); item.append(image, label, controls); galleryPreview.append(item);
        item.addEventListener('dragstart', (event) => event.dataTransfer?.setData('text/plain', String(position)));
        item.addEventListener('dragover', (event) => event.preventDefault());
        item.addEventListener('drop', (event) => {
            event.preventDefault();
            const from = Number(event.dataTransfer?.getData('text/plain'));
            if (! Number.isInteger(from) || from === position) return;
            const [moved] = galleryFiles.splice(from, 1);
            galleryFiles.splice(position, 0, moved); syncGallery();
        });
    });
};

const addGalleryFiles = (files) => {
    galleryFiles = [...galleryFiles, ...files.map((file) => ({ file, alt: '' }))].slice(0, 6);
    syncGallery();
};
galleryInput?.addEventListener('change', () => addGalleryFiles([...galleryInput.files]));
galleryDropzone?.addEventListener('dragover', (event) => { event.preventDefault(); galleryDropzone.classList.add('is-dragging'); });
galleryDropzone?.addEventListener('dragleave', () => galleryDropzone.classList.remove('is-dragging'));
galleryDropzone?.addEventListener('drop', (event) => {
    event.preventDefault(); galleryDropzone.classList.remove('is-dragging'); addGalleryFiles([...(event.dataTransfer?.files ?? [])]);
});
