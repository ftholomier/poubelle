# Le film iOiO

Chaîne de fabrication du film de présentation, calé image par image sur une
bande-son. Tout est natif : du Python sans dépendance, un Chromium piloté par
Playwright pour les textes, `ffmpeg` pour l'image et le son. Aucun service
tiers, aucun montage à la main.

Résultat de référence : **1920 × 1080, 30 i/s, 1 min 55 s, 34 plans.**

## Pourquoi cette chaîne

Les cartons ne sont pas dessinés dans `ffmpeg` : ils sont rendus par le
navigateur à partir des **vraies polices du site** (les `woff2` de
`public/assets/fonts/`, intégrées en base64) et des **mêmes jetons de couleur**
que la feuille de style. Le film ne peut donc pas dériver de la charte, et une
retouche de texte se fait dans du HTML/CSS, pas dans une ligne de commande.

Les photos viennent de `public/media/` — celles-là mêmes qui sont publiées sur
le site. Aucune image d'illustration achetée ailleurs.

## Les cinq étapes

| Fichier | Rôle |
|---|---|
| `analyse_musique.py` | Repère les attaques de la bande-son (énergie par trame, dérivée positive, pics au-dessus de la moyenne glissante). Sert à choisir où couper. |
| `storyboard.py` | Le conducteur : 34 plans, bornes en secondes, photo ou carton, sens du mouvement, incrustation associée. **C'est le seul fichier à toucher pour remonter le film.** |
| `gen_html.py` | Fabrique une page HTML contenant tous les cartons et toutes les incrustations, polices et logos intégrés. |
| `shoot.js` | Playwright photographie chaque bloc en PNG 1920 × 1080. Les incrustations sont capturées en fond transparent. |
| `build.py` | Un clip muet par plan : recadrage, Ken Burns, étalonnage, vignettage, incrustation en fondu. |
| `assemble.py` | Concatène les 34 clips, pose la musique, ajoute un grain léger, encode le master. |

## Fabriquer le film

```sh
cd tools/video
export MUSIQUE=/chemin/vers/la-bande-son.mp3
python3 gen_html.py      # les cartons en HTML
node    shoot.js         # les cartons en PNG
python3 build.py         # les 34 clips muets      (~6 min)
python3 assemble.py      # le master sonorisé      (~5 min)
# -> build/ioio-le-film.mp4
```

Tout atterrit dans `tools/video/build/`, qui n'est pas versionné.

### Variables d'environnement

| Variable | Défaut | Usage |
|---|---|---|
| `FFMPEG` | `ffmpeg` | Chemin du binaire. Il lui faut **libx264 et aac** : celui que livre Playwright ne sait faire que du VP8 sans son. |
| `MUSIQUE` | `build/musique.mp3` | La bande-son à poser. |
| `SORTIE` | `tools/video/build` | Dossier de travail. |
| `PLAYWRIGHT_PATH` | — | Si le module n'est ni local ni global. |
| `SEULES_PHOTOS` | — | `1` pour ne refaire que les plans photo (étalonnage retouché, cartons inchangés). |
| `PLANS` | — | Identifiants séparés par des virgules (`b03,b04`) pour ne refaire que ces plans-là. |

## Caler un film sur une autre musique

1. `python3 analyse_musique.py la-musique.mp3 build/analyse.json`
2. Lire les attaques et repérer la structure : montée, apogée, **ruptures**
   (les trous entre deux attaques sont les meilleurs moments pour un carton nu).
3. Réécrire les bornes `t0`/`t1` dans `storyboard.py`. Elles doivent se suivre
   sans trou : `build.py` vérifie que le total retombe sur la durée du morceau.
4. Relancer les étapes 3 à 5.

Les bornes sont converties en **numéros d'image** (`round(t * 30)`) avant
découpe : aucune dérive ne s'accumule, et chaque coupe tombe à moins d'une
demi-image de l'attaque visée.

## Le montage de référence

| Temps | Acte | Contenu |
|---|---|---|
| 0 – 19,8 s | Ouverture | Les yoyos de la marque, le titre, trois plans larges |
| 19,8 – 45,4 s | Carnot | Carton de lieu puis cinq plans étiquetés |
| 45,4 – 69,1 s | Granvelle | Carton de lieu puis quatre plans étiquetés |
| 69,1 – 91,4 s | Apogée | Montage alterné mot/photo, une coupe toutes les ~2,4 s |
| 91,4 – 93,1 s | Rupture | « Tout est compris. » dans le trou de la musique |
| 93,1 – 104,4 s | Les chiffres | 2 adresses · 21 postes · 150 € · 0 € de frais cachés |
| 104,4 – 110,6 s | Les trois coups | Un carton par frappe espacée |
| 110,6 – 115,1 s | Fin | Logo, site, téléphone, fondu au noir |

## Pièges rencontrés

- **`drawtext` n'existe pas** dans un `ffmpeg` sans freetype. C'est une raison
  de plus de passer par le navigateur : les textes sont des PNG.
- **`omitBackground` ne suffit pas** à obtenir un PNG transparent si le `body`
  porte une couleur de fond. Il faut `background:transparent` sur le `body`.
- **`zoompan` saccade** quand on l'applique à une image à la taille de sortie.
  On agrandit d'abord à 3840 × 2160, puis on sort en 1920 × 1080.
- **Le vignettage par défaut (`PI/5`) écrase les coins.** `PI/11` suffit.
- **Un grain trop appuyé double le poids du fichier** : `alls=2` évite le
  banding sur les aplats encre sans gonfler le débit.
- **Le pic audio sortait à +0,2 dBFS.** `volume=-1.5dB` avant encodage laisse
  la marge qu'attendent les plateformes.

## Droits

Les photos et les logos appartiennent au iOiO. **La bande-son n'est pas
versionnée** : elle est fournie par le client au moment de la fabrication, et
sa licence de diffusion relève de lui.
