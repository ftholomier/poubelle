# Le Signal — relevé du site existant

Relevé effectué le **1er octobre 2026** depuis `https://le-signal.com`
(l'apex ; `www.le-signal.com` ne répond pas). Sources : rendu HTML des pages,
API WooCommerce Store `/wp-json/wc/store/v1/products`, API WordPress
`/wp-json/wp/v2/pages`.

Ce relevé évite à l'IA de repartir de zéro. **Il reste à vérifier** : le site
peut avoir changé depuis, et les tarifs comme les disponibilités bougent.

---

## 1. La bonne nouvelle : c'est le même métier que le iOiO

| | Le iOiO | Le Signal |
| --- | --- | --- |
| Activité | coworking, bureaux privés + open space | **identique** |
| Ville | Besançon | Montbéliard |
| Lieux | 2 (Carnot, Granvelle) | **1 seul** |
| Catalogue | 21 bureaux | **13 bureaux** |
| Plateforme | WooCommerce | **WooCommerce** |
| Téléphone | 06 09 15 25 73 | **06 09 15 25 73 — le même** |

Le modèle de données est donc **réutilisable presque tel quel**. `Offices.php`
n'a pratiquement rien à changer : un seul lieu au lieu de deux, les mêmes
types (`private`, `openspace`), les mêmes statuts.

---

## 2. Identité

- **Dénomination** : LE SIGNAL
- **Adresse** : 95 Faubourg de Besançon, 25200 Montbéliard
- **Téléphone** : 06 09 15 25 73
- **Email** : non publié sur le site
- **Directeur de la publication** : Denis Seigne
- **Positionnement** : « une demeure de prestige », accessible 24h/24 7j/7,
  « clé en main, tout compris »
- **Zone visée** : aire urbaine Montbéliard–Belfort, Lure, Héricourt
- **Ton** : direct et familier, émojis, interpellations
  (« On échange ? », « il n'y aura pas de la place pour tout le monde :-) »)

---

## 3. Pages et URL actuelles

| Page | URL |
| --- | --- |
| Accueil | `/` |
| Le Signal | `/location-bureaux-montbeliard-coworking/` |
| Nos bureaux | `/louer-bureau-coworking-montbeliard-location-bureaux/` |
| Bureaux privés | `/categorie-produit/location-louer-bureaux-montbeliard/` |
| Bureaux ouverts | `/categorie-produit/location-louer-bureaux-coworking-montbeliard/` |
| Bureaux disponibles | `/categorie-produit/louer-bureaux-montbeliard-coworking/` |
| Contact | `/location-bureaux-montbeliard-belfort-aire-urbaine-doubs/` |
| Mentions légales | `/coworking-montbeliard-belfort-location-bureau-prive-openspace/` |
| Panier / Commande / Mon compte | `/panier/`, `/commander/`, `/mon-compte/` |

Les URL actuelles sont **bourrées de mots-clés**. À refaire proprement
(`/nos-bureaux`, `/contact`…) avec des redirections 301 depuis les anciennes,
sinon le référencement acquis est perdu.

---

## 4. Le catalogue — 13 bureaux

Disponible dans `docs/le-signal-catalogue.json`, déjà au format de
`content/offices.json`.

**7 bureaux privés** — 3 libres, 4 loués :

| Bureau | Surface | Capacité | Prix | État |
| --- | --- | --- | --- | --- |
| Privé N°01 | 8 m² | 1 personne | 240 € | libre |
| Privé N°02 | 9 m² | 1 personne, entrée visiteur privative | 270 € | libre |
| Privé N°03 | 25 m² | 4 personnes, entrée visiteur privative | 650 € | loué |
| Privé N°04 | 30 m² | 5 à 8 personnes | 700 € | loué |
| Privé N°05 | 13 m² | 2 personnes | 400 € (barré 450 €) | libre |
| Privé N°06 | 13 m² | 2 personnes | 400 € | loué |
| Privé N°07 | 11 m² | 1 personne | 310 € | loué |

**6 postes en open space** — tous libres, 3 m², 1 personne, emplacement
privé, **150 €** chacun.

Soit **9 disponibilités sur 13**. Le site actuel affiche « DERNIER BUREAU
PRIVÉ DISPONIBLE — à réserver en urgence » sur le N°05 alors que les N°01 et
N°02 sont également libres : **à faire confirmer**.

---

## 5. Prestations annoncées

- mobilier à disposition si besoin : bureau, fauteuil, rangement
- salle de réunion partagée de 9 m² (6 à 8 personnes), tableau blanc et écran mural
- espace détente de 40 m² : cuisine équipée, réfrigérateur/congélateur,
  four et micro-ondes, bouilloire, cafetière Nespresso, vaisselle, évier,
  placards, table, chaises
- charges comprises : internet haut débit, eau, électricité, ménage
- accès 24h/24, 7j/7
- surfaces de 8 à 30 m²

---

## 6. Les photos — le point de vigilance

27 photos dans le catalogue, servies en **1024 × 768** et **768 × 1024**
(redimensionnées par WordPress), environ 700 Ko chacune en JPEG.

C'est **insuffisant** pour le socle : les visuels y sont servis en WebP avec
des dérivés jusqu'à 1600 px, et une photo de 1024 px s'affiche floue sur un
écran à forte densité. Les noms de fichiers (`20250408_103444.jpg`) indiquent
des photos de téléphone d'avril 2025 : **les originaux existent forcément** et
font probablement 3000 px de large.

**Réclamer les originaux avant de commencer.** Sur le projet iOiO, des
visuels basse définition ont donné une impression de site bâclé pendant
plusieurs itérations, alors que le code était correct.

---

## 7. La vente en ligne est abandonnée — décision prise

Le site actuel vend en ligne : panier, commande et compte client WooCommerce
sont actifs. **Le client a tranché : on bascule sur le modèle du iOiO.** Pas
de boutique, pas de paiement, pas de commande. Le visiteur manifeste son
intérêt, l'équipe rappelle.

Ce que cela implique concrètement :

- **Trois URL disparaissent** : `/panier/`, `/commander/`, `/mon-compte/`.
  Elles doivent renvoyer un **410 Gone** — et non un 301 vers l'accueil —
  pour que les moteurs les retirent proprement de leur index. Un 301 vers une
  page sans rapport est traité comme un soft 404 et traîne des mois.
- **Les URL produit et catégorie** (`/categorie-produit/…`, les fiches de
  bureaux) se redirigent en **301** vers leurs équivalents du nouveau site :
  la fiche correspondante, ou le catalogue filtré.
- **Le vocabulaire change** : « Ajouter au panier » devient « Ce bureau
  m'intéresse », exactement comme sur le iOiO. « Réservé » reste, c'est le
  statut `rented` du socle.
- **Le formulaire remplace la commande.** Le socle enregistre la demande dans
  `content/requests.json`, prévient l'équipe par email, envoie un accusé de
  réception au visiteur, et passe le tout par l'anti-spam.
- **Le prix barré est géré** : le socle a désormais deux champs par bureau,
  tarif normal et tarif promo. Le bureau privé N°05 est donc saisi à 450 € de
  tarif normal et 400 € de promo ; c'est déjà fait dans
  `le-signal-catalogue.json`.

## 8. Un seul lieu — à confirmer

Toute la mécanique à deux adresses du iOiO (bascule Carnot/Granvelle, filtres
par lieu, cartes multiples) se simplifie. À garder en l'état si d'autres
adresses sont prévues : la retirer puis la réintroduire coûterait plus cher
que de la laisser dormante.

---

## 9. Ce qui existe sur le site actuel et mérite d'être repris

- la **version audio** de la présentation (« Tout savoir sur Le Signal en
  audio ») — original, à conserver ;
- la mention de consentement sous le formulaire de contact ;
- le vocabulaire « bureau ouvert » plutôt qu'« open space » dans le catalogue ;
- la mise en avant de la proximité Belfort / aire urbaine, utile en référencement local.
