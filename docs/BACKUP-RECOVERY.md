# Backup e recuperação

## Proposta sujeita a aprovação operacional

- Backup lógico PostgreSQL diário com `pg_dump --format=custom`, criptografado antes de sair do host.
- Cópia em armazenamento separado, versionado e com acesso mínimo; nunca dentro da árvore pública da aplicação.
- Retenção provisória sugerida: 7 diários e 4 semanais. O prazo final depende da política LGPD aprovada.
- RPO/RTO não foram contratados. Valores iniciais para decisão: RPO de 24 horas e RTO de 4 horas.

## Restauração

1. Abrir incidente, registrar responsável e congelar escritas quando aplicável.
2. Provisionar PostgreSQL isolado da mesma versão principal.
3. Verificar checksum e descriptografar o backup em volume temporário protegido.
4. Restaurar com `pg_restore`, executar migrations pendentes somente após revisão e validar contagens/constraints.
5. Testar login MFA, leitura administrativa, confirmação e exportação em ambiente privado.
6. Registrar evidências, destruir temporários com segurança e só então autorizar troca de tráfego.

Uma restauração de ensaio deve ocorrer pelo menos trimestralmente. Backup sem restauração demonstrada não conta como controle validado.

