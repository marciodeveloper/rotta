import test from 'node:test';
import assert from 'node:assert/strict';
import { explorerRequest } from '../../resources/js/business-explorer.js';

test('search, details and reference actions use explicit POST Fetch with CSRF and no browser cache', async () => {
    const calls = [];
    const fake = async (url, options) => { calls.push({ url, options }); return { ok: true, redirected: false, json: async () => ({ places: [] }) }; };
    assert.deepEqual(await explorerRequest(fake, '/admin/prospeccao/explorador/pesquisar', { query: 'Cafeterias em Vassouras, RJ' }, 'synthetic-csrf'), { places: [] });
    assert.equal(calls.length, 1);
    assert.equal(calls[0].options.method, 'POST');
    assert.equal(calls[0].options.cache, 'no-store');
    assert.equal(calls[0].options.credentials, 'same-origin');
    assert.equal(calls[0].options.headers['X-CSRF-TOKEN'], 'synthetic-csrf');
    assert.equal(calls[0].options.headers['X-Requested-With'], 'XMLHttpRequest');
    assert.deepEqual(JSON.parse(calls[0].options.body), { query: 'Cafeterias em Vassouras, RJ' });
});

test('expired session redirects are never interpreted as search results', async () => {
    await assert.rejects(explorerRequest(async () => ({ redirected: true }), '/details', { token: 'expired' }, 'csrf'), /sessão expirou/);
});

test('provider failures and validation errors produce Portuguese messages without automatic retries', async () => {
    let calls = 0;
    await assert.rejects(explorerRequest(async () => { calls++; return { ok: false, json: async () => ({ errors: { query: ['Informe a pesquisa.'] } }) }; }, '/search', {}, 'csrf'), /Informe a pesquisa/);
    assert.equal(calls, 1);
    await assert.rejects(explorerRequest(async () => ({ ok: false, json: async () => ({ message: 'A quota foi atingida.' }) }), '/search', {}, 'csrf'), /quota foi atingida/);
});

test('invalid JSON responses are handled without navigating or retaining provider data', async () => {
    await assert.rejects(explorerRequest(async () => ({ ok: true, json: async () => { throw new SyntaxError(); } }), '/search', {}, 'csrf'), /interpretar a resposta/);
});

test('network and timeout failures return actionable messages and do not retry', async () => {
    await assert.rejects(explorerRequest(async () => { const error = new Error(); error.name = 'AbortError'; throw error; }, '/search', {}, 'csrf'), /demorou mais/);
    await assert.rejects(explorerRequest(async () => { throw new TypeError('internal network details'); }, '/search', {}, 'csrf'), /Verifique a conexão/);
});

test('Google history restores page 1 and page 2 without another provider request', async () => {
    const { ExplorerPageHistory }=await import('../../resources/js/business-explorer.js');
    const history=new ExplorerPageHistory();history.reset('Cafeterias em Vassouras RJ');
    const first={page:1,max_pages:2,places:Array.from({length:5},(_,i)=>({place_id:'first_'+i})),next_cursor:'cursor-only-query-one'};
    const second={page:2,max_pages:2,places:[{place_id:'second'}],next_cursor:null};
    history.commit(first);assert.equal(history.canPrevious(),false);assert.equal(history.canNext(),true);
    history.commit(second);assert.equal(history.canNext(),false);assert.equal(history.previous(),first);assert.equal(history.canNext(),true);assert.equal(history.forward(),second);assert.equal(history.query,'Cafeterias em Vassouras RJ');assert.equal(history.pages.length,2);
});
test('history reset removes results and cursors from other queries and handles empty or single-page searches', async () => {
    const { ExplorerPageHistory }=await import('../../resources/js/business-explorer.js');
    const history=new ExplorerPageHistory();history.reset('one');history.commit({places:[],page:1,max_pages:2,next_cursor:null});assert.equal(history.canNext(),false);assert.equal(history.canPrevious(),false);
    history.reset('two');assert.equal(history.current(),null);assert.deepEqual(history.pages,[]);assert.equal(history.forward(),null);assert.equal(history.query,'two');
});
test('saved-reference metadata is kept across every cached page and can refresh linked state', async () => {
    const { ExplorerPageHistory }=await import('../../resources/js/business-explorer.js');
    const history=new ExplorerPageHistory();history.commit({places:[{place_id:'a'},{place_id:'b'}],page:1,next_cursor:'x',max_pages:2});history.commit({places:[{place_id:'a'}],page:2,next_cursor:null,max_pages:2});
    history.updateReference('a',{id:'reference'}, {name:'Own CRM name',blocked:true,url:'/own'});
    assert.equal(history.current().places[0].linked_prospect.blocked,true);history.previous();assert.equal(history.current().places[0].reference.id,'reference');assert.equal(history.current().places[1].reference,undefined);
});

test('saved reference identification uses allowed persisted name first, then exact temporary Place ID', async () => {
    const { TransientPlaceNames } = await import('../../resources/js/business-explorer.js');
    const names = new TransientPlaceNames();
    names.remember({ place_id:'id-one',name:'Padaria semelhante' });
    names.remember({ place_id:'id-two',name:'Padaria semelhante — filial' });
    assert.equal(names.resolve('id-one'),'Padaria semelhante');
    assert.equal(names.resolve('id-two'),'Padaria semelhante — filial');
    assert.equal(names.resolve('id-one','Nome legítimo do CRM'),'Nome legítimo do CRM');
    names.remember({ place_id:'id-one',name:'' });
    assert.equal(names.resolve('id-one'),'Padaria semelhante');
    assert.equal(names.resolve('unknown'),null);
    names.clear();assert.equal(names.resolve('id-one'),null);
});

test('response-body timeout remains bounded and does not trigger another request', async () => {
    let calls=0;
    await assert.rejects(explorerRequest(async()=>{calls++;return {ok:true,json:async()=>{throw Object.assign(new Error(),{name:'AbortError'});}};},'/search',{query:'texto preservado'},'csrf'),/demorou mais/);
    assert.equal(calls,1);
});

test('diagnosed failures expose only a safe correlation identifier and preserve one explicit attempt', async () => {
    let calls=0;
    await assert.rejects(explorerRequest(async()=>{calls++;return {ok:false,json:async()=>({message:'A consulta excedeu o prazo.',correlation_id:'11111111-2222-4333-8444-555555555555'})};},'/search',{query:'texto preservado'},'csrf'),error=>error.correlationId==='11111111-2222-4333-8444-555555555555');
    assert.equal(calls,1);
});
