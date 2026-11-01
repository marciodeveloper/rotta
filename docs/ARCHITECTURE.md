# Arquitetura

## Limite do sistema

`rotta-prelaunch` é uma aplicação Laravel independente, com banco e ciclo de vida próprios. Ela captura intenções de cadastro; não publica empresas no produto final e não compartilha tabelas com a futura plataforma ROTTA.

## Componentes

- Interface pública Blade/Alpine: formulário progressivo, validação de UX e fallback manual de endereço.
- Camada HTTP Laravel: Form Requests, rate limits, cabeçalhos de segurança e respostas neutras.
- Serviços de domínio: validação CPF/CNPJ, criação transacional, HMAC, TOTP e adaptadores de Turnstile/CEP.
- Persistência PostgreSQL: UUIDs, índices por status/data, UF/cidade e categoria/data.
- Administração: guard separado `admin`, senha, segundo fator TOTP, status, filtros, auditoria e exportação.
- E-mail: token aleatório armazenado somente como hash, expiração e consumo único.

## Modelo de dados

- `privacy_notices`: versão, conteúdo, finalidade, base legal e retenção; a versão inicial é rascunho.
- `pre_registrations`: dados do candidato, endereço, consentimentos, origem e estado do fluxo.
- `email_confirmation_tokens`: token com hash, prazo e instante de uso.
- `pre_registration_status_histories`: trilha append-only de alterações de estado.
- `admins`: identidade administrativa, senha e segredo TOTP criptografado.
- `admin_audit_events`: login, alteração e exportação, com IP pseudonimizado.

## Estados

`pending_email_confirmation` → `under_review` → `approved` ou `rejected`. Também é possível marcar `archived`. Toda mudança administrativa exige justificativa e cria histórico/auditoria.

## Limites intencionais

Não há Redis, filas, armazenamento S3, API pública ou sincronização automática nesta fase. Eles só devem ser introduzidos quando volume, SLA ou um consumidor real justificarem a complexidade.

