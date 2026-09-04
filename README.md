# Angeot — site officiel de la commune

Site de la **mairie d’Angeot**, dans le Territoire de Belfort :
démarches administratives, équipe municipale, salle Camille, bois et forêts,
associations, actualités, agenda et coordonnées du secrétariat.

PHP 8.1+, **aucune dépendance** : ni Composer, ni base de données, ni build
front. Le contenu vit dans des fichiers JSON qu'un back-office complet écrit.
Conçu pour l'hébergement mutualisé français.

---

## Les documents

- **[CLAUDE.md](CLAUDE.md)** — les consignes de travail sur ce dépôt. Chargé
  automatiquement par Claude Code ; à lire en premier de toute façon.
- **[SITE.md](SITE.md)** — ce site-ci : arborescence, charte, contenu repris de
  l'ancien site, et **ce qui reste à renseigner par la mairie**.
- **[KIT.md](KIT.md)** — le socle technique, ses conventions et les pièges
  rencontrés pendant son développement.
- **[NOUVEAU-SITE.md](NOUVEAU-SITE.md)** — la recette pour produire un autre
  site sur ce modèle : le système de design en entier, l'itinéraire, les
  auditeurs et les pièges déjà payés.
- **[DEPLOIEMENT.md](DEPLOIEMENT.md)** — mise en ligne, droits, FTP, mises à
  jour par git, dépannage.

---

## Démarrer en trente secondes

```bash
php -S localhost:8080 -t public public/index.php
```

Puis `http://localhost:8080` pour le site, `/admin` pour créer le compte
administrateur — il n'y a aucun identifiant par défaut.

Le contenu de `data-modele/` se recopie tout seul dans `data/` à la première
visite : le site s'affiche complet, sans aucune manipulation préalable.

---

## Ce que contient le site

**Trente-trois adresses publiques**, groupées en cinq rubriques : la mairie
(équipe municipale, commissions et comités, comptes-rendus, délibérations et
arrêtés, budget, publications, urbanisme), les démarches (douze fiches,
démarches en ligne, services de l'État, CCAS), le village (histoire et
patrimoine, salle Camille, bois et forêts, associations, album photos), les
actualités (actualités, agenda, info à la une) et le quotidien (déchets, vie
scolaire, intercommunalité, liens utiles, numéros utiles) — plus le contact et
les pages de service.

S'y ajoutent **soixante-dix-neuf documents** repris de l'ancien site : les
comptes-rendus du conseil de 2020 à 2026, et les documents budgétaires de 2017
à 2025.

**Le contenu vivant est rappelé sur chaque page** par le bandeau « En ce
moment » : la dernière actualité, le prochain rendez-vous, le dernier Flash
Info. C'est ce que voit un administré arrivé par un moteur de recherche sur une
fiche de démarche.

**Trente-huit photos** du village, reprises des albums de la mairie et de
Wikimedia Commons, recadrées, recompressées et pourvues d'un texte de
remplacement qui décrit la scène.

**Des redirections 301** depuis les adresses de l'ancien site : les dix-huit
identifiants numériques de ses pages, ses huit adresses `.php` de racine et une
trentaine d'adresses courtes. Aucune ne tombe sur une erreur — un identifiant
inconnu arrive sur le plan du site.

---

## Les cinq auditeurs

La qualité de ce site ne vient pas du goût mais de la mesure. Cinq scripts,
qui sortent en code 1 s'ils trouvent quelque chose :

```bash
php -S 127.0.0.1:8081 -t public public/index.php &

python3 outils/verifs/contraste.py       # contraste réel de chaque texte
python3 outils/verifs/mise-en-page.py    # débordement, cibles, titres, alt
python3 outils/verifs/traceurs.py        # aucune requête tierce sans accord
python3 outils/verifs/bandeau.py         # chaque photo du diaporama, une à une
python3 outils/verifs/entete.py          # l'en-tête aux bornes du réglage du logo
```

Zéro écart, zéro souci, zéro hôte tiers : c'est la définition de « fini » ici.

---

## Ce que ce dépôt ne contient pas

Volontairement : le compte d'administration (`data/admin/`), le contenu vivant
du site une fois édité (`data/*.json`), et les clés d'API — Gemini pour
l'assistant, Google pour les avis, DeepL pour la traduction. Le site fonctionne
sans, chaque fonction concernée se retirant d'elle-même tant qu'elle n'est pas
configurée.
