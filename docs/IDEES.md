# Idées en réserve

Pistes proposées en octobre 2026, à reprendre quand l'association le décidera. Chiffres
calculés sur les données du musée à cette date ; tarifs de Google à confirmer au moment de
s'y mettre.

## Allô Bonal — la ligne des souvenirs (à financer)

Un numéro de téléphone local : un ancien supporter, sans internet, appelle et une IA (qui se
présente comme telle) l'interroge sur ses souvenirs de Bonal. Pendant l'appel, elle s'appuie
sur les fiches du musée pour faire revenir les détails (« Ce ne serait pas Sochaux–Le Puy, le
28 février 1988 ? La neige avait été déblayée dès 8 h… »). Après l'appel : transcription,
résumé, matchs et joueurs reconnus, contribution soumise aux historiens ; sur les fiches de
match, un bloc « Ils y étaient » avec la voix des témoins (consentement oral, retrait possible).

- V1 « répondeur intelligent » (questions enregistrées, traitement par la tâche planifiée) :
  compatible avec l'hébergement actuel ; quelques centimes d'IA + 1 à 2 centimes la minute de
  téléphone (opérateur avec API : Twilio, Vonage…).
- V2 conversation en direct (voix temps réel de Gemini) : petit service à côté d'o2switch ;
  de l'ordre de 1 à 2 € pour 20 minutes.
- Financements possibles une fois « Les Après-midi Bonal » lancés : fondations, caisses de
  retraite, Département (prévention de la perte d'autonomie).

## Le Rétro-Direct — « il y a 40 ans jour pour jour, en direct de Bonal » (gratuit)

Le jour anniversaire d'un grand match, à l'heure du coup d'envoi, le site le rejoue minute
par minute : score qui change à la minute des buts, compos, temps forts, photos à la
mi-temps, réactions au coup de sifflet final ; compteur de personnes connectées, réactions en
un clic, bouton « J'y étais ! », image de partage à chaque but. 1 501 matchs ont déjà leurs
temps forts minute par minute et 1 655 leur composition. Aucune IA : la chronologie est
déroulée par le navigateur. Back-office : calendrier des anniversaires proposés (finales,
derbys, gros scores, anniversaires ronds). Exemples : Sochaux–Marseille 2-0 du 4 octobre 1986
(40 ans), Sochaux–Toulon 7-2 du 19 octobre 1996 (30 ans).

## Le Fil jaune — tous les Lionceaux sont reliés (gratuit)

Deux joueurs choisis, et le site trouve la chaîne des matchs joués ensemble qui les relie,
chaque maillon menant à la fiche du match. Sur les compositions actuelles : 440 joueurs de
1980 à 2026 forment une seule famille, reliés en 6 passes au plus (2,8 en moyenne). Autour :
défi du jour à partager, « galaxie jaune et bleue » des coéquipiers par décennie, records
(joueur le plus connecté). Révèle aussi les trous et les erreurs de saisie (homonymes) ; les
compos d'avant 1980 sont encore rares.

## Les Après-midi Bonal — kit souvenirs pour les EHPAD et les familles (gratuit, social)

Chaque mois, un PDF de 4 pages en gros caractères fabriqué automatiquement : « Il y a 40 ans
ce mois-ci » (le grand match du mois), « Vous les reconnaissez ? » (photos de joueurs de
l'époque), le quiz des anciens, « Racontez-nous » (questions et retour par courrier, QR code
vers le formulaire de contribution ou l'animateur). Les souvenirs recueillis alimentent le
bloc « Ils y étaient » des fiches. Coût nul (moteur PDF, quiz, photos, envoi par e-mail et
contributions existent déjà). Marchepied d'Allô Bonal : réseau d'EHPAD, premiers témoignages,
preuve d'impact.

## La fiche qui se raconte en 30 secondes (gratuit ou ~20 €)

Une icône haut-parleur sur chaque fiche lit un résumé de 30 secondes (environ 75 mots).
- Gratuit : voix du navigateur et résumé construit à partir des données de la fiche.
- Voix naturelle (Gemini 3.8 Flash TTS, octobre 2026 : 9 $ le million de jetons audio, 25
  jetons par seconde) : environ 0,6 centime par fiche, soit environ 20 € pour les 2 940
  fiches une fois (10 € en traitement groupé), fichier audio gardé sur le serveur et refait
  seulement si la fiche change. Tarif doublé par Google au 1er janvier 2027.
- Accessibilité : texte affiché sous le bouton, version anglaise possible.
