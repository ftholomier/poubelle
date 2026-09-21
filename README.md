# intermittent.fr — refonte 2026

Offres d'emploi et annuaire de CV pour les intermittents du spectacle.
Site gratuit des deux côtés, financé par la publicité.

**PHP 8.3 natif, sans base de données, sans dépendance Composer.**
Le stockage est fait de fichiers JSON, la racine web est `public/`, tout le
reste vit au-dessus et n'est pas servable.

---

## Démarrer

```bash
# 1. Secrets (toutes les clés sont facultatives : voir « Dégradation »)
cp config/secrets.example.php config/secrets.php

# 2. Reprise des données de l'ancien WordPress
php bin/import-wordpress.php --dump=/chemin/export.sql --uploads=/chemin/wp-content/uploads

# 3. Pages éditoriales que l'ancien site n'avait pas
php bin/seed-pages.php

# 4. Un compte pour entrer dans le back-office
php bin/create-admin.php --email=vous@exemple.fr --name="Votre nom"

# 5. En local
php -S 127.0.0.1:8080 -t public bin/router-dev.php
```

En production, faites pointer la racine web (`DocumentRoot`) sur `public/`.
Le `.htaccess` fourni gère la réécriture, la redirection HTTPS et les en-têtes
de cache. Rien d'autre que `public/` ne doit être accessible depuis le web.

**Prérequis** : PHP 8.3+ avec `curl`, `mbstring`, `fileinfo`, `zip`, `intl`.
Aucune extension supplémentaire, aucun paquet Composer.

---

## Arborescence

```
public/                 ← racine web (DocumentRoot)
  index.php             front-controller unique
  .htaccess             réécriture, HTTPS, cache, refus des fichiers sensibles
  assets/css|js|fonts|img

app/
  bootstrap.php         autoload PSR-4 maison, config, migrations de schéma
  Core/                 Router, Request, Response, View, Session, Csrf, Security, Config, Kernel
  Storage/              Json (écriture atomique), Lock, Schema, Repository, Index, Backup, Audit
  Domain/               JobRepository, CvRepository, EmployerRepository, UserRepository, PageRepository
  Controllers/          Home, Job, Cv, Employer, Page, Submit, Api, Admin, Media, Sitemap
  Services/             Search, I18n, Translator, Regie, Knowledge, Auth, LegacyPassword,
                        Upload, Validator, Sanitizer, RateLimit, Reviews, Ads, JobReview, Mailer, Http
  Support/              helpers.php, Icon.php

templates/              gabarits PHP (layout, partials/, pages/, admin/)
config/                 config.php (versionné) · secrets.php (jamais versionné)
bin/                    scripts CLI (import, seed, admin, médias, polices, réindexation, sauvegarde)

data/                   ← hors racine web, contient tout l'état du site
  content/{lang}/*.json pages éditoriales, une par langue
  jobs/*.json           offres d'emploi
  cv/*.json             profils
  employers/*.json      structures
  users/*.json          comptes
  index/*.json          index de recherche dénormalisés + cache (avis, sitemap)
  uploads/cv|photo|logo fichiers déposés, servis par PHP et non par le serveur web
  backups/*.zip         instantanés horodatés
  logs/                 journal d'audit (jsonl), erreurs PHP, copies d'e-mails
  private/auth/         jetons de récupération (hachés)
  private/locks/        verrous d'édition
  private/ai/           documents de la base de connaissance
```

---

## Format des fichiers JSON

Chaque fichier porte un champ `schema` et se lit avec des valeurs de repli :
un champ absent prend sa valeur par défaut, un champ inconnu est conservé tel
quel. **Une évolution de structure ne casse jamais le front.**

### `data/jobs/{id}.json`

```json
{
  "schema": 3,
  "id": "j29389",
  "slug": "felicie-production-corse-44-tournage-en-corse",
  "title": "Tournage en Corse, janvier 2027",
  "status": "publish",            // publish | draft | expired
  "description": "…",
  "requirements": ["…"],           // « Profil recherché », en puces
  "conditions": "…",
  "company": { "name": "", "slug": "", "website": "", "tagline": "", "description": "", "logo": "" },
  "location": { "city": "Corse", "region": "Corse", "country": "FR", "remote": false },
  "salary": "",
  "contract": ["CDD d'usage"],     // taxonomie job_listing_type
  "category": ["Production cinématographique"],
  "tags": ["chef op", "cadreur"],
  "starts_at": "", "expires_at": "", "filled": false, "featured": false,
  "apply": { "email": "…", "url": "" },
  "author_id": 1020, "views": 0,
  "created_at": "…", "updated_at": "…", "published_at": "…",
  "legacy_id": 29389               // identifiant WordPress d'origine
}
```

### `data/cv/{id}.json`

Mêmes principes. `contact.public` est **faux par défaut** : l'adresse e-mail
n'est jamais affichée sans consentement explicite. `file.path` est relatif à
`data/uploads/`; `file.legacy_url` garde l'URL de l'ancien site tant que le
fichier n'a pas été récupéré.

### `data/content/{lang}/{slug}.json`

Le français est la **langue pivot**. `source_hash` est l'empreinte de la source
FR : quand elle change, les traductions passent à l'état « obsolète » dans le
back-office. `translated: true` marque une page réellement traduite.

### Index

`data/index/{jobs,cv,employers,pages}.json` sont **dérivés** : ils contiennent
une version allégée de chaque fiche plus les compteurs de facettes. Ils sont
régénérés à chaque publication, et reconstruits automatiquement s'ils manquent.
On peut les supprimer sans rien perdre :

```bash
php bin/reindex.php
```

---

## Sauvegarde et restauration

Un instantané zip horodaté est créé **automatiquement** avant chaque
publication et chaque suppression, dans `data/backups/`. Il contient tous les
JSON (`content/`, `jobs/`, `cv/`, `employers/`, `users/`, `index/`) et un
`manifest.json`. Les fichiers déposés n'y sont pas : sauvegardez `data/uploads/`
séparément.

**Depuis le back-office** : *Sauvegardes* → `Restaurer`. L'état courant est
sauvegardé avant écrasement, donc une restauration reste réversible.

**En ligne de commande** :

```bash
php bin/backup.php                         # créer un instantané
php bin/backup.php --list                  # lister
php bin/backup.php --restore=snapshot-….zip
```

**À la main**, si PHP n'est plus disponible :

```bash
cd data && unzip -o backups/snapshot-20260921-205442-publication.zip
php bin/reindex.php
```

---

## Sécurité

| Sujet | Mise en œuvre |
| --- | --- |
| Mots de passe | Argon2id. Les empreintes WordPress (`$wp$` bcrypt, `$P$` phpass) sont acceptées une dernière fois et **converties à la première connexion réussie** — personne n'a à réinitialiser. |
| Récupération | Jeton à usage unique, 30 minutes, stocké **haché** (`sha256`) dans `data/private/auth/`. |
| Limitation de débit | Par IP, sur fichier : connexion (5/15 min), dépôts (5/h), assistant (20/10 min), récupération (5/h). L'IP n'est jamais stockée en clair, seulement un HMAC. |
| CSRF | Un jeton par formulaire, comparé en temps constant. |
| Cookies | `SameSite=Strict`, `HttpOnly`, `Secure` dès que HTTPS est actif. |
| En-têtes | CSP avec nonce, HSTS, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`. |
| Uploads | Type MIME **réel** vérifié avec `finfo`, taille plafonnée, fichier renommé, stocké **hors** de `public/` et servi par un script qui contrôle les droits et bloque la traversée de répertoire. |
| HTML éditorial | Liste blanche stricte (`Sanitizer`) : ni `<script>`, ni attribut événementiel, ni URL `javascript:`. |
| Journal | `data/logs/audit-AAAA-MM.jsonl` : connexions, publications, suppressions, incidents. Les adresses e-mail y sont masquées. |

**Certificat SSL** : le site actuel n'est joignable qu'en HTTP. Le code force
la redirection HTTP → HTTPS et envoie HSTS, mais **le certificat doit être
réparé côté hébergeur (PHPNet)** — c'est un prérequis, pas quelque chose que le
code peut résoudre.

---

## Multilingue

Sept langues : **FR** (pivot), EN, ES, DE, IT, PT, NL.
URLs préfixées (`/fr/…`, `/en/…`), `hreflang` sur chaque page, `lang` correct
sur `<html>`.

Les chaînes d'interface vivent dans `app/Services/lang/fr.php`. Les autres
langues sont produites par Google Cloud Translation **côté serveur** et mises
en cache dans `data/i18n/{lang}.json`. Les pages éditoriales sont traduites
vers `data/content/{lang}/`.

Depuis le back-office : *Traductions* → `Traduire ce qui manque`. Seul ce qui
manque part à l'API : une clé déjà traduite n'est jamais renvoyée.

### Ce qui est traduit

| Contenu | Traduit | Comment |
| --- | --- | --- |
| Interface | oui | Une seule source FR, cache par langue. |
| Pages éditoriales | oui | Stockées par langue dans `data/content/{lang}/`. |
| **Annonces** | **oui** | Titre, description, conditions et profil recherché. Cache par fiche dans `data/i18n/job/{lang}/`, indexé sur une empreinte du texte source : une annonce modifiée est remise en file, une annonce inchangée n'est jamais repayée. |
| CV | non par défaut | Textes personnels, qui se lisent d'ordinaire dans leur langue. Activable par `i18n.translate_cv`. |
| Offres externes | non | Elles appartiennent à leur source et renvoient vers elle. |

Une annonce consultée dans une langue non encore traduite est traduite à la
volée puis mise en cache — le visiteur suivant ne l'attend plus. La traduction
à la demande est bornée à 12 fiches par heure et par IP, pour qu'un robot
d'indexation ne se transforme pas en facture. Le traitement par lot depuis le
back-office est plafonné à 150 fiches par clic.

Une traduction en cache est **toujours** servie, même si la clé d'API est
retirée ensuite : l'API n'est nécessaire que pour la produire.

---

## Configuration

Tout se règle depuis *Back-office → Clés d'API* : assistant Régie, traduction,
avis Google, AdSense et ses sept emplacements, les quatre sources d'offres
externes, expédition des e-mails, clé de signature. Chaque champ porte son mode
d'emploi et un lien vers la console où créer la clé ; chaque groupe a un bouton
qui interroge réellement le service plutôt que de valider un format.

Les valeurs vivent dans `data/private/secrets.json`, hors racine web, en 0600.
Un secret n'est jamais renvoyé au navigateur : l'écran n'affiche qu'une
empreinte partielle (`AIza••••••••4f2a`) et un champ laissé vide conserve la
valeur en place. Le journal retient quelles clés ont changé, jamais leur valeur.

`config/secrets.php` reste utilisable pour un déploiement automatisé : le
magasin piloté depuis l'interface l'emporte simplement sur le fichier.

## Dégradation sans clé d'API

Le site est **entièrement fonctionnel sans aucune clé**. Chaque intégration
absente se désactive proprement plutôt que d'échouer :

| Clé absente | Conséquence |
| --- | --- |
| `gemini_api_key` | Régie répond en citant directement la base de connaissance locale (recherche IDF + couverture), et décline hors périmètre. |
| `translate_api_key` | Les pages non traduites sont servies en français, sous un bandeau qui le dit explicitement. |
| `places_api_key` | Le bloc d'avis Google n'est pas affiché. Aucune note n'est inventée. |
| `adsense_client` | Les sept emplacements affichent le cadre en pointillés de la maquette. |
| MTA indisponible | Les e-mails sont archivés dans `data/logs/mail/` : un lien de récupération n'est jamais perdu. |

---

## Offres externes

La liste d'offres greffe, autour des annonces déposées ici, des offres venues
de sources externes — comme le faisait l'extension Indeed du site WordPress.
Les réglages d'origine sont repris : 5 offres externes avant les annonces du
site, 25 après, pays `fr`, et la requête de 30 métiers du spectacle.

Trois garanties :

- Une offre externe n'est **jamais** enregistrée comme une annonce locale. Elle
  vit en cache (1 h), porte la mention de sa provenance, et renvoie vers le
  site d'origine en `rel="nofollow noopener"`.
- Elle n'entre ni dans le plan du site, ni dans l'index de recherche, ni dans
  les compteurs : « 51 offres » reste le nombre d'annonces déposées ici.
- Une annonce publiée sur intermittent.fr prime sur sa reprise chez un
  agrégateur : le doublon est écarté.

### Sources disponibles

| Source | Accès | État |
| --- | --- | --- |
| **Indeed** | Flux partenaire (`indeed_feed_url`), point d'entrée partenaire (`indeed_api_base`), ou ancien publisher ID | ⚠ L'API publisher historique, celle qu'utilisait l'extension WordPress, **a été fermée par Indeed**. Leur recherche est passée en accès partenaire, sans inscription libre-service. Le publisher ID du site (`4142992219966569`) est conservé au cas où, mais il ne renvoie probablement plus rien. Le scraping de leurs pages est contraire à leurs conditions et n'est pas implémenté. |
| **France Travail** | `client_id` + `client_secret` sur [francetravail.io](https://francetravail.io), API « Offres d'emploi v2 » | Gratuit, libre-service, officiel. **La source la plus pertinente ici** : elle couvre nativement le domaine « L » du ROME (spectacle, cinéma, audiovisuel), ce qu'aucun agrégateur généraliste ne fait aussi proprement. |
| **Adzuna** | `app_id` + `app_key` | Palier gratuit, libre-service. Bon repli généraliste. |
| **Jooble** | `jooble_key` | Clé en libre-service. Complément utile. |

Chaque source s'active dès que ses clés sont présentes, et se pilote depuis
*Back-office → Offres externes* (activation, état du cache, vidage). Les clés
elles-mêmes se saisissent dans *Back-office → Clés d'API*, avec un lien direct
vers la console de chaque fournisseur et un bouton qui teste la connexion. Une source
en panne ne casse jamais la page : au pire, il n'y a que les annonces locales.

Les codes ROME interrogés côté France Travail sont dans
`config.php → sources.france_travail.rome` ; vider ce tableau bascule la
recherche sur les seuls mots-clés.

## Publicité et consentement

Sept emplacements, activables individuellement depuis le back-office :
`home_top`, `home_mid`, `list_side`, `list_infeed`, `job_below`,
`profile_side`, `dir_bottom`.

Le script AdSense n'est chargé **qu'après consentement explicite**. Tant que le
visiteur n'a pas répondu, aucune requête ne part vers Google.

---

## Scripts CLI

| Script | Rôle |
| --- | --- |
| `bin/import-wordpress.php` | Reprise du dump SQL WordPress vers JSON. Réexécutable. |
| `bin/seed-pages.php` | Crée les pages éditoriales manquantes. |
| `bin/create-admin.php` | Crée ou promeut un administrateur. |
| `bin/fetch-media.php` | Récupère les CV, photos et logos depuis l'ancien site. |
| `bin/fetch-fonts.php` | Auto-héberge Bricolage Grotesque et Plus Jakarta Sans. |
| `bin/reindex.php` | Reconstruit les index et la base de connaissance. |
| `bin/backup.php` | Instantanés : créer, lister, restaurer. |
| `bin/router-dev.php` | Routeur du serveur PHP intégré (développement). |

---

## État de la reprise de données

Import réalisé depuis l'export du 21/09/2026 (`sql8732_phpnet_org`).

| Donnée | Repris | Remarque |
| --- | --- | --- |
| Offres | **89** | 434 brouillons de pourriel d'octobre 2025 écartés (titres de 10 lettres aléatoires). |
| CV | **159** | 137 publiés, 22 brouillons. |
| Employeurs | **82** | Déduits des annonces et des comptes employeurs. |
| Comptes | **233** | 761 comptes de pourriel écartés. 212 empreintes phpass, 21 bcrypt. |
| Pages | **1** | Seules les mentions légales avaient du contenu ; les autres pages WordPress n'étaient que des shortcodes. 4 pages ont été rédigées (`bin/seed-pages.php`). |
| Candidatures | **0** | 21 709 candidatures existent dans le dump. Non reprises sur décision : données personnelles de 21 709 personnes. L'importeur sait les traiter (`--with-applications`). |

**Géolocalisation reconstruite** : l'ancien site rangeait les 137 CV dans une
seule région (bug de la taxonomie `resume_region`). La ville et la région sont
désormais déduites du champ libre, en gérant les régions d'avant 2016, les
sigles (PACA, IDF), les codes départements et les adresses hors de France :
88/89 offres et 121/159 CV sont géolocalisées.

**Validation par le site d'origine** : la page d'accueil archivée le 14/09/2026
affichait « 52 emplois publiés · 137 CV publiés · 40 entreprises ». Le site
reconstruit calcule 51, 137 et 39 sur les données migrées — l'écart d'une offre
et d'un employeur vient d'une annonce expirée entre l'archive et l'export.

**Fichiers joints** : les CV PDF, photos de candidats et logos sont référencés
mais absents de l'export fourni (ils vivent dans `wp-content/uploads/resumes/`
et `wp-content/uploads/job-manager-uploads/`, frères de `uploads/AAAA/MM/`).
Les fiches conservent l'URL d'origine, et une fiche sans image retombe sur sa
tuile à initiales.

Trois voies pour les récupérer, de la plus complète à la plus partielle :

```bash
# 1. Depuis une archive du serveur — la seule voie complète
php bin/import-wordpress.php --dump=… --uploads=/chemin/wp-content/uploads

# 2. Depuis le site encore en ligne
php bin/fetch-media.php

# 3. Depuis la Wayback Machine, quand le site n'est plus joignable
php bin/import-wayback.php
```

La troisième voie a déjà été passée : **24 fichiers récupérés** (7 photos de
candidats, 17 logos d'employeurs). Les CV PDF, eux, n'ont jamais été archivés
par archive.org — seul un accès au serveur les ramènera.

## Images

Une fiche affiche sa vraie image dès qu'elle en a une, et sa tuile à initiales
colorée sinon (`templates/partials/avatar.php`). Cela vaut pour les photos de
candidats, les logos d'employeurs — reportés sur leurs offres — et les grandes
vignettes des pages de détail.

Les fichiers vivent dans `data/uploads/`, hors racine web, et sont servis par
`/media/{photo|logo|cv}/{id}` après contrôle des droits. Un CV part avec
`X-Robots-Tag: noindex`.

---

## Conventions

- Aucune écriture en place : tout passe par un temporaire puis `rename()`.
- Verrou `flock()` autour de chaque écriture, TTL de 15 minutes pour les
  verrous éditoriaux affichés dans le back-office.
- Les filtres de recherche fonctionnent **sans JavaScript** (formulaires `GET`).
  Le JS n'ajoute que du confort.
- Les animations sont coupées sous `prefers-reduced-motion: reduce`.
- Au survol d'un bouton, la couleur du texte ne se fond jamais : soit elle ne
  change pas (quand une seule couleur tient sur les deux fonds), soit elle
  bascule d'un coup à mi-parcours. Un fondu de 350 ms sur un fond qui glisse en
  500 ms donnait, au milieu du geste, du blanc sur jaune à 1,58:1.
- Le champ « Ville ou région » propose les lieux dès la première lettre
  (`/api/places`), en ignorant accents, casse et abréviations : « st etien »
  trouve « Saint-Étienne ». Sans JavaScript, c'est un champ texte ordinaire.
- Polices auto-hébergées (178 Ko), aucune requête vers un domaine tiers au
  chargement.
