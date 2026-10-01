# Le film iOiO

Chaîne de fabrication du film de présentation, calé image par image sur une
bande-son. Un Chromium piloté par Playwright pour les textes, `ffmpeg` pour
l'image et le son, du Python pour le reste. Aucun service tiers, aucun montage
à la main.

Seul `analyse_musique.py` demande **numpy** ; les quatre autres scripts
n'ont aucune dépendance.

Résultat de référence : **1920 × 1080, 60 i/s, 1 min 55 s, 64 plans.**

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
| `analyse_musique.py` | Trouve la **grille métrique** du morceau : période et phase. C'est la pièce maîtresse — voir ci-dessous. |
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

## Le point critique : la grille

Une coupe posée sur une « attaque » détectée une par une **flotte** autour du
temps. Les attaques d'un morceau orchestral sont irrégulières : une coupe à
1,26 s, la suivante à 1,48 s, la troisième à 1,36 s. Chacune tombe sur un son,
aucune ne tombe sur la pulsation. À l'œil, ça ne colle pas.

`analyse_musique.py` ne cherche donc pas des attaques mais **une période et
une phase** :

1. Flux spectral à compression logarithmique, débarrassé de sa tendance lente.
2. Autocorrélation → les périodes candidates.
3. Celle dont le contraste avec sa propre anti-phase est le plus fort.
4. **Redescente à la pulsation fondamentale** : l'autocorrélation préfère les
   périodes longues (grille clairsemée, plus facile à faire coïncider), donc on
   garde le plus petit diviseur dont le peigne tient encore.
5. Balayage fin : une erreur de 1 ms sur la période fait dériver d'une
   demi-seconde au bout de deux minutes.

Pour la bande-son de référence : **U = 0,272736 s**, soit 219,99 BPM à la
croche et **110 BPM au temps**, phase 0,0432 s, contraste ×2,30, dérive
inférieure à 4 ms sur les 115 secondes.

Ensuite, `storyboard.py` n'exprime **aucune durée en secondes** : chaque plan
vaut un nombre de croches. Une coupe ne *peut pas* tomber à côté du temps.

| Unité | Durée | Usage |
|---|---|---|
| 2 croches | 0,545 s | un temps — la rafale finale de l'apogée |
| 4 croches | 1,091 s | l'apogée |
| 8 croches | 2,182 s | une mesure — l'ouverture et les deux lieux |
| 12 croches | 3,273 s | les cartons de chapitre |

Le film sort en **60 i/s** : l'arrondi d'une coupe à l'image la plus proche
tombe alors à 4 ms en moyenne, 8 ms au pire, bien en dessous du seuil où l'œil
décroche. En 30 i/s on doublait ces valeurs.

## Caler un film sur une autre musique

1. `python3 analyse_musique.py la-musique.mp3 build/grille.json`
2. Vérifier le rapport : contraste supérieur à 2, dérive sous 10 ms par
   tranche. Sinon le morceau n'a pas de grille stable et il faut monter à
   l'oreille.
3. Reporter `U` et `PHASE` en tête de `storyboard.py`, ajuster `DUREE`.
4. Réécrire `PLAN` : une ligne par plan, la durée en croches. Le module
   calcule les instants, et le contrôle vérifie que le total retombe pile sur
   la durée du morceau.
5. Relancer les étapes 3 à 5.

## Le montage de référence

Le rythme se resserre acte après acte : c'est ce qui fait monter le film.

| Temps | Acte | Durée des plans | Contenu |
|---|---|---|---|
| 0 – 19,7 s | Ouverture | 8 croches | Les yoyos, le titre, six plans — **une ligne de texte par plan** |
| 19,7 – 44,8 s | Carnot | 8 croches | Carton de lieu (volet jaune) puis dix plans |
| 44,8 – 67,7 s | Granvelle | 8 croches | Carton de lieu (volet vert) puis neuf plans |
| 67,7 – 87,3 s | Apogée | 4 croches | Six cartons-mots, chacun suivi de deux photos |
| 87,3 – 90,6 s | Rafale | **2 croches** | Six photos, **une coupe par temps** |
| 90,6 – 93,3 s | Rupture | 3 + 7 | Un dernier mot, puis « Tout est compris. » dans le trou |
| 93,3 – 104,3 s | Les chiffres | 8 croches | 2 adresses · 21 postes · 150 € HT · 0 € de frais cachés |
| 104,3 – 110,8 s | Les trois coups | 8 croches | Un carton par frappe isolée (croches 382, 390, 398) |
| 110,8 – 115,1 s | Fin | 16 croches | Logo, site, téléphone, fondu au noir |

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
- **Monter sur des attaques détectées une par une ne suffit pas.** Elles sont
  irrégulières : sur une première version, 38 coupes sur 51 tombaient à côté
  du temps, jusqu'à 135 ms d'écart. Il faut une grille régulière.
- **L'autocorrélation renvoie volontiers un multiple de la pulsation.** Sur
  cette bande-son elle proposait 1,363 s — exactement cinq croches. Sans
  l'étape de redescente, tout le film serait monté cinq fois trop lentement.

## Droits

Les photos et les logos appartiennent au iOiO. **La bande-son n'est pas
versionnée** : elle est fournie par le client au moment de la fabrication, et
sa licence de diffusion relève de lui.
