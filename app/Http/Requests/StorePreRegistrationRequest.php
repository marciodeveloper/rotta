<?php

namespace App\Http\Requests;

use App\Rules\ValidBrazilianDocument;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\Attributes\RedirectToRoute;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

#[RedirectToRoute('pre-registration.create')]
class StorePreRegistrationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'applicant_type' => ['required', Rule::in(['company', 'professional'])],
            'document' => ['required', 'string', new ValidBrazilianDocument($this->string('applicant_type')->toString())],
            'business_name' => ['required', 'string', 'min:2', 'max:160'],
            'category' => ['required', Rule::in(config('prelaunch.categories'))],
            'short_description' => ['required', 'string', 'min:20', 'max:500'],
            'responsible_name' => ['required', 'string', 'min:2', 'max:160'],
            'email' => ['required', 'email:strict', 'max:254'],
            'phone' => ['required', 'string', 'regex:/^\(?\d{2}\)?\s?9?\d{4}-?\d{4}$/'],
            'website' => ['nullable', 'url:http,https', 'max:500'],
            'instagram' => ['nullable', 'string', 'regex:/^@?[A-Za-z0-9._]{1,30}$/'],
            'address_mode' => ['required', Rule::in(['public_address', 'service_area'])],
            'postal_code' => ['nullable', 'required_if:address_mode,public_address', 'regex:/^\d{5}-?\d{3}$/'],
            'street' => ['nullable', 'required_if:address_mode,public_address', 'string', 'max:180'],
            'street_number' => ['nullable', 'required_if:address_mode,public_address', 'string', 'max:30'],
            'complement' => ['nullable', 'string', 'max:100'],
            'neighborhood' => ['nullable', 'required_if:address_mode,public_address', 'string', 'max:120'],
            'city' => ['required', 'string', 'max:120'],
            'state' => ['required', 'string', 'size:2', 'regex:/^[A-Za-z]{2}$/'],
            'service_area' => ['nullable', 'required_if:address_mode,service_area', 'string', 'max:300'],
            'google_place_id' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'marketing_opt_in' => ['nullable', 'boolean'],
            'privacy_accepted' => ['accepted'],
            'privacy_notice_version' => ['required', 'string', 'max:50'],
            'cf-turnstile-response' => ['required', 'string', 'max:2048'],
            'company_website' => ['nullable', 'string', 'max:200'],
            'logo' => ['nullable', File::types(['jpg', 'jpeg', 'png', 'webp'])->max('5mb')],
            'logo_alt' => ['nullable', 'required_with:logo', 'string', 'max:240'],
            'cover' => ['nullable', File::types(['jpg', 'jpeg', 'png', 'webp'])->max('5mb')],
            'cover_alt' => ['nullable', 'required_with:cover', 'string', 'max:240'],
            'gallery' => ['nullable', 'array', 'max:6'],
            'gallery.*' => [File::types(['jpg', 'jpeg', 'png', 'webp'])->max('5mb')],
            'gallery_alt' => ['nullable', 'array', 'max:6'],
            'gallery_alt.*' => ['required', 'string', 'max:240'],
            'media_rights_confirmed' => ['sometimes', 'accepted'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'applicant_type.required' => 'Selecione se o cadastro é de empresa ou profissional.',
            'applicant_type.in' => 'Selecione um tipo de cadastro válido.',
            'document.required' => 'Informe o CPF ou CNPJ.',
            'business_name.required' => 'Informe o nome comercial ou profissional.',
            'business_name.min' => 'O nome comercial ou profissional deve ter pelo menos :min caracteres.',
            'category.required' => 'Selecione uma categoria.',
            'category.in' => 'Selecione uma categoria válida.',
            'short_description.required' => 'Escreva uma descrição curta do negócio ou atuação.',
            'short_description.min' => 'A descrição curta deve ter pelo menos :min caracteres.',
            'responsible_name.required' => 'Informe o nome do responsável.',
            'responsible_name.min' => 'O nome do responsável deve ter pelo menos :min caracteres.',
            'email.required' => 'Informe o e-mail de contato.',
            'email.email' => 'Informe um endereço de e-mail válido.',
            'phone.required' => 'Informe o telefone ou WhatsApp.',
            'phone.regex' => 'Informe um telefone brasileiro válido, com DDD.',
            'website.url' => 'Informe uma URL completa iniciada por http:// ou https://.',
            'instagram.regex' => 'Informe um perfil do Instagram válido, com até 30 caracteres.',
            'address_mode.required' => 'Selecione como você atende.',
            'address_mode.in' => 'Selecione uma modalidade de atendimento válida.',
            'postal_code.required_if' => 'Informe o CEP do endereço público.',
            'postal_code.regex' => 'Informe um CEP válido com oito dígitos.',
            'street.required_if' => 'Informe o logradouro do endereço público.',
            'street_number.required_if' => 'Informe o número do endereço público.',
            'neighborhood.required_if' => 'Informe o bairro do endereço público.',
            'city.required' => 'Informe a cidade.',
            'state.required' => 'Informe a UF.',
            'state.size' => 'A UF deve ter exatamente :size letras.',
            'state.regex' => 'Informe uma UF válida com duas letras.',
            'service_area.required_if' => 'Descreva a área de atendimento.',
            'latitude.between' => 'A latitude informada é inválida.',
            'longitude.between' => 'A longitude informada é inválida.',
            'privacy_accepted.accepted' => 'Você precisa aceitar o aviso de privacidade para enviar.',
            'privacy_notice_version.required' => 'Não foi possível identificar a versão do aviso de privacidade. Recarregue a página.',
            'cf-turnstile-response.required' => 'Conclua a verificação de segurança.',
            'logo.required_with' => 'Selecione uma logo válida.',
            'logo_alt.required_with' => 'Informe a descrição alternativa da logo.',
            'cover_alt.required_with' => 'Informe a descrição alternativa da foto de capa.',
            'logo.mimes' => 'A logo deve ser uma imagem JPEG, PNG ou WebP.',
            'cover.mimes' => 'A foto de capa deve ser uma imagem JPEG, PNG ou WebP.',
            'gallery.*.mimes' => 'Cada fotografia deve ser uma imagem JPEG, PNG ou WebP.',
            'logo.max' => 'A logo deve ter no máximo 5 MB.',
            'cover.max' => 'A foto de capa deve ter no máximo 5 MB.',
            'gallery.*.max' => 'Cada fotografia deve ter no máximo 5 MB.',
            'media_rights_confirmed.accepted' => 'Confirme que possui autorização para usar as imagens enviadas.',
            'gallery.array' => 'As fotografias enviadas são inválidas.',
            'gallery.max' => 'Envie no máximo seis fotografias.',
            'gallery_alt.array' => 'As descrições alternativas das fotografias são inválidas.',
            'gallery_alt.max' => 'Informe no máximo seis descrições alternativas.',
            'gallery_alt.*.required' => 'Informe uma descrição alternativa para cada fotografia.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'applicant_type' => 'tipo de cadastro',
            'document' => 'CPF ou CNPJ',
            'business_name' => 'nome comercial ou profissional',
            'category' => 'categoria',
            'short_description' => 'descrição curta',
            'responsible_name' => 'nome do responsável',
            'email' => 'e-mail',
            'phone' => 'telefone ou WhatsApp',
            'website' => 'website',
            'instagram' => 'Instagram',
            'address_mode' => 'modalidade de atendimento',
            'postal_code' => 'CEP',
            'street' => 'logradouro',
            'street_number' => 'número',
            'complement' => 'complemento',
            'neighborhood' => 'bairro',
            'city' => 'cidade',
            'state' => 'UF',
            'service_area' => 'área de atendimento',
            'google_place_id' => 'identificador do endereço',
            'latitude' => 'latitude',
            'longitude' => 'longitude',
            'marketing_opt_in' => 'preferência de marketing',
            'privacy_accepted' => 'aceite do aviso de privacidade',
            'privacy_notice_version' => 'versão do aviso de privacidade',
            'cf-turnstile-response' => 'verificação de segurança',
            'company_website' => 'website de confirmação',
            'logo' => 'logo',
            'logo_alt' => 'descrição alternativa da logo',
            'cover' => 'foto de capa',
            'cover_alt' => 'descrição alternativa da foto de capa',
            'gallery' => 'fotografias',
            'gallery.*' => 'fotografia',
            'gallery_alt' => 'descrições alternativas das fotografias',
            'gallery_alt.*' => 'descrição alternativa da fotografia',
            'media_rights_confirmed' => 'autorização de uso das imagens',
        ];
    }

    /** @return array<callable> */
    public function after(): array
    {
        return [function ($validator): void {
            $gallery = $this->file('gallery');
            $hasMedia = $this->hasFile('logo') || $this->hasFile('cover') || (is_array($gallery) && $gallery !== []);
            if ($hasMedia && ! $this->boolean('media_rights_confirmed')) {
                $validator->errors()->add('media_rights_confirmed', 'Confirme que possui autorização para usar as imagens enviadas.');
            }
            foreach (is_array($gallery) ? $gallery : [] as $position => $file) {
                $alt = $this->input("gallery_alt.{$position}");
                if (! is_string($alt) || trim($alt) === '') {
                    $validator->errors()->add("gallery_alt.{$position}", 'Informe uma descrição alternativa para cada fotografia.');
                }
            }
        }];
    }
}
