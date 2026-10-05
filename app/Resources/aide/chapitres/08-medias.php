<?php
return [
    'slug' => 'medias',
    'title' => 'La médiathèque',
    'summary' => 'Envoyer des photos et des PDF, les légender et les créditer, les retoucher, les remplacer, retrouver où elles sont utilisées.',
    'sections' => [
        ['id' => 'tour', 'title' => 'Tour d’horizon', 'html' => <<<'HTML'
<p>Contenus › <b>Médiathèque</b> : toutes les photos et tous les PDF du musée.</p>
[[img:medias-grille.webp|La médiathèque : (1) envoyer des fichiers, (2) recherche, (3) filtres, (4) dossiers par mois, (5) sélection multiple]]
<p><b>Filtres</b> utiles : sans crédit, sans légende, droits à préciser, doublons, inutilisées, PDF, issues des contributions, récentes.</p>
HTML],
        ['id' => 'envoyer', 'title' => 'Envoyer des photos', 'html' => <<<'HTML'
<ol>
<li>Glissez les fichiers sur la page (ou bouton d’envoi). Formats : JPG, PNG, GIF, WebP, PDF ; 25 Mo au plus par fichier.</li>
<li>Une fenêtre demande la <b>légende</b>, le <b>crédit</b> (obligatoire) et les <b>droits</b> des fichiers envoyés.</li>
<li>Les photos sont aussitôt utilisables dans les fiches.</li>
</ol>
[[img:medias-envoi.webp|La description des photos envoyées : le crédit est obligatoire]]
[[astuce|<p>Les doublons exacts sont repérés automatiquement (filtre « Doublons »).</p>]]
HTML],
        ['id' => 'decrire', 'title' => 'Légende, crédit, droits et texte alternatif', 'html' => <<<'HTML'
<p>Cliquez une photo pour ouvrir sa fiche :</p>
<ul>
<li><b>Légende</b> : qui, où, quand.</li>
<li><b>Crédit</b> : photographe, journal, collection. Pas de date ni de légende dans ce champ (elles vont dans « Date ou époque » et « Légende ») : une photo bien créditée peut aller sur les [[aide:interactif#murs-photos|murs de photos]].</li>
<li><b>Droits / licence</b> : conditions d’usage (« Tous droits réservés », « cession du donateur », « CC BY-SA »…).</li>
<li><b>Texte alternatif</b> : décrit l’image pour les personnes malvoyantes et pour Google.</li>
<li>Légende et texte alternatif ont leur version anglaise.</li>
<li><b>Utilisée dans</b> : les fiches qui affichent cette photo.</li>
<li><b>Murs de photos</b> : indique si la photo peut être tirée au hasard sur les murs de photos de la rubrique Interactif, ou pourquoi elle ne l’est pas (sans crédit, « DR », crédit exclu, trop petite, fiche pas encore publiée…). La case <b>Jamais sur les murs de photos</b> l’en retire, même bien créditée.</li>
</ul>
[[img:medias-detail.webp|La fiche d’une photo : description, droits, « utilisée dans », actions]]
<p><b>Modification groupée</b> : cochez plusieurs photos pour leur donner le même crédit ou les mêmes droits en une fois (« Remplacer aussi les valeurs déjà saisies » si besoin).</p>
HTML],
        ['id' => 'retoucher', 'title' => 'Recadrer, pivoter, remplacer', 'html' => <<<'HTML'
<ul>
<li><b>Recadrer · pivoter</b> : la retouche est appliquée partout, l’original reste intact (on peut revenir en arrière).</li>
<li><b>Remplacer le fichier…</b> : un meilleur scan, par exemple. La photo garde la même adresse : toutes les fiches qui l’utilisent affichent le nouveau fichier.</li>
</ul>
[[img:medias-retouche.webp|Le recadrage et la rotation, sans toucher à l’original]]
[[attention|<p>La suppression d’un fichier est réservée aux administrateurs ; vérifiez d’abord « Utilisée dans ».</p>]]
HTML],
        ['id' => 'fiches', 'title' => 'Utiliser les photos dans les fiches', 'html' => <<<'HTML'
<p>Onglet <b>Médias</b> d’une fiche : <b>Image à la une</b> (bouton de choix), <b>Galerie</b> (ajout depuis la médiathèque ou envoi direct), vidéos. La légende et le crédit saisis dans la galerie priment pour cette fiche ; vides, ceux de la médiathèque s’appliquent.</p>
HTML],
        ['id' => 'bonnes-pratiques', 'title' => 'Bonnes pratiques', 'html' => <<<'HTML'
<ul>
<li>Scannez en bonne définition (1 600 pixels de large suffisent pour le site) ; le site crée seul les vignettes légères.</li>
<li>Créditez toujours, et vérifiez les droits avant de publier une photo de presse.</li>
<li>Nommez les fichiers clairement (« fcsm-nantes-1990-but-prat.jpg ») : ils se retrouvent plus facilement.</li>
</ul>
HTML],
    ],
];
