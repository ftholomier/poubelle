# Formation SCFR — « Anticipez 2026 : cumul emploi-retraite & facturation électronique »

Supports d'une formation de **2 jours / 14 heures** destinée aux dirigeants de TPE-PME et aux
micro-entrepreneurs, produite pour l'**Agence SCFR** (organisme de formation certifié Qualiopi).

Données arrêtées au **2 septembre 2026**.

## Livrables

| Fichier | Destinataire | Contenu |
|---|---|---|
| `dist/SCFR-Formation-2026-Support-Formateur.pptx` | Projeté en salle | 93 diapositives 16:9, avec **notes du formateur** sur chaque slide (timing, points d'attention, réponses aux objections) |
| `dist/SCFR-Formation-2026-Livret-Stagiaire.pdf` | Remis à chaque stagiaire | 78 pages A4 : contenu détaillé rédigé, exemples chiffrés, checklists, **annexes Qualiopi** |

### Découpage pédagogique

- **Jour 1 — Cumul emploi-retraite** (7 h) : paysage retraite 2026 et suspension de la réforme
  (LFSS 2026), cumul intégral, cumul plafonné, seconde pension, dispositifs voisins, fiscalité,
  et la réforme du 1<sup>er</sup> janvier 2027 (article 102, loi n° 2025-1403).
- **Jour 2 — Facturation électronique** (7 h) : cadre et calendrier, périmètre e-invoicing /
  e-reporting / hors champ, écosystème des plateformes agréées, formats, mentions obligatoires
  et cycle de vie, sanctions et archivage, plan de mise en conformité.

Six exemples chiffrés détaillés et deux ateliers de tri de flux, sur des profils de dirigeants
et de micro-entrepreneurs.

### Annexes Qualiopi incluses dans le livret

`A` test de positionnement · `B` programme détaillé et modalités (accessibilité PSH, réclamations)
· `C` plan d'action personnalisé · `D` évaluation des acquis (20 questions) et corrigé commenté
· `E` questionnaire de satisfaction à chaud · `F` feuille d'émargement et modèle d'attestation
· `G` glossaire · `H` sources et références.

## Arborescence

```
formation-scfr-2026/
├── content/
│   └── sources-et-donnees-2026.md   Référentiel de données vérifiées + sources
├── build/
│   ├── deck-lib.js                  Composants de slides (charte SCFR, moteur de mise en page)
│   ├── make-deck.js                 Contenu du deck et génération
│   ├── make-livret.py               Rendu PDF du livret
│   └── livret/
│       ├── livret.html              Contenu du livret
│       └── style.css                Feuille de style d'impression A4
└── dist/                            Livrables générés
```

## Régénérer les livrables

```bash
npm install                     # pptxgenjs
pip install weasyprint          # rendu PDF

node build/make-deck.js dist/SCFR-Formation-2026-Support-Formateur.pptx
python3 build/make-livret.py
```

## Charte graphique

Extraite de la plaquette commerciale SCFR :
orange `#E07B22`, violet `#8A79B0`, anthracite `#322F2E`, crème `#FDFCFB`,
orange foncé `#B45E10`, gris `#6B6663`.

Le motif visuel repris sur les deux supports — disques superposés — reprend le logo SCFR.

## Points d'attention pour une réutilisation

- **Les montants sont datés.** PASS, SMIC, valeur du point et plafonds sont revalorisés chaque
  1<sup>er</sup> janvier. Le référentiel `content/sources-et-donnees-2026.md` centralise ces valeurs.
- **Les décrets d'application de la réforme du cumul au 1<sup>er</sup> janvier 2027 n'étaient pas
  tous publiés** au 2 septembre 2026. Le seuil d'environ 7 000 €/an est présenté comme provisoire
  dans les deux supports, et le SAV 12 mois est mis en avant sur ce point.
- **La plaquette d'origine décrivait une formation d'1 jour / 8 h.** Ces supports couvrent 2 jours /
  14 h : le tarif et les mentions de durée de la plaquette commerciale sont à mettre à jour en
  conséquence.
