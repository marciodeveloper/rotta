# Cadastro assistido: Buscar → Preencher → Revisar → Salvar

## Interface e contrato

A tela foi reduzida a resumo, formulário original e ações. Não existem campos duplicados nem confirmações individuais de proveniência. “Origem dos dados” fica recolhido e é opcional na operação normal. O cadastro exige nome, cidade e UF válidos; enriquecimento e origem externa não são requisitos. Dados manuais são declarações do administrador, sem confirmação externa presumida.

- Abrir a tela não consulta fornecedores nem consome quota.
- “Consultar resumo Google” faz uma chamada básica Pro, sem preencher inputs.
- “Buscar dados e preencher cadastro” procura primeiro uma ficha CRM já vinculada e, para novas referências, uma fonte independente autorizada. Uma chamada Google só complementa uma fonte que retornou dados ou atende a uma consulta Enterprise explicitamente solicitada. Sem fonte/dados e sem Enterprise, não consome consulta Google. Somente dados independentes preenchem o formulário.
- O resumo informa campos preenchidos, pendentes e divergentes com contagens reais. Zero dados disponíveis produz “Nenhum dado disponível para preenchimento automático. Motivo: ...”, sem sucesso fictício. Valores já existentes ou divergentes não são contados como novos preenchimentos. Valores manuais são preservados; cada divergência permite manter ou substituir explicitamente.
- Dados Google e dados independentes aparecem em áreas visualmente separadas. Ausência de fonte, licença ou identidade suficiente produz mensagem clara e mantém o cadastro manual disponível, sem fingir preenchimento.
- Nenhuma pesquisa ocorre a cada tecla, ao abrir o formulário ou após erro automaticamente.

## Google básico e Enterprise

A pesquisa existente não foi alterada. Details básico usa `id`, `displayName`, `formattedAddress`, `addressComponents`, `primaryType`, `primaryTypeDisplayName`, `types`, `businessStatus`, `googleMapsUri` e `attributions`.

O administrador de configuração pode habilitar `GOOGLE_PLACES_ENTERPRISE_ENABLED=true`. O padrão é `false`; a configuração local real foi preservada desabilitada. Quando habilitado, a tela oferece uma opção explícita de consulta Enterprise com aviso de cobrança. Uma única requisição acrescenta `nationalPhoneNumber`, `internationalPhoneNumber`, `websiteUri`, `regularOpeningHours`, `rating` e `userRatingCount`. A seleção nunca afeta a navegação ou o endpoint de detalhes básico. Não são solicitados `reviews`, fotos ou `*`.

Nota, quantidade de avaliações, horários, telefone e website Google são exclusivamente temporários. A nota é apresentada com atribuição Google Maps, data/hora e link Maps. Não há colunas nem cache para reputação. As coordenadas continuam não solicitadas.

Cada tentativa Google passa pelo mesmo orçamento existente, inclusive Enterprise e falhas: 5 resultados/página, 2 páginas, 3 operações/minuto por administrador, 10/dia por administrador e 20/dia globais no ambiente local. A página apenas avisa antes de executar; não é feita chamada faturável para mostrar o aviso. Não há retry automático.

Pela tabela global consultada em 17/11/2026, na primeira faixa paga Details Pro custa US$ 17/mil eventos e Enterprise US$ 20/mil, com franquias publicadas distintas. O faturamento usa o maior SKU solicitado; não soma uma chamada Pro a outra Enterprise na ação ampliada. O valor efetivo depende da conta, consumo agregado, impostos e contratos. As duas leituras do website (robots e página) não são eventos Places.

## Fontes independentes e permissão

Não existe permissão genérica para extrair todos os websites. Uma informação ser pública, aparecer no Google ou ser visualmente confirmada não prova licença de armazenamento. O backend usa um registro de fontes revisado no servidor, vinculado ao Place ID. Não aceita URL de destino enviada pelo navegador nem utiliza automaticamente o website retornado pelo Google.

`BUSINESS_ENRICHMENT_SOURCES` contém um objeto JSON. Exemplo **exclusivamente sintético**, que não deve ser usado para afirmar uma autorização real:

```dotenv
GOOGLE_PLACES_ENTERPRISE_ENABLED=false
BUSINESS_ENRICHMENT_SOURCES='{"ChIJ_EXEMPLO_SINTETICO":{"url":"https://empresa.example.invalid/contato","expected_name":"Estabelecimento sintético","storage_permitted":true,"identity_verified":true,"permission_reference":"Referência da autorização real, a preencher somente após verificação"}}'
```

Cada entrada exige URL HTTPS, identidade comercial verificada e referência de permissão de armazenamento. A identidade esperada deve vir da verificação independente, não de cópia do resultado Google. Não configure `true` sem evidência. As permissões precisam cobrir a utilização pretendida, inclusive links sociais, e devem ser revistas/revogadas no registro quando necessário.

A fonte atual é uma página oficial autorizada com dados estruturados JSON-LD de estabelecimento/organização. Endereço é opcional: sua ausência não impede nome, website e contatos inequívocos publicados em links tel:/mailto:. O nome deve corresponder à identidade configurada e só pode haver um objeto compatível. Não há IA, scraping Google/Instagram, execução de JavaScript remoto, leitura arbitrária de PDFs nem busca genérica em redes sociais. Websites sem os dados estruturados suportados permanecem parcialmente manuais. Outros provedores licenciados não foram inventados ou contratados.

A configuração real de desenvolvimento não possui fontes autorizadas cadastradas neste momento. Portanto, o preenchimento automático real depende desse cadastro; os testes demonstram o fluxo completo com uma fonte sintética explicitamente autorizada.

## Mapeamento independente

| Campo CRM | Informação da fonte autorizada |
| --- | --- |
| `business_name` | `name` do objeto com identidade verificada |
| `category` | PropertyValue `Segmento`, quando publicado |
| `city`, `state`, `postal_code` | `addressLocality`, `addressRegion`, `postalCode` |
| `street`, `street_number` | `streetAddress`; separação somente no padrão inequívoco `logradouro, número` |
| `street_number`, `complement`, `neighborhood` | PropertyValue explícito `Número`, `Complemento`, `Bairro` |
| `phone`, `email` | `telephone`, `email`, sujeitos às regras existentes |
| `website` | Origem HTTPS da fonte oficial configurada |
| `instagram` | Perfil público publicado no próprio site, com regras abaixo |

Telefone e CEP são normalizados para dígitos, UF para maiúsculas, Instagram para URL de perfil sem rastreamento. Não são inferidos CNPJ, proprietário, e-mail ausente, país, horário ou contatos. Valores incompatíveis com as regras do CRM não são preenchidos. Campos internos (`notes`, `priority`, `assigned_to`) continuam manuais; não vêm do Google ou do website.

## Descoberta de Instagram

Após validar fonte e identidade comercial, a aplicação procura `sameAs` e links da página. Aceita somente URLs HTTPS de perfil `instagram.com`, excluindo posts, reels, stories, contas e outros caminhos de navegação. Remove parâmetros e normaliza o identificador. Havendo mais de um perfil distinto, não escolhe arbitrariamente: mantém o campo pendente. A validação é evidência do vínculo publicado no website autorizado; não é confirmação de titularidade pela Meta. O Instagram nunca é consultado pelo backend.

## Segurança de rede

A consulta usa HTTPS/443, valida todos os registros A/AAAA e bloqueia IPs privados/reservados, loopback, metadata, IP literal e respostas DNS mistas. Respostas DNS com IPv4 e IPv6 públicos são aceitas, preferindo IPv4. IPv6 público isolado também é suportado, com endereço entre colchetes no pin de `CURLOPT_RESOLVE`. IPv6 privado, local, mapeado e reservado continua bloqueado. A conexão é fixada no IP validado, sem proxy e com validação TLS padrão.

Não segue redirecionamentos, mesmo públicos; o cadastro da fonte deve apontar à URL final. Consulta `robots.txt` antes da página e respeita conservadoramente qualquer `Disallow` compatível, inclusive de outros agentes. Falha de leitura impede prosseguir, exceto 404 de robots. Não interpreta toda a semântica opcional de robots: pode recusar sites que seriam permitidos.

Time limits: conexão 2s, resposta 5s por leitura, leitura 3s; limite 32 KiB para robots e 256 KiB para HTML. O progresso da transferência interrompe respostas excessivas, inclusive sem Content-Length; a leitura do corpo também é limitada. Aceita somente tipos de conteúdo previstos, usa DOM sem rede e não executa scripts. Não guarda HTML bruto.

O endpoint requer autenticação, autorização, CSRF, confirmação de custo e rate limiting já existente. Quando a quota Google falha, uma fonte independente autorizada ainda pode fornecer dados, sem nova tentativa Google. O endpoint não funciona como proxy arbitrário: o único destino de website é o previamente configurado para aquela referência.

## Proveniência, gravação e recuperação

O backend emite um comprovante criptografado temporário vinculado ao administrador, referência, valores normalizados, fonte e SHA-256 do conteúdo independente. Esse comprovante contém apenas dados independentes permitidos, nunca resposta Google. Não são criadas tabelas novas.

Na gravação, valores que continuam correspondendo ao comprovante recebem a origem verificada e o checksum de evidência. Campos alterados ou acrescentados recebem origem de declaração manual, sem serem automaticamente marcados como confirmados por fonte independente. Isso não certifica automaticamente a veracidade da declaração manual. Proveniência arbitrária enviada pelo navegador no modo simples é ignorada e reconstruída no serviço compartilhado.

A action existente mantém `CreateProspect`, deduplicação, bloqueios, transação, concorrência e idempotência. Usa `prospect_sources` com origem criptografada, mapeamento de campos em `confirmed_fields`, autoria/data e histórico. Não modifica prospects já existentes. Um reenvio com dados ou proveniência divergentes é rejeitado, sem duplicação.

O comprovante expira em 15 minutos. Após expiração, a tela pede nova consulta. Erros preservam os campos e o token antigo para revisão; não executam uma consulta paga automaticamente. Não há cópia integral de Google Places para o banco. Tal uso dependeria de permissão contratual específica não fornecida nesta implementação.

## Homologação local

1. No CRM de desenvolvimento encaminhado pela porta 18080, abra Explorador → referência não vinculada → Iniciar cadastro.
2. Confirme que não existe painel paralelo de proveniência nem consulta automática na abertura.
3. Com uma fonte independente realmente autorizada configurada no servidor, clique Buscar dados e preencher cadastro. Sem fonte, a mensagem deve explicar a ausência e preservar a entrada manual.
4. Confira os campos, Instagram e divergências. Escolha manter ou substituir somente quando necessário; Origem dos dados pode permanecer fechado.
5. Se Enterprise estiver habilitado e houver autorização de consumo, selecione a opção antes de buscar. Confira nota/quantidade e atribuição no resumo, sem persistência da reputação.
6. Teste erro de validação, retorno, correção e salvamento usando exclusivamente dados sintéticos locais. Confirme fontes, autoria e Place ID na ficha.

Fontes oficiais consultadas:

- https://developers.google.com/maps/documentation/places/web-service/policies
- https://developers.google.com/maps/documentation/places/web-service/place-details
- https://developers.google.com/maps/billing-and-pricing/pricing

A implementação não constitui parecer jurídico nem prova de permissão para um website específico. O preenchimento real e a consulta Enterprise dependem de configuração e homologação controlada; os testes automatizados e de Chromium usam mocks, sem chamadas faturáveis.

## Evidência desta execução

- PostgreSQL 17 isolado: **266 testes PHP, 1.706 asserções — PASS**.
- JavaScript: **37 testes — PASS**; Vite, Pint e diff check — PASS.
- Chromium 1440×900 e 390×844: preenchimento de 12 campos com 1 divergência manual preservada, resolução explícita, Instagram, reputação sintética, CSRF 419, recuperação de validação e salvamento — PASS. Sem controles de origem por campo, campos duplicados ou POST ao pré-cadastro.
- Capturas anteriores e atuais foram comparadas visualmente; a nova tela mantém identidade e formulário comercial, com Origem dos dados recolhido.
- Todo fornecedor foi simulado; fontes de rede externas do navegador foram bloqueadas no QA. Nenhuma chamada faturável real, alteração em produção, migration adicional, commit, push ou deploy.
- A consulta Enterprise e a obtenção independente reais permanecem pendentes de configuração/autorização específica das fontes e homologação controlada.

## Diagnóstico de retorno vazio — execução posterior

A inspeção do ambiente real de desenvolvimento encontrou zero fontes independentes configuradas, Enterprise desabilitado, três referências salvas e nenhuma vinculada a prospect. Há registros verificados no CRM, mas não existe associação inequívoca deles às referências; nomes semelhantes não autorizam reutilização ou fusão automática. Não há outro provedor comercial licenciado integrado ao fluxo.

O endpoint POST `/admin/prospeccao/explorador/referencias/{reference}/enriquecer` retornava `fields: []` corretamente, enquanto o frontend sempre dizia “Cadastro atualizado”. A causa principal é configuração ausente e restrição legítima de uso dos dados Google, somadas a mensagem incorreta. Não foi demonstrado descarte de valores válidos pelo JavaScript. O website Google não é descoberto pela máscara Pro, que não solicita `websiteUri`; sua ausência nessa consulta não prova que o estabelecimento não possui website.

A correção adiciona `outcome` e `reason_code`, cancela consultas básicas inúteis quando não existem dados independentes, não emite prova expirada para resultado vazio e mantém o formulário manual utilizável. Uma ficha já vinculada é oferecida para reutilização, com autorização e auditoria, sem criar clone. Não existe casamento automático por nome.

Foram corrigidos dois bloqueios excessivos do leitor autorizado: DNS dual-stack público e Organization sem endereço. Contatos únicos por tel:/mailto: são aceitos apenas depois da verificação da identidade e permissão da fonte. Não se escolhe arbitrariamente entre contatos conflitantes.

O único link apresentado para homologação real foi Google Maps (CID), não um website independente acompanhado de permissão de armazenamento. Ele não foi usado para scraping nem tratado como licença. Nenhuma fonte real foi configurada, consultada ou declarada aprovada a partir desse link.

- Cenário A (preenchimento com fonte independente real): **PENDENTE**, falta fonte oficial/licenciada e evidência de autorização. Os testes positivos com fonte sintética não substituem esse gate.
- Cenário B (fonte ausente): executado em Chromium desktop/mobile com a mesma condição de configuração ausente do desenvolvimento, em base privada sintética. O tratamento da requisição não usou respostas simuladas de provedores; chamadas externas foram bloqueadas por precaução e nenhuma foi necessária. Resultado vazio específico, nenhuma falsa atualização e cadastro manual funcional.
- Cenário C (divergências e recuperação): executado com fonte simulada, sem sobrescrita silenciosa; não é homologação de fonte externa real.

O preenchimento real não é considerado homologado até que o Cenário A seja comprovado. Permissões, licença, configuração do provedor e homologação controlada permanecem gates para produção. Nenhuma chamada faturável foi executada, Enterprise não foi habilitado, produção e credenciais não foram alteradas.

## Homologação real autorizada — Barber Shop Alemão

Em 17/11/2026, o usuário informou `https://bsaprotesecapilar.com.br/`, indicou `barbershopalemao` e confirmou ser responsável ou estar autorizado a permitir consulta e armazenamento dos dados comerciais. O website confirmou o nome Barber Shop Alemão e o vínculo público com esse Instagram. Seu robots.txt permite a página inicial.

O cenário A foi executado em Chromium 1440×900 e 390×844 contra o backend Laravel, com leituras HTTPS reais de robots.txt e da página inicial. Não houve `Http::fake` nesse fluxo. O Google foi desabilitado no ambiente de QA; somente o domínio autorizado pôde receber requisições. Uma referência explicitamente de QA foi utilizada em PostgreSQL isolado; ela não é um Place ID Google verificado e não deve ser migrada para desenvolvimento ou produção.

Resultado real: **8 campos preenchidos** — nome, cidade, UF, CEP, endereço/logradouro integral, telefone, website e Instagram. Foram comprovados revisão, preservação de edição manual, resolução de divergência, retorno de validação preservando dados/comprovante e salvamento com fonte independente no banco isolado. E-mail, segmento, número, complemento e bairro ficaram pendentes: e-mail/complemento não estavam disponíveis em campos suportados; categoria não foi mapeada; número e bairro estão no endereço integral, sem separação automática neste formato.

O teste real revelou e corrigiu falhas não alcançadas por respostas simuladas: erro DNS não tratado (500), combinação incompatível de streaming com CURLOPT_RESOLVE e uso de CURLOPT_PROXY não aceito pelo Guzzle instalado. O transporte agora usa cURL com IP fixado, proxy desabilitado via opção Guzzle, tamanho limitado por progresso/leitura e conteúdo comprimido não decodificado/recusado. A validação TLS e as proteções SSRF permanecem ativas.

Também foi corrigida a adaptação do telefone brasileiro com +55 à máscara nacional existente, evitando perda de dígitos. A equivalência entre formas com e sem código de país conserva a proveniência verificada, sem alterar a máscara do formulário público.

A leitura/preenchimento reais desta fonte estão homologados no ambiente isolado. Para ativar o mesmo preenchimento na referência efetivamente salva no CRM de desenvolvimento, falta identificar seu UUID ou Place ID Google correto. A fonte não foi associada arbitrariamente a uma das referências existentes, nem ao Restaurante Varandas. Enterprise, credenciais, infraestrutura de produção e dados de produção não foram alterados.

Validação final da homologação real BSA: **268 testes PHP, 1.723 asserções; 38 testes JavaScript; Vite, Pint e diff check aprovados**. As duas sessões Chromium realizaram oito leituras HTTPS no domínio autorizado (robots e página, duas consultas por viewport), sem resposta simulada e sem chamadas Google. Evidências locais em `/tmp/rotta-bsa-real-qa-20261117/`. O ambiente real de desenvolvimento e suas credenciais não foram alterados; o vínculo à referência verdadeira aguarda sua identificação.

### Vínculo confirmado no desenvolvimento

Após o usuário informar o Place ID `ChIJkQwlDhy1ngARDWgsSPxqKb0`, a referência existente `01a1e864-c880-714a-a56b-df92e3953376` foi identificada sem vínculo com prospect. A fonte autorizada BSA foi configurada no `.env` local montado do ambiente de desenvolvimento, preservando a chave e as demais variáveis. O leitor real desse ambiente retornou os mesmos oito campos e confirmou o Instagram. Nenhuma chamada Google, alteração de produção ou criação de prospect foi necessária nesta configuração. A pendência anterior de identificação da referência está resolvida.

## Listagens, paginação e cadastro universal — entrega atual

### Causas e correções

- O cliente mantinha somente `places` e o cursor atual; avançar substituía a página anterior. Não havia botão Anterior e o pagehide apagava os dados. Agora ExplorerPageHistory mantém no máximo as duas páginas da pesquisa ativa em memória do documento. Página anterior e próxima já visitada não fazem outra chamada Google; termo novo descarta páginas/cursors. Erro na próxima página mantém a atual e permite tentativa explícita. Parâmetros seguem no cursor assinado existente.
- A listagem persistida já paginava dez itens, mas faltavam tamanho configurável, filtros e controles completos. Agora usa paginação SQL de 10/25/50, total real, ordenação por created_at/id, primeira/anterior/próxima/última, filtros por situação e identificador/nome permitido. Página fora do intervalo é ajustada. As alterações da lista são carregadas por Fetch sem descartar a pesquisa Google ativa.
- Nomes vêm do prospect vinculado ou de metadados independentes autorizados já configurados. Place ID fica secundário/truncado, com cópia integral. Sem nome permitido, apresenta Nome não disponível e consulta explícita. Não consulta Google nem websites para montar a lista. Possível duplicidade é apenas um alerta baseado em nome independente disponível, sem fusão ou desbloqueio. O próprio prospect vinculado não reaparece como sua duplicidade.
- Botões primários/secundários substituem ações ambíguas. A tabela Google tem largura delimitada e ações com espaço próprio; mobile apresenta linhas como cards, sem rolagem horizontal. Estilos são restritos ao Explorador.

### Estado e navegação

Conteúdo Google não é salvo no PostgreSQL, localStorage, sessionStorage ou cache de servidor. Permanece somente na memória da página ativa. Voltar do formulário utiliza histórico nativo quando disponível; filtros persistidos ficam no URL e são validados no retorno. Em restauração BFCache, metadados CRM podem ser atualizados pelo endpoint administrativo local, sem Google. Nesta execução Chromium/automação não restaurou BFCache: a navegação criou novo documento e exibiu a instrução para refazer a pesquisa, com aviso de possível quota. Não se alega preservação dos resultados após recarregamento completo.

### Três fluxos de cadastro

1. Manual direto: Novo estabelecimento em Prospecção e também no Explorador. Nome/cidade/UF mais token técnico de idempotência/CSRF bastam; prioridade possui padrão existente. Não cria referência nem chama Google.
2. Referência sem enriquecimento: mantém Place ID e aceita os mesmos dados mínimos. Sem website, telefone, Instagram, licença/provedor configurado ou consulta bem-sucedida, o salvamento continua disponível. O resumo Google é uma consulta explícita separada; visualizar não preenche inputs. Prova expirada/inválida continua sendo recusada como origem verificada, mas a interface oferece descartar a prova e prosseguir como declaração manual preservando valores.
3. Referência com fonte autorizada: mantém o preenchimento real BSA, origem, checksum e confirmações apenas nos campos correspondentes à prova válida. Campos editados ou manuais ficam em `declared_fields` e não recebem confirmação independente. A API legada de proveniência explícita mantém suas validações; o modo simples reconstrói e valida a origem no domínio.

Os três caminhos preservam autenticação, autorização, CSRF, idempotência, deduplicação, bloqueios, criptografia e auditoria. Não foram alterados schema, dados existentes ou o pré-cadastro público.

### Componentes e evidências

Chromium desktop 1440×900 e mobile 390×844 comprovou uma única instância de logradouro, Observações, formulário e rodapé, com rodapé estático. Não foi reproduzida duplicação no DOM. Capturas full-page durante scroll/foco podem deslocar visualmente o link fixo de acessibilidade; foram incluídas capturas de viewport e não se alterou o layout público para mascarar o efeito.

A navegação Google foi exercitada com cinco resultados na primeira página e três na segunda, falha transitória seguida de recuperação, clique duplo, 1→2→1→2, fechamento de detalhes, referência salva preservada, nova pesquisa, página única, lista vazia e quota. As duas restaurações consumiram zero consultas adicionais; os três POSTs observados incluem uma falha deliberada e sua tentativa explícita.

A fonte BSA foi consultada novamente de verdade no ambiente isolado com a autorização já concedida: oito campos, Instagram verificado, edição preservada, retorno após erro e salvamento. Nenhuma resposta desse website foi simulada, e nenhuma chamada Google/Enterprise real foi feita. O caso sem fonte foi executado sem respostas simuladas de fornecedores e também com consulta Google explicitamente simulada para verificar separação de visualização e persistência. O cadastro totalmente manual foi salvo em ambos os viewports.

Fontes alternativas: não há provedor comercial licenciado adicional configurado. A permissão para o website BSA não se estende a outros sites; não se infere licença de links públicos ou de resultado Google. Descoberta geral com armazenamento autorizado depende de fornecedor/licença e identificação confiável. Essa ausência não bloqueia cadastro manual.

### Homologação humana

1. Abra `/admin/prospeccao/explorador` no desenvolvimento. Faça uma busca explícita e navegue Próxima → Anterior → Próxima; as páginas visitadas devem ser restauradas sem nova consulta.
2. Confira nomes, Copiar ID, situações e botões na lista salva. Teste 10/25/50, filtros e primeira/última página. Esses controles não consultam Google.
3. Abra uma referência sem fonte; consulte o resumo Google somente se desejar consumir quota. Preencha apenas nome/cidade/UF e salve, sem website ou prova externa.
4. Use Novo estabelecimento para o fluxo sem Google. Para BSA já vinculada, Abrir ficha é o comportamento correto; não criar outra referência ou prospect duplicado para repetir o teste.
5. Após recarregar/navegar com documento novo, a pesquisa temporária pode precisar ser refeita explicitamente. Os filtros da lista persistida devem continuar no URL.

Sem commits, push, PR ou deploy. Enterprise continua desabilitado no desenvolvimento real; limites 5/2/3/10/20 e FieldMasks preservados. As referências/volumes de QA ficaram em banco isolado, sem alterações de produção.

## Fontes alternativas sem website — 17/11/2026

A limitação anterior era de implementação: `EnrichProspectReference` consultava apenas `AuthorizedBusinessWebsite`, indexado pelo Place ID. A permissão previamente revisada do website continua necessária; acesso público ou robots.txt permissivo não concede licença de armazenamento. Não havia adaptador cadastral alternativo. Isso foi separado da restrição contratual de não copiar conteúdo Places.

### Fontes e escolha de identidade

- CRM: nome, cidade e UF declarados no formulário permitem localizar uma ficha própria possível. Uma correspondência única oferece abrir a ficha; não copia dados, não funde registros nem remove bloqueios. Homônimos/filiais não são resolvidos automaticamente.
- Website autorizado: fluxo e evidências existentes preservados, inclusive BSA. Sem CNPJ informado, a consulta continua usando a fonte já aprovada.
- Dados cadastrais públicos: `PublicCompanyData` consulta `https://minhareceita.org/<CNPJ>` por HTTPS, somente mediante ação explícita. É fonte secundária dos dados abertos RFB, não conexão direta à Receita Federal. Não exige website, chave ou serviço Google. O CNPJ é opcional, validado, e a resposta deve corresponder exatamente ao documento solicitado.
- A API não fornece busca por nome comercial. Não se varre uma cidade inteira nem se escolhe CNPJ por similaridade. Após a consulta, uma revisão única da empresa/unidade antecede o preenchimento. Confirmação usa token criptografado, vinculado a administrador, referência, finalidade e expiração de 15 minutos; não repete a consulta externa. Nenhum cadastro é criado pela consulta.
- Fontes licenciadas: nenhum contrato/credencial adicional foi identificado ou presumido. SERPRO é uma alternativa de integração oficial por contratação; Conecta tem elegibilidade institucional própria. Não foram habilitados serviços pagos nem criado um adaptador fictício.
- Informações fornecidas pelo estabelecimento continuam disponíveis pelo cadastro manual e pela identificação de origem existente. Não recebem verificação independente fictícia.

### Dados, segurança e consumo

O adaptador público aceita nome fantasia/razão social, segmento CNAE, cidade/UF e componentes de endereço compatíveis com o schema existente. Aplica normalização e validação do domínio; valores ausentes permanecem vazios. Telefones/e-mails da base CNPJ não são importados automaticamente, pois não se comprova que sejam contatos comerciais e podem ser pessoais. Não utiliza os dados de sócios recebidos na resposta, nem consulta grafos, Instagram ou avaliações; não envia QSA/CPF/resposta bruta ao navegador ou à persistência. Instagram, telefone e website continuam preenchíveis pela fonte independente apropriada, como BSA.

As fontes cadastrais são registradas como `public_data`, com URL de origem, evidência SHA-256 dos campos permitidos e autoria. Isso descreve a origem, não comprova titularidade nem atualidade. A prova de uma fonte é distinta da escolha de identidade feita pelo administrador. CSRF, autorização, deduplicação, bloqueios, criptografia e idempotência do salvamento continuam nos serviços existentes.

As requisições públicas têm orçamento separado `public-company`, usando os limites configurados 3/minuto/admin, 10/dia/admin e 20/dia/global quando assim configurados, além do throttle HTTP já existente. Não consomem o contador Google. Há timeout, limite de leitura de 128 KiB e destino fixo, sem redirecionamentos. Erro, resposta inválida, ausência de identificação e excesso de quota não impedem o cadastro manual. A API pública não tem SLA. CNPJ alfanumérico continua aceito nas regras existentes do produto, mas este fornecedor de enriquecimento está limitado a CNPJ numérico e não bloqueia cadastro manual dos demais.

### Homologação real e limitações

Chromium desktop/mobile executou consulta real, sem mocks, para o CNPJ candidato 64.104.213/0001-46: oito campos disponíveis, sete aplicados e logradouro manual mantido como divergência. Dados retornados incluíram PADARIA MANDARO, VASSOURAS/RJ e endereço cadastral. O teste confirmou recuperação após validação; não criou ficha real. A pesquisa pública também encontrou associação histórica a outro CNPJ; isso não comprova continuidade empresarial, identidade atual ou qual unidade deve ser vinculada ao Place ID. Essa decisão permanece para o administrador, mediante confirmação independente.

BSA foi consultada novamente no website autorizado: oito campos reais, Instagram, edição preservada e salvamento no banco isolado. O caso sem fonte e o manual direto passaram em desktop/mobile; listagens e paginação também foram reexecutadas. Sem fonte ou identificador seguro, nenhum dado é inventado e o cadastro com nome/cidade/UF permanece disponível. Não há promessa de enriquecimento universal por nome.

Documentação dos fornecedores revisada: https://docs.minhareceita.org/ ; https://docs.minhareceita.org/como-usar/ ; https://docs.minhareceita.org/sobre-os-dados/ ; https://developers.google.com/maps/documentation/places/web-service/policies . API pública aberta/gratuita não dispensa finalidade, minimização, LGPD ou aprovação jurídica da operação comercial. Não se pressupõe licença especial Google: eventual contrato de armazenamento permanente precisaria ser concedido expressamente pelo fornecedor, com condições e preço próprios.

### Homologação local

Abra uma referência no Explorador, informe o CNPJ que você identificou independentemente, clique em Buscar dados e preencher cadastro e confira o candidato. Use Confirmar empresa e preencher apenas para a empresa/unidade correta; revise divergências e salve pelas regras normais. Sem CNPJ/fonte, use Continuar cadastro manual. Se a BSA já estiver vinculada, abra a ficha existente, sem tentar duplicá-la. Nenhuma variável ou chave nova é necessária para a consulta pública; Google Enterprise permanece desabilitado.

## Preenchimento Google explícito e identificação temporária — 17/11/2026

### Decisão e limites contratuais

A administração solicitou expressamente preencher os inputs com conteúdo Google e manter a origem verdadeira, reconhecendo que a confirmação administrativa não é uma licença Google. A implementação anterior restringia o preenchimento ao serviço independente; o resumo Google não possuía ligação com os inputs. A lista persistida, por sua vez, não consultava a memória dos resultados e apresentava falta de nome mesmo logo após salvar um Place ID.

1. Licença padrão: Place ID pode ser armazenado; exibição de conteúdo exige atribuição. Não se presume autorização para copiar nome/endereço/categoria ou outros conteúdos Places para uma base CRM permanente. Referência oficial revisada: https://developers.google.com/maps/documentation/places/web-service/policies e https://cloud.google.com/maps-platform/terms/maps-service-terms .
2. Solicitação administrativa: ação explícita Preencher com dados do Google, com revisão e proveniência, implementada em desenvolvimento. Não é descrita como contratualmente regular nem disfarçada como fonte independente.
3. Risco da persistência: armazenar conteúdo Google pode contrariar o contrato padrão. Flags, confirmação e auditoria não eliminam esse risco nem substituem permissão do fornecedor. Não foi apresentada uma autorização específica do Google.
4. Publicação regular: exige análise jurídica e concessão contratual aplicável ao armazenamento e finalidade CRM, eventualmente negociada com Google Sales, com condições e preço próprios; nenhuma modalidade ou valor é presumido. A decisão de publicação é separada desta implementação. Não houve deploy.

### Configuração e fluxo

`GOOGLE_PLACES_IMPORT_ENABLED=false` controla o preenchimento e é conferida no backend. `GOOGLE_PLACES_PERSISTENCE_ENABLED=false` bloqueia a gravação pelo caminho de importação, independentemente do botão visível. Os padrões e `.env.example` mantêm ambas desabilitadas. Somente o arquivo de ambiente do desenvolvimento existente (`/tmp/rotta-admin-evolution-qa.env`, montado no container local) recebeu ambas como true para homologação; não foram alteradas credenciais ou configurações de produção. Enterprise permanece false.

O botão pode obter Details e preencher em sequência; se o resumo já tiver sido consultado, reutiliza um snapshot mínimo criptografado recebido pelo navegador, com administrador/Place ID/finalidade/expiração de 15 minutos verificados no backend. Não armazena resposta bruta, não usa cache duradouro nem faz outra chamada ao Google ao reutilizar o snapshot. Alteração explícita para Enterprise só reutiliza snapshot Enterprise; uma consulta básica anterior não é suficiente para obter telefone/website. Enterprise exige configuração e ação de custo existentes. Tokens expirados falham sem gerar uma nova chamada automática.

O mapeamento usa `displayName.text → business_name`, `primaryTypeDisplayName.text → category`, `locality → city` com fallback municipal `administrative_area_level_2`, UF por `administrative_area_level_1.shortText`, CEP/route/street_number/sublocality → seus campos correspondentes. Telefone nacional e website somente quando retornados pela consulta Enterprise autorizada. Não se usa formattedAddress para inventar componentes, nem se inventam e-mail ou Instagram. Cada candidato passa pela normalização e pelas regras existentes do CRM.

Valores manuais são preservados; divergências exigem escolha. A contagem representa apenas inputs alterados. Campos aplicados são identificados como Google Places; edições posteriores desses campos conservam a origem Google com marca de edição no histórico, sem alegar verificação independente. Uma confirmação de origem/risco antecede o salvamento. O caminho normal conserva deduplicação, bloqueios, idempotência, CSRF, autorização e vínculo com a referência.

Google usa token próprio, separado da prova de website/CNPJ. O backend verifica referência, administrador, finalidade, prazo e whitelist de campos. Fontes Google ficam identificadas como `google_places`, com origem, evidência, autoria, referência e datas. `verified_at` permanece nulo e os campos não são promovidos a `confirmed_fields` como dados independentemente verificados. O histórico distingue `field_sources`, `field_origins` (incluindo manual), `declared_fields`, data da consulta e estado contratual não regularizado. O valor manual mantido em uma divergência não é rotulado automaticamente como Google.

Os controles cobrem o fluxo suportado; não se afirma detectar automaticamente conteúdo que uma pessoa copie por fora da aplicação e declare manualmente. As provas de fontes independentes permanecem separadas, e o backend não reclassifica a prova Google como independente.

### Nomes nas referências

A prioridade é nome da ficha vinculada, nome independente autorizado e então nome Google disponível em memória da interface. O mapa temporário usa Place ID exato; nomes semelhantes não criam associação. Salvar envia somente o token de identificação, preserva o nome local e atualiza a lista sem outra consulta Google. Paginação/filtros da lista reaplicam os nomes temporários após trocar o fragmento HTML. Consultar detalhes sob demanda atualiza o nome local sem salvar outra referência.

No recarregamento completo, não há recuperação automática ou em massa: nomes permitidos persistidos são renderizados pelo servidor e os demais apresentam Estabelecimento não consultado, com ação explícita Consultar no Google. Place ID segue secundário/copiável. O nome Google temporário aparece com sua origem e atribuição, sem nova coluna de banco, localStorage ou sessionStorage. Nomes persistidos em um prospect pelo modo experimental estão sujeitos ao risco documentado acima; isso não transforma sua origem em dado próprio independente.

### Evidências e pendência real

Chromium 1440×900 e 390×844 executou clique real com provedor Google simulado: oito campos disponíveis, sete aplicados e cidade manual preservada; reuso do resumo sem segunda chamada ao provedor, confirmação obrigatória, retorno de validação e salvamento em PostgreSQL isolado com origem Google. A navegação dos nomes foi exercitada ao salvar, paginar resultados/referências, recarregar e consultar explicitamente.

Esses resultados não comprovam os dados atuais da referência real da Padaria Mandaro. Nenhuma chamada Google faturável foi executada nesta correção. Foi solicitada a URL da referência e autorização específica para uma única consulta básica; essa homologação real permanece pendente enquanto não houver resposta. BSA e CNPJ foram reexecutados com fontes reais já autorizadas, separadamente do cenário Google. Os dados externos da BSA/CNPJ não são apresentados como se fossem conteúdo Google.

### Homologação humana

No desenvolvimento existente, recarregue a tela para obter o bundle atual, abra a referência desejada e clique em Preencher com dados do Google. Isso pode consumir uma consulta básica, se não houver resumo válido já consultado nesta tela. Revise campos/divergências, confirme a origem e salve somente os registros de desenvolvimento pretendidos. Não é necessário informar CNPJ ou configurar website. Para testar apenas a transferência sem nova consulta ao provedor, consulte o resumo uma vez e depois acione o preenchimento.

No Explorador, pesquise, salve referências e confira os nomes imediatamente; navegue nas duas paginações. Recarregue e recupere um nome por Consultar no Google, sem salvar outra referência. Não há consulta automática de nomes na abertura da lista.

Validação desta entrega: suíte PHP completa em PostgreSQL 17 isolado — 286 testes, 1.893 asserções; JavaScript — 43 testes; Vite, Pint e diff check aprovados. Chromium cobriu múltiplas referências salvas com nomes, as duas paginações, perda/restauração explícita do nome após reload, transferência Google com valores editados preservados, dados sem CEP/telefone/website, confirmação e persistência isolada. Evidências: `/tmp/rotta-google-import-qa-20261117/`. Nenhuma nova migration foi adicionada nesta correção; as alterações anteriores da branch foram preservadas.

Arquivos desta correção: `.env.example`, `config/business_explorer.php`, `app/Services/BusinessExplorer.php`, `app/Services/GooglePlacesProvider.php`, `app/Services/ReferenceFieldProvenance.php`, `app/Services/CreateProspectFromPlaceReference.php`, `app/Http/Controllers/Admin/BusinessExplorerController.php`, `app/Http/Requests/Admin/CreateProspectFromReferenceRequest.php`, `routes/web.php`, `resources/js/business-explorer.js`, `resources/js/assisted-prospect.js`, `resources/views/admin/explorer/index.blade.php`, `resources/views/admin/explorer/assisted-reference.blade.php`, `resources/views/admin/prospects/form.blade.php`, `tests/Feature/Admin/BusinessExplorerTest.php`, `tests/js/business-explorer.test.mjs` e este documento. Fora do Git, apenas as duas flags do arquivo de ambiente local mencionado foram habilitadas; os scripts/evidências temporários foram mantidos fora do repositório.

### Conclusão da homologação real Mandaro — 17/11/2026

Após a autorização expressa para uma única chamada básica, foi utilizada a referência real de desenvolvimento `01a1e8d7-2596-7292-8133-d19fa50c8e24` (Place ID `ChIJo-44PSs3mQARh6isdD_ATsQ`). Chromium desktop 1440×900 clicou em Preencher com dados do Google no formulário efetivamente servido pelo ambiente local, com provedor real e Enterprise desabilitado. A consulta retornou Padaria Mandaro e aplicou oito campos: nome, segmento Padaria, cidade Vassouras, UF RJ, CEP 27700000, Rua João XXIII, número 250 e bairro Residência.

Chromium mobile 390×844 repetiu o clique utilizando o mesmo snapshot real criptografado, enviado ao backend como details_token para impedir uma segunda chamada faturável. Nenhum valor de teste substituiu o conteúdo Google. Foram observadas exatamente uma operação básica do provedor e zero submissões de cadastro. Inputs, URL da página, uma única aba, ausência de scroll horizontal e ausência de erros JavaScript foram verificados em ambos os viewports. Telefone, website e Instagram permaneceram vazios. A confirmação de origem permaneceu desmarcada e nenhum botão Salvar foi acionado.

Contagens de prospects, pré-cadastros e outbox permaneceram iguais às anteriores; a referência continua sem prospect vinculado. Somente uma sessão administrativa temporária de QA foi criada no desenvolvimento e removida ao terminar; nenhuma credencial foi exposta ou alterada. Não houve mudanças em código, commit, push ou deploy nesta validação. O resultado elimina a pendência de consulta real citada na seção anterior, sem eliminar a restrição contratual para persistência/publicação.

Evidências locais: `/tmp/rotta-mandaro-google-real-20261117/results.json`, `desktop-filled.png` e `mobile-filled.png` no mesmo diretório. O cenário E de persistência permanece comprovado apenas pelo PostgreSQL isolado da etapa anterior, conforme solicitado; nenhum estabelecimento real foi salvo para esta homologação.

## Timeout do Text Search, observabilidade e preservação de estado — 17/11/2026

### Diagnóstico comprovado

A mensagem original era produzida por GooglePlacesProvider ao capturar qualquer ConnectionException e criar PlacesUnavailable com HTTP 503. O Controller apenas devolvia esse código. Portanto, o bundle JavaScript apontado no navegador era o local de exibição/lançamento do erro, não prova de HTTP 503 upstream. A auditoria local contém falhas de search às 10:49:51, 10:51:07 e 10:52:47 UTC, sem errno/duração; a exceção original não era reportada e esses detalhes históricos não podem ser recuperados honestamente.

Uma primeira pesquisa real autorizada pelo fluxo normal reproduziu cURL 28: DNS 53,67 ms, conexão 54,52 ms e TLS 107,82 ms, sem primeiro byte/HTTP upstream até expirar o limite de 8 s. A transferência levou 8.049,13 ms, e o navegador 8.214 ms. A classificação agora é response_timeout com HTTP local 504 e upstream_status nulo. Correlação: f3e674d8-b1bf-407b-8c92-3112aad29a44. Não foi recebida uma resposta HTTP 503 do Google nessa reprodução.

Diagnósticos sem chave e fora dos métodos Places foram feitos a partir do container local: resolução A/AAAA disponível, raiz HTTPS por IPv4 retornou 404 com TLS verificado, IPv6 falhou imediatamente com errno 7 e seleção automática funcionou. POST à raiz respondeu em aproximadamente 0,8–0,9 s, tanto com cURL quanto com o cliente Laravel/Guzzle, com corpo enviado. A ausência de IPv6 não foi tomada como causa do timeout; não se forçou IPv4 nem se modificou DNS, TLS, proxy ou rede global. O diagnóstico comprova a espera de resposta no caminho autenticado do Text Search, não a causa interna de uma eventual lentidão no Google.

### Correção e limites

- HTTP Client: logs seguros places.http.started/completed e places.operation.started/completed registram correlação, início, operação, duração, tempos DNS/conexão/TLS/primeiro byte, bytes transferidos, status local/upstream, errno/categoria, tentativa e resultado de quota. Não registram chave, headers, query, Place ID, payload, resposta comercial ou mensagem crua da exceção. Context::scope mantém a correlação sem vazar contexto entre operações. Guzzle instalado 8.3.1 usa on_stats; dados faltantes não são inventados.
- Erros: timeout com errno 28 resulta em 504; DNS/conexão/TLS recebem categorias distintas; quota externa/interna recebe 429 com origem distinta; credenciais recusadas pelo upstream recebem 502 com orientação administrativa; resposta malformada recebe 502; 5xx upstream permanece identificado como indisponibilidade do provedor. HTTP já recebido é preservado mesmo quando a leitura do corpo expira. A resposta pública inclui apenas mensagem segura, error_code e correlation_id.
- Timeout: conexão mantém 3 s. Details mantém GOOGLE_PLACES_TIMEOUT_SECONDS=8. Text Search usa GOOGLE_PLACES_SEARCH_TIMEOUT_SECONDS=15, limitado a no máximo 15 s, reservando margem dentro do orçamento de 20 s do frontend. A alteração é específica, motivada pelo esgotamento de 8 s depois de conexão/TLS rápidos, sem alterar consultas de detalhes ou infraestrutura. O Fetch agora mantém o AbortController ativo também durante response.json(), não somente até os headers.
- Retry: nenhuma tentativa automática adicional. Cada ação despacha no máximo uma tentativa ao cliente HTTP e reserva quota antes dela; timeout ambíguo não é repetido silenciosamente. A recuperação é explícita. Não se adicionou backoff/jitter sem uma política de repetição justificada e autorizada. A contagem representa tentativas iniciadas no cliente, não afirmação de cobrança efetiva pelo Google.
- Quotas: o desenvolvimento estava com CACHE_STORE=array, perdendo contadores entre requisições. Somente seu arquivo local de ambiente foi alterado para database, usando cache/cache_locks existentes; guard recusa cache array/null para quotas fora de testing. Não houve migrations. Os contadores anteriores voláteis não representam um histórico de faturamento recuperável. As duas tentativas autorizadas após a correção incrementaram dia/global para 2 e os valores foram lidos em outro processo, comprovando persistência. Nenhuma quota foi ampliada ou reiniciada para permitir o teste.
- UX: uma nova pesquisa substitui resultados/histórico somente após sucesso. Falhas conservam texto, resultados e páginas anteriores; mudar o termo invalida a navegação por cursores da consulta anterior até restaurar o termo ou receber uma nova resposta. Cliques duplicados continuam bloqueados. Voltar/avançar páginas já visitadas não gera novas chamadas.

### Consulta real final e resultados

Após uma segunda autorização expressa, foi executada uma última Text Search pelo botão normal do Explorador, sem retry e sem Enterprise: HTTP Google 200 e local 200, cinco resultados, transferência de 1.220,78 ms, navegador de 1.436 ms, primeiro byte em 1.160,92 ms, 93 bytes enviados e 2.880 recebidos. Correlação: 424fd858-5e69-4d4b-9061-d61371782f50. A resposta abaixo de 8 s demonstra recuperação transitória; não prova que a ampliação para 15 s tenha sido a causa exclusiva da recuperação nem fornece garantia de latência futura.

Foram duas tentativas Text Search faturáveis individualmente autorizadas nesta missão: uma reprodução com 8 s e uma verificação com 15 s. Nenhum cadastro, pré-cadastro ou mensagem foi criado. As sessões temporárias usadas no desenvolvimento foram removidas. Desktop executou a consulta real e mobile exibiu o mesmo resultado por redimensionamento, sem outra requisição faturável.

Suíte PHP completa: 303 testes, 2.067 asserções PASS. A suíte focada final tem 17 testes/174 asserções PASS, incluindo timeout de conexão, TLS e resposta/corpo, fase desconhecida, DNS, TLS inválido, upstream 503/429/401/403, resposta malformada, recuperação explícita, limite de quota, falha inesperada, limites de tempo e proteção contra vazamento. JavaScript: 45 testes PASS. Vite, Pint e git diff --check PASS. Chromium desktop/mobile com falha simulada confirmou estado anterior preservado, envio duplo gerando uma única tentativa, erro com correlação, recuperação explícita e paginação 1→2→1→2 após recuperação. Nenhum dado fictício foi inserido em produção.

Evidências: `/tmp/rotta-timeout-qa-20261117/` (falhas simuladas e resumo), `/tmp/rotta-search-real-20261117/first-8s/` (primeira tentativa) e `/tmp/rotta-search-real-20261117/` (verificação final). O leitor Docker logs não retornou dentro de 8 s e foi encerrado; a análise utilizou a auditoria e o arquivo Laravel, sem reiniciar Docker/containers para obter logs. Novos eventos seguros persistem em storage/logs/laravel.log e na auditoria.

Arquivos desta correção: app/Exceptions/PlacesUnavailable.php, app/Services/GooglePlacesProvider.php, app/Services/BusinessExplorer.php, app/Services/PlacesQuota.php, app/Http/Controllers/Admin/BusinessExplorerController.php, config/business_explorer.php, .env.example, resources/js/business-explorer.js, tests/Feature/PlacesTransportTest.php, tests/Feature/Admin/BusinessExplorerTest.php, tests/js/business-explorer.test.mjs e este documento. No ambiente local externo ao Git, apenas CACHE_STORE=database e GOOGLE_PLACES_SEARCH_TIMEOUT_SECONDS=15 foram ajustados nesta missão; credenciais e Enterprise não foram alterados.

Parecer: APTO para revisão/publicação técnica controlada desta correção; não equivale a autorização de merge/deploy, aprovação contratual Google ou GO-LIVE geral. Riscos residuais: dependência da disponibilidade/latência externa e ausência de detalhes do erro histórico anterior à instrumentação. Permanecem sem alterações o cadastro assistido, CNPJ, website, deduplicação, bloqueios, registros e demais projetos da VPS. Não houve commit, push, PR ou deploy.

## Perfil de release de produção autorizado — 17/11/2026

Destino Git exclusivo: chore/prelaunch-release-readiness, a partir de feat/business-explorer-google-places. O perfil Compose de produção força GOOGLE_PLACES_IMPORT_ENABLED=false e GOOGLE_PLACES_PERSISTENCE_ENABLED=false, independentemente do ambiente local experimental. Enterprise continua false; pesquisa/Details/Place ID, fontes independentes e cadastro manual permanecem disponíveis. O perfil aprovado é 5 resultados, 2 páginas, 3 operações/minuto/admin, 10/dia/admin e 20/dia/global, Text Search 15 s, Details 8 s, cache database. LOG_LEVEL=info aplica-se somente ao app para conservar as métricas Places sanitizadas; worker/scheduler mantêm o nível anterior. A chave permanece somente no servidor e fora do Git.

Os arquivos de termos e complemento de privacidade foram comparados com as URLs já públicas e são idênticos; não se altera o aviso LGPD do pré-cadastro. A configuração protegida é lida em memória pelo mesmo procedimento Docker isolado da publicação anterior; não se grava configuração expandida ou secrets em logs/manifests. APP_KEY, pepper e credenciais PostgreSQL devem coincidir com o runtime anterior.

Única migration nova: 2026_11_17_042854_create_google_place_references_table. Cria tabela, FKs, índice de paginação, unicidade e checks de Place ID/vínculo. Não altera tabelas anteriores. O down() foi exercitado apenas no PostgreSQL isolado; rollback de produção mantém a tabela e reverte somente imagens app/web, recriando app, worker, scheduler e web com --no-deps, preservando PostgreSQL, volumes, mídias e históricos.

Antes de alterar produção: registrar imagens/containers/configuração por hashes, preservar tags exclusivas, criar e verificar backup consistente criptografado, ensaiar restauração privada, comparar os registros preexistentes por fingerprints sem registrar conteúdo. Aplicar somente a migration pendente. Conferir a imagem candidata, SHA e manifest Vite antes de trocar serviços. Revalidar HTTPS, /up, /ready, login e CRM autenticado por leitura, sem criar prospect, alterar status ou enviar mensagens. Smoke Google faturável exige autorização específica; as duas chamadas da missão de timeout não autorizam outra consulta de produção.

Esta autorização permite commits, push, PR, CI, merge e deploy técnico. Não permite publicação do modo experimental de persistência Google nem publicação/alteração automática de aviso jurídico. Relatório operacional e manifest de execução ficam protegidos fora do Git, com resultados finais registrados após a verificação da produção.
