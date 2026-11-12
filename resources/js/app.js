import Alpine from '@alpinejs/csp';
import { installProspectForms } from './prospect-forms';
installProspectForms();
import { installCepLookup } from './cep-lookup';
import { cnpjIsValid, cpfIsValid, createPreRegistrationValidator } from './pre-registration-validation';

if (document.querySelector('[data-admin-table-shell]')) {
    import('./admin-table').then(({ installAdminTable }) => installAdminTable()).catch(() => {
        document.querySelector('[data-table-error]').hidden = false;
    });
}

if (document.querySelector('[data-action-dialog]')) {
    import('./admin-actions').then(({ installAdminActions }) => installAdminActions());
}

Alpine.data('preRegistrationForm', () => {
    let validator;

    return {
        step: 1,
        submitting: false,
        applicantType: 'company',
        addressMode: 'public_address',
        init() {
            this.applicantType = document.querySelector('input[name="applicant_type"]:checked')?.value ?? 'company';
            this.addressMode = document.querySelector('input[name="address_mode"]:checked')?.value ?? 'public_address';
            const form = document.querySelector('[data-pre-registration-form]');
            validator = createPreRegistrationValidator({
                form,
                summary: document.querySelector('[data-client-error-summary]'),
                serverSummary: document.querySelector('[data-error-summary]'),
                onStepRequested: (step) => { this.step = step; },
            });
            this.step = validator.initialStep();
            if (document.querySelector('[data-error-summary]')) {
                setTimeout(() => document.querySelector('[data-error-summary]')?.focus(), 0);
            }
        },
        get documentLabel() { return this.applicantType === 'company' ? 'CNPJ' : 'CPF'; },
        get documentInputMode() { return this.applicantType === 'company' ? 'text' : 'numeric'; },
        get hasPublicAddress() { return this.addressMode === 'public_address'; },
        get hasServiceArea() { return this.addressMode === 'service_area'; },
        get submitLabel() { return this.submitting ? 'Enviando…' : 'Enviar pré-cadastro'; },
        isStep(value) { return this.step === value; },
        stepClass(value) { return value === this.step ? 'is-active' : (value < this.step ? 'is-complete' : ''); },
        next() {
            if (! validator.validateStep(this.step).valid) return;
            if (this.step < 4) {
                this.step += 1;
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        },
        previous() { if (this.step > 1) { this.step -= 1; window.scrollTo({ top: 0, behavior: 'smooth' }); } },
        startSubmitting(event) {
            if (! validator.validateAll().valid) {
                event.preventDefault();
                return;
            }
            this.submitting = true;
        },
    };
});

window.Alpine = Alpine;
Alpine.start();

const onlyDigits = (value) => value.replace(/\D/g, '');
const documentInput = document.querySelector('[data-document-input]');
const documentFeedback = document.querySelector('[data-document-feedback]');

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
        remove.addEventListener('click', () => {
            input.value = '';
            preview.replaceChildren();
            input.dispatchEvent(new Event('change', { bubbles: true }));
        });
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
        label.dataset.fieldContainer = '';
        label.textContent = `Descrição da foto ${position + 1}`;
        const alt = document.createElement('input');
        alt.id = `field-gallery_alt-${position}`;
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
    galleryFiles = [...galleryFiles, ...files.map((file) => ({ file, alt: '' }))];
    syncGallery();
};
galleryInput?.addEventListener('change', () => addGalleryFiles([...galleryInput.files]));
galleryDropzone?.addEventListener('dragover', (event) => { event.preventDefault(); galleryDropzone.classList.add('is-dragging'); });
galleryDropzone?.addEventListener('dragleave', () => galleryDropzone.classList.remove('is-dragging'));
galleryDropzone?.addEventListener('drop', (event) => {
    event.preventDefault(); galleryDropzone.classList.remove('is-dragging'); addGalleryFiles([...(event.dataTransfer?.files ?? [])]);
});
