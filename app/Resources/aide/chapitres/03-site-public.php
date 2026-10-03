<?php
return [
    'slug' => 'site-public',
    'title' => 'Comment le site est organisé',
    'summary' => 'Ce que voient les visiteurs, d’où vient chaque élément et ce qui se met à jour tout seul.',
    'sections' => [
        ['id' => 'accueil', 'title' => 'L’accueil et les menus', 'html' => <<<'HTML'
<p>L’accueil réunit le bandeau « En direct du musée », le grand slider des fiches « À la une », le compte à rebours du centenaire (20 mai 2028), « Ce jour-là », le palmarès, les grandes époques et les réserves.</p>
[[img:site-accueil.webp|L’accueil du site public]]
<p>Les menus reprennent l’arborescence de l’ancien site : <b>Accueil, Matchs, Nos Lions, Supporters, Infrastructures, Symboles</b>, et la nouvelle rubrique <b>Interactif</b>. Chaque menu s’ouvre en méga-menu avec ses sous-rubriques.</p>
[[img:site-megamenu.webp|Le méga-menu « Matchs »]]
[[ecran:/admin/accueil|Régler l’accueil et le bandeau]]
HTML],
        ['id' => 'mosaiques', 'title' => 'Les rubriques en mosaïque', 'html' => <<<'HTML'
<p>Chaque rubrique affiche ses fiches en mosaïque : vignettes avec image à la une, filtres, tri, vue liste et bouton « Afficher plus ». Une fiche apparaît dans toutes les rubriques cochées dans son onglet « Classement &amp; SEO ».</p>
[[img:site-mosaique.webp|Une mosaïque de rubrique]]
[[astuce|<p>L’ordre des fiches d’une mosaïque se règle rubrique par rubrique dans Éditorial › Rubriques &amp; menus : ordre manuel (glisser-déposer, comme dans l’ancien WordPress), chronologique ou alphabétique. Les décennies et les saisons gardent l’ordre manuel repris de l’ancien site.</p>]]
HTML],
        ['id' => 'fiches', 'title' => 'Les fiches', 'html' => <<<'HTML'
<p><b>Fiche match</b> : score, informations, terrain et tableau de composition, temps forts, réactions, vidéos, galerie, et un encadré face-à-face calculé automatiquement.</p>
[[img:site-match.webp|Une fiche match : la composition sur le terrain et en tableau]]
<p><b>Fiche personne</b> : carte à collectionner, identité, récit, statistiques et la liste de tous ses matchs, reliés automatiquement depuis les compositions.</p>
[[img:site-joueur.webp|Une fiche joueur]]
HTML],
        ['id' => 'calcule', 'title' => 'Les pages calculées automatiquement', 'html' => <<<'HTML'
<p>Ces pages n’ont rien à saisir : elles sont construites à partir des fiches match et se mettent à jour à chaque enregistrement.</p>
<table>
<tr><th>Page</th><th>Ce qu’elle montre</th><th>D’où viennent les données</th></tr>
<tr><td>Saisons</td><td>résultats, effectif, buteurs d’une saison</td><td>fiches match et compositions</td></tr>
<tr><td>Face-à-face</td><td>bilan contre chaque adversaire</td><td>fiches match (adversaire, score)</td></tr>
<tr><td>Bilans</td><td>par compétition, par stade</td><td>fiches match (compétition, stade)</td></tr>
<tr><td>Records</td><td>buteurs, joueurs les plus utilisés, affluences, séries</td><td>compositions, spectateurs, scores</td></tr>
<tr><td>Carte</td><td>stades, origines des joueurs, épopées, lieux</td><td>fiches match et personnes, outils interactifs</td></tr>
</table>
[[img:site-explorer.webp|Une page face-à-face, entièrement calculée]]
HTML],
        ['id' => 'interactif', 'title' => 'Interactif, recherche, assistant, dons', 'html' => <<<'HTML'
<ul>
<li><b>Interactif</b> : quiz, album de cartes, maillots, frise, carte, centenaire (100 moments, Onze de légende), réserves du musée.</li>
<li><b>Recherche</b> : loupe en haut du site, sur toutes les fiches.</li>
<li><b>Assistant IA</b> : bulle en bas à droite, qui répond à partir des données du musée.</li>
<li><b>Contact, Contribuer, Newsletter, Faire un don</b> : leurs messages et leurs dons arrivent dans le menu Communauté.</li>
<li><b>Anglais</b> : chaque page existe en anglais (bouton FR / EN) ; voir [[aide:anglais|Version anglaise]].</li>
</ul>
[[img:site-interactif.webp|La rubrique Interactif]]
HTML],
        ['id' => 'visibilite', 'title' => 'Qui voit quoi', 'html' => <<<'HTML'
<ul>
<li>Seules les fiches <b>publiées</b> sont visibles. Les brouillons, fiches à relire et planifiées ne le sont pas.</li>
<li>Le bouton <b>Aperçu</b> d’une fiche montre son rendu avant publication.</li>
<li>Connecté au back-office, vous voyez le site normal même quand la <b>page d’attente</b> est activée.</li>
</ul>
HTML],
    ],
];
