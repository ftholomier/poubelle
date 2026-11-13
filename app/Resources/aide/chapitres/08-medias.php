<?php
return [
    'slug' => 'medias',
    'title' => 'La médiathèque',
    'summary' => 'Envoyer des photos et des PDF, les légender et les créditer, les retoucher, les remplacer, retrouver où elles sont utilisées.',
    'sections' => [
        ['id' => 'tour', 'title' => 'Tour d’horizon', 'html' => <<<'HTML'
<p>Contenus › <b>Médiathèque</b> : toutes les photos et tous les PDF du musée.</p>
[[img:medias-grille.webp|La médiathèque : (1) envoyer des fichiers, (2) recherche, (3) filtres, (4) dossiers par mois, (5) sélection multiple]]
<p><b>Filtres</b> utiles : sans crédit, sans légende, droits à préciser, [[aide:medias#droits|droits à vérifier]] (agences, banques d’images, journaux), doublons, basse définition, inutilisées, PDF, issues des contributions, récentes.</p>
<p><b>Basse définition</b> : photos de moins de 400 pixels de large et de haut (repérées « ⚠ Basse définition » avec leurs dimensions). Sur le site, elles sont montrées à leur taille réelle sur un fond flou, jamais agrandies ni pixellisées. Si vous trouvez une version plus grande (scan, journal, archives), utilisez <b>Remplacer le fichier…</b> : toutes les fiches qui l’utilisent l’afficheront en grand.</p>
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
<p><b>Médiathèque et fiches restent d’accord.</b> Une photo de galerie garde sa légende et son crédit dans la fiche. Quand vous les modifiez dans la médiathèque, ils sont repris dans les fiches qui montraient le même texte (ou encore l’ancienne légende avec le crédit dedans). Quand vous les saisissez dans une fiche (photo ajoutée, légende corrigée), ils sont repris dans la médiathèque si elle était vide ou avait l’ancien texte de la fiche. Une légende propre à une fiche n’est jamais écrasée ; un crédit effacé dans une fiche n’efface rien dans la médiathèque.</p>
HTML],
        ['id' => 'retoucher', 'title' => 'Recadrer, pivoter, remplacer', 'html' => <<<'HTML'
<ul>
<li><b>Recadrer · pivoter</b> : la retouche est appliquée partout, l’original reste intact (on peut revenir en arrière).</li>
<li><b>Remplacer le fichier…</b> : un meilleur scan, par exemple. La photo garde la même adresse : toutes les fiches qui l’utilisent affichent le nouveau fichier.</li>
</ul>
[[img:medias-retouche.webp|Le recadrage et la rotation, sans toucher à l’original]]
[[attention|<p><b>Supprimer une photo</b> (tout le monde, historiens compris) : bouton rouge « Supprimer » en bas de la fenêtre de la photo. Une photo ne peut être supprimée <b>que si elle n’est plus utilisée nulle part</b> : tant qu’elle sert dans une fiche, une galerie ou l’accueil, le bouton est grisé et un encadré liste les contenus concernés. Retirez-la d’abord de chacun d’eux (ou mettez-y une autre photo) ; le bouton se débloque alors. Pour mettre une meilleure version partout, utilisez plutôt « Remplacer le fichier… ». La suppression est définitive et notée dans le Journal.</p>]]
HTML],
        ['id' => 'droits', 'title' => 'Droits des photos : agences, banques d’images, journaux', 'html' => <<<'HTML'
<p>Des sociétés (Copytrack, Pixsy, PicRights pour l’AFP…) parcourent le web pour retrouver les photos d’agences et de banques d’images, et envoient des factures aux sites qui les publient sans licence. Elles comparent l’image elle-même : effacer ou modifier les informations d’une photo n’y change rien, et c’est interdit par la loi. Le musée fait donc l’inverse : il repère ces photos lui-même, pour que l’équipe décide.</p>
<p><b>Ce qui est repéré</b> : un nom d’agence ou de banque d’images (AFP, Reuters, Icon Sport, Maxppp, Presse Sports, Panoramic, Getty Images, Adobe Stock, Shutterstock, iStock…) ou de journal (L’Équipe, L’Est Républicain, France Football, Le Pays…) dans le crédit, les droits, le nom du fichier, la légende (après « photo », « crédit », « © »…), ou dans les <b>informations cachées du fichier</b> (copyright, crédit, auteur, source, conditions d’utilisation). Ces informations sont lues par la tâche planifiée « Droits des photos » et à chaque envoi ; elles ne sont jamais modifiées.</p>
<ul>
<li><b>À l’envoi</b> : un encadré rouge prévient si la photo appartient sans doute à une agence ; depuis une fiche, le musée demande confirmation avant de l’utiliser. Le crédit et les droits sont préremplis avec les informations de la photo.</li>
<li><b>Dans la médiathèque</b> : filtre « Droits à vérifier », mention « ⚠ Droits à vérifier : AFP » sur la vignette, et dans la fenêtre de la photo le détail de ce qui a été trouvé et où.</li>
<li><b>Dans Qualité</b> : onglet « Droits des photos » (grave pour une agence ou une banque d’images utilisée sur le site, moyen pour un journal, faible pour une photo utilisée nulle part).</li>
</ul>
<p><b>Décider</b> : si le musée n’a pas le droit d’utiliser la photo, retirez-la des contenus puis supprimez-la. Sinon, écrivez pourquoi on la garde (autorisation écrite, licence achetée, collection du club, domaine public…) et cliquez <b>Droits vérifiés : on la garde</b> : la photo n’est plus signalée, la décision (qui, quand, pourquoi) reste visible et peut être annulée (« Remettre à vérifier »). Seul un membre de l’équipe décide, jamais le musée tout seul.</p>
[[attention|<p>Une photo d’agence dont toutes les informations ont été effacées avant d’arriver chez nous (copiée d’un autre site) ne peut pas être repérée : en cas de doute sur l’origine d’une photo, demandez à qui l’a fournie.</p>]]
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
