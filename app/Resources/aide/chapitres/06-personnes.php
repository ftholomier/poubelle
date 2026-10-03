<?php
return [
    'slug' => 'personnes',
    'title' => 'Fiches joueurs, entraîneurs, dirigeants',
    'summary' => 'Créer une fiche personne, la placer sur la carte, relier ses matchs, gérer ses statistiques et sa carte d’album.',
    'sections' => [
        ['id' => 'creer', 'title' => 'Créer une fiche personne', 'html' => <<<'HTML'
<p><b>+ Nouveau › Personne</b>. Une même fiche sert à un joueur devenu entraîneur ou dirigeant : cochez toutes ses <b>rubriques</b> ; la première cochée donne l’adresse de la page (<code>/joueurs/…</code>, <code>/entraineurs/…</code>).</p>
[[img:personne-identite.webp|L’onglet Identité : (1) noms, (2) autres graphies, (3) rubriques, (4) naissance]]
HTML],
        ['id' => 'identite', 'title' => 'Identité, naissance et carte des origines', 'html' => <<<'HTML'
<ul>
<li><b>Prénom, Nom</b> (obligatoire), <b>Nom affiché</b> (vide : « Prénom Nom »), surnom.</li>
<li><b>Poste</b> en clair (« défenseur latéral droit ») et <b>Ligne</b> (gardien, défenseur, milieu, attaquant) pour les filtres et le terrain.</li>
<li><b>Naissance</b> : date (« 8 décembre 1964 », « 12/1964 » ou « 1964 ») et ville. La ville place la personne sur la carte des origines ; la position est trouvée automatiquement.</li>
<li><b>Statuts</b> : formé au club, international, à l’essai, légende, visible sur la carte.</li>
</ul>
[[auto|<p>La position sur la carte est recherchée automatiquement (OpenStreetMap). Corrigez la latitude et la longitude seulement si le point est mal placé.</p>]]
HTML],
        ['id' => 'graphies', 'title' => 'Autres graphies dans les compositions', 'html' => <<<'HTML'
<p>Dans les compositions anciennes, un même joueur peut être écrit de plusieurs façons (« N’DIAYE », « Ndiaye », « Rassoul Nidaye »…). Le site relie déjà automatiquement les variantes proches ; pour les autres, ajoutez la graphie dans <b>Autres graphies dans les compositions</b> : tous ces matchs rejoignent la fiche à l’enregistrement.</p>
[[astuce|<p>Qualité › onglet <b>Liens joueurs</b> liste les noms sans fiche et les rapprochements automatiques à vérifier.</p>]]
HTML],
        ['id' => 'carriere', 'title' => 'Carrière au club', 'html' => <<<'HTML'
<p>Onglet <b>Carrière</b> : arrivée et départ (joueur, entraîneur), période d’essai, premiers et derniers matchs, palmarès, « Après Sochaux », fiche d’identité d’origine et matchs marquants.</p>
[[img:personne-carriere.webp|L’onglet Carrière]]
[[astuce|<p>Les dates d’arrivée et de départ servent aussi à relier les compositions au bon joueur quand deux joueurs ont le même nom de famille.</p>]]
HTML],
        ['id' => 'matchs-relies', 'title' => 'Matchs reliés automatiquement', 'html' => <<<'HTML'
<p>La fiche liste tous les matchs où la personne figure dans une composition, avec son temps de jeu et ses buts. Cette liste, ses totaux (matchs, buts, cartons, saisons) et la page publique se mettent à jour seuls.</p>
[[img:personne-matchs.webp|Les matchs reliés automatiquement depuis les compositions]]
HTML],
        ['id' => 'statistiques', 'title' => 'Statistiques saison par saison', 'html' => <<<'HTML'
<p>Onglet <b>Statistiques</b> : le tableau d’origine (saison, compétition, matchs, buts…), modifiable comme une feuille de calcul. Il complète les totaux calculés depuis les compositions, notamment pour les saisons qui n’ont pas encore toutes leurs fiches match.</p>
[[img:personne-stats.webp|Le tableau des statistiques]]
HTML],
        ['id' => 'album', 'title' => 'La carte de l’album du centenaire', 'html' => <<<'HTML'
<p>Dans l’onglet Identité, bloc <b>Carte de l’album du centenaire</b> : cochez « Dans l’album », donnez un numéro et une rareté (légende, classique, actuel). La carte utilise l’image à la une de la fiche.</p>
HTML],
    ],
];
