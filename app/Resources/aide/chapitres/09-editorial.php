<?php
return [
    'slug' => 'editorial',
    'title' => 'Accueil, rubriques et menus',
    'summary' => 'Régler la page d’accueil et le bandeau, l’ordre des mosaïques, les redirections et la page d’attente.',
    'sections' => [
        ['id' => 'accueil', 'title' => 'La page d’accueil', 'html' => <<<'HTML'
<p>Éditorial › <b>Accueil &amp; bandeau</b>. Chaque bloc de la page d’accueil a sa carte ; enregistrez avec le bouton en bas de page.</p>
<ul>
<li><b>Grand slider</b> : tirage au hasard parmi toutes les fiches publiées (matchs, joueurs, récits, articles…) dont la photo à la une est assez grande pour rester nette en plein écran (au moins 1 200 × 600 pixels), ou une liste choisie à la main dans l’ordre voulu. Les fiches à la photo trop petite ne sont pas tirées : la carte dit combien sont écartées. En sélection manuelle, l’étiquette « photo trop petite » signale une photo qui paraîtra floue.</li>
<li><b>Compteurs et centenaire</b>, <b>Palmarès</b> (bandeau jaune), <b>Les grandes époques</b> (nom, dates, texte, image, dates clés), <b>Les réserves du musée</b>, <b>« Ils ont porté le lion »</b>, <b>Images des encarts</b> (quiz, maillots, frise, contribuer). Une photo de la médiathèque est choisie au départ pour chaque époque, réserve et encart (et pour la frise et le comparateur de maillots) : « Choisir… » pour la remplacer.</li>
<li><b>Référencement de l’accueil</b> : titre et description pour Google.</li>
<li><b>Le chiffre du jour</b> (onglet Textes &amp; compteurs) : un des 100 chiffres du FCSM affiché sous « Ce jour-là », un nouveau chaque jour ; la carte montre celui du jour, la case l’affiche ou le masque.</li>
<li><b>Le teaser vidéo</b> (onglet Textes &amp; compteurs) : la vidéo de présentation du musée (1 min 55), sous les compteurs. Tant que la page d’attente est active, le public ne la voit pas : seule l’équipe connectée la voit.</li>
</ul>
[[img:accueil-slider.webp|Le réglage du grand slider de l’accueil]]
[[astuce|<p>Toute fiche publiée entre dans le tirage au hasard du slider dès qu’elle a une image à la une d’au moins 1 200 × 600 pixels. Sous la case, la fiche signale une image trop petite : un plus grand scan (Médiathèque › cliquez la photo › « Remplacer le fichier… ») la fait entrer dans le tirage.</p>]]
HTML],
        ['id' => 'bandeau', 'title' => 'Le bandeau « En direct du musée »', 'html' => <<<'HTML'
<p>Le bandeau défilant en haut de toutes les pages mêle des messages automatiques (« Ce jour-là », compte à rebours du centenaire, dernier match fiché), que l’on peut activer ou non, et les <b>messages de l’équipe</b> : étiquette, message, lien facultatif, version anglaise, case « Affiché ».</p>
[[img:accueil-bandeau.webp|Les messages du bandeau]]
HTML],
        ['id' => 'menus', 'title' => 'Les menus du site (menu principal, Interactif, pied de page)', 'html' => <<<'HTML'
<p>Éditorial › <b>Menus du site</b> règle tous les liens des menus, en cinq onglets. Chaque ligne a un libellé (et sa version anglaise, ou le bouton « Traduire en anglais »), un lien et une case <b>Masqué</b> ; l’ordre se change par glisser-déposer ou avec les flèches ↑ ↓.</p>
<ul>
<li><b>Boutons du haut</b> : les trois boutons en haut à droite (le lien discret « Contribuer », le bouton bleu de la boutique avec son panier, le bouton jaune ♥ du don), repris dans le menu sur téléphone et, pour la boutique et le don, dans le pied de page. Leur place et leur style restent fixes ; libellé, lien et « Masqué » se changent.</li>
<li><b>Menu principal</b> : les entrées du haut du site. Celles marquées « grand menu » ouvrent leur menu déroulant (Matchs, Nos Lions, Supporters, Infrastructures, Symboles, Interactif) ; vous pouvez les renommer, les déplacer ou les masquer. « Ajouter un lien au menu » crée une entrée simple.</li>
<li><b>Matchs › Explorer</b> : la colonne de liens du grand menu Matchs (Saisons, Face-à-face, Palmarès…).</li>
<li><b>Interactif</b> : les groupes d’outils du grand menu Interactif et de la page Interactif, avec pour chaque outil une icône, un libellé, une phrase et un lien. Le groupe des murs de photos se remplit tout seul.</li>
<li><b>Pied de page</b> : les colonnes de liens (jusqu’à 4), chacune avec son titre.</li>
</ul>
<p><b>Liens</b> : une adresse du site (<code>/palmares/</code>, la barre finale est ajoutée toute seule), une adresse complète (<code>https://…</code>, ouverte dans un nouvel onglet) ou <code>{association}</code> pour le site de l’association. Un lien vers la boutique ou l’appli disparaît quand elles sont fermées.</p>
<p>Le contenu des sous-menus des rubriques (saisons, compétitions, sous-rubriques) se règle toujours dans Rubriques &amp; menus ; la phrase, le bandeau et le copyright du pied de page dans Réglages › Pied de page. <b>Revenir aux menus de départ</b>, en bas de l’écran, efface tous les changements.</p>
HTML],
        ['id' => 'rubriques', 'title' => 'Rubriques, menus et ordre des mosaïques', 'html' => <<<'HTML'
<p>Éditorial › <b>Rubriques &amp; menus</b> : l’arborescence du site, reprise de l’ancien WordPress. Cliquez une rubrique pour régler :</p>
<ul>
<li><b>Ordre d’affichage sur le site</b> : ordre manuel, chronologique (du plus ancien ou du plus récent) ou alphabétique. Les visiteurs peuvent toujours changer de tri ; ce réglage choisit celui qu’ils voient d’abord.</li>
<li><b>Ordre des fiches</b> (ordre manuel) : toutes les fiches de la rubrique, sous-rubriques comprises — par exemple les 545 matchs des années 90. Déplacez-les avec ↑ / ↓ ou l’icône quatre flèches (voir <a href="/admin/aide/prise-en-main#ordre">Changer l’ordre d’une liste</a>), retrouvez une fiche avec « Trouver une fiche dans la liste », ou repartez d’un ordre automatique avec <b>Date ↑</b>, <b>Date ↓</b>, <b>A → Z</b> avant d’ajuster à la main.</li>
<li><b>Ordre des sous-rubriques</b> : les saisons d’une décennie, les compétitions… dans l’ordre des menus, onglets et filtres, par glisser-déposer.</li>
<li><b>Libellés</b> en français et en anglais (menus, fil d’Ariane, mosaïque) et <b>texte d’introduction</b> de la mosaïque.</li>
</ul>
[[img:rubrique-ordre.webp|Une rubrique (ici les années 90) : (1) ordre d’affichage sur le site, (2) trouver une fiche, (3) remettre en ordre automatiquement, (4) position (cliquer pour la taper), (5) ↑ / quatre flèches / ↓]]
[[astuce|<p>Les fiches créées après le dernier classement sont signalées « non classée » et placées en fin de liste : glissez-les à leur place puis enregistrez.</p>]]
[[attention|<p>Une fiche n’apparaît dans une rubrique que si cette rubrique est cochée dans la fiche (onglet « Classement &amp; SEO »).</p>]]
HTML],
        ['id' => 'redirections', 'title' => 'Redirections et adresses introuvables', 'html' => <<<'HTML'
<p>Éditorial › <b>Redirections</b> : les anciennes adresses envoyées vers les nouvelles (redirections « 301 », comprises par Google). Les 5 984 adresses de l’ancien site sont déjà là.</p>
<ul>
<li><b>Nouvelle redirection</b> : ancienne adresse (commençant par <code>/</code>) et nouvelle adresse.</li>
<li>Onglet <b>Adresses introuvables</b> : les adresses demandées par des visiteurs qui n’existent pas, avec la fiche la plus proche proposée ; un clic crée la redirection.</li>
</ul>
[[img:redirections-introuvables.webp|Les adresses introuvables, à rediriger en un clic]]
[[auto|<p>Quand vous changez l’adresse d’une fiche publiée, l’ancienne est redirigée automatiquement.</p>]]
HTML],
        ['id' => 'attente', 'title' => 'La page d’attente', 'html' => <<<'HTML'
<p>Éditorial › <b>Page d’attente</b> : pour fermer le site au public (préparation du lancement, maintenance). Elle reprend le logo, un texte mis en forme et, si vous le voulez, un compte à rebours jusqu’à une date. <b>Elle est active dès l’installation</b> : le site reste fermé au public jusqu’au jour où vous la décochez.</p>
[[img:page-attente.webp|Le réglage de la page d’attente et son aperçu]]
<ul>
<li>Cochez <b>Activer la page d’attente</b>, rédigez le texte, enregistrez. Décochez-la le jour de l’ouverture.</li>
<li>Tant qu’elle est active, <b>rien n’est indexé</b> par les moteurs de recherche (Google…), et la page ne propose aucun lien vers le back-office.</li>
<li><b>Connecté au back-office, vous voyez le vrai site</b>, avec un bandeau jaune « Site fermé au public » en haut de chaque page ; déconnecté, la page d’attente. Pour vous connecter : <code>/admin</code>.</li>
<li><b>Aperçu de la page</b> montre ce que voient les visiteurs ; les pages légales restent accessibles.</li>
<li><b>Afficher le teaser vidéo</b> : décoché par défaut, la vidéo reste secrète (introuvable par le public). <b>Aperçu avec le teaser</b> la montre à l’équipe sans l’activer.</li>
<li><b>« Prévenez-moi de l’ouverture »</b> : les visiteurs reçoivent une notification le jour J (Android et ordinateur directement ; sur iPhone, après avoir ajouté la page à l’écran d’accueil). L’annonce se prépare dans Communauté › Notifications. Réglages › Application du musée pour retirer le bouton.</li>
</ul>
<p>Cette page ne concerne que le musée. Le site de l’association (www.fcsochauxretro.com) a sa propre page d’attente, différente, réglée par les administrateurs.</p>
HTML],
    ],
];
