# Réutiliser ce socle pour un autre site

Ce dossier contient un site vitrine + catalogue complet, en **PHP natif sans
aucune dépendance** : pas de base de données, pas de Composer, pas de npm, pas
de framework. 14 000 lignes de PHP, JS et CSS écrites à la main, 29 classes,
un back-office complet.

Il a été développé pour **Le iOiO**, coworking à Besançon. Ce guide explique ce
qui est générique (à garder tel quel), ce qui est propre au iOiO (à remplacer),
et dans quel ordre procéder pour transposer l'ensemble à un autre site.

---

## 1. Pourquoi cette architecture

Trois contraintes ont tout décidé :

1. **Aucune base de données.** Le contenu vit dans des fichiers JSON versionnés.
   Un `git clone` + un `chmod` suffisent à déployer ; une sauvegarde, c'est une
   copie de dossier ; un retour arrière, c'est un fichier restauré.
2. **Aucune dépendance.** Rien à mettre à jour, rien qui casse dans deux ans,
   rien à auditer. Le site tourne sur n'importe quel hébergement mutualisé en
   PHP 8.1+.
3. **Rien ne doit jamais tomber.** Chaque service externe (IA, avis Google,
   traduction, SMTP) a un repli écrit à l'avance. Sans clé, sans réseau, sans
   JavaScript : le site reste utilisable et vendeur.

Ce n'est pas un choix d'austérité, c'est un choix de durée de vie.

---

## 2. L'architecture en dix minutes

```
/public              ← seul dossier exposé par le serveur web
  index.php            routeur unique du site public
  /api/*.php           endpoints JSON (contact, réservation, assistant, pixel…)
  /admin/index.php     back-office, un seul point d'entrée
  /assets              CSS, JS, polices auto-hébergées
  /media               photos converties en WebP + dérivés

/app                 ← tout le code, hors racine web
  bootstrap.php        autoload maison, gestion d'erreurs, en-têtes
  *.php                29 classes, une responsabilité chacune
  /views/pages         une vue par page
  /views/partials      en-tête, pied, bandeaux, carrousel, assistant…
  /admin/views         les écrans du back-office
  /lang                fr.json, en.json — ~170 clés

/content             ← le contenu, en JSON, écrit par le back-office
  settings.json        identité, lieux, conversion, anti-spam
  offices.json         le catalogue (à renommer selon votre métier)
  /pages/*.fr.json     le texte de chaque page, champ par champ
  /_versions           30 versions conservées par fichier

/storage             ← écrit par l'application, jamais exposé
  secrets.json, app-secret.txt, logs, verrous, index de recherche

/bin                 ← cron.php (sauvegarde, index, avis, ménage)
```

**Le flux d'une page publique** : `public/index.php` → `Router` reconnaît
l'URL et la langue → charge `content/pages/<page>.<lang>.json` → `View::page()`
rend `app/views/pages/<page>.php` dans le gabarit → la vue lit le contenu via
`Content::text()` / `Content::list()`, qui ne lèvent **jamais** d'erreur sur une
clé absente.

---

## 3. Les 29 classes, une ligne chacune

| Classe | Rôle |
| --- | --- |
| `Config` | chemins, constantes, résolution des clés (env > .env > secrets.json), clé de signature |
| `Store` | **cœur du système** : lecture/écriture JSON atomique, verrous, versionnage, restauration |
| `Content` | lecture tolérante du contenu par chemin pointé, repli de langue, journal des clés manquantes |
| `Router` | URL propres multilingues, génération d'URL, détection de langue |
| `View` | rendu des pages, partiels, écrans d'admin, balise `<img>` avec srcset |
| `I18n` | traductions, formats de date et de prix selon la locale |
| `Text` | échappement, slugs, extraits, initiales, suppression d'accents |
| `Media` | import d'images, conversion WebP, dérivés 400/800/1600, srcset, texte alternatif |
| `Seo` | balises meta, Open Graph, JSON-LD, hreflang, sitemap, robots |
| `Session` | session durcie (HttpOnly, SameSite=Strict, dossier dédié) |
| `Csrf` | jeton par formulaire, comparaison à temps constant |
| `Auth` | comptes Argon2id, connexion, réinitialisation, verrouillage après échecs |
| `RateLimit` | compteurs par IP dans des fichiers verrouillés |
| `Spam` | **jeton signé, pixel de présence, leurres, note de suspicion, quarantaine** |
| `Api` | socle des endpoints JSON : réponses, validation, garde-fous |
| `Requests` | demandes entrantes (contact, réservation), statuts, quarantaine |
| `Mailer` | emails HTML + texte, SMTP authentifié ou `mail()`, quoted-printable |
| `Http` | petit client HTTP (cURL avec repli sur les flux) |
| `Log` | journaux séparés par domaine dans `storage/logs` |
| `Consent` | consentement cookies par catégorie, 13 mois |
| `Reviews` | avis Google (API Places) fusionnés avec les avis saisis |
| `Translator` | traduction assistée FR → EN, écrite en brouillon |
| `Diagnostics` | **test réel de chaque intégration** depuis le back-office |
| `Admin` | menu, écrans, **schéma d'édition des pages** |
| `Offices` | le catalogue métier — **c'est la classe à remplacer** |
| `Ai\Indexer` | index de recherche local (BM25 simplifié) sur le contenu du site |
| `Ai\Facts` | **chiffres réels du catalogue** injectés dans l'assistant |
| `Ai\Gemini` | assistant : données + RAG + Gemini, avec repli local complet |
| `Ai\Docs` | documents téléversés et indexés pour l'assistant |

---

## 4. Les six règles qui ne se négocient pas

Elles expliquent pourquoi le code est écrit comme il est. Les enfreindre, c'est
perdre ce qui fait tenir l'ensemble.

1. **Écriture atomique.** Tout JSON part dans un `.tmp`, est relu et validé,
   puis remplace l'ancien par `rename()`. Jamais de fichier à moitié écrit.
   → `Store::write()`
2. **Lecture tolérante.** Une clé absente ne lève jamais d'erreur : valeur par
   défaut, journal, signalement au tableau de bord. Un bloc inconnu est ignoré.
   → `Content::text()`, `Content::list()`
3. **Versionnage systématique.** Copie de l'ancien fichier avant chaque
   écriture, 30 versions, restauration en un clic.
4. **Repli écrit à l'avance.** Chaque service externe a son comportement sans
   clé, défini et testé. L'assistant répond sans Gemini, les avis s'affichent
   sans Places, les emails partent sans SMTP.
5. **Rien ne sort avant consentement.** Polices auto-hébergées, aucun CDN,
   aucune requête tierce tant que le visiteur n'a pas accepté — à l'exception
   assumée des plans, documentée dans le panneau.
6. **Dégradation sans JavaScript.** Les formulaires postent normalement, les
   contenus sont dans le HTML, la navigation fonctionne. Le JS ajoute du
   confort, jamais du fonctionnel indispensable.

---

## 5. Ce qui est générique — à garder tel quel

Environ **80 % du code**. Rien à y changer pour un autre métier :

- tout `Store`, `Content`, `Config`, `Session`, `Csrf`, `Auth`, `RateLimit`,
  `Spam`, `Api`, `Mailer`, `Http`, `Log`, `Text`, `Media`, `Seo`, `I18n`,
  `Consent`, `Diagnostics`, `Translator`, `View`, `Router` ;
- le back-office entier : connexion, installation, édition de pages par schéma,
  photothèque, demandes, réglages, clés API, anti-spam, comptes ;
- l'assistant : `Indexer`, `Gemini`, `Docs` — seul `Facts` est métier ;
- l'anti-spam complet ;
- le cron de maintenance ;
- la grille CSS, les composants (boutons, pastilles, cartes, carrousel,
  visionneuse, bandeau cookies), les animations.

---

## 6. Ce qui est propre au iOiO — à remplacer

| Élément | Ce qu'il contient | Ce qu'il devient |
| --- | --- | --- |
| `app/Offices.php` | le catalogue : statuts, types, lieux, prix, décoration | votre objet métier (prestations, références, produits…) |
| `app/Ai/Facts.php` | les chiffres réels injectés dans l'assistant | les chiffres de **votre** métier |
| `content/offices.json` | 21 bureaux | vos données |
| `content/pages/*.json` | les textes des 7 pages | vos textes |
| `content/settings.json` | identité, deux lieux, marquee, conversion | votre identité |
| `app/Router.php` | la table `ROUTES` | vos URL, FR et EN |
| `app/Admin.php` | la constante `PAGES` (schéma d'édition) | vos champs éditables |
| `public/assets/css/site.css` | les variables de couleur en tête de fichier | votre charte |
| `app/views/pages/*` | 9 vues | vos pages |
| `public/media/*` | 78 photos du iOiO | vos visuels |

**Le modèle de données est le point central.** `Offices` suppose :
un identifiant, un nom, un lieu, un type, un statut, un tarif normal, un tarif
promo facultatif, des photos, des points forts, une version anglaise. Si votre métier a la même forme — une
collection d'objets qu'on filtre, qu'on active/désactive et qu'on présente en
fiche — vous renommez et vous gardez toute la mécanique : filtres, compteurs,
cartes, fiche, API JSON, assistant.

---

## 7. Marche à suivre, dans l'ordre

1. **Copier le dossier**, vider `content/`, `public/media/`, `storage/`
   (en gardant les `.gitkeep` et les `index.html`).
2. **Poser la charte** : les variables CSS en tête de `site.css` (couleurs,
   polices), puis les polices dans `public/assets/fonts`. Rien d'autre à
   toucher pour changer complètement l'allure.
3. **Définir le modèle métier** : renommer `Offices` et ses constantes, adapter
   `Config::SITES`, `STATUSES`, `TYPES`.
4. **Écrire les routes** dans `Router::ROUTES`, FR et EN.
5. **Déclarer les pages éditables** dans `Admin::PAGES` — c'est ce schéma qui
   génère automatiquement les écrans d'édition du back-office.
6. **Créer les vues** dans `app/views/pages`, en réutilisant les partiels.
7. **Remplir le contenu** par le back-office, pas à la main.
8. **Adapter `Facts`** pour que l'assistant connaisse vos chiffres.
9. **Configurer les clés** dans Réglages → Clés API, et **tester chacune** avec
   le bouton prévu.
10. **Vérifier** : `/sitemap.xml`, `/robots.txt`, les deux langues, un envoi de
    formulaire réel, l'assistant sans clé puis avec.

---

## 8. Déploiement

**Recommandé** : `DocumentRoot` sur `/public`. Rien d'autre n'est accessible.

**Hébergement mutualisé** sans racine déplaçable : placez le contenu de
`/public` à la racine web et le reste au-dessus, puis ajustez les deux `require`
en tête de `public/index.php` et `public/admin/index.php`.

Exigences : PHP 8.1+, extensions `json`, `mbstring`, `gd` ou `imagick`
(photos), `curl` (recommandé), `intl` (recommandé, sinon repli manuel).
Droits d'écriture sur `content/` et `storage/` uniquement.

Au premier accès à `/admin/`, un écran d'installation crée le premier compte.

---

## 9. Les pièges rencontrés sur ce projet

Ils sont déjà corrigés ici, mais vous les recroiserez ailleurs.

- **`srcset` sans l'original** : les écrans à forte densité plafonnaient à
  800 px. Le fichier maître doit figurer dans le `srcset`.
- **`sizes` menteur** : déclarer `340px` pour un emplacement rendu à 590 px fait
  télécharger une image trop petite. Le symptôme ressemble à des photos de
  mauvaise qualité ; la cause est un attribut.
- **`auto-fit` en CSS Grid** : avec peu de résultats, les colonnes vides
  disparaissent et les cartes s'étirent — la mise en page change selon le
  filtre. Utiliser `auto-fill` ou un nombre de colonnes fixe.
- **Filtres qui se cumulent** : des compteurs calculés sur tout le catalogue
  mentent dès qu'un filtre est actif. Soit les compteurs tiennent compte du
  contexte, soit les filtres sont exclusifs. Ici : exclusifs.
- **`src` contre `srcset`** : changer `src` en JavaScript ne fait rien si
  l'image porte un `srcset`. Il faut déplacer les deux.
- **Emails sur une seule ligne** : un corps HTML de plusieurs milliers de
  caractères en `8bit` est recoupé par le serveur de mail, parfois au milieu
  d'une balise. Encoder en quoted-printable.
- **`SCRIPT_NAME` et les sous-dossiers** : ne retirer que `index.php` casse tous
  les liens fabriqués depuis `/api/chat.php`. Retirer n'importe quel `*.php`.
- **Clé Google restreinte « Sites Web »** : elle vérifie le `Referer` du
  navigateur, or le serveur n'en envoie pas. Soit on envoie un référent, soit —
  mieux — on utilise une clé distincte restreinte par adresse IP.
- **L'API Places ne renvoie que 5 avis**, quel que soit le nombre publié.
  Les compléter par des avis saisis.
- **Pastille plus haute que sa ligne** : un `line-height: 1` sur un libellé qui
  contient un badge de 26 px le fait déborder sur le champ voisin. Passer le
  libellé en flex.

---

## 10. Ce qu'il faut tester avant de livrer

- les deux langues sur toutes les pages ;
- un envoi réel de chaque formulaire, et le même envoi depuis `curl` (il doit
  être arrêté) ;
- le site **sans JavaScript** ;
- le site **sans aucune clé API** ;
- 390 px de large, sans débordement horizontal ;
- `/sitemap.xml` non vide et `/robots.txt` cohérent ;
- la restauration d'une version depuis le back-office ;
- l'écran d'installation sur une base vierge.
