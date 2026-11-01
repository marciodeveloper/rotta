# Integração futura com a plataforma ROTTA

## Estratégia

A aplicação final deve importar o CSV `1.0` em uma área de staging, nunca diretamente em entidades públicas. O contrato está em `contracts/pre-registration-export-v1.schema.json`.

1. Validar cabeçalhos, versão, tipos, estados permitidos e tamanho máximo.
2. Registrar lote, checksum, operador e instante da importação.
3. Usar `(source, source_id)` como chave idempotente; uma reimportação do mesmo registro atualiza o staging, não duplica entidades.
4. Revalidar CPF/CNPJ e calcular o HMAC no domínio de destino, com pepper próprio; nunca transferir hashes internos como identidade global.
5. Colocar conflitos de documento/e-mail em quarentena para revisão humana.
6. Mapear categoria/região para IDs canônicos e manter o valor original para auditoria.
7. Só criar/atualizar organização, estabelecimento ou profissional após aprovação explícita. Pré-cadastro não equivale a publicação.
8. Registrar resultado por linha e permitir retry idempotente dos erros transitórios.

## Compatibilidade

Campos podem ser adicionados apenas em nova versão. Consumidores devem rejeitar versões principais desconhecidas. O export não inclui coordenadas ou `google_place_id` na versão 1.0 por minimização; alterações exigem revisão de finalidade e contrato.

