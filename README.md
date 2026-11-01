# ROTTA Prelaunch

Aplicação isolada para pré-cadastro de empresas e profissionais da ROTTA. Este projeto não substitui nem altera o protótipo público em `/home/dev/projects/rotta`.

## Estado da entrega

- Laravel 13, PHP 8.3+, Blade, Alpine CSP, Tailwind CSS 4 e PostgreSQL 17.
- Fluxo público em quatro etapas, confirmação de e-mail, CPF/CNPJ, CEP, Google Places New com fallback manual, honeypot, Turnstile e respostas neutras para duplicidade.
- Administração separada com senha, TOTP obrigatório, filtros, transição de status, auditoria e exportação CSV versionada.
- Dados pessoais reversíveis são criptografados; hashes HMAC separados suportam unicidade e busca sem usar texto puro.
- O aviso de privacidade incluído é **rascunho**. Coleta pública permanece bloqueada até aprovação jurídica e configuração de serviços externos.
- Nenhum deploy, configuração de Nginx ou alteração na VPS de produção faz parte desta entrega.

## Execução local isolada

Copie `.env.example` para `.env`, gere `APP_KEY` e defina valores locais não reutilizados. Os serviços Docker usam rede interna e não publicam portas.

```bash
docker compose up -d database
docker compose run --rm app php artisan migrate --seed
```

O Compose deliberadamente não oferece preview ao host. Para QA, use um navegador no mesmo namespace Docker ou um override local revisado que publique somente em loopback e em porta livre. Não remova `internal: true` nem exponha o PostgreSQL.

Não use o servidor embutido em produção. O desenho de produção exige Nginx, PHP-FPM, HTTPS e os controles descritos em `docs/DEPLOYMENT-CHECKLIST.md`.

## Testes

```bash
docker compose --profile test up -d database_test
docker compose --profile test run --rm --no-deps \
  -v "$PWD/.env:/app/.env:ro" \
  -e APP_ENV=testing -e DB_CONNECTION=pgsql -e DB_HOST=database_test \
  -e DB_DATABASE=rotta_prelaunch_test -e DB_USERNAME=rotta_prelaunch_test \
  -e DB_PASSWORD=local-only-test-password -e PRELAUNCH_DATA_PEPPER=testing-only-pepper \
  -e CACHE_STORE=array -e SESSION_DRIVER=array -e MAIL_MAILER=array \
  app php artisan test --compact
docker compose --profile test down
```

Checks adicionais:

```bash
composer validate --strict
composer audit
npm ci
npm run build
npm audit
vendor/bin/pint --format agent
```

## Administração

Crie o primeiro administrador somente em ambiente privado:

```bash
php artisan admin:create
```

O comando exibe o segredo TOTP uma única vez. Acesso administrativo deve ficar atrás de HTTPS e controles de rede, e os eventos relevantes ficam em `admin_audit_events`.

## Documentação

- `docs/ARCHITECTURE.md`: componentes e modelo de dados.
- `docs/SECURITY.md`: ameaças, controles e pendências.
- `docs/PRIVACY-DRAFT.md`: pontos que exigem decisão jurídica.
- `docs/BACKUP-RECOVERY.md`: procedimento proposto de backup e restauração.
- `docs/DEPLOYMENT-CHECKLIST.md`: gate de homologação e produção.
- `docs/INTEGRATION.md`: contrato de exportação/importação futura.
- `contracts/pre-registration-export-v1.schema.json`: esquema versionado.

