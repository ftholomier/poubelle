<?php
return [
    'slug' => 'anglais',
    'title' => 'La version anglaise',
    'summary' => 'Traduire l’interface et les fiches, relire, savoir ce qui est à revoir.',
    'sections' => [
        ['id' => 'principe', 'title' => 'Le principe', 'html' => <<<'HTML'
<p>Chaque page du site existe en anglais, à l’adresse précédée de <code>/en</code> (bouton FR / EN du site). Le français fait foi : un texte non traduit s’affiche en français.</p>
<ul>
<li><b>L’interface</b> (menus, boutons, libellés) : Système › Traductions EN, onglet « Interface du site ».</li>
<li><b>Les fiches</b> : onglet <b>Version EN</b> de chaque fiche, ou en série depuis Système › Traductions EN, onglet « Fiches ».</li>
<li><b>Les blocs éditoriaux</b> (bandeau, époques, quiz…) : champs « (EN) » et boutons « Traduire en anglais ».</li>
</ul>
HTML],
        ['id' => 'fiches', 'title' => 'Traduire une fiche', 'html' => <<<'HTML'
<p>Onglet <b>Version EN</b> : « Traduire avec Gemini » propose une traduction de toute la fiche par l’intelligence artificielle ; relisez, corrigez, enregistrez.</p>
[[img:fiche-version-en.webp|L’onglet Version EN d’une fiche]]
[[auto|<p>Avec la traduction automatique activée (Réglages › Traduction), la tâche planifiée traduit peu à peu les fiches publiées. Une fiche dont le français change est signalée « à revoir ».</p>]]
<p>Système › Traductions EN › onglet <b>Fiches</b> : « Tout traduire d’un coup » traduit à la suite toutes les fiches manquantes ou à revoir (barre d’avancement, bouton Stop). Si le changement du français ne touche pas la version anglaise, « La traduction reste bonne » retire l’alerte d’une fiche, et « Tout marquer à jour » le fait pour toutes.</p>
HTML],
        ['id' => 'interface', 'title' => 'L’interface du site', 'html' => <<<'HTML'
<p>Système › <b>Traductions EN</b> : chaque libellé français de l’interface et sa traduction. Le filtre « Sans traduction » montre ce qui manque ; « Traduire les manquants avec Gemini » les propose d’un coup.</p>
[[img:traductions.webp|Les libellés de l’interface et leur traduction]]
HTML],
    ],
];
