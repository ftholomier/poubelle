# Passation · prototype « Visite Immo » pour le réseau Synapse

> **À lire en premier par l'équipe (ou l'IA) qui fera le vrai développement.**
> Ce dépôt contient un **prototype d'exploration** : il a servi à tester des idées vite, avant de les développer
> proprement dans le logiciel métier Synapse. Ce document résume ce qui a été construit, ce qui a été appris,
> les pièges rencontrés et ce qu'il faudra refaire autrement en production.
>
> Feuille de route et état d'avancement : [`PLAN-DEVELOPPEMENT.md`](PLAN-DEVELOPPEMENT.md).
>
> Dernière mise à jour : 7 octobre 2026 · branche `claude/nice-cori-uknqlh` du dépôt `ftholomier/poubelle`.

---

## 1. Récupérer tout le travail (export zip)

Tout est versionné dans Git :

- **GitHub** : ouvrir `https://github.com/ftholomier/poubelle/tree/claude/nice-cori-uknqlh`, bouton **Code → Download ZIP**.
- **En ligne de commande** :
  ```sh
  git clone -b claude/nice-cori-uknqlh https://github.com/ftholomier/poubelle.git
  cd poubelle && git archive --format=zip -o visite-immo.zip HEAD visite-immo synapse-offre PASSATION.md PLAN-DEVELOPPEMENT.md
  ```
- L'historique des commits (`git log`) raconte l'ordre des décisions, en français, une phase par commit.

| Dossier / fichier | Quoi |
|---|---|
| `visite-immo/` | Le prototype : PWA mobile + API en PHP natif. Son `README.md` décrit l'installation. |
| `visite-immo/tests/` | Tests de bout en bout (Playwright) et services simulés. |
| `synapse-offre/` | L'infographie A4 « L'offre irrésistible » (PDF + générateur). |
| `PASSATION.md`, `PLAN-DEVELOPPEMENT.md` | Ce document et la feuille de route. |

---

## 2. Le principe : tout part de la visite

**Objectif : l'agent fait son métier (visiter, rentrer des mandats, conseiller, vendre), le logiciel fait le reste.**
L'agent enregistre la visite ; en sortant, tout est prêt automatiquement ; ensuite il n'a **qu'une action à la fois**,
affichée en grand (« Prochaine étape »). Interface volontairement ultra simple : 5 entrées en bas d'écran
(Aujourd'hui · Biens · ● Visite · Acquéreurs · Agenda), le reste dans le menu.

Parcours complet, de la prospection à l'acte (chaque étape est construite et testée) :

| Étape | Ce qui se fait tout seul | Ce que fait l'agent |
|---|---|---|
| **Prospection** | Logements classés F/G du secteur (ADEME), rues où les ventes bougent (DVF), courriers « Au propriétaire » personnalisés, flyers de boîtage ; **« Estimer un bien » en temps réel** (adresse + caractéristiques → prix qui bouge à chaque réglage) | Choisir sa commune, imprimer les courriers, suivre les réponses |
| **Visite** | Enregistrement par morceaux, transcription au fil de l'eau, **captation en direct** (sujets déjà abordés, « pensez à demander… »), photos du bien et documents du vendeur pris sans arrêter l'audio | Appuyer sur le bouton rouge |
| **Sortie de visite** | Fiche (61 champs), annonce, rapport interne, compte rendu vendeur, publications réseaux, croquis de plan, **dossier technique** (cadastre IGN, Géorisques, DPE ADEME), **avis de valeur** (ventes DVF similaires, recalculé en direct), **liste des pièces** à demander, mandat pré-rempli, **tableau de suivi** (chaque case : fait / à faire / en attente / pas commencé / prévu) | « Compléter à la voix » (l'assistant ne pose que les questions manquantes), puis **« Tout envoyer au vendeur »** |
| **Mandat** | Numéro au registre, signature au choix : intégrée (lien, code par e-mail, relances J+2 / J+5) ou **firma.dev / BoldSign** (champs placés dans nos cadres, retour par webhook signé), exemplaire signé à chacun | Signer (au doigt), ou envoyer pour signature |
| **Pièces du vendeur** | Le vendeur dépose en photo dans son espace, **l'IA lit chaque document** (titre, taxe foncière, PV d'AG, diagnostics…), remplit la fiche, signale les points de vigilance ; relances J+2, J+5, J+10… | Rien |
| **Commercialisation** | **Rendu Leboncoin fidèle** (téléphone / ordinateur, vraies photos, contrôle de conformité, texte prêt à coller), page publique du bien, **assistant acquéreurs 24 h/24** (répond, qualifie, réserve la visite dans l'agenda), flux portails, visuels carré et story, vidéo courte, alertes aux acquéreurs compatibles, statistiques | Publier (un bouton) |
| **Visites acquéreurs** | Créneaux libres proposés, abonnement calendrier, bon de visite signé, **point de la semaine au vendeur chaque vendredi** | Dicter le retour en 30 secondes |
| **Offre** | Offre dictée → PDF, signature de l'acquéreur, transmission au vendeur, acceptation signée / refus / contre-proposition depuis son espace | Dicter l'offre |
| **Compromis → acte** | Échéancier (rétractation SRU de 10 jours avec jours fériés, prêt, acte), relances acquéreur / courtier / notaires, **contrôle anti-blanchiment** (pièce d'identité lue par l'IA, registre des gels), dossier et espace notaire | Saisir les dates, photographier les pièces d'identité |
| **Acte** | Facture numérotée, note de commission (paliers 80 / 90 / 95 %), retrait de l'annonce, demande d'avis Google suivie (relance si pas de clic) | Appuyer sur « Acte signé » |
| **Au quotidien** | Écran Aujourd'hui (tout ce qui est à faire, tous modules confondus), briefing du matin à écouter, notifications sur le téléphone, tableau de bord (palier, temps gagné) | Commande vocale (« relance la vendeuse du Chênois »), bilan d'appel dicté |
| **Réseau** | Coaching des 90 premiers jours (étapes cochées d'après l'activité réelle), suivi formation loi ALUR, vue tête de réseau | Question au juriste, transmettre un contact à un collègue |

---

## 3. Architecture

Choix volontairement simples pour tester vite : **PHP 8.2+ natif sans framework ni Composer, fichiers JSON, pas de
base de données**, interface en HTML/CSS/JS sans framework (modules ES). Environ 16 000 lignes.

### 3.1 Un module = une fonction métier

Tout le métier est dans `app/modules/*.php`. Chaque module **s'enregistre** : ajouter une fonction = ajouter un module,
sans toucher au reste.

| Point d'extension (dans `app/store.php` et les modules socles) | Rôle |
|---|---|
| `route('POST signer', fn ($id) => …)` | route d'API `/api/?r=signer` |
| `tache_cron('relances_pieces', fn ($agent, $dossiers) => …)` | tâche automatique lancée par `app/cron.php` |
| `a_faire('pieces', fn ($agent, $dossiers) => […])` | éléments de l'écran « Aujourd'hui » |
| `pdf_module('avis', 'Avis de valeur', 'rendre_avis_valeur')` | document PDF à la charte |
| `document_module(fn ($v) => […])` | document listé dans l'onglet « Documents » du dossier |
| `document_signable('offre', …, pdf:, signataires:, apres:)` | document signable (et ce qui se passe une fois signé) |
| `type_dictee('appel', $schema, $consigne, $demo)` + `apres_dictee()` | dictée de 30 s transformée en données structurées |
| `espace_section('vendeur', 20, fn ($ctx) => '<section>…')` | bloc de l'espace client (vendeur, acquéreur, notaire) |
| `apres_champs(fn ($agent, $id, $cles) => …)` | réagir à un changement de la fiche (saisie, dialogue, pièce lue) : ex. estimation recalculée |

Modules socles (chargés en premier) : `dossier` (étapes, prochaine action, documents), `signature`, `espace`, `dictee`.

| Module | Contenu |
|---|---|
| `donnees_publiques` | BAN (géocodage), cadastre IGN, Géorisques, DPE ADEME, DVF ; simulation si un service ne répond pas |
| `estimation` | moteur d'estimation : ressemblance de chaque vente DVF, tendance du marché, ajustements, confiance ; outil « Estimer un bien » ; recalcul en direct du dossier |
| `avis_valeur` | avis de valeur du dossier (chiffres du moteur `estimation`), argumentaire IA, PDF |
| `suivi` | tableau de suivi du projet (cases et pourcentage « opérationnel »), coach de captation, export CRM (JSON) |
| `plan` | croquis de plan (disposition « squarified » des pièces citées) en SVG et PDF |
| `pieces` | pièces requises selon le bien, dépôt, lecture IA, alertes, relances |
| `sortie_visite` | `preparer` (après génération) et « Tout envoyer au vendeur » |
| `signature` | signature interne (au doigt + code e-mail), certificat de preuve ; choix du service |
| `signature_fournisseurs` | firma.dev, BoldSign (repris du projet Qualiopi), API générique : envoi, webhooks, synchronisation |
| `espace` | espace client par lien personnel (`public/espace/`) |
| `photos`, `visuels` | retouche (niveaux, netteté), détection du flou, pièce reconnue, home staging, visuels réseaux sociaux |
| `commercialisation`, `vitrine` | publication, page publique (`public/v/`), assistant 24 h/24, contacts, flux portails |
| `acquereurs`, `agenda`, `visites_acquereurs` | fiches, rapprochement, alertes, agenda et ICS, bon de visite, retours, point du vendredi |
| `offres`, `compromis`, `lcbft`, `facturation` | offre → acceptation, échéancier et relances, anti-blanchiment, facture, commission, avis Google |
| `aujourdhui`, `quotidien`, `push` | écran Aujourd'hui, tâches, bilan d'appel, commande vocale, briefing, tableau de bord, notifications Web Push |
| `prospection`, `reseau`, `rgpd`, `demo_jeu` | prospection, réseau, données personnelles, jeu de démonstration |
| `suppressions` | supprimer une tâche, un document du vendeur, un secteur de prospection, un contact reçu, une visite d'acquéreur (le bien entier : bouton en bas du résumé) |
| `journal_acces` | journal des accès (RGPD) : qui a consulté quel dossier, document ou pièce d'identité ; écran admin, CSV, purge |

### 3.2 Fichiers

```
visite-immo/
├── app/
│   ├── bootstrap.php        config, session, stockage JSON (verrous), chargement des modules, api_base()
│   ├── store.php            dossiers, collections par agent, liens clients, HTTP, dates, points d'extension, réglages
│   ├── cron.php             tâches automatiques (à lancer toutes les 10 min)
│   ├── fields.php           LA définition des champs du dossier (formulaire, prompts, complétude, PDF)
│   ├── ai.php, demo.php     Gemini (transcription, génération, Live, coûts) ; données de démonstration
│   ├── pdf.php, mandat.php  PDF à la charte (tFPDF), mandat et registre
│   ├── mailer.php           client SMTP natif, MIME, e-mail HTML
│   ├── modules/             toutes les fonctions métier (§3.1)
│   └── lib/tfpdf/           tFPDF 1.33 (LGPL, patché) + polices
├── data/                    stockage (hors web)
├── public/
│   ├── index.php            l'appli (versionne CSS/JS, importmap automatique de js/ et js/vues/)
│   ├── api/index.php        API JSON (/api/?r=…) ; api/signature.php : retour du service de signature
│   ├── espace/              espace client (vendeur, acquéreur, notaire), sans mot de passe
│   ├── v/                   page publique d'un bien + assistant
│   ├── ics.php, flux.php, avis.php   abonnement calendrier, flux portails, lien d'avis suivi
│   ├── js/                  app.js (routeur, écrans historiques), ui.js (outils partagés), dictee.js, pad.js,
│   │                        video.js, recorder.js, uploader.js, dialogue.js, apercu.js (rendu Leboncoin), espace.js, vitrine.js
│   ├── js/vues/             un fichier par grand écran (aujourdhui, dossier-auto, vente, acquereurs, agenda,
│   │                        estimation, suivi…)
│   └── css/                 app.css, apercu.css (page simulée du portail), espace.css, vitrine.css
└── tests/                   lancer.sh, tout.sh, services-simules.php, smtp-simule.py, e2e-*.mjs
```

### 3.3 Données (`data/`)

| Fichier | Contenu |
|---|---|
| `users.json` | comptes (hash), coordonnées, disponibilités, abonnements push, jeton ICS, notes du coach |
| `visites/<agent>/<dossier>/visite.json` | **le dossier du bien** (voir ci-dessous) + `audio/`, `photos/`, `pieces/`, `signatures/`, `lcbft/` |
| `agents/<agent>/acquereurs.json`, `agenda.json`, `taches.json`, `prospection.json`, `formations.json`, `questions.json` | collections de l'agent |
| `liens.json` | liens clients (jeton → agent, dossier, rôle, expiration) |
| `vitrines.json` | slug de page publique → dossier |
| `registre.json`, `factures.json` | registre des mandats, registre des factures (numéros chronologiques) |
| `conversations/` | échanges de l'assistant de la page du bien |
| `couts/AAAA-MM.json`, `cron.json`, `cache/` | coûts IA, dernier passage du cron, caches (DVF : CSV 30 jours + ventes lues 1 jour, registre des gels) |
| `acces/AAAA-MM.jsonl` | journal des accès (une ligne JSON par accès, ajout seul, effacé après N mois) |
| `cache/voisines/<insee>.json` | communes voisines d'une commune (estimation élargie) |
| `signatures_externes.json`, `logs/signature.log` | demande firma.dev / BoldSign → dossier ; échanges avec le service (sans secret) |

Le dossier (`visite.json`) s'enrichit au fil de la vie du bien :

```jsonc
{
  "id": "…", "agent": "u…", "titre": "…", "cree_le": "…", "consentement_le": "…",
  "morceaux": [ … ], "fiche": { "champs": { "dpe": { "valeur": "C", "citation": "…", "source": "public" } } },
  "titre_annonce": "…", "annonce": "…", "rapport_agent": "…", "rapport_vendeur": "…",
  "points_forts": [], "plan": { "pieces": [] }, "posts": { "instagram": "…" },
  "public": { "geo": {}, "cadastre": {}, "risques": {}, "dpe": {}, "ventes": [], "simulation": false },
  "avis_valeur": { "bas": 0, "haut": 0, "retenu": 0, "confiance": { "niveau": "élevée" }, "tendance": {}, "comparables": [{ "similarite": 56 }], "argumentaire": "…", "argumentaire_perime": false },
  "pieces": [{ "cle": "titre", "statut": "recue", "fichiers": [{ "resume": "…", "alertes": [] }] }],
  "envoi_vendeur": { "date": "…" }, "relances_pieces": [],
  "mandat": { "numero": "2026-0001", "signe_le": "…" },
  "signatures": { "mandat": { "statut": "signe", "mode": "interne|firma|boldsign|api", "api_id": "…", "hash": "sha256…", "signataires": [{ "nom": "…", "signe_le": "…", "methode": "…", "ip": "…" }] } },
  "espace_vu": { "vendeur": "…" },
  "photos": [{ "fichier": "…", "piece": "Séjour", "floue": false, "staging": { … } }],
  "vitrine": { "slug": "…", "publiee": true, "vues": { "2026-10-07": 12 } }, "contacts": [],
  "visites_acq": [{ "acquereur": "a…", "date": "…", "bon_signe": true, "retour": { "interet": 4, "resume_vendeur": "…" } }],
  "cr_hebdo": [], "offres": [{ "montant": 0, "statut": "acceptee" }],
  "vente": { "prix": 0, "compromis_le": "…", "notification_sru": "…", "pret_limite": "…", "acte_prevu": "…", "acte_le": "…", "facture": {}, "commission": {} },
  "lcbft": { "vendeur": { "niveau": "simplifiée", "gels": {} } }, "avis": {}, "journal": [{ "date": "…", "type": "relance", "texte": "…" }]
}
```

- **`source` d'un champ** : `ia` (extrait de la visite), `agent` (saisi), `dialogue` (dicté), `document` (lu dans une
  pièce du vendeur), `public` (donnée publique). **L'IA n'écrase jamais** `agent`, `dialogue`, `document`, `public`.
- **Étape du dossier** (calculée, jamais stockée) : visite → préparation → signature → en vente → offre → compromis → vendu.
- Le **journal** du dossier trace chaque action automatique : c'est aussi la base du « temps gagné ».

### 3.4 Pages et liens publics

| Adresse | Pour qui | Sécurité |
|---|---|---|
| `/espace/?t=<jeton>` | vendeur, acquéreur, notaire | jeton aléatoire 32 caractères, rôle, expiration 90 jours ; écritures avec en-tête `X-Requested-With: espace` |
| `/v/?b=<slug>` | public (page du bien) | uniquement les champs non internes ; 40 messages par heure et par IP pour l'assistant |
| `/ics.php?t=<jeton>` | agenda de l'agent | jeton personnel |
| `/flux.php?t=<jeton>` | portails, multidiffuseur | jeton de l'agence |
| `/avis.php?t=<jeton>` | client après l'acte | jeton, redirection vers la fiche Google |
| `/api/signature.php` | firma.dev, BoldSign, service générique | firma : `X-Firma-Signature` (HMAC-SHA256 de « t.corps », 1 h max) ; BoldSign : `X-BoldSign-Signature` ; générique : `Authorization: Bearer <clé>` |

### 3.5 Sécurité (revue du 7 octobre 2026)

- **En-têtes sur toutes les réponses** : `nosniff`, `Referrer-Policy: same-origin` (les liens personnels ne fuient
  pas vers les cartes ou Google), `X-Frame-Options: SAMEORIGIN` (pas de clickjacking sur la signature),
  `Permissions-Policy` (micro et caméra pour le site seul), HSTS en HTTPS (détecté aussi derrière un proxy).
- **Politique de contenu (CSP)** stricte sur l'appli, l'espace client et la page du bien : seuls nos fichiers JS
  s'exécutent (plus Leaflet sur cdnjs), les deux petits scripts en ligne sont autorisés par leur empreinte SHA-256.
  Une donnée piégée (texte lu par l'IA dans un document du vendeur, message du public) ne peut lancer aucun code.
- **Échappement** : toute donnée affichée passe par `esc()` (JS) ou `e()` (PHP) ; audit complet fait, derniers
  oublis corrigés (identifiants d'acquéreur, de rendez-vous, de tâche, rendu Leboncoin, historique des envois).
- **Connexion** : 5 échecs en 15 min bloquent l'identifiant, 20 l'adresse IP ; échecs notés au journal des accès ;
  session `HttpOnly`, `SameSite=Strict`, `Secure` en HTTPS, mode strict (pas de fixation de session).
- **Liens clients** : un document signable n'est visible que par un rôle qui le signe (l'acquéreur ne voit pas le
  mandat), un lien de signature ne vaut que pour son document et son signataire, l'agent du lien est vérifié.
- **Entrées** : identifiants imposés par la requête refusés (acquéreur, rendez-vous, références de tâche),
  documents envoyés au vendeur limités aux documents connus, suppression de photo par nom exact uniquement,
  recherche de communes réservée aux agents connectés.
- **Reste à faire en production** : chiffrement des pièces d'identité au repos, sauvegardes chiffrées,
  authentification à deux facteurs pour les administrateurs, revue par un tiers avant ouverture au réseau.

---

## 4. Services extérieurs

Toutes les adresses sont surchargeables (variable d'environnement `VI_API_<NOM>` ou réglage `api_<nom>`), ce qui permet
les tests et un changement d'URL sans toucher au code (`api_base()` dans `bootstrap.php`).

| Service | Adresse par défaut | Usage | Si indisponible |
|---|---|---|---|
| Gemini | `generativelanguage.googleapis.com` | transcription, génération, lecture de documents et photos, home staging, Live | mode démo |
| Adresse (BAN, IGN) | `data.geopf.fr/geocodage/search` | géocodage, communes | données simulées signalées |
| Cadastre | `apicarto.ign.fr/api/cadastre/parcelle` | parcelle, contenance | simulées |
| Géorisques | `georisques.gouv.fr/api/v1` (`gaspar/risques`, `zonage_sismique`, `radon`) | risques | simulés |
| DPE ADEME | `data.ademe.fr/data-fair/api/v1/datasets/dpe03existant/lines` | DPE du logement, logements F/G d'une commune | « non trouvé » |
| DVF | `files.data.gouv.fr/geo-dvf/latest/csv/<année>/communes/<dép>/<insee>.csv` | ventes (cache 30 jours) | simulées |
| Gel des avoirs | `gels-avoirs.dgtresor.gouv.fr/…/derniere-publication-flux-json` | LCB-FT (cache 1 jour) | « vérification à refaire » |
| Leaflet + OpenStreetMap | `cdnjs.cloudflare.com`, tuiles OSM | cartes de prospection et d'estimation | liste seule |
| Découpage administratif | `geo.api.gouv.fr/communes?lat=&lon=` | communes voisines (estimation dans les petites communes) | estimation sur la commune seule |
| firma.dev | `api.firma.dev/functions/v1/signing-request-api` | signature électronique (mode recommandé) | message d'erreur, rien n'est envoyé |
| BoldSign | `api-eu.boldsign.com` | signature électronique (comme le projet Qualiopi) | idem |

**À vérifier en premier en production** : les formats exacts des réponses (écrits d'après la documentation, testés
contre des services simulés : le réseau de l'environnement de développement bloquait ces adresses), en particulier
le paramètre `geo_distance` et les noms de colonnes de l'API DPE de l'ADEME, et le format JSON du registre des gels.

### 4.1 Signature électronique : quatre modes (Paramètres → Signature électronique)

| Mode | Comment ça marche |
|---|---|
| **Intégrée** (par défaut) | signature électronique simple (eIDAS) au doigt, code à 6 chiffres par e-mail (15 min, 5 essais), IP, appareil, horodatage, empreinte SHA-256, images des signatures dans le document et **certificat de signature** en dernière page |
| **firma.dev** (recommandé) | `POST /signing-requests/create-and-send` (`Authorization: <clé>`, sans « Bearer ») : PDF en base64, destinataires `temp_1…`, champs `signature` placés **en % de la page** exactement dans les cadres dessinés par nos PDF (`VisitePdf::zoneSignature`), langue `fr`, OTP en option. Retour : webhook `signing_request.recipient.signed` / `.completed` / `.cancelled` / `.expired`, vérifié par HMAC (`X-Firma-Signature: t=…,v1=…`, secret enregistré par le bouton « Déclarer cette adresse chez firma.dev », `POST /webhooks`). Le PDF signé est récupéré via `final_document_download_url` (`GET /signing-requests/{id}`) |
| **BoldSign** | même intégration que le projet **Qualiopi** (`BoldSignService.php`) : `X-API-KEY`, `POST /v1/document/send` en multipart, champs en pixels à 96 dpi, locale FR (nouvel essai sans locale si refusée), webhook `X-BoldSign-Signature` (seul « Completed » est traité, le reste est acquitté), `GET /v1/document/download` |
| **API générique** | contrat ci-dessous, pour un autre service |

Dans tous les modes externes : le document part **quand l'agent clique « Envoyer pour signature »** (jamais
automatiquement à la sortie de visite), l'exemplaire renvoyé par le service devient le PDF officiel du dossier, la
tâche `synchro_signatures` interroge le service toutes les 30 min si un webhook se perd, et le bouton « Vérifier
l'état » fait de même à la demande. Note : le projet Qualiopi utilise **BoldSign**, pas firma.dev.

**À vérifier en production** : le nom exact du champ du secret renvoyé par `POST /webhooks` chez firma.dev
(`signing_secret` attendu, sinon le coller à la main dans Paramètres), les événements à cocher, la tolérance
d'horodatage (1 h ici) ; chez BoldSign, le nouvel essai sans locale du projet Qualiopi est repris.

Contrat générique (mode « api ») :

```
POST <signature_api_url>/demandes            Authorization: Bearer <signature_api_cle>
{ "reference": "<dossier>|<document>", "titre": "Mandat de vente · …",
  "document": { "nom": "mandat.pdf", "contenu_base64": "…" },
  "signataires": [{ "id": "s1", "nom": "…", "email": "…", "telephone": "…", "role": "vendeur|agent|acquereur",
                    "zone": { "page": 2, "x": 22, "y": 116, "w": 74, "h": 20, "page_w": 210, "page_h": 297 } }],
  "url_retour": "https://…/api/signature.php" }
→ { "id": "<id de la demande>" }

Retour (le service appelle) : POST /api/signature.php      Authorization: Bearer <signature_api_cle>
{ "id": "…", "reference": "<dossier>|<document>", "statut": "en_cours|signe|refuse",
  "signataires": [{ "id": "s1", "signe_le": "…" }], "document_signe_base64": "…" }
```

### 4.2 Flux pour les portails (`/flux.php`)

XML simple (`<annonces><annonce reference="…"><titre/><type/><prix/><mention_prix/>…<photos><photo/></photos></annonce>`),
à convertir vers le format du multidiffuseur retenu. La vraie diffusion demande des contrats avec les portails :
**à faire par le logiciel métier**.

---

## 5. L'IA (Gemini)

| Usage | Appel |
|---|---|
| Transcription | `generateContent`, audio en `inline_data` |
| Dossier complet (fiche, annonce, rapports, atouts, pièces du plan, publications) | **un seul** `generateContent` avec `responseSchema` (`generation_schema()`) |
| Argumentaire de l'avis de valeur, point du vendredi, briefing | `generateContent` texte |
| Lecture d'une pièce du vendeur, d'une pièce d'identité, d'une photo | `generateContent` multimodal avec schéma |
| Dictées (acquéreur, retour de visite, appel, offre, rendez-vous, commande) | transcription puis extraction avec schéma (`type_dictee()`) |
| Assistant de la page du bien | `generateContent` avec historique, schéma (réponse, action, créneau, contact) |
| Home staging | modèle d'images (réglage `modele_image`, `responseModalities: IMAGE`) |
| Conversation vocale | Gemini Live (§6) |

**Choix des modèles** : toujours par l'administrateur (Paramètres → Modèles), dans la liste renvoyée par Google
pour sa clé, avec un prix indicatif (€ à €€€€, moins chers en premier). Google retire régulièrement des modèles
(ex. `gemini-live-2.5-flash-preview`) : l'appli ne change jamais de modèle d'elle-même ; elle vérifie avant une
conversation que le modèle existe encore (`modele_live()`, liste gardée 12 h) et sinon affiche lesquels choisir,
et Paramètres signale ⚠ le modèle retiré.

Règles de prompt importantes : ne remplir que ce qui est dit, citation exacte, nombres sans unité, valeurs de listes
exactes ; l'annonce n'invente rien ; le compte rendu vendeur ne contient aucune remarque interne ; l'assistant public
ne donne ni l'adresse exacte, ni d'information sur le vendeur, ni de marge de négociation.

**Mode démo** : sans clé, chaque appel renvoie des données simulées cohérentes (`demo.php` et les fonctions `*_demo`).
Coûts : estimés depuis `usageMetadata`, ventilés par usage et par agent (Paramètres).

---

## 6. Conversation vocale (Gemini Live) · détails et pièges

Fichiers : `public/js/dialogue.js`, `route_live()` dans `public/api/index.php`.

- **Jeton temporaire à usage unique** (`POST …/v1alpha/auth_tokens`), la clé ne quitte jamais le serveur ; WebSocket
  `…BidiGenerateContentConstrained?access_token=<jeton>` (formats du SDK officiel `googleapis/js-genai`).
- `setup` : `responseModalities: ["TEXT"]`, outils `noter` / `terminer`, détection d'activité désactivée côté serveur,
  `contextWindowCompression.slidingWindow`, `inputAudioTranscription`.
- **Optimisations de coût** : réponses texte lues par la voix du navigateur (gratuite) ; détection de parole sur le
  téléphone (seuls les passages parlés partent) ; historique compressé ; uniquement les champs manquants.
- **Pièges** : modèles « native-audio » = voix uniquement (bascule automatique, ≈ 5× plus cher) ; seuil de détection
  relevé pendant que le téléphone parle ; iOS : débloquer `AudioContext` et `speechSynthesis` dans le geste ;
  messages parfois en `Blob`.
- **Gemini 3 (3.1 Flash Live, 3.8 Live)** : voix seulement (`live_voix_seule()`), donc gamme €€€€. Gemini 3.1 coupe la
  conversation (code 1007) si le texte arrive en `clientContent` (« invalid argument ») ou si on lui impose la
  détection de parole manuelle / `languageCode` (« Precondition check failed », constaté en réel). Pour lui
  (`live_texte_direct()`, « mode direct ») : réglage standard de Google (il détecte la parole lui-même), audio envoyé
  seulement quand l'agent parle puis `audioStreamEnd`, texte en `realtimeInput.text`, langue non imposée (aucun
  modèle en voix Gemini ne reçoit `languageCode`). Si un autre modèle coupe en 1007 après un `clientContent`, l'appli
  rouvre seule la conversation en mode direct. Toute coupure affiche le code, le motif de Google et le dernier type
  de message envoyé (sans contenu), pour le diagnostic.
- **Questions difficiles** : boutons « ⏭ Passer cette question » et « ❓ Je ne comprends pas » sous la conversation.
  Ils envoient un tour texte `[PASSER] …` / `[EXPLIQUER] …` (`Conversation.commande()`) ; les consignes disent à l'IA
  de ne plus reposer une question passée, et d'expliquer en deux phrases avec un exemple avant de la reposer. Les
  explications des champs juridiques (`AIDE_CHAMPS` dans `app/fields.php`) sont jointes aux consignes et affichées
  dans la Fiche derrière un « ? » à côté du libellé.
- Testé contre un faux serveur Live, **pas encore contre le vrai Gemini**.

---

## 7. Documents, règles métier et légales

- **PDF** (tFPDF 1.33, polices Archivo et JetBrains Mono en versions fixes, patch du cache de police) : fiche, annonce,
  rapport, compte rendu, dossier complet, mandat, avis de valeur, dossier technique, croquis de plan, bon de visite,
  offre d'achat, point de la semaine, fiche notaire, fiche de vigilance, facture, note de commission, courrier de
  prospection, flyer de boîtage. Charte Synapse partout.
- **Mandat** (gabarit Synapse.immo) : jetons, « [à compléter] », PROJET en filigrane, **numéro de registre
  chronologique attribué avant la signature** (il doit figurer sur l'exemplaire signé), clause de dénonciation encadrée
  (exclusif), rétractation 14 jours + formulaire détachable hors établissement.
- **Annonce** : prix honoraires inclus, pourcentage TTC et qui paie, prix hors honoraires, DPE/GES, **dépenses
  d'énergie estimées** (fourchette du DPE), **« logement à consommation énergétique excessive »** pour F et G,
  copropriété, Géorisques (arrêté du 10 janvier 2017 modifié). **Publication impossible sans mandat signé.**
- **Rendu Leboncoin** : simulation interne (nom en texte, aucun logo), dans un cadre à la largeur réelle d'un téléphone
  (390 px) ou d'un ordinateur (1 280 px). Contrôle avant diffusion : titre (70 caractères conseillés), description
  (400 à 4 000), photos (3 minimum, 8 conseillées), prix, honoraires, DPE et cohérence avec le texte, dépenses
  d'énergie, copropriété, coordonnées dans le texte. Les limites exactes sont à confirmer avec le multidiffuseur.
  La publication réelle passe par un compte pro Leboncoin ou un multidiffuseur (vrai logiciel).
- **Estimation** : ventes DVF de la commune notées selon leur ressemblance (surface ±25 %, pièces, terrain, distance,
  ancienneté), prix actualisés avec la tendance locale (médiane par année, ±10 %/an au plus), médiane et
  quantiles 20/80 pondérés, ajustements état / DPE / extérieur / stationnement / exposition, indice de confiance.
  **Petites communes** : sous 12 ventes du même type en 5 ans, on ajoute les communes voisines (trouvées en
  demandant à geo.api.gouv.fr la commune de 16 points placés à 4 et 9 km), jusqu'à 30 ventes et 6 communes au plus ;
  la distance est alors jugée à l'échelle du canton (5 km au lieu de 1,5 km). Les communes ajoutées sont citées à
  l'écran et dans l'argumentaire de l'avis de valeur.
- **Acquéreurs ↔ biens** : la commune recherchée est un critère éliminatoire (accents, tirets, « St/Saint » et code
  postal ignorés). Les communes voisines sont proposées jusqu'à 15 km avec un score qui baisse avec la distance
  (−5 points puis −2,5 par km) ; liste classée du plus au moins compatible, score affiché. Position du bien : celle
  du dossier technique si elle est réelle, sinon le centre de sa commune retrouvé par son nom (données simulées, jeu
  de démonstration). Les biens écartés sont listés avec la raison (distance, budget, type, commune introuvable).
- **Journal des accès** : consultations de dossier par les agents (une ligne par quart d'heure et par dossier), PDF
  (mandat, dossier complet, fiche notaire, fiche de vigilance marqués sensibles), pièces, audio, exports, dépôt de
  pièce d'identité ; côté clients, ouverture de l'espace, documents et pièces. Date, personne, rôle, bien, objet,
  adresse IP. Consultation et export CSV réservés aux administrateurs (l'export est lui-même tracé) ; inclus dans
  l'export des données d'une personne ; effacé après 12 mois par défaut (réglage).
- **Avis de valeur** : mention « ne constitue pas une expertise ».
- **Home staging** : mention « aménagement virtuel · image retouchée » incrustée sur toute image modifiée.
- **Bon de visite** : engagement de passer par l'agence (durée du mandat + 12 mois).
- **Offre d'achat** : condition suspensive de prêt (L313-40 code conso), aucun versement (art. 1589-1 code civil),
  rappel du délai SRU.
- **Délai SRU** : 10 jours à compter du lendemain de la notification, reporté au premier jour ouvrable (week-ends,
  jours fériés, Pâques calculé).
- **LCB-FT** : identification, registre national des gels, PPE, origine des fonds, niveau de vigilance
  (simplifiée / standard / renforcée / interdit), fiche conservée 5 ans ; en cas de correspondance : ne pas poursuivre,
  déclarer à TRACFIN.
- **Facture** : mentions de pénalités de retard et d'indemnité de 40 €, carte professionnelle et garantie.
- **Prospection** : courrier adressé « Au propriétaire » (données publiques ADEME), mention d'opposition ; pas de
  démarchage téléphonique (consentement préalable désormais requis) ; pas de récupération des annonces de
  particuliers sur les portails (conditions d'utilisation).
- **Tous ces textes sont à faire relire par un juriste** avant usage réel, puis à remplacer par les modèles du réseau.

---

## 8. Tâches automatiques (`app/cron.php`, toutes les 10 minutes)

| Tâche | Quand |
|---|---|
| `relances_pieces` | J+2, J+5, J+10, J+17, J+24 après l'envoi au vendeur, heures ouvrables, tant qu'il manque des pièces |
| `relances_signature` | J+2 et J+5 pour chaque signataire qui n'a pas signé |
| `alertes_acquereurs` | quand un bien publié correspond à un acquéreur (score ≥ 75, alertes actives) |
| `cr_hebdo` | vendredi à partir de 17 h : point de la semaine (envoyé ou « à relire » selon le choix de l'agent) |
| `relances_vente` | prêt à J-15 et J-5 (acquéreur, courtier), notaires à J-10 de l'acte, rappel aux parties à J-2 |
| `avis_google` | J+2 après l'acte, relance à J+9 si le lien n'a pas été ouvert |
| `briefing` | notification à 7 h 30 les jours ouvrés |
| `rgpd_audio` | effacement de l'audio après N jours (réglage) |
| `rgpd_journal_acces` | effacement des mois du journal des accès plus anciens que la durée choisie |
| `synchro_signatures` | firma.dev / BoldSign : état des demandes en attente, au plus toutes les 30 min chacune |

Chaque tâche mémorise ce qu'elle a déjà fait (pas de doublon). `--maintenant=AAAA-MM-JJTHH:MM` simule une date
(tests), `--tache=<nom>` n'en lance qu'une.

---

## 9. Tester le prototype

```sh
cd visite-immo
php -S localhost:8000 -t public          # puis http://localhost:8000 : créer le compte admin
```

- Paramètres → **« Charger le jeu de démonstration »** : 6 biens à toutes les étapes, acquéreurs, agenda, prospection.
- **Tests automatisés** (`tests/`, voir son en-tête) : `./tests/tout.sh` lance 21 scénarios Playwright sur des
  données neuves, avec services publics, SMTP, push, firma.dev et BoldSign simulés. Couvre : parcours de base, sortie
  de visite complète, espace vendeur et signature avec code, photos, acquéreurs et agenda, assistant qui réserve,
  vidéo, offre → acte, quotidien (dont **déchiffrement réel d'une notification push** et vérification de la
  signature VAPID), prospection, **signature firma.dev / BoldSign** (positions des champs, webhooks HMAC, rejeu,
  exemplaire signé), **estimation en temps réel**, **rendu Leboncoin**, **suivi du projet** (captation, cases en
  direct, export CRM), **journal des accès**, **accessibilité** (audit axe-core WCAG 2.1 AA sur 36 écrans : clair, sombre, plein soleil,
  espace client, page publique), **sécurité** (en-têtes, essais de connexion, liens cloisonnés, données piégées),
  **suppressions**, démo et réseau.

**Non testé en conditions réelles** (à faire en premier) :
- vrais appels Gemini (transcription, génération, Live, multimodal, images) ;
- vrais services publics (formats de réponse, §4) ;
- formats audio des téléphones (`webm` Android, `mp4` iPhone) côté Gemini ;
- iPhone réel : micro écran verrouillé, voix de synthèse, notifications (PWA installée, iOS 16.4+) ;
- envoi SMTP chez un vrai fournisseur ; délivrabilité des relances ;
- firma.dev et BoldSign avec de vraies clés (formats écrits d'après leur documentation, §4.1) ;
- relecture juridique de tous les documents.

---

## 10. Pour le vrai développement : quoi garder, quoi refaire

**À garder (le savoir, pas forcément le code)**
- la définition des champs (`fields.php`) et la règle des sources ;
- les points d'extension par module (§3.1) : c'est la bonne découpe du métier ;
- les prompts et schémas (`ai.php`, `type_dictee`), l'assistant vocal et ses optimisations (§6) ;
- les règles métier et légales (§7) et les délais des relances (§8) ;
- l'accessibilité (contrastes AA, libellés, cibles de 44 px) et les réglages « Confort sur le terrain » (plein soleil,
  grands boutons, texte plus grand, sans animations) ;
- l'UX : un seul bouton pour démarrer, « Prochaine étape », écran Aujourd'hui, dictées de 30 s, espace client sans
  mot de passe, tout signable au doigt.

**À refaire autrement**
- **Intégrer dans la plateforme Synapse.immo** (CRM, contrats, GED, registre unique) plutôt qu'une appli à part.
  Le dossier devient bien + contacts + mandat + vente du CRM.
- Base de données à la place du JSON ; stockage objet pour l'audio et les photos ; **file de tâches** pour les appels
  IA (aujourd'hui synchrones dans la requête) ; cron remplacé par un planificateur.
- Multi-agences, droits fins, journal d'audit, chiffrement des données sensibles (état civil, pièces d'identité),
  sauvegardes.
- Diffusion portails et signature via les connecteurs du logiciel métier.
- Tarifs IA dans une configuration à jour.

---

## 11. Décisions prises avec le client (pour ne pas les rediscuter)

- PWA mobile ; enregistrement **dans l'appli** ; archivage ; suppression de l'audio seul ou de tout.
- PHP natif, sans base de données, **pour le prototype uniquement** ; cron disponible sur l'hébergement.
- **Interface ultra simple** : presque tout découle de la visite et se fait automatiquement en sortant.
- IA : **Gemini uniquement**, tout réglable dans l'appli ; conversation vocale en direct, voix du navigateur.
- Vrais PDF côté serveur, vrais e-mails avec pièces jointes ; charte Synapse partout.
- Signature : **firma.dev** (choix du client) ; BoldSign gardé en alternative car déjà utilisé dans le projet Qualiopi ;
  signature intégrée conservée pour tester sans compte.
- Documents Synapse officiels non fournis : on travaille avec le gabarit existant (outil de test).
- Envoi vers le CRM : **plus tard**, dans le vrai développement (l'export JSON `crm_export` donne déjà le format).
- **Suivi temps réel** : à la sortie de la visite, si l'audio, les photos, les documents et le dialogue avec l'IA sont
  faits, le dossier doit être quasiment opérationnel ; le tableau de suivi le montre case par case.
- Offre agents (à valider) : 80 % → 90 % (40 k€) → 95 % (80 k€) des honoraires HT sur 12 mois glissants, réglables ;
  abonnement 79 € HT/mois.
