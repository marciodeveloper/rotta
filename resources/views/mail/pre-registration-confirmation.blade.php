<x-mail::message>
# Confirme seu pré-cadastro

Olá! Recebemos uma manifestação de interesse para **{{ $businessName }}**.

Para confirmar o endereço de e-mail e encaminhar o pré-cadastro para análise, use o botão abaixo. O link expira e pode ser usado apenas uma vez.

<x-mail::button :url="$confirmationUrl">
Confirmar e-mail
</x-mail::button>

Se você não iniciou este processo, ignore esta mensagem. O envio não garante aprovação ou publicação imediata.

Equipe ROTTA
</x-mail::message>
