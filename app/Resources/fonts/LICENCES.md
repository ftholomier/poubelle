# Polices des exports PDF

Instances fixes (graisse et corps optique figés, latin et latin étendu réunis) des polices
du site, utilisées par `App\Pdf\Layout` et embarquées en sous-ensembles dans les PDF :

| Fichier | Police d'origine | Réglages |
|---|---|---|
| `BigShouldersDisplay-Black.ttf` | Big Shoulders Display | graisse 900 |
| `BigShouldersDisplay-ExtraBold.ttf` | Big Shoulders Display | graisse 800 |
| `Newsreader-Regular.ttf` | Newsreader | graisse 400, corps optique 12 |
| `Newsreader-SemiBold.ttf` | Newsreader | graisse 600, corps optique 12 |
| `Newsreader-Italic.ttf` | Newsreader Italic | corps optique 12 |

- Big Shoulders Display : Copyright 2019 The Big Shoulders Project Authors
  (https://github.com/xotypeco/big_shoulders).
- Newsreader : Copyright 2020 The Newsreader Project Authors
  (https://github.com/productiontype/Newsreader).

Ces polices sont publiées sous la licence SIL Open Font License 1.1
(https://openfontlicense.org), qui autorise leur utilisation, leur modification et leur
intégration dans des documents, à condition de ne pas les vendre seules.

Fabrication (pour information) : `fontTools` (instancer + merge) à partir des fichiers
WOFF2 de `public/assets/fonts/`, tables de mise en forme avancée retirées.
