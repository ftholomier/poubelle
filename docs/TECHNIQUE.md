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

Avant tout, `app/bootstrap.php` vérifie l'hébergement : PHP 8.3 et les extensions sans
lesquelles rien ne marche (`mbstring`, `intl`, `dom`, `sodium`, `ctype`, chiffrement
Argon2) ; s'il manque quelque chose, une page autonome « Réglage du serveur en cours » (503)
dit quoi régler dans cPanel, au lieu d'une erreur 500 muette. Le point complet (toutes les
extensions, dossiers inscriptibles, dernières erreurs du journal PHP) est fait par
`App\Services\ServerCheck`, montré aux administrateurs : carte Serveur de Tâches
planifiées, tâche « Régler le serveur » en tête du tableau de bord, et détail de l'erreur
sur la page « Arrêt de jeu » quand un administrateur est connecté.

`App\Kernel::dispatch()`, dans l'ordre :

1. `/media/{largeur}/{fichier}.webp` : vignettes générées à la volée (§ 6).
2. `/wp-content/uploads/…` : redirection 301 vers la médiathèque (anciens liens d'images).
3. `/admin…` : back-office (`App\Admin\Router`, § 8).
4. Préfixe `/en` : passage en anglais, puis traitement normal de l'adresse sans préfixe.
5. `/api/…` : API JSON du site (`App\Front\Api`) : recherche, assistant, carte, votes,
   dons et leurs webhooks. Les webhooks des paiements et le consentement aux cookies ne sont
   jamais bloqués ; le reste de l'API est fermé avec le site (page d'attente, mot de passe).
6. `/video/teaser.mp4` et `.jpg` (`Front\Pages::teaser()`, fichiers dans
   `app/Resources/video/`, hors de `public/`) : servis au public seulement quand le teaser est
   montré (case de la page d'attente, ou accueil une fois le site ouvert), à l'équipe
   connectée toujours ; lecture par morceaux (`Range`, `Response::media()`) pour l'avance
   rapide et Safari ; sinon l'adresse suit le chemin ordinaire (page d'attente ou 404).
7. Page d'attente, puis mot de passe d'accès éventuel. La page d'attente est **active par
   défaut** (`waiting.enabled` vaut `true` tant que rien n'est enregistré : une installation
   neuve est fermée au public). Les membres connectés du back-office voient le site, avec un
   bandeau `.team-bar` « Site fermé au public » (gabarit `layout.php`) ; les pages légales et
   `robots.txt` restent servis ; la page d'attente (503, `no-store`) n'a aucun lien vers le
   back-office.
8. Adresse sans barre finale → 301 vers l'adresse avec barre finale.
9. Routes fixes (accueil, explorer, interactif, communauté, dons, pages légales…), déclarées
   dans `Kernel::routes()`.
10. Fiche ou rubrique à cette adresse (`Front\Pages::byPath()` : index des fiches, puis
    rubriques).
11. Ancienne adresse connue (`data/redirects.json`) → 301 ; sinon page 404 et adresse
    notée dans `storage/404.json` (Back-office › Redirections › Adresses introuvables).

Site fermé ou masqué (`Front\Seo::closed()` : page d'attente ou mot de passe ;
`Seo::hidden()` : en plus, Réglages › Général › « Masquer le site aux moteurs de recherche ») :
`Kernel::unindexed()` ajoute `X-Robots-Tag: noindex, nofollow` à toute réponse hors
back-office, y compris ce que voit l'équipe connectée, qui reçoit en plus
`Cache-Control: private, no-store` ; `robots.txt` répond `Disallow: /` ; la mesure d'audience
ne compte rien tant que le site est fermé (`public/index.php`).

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
| `index-2.php` | résumé de chaque fiche (titre, adresse, type, rubriques, extrait sans « xx », empreinte des textes relus par le correcteur…) ; le numéro change quand le contenu du résumé change, l'ancien fichier est alors ignoré | à chaque enregistrement ; reconstruit s'il manque |
| `derived.php` | données calculées (§ 5) | marqué « à recalculer » à chaque enregistrement, recalculé après l'envoi de la page ou par la tâche planifiée |
| `search.php` | index de recherche | à chaque enregistrement |
| `media.php`, `media-versions.php`, `media-usage.json` | médiathèque, versions des fichiers retouchés, « utilisée dans » | à chaque modification |
| `carte-*.json`, `sitemap.xml`, `share/` | données de la carte, plan du site, images de partage | à la demande |
| `pdf/` | PDF exportés (fiches, saisons, face-à-face, bilans, records, kits souvenirs) | à la demande ; nom lié à la date de modification de la fiche et aux données calculées, donc refait dès qu'un contenu change (kit souvenirs : un fichier par langue, refait seulement si le contenu du kit change) ; ménage des fichiers de plus de 30 jours |
| `correcteur/` | réponses de Gemini au correcteur, une par texte (empreinte du texte, du modèle et des consignes) : un texte inchangé n'est jamais renvoyé | à la demande ; ménage des réponses inutilisées depuis 180 jours |
| `chiffres-{fr,en}.json` | les 100 chiffres du FCSM (§ 7 nonies), déjà mis en forme dans chaque langue | refaits quand `derived.php`, `index-2.php` ou le dictionnaire anglais changent ; calculés d'avance par la tâche « statistiques » |
| `audio-resumes.ser` | résumés automatiques des fiches audio (texte lu par défaut), avec l'empreinte de chaque fiche : l'écran Système › Fiches audio et les traitements groupés ne les recalculent pas (4 s pour tout le musée sinon) | à chaque calcul sur tout le musée, pour les seules fiches modifiées ; tout est refait si `FicheAudio.php` ou `Unknown.php` change |
| `controle-site.php` | vérifications du site hors fiches (redirections, référentiels, rubriques, textes de l'interface), pour le compteur d'alertes graves du menu | refaites dès qu'un des fichiers lus change (empreinte des dates et tailles) et à chaque contrôle complet |

**Travail après l'envoi de la page** (recalcul de `derived.php` et des 100 chiffres, mesure
d'audience, e-mail de mot de passe oublié) : `Response::detach()` libère la session puis termine
la page (`fastcgi_finish_request()` sous PHP-FPM, `litespeed_finish_request()` sous LiteSpeed).
Sans cela, le fichier de session reste verrouillé jusqu'à la fin du calcul et le clic suivant
dans le back-office attend (plusieurs secondes après chaque enregistrement). Les calculs longs
faits pendant une page (écran Fiches audio, lancement d'un traitement groupé, vérification
GitHub) appellent `Session::release()` d'abord : la session se rouvre d'elle-même au prochain
accès (message, jeton des formulaires créé avant). `Settings::schema()` et `defaults()` sont lus
une fois par page (chaque réglage jamais enregistré relisait `config/settings.php`).

`index-2.php` et `search.php` sont modifiés par `App\Core\PhpCache::update()` : le fichier est
relu sous verrou, seules les fiches enregistrées sont remplacées, puis il est réécrit. Un
processus qui a chargé l'index plus tôt (tâche planifiée, longue requête) n'écrase donc
jamais ce que d'autres ont enregistré entre-temps (en mode « lot », les modifications sont
appliquées ensemble à la fin). Recalcul de `derived.php` : la marque `derived.dirty` est
renommée `derived.building` au début du calcul ; un enregistrement fait pendant le calcul
recrée `derived.dirty` et un nouveau calcul suivra (un calcul interrompu depuis plus de
15 minutes est refait). Le recalcul d'après-page ne fait jamais attendre : si un autre
processus calcule déjà, il abandonne. La limite de mémoire est portée à 512 Mo pendant le
calcul (environ 200 Mo pour 3 000 fiches).

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

`scorers` garde, pour chaque match, les buteurs sochaliens de la composition avec les minutes
de leurs buts et leurs penaltys (« 32' s.p. », « sp », « pen. »). Alertes de qualité propres
aux statistiques : total des buts ≠ buteurs (`buts`), composition recopiée d'un autre match
(`tableau`), total d'un tableau de statistiques ≠ somme des saisons (`stats`) et tableau de
statistiques identique sur plusieurs fiches de joueurs (`stats-copie`, modèle recopié de
l'ancien site : 594 fiches à la reprise), joueur inscrit deux fois dans une composition
officielle (`doublon` : une seule apparition, buts sans doublon de minute, temps de jeu
plafonné) et dates d'une personne incohérentes (`dates` : naissance improbable, décès avant
la naissance, départ avant l'arrivée, âge d'arrivée impossible).

Autres alertes :
- jour de la semaine incohérent avec la date ;
- date en toutes lettres illisible ;
- tirs au but sans score de séance (`tab`) ;
- lien vidéo non reconnu (`video`) ;
- « xx » de l'ancien site (`inconnu`, information inconnue, avec l'endroit : fiche
  d'identité, naissance, arbitre, texte…).

Contrôles des matchs (`matchChecks`, surtout utiles pour les fichiers modifiés hors du
masque de saisie, qui calcule lui-même saison et résultat) : date absente ou impossible
(`match-date`), date hors de la saison indiquée ou saison vide (`saison`, amicaux de juin
et de juillet tolérés), résultat incohérent avec le score ou tirs au but sur un score non nul
(`resultat`), score d'un match à venir ou match officiel joué sans score (`score`),
composition d'un match officiel (`compo` : minute d'entrée sur une ligne de titulaire, plus
de 11 titulaires, deux gardiens, moins de 9), même match saisi deux fois (`doublon-match`,
même jour et même adversaire). Personnes : date impossible au calendrier (dans `dates`),
aucune rubrique (`role`), même nom qu'une autre fiche sans dates de naissance différentes
(`homonyme`). Toutes les fiches (`ficheChecks`) : titre vide (`titre`), adresse vide, mal
formée ou partagée par deux fiches (`adresse`), rubrique supprimée (`rubrique`), image absente
de la médiathèque (`image`), fichier d'image absent du serveur (`image` par fiche ; une seule
alerte `photos` s'il manque plus de 20 % des fichiers, cas d'un serveur neuf avant la copie
des photos), fichier de fiche illisible ou mal numéroté (`fichier`), version anglaise
dépassée (`traduction`, moyenne si elle avait été corrigée à la main).

L'écran Qualité (`App\Admin\Quality::all()`) les range en onglets (`Quality::TABS`) :
« Statistiques et dates », « À compléter » (`inconnu`, `avenir`, `arbitre`, `video`,
`role`), « Liens joueurs » (sans fiche, `rapproche`, `homonyme`), « Adresses et médias »
(`titre`, `adresse`, `rubrique`, `image`, `fichier`), « Traductions à revoir »
(`traduction`)… S'y ajoutent les vérifications hors fiches de
`App\Services\Controle::siteChecks()` : redirections, suivies comme par un visiteur avec
`Kernel::probe()` (même ordre que `dispatch()`, sans exécuter les pages : fichier de `public/`,
image de la médiathèque, `/en`, « / » final ajouté, anciens liens `?p=`, pages calculées à
paramètre vérifiées comme par la page elle-même — saison, face-à-face, bilan, Rétro-Direct,
Fil jaune, réserves —, fiche ou rubrique, redirection) : jamais utilisée, vers une page
absente ou non publiée, en chaîne, en boucle (A → B → A), vers elle-même ;
référentiels (adversaire ou stade en double, graphie qui désigne deux entrées), rubriques
orphelines, textes de l'interface dont les variables `{…}` ou les balises diffèrent en
anglais. Les plus graves viennent d'abord, 300 par page ; « Corriger » ouvre la fiche à
l'onglet concerné (`#infos`, `#compo`, `#seo`…).

### Contrôle complet (bouton « Contrôler maintenant »)

`App\Services\Controle::run()` (route `POST /admin/qualite/controler`, ouverte à tous les
comptes ; `php bin/console.php controle` en ligne de commande) :
1. remet l'index des fiches et la recherche à jour si des fichiers ont changé hors du
   back-office : résumé recalculé depuis chaque fichier et comparé à l'index (même si la
   date de modification interne n'a pas changé, cas d'une restauration), fichier plus
   récent que la recherche ;
2. recalcule toutes les données calculées (`Derived::rebuild()`) ;
3. donne à chaque alerte une clé stable (`Controle::key()` : onglet, fiche ou nom, nature et
   `ref`, ce que vise l'alerte — nom rapproché, adresse partagée, sorte d'écart de date,
   d'erreur de composition, identifiant en double… ; jamais le libellé, qui peut changer :
   nombre de compositions, titre d'une autre fiche, liste de fichiers) ;
4. compare avec le contrôle précédent : nouvelles et corrigées ;
5. enregistre `storage/controle.json` (clés par onglet, nouvelles, historique des 8 derniers
   contrôles) et une ligne du journal.

Un seul contrôle à la fois (verrou `storage/controle.lock`) ; environ 2 à 3 secondes pour
~3 000 fiches ; quatre contrôles au plus en deux minutes par personne, session libérée pendant
le contrôle (les autres onglets restent utilisables). Une alerte est marquée « Nouveau » si
elle est nouvelle au dernier contrôle ou absente de celui-ci (apparue depuis). Exception,
l'onglet Orthographe, rempli peu à peu par le correcteur qui relit tout le musée en tâche de
fond (`Controle::BACKGROUND_TABS`) : une correction proposée n'y est nouvelle que si les
**textes** de sa fiche ont changé depuis le contrôle (empreinte `Proofreader::textSig()` des
champs relus, gardée par fiche dans `controle.json` ; une traduction anglaise, un numéro
d'album ou une publication programmée ne comptent pas). Le correcteur lui-même garde sa
vérification tant que cette empreinte ne change pas (pas de nouvel appel à Gemini). Avant le
premier contrôle, la comparaison se fait avec `app/Resources/controle-reference.json`, les
clés des anomalies des données du dépôt (onglet Orthographe exclu : il dépend du correcteur
de chaque serveur). Elle se réécrit avec `php bin/console.php controle-reference "libellé"`
après une modification des vérifications ou des clés, données du dépôt à jour.

### « xx » de l'ancien site (`App\Front\Unknown`)

Information inconnue au moment de la saisie : « xx » isolé (ou « XX »), année « 19xx »,
taille « 1mxx », rang « xxè » (en minuscules : « XXe siècle » est un vrai siècle), score
« x-x » (`Unknown::RE` ; « XXL », « Maxxsport » ou un identifiant de vidéo ne sont pas
concernés). Jamais affichée ni lue à voix haute : `Unknown::doc()` dans `Fiche::localize()`
(page, PDF), pour l'image de partage et dans le résumé audio réécrit la phrase (« né le xx/xx/1925 à
Aulnoye » → « né en 1925 à Aulnoye », « né le xx à xx (Hongrie) » → « né en Hongrie »,
« Sochaux - Strasbourg du xx/09/1990 » → « … en septembre 1990 »), ou retire la ligne quand
il ne reste rien d'utile (« Taille 1mxx », « Poids xx kg », modèle de match jamais rempli).
Texte riche : seuls les nœuds de texte sont touchés, attributs intacts ; un bloc réduit à
son étiquette (« Arbitre : ») disparaît. L'extrait de l'index (cartes, accueil, recherche) et
les extraits de la recherche sont nettoyés de même. La fiche n'est pas modifiée : l'écran
Qualité liste les « xx » à compléter (`inconnu`). Le résumé audio est calculé sur la fiche
enregistrée, comme au back-office : le texte de l'IA et la voix enregistrée sont reconnus.
Cas couverts : `tests/inconnu.php`.

### Fichiers retouchés à la main

Une fiche est lue seulement si son fichier est un objet JSON qui porte son numéro, un type
connu et, pour un match ou une personne, ses données (`Fiches::usable()` ; sinon alerte
`fichier`, « illisible, incomplet ou mal numéroté »). Les champs d'un type inattendu (« 2 » ou
2.0 au lieu de 2, liste écrite comme un texte, date en nombre…) sont lus comme s'ils étaient
bien formés (`Fiches::normalize()`, sans effet sur une fiche bien formée) et la fiche est
signalée (`fichier`, ref `format`) jusqu'à son prochain enregistrement, qui la répare. En
dernier recours, une fiche qui ferait encore échouer un calcul en est écartée et signalée
(`fichier`, ref `donnees`) ; l'index et la recherche font de même : une fiche ne peut plus
mettre le site entier en erreur. Fichiers de référence abîmés (`data/redirects.json`,
`data/i18n/en.json`, `storage/controle.json`) : signalés dans Qualité, le site continue (sans
redirections, en français) ; ils ne sont jamais réécrits tant qu'ils ne sont pas remplacés.

Les clubs et stades créés automatiquement (`data/collections/clubs.json`, `stades.json`)
sont fusionnés sous verrou à la fin du calcul : seuls les ajouts et les noms des entrées
encore « automatiques » sont écrits, une correction faite pendant le calcul est gardée.

## 6. Images

`/media/{largeur}/{fichier}.webp` (largeurs 160, 320, 480, 640, 800, 1200, 1600) :
`App\Services\Images` génère la vignette WebP avec GD (en appliquant la retouche
éventuelle) dans `public/media/…`, d'où Apache la sert ensuite directement.
`php bin/console.php images [largeur]` les prépare à l'avance (800 par défaut, seulement une
des largeurs ci-dessus). Sur un serveur neuf, tant que les originaux ne sont pas copiés
(`scripts/wp/media-sync.php`), la tâche `statistiques` refait les alertes toutes les
30 minutes pour que l'alerte `photos` disparaisse à la fin de la copie. `img()` ajoute
`?v=` aux fichiers retouchés ou remplacés. Original absent ou illisible : cadre beige servi
en `Cache-Control: no-store`, pour que la photo apparaisse dès que l'original arrive (jamais
gardé par le navigateur). AVIF : vignette si GD sait le lire (`imagecreatefromavif`), sinon
l'original est servi tel quel (les navigateurs l'affichent). Les vignettes des vidéos sont copiées dans
`storage/media/originals/_video/` (`App\Services\VideoThumbs`) pour ne contacter
l'hébergeur vidéo qu'après l'accord du visiteur.

Images de partage 1200 × 630 (`App\Front\Share`, GD, `/partage/{id}.png`) : fiche lue sans
ses « xx » (`Unknown::doc()`) ; emoji retirés et lettres stylisées ramenées aux lettres
simples (`printable()`), sinon GD dessine un carré vide. Cache `storage/cache/share/`,
refait quand la fiche ou sa photo change (`Share::VERSION` pour tout refaire).

## 7. Services

| Classe | Rôle |
|---|---|
| `Search` | recherche plein texte sans base de données (normalisation des accents, pondération des champs) |
| `Rag`, `Gemini` | assistant IA : index sémantique (`storage/ai/`), réponses limitées aux données du site, quotas, journal des questions (RGPD) |
| `Translator`, `I18n` | traduction anglaise des fiches avec Gemini (champ `i18n.en`), libellés de l'interface (`t()`) |
| `Proofreader` | correcteur d'orthographe et de syntaxe (§ 7 ter) |
| `AiCosts` | coût de l'IA en temps réel, budget, remboursements (§ 7 quater) |
| `FicheAudio` | fiches audio : explication de la fiche (durée maximale réglable, 3 min), voix IA, traitement groupé (§ 7 quinquies) |
| `PageAudio` | pages de synthèse racontées (face-à-face, saisons, bilans, records, chiffres) : récit rédigé par l'IA puis voix IA enregistrée (traitements groupés de `FicheAudio`, refaits la nuit quand les chiffres changent), sinon récit automatique calculé à chaque affichage, lu par la voix du navigateur ; français et anglais (§ 7 quinquies) |
| `Updater` | mises à jour en un clic depuis GitHub (Système › Mises à jour) : dernière version de la branche (API, flux Atom en secours), changements, application du seul code qui a changé (`app/`, `bin/`, `config/`, `scripts/`, `templates/`, `public/` sauf `public/media/`), libellés `data/i18n/en.json` fusionnés, `public/.htaccess` modifié gardé, fichiers retirés du dépôt supprimés (manifeste CRC32), sauvegarde et retour arrière, pause du site pendant la copie (`storage/update/maintenance`, lu par le Kernel), caches vidés sauf `storage/cache/correcteur/` ; synchronisation : à chaque vérification, empreintes Git (`sha1("blob <taille>\0<contenu>")`) des fichiers du serveur comparées à l'arborescence de la version (API `git/trees`, un appel par dossier du code, listes gardées sous leur empreinte dans `storage/update/arbres/`), fins de ligne ignorées pour les fichiers texte ; site mis en ligne par FTP et identique : version reconnue (manifeste écrit, sans le `.htaccess` réglé à la main) ; fichiers différents : « Synchroniser avec GitHub » |
| `Payments` | Stripe Checkout et abonnements, PayPal Orders v2 et abonnements, vérification des webhooks |
| `Mailer`, `Newsletter` | e-mails (SMTP ou mail()), newsletter hebdomadaire « Ce jour-là » |
| `Geo` | géolocalisation (répertoire intégré, puis Nominatim d'OpenStreetMap, une requête par seconde) |
| `Backup` | sauvegardes ZIP de `data/` et des fichiers importants de `storage/` |
| `Stats` | mesure d'audience sans cookie ni adresse IP |
| `Pdf` | reçus fiscaux en PDF, sans bibliothèque (les exports des fiches utilisent `App\Pdf`, § 7 bis) |
| `Cron` | tâches planifiées (§ 9) |

## 7 bis. Export PDF (`App\Pdf`, `App\Front\PdfExport`)

Un vrai document A4, pas une impression du navigateur, fabriqué en PHP pur (aucune
bibliothèque à installer) :

| Classe | Rôle |
|---|---|
| `Pdf\TrueType` | lit les polices TrueType (`app/Resources/fonts/`, instances fixes de Big Shoulders Display et Newsreader tirées des polices du site) et n'embarque que les glyphes employés (sous-ensemble). Caractère absent de la police : espace fine ou séparateur → espace ; lettre accentuée ou stylisée (« 𝗣 » des tweets, « ᵉ ») → lettre de base ; emoji, pictogramme, flèche, variante d'affichage → omis ; autre écriture → « ? ». La table ToUnicode donne le caractère réellement imprimé (texte copié ou cherché exact) |
| `Pdf\Writer` | écrit le fichier PDF 1.7 : polices Type0/CIDFontType2 avec table ToUnicode (texte sélectionnable et copiable), images JPEG (photos converties depuis les vignettes WebP) et PNG avec transparence (blason), liens, signets, métadonnées, langue |
| `Pdf\Layout` | mise en page aux couleurs du musée : bandeau rayé et blason qui déborde, texte enrichi avec retour à la ligne, intertitres (signets), listes, citations, encadré « le chiffre », grilles, tableaux à en-tête répété, photos, galerie recadrée, bandeau courant et pieds de page « page n / total » |
| `Pdf\HtmlFlow` | convertit le HTML des fiches (paragraphes, gras, italique, liens, listes, citations, tableaux, images) ; une citation posée directement dans une liste (tweets de l'ancien site) est imprimée à sa place, la numérotation de la liste continue après elle |
| `Front\PdfExport` | contenu de chaque document (mêmes données que les pages : `Fiche::matchData()`, `personData()`, `articleData()`, `Explore::seasonData()`, `opponentData()`, `bilanPage()`), cache, réponse |

Adresses : `/pdf/fiche/{id}.pdf`, `/pdf/saison/{saison}.pdf`, `/pdf/face-a-face/{club}.pdf`,
`/pdf/bilan/{clé}.pdf`, `/pdf/records.pdf` (et `/en/pdf/…` en anglais). Réponse en
téléchargement (`Content-Disposition: attachment`), `X-Robots-Tag: noindex` et `Disallow`
dans `robots.txt` (pas de contenu en double pour Google). Fabrication limitée à 40 PDF
par adresse IP et par 10 minutes (les PDF déjà en cache sont servis sans limite) ; une
fiche non publiée n'est exportable que par un membre connecté du back-office.
Repères : un match ≈ 0,5 s et 5 pages ; le joueur le plus capé (423 matchs) ≈ 1 à 3 s,
24 pages, 65 Mo de mémoire au plus.

Compositions : les symboles saisis au back-office (↑ ↓, 🟨 🟥, ⚽), absents des polices,
s'impriment en lettres comme on les écrit aussi (`PdfExport::marks()` : « Entrée 59' Sortie
66' », « J 45'+2 R 55' »). Ménage du cache (un PDF sur 40 fabriqués) : fichiers de plus de
30 jours ; un fichier renommé ou supprimé entre-temps par une autre demande est ignoré.
`tests/pdf.php` vérifie polices, symboles et citations.

Pour changer la mise en page : `Pdf\Layout` (couleurs, polices, blocs) et
`Front\PdfExport` (contenu) ; augmenter `PdfExport::VERSION` (et `Kit::VERSION` pour le kit
souvenirs) pour refaire les PDF en cache.

## 7 ter. Correcteur d'orthographe (`App\Services\Proofreader`)

- **Champs vérifiés** : dans les formulaires, un champ de texte rédigé porte `data-proof`
  (nature `text`, `title`, `quote` ou `caption`, option `proof` de `App\Admin\Form` ; par
  défaut pour les éditeurs de texte riche) et `lang="en"` pour un champ `…_en` ou
  `i18n_en.…`. Ces champs ont aussi `spellcheck` (soulignement du navigateur).
  `public/assets/admin/correcteur.js` envoie leurs valeurs à `POST /admin/api/correcteur`
  (bouton `[data-proofread]` : fiches, accueil, rubriques, collections) et affiche le
  panneau des propositions.
- **Texte** : le HTML est ramené à ses nœuds de texte, un retour à la ligne autour de chaque
  bloc ; le même calcul côté navigateur retrouve le passage (contexte avant/après, rang de
  l'occurrence, espaces tolérées) et ne modifie que les nœuds de texte : balises et liens
  restent intacts. La valeur passe ensuite par `BO.setValue` (éditeur visuel rechargé,
  formulaire marqué « non enregistré »).
- **Règles du musée** (français, sans service extérieur) : mot répété, espace avant une
  virgule ou un point, espace oubliée après la ponctuation, apostrophe d'élision suivie d'une
  espace, ordinaux (« 2e », « 1re », « XXe »), « À » en tête de phrase. Volontairement
  prudentes ; essayées sur les 2 940 fiches reprises (`tests/correcteur.php`).
- **Gemini** (si la clé est réglée) : textes groupés par lots de 6 000 caractères (un texte
  long est découpé aux paragraphes), lots envoyés en parallèle (`Gemini::generateMany`,
  réponse JSON à structure imposée). Chaque proposition est contrôlée avant d'être montrée :
  l'extrait doit exister dans le texte, les chiffres restent identiques, les mots protégés
  (noms des personnes, clubs, stades, joueurs cités, dictionnaire du musée) ne changent pas,
  une réécriture trop éloignée est écartée, une différence purement typographique aussi.
- **Dictionnaire du musée** : collection `dictionnaire` (`data/collections/dictionnaire.json`,
  écran Qualité › Dictionnaire du musée, bouton « + Dictionnaire » du panneau).
- **Corrections ignorées** : `storage/correcteur/ignorees.json`, par fiche ou par écran.
- **Tâche de fond** (`correcteur`, à chaque passage du cron, 40 s au plus) : vérifie d'abord
  les fiches nouvelles ou modifiées, puis les autres ; résultat par fiche dans
  `storage/correcteur/fiches/{id}.json`, résumé dans `storage/correcteur/index.json` (date
  de modification de la fiche, nombre de corrections, fautes de langue, exemple).
  Plafond quotidien d'appels (Réglages › Correcteur, 300 par défaut), pause d'une heure si
  Gemini répond « quota atteint ». Un passage complet du musée demande environ 4 200 appels
  (environ 10 millions de jetons envoyés) : `php bin/console.php correcteur` le fait d'un
  coup, sans plafond.
- **Essais sans clé** : avec le serveur de développement de PHP uniquement (`php -S`), la
  variable d'environnement `GEMINI_MOCK_URL=http://127.0.0.1:port/` dirige les appels vers
  un faux Gemini local ; impossible sur l'hébergement.

## 7 quater. Coûts de l'IA (`App\Services\AiCosts`)

- **Comptage** : chaque réponse de Gemini (`Gemini::generate`, `generateMany`, `embed`) est
  enregistrée par `AiCosts::record()` avec son usage (`assistant`, `traduction`,
  `correcteur`, `index`, `autre` : option `for` des appels), le demandeur (membre connecté,
  « Visiteur du site » pour l'assistant, « Tâche automatique » en ligne de commande) et la
  fiche concernée (option `ref` ou `AiCosts::$ref`, ex. `fiche:123`). Jetons lus dans
  `usageMetadata` : envoyés (`promptTokenCount` + `toolUsePromptTokenCount`), dont lus dans
  le cache (`cachedContentTokenCount`, tarif réduit), produits (`candidatesTokenCount`) et
  de réflexion (`thoughtsTokenCount`, facturés comme des jetons produits). Embeddings sans
  compte : estimation à 4 caractères par jeton (signalée). Une erreur d'écriture n'interrompt
  jamais l'appel à Gemini.
- **Barème** : dollars par million de jetons (entrée, sortie, entrée en cache) par début
  d'identifiant de modèle ; la ligne au début d'identifiant le plus long l'emporte, puis la
  plus récente déjà en vigueur (date « à partir du » : une hausse annoncée se saisit à
  l'avance). Tarifs publics de Google d'octobre 2026 par défaut (`DEFAULT_PRICES`), barème
  modifié dans l'écran : `storage/ia/tarifs.json`. Modèle inconnu : 0,50 $ / 3,00 $
  (signalé « tarif par défaut »). Le coût est figé à l'appel : changer le barème ne
  recalcule rien.
- **Fichiers** (`storage/ia/`) : `AAAA-MM.jsonl` (une ligne par appel : date, usage,
  modèle, jetons, coût en dollars, demandeur, fiche ; `g` = coût évité au niveau gratuit,
  `e` = jetons estimés, `x` = tarif par défaut), `totaux.json` (cumuls par mois, jour, usage
  et modèle, mis à jour sous verrou), `remboursements.json`, `tarifs.json`.
- **Écran** Système › Coûts IA (`App\Admin\Costs`, `templates/admin/system/couts.php`,
  `public/assets/admin/couts.js`) : chiffres rafraîchis toutes les 10 secondes par
  `GET /admin/api/couts` quand l'onglet est visible ; relevé mensuel PDF
  (`/admin/couts-ia/releve/AAAA-MM.pdf`, `Pdf\Layout` : totaux par usage, modèle et jour,
  cases de signature) et détail CSV (`/admin/couts-ia/detail/AAAA-MM.csv`, UTF-8 avec BOM,
  séparateur « ; » pour Excel). Remboursement noté par un administrateur, mois terminés
  seulement ; rappel sur le tableau de bord.
- **Budget** (Réglages › Coûts IA, en euros, au taux réglé) : une fois atteint,
  `AiCosts::paused()` suspend les tâches automatiques (traductions, index de l'assistant,
  Gemini dans la relecture de fond : les règles du musée continuent) et, si la case est
  cochée, l'assistant du site (réponse 429 « L'assistant fait une pause »). Les boutons du
  back-office ne sont jamais bloqués. Niveau gratuit de Google : appels comptés à 0 $.
- **Coût d'une action** : `AiCosts::request()` (appels de la requête en cours) est renvoyé
  par `/admin/api/correcteur` et `/admin/api/traduire` et ajouté aux messages de traduction
  et de réindexation (`Admin\Base::aiCost()`).

## 7 quinquies. Fiches audio (`App\Services\FicheAudio`)

- **Sur le site** : bouton « Écouter (durée) » des fiches (`templates/partials/audio-button.php`,
  `public/assets/js/audio.js`), caché sans JavaScript. Il joue la voix IA enregistrée si elle
  correspond au texte lu, sinon lit le texte avec la synthèse vocale du navigateur
  (`speechSynthesis`, phrase par phrase, meilleure voix de la langue). Le texte lu s'affiche
  sous le bouton pendant l'écoute.
- **Pages de synthèse** (`App\Services\PageAudio`) : même bouton sur les face-à-face, saisons,
  bilans, records et chiffres (`Explore::withAudio()`). Le récit est construit à chaque affichage
  à partir des variables de la page (`opponent()`, `competition()`, `stadium()`, `season()`,
  `records()`, `chiffres()`) : accroche selon le nombre de rencontres, bilan et sa tendance,
  domicile et extérieur, compétitions, premier et dernier match, plus large victoire, plus lourde
  défaite, affluence, buteurs (compositions, `Derived::apps`), série sans défaite, finales et
  tour le plus avancé des coupes, conclusion. `speakable()` prépare le texte pour la voix
  (milliers sans espace, scores « 7 à 0 », dates en toutes lettres, saisons « 1987‑1988 » à
  trait d'union insécable). Gratuit, sans état ni cache, coupé à `FicheAudio::maxWords()`.
- **Récits des pages par l'IA** : chaque page a ses « faits » (`factsFor()`, toujours calculés en
  français : même empreinte pour les deux langues) : chiffres, domicile et extérieur,
  compétitions, premier et dernier match, plus large victoire, plus lourde défaite, affluence et
  début de leur fiche, buteurs, série sans défaite, finales, saison par saison, tous les matchs
  (80 au plus), bilan de la saison (fiche), classement des records, 100 chiffres. `aiPrompt()` :
  consigne de conteur (comme les fiches) et faits en JSON. Texte rangé dans
  `storage/audio/pages/{page}.json` avec l'empreinte des faits (`sig()`, avec `TEXT_VERSION` et
  la durée maximale) ; lu seulement si l'empreinte correspond encore (`choose()`), sinon récit
  automatique. Pages : `slugs()` (adversaires, saisons, compétitions, stades, records avec leurs
  filtres, chiffres), environ 830 pages qui se racontent, 1 660 récits. Rédaction : clés
  `page:{page}:{langue}` dans les traitements groupés de texte (`FicheAudio::queueTexts()`,
  `submit()`, `process()`, sans voix ensuite) ; chaque nuit, `PageAudio::launch()` (réglage
  `audio.pages_ai`) confie les récits manquants ou dépassés (`plan()`, une dizaine de secondes,
  dernier calcul dans `storage/audio/pages-plan.json`). Coûts : usage « Fiches audio », référence
  `page:{page}`.
- **Voix IA des pages** (réglage `audio.pages_voice`) : un traitement de récits de pages a
  `then_voice` ; à la fin, les voix de ses pages sont commandées (travail `voix` marqué `pages`).
  Essai : `tryPage()` (adresse → page par `slugFromUrl()`, récit par `Gemini::generate` et voix
  par `Gemini::speech`, tout de suite au tarif normal). La rédaction de nuit ne commence qu'après
  « Lancer pour tout le musée » (`activate()`, `storage/audio/pages-etat.json`).
  `plan()` liste aussi les voix manquantes des récits à jour (`voices`), confiées par `launch()`
  (`FicheAudio::queueVoices()`). Demande : `PageAudio::speechRequest()` (le récit rangé, passé par
  `speakable()`, voix réglée, sans consigne de ton) ; rangement : `PageAudio::storeVoice()` via
  `FicheAudio::encodeVoice()` (MP3, voir « Voix IA ») dans
  `public/media/audio/pages/{page}-{langue}-{empreinte}.mp3`, ancienne voix supprimée. Jouée
  (`url` du bouton) seulement si son empreinte `th` est celle du récit affiché.
- **Texte lu** (`maxWords()` : durée maximale `audio.max_minutes`, 3 par défaut, × 150 mots),
  par ordre de priorité : écrit à la main (`src: manual`),
  rédigé par Gemini (`src: ai`, valable tant que l'empreinte `sig` des titres, textes et faits
  de la fiche n'a pas changé : modifier une photo ne l'invalide pas), sinon résumé automatique
  construit à la volée (`template()` : date, stade, score, buteurs, carrière, puis
  introduction, texte et brèves de la fiche ; abréviations dites en toutes lettres). La
  consigne de l'IA demande d'expliquer toute la fiche, longueur selon son contenu ; l'empreinte
  `sig` inclut la version de cette consigne et la durée maximale (les changer fait refaire les
  textes IA). Consigne : un historien qui raconte (accroche, décor, récit en paragraphes,
  conclusion), paragraphes gardés (`paragraphs()`, `fitText()`). Modèle de rédaction :
  `textModel()` (`audio.text_model`, sinon `ai.model`). `plan(..., textOnly: true)` /
  `launch(..., textOnly: true)` : textes seulement, sans voix IA (carte « Réécrire les textes
  avec l'IA »). Lots de voix : `voiceBatch()`, environ 300 Mo de résultats quelle que soit la durée. Version anglaise pour les fiches traduites.
- **Voix IA** : `Gemini::speech()` (modèle de voix réglé ou le meilleur disponible, voix
  réglable ; le texte seul est envoyé), PCM 16 bits mono à 24 kHz (`Gemini::speechAudio()`
  recolle les morceaux d'une réponse en plusieurs parties et ignore ce qui n'est pas de
  l'audio). `FicheAudio::encodeVoice()` :
  1. **fin nettoyée** (`trimTail()`) : la synthèse ajoute parfois du bruit (grésillement) ou un
     long silence après la dernière phrase. Dernier son voisé cherché en remontant depuis la fin
     (trames de 20 ms assez fortes et périodiques : autocorrélation ≥ 0,5 pour une hauteur de
     70 à 400 Hz, signal pré-accentué ramené vers 8 kHz ; 3 trames de suite), coupe 0,26 s
     après (consonne finale) ou dès que le niveau tombe 35 dB sous la voix, fondu de 50 ms ;
  2. **MP3** : ffmpeg (LAME, 48 kbit/s) s'il est sur le serveur, sinon l'encodeur du site
     `App\Services\Mp3Encoder` (cas d'o2switch), sinon WAV en dernier recours.

  `Mp3Encoder` : PHP pur, adapté de shine (licence LGPL 2 pour ce seul fichier,
  `docs/licences/LGPL-2.0.txt`) ; MPEG-2 Layer III mono, 16 / 22,05 / 24 kHz, débit constant
  (`FicheAudio::MP3_KBPS` = 64 kbit/s, un peu plus que LAME faute de modèle psychoacoustique).
  Banc de filtres polyphase (fenêtre de la norme, matrice repliée en 32 × 32), MDCT des blocs
  longs, réduction du repliement ; pour chaque granule, le pas de quantification le plus fin qui
  tient dans le débit (dichotomie puis pas à pas), tables de Huffman choisies par région, sans
  facteurs d'échelle ni réservoir de bits : chaque trame se suffit à elle-même, sa fin est
  remplie de données annexes. Quadruplets dans l'ordre de la norme (8v + 4w + 2x + y ; shine
  les inverse). Mesures : voix rendue au même niveau, rapport signal/bruit 45 dB à 64 kbit/s
  (39 à 48), environ 6 % du temps réel en local (3 min de voix : une dizaine de secondes) ;
  décodage vérifié par ffmpeg et Chromium (tests/audio.php).

  Fichier `public/media/audio/{id}-{langue}-{empreinte}.mp3`, servi directement par Apache (mis
  en cache un an : le nom change avec le contenu). Valable tant que l'empreinte `th` du texte
  lu ne change pas. Les voix enregistrées en WAV avant l'encodeur sont converties par la tâche
  planifiée (`convertWavs()`, avec le temps qui reste au passage « audio », fin nettoyée,
  ancien fichier supprimé ; nombre restant affiché dans Système › Fiches audio).
- **Traitement groupé** (API Batch de Google, moitié prix) : `launch()` crée des travaux de
  150 fiches (voix) ou 800 (résumés IA, suivis automatiquement des voix) dans
  `storage/audio/jobs.json` ; la tâche planifiée `audio` (et `php bin/console.php audio`)
  écrit le fichier JSONL des demandes, l'envoie (`Gemini::uploadFile`, envoi en deux temps),
  crée le traitement (`batchCreate`), l'interroge toutes les 2 minutes (`batchGet`),
  télécharge les résultats sans les charger en mémoire (`download`) et les range ligne par
  ligne en reprenant là où il s'était arrêté. Chaque nuit, les voix IA devenues anciennes
  sont refaites (réglage). Coûts comptés (usage « Fiches audio », `batch` : moitié prix).
- **Back-office** : carte « Écouter » de l'éditeur (`public/assets/admin/audio.js`,
  `POST /admin/api/audio` : enregistrer, automatique, ia-texte, voix, supprimer-voix) ;
  écran Système › Fiches audio (`App\Admin\Audio`) : chiffres, estimation, essai sur
  20 fiches ou tout le musée, suivi et annulation des travaux.

## 7 sexies. Rétro-Direct (`App\Services\RetroDirect`, `App\Front\Retro`)

- **Principe** : un match est rejoué en direct le jour et à l'heure programmés
  (`data/collections/retrodirect.json` : `id`, `date`, `time`, `intro`, `intro_en`, `by`,
  `at`). Aucune IA : la chronologie est calculée depuis la fiche et déroulée par le navigateur
  (`public/assets/js/retro.js`) à l'horloge du serveur (heure de la page + `performance.now()`).
- **Chronologie** (`timeline()`, en secondes depuis le coup d'envoi ; une minute de match =
  une minute réelle) : temps forts (`highlights`), buts, entrées en jeu appariées aux sorties
  de la même minute (à deux minutes près ; une sortie seule n'est pas annoncée), cartons,
  mi-temps (15 min), reprise, fin du temps réglementaire, prolongation (5 min de pause, si
  `aet`, « a.p » ou tirs au but avec des minutes au-delà de la 90e), séance de tirs au but
  (8 min), coup de sifflet final. Temps additionnel : « 45+2 », ou « 93 » sans prolongation.
  Les buts viennent des temps forts si leurs scores mènent au score final (retournés quand
  ils sont notés du point de vue de Sochaux), sinon de la ligne des buteurs ; le camp du
  buteur colore le but (jaune pour Sochaux). Noms des joueurs : ceux de la fiche du joueur
  quand elle est mieux accentuée. Il faut 4 événements datés (`playable()`).
- **Programme** (`program()`) : états `avenir`, `direct` (jusqu'à 15 min après le coup de
  sifflet final), `termine`. Durée exacte (chronologie) pour les directs à moins de 2 jours,
  estimée sinon (bandeau, API : `program(…, false)` ne relit pas la fiche).
  `suggestions()` : anniversaires ronds (10, 20, 25, 30, 40, 50… ans) à venir, matchs d'au
  moins 5 temps forts, classés par `interest()` (temps forts plafonnés à 20, coupe, finale ou
  demi-finale, victoire large, 5 buts ou plus, affluence, fiche à la une ; amicaux écartés).
  `classics()` : grands matchs à revivre (un par adversaire). Derived : `hl` (nombre de
  temps forts) dans le résumé de chaque match.
- **Site** : `/interactif/retro-direct/` (direct en cours, prochains directs, grands matchs,
  directs passés), `/interactif/retro-direct/{dernier segment de l'adresse du match}/`
  (modes `live`, `upcoming` : compte à rebours sans score puis rechargement au coup d'envoi,
  `replay` : ×1, ×10, ×60, « Temps fort suivant », pauses ramenées à 4 s),
  `/interactif/retro-direct/agenda.ics` (tous les directs à venir, ou un seul avec
  `?match=&date=` ; heures UTC, lignes pliées à 75 octets). Bouton « Revivre en direct » sur
  les fiches de match rejouables, message dans le bandeau (en cours, ou dans les 7 jours ;
  case « Rétro-Direct » d'Éditorial › Accueil & bandeau), plan du site.
- **API** `POST /api/retro-direct` (JSON) : `presence` (jeton aléatoire du navigateur toutes
  les 30 s, oublié après 75 s ; spectateurs et pic) et `react` (⚽ 👏 😱) seulement pendant un
  direct, `etais` (« J'y étais ! », par match, 2 par jour et par adresse) ; 40 requêtes par
  minute et par adresse. Compteurs dans `storage/retro/{id}-{date}.json` et
  `storage/retro/etais.json`.
- **Back-office** : Interactif › Rétro-Direct (`App\Admin\Retro`) : programme (modifier,
  retirer), programmation d'un match (date du prochain anniversaire proposée,
  `public/assets/admin/retro.js`), anniversaires des 30 à 365 prochains jours, « Programmer
  à 20 h » en un clic.

## 7 septies. Le Fil jaune (`App\Services\FilJaune`, `App\Front\Fil`)

- **Réseau** : deux joueurs sont coéquipiers s'ils figurent dans la même composition
  (rôle « joueur » des apparitions de `Derived` : titulaires et remplaçants entrés en jeu ;
  fiches publiées seulement). `graph()` le construit à chaque page (quelques centièmes de
  seconde) : `adj` (matchs joués ensemble), premier et dernier match de chaque paire,
  matchs par joueur, adresses (dernier segment de l'adresse de la fiche).
- **Chaîne** (`path()`) : distances depuis l'arrivée (parcours en largeur), puis descente par
  le lien le plus solide (le plus de matchs ensemble) à longueur égale. `find()` retrouve un
  joueur par adresse, numéro ou nom (le plus capé en cas d'homonymes).
- **Records** (`records()`) : familles (composantes), plus grande distance et distance
  moyenne (parcours depuis chaque joueur, 0,2 s), plus connectés, inséparables ; gardés dans
  `storage/cache/fil-jaune.json`, refaits quand `storage/cache/derived.php` change.
- **Défi du jour** (`daily()`) : tirage déterministe par date (`Random\Randomizer` sur
  `Mt19937`, sans toucher au générateur global) de deux joueurs d'au moins 40 matchs à 3 ou 4
  passes ; le visiteur choisit un coéquipier à chaque passe (`public/assets/js/filjaune.js`,
  `GET /api/fil-jaune?id=` : coéquipiers d'un joueur, 300 requêtes par minute et par adresse),
  progression gardée dans le navigateur, score à partager.
- **Pages** : `/interactif/fil-jaune/` (recherche avec liste native `datalist`, défi,
  records ; `?a=&b=` redirige vers la chaîne, `?de=` préremplit), `/interactif/fil-jaune/{a}/`
  (constellation en SVG : coéquipiers en spirale du plus fidèle au plus rare, taille selon les
  matchs ensemble), `/interactif/fil-jaune/{a}/{b}/` (la chaîne, avec le premier match de
  chaque lien). Encadré « Le Fil jaune » sur les fiches des joueurs reliés.

## 7 octies. Kit souvenirs et « Ils y étaient » (`App\Services\Souvenirs`, `App\Front\Kit`, `App\Services\Qr`)

- **Match du mois** (`candidates()`, `match()`) : matchs joués ce mois-là les années passées
  (3 temps forts au moins), classés par l'intérêt du Rétro-Direct, plus un bonus pour un
  anniversaire rond, pour 30 à 60 ans d'âge et pour une vraie photo. Choix des historiens et
  mot d'introduction dans `data/collections/souvenirs.json` (`{"2026-10": {"match", "intro"}}`).
- **Kit** (`kit()`) : récit (buts d'abord, 8 temps forts au plus), brève, six visages (joueurs
  de la composition avec une vraie photo, complétés par les joueurs de l'époque, ordre tiré
  par mois), quiz (premier buteur de Sochaux et affluence, avec des réponses plausibles, puis
  le quiz du site ; 3 réponses au plus, tirage déterministe par mois), questions pour
  raconter, adresse du musée (`legal.address`, sinon `dons.org_address`) et e-mail.
- **PDF** (`Kit::build()`, moteur `App\Pdf\Layout`) : 4 pages A4 en gros caractères ;
  solutions à l'envers (`Layout::textUpsideDown()`) ; QR code vers `/souvenir/{id}/` (redirige
  vers `/contribuer/?type=temoignage&fiche=…`). Cache `storage/cache/pdf/souvenirs-{mois}-{empreinte}.pdf`
  (empreinte : données calculées, choix du mois, adresse du site, langue). Pages :
  `/interactif/souvenirs/` (mois en cours, mois suivant à préparer, six mois passés, derniers
  témoignages), `/interactif/souvenirs/{aaaa-mm}.pdf` (jusqu'à deux mois à l'avance).
- **QR code** (`App\Services\Qr`, PHP pur) : mode octet, correction M, versions 1 à 10
  (213 octets), Reed-Solomon sur GF(256), placement en zigzag, 8 masques et pénalités de la
  norme ; `matrix()` et `svg()`. Vérifié par décodage (versions 1 à 10, accents).
- **« Ils y étaient »** : sur chaque fiche de match, compteur « J'y étais ! »
  (`RetroDirect::etais`, `public/assets/js/etais.js`), témoignages publiés, lien vers le
  formulaire. Un témoignage validé (Communauté › Contributions) avec « Publier ce souvenir »,
  rattaché à une fiche de match, est publié avec un texte et une signature relus
  (`public`, `public_text`, `public_name` de la contribution) ; l'index
  `storage/inbox/temoignages.json` est refait à chaque validation, refus ou suppression.
- **Back-office** : Interactif › Kit souvenirs (`App\Admin\Kit`) : trois mois, choix du
  match, mot d'introduction, PDF ; souvenirs publiés.

## 7 nonies. Les chiffres du FCSM (`App\Services\Chiffres`, `/chiffres/`)

- **Sources** (badge de chaque chiffre) : *Carrières* (onglet Statistiques des fiches
  joueurs, lu par `career()` : colonnes « compétition / Buts » rangées par `compKey()`, total
  de la ligne s'il existe, « ? » = saison au club aux chiffres perdus), *Matchs racontés*
  (matchs officiels de `Derived`, sans amicaux, Coupe d'été ni Coupes diverses ; apparitions
  et `scorers`), *Récits des matchs* (temps forts : `goalSequence()` ne garde un match que si
  les scores « (1-0) » avancent d'un but à la fois, dans l'ordre des minutes, jusqu'au score
  final), *Fiches des Lions* (naissance au jour près, taille, pied, formé au club).
- **Garde-fous** : séries comptées seulement dans les blocs de saisons racontées en entier
  (28 matchs de championnat au moins ; `longestRun()` ne passe jamais d'un bloc à l'autre) ;
  compositions signalées `tableau` ou `buts` écartées de tout ce qui vient des compositions ;
  dates signalées `date` écartées des âges ; tableau de carrière recopié sur plusieurs fiches
  gardé pour la seule fiche dont l'arrivée et le départ collent (`dropCopiedCareers()`),
  sinon écarté ; date de naissance donnant moins de 15 ou plus de 45 ans le jour du match, ou
  plus de 36 ans à l'arrivée au club, ignorée.
- **Chapitres** (`CHAPTERS` : titre, chapeau, quota) et ordre de priorité (`ORDER`) : chaque
  chapitre calcule plus de chiffres que son quota ; `quotas()` en retient 100 et comble un
  chiffre manquant par les réserves des autres chapitres. Doublons évités (festival offensif
  identique à la plus large victoire, plus longue fidélité du recordman des matchs,
  invincibilité à Bonal faite de victoires seulement).
- **Passes décisives** (`passer()`) : « passe / centre / corner / coup franc / remise… de X »
  dans le récit d'un but sochalien, X reconnu parmi les joueurs de la composition du match ;
  le buteur est retrouvé par la minute du but (`scorers`).
- **Cache** : `all()` lit `storage/cache/chiffres-{langue}.json` (textes, nombres, dates et
  adresses déjà dans la langue) ; signature : dates de `derived.php`, `index-2.php` et du
  dictionnaire anglais. Calcul complet : 5 s environ ; `warm()` le fait d'avance pour chaque
  langue dans la tâche « statistiques ».
- **Chiffre du jour** (accueil, sous « Ce jour-là ») : `daily()` lit `cached()` (le cache,
  même périmé : un cache absent ou périmé est refait après l'envoi de la page, l'accueil
  n'attend jamais) ; un chiffre par jour, ordre mélangé par cycle (`Random\Randomizer` sur
  `Mt19937`, graine 1928 + numéro du cycle), chacun une fois par cycle. Lien vers
  `/chiffres/#clé` ; réglage `home.daily_figure` (Éditorial › Accueil & bandeau).
- **Page** : `Explore::chiffres()`, gabarit `templates/chiffres.php`, styles
  `public/assets/css/chiffres.css` (compteurs animés par `site.js`, `data-count`, nombres
  entiers seulement). Liens : méga-menu Matchs › Explorer, Interactif › Explorer l'histoire,
  page des records, plan du site.

## 8. Back-office

- `App\Admin\Router` : connexion obligatoire (sauf connexion, premier accès, invitation,
  mot de passe oublié), jeton CSRF sur toute requête POST (champ `_csrf` ou en-tête
  `X-CSRF`), en-têtes `X-Frame-Options: DENY` et `noindex`.
- Deux niveaux (`App\Core\Auth::can()`) : l'utilisateur peut tout faire sauf la gestion
  des comptes (`users`), les réglages (`settings`), la suppression définitive (`destroy`)
  et la restauration de versions (`restore`, `backup_restore`). Écrans techniques réservés
  aux administrateurs (`Router::ADMIN_ONLY`, menu `Base::NAV`) : Assistant IA, Fiches audio
  (traitement groupé), Coûts IA, Sauvegardes, Tâches planifiées, reçus fiscaux
  (`/admin/dons/recu/…`) ; les coûts d'IA ne sont affichés qu'aux administrateurs
  (`Base::aiCost()`, `aiCostData()`). Dans `App\Admin\Donations`, l'enregistrement d'un don
  hors ligne (`manuel`), l'émission d'un reçu (`recu`) et le remboursement noté d'un don hors
  ligne (`rembourse`) sont refusés aux autres comptes, et cachés dans les écrans.
- **Favoris** (`App\Admin\Favorites`) : ligne ★ de la bande du haut (`templates/admin/layout.php`),
  liens directs propres à chaque compte (clé `favoris` de `storage/users.json`, 12 au plus,
  nom de 40 caractères). « + Ajouter un favori » ouvre une fenêtre (`admin.js`, données
  `#bo-favs` : favoris, page affichée, `choices()` = écrans de `Base::NAV` permis, créations
  `Base::createLinks()`, groupes des Réglages pour les administrateurs) ; `POST
  /admin/api/favoris` (`ajouter`, `retirer`, `renommer`, `ordre`). Adresses du back-office
  seulement (`clean()` : `/admin`, `/admin/…`, `/admin?…`, ni espace ni `//`) ; les écrans
  réservés aux administrateurs (`Base::NAV`, `Router::adminOnly()`) ne sont ni proposés ni
  gardés pour les autres comptes.
- Formulaires : les écrans envoient du JSON (`public/assets/admin/admin.js`, champs nommés
  par chemin pointé : `match.referee`, répétitions `data-repeater`), contrôle de
  modification simultanée, brouillon conservé dans le navigateur.
- **Verrou de modification** (`App\Services\EditLock`, `public/assets/admin/verrou.js`) :
  un formulaire `data-lock="fiche:123"` (aussi `collection:…`, `ecran:accueil`,
  `ecran:rubrique-…`) signale sa présence toutes les 30 secondes à `POST /admin/api/verrou`
  (`hold`, `watch`, `take`, `release` par `navigator.sendBeacon` à la fermeture). Un
  identifiant par onglet ; un onglet muet depuis 2 minutes ne compte plus ; l'éditeur rend
  la fiche après 30 minutes sans frappe ni clic. L'éditeur d'une fiche prend le verrou dès
  l'ouverture (`Fiches::edit`). Qui ouvre ensuite voit un bandeau à son nom, en lecture seule
  (`inert` sur le contenu, boutons d'enregistrement désactivés) ; « Prendre la main » prévient
  la personne évincée au signal suivant et l'écrit au journal. Côté serveur, tout
  enregistrement (fiche, corbeille, restauration, traduction, collection, accueil,
  rubrique) est refusé par `Base::lockedJson()` / `lockMessage()` (423) si quelqu'un
  d'autre tient le verrou ; les actions groupées laissent de côté les fiches ouvertes par
  d'autres. État dans `storage/verrous.json` (écrit sous verrou de fichier).
- Textes longs : éditeur WYSIWYG natif (`wysiwyg.js`) ; le HTML est nettoyé côté serveur
  par liste blanche (`App\Admin\Html`).
- Chaque enregistrement crée une version (`storage/versions/{id}/`) ; le premier
  enregistrement d'une fiche reprise archive d'abord son état d'origine. Enregistrer sans
  rien changer ne crée ni version ni modification (« Aucune modification ») :
  `FicheForm::apply()` garde la valeur d'origine de tout champ que la personne n'a pas
  touché, même quand le masque ne sait pas la représenter (HTML repris de l'ancien site,
  date à la seconde, date partielle non reconnue comme « juin 1978 ? », score « a.p » ou
  « (4-5 tab) », lieu de décès détaillé, date en toutes lettres et saison d'un match, ligne
  de jeu hors liste, vidéo « iframe »), et `settle()` laisse un champ vide sous sa forme
  d'origine (`null`, `""` ou liste vide) sans ajouter de champ vide. « Déjà publiée »
  (`published_once`) n'est retenu qu'au changement de statut. Vérification :
  `tests/fiche-form.php`.
- 100 moments du centenaire : un moment « publié » ou « planifié » dont la semaine n'est
  pas arrivée reste planifié pour le jour de sa case à 8 h (`Fiches::scheduleMoment`), à
  l'enregistrement, au réordonnancement du calendrier et à chaque passage de la tâche
  « publication » (changement de la date de départ).
- Réglages (`config/settings.php` pour la liste, `storage/settings.json` pour les
  valeurs) ; les secrets (clés API, mots de passe) sont chiffrés avec sodium
  (`storage/secret.key`) et ne sont jamais renvoyés au navigateur.

## 9. Tâches planifiées

Une ligne de cron toutes les 5 minutes (`php bin/console.php cron`) ; chaque tâche a sa
fréquence, l'état est dans `storage/cron.json`, un verrou empêche deux passages simultanés
(lancée depuis le back-office pendant un passage, une tâche le signale au lieu de dire
« rien à faire » ; une sauvegarde ratée est notée en échec) :
publication des fiches programmées, statistiques (et les 100 chiffres du FCSM), audience, traductions, correcteur
d'orthographe, newsletter,
géolocalisation (toutes les 10 min), médiathèque, vignettes des vidéos (toutes les heures),
assistant IA (toutes les heures), dons (toutes les heures), plan du site (chaque jour),
sauvegarde, reçus annuels, purges RGPD.

## 10. `storage/` (hors dépôt)

| Dossier ou fichier | Contenu | Sauvegardé |
|---|---|---|
| `media/originals/` | photos originales | le dimanche si l'option est cochée |
| `users.json`, `settings.json`, `secret.key` | comptes, réglages, clé de chiffrement | oui |
| `versions/` | historique des fiches et des collections | oui |
| `inbox/`, `newsletter/`, `dons/`, `votes/`, `counters.json`, `activity/` | messages et contributions (dont `inbox/temoignages.json`, témoignages publiés), abonnés, dons, votes, compteurs, journal d'activité | oui |
| `ai/` | index et journal de l'assistant | non (reconstructible, journal purgé) |
| `correcteur/` | résultats de la vérification de fond, corrections ignorées | non (recalculé) |
| `ia/` | dépense d'IA : détail des appels, cumuls, remboursements, barème (§ 7 quater) | oui |
| `audio/` | fiches audio : texte lu et voix IA de chaque fiche, traitements groupés (§ 7 quinquies) ; `audio/pages/` : récits des pages de synthèse rédigés par l'IA ; `audio/jobs/` : fichiers d'échange temporaires | oui (sauf `jobs/`) ; les voix IA (`public/media/audio/`) avec les photos, le dimanche |
| `retro/` | Rétro-Direct : spectateurs connectés, pic et réactions de chaque direct, « J'y étais ! » par match (§ 7 sexies) | oui |
| `verrous.json` | fiches et écrans ouverts en ce moment (verrou de modification) | non (temporaire) |
| `controle.json` | dernier contrôle complet (bouton « Contrôler maintenant ») : clés des alertes, nouvelles, historique | non (le contrôle suivant le refait ; sans lui, comparaison avec la référence livrée) |
| `cache/`, `sessions/`, `ratelimit/`, `logs/`, `backups/`, `import/` | fichiers techniques (dont `cache/fil-jaune.json`, records du Fil jaune, et `cache/chiffres-*.json`, les 100 chiffres) | non |

## 11. Sécurité

- Seul `public/` est exposé ; `public/.htaccess` interdit l'affichage des dossiers et
  ajoute les en-têtes de sécurité aux fichiers statiques.
- Politique CSP sur toutes les pages HTML ; jeton CSRF sur tous les formulaires.
- Mots de passe hachés en Argon2id ; cookie de session `HttpOnly`, `SameSite=Lax`,
  `Secure` en HTTPS, identifiant de session imposé refusé (`use_strict_mode`), nouveau
  jeton CSRF à la connexion ; limitation des tentatives (connexion : 8 par compte et par
  adresse et 30 par adresse en 15 minutes, 30 par compte en une heure, débloqué par « Mot
  de passe oublié » ; une adresse IPv6 compte pour son bloc /64 ; formulaires, recherche,
  assistant, dons : quotas par adresse) ; même durée de réponse que le compte existe ou non
  (empreinte factice, e-mail de réinitialisation envoyé après la réponse).
- Liens d'invitation et de réinitialisation construits avec l'adresse du site réglée, jamais
  avec l'en-tête `Host` (sans adresse réglée, le lien n'est pas envoyé). Un compte désactivé
  ne peut pas être réinvité. Back-office ouvert sur une autre adresse que celle réglée
  (sous-domaine d'essai) : le tableau de bord et l'écran Utilisateurs le signalent aux
  administrateurs (`Base::addressMismatch()`, « www. » ignoré).
- Page d'attente et mot de passe d'avant-lancement : l'API publique est fermée aussi (sauf
  les webhooks de paiement et le consentement aux cookies).
- Adresses : caractères de contrôle retirés du chemin et des redirections (pas de
  redirection vers un autre site par `/%09/…`) ; chemins de la médiathèque contrôlés
  segment par segment (`Media::safeRel`). Une redirection avec « .. » ou un caractère
  invisible est refusée ; la vérification des redirections (Qualité) ne lit que des fichiers
  de `public/` (chemin réel contrôlé) et de la médiathèque : elle ne révèle pas si un fichier
  existe ailleurs sur le serveur. Scripts de copie des photos (`scripts/wp/`) : nom de fichier
  vérifié (`safe_media_rel()` : jamais hors du dossier, extensions de média seulement).
- Rôles : les règles réservées à l'administrateur sont appliquées côté serveur (écrans,
  API, envois de formulaires), pas seulement masquées dans le menu ; les montants des coûts
  de l'IA (bulles d'aide, éditeur, correcteur, aide en ligne, guide PDF) ne sont montrés qu'aux
  administrateurs (`Tips::withoutCosts()`, parties `admin` de l'aide).
- HTML : dictionnaire anglais nettoyé à l'enregistrement ; `safe_html()` retire aussi les
  attributs d'événement collés (`<a/onclick=…>`) et les balises `base`, `frame`, `svg`…
- Newsletter : confirmation et désinscription par un bouton (les messageries ouvrent seules
  les liens des e-mails) ; envoi sous verrou, file enregistrée après chaque e-mail.
- Exports CSV : cellules commençant par `= + - @` préfixées d'une apostrophe (`csv_safe`).
- Contributions : 3 Go de fichiers en attente au plus ; fichiers servis avec leur type
  vérifié et `nosniff`.
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
- `php tests/fiche-form.php` : masque de saisie (enregistrement sans modification qui ne
  change rien, valeurs reprises gardées, vraies modifications appliquées, moments planifiés).
- `php tests/correcteur.php` : correcteur d'orthographe (texte des champs, règles du musée,
  contrôle des propositions de Gemini simulé, mots protégés, cache, découpage).
- `php tests/couts.php` : coûts de l'IA (barème daté, calcul, cumuls, niveau gratuit,
  budget et pause, remboursements, relevé PDF, détail CSV).
- `php tests/verrou.php` : verrou de modification (prise, observation, prise de main,
  onglets multiples, libération, expiration, inactivité).
- `php tests/audio.php` : fiches audio (résumés automatiques, texte retenu, voix enregistrée,
  rangement des résultats d'un traitement groupé, coût à moitié prix, barème des voix) et pages
  de synthèse racontées (face-à-face en français et en anglais, saison, coupe, Bonal, records,
  chiffres, texte préparé pour la voix, audio désactivé ; récits de l'IA : même empreinte en
  français, en anglais et au traitement groupé, récit dépassé remplacé par l'automatique, pages
  à rédiger, demande envoyée, rangement des résultats, coût compté ; voix des pages : demande sur
  le récit rangé, rangement et lecture sur la page, ancienne voix plus jouée quand le récit
  change, voix commandées après les récits, voix des pages désactivées ; fin de voix nettoyée,
  encodeur MP3 : trames, silence, 22,05 kHz, décodage par ffmpeg s'il est là ; conversion des
  voix WAV, morceaux de voix recollés, résumés gardés d'un affichage à l'autre).
- `php tests/favoris.php` : favoris du back-office (adresses du back-office seulement, choix
  selon le rôle, favoris d'un compte filtrés et noms nettoyés).
- `php tests/retro.php` : Rétro-Direct (chronologie : buts et score, mi-temps, prolongation,
  tirs au but, score retourné, buteurs ; programme et états ; anniversaires ; spectateurs et
  réactions ; agenda .ics).
- `php tests/filjaune.php` : Fil jaune (réseau symétrique, joueurs retrouvés, chaîne la plus
  courte, liens, familles et records, défi du jour).
- `php tests/souvenirs.php` : kit souvenirs (match du mois et choix des historiens, visages,
  quiz, PDF de 4 pages), « Ils y étaient » (témoignages publiés seulement), QR code.
- `php tests/chiffres.php` : les chiffres du FCSM (tableaux de carrière et tableaux recopiés,
  buts minute par minute, séries par blocs de saisons, quotas, passeurs, âges, mise en forme
  française et anglaise ; puis les 100 chiffres du musée et les garde-fous contre les données
  douteuses).
- `php tests/controle.php` : contrôle complet (vérifications des matchs et des fiches,
  fichiers retouchés à la main, adresses suivies comme par un visiteur, clés stables des
  alertes, orthographe et empreinte des textes, nouvelles et corrigées d'un contrôle à
  l'autre, index remis à jour, un seul contrôle à la fois) ; le fichier du dernier contrôle
  est remis en place, rien n'est écrit dans le journal.
- `php tests/inconnu.php` : « xx » de l'ancien site (lignes de la fiche d'identité,
  naissance et décès, références de match, temps forts, réactions, chiffre clé, texte riche,
  fiche anglaise, cas limites).
- `php tests/updater.php` : mises à jour en un clic (fichiers du code seulement, données et
  médias jamais touchés, `.htaccess` gardé, libellés fusionnés, suppression des fichiers retirés,
  sauvegarde et retour arrière, archive piégée refusée, API et flux Atom, pause du site,
  synchronisation : empreintes Git, fins de ligne, image abîmée, version reconnue, dossiers en
  cache, fichier retouché signalé puis remplacé, limite de l'API).
- `php tests/attente.php` : ouverture du site (installation neuve fermée, page d'attente
  pour le public et vrai site pour l'équipe connectée, bandeau, aucun lien vers le
  back-office, noindex et cache partout, robots.txt, mot de passe d'accès, site ouvert ou
  masqué aux moteurs, teaser secret ou montré, lecture par morceaux de la vidéo).
- `php tests/pdf.php` : polices du PDF et des images de partage (lettres stylisées, emoji,
  séparateurs, « ? » seulement pour une écriture absente, texte copiable exact), symboles des
  compositions, citation posée dans une liste et numérotation.
- `tests/smoke.js` (Playwright) : parcourt les pages du site et du back-office et signale
  les erreurs JavaScript et les blocages de la politique CSP.
