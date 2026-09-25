# API publique v1

Accès en lecture seule aux **données publiées** d’un territoire : fiches, communes, catégories, agenda,
actualités. Usages : site de l’office de tourisme ou de la collectivité en marque blanche, application
mobile, borne, open data, connecteurs.

- Adresse de base : `https://terricom.fr/api/v1` (description OpenAPI 3.1 : `/api/v1/openapi.json`, sans clé)
- Authentification : `Authorization: Bearer tc_xxxxxxxx_…` (ou en-tête `X-Api-Key`) ; jamais dans l’URL
- Une clé est rattachée à **un territoire** ; elle se crée et se révoque dans le back-office
  (« API & données », administrateurs du territoire). Seule l’empreinte SHA-256 est conservée : la clé
  complète n’est affichée qu’une fois. Création et révocation sont journalisées (catégorie Sécurité).
- Limite : 120 requêtes par minute et par clé (`X-RateLimit-Limit`, `X-RateLimit-Remaining`,
  `X-RateLimit-Reset`, `Retry-After` sur 429)
- CORS ouvert en lecture (`GET`, `OPTIONS`) pour les sites en marque blanche
- Pagination : `page` (à partir de 1) et `per_page` (20 par défaut, 100 au plus) ; réponse
  `{ "data": [...], "meta": { "page", "per_page", "total" } }`
- Erreurs : `{ "error": { "code", "message" } }` avec les statuts 400, 401, 403, 404, 429, 500

## Points d’accès

| Requête                    | Réponse                                                                                                                                        |
| -------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------- |
| `GET /territory`           | Le territoire : nom, adresse du portail, couleurs, logo, nombre de communes et de fiches                                                       |
| `GET /communes`            | Communes : code INSEE, codes postaux, position, nombre de fiches, adresse sur le portail                                                       |
| `GET /categories`          | Catégories présentes, avec le nom personnalisé par le territoire                                                                               |
| `GET /establishments`      | Fiches publiées. Filtres : `commune` (slug), `category` (slug), `q` (nom, activité), `updated_since` (ISO 8601, synchronisation incrémentale)  |
| `GET /establishments/{id}` | Une fiche : adresse, position, contacts, réseaux, photos, horaires hebdomadaires et exceptionnels, `open_now`, labels et services, traductions |
| `GET /events`              | Événements à venir, ou entre `from` et `to` (ISO 8601)                                                                                         |
| `GET /posts`               | Actualités et offres publiées ; `kind=promo` pour les seules offres                                                                            |

Les horaires utilisent `weekday` de 0 (lundi) à 6 (dimanche) et des heures `HH:MM` (heure de Paris).
Le champ `status` d’une fiche vaut `verified` (vérifiée par la collectivité), `claimed` (tenue par le
professionnel) ou `unclaimed` (données publiques SIRENE).

## Exemple

```bash
curl -H "Authorization: Bearer $TERRICOM_KEY" \
  "https://terricom.fr/api/v1/establishments?commune=ornans&category=boulangerie&per_page=50"
```

```json
{
  "data": [
    {
      "id": "…",
      "name": "Boulangerie Martin",
      "url": "https://valdeloue.terricom.fr/ornans/boulangerie/boulangerie-martin",
      "activity": "Boulangerie",
      "commune": { "slug": "ornans", "name": "Ornans", "insee_code": "25434" },
      "status": "verified",
      "geo": { "lat": 47.1068, "lng": 6.1432 },
      "hours": [{ "weekday": 0, "opens": "06:30", "closes": "19:00" }],
      "open_now": true,
      "updated_at": "2026-09-25T08:12:00.000Z"
    }
  ],
  "meta": { "page": 1, "per_page": 50, "total": 1 }
}
```

## Mise en œuvre

`src/server/services/public-api.ts` (clés, authentification, limitation, sérialisation),
`src/server/services/openapi.ts` (description), routes `src/app/api/v1/**/route.ts`, page de gestion
`src/app/collectivite/api/`. Les données exposées sont exactement celles du portail public : aucune donnée
personnelle d’abonné, de client ou d’auteur de message n’est accessible.
