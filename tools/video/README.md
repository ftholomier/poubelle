# Le film iOiO

Chaîne de fabrication du film de présentation, calé image par image sur une
bande-son. Tout est natif : du Python sans dépendance, un Chromium piloté par
Playwright pour les textes, `ffmpeg` pour l'image et le son. Aucun service
tiers, aucun montage à la main.

Résultat de référence : **1920 × 1080, 30 i/s, 1 min 55 s, 52 plans.**

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

Le rythme se resserre acte après acte : c'est ce qui fait monter le film.

| Temps | Acte | Durée des plans | Contenu |
|---|---|---|---|
| 0 – 19,8 s | Ouverture | 2,6 s | Les yoyos de la marque, le titre, cinq plans — **une ligne de texte par plan** |
| 19,8 – 45,4 s | Carnot | 2,45 s | Carton de lieu (volet jaune) puis neuf plans |
| 45,4 – 69,1 s | Granvelle | 2,45 s | Carton de lieu (volet vert) puis huit plans |
| 69,1 – 91,4 s | Apogée | **1,4 s** | Six cartons-mots entrecoupés de rafales de deux photos |
| 91,4 – 93,1 s | Rupture | 1,7 s | « Tout est compris. » dans le trou de la musique |
| 93,1 – 104,4 s | Les chiffres | 2,25 s | 2 adresses · 21 postes · 150 € HT · 0 € de frais cachés |
| 104,4 – 110,6 s | Les trois coups | 2 s | Un carton par frappe espacée |
| 110,6 – 115,1 s | Fin | 4,5 s | Logo, site, téléphone, fondu au noir |

### Ce qui rend la coupe audible

Une coupe posée sur un temps ne suffit pas : elle se voit, elle ne s'entend
pas. Trois procédés s'y ajoutent, déclarés plan par plan dans le conducteur.

- **Mouvement amorti** (`ken`). Le Ken Burns ne va pas à vitesse constante :
  il part vite sur la coupe et ralentit (`1-exp(-2.8·p)`). L'œil lit le
  démarrage comme une frappe. `kick` va plus loin : le plan arrive déjà zoomé
  et se détend d'un coup sur le premier dixième de seconde.
- **Éclair** (`flash`). Trois images surexposées qui s'éteignent, sur les
  accents forts.
- **Volet** (`wipe`). Un aplat jaune ou vert couvre la première image puis
  sort du cadre en six images. Réservé aux changements d'acte.

Le texte monte d'autant plus vite que le plan est court : le fondu de
l'incrustation est calculé sur la durée du plan, pas en valeur fixe.

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
