# SI-JUR-06 · Prospection téléphonique (Suisse Immo)

Adaptation pour le réseau Suisse Immo de la fiche juridique SYNAPSE SYN-JUR-06 :
7 pages sur le consentement préalable au démarchage téléphonique (en vigueur
depuis le 11 août 2026), plus un formulaire de recueil du consentement en annexe.

## Livrables (`livrables/`)

| Fichier | Usage |
|---|---|
| `SI-JUR-06_Prospection-telephonique_Suisse-Immo.pdf` | Fiche complète, A4, prête à imprimer ou à diffuser |
| `SI-JUR-06_Prospection-telephonique_Suisse-Immo.docx` | Version Word modifiable, avec les polices de la marque intégrées, les champs à compléter sous forme de zones de saisie et des cases à cocher cliquables |
| `SI-JUR-06_Prospection-telephonique_Suisse-Immo.html` | Version web autonome (polices et logo intégrés) |
| `SI-JUR-06_Formulaire-consentement.pdf` | Formulaire seul sur une page, sans note ni pied de page, prêt à imprimer |
| `SI-JUR-06_Formulaire-consentement.docx` | Le même formulaire en Word, avec zones de saisie et cases à cocher |
| `SI-JUR-06_Formulaire-consentement_a-remplir.pdf` | Le même formulaire en PDF à remplir sur tablette : 27 champs, cases à cocher et champ de signature |

## Partis pris

- **Contenu.** Seules les pages 6 (fin) et 7 de l'original SYNAPSE étaient
  disponibles. Les pages 1 à 6 ont donc été entièrement réécrites à partir du
  Code de la consommation (art. L. 223-1 et suivants, dans leur rédaction issue de
  la loi n° 2025-594 du 30 juin 2025), du décret n° 2026-662 du 23 juillet 2026 et
  de l'art. D. 223-8. Le formulaire reprend le texte de l'original. Seuls changent
  le bloc d'identité de l'agence, l'objet du consentement, la mention des agents
  commerciaux et une phrase précisant que les données ne passent pas aux autres
  agences du réseau.
- **Émetteur.** Chaque agence Suisse Immo est une société distincte titulaire de
  sa propre carte T, et le consentement est donné à une société précise. Le
  formulaire reste donc un modèle, que chaque agence complète avec sa raison
  sociale, son adresse et sa carte T.
- **Agents commerciaux.** Ils sont visés explicitement dans le texte : ils
  recueillent l'accord au nom de l'agence qui les habilite et consultent son
  registre avant chaque appel.
- **Objet du consentement.** La location s'ajoute à la vente.
- **Charte.** Celle du site recrutement (recrutement.suisse-immo.fr) : logo
  vectoriel, rouge `#CC0017`, titres en Bricolage Grotesque, texte en Inter et
  libellés en Space Grotesk. Les trois polices sont sous licence SIL Open Font
  License 1.1.

La fiche est à jour au 27 septembre 2026 et ne remplace pas une consultation
juridique : à faire relire avant diffusion.

## Régénérer

Le texte se modifie à un seul endroit, `source/content.py`, puis :

```sh
cd source
npm install          # docx, jszip
sh build.sh          # écrit les sept livrables dans ../livrables
```

Prérequis : Python 3 avec `pymupdf`, `fonttools` et `brotli`, ainsi que Node 18
ou plus avec Playwright et Chromium installés globalement.
