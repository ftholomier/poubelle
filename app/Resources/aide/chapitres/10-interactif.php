<?php
return [
    'slug' => 'interactif',
    'title' => 'Les outils interactifs',
    'summary' => 'Quiz, frise, maillots, carte, partenaires, page « Faire un don », Onze de légende et album.',
    'sections' => [
        ['id' => 'principe', 'title' => 'Le principe', 'html' => <<<'HTML'
<p>Interactif › <b>Quiz, frise, carte…</b> : chaque outil est une liste d’éléments (une question, une date, une époque, un lieu…) qu’on ajoute, modifie, réordonne par glisser-déposer et traduit. La carte de chaque outil indique les éléments <b>à valider</b> et ceux <b>sans anglais</b>.</p>
[[img:interactif.webp|Les outils interactifs et ce qu’il reste à valider]]
[[attention|<p>Les contenus de départ (quiz, frise, maillots, épopées, lieux) ont été préparés à partir des fiches du musée : relisez-les et cochez « Validé » élément par élément.</p>]]
HTML],
        ['id' => 'quiz', 'title' => 'Le quiz', 'html' => <<<'HTML'
<p>Une question, ses quatre réponses, la bonne réponse et un « Le saviez-vous ? » affiché après la réponse. « Affichée dans le quiz » la met en jeu ; « Validée par un historien » confirme qu’elle a été vérifiée. Le bouton « Traduire en anglais » propose la version anglaise.</p>
[[img:quiz.webp|L’édition d’une question du quiz]]
HTML],
        ['id' => 'autres', 'title' => 'Frise, maillots, carte, partenaires, page de don', 'html' => <<<'HTML'
<ul>
<li><b>Frise chronologique</b> : une année, un titre, un texte, une image, un lien vers une fiche ou une rubrique, une mise en valeur.</li>
<li><b>Comparateur de maillots</b> : une année de référence, son intitulé, la photo du maillot, une description.</li>
<li><b>Carte : grandes épopées</b> : les étapes d’un parcours (année, saison, compétition, lieu, coordonnées, victoire ou non, lien vers la fiche) ; <b>Carte : lieux du club</b> : nom, type, description, coordonnées, lien. Les stades des matchs et les lieux de naissance viennent des fiches.</li>
<li><b>Partenaires</b> : nom, logo, site web (page Contact).</li>
<li><b>Page « Faire un don »</b> : titre, accroche et textes de la page, paliers et leur impact.</li>
</ul>
HTML],
        ['id' => 'onze-album', 'title' => 'Onze de légende et album', 'html' => <<<'HTML'
<p>Interactif › <b>Onze &amp; album</b> :</p>
<ul>
<li><b>Le Onze du public</b> : le résultat des votes, poste par poste ; <b>Date de dévoilement</b> : quand il est révélé sur le site. La remise à zéro des votes est réservée aux administrateurs.</li>
<li><b>Album du centenaire</b> : les cartes à collectionner ; on ajoute un joueur depuis sa fiche (onglet Identité › Carte de l’album).</li>
</ul>
[[img:onze.webp|Le Onze de légende du public]]
HTML],
    ],
];
