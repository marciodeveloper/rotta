@php
    $blockedCandidates=$candidates->filter(fn($candidate)=>$candidate->contact_blocked_at!==null);
    $previousVersions=old('blocked_match_versions',[]);
    $reviewStillCurrent=is_array($previousVersions);
    foreach($blockedCandidates as $candidate) {
        $reviewStillCurrent=$reviewStillCurrent && isset($previousVersions[$candidate->id]) && is_scalar($previousVersions[$candidate->id]) && (string)$previousVersions[$candidate->id]===(string)$candidate->lock_version;
    }
@endphp
@if($blockedCandidates->isNotEmpty())
    <div class="alert alert-error" id="blocked_match_versions" tabindex="-1">
        <h3>Revisão de correspondências com contatos bloqueados</h3>
        <p>Não prossiga se for o mesmo estabelecimento. Para outra unidade ou negócio que compartilha informações, abra as fichas e justifique explicitamente a distinção. Nenhum contato existente será desbloqueado.</p>
        @can('reviewBlockedMatch',\App\Models\Prospect::class)
            @foreach($blockedCandidates as $candidate)<input type="hidden" name="blocked_match_versions[{{ $candidate->id }}]" value="{{ $candidate->lock_version }}">@endforeach
            <label class="prospect-check"><input type="checkbox" id="blocked_match_acknowledged" name="blocked_match_acknowledged" value="1" @checked($reviewStillCurrent && in_array(old('blocked_match_acknowledged'),[true,1,'1'],true)) aria-invalid="{{ $errors->has('blocked_match_acknowledged') ? 'true' : 'false' }}" aria-describedby="blocked-match-error"> Revisei as fichas indicadas e declaro que este é um estabelecimento distinto.</label>
            <p id="blocked-match-error" class="field-error">{{ $errors->first('blocked_match_acknowledged') }}</p>
            <x-prospect-field name="blocked_match_reason" label="Justificativa da distinção em relação aos contatos bloqueados" type="textarea" :maxlength="500" />
        @endcan
    </div>
@endif
