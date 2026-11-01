# ROTTA

**Pessoas, lugares e histórias em movimento.**

Protótipo visual premium para o ecossistema ROTTA: descoberta de destinos, experiências, negócios locais, marketplace híbrido, histórias e oportunidades.

## Stack desta fase
- HTML semântico
- CSS com design tokens e responsividade
- JavaScript ES Modules, sem dependências externas
- GitHub Pages (deploy via GitHub Actions)

A decisão por uma base estática nesta primeira fase permite validar a experiência e a direção visual sem antecipar decisões de backend. A arquitetura de produção poderá evoluir separadamente quando os fluxos e requisitos estiverem validados.

## Rodar localmente
Por usar ES Modules, sirva a pasta com um servidor HTTP:

~~~bash
python -m http.server 8080
~~~

Acesse http://localhost:8080.

## Validar
Com Node.js 22 ou superior:

~~~bash
npm run check
~~~

## Publicar no GitHub Pages
1. Faça merge da branch de protótipo para main.
2. No GitHub, abra **Settings → Pages**.
3. Em **Build and deployment**, selecione **GitHub Actions**.
4. O workflow em .github/workflows/pages.yml publica o conteúdo estático a cada push em main.

URL esperada: https://marciodeveloper.github.io/rotta/

## O que já funciona no protótipo
- Navegação responsiva e menu mobile.
- Busca por termo, localidade e categoria.
- Filtros de experiências.
- Cards de destinos, experiências, negócios e produtos.
- Favoritos locais (estado apenas durante a sessão).
- Modais demonstrativos para detalhes, parceiros, histórias, marketplace e área do usuário.
- Metadados Open Graph e Twitter Card.

## Limites explícitos
Não existe backend, login real, envio de formulário, contato WhatsApp, reserva, pedido, pagamento ou checkout. Os dados comerciais, preços, avaliações, eventos e perfis são fictícios. Não usar como catálogo de ofertas reais.

## Documentação
- docs/brand-system.md — sistema visual e direção de arte.
- docs/product-architecture.md — pilares e evolução do produto.
- docs/asset-credits.md — fontes das imagens demonstrativas.


## Fotografias regionais e proteção

As fotografias autorais otimizadas ficam em `assets/photos/`. O pacote regional ROTTA contém 11 arquivos WebP e deve ser extraído na raiz do repositório, preservando a estrutura `assets/photos/`, antes da validação visual da branch.

A interface dificulta o salvamento casual por menu de contexto, arraste e seleção sobre as imagens. Isso não impede captura de tela, inspeção de rede ou download técnico: todo arquivo publicado em um site público pode ser recuperado. Por isso, publique somente versões otimizadas e mantenha os originais fora do repositório.
