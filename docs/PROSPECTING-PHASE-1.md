# Prospecção comercial — Fase 1

Branch de trabalho: feat/prospecting-phase-1. Base: fd2c4fcd20d46288e647d5025c6862b92e554c6f.
Implementação homologada pelo usuário em dez cenários humanos. Preparação de commits e PR autorizada; merge e deploy dependem de aprovação posterior. Plano de publicação: [PROSPECTING-RELEASE-PLAN.md](PROSPECTING-RELEASE-PLAN.md).

## Funcionalidades

- Cadastro, edição, filtros, ordenação e paginação no servidor; ficha individual com retorno ao contexto da listagem.
- Nome, cidade e UF obrigatórios. Contatos, endereço, segmento, responsável, prioridade, observações e referências opcionais.
- Origem manual automática, múltiplas referências, datas desconhecidas nulas, restrições de uso e autoria.
- Confirmações explícitas por campo com autor e data; alteração ou retirada de confirmação exige decisão e justificativa.
- Bloqueio e desbloqueio operacionais com justificativa, controle de versão, histórico e auditoria.
- Possíveis duplicidades por nome/cidade/UF normalizados ou contato comercial. Nenhuma fusão automática.
- Correspondência atual ou histórica com prospect bloqueado exige revisão específica, autorizada, justificada e vinculada às versões dos registros; edição e nova origem preservam o bloqueio.
- Envios de criação e associação de origem idempotentes, vinculados ao autor e ao conteúdo.

## Organização

Prospect, ProspectSource e ProspectActivity são entidades independentes de PreRegistration.
ProspectPolicy autoriza cada operação. Os FormRequests e serviços usam ProspectRules.
CreateProspect, UpdateProspect, AddProspectSource e ManageProspectContact aplicam regras e autorização sem depender de controllers.
NormalizeProspect trata dados e referências; DetectProspectDuplicates sinaliza correspondências; ProspectAudit registra atividades e auditoria na transação.

A TanStack existente recebe colunas configuráveis, preservando as colunas padrão dos pré-cadastros.
O frontend só impede o envio repetido na sessão da página; os serviços e constraints são a proteção definitiva.
Contatos, endereço, observações, referências, restrições e justificativas são criptografados. Hashes contextualizados de contato não são únicos.
Metadados de auditoria contêm identificadores e nomes de campos, sem copiar contatos ou observações.

## Banco e concorrência

Migration nova: database/migrations/2026_11_12_002420_create_prospect_tables.php.
Cria exclusivamente prospects, prospect_sources e prospect_activities, com UUID, foreign keys restritivas, timestamptz, índices e checks.
Status comercial inicial e único nesta fase: identified. O bloqueio operacional é independente.
As escritas manuais são serializadas por advisory lock transacional PostgreSQL; edições também verificam lock_version sob bloqueio da linha. Os fingerprints anteriores e atuais são conservados na mesma transação.
O lock compartilhado prioriza integridade para o baixo volume da entrada manual. Importações futuras precisam medir duração e capacidade antes de reutilizar transações maiores.

A reversão e reaplicação foram executadas somente em rotta_prelaunch_test. down() apaga as três tabelas e seus dados: não usar como rollback de aplicação em produção. Uma futura publicação deverá manter tabelas preenchidas quando reverter apenas a aplicação.

## Validação executada

- PHPUnit: 179 testes, 1.162 asserções, PASS; PostgreSQL isolado. São 27 novos casos de regressão das correções, incluindo migration e concorrência.
- JavaScript: 22 testes, PASS.
- Pint em modo de correção dos arquivos alterados e verificação completa: PASS.
- npm run build: PASS.
- git diff --check: PASS.
- Migration: rollback e reaplicação no banco de testes, PASS.
- Chromium desktop 1440x900 e mobile 390x844: cadastro, erros inline, preservação de dados, alteração de campo confirmado, nova origem, bloqueio, impedimento de duplicado bloqueado, desbloqueio, filtros via Fetch, retorno à listagem e navegação por teclado, PASS.
- Requisição sem CSRF no navegador: HTTP 419, sem alteração.
- Zero erros JavaScript ou falhas de assets da aplicação nos cenários de navegador.
- A regressão encontrou uma comparação de timestamps sujeita à mudança de segundo no teste existente de login. O teste passou a congelar o relógio, mantendo suas asserções e sem alterar o código de autenticação.

Os testes de concorrência incluem processos reais simultâneos, conexões PostgreSQL independentes e barreira de início: criação contra alteração de identificadores bloqueados e retries de criação com o mesmo token. Também cobrem versões obsoletas. Não foi executado ensaio de carga.

## Correções dos cinco achados

1. prospect_identifiers conserva somente fingerprints HMAC contextualizados da identidade composta (nome normalizado, cidade e UF), e-mail e telefone. A combinação é indexada, mas não é única entre estabelecimentos. Registra os identificadores antes e depois da edição, além de criação e bloqueio/desbloqueio. Nenhum identificador externo proibido é convertido em hash para contornar restrições contratuais.
2. Correspondências históricas aparecem na revisão. Para declarar um estabelecimento distinto, o administrador precisa da Policy reviewBlockedMatch, justificativa específica e versões atuais de todos os bloqueados encontrados. Uma mudança de versão invalida a seleção anterior. A operação registra candidatos, versões e decisão; nunca faz fusão nem desbloqueia o registro original. Contatos compartilhados não provocam bloqueio automático da nova unidade.
3. decision_reasons conserva separadamente as justificativas de alteração, duplicidade, revisão de bloqueio e contato, com criptografia. A auditoria referencia a atividade e os tipos de decisão, sem copiar os textos para metadados em claro. Atividades antigas permanecem legíveis no campo reason.
4. Componentes Blade descartam valores anteriores não escalares na apresentação, preservando campos válidos e erros. Há regressões POST inválido -> redirect -> GET 200 para inputs, textarea, selects, confirmações, datas e campos ocultos. Os testes preservam o cookie de sessão entre as requisições.
5. Justificativas são normalizadas na camada compartilhada: controles, caracteres de formato e espaços Unicode são reduzidos antes da validação. Chamadas diretas não podem usar justificativas vazias ou compostas somente por espaços/controles. A ordenação do responsável usa LEFT JOIN por chave primária, nome relacionado, NULLS LAST nas duas direções e desempate pelo UUID do prospect; a whitelist permanece.

Migration corretiva: database/migrations/2026_11_12_033611_preserve_prospect_identifiers_and_decision_reasons.php. Cria prospect_identifiers e adiciona decision_reasons a prospect_activities. Retroalimenta os identificadores atuais conhecidos em lotes de 200, sem alterar contatos, bloqueios ou textos anteriores. first seen corresponde ao registro do fingerprint; não inventa quando um identificador antigo foi originalmente coletado.

O teste da migration executou reversão e reaplicação em PostgreSQL isolado, conservando o ciphertext dos contatos, o bloqueio e a justificativa legada. down() elimina fingerprints e justificativas estruturadas: nunca usá-lo para rollback de aplicação sobre dados reais. Nenhuma migration foi aplicada em produção.

Não é possível reconstruir identificadores ou justificativas que tenham sido perdidos antes desta correção e nunca foram armazenados. A preservação histórica passa a valer a partir dos valores atuais retroalimentados e das alterações posteriores. APP_KEY e PRELAUNCH_DATA_PEPPER existentes devem ser preservados; rotação exige estratégia específica para os dados criptografados e fingerprints.

## Homologação local nesta VPS

O ambiente existente está em http://localhost:18080/admin/entrar, mediante encaminhamento da porta 18080 no VS Code/SSH.
Depois do login com o administrador sintético local já fornecido, abrir Prospecção, ou http://localhost:18080/admin/prospeccao.

O container rotta-admin-evolution-browser usa o código do workspace e rotta_prelaunch_dev. Essa base foi separada de rotta_prelaunch_test, usada pelo PHPUnit.
O PostgreSQL de homologação é temporário, sem portas públicas e sem volumes de produção. Recriar o container de banco elimina seus dados locais.
O ambiente contém somente prospects sintéticos de QA. Nenhum pré-cadastro formal foi criado na base de demonstração.
Não executar testes usando release.env nem dentro da stack de produção. Não executar migrate:fresh sobre a base de demonstração para atualizar código.

Roteiro: criar estabelecimento; abrir ficha; confirmar um campo; tentar alterá-lo sem justificativa; autorizar a alteração; adicionar outra origem; bloquear contato; tentar cadastrar um semelhante; conferir o impedimento; desbloquear justificadamente; verificar histórico e filtros ao retornar.

## Evidências

Capturas e resultados: /tmp/rotta-prospecting-qa-20261112/.
Há capturas desktop/mobile de listagem, formulário, ficha e bloqueio, além de results.json.
O log final do PHPUnit está em /tmp/rotta-prospecting-final-php.log.
Evidências das correções: /tmp/rotta-prospect-security-fixes-20261112/; capturas desktop/mobile da revisão histórica, justificativas separadas, recuperação de validação e ordenação. Os fluxos passaram sem erros JavaScript. Log PHP atualizado das correções: /tmp/rotta-prospect-fixes-full.log.
Google Drive indisponível nas ferramentas conectadas desta sessão; nenhum upload foi realizado.

## Limites

CSV, Explorador, provedores externos, visitas, demais estados comerciais e vínculo com pré-cadastros pertencem às próximas fases.
As constraints desta fase aceitam somente origem manual; habilitar novas origens exige evolução explícita, elegibilidade contratual e testes, sem rotulá-las como coleta manual.
Todos os administradores ativos podem operar prospects, seguindo o alcance administrativo atual; atribuição de responsável não é isolamento de carteira.
Cadastro e contato permitido não constituem consentimento para marketing. Nenhum envio de e-mail, mensagem ou consulta externa faz parte desta fase.
Finalidade, retenção e direitos relativos aos dados de prospecção ainda dependem de definição e aprovação antes de uso público/operacional com dados reais.
