import { createTable, getCoreRowModel } from '@tanstack/table-core';

import { explorerRequest } from './business-explorer-request.js';
export { explorerRequest } from './business-explorer-request.js';
import { setIntelligenceContext, renderMapsActions } from './commercial-intelligence.js';

export class ExplorerPageHistory {
    constructor() { this.reset(''); }
    reset(query) { this.query=query;this.pages=[];this.index=-1; }
    current() { return this.pages[this.index] ?? null; }
    commit(body) { this.pages.push(body);this.index=this.pages.length-1; }
    canPrevious() { return this.index>0; }
    canNext() { return this.index+1<this.pages.length || Boolean(this.current()?.next_cursor && this.pages.length<Math.min(2,this.current()?.max_pages ?? 2)); }
    previous() { if(this.canPrevious()) this.index--;return this.current(); }
    forward() { if(this.index+1<this.pages.length){this.index++;return this.current();}return null; }
    updateReference(placeId, reference, linked = null) { for(const page of this.pages) for(const place of page.places) if(place.place_id===placeId){place.reference=reference;place.linked_prospect=linked;} }
}

export class TransientPlaceNames {
    constructor() {this.names=new Map();}
    remember(place) {if(typeof place?.place_id==='string'&&typeof place.name==='string'&&place.name.trim()){this.names.set(place.place_id,place.name);}}
    resolve(placeId, persistedName = null) {return persistedName || this.names.get(placeId) || null;}
    clear() {this.names.clear();}
}

export function installBusinessExplorer(root = document, fetcher = window.fetch.bind(window)) {
    const shell = root.querySelector('[data-business-explorer]');
    if (!shell) return;
    const form = shell.querySelector('[data-explorer-search]');
    const query = form.elements.namedItem('query');
    const csrf = form.elements.namedItem('_token').value;
    const status = shell.querySelector('[data-explorer-status]');
    const error = shell.querySelector('[data-explorer-error]');
    const results = shell.querySelector('[data-explorer-results]');
    const rows = shell.querySelector('[data-explorer-rows]');
    const next = shell.querySelector('[data-explorer-next]');
    const previous = shell.querySelector('[data-explorer-previous]');
    const history = new ExplorerPageHistory();
    const visualNames = new TransientPlaceNames();
    const applyVisualNames = () => {shell.querySelectorAll('[data-saved-place-id]').forEach(row=>{if(['crm','independent'].includes(row.dataset.nameOrigin))return;const name=visualNames.resolve(row.dataset.savedPlaceId);if(!name)return;row.querySelector('[data-reference-name]').textContent=name;row.querySelector('[data-reference-name-source]').textContent='Google Maps · nome temporário desta consulta';row.dataset.nameOrigin='google_temporary';row.querySelector('[data-copy-place-id]')?.setAttribute('aria-label',`Copiar Place ID de ${name}`);});};
    const pageLabel = shell.querySelector('[data-explorer-page]');
    const dialog = shell.querySelector('[data-explorer-dialog]');
    const detail = shell.querySelector('[data-explorer-detail]');
    let places = [];
    let cursor = null;
    let busy = false;
    let invoker;

    const text = (tag, value, className = '') => {
        const node = document.createElement(tag); node.textContent = value; node.className = className; return node;
    };
    const link = (label, url, external = false, attribution = false) => {
        const node = text('a', label, attribution ? 'crm-context-link' : (external ? 'button button-secondary' : 'button button-primary'));
        const parsed = new URL(url, window.location.href);
        if (external ? !(attribution ? ['http:', 'https:'] : ['https:']).includes(parsed.protocol) : parsed.origin !== window.location.origin) throw new Error('O endereço retornado é inválido.');
        if (!external) for(const name of ['page','per_page','q','status']) {const value=new URL(window.location.href).searchParams.get(name);if(value)parsed.searchParams.set('explorer_'+name,value);}
        node.href = parsed.href;
        if (external) { node.target = '_blank'; node.rel = 'noopener noreferrer'; }
        return node;
    };
    const button = (label, action, place) => {
        const node = text('button', label, label.startsWith('Salvar') ? 'button button-primary' : 'button button-secondary'); node.type = 'button';
        node.addEventListener('click', () => action(place, node)); return node;
    };
    const setBusy = (value) => {
        busy = value; shell.setAttribute('aria-busy', String(value));
        query.disabled = value;
        shell.querySelectorAll('button:not([data-explorer-close])').forEach((node) => { node.disabled = value; });
        form.querySelector('button[type="submit"]').disabled = value || shell.dataset.explorerConfigured !== 'true';
        const sameQuery=query.value.trim()===history.query;previous.disabled=value || !sameQuery || !history.canPrevious();next.disabled=value || !sameQuery || !history.canNext();
    };
    const operate = async (operation) => {
        if (busy) return;
        setBusy(true); error.hidden = true; query.setAttribute('aria-invalid', 'false');
        try { await operation(); } catch (exception) {
            if (exception.fields?.includes('query')) query.setAttribute('aria-invalid', 'true');
            error.textContent = (exception.message || 'Não foi possível conectar ao servidor. Tente novamente.')+(exception.correlationId?` Referência: ${exception.correlationId}`:'');
            error.hidden = false; error.focus(); status.textContent = 'A operação não foi concluída; nenhuma nova consulta será feita automaticamente.';
        } finally { setBusy(false); }
    };
    const attributions = (place) => {
        const box = document.createElement('div'); box.className = 'text-sm mt-2';
        (place.attributions ?? []).forEach((source) => {
            const item = text('p', 'Fonte adicional: ');
            item.append(source.url ? link(source.name, source.url, true, true) : text('span', source.name)); box.append(item);
        });
        return box;
    };
    const crm = (place) => {
        const box = document.createElement('div'); box.className = 'flex flex-col gap-2';
        if (place.linked_prospect) {
            box.append(text('strong', 'Vinculado ao CRM', 'explorer-badge'));
            if (place.linked_prospect.blocked) box.append(text('strong', 'Contato bloqueado', 'field-error'));
        } else if (place.reference) box.append(text('span', 'Referência salva; revisão pendente'));
        else box.append(text('span', 'Sem vínculo por Place ID'));
        (place.possible_duplicates ?? []).forEach((match) => {
            const candidate=link(match.name,match.url);candidate.className='crm-context-link';
            box.append(text('span', 'Possível semelhança de nome:'), candidate);
            if (match.blocked) box.append(text('strong', 'Contato bloqueado', 'field-error'));
        });
        return box;
    };
    const save = (place) => operate(async () => {
        visualNames.remember(place);
        status.textContent = 'Salvando somente o Place ID…';
        const body = await explorerRequest(fetcher, shell.dataset.saveUrl, { token: place.token }, csrf);
        history.updateReference(place.place_id, body.reference);
        place.reference = body.reference;
        if (body.linked_url) { window.location.assign(body.linked_url); return; }
        renderRows();
        status.textContent = 'Referência salva. O nome Google permanece temporário nesta tela; nenhum prospect foi criado.';
        rows.querySelector(`[data-place-id="${place.place_id}"] [data-start-prospect]`)?.focus();
        await refreshSaved(window.location.href);
    });
    const showDetails = (payload, source) => operate(async () => {
        status.textContent = 'Consultando detalhes atualizados no Google…';
        const { place } = await explorerRequest(fetcher, shell.dataset.detailsUrl, payload, csrf);
        visualNames.remember(place);applyVisualNames();
        detail.replaceChildren(text('h3', place.name || 'Nome não informado'), text('p', place.address || 'Endereço não informado'), text('p', place.category || 'Categoria não informada'), text('code', place.place_id, 'break-all'), attributions(place));
        setIntelligenceContext(dialog.querySelector('[data-commercial-intelligence]'), place, payload);
        detail.append(text('h3', 'CRM ROTTA · dados internos', 'mt-6'), crm(place));
        invoker = source; if (!dialog.open) dialog.showModal();
        status.textContent = 'Detalhes carregados. Os dados do Google permanecem temporários.';
    });
    const table = createTable({
        data: [], columns: ['name', 'address', 'category', 'place_id'].map((accessorKey) => ({ accessorKey })),
        getCoreRowModel: getCoreRowModel(), manualPagination: true, manualSorting: true,
        state: {}, onStateChange() {}, renderFallbackValue: '',
    });
    function renderRows() {
        places.forEach(place=>visualNames.remember(place));applyVisualNames();
        table.setOptions((options) => ({ ...options, data: [...places] })); rows.replaceChildren();
        table.getRowModel().rows.forEach(({ original: place }) => {
            const row = document.createElement('tr'); row.dataset.placeId = place.place_id;
            const name = document.createElement('td'); name.append(text('strong', place.name || 'Nome não informado'), text('p', place.place_id, 'crm-muted text-sm break-all'), attributions(place));
            const internal = document.createElement('td'); internal.append(crm(place));
            const actions = document.createElement('td'); actions.className = 'crm-actions-column';
            const controls = document.createElement('div'); controls.className = 'explorer-actions';
            controls.append(button('Ver detalhes', (item, source) => showDetails({ token: item.token }, source), place));
            const location = document.createElement('details');location.append(text('summary','Localização e fotos'));
            const mapControls = document.createElement('div');mapControls.className='flex flex-wrap gap-2';renderMapsActions(mapControls, place);location.append(mapControls);controls.append(location);
            if (place.linked_prospect) controls.append(link('Abrir ficha', place.linked_prospect.url));
            else if (place.reference) { const start = link('Iniciar cadastro', place.reference.create_url); start.dataset.startProspect = ''; controls.append(start); }
            else controls.append(button('Salvar referência para revisão', save, place));
            actions.append(controls);const cells=[name,text('td',place.address||'Não informado'),text('td',place.category||'Não informada'),internal,actions];cells.forEach((cell,index)=>cell.dataset.label=['Estabelecimento','Endereço','Categoria','Situação no CRM','Ações'][index]);row.append(...cells); rows.append(row);
        });
        if (!places.length) { const cell = text('td', 'Nenhum estabelecimento encontrado. Ajuste a pesquisa.'); cell.colSpan = 5; const row = document.createElement('tr'); row.append(cell); rows.append(row); }
    }
    function renderCurrent() {
        const body=history.current();if(!body)return;
        places=body.places;cursor=body.next_cursor;renderRows();results.hidden=false;
        pageLabel.textContent=`Página ${body.page} de até ${Math.min(2,body.max_pages ?? 2)}`;
        previous.disabled=busy||!history.canPrevious();next.disabled=busy||!history.canNext();
    }
    async function refreshSaved(url) {
        const target=new URL(url,window.location.href);if(target.origin!==window.location.origin)throw new Error('Destino inválido.');
        const response=await fetcher(target.href,{credentials:'same-origin',cache:'no-store',headers:{Accept:'text/html','X-Requested-With':'XMLHttpRequest'}});
        const html=await response.text();const parsed=new DOMParser().parseFromString(html,'text/html');const section=parsed.querySelector('[data-saved-references]');
        if(!response.ok||response.redirected||!section)throw new Error('Não foi possível atualizar as referências. Recarregue a página.');
        shell.querySelector('[data-saved-references]').replaceWith(section);applyVisualNames();
        window.history.replaceState(null,'',target.href);
    }
    const search = (payload) => operate(async () => {
        status.textContent = 'Pesquisando no Google Maps…';
        const body = await explorerRequest(fetcher, shell.dataset.searchUrl, payload, csrf);
        if ('query' in payload) history.reset(payload.query);
        history.commit(body);renderCurrent();
        status.textContent = `${places.length} resultado(s) nesta página. Nenhum prospect foi criado.`;
    });
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        if (shell.dataset.explorerConfigured === 'true') search({ query: query.value.trim() });
    });
    query.addEventListener('input',()=>{cursor=query.value.trim()===history.query?history.current()?.next_cursor??null:null;setBusy(busy);});
    previous.addEventListener('click',()=>{if(!busy&&query.value.trim()===history.query&&history.canPrevious()){history.previous();renderCurrent();status.textContent='Página anterior restaurada da memória, sem nova consulta Google.';}});
    next.addEventListener('click',()=>{if(busy||query.value.trim()!==history.query)return;if(history.index+1<history.pages.length){history.forward();renderCurrent();status.textContent='Página restaurada da memória, sem nova consulta Google.';}else if(history.canNext()&&cursor)search({cursor});});
    shell.addEventListener('submit',event=>{const filters=event.target.closest('[data-reference-filters]');if(!filters)return;event.preventDefault();const url=new URL(filters.action);new FormData(filters).forEach((value,key)=>{if(value)url.searchParams.set(key,value);});operate(()=>refreshSaved(url.href));});
    shell.addEventListener('click',event=>{
        const detailsButton=event.target.closest('[data-reference-details]');if(detailsButton){showDetails({reference_id:detailsButton.dataset.referenceDetails},detailsButton);return;}
        const pagination=event.target.closest('[data-saved-references] nav a');if(pagination){event.preventDefault();operate(()=>refreshSaved(pagination.href));return;}
        const copy=event.target.closest('[data-copy-place-id]');if(copy){operate(async()=>{try{await navigator.clipboard.writeText(copy.dataset.copyPlaceId);}catch{const input=document.createElement('textarea');input.value=copy.dataset.copyPlaceId;shell.append(input);input.select();const copied=document.execCommand('copy');input.remove();copy.focus();if(!copied)throw new Error('Não foi possível copiar. Selecione o identificador completo.');}status.textContent='Place ID copiado.';});}
    });
    window.addEventListener('pageshow',event=>{if(event.persisted&&history.current()){renderCurrent();const ids=[...new Set(history.pages.flatMap(page=>page.places.map(place=>place.place_id)))];operate(async()=>{const body=await explorerRequest(fetcher,shell.dataset.metadataUrl,{place_ids:ids},csrf);const refs=new Map(body.references.map(item=>[item.place_id,item]));for(const id of ids){const item=refs.get(id);history.updateReference(id,item?.reference??null,item?.linked_prospect??null);}renderCurrent();await refreshSaved(window.location.href);});}});
    shell.querySelector('[data-explorer-close]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => { detail.replaceChildren(); if (invoker?.isConnected) invoker.focus(); });
    window.addEventListener('pagehide', event => { if(event.persisted)return;visualNames.clear();history.reset('');places=[];cursor=null;rows.replaceChildren();detail.replaceChildren();results.hidden=true; });
    setBusy(false);
}
