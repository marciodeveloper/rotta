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
            'phone.regex' => 'Informe um telefone brasileiro válido, com DDD.',
            'privacy_accepted.accepted' => 'Você precisa aceitar o aviso de privacidade para enviar.',
            'cf-turnstile-response.required' => 'Conclua a verificação de segurança.',
            'media_rights_confirmed.accepted' => 'Confirme que possui autorização para usar as imagens enviadas.',
            'gallery.max' => 'Envie no máximo seis fotografias.',
            'gallery_alt.*.required' => 'Informe uma descrição alternativa para cada fotografia.',
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
