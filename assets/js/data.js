const localPhoto = function(name) {
  return "./assets/photos/" + name;
};

const image = function(id, width) {
  return "https://images.unsplash.com/" + id + "?auto=format&fit=crop&w=" + (width || 1000) + "&q=86";
};

export const heroSlides = [
  {
    image: localPhoto("vassouras-ponte-historica.webp"),
    fallback: image("photo-1724161438805-dc1d37cddb35", 2400),
    alt: "Ponte histórica e paisagem de Vassouras",
    statement: "Há lugares onde o tempo deixa marcas bonitas.",
    caption: "Vassouras · patrimônio e memória."
  },
  {
    image: localPhoto("miguel-pereira-maria-fumaca.webp"),
    fallback: image("photo-1519681393784-d120267933ba", 2400),
    alt: "Locomotiva a vapor histórica em Miguel Pereira",
    statement: "Toda viagem começa com uma boa história.",
    caption: "Miguel Pereira · memória ferroviária."
  },
  {
    image: localPhoto("miguel-pereira-praca-noturna.webp"),
    fallback: image("photo-1519608487953-e999c86e7455", 2400),
    alt: "Praça iluminada e igreja em Miguel Pereira à noite",
    statement: "Quando a cidade acende, novas lembranças começam.",
    caption: "Miguel Pereira · encontros e experiências."
  },
  {
    image: localPhoto("vassouras-reflexos-por-do-sol.webp"),
    fallback: image("photo-1500530855697-b586d89ba3ee", 2400),
    alt: "Paisagem de Vassouras refletida na água ao entardecer",
    statement: "Às vezes, o melhor caminho é desacelerar.",
    caption: "Vassouras · paisagens e contemplação."
  }
];

export const destinations = [
  {
    id: "vassouras-memorias",
    title: "Vassouras, entre memórias",
    subtitle: "Casarões, praças e caminhos que guardam a história do Vale do Café.",
    location: "Vassouras · RJ",
    tag: "Patrimônio & cultura",
    image: localPhoto("vassouras-casario-colonial.webp"),
    fallback: image("photo-1762995875767-9b67bef64f05", 1200),
    alt: "Fachada histórica de arquitetura colonial em Vassouras",
    size: "large",
    protected: true
  },
  {
    id: "miguel-pereira-trilhos",
    title: "Miguel Pereira, no ritmo dos trilhos",
    subtitle: "A memória ferroviária encontra novas formas de viver o destino.",
    location: "Miguel Pereira · RJ",
    tag: "Memória & experiência",
    image: localPhoto("miguel-pereira-maria-fumaca.webp"),
    fallback: image("photo-1500530855697-b586d89ba3ee", 1200),
    alt: "Locomotiva a vapor histórica na estação de Miguel Pereira",
    size: "regular",
    protected: true
  },
  {
    id: "miguel-pereira-noite",
    title: "Quando a cidade acende",
    subtitle: "Uma noite para caminhar, encontrar e guardar na memória.",
    location: "Miguel Pereira · RJ",
    tag: "Vida & encontros",
    image: localPhoto("miguel-pereira-praca-noturna.webp"),
    fallback: image("photo-1519608487953-e999c86e7455", 1200),
    alt: "Praça iluminada e igreja em Miguel Pereira durante a noite",
    size: "regular",
    protected: true
  }
];

export const regionalHighlights = [
  {
    id: "maria-fumaca",
    title: "Maria-fumaça",
    category: "Memória ferroviária",
    description: "Uma imagem que já conta uma história: o vapor, os trilhos e a memória de viajar sem pressa.",
    location: "Miguel Pereira · RJ",
    image: localPhoto("miguel-pereira-maria-fumaca.webp"),
    fallback: image("photo-1500530855697-b586d89ba3ee", 1000),
    alt: "Locomotiva a vapor histórica fotografada em Miguel Pereira",
    credit: "Fotografia autoral · Acervo ROTTA"
  },
  {
    id: "rua-do-aroma",
    title: "Rua do Aroma",
    category: "Passeio & identidade",
    description: "Um endereço que convida a caminhar, observar os detalhes e descobrir a atmosfera da cidade.",
    location: "Miguel Pereira · RJ",
    image: localPhoto("miguel-pereira-rua-do-aroma.webp"),
    fallback: image("photo-1519608487953-e999c86e7455", 1000),
    alt: "Placa da Rua do Aroma em Miguel Pereira",
    credit: "Fotografia autoral · Acervo ROTTA"
  },
  {
    id: "trex-restaurant",
    title: "T-Rex Restaurant",
    category: "Gastronomia & diversão",
    description: "Um ponto temático que transforma uma parada para comer em parte da experiência.",
    location: "Miguel Pereira · RJ",
    image: localPhoto("miguel-pereira-trex-restaurant.webp"),
    fallback: image("photo-1517248135467-4c7edcad34c4", 1000),
    alt: "Entrada temática do T-Rex Restaurant em Miguel Pereira",
    credit: "Fotografia autoral · Acervo ROTTA"
  }
];

export const regionalPlaces = [
  "Lago do Javary",
  "Rua Torta",
  "Rua Coberta",
  "Parque dos Dinossauros",
  "Balonismo",
  "Ponte da ferrovia"
];

export const experiences = [
  {
    id: "trilha-do-entardecer",
    title: "Trilha do entardecer",
    category: "Natureza",
    location: "Miguel Pereira · RJ",
    description: "Uma caminhada leve por caminhos de mata, com parada para contemplar a paisagem no fim da tarde.",
    duration: "2h30",
    price: "R$ 89",
    priceNote: "por pessoa",
    rating: "4,9",
    image: image("photo-1708597786511-6d65ed5ffc91", 1000),
    alt: "Casal caminhando em trilha de montanha",
    badge: "Ao ar livre"
  },
  {
    id: "cafe-com-historia",
    title: "Café com história",
    category: "Gastronomia",
    location: "Vassouras · RJ",
    description: "Uma pausa com café especial, quitutes artesanais e histórias sobre os sabores da região.",
    duration: "1h30",
    price: "R$ 65",
    priceNote: "por pessoa",
    rating: "4,8",
    image: image("photo-1780815227186-9adce46d69f6", 1000),
    alt: "Mesa de café com flores e confeitaria",
    badge: "Mais procurado"
  },
  {
    id: "atelier-de-barro",
    title: "Ateliê de barro",
    category: "Cultura",
    location: "Vale do Café · RJ",
    description: "Conheça o processo artesanal, converse com quem cria e experimente colocar a mão na massa.",
    duration: "2h",
    price: "R$ 72",
    priceNote: "por pessoa",
    rating: "5,0",
    image: image("photo-1768321481665-b40705ab11ce", 1000),
    alt: "Peças de cerâmica artesanal expostas em um mercado",
    badge: "Feito à mão"
  },
  {
    id: "refugio-na-serra",
    title: "Um refúgio na serra",
    category: "Hospedagem",
    location: "Serra fluminense · RJ",
    description: "Uma estadia acolhedora para aproveitar a paisagem, o silêncio e o prazer de não ter pressa.",
    duration: "Fim de semana",
    price: "R$ 480",
    priceNote: "a diária",
    rating: "4,9",
    image: image("photo-1501785888041-af3ef285b470", 1000),
    alt: "Paisagem de montanhas e lago em meio à natureza",
    badge: "Pausa merecida"
  }
];

export const businesses = [
  {
    id: "casa-bruma",
    name: "Casa Bruma Café",
    category: "Café · Brunch · Produtos locais",
    location: "Vassouras · RJ",
    description: "Um espaço de encontros, café de origem e sabores preparados com cuidado.",
    image: image("photo-1751093747693-87903d4329a1", 1000),
    alt: "Interior acolhedor de um café com decoração natural",
    rating: "4,9",
    label: "Experiência local"
  },
  {
    id: "atelie-sabia",
    name: "Ateliê Sabiá",
    category: "Arte · Cerâmica · Presentes",
    location: "Miguel Pereira · RJ",
    description: "Peças artesanais em pequenas séries, feitas para levar um pouco do lugar com você.",
    image: image("photo-1768321481665-b40705ab11ce", 1000),
    alt: "Peças de cerâmica e artesanato em uma banca",
    rating: "5,0",
    label: "Feito à mão"
  },
  {
    id: "estacao-sabor",
    name: "Estação do Sabor",
    category: "Gastronomia · Cozinha afetiva",
    location: "Vale do Café · RJ",
    description: "Receitas de família, ingredientes da estação e uma mesa sempre pronta para receber.",
    image: image("photo-1780815227186-9adce46d69f6", 1000),
    alt: "Mesa rústica com café e confeitaria",
    rating: "4,8",
    label: "Da nossa região"
  }
];

export const products = [
  {
    id: "cafe-da-serra",
    name: "Café de origem · edição da serra",
    seller: "Casa Bruma Café",
    price: "R$ 38,00",
    image: image("photo-1780815227186-9adce46d69f6", 900),
    alt: "Café servido em mesa de madeira",
    type: "Produto local"
  },
  {
    id: "caneca-barro",
    name: "Caneca artesanal · barro natural",
    seller: "Ateliê Sabiá",
    price: "R$ 72,00",
    image: image("photo-1768321481665-b40705ab11ce", 900),
    alt: "Cerâmicas artesanais em tons naturais",
    type: "Feito à mão"
  },
  {
    id: "kit-sabores",
    name: "Kit sabores da estação",
    seller: "Estação do Sabor",
    price: "R$ 64,00",
    image: image("photo-1780815227186-9adce46d69f6", 900),
    alt: "Conjunto de café e confeitaria artesanal",
    type: "Edição especial"
  }
];

export const opportunities = [
  {
    id: "feira-autoral",
    type: "Encontro & cultura",
    date: "SÁB · 09H — 16H",
    title: "Feira de produtores autorais",
    description: "Uma vitrine para pequenos criadores, sabores e produtos feitos na região."
  },
  {
    id: "rota-cafes",
    type: "Experiência coletiva",
    date: "DOM · 10H — 13H",
    title: "Circuito de cafés e boas conversas",
    description: "Um roteiro demonstrativo para descobrir cafeterias, torrefações e histórias."
  },
  {
    id: "parceiros-rotta",
    type: "Conexão de negócios",
    date: "INSCRIÇÕES ABERTAS",
    title: "Novos parceiros ROTTA",
    description: "Negócios e criadores podem se apresentar para participar do ecossistema."
  }
];

export const stories = [
  {
    id: "memoria-nas-ruas",
    title: "A memória que mora nas ruas",
    category: "Cultura & patrimônio",
    description: "Uma caminhada por fachadas, praças e encontros que mantêm viva a identidade de Vassouras.",
    image: localPhoto("vassouras-casario-colonial.webp"),
    fallback: image("photo-1762995875767-9b67bef64f05", 1400),
    alt: "Fachada histórica em Vassouras"
  },
  {
    id: "memoria-nos-trilhos",
    title: "O tempo que ainda passa pelos trilhos",
    category: "Memória & experiência",
    description: "A Maria-fumaça transforma patrimônio ferroviário em convite para conhecer Miguel Pereira.",
    image: localPhoto("miguel-pereira-maria-fumaca.webp"),
    fallback: image("photo-1500530855697-b586d89ba3ee", 1400),
    alt: "Locomotiva a vapor em Miguel Pereira"
  }
];

export const demoNotice = "Este é um conteúdo demonstrativo. Os estabelecimentos, produtos, preços, avaliações e experiências apresentados são fictícios e não representam ofertas disponíveis para compra ou reserva.";
