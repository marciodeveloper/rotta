# Arquitetura

## Limite do sistema

`rotta-prelaunch` é uma aplicação Laravel independente, com banco e ciclo de vida próprios. Ela captura intenções de cadastro; não publica empresas no produto final e não compartilha tabelas com a futura plataforma ROTTA.

## Componentes

- Interface pública Blade/Alpine: formulário progressivo, validação de UX e fallback manual de endereço.
- Camada HTTP Laravel: Form Requests, rate limits, cabeçalhos de segurança e respostas neutras.
- Serviços de domínio: validação CPF/CNPJ numérico/alfanumérico, criação transacional, HMAC separado por domínio, TOTP/recovery codes e adaptadores de Turnstile/CEP.
- Persistência PostgreSQL: UUIDs, índices por status/data, UF/cidade e categoria/data.
- Administração: guard separado `admin`, senha, segundo fator TOTP, status, filtros, auditoria e exportação.
- E-mail: token aleatório armazenado somente como hash, expiração e consumo único via POST após página GET não mutável.
- Mídia: JPEG/PNG/WebP reencodados, metadados removidos, miniaturas e objetos privados vinculados ao UUID do pré-cadastro.

## Modelo de dados

- `privacy_notices`: versão, conteúdo, finalidade, base legal e retenção; a versão inicial é rascunho.
- `pre_registrations`: dados do candidato, endereço, consentimentos, origem e estado do fluxo.
- `email_confirmation_tokens`: token com hash, prazo e instante de uso.
- `pre_registration_status_histories`: trilha append-only de alterações de estado.
- `admins`: identidade administrativa, senha e segredo TOTP criptografado.
- `admin_audit_events`: login, alteração e exportação, com IP pseudonimizado.
- `pre_registration_media`: manifesto durável de objetos privados, checksum, dimensões, posição, direitos e estado de moderação.
- `admin_recovery_codes`: hashes separados por domínio de códigos administrativos de uso único.

## Estados

`pending_email_confirmation` → `under_review` → `approved` ou `rejected`. Também é possível marcar `archived`. Toda mudança administrativa exige justificativa e cria histórico/auditoria.

## Limites intencionais

Não há Redis, API pública ou sincronização automática nesta fase. O disco de mídia é local privado por padrão e pode usar um adaptador S3 compatível quando o pacote Flysystem S3 e credenciais operacionais forem aprovados.

