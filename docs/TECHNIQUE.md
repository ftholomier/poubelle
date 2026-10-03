# Sochaux Rétro — documentation technique

Pour un développeur qui reprend le code. Le guide d'installation est dans
[DEPLOIEMENT.md](DEPLOIEMENT.md), l'utilisation du back-office dans
[GUIDE-BACK-OFFICE.md](GUIDE-BACK-OFFICE.md).

## 1. Principes

- PHP 8.3 sans framework ni dépendance ; aucune base de données : les données sont des
  fichiers JSON, lus et écrits par `App\Core\JsonStore` (écriture atomique : fichier
  temporaire puis `rename()` ; mises à jour sous verrou `flock`).
- Seul `public/` est exposé. `public/index.php` charge `app/bootstrap.php` (constantes de
  chemins, chargement automatique des classes `App\…` depuis `app/`, gestion des erreurs)
  puis confie la requête à `App\Kernel`.
- Gabarits PHP natifs (`templates/`), JavaScript natif (`public/assets/`), aucun outil de
  construction. Les URL des fichiers statiques portent leur date de modification
  (`asset()`), ce qui permet un cache navigateur d'un an.
- Vocabulaire du code en français, comme celui du club (fiche, rubrique, composition…).

## 2. Cheminement d'une requête

`App\Kernel::dispatch()`, dans l'ordre :

1. `/media/{largeur}/{fichier}.webp` : vignettes générées à la volée (§ 6).
2. `/wp-content/uploads/…` : redirection 301 vers la médiathèque (anciens liens d'images).
3. `/admin…` : back-office (`App\Admin\Router`, § 8).
4. Préfixe `/en` : passage en anglais, puis traitement normal de l'adresse sans préfixe.
5. `/api/…` : API JSON du site (`App\Front\Api`) : recherche, assistant, carte, votes,
   dons et leurs webhooks ; jamais bloquée par la page d'attente.
6. Page d'attente (si activée, sauf pour les membres connectés du back-office et les pages
   légales), puis mot de passe d'accès éventuel.
7. Adresse sans barre finale → 301 vers l'adresse avec barre finale.
8. Routes fixes (accueil, explorer, interactif, communauté, dons, pages légales…), déclarées
   dans `Kernel::routes()`.
9. Fiche ou rubrique à cette adresse (`Front\Pages::byPath()` : index des fiches, puis
   rubriques).
10. Ancienne adresse connue (`data/redirects.json`) → 301 ; sinon page 404 et adresse
    notée dans `storage/404.json` (Back-office › Redirections › Adresses introuvables).

Toute réponse HTML reçoit un en-tête Content-Security-Policy (`Kernel::csp()`) : scripts du
site uniquement (le court script d'en-tête du gabarit est signé par `csp_nonce()`),
cadres limités aux lecteurs vidéo reconnus, envoi de formulaires limité au site et aux
pages de paiement.

## 3. Données (`data/`, versionné)

| Fichier | Contenu |
|---|---|
| `fiches/{id}.json` | une fiche par fichier |
| `media.json` | médiathèque : clé = chemin du fichier (`2024/10/photo.jpg`) |
| `categories.json` | rubriques : chemin, parent, libellés, ordre des mosaïques |
| `redirects.json` | anciennes adresses → nouvelles |
| `collections/*.json` | quiz, frise, maillots, épopées, lieux, partenaires, bandeau, référentiels clubs et stades, positions de la carte… (contenus par défaut dans `App\Data\Seeds`) |
| `i18n/en.json` | libellés de l'interface : « texte français » → « English text » |
| `legacy/{id}.json.gz` | archive brute de chaque page WordPress (blocs HTML, tableaux) |

### Fiches

Types : `match`, `personne`, `article`, `page`, `objet` (réserves), `moment` (100 moments).
Statuts : `brouillon`, `relire`, `planifie` (avec `publish_at`), `publie`, `corbeille`.
Identifiant : celui de WordPress pour les fiches reprises, à partir de 1 000 000 pour les
fiches créées dans le back-office.

Champs communs : `id, type, status, publish_at, slug, path, title, categories, a_la_une,
featured_image, date, modified, author, seo{title, description}, intro, sections[{title,
html}], key_figure, gallery[{image, caption, credit}], images, videos[{provider, id|url,
title}], embeds, tables[{title, headers, rows}], legacy, i18n{en{…}}`, plus un bloc propre au
type :

- `match` : date, saison, compétition, journée, équipes, score (`home`, `away`, a.p.,
  t.a.b.), stade, spectateurs, arbitre, buteurs par équipe, temps forts, réactions, brèves,
  `lineup` (composition de Sochaux) et `other_lineups`.
  Ligne de composition : `position` (G, D, M, A, R, E), `name`, `number`, `captain`,
  textes saisis `goals_text`, `sub_text`, `cards_text`, et valeurs qui en sont tirées
  `goals`, `own_goals`, `sub_in`, `sub_out`, `yellow`, `red` ; `person_id` (lien manuel
  vers une fiche), `extra` (colonnes inconnues du tableau d'origine).
- `personne` : rubriques (joueur, entraîneur, dirigeant, personnage), noms et
  `aliases` (autres graphies), naissance et décès (lieu géolocalisé), mensurations, poste,
  arrivée et départ, premiers et derniers matchs, palmarès, statistiques, carte de l'album.
- `article` / `page` : genre (article, bilan de saison, portrait, dossier), titre et
  chapeau d'origine ; `listing` pour une page WordPress qui n'était qu'une liste d'articles.
- `objet` : collection, date, provenance, crédit, fiches liées ; `moment` : numéro
  (1 à 100), année, fiches liées.

Les textes des compositions sont lus par une seule classe, `App\Data\Lineup` (reprise et
back-office) : « 31' csc » est un but contre son camp, « J 35' R 80' » un jaune puis un
rouge, « ↑ 46' ↓ 80' » une entrée puis une sortie, « x2 » deux buts sans minute.

### Médiathèque

`data/media.json` décrit chaque fichier (légende, crédit, droits, texte alternatif,
dimensions, poids, empreinte `sha1`, retouche non destructive `edit` : rotation et
recadrage). Les originaux sont dans `storage/media/originals/{année}/{mois}/` (hors dépôt).

## 4. Caches (`storage/cache/`, tous reconstructibles)

| Fichier | Contenu | Mise à jour |
|---|---|---|
| `index.php` | résumé de chaque fiche (titre, adresse, type, rubriques…) | à chaque enregistrement ; reconstruit s'il manque |
| `derived.php` | données calculées (§ 5) | marqué « à recalculer » à chaque enregistrement, recalculé après l'envoi de la page ou par la tâche planifiée |
| `search.php` | index de recherche | à chaque enregistrement |
| `media.php`, `media-versions.php`, `media-usage.json` | médiathèque, versions des fichiers retouchés, « utilisée dans » | à chaque modification |
| `carte-*.json`, `sitemap.xml`, `share/` | données de la carte, plan du site, images de partage | à la demande |

Après une modification de fichiers faite à la main (envoi FTP de `data/`, script), vider
`storage/cache/` ou lancer `php bin/console.php index`, `derived` et `search`.

## 5. Données calculées (`App\Data\Derived`)

Recalculées à partir des fiches, sans aucune saisie : liste des matchs, apparitions de
chaque joueur (buts, minutes, cartons), totaux par personne, saisons, référentiels des
clubs et des stades (regroupement des variantes de noms), bilans, records, « Ce jour-là »,
utilisation des médias et alertes de qualité.

Liaison d'un nom de composition à une fiche personne, dans l'ordre : lien manuel
(`person_id`), nom identique (tous les noms et `aliases` de la fiche, ordre des mots
indifférent), nom de famille seul si un seul joueur correspond à la période, même nom
autrement écrit (apostrophe, trait d'union), une lettre de différence (deux pour un nom
long), nom incomplet (« Carlao » → « Carlao Roberto Da Cruz », avec contrôle de période).
Les rapprochements approchés sont signalés dans Qualité.

## 6. Images

`/media/{largeur}/{fichier}.webp` (largeurs 160, 320, 480, 640, 800, 1200, 1600) :
`App\Services\Images` génère la vignette WebP avec GD (en appliquant la retouche
éventuelle) dans `public/media/…`, d'où Apache la sert ensuite directement. `img()` ajoute
`?v=` aux fichiers retouchés ou remplacés. Les vignettes des vidéos sont copiées dans
`storage/media/originals/_video/` (`App\Services\VideoThumbs`) pour ne contacter
l'hébergeur vidéo qu'après l'accord du visiteur.

## 7. Services

| Classe | Rôle |
|---|---|
| `Search` | recherche plein texte sans base de données (normalisation des accents, pondération des champs) |
| `Rag`, `Gemini` | assistant IA : index sémantique (`storage/ai/`), réponses limitées aux données du site, quotas, journal des questions (RGPD) |
| `Translator`, `I18n` | traduction anglaise des fiches avec Gemini (champ `i18n.en`), libellés de l'interface (`t()`) |
| `Payments` | Stripe Checkout et abonnements, PayPal Orders v2 et abonnements, vérification des webhooks |
| `Mailer`, `Newsletter` | e-mails (SMTP ou mail()), newsletter hebdomadaire « Ce jour-là » |
| `Geo` | géolocalisation (répertoire intégré, puis Nominatim d'OpenStreetMap, une requête par seconde) |
| `Backup` | sauvegardes ZIP de `data/` et des fichiers importants de `storage/` |
| `Stats` | mesure d'audience sans cookie ni adresse IP |
| `Pdf` | reçus fiscaux en PDF, sans bibliothèque |
| `Cron` | tâches planifiées (§ 9) |

## 8. Back-office

- `App\Admin\Router` : connexion obligatoire (sauf connexion, premier accès, invitation,
  mot de passe oublié), jeton CSRF sur toute requête POST (champ `_csrf` ou en-tête
  `X-CSRF`), en-têtes `X-Frame-Options: DENY` et `noindex`.
- Deux niveaux (`App\Core\Auth::can()`) : l'utilisateur peut tout faire sauf la gestion
  des comptes (`users`), les réglages (`settings`), la suppression définitive (`destroy`)
  et la restauration de versions (`restore`, `backup_restore`).
- Formulaires : les écrans envoient du JSON (`public/assets/admin/admin.js`, champs nommés
  par chemin pointé : `match.referee`, répétitions `data-repeater`), contrôle de
  modification simultanée, brouillon conservé dans le navigateur.
- Textes longs : éditeur WYSIWYG natif (`wysiwyg.js`) ; le HTML est nettoyé côté serveur
  par liste blanche (`App\Admin\Html`).
- Chaque enregistrement crée une version (`storage/versions/{id}/`) ; le premier
  enregistrement d'une fiche reprise archive d'abord son état d'origine.
- Réglages (`config/settings.php` pour la liste, `storage/settings.json` pour les
  valeurs) ; les secrets (clés API, mots de passe) sont chiffrés avec sodium
  (`storage/secret.key`) et ne sont jamais renvoyés au navigateur.

## 9. Tâches planifiées

Une ligne de cron toutes les 5 minutes (`php bin/console.php cron`) ; chaque tâche a sa
fréquence, l'état est dans `storage/cron.json`, un verrou empêche deux passages simultanés :
publication des fiches programmées, statistiques, audience, traductions, newsletter,
géolocalisation (toutes les 10 min), médiathèque, vignettes des vidéos (toutes les heures),
assistant IA (toutes les heures), dons (toutes les heures), plan du site (chaque jour),
sauvegarde, reçus annuels, purges RGPD.

## 10. `storage/` (hors dépôt)

| Dossier ou fichier | Contenu | Sauvegardé |
|---|---|---|
| `media/originals/` | photos originales | le dimanche si l'option est cochée |
| `users.json`, `settings.json`, `secret.key` | comptes, réglages, clé de chiffrement | oui |
| `versions/` | historique des fiches et des collections | oui |
| `inbox/`, `newsletter/`, `dons/`, `votes/`, `counters.json`, `activity/` | messages et contributions, abonnés, dons, votes, compteurs, journal d'activité | oui |
| `ai/` | index et journal de l'assistant | non (reconstructible, journal purgé) |
| `cache/`, `sessions/`, `ratelimit/`, `logs/`, `backups/`, `import/` | fichiers techniques | non |

## 11. Sécurité

- Seul `public/` est exposé ; `public/.htaccess` interdit l'affichage des dossiers et
  ajoute les en-têtes de sécurité aux fichiers statiques.
- Politique CSP sur toutes les pages HTML ; jeton CSRF sur tous les formulaires.
- Mots de passe hachés en Argon2id ; cookie de session `HttpOnly`, `SameSite=Lax`,
  `Secure` en HTTPS ; limitation des tentatives (connexion : 8 par compte et 30 par adresse
  en 15 minutes ; formulaires, recherche, assistant, dons : quotas par adresse).
- Formulaires publics : champ piège et délai minimal contre les robots.
- Envois de fichiers : extensions limitées (jpg, png, gif, webp, pdf), 25 Mo au plus,
  contenu vérifié.
- Vie privée : aucun cookie tiers avant accord (vidéos chargées au clic), YouTube sans
  cookie, polices hébergées sur le site, mesure d'audience sans cookie.

## 12. Reprise WordPress (`scripts/wp/`)

1. `fetch.php` : aspiration de l'ancien site (pages HTML, API REST, ordre des mosaïques,
   médias) dans `storage/import/` ; mot de passe du site dans la variable `WP_PASSWORD`.
2. `parser.php` : lecture des blocs du thème BeTheme (textes, galeries, vidéos, tableaux,
   compositions…) ; `import.php` : écriture de `data/` (adresses « option B »,
   redirections, rubriques, médiathèque).
3. `completeness.php` : contrôle d'exhaustivité, page par page (texte mot à mot, images,
   vidéos, tableaux) ; rapport dans `storage/import/completeness.json`.
4. `media-sync.php`, `media-extra.php` : photos originales.

L'import est rejouable mais réécrit les fiches reprises (seules les fiches créées dans le
back-office sont préservées) : il ne sert plus une fois le site en service.

## 13. Vérifications

- `php tests/lineup.php` : lecture des cellules de composition (buts, remplacements,
  cartons) sur tous les formats rencontrés dans les fiches d'origine.
- `tests/smoke.js` (Playwright) : parcourt les pages du site et du back-office et signale
  les erreurs JavaScript et les blocages de la politique CSP.
