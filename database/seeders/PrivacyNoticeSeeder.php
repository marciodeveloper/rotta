<?php

namespace Database\Seeders;

use App\Models\PrivacyNotice;
use Illuminate\Database\Seeder;

class PrivacyNoticeSeeder extends Seeder
{
    public function run(): void
    {
        PrivacyNotice::query()->updateOrCreate(
            ['version' => 'draft-2026.01'],
            [
                'title' => 'Aviso de privacidade — versão de trabalho',
                'content' => 'Este aviso é um rascunho operacional e depende de aprovação jurídica antes da coleta pública. Os dados serão usados para receber, validar e avaliar o interesse de empresas e profissionais em participar futuramente da ROTTA. O envio não garante aprovação nem publicação.',
                'controller_name' => config('prelaunch.privacy.controller_name'),
                'contact_email' => config('prelaunch.privacy.contact_email'),
                'purpose' => 'Receber, confirmar e avaliar manifestações de interesse de empresas e profissionais para possível inclusão futura na ROTTA.',
                'legal_basis' => null,
                'retention_days' => null,
                'is_current' => false,
                'status' => 'draft',
            ],
        );
    }
}
