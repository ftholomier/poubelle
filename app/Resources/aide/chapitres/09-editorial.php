<?php
return [
    'slug' => 'editorial',
    'title' => 'Accueil, rubriques et menus',
    'summary' => 'Régler la page d’accueil et le bandeau, l’ordre des mosaïques, les redirections et la page d’attente.',
    'sections' => [
        ['id' => 'accueil', 'title' => 'La page d’accueil', 'html' => <<<'HTML'
<p>Éditorial › <b>Accueil &amp; bandeau</b>. Chaque bloc de la page d’accueil a sa carte ; enregistrez avec le bouton en bas de page.</p>
<ul>
<li><b>Grand slider</b> : tirage au hasard parmi les fiches cochées « À la une » qui ont une image (comme l’ancien site), ou une liste choisie à la main dans l’ordre voulu.</li>
<li><b>Compteurs et centenaire</b>, <b>Palmarès</b> (bandeau jaune), <b>Les grandes époques</b> (nom, dates, texte, image, dates clés), <b>Les réserves du musée</b>, <b>« Ils ont porté le lion »</b>, <b>Images des encarts</b> (quiz, maillots, frise, contribuer).</li>
<li><b>Référencement de l’accueil</b> : titre et description pour Google.</li>
</ul>
[[img:accueil-slider.webp|Le réglage du grand slider de l’accueil]]
[[astuce|<p>Pour faire entrer une fiche dans le slider en tirage au hasard : cochez « À la une » dans son onglet « Classement &amp; SEO » et donnez-lui une image à la une.</p>]]
HTML],
        ['id' => 'bandeau', 'title' => 'Le bandeau « En direct du musée »', 'html' => <<<'HTML'
<p>Le bandeau défilant en haut de toutes les pages mêle des messages automatiques (« Ce jour-là », compte à rebours du centenaire, dernier match fiché), que l’on peut activer ou non, et les <b>messages de l’équipe</b> : étiquette, message, lien facultatif, version anglaise, case « Affiché ».</p>
[[img:accueil-bandeau.webp|Les messages du bandeau]]
HTML],
        ['id' => 'rubriques', 'title' => 'Rubriques, menus et ordre des mosaïques', 'html' => <<<'HTML'
<p>Éditorial › <b>Rubriques &amp; menus</b> : l’arborescence du site, reprise de l’ancien WordPress. Cliquez une rubrique pour régler :</p>
<ul>
<li><b>Ordre d’affichage sur le site</b> : ordre manuel, chronologique (du plus ancien ou du plus récent) ou alphabétique. Les visiteurs peuvent toujours changer de tri ; ce réglage choisit celui qu’ils voient d’abord.</li>
<li><b>Ordre des fiches</b> (ordre manuel) : toutes les fiches de la rubrique, sous-rubriques comprises — par exemple les 545 matchs des années 90. Déplacez-les avec ↑ / ↓ ou l’icône quatre flèches (voir <a href="/admin/aide/prise-en-main#ordre">Changer l’ordre d’une liste</a>), retrouvez une fiche avec « Trouver une fiche dans la liste », ou repartez d’un ordre automatique avec <b>Date ↑</b>, <b>Date ↓</b>, <b>A → Z</b> avant d’ajuster à la main.</li>
<li><b>Ordre des sous-rubriques</b> : les saisons d’une décennie, les compétitions… dans l’ordre des menus, onglets et filtres, par glisser-déposer.</li>
<li><b>Libellés</b> en français et en anglais (menus, fil d’Ariane, mosaïque) et <b>texte d’introduction</b> de la mosaïque.</li>
</ul>
[[img:rubrique-ordre.webp|Une rubrique : ordre d’affichage et ordre des fiches par glisser-déposer]]
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
<p>Éditorial › <b>Page d’attente</b> : pour fermer temporairement le site (maintenance, préparation du lancement). Elle reprend le logo, un texte mis en forme et, si vous le voulez, un compte à rebours jusqu’à une date.</p>
[[img:page-attente.webp|Le réglage de la page d’attente et son aperçu]]
<ul>
<li>Cochez <b>Activer la page d’attente</b>, rédigez le texte, enregistrez.</li>
<li><b>Aperçu de la page</b> montre ce que verront les visiteurs.</li>
<li>Connecté au back-office, vous voyez toujours le site normal ; les pages légales restent accessibles.</li>
</ul>
HTML],
    ],
];
