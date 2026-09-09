# Le iOiO — site public + back-office

Refonte du site [ioio.fr](https://www.ioio.fr) : catalogue des bureaux des deux espaces
de coworking bisontins (**Carnot** et **Granvelle**), avec un back-office complet pour
gérer le catalogue, les contenus, les photos et l'assistant IA.

**Techno : PHP 8.1+ natif, HTML, CSS et JavaScript natifs, données en fichiers JSON.
Aucune base de données, aucun gestionnaire de paquets, aucune dépendance externe.**

L'interface reprend au pixel près le design de référence livré avec le projet
(`docs/HANDOFF-CLAUDE-CODE.md`) : encre `#0E0E0E`, jaune `#FFD100`, crème `#FFF8EA`,
vert « disponible » `#12B39A`, sable « loué » `#EDE5D5`, titres Bricolage Grotesque,
texte Manrope, bordures noires 2px, flat design strict.

---

## 1. Démarrer en local

```bash
php -S localhost:8000 -t public bin/router.php
```

Puis ouvrir <http://localhost:8000>. Le back-office est sur <http://localhost:8000/admin/> :
au premier accès, un **écran d'installation** demande l'email et le mot de passe du
premier compte administrateur. Aucun identifiant n'est écrit en dur dans le dépôt.

Pour repartir des contenus de démonstration : `php bin/seed.php` (ajoute ce qui manque)
ou `php bin/seed.php --force` (réécrit tout).

## 2. Mise en production

**Le `DocumentRoot` du domaine doit pointer sur `/public`.** `app/`, `content/`,
`storage/` et `bin/` restent hors racine web.

```
DocumentRoot /var/www/ioio/public
```

1. Copier les fichiers sur le serveur.
2. `cp .env.example .env` puis renseigner au moins `SITE_URL` et `MAIL_FROM`.
   Les clés API peuvent aussi être saisies au back-office (Réglages → Clés API) ;
   une clé posée dans `.env` reste prioritaire et s'affiche en lecture seule.
3. Droits : le serveur web doit pouvoir écrire dans `content/`, `storage/` et `public/media`.
   ```bash
   chown -R www-data:www-data content storage public/media
   chmod -R u+rwX,go-rwx content storage
   ```
4. Ouvrir `/admin/` et créer le compte administrateur.
5. Programmer la maintenance nocturne :
   ```cron
   20 3 * * * /usr/bin/php /var/www/ioio/bin/cron.php >> /var/www/ioio/storage/logs/cron.log 2>&1
   ```

### Hébergement mutualisé sans `DocumentRoot` déplaçable

Chaque dossier hors `public/` contient déjà un `.htaccess` `Require all denied` et un
`index.html` vide : rien n'y est servi même si la racine web est celle du projet.
Vérifier après déploiement que `https://votre-domaine/content/settings.json`
renvoie bien une erreur 403.

### Exigences serveur

| Élément | Rôle |
| --- | --- |
| PHP **8.1+** | `mbstring`, `json`, `gd` (WebP), `intl` (prix et dates), `curl`, `zip`, `dom` |
| `mod_rewrite` | URLs propres |
| `mod_headers` | en-têtes de sécurité du `.htaccess` |
| `pdftotext` *(facultatif)* | meilleure extraction du texte des PDF pour l'assistant |

Aucune base de données n'est requise.

## 3. Arborescence

```
app/                  code applicatif, hors racine web
├─ Config, Router, Content, Store, Auth, Csrf, Mailer, I18n, Media, Offices…
├─ Ai/                Indexer (BM25 léger), Gemini, Docs
├─ lang/{fr,en}.json  libellés d'interface
├─ views/             gabarits du site public
└─ admin/views/       gabarits du back-office
content/              DONNÉES ÉDITABLES (écrites par le back-office)
├─ settings.json      identité, lieux, conversion, intégrations
├─ offices.json       LE CATALOGUE DES BUREAUX
├─ pages/*.{fr,en}.json
├─ posts.json, reviews.json, media.json, docs.json
├─ users.json, requests.json         (non versionnés dans git)
└─ _versions/         30 dernières versions de chaque fichier
storage/              logs, verrous, sessions, index IA, documents, sauvegardes
├─ secrets.json       clés API saisies au back-office (0600, jamais dans git)
├─ docs/              PDF/DOCX de l'assistant, jamais servis en direct
└─ index/chunks.json  index de recherche de l'assistant
public/               SEULE RACINE WEB
├─ index.php          contrôleur frontal
├─ admin/             back-office
├─ api/               endpoints JSON
├─ assets/{css,js,img}
└─ media/             photos publiées (WebP + dérivés 1600/800/400)
bin/                  seed, cron, user, router de développement
```

## 4. Le back-office

| Écran | Ce qu'on y fait |
| --- | --- |
| **Tableau de bord** | compteurs réels, dernières demandes, état des intégrations, champs de contenu manquants |
| **Pages & contenus** | tous les textes du site, champ par champ, en FR et en EN. Brouillon → publication, verrou d'édition 10 min, restauration de version, traduction assistée |
| **Bureaux & dispos** | **le catalogue** : ajouter, modifier, réordonner, activer/désactiver, faire tourner la disponibilité (Disponible → Bientôt libre → Loué), prix, surface, description, points forts, photos, version anglaise |
| **Photos** | téléversement (conversion WebP + dérivés), texte alternatif et légende FR/EN, suppression |
| **L'actu** | articles avec éditeur WYSIWYG, brouillon/publié, image, version anglaise |
| **Demandes** | contacts, réservations et rappels de disponibilités, avec statut |
| **Assistant IA** | documents indexés, prompt système, suggestions, questions restées sans réponse, réindexation |
| **Réglages & clés** | identité, coordonnées, les deux lieux, conversion, mesure d'audience, **clés API** (test de chaque intégration, recherche du Place ID par adresse, choix du modèle Gemini), comptes |

Le catalogue est la source unique : la page « Nos bureaux », les cartes de l'accueil,
le compteur « il reste N places », le badge du hero, la carte « à partir de »,
l'email envoyé après la pop-up de sortie et l'assistant lisent tous `content/offices.json`.

### Règles anti-casse

1. **Écriture atomique** — le JSON part dans un `.tmp`, est relu et validé, puis remplace
   l'ancien par `rename()`. Jamais de fichier à moitié écrit en production.
2. **Verrou** — `flock(LOCK_EX)` pendant l'écriture ; verrou éditorial de 10 minutes
   affiché dans l'éditeur (« Verrou : vous éditez cette page »).
3. **Versionnage** — copie de l'ancien fichier dans `content/_versions/` avant chaque
   écriture, 30 versions conservées, restauration en un clic.
4. **Lecture tolérante** — une clé absente ne lève jamais d'erreur : valeur par défaut,
   journal dans `storage/logs/content.log`, signalement au tableau de bord.
   Un bloc inconnu est ignoré au rendu.
5. **Sauvegarde** — `bin/cron.php backup` crée une archive datée de `content/` et
   `storage/docs`, avec 14 jours de rétention.

## 5. API interne (JSON, même origine)

| Endpoint | Méthode | Réponse |
| --- | --- | --- |
| `/api/offices.php?site=carnot&status=available` | GET | `{ offices:[…], available, total, minPrice, updatedAt }` |
| `/api/reviews.php` | GET | `{ rating, count, reviews:[…], source, updatedAt }` — cache 24 h, jamais d'appel Google au rendu |
| `/api/contact.php` | POST | `{ ok:true, ref:"C-0909-123" }` |
| `/api/reserve.php` | POST | `{ ok:true, ref:"R-0909-014" }` |
| `/api/lead.php` | POST | `{ ok:true }` — envoie au visiteur la liste réelle des bureaux libres |
| `/api/chat.php` | POST | `{ ok:true, answer, sources:[{label,url}], engine }` |

Sécurité commune à tous les POST : jeton **CSRF** en session, champ **honeypot** `hp`,
horodatage `ts` (rejet sous 2 secondes), **quota** 5 requêtes / 10 min / IP
(20 questions/h pour l'assistant), `filter_var` + longueurs maximales,
échappement à l'affichage, réponses toujours en `application/json` avec `nosniff`.

## 6. Intégrations — et ce qui se passe sans clé

| Intégration | Avec la clé | Sans la clé (repli livré) |
| --- | --- | --- |
| **Assistant Gemini** | RAG sur l'index local puis le modèle choisi dans la liste (`gemini-2.5-flash` par défaut), température 0,2, 400 jetons, timeout 8 s, une seule tentative | réponses rapides du back-office (mots-clés) puis extraction des phrases pertinentes de l'index — toujours sourcées |
| **Avis Google Places** | récupération serveur, cache 24 h, avis non retouchés | avis saisis dans `content/reviews.json` |
| **Google Translate** | bouton « Traduire depuis le français », résultat écrit **en brouillon** | traduction manuelle par onglet de langue |
| **SMTP** | envoi authentifié | fonction `mail()` de l'hébergeur |

Les clés se saisissent dans **Réglages → Clés API** (stockées dans `storage/secrets.json`,
hors racine web, en droits `0600`) ou dans `.env`, qui reste prioritaire.

### Trois outils dans l'écran des clés

1. **Tester les intégrations** — un bouton par intégration. Chaque test fait un vrai
   appel, le plus court possible, avec la clé enregistrée, et affiche la réponse de
   Google (ou l'erreur exacte : clé invalide, API non activée, quota) avec la durée.
   Le test « Envoi d'emails » expédie un email réel à la boîte configurée et indique la
   voie utilisée (SMTP ou `mail()`). Aucun test n'écrit dans le contenu du site.
2. **Trouver l'identifiant de la fiche Google** — on saisit l'adresse postale (ou le nom)
   de l'établissement, on choisit la bonne fiche dans les résultats, et son Place ID est
   enregistré dans `GOOGLE_PLACE_ID`. Plus besoin d'aller le chercher à la main.
3. **Modèles Gemini disponibles** — dès que la clé Gemini est enregistrée, le catalogue
   des modèles du compte Google est récupéré (`GET /v1beta/models`, cache 24 h dans
   `storage/gemini-models.json`) et `GEMINI_MODEL` devient une liste déroulante. Seuls
   les modèles capables de répondre en texte sont proposés ; le panneau affiche la
   description et la taille de contexte de chacun, avec un bouton de rafraîchissement.

## 7. Multilangue

* URLs préfixées : `/nos-bureaux` en français, `/en/offices` en anglais.
  `/fr/...` redirige en 301 vers l'URL courte.
* Détection : premier segment d'URL → cookie `ioio_lang` → `Accept-Language` → français.
* `hreflang` réciproques + `x-default`, `og:locale`, `sitemap.xml` généré à la volée.
* Trois niveaux : libellés d'interface (`app/lang/xx.json`), contenu éditorial
  (un JSON par page et par langue), champs de données (`i18n.<lang>.<champ>`).
  Une valeur absente retombe **toujours** sur le français : aucune page vide.
* Ajouter une langue = déposer `app/lang/xx.json`, ajouter le code dans
  `Config::LANGS` et les slugs dans `Router::ROUTES`. Aucun autre code à toucher.

## 8. Conversion et mesure

Barre CTA collante sur toutes les pages (halo animé configurable), pop-up de sortie
(`mouseout` haut de page, variante mobile sur retour arrière ou inactivité, une fois
par session), preuves calculées depuis le catalogue, avis Google.

Événements poussés vers Plausible ou Matomo s'ils sont configurés :
`cta_sticky_contact`, `cta_sticky_reserve`, `exit_popup_open`, `exit_popup_submit`,
`chat_open`, `chat_question`, `reserve_submit`, `contact_submit`.

## 9. Provenance du contenu

Tout le contenu éditorial, le catalogue et les photos sont **repris du site
d'origine ioio.fr**, et non inventés :

| Donnée | Source |
| --- | --- |
| Catalogue (21 bureaux, tarifs, disponibilités, photos) | API WooCommerce `/wp-json/wc/store/v1/products` |
| Textes « Nos espaces », accueil, contact | pages Elementor rendues |
| Coordonnées, équipe, téléphone | page Contact |
| Éditeur, SIRET, hébergeur | page Mentions légales |
| Avis clients | widget Trustindex de la page d'accueil, textes non retouchés |
| Articles | `/wp-json/wp/v2/posts` + pages rendues |
| Photos | fichiers d'origine `wp-content/uploads`, en pleine résolution |

Le catalogue réel : **21 bureaux**, dont 4 bureaux privés à Carnot (320 à 385 €
HT/mois), 3 bureaux privés à Granvelle (350 à 360 €) et 14 postes en open space
(150 €). Deux seulement sont disponibles : Carnot 03 et le poste open space 12.

## 10. Photos : politique de définition

Les 26 photos viennent des originaux de ioio.fr, converties en WebP, plafonnées à
1600 px de large, avec des dérivés 800 et 400 px. Le site se protège en plus contre
tout agrandissement, au cas où une petite image serait téléversée plus tard :

* chaque emplacement déclare une **largeur minimale** (`View::image(..., ['minWidth' => 700])`) ;
* une image plus étroite n'est jamais agrandie : le site affiche à la place la
  vignette de marque (aplat coloré, anneau du logo, nom du bureau) ;
* la fiche d'un bureau met automatiquement en grand **la première photo assez définie**
  et bascule les autres en vignettes, où leur définition suffit ;
* le back-office signale en clair les photos en basse définition à remplacer.

Largeurs minimales en place : diaporama 900, grande vue et carte d'espace 700,
image d'article 400, carte de bureau et vignette de fiche 190.

Chaque photo porte le nom de son sujet et l'identifiant de son lieu, si bien qu'un
visuel de Granvelle ne peut pas se retrouver sur un bureau de Carnot. L'affectation
photo → bureau est celle du site d'origine.


## 11. Cookies et consentement

Bandeau affiché à la première visite : **Tout accepter**, **Tout refuser**,
**Paramètres**. Trois catégories, pas une de plus :

| Catégorie | Contenu | Sans consentement |
| --- | --- | --- |
| Strictement nécessaires | session anti-spam des formulaires, langue, fenêtres déjà fermées, mémorisation du choix (13 mois) | actives, comme le permet l'article 82 de la loi Informatique et Libertés |
| Mesure d'audience | Plausible ou Matomo | **aucun script chargé**, aucun événement envoyé |
| Assistant iOiO | envoi des questions à Google (API Gemini) | l'assistant répond uniquement depuis l'index local du site, **rien ne sort du serveur** |

Le choix est rejouable à tout moment par le lien « Cookies » du pied de page.
Le bandeau se désactive dans **Réglages → Conversion**.

Les polices Bricolage Grotesque et Manrope sont **hébergées sur le domaine**
(`public/assets/fonts`) : aucune requête vers `fonts.googleapis.com` ni
`fonts.gstatic.com`, donc aucune adresse IP transmise à un tiers avant
consentement. Le site n'appelle aucun domaine externe tant que rien n'est accepté.

## 12. Accessibilité et performance

* Contraste conforme à la charte : le jaune est toujours un **fond**, jamais une encre.
* Navigation au clavier, `aria-*` sur les composants interactifs, lien d'évitement,
  libellés de formulaire (visibles ou `sr-only`).
* `prefers-reduced-motion: reduce` coupe marquee, parallaxe, halo, balancement et
  révélations, en gardant l'état final lisible.
* Images en WebP avec `srcset`, `loading="lazy"`, `width`/`height` connus : aucun
  décalage de mise en page.
* Sans JavaScript : tous les contenus restent lisibles et les formulaires postent
  normalement (réponse JSON, aucune page blanche).

## 13. Outils en ligne de commande

```bash
php bin/seed.php [--force]     # contenus de démonstration
php bin/cron.php [tâche]       # all | index | reviews | backup | prune
php bin/user.php list          # comptes
php bin/user.php add email "MotDePasse" admin
php bin/user.php password email "NouveauMotDePasse"
php bin/user.php delete email
```

## 14. À compléter avant la mise en ligne

* Mentions légales et politique de confidentialité : les champs entre crochets
  (`[forme juridique]`, `[SIRET]`, `[hébergeur]`…) se remplissent dans
  **Pages & contenus → Mentions légales**.
* Coordonnées : téléphone et adresse email dans **Réglages → Site & lieux**
  (le téléphone laissé vide n'est simplement pas affiché).
* Catalogue : vérifier dans **Bureaux & dispos** que les disponibilités sont à jour
  au moment de la mise en ligne (elles ont été relevées sur ioio.fr).
* Surfaces : le site d'origine n'indique pas de surface par bureau ; le champ est
  vide et n'apparaît pas. À renseigner au back-office si vous le souhaitez.
* Clés API si l'assistant Gemini, les avis Google ou la traduction sont souhaités.
* Décommenter l'en-tête `Strict-Transport-Security` dans `public/.htaccess` une fois
  le certificat HTTPS en place.
