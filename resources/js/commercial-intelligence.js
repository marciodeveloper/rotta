import { explorerRequest } from './business-explorer-request.js';

const states = new WeakMap();
export function googleLink(url) {
    try {
        const u = new URL(url);
        return u.protocol === 'https:' && !u.username && !u.password && (u.hostname === 'google.com' || u.hostname.endsWith('.google.com') || u.hostname === 'maps.app.goo.gl') ? u.href : null;
    } catch { return null; }
}
export function mapsActions(place) {
    const links = place.maps_links ?? {};
    const map = googleLink(links.place || place.maps_url);
    return [['Abrir Google Maps', map], ['Obter rota para visita', googleLink(links.directions)],
        [links.reviews ? 'Abrir avaliações' : 'Avaliações no Maps', googleLink(links.reviews) || map],
        [links.photos ? 'Abrir fotos' : 'Fotos no Maps', googleLink(links.photos) || map]].filter(([, url]) => url);
}
const node = (tag, value, className = '') => { const n = document.createElement(tag); n.textContent = value; n.className = className; return n; };
const external = (label, url, googleOnly = true) => {
    const safe = googleOnly ? googleLink(url) : (()=>{try{const u=new URL(url);return ['https:','http:'].includes(u.protocol)&&!u.username&&!u.password?u.href:null;}catch{return null;}})();
    if (!safe) return null;
    const a = node('a', label, 'button button-secondary'); a.href = safe; a.target = '_blank'; a.rel = 'noopener noreferrer'; return a;
};
export function renderMapsActions(container, place) {
    container.replaceChildren();
    for (const [label, url] of mapsActions(place)) container.append(external(label, url));
}
export function renderIntelligence(container, data) {
    container.replaceChildren();
    const rating = typeof data.rating === 'number' && data.rating >= 0 && data.rating <= 5 ? `${data.rating.toLocaleString('pt-BR')}/5` : 'Nota não disponível';
    const count = Number.isInteger(data.rating_count) && data.rating_count >= 0 ? `${data.rating_count.toLocaleString('pt-BR')} avaliações` : 'Quantidade não disponível';
    container.append(node('p', `Google Maps: ${rating} — ${count}`, 'font-semibold mt-3'));
    if (data.queried_at && !Number.isNaN(Date.parse(data.queried_at))) container.append(node('p', `Consultado em ${new Date(data.queried_at).toLocaleString('pt-BR')}`, 'crm-muted text-sm'));
    const review = data.review_summary;
    if (review && typeof review.text === 'string' && typeof review.disclosure === 'string' && googleLink(review.reviews_url) && googleLink(review.flag_url)) {
        container.append(node('h3', 'Review summary', 'mt-4'), node('p', review.text, 'crm-pre-wrap'), node('p', review.disclosure, 'crm-muted text-sm'));
        const actions = node('div', '', 'flex flex-wrap gap-3 my-3');
        actions.append(external('Ver avaliações', review.reviews_url), external('Denunciar resumo', review.flag_url), external('Sobre este resumo', 'https://support.google.com/local-listings/answer/9851099'));
        container.append(actions, node('p', 'Para solicitar a remoção de conteúdo por motivos legais, utilize o link de denúncia.', 'crm-muted text-sm'));
    } else if (data.summary_status !== 'not_requested') {
        container.append(node('p', 'Resumo de avaliações indisponível para este local. Você pode consultar as avaliações originais no Google Maps.'));
    }
    for (const source of data.attributions ?? []) {
        const attribution = node('p', `Fonte adicional: ${source.name ?? ''}`, 'text-sm');
        const link = source.url ? external('Consultar fonte', source.url, false) : null;
        if (link) attribution.append(link);
        container.append(attribution);
    }
}
export function setIntelligenceContext(panel, place, payload) {
    if (!panel) return;
    const state = states.get(panel);
    if (state) { state.version++; state.payload = payload; }
    panel.dataset.referenceId = payload.reference_id || '';
    panel.dataset.placeToken = payload.token || '';
    renderMapsActions(panel.querySelector('[data-intelligence-links]'), place);
    panel.querySelector('[data-intelligence-result]').replaceChildren();
    panel.querySelector('[data-intelligence-status]').textContent = '';
    panel.querySelector('[data-intelligence-error]').hidden = true;
    const consent = panel.querySelector('[data-intelligence-consent]'); if (consent) consent.checked = false;
}
export function installCommercialIntelligence(root = document, fetcher = window.fetch.bind(window)) {
    root.querySelectorAll('[data-commercial-intelligence]').forEach(panel => {
        if (states.has(panel)) return;
        const state = { busy:false, version:0, payload:null }; states.set(panel,state);
        const error = panel.querySelector('[data-intelligence-error]'), status = panel.querySelector('[data-intelligence-status]');
        const buttons = [...panel.querySelectorAll('[data-intelligence-query]')];
        for (const button of buttons) button.addEventListener('click', async () => {
            if (state.busy) return;
            error.hidden = true;
            if (!panel.querySelector('[data-intelligence-consent]')?.checked) { error.textContent = 'Confirme o possível consumo de quota e cobrança antes de consultar.'; error.hidden=false;error.focus();return; }
            const payload = state.payload || (panel.dataset.referenceId ? {reference_id:panel.dataset.referenceId} : {token:panel.dataset.placeToken});
            const version = state.version; state.busy=true;buttons.forEach(b=>b.disabled=true);panel.setAttribute('aria-busy','true');status.textContent='Consultando Google Maps…';
            try {
                const {intelligence} = await explorerRequest(fetcher,panel.dataset.intelligenceUrl,{...payload,mode:button.dataset.intelligenceQuery,cost_acknowledged:true},panel.querySelector('[data-intelligence-csrf]').value);
                if (state.version !== version) return;
                renderIntelligence(panel.querySelector('[data-intelligence-result]'),intelligence);
                renderMapsActions(panel.querySelector('[data-intelligence-links]'),intelligence);
                status.textContent='Consulta concluída. Conteúdo temporário do Google Maps.';
                panel.querySelector('[data-intelligence-result]').focus();
                panel.querySelector('[data-intelligence-consent]').checked=false;
            } catch (e) {
                if (state.version !== version) return;
                error.textContent=e.message || 'Não foi possível concluir a consulta. Tente novamente.';error.hidden=false;error.focus();status.textContent='Os dados anteriores foram preservados. Nenhuma nova tentativa será feita automaticamente.';
            } finally { state.busy=false;buttons.forEach(b=>b.disabled=false);panel.setAttribute('aria-busy','false'); }
        });
    });
}
