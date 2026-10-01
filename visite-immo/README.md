# Visite Immo

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
│   └── demo.php            données simulées quand il n'y a pas de clé
├── data/                ← stockage (hors web, créé automatiquement)
│   ├── users.json
│   └── visites/<agent>/<visite>/visite.json + audio/
└── public/              ← racine du site
    ├── index.html, manifest.webmanifest, sw.js, icônes
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
5. Menu ☰ → **Réglages** (administrateurs uniquement) :
   - **clé API Gemini** (https://aistudio.google.com/apikey) : dès qu'elle est saisie, l'appli la vérifie et charge
     la liste des modèles disponibles pour cette clé ;
   - **modèle d'analyse** (fiche, annonce, rapports) et **modèle de transcription**, choisis dans cette liste ;
   - **dossier de stockage** : en le changeant, les comptes et visites y sont déplacés automatiquement ;
   - **nom de l'agence**, qui signe le compte rendu vendeur (« Nom de l'agent, Agence »).
   Sans clé, l'appli tourne en **mode démo** (transcription et analyse simulées) pour tester l'interface.
6. Menu ☰ → « Gérer l'équipe » pour ajouter les agents. Chaque agent ne voit que ses propres visites.
7. Sur le téléphone : ouvrir le site puis « Ajouter à l'écran d'accueil » pour l'avoir comme une appli.

### Prérequis

- **HTTPS obligatoire** : sans lui, le navigateur refuse l'accès au micro.
- PHP 8.1+ avec les extensions `curl`, `fileinfo` et `mbstring`.
- `upload_max_filesize` et `post_max_size` à **10M** minimum (un morceau de 3 min fait environ 1 Mo).
- `max_execution_time` à 300 s si possible (la génération prend 20 à 60 s).

Test en local : `php -S localhost:8000 -t public` puis http://localhost:8000 (le micro est autorisé sur localhost).

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
