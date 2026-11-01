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
