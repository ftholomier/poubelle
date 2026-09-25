import { API_MAX_PER_PAGE, API_RATE_LIMIT } from './public-api';

/** Description OpenAPI 3.1 de l'API publique v1 (servie sur /api/v1/openapi.json). */
export function openApiSpec(serverUrl: string) {
  const page = [
    { name: 'page', in: 'query', schema: { type: 'integer', minimum: 1, default: 1 } },
    { name: 'per_page', in: 'query', schema: { type: 'integer', minimum: 1, maximum: API_MAX_PER_PAGE, default: 20 } },
  ];
  const meta = { type: 'object', properties: { page: { type: 'integer' }, per_page: { type: 'integer' }, total: { type: 'integer' } } };
  const list = (ref: string) => ({
    type: 'object',
    properties: { data: { type: 'array', items: { $ref: `#/components/schemas/${ref}` } }, meta },
  });
  const one = (ref: string) => ({ type: 'object', properties: { data: { $ref: `#/components/schemas/${ref}` } } });
  const ok = (schema: object) => ({ '200': { description: 'Succès', content: { 'application/json': { schema } } }, ...errors });
  const errors = {
    '401': { description: 'Clé absente, invalide ou révoquée', content: { 'application/json': { schema: { $ref: '#/components/schemas/Error' } } } },
    '429': {
      description: `Plus de ${API_RATE_LIMIT} requêtes par minute`,
      content: { 'application/json': { schema: { $ref: '#/components/schemas/Error' } } },
    },
  };
  const geo = { type: ['object', 'null'], properties: { lat: { type: 'number' }, lng: { type: 'number' } } };
  return {
    openapi: '3.1.0',
    info: {
      title: 'API publique terricom',
      version: '1.0.0',
      description:
        'Données publiées d’un territoire (commerces, artisans, producteurs, agenda, actualités), en lecture seule. ' +
        'Chaque clé est rattachée à un territoire et se crée dans le back-office de la collectivité (« API & données »). ' +
        `Limite : ${API_RATE_LIMIT} requêtes par minute et par clé (en-têtes X-RateLimit-*).`,
    },
    servers: [{ url: serverUrl }],
    security: [{ bearer: [] }],
    paths: {
      '/territory': { get: { summary: 'Le territoire de la clé', operationId: 'getTerritory', responses: ok(one('Territory')) } },
      '/communes': {
        get: {
          summary: 'Communes du territoire',
          operationId: 'listCommunes',
          responses: ok({ type: 'object', properties: { data: { type: 'array', items: { $ref: '#/components/schemas/Commune' } } } }),
        },
      },
      '/categories': {
        get: {
          summary: 'Catégories d’activité présentes',
          operationId: 'listCategories',
          responses: ok({ type: 'object', properties: { data: { type: 'array', items: { $ref: '#/components/schemas/Category' } } } }),
        },
      },
      '/establishments': {
        get: {
          summary: 'Établissements publiés',
          operationId: 'listEstablishments',
          parameters: [
            { name: 'commune', in: 'query', description: 'Identifiant d’URL de la commune', schema: { type: 'string' } },
            { name: 'category', in: 'query', description: 'Identifiant d’URL de la catégorie', schema: { type: 'string' } },
            { name: 'q', in: 'query', description: 'Recherche dans le nom et l’activité', schema: { type: 'string' } },
            {
              name: 'updated_since',
              in: 'query',
              description: 'Modifiés depuis (ISO 8601), pour une synchronisation incrémentale',
              schema: { type: 'string', format: 'date-time' },
            },
            ...page,
          ],
          responses: ok(list('Establishment')),
        },
      },
      '/establishments/{id}': {
        get: {
          summary: 'Un établissement',
          operationId: 'getEstablishment',
          parameters: [{ name: 'id', in: 'path', required: true, schema: { type: 'string', format: 'uuid' } }],
          responses: { ...ok(one('Establishment')), '404': { description: 'Introuvable' } },
        },
      },
      '/events': {
        get: {
          summary: 'Agenda (événements à venir par défaut)',
          operationId: 'listEvents',
          parameters: [
            { name: 'from', in: 'query', schema: { type: 'string', format: 'date-time' } },
            { name: 'to', in: 'query', schema: { type: 'string', format: 'date-time' } },
            ...page,
          ],
          responses: ok(list('Event')),
        },
      },
      '/posts': {
        get: {
          summary: 'Actualités et offres publiées',
          operationId: 'listPosts',
          parameters: [{ name: 'kind', in: 'query', schema: { type: 'string', enum: ['news', 'promo', 'nouveaute', 'event', 'hours', 'job'] } }, ...page],
          responses: ok(list('Post')),
        },
      },
    },
    components: {
      securitySchemes: { bearer: { type: 'http', scheme: 'bearer', description: 'Clé d’API : tc_xxxxxxxx_…' } },
      schemas: {
        Error: { type: 'object', properties: { error: { type: 'object', properties: { code: { type: 'string' }, message: { type: 'string' } } } } },
        Territory: {
          type: 'object',
          properties: {
            id: { type: 'string', format: 'uuid' },
            slug: { type: 'string' },
            name: { type: 'string' },
            legal_name: { type: 'string' },
            url: { type: 'string', format: 'uri' },
            communes: { type: 'integer' },
            establishments: { type: 'integer' },
          },
        },
        Commune: {
          type: 'object',
          properties: {
            id: { type: 'string', format: 'uuid' },
            slug: { type: 'string' },
            name: { type: 'string' },
            insee_code: { type: 'string' },
            postal_codes: { type: 'array', items: { type: 'string' } },
            geo,
            establishments: { type: 'integer' },
            url: { type: 'string', format: 'uri' },
          },
        },
        Category: {
          type: 'object',
          properties: { slug: { type: 'string' }, name: { type: 'string' }, family: { type: 'string' }, establishments: { type: 'integer' } },
        },
        Establishment: {
          type: 'object',
          properties: {
            id: { type: 'string', format: 'uuid' },
            name: { type: 'string' },
            url: { type: 'string', format: 'uri' },
            activity: { type: 'string' },
            category: { type: 'object', properties: { slug: { type: 'string' }, name: { type: 'string' } } },
            commune: { type: 'object', properties: { slug: { type: 'string' }, name: { type: 'string' }, insee_code: { type: 'string' } } },
            siret: { type: ['string', 'null'] },
            status: { type: 'string', enum: ['verified', 'claimed', 'unclaimed'] },
            tagline: { type: ['string', 'null'] },
            description: { type: ['string', 'null'] },
            address: {
              type: 'object',
              properties: { street: { type: ['string', 'null'] }, postal_code: { type: ['string', 'null'] }, city: { type: 'string' } },
            },
            geo,
            contact: {
              type: 'object',
              properties: { phone: { type: ['string', 'null'] }, email: { type: ['string', 'null'] }, website: { type: ['string', 'null'] } },
            },
            photos: { type: 'array', items: { type: 'object', properties: { url: { type: 'string' }, alt: { type: ['string', 'null'] } } } },
            hours: {
              type: 'array',
              description: 'Créneaux hebdomadaires (weekday : 0 = lundi … 6 = dimanche)',
              items: { type: 'object', properties: { weekday: { type: 'integer' }, opens: { type: 'string' }, closes: { type: 'string' } } },
            },
            exceptional_hours: { type: 'array', items: { type: 'object' } },
            open_now: { type: ['boolean', 'null'] },
            attributes: {
              type: 'array',
              items: { type: 'object', properties: { slug: { type: 'string' }, label: { type: 'string' }, group: { type: 'string' } } },
            },
            updated_at: { type: 'string', format: 'date-time' },
          },
        },
        Event: {
          type: 'object',
          properties: {
            id: { type: 'string', format: 'uuid' },
            title: { type: 'string' },
            kind: { type: 'string' },
            starts_at: { type: 'string', format: 'date-time' },
            ends_at: { type: ['string', 'null'], format: 'date-time' },
            location: { type: 'object' },
            geo,
            price: { type: ['string', 'null'] },
            url: { type: 'string', format: 'uri' },
          },
        },
        Post: {
          type: 'object',
          properties: {
            id: { type: 'string', format: 'uuid' },
            kind: { type: 'string' },
            title: { type: 'string' },
            body: { type: 'string' },
            promo: { type: ['object', 'null'] },
            establishment_id: { type: ['string', 'null'] },
            published_at: { type: ['string', 'null'], format: 'date-time' },
          },
        },
      },
    },
  };
}
