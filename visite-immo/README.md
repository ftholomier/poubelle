# Visite Immo · Synapse

> **Statut : prototype d'exploration.** Ce projet sert à tester les idées et les usages (enregistrement de visite,
> IA, conversation vocale, documents, diffusion) avant un vrai développement, plus tard, dans le logiciel métier.
> Les choix techniques (PHP natif, fichiers JSON, pas de base de données) visent la rapidité de test, pas la production.

Interface, PDF et e-mails à la charte **Synapse** : fond crème, encre noire, surlignage citron, étiquettes
orange, polices Archivo et JetBrains Mono. Logo vectoriel dans `public/img/` (`synapse-logo.svg`,
`synapse-logo-clair.svg` pour fond sombre, `synapse-icone.svg`).

Appli mobile (PWA) pour agents immobiliers : l'agent enregistre la visite avec son téléphone, puis un bouton
« Créer la fiche » produit automatiquement :

- **la fiche du bien**, champs remplis (chambres, surface, DPE, prix…), chaque valeur reliée à la phrase entendue ;
- **l'annonce** (titre et description pour les portails) ;
- **le rapport interne** pour l'agent (points forts et faibles, avis sur le prix, choses à vérifier, prochaines étapes) ;
- **le compte rendu au vendeur**, prêt à envoyer par e-mail.

Les visites sont archivées (audio, transcription, documents) et l'agent peut supprimer l'audio seul ou toute la visite.

PHP natif, sans framework, sans Composer, **sans base de données** : tout est stocké en fichiers JSON.

## Parcours

1. 🔴 **Nouvelle visite** → l'agent coche l'accord du vendeur, appuie sur le bouton rouge. Il peut faire pause, reprendre, terminer.
2. Pendant l'enregistrement, l'audio est découpé en morceaux de 3 minutes, **envoyés et transcrits au fil de l'eau**.
   Sans réseau, les morceaux attendent dans le téléphone (IndexedDB) et repartent tout seuls.
3. ✨ **Créer la fiche** → en 20 à 40 secondes, fiche, annonce et rapports sont prêts, modifiables, sauvegardés automatiquement.
4. 📁 **Mes visites** → archive consultable : réécouter, relire, copier, PDF, envoyer, supprimer.

## Structure

```
visite-immo/
├── app/                 ← code serveur (hors web)
│   ├── config.sample.php   valeurs par défaut (tout se règle ensuite dans l'appli)
│   ├── settings.json       réglages faits dans l'appli (créé automatiquement)
│   ├── bootstrap.php       session, utilisateurs, stockage JSON
│   ├── fields.php          liste des champs de la fiche (modifiable)
│   ├── ai.php              Gemini en cURL : liste des modèles, transcription, génération
│   ├── pdf.php             mise en page des documents PDF
│   ├── mailer.php          envoi d'e-mails (client SMTP natif ou mail())
│   ├── lib/tfpdf/          bibliothèque PDF tFPDF + police Archivo
│   └── demo.php            données simulées quand il n'y a pas de clé
├── data/                ← stockage (hors web, créé automatiquement)
│   ├── users.json
│   └── visites/<agent>/<visite>/visite.json + audio/
└── public/              ← racine du site
    ├── index.php (page de l'appli), manifest.webmanifest, sw.js, icônes
    ├── api/index.php       API JSON (/api/?r=…)
    ├── css/app.css
    └── js/                 app.js, recorder.js, uploader.js, api.js
```

## Installation

1. Déposer le dossier sur l'hébergement et faire pointer le domaine (ou sous-domaine) sur `public/`.
   Si l'hébergeur impose un dossier unique, `app/` et `data/` contiennent un `.htaccess` qui en interdit l'accès.
2. Donner les droits d'écriture à PHP sur `app/` (pour `settings.json`).
3. Donner les droits d'écriture à PHP sur `data/`.
4. Ouvrir le site : au premier lancement, on crée le **compte administrateur**.
5. Bouton ⚙️ en haut de l'écran, ou menu ☰ → **Paramètres** (administrateurs uniquement) :
   - **clé API Gemini** (https://aistudio.google.com/apikey) : dès qu'elle est saisie, l'appli la vérifie et charge
     la liste des modèles disponibles pour cette clé ;
   - **modèle d'analyse** (fiche, annonce, rapports) et **modèle de transcription**, choisis dans cette liste ;
   - **dossier de stockage** : en le changeant, les comptes et visites y sont déplacés automatiquement ;
   - **modèle de conversation vocale** (liste des modèles compatibles Gemini Live) ;
   - **identité de l'agence** : nom (signature du compte rendu vendeur), coordonnées, mentions légales du mandat
     (raison sociale, carte professionnelle, garant, RCP…) et logo (le logo Synapse
     par défaut ; un logo déposé ici le remplace partout : interface, PDF, e-mails) ;
   - **envoi des e-mails** : serveur SMTP (OVH, o2switch, Gmail, Microsoft 365…) ou fonction `mail()` de l'hébergeur,
     avec un bouton « Envoyer un test ».
   Sans clé, l'appli tourne en **mode démo** (transcription et analyse simulées) pour tester l'interface.
6. Chaque agent renseigne son e-mail et son téléphone dans **Mon compte** : ils apparaissent sur les PDF, et les
   clients qui répondent à un e-mail écrivent directement à l'agent.
7. Menu ☰ → « Gérer l'équipe » pour ajouter les agents. Chaque agent ne voit que ses propres visites.
8. Sur le téléphone : ouvrir le site puis « Ajouter à l'écran d'accueil » pour l'avoir comme une appli.

### Prérequis

- **HTTPS obligatoire** : sans lui, le navigateur refuse l'accès au micro.
- PHP 8.1+ avec les extensions `curl`, `fileinfo` et `mbstring`.
- `upload_max_filesize` et `post_max_size` à **10M** minimum (un morceau de 3 min fait environ 1 Mo).
- `max_execution_time` à 300 s si possible (la génération prend 20 à 60 s).

Test en local : `php -S localhost:8000 -t public` puis http://localhost:8000 (le micro est autorisé sur localhost).

## Compléter le dossier à la voix

Bouton **🎙️ Compléter à la voix** (fiche d'une visite) : un assistant vocal pose à l'agent les questions qui manquent
(vendeurs, état civil, régime matrimonial, situation juridique du bien, type de mandat, prix, honoraires, durée, lieu de
signature…) et remplit les champs pendant la conversation. Une jauge indique le taux de complétude du dossier ; les champs
obligatoires manquants sont marqués d'un point orange, les champs dictés de l'étiquette « DICTÉ ». À la fin,
« Mettre à jour les documents » régénère la fiche, l'annonce et les rapports en tenant compte de tout ce qui a été dicté.

Technique et coûts :
- **Gemini Live** (conversation en temps réel). La clé Gemini reste sur le serveur : le téléphone reçoit un jeton
  temporaire à usage unique (30 minutes maximum).
- L'IA répond **en texte, lu par la voix du téléphone** (gratuite). Les modèles « native-audio » ne répondent qu'en voix
  Gemini (≈ 5 fois plus cher) : l'appli s'y adapte, mais préférez un modèle sans « native-audio » (Paramètres).
- La détection de parole se fait sur le téléphone : **seuls les moments où l'agent parle sont envoyés**, pas les silences.
- L'historique est compressé (fenêtre glissante) pour que chaque échange ne refacture pas toute la conversation.
- Paramètres → **Coût de l'IA** : estimation du mois, par usage (transcription, analyse, conversation) et par agent.

Les champs du mandat suivent les mentions obligatoires définies par la plateforme Synapse.immo (loi Hoguet,
décret 72-678, Code de la consommation). La génération du mandat lui-même viendra avec les modèles Synapse.

## Mandat de vente en PDF

Onglet **Mandat** d'une visite : le mandat est rempli automatiquement avec le dossier (enregistrement de la visite et
réponses dictées) et les mentions légales de l'agence (Paramètres).

- **Projet** tant qu'il n'est pas inscrit au registre : filigrane « PROJET », informations manquantes surlignées en rouge
  et listées à l'écran, avec un bouton pour les compléter à la voix.
- **Inscription au registre** (bouton, une fois les mentions complètes) : numéro chronologique définitif `AAAA-0001`,
  jamais réattribué (registre tenu dans `data/registre.json`, décret 72-678 art. 72).
- Contenu : identité complète des mandants (dates en toutes lettres, régime matrimonial, accords), désignation du bien
  (cadastre, copropriété, occupation), origine de propriété, prix et honoraires en chiffres et en lettres avec le prix
  net vendeur, durée avec date de fin, clause de dénonciation encadrée pour l'exclusif et le semi-exclusif.
- Signé au domicile ou à distance : clause de rétractation de 14 jours avec sa date de fin, et **formulaire de
  rétractation détachable** en dernière page (art. L221-5 et R221-1 du code de la consommation).
- Téléchargement PDF et envoi au vendeur par e-mail.

Le texte reprend le gabarit de mandat de la plateforme Synapse.immo : point de départ conforme, à faire relire, puis à
remplacer par le modèle du réseau (`app/mandat.php`).

## Aperçu de l'annonce sur un portail (simulation)

Onglet Annonce → **👁 Aperçu portail** : l'annonce telle qu'elle apparaîtrait sur un site d'annonces (mise en page
inspirée de leboncoin), assemblée automatiquement à partir de la fiche, des réponses dictées et du texte de l'IA, avec
les mentions légales obligatoires (prix honoraires inclus et à la charge de qui, prix hors honoraires, DPE/GES,
copropriété, Géorisques, carte professionnelle). C'est une **simulation interne** : rien n'est publié. La diffusion
réelle passera par le logiciel métier de l'agence, qui alimente les portails.

## Documents PDF et e-mails

Depuis chaque visite, boutons **📄 PDF** et **✉️ Envoyer** :

| Document | Contenu | Destiné à |
|---|---|---|
| Fiche du bien | chiffres clés, étiquette DPE, caractéristiques (sans la partie vendeur) | client, acquéreur |
| Annonce | titre, chiffres clés, description, encadré contact de l'agent | acquéreur |
| Compte rendu de visite | courrier au vendeur, sur papier à en-tête | vendeur |
| Rapport de visite | rapport interne, marqué « CONFIDENTIEL » | agence uniquement |
| Dossier complet | tout, y compris la partie vendeur et le rapport interne | agence uniquement |

L'envoi : saisir l'adresse du client, l'objet et le message (préremplis), cocher les PDF à joindre, « Envoyer ».
L'appli demande confirmation avant d'envoyer un document interne. Chaque envoi est consigné dans la visite.

## Personnaliser la fiche

Tout est dans `app/fields.php` : ajouter, retirer ou renommer un champ suffit, le formulaire et l'IA suivent.
Types disponibles : `text`, `textarea`, `number`, `bool`, `select` (avec `options`).

## Bon à savoir

- **Consentement** : l'enregistrement ne démarre pas tant que l'accord du vendeur n'est pas coché, et la date est archivée.
- **iPhone** : si l'écran se verrouille ou si l'agent change d'appli, iOS peut couper le micro. L'appli garde l'écran allumé
  pendant l'enregistrement et, en cas de coupure, sauve ce qui a été capté et propose de reprendre.
- Les champs **corrigés à la main** ne sont jamais écrasés quand on régénère la fiche.
- Toute l'IA passe par **Gemini** (Google) : la transcription aussi, l'audio est envoyé directement au modèle choisi.
- La clé API est stockée dans `app/settings.json` (protégé par `.htaccess`, jamais renvoyée en entier à l'appli).

## Crédits

- [tFPDF](https://github.com/Setasign/tFPDF) 1.33 (licence LGPL), légèrement modifiée pour que son cache de polices
  fonctionne après un déménagement de serveur.
- Polices [Archivo](https://github.com/Omnibus-Type/Archivo) et [JetBrains Mono](https://github.com/JetBrains/JetBrainsMono)
  (SIL Open Font License 1.1), en versions fixes extraites des polices variables officielles.
- Logo Synapse redessiné en vectoriel d'après le logo fourni : le texte est tracé à partir des polices, le bol et les
  feuilles sont redessinés. À remplacer par le fichier officiel s'il existe.
