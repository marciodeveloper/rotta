# Relatório de entrega — 2026-11-01

## Resultado

Fundação funcional implementada em `/home/dev/projects/rotta-prelaunch`, isolada do protótipo aprovado. O resultado está **apto para próxima etapa de homologação privada** e **bloqueado para coleta pública** até o fechamento dos gates jurídico, credenciais e infraestrutura.

## Escopo entregue

- Laravel 13/PHP 8.3, Blade, Alpine CSP, Tailwind 4 e PostgreSQL 17.
- Formulário responsivo em quatro etapas, Empresa/CNPJ e Profissional/CPF.
- Google Places New opcional, preenchimento manual e consulta CEP.
- Turnstile server-side, honeypot, rate limits e duplicidade neutra.
- Confirmação de e-mail com token de uso único, hash e expiração.
- Dados pessoais criptografados, HMAC de pesquisa/unicidade e trilha de status.
- Administração separada com senha + TOTP, filtros, status, auditoria e exportação CSV 1.0.
- Aviso LGPD explicitamente em rascunho e contrato de integração futura.
- Docker isolado, sem portas publicadas, deploy, DNS, Nginx ou alteração de serviços da VPS.

## Evidências executadas

| Check | Resultado |
|---|---|
| PHPUnit em PostgreSQL 17 efêmero | PASS — 22 testes, 77 asserções |
| Build Vite | PASS |
| Docker build | PASS |
| `composer validate --strict` | PASS |
| `composer audit` | PASS — 0 advisories |
| `npm audit --audit-level=moderate` | PASS — 0 vulnerabilidades |
| Pint | PASS após formatação |
| Sintaxe PHP | PASS |
| Browser 1440×900 | PASS — passos 1–4, logo, assets, sem overflow ou request com erro |
| Browser 390×844 | PASS — passos 1–4, logo, assets, sem overflow ou request com erro |
| Console | Sem erro do produto; apenas aviso COOP esperado no QA interno HTTP |
| Turnstile ausente | Gate visível e botão de envio desabilitado |
| Assets aprovados | SHA-256 idênticos aos três arquivos de origem |

Screenshots e telemetria do browser estão em `/tmp/rotta-prelaunch-qa-2026-11-01/`.

## Integridade do protótipo original

- Branch: `fix/hero-autoplay-manual-reset`.
- HEAD: `dafb13ab586aac0d6a642a5bbce145750496efc7`.
- Diff rastreado: vazio.
- Único item não rastreado: `.vscode/`, preservado.
- `.vscode/settings.json`: SHA-256 `f6b023018009726d974b99208b3c190e74a94132196b32548b367d4fa42fc857`.

## Bloqueadores para publicação

1. Aprovação jurídica do controlador, contato, base legal, retenção e texto final.
2. Turnstile, Google Places e SMTP reais, restritos e testados no hostname de homologação.
3. Nginx + PHP-FPM + HTTPS, secret store, logs sem tokens e isolamento PostgreSQL definitivos.
4. Backup/restauração demonstrados, monitoramento, recuperação MFA e resposta a incidente.
5. QA em HTTPS, acessibilidade, navegadores e teste de segurança/abuso.

## Observações de entrega

O diretório novo ainda não é um repositório Git; nenhum commit, push, PR ou deploy foi realizado. A leitura de metadados de um eventual upload ao Drive comprova nome/tamanho, não equivalência byte a byte; checksums locais devem acompanhar o pacote.
