# Le Signal — table de redirections

Les URL actuelles sont bourrées de mots-clés et disparaissent avec la refonte.
Sans redirections, le référencement acquis est perdu : ces pages sont indexées
et reçoivent du trafic.

Deux règles, et une seule exception :

- **301 permanent** vers l'équivalent sur le nouveau site, quand la page a un
  successeur ;
- **410 Gone** pour les pages de la boutique, qui n'ont plus de successeur.
  Surtout pas un 301 vers l'accueil : Google le traite comme un soft 404 et la
  page traîne des mois dans l'index.

À implémenter dans `public/.htaccess`, avant la règle de réécriture du routeur,
ou dans `Router` si l'hébergement ne permet pas la réécriture.

---

## Pages — 301

| Ancienne URL | Nouvelle |
| --- | --- |
| `/location-bureaux-montbeliard-coworking/` | `/le-signal` |
| `/louer-bureau-coworking-montbeliard-location-bureaux/` | `/nos-bureaux` |
| `/location-bureaux-montbeliard-belfort-aire-urbaine-doubs/` | `/contact` |
| `/coworking-montbeliard-belfort-location-bureau-prive-openspace/` | `/mentions-legales` |

## Catégories produit — 301 vers le catalogue filtré

| Ancienne URL | Nouvelle |
| --- | --- |
| `/categorie-produit/location-louer-bureaux-montbeliard/` | `/nos-bureaux?type=private` |
| `/categorie-produit/location-louer-bureaux-coworking-montbeliard/` | `/nos-bureaux?type=openspace` |
| `/categorie-produit/louer-bureaux-montbeliard-coworking/` | `/nos-bureaux?status=available` |

## Boutique — 410 Gone

| URL | Traitement |
| --- | --- |
| `/panier/` | 410 |
| `/commander/` | 410 |
| `/mon-compte/` | 410 |

## Fiches de bureaux — 301

Les identifiants cibles sont ceux de `le-signal-catalogue.json`.

| Ancienne URL | Nouvelle | Bureau |
| --- | --- | --- |
| `/produit/location-bureau-coworking-montbeliard-1/` | `/nos-bureaux/openspace-01` | Bureau ouvert — N°01 |
| `/produit/location-bureau-coworking-montbeliard-2/` | `/nos-bureaux/openspace-02` | Bureau ouvert — N°02 |
| `/produit/location-bureau-coworking-montbeliard-3/` | `/nos-bureaux/openspace-03` | Bureau ouvert — N°03 |
| `/produit/location-bureau-coworking-montbeliard-4/` | `/nos-bureaux/openspace-04` | Bureau ouvert — N°04 |
| `/produit/location-bureau-coworking-montbeliard-5/` | `/nos-bureaux/openspace-05` | Bureau ouvert — N°05 |
| `/produit/location-bureau-coworking-montbeliard-6/` | `/nos-bureaux/openspace-06` | Bureau ouvert — N°06 |
| `/produit/location-bureau-montbeliard-1/` | `/nos-bureaux/prive-01` | Bureau privé — N°01 |
| `/produit/location-bureau-montbeliard-2/` | `/nos-bureaux/prive-02` | Bureau privé — N°02 |
| `/produit/location-bureau-montbeliard-3/` | `/nos-bureaux/prive-03` | Bureau privé — N°03 |
| `/produit/location-bureau-montbeliard-4/` | `/nos-bureaux/prive-04` | Bureau privé — N°04 |
| `/produit/location-bureau-montbeliard-5/` | `/nos-bureaux/prive-05` | Bureau privé — N°05 |
| `/produit/location-bureau-montbeliard-6/` | `/nos-bureaux/prive-06` | Bureau privé — N°06 |
| `/produit/location-bureau-montbeliard-7/` | `/nos-bureaux/prive-07` | Bureau privé — N°07 |

---

## Vérification

Après mise en ligne, contrôler que chaque ancienne URL répond bien :

```bash
while read -r u; do
  printf '%-70s %s\n' "$u" "$(curl -s -o /dev/null -w '%{http_code} -> %{redirect_url}' "https://le-signal.com$u")"
done < anciennes-urls.txt
```

Attendu : `301` avec une destination qui répond `200`, ou `410` pour la
boutique. Aucun `404`, aucune chaîne de deux redirections.
