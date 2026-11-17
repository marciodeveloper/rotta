import test from 'node:test';
import assert from 'node:assert/strict';
import {googleLink,mapsActions,renderIntelligence,installCommercialIntelligence,setIntelligenceContext} from '../../resources/js/commercial-intelligence.js';

class Element {
    constructor(){this.children=[];this.textContent='';this.dataset={};this.handlers={};this.checked=false;this.hidden=false;this.disabled=false;}
    append(...nodes){assert.ok(nodes.every(Boolean));this.children.push(...nodes);}
    replaceChildren(...nodes){this.children=nodes;}
    addEventListener(type,fn){this.handlers[type]=fn;}
    setAttribute(){}
    focus(){}
}
function fixture(){
    const controls=Object.fromEntries(['links','result','status','error','consent','csrf'].map(k=>[`[data-intelligence-${k}]`,new Element()]));
    controls['[data-intelligence-csrf]'].value='synthetic-csrf';
    const button=new Element();button.dataset.intelligenceQuery='summary';
    const panel=new Element();panel.dataset={referenceId:'synthetic-ref',intelligenceUrl:'/intelligence'};
    panel.querySelector=s=>controls[s];panel.querySelectorAll=()=>[button];
    return {controls,panel,button,root:{querySelectorAll:()=>[panel]}};
}
const dom=fn=>{const original=globalThis.document;globalThis.document={createElement:()=>new Element()};return Promise.resolve().then(fn).finally(()=>{globalThis.document=original;});};
const text=element=>[element.textContent,...element.children.map(text)].join('\n');

test('only official HTTPS Google links are accepted; credentials, suffix tricks and scripts are rejected',()=>{
    assert.ok(googleLink('https://www.google.com/maps/'));assert.ok(googleLink('https://maps.app.goo.gl/abc'));
    for(const url of ['javascript:alert(1)','http://google.com/','https://google.com.evil.invalid/','https://user:secret@google.com/'])assert.equal(googleLink(url),null);
});
test('missing review/photo deep links open the existing Maps listing without inventing unsupported URLs',()=>{
    const actions=mapsActions({maps_links:{place:'https://www.google.com/maps/?cid=1',directions:'https://www.google.com/maps/dir/?api=1'}});
    assert.equal(actions.length,4);assert.equal(actions[2][0],'Avaliações no Maps');assert.equal(actions[2][1],actions[0][1]);assert.equal(actions[3][1],actions[0][1]);
});
test('full summary and unchanged disclosure render as text with all required attribution actions',()=>dom(()=>{
    const box=new Element();const summary='<script>not executed</script> '+ 'Texto completo. '.repeat(40);
    renderIntelligence(box,{rating:4.9,rating_count:65,review_summary:{text:summary,disclosure:'Resumido com o Gemini',reviews_url:'https://google.com/maps/reviews',flag_url:'https://google.com/report'}});
    assert.ok(text(box).includes(summary));assert.match(text(box),/Resumido com o Gemini/);assert.match(text(box),/Review summary/);assert.match(text(box),/Sobre este resumo/);assert.match(text(box),/Denunciar resumo/);assert.match(text(box),/4,9\/5 — 65 avaliações/);
}));
test('unavailable summary and missing rating have neutral states and never invent zero ratings',()=>dom(()=>{
    const box=new Element();renderIntelligence(box,{rating:null,rating_count:null,summary_status:'unavailable'});
    assert.match(text(box),/Nota não disponível/);assert.match(text(box),/Resumo de avaliações indisponível/);assert.doesNotMatch(text(box),/0\/5/);
}));
test('consent, duplicate clicks and repeated installation cannot issue duplicate requests',()=>dom(async()=>{
    const f=fixture();let calls=0,resolve;const pending=new Promise(r=>resolve=r);const fetcher=async()=>{calls++;await pending;return {ok:true,json:async()=>({intelligence:{rating:4.9,rating_count:65,summary_status:'unavailable'}})};};
    installCommercialIntelligence(f.root,fetcher);installCommercialIntelligence(f.root,fetcher);
    await f.button.handlers.click();assert.equal(calls,0);assert.match(f.controls['[data-intelligence-error]'].textContent,/Confirme/);
    f.controls['[data-intelligence-consent]'].checked=true;const request=f.button.handlers.click();await f.button.handlers.click();assert.equal(calls,1);assert.equal(f.button.disabled,true);resolve();await request;assert.equal(f.button.disabled,false);assert.equal(f.controls['[data-intelligence-consent]'].checked,false);
}));
test('failed query preserves prior results and does not automatically retry',()=>dom(async()=>{
    const f=fixture();let calls=0;f.controls['[data-intelligence-consent]'].checked=true;const prior=new Element();prior.textContent='Reputação anterior';f.controls['[data-intelligence-result]'].append(prior);
    installCommercialIntelligence(f.root,async()=>{calls++;return {ok:false,status:429,json:async()=>({message:'Quota excedida'})};});await f.button.handlers.click();assert.equal(calls,1);assert.equal(f.controls['[data-intelligence-result]'].children[0],prior);assert.match(f.controls['[data-intelligence-error]'].textContent,/Quota excedida/);
}));
test('changing the selected establishment discards late results from the previous request',()=>dom(async()=>{
    const f=fixture();let resolve;const pending=new Promise(r=>resolve=r);f.controls['[data-intelligence-consent]'].checked=true;
    installCommercialIntelligence(f.root,async()=>{await pending;return{ok:true,json:async()=>({intelligence:{rating:1,rating_count:2}})};});const request=f.button.handlers.click();setIntelligenceContext(f.panel,{maps_links:{}},{token:'another-place'});resolve();await request;assert.equal(f.controls['[data-intelligence-result]'].children.length,0);
}));
