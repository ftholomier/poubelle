# Le iOiO — socle de site PHP natif, export complet

Vous avez entre les mains **l'intégralité du code** d'un site vitrine +
catalogue + back-office, en PHP natif : **aucune base de données, aucun
framework, aucune dépendance** (ni Composer, ni npm, ni CDN, ni police
distante). 14 000 lignes écrites à la main, 29 classes, 7 pages publiques,
8 écrans d'administration.

Le site est en ligne de production pour Le iOiO, coworking à Besançon.
Ce paquet sert de **socle à reproduire pour un autre site**.

---

## Par où commencer

| Vous voulez… | Lisez |
| --- | --- |
| lancer l'IA sur le-signal.com | **`docs/PROMPT-LE-SIGNAL.md`** — le texte à copier-coller |
| savoir ce qu'il y a sur le-signal.com | **`docs/le-signal-releve.md`** — relevé complet, déjà fait |
| le catalogue du Signal, prêt à importer | `docs/le-signal-catalogue.json` — 13 bureaux au bon format |
| comprendre ce que fait le socle | **`README.md`** — 14 sections, tout y est |
| réutiliser le socle ailleurs | **`docs/REUTILISATION.md`** — générique vs métier, marche à suivre, pièges |
| la direction visuelle d'origine | `docs/HANDOFF-CLAUDE-CODE.md` |

---

## Essayer en trois commandes

```bash
php -S 127.0.0.1:8080 -t public
```

Puis ouvrez `http://127.0.0.1:8080`. Le site fonctionne immédiatement, avec son
contenu réel et **sans aucune clé API** : l'assistant répond depuis l'index
local, les avis s'affichent depuis le fichier, les formulaires s'enregistrent.

Pour le back-office, ouvrez `http://127.0.0.1:8080/admin/` : un écran
d'installation vous fait créer le premier compte.

Exigences : PHP 8.1 ou plus, extensions `json`, `mbstring`, `gd` (ou `imagick`)
pour les photos. `curl` et `intl` recommandés, avec repli si absents.

---

## Ce que contient le paquet

```
README.md                      la documentation complète du site
LISEZMOI-EXPORT.md             ce fichier
docs/REUTILISATION.md          guide de transposition vers un autre site
docs/PROMPT-LE-SIGNAL.md       le prompt prêt à coller
docs/le-signal-releve.md       relevé du site le-signal.com (1er octobre 2026)
docs/le-signal-catalogue.json  les 13 bureaux du Signal, au format du socle
docs/HANDOFF-CLAUDE-CODE.md    la direction visuelle d'origine

app/                           29 classes + vues + traductions (hors racine web)
public/                        le seul dossier exposé : index, api, admin, assets, 78 photos
content/                       le contenu en JSON, tel que le back-office l'écrit
storage/                       dossiers d'exécution, vides (logs, verrous, index)
bin/cron.php                   sauvegarde, réindexation, avis, ménage
.env.example                   les clés configurables (toutes facultatives)
```

**Ce qui n'est pas dans le paquet**, volontairement : aucune clé API, aucun mot
de passe, aucun compte, aucune demande de visiteur, aucun journal. Le dossier
est propre et peut être transmis tel quel.

---

## Ce que le socle apporte, concrètement

**Côté visiteur** : pages multilingues à URL propres, catalogue filtrable,
fiches détaillées avec galerie, album photo et visionneuse, carrousel de
témoignages, cartes Google intégrées sans clé, assistant IA qui lit les données
réelles du catalogue, bandeau cookies par catégories, formulaires protégés.

**Côté administrateur** : édition de tous les textes champ par champ en deux
langues, brouillon puis publication, **30 versions conservées et restaurables**,
verrou d'édition, catalogue complet, photothèque avec conversion WebP
automatique, demandes entrantes avec statuts et quarantaine anti-spam,
réglages, **test réel de chaque clé API** depuis l'écran, recherche de la fiche
Google par adresse, choix du modèle Gemini dans une liste chargée depuis Google.

**Côté exploitation** : écriture atomique et versionnée, repli écrit à l'avance
pour chaque service externe, anti-spam natif (jeton signé, pixel de présence,
leurres, note de suspicion, quarantaine), sauvegardes par cron, aucune
dépendance à mettre à jour.

---

## La philosophie, en une phrase

Rien ne doit jamais tomber : sans clé API, sans réseau, sans JavaScript, sans
base de données, le site reste complet, rapide et vendeur. C'est ce qui lui
donne une durée de vie de dix ans sur un hébergement mutualisé à cinq euros.
