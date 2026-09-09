# Le iOiO — handoff développement (PHP natif, sans base de données)

Design de référence dans ce projet :
- `Site iOiO.dc.html` — site public : accueil, nos espaces, nos bureaux, fiche bureau + réservation, l'actu, contact, mentions légales, barre CTA collante, assistant IA, pop-up de sortie, sélecteur FR/EN.
- `Back-office iOiO.dc.html` — connexion, mot de passe oublié, tableau de bord, éditeur WYSIWYG, bureaux & dispos, assistant IA.
- `assets/ioio-logo.png`, `assets/ioio-mark.png`, `assets/ioio-carnot.png`, `assets/ioio-granvelle.png` — logos fournis par le client.

**Travailler dans une nouvelle branche** : `git checkout -b feat/site-ioio` depuis `claude/svg-export-odnc-fmjj3l`. Le dépôt est vide de code site : tout est à créer.

---

## 1. Direction visuelle (à respecter au pixel près)

| Rôle | Valeur |
| --- | --- |
| Encre / fonds sombres | `#0E0E0E` |
| Jaune marque | `#FFD100` |
| Fond crème | `#FFF8EA` |
| Blanc cartes | `#FFFFFF` |
| Vert « disponible » | `#12B39A` |
| Sable « loué » | `#EDE5D5` |

- Titres : **Bricolage Grotesque** 800, `letter-spacing:-.03em`. Texte : **Manrope** 500/700/800.
- Flat design strict : aucun dégradé décoratif, aucune ombre portée sauf les 3 éléments flottants (barre CTA, panneau du bot, pop-up de sortie). Bordures noires 2px, rayons 14 à 26px.
- Motif de marque : les « O » du logo pendent au bout d'un fil (`votre bureau au bout du fil`). Repris en décor de la hero (fil 2px + anneau, `@keyframes ioioSwing`).
- Contraste : jamais de texte jaune sur crème ni sur blanc. Le jaune sert de **fond**, jamais d'encre.

### Animations à reproduire
| Effet | Implémentation attendue |
| --- | --- |
| Apparition au scroll | `data-reveal` + `data-delay`, opacité 0 → 1 et `translateY(30px)` → 0, `transition .8s cubic-bezier(.2,.75,.2,1)`. Un passage `getBoundingClientRect` sur `scroll`/`resize` (plus fiable que l'IntersectionObserver quand la page est scalée), déclenchement à 94 % de la hauteur de fenêtre. |
| Boutons — fondu + glissement gauche→droite | Une seule couche : `background-image:linear-gradient(COULEUR,COULEUR); background-size:0% 100%; background-position:0 0; background-repeat:no-repeat`, au survol `background-size:100% 100%`, plus `color` en transition. `transition:background-size .5s cubic-bezier(.22,.9,.2,1), color .35s ease`. Aucun pseudo-élément, aucun `overflow:hidden`. |
| Menus (header + footer) — trait fin gauche→droite | Même technique, `background-position:0 100%; background-size:0% 2px` → `100% 2px` (1.5px au footer, jaune). |
| Halo du bouton d'accompagnement | `span` en `position:absolute;inset:0` derrière le bouton, `@keyframes ioioHalo` (scale .96 → 1.5, opacité .6 → 0, 2.4s infinite). Porté par **« Réservez votre bureau »**. |
| Bandeau des prestations | `@keyframes ioioMarquee` translateX 0 → -50 %, liste dupliquée. |
| Parallaxe | `data-parallax="0.06"` sur les cercles de la hero, `translate3d` piloté au scroll ; `data-float` suit légèrement la souris. |
| Ouverture bot / pop-up | `@keyframes ioioPop` (18–26px + scale .97). |

Respecter `prefers-reduced-motion: reduce` : couper marquee, parallaxe, halo et swing, garder l'opacité finale.

---

## 2. Arborescence

```
/                          (hors racine web)
├─ app/
│  ├─ Config.php           constantes, chemins, langues, clés (lues depuis .env)
│  ├─ Router.php           routage par chemin propre, résolution locale
│  ├─ Content.php          lecture JSON + cache opcache/APCu, valeurs par défaut
│  ├─ Store.php            écriture atomique + flock + versionnage
│  ├─ Auth.php             login, sessions, reset de mot de passe, throttling
│  ├─ Csrf.php
│  ├─ Mailer.php           mail() ou SMTP, gabarits texte + HTML
│  ├─ Ai/Indexer.php       extraction texte pages + docs → chunks
│  ├─ Ai/Gemini.php        appel API, RAG, garde-fous
│  ├─ Reviews.php          Google Places, cache 24 h
│  ├─ I18n.php             chargement des dictionnaires, fallback FR
│  └─ views/               gabarits PHP (une vue par page + partials)
├─ content/                DONNÉES ÉDITABLES (écrites par le back-office)
│  ├─ settings.json
│  ├─ pages/{home,spaces,offices,news,contact,legal}.{fr,en}.json
│  ├─ offices.json
│  ├─ posts.json
│  ├─ reviews.cache.json
│  ├─ requests.json
│  ├─ users.json
│  └─ _versions/<fichier>/<timestamp>.json
├─ storage/
│  ├─ docs/                PDF/DOCX déposés au back-office (jamais servis en direct)
│  ├─ index/chunks.json    index de l'assistant
│  ├─ locks/
│  └─ logs/
├─ public/                 SEULE RACINE WEB
│  ├─ index.php            front controller public
│  ├─ .htaccess            rewrite, en-têtes de sécurité, blocage de /content
│  ├─ assets/{css,js,img,fonts}/
│  ├─ media/               photos publiées (dérivés WebP générés à l'upload)
│  └─ api/
│     ├─ contact.php       POST demande de contact
│     ├─ reserve.php       POST réservation d'un bureau
│     ├─ lead.php          POST email de la pop-up de sortie
│     ├─ offices.php       GET disponibilités (JSON)
│     ├─ reviews.php       GET avis Google en cache
│     └─ chat.php          POST question → réponse Gemini + sources
└─ admin/                  back-office (protégé par session ; alias /public/admin ou vhost dédié)
   ├─ index.php
   └─ assets/
```

Règle de déploiement : **DocumentRoot sur `/public`**. Si l'hébergement l'interdit, `content/`, `storage/` et `app/` reçoivent un `.htaccess` `Require all denied` + un `index.html` vide, et les noms de fichiers JSON restent non devinables depuis le front.

---

## 3. API interne (JSON, même origine)

| Endpoint | Méthode | Corps | Réponse |
| --- | --- | --- | --- |
| `/api/offices.php` | GET | `?site=carnot&status=available` | `{ "offices":[…], "available":5, "updatedAt":"…" }` |
| `/api/reviews.php` | GET | — | `{ "rating":4.9, "count":31, "reviews":[…] }` (cache 24 h, jamais d'appel Google en direct) |
| `/api/contact.php` | POST | `name,email,phone,need,message,csrf,hp,ts` | `{ "ok":true }` |
| `/api/reserve.php` | POST | `officeId,name,email,phone,startDate,csrf,hp,ts` | `{ "ok":true,"ref":"R-2609-014" }` |
| `/api/lead.php` | POST | `email,source:"exit-intent",csrf` | `{ "ok":true }` |
| `/api/chat.php` | POST | `{ "q":"…","lang":"fr","history":[…] }` | `{ "answer":"…","sources":[{"label":"Page Nos espaces","url":"/nos-espaces"}] }` |

Sécurité commune à tous les POST : jeton CSRF en session, champ honeypot `hp` vide, `ts` (formulaire soumis en moins de 2 s = rejet), limite 5 requêtes / 10 min / IP (fichier compteur dans `storage/locks`), `filter_var` + longueurs maximales, `htmlspecialchars` à l'affichage, réponses toujours en `application/json` avec `X-Content-Type-Options: nosniff`.

En-têtes servis par `.htaccess` : `Content-Security-Policy` (self + `fonts.googleapis.com`, `fonts.gstatic.com`, `translate.googleapis.com`, `maps.googleapis.com`), `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy` minimal, HSTS.

---

## 4. Contenu en JSON — schéma, verrou, non-régression

### Forme d'un fichier de page
```json
{
  "_schema": 1,
  "slug": "home",
  "lang": "fr",
  "status": "published",
  "seo": { "title": "…", "description": "…", "ogImage": "/media/hero.webp" },
  "blocks": [
    { "id": "hero", "type": "hero", "eyebrow": "4 postes disponibles ce mois",
      "title": "Un bureau qui donne envie d'y aller le lundi.",
      "text": "…", "ctaPrimary": { "label": "Voir les bureaux libres", "href": "/nos-bureaux" },
      "image": "/media/hero.webp", "stats": [ { "value": "170 m²", "label": "sur 2 adresses" } ] },
    { "id": "faq", "type": "faq", "items": [ { "q": "…", "a": "…" } ] }
  ],
  "updatedAt": "2026-09-09T14:20:11+02:00",
  "updatedBy": "fanny@ioio.fr"
}
```

`offices.json` :
```json
{ "_schema": 1, "offices": [
  { "id":"carnot-03", "site":"carnot", "name":"Carnot — Bureau 03", "type":"private",
    "area":"11 m²", "price":320, "currency":"EUR", "period":"month", "vat":"excl",
    "status":"available", "availableFrom":null, "order":3,
    "photos":["/media/carnot-03-1.webp"], "i18n":{ "en":{ "name":"Carnot — Office 03" } } } ] }
```

### Règles anti-casse (exigence explicite du client)
1. **Écriture atomique** : `Store::put()` écrit `content/x.json.tmp`, `fflush` + `fsync`, `json_decode` de contrôle, puis `rename()` (atomique sur le même volume). Jamais de `file_put_contents` direct sur le fichier servi.
2. **Verrou** : `flock(LOCK_EX)` sur un fichier jumeau dans `storage/locks/` pendant l'écriture ; verrou éditorial séparé (`storage/locks/page-home.lock`, TTL 10 min, propriétaire + expiration) affiché dans le back-office (« Verrou : vous éditez cette page »).
3. **Versionnage** : avant chaque `rename`, copie de l'ancien fichier dans `content/_versions/<nom>/<ISO8601>.json`, rétention 30 versions. Restauration = simple copie inverse.
4. **Lecture tolérante** : `Content::get('blocks.hero.title', $default)`. Un bloc ou une clé absente ne lève jamais d'erreur — valeur par défaut, log dans `storage/logs/content.log`, et signalement dans le tableau de bord. Un `type` de bloc inconnu est **ignoré** au rendu.
5. **Migrations de schéma** : `_schema` incrémenté + `app/migrations/00X_*.php` idempotents joués au déploiement. Le front sait rendre `_schema` N et N-1.
6. **Sauvegarde** : dump quotidien de `content/` + `storage/docs` en `.tar.gz` daté, 14 jours de rétention.

### Comptes
`content/users.json` : `{ "users":[ { "email":"…", "hash":"$argon2id$…", "role":"admin", "createdAt":"…", "resetHash":null, "resetExpires":null, "failedAttempts":0, "lockedUntil":null } ] }`
Réinitialisation : jeton aléatoire 32 octets, **haché** en base, valable 30 min, à usage unique, réponse identique que le compte existe ou non. Session : cookie `HttpOnly` + `Secure` + `SameSite=Strict`, régénération d'ID à la connexion, expiration 8 h (7 jours si « rester connecté », jeton distinct).

---

## 5. Assistant IA (Gemini, côté front)

Chaîne : `/api/chat.php` → recherche locale dans `storage/index/chunks.json` → prompt Gemini → réponse + sources.

1. **Indexation** (à la publication d'une page, à l'upload d'un document, et via cron nocturne) : texte des pages publiées (FR + EN) et des documents (`pdftotext`, `docx` = XML dézippé, `txt`) découpés en extraits de ~800 caractères avec 100 de recouvrement. Chaque extrait : `{ id, source:{label,url|doc}, lang, text }`.
2. **Récupération** : score BM25-léger en PHP (tokenisation, stopwords FR/EN, bonus titres) → 6 meilleurs extraits. Aucun service vectoriel externe requis ; si un embedding est souhaité plus tard, `text-embedding-004` avec cache dans `storage/index/vectors.json`.
3. **Appel** : modèle `gemini-2.5-flash`, `temperature 0.2`, `maxOutputTokens 400`, clé **serveur uniquement** (`.env`, jamais dans le JS). Timeout 8 s, une seule tentative, dégradation propre : message « je transmets à l'équipe » + lien contact.
4. **Prompt système** : « Tu es l'assistant du iOiO, coworking à Besançon. Réponds en 3 phrases maximum, dans la langue du visiteur, uniquement à partir des extraits fournis. Cite les sources. Si l'information manque, dis-le et propose le formulaire de contact ou le bouton Réservez votre bureau. N'invente jamais un tarif ni une disponibilité. »
5. **Garde-fous** : 20 requêtes/h/IP, question ≤ 500 caractères, journal des questions sans correspondance (`storage/logs/ai-misses.log`) affiché dans le back-office, refus de tout contenu hors sujet, aucune donnée personnelle envoyée à Google.
6. **UI** : lanceur en bas à droite (`bottom:22px; right:20px`, jamais plus haut, pastille de marque `ioio-mark.png` en flat), panneau 378px, trois suggestions cliquables, puces de sources sous chaque réponse, mention « Propulsé par Gemini » en pied de panneau.

---

## 6. Multilangue (FR référence, EN livré, extensible)

- URLs préfixées : `/` et `/en/…`. `Router` détecte la locale au premier segment, sinon cookie `ioio_lang`, sinon `Accept-Language`, sinon FR.
- `<html lang="fr">` + `hreflang` réciproques sur chaque page (`fr`, `en`, `x-default` → FR) + `og:locale` / `og:locale:alternate`, un `sitemap.xml` par locale.
- Trois niveaux de traduction :
  1. **Interface** : `app/lang/{fr,en}.json` (nav, boutons, formulaires, barre CTA, pop-up, bot). Clé manquante → chaîne FR + log.
  2. **Contenu éditorial** : un fichier JSON par page et par langue. Le back-office propose « Traduire en anglais » : appel serveur à Google Cloud Translation, résultat **écrit en brouillon** et relisible avant publication (pas de traduction à la volée côté visiteur).
  3. **Contenus longs non traduits** : bandeau honnête « Contenus longs traduits automatiquement » + widget Google Traduction chargé uniquement sur ces pages.
- Champs traduisibles côté données : `i18n.<lang>.<champ>`, fallback FR automatique. Ajouter une langue = déposer `app/lang/xx.json` + les fichiers `pages/*.xx.json` ; aucune modification de code.
- Prix et dates formatés par `Intl` (`NumberFormatter`, `IntlDateFormatter`) selon la locale.

---

## 7. Conversion — à ne pas perdre en route

- **Barre CTA collante** sur toutes les pages : `position:fixed; bottom:0; left:0; right:0`, contenu centré, pilule noire, « Contactez-nous » (contour) + « Réservez votre bureau » (jaune, halo). `body { padding-bottom:132px }` pour ne jamais masquer le pied de page. En mobile, largeur pleine avec `gap:8px` et libellés courts (« Contact » / « Réserver »).
- **Pop-up de sortie** : `mouseout` avec `clientY <= 6` et `!relatedTarget`, une seule fois par session (`sessionStorage`), plus une variante mobile déclenchée au retour arrière (`popstate`) ou après 45 s d'inactivité. Formulaire email → `/api/lead.php`.
- **Avis Google** : Places API côté serveur, cache 24 h, note moyenne + 4 avis les plus récents, aucune modification du texte, lien vers la fiche Google.
- **Preuves** : « 4 postes disponibles ce mois » et « places restantes » calculés depuis `offices.json`, jamais codés en dur.
- **Mesure** : événements `cta_sticky_contact`, `cta_sticky_reserve`, `exit_popup_submit`, `chat_open`, `chat_question`, `reserve_submit` (Plausible ou Matomo auto-hébergé, sans cookie).
- Photos : les emplacements du design (`image-slot`) attendent les vraies photos des espaces ; prévoir un upload back-office avec génération WebP 1600/800/400 et `loading="lazy"` + `width`/`height` pour éviter tout décalage.

---

## 8. Ordre de réalisation conseillé

1. Squelette `app/` + `public/index.php` + routeur + `Content`/`Store` (avec tests d'écriture concurrente).
2. Front public statique alimenté par les JSON, animations et barre CTA — puis validation visuelle contre `Site iOiO.dc.html`.
3. Auth + back-office pages/bureaux (le cœur métier du client).
4. Formulaires + emails + pop-up de sortie.
5. Indexation + `chat.php` + panneau du bot.
6. EN, hreflang, sitemaps, avis Google, mesure, sauvegardes.
