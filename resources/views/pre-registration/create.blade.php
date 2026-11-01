@extends('layouts.app')

@section('title', 'Faça parte')

@push('head')
    @if ($turnstileSiteKey)
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    @endif
@endpush

@section('content')
<section class="prelaunch-shell">
    <aside class="prelaunch-story" aria-labelledby="page-title">
        <img class="story-photo" src="{{ asset('assets/photos/vassouras-igreja-praca.webp') }}" alt="Igreja histórica e praça de Vassouras">
        <div class="story-shade"></div>
        <div class="story-content">
            <div class="eyebrow"><span></span> Negócios · Profissionais · Território</div>
            <h1 id="page-title">Seu trabalho também conta a <em>história deste lugar.</em></h1>
            <p>Apresente seu negócio ou atuação profissional para a fase de pré-cadastro da ROTTA.</p>
            <div class="story-note"><strong>Importante</strong><span>O envio será analisado e não representa aprovação ou publicação automática.</span></div>
        </div>
    </aside>

    <div class="form-panel" data-pre-registration-root data-initial-step="{{ $errors->any() ? 1 : 1 }}">
        <div class="form-intro">
            <span class="form-kicker">Manifestação de interesse</span>
            <h2>Comece seu pré-cadastro</h2>
            <p>Leva cerca de 5 minutos. Seus dados serão usados exclusivamente para confirmação e avaliação.</p>
        </div>

        @if ($errors->any())
            <div class="alert alert-error" role="alert" tabindex="-1" data-error-summary>
                <strong>Revise os campos indicados.</strong>
                <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ route('pre-registration.store') }}" x-data="preRegistrationForm" x-on:submit="startSubmitting" novalidate>
            @csrf
            <input class="honeypot" type="text" name="company_website" tabindex="-1" autocomplete="off" aria-hidden="true">
            <input type="hidden" name="privacy_notice_version" value="{{ $privacyNotice->version }}">

            <ol class="progress" aria-label="Progresso do formulário">
                @foreach (['Identificação', 'Contato', 'Endereço', 'Revisão'] as $index => $label)
                    <li x-bind:class="stepClass({{ $index + 1 }})"><span>{{ $index + 1 }}</span><small>{{ $label }}</small></li>
                @endforeach
            </ol>

            <section class="form-step" x-show="isStep(1)" x-cloak aria-labelledby="step-1-title">
                <div class="step-heading"><span>01 / 04</span><h3 id="step-1-title">Identificação</h3></div>
                <fieldset class="choice-grid">
                    <legend>Como deseja se cadastrar?</legend>
                    <label><input type="radio" name="applicant_type" value="company" x-model="applicantType" {{ old('applicant_type', 'company') === 'company' ? 'checked' : '' }}><span><strong>Empresa</strong><small>CNPJ</small></span></label>
                    <label><input type="radio" name="applicant_type" value="professional" x-model="applicantType" {{ old('applicant_type') === 'professional' ? 'checked' : '' }}><span><strong>Profissional</strong><small>CPF</small></span></label>
                </fieldset>
                <div class="field-grid">
                    <label class="field"><span x-text="documentLabel">CNPJ</span><input name="document" value="{{ old('document') }}" inputmode="numeric" autocomplete="off" required aria-describedby="document-help @error('document') document-error @enderror"><small id="document-help">A validação matemática não comprova titularidade.</small>@error('document')<b id="document-error">{{ $message }}</b>@enderror</label>
                    <label class="field"><span>Nome comercial ou profissional</span><input name="business_name" value="{{ old('business_name') }}" maxlength="160" required>@error('business_name')<b>{{ $message }}</b>@enderror</label>
                    <label class="field"><span>Categoria</span><select name="category" required><option value="">Selecione</option>@foreach ($categories as $category)<option @selected(old('category') === $category)>{{ $category }}</option>@endforeach</select>@error('category')<b>{{ $message }}</b>@enderror</label>
                    <label class="field field-wide"><span>Descrição curta</span><textarea name="short_description" maxlength="500" rows="4" required>{{ old('short_description') }}</textarea><small>Entre 20 e 500 caracteres.</small>@error('short_description')<b>{{ $message }}</b>@enderror</label>
                </div>
                <div class="step-actions"><button class="button button-primary" type="button" x-on:click="next">Continuar <span>→</span></button></div>
            </section>

            <section class="form-step" x-show="isStep(2)" x-cloak aria-labelledby="step-2-title">
                <div class="step-heading"><span>02 / 04</span><h3 id="step-2-title">Contato</h3></div>
                <div class="field-grid">
                    <label class="field field-wide"><span>Nome do responsável</span><input name="responsible_name" value="{{ old('responsible_name') }}" maxlength="160" autocomplete="name" required>@error('responsible_name')<b>{{ $message }}</b>@enderror</label>
                    <label class="field"><span>E-mail</span><input type="email" name="email" value="{{ old('email') }}" maxlength="254" autocomplete="email" required>@error('email')<b>{{ $message }}</b>@enderror</label>
                    <label class="field"><span>Telefone / WhatsApp</span><input name="phone" value="{{ old('phone') }}" x-on:input="maskPhone" inputmode="tel" autocomplete="tel" placeholder="(24) 99999-9999" required>@error('phone')<b>{{ $message }}</b>@enderror</label>
                    <label class="field"><span>Website <i>opcional</i></span><input type="url" name="website" value="{{ old('website') }}" placeholder="https://" maxlength="500">@error('website')<b>{{ $message }}</b>@enderror</label>
                    <label class="field"><span>Instagram <i>opcional</i></span><input name="instagram" value="{{ old('instagram') }}" placeholder="@seuperfil" maxlength="31">@error('instagram')<b>{{ $message }}</b>@enderror</label>
                </div>
                <div class="step-actions"><button class="button button-secondary" type="button" x-on:click="previous">Voltar</button><button class="button button-primary" type="button" x-on:click="next">Continuar <span>→</span></button></div>
            </section>

            <section class="form-step" x-show="isStep(3)" x-cloak aria-labelledby="step-3-title">
                <div class="step-heading"><span>03 / 04</span><h3 id="step-3-title">Endereço profissional</h3></div>
                <fieldset class="choice-grid">
                    <legend>Como você atende?</legend>
                    <label><input type="radio" name="address_mode" value="public_address" x-model="addressMode" {{ old('address_mode', 'public_address') === 'public_address' ? 'checked' : '' }}><span><strong>Endereço público</strong><small>Loja, escritório ou espaço</small></span></label>
                    <label><input type="radio" name="address_mode" value="service_area" x-model="addressMode" {{ old('address_mode') === 'service_area' ? 'checked' : '' }}><span><strong>Área de atendimento</strong><small>Sem ponto físico público</small></span></label>
                </fieldset>
                <div x-show="hasPublicAddress" x-cloak>
                    @if ($googleMapsApiKey)
                        <div class="place-search"><span>Buscar endereço com Google</span><div data-google-place data-google-key="{{ $googleMapsApiKey }}"></div><small>Você também pode preencher manualmente abaixo.</small></div>
                    @endif
                    <div class="cep-row"><label class="field"><span>CEP</span><input name="postal_code" value="{{ old('postal_code') }}" inputmode="numeric" maxlength="9" data-cep-input x-bind:disabled="hasServiceArea"></label><button class="button button-secondary" type="button" data-cep-button>Buscar CEP</button><span data-cep-status aria-live="polite"></span></div>
                    <div class="field-grid">
                        <label class="field field-wide"><span>Logradouro</span><input name="street" value="{{ old('street') }}" maxlength="180" data-address-field="street" x-bind:disabled="hasServiceArea"></label>
                        <label class="field"><span>Número</span><input name="street_number" value="{{ old('street_number') }}" maxlength="30" x-bind:disabled="hasServiceArea"></label>
                        <label class="field"><span>Complemento <i>opcional</i></span><input name="complement" value="{{ old('complement') }}" maxlength="100" x-bind:disabled="hasServiceArea"></label>
                        <label class="field"><span>Bairro</span><input name="neighborhood" value="{{ old('neighborhood') }}" maxlength="120" data-address-field="neighborhood" x-bind:disabled="hasServiceArea"></label>
                        <label class="field"><span>Cidade</span><input name="city" value="{{ old('city') }}" maxlength="120" data-address-field="city" x-bind:disabled="hasServiceArea" required></label>
                        <label class="field"><span>UF</span><input name="state" value="{{ old('state') }}" maxlength="2" data-address-field="state" x-bind:disabled="hasServiceArea" required></label>
                    </div>
                    <input type="hidden" name="google_place_id" data-address-field="google_place_id"><input type="hidden" name="latitude" data-address-field="latitude"><input type="hidden" name="longitude" data-address-field="longitude">
                </div>
                <div x-show="hasServiceArea" x-cloak class="field-grid">
                    <label class="field"><span>Cidade-base</span><input name="city" value="{{ old('city') }}" maxlength="120" x-bind:disabled="hasPublicAddress" required></label>
                    <label class="field"><span>UF</span><input name="state" value="{{ old('state') }}" maxlength="2" x-bind:disabled="hasPublicAddress" required></label>
                    <label class="field field-wide"><span>Área de atendimento</span><textarea name="service_area" rows="3" maxlength="300" placeholder="Ex.: Vassouras, Miguel Pereira e região" x-bind:disabled="hasPublicAddress">{{ old('service_area') }}</textarea></label>
                </div>
                @foreach (['postal_code','street','street_number','neighborhood','city','state','service_area'] as $field) @error($field)<p class="field-error">{{ $message }}</p>@enderror @endforeach
                <div class="step-actions"><button class="button button-secondary" type="button" x-on:click="previous">Voltar</button><button class="button button-primary" type="button" x-on:click="next">Revisar <span>→</span></button></div>
            </section>

            <section class="form-step" x-show="isStep(4)" x-cloak aria-labelledby="step-4-title">
                <div class="step-heading"><span>04 / 04</span><h3 id="step-4-title">Privacidade e envio</h3></div>
                <div class="review-note"><strong>Antes de enviar</strong><p>Confirme os dados nas etapas anteriores. Após o envio, mandaremos um link de confirmação ao e-mail informado.</p></div>
                <article class="privacy-card"><span>Versão {{ $privacyNotice->version }} · {{ strtoupper($privacyNotice->status) }}</span><h4>{{ $privacyNotice->title }}</h4><p>{{ $privacyNotice->content }}</p><dl><div><dt>Finalidade</dt><dd>{{ $privacyNotice->purpose }}</dd></div><div><dt>Controlador</dt><dd>{{ $privacyNotice->controller_name }}</dd></div><div><dt>Contato</dt><dd>{{ $privacyNotice->contact_email }}</dd></div><div><dt>Base legal e retenção</dt><dd>Pendentes de aprovação jurídica.</dd></div></dl></article>
                <label class="check-field"><input type="checkbox" name="privacy_accepted" value="1" required><span>Li e aceito o aviso de privacidade apresentado.</span></label>
                <label class="check-field"><input type="checkbox" name="marketing_opt_in" value="1" {{ old('marketing_opt_in') ? 'checked' : '' }}><span>Quero receber novidades da ROTTA. <small>Opcional e desmarcado por padrão.</small></span></label>
                @error('privacy_accepted')<p class="field-error">{{ $message }}</p>@enderror
                @if ($turnstileSiteKey)
                    <div class="cf-turnstile" data-sitekey="{{ $turnstileSiteKey }}" data-theme="light"></div>
                @else
                    <div class="integration-pending" role="status">Turnstile ainda não configurado. O envio público permanece bloqueado até a credencial ser fornecida.</div>
                    <input type="hidden" name="cf-turnstile-response" value="">
                @endif
                @error('cf-turnstile-response')<p class="field-error">{{ $message }}</p>@enderror
                <div class="step-actions"><button class="button button-secondary" type="button" x-on:click="previous">Voltar</button><button class="button button-primary" type="submit" x-bind:disabled="submitting" @disabled(! $turnstileSiteKey)><span x-text="submitLabel">Enviar pré-cadastro</span></button></div>
            </section>
        </form>
    </div>
</section>
@endsection
