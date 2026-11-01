import {
  heroSlides,
  destinations,
  regionalHighlights,
  regionalPlaces,
  experiences,
  businesses,
  products,
  opportunities,
  stories,
  demoNotice
} from './data.js';

const $ = (selector, scope) => (scope || document).querySelector(selector);
const $$ = (selector, scope) => Array.from((scope || document).querySelectorAll(selector));
const dialog = $('#appDialog');
const dialogEyebrow = $('#dialogEyebrow');
const dialogTitle = $('#dialogTitle');
const dialogContent = $('#dialogContent');
const toast = $('#toast');
const favorites = new Set();

const icons = {
  pin: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 10.2c0 5.2-8 11.2-8 11.2S4 15.4 4 10.2a8 8 0 1 1 16 0Z"></path><circle cx="12" cy="10" r="2.4"></circle></svg>',
  arrow: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 19 19 5M7 5h12v12"></path></svg>',
  heart: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.8 8.8c0 5.1-8.8 10.2-8.8 10.2S3.2 13.9 3.2 8.8A4.8 4.8 0 0 1 12 6.1a4.8 4.8 0 0 1 8.8 2.7Z"></path></svg>',
  star: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 2.7 5.6 6.2.9-4.5 4.4 1.1 6.2-5.5-2.9-5.5 2.9 1.1-6.2-4.5-4.4 6.2-.9L12 3Z"></path></svg>',
  clock: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2"></path></svg>',
  bag: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 8h16l-1 13H5L4 8Z"></path><path d="M8 9V6a4 4 0 0 1 8 0v3"></path></svg>',
  compass: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="m15.7 8.3-2.4 5-5 2.4 2.4-5 5-2.4Z"></path></svg>'
};

function escapeHTML(value) {
  return String(value == null ? '' : value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

function showToast(message) {
  toast.textContent = message;
  toast.classList.add('is-visible');
  window.clearTimeout(showToast.timer);
  showToast.timer = window.setTimeout(() => toast.classList.remove('is-visible'), 3200);
}

function openDialog(eyebrow, title, content) {
  closeMobileMenu();
  dialogEyebrow.textContent = eyebrow;
  dialogTitle.textContent = title;
  dialogContent.innerHTML = content;
  if (!dialog.open) dialog.showModal();
  document.body.classList.add('dialog-open');
}

function closeDialog() {
  if (dialog.open) dialog.close();
  document.body.classList.remove('dialog-open');
}

function locationLine(value) {
  return '<span class="meta-location">' + icons.pin + '<span>' + escapeHTML(value) + '</span></span>';
}

function renderDestinations() {
  const root = $('#destinationGrid');
  if (!root) return;
  root.innerHTML = destinations.map((item, index) => {
    return '<article class="destination-card destination-card-' + (index + 1) + ' reveal">' +
      '<div class="destination-media' + (item.protected ? ' protected-media' : '') + '"' + (item.protected ? ' data-protected-media' : '') + '>' +
      '<img src="' + escapeHTML(item.image) + '" data-fallback="' + escapeHTML(item.fallback || '') + '" alt="' + escapeHTML(item.alt) + '" loading="lazy" decoding="async" draggable="false">' +
      (item.protected ? '<span class="photo-credit">Fotografia autoral · Acervo ROTTA</span>' : '') + '</div>' +
      '<div class="destination-gradient"></div>' +
      '<div class="destination-topline"><span>' + escapeHTML(item.tag) + '</span><span class="destination-index">0' + (index + 1) + '</span></div>' +
      '<div class="destination-content"><span class="destination-location">' + escapeHTML(item.location) + '</span>' +
      '<h3>' + escapeHTML(item.title) + '</h3><p>' + escapeHTML(item.subtitle) + '</p>' +
      '<button class="circle-arrow" type="button" data-detail="destination:' + item.id + '" aria-label="Ver ' + escapeHTML(item.title) + '">' + icons.arrow + '</button></div>' +
      '</article>';
  }).join('');
}

function renderRegionalHighlights() {
  const root = $('#regionFeatureGrid');
  const places = $('#regionPlaceList');
  if (root) {
    root.innerHTML = regionalHighlights.map((item, index) => {
      return '<article class="region-feature-card reveal">' +
        '<div class="region-feature-media protected-media" data-protected-media>' +
        '<img src="' + escapeHTML(item.image) + '" data-fallback="' + escapeHTML(item.fallback || '') + '" alt="' + escapeHTML(item.alt) + '" loading="lazy" decoding="async" draggable="false">' +
        '<span class="photo-credit">' + escapeHTML(item.credit || 'Fotografia autoral · Acervo ROTTA') + '</span>' +
        '<span class="region-feature-index">0' + (index + 1) + '</span></div>' +
        '<div class="region-feature-body"><span class="region-feature-category">' + escapeHTML(item.category) + '</span>' +
        '<h3>' + escapeHTML(item.title) + '</h3><p>' + escapeHTML(item.description) + '</p>' +
        '<span class="region-feature-location">' + icons.pin + escapeHTML(item.location) + '</span></div></article>';
    }).join('');
  }
  if (places) {
    places.innerHTML = regionalPlaces.map(place => '<span class="region-place-chip">' + escapeHTML(place) + '</span>').join('');
  }
}

function initHeroCarousel() {
  const root = $('.hero');
  const layers = $('.hero-image', root);
  const pagination = $('#heroPagination');
  if (!root || layers.length < 2 || !heroSlides.length) return;

  let currentIndex = 0;
  let activeLayer = layers[0];

  const updateMeta = (index) => {
    const slide = heroSlides[index];
    const counter = $('.aside-index', root);
    const statement = $('.aside-card strong', root);
    const caption = $('.aside-caption', root);
    if (counter) counter.innerHTML = String(index + 1).padStart(2, '0') + ' <i>/ ' + String(heroSlides.length).padStart(2, '0') + '</i>';
    if (statement) statement.textContent = slide.statement;
    if (caption) caption.textContent = slide.caption;
    if (pagination) {
      $('[data-hero-slide]', pagination).forEach(button => {
        const active = Number(button.dataset.heroSlide) === index;
        button.classList.toggle('is-active', active);
        if (active) button.setAttribute('aria-current', 'true');
        else button.removeAttribute('aria-current');
      });
    }
  };

  const setSlide = (index) => {
    if (index === currentIndex) return;
    const slide = heroSlides[index];
    const nextLayer = activeLayer === layers[0] ? layers[1] : layers[0];
    const activate = () => {
      nextLayer.classList.add('is-active');
      activeLayer.classList.remove('is-active');
      activeLayer = nextLayer;
      currentIndex = index;
      updateMeta(index);
      nextLayer.onload = null;
    };
    nextLayer.onload = activate;
    nextLayer.dataset.fallback = slide.fallback || '';
    nextLayer.dataset.fallbackApplied = 'false';
    nextLayer.alt = slide.alt;
    nextLayer.removeAttribute('aria-hidden');
    if (nextLayer.getAttribute('src') === slide.image && nextLayer.complete && nextLayer.naturalWidth > 0) {
      activate();
      return;
    }
    nextLayer.src = slide.image;
    if (nextLayer.complete && nextLayer.naturalWidth > 0) activate();
  };

  updateMeta(currentIndex);
  if (pagination) {
    pagination.addEventListener('click', event => {
      const button = event.target.closest('[data-hero-slide]');
      if (!button) return;
      setSlide(Number(button.dataset.heroSlide));
    });
  }

  if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    window.setInterval(() => setSlide((currentIndex + 1) % heroSlides.length), 7800);
  }
}

function experienceCard(item) {
  const isSaved = favorites.has(item.id);
  return '<article class="experience-card reveal">' +
    '<div class="experience-media"><img src="' + escapeHTML(item.image) + '" alt="' + escapeHTML(item.alt) + '" loading="lazy" decoding="async">' +
    '<span class="image-tag">' + escapeHTML(item.badge) + '</span>' +
    '<button class="favorite-button ' + (isSaved ? 'is-saved' : '') + '" type="button" data-favorite="' + item.id + '" aria-label="' + (isSaved ? 'Remover dos favoritos' : 'Salvar nos favoritos') + '" aria-pressed="' + isSaved + '">' + icons.heart + '</button></div>' +
    '<div class="experience-body"><div class="card-meta">' + locationLine(item.location) + '<span class="rating">' + icons.star + ' ' + escapeHTML(item.rating) + '</span></div>' +
    '<div class="card-category">' + escapeHTML(item.category) + '</div><h3>' + escapeHTML(item.title) + '</h3>' +
    '<p>' + escapeHTML(item.description) + '</p>' +
    '<div class="experience-foot"><div class="price"><strong>' + escapeHTML(item.price) + '</strong><small>' + escapeHTML(item.priceNote) + '</small></div>' +
    '<button class="text-link text-link-small" type="button" data-detail="experience:' + item.id + '">Conhecer <span>↗</span></button></div></div></article>';
}

function renderExperiences(filter) {
  const root = $('#experienceGrid');
  if (!root) return;
  const current = filter || 'Todas';
  const visible = current === 'Todas' ? experiences : experiences.filter(item => item.category === current);
  root.innerHTML = visible.length ? visible.map(experienceCard).join('') : '<p class="empty-state">Ainda estamos preparando novas experiências nesta categoria.</p>';
  $$('#experienceFilters [data-filter]').forEach(button => {
    const active = button.dataset.filter === current;
    button.classList.toggle('is-active', active);
    button.setAttribute('aria-pressed', String(active));
  });
  observeReveals();
}

function renderBusinesses() {
  const root = $('#businessGrid');
  if (!root) return;
  root.innerHTML = businesses.map(item => {
    return '<article class="business-card reveal"><div class="business-image"><img src="' + escapeHTML(item.image) + '" alt="' + escapeHTML(item.alt) + '" loading="lazy" decoding="async"><span class="business-label">' + escapeHTML(item.label) + '</span></div>' +
      '<div class="business-body"><div class="card-meta">' + locationLine(item.location) + '<span class="rating">' + icons.star + ' ' + escapeHTML(item.rating) + '</span></div>' +
      '<span class="card-category">' + escapeHTML(item.category) + '</span><h3>' + escapeHTML(item.name) + '</h3><p>' + escapeHTML(item.description) + '</p>' +
      '<button class="text-link text-link-small" type="button" data-detail="business:' + item.id + '">Conhecer negócio <span>↗</span></button></div></article>';
  }).join('');
}

function renderProducts() {
  const root = $('#productGrid');
  if (!root) return;
  root.innerHTML = products.map(item => {
    return '<article class="product-card reveal"><div class="product-image"><img src="' + escapeHTML(item.image) + '" alt="' + escapeHTML(item.alt) + '" loading="lazy" decoding="async"><span>' + escapeHTML(item.type) + '</span>' +
      '<button class="product-quick" type="button" data-detail="product:' + item.id + '" aria-label="Ver produto">' + icons.arrow + '</button></div>' +
      '<div class="product-body"><small>' + escapeHTML(item.seller) + '</small><h3>' + escapeHTML(item.name) + '</h3><div class="product-foot"><strong>' + escapeHTML(item.price) + '</strong><button type="button" data-detail="product:' + item.id + '">Ver item ↗</button></div></div></article>';
  }).join('');
}

function renderOpportunities() {
  const root = $('#opportunityList');
  if (!root) return;
  root.innerHTML = opportunities.map((item, index) => {
    return '<button class="opportunity-item reveal" type="button" data-detail="opportunity:' + item.id + '">' +
      '<span class="opportunity-number">0' + (index + 1) + '</span><span class="opportunity-main"><small>' + escapeHTML(item.type) + '</small><strong>' + escapeHTML(item.title) + '</strong><span>' + escapeHTML(item.description) + '</span></span>' +
      '<span class="opportunity-date">' + escapeHTML(item.date) + '</span><span class="opportunity-arrow">↗</span></button>';
  }).join('');
}

function findItem(type, id) {
  const collections = {
    destination: destinations,
    experience: experiences,
    business: businesses,
    product: products,
    opportunity: opportunities,
    story: stories
  };
  return (collections[type] || []).find(item => item.id === id);
}

function openDetail(type, id) {
  const item = findItem(type, id);
  if (!item) return;
  const imageMarkup = item.image ? '<div class="detail-image"><img src="' + escapeHTML(item.image) + '" alt="' + escapeHTML(item.alt || item.title || item.name) + '"></div>' : '';
  const title = item.title || item.name;
  const category = item.category || item.tag || item.type || 'ROTTA';
  let facts = '';
  if (item.location) facts += '<span>' + icons.pin + escapeHTML(item.location) + '</span>';
  if (item.duration) facts += '<span>' + icons.clock + escapeHTML(item.duration) + '</span>';
  if (item.rating) facts += '<span>' + icons.star + escapeHTML(item.rating) + '</span>';
  if (item.price) facts += '<span class="detail-price">' + escapeHTML(item.price) + ' <small>' + escapeHTML(item.priceNote || '') + '</small></span>';
  if (item.seller) facts += '<span>' + icons.bag + 'Vendido por ' + escapeHTML(item.seller) + '</span>';
  const description = item.description || item.subtitle || '';
  const actionLabel = type === 'product' ? 'Consultar disponibilidade' : type === 'business' ? 'Entrar em contato' : 'Tenho interesse';
  openDialog(category, title, imageMarkup +
    '<div class="detail-facts">' + facts + '</div>' +
    '<p class="detail-description">' + escapeHTML(description) + '</p>' +
    '<div class="demo-disclaimer">' + escapeHTML(demoNotice) + '</div>' +
    '<button class="button button-copper dialog-primary" type="button" data-demo-interest>' + actionLabel + ' <span>↗</span></button>');
}

function openPartnerDialog() {
  openDialog('Faça parte da ROTTA', 'Sua história também merece espaço.', 
    '<p class="dialog-lead">Apresente seu negócio, produto, serviço ou experiência. Nesta etapa, estamos demonstrando como será a entrada de novos parceiros no ecossistema.</p>' +
    '<form class="dialog-form" data-partner-form>' +
    '<label>Seu nome<input name="name" type="text" placeholder="Como podemos chamar você?" required></label>' +
    '<label>O que você representa?<select name="type" required><option value="">Selecione uma opção</option><option>Negócio local</option><option>Produto ou marca independente</option><option>Experiência ou turismo</option><option>Criador de conteúdo</option><option>Parceiro</option></select></label>' +
    '<label>E-mail para contato<input name="email" type="email" placeholder="voce@exemplo.com" required></label>' +
    '<label>Conte um pouco sobre sua ideia<textarea name="message" rows="3" placeholder="O que você gostaria de apresentar na ROTTA?"></textarea></label>' +
    '<button class="button button-copper" type="submit">Enviar apresentação <span>↗</span></button></form>' +
    '<p class="form-footnote">Protótipo local: nenhum dado será enviado ou armazenado.</p>');
}

function openAccessDialog() {
  openDialog('Área do usuário', 'Sua próxima descoberta começa aqui.',
    '<div class="access-placeholder"><span class="access-symbol">' + icons.compass + '</span><p>A área de membros está prevista para uma próxima etapa do produto. Em breve, será possível guardar favoritos, acompanhar contatos e organizar suas experiências.</p><span class="coming-soon">Área do usuário · em desenvolvimento</span></div>' +
    '<button class="button button-dark dialog-primary" type="button" data-close-dialog>Continuar explorando</button>');
}

function openStoryDialog() {
  const story = stories[0];
  openDialog('ROTTA histórias · conteúdo demonstrativo', 'Todo lugar tem uma história.',
    '<div class="story-dialog-image"><img src="' + escapeHTML(story.image) + '" alt="Rua histórica em uma cidade brasileira"><span class="story-play-badge">▶</span></div>' +
    '<p class="dialog-lead">A ROTTA nasce do desejo de aproximar pessoas dos lugares que visitam. Por trás de cada fachada, receita, trilha ou pequeno negócio existe uma história esperando para ser descoberta.</p>' +
    '<div class="story-dialog-caption"><span>01 / OLHAR HUMANO</span><span>Conteúdo audiovisual · Cultura · Experiências</span></div>' +
    '<div class="demo-disclaimer">O player audiovisual e os episódios reais serão conectados em uma etapa posterior. Esta é uma prévia conceitual.</div>');
}

function openMarketplaceDialog() {
  openDialog('Marketplace ROTTA', 'Descobrir também é levar junto.',
    '<p class="dialog-lead">O marketplace foi pensado como um catálogo híbrido: negócios locais e vendedores independentes poderão apresentar produtos e serviços, com descoberta, contato direto e evolução futura para checkout integrado.</p>' +
    '<div class="marketplace-steps"><div><span>01</span><strong>Descubra</strong><small>Explore produtos, serviços e marcas.</small></div><div><span>02</span><strong>Conecte</strong><small>Conheça quem vende e produz.</small></div><div><span>03</span><strong>Compre</strong><small>Checkout integrado em uma fase futura.</small></div></div>' +
    '<div class="demo-disclaimer">' + escapeHTML(demoNotice) + '</div>');
}

function openOpportunitiesDialog() {
  const content = opportunities.map(item => '<div class="dialog-opportunity"><small>' + escapeHTML(item.type) + ' · ' + escapeHTML(item.date) + '</small><strong>' + escapeHTML(item.title) + '</strong><p>' + escapeHTML(item.description) + '</p></div>').join('');
  openDialog('ROTTA oportunidades', 'Conexões que abrem caminhos.', '<p class="dialog-lead">Uma vitrine para encontros, parcerias, eventos e novas possibilidades.</p>' + content);
}

function searchableItems() {
  return []
    .concat(destinations.map(item => ({...item, kind: 'destination', label: 'Descoberta'})))
    .concat(experiences.map(item => ({...item, kind: 'experience', label: 'Experiência'})))
    .concat(businesses.map(item => ({...item, kind: 'business', title: item.name, label: 'Negócio'})))
    .concat(products.map(item => ({...item, kind: 'product', title: item.name, label: 'Marketplace'})));
}

function performSearch(query, place, category, target) {
  const q = (query || '').trim().toLocaleLowerCase('pt-BR');
  const p = (place || '').trim().toLocaleLowerCase('pt-BR');
  const c = (category || '').trim().toLocaleLowerCase('pt-BR');
  const results = searchableItems().filter(item => {
    const text = [item.title, item.description, item.location, item.category, item.tag, item.subtitle, item.seller].join(' ').toLocaleLowerCase('pt-BR');
    const matchesText = !q || text.includes(q);
    const matchesPlace = !p || (item.location || '').toLocaleLowerCase('pt-BR').includes(p);
    const matchesCategory = !c || (item.category || item.label || item.tag || '').toLocaleLowerCase('pt-BR').includes(c) || (c === 'produtos' && item.kind === 'product');
    return matchesText && matchesPlace && matchesCategory;
  });
  const list = results.length ? results.map(item => {
    return '<button class="search-result" type="button" data-detail="' + item.kind + ':' + item.id + '">' +
      '<img src="' + escapeHTML(item.image) + '" alt="" loading="lazy"><span><small>' + escapeHTML(item.label) + ' · ' + escapeHTML(item.location || item.seller || '') + '</small><strong>' + escapeHTML(item.title) + '</strong><em>' + escapeHTML(item.description || item.subtitle || '') + '</em></span><b>↗</b></button>';
  }).join('') : '<div class="empty-search"><span>' + icons.compass + '</span><strong>Nenhuma descoberta por aqui — ainda.</strong><p>Tente outro termo ou amplie sua busca.</p></div>';
  if (target) target.innerHTML = list;
  return results.length;
}

function openSearchDialog() {
  openDialog('Busca ROTTA', 'O que você quer descobrir?',
    '<form class="dialog-search-form" id="dialogSearchForm"><label class="dialog-search-input"><span>Buscar por palavra</span><input id="dialogQuery" type="search" placeholder="Ex.: café, trilha, hospedagem..."></label><div class="dialog-search-row"><label><span>Destino</span><select id="dialogPlace"><option value="">Qualquer lugar</option><option>Vassouras</option><option>Miguel Pereira</option><option>Vale do Café</option><option>Serra fluminense</option></select></label><label><span>Categoria</span><select id="dialogCategory"><option value="">Todas</option><option>Natureza</option><option>Gastronomia</option><option>Cultura</option><option>Hospedagem</option><option>Produtos</option></select></label><button class="button button-copper" type="submit">Buscar ↗</button></div></form><div class="search-results" id="dialogSearchResults"></div>');
  $('#dialogSearchForm').addEventListener('submit', event => {
    event.preventDefault();
    const count = performSearch($('#dialogQuery').value, $('#dialogPlace').value, $('#dialogCategory').value, $('#dialogSearchResults'));
    showToast(count + (count === 1 ? ' resultado encontrado.' : ' resultados encontrados.'));
  });
  performSearch('', '', '', $('#dialogSearchResults'));
}

function observeReveals() {
  const elements = $$('.reveal:not(.is-visible)');
  if (!('IntersectionObserver' in window)) {
    elements.forEach(el => el.classList.add('is-visible'));
    return;
  }
  if (!window.revealObserver) {
    window.revealObserver = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          window.revealObserver.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -35px 0px' });
  }
  elements.forEach(el => window.revealObserver.observe(el));
}

function closeMobileMenu() {
  const menu = $('#mobileMenu');
  const toggle = $('.menu-toggle');
  menu.hidden = true;
  toggle.setAttribute('aria-expanded', 'false');
  toggle.setAttribute('aria-label', 'Abrir menu');
  document.body.classList.remove('menu-open');
}

document.addEventListener('error', event => {
  const image = event.target;
  if (!(image instanceof HTMLImageElement)) return;
  if (image.dataset.fallback && image.dataset.fallbackApplied !== 'true') {
    image.dataset.fallbackApplied = 'true';
    image.src = image.dataset.fallback;
  }
}, true);

document.addEventListener('contextmenu', event => {
  if (event.target instanceof Element && event.target.closest('[data-protected-media]')) event.preventDefault();
});

document.addEventListener('dragstart', event => {
  if (event.target instanceof Element && event.target.closest('[data-protected-media]')) event.preventDefault();
});

document.addEventListener('selectstart', event => {
  if (event.target instanceof Element && event.target.closest('[data-protected-media]')) event.preventDefault();
});

document.addEventListener('click', event => {
  const menuToggle = event.target.closest('.menu-toggle');
  if (menuToggle) {
    const menu = $('#mobileMenu');
    const isOpen = menuToggle.getAttribute('aria-expanded') === 'true';
    menu.hidden = isOpen;
    menuToggle.setAttribute('aria-expanded', String(!isOpen));
    menuToggle.setAttribute('aria-label', isOpen ? 'Abrir menu' : 'Fechar menu');
    document.body.classList.toggle('menu-open', !isOpen);
    return;
  }

  if (event.target.closest('#mobileMenu a')) {
    closeMobileMenu();
    return;
  }

  if (event.target.closest('[data-open-search]')) {
    openSearchDialog();
    return;
  }
  if (event.target.closest('[data-open-partner]')) {
    openPartnerDialog();
    return;
  }
  if (event.target.closest('[data-open-access]')) {
    openAccessDialog();
    return;
  }
  if (event.target.closest('[data-open-story]')) {
    openStoryDialog();
    return;
  }
  if (event.target.closest('[data-open-marketplace]')) {
    openMarketplaceDialog();
    return;
  }
  if (event.target.closest('[data-open-opportunities]')) {
    openOpportunitiesDialog();
    return;
  }
  if (event.target.closest('[data-close-dialog]')) {
    closeDialog();
    return;
  }

  const filterButton = event.target.closest('[data-filter]');
  if (filterButton) {
    renderExperiences(filterButton.dataset.filter);
    return;
  }

  const favoriteButton = event.target.closest('[data-favorite]');
  if (favoriteButton) {
    const id = favoriteButton.dataset.favorite;
    if (favorites.has(id)) {
      favorites.delete(id);
      showToast('Experiência removida dos favoritos.');
    } else {
      favorites.add(id);
      showToast('Experiência salva nos favoritos.');
    }
    renderExperiences($('.filter-chip.is-active')?.dataset.filter || 'Todas');
    return;
  }

  const detailButton = event.target.closest('[data-detail]');
  if (detailButton) {
    const parts = detailButton.dataset.detail.split(':');
    openDetail(parts[0], parts[1]);
    return;
  }

  if (event.target.closest('[data-demo-interest]')) {
    openDialog('Interesse registrado localmente', 'Essa conexão começa aqui.',
      '<p class="dialog-lead">Este protótipo ainda não envia solicitações para os vendedores. Na versão funcional, aqui será possível iniciar uma conversa ou consultar disponibilidade.</p><div class="demo-disclaimer">Nenhuma reserva, compra ou mensagem foi enviada.</div><button class="button button-dark dialog-primary" type="button" data-close-dialog>Entendi</button>');
    return;
  }
});

$('#heroSearch').addEventListener('submit', event => {
  event.preventDefault();
  const query = $('#searchQuery').value;
  const place = $('#searchPlace').value;
  const category = $('#searchCategory').value;
  openDialog('Busca ROTTA', 'Descobertas para o seu próximo caminho.',
    '<div class="search-summary"><span>Busca</span><strong>' + escapeHTML(query || 'Todas as descobertas') + '</strong><small>' + escapeHTML([place, category].filter(Boolean).join(' · ') || 'Todos os lugares e categorias') + '</small></div><div class="search-results" id="heroSearchResults"></div>');
  const count = performSearch(query, place, category, $('#heroSearchResults'));
  showToast(count + (count === 1 ? ' resultado encontrado.' : ' resultados encontrados.'));
});

dialog.addEventListener('click', event => {
  if (event.target === dialog) closeDialog();
});

dialog.addEventListener('close', () => {
  document.body.classList.remove('dialog-open');
});

document.addEventListener('submit', event => {
  const form = event.target.closest('[data-partner-form]');
  if (!form) return;
  event.preventDefault();
  closeDialog();
  showToast('Apresentação demonstrativa concluída. Nenhum dado foi enviado.');
});

$('#currentYear').textContent = new Date().getFullYear();

renderDestinations();
renderRegionalHighlights();
initHeroCarousel();
renderExperiences('Todas');
renderBusinesses();
renderProducts();
renderOpportunities();
observeReveals();

window.addEventListener('scroll', () => {
  $('.site-header').classList.toggle('is-scrolled', window.scrollY > 30);
}, { passive: true });

window.addEventListener('resize', () => {
  if (window.innerWidth > 900) closeMobileMenu();
});

