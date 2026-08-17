# Logo Étang Fourchu — Nature & Loisirs

Vectorisation du logo fourni. **Fond transparent**, textes convertis en courbes
(aucune police requise à l'affichage), tracés 100 % vectoriels.

## Fichiers

| Fichier | Usage |
|---|---|
| `logo-etang-fourchu.svg` | **Principal** — or dégradé + texte blanc, pour fond sombre |
| `logo-etang-fourchu-sur-clair.svg` | Or dégradé + texte noir, pour fond clair |
| `logo-etang-fourchu-or.svg` | Monochrome or aplat |
| `logo-etang-fourchu-blanc.svg` | Monochrome blanc — fond photo, filigrane |
| `logo-etang-fourchu-noir.svg` | Monochrome noir — impression, fax, gravure |
| `logo-etang-fourchu-clair.svg` | **Or clair en aplat + texte blanc** — fond très sombre et petits formats |
| `embleme-etang-fourchu.svg` | Emblème seul (cercle + vague), sans texte |
| `embleme-etang-fourchu-or.svg` | Emblème seul, or aplat |
| `embleme-etang-fourchu-clair.svg` | Emblème seul, or clair aplat |
| `logo-etang-fourchu@{400,800,1600}.png` | Exports PNG transparents |
| `favicon-512.png` | Emblème 512 px, transparent |

## Charte

**Or (dégradé)** — `#C9A24B` → `#F2DFA4` → `#DCB863` → `#F7EBC4` → `#C79E42`
**Or (aplat)** — `#D9B45F`
**Or clair (aplat)** — `#E6CD8F`, pour les fonds très sombres
**Noir** — `#111111` (texte) / `#141414` (fond)

## Typographie

- Nom : **Playfair Display** 700 (serif à fort contraste)
- Baseline : **Allura** 400 (anglaise calligraphique)

Les deux sont sous licence SIL Open Font License 1.1, libres d'usage commercial.

## Quelle version choisir

Sur un fond **très sombre** ou en **petit format**, préférez la version
`-clair` : le dégradé traverse des tons soutenus (`#C9A24B`, `#C79E42`) qui
se confondent avec le fond, d'autant plus que le tracé est fin. L'aplat clair
garde un contraste constant.

Le dégradé reste préférable en grand format, où il donne au tracé son relief.

## Zone de protection

Réserver autour du logo une marge égale à la hauteur de la capitale « É ».
Taille minimale conseillée : 120 px de large à l'écran, 30 mm en impression.

## Régénérer

```bash
python3 tools/mklogo.py public/assets/img/logo
```

Dépendances : `fonttools`, `brotli`, `cairosvg`, et les fichiers `.woff2`
Playfair Display 700 / Allura 400 dans `tools/fonts/` (paquets npm
`@fontsource/playfair-display` et `@fontsource/allura`).
