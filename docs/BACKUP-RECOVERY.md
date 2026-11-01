# Backup e recuperação

## Estratégia temporária aprovada

- `php artisan prelaunch:backup:create` adquire o mesmo lock PostgreSQL usado pelos uploads e pela limpeza, valida cada mídia contra o SHA-256 e cria um lote com `database.dump`, `media.tar.gz`, `media-manifest.json` e `SHA256SUMS`.
- O staging local fica em `/var/lib/rotta-prelaunch/backups`, fora do document root, com diretórios `0700` e arquivos `0600`. Ele não substitui a cópia externa.
- Antes de sair da VPS, o lote real deve ser criptografado com uma chave mantida fora da VPS. Em seguida, deve ser copiado para storage externo privado e ter nome, tamanho, parent e compartilhamento relidos no destino.
- `php artisan prelaunch:backup:verify /caminho/absoluto/do/lote` valida os checksums antes de transporte ou restauração.
- Retenção provisória sugerida: 7 diários e 4 semanais. O prazo final depende da política LGPD aprovada.
- RPO/RTO não foram contratados. Valores iniciais para decisão: RPO de 24 horas e RTO de 4 horas.

## Restauração

1. Abrir incidente, registrar responsável e congelar escritas quando aplicável.
2. Provisionar PostgreSQL isolado da mesma versão principal.
3. Baixar a cópia externa, verificar o checksum do pacote, descriptografar em volume temporário protegido e executar `prelaunch:backup:verify`.
4. Restaurar com `pg_restore`, executar migrations pendentes somente após revisão e validar contagens/constraints.
5. Restaurar os objetos privados nas mesmas chaves, verificar SHA-256 contra `pre_registration_media.checksum` e confirmar que nenhum objeto ficou público.
6. Testar login MFA, leitura administrativa, confirmação e exportação em ambiente privado.
7. Registrar evidências, destruir temporários com segurança e só então autorizar troca de tráfego.

Uma restauração de ensaio deve ocorrer antes da campanha e depois pelo menos trimestralmente. O ensaio deve restaurar o dump em PostgreSQL temporário sem porta pública, extrair a mídia em volume separado e reconciliar todos os SHA-256 do manifesto. Backup sem restauração demonstrada não conta como controle validado.

O ensaio de 2026-11-01 restaurou um pré-cadastro e uma mídia exclusivamente sintéticos. A cópia off-site de ensaio foi mantida privada no Google Drive; credenciais e política de criptografia para backups reais continuam sendo um gate operacional.

