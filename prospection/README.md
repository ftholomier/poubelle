# Prospection

`intercommunalites-france.xlsx` : toutes les intercommunalités de France avec leurs coordonnées publiques.

- Onglet « Toutes les intercommunalités » : les 1 255, triées par département.
- Onglet « CA modèle B » : pour chacune des 989 communautés de communes, l’abonnement annuel du modèle B
  (10 000 € + 0,20 € par habitant, plafonné à 59 000 € HT), la mise en service de 5 000 €, le total de la
  première année et le total sur trois ans. Des totaux et des hypothèses de part de marché complètent l’onglet.
  Les paramètres sont modifiables en haut de l’onglet, et tout se recalcule (formules Excel).
- Onglet « Communautés de communes » : 989 lignes.
- Onglet « Agglos, CU, métropoles » : 266 lignes.
- Onglet « Sources ».

Colonnes : nom, type, SIREN, département, région, population, nombre de communes, adresse, téléphone, courriel,
site web, formulaire de contact, fiche Service-public.fr. Trois colonnes vides sont prévues pour le suivi :
statut, interlocuteur, notes.

Pour régénérer le fichier : `python3 prospection/intercommunalites.py` (openpyxl requis).

- Sources ouvertes, sous Licence Ouverte Etalab 2.0 : geo.api.gouv.fr, Annuaire de l’administration de
  Service-public.fr et registre SIRENE.
- Les téléchargements sont gardés dans `prospection/.cache/`, qui n’est pas versionné. Supprimez ce dossier
  pour repartir des données du jour.
