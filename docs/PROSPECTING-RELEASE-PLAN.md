# Prospecção Fase 1 — plano de release e recuperação

Inspeção de 12/11/2026, horário de Brasília. Documento preparatório: nenhum merge, deploy ou migration de produção foi executado nesta missão.

## Gates e evidências

- Branch: `feat/prospecting-phase-1`; destino exclusivo: `chore/prelaunch-release-readiness`. Base comum verificada: `fd2c4fcd20d46288e647d5025c6862b92e554c6f`. Revalidar refs e CI no SHA aprovado antes da publicação.
- PHPUnit: 179 testes e 1.162 asserções; JavaScript: 22 testes; Pint, build, Composer validate e requisitos de plataforma aprovados. CI mantém PostgreSQL 16; ensaio local usou PostgreSQL 17.10.
- Chromium desktop 1440×900 e mobile 390×844: revisão de correspondências históricas, preservação de bloqueio, justificativas separadas, retorno de validação malformada, ordenação e desbloqueio autorizado aprovados; zero erros JavaScript. Dez cenários humanos aprovados pelo usuário.
- Composer audit identificou sete advisories em quatro componentes Symfony 7.4.22. O lock foi atualizado exclusivamente para os patches 7.4.23 de http-foundation, http-kernel, routing e yaml; auditoria completa posterior sem advisories. Não houve alteração de credenciais nem de composer.json.
- Ensaio com dados exclusivamente sintéticos: migrations legadas pela imagem anterior, aplicação das duas migrations novas, criação de históricos, execução da imagem anterior contra o esquema expandido, backup consistente, verificação, criptografia, descriptografia e restauração em outra base PostgreSQL 17.10. Fingerprints das linhas legadas, prospects, origens, atividades, decisões e imagens conferidos. Nenhum acesso ao banco de produção nesse ensaio.
- Na imagem anterior real, login sintético, dashboard, ficha, mídia privada, exportação CSV, `/up` e `/ready` passaram contra a base isolada expandida. Isso demonstra compatibilidade do rollback de aplicação; não constitui homologação autenticada de produção.

Evidências locais protegidas, fora do repositório: `/tmp/rotta-prospect-release-20261112/results.json`, subdiretório `browser/` e `/tmp/rotta-prospect-release-quality-final.log`. Não versionar dumps, chaves de ensaio ou dados sintéticos gerados. Google Drive não está disponível nas ferramentas desta sessão; nenhum upload realizado.

## Migrations e compatibilidade

Aplicar nesta ordem, exclusivamente após autorização de publicação:

1. `database/migrations/2026_11_12_002420_create_prospect_tables.php`: prospects, origens e atividades, UUID, relacionamentos, checks e índices; sem alterar tabelas de pré-cadastros.
2. `database/migrations/2026_11_12_033611_preserve_prospect_identifiers_and_decision_reasons.php`: identificadores históricos HMAC e decisões criptografadas; retroalimentação dos identificadores atuais em lotes de 200. Usa a APP_KEY e o pepper existentes, que devem permanecer idênticos. Não reconstrói identificadores antigos nunca conservados.

Os `up()` são aditivos. Os `down()` removem dados e foram exercitados somente em banco isolado. Em produção, rollback será exclusivamente de aplicação, mantendo as novas tabelas e colunas. Não executar `migrate:rollback`, `migrate:reset` ou `migrate:fresh`. O lock transacional compartilhado prioriza integridade na entrada manual; medir capacidade antes de futuras importações em lote.

## Produção inspecionada somente para leitura

- Projeto Compose: `rotta-prelaunch-production`; arquivo `compose.release.yaml`, env `/etc/rotta-prelaunch/release.env`, override `/etc/rotta-prelaunch/compose.override.yaml`. Não imprimir a configuração expandida nem copiar secrets para o Git.
- Cinco serviços saudáveis: app, worker, scheduler, web e database. Somente web publica `127.0.0.1:18970`; PostgreSQL e PHP-FPM não têm portas públicas.
- Baseline publicada associada a `fd2c4fc` por registro de release e comparação de cinco arquivos da imagem com o Git, incluindo composer.lock e rotas. A imagem não possui label OCI de revisão: registrar o novo SHA e IDs em manifesto protegido na publicação.
- Imagem anterior app/worker/scheduler: `sha256:b611ba32d3a15ce56252f901c24b6971f2e4e47baea11d873812f0aab36ef437`.
- Imagem anterior web: `sha256:493e44e3506195925889b2a9f8243cf6eba739fbca6025c5e6e9a572271ee1b4`.
- Imagem PostgreSQL: `sha256:742f40ea20b9ff2ff31db5458d127452988a2164df9e17441e191f3b72252193`. Não reconstruir/recriar database.
- Volumes: `rotta-prelaunch-production_pgdata`, `rotta_prelaunch_media`, `rotta_prelaunch_backups`. Preservar volumes, sessões, APP_KEY e pepper.
- Disco: 387 GB totais, 344 GB usados, 44 GB disponíveis, 89% ocupado; inodes 12%. Revalidar imediatamente antes de construir imagens. Preservar o piso operacional de 20 GiB e margem para build, backup e crescimento. Interromper se a projeção ultrapassar a margem; não fazer prune indiscriminado.
- HTTPS verificado externamente: `/`, `/admin/entrar`, `/privacidade/`, `/up` e `/ready` responderam 200, com validação TLS. Nenhum teste administrativo autenticado ou escrita de produção realizado.

As tags imutáveis antigas `git-fd2c4fcd20d4` não estão disponíveis. Os IDs acima existem e devem receber tags exclusivas de rollback ANTES de qualquer build. Não usar o rollback do helper histórico `20261111-crm/release.php`: ele aponta para `4c3c4d4`, anterior à baseline atual. Não alterar esse helper; gerar novo registro da execução.

## Backup e recuperação

Último arquivo observado: `/var/lib/rotta-prelaunch/backups/20261112_031902_9lxx3msm.rottabackup`; sidecar SHA-256 conferido com sucesso. Timer `rotta-prelaunch-backup.timer` ativo e última execução bem-sucedida. Isso comprova integridade do arquivo criptografado, não restauração desse backup real.

O ensaio de restauração realizado nesta missão foi sintético. Antes da publicação, validar um backup recente real em ambiente isolado e protegido, sem comunicação externa e com controle de acesso aos dados, depois gerar outro backup imediatamente antes da alteração. Não restaurar sobre produção para ensaiar. A custódia externa da chave e a cópia off-site definitiva não foram comprovadas nesta inspeção; continuam gates operacionais pendentes. Não versionar nem incluir a chave no arquivo de backup.

Os comandos existentes são `prelaunch:backup:create`, `prelaunch:backup:verify`, `prelaunch:backup:encrypt` e `prelaunch:backup:decrypt`. O create gera dump e arquivo de mídias consistentes; verificar o lote antes de cifrar. Restaurar dump com cliente PostgreSQL 17 e mídia em volume privado separado; conferir manifest/checksums e históricos. Para ensaio real, usar chaves de aplicação existentes em ambiente restrito, nunca logs ou chat.

## Sequência futura de publicação — NÃO executada

Executar somente após aprovação do plano e integração autorizada. Substituir todos os marcadores antes de executar. Usar checkout limpo no SHA aprovado e preservar as configurações atuais.

```bash
sudo -v
cd /home/dev/projects/rotta-prelaunch
ROTTA_RELEASE_SHA='PREENCHER_SHA_INTEGRADO_APROVADO'
ROTTA_STAMP='PREENCHER_IDENTIFICADOR_UNICO_DA_EXECUCAO'
ROTTA_COMPOSE=(sudo docker compose --project-name rotta-prelaunch-production --env-file /etc/rotta-prelaunch/release.env -f compose.release.yaml -f /etc/rotta-prelaunch/compose.override.yaml)
test "$(git rev-parse HEAD)" = "$ROTTA_RELEASE_SHA"
test -z "$(git status --porcelain)"
"${ROTTA_COMPOSE[@]}" config --quiet
"${ROTTA_COMPOSE[@]}" ps
df -h /
df -i /
ROTTA_OLD_APP='sha256:b611ba32d3a15ce56252f901c24b6971f2e4e47baea11d873812f0aab36ef437'
ROTTA_OLD_WEB='sha256:493e44e3506195925889b2a9f8243cf6eba739fbca6025c5e6e9a572271ee1b4'
sudo docker image inspect "$ROTTA_OLD_APP" "$ROTTA_OLD_WEB" --format '{{.Id}}'
sudo docker tag "$ROTTA_OLD_APP" "rotta-prelaunch-app:rollback-fd2c4fc-$ROTTA_STAMP"
sudo docker tag "$ROTTA_OLD_WEB" "rotta-prelaunch-web:rollback-fd2c4fc-$ROTTA_STAMP"
"${ROTTA_COMPOSE[@]}" exec -T app php artisan prelaunch:backup:create
```

Confirmar que os containers ainda usam exatamente os IDs anteriores; interromper se houver divergência. Registrar container IDs, imagens, hashes dos arquivos de configuração protegidos (sem conteúdo), SHA Git e horário em manifesto restrito. Confirmar restauração recente e disponibilidade off-site. Usar o caminho exato do lote retornado pelo create:

```bash
ROTTA_BACKUP_BATCH='PREENCHER_CAMINHO_ABSOLUTO_DO_LOTE_RETORNADO'
"${ROTTA_COMPOSE[@]}" exec -T app php artisan prelaunch:backup:verify "$ROTTA_BACKUP_BATCH"
"${ROTTA_COMPOSE[@]}" exec -T app php artisan prelaunch:backup:encrypt "$ROTTA_BACKUP_BATCH" --key-file=/run/secrets/rotta-backup-recovery-key
"${ROTTA_COMPOSE[@]}" exec -T app sh -c 'cd "$(dirname "$1")" && sha256sum -c "$(basename "$1").sha256"' backup-check "${ROTTA_BACKUP_BATCH}.rottabackup"
"${ROTTA_COMPOSE[@]}" build app web
sudo docker tag rotta-prelaunch-app:release "rotta-prelaunch-app:git-$ROTTA_RELEASE_SHA"
sudo docker tag rotta-prelaunch-web:release "rotta-prelaunch-web:git-$ROTTA_RELEASE_SHA"
"${ROTTA_COMPOSE[@]}" run --rm --no-deps app php artisan migrate:status
"${ROTTA_COMPOSE[@]}" run --rm --no-deps app php artisan migrate --force --no-interaction --path=database/migrations/2026_11_12_002420_create_prospect_tables.php --path=database/migrations/2026_11_12_033611_preserve_prospect_identifiers_and_decision_reasons.php
"${ROTTA_COMPOSE[@]}" up -d --no-build --no-deps --force-recreate app worker scheduler web
"${ROTTA_COMPOSE[@]}" ps
for ROTTA_PATH in / /admin/entrar /privacidade/ /up /ready; do
  curl --fail --silent --show-error --output /dev/null --write-out '%{http_code}\n' "https://rotta.serradev.com$ROTTA_PATH"
done
```

Antes do up, conferir as imagens finais, composer.lock, manifest Vite e as rotas da imagem candidata. Não transportar caches PHP locais: `.dockerignore` exclui `bootstrap/cache/*.php`; o build regenera manifests de pacotes. Não executar limpeza global de cache compartilhado nem reiniciar o serviço systemd do projeto: sua unidade pode afetar todos os serviços.

Aguardar healthchecks dos cinco serviços, confirmar mesmo container/volume de PostgreSQL e mesmos mounts privados. Conferir versão efetivamente servida, JS/CSS do manifest e ausência de erros relevantes nos logs recentes, filtrando dados sensíveis. Comparar contagens/indicadores de integridade dos pré-cadastros antes/depois sem expor registros; autenticação administrativa, listagem, ficha e mídia devem ser homologadas em modo somente leitura por operador autorizado. Não alterar status, reenviar e-mails ou criar dados artificiais. Prospect count pode permanecer zero. Não alterar LGPD, SMTP, Turnstile, DNS, TLS ou Nginx. Revalidar os bloqueios operacionais de coleta existentes, sem habilitação automática.

## Rollback de aplicação, preservando todos os dados

Se migration falhar, não avançar ao up: a aplicação anterior permanece ativa, investigar com o esquema aditivo mantido. Se houver falha crítica após troca da aplicação:

```bash
sudo docker tag "rotta-prelaunch-app:rollback-fd2c4fc-$ROTTA_STAMP" rotta-prelaunch-app:release
sudo docker tag "rotta-prelaunch-web:rollback-fd2c4fc-$ROTTA_STAMP" rotta-prelaunch-web:release
"${ROTTA_COMPOSE[@]}" up -d --no-build --no-deps --force-recreate app worker scheduler web
"${ROTTA_COMPOSE[@]}" ps
for ROTTA_PATH in / /admin/entrar /privacidade/ /up /ready; do
  curl --fail --silent --show-error --output /dev/null --write-out '%{http_code}\n' "https://rotta.serradev.com$ROTTA_PATH"
done
```

Revalidar healthchecks e IDs anteriores. Manter esquema expandido, prospects, origens, identificadores, atividades, decisões e dados legados. Não restaurar backup por rotina para reverter código; recuperação de desastre exige incidente e plano específico para não perder gravações posteriores.

A imagem anterior ainda contém os componentes Symfony com advisories; um rollback recupera disponibilidade, mas também reintroduz esse risco. Sua utilização deve ser contingência temporária com correção posterior aprovada. Não construir silenciosamente outra baseline nem declarar a produção corrigida antes de uma publicação autorizada. Merge, backup real para a publicação, tags de rollback e deploy permanecem pendentes de execução autorizada posterior.
