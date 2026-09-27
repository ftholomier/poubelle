# Prospection

`intercommunalites-france.xlsx` : toutes les intercommunalités de France avec leurs coordonnées publiques.

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
