# Visite Immo · Synapse

> **Statut : prototype d'exploration.** Voir [`../PASSATION.md`](../PASSATION.md) (architecture, données, services,
> ce qui reste à valider) et [`../PLAN-DEVELOPPEMENT.md`](../PLAN-DEVELOPPEMENT.md) (avancement).
> Les choix techniques (PHP natif, fichiers JSON, pas de base de données) visent la rapidité de test, pas la production.

Appli mobile (PWA) pour les agents du réseau Synapse. **Presque tout part de la visite** : l'agent l'enregistre, et en
sortant tout est prêt (fiche, annonce, compte rendu, avis de valeur, dossier technique, mandat, pièces à demander).
Ensuite l'appli n'affiche qu'**une action à la fois** et fait le reste toute seule : relances, signature en ligne,
page du bien et assistant acquéreurs 24 h/24, point du vendredi au vendeur, offres, échéancier du compromis,
dossier notaire, facture, commission, demande d'avis.

Interface, PDF et e-mails à la charte **Synapse** (crème, encre, citron, orange ; Archivo et JetBrains Mono).
PHP natif, sans framework, sans Composer, **sans base de données** : tout est stocké en fichiers JSON.

## L'appli en bref

- **Aujourd'hui** : tout ce qu'il y a à faire, tous biens confondus ; briefing du matin à écouter ; « Dites-moi »
  (commande vocale) ; bilan d'appel dicté ; notifications sur le téléphone.
- **Biens** : chaque dossier avec ses étapes (Visite → Dossier → Mandat → En vente → Offre → Compromis → Vendu),
  la prochaine action en grand, les documents, les photos, la vente.
- **● Visite** : le bouton rouge. Enregistrement découpé et transcrit au fil de l'eau, même hors réseau.
- **Acquéreurs** : fiche dictée en 30 secondes, biens compatibles, alertes automatiques.
- **Agenda** : rendez-vous, créneaux proposés par l'assistant, abonnement depuis Google / Apple / Outlook.
- Dans chaque dossier, l'onglet **Suivi** : toutes les cases de la visite à la diffusion (fait, à faire, en attente,
  prévu), le pourcentage « opérationnel », mis à jour en direct ; pendant l'enregistrement, la **captation en direct**
  indique les sujets déjà abordés et ceux à demander.
- Menu : tableau de bord (palier de rémunération, temps gagné), **estimer un bien** (prix en temps réel d'après les
  ventes similaires DVF), prospection (logements F/G, courriers),
  réseau (coaching 90 jours, formation ALUR, juriste, collègues), paramètres.
- Pour les clients, **sans mot de passe** : espace vendeur (documents, signature, dépôt des pièces en photo, visites,
  offres), espace notaire, page publique du bien avec assistant.

## Structure

```
visite-immo/
├── app/            code serveur (hors web) : bootstrap, store, cron.php, fields.php, ai.php, pdf.php, mandat.php,
│                   mailer.php, modules/ (une fonction métier par fichier), lib/tfpdf/
├── data/           stockage (hors web, créé automatiquement)
├── public/         racine du site : index.php (appli), api/, espace/, v/ (page du bien), ics.php, flux.php, avis.php,
│                   js/ (+ js/vues/), css/, img/, fonts/, sw.js
└── tests/          tests de bout en bout et services simulés (./tests/tout.sh)
```

Le détail de chaque module et du modèle de données est dans `PASSATION.md`.

## Installation

1. Déposer le dossier sur l'hébergement et faire pointer le domaine (ou sous-domaine) sur `public/`.
   Si l'hébergeur impose un dossier unique, `app/` et `data/` contiennent un `.htaccess` qui en interdit l'accès.
2. Droits d'écriture pour PHP sur `app/` (pour `settings.json`) et sur `data/`.
3. **Tâches automatiques** (relances, point du vendredi, échéances, alertes, briefing) : ajouter au cron de l'hébergeur
   ```
   */10 * * * * php /chemin/vers/visite-immo/app/cron.php >> /chemin/vers/visite-immo/data/cron.log 2>&1
   ```
   La ligne exacte est affichée dans Paramètres, avec la date du dernier passage.
4. Ouvrir le site : au premier lancement, on crée le **compte administrateur**.
5. **Paramètres** (administrateur) : clé Gemini et modèles, identité et mentions légales de l'agence, logo, envoi des
   e-mails (SMTP), adresse publique du site, signature électronique (intégrée, firma.dev, BoldSign ou votre API), paliers de
   rémunération, lien d'avis Google, e-mail du juriste, conservation de l'audio, **jeu de démonstration**.
   Sans clé Gemini, l'appli tourne en **mode démo** (tout est simulé, rien n'est bloqué).
6. Chaque agent renseigne son e-mail et son téléphone dans **Mon compte** et active les notifications sur son téléphone
   (écran Aujourd'hui).
7. Sur le téléphone : ouvrir le site puis « Ajouter à l'écran d'accueil ».

### Prérequis

- **HTTPS obligatoire** : sans lui, le navigateur refuse l'accès au micro.
- PHP 8.2+ avec les extensions `curl`, `fileinfo`, `mbstring`, `gd` (photos, visuels), `openssl` (notifications) ;
  `exif` et `calendar` recommandées (photos de téléphone redressées, jours fériés de Pâques).
- `upload_max_filesize` et `post_max_size` à **10M** minimum (un morceau de 3 min fait environ 1 Mo).
- `max_execution_time` à 300 s si possible (la génération prend 20 à 60 s).

Test en local : `php -S localhost:8000 -t public` puis http://localhost:8000 (le micro est autorisé sur localhost).
Tests automatisés : `./tests/tout.sh` (Playwright, Chromium ; services publics, SMTP et notifications simulés).

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
décret 72-678, Code de la consommation).

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
- Téléchargement PDF, envoi au vendeur, **signature électronique** : au doigt sur le téléphone de l'agent, ou par
  lien personnel avec code envoyé par e-mail ; numéro de registre attribué avant la signature, certificat de preuve
  en dernière page, exemplaire signé envoyé à chacun. Ou par un service : **firma.dev** (recommandé) ou BoldSign
  (comme le projet Qualiopi), champs de signature placés dans les cadres du mandat, retour par webhook signé.

Le texte reprend le gabarit de mandat de la plateforme Synapse.immo : point de départ conforme, à faire relire, puis à
remplacer par le modèle du réseau (`app/mandat.php`).

## Rendu de l'annonce sur Leboncoin (simulation)

Onglet Annonce → **👁 Rendu Leboncoin** : l'annonce telle qu'elle apparaîtrait sur Leboncoin, sur un téléphone ou sur
un ordinateur (bouton de bascule), avec les vraies photos du dossier et les mentions légales (prix honoraires inclus
et à la charge de qui, prix hors honoraires, DPE/GES, dépenses d'énergie, « logement à consommation énergétique
excessive » pour F/G, copropriété, Géorisques). En dessous : **« Prête à diffuser ? »**, le contrôle de conformité
case par case, et les boutons pour copier le titre et le texte avec ses mentions. C'est une **simulation interne** :
rien n'est publié. La diffusion réelle passe par un compte pro Leboncoin ou un multidiffuseur (vrai logiciel).

## Documents PDF et e-mails

Onglet **Documents** d'un dossier : chaque document avec **📄 PDF** et **✉️ Envoyer**. Fiche du bien, annonce,
compte rendu de visite, rapport interne, dossier complet, mandat, avis de valeur, dossier technique, croquis de plan,
publications réseaux sociaux ; puis au fil de la vente : bons de visite, point de la semaine, offres, fiche notaire,
fiches de vigilance, facture et note de commission. Tous à la charte Synapse.

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
