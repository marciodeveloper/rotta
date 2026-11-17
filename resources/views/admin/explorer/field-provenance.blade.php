<details class="crm-card col-span-full" data-origin-details @if($errors->hasAny(['origin_reference','enrichment_token','token'])) open @endif>
    <summary class="font-semibold cursor-pointer">Origem dos dados</summary>
    <p class="crm-muted">O sistema registra automaticamente as fontes verificadas. Valores acrescentados ou alterados por você serão registrados como informação declarada manualmente, sem alegar confirmação externa.</p>
    <p data-enrichment-origin>Sem enriquecimento aplicado nesta tela.</p>
    <x-prospect-field name="origin_reference" label="Origem dos dados informados manualmente (opcional)" :maxlength="500" />
    <x-prospect-field name="usage_restrictions" label="Restrições conhecidas de uso (opcional)" type="textarea" :maxlength="1000" />
    <x-prospect-field name="collected_at" label="Data da coleta, se conhecida" type="date" />
    <x-prospect-field name="verified_at" label="Data da verificação, se realizada" type="date" />
    <p class="crm-muted">Place ID: <code class="break-all">{{ $googleReference->place_id }}</code>. Conferir o Google não autoriza copiar seu conteúdo. As origens e a autoria ficam disponíveis no histórico após salvar.</p>
    @if($errors->hasAny(['enrichment_token','token']))<p class="field-error">{{ $errors->first('enrichment_token') ?: $errors->first('token') }} Você pode consultar novamente ou manter os dados como declaração manual.</p><button type="button" class="button button-secondary" data-discard-enrichment>Continuar com dados declarados manualmente</button>@endif
</details>
