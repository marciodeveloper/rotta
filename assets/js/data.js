const image = function(id, width) {
  return "https://images.unsplash.com/" + id + "?auto=format&fit=crop&w=" + (width || 1000) + "&q=86";
};

export const destinations = [
  {
    id: "centro-historico",
    title: "Entre ruas e memórias",
    subtitle: "Arquitetura, cultura e histórias que atravessam gerações.",
    location: "Cidades históricas",
    tag: "Cultura & patrimônio",
    image: image("photo-1762995875767-9b67bef64f05", 1200),
    alt: "Rua de pedras cercada por construções históricas brasileiras",
    size: "large"
  },
  {
    id: "serra-horizontes",
    title: "Horizontes da serra",
    subtitle: "Paisagens para desacelerar e respirar novos ares.",
    location: "Serra fluminense",
    tag: "Natureza & bem-estar",
    image: image("photo-1724161438805-dc1d37cddb35", 1200),
    alt: "Viajante contemplando montanhas durante o pôr do sol",
    size: "regular"
  },
  {
    id: "sabores-de-origem",
    title: "Sabores de origem",
    subtitle: "Cafés, mesas e encontros que contam a cultura local.",
    location: "Rotas gastronômicas",
    tag: "Gastronomia",
    image: image("photo-1780815227186-9adce46d69f6", 1200),
    alt: "Café e confeitaria em uma mesa acolhedora",
    size: "regular"
  }
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
    description: "Uma caminhada por fachadas, praças e encontros que mantêm viva a identidade de um lugar.",
    image: image("photo-1762995875767-9b67bef64f05", 1400)
  },
  {
    id: "cafe-conversa",
    title: "O café começa na conversa",
    category: "Pessoas & sabores",
    description: "Pequenos negócios também guardam grandes histórias. Basta sentar à mesa e escutar.",
    image: image("photo-1780815227186-9adce46d69f6", 1400)
  }
];

export const demoNotice = "Este é um conteúdo demonstrativo. Os estabelecimentos, produtos, preços, avaliações e experiências apresentados são fictícios e não representam ofertas disponíveis para compra ou reserva.";
