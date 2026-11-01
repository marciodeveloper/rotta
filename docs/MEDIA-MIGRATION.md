# Migração de mídias para a ROTTA

## Contrato e idempotência

O contrato versionado está em `contracts/pre-registration-media-import-v1.schema.json`. `pre_registration_id` e `media_id` são UUIDs imutáveis; o destino deve usar `(source = rotta-prelaunch, media_id)` como chave idempotente. Uma repetição com o mesmo UUID e checksum é neutra. Divergência de checksum ou chave para o mesmo UUID deve ir para quarentena, nunca sobrescrever silenciosamente.

O CSV existente permanece inalterado. O manifesto de mídia é transferido separadamente e referencia chaves duráveis, não caminhos temporários nem URLs assinadas. Antes da importação, copie os objetos privados e valide SHA-256, MIME decodificado e dimensões no destino.

## Sequência controlada

1. Congelar o lote e registrar contagens/checksums do banco e dos objetos.
2. Exportar somente pré-cadastros aprovados para staging privado no destino.
3. Copiar objetos por chave privada, sem ACL pública.
4. Importar manifestos, validar o schema v1 e reconciliar cada checksum.
5. Repetir o mesmo lote para comprovar idempotência; a contagem não pode aumentar.
6. Liberar mídia apenas após moderação e associação explícita à entidade definitiva.

## Operação

`prelaunch:media:cleanup-orphans` apenas relata por padrão; `--force` remove objetos não referenciados com idade superior ao limite. `prelaunch:hashes:backfill --dry-run` mede a migração de hashes legados, e a execução sem `--dry-run` preenche os hashes separados preservando as colunas antigas durante a transição.

Durante a campanha temporária, o disk `prelaunch_media` aponta para `/var/lib/rotta-prelaunch/media`. A lógica de negócio continua usando `Storage::disk`, de modo que a futura adoção de S3 exige apenas configuração/adaptador, não mudança nos serviços de mídia.

Antes de migrar para S3:

1. Bloquear novos uploads e executar `prelaunch:media:capacity --json`.
2. Criar e verificar um backup consistente.
3. Copiar cada `storage_key` e `thumbnail_storage_key` preservando as chaves relativas e ACL privada.
4. Comparar tamanho e SHA-256 do original com `pre_registration_media`; comparar miniaturas com o manifesto do backup.
5. Executar uma reimportação completa e uma segunda execução idempotente.
6. Trocar o driver somente após reconciliação sem divergências e manter o volume local até o fim da janela de rollback.

Ao encerrar a campanha, criar o backup final, verificar a restauração e a cópia off-site, registrar retenção autorizada e somente então executar a limpeza de órfãos. A remoção definitiva do volume exige autorização operacional separada.
