const fieldSteps = {
    applicant_type: 1,
    document: 1,
    business_name: 1,
    category: 1,
    short_description: 1,
    responsible_name: 2,
    email: 2,
    phone: 2,
    website: 2,
    instagram: 2,
    address_mode: 3,
    postal_code: 3,
    street: 3,
    street_number: 3,
    complement: 3,
    neighborhood: 3,
    city: 3,
    state: 3,
    service_area: 3,
    google_place_id: 3,
    latitude: 3,
    longitude: 3,
    logo: 4,
    logo_alt: 4,
    cover: 4,
    cover_alt: 4,
    gallery: 4,
    gallery_alt: 4,
    media_rights_confirmed: 4,
    privacy_accepted: 4,
    'cf-turnstile-response': 4,
};

const messages = {
    applicant_type: 'Selecione se o cadastro é de empresa ou profissional.',
    document_company: 'Informe um CNPJ válido, incluindo os dígitos verificadores.',
    document_professional: 'Informe um CPF válido, incluindo os dígitos verificadores.',
    business_name: 'Informe o nome comercial ou profissional com pelo menos 2 caracteres.',
    category: 'Selecione uma categoria.',
    short_description: 'A descrição curta deve ter pelo menos 20 caracteres.',
    responsible_name: 'Informe o nome do responsável com pelo menos 2 caracteres.',
    email: 'Informe um endereço de e-mail válido.',
    phone: 'Informe um telefone brasileiro válido, com DDD.',
    website: 'Informe uma URL completa iniciada por http:// ou https://.',
    instagram: 'Informe um perfil do Instagram válido, com até 30 caracteres.',
    address_mode: 'Selecione como você atende.',
    postal_code: 'Informe um CEP válido com oito dígitos.',
    street: 'Informe o logradouro do endereço público.',
    street_number: 'Informe o número do endereço público.',
    neighborhood: 'Informe o bairro do endereço público.',
    city: 'Informe a cidade.',
    state: 'Informe uma UF válida com duas letras.',
    service_area: 'Descreva a área de atendimento.',
    logo_alt: 'Informe a descrição alternativa da logo.',
    cover_alt: 'Informe a descrição alternativa da foto de capa.',
    gallery_alt: 'Informe uma descrição alternativa para cada fotografia.',
    media_rights_confirmed: 'Confirme que possui autorização para usar as imagens enviadas.',
    privacy_accepted: 'Você precisa aceitar o aviso de privacidade para enviar.',
    'cf-turnstile-response': 'Conclua a verificação de segurança.',
};

const normalizeDocument = (value) => value.toUpperCase().replace(/[^A-Z0-9]/g, '');
const onlyDigits = (value) => value.replace(/\D/g, '');
const hasLength = (value, minimum, maximum) => value.trim().length >= minimum && value.trim().length <= maximum;

export const cpfIsValid = (value) => {
    const digits = onlyDigits(value);
    if (digits.length !== 11 || /^(\d)\1{10}$/.test(digits)) return false;

    return [9, 10].every((position) => {
        let sum = 0;
        for (let index = 0; index < position; index += 1) sum += Number(digits[index]) * (position + 1 - index);
        return Number(digits[position]) === ((10 * sum) % 11) % 10;
    });
};

export const cnpjIsValid = (value) => {
    const normalized = normalizeDocument(value);
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

const validUrl = (value) => {
    if (value === '') return true;
    try {
        return ['http:', 'https:'].includes(new URL(value).protocol);
    } catch {
        return false;
    }
};

const imageError = (file, label) => {
    if (! file) return null;
    if (! ['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
        return `${label} deve ser uma imagem JPEG, PNG ou WebP.`;
    }
    if (file.size > 5 * 1024 * 1024) return `${label} deve ter no máximo 5 MB.`;

    return null;
};

export const validateStepValues = (step, values, files = {}) => {
    const errors = {};
    const add = (field, message) => { if (! errors[field]) errors[field] = message; };
    const value = (field) => String(values[field] ?? '').trim();

    if (step === 1) {
        if (! ['company', 'professional'].includes(value('applicant_type'))) add('applicant_type', messages.applicant_type);
        const professional = value('applicant_type') === 'professional';
        if (! (professional ? cpfIsValid(value('document')) : cnpjIsValid(value('document')))) {
            add('document', professional ? messages.document_professional : messages.document_company);
        }
        if (! hasLength(value('business_name'), 2, 160)) add('business_name', messages.business_name);
        if (! value('category')) add('category', messages.category);
        if (! hasLength(value('short_description'), 20, 500)) add('short_description', messages.short_description);
    }

    if (step === 2) {
        if (! hasLength(value('responsible_name'), 2, 160)) add('responsible_name', messages.responsible_name);
        if (! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value('email')) || value('email').length > 254) add('email', messages.email);
        if (! /^\(?\d{2}\)?\s?9?\d{4}-?\d{4}$/.test(value('phone'))) add('phone', messages.phone);
        if (! validUrl(value('website')) || value('website').length > 500) add('website', messages.website);
        if (value('instagram') && ! /^@?[A-Za-z0-9._]{1,30}$/.test(value('instagram'))) add('instagram', messages.instagram);
    }

    if (step === 3) {
        const addressMode = value('address_mode');
        if (! ['public_address', 'service_area'].includes(addressMode)) add('address_mode', messages.address_mode);
        if (addressMode === 'public_address') {
            if (! /^\d{5}-?\d{3}$/.test(value('postal_code'))) add('postal_code', messages.postal_code);
            if (! value('street')) add('street', messages.street);
            if (! value('street_number')) add('street_number', messages.street_number);
            if (! value('neighborhood')) add('neighborhood', messages.neighborhood);
        }
        if (! value('city')) add('city', messages.city);
        if (! /^[A-Za-z]{2}$/.test(value('state'))) add('state', messages.state);
        if (addressMode === 'service_area' && ! value('service_area')) add('service_area', messages.service_area);
    }

    if (step === 4) {
        const logo = files.logo?.[0];
        const cover = files.cover?.[0];
        const gallery = files.gallery ?? [];
        const mediaCount = Number(Boolean(logo)) + Number(Boolean(cover)) + gallery.length;
        const logoError = imageError(logo, 'A logo');
        const coverError = imageError(cover, 'A foto de capa');
        if (logoError) add('logo', logoError);
        if (coverError) add('cover', coverError);
        if (gallery.length > 6 || mediaCount > 8) add('gallery', 'Envie no máximo seis fotografias e oito imagens no total.');
        gallery.forEach((file) => {
            const error = imageError(file, 'Cada fotografia');
            if (error) add('gallery', error);
        });
        if (logo && ! value('logo_alt')) add('logo_alt', messages.logo_alt);
        if (cover && ! value('cover_alt')) add('cover_alt', messages.cover_alt);
        const galleryAlt = values.gallery_alt ?? [];
        if (gallery.some((_file, index) => ! String(galleryAlt[index] ?? '').trim())) add('gallery_alt', messages.gallery_alt);
        if (mediaCount > 0 && ! values.media_rights_confirmed) add('media_rights_confirmed', messages.media_rights_confirmed);
        if (! values.privacy_accepted) add('privacy_accepted', messages.privacy_accepted);
        if (! value('cf-turnstile-response')) add('cf-turnstile-response', messages['cf-turnstile-response']);
    }

    return errors;
};

const baseFieldName = (field) => field.replace(/\.\d+$/, '').replace(/\[\]$/, '');

export const stepForServerErrors = (fields) => fields.reduce((step, field) => (
    Math.min(step, fieldSteps[baseFieldName(field)] ?? 4)
), 4);

const fieldSelector = (field) => `[name="${field}"], [name="${field}[]"]`;

export const createPreRegistrationValidator = ({ form, summary, serverSummary, onStepRequested = () => {} }) => {
    const activeFields = (name) => [...form.querySelectorAll(fieldSelector(name))].filter((field) => ! field.disabled);
    const firstField = (name) => activeFields(name)[0] ?? null;
    const fieldValue = (name) => {
        const fields = activeFields(name);
        if (fields[0]?.type === 'radio') return fields.find((field) => field.checked)?.value ?? '';
        if (fields[0]?.type === 'checkbox') return fields.some((field) => field.checked);
        if (name === 'gallery_alt') return fields.map((field) => field.value);
        return fields[0]?.value ?? '';
    };
    const fileValues = () => ({
        logo: [...(firstField('logo')?.files ?? [])],
        cover: [...(firstField('cover')?.files ?? [])],
        gallery: [...(firstField('gallery')?.files ?? [])],
    });
    const values = () => Object.fromEntries(Object.keys(fieldSteps).map((name) => [name, fieldValue(name)]));
    const errorId = (name) => `client-error-${name.replace(/[^a-z0-9_-]/gi, '-')}`;
    const clearError = (name) => {
        form.querySelectorAll(`[data-client-error-for="${name}"], [data-server-error-for="${name}"]`).forEach((error) => error.remove());
        activeFields(name).forEach((field) => {
            field.removeAttribute('aria-invalid');
            const describedBy = (field.getAttribute('aria-describedby') ?? '').split(/\s+/).filter((id) => id && id !== errorId(name));
            if (describedBy.length) field.setAttribute('aria-describedby', describedBy.join(' '));
            else field.removeAttribute('aria-describedby');
        });
    };
    const showError = (name, message) => {
        clearError(name);
        const field = firstField(name);
        if (! field) return;
        const error = document.createElement('p');
        error.id = errorId(name);
        error.className = 'field-error';
        error.dataset.clientErrorFor = name;
        error.setAttribute('aria-live', 'polite');
        error.textContent = message;
        const container = field.closest('[data-field-container]') ?? field.parentElement;
        container?.append(error);
        activeFields(name).forEach((item) => {
            item.setAttribute('aria-invalid', 'true');
            const describedBy = new Set((item.getAttribute('aria-describedby') ?? '').split(/\s+/).filter(Boolean));
            describedBy.add(error.id);
            item.setAttribute('aria-describedby', [...describedBy].join(' '));
        });
    };
    const focusField = (name) => {
        const field = firstField(baseFieldName(name));
        if (! field) return;
        field.focus({ preventScroll: true });
        field.scrollIntoView({ behavior: 'smooth', block: 'center' });
    };
    const renderSummary = (errors, focus = true) => {
        if (! summary) return;
        const list = summary.querySelector('ul');
        list?.replaceChildren();
        Object.entries(errors).forEach(([name, message]) => {
            const item = document.createElement('li');
            const link = document.createElement('a');
            link.href = `#${firstField(name)?.id ?? name}`;
            link.textContent = message;
            link.addEventListener('click', (event) => {
                event.preventDefault();
                const step = fieldSteps[name] ?? 4;
                onStepRequested(step);
                setTimeout(() => focusField(name), 0);
            });
            item.append(link);
            list?.append(item);
        });
        summary.hidden = Object.keys(errors).length === 0;
        if (focus && ! summary.hidden) summary.focus();
    };
    const validateStep = (step, options = {}) => {
        const errors = validateStepValues(step, values(), fileValues());
        Object.keys(fieldSteps).filter((name) => fieldSteps[name] === step).forEach(clearError);
        Object.entries(errors).forEach(([name, message]) => showError(name, message));
        renderSummary(errors, options.focusSummary !== false);
        if (Object.keys(errors).length && options.focusField !== false) focusField(Object.keys(errors)[0]);

        return { valid: Object.keys(errors).length === 0, errors, step };
    };
    const validateAll = () => {
        const results = [1, 2, 3, 4].map((step) => ({ step, errors: validateStepValues(step, values(), fileValues()) }));
        const firstInvalid = results.find((result) => Object.keys(result.errors).length);
        if (! firstInvalid) {
            renderSummary({}, false);
            return { valid: true, errors: {}, step: 4 };
        }
        onStepRequested(firstInvalid.step);
        setTimeout(() => {
            Object.entries(firstInvalid.errors).forEach(([name, message]) => showError(name, message));
            renderSummary(firstInvalid.errors);
            focusField(Object.keys(firstInvalid.errors)[0]);
        }, 0);

        return { valid: false, errors: firstInvalid.errors, step: firstInvalid.step };
    };

    form.addEventListener('input', (event) => {
        const name = baseFieldName(event.target?.name ?? '');
        if (! name || (! form.querySelector(`[data-client-error-for="${name}"]`) && ! form.querySelector(`[data-server-error-for="${name}"]`))) return;
        const step = fieldSteps[name];
        const errors = validateStepValues(step, values(), fileValues());
        if (errors[name]) showError(name, errors[name]);
        else clearError(name);
    });
    form.addEventListener('change', (event) => {
        const name = baseFieldName(event.target?.name ?? '');
        if (! name) return;
        if (name === 'address_mode') {
            Object.keys(fieldSteps).filter((field) => fieldSteps[field] === 3 && ! firstField(field)).forEach(clearError);
            return;
        }
        const relatedFields = {
            logo: ['logo', 'logo_alt', 'media_rights_confirmed'],
            cover: ['cover', 'cover_alt', 'media_rights_confirmed'],
            gallery: ['gallery', 'gallery_alt', 'media_rights_confirmed'],
        }[name] ?? [name];
        const errors = validateStepValues(fieldSteps[name], values(), fileValues());
        relatedFields.forEach((field) => {
            if (errors[field]) showError(field, errors[field]);
            else clearError(field);
        });
    });

    const handleServerSummaryClick = (event) => {
        const link = event.target.closest('[data-server-error-field]');
        if (! link) return;
        event.preventDefault();
        const field = baseFieldName(link.dataset.serverErrorField);
        onStepRequested(fieldSteps[field] ?? 4);
        setTimeout(() => focusField(field), 0);
    };
    serverSummary?.addEventListener('click', handleServerSummaryClick);

    const serverErrors = [...form.querySelectorAll('[data-server-error-for]')];
    const serverFields = serverErrors.map((error) => error.dataset.serverErrorFor);
    serverErrors.forEach((error) => {
        activeFields(error.dataset.serverErrorFor).forEach((field) => {
            field.setAttribute('aria-invalid', 'true');
            const describedBy = new Set((field.getAttribute('aria-describedby') ?? '').split(/\s+/).filter(Boolean));
            describedBy.add(error.id);
            field.setAttribute('aria-describedby', [...describedBy].join(' '));
        });
    });

    return {
        initialStep: () => serverFields.length ? stepForServerErrors(serverFields) : 1,
        validateStep,
        validateAll,
    };
};
