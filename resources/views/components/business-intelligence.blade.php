@props(['reference' => null])
<section class="crm-card my-4 min-w-0" data-commercial-intelligence data-intelligence-url="{{ route('admin.explorer.intelligence') }}" @if($reference) data-reference-id="{{ $reference->id }}" @endif>
    <h2>Inteligência Comercial</h2>
    <h3 class="mt-4">Localização e presença digital</h3>
    <div class="flex flex-wrap gap-3 my-3" data-intelligence-links>
        @if($reference)
            @php($mapsLinks = \App\Services\GoogleMapsLinks::forPlace($reference->place_id))
            <a class="button button-secondary" href="{{ $mapsLinks['place'] }}" target="_blank" rel="noopener noreferrer">Abrir Google Maps</a>
            <a class="button button-secondary" href="{{ $mapsLinks['directions'] }}" target="_blank" rel="noopener noreferrer">Obter rota para visita</a>
            <a class="button button-secondary" href="{{ $mapsLinks['place'] }}" target="_blank" rel="noopener noreferrer">Avaliações no Maps</a>
            <a class="button button-secondary" href="{{ $mapsLinks['place'] }}" target="_blank" rel="noopener noreferrer">Fotos no Maps</a>
        @endif
    </div>
    <p class="crm-muted text-sm">Sem um link específico já consultado, avaliações e fotos abrem a ficha do local no Maps; selecione a aba desejada.</p>
    <h3 class="mt-4">Reputação no Google</h3>
    <x-google-maps-attribution />
    <input type="hidden" data-intelligence-csrf value="{{ csrf_token() }}">
    @if(config('business_explorer.enterprise_enabled') && config('business_explorer.reputation_enabled'))
        <label class="prospect-check"><input type="checkbox" data-intelligence-consent> Autorizo esta consulta, com consumo de quota e possível cobrança Google.</label>
        <div class="flex flex-wrap gap-3 my-3">
            <button class="button button-secondary" type="button" data-intelligence-query="reputation">Consultar reputação · Enterprise</button>
            @if(config('business_explorer.review_summary_enabled'))
                <button class="button button-secondary" type="button" data-intelligence-query="summary">Consultar resumo com IA · Enterprise + Atmosphere</button>
            @else
                <p class="crm-muted text-sm">Resumo com IA desabilitado.</p>
            @endif
        </div>
    @else
        <p class="crm-muted">Consulta de reputação desabilitada. Os links Google Maps continuam disponíveis.</p>
    @endif
    <p data-intelligence-status role="status" aria-live="polite" class="crm-muted"></p>
    <p data-intelligence-error role="alert" tabindex="-1" class="field-error" hidden></p>
    <div data-intelligence-result tabindex="-1" class="min-w-0 break-words"></div>
    <p class="crm-muted text-sm mt-3">Conteúdo Google somente para consulta nesta tela. Não é salvo nas observações nem utilizado para gerar uma síntese própria.</p>
</section>
