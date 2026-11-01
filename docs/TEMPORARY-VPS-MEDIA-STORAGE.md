# Storage temporário de mídia na VPS

## Capacidade e limites

Baseline de 2026-11-01: partição ext4 de 387 GiB, 336 GiB usados e 51 GiB livres; 12% dos inodes usados. Como a raiz já está em 87%, a aplicação mantém um piso operacional de 20 GiB livres.

- quota global: 5 GiB;
- alerta: 70% (3,5 GiB);
- bloqueio de novos uploads: 80% (4 GiB);
- limite individual: 5 MiB antes e depois do re-encode;
- limite por cadastro: logo, capa e até seis fotos, totalizando oito imagens.

`prelaunch:media:capacity --json` mede os bytes físicos do disk, o espaço livre do filesystem e atualiza o singleton `media_storage_usages`. O scheduler executa essa verificação a cada cinco minutos. Uploads usam lock de linha e verificam o estado projetado antes da escrita; falha de leitura do espaço livre bloqueia a operação.

## Diretórios e volumes

Os caminhos aprovados são `/var/lib/rotta-prelaunch/media` e `/var/lib/rotta-prelaunch/backups`, ambos `0700` e pertencentes ao UID/GID `10001:10001`. Arquivos são criados como `0600`. Eles ficam fora de `public/` e não há rota de download público.

Os volumes externos `rotta_prelaunch_media` e `rotta_prelaunch_backups` são bind volumes para esses caminhos. O Compose usa `volume.nocopy: true`; sem isso, o Docker pode substituir ownership e modo ao inicializar um volume vazio.

Conferência operacional:

```bash
docker volume inspect rotta_prelaunch_media rotta_prelaunch_backups
docker compose config
docker compose exec app php artisan prelaunch:media:capacity --json
```

Nenhuma quota de filesystem é aplicada à partição compartilhada. O limite é fail-closed na aplicação e não autoriza consumo além de 4 GiB; monitoramento da VPS continua obrigatório porque outros projetos compartilham `/dev/sda1`.

## Crescimento e resposta

Registrar diariamente `used_bytes`, `free_bytes` e o número de mídias. Em `alert`, revisar a tendência e confirmar o backup externo. Em `blocked`, manter uploads desativados, verificar espaço global e não apagar dados sem backup/restauração comprovados. O histórico anterior à baseline não estava configurado, portanto a primeira taxa de crescimento só poderá ser calculada após duas medições reais.

## Provisionamento seguro

Os volumes devem existir antes de iniciar o serviço. Nunca use um caminho genérico ou volume de outro projeto. Não use `docker compose down -v`, pois os volumes de mídia e backup são externos e persistentes. A aplicação deve permanecer não-root e o formulário só pode ser publicado após os gates de go-live.
