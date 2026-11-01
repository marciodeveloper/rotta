# Segurança

## Controles implementados

- Autorização no backend por guard administrativo e middleware de administrador ativo.
- Senhas com o hasher do Laravel e MFA TOTP obrigatório antes da sessão autenticada.
- CSRF, cookies de sessão criptografados, `HttpOnly`, `SameSite=Lax` e `Secure` em produção.
- Rate limiting separado para formulário, confirmação, CEP e autenticação.
- Turnstile validado no servidor antes de qualquer escrita, incluindo hostname, action, idade do desafio, timeouts e falha fechada; honeypot com resposta neutra.
- CPF e CNPJ numérico/alfanumérico validados por dígitos verificadores; duplicidade por HMAC separado por domínio, mantendo hashes legados durante o backfill.
- Campos pessoais criptografados pelo Laravel; filtros usam somente dados minimizados não criptografados.
- Tokens de confirmação aleatórios, armazenados como hash, expiráveis e de uso único; GET não consome o token e POST protegido por CSRF confirma.
- TOTP rejeita reutilização do mesmo time-step; recovery codes são hasheados e consumidos atomicamente.
- Mídias ficam em disco privado, são reencodadas e limitadas por tipo, tamanho, dimensões e quantidade; SVG e formatos ativos são recusados.
- CSP, HSTS em HTTPS, `frame-ancestors 'none'`, `nosniff`, política de referência e de permissões.
- Exportação autenticada, auditada, em streaming e protegida contra fórmula de planilha.

## Modelo de ameaça resumido

| Risco | Controle | Pendência antes de público |
|---|---|---|
| Automação e spam | Turnstile, honeypot, rate limit | Chaves reais e teste no hostname final |
| Enumeração de cadastros | Respostas neutras para sucesso/duplicado | Teste externo de abuso |
| Elevação administrativa | Guard separado, TOTP, `is_active` | Política de provisionamento/revogação e recuperação MFA |
| Vazamento do banco | Criptografia de campos e HMAC | KMS/secret store, rotação e teste de restauração |
| Token em URL | Hash, TTL e uso único | Nginx deve omitir/redigir query e path de confirmação dos access logs |
| Exfiltração por CSV | Autorização, auditoria, filtros e mitigação de fórmula | Política de acesso, expiração e descarte do arquivo |
| Dependência externa indisponível | Timeouts e falha fechada do Turnstile; fallback manual de endereço | Alertas e runbook de indisponibilidade |

## Secrets

Nunca versionar `.env`, `APP_KEY`, pepper, senha do PostgreSQL, credenciais SMTP, segredo Turnstile ou chave Google. Produção deve injetá-los por secret store, restringir leitura ao processo e possuir procedimento testado de rotação. `APP_KEY` e pepper não podem ser rotacionados sem plano de recriptografia/reindexação.

## Logging

Não registrar payloads, documentos, e-mails, telefones, tokens ou exports. Logs devem ter retenção limitada, acesso segregado e alertas para tentativas de login, falhas repetidas de MFA, picos de rate limit e exportações.

## Pendências de segurança

- Revisão OWASP ASVS direcionada e teste de intrusão no ambiente de homologação.
- Credenciais reais restritas por hostname/IP e quotas.
- SMTP com SPF, DKIM e DMARC validados.
- Política aprovada de MFA perdido, desligamento de admin e resposta a incidentes.
- Nginx/PHP-FPM endurecidos e dependências reavaliadas imediatamente antes da publicação.

