# Passation · prototype « Visite Immo » pour le réseau Synapse

> **À lire en premier par l'équipe (ou l'IA) qui fera le vrai développement.**
> Ce dépôt contient un **prototype d'exploration** : il a servi à tester des idées vite, avec de vrais utilisateurs,
> avant de les développer proprement dans le logiciel métier Synapse. Ce document résume ce qui a été construit,
> ce qui a été appris, les pièges rencontrés et ce qu'il faudra refaire autrement en production.
>
> Feuille de route de la suite : [`PLAN-DEVELOPPEMENT.md`](PLAN-DEVELOPPEMENT.md).
>
> Dernière mise à jour : 7 octobre 2026 · branche `claude/nice-cori-uknqlh` du dépôt `ftholomier/poubelle`.

---

## 1. Récupérer tout le travail (export zip)

Tout est versionné dans Git. Trois façons d'en faire un zip :

- **GitHub** : ouvrir `https://github.com/ftholomier/poubelle/tree/claude/nice-cori-uknqlh`, bouton **Code → Download ZIP**.
- **En ligne de commande** :
  ```sh
  git clone -b claude/nice-cori-uknqlh https://github.com/ftholomier/poubelle.git
  cd poubelle && git archive --format=zip -o visite-immo.zip HEAD visite-immo synapse-offre PASSATION.md
  ```
- L'historique des commits (`git log`) raconte l'ordre des décisions, en français, une étape par commit.

Contenu utile du dépôt :

| Dossier / fichier | Quoi |
|---|---|
| `visite-immo/` | Le prototype : PWA mobile + API en PHP natif. Son `README.md` décrit l'installation et l'usage. |
| `synapse-offre/` | L'infographie A4 « L'offre irrésistible » (PDF + générateur). |
| `PASSATION.md` | Ce document. |
| autres fichiers à la racine | Sans rapport (exports de logos d'autres projets). |

---

## 2. L'idée et ce qui a été validé

**Objectif métier : que l'agent immobilier ne saisisse (presque) plus rien.** Il fait son vrai métier (visiter,
rentrer des mandats, conseiller, vendre) ; le logiciel produit la paperasse.

Parcours testé dans le prototype :

1. **Enregistrer la visite** au téléphone (gros bouton rouge, consentement du vendeur obligatoire).
   L'audio part au serveur par morceaux de 3 minutes, transcrits au fil de l'eau.
2. **« Créer la fiche »** : l'IA produit en une fois la fiche du bien (61 champs), l'annonce, un rapport interne et
   un compte rendu pour le vendeur. Chaque valeur remplie par l'IA garde la phrase entendue (« citation »).
3. **« Compléter à la voix »** : en sortant de la visite, un assistant vocal en temps réel pose uniquement les
   questions qui manquent (vendeurs, état civil, régime matrimonial, cadastre, type de mandat, prix, honoraires…)
   et remplit les champs pendant la conversation. Jauge de complétude du dossier.
4. **Mandat de vente en PDF** rempli automatiquement, conforme (mentions obligatoires), avec registre des mandats,
   clause de rétractation et formulaire détachable quand il est signé au domicile.
5. **Documents PDF** à la charte (fiche, annonce, rapport, compte rendu, dossier complet) et **envoi par e-mail**
   avec pièces jointes, depuis l'appli.
6. **Aperçu de l'annonce sur un portail** (mise en page inspirée de leboncoin), avec les mentions légales calculées.

Ce qui reste à valider avec de vrais agents : la qualité réelle de la transcription et de l'extraction sur des
visites bruyantes, l'acceptation de la conversation vocale, et les temps gagnés (les chiffres de l'infographie
sont des estimations).

---

## 3. Architecture du prototype

Choix volontairement simples pour tester vite : **PHP natif sans framework ni Composer, fichiers JSON, pas de base
de données**, interface en HTML/CSS/JS sans framework (modules ES). À ne pas reproduire tel quel en production
(voir §9).

```
visite-immo/
├── app/                        code serveur (jamais servi directement, .htaccess)
│   ├── bootstrap.php           config, session, utilisateurs, stockage JSON avec verrous, logo, constantes
│   ├── config.sample.php       valeurs par défaut (tout se règle ensuite dans l'appli → app/settings.json)
│   ├── fields.php              LA définition des champs du dossier (formulaire + prompts IA + complétude)
│   ├── ai.php                  Gemini : modèles, transcription, génération, jeton Live, coûts
│   ├── demo.php                données simulées (mode démo sans clé API)
│   ├── pdf.php                 documents PDF (tFPDF) à la charte Synapse
│   ├── mandat.php              mandat de vente : gabarit, jetons, PDF, registre
│   ├── mailer.php              client SMTP natif (SSL/STARTTLS) + mail(), MIME, e-mail HTML
│   ├── assets/synapse-logo.png logo par défaut des PDF et e-mails
│   └── lib/tfpdf/              tFPDF 1.33 (LGPL, patché) + polices Archivo et JetBrains Mono
├── data/                       stockage (hors web) : users.json, visites/<agent>/<visite>/, registre.json, couts/
└── public/                     racine web
    ├── index.php               page de l'appli (versionne CSS/JS pour casser les caches)
    ├── api/index.php           toute l'API JSON (/api/?r=<route>)
    ├── js/app.js               écrans, navigation, formulaires (le gros du front)
    ├── js/recorder.js          enregistrement micro découpé en morceaux autonomes
    ├── js/uploader.js          file d'envoi des morceaux (IndexedDB, reprise hors ligne)
    ├── js/dialogue.js          conversation vocale Gemini Live (micro, détection de parole, voix)
    ├── js/apercu.js            aperçu de l'annonce façon portail
    ├── js/api.js               appels à l'API
    ├── css/app.css             charte Synapse
    ├── sw.js, manifest         PWA (installable, s'ouvre hors ligne)
    └── img/, fonts/            logo Synapse vectorisé, polices
```

Environ 5 500 lignes. Tests faits avec Playwright (Chromium, micro simulé), un faux serveur Gemini Live, un faux
serveur SMTP ; ils sont décrits au §8 mais ne sont pas versionnés.

### API (`public/api/index.php`, toutes en JSON, `?r=<route>`)

| Route | Rôle |
|---|---|
| `GET status` · `POST setup/login/logout/password/profile` | session, premier compte admin, profil (e-mail, téléphone de l'agent) |
| `GET/POST/DELETE users` | équipe (admin) |
| `GET/POST settings` · `POST models` | paramètres (admin) : clé Gemini, modèles, stockage, identité et mentions légales de l'agence, SMTP |
| `POST mailtest` · `GET/POST/DELETE logo` | e-mail de test, logo de l'agence |
| `GET visits` · `POST visits` | liste, création d'une visite (consentement obligatoire) |
| `GET/POST/DELETE visit` | lire (avec complétude et manques du mandat), enregistrer des champs (`source` : `agent` ou `dialogue`), supprimer |
| `POST chunk` | réception d'un morceau audio + transcription immédiate |
| `POST generate` | génération fiche + annonce + rapports |
| `GET/DELETE audio` | lecture (Range, iPhone) ou suppression de l'audio seul |
| `GET pdf&doc=` | `fiche`, `annonce`, `rapport`, `vendeur`, `dossier`, `mandat` |
| `POST send` | e-mail avec PDF joints, historique dans la visite |
| `POST live` · `POST usage` | conversation vocale : jeton temporaire + consignes ; coût consommé |
| `POST registre` | inscription du mandat au registre (numéro définitif) |

Sécurité du prototype : session PHP (cookie `SameSite=Strict`, `HttpOnly`), en-tête `X-Requested-With: visite-immo`
exigé sur toute écriture (anti-CSRF), mots de passe `password_hash`, identifiants de visite validés par regex, données
hors du dossier web.

---

## 4. Modèle de données

**Une visite = un fichier `data/visites/<id agent>/<id visite>/visite.json`** (+ `audio/000.webm`…).

```jsonc
{
  "id": "20261007-063305-1363a6", "titre": "11 rue du Chenois, 25260 Lougres",
  "statut": "enregistrement | enregistre | generation | pret | erreur",
  "cree_le": "…", "consentement_le": "…",
  "morceaux": [{ "n": 0, "fichier": "000.webm", "mime": "audio/webm", "duree": 180, "transcription": "…", "statut": "transcrit" }],
  "audio_supprime": false,
  "fiche": { "champs": { "nb_chambres": { "valeur": "4", "citation": "Il y a quatre chambres", "source": "ia" } } },
  "titre_annonce": "…", "annonce": "…", "rapport_agent": "…", "rapport_vendeur": "…",
  "mandat": { "numero": "2026-0001", "inscrit_le": "…" },
  "envois": [{ "date": "…", "a": "client@…", "docs": ["vendeur", "fiche"], "copie": true }],
  "couts": { "transcription": 0.012, "analyse": 0.03, "conversation": 0.41 }
}
```

- **`source` d'un champ** : `ia` (extrait de la visite), `agent` (corrigé à la main), `dialogue` (dicté à l'assistant
  vocal). Règle clé : **une régénération par l'IA n'écrase jamais un champ `agent` ou `dialogue`.**
- **Les champs** sont tous définis dans `app/fields.php` (sections, types `text/number/select/bool/date/textarea`,
  `requis`, `requis_si` conditionnel comme « régime matrimonial si marié »). Le formulaire, les prompts, la complétude,
  les PDF et la conversation vocale en découlent : **c'est la pièce à porter en premier** dans le vrai système.
- Sections marquées `interne` (vendeurs, situation juridique, mandat) : exclues de la fiche envoyée aux clients.
- `data/registre.json` : registre des mandats `{ compteurs: {2026: 1}, entrees: [...] }`.
- `data/couts/AAAA-MM.json` : coût IA estimé du mois, par usage et par agent.

---

## 5. L'IA (Gemini)

Choix du client : **Gemini uniquement**, clé et modèles réglables dans l'appli (liste des modèles chargée
dynamiquement depuis la clé). Tous les appels sont en cURL natif dans `app/ai.php`.

| Usage | Appel | Notes |
|---|---|---|
| Transcription | `generateContent`, audio en `inline_data` (base64) + consigne | un morceau de 3 min ≈ 1 Mo |
| Fiche + annonce + rapports | `generateContent` avec `responseMimeType: application/json` + `responseSchema` (format OpenAPI, types en MAJUSCULES) | un seul appel produit tout ; prompt dans `generation_prompt()` |
| Conversation vocale | **Gemini Live** (WebSocket bidirectionnel) | voir §6 |

Prompts importants (à reprendre) :
- extraction : ne remplir que ce qui est dit sans ambiguïté, garder la dernière valeur en cas de correction, citation
  exacte, nombres sans unité, valeurs de listes exactes ;
- annonce : n'invente rien, pas de motif de vente ni de défauts, mentions en fin ;
- rapport vendeur : ton valorisant, aucune remarque interne ;
- les **informations validées par l'agent** sont injectées comme prioritaires dans la génération.

**Mode démo** : sans clé, transcription et génération renvoient des données simulées (`app/demo.php`), ce qui permet
de tester toute l'interface.

**Coûts** (estimation, `tarif()` / `cout_usage()`) : calculés depuis `usageMetadata` renvoyé par Gemini, convertis en €,
affichés dans Paramètres. Ordres de grandeur trouvés en octobre 2026 (à revérifier) : visite de 30 min transcrite et
analysée ≈ 0,15 € ; conversation vocale ≈ 0,05 à 0,50 € selon les optimisations.

---

## 6. Conversation vocale (Gemini Live) · détails et pièges

Fichiers : `public/js/dialogue.js` (client), `route_live()` dans `public/api/index.php` (consignes et outils).

- **Sécurité de la clé** : le serveur crée un **jeton temporaire à usage unique**
  (`POST https://generativelanguage.googleapis.com/v1alpha/auth_tokens`, `uses: 1`, expiration 30 min) ; le téléphone
  se connecte à
  `wss://generativelanguage.googleapis.com/ws/google.ai.generativelanguage.v1alpha.GenerativeService.BidiGenerateContentConstrained?access_token=<jeton>`.
  Ces formats viennent du SDK officiel `googleapis/js-genai` (`src/live.ts`, `src/tokens.ts`) ; fonction encore
  marquée expérimentale chez Google.
- **Message `setup`** : modèle, `generationConfig.responseModalities: ["TEXT"]`, `systemInstruction`, `tools`
  (fonctions `noter` et `terminer`), `realtimeInputConfig.automaticActivityDetection.disabled: true`,
  `contextWindowCompression.slidingWindow`, `inputAudioTranscription`.
- **Optimisations de coût retenues** (demandées par le client) :
  1. réponses en **texte lues par la voix du navigateur** (`speechSynthesis`, gratuite) au lieu de la voix Gemini ;
  2. **détection de parole sur le téléphone** : seuls les passages parlés sont envoyés (`activityStart`, audio PCM
     16 kHz en base64, `activityEnd` après 900 ms de silence) ; les silences ne sont pas facturés ;
  3. historique compressé (fenêtre glissante) ;
  4. consignes : questions courtes, ne demander que les champs manquants (liste injectée dans les consignes).
- **Remplissage** : l'IA appelle `noter({champs:[{cle, valeur}]})` ; le client enregistre (`source: dialogue`) et
  renvoie la liste des champs obligatoires restants dans la réponse de l'outil.
- **Pièges** :
  - les modèles « native-audio » ne répondent qu'en voix : le client détecte le nom et bascule en `AUDIO`
    (lecture PCM 24 kHz), environ 5 fois plus cher ;
  - pendant que la voix du téléphone parle, le micro l'entend : le seuil de détection est relevé (×3,5) pour éviter
    que l'IA se réponde à elle-même, tout en laissant l'agent lui couper la parole en parlant fort ;
  - iOS : `AudioContext` et `speechSynthesis` doivent être débloqués pendant le geste de l'utilisateur (bouton
    « Démarrer ») ;
  - les messages du serveur arrivent parfois en binaire (`Blob`) : toujours `await data.text()`.
- **Statut** : testé de bout en bout contre un **faux serveur Live** respectant le protocole, **pas encore contre le
  vrai Gemini**. Le nom du modèle par défaut (`gemini-live-2.5-flash-preview`) est à choisir dans la liste réelle.

---

## 7. Documents, mandat, e-mails

- **PDF** : tFPDF 1.33 (UTF-8, polices TrueType intégrées en sous-ensemble). Deux adaptations :
  - patch dans `tfpdf.php` : le cache de police mémorisait un chemin absolu, recalculé à la lecture ;
  - Archivo et JetBrains Mono n'existent qu'en polices **variables** ; des versions fixes ont été extraites avec
    `fontTools.varLib.instancer` (tFPDF ne lit pas les polices variables). La police n'a pas le caractère « ★ » :
    étoiles dessinées en vectoriel.
- **Charte Synapse** dans les PDF : fond crème, titre surligné citron, étiquette orange inclinée, cartes à contour noir,
  carte prix noire, bandeau noir à étoiles en pied de page, DPE aux couleurs officielles.
- **Mandat** (`app/mandat.php`) : gabarit repris de la plateforme Synapse.immo (dépôt `ftholomier/suisse-immo`,
  `config/contracts.php`), qui contient la liste des mentions obligatoires avec leurs références légales. Règles
  appliquées :
  - jetons `{{…}}` remplis depuis le dossier ; une valeur manquante devient « [à compléter : …] » surligné en rouge ;
  - **PROJET** en filigrane tant que non inscrit ; inscription refusée si une mention manque ;
  - **numéro de registre chronologique sans trou ni réemploi** (décret 72-678 art. 72) ;
  - exclusif et semi-exclusif : clause de dénonciation à trois mois **en caractères très apparents** (encadrée) ;
  - signé au domicile ou à distance : rétractation de 14 jours avec date de fin et **formulaire détachable** (sans lui,
    le délai passe à 12 mois et 14 jours : première cause de perte d'honoraires) ;
  - montants en chiffres et en lettres, prix net vendeur déduit, accords grammaticaux (mariés, née/né).
  Le texte n'a pas été relu par un juriste : **à faire valider**, puis remplacer par les modèles du réseau (le client a
  évoqué une dizaine de documents Synapse rédigés ailleurs, non retrouvés dans les dépôts).
- **E-mails** (`app/mailer.php`) : client SMTP natif (SSL 465 / STARTTLS 587, AUTH PLAIN/LOGIN) ou `mail()`, MIME
  `mixed > related > alternative` (texte + HTML + logo intégré + PDF joints), `Reply-To` = e-mail de l'agent,
  copie cachée à l'agent. Testé contre un faux serveur SMTP, pas encore avec un vrai fournisseur.
- **Annonce et mentions légales** (`public/js/apercu.js`) : prix honoraires inclus, pourcentage TTC et qui paie, prix hors
  honoraires, DPE/GES, copropriété, mention Géorisques (arrêté du 10 janvier 2017).

---

## 8. Tester le prototype

```sh
cd visite-immo
php -S localhost:8000 -t public      # puis http://localhost:8000 : créer le compte admin
```

- Sans clé Gemini : **mode démo** complet (enregistrement, fiche, PDF, mandat, aperçu).
- Avec clé : Paramètres → clé Gemini → choisir les trois modèles (analyse, transcription, conversation).
- Les tests automatisés de la session utilisaient Playwright avec
  `--use-fake-device-for-media-stream --use-file-for-fake-audio-capture=<wav>`, un faux Gemini Live (`ws`, Node) et
  un faux SMTP (`aiosmtpd`). Ils ne sont pas versionnés ; à reconstruire dans une vraie suite de tests.

**Non testé en conditions réelles** (à faire en premier) :
- vrais appels Gemini (transcription, génération, Live, jeton temporaire) ;
- formats audio des téléphones (`webm` Android, `mp4` iPhone) : absents de la liste officielle des formats Gemini ;
- iPhone réel : micro écran verrouillé, voix de synthèse, PWA installée ;
- envoi SMTP chez OVH / Gmail / o2switch ;
- relecture juridique du mandat.

---

## 9. Pour le vrai développement : quoi garder, quoi refaire

**À garder (le savoir, pas forcément le code)**
- la définition des champs (`fields.php`) et la règle des sources (`ia` / `agent` / `dialogue`) ;
- les prompts et schémas de sortie (`ai.php`) et les consignes de l'assistant vocal (`route_live`) ;
- le protocole Live et ses optimisations de coût (§6) ;
- les règles du mandat et de l'annonce (§7) ; le gabarit et les mentions viennent de la plateforme Synapse.immo,
  qui reste la source de vérité ;
- l'enregistrement par morceaux autonomes + file d'envoi hors ligne (fiable sur le terrain) ;
- l'UX : un gros bouton, « Créer la fiche », jauge de complétude, champs « IA » avec citation et « DICTÉ ».

**À refaire autrement**
- **Intégrer dans la plateforme Synapse.immo** (`suisse-immo`, PHP, base de données, CRM, contrats, GED) plutôt que
  de garder une appli à part. Le dossier d'une visite devient un bien + des contacts + un mandat du CRM.
  Correspondance des jetons du mandat : `mandant.*`, `bien.*`, `mandat.*`, `agence.*` (identiques au gabarit Synapse).
- **Un seul registre des mandats** : celui du logiciel métier. Le registre JSON du prototype ne doit pas coexister
  avec un autre.
- Base de données à la place des fichiers JSON ; stockage objet pour l'audio ; file de tâches pour la transcription et
  la génération (aujourd'hui synchrones dans la requête HTTP, `set_time_limit(320)`).
- Multi-agences (tenants), droits fins, journal d'audit, chiffrement des données personnelles (date de naissance,
  état civil), durée de conservation de l'audio (RGPD), consentement à l'enregistrement tracé.
- Signature électronique et diffusion portails via les connecteurs du logiciel métier.
- Tarifs IA : les lire dans une configuration à jour, pas en dur.

---

## 10. Idées suivantes (proposées, non construites)

Détaillées dans l'infographie `synapse-offre/` (parcours en 6 étapes). Priorités suggérées :

1. **Dossier technique automatique depuis l'adresse** : cadastre (IGN), état des risques (Géorisques), DPE (ADEME),
   ventes du quartier et avis de valeur (DVF). Données publiques gratuites.
2. **Lecture des papiers du vendeur par l'IA** (acte, taxe foncière, PV d'AG, diagnostics) → remplit origine de
   propriété, cadastre, lots, charges ; relance automatique des pièces manquantes.
3. **Assistant acquéreurs 24 h/24** : réponse aux contacts des portails, qualification, prise de rendez-vous.
4. Compte rendu de visite acquéreur dicté + **rapport de commercialisation hebdomadaire** automatique au vendeur.
5. Suivi du compromis à l'acte (dossier notaire, échéances SRU / prêt, relances).
6. Autres : contrôle LCB-FT automatique (la plateforme Synapse.immo a déjà un module de sanctions), plan 2D au LiDAR,
   photos et vidéos réseaux sociaux, prospection ciblée par courrier (DPE F/G, DVF), briefing du matin, bilan d'appel
   dicté, facture d'honoraires, demande d'avis Google.

---

## 11. Décisions prises avec le client (pour ne pas les rediscuter)

- PWA mobile, l'enregistrement se fait **dans l'appli** ; archivage des visites, suppression possible de l'audio seul
  ou de toute la visite.
- PHP natif, sans base de données, pour le prototype uniquement.
- Multi-utilisateurs ; rapports internes **et** compte rendu envoyé au vendeur.
- IA : **Gemini uniquement**, tout réglable dans l'appli (clé, modèles, stockage, signature).
- Conversation vocale **en direct**, avec toutes les optimisations de coût, voix du navigateur.
- Vrais PDF générés côté serveur (pas d'impression navigateur) ; vrai envoi d'e-mails avec pièces jointes.
- Charte Synapse partout (interface, PDF, e-mails) : crème, encre, citron, orange, vert, Archivo + JetBrains Mono ;
  logo redessiné en vectoriel d'après une image (à remplacer par le fichier officiel s'il existe).
- Offre agents (proposition à valider) : 80 % → 90 % (40 k€) → 95 % (80 k€) des honoraires HT sur 12 mois glissants,
  abonnement 79 € HT/mois, 3 mois offerts, sans engagement.
