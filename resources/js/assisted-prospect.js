import { explorerRequest } from './business-explorer.js';

const key = (value) => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();

export function parseIndependentDraft(text, labels) {
    const aliases = new Map(Object.entries(labels).flatMap(([field, label]) => [[key(field), field], [key(label), field]]));
    const values = {}; const unknown = [];
    for (const line of text.split(/\r?\n/).filter((item) => item.trim())) {
        const match = line.match(/^([^:\t]+)[:\t]\s*(.*)$/);
        const field = match && aliases.get(key(match[1]));
        if (!field || !match[2].trim()) { unknown.push(line.split(/[:\t]/)[0]); continue; }
        values[field] = match[2].trim();
    }
    return { values, unknown };
}

export function fillIndependentFields(form, values, { source, kind, confirmed, overwrite = false }) {
    if (!confirmed || !['direct_contact', 'owned_records', 'licensed_source', 'public_data'].includes(kind) || source.trim().length < 10 || /google|goo\.gl|g\.co|gstatic|googleapis/i.test(source)) {
        throw new Error('Confirme os dados e informe uma fonte independente ou licença válida, com pelo menos 10 caracteres.');
    }
    const filled = []; const conflicts = [];
    form.querySelectorAll('[data-provenance-field]').forEach((row) => {
        const field = row.dataset.provenanceField;
        const input = form.elements.namedItem(field);
        if (!input || typeof values[field] !== 'string' || !values[field].trim()) return;
        const value = values[field].trim();
        if (input.value.trim() && input.value !== value && !overwrite) { conflicts.push(field); return; }
        if (input.maxLength > 0 && value.length > input.maxLength) { conflicts.push(field); return; }
        input.value = value;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        row.querySelector('[data-provenance-kind]').value = kind;
        row.querySelector('[data-provenance-source]').value = source.trim();
        row.querySelector('[data-provenance-confirmed]').checked = true;
        filled.push(field);
    });
    if (filled.length && !form.elements.namedItem('origin_reference').value) form.elements.namedItem('origin_reference').value = source.trim();
    return { filled, conflicts };
}


export const commercialFields = ['business_name', 'category', 'city', 'state', 'postal_code', 'street', 'street_number', 'complement', 'neighborhood', 'email', 'phone', 'website', 'instagram'];

export function equivalentCommercialValue(field, left, right) {
    if (field === 'phone' || field === 'postal_code') {
        const digits = value => {
            const number = value.replace(/\D/g, '');
            return field === 'phone' && number.length > 11 && number.startsWith('55') ? number.slice(2) : number;
        };
        return digits(left) === digits(right);
    }
    return left.trim().replace(/\s+/g, ' ') === right.trim().replace(/\s+/g, ' ');
}

export function enrichmentInputValue(field, value) {
    const cleaned = value.trim();
    if (field === 'phone') {
        const digits = cleaned.replace(/\D/g, '');
        if (/^55\d{10,11}$/.test(digits)) return digits.slice(2);
    }
    return cleaned;
}

export function applyAuthorizedEnrichment(form, values) {
    const filled = []; const conflicts = [];
    for (const field of commercialFields) {
        const input = form.elements.namedItem(field);
        const candidate = values[field];
        if (!input || typeof candidate !== 'string' || !candidate.trim()) continue;
        const value = enrichmentInputValue(field, candidate);
        if (input.maxLength > 0 && value.length > input.maxLength) continue;
        if (input.value.trim() && !equivalentCommercialValue(field, input.value, value)) { conflicts.push({ field, current: input.value, value }); continue; }
        if (!input.value.trim()) { input.value = value; input.dispatchEvent(new Event('input', { bubbles: true })); filled.push(field); }
    }
    return { filled, conflicts, pending: commercialFields.filter(field => !form.elements.namedItem(field)?.value.trim()).length };
}

export function enrichmentSummary({ filled, pending, conflicts, available, reason }) {
    if (!available) return `Nenhum dado disponível para preenchimento automático. Motivo: ${reason || 'Nenhuma fonte autorizada retornou dados válidos.'}`;
    if (!filled && !conflicts) return 'Os dados disponíveis já estão preenchidos. Nenhum campo foi alterado.';
    if (!filled) return `Nenhum campo foi alterado automaticamente. ${conflicts} ${conflicts === 1 ? 'divergência aguarda' : 'divergências aguardam'} sua revisão. Seus dados foram preservados.`;
    return `${filled} campos preenchidos, ${pending} pendentes e ${conflicts} ${conflicts === 1 ? 'divergência' : 'divergências'}.`;
}

export function installAssistedProspect(root = document, fetcher = window.fetch.bind(window)) {
    const shell = root.querySelector('[data-assisted-reference]');
    const form = root.querySelector('[data-prospect-form]');
    if (!shell || !form || shell.dataset.installed) return;
    shell.dataset.installed = 'true';
    root.querySelector('[data-discard-enrichment]')?.addEventListener('click',()=>{
        form.elements.namedItem('enrichment_token').value='';
        form.querySelector('[data-enrichment-origin]').textContent='Consulta descartada. Os valores foram preservados e serão registrados como declarações manuais ao salvar.';
        form.querySelector('[data-enrichment-summary]').textContent='Você pode salvar sem consultar serviços externos. Os dados não receberão confirmação independente.';
    });
    root.querySelector('[data-explorer-back]')?.addEventListener('click',event=>{
        try {const previous=new URL(document.referrer);if(previous.origin===window.location.origin&&previous.pathname.replace(/\/$/,'')==='/admin/prospeccao/explorador'&&window.history.length>1){event.preventDefault();window.history.back();}}catch{}
    });
    const error = shell.querySelector('[data-assisted-error]');
    const status = shell.querySelector('[data-assisted-status]');
    const summary = form.querySelector('[data-enrichment-summary]');
    const conflictBox = form.querySelector('[data-enrichment-conflicts]');
    let busy = false;
    let activeConflicts = [];
    let lastResult = null;
    let googleDetails = null;
    let googleApplied;try{googleApplied=new Set(JSON.parse(form.elements.namedItem('google_applied_fields')?.value||'[]'));}catch{googleApplied=new Set();}
    const refreshSummary = () => {
        if (!lastResult) return;
        const pending = commercialFields.filter(field => !form.elements.namedItem(field)?.value.trim()).length;
        summary.textContent = lastResult.provider === 'google_places' && lastResult.filled ? `${lastResult.filled} campos preenchidos com dados do Google. Revise as informações antes de salvar. ${pending} pendentes e ${activeConflicts.length} divergências.` : enrichmentSummary({filled:lastResult.filled,pending,conflicts:activeConflicts.length,available:lastResult.available,reason:lastResult.message});
    };
    const labels = Object.fromEntries(commercialFields.map(field => [field, form.querySelector(`label[for="${field}"]`)?.textContent.replace('*', '').trim() || field]));
    const badges = {};
    for (const field of commercialFields) {
        const input = form.elements.namedItem(field);
        if (!input) continue;
        const badge = document.createElement('p'); badge.className = 'crm-muted text-sm'; badge.dataset.fieldStatus = field;
        input.closest('.prospect-field').append(badge); badges[field] = badge;
    }
    const refreshBadges = (conflicts = activeConflicts) => {
        for (const field of commercialFields) if (badges[field]) badges[field].textContent = conflicts.some(item => item.field === field) ? 'Necessita revisão' : form.elements.namedItem(field).value.trim() ? (googleApplied.has(field) ? 'Google Places · revise antes de salvar' : 'Preenchido · revise antes de salvar') : 'Pendente · complete se disponível';
    };
    form.addEventListener('input', () => {refreshBadges();refreshSummary();}); refreshBadges();
    const text = (label, value) => { const p = document.createElement('p'); p.className = 'break-words'; p.textContent = `${label}: ${value || 'Não informado'}`; return p; };
    const link = (label, raw, hosts = null) => {
        try {
            const url = new URL(raw);
            const internal = url.origin === window.location.origin && hosts?.includes(window.location.hostname);
            if ((!internal && url.protocol !== 'https:') || (hosts && !hosts.includes(url.hostname))) return null;
            const a = document.createElement('a'); a.className = 'crm-context-link'; a.textContent = label; a.href = url.href; a.target = '_blank'; a.rel = 'noopener noreferrer'; return a;
        } catch { return null; }
    };
    const showGoogle = (place) => {
        if (!place) return;
        googleDetails = place;
        const box = shell.querySelector('[data-assisted-google]'); box.replaceChildren();
        for (const [label, value] of [['Nome Google', place.name], ['Categoria Google', place.category], ['Endereço Google', place.address], ['Telefone Google', place.phone || place.international_phone], ['Website Google', place.website]]) box.append(text(label, value));
        if (place.rating !== null && place.rating !== undefined && place.rating_count !== null && place.rating_count !== undefined) box.append(text('Google Maps', `${Number(place.rating).toLocaleString('pt-BR')}/5 — ${place.rating_count} avaliações`));
        else box.append(text('Reputação Google', 'Não informada nesta consulta'));
        if (place.queried_at) box.append(text('Consultado em', new Date(place.queried_at).toLocaleString('pt-BR')));
        if (place.maps_url) { const maps = link('Abrir local e avaliações no Google Maps', place.maps_url, ['maps.google.com', 'google.com', 'www.google.com']); if (maps) box.append(maps); }
        if (place.opening_hours?.length) box.append(text('Horários Google', place.opening_hours.join(' · ')));
        for (const attribution of place.attributions ?? []) { const source = link(attribution.name, attribution.url); box.append(source || text('Fonte adicional', attribution.name)); }
    };
    const operate = async (button, operation) => {
        if (busy) return;
        busy = true; error.hidden = true;
        const buttons = [...form.querySelectorAll('[data-enrichment-start],button[type="submit"]'), shell.querySelector('[data-assisted-details]'), shell.querySelector('[data-google-fill]'), button].filter(Boolean);
        buttons.forEach(node => {node.disabled = true;}); shell.setAttribute('aria-busy', 'true');
        try { await operation(); } catch (exception) {error.textContent = exception.message; error.hidden = false; summary.textContent = 'Consulta não concluída. Seus dados foram preservados.';}
        finally {busy = false;buttons.forEach(node => {node.disabled = false;});shell.setAttribute('aria-busy', 'false');if(error.hidden)button.focus();else error.focus();}
    };
    shell.querySelector('[data-assisted-details]').addEventListener('click', event => operate(event.currentTarget, async () => {
        status.textContent = 'Consultando resumo Google…';
        const body = await explorerRequest(fetcher, shell.dataset.detailsUrl, { reference_id: shell.dataset.referenceId }, form.elements.namedItem('_token').value);
        showGoogle(body.place); status.textContent = 'Resumo atualizado. Nenhum campo comercial foi preenchido com conteúdo Google.';
    }));
    shell.querySelector('[data-google-fill]')?.addEventListener('click', event => operate(event.currentTarget, async () => {
        summary.textContent = 'Preparando preenchimento Google…';
        const body = await explorerRequest(fetcher, shell.dataset.googleFillUrl, {details_token:googleDetails?.import_token || null,enterprise:form.querySelector('[data-enrichment-enterprise]')?.checked || false,cost_acknowledged:true},form.elements.namedItem('_token').value);
        showGoogle(body.google);
        const result = applyAuthorizedEnrichment(form,body.fields);
        activeConflicts=[...result.conflicts];lastResult={filled:result.filled.length,available:Object.keys(body.fields).length,message:'A consulta não retornou campos válidos para preencher.',provider:'google_places'};
        const mark = fields => {
            fields.forEach(field=>googleApplied.add(field));
            form.elements.namedItem('google_applied_fields').value=JSON.stringify([...googleApplied]);
            form.elements.namedItem('google_enrichment_token').value=body.google_token;
            form.querySelector('[data-google-origin-review]').hidden=false;
            form.elements.namedItem('google_origin_confirmed').checked=false;
        };
        if(result.filled.length)mark(result.filled);
        conflictBox.replaceChildren();
        for(const conflict of result.conflicts){
            const card=document.createElement('div');card.className='rounded-lg border border-stone-300 p-3';
            card.append(text(labels[conflict.field],`Existente: ${conflict.current} · Google: ${conflict.value}`));
            for(const[label,replace]of [['Manter meu valor',false],['Usar valor encontrado',true]]){
                const control=document.createElement('button');control.type='button';control.className='button button-secondary';control.textContent=label;
                control.addEventListener('click',()=>{if(replace){const input=form.elements.namedItem(conflict.field);input.value=enrichmentInputValue(conflict.field,conflict.value);mark([conflict.field]);lastResult.filled++;input.dispatchEvent(new Event('input',{bubbles:true}));}activeConflicts=activeConflicts.filter(item=>item.field!==conflict.field);card.remove();refreshBadges();refreshSummary();});card.append(control);
            }conflictBox.append(card);
        }
        form.querySelector('[data-enrichment-origin]').textContent='Google Places · origem identificada; licença de persistência não comprovada.';
        status.textContent=result.filled.length?'Campos atualizados com origem Google. Confirme a revisão antes de salvar.':'Nenhum campo foi alterado automaticamente. Revise as divergências, se houver.';
        refreshBadges();refreshSummary();
    }));
    const enrich = async (button, companyToken = null) => operate(button, async () => {
        summary.textContent = 'Buscando dados autorizados…';
        const body = await explorerRequest(fetcher, shell.dataset.enrichUrl, { enterprise: form.querySelector('[data-enrichment-enterprise]')?.checked || false, cost_acknowledged: true, cnpj: companyToken ? null : form.querySelector('[data-enrichment-cnpj]')?.value.trim() || null, company_token: companyToken, ...Object.fromEntries(['business_name','city','state'].map(name => [name, form.elements.namedItem(name)?.value || ''])) }, form.elements.namedItem('_token').value);
        showGoogle(body.google);
        if (body.outcome === 'review' && body.company_token) {
            conflictBox.replaceChildren();
            const card = document.createElement('div'); card.className = 'rounded-lg border border-stone-300 p-3';
            card.append(text('Dados para revisar', body.message));
            for (const [field, value] of Object.entries(body.review_fields || {})) if (commercialFields.includes(field)) card.append(text(labels[field], value));
            card.append(text('Fonte', body.source));
            const use = document.createElement('button'); use.type = 'button'; use.className = 'button button-primary'; use.textContent = 'Confirmar empresa e preencher';
            use.addEventListener('click', () => enrich(use, body.company_token)); card.append(use); conflictBox.append(card);
            summary.textContent = 'Dados para revisar. Confira a identidade da empresa; nenhum campo foi preenchido ainda.';
            status.textContent = 'Consulta de dados públicos concluída. Se não corresponder ao estabelecimento, continue manualmente.';
            return;
        }
        const result = applyAuthorizedEnrichment(form, body.fields);
        activeConflicts = [...result.conflicts];
        lastResult = {filled:result.filled.length,available:Object.keys(body.fields).length,message:body.reason_code === 'source_not_configured' ? 'Informações não encontradas. Você pode informar o CNPJ para consultar dados públicos ou cadastrar este estabelecimento normalmente.' : `${body.message} ${(body.warnings || []).join(' ')}`};
        if (body.enrichment_token) form.elements.namedItem('enrichment_token').value = body.enrichment_token;
        form.querySelector('[data-enrichment-origin]').textContent = body.source ? `Fonte independente: ${body.source}` : 'Sem fonte independente disponível para este estabelecimento.';
        conflictBox.replaceChildren();
        if (body.existing_prospect_url) {
            const existing = link('Abrir ficha existente no CRM', body.existing_prospect_url, [window.location.hostname]);
            if (existing) conflictBox.append(existing);
        }
        for (const conflict of result.conflicts) {
            const card = document.createElement('div'); card.className = 'rounded-lg border border-stone-300 p-3';
            card.append(text(labels[conflict.field], `Existente: ${conflict.current} · Encontrado: ${conflict.value}`), text('Fonte', body.source));
            for (const [label, replace] of [['Manter meu valor', false], ['Usar valor encontrado', true]]) {
                const button = document.createElement('button');button.type='button';button.className='crm-context-link mr-4';button.textContent=label;
                button.addEventListener('click', () => {if(replace){const input=form.elements.namedItem(conflict.field);input.value=enrichmentInputValue(conflict.field,conflict.value);lastResult.filled++;input.dispatchEvent(new Event('input',{bubbles:true}));}activeConflicts=activeConflicts.filter(item=>item.field!==conflict.field);card.remove();refreshBadges();refreshSummary();});card.append(button);
            }
            conflictBox.append(card);
        }
        refreshBadges(result.conflicts);
        refreshSummary();
        const instagram = body.fields.instagram;
        const independent = shell.querySelector('[data-assisted-independent]');
        independent.replaceChildren(); independent.hidden = !Object.keys(body.fields).length;
        if (!independent.hidden) independent.append(text('Fonte independente autorizada', body.fields.business_name || 'Dados comerciais disponíveis'));
        if (instagram) independent.append(text('Instagram · website independente', instagram));
        status.textContent = Object.keys(body.fields).length ? 'Dados Google somente para consulta; preenchimento realizado com dados independentes autorizados.' : 'Nenhum dado foi aplicado. O cadastro manual permanece disponível e os valores anteriores foram preservados.';
    });
    form.querySelector('[data-enrichment-start]').addEventListener('click', event => enrich(event.currentTarget));
    form.querySelector('[data-enrichment-review]').addEventListener('click', () => {
        const field = commercialFields.map(name => form.elements.namedItem(name)).find(input => input?.required && !input.value.trim()) || form.elements.namedItem('business_name');
        field?.focus();field?.scrollIntoView({block:'center',behavior:window.matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth'});
    });
}
