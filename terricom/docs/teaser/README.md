# Teaser vidéo terricom

`terricom-teaser.mp4` : 1 min 53, 1920 × 1080, 30 images par seconde, calé sur la musique `musique.mp3`
(« The Mountain », trailer épique, 110 BPM : une mesure toutes les 2,184 s).

## Déroulé

| Temps (s) | Musique                | Image                                                                                                                                                                  |
| --------- | ---------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 0 – 19    | intro, premières notes | Naissance de la marque : le point ambre, la grille, le carré, les arrondis, la rotation à 45° (un repère de carte), le « t », les couleurs                             |
| 19 – 30   | premier impact, montée | Plan topographique réel du Haut-Doubs : le contour du territoire se trace, 1 971 repères tombent ; « terri·toire », « com·merce », « com·munauté », « com·munication » |
| 30 – 35   | accélération           | Éléments de la charte en coupes rapides : couleurs, typographies, familles d’activité, badges, recherche, icône                                                        |
| 35 – 41   | grand impact (36,45)   | Révélation : « terri » et « com » se rejoignent, le point ambre tombe : **terricom.** puis la signature                                                                |
| 41 – 54   | reprise                | Pour les habitants : portail, recherche « fromagerie », carte, zoom sur Métabief, page de Métabief                                                                     |
| 54 – 56   | silence                | Pour la collectivité : le curseur va vers « Préparer » ; le clic tombe sur la reprise                                                                                  |
| 56 – 91   | climax                 | Assistant de campagne, SIRENE, chiffres, espace commerçant, téléphones, tableau de bord, statistiques, mur d’écrans                                                    |
| 93 – 113  | coda                   | Photos du territoire, carte lumineuse, « Le territoire, en vitrine. », logo, terricom.fr, crédits                                                                      |

## Ce qui est réel

- **Captures** : l’application tourne vraiment (compilation de production, mode démonstration, base du Haut-Doubs).
  Le temps de la page est piloté image par image (horloge Playwright, animations CSS pas à pas), un curseur dessiné
  suit des trajectoires lissées et chaque clic est un vrai clic. Les adresses affichées sont celles de la production.
- **Données** : 32 communes et 1 971 établissements issus de SIRENE ; les seuls contenus inventés sont ceux déjà
  signalés dans la démonstration (« … de démonstration », « Exemple de démonstration »).
- **Carte** : fond de carte rendu pour l’occasion (MapLibre) depuis les données ouvertes Overture Maps (issues
  d’OpenStreetMap : routes, eau, forêts, bâtiments, pistes de ski) et le relief Terrarium ; servi à l’application
  pendant les captures (`MAP_TILE_URL`), comme le seraient les tuiles OpenStreetMap en production.
- **Photos** : photos libres de Wikimedia Commons. Pendant les captures, elles remplacent les photos Unsplash du
  jeu de démonstration (visibles en production, bloquées dans cet environnement) et illustrent les pages de
  Métabief, Jougne et Malbuisson (ajout fait uniquement pour la capture, puis `npm run db:reset`).

## Crédits

- Lac de Saint-Point depuis le belvédère de Montperreux : Wikipedro, CC BY-SA 4.0
- Métabief, panorama depuis Saint-Antoine : René Hourdry, CC0
- Jougne, église ; Jougne, place : René Hourdry, CC BY-SA 4.0
- Malbuisson au bord du lac de Saint-Point : Boutrol, CC BY-SA 3.0
- Vue depuis le Mont d’Or : Zairon, CC BY-SA 4.0
- Cave d’affinage de Comté : Arnaud 25, CC BY-SA 4.0
- Vacherin Mont-d’Or : Guy Waterval, CC BY-SA 4.0
- Pain au lait : DocteurCosmos, CC BY-SA 3.0
- Métabief, roue à aubes sur le Bief Rouge : JGS25, CC BY 4.0
- Fond de carte : © contributeurs OpenStreetMap, Overture Maps Foundation (ODbL) ; relief : Terrarium (AWS Open Data)
- Contours des communes : france-geojson (d’après l’IGN)

## Refaire la vidéo

Tout est produit dans `.teaser/` (ignoré par git, plusieurs Go). Réseau nécessaire : S3 d’AWS (Overture, relief),
raw.githubusercontent.com (contours), commons.wikimedia.org (photos).

```bash
# outils
pip install --target .teaser/py pyarrow shapely pillow
npm install --prefix .teaser maplibre-gl@5 maplibre-contour
export PYTHONPATH=.teaser/py

# données, fond de carte sombre, photos
python3 scripts/teaser/donnees.py overture relief communes geo pins
node scripts/teaser/sombre.mjs
python3 scripts/teaser/photos.py
SET=wide node scripts/teaser/tuiles.mjs --zone '[{"z":12,"bbox":[5.85,46.45,6.75,47.0]},{"z":11,"bbox":[5.8,46.4,6.8,47.05]},{"z":10,"bbox":[5.6,46.2,7.0,47.2]}]'

# serveur de production pointé sur les tuiles locales, puis captures
npm run build   # puis copier .next/static et public dans .next/standalone
MAP_TILE_URL='https://tuiles.terricom.test/{z}/{x}/{y}.jpg' MAP_TILE_ATTRIBUTION='© OpenStreetMap · Overture Maps' \
  PORT=3100 node .next/standalone/server.js &
scripts/teaser/preparer.sh
node scripts/teaser/scenes.mjs
# tuiles relevées pendant les captures, puis nouvelle série de captures
SET=core node scripts/teaser/tuiles.mjs --liste .teaser/manquantes.txt
SET=wide node scripts/teaser/tuiles.mjs --liste .teaser/manquantes.txt
scripts/teaser/preparer.sh && node scripts/teaser/scenes.mjs

# aperçus, puis vidéo (ffmpeg avec libx264)
node scripts/teaser/rendu.mjs --apercu 20 37 56 84
FFMPEG=/chemin/ffmpeg node scripts/teaser/rendu.mjs
npm run db:reset
```

La composition (séquences, repères musicaux, textes) est dans `teaser.html` ; les scénarios filmés dans
`scripts/teaser/scenes.mjs`.

## Film de présentation aux élus

`terricom-elus.mp4` : 1 min 54, 1920 × 1080, même musique que le teaser. Il s’adresse aux élus des communautés
de communes et des communes : l’esprit de la solution, ce que chacun y gagne, les fonctionnalités. Pas de logo au
début : la marque et l’adresse terricom.fr n’apparaissent qu’à la fin. **Aucun tarif.**

| Temps (s) | Image                                                                                                          |
| --------- | -------------------------------------------------------------------------------------------------------------- |
| 0 – 19    | Photos de toute la France : « Dans chaque territoire, des commerçants, des artisans, des producteurs… »        |
| 19 – 36   | L’enjeu pour la CC : le contour officiel du territoire se trace, 32 communes, 1 971 entreprises SIRENE         |
| 36 – 45   | Grand impact : « Une plateforme d’animation économique du territoire », habitants, collectivité, entreprises   |
| 45 – 54   | Pour les habitants : recherche, carte, mobile                                                                  |
| 54 – 91   | La collectivité : tableau de bord, SIRENE, campagne en une phrase, lettre ; chaque commune ; chaque entreprise |
| 91 – 104  | Les résultats à présenter au conseil, ce que chacun y gagne, « Le territoire, en vitrine. »                    |
| 104 – fin | Logo, terricom.fr, crédits                                                                                     |

Les écrans sont les boucles réelles de l’application du site commercial (`site-terricom/public/assets/video/app`),
lues image par image ; la carte est dessinée à partir des contours officiels (geo.api.gouv.fr) et des
établissements de la base de démonstration. Photos : `site-terricom/sources` (crédits dans
`site-terricom/outils/credits.json`).

```bash
npm run db:reset                                   # base de démonstration réelle (repères)
FFMPEG=/chemin/ffmpeg scripts/teaser/elus-donnees.sh
node scripts/teaser/rendu-elus.mjs --apercu 10 30 50   # aperçus
FFMPEG=/chemin/ffmpeg node scripts/teaser/rendu-elus.mjs
```
