# Le film iOiO

Chaîne de fabrication du film de présentation, **bande-son comprise**. Un
Chromium piloté par Playwright pour les textes, `ffmpeg` pour l'image, du
Python pour la musique et le montage. Aucun service tiers, aucun montage à la
main, aucune licence à gérer : la musique est synthétisée ici.

`musique.py` et `analyse_musique.py` demandent **numpy** et **scipy** ; les
quatre autres scripts n'ont aucune dépendance.

Résultat de référence : **1920 × 1080, 60 i/s, 1 min 48 s, 56 plans.**

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
| `musique.py` | **Compose la bande-son** à 120 BPM pile. La grille n'est pas mesurée, elle est posée — voir ci-dessous. |
| `analyse_musique.py` | Pour une musique qu'on n'a pas composée : retrouve sa grille métrique. Inutile si on passe par `musique.py`. |
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

Trois tentatives ont été nécessaires, et la leçon vaut d'être écrite.

**Première version — coupes sur des attaques détectées une par une.** Les
attaques d'un morceau orchestral sont irrégulières : 1,26 s, puis 1,48 s, puis
1,36 s. Chacune tombe sur un son, aucune ne tombe sur la pulsation. Mesuré
après coup : 38 coupes sur 51 à côté du temps, jusqu'à 135 ms d'écart.

**Deuxième version — grille mesurée.** `analyse_musique.py` cherche une
période et une phase plutôt que des attaques. L'écart tombe à 4 ms médian.
Mieux, mais il reste l'arrondi à l'image (8 ms au pire à 60 i/s), la dérive de
la mesure, et surtout : le montage s'adapte à la musique, jamais l'inverse.

**Troisième version — grille posée.** On compose la bande-son. À **120 BPM**,
un temps dure exactement 0,5 s, soit **exactement 30 images à 60 i/s**. Un plan
qui vaut un nombre entier de temps tombe sur une frontière d'image exacte :
l'erreur d'arrondi n'est pas petite, elle est **nulle**. Et l'arrangement est
écrit pour le montage — les dix sections de `musique.py` coïncident une à une
avec les dix actes de `storyboard.py`.

C'est la version en service. Ce qui suit décrit l'outil de mesure, utile
seulement si l'on impose une musique qu'on n'a pas composée.

`analyse_musique.py` ne cherche pas des attaques mais **une période et
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

### L'unité du conducteur

`storyboard.py` n'exprime aucune durée en secondes : chaque plan vaut un nombre
entier de **temps** (0,5 s, 30 images).

| Unité | Durée | Images | Usage |
|---|---|---|---|
| 1 temps | 0,5 s | 30 | la fin de la montée, une coupe par temps |
| 2 temps | 1,0 s | 60 | l'apogée |
| 4 temps | 2,0 s | 120 | une mesure — les plans des deux lieux |
| 8 temps | 4,0 s | 240 | l'ouverture et les cartons de chapitre |

### Composer pour le montage

`musique.py` décrit l'arrangement par sections, en numéros de mesure. Chaque
section porte un **niveau** : c'est lui qui fait l'arc du morceau, et son
absence est ce qui rendait la première bande-son plate. Le profil obtenu va de
−31 dB sur l'intro à −14 dB sur l'apogée, avec une chute à −37 dB sur la
rupture — c'est ce creux qui porte le carton « Tout est compris. ».

Les instruments sont synthétisés : grosse caisse à enveloppe de hauteur, caisse
claire et clap, charley, basse filtrée, arpège, nappe de scies désaccordées,
cuivres, montée, impact. Le mixage applique une compression déclenchée par la
grosse caisse — la respiration « corporate » — une réverbération par
convolution, un élargissement stéréo, et une bascule tonale finale.

## Refaire le film

```sh
cd tools/video
python3 musique.py build/musique.wav   # la bande-son      (~1 min)
python3 gen_html.py && node shoot.js   # les cartons
MUSIQUE=build/musique.wav python3 build.py     # les plans  (~25 min)
MUSIQUE=build/musique.wav python3 assemble.py  # le master  (~10 min)
```

Pour changer le rythme du film, on change les durées dans `PLAN`
(`storyboard.py`) ; pour changer le morceau, on change `SECTIONS`
(`musique.py`). Les deux listes se lisent en parallèle.

## Caler un film sur une musique imposée

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
  une bande-son d'essai elle proposait 1,363 s — exactement cinq croches. Sans
  l'étape de redescente, tout le film serait monté cinq fois trop lentement.
- **Un tempo rond vaut mieux qu'un tempo mesuré.** 120 BPM à 60 i/s donne un
  temps de 30 images exactement ; tout autre tempo laisse un reste.
- **Un arrangement sans gain par section sort plat.** Mesuré sur la première
  bande-son composée : ouverture, apogée et final tous à −11 dB. Le film
  n'avait aucune montée alors que le montage, lui, s'accélérait.
- **Un volet de la couleur du carton se lit comme un texte tronqué.** Le volet
  jaune sur le carton jaune donnait « BONNE HUMEU ». Il faut contraster.

## Droits

Les photos et les logos appartiennent au iOiO. **La bande-son n'est pas
versionnée** : elle est fournie par le client au moment de la fabrication, et
sa licence de diffusion relève de lui.
