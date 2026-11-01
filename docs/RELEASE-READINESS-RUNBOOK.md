# Release readiness sem coleta pública

## Estado permitido antes do GO-LIVE

O domínio público deve responder apenas com a configuração de manutenção e HTTP 503. A aplicação fica em `127.0.0.1:18970`; ativar proxy para essa porta exige autorização expressa de GO-LIVE, aviso de privacidade publicado e integrações reais homologadas. O endpoint `/up` mede vida do Laravel e `/ready` confirma PostgreSQL e storage privado sem revelar detalhes.

## Stack privada

1. Confirmar que `127.0.0.1:18970` está livre e que os volumes externos apontam exclusivamente para `/var/lib/rotta-prelaunch/media` e `/var/lib/rotta-prelaunch/backups`.
2. Fornecer `APP_KEY`, `PRELAUNCH_DATA_PEPPER` e `DB_PASSWORD` por arquivo de ambiente `0600`, fora do Git.
3. Validar com `docker compose --env-file /caminho/seguro -f compose.release.yaml config --quiet` e construir uma única vez com `docker compose --env-file /caminho/seguro -f compose.release.yaml build app web`.
4. Subir `database` e `app`, executar `php artisan migrate --force`, e somente depois subir `worker`, `scheduler` e `web`. Worker e scheduler reutilizam exatamente a imagem imutável do PHP-FPM.
5. Verificar containers, `curl http://127.0.0.1:18970/up`, `curl http://127.0.0.1:18970/ready`, logs e persistência após reinício.

Não usar `docker compose down -v`. Logs usam rotação local de cinco arquivos de 10 MiB por container.

## Nginx e TLS com rollback

As operações abaixo exigem sudo autenticado. Antes de qualquer alteração, criar `/var/backups/rotta-prelaunch-release/UTC_TIMESTAMP`, copiar os arquivos Nginx envolvidos preservando atributos e registrar `nginx -T` no diretório de evidências.

1. Instalar primeiro `deploy/nginx/rotta.serradev.com.bootstrap.conf`, criar `/var/www/letsencrypt`, validar com `nginx -t` e recarregar Nginx.
2. Emitir somente o certificado: `certbot certonly --webroot -w /var/www/letsencrypt -d rotta.serradev.com`, usando conta e e-mail operacionais aprovados.
3. Trocar para `deploy/nginx/rotta.serradev.com.maintenance.conf`, validar e recarregar. Confirmar SAN, cadeia, expiração, renovação e que `/` continua em 503.
4. Rollback: restaurar os arquivos do diretório de backup, remover apenas o symlink ROTTA criado nesta execução, executar `nginx -t` e recarregar. Nunca alterar o virtual host default nem sites de outros projetos.

O template de proxy para coleta não faz parte desta etapa e não deve ser criado ou ativado antes do GO-LIVE.

## Gates restantes

- aviso jurídico aprovado e publicado por fluxo administrativo, nunca por migration ou deploy;
- credenciais reais de SMTP e Turnstile, com teste controlado e hostname/action conferidos;
- certificado específico de `rotta.serradev.com` e renovação validada;
- destino off-site privado e política de retenção LGPD aprovados;
- chave de recuperação custodiada fora da VPS, separada do pacote de backup;
- autorização explícita para substituir manutenção por proxy da aplicação.
