<aside class="crm-card mb-6" data-assisted-reference data-reference-id="{{ $googleReference->id }}" data-details-url="{{ route('admin.explorer.details') }}" data-google-fill-url="{{ route('admin.explorer.references.google-draft', $googleReference) }}" data-enrich-url="{{ route('admin.explorer.references.enrich', $googleReference) }}">
    <div class="flex flex-wrap items-center justify-between gap-3"><h2>Resumo do estabelecimento</h2><span class="crm-muted text-sm">Google Maps · origem dos dados</span></div>
    <p class="crm-muted">A consulta Google é explícita, consome quota e pode gerar cobrança. O preenchimento experimental mantém a origem Google e não representa autorização contratual de armazenamento.</p>
    <div class="grid gap-2 md:grid-cols-2 mt-3" data-assisted-google><p>Nenhuma consulta realizada nesta tela.</p></div>
    <div class="flex flex-wrap items-center gap-4 mt-4"><button type="button" class="button button-secondary" data-assisted-details>Consultar resumo Google</button>@if(config('business_explorer.google_import_enabled'))<button type="button" class="button button-primary" data-google-fill>Preencher com dados do Google</button>@endif<x-google-maps-attribution /></div>
    <div class="border-t border-stone-300 mt-4 pt-4" data-assisted-independent hidden></div>
    <p role="status" aria-live="polite" data-assisted-status></p>
    <div role="alert" tabindex="-1" class="field-error" data-assisted-error hidden></div>
</aside>
