# L'offre irrésistible · infographie A4 Synapse

- `Synapse-offre-irresistible.pdf` : l'infographie, une page A4, à la charte Synapse.
- `offre.html` : la page source (ouvrable dans un navigateur).
- Pour modifier : changer les textes dans `generer.py` (contenu) ou `style.css` (mise en page), puis :

```sh
python3 generer.py <dossier> ../visite-immo      # écrit <dossier>/offre.html
node render.mjs <dossier>                         # écrit <dossier>/Synapse-offre-irresistible.pdf (Playwright + Chromium)
```

Document de travail : paliers, tarifs, contenus et gains de temps sont des propositions à valider et à mesurer.
