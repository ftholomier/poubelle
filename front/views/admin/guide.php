<?php
/**
 * Guide et formation du back-office, écrit pour le rôle « Éditeur » (contenus et demandes).
 * Captures d'écran annotées : public/assets/guide/*.webp (pastilles numérotées reprises dans le texte).
 * La progression (« J'ai compris ») est gardée dans le navigateur, par compte.
 */
$u = Auth::user();
$isAdmin = Auth::isAdmin($u);
$admins = array_values(array_filter(Auth::users(), fn($x) => ($x['role'] ?? '') === 'admin' && ($x['id'] ?? '') !== ($u['id'] ?? '')));
$siteName = (string) (Content::site()['name'] ?? "Renov'Est");
$adminUrl = Http::abs('/admin');

$img = fn(string $name): string => e(asset('/assets/guide/' . $name . '.webp'));
$fig = function (string $name, string $caption, string $cls = '') use ($img): string {
    return '<figure class="gfig' . ($cls !== '' ? ' ' . $cls : '') . '"><a href="' . $img($name) . '" data-zoom title="Agrandir">'
        . '<img src="' . $img($name) . '" alt="' . e($caption) . '" loading="lazy"></a><figcaption>' . e($caption) . '</figcaption></figure>';
};
$n = fn(int $i): string => '<span class="gn">' . $i . '</span>';

// Sommaire : identifiant => [titre, durée de lecture]
$chapters = [
    'essentiel' => ['L\'essentiel en 1 minute', '1 min'],
    'prise-en-main' => ['Prise en main', '3 min'],
    'compte' => ['Mon compte et mot de passe', '1 min'],
    'demandes' => ['Traiter les demandes', '5 min'],
    'conversations' => ['Conversations du bot', '1 min'],
    'bases' => ['Modifier un contenu : les bases', '4 min'],
    'accueil' => ['Page d\'accueil', '2 min'],
    'pages' => ['Pages', '2 min'],
    'produits' => ['Produits', '3 min'],
    'photos' => ['Photos produits', '2 min'],
    'realisations' => ['Réalisations', '2 min'],
    'actus' => ['Actualités', '4 min'],
    'medias' => ['Médiathèque', '2 min'],
    'menu' => ['Menu', '1 min'],
    'coordonnees' => ['Coordonnées', '2 min'],
    'bonnes-pratiques' => ['Bonnes pratiques', '3 min'],
    'faq' => ['Questions fréquentes', '3 min'],
];
$num = 0;
$open = function (string $id) use ($chapters, &$num): string {
    $num++;
    return '<section class="gch" id="' . e($id) . '" data-gch="' . e($id) . '"><div class="gch__h"><span class="gch__n">' . $num . '</span><div><h2>'
        . e($chapters[$id][0]) . '</h2><div class="gch__meta">Lecture : ' . e($chapters[$id][1]) . '</div></div></div>';
};
$close = fn(string $id): string => '<div class="gch__done"><a class="b b--l" href="#top-guide">↑ Sommaire</a><button class="b b--l" type="button" data-guide-done="' . e($id) . '">J\'ai compris ✓</button></div></section>';
?>
<div class="guide" id="top-guide" data-guide data-user="<?= e((string) ($u['id'] ?? '')) ?>" data-total="<?= count($chapters) ?>">
  <nav class="guide__toc" aria-label="Sommaire du guide">
    <b>Sommaire</b>
    <?php $i = 0; foreach ($chapters as $id => [$title]): $i++; ?>
      <a href="#<?= e($id) ?>" data-toc="<?= e($id) ?>"><i><?= $i ?></i><span><?= e($title) ?></span></a>
    <?php endforeach; ?>
  </nav>

  <div class="guide__main">
    <div class="guide__hero">
      <h1>Bienvenue dans le back-office <em><?= e($siteName) ?></em></h1>
      <p>Le back-office est l'espace privé qui fait vivre le site : c'est ici que vous traitez les demandes des clients et que vous mettez à jour les textes, les produits, les photos et les actualités.</p>
      <p>Ce guide vous forme pas à pas, avec des captures d'écran du vrai back-office. Les <span class="gn">1</span> pastilles numérotées sur les images renvoient aux explications. Comptez une demi-heure pour tout lire. Cliquez sur « J'ai compris » à la fin de chaque chapitre pour suivre votre progression.</p>
      <div class="guide__bar">
        <div class="guide__prog" aria-hidden="true"><i data-guide-bar></i></div>
        <small data-guide-count>0 / <?= count($chapters) ?> chapitres</small>
        <button class="b b--l" type="button" data-guide-print>Imprimer le guide</button>
      </div>
    </div>

    <input class="guide__search" type="search" placeholder="Chercher dans le guide : photo, actualité, mot de passe, devis…" data-guide-search aria-label="Chercher dans le guide">
    <p class="bo-muted" data-guide-empty hidden>Aucun chapitre ne parle de ce mot. Essayez un mot plus simple, ou posez la question à l'administrateur.</p>

    <?= $open('essentiel') ?>
      <p class="gch__lead">Si vous ne deviez retenir que sept choses :</p>
      <ol class="gsteps">
        <li><b>Les demandes d'abord.</b> Chaque jour, ouvrez <b>Demandes</b> et traitez les nouvelles : le badge jaune du menu indique combien attendent. Un client rappelé dans la journée signe beaucoup plus souvent.</li>
        <li><b>Rien n'est publié tant que vous n'avez pas cliqué sur <span class="btn-like btn-like--y">Enregistrer</span>.</b> Et tout est en ligne dès que vous avez cliqué : il n'y a pas d'étape de validation.</li>
        <li><b>Vérifiez toujours le résultat</b> avec le bouton <span class="btn-like">Voir la page ↗</span>, qui ouvre la page du site dans un nouvel onglet.</li>
        <li><b>Il n'y a pas de bouton « Annuler ».</b> Avant de transformer un long texte, copiez-le dans un document : vous pourrez le remettre si besoin.</li>
        <li><b>Ne changez jamais l'« Adresse » d'une page ou d'un produit existant</b> : Google la connaît, la modifier fait perdre des visites.</li>
        <li><b>Photos : en JPG, prises à l'horizontale, 15 Mo au maximum.</b> Le site les réduit et les optimise tout seul.</li>
        <li><b>Un doute ?</b> Le bouton <span class="btn-like"><span class="gn" style="width:16px;height:16px;font-size:10px;box-shadow:none">?</span> Aide</span> en haut de chaque écran ouvre directement le bon chapitre de ce guide.</li>
      </ol>
    <?= $close('essentiel') ?>

    <?= $open('prise-en-main') ?>
      <p class="gch__lead">Se connecter, se repérer dans les écrans et se déconnecter.</p>
      <h3>Se connecter</h3>
      <?= $fig('connexion', 'La page de connexion.', 'gfig--narrow') ?>
      <ol class="gsteps">
        <li>Ouvrez <b><?= e($adminUrl) ?></b> dans votre navigateur (pensez à l'ajouter à vos favoris).</li>
        <li>Saisissez votre adresse e-mail <?= $n(1) ?> et votre mot de passe <?= $n(2) ?>, puis cliquez sur <span class="btn-like btn-like--y">Se connecter</span>.</li>
        <li>Mot de passe oublié ? Cliquez sur le lien <?= $n(3) ?> : vous recevez par e-mail un lien pour en choisir un nouveau, valable une heure.</li>
      </ol>
      <div class="gtip gtip--warn"><b>Trop de tentatives</b>Après plusieurs mots de passe erronés, la connexion est bloquée 15 minutes. C'est une protection contre les pirates : patientez, ou utilisez « Mot de passe oublié ».</div>

      <h3>Le tableau de bord</h3>
      <p>C'est le premier écran après la connexion. Il résume l'activité du site.</p>
      <?= $fig('tableau-de-bord', 'Le tableau de bord et la barre du haut, présente sur tous les écrans.') ?>
      <table class="gtable">
        <tr><td><?= $n(1) ?></td><td><b>Le menu.</b> Toutes les rubriques, rangées en trois familles : <b>Suivi</b> (demandes des clients), <b>Contenus</b> (ce qui s'affiche sur le site) et <b>Site</b> (menu et coordonnées). Le badge jaune à côté de « Demandes » compte les demandes pas encore traitées.</td></tr>
        <tr><td><?= $n(2) ?></td><td><b>Les compteurs</b> : demandes à traiter, demandes du mois, actualités publiées, conversations avec l'assistant. Un clic ouvre la rubrique.</td></tr>
        <tr><td><?= $n(3) ?></td><td><b>Ce guide</b>, avec votre progression.</td></tr>
        <tr><td><?= $n(4) ?></td><td><b>Aide</b> : ouvre ce guide directement au chapitre de l'écran où vous êtes.</td></tr>
        <tr><td><?= $n(5) ?></td><td><b>Voir le site</b> : ouvre le site public dans un nouvel onglet.</td></tr>
        <tr><td><?= $n(6) ?></td><td><b>Mon compte</b> (votre initiale) : nom, e-mail et mot de passe.</td></tr>
        <tr><td><?= $n(7) ?></td><td><b>Déconnexion.</b> Pensez-y sur un ordinateur partagé.</td></tr>
      </table>
      <p>Le graphique montre le nombre de demandes reçues chaque jour sur deux semaines. En dessous, les dernières demandes : les lignes surlignées en jaune sont celles qui n'ont pas encore été traitées.</p>

      <h3>Sur téléphone ou tablette</h3>
      <p>Le back-office fonctionne aussi sur téléphone : pratique pour rappeler un client depuis un chantier. Le menu se cache derrière le bouton <b>☰</b> en haut à gauche.</p>
      <div style="display:flex;gap:18px;flex-wrap:wrap">
        <?= $fig('mobile', 'Les demandes sur téléphone.', 'gfig--phone') ?>
        <?= $fig('mobile-menu', 'Le menu ouvert avec ☰.', 'gfig--phone') ?>
      </div>
    <?= $close('prise-en-main') ?>

    <?= $open('compte') ?>
      <p class="gch__lead">Changer votre nom, votre adresse e-mail ou votre mot de passe.</p>
      <?= $fig('compte', 'La page « Mon compte », ouverte en cliquant sur votre initiale en haut à droite.', 'gfig--narrow') ?>
      <ol class="gsteps">
        <li>Cliquez sur votre initiale, en haut à droite de l'écran.</li>
        <li>Pour changer de mot de passe, tapez le nouveau <?= $n(1) ?>. Il doit faire <b>au moins 10 caractères</b>, avec des lettres et des chiffres.</li>
        <li>Tapez toujours votre mot de passe <b>actuel</b> <?= $n(2) ?> : c'est la preuve que c'est bien vous.</li>
        <li>Cliquez sur <span class="btn-like btn-like--y">Enregistrer</span>. Vos autres connexions (autre ordinateur, téléphone) sont alors fermées.</li>
      </ol>
      <div class="gtip"><b>Un bon mot de passe</b>Une petite phrase facile à retenir, avec un chiffre : par exemple « Volets-bleus-2026 ». Ne le notez pas sur un post-it collé à l'écran, et ne le partagez pas : chaque personne doit avoir son propre compte.</div>
    <?= $close('compte') ?>

    <?= $open('demandes') ?>
      <p class="gch__lead">Le cœur du back-office : chaque demande est un client potentiel. Voici comment ne jamais en perdre une.</p>
      <h3>D'où viennent les demandes ?</h3>
      <table class="gtable">
        <tr><th>Type</th><th>Ce que le visiteur a fait sur le site</th></tr>
        <tr><td><span class="tag tag--devis">Devis</span></td><td>Il a rempli le formulaire de devis en 3 étapes (produits, détails, coordonnées), ou le formulaire de contact avec l'objet « Devis ».</td></tr>
        <tr><td><span class="tag tag--contact">Contact</span></td><td>Il a envoyé un message par le formulaire de contact.</td></tr>
        <tr><td><span class="tag tag--rappel">Rappel</span></td><td>Il allait quitter le site, une fenêtre lui a proposé d'être rappelé, et il a laissé son numéro.</td></tr>
        <tr><td><span class="tag tag--vocal">Vocal</span></td><td>Il a enregistré un message au micro avec le bouton rouge « Laissez un vocal ».</td></tr>
      </table>
      <p>Chaque demande arrive à deux endroits : dans la rubrique <b>Demandes</b>, et par <b>e-mail</b> à l'adresse de l'entreprise. L'e-mail contient toutes les informations : vous pouvez traiter une demande sans ouvrir le back-office.</p>
      <?= $fig('email', 'L\'e-mail reçu pour une demande de devis. Sur téléphone, « Appeler » lance l\'appel ; « Répondre » écrit directement au client.', 'gfig--narrow') ?>

      <h3>L'écran des demandes</h3>
      <?= $fig('demandes', 'La liste des demandes à gauche, le détail de la demande choisie à droite.') ?>
      <table class="gtable">
        <tr><td><?= $n(1) ?></td><td><b>Recherche et filtres</b> : retrouvez une demande par nom, téléphone, e-mail, code postal ou mot du message. Les listes permettent de n'afficher qu'un type (Devis, Vocal…) ou qu'un statut. Cliquez sur <span class="btn-like">Filtrer</span>.</td></tr>
        <tr><td><?= $n(2) ?></td><td><b>La liste</b>, de la plus récente à la plus ancienne. Les lignes jaunes sont les nouvelles demandes. Cliquez sur une ligne pour l'ouvrir.</td></tr>
        <tr><td><?= $n(3) ?></td><td><b>Les coordonnées et le projet</b> : téléphone et e-mail sont cliquables (appel ou e-mail direct), puis les produits, la quantité, l'échéance, le code postal du chantier, et la page d'où le client a écrit. Le message du client est juste en dessous.</td></tr>
        <tr><td><?= $n(4) ?></td><td><b>Le statut</b>, à faire avancer au fil du traitement (voir ci-dessous).</td></tr>
        <tr><td><?= $n(5) ?></td><td><b>Les notes internes</b> : « rappelé le 9, visite jeudi 14 h ». Elles ne sont visibles que dans le back-office, jamais par le client. Chaque note garde votre nom et l'heure.</td></tr>
        <tr><td><?= $n(6) ?></td><td><b>Supprimer</b> : définitif. Préférez le statut « Archivé ».</td></tr>
        <tr><td><?= $n(7) ?></td><td><b>Exporter (CSV)</b> : télécharge toutes les demandes dans un fichier qui s'ouvre avec Excel.</td></tr>
      </table>

      <h3>Traiter une demande, pas à pas</h3>
      <ol class="gsteps">
        <li>Ouvrez la demande (ligne jaune) et lisez tout, message compris.</li>
        <li>Appelez le client : cliquez sur son numéro depuis un téléphone. Cliquez sur <span class="btn-like">En cours</span>.</li>
        <li>Écrivez une note avec ce qui a été dit et la suite prévue, puis cliquez sur <span class="btn-like btn-like--y">Ajouter la note</span>.</li>
        <li>Quand le devis est envoyé ou le rendez-vous fixé, cliquez sur <span class="btn-like">Traité</span>.</li>
      </ol>
      <table class="gtable">
        <tr><th>Statut</th><th>Quand l'utiliser</th></tr>
        <tr><td><span class="st st--nouveau">Nouveau</span></td><td>Personne ne s'en est encore occupé. Compté dans le badge jaune du menu.</td></tr>
        <tr><td><span class="st st--encours">En cours</span></td><td>Le client a été contacté, l'affaire est en route (visite, métré, devis à faire).</td></tr>
        <tr><td><span class="st st--traite">Traité</span></td><td>Devis envoyé, rendez-vous fixé ou réponse donnée : plus rien à faire pour l'instant.</td></tr>
        <tr><td><span class="st st--archive">Archivé</span></td><td>Sans suite : doublon, erreur, client injoignable après plusieurs essais.</td></tr>
        <tr><td><span class="st st--spam">Spam</span></td><td>Publicité ou envoi de robot. Le site y range tout seul les demandes suspectes (voir ci-dessous) ; vous pouvez aussi y mettre un démarchage passé entre les mailles.</td></tr>
      </table>
      <div class="gtip gtip--ok"><b>Le bon réflexe</b>Rappelez chaque nouvelle demande dans la journée, même pour dire « je reviens vers vous demain ». Le client a souvent demandé plusieurs devis : le premier qui rappelle prend une longueur d'avance.</div>

      <h3>Le dossier Spam</h3>
      <p>Le site trie automatiquement les envois suspects : publicités en anglais pour du référencement, messages pleins de liens, robots qui remplissent les formulaires en une seconde… Ces demandes ne déclenchent <b>pas d'e-mail</b> et n'apparaissent pas dans la liste habituelle : elles sont rangées à part, dans le statut « Spam ».</p>
      <ul>
        <li>Un bouton <span class="btn-like">Spam (3)</span> apparaît à droite des filtres quand il y en a : il ouvre ce dossier.</li>
        <li>Chaque spam indique pourquoi il a été écarté (« Message rédigé en anglais », « 2 liens dans le message »…).</li>
        <li>Si une vraie demande s'y est glissée, ouvrez-la et cliquez sur <span class="btn-like">Ce n'est pas un spam</span> : elle repasse en « Nouveau » et l'e-mail habituel part aussitôt.</li>
        <li>Les spams s'effacent tout seuls au bout de 30 jours : pas besoin de les supprimer.</li>
      </ul>
      <div class="gtip"><b>Un coup d'œil par semaine</b>Le tri se trompe très rarement, mais un client qui écrit en anglais, colle plusieurs liens ou laisse un numéro étranger peut s'y retrouver. Jetez un œil au dossier Spam de temps en temps.</div>

      <h3>Les messages vocaux</h3>
      <?= $fig('demande-vocal', 'Le détail d\'un message vocal.', 'gfig--narrow') ?>
      <ul>
        <li><?= $n(1) ?> <b>Écoutez</b> le message avec le lecteur, ou cliquez sur <span class="btn-like">Télécharger</span> pour garder le fichier.</li>
        <li><?= $n(2) ?> <b>Le résumé IA</b> donne l'essentiel en trois lignes : projet, produits, lieu et délai.</li>
        <li><?= $n(3) ?> <b>La transcription</b> est le texte du message, écrit automatiquement. Elle peut se tromper sur un nom, une commune ou un numéro : en cas de doute, écoutez.</li>
        <li><?= $n(4) ?> Le numéro laissé par le client, cliquable pour rappeler.</li>
      </ul>
      <div class="gtip gtip--warn"><b>« E-mail de notification non envoyé »</b>Si ce message rouge apparaît dans une demande, l'e-mail n'est pas parti : la demande est bien enregistrée ici, mais prévenez l'administrateur pour qu'il vérifie l'envoi des e-mails.</div>
    <?= $close('demandes') ?>

    <?= $open('conversations') ?>
      <p class="gch__lead">Sur le site, un assistant répond aux questions des visiteurs à toute heure. Ici, vous lisez ce qu'ils lui ont demandé.</p>
      <?= $fig('conversations', 'Les conversations avec l\'assistant, de la plus récente à la plus ancienne.') ?>
      <ul>
        <li><?= $n(1) ?> Cliquez sur une conversation pour la déplier : date, nombre de messages et première question.</li>
        <li><?= $n(2) ?> En jaune, la question du visiteur. <?= $n(3) ?> En gris, la réponse de l'assistant.</li>
      </ul>
      <div class="gtip"><b>À quoi ça sert ?</b>C'est une mine d'idées : si plusieurs visiteurs posent la même question (prix, délais, une commune, un produit), ajoutez la réponse sur la page concernée ou écrivez une actualité. Si une réponse de l'assistant vous paraît fausse, notez-la et prévenez l'administrateur : c'est lui qui règle l'assistant.</div>
    <?= $close('conversations') ?>

    <?= $open('bases') ?>
      <p class="gch__lead">Toutes les rubriques de contenu fonctionnent de la même façon. Une fois ce chapitre compris, vous savez tout modifier.</p>
      <h3>Modifier puis enregistrer</h3>
      <?= $fig('formulaire', 'Un écran de modification (ici, la page d\'accueil).') ?>
      <ol class="gsteps">
        <li>Ouvrez la rubrique dans le menu. Les champs sont rangés par blocs <?= $n(1) ?>, dans l'ordre où ils apparaissent sur le site.</li>
        <li>Cliquez dans un champ <?= $n(2) ?> et modifiez le texte. Un astérisque * signale un champ obligatoire ; les petites lignes grises sous un champ donnent un conseil.</li>
        <li>Cliquez sur <span class="btn-like btn-like--y">Enregistrer</span> <?= $n(3) ?>. Ce bouton reste toujours visible en bas de l'écran, même dans les longues pages.</li>
        <li>Un message de confirmation apparaît en bas à droite :</li>
      </ol>
      <?= $fig('enregistre', 'Le message de confirmation : la modification est en ligne.', 'gfig--narrow') ?>
      <ol class="gsteps" start="5" style="counter-reset: gs 4">
        <li>Cliquez sur <span class="btn-like">Voir la page ↗</span> <?= $n(4) ?> pour vérifier le résultat sur le site. Si la page était déjà ouverte, rechargez-la (touche <kbd>F5</kbd>, ou tirez l'écran vers le bas sur téléphone).</li>
      </ol>
      <div class="gtip gtip--warn"><b>Rien n'est gardé sans « Enregistrer »</b>Si vous quittez l'écran sans enregistrer, le navigateur vous demande confirmation : choisissez « Rester » puis enregistrez. Si un message rouge apparaît, rien n'a été enregistré : lisez-le, il dit quoi corriger.</div>

      <h3>L'éditeur de texte</h3>
      <p>Les textes longs (contenu d'une page, d'un produit, d'une actualité) se modifient comme dans un traitement de texte.</p>
      <?= $fig('editeur-texte', 'L\'éditeur de texte : la barre d\'outils et la zone de texte.') ?>
      <p>Sélectionnez un mot ou une phrase, puis cliquez sur un bouton de la barre <?= $n(1) ?>. Le texte s'écrit dans la zone <?= $n(2) ?>.</p>
      <table class="gtable">
        <tr><th>Bouton</th><th>Effet</th></tr>
        <tr><td><kbd>G</kbd> <kbd>I</kbd></td><td>Gras, italique. Le gras met en valeur quelques mots clés, pas des phrases entières.</td></tr>
        <tr><td><kbd>T2</kbd> <kbd>T3</kbd></td><td>Titre et sous-titre. Découpez les longs textes en parties avec un titre T2 : c'est plus lisible, et Google aime ça.</td></tr>
        <tr><td><kbd>¶</kbd></td><td>Remet un titre en paragraphe normal.</td></tr>
        <tr><td><kbd>• Liste</kbd></td><td>Liste à puces.</td></tr>
        <tr><td><kbd>Lien</kbd> <kbd>⨯ Lien</kbd></td><td>Transforme la sélection en lien (collez l'adresse : <code>https://…</code> pour un autre site, <code>/devis/</code> pour une page du site), ou retire le lien.</td></tr>
        <tr><td><kbd>Image</kbd></td><td>Insère une image de la médiathèque à l'endroit du curseur.</td></tr>
        <tr><td><kbd>Tx</kbd></td><td>Efface la mise en forme de la sélection.</td></tr>
        <tr><td><kbd>HTML</kbd> <?= $n(3) ?></td><td>Affiche le code de la page. Réservé aux connaisseurs : n'y touchez pas.</td></tr>
      </table>
      <div class="gtip"><b>Copier depuis Word ou un e-mail</b>Le texte collé arrive sans sa mise en forme : c'est voulu, cela garde le site propre. Remettez ensuite les titres, le gras et les listes avec la barre d'outils.</div>

      <h3>Choisir une image</h3>
      <p>Les champs image ont un bouton <span class="btn-like">Choisir…</span>, qui ouvre la médiathèque :</p>
      <?= $fig('choisir-image', 'La fenêtre de choix d\'image.') ?>
      <ol class="gsteps">
        <li>Tapez une partie du nom de l'image pour la retrouver <?= $n(1) ?>.</li>
        <li>Pour une nouvelle photo, cliquez sur <span class="btn-like btn-like--y">Téléverser</span> <?= $n(2) ?> et choisissez le fichier sur votre ordinateur ou votre téléphone.</li>
        <li>Cliquez sur l'image voulue <?= $n(3) ?>. Quand on peut en choisir plusieurs (actualités), cochez-les une à une puis cliquez sur <span class="btn-like btn-like--y">Utiliser</span> <?= $n(4) ?>.</li>
        <li>Le bouton <span class="btn-like">Retirer</span>, à côté de « Choisir… », enlève l'image du champ (le fichier reste dans la médiathèque).</li>
      </ol>

      <h3>Les listes : ajouter, déplacer, supprimer</h3>
      <p>Beaucoup d'écrans contiennent des listes d'éléments du même genre : photos de chantiers, chiffres clés, agences, entrées du menu…</p>
      <?= $fig('liste-repetable', 'Une liste (ici, les photos de chantiers).') ?>
      <ul>
        <li><?= $n(1) ?> Chaque élément est un cadre.</li>
        <li><?= $n(2) ?> Les flèches <kbd>↑</kbd> <kbd>↓</kbd> le déplacent (l'ordre est celui du site) ; la croix <kbd>×</kbd> le supprime.</li>
        <li><?= $n(3) ?> <span class="btn-like">Choisir…</span> change la photo de cet élément.</li>
        <li><?= $n(4) ?> Le bouton <span class="btn-like">+ Ajouter…</span> crée un nouvel élément à la fin de la liste.</li>
      </ul>
      <div class="gtip"><b>N'oubliez pas d'enregistrer</b>Ajouts, déplacements et suppressions ne sont pris en compte qu'après un clic sur Enregistrer. Une croix cliquée par erreur ? Quittez l'écran sans enregistrer (choisissez « Quitter la page ») : rien n'est perdu.</div>
      <p>Certains champs indiquent <b>« Une ligne par élément »</b> : tapez un élément par ligne, avec la touche <kbd>Entrée</kbd> entre chacun.</p>

      <h3>Le bloc « Référencement (Google) »</h3>
      <p>En bas de la plupart des pages se trouvent deux champs facultatifs, qui ne s'affichent pas dans la page mais dans les résultats de Google :</p>
      <ul>
        <li><b>Titre de la page (balise title)</b> : le titre bleu cliquable dans Google. Environ 60 caractères, avec le produit et la ville : « Pergola bioclimatique à Montbéliard | Renov'Est ».</li>
        <li><b>Description (meta description)</b> : les deux lignes sous le titre. Environ 150 caractères qui donnent envie de cliquer.</li>
      </ul>
      <p>Laissés vides, ils sont remplis automatiquement à partir du titre et du texte de la page.</p>
    <?= $close('bases') ?>

    <?= $open('accueil') ?>
      <p class="gch__lead">La page la plus vue du site. Ses blocs se modifient dans l'ordre où ils apparaissent, de haut en bas.</p>
      <table class="gtable">
        <tr><th>Bloc</th><th>Ce qu'on y règle</th></tr>
        <tr><td>Héros (vidéo plein écran)</td><td>La pastille au-dessus du titre, le grand titre (H1), le <b>mot en jaune</b>, le paragraphe et la ligne des ✓ (une ligne par argument). Le mot en jaune doit être écrit exactement comme dans le titre.</td></tr>
        <tr><td>Chiffres clés</td><td>Les compteurs animés. Chaque chiffre a un « Avant » (ex. <code>+</code>), une « Valeur » (ex. <code>30</code>), un « Après » (ex. <code> ans</code>) et un libellé.</td></tr>
        <tr><td>Section « Nos produits »</td><td>Sur-titre et titre. Les cartes produits viennent de la rubrique Produits.</td></tr>
        <tr><td>Notre philosophie</td><td>Le titre et les engagements (titre + texte de chaque engagement).</td></tr>
        <tr><td>Présentation</td><td>Le bloc gris avec photo : textes, bouton et photo.</td></tr>
        <tr><td>Avis et actualités</td><td>Seulement les titres de ces sections : les avis et les actualités se mettent à jour tout seuls.</td></tr>
        <tr><td>Bloc final (noir)</td><td>Le dernier appel à demander un devis : titre, texte, bouton.</td></tr>
      </table>
      <div class="gtip gtip--warn"><b>Vidéo et image du héros</b>La vidéo d'accueil est un gros fichier préparé spécialement. Ne la changez pas sans en parler à l'administrateur.</div>
      <div class="gtip"><b>Des titres courts</b>Le grand titre doit se lire d'un coup d'œil, sur téléphone aussi : une dizaine de mots au maximum. Vérifiez toujours avec « Voir la page ».</div>
    <?= $close('accueil') ?>

    <?= $open('pages') ?>
      <p class="gch__lead">Les autres pages du site : présentation, produits, réalisations, actualités, contact, devis, mentions légales…</p>
      <?= $fig('pages', 'La liste des pages du site.') ?>
      <ul>
        <li><?= $n(1) ?> La page d'accueil a son propre écran (chapitre précédent).</li>
        <li><?= $n(2) ?> <span class="btn-like">Modifier</span> ouvre les textes de la page : bandeau du haut (sur-titre, chapeau), titre affiché (H1), introduction, contenu…</li>
        <li><?= $n(3) ?> <span class="btn-like">Voir ↗</span> ouvre la page sur le site.</li>
      </ul>
      <p>La colonne « Adresse » montre l'adresse de chaque page. Elles reprennent exactement celles de l'ancien site, pour garder le référencement acquis auprès de Google.</p>
      <div class="gtip gtip--warn"><b>Ne modifiez pas les adresses</b>Le champ « Adresse » existe dans chaque page, mais une adresse changée, c'est une page que Google ne retrouve plus, et des liens cassés partout où elle était citée. Laissez-les telles quelles.</div>
      <p>Certaines pages ont surtout un bandeau et une introduction, leur contenu étant automatique : « Nos produits » liste les produits, « Nos réalisations » les photos de chantiers, « L'actualité » les actualités, « Nos derniers avis » les avis clients.</p>
    <?= $close('pages') ?>

    <?= $open('produits') ?>
      <p class="gch__lead">Chaque produit a sa page, sa carte sur l'accueil et sa place dans le sous-menu « Nos produits ».</p>
      <?= $fig('produits', 'La liste des produits.') ?>
      <table class="gtable">
        <tr><td><?= $n(1) ?></td><td><b>+ Nouveau produit</b> : crée une nouvelle page produit.</td></tr>
        <tr><td><?= $n(2) ?></td><td><b>Les flèches ↑ ↓</b> changent l'ordre des produits (menu, accueil, page « Nos produits »). L'ordre est enregistré dès le clic.</td></tr>
        <tr><td><?= $n(3) ?></td><td><b>Le statut</b> : « En ligne » ou « Masqué ».</td></tr>
        <tr><td><?= $n(4) ?></td><td><b>Voir ↗</b> ouvre la page du produit sur le site.</td></tr>
        <tr><td><?= $n(5) ?></td><td><b>Modifier</b> ouvre la fiche du produit.</td></tr>
      </table>
      <h3>La fiche d'un produit</h3>
      <?= $fig('produit', 'Le haut de la fiche d\'un produit.') ?>
      <ul>
        <li><?= $n(1) ?> <b>Nom du produit</b> : celui du menu et des cartes.</li>
        <li><?= $n(2) ?> <b>Adresse de la page</b> : ne la changez pas sur un produit existant. Pour un nouveau produit, laissez-la vide : elle est créée à partir du nom.</li>
        <li><?= $n(3) ?> <b>Afficher sur le site</b> : décochez pour cacher le produit sans le supprimer (gamme arrêtée, page en préparation).</li>
        <li><?= $n(4) ?> <b>Résumé</b> : deux ou trois phrases, reprises sur les cartes et en tête de page.</li>
      </ul>
      <p>Plus bas : le titre de la page (H1), le titre dans la liste, les points forts (une ligne par point), la <b>photo principale</b>, le <b>pictogramme</b>, la <b>gamme</b> qui filtre la galerie photos, le choix <b>présélectionné dans le formulaire de devis</b> quand on vient de cette page, et le <b>contenu</b> complet de la page.</p>
      <h3>Créer un produit</h3>
      <ol class="gsteps">
        <li>Cliquez sur <span class="btn-like btn-like--y">+ Nouveau produit</span>.</li>
        <li>Remplissez au moins le nom, le résumé, la photo principale et le contenu. Laissez l'adresse vide.</li>
        <li>Pour le préparer tranquillement, décochez « Afficher sur le site », enregistrez, et cochez-le le jour où il est prêt.</li>
        <li>Enregistrez : le produit apparaît automatiquement dans le menu, sur l'accueil et sur la page « Nos produits ».</li>
      </ol>
      <div class="gtip gtip--warn"><b>Supprimer un produit</b>Le bouton rouge <span class="btn-like">Supprimer</span>, en bas de la fiche, efface la page pour de bon, et son adresse ne mène plus nulle part. Pour une gamme arrêtée, préférez décocher « Afficher sur le site ».</div>
    <?= $close('produits') ?>

    <?= $open('photos') ?>
      <p class="gch__lead">La galerie de la page « Nos produits » et de chaque page produit, classée par gamme.</p>
      <?= $fig('photos', 'En haut : les gammes, qui servent de filtres dans la galerie.') ?>
      <p><?= $n(1) ?> <b>Les gammes</b> sont les boutons de filtre de la galerie (« Menuiserie PVC / ALU », « Portes d'entrée »…). Chaque gamme a un identifiant technique (en minuscules, sans espace) et un libellé affiché. Ne changez pas l'identifiant d'une gamme existante : les photos y sont rattachées par lui.</p>
      <?= $fig('photos-liste', 'Plus bas : les photos, chacune rattachée à une gamme.') ?>
      <ol class="gsteps">
        <li>Descendez jusqu'à la liste des photos et cliquez sur <span class="btn-like">+ Ajouter une photo</span>, tout en bas.</li>
        <li>Cliquez sur <span class="btn-like">Choisir…</span> et téléversez ou choisissez la photo.</li>
        <li>Choisissez sa <b>gamme</b> dans la liste : c'est elle qui décide sur quelle page produit la photo apparaît.</li>
        <li>Enregistrez.</li>
      </ol>
      <div class="gtip"><b>Les plus belles d'abord</b>La galerie suit l'ordre de la liste : remontez vos meilleures photos avec la flèche ↑.</div>
    <?= $close('photos') ?>

    <?= $open('realisations') ?>
      <p class="gch__lead">La page « Nos réalisations » : vos chantiers en photos. C'est souvent ce qui décide un client.</p>
      <?= $fig('liste-repetable', 'L\'écran des réalisations.') ?>
      <ol class="gsteps">
        <li>Cliquez sur <span class="btn-like">+ Ajouter une photo</span> <?= $n(4) ?>, en bas de la liste.</li>
        <li>Cliquez sur <span class="btn-like">Choisir…</span> <?= $n(3) ?> et ajoutez la photo du chantier.</li>
        <li>Si vous le souhaitez, ajoutez un titre (« Pergola bioclimatique ») et un lieu (« Audincourt ») : ils s'affichent en légende sur la photo. Le lieu seul, sans titre, ne s'affiche pas.</li>
        <li>Placez la photo avec les flèches <?= $n(2) ?> : la première de la liste est la première affichée. Puis enregistrez.</li>
      </ol>
      <div class="gtip"><b>Une photo de chantier qui donne envie</b>Prise de jour, à l'horizontale, chantier rangé, sans voiture ni personne reconnaissable. Une paire avant / après fait toujours beaucoup d'effet. Demandez au client s'il est d'accord pour que sa maison apparaisse, et n'écrivez pas l'adresse exacte : la commune suffit.</div>
    <?= $close('realisations') ?>

    <?= $open('actus') ?>
      <p class="gch__lead">Les actualités montrent que l'entreprise est active : portes ouvertes, chantiers terminés, offres du moment, conseils. Elles comptent aussi pour Google.</p>
      <?= $fig('actus', 'La liste des actualités.') ?>
      <ul>
        <li><?= $n(1) ?> <span class="btn-like btn-like--y">+ Nouvelle actualité</span> crée une actualité.</li>
        <li><?= $n(2) ?> Les actualités, de la plus récente à la plus ancienne, avec leur vignette, leur catégorie et leur origine.</li>
        <li><?= $n(3) ?> Le statut : <span class="st st--traite">En ligne</span>, <span class="st st--encours">Programmée</span> (date à venir) ou <span class="st st--archive">Masquée</span>.</li>
        <li><?= $n(4) ?> <span class="btn-like">Modifier</span> ouvre l'actualité.</li>
      </ul>
      <h3>Publier une actualité, pas à pas</h3>
      <?= $fig('actu', 'Le haut d\'une nouvelle actualité.') ?>
      <ol class="gsteps">
        <li><?= $n(1) ?> <b>Titre</b> : court et précis (« Portes ouvertes les 14 et 15 mars »).</li>
        <li><?= $n(2) ?> <b>Date de publication</b>, au format année-mois-jour : <code>2026-03-14</code>. Une date à venir <b>programme</b> l'actualité : elle apparaîtra toute seule ce jour-là.</li>
        <li><?= $n(3) ?> <b>Catégorie</b> : elle sert de filtre sur la page des actualités.</li>
        <li><?= $n(4) ?> <b>Publiée sur le site</b> : décochez pour garder l'actualité en brouillon.</li>
        <li><?= $n(5) ?> <b>Images</b> : cliquez sur <span class="btn-like">+ Ajouter</span> et choisissez une ou plusieurs photos. <b>La première sert de vignette</b> dans les listes ; les flèches ← → changent l'ordre et × retire une image. Sur le site, les images s'affichent en entier, sans être coupées.</li>
        <li><?= $n(6) ?> <b>Chapeau</b> (facultatif) : une ou deux phrases d'introduction, en gros sous le titre.</li>
      </ol>
      <?= $fig('actu-texte', 'Le bas de l\'actualité.') ?>
      <ol class="gsteps" start="7" style="counter-reset: gs 6">
        <li><?= $n(7) ?> <b>Texte</b> : le contenu de l'actualité, avec l'éditeur de texte (titres, listes, liens).</li>
        <li><?= $n(8) ?> <b>Extrait des cartes</b> (facultatif) : le petit texte des cartes d'actualité. Vide, il reprend le début du texte.</li>
        <li><?= $n(9) ?> <span class="btn-like btn-like--y">Enregistrer</span>, puis vérifiez avec « Voir la page ».</li>
      </ol>
      <p>Le champ « Adresse » peut rester vide : elle est créée à partir du titre. Si l'actualité a aussi été publiée sur Facebook, collez le lien de la publication dans le champ prévu : un lien « Voir sur Facebook » s'ajoute sous l'article.</p>
      <?= $fig('site-actu', 'Le résultat sur le site : l\'image s\'affiche en entier, le texte en dessous.') ?>
      <div class="gtip gtip--ok"><b>Des idées d'actualités</b>Un chantier terminé (avant / après), une nouveauté produit, une offre du mois, les portes ouvertes, un salon, un conseil de saison (« Préparer ses volets avant l'hiver »), l'arrivée d'un collaborateur. Le bon rythme : deux par mois.</div>
    <?= $close('actus') ?>

    <?= $open('medias') ?>
      <p class="gch__lead">Toutes les images et vidéos du site, au même endroit.</p>
      <?= $fig('medias', 'La médiathèque.') ?>
      <table class="gtable">
        <tr><td><?= $n(1) ?></td><td><b>Ajouter des fichiers</b> : glissez-les dans le cadre depuis votre ordinateur, ou cliquez sur <span class="btn-like btn-like--y">Choisir des fichiers</span>. Formats acceptés : JPG, PNG, WebP, GIF (15 Mo au maximum) et MP4 pour la vidéo. Les grandes photos sont réduites automatiquement.</td></tr>
        <tr><td><?= $n(2) ?></td><td><b>Les dossiers</b> : « Téléversés » contient vos ajouts, « Importés de renovest.fr » les images reprises de l'ancien site.</td></tr>
        <tr><td><?= $n(3) ?></td><td><b>Filtrer par nom</b> pour retrouver une image.</td></tr>
        <tr><td><?= $n(4) ?></td><td><b>Une vignette</b> : un clic ouvre l'image en grand.</td></tr>
        <tr><td><?= $n(5) ?></td><td><b>Copier le lien</b> de l'image, à coller dans un champ image ou un lien. Sur vos propres fichiers, un bouton <b>Supprimer</b> apparaît aussi.</td></tr>
      </table>
      <div class="gtip"><b>Bien nommer ses photos</b>Avant d'envoyer une photo, renommez-la sur votre ordinateur : <code>pergola-bioclimatique-audincourt.jpg</code> plutôt que <code>IMG_4521.jpg</code>. Vous la retrouverez plus facilement, et Google la comprend mieux.</div>
      <div class="gtip gtip--warn"><b>Avant de supprimer un fichier</b>Vérifiez qu'il n'est plus utilisé : une image supprimée laisse un trou partout où elle s'affichait (page, produit, actualité).</div>
      <div class="gtip gtip--warn"><b>Photos d'iPhone</b>Le format HEIC des iPhone n'est pas accepté. Sur l'iPhone : Réglages › Appareil photo › Formats › « Le plus compatible ». Les photos seront alors en JPG.</div>
    <?= $close('medias') ?>

    <?= $open('menu') ?>
      <p class="gch__lead">Le menu principal, en haut de toutes les pages du site.</p>
      <?= $fig('menu', 'Les entrées du menu.') ?>
      <ul>
        <li><?= $n(1) ?> Les flèches changent l'ordre des entrées ; la croix en retire une.</li>
        <li><?= $n(2) ?> <b>Page</b> : la page vers laquelle mène l'entrée.</li>
        <li><?= $n(3) ?> <b>Libellé</b> : le texte affiché dans le menu, aussi court que possible.</li>
        <li><?= $n(4) ?> <b>Visible</b> : décochez pour cacher l'entrée sans la supprimer.</li>
      </ul>
      <p>Le sous-menu de « Nos produits » se remplit tout seul avec les produits en ligne, dans l'ordre de la rubrique Produits.</p>
      <div class="gtip"><b>Peu d'entrées, des mots courts</b>Le menu a une place limitée : au-delà de sept entrées, ou avec des libellés longs, il risque de ne plus tenir sur une ligne. Vérifiez sur ordinateur et sur téléphone.</div>
    <?= $close('menu') ?>

    <?= $open('coordonnees') ?>
      <p class="gch__lead">Téléphones, horaires, agences : ces informations s'affichent à de nombreux endroits (haut et bas de chaque page, page Contact, mentions légales) et l'assistant du site s'en sert pour répondre.</p>
      <?= $fig('coordonnees', 'Le haut de l\'écran « Coordonnées ».') ?>
      <ul>
        <li><?= $n(1) ?> <b>Entreprise</b> : nom, raison sociale, phrase du pied de page, SIRET, directeur de la publication (mentions légales).</li>
        <li><?= $n(2) ?> <b>Contact</b> : le téléphone principal (affiché en haut de chaque page), le service technique / SAV, le fax et l'e-mail affiché.</li>
        <li><?= $n(3) ?> <b>Horaires</b>, tels qu'ils doivent apparaître sur le site.</li>
      </ul>
      <?= $fig('agences', 'Plus bas : les agences.') ?>
      <ul>
        <li><?= $n(1) ?> Chaque agence : nom, ville, adresse, code postal, commune, et si besoin un téléphone ou des horaires différents.</li>
        <li><?= $n(2) ?> <span class="btn-like">+ Ajouter une agence</span> pour en créer une.</li>
        <li><?= $n(3) ?> <b>La carte Google</b> de la page Contact. Pour l'obtenir : sur Google Maps, cherchez l'agence, cliquez sur <b>Partager</b>, puis <b>Intégrer une carte</b> ; dans le code proposé, copiez seulement l'adresse entre les guillemets qui suivent <code>src=</code> (elle commence par <code>https://www.google.com/maps/embed</code>).</li>
      </ul>
      <div class="gtip gtip--warn"><b>Relisez deux fois</b>Une erreur dans un numéro de téléphone, et ce sont des appels perdus sur toutes les pages. Après chaque changement, vérifiez le haut et le bas du site, et la page Contact.</div>
      <div class="gtip"><b>Fermeture exceptionnelle</b>Congés, jour férié : modifiez les horaires quelques jours avant (« Fermé du 4 au 18 août »), puis remettez-les à votre retour.</div>
    <?= $close('coordonnees') ?>

    <?= $open('bonnes-pratiques') ?>
      <p class="gch__lead">Quelques habitudes qui font un site plus beau, mieux placé sur Google, et qui rapporte plus de demandes.</p>
      <h3>Les photos</h3>
      <ul>
        <li>À l'horizontale, nettes, lumineuses, prises de jour. Évitez les captures d'écran et les photos floues ou sombres.</li>
        <li>Uniquement vos propres photos (ou celles des fabricants avec leur accord) : une image prise sur Internet peut coûter cher en droits d'auteur.</li>
        <li>Pas de visage reconnaissable ni de plaque d'immatriculation sans accord.</li>
        <li>Renommez le fichier avant l'envoi (produit + commune).</li>
      </ul>
      <h3>Les textes</h3>
      <ul>
        <li>Des phrases courtes, des mots simples. Un titre T2 tous les deux ou trois paragraphes.</li>
        <li>Citez naturellement les produits et les communes (« pose de volets roulants à Montbéliard ») : c'est ce que les clients tapent dans Google.</li>
        <li>Pas de texte entier en MAJUSCULES, ni en gras.</li>
        <li>Relisez-vous, et faites relire les textes importants.</li>
      </ul>
      <h3>Votre routine</h3>
      <table class="gtable">
        <tr><th>Quand</th><th>Quoi</th></tr>
        <tr><td><b>Chaque jour</b></td><td>Traiter les nouvelles demandes (et vérifier les « En cours » qui attendent une suite).</td></tr>
        <tr><td><b>Chaque semaine</b></td><td>Ajouter les photos des chantiers terminés. Lire les conversations avec l'assistant.</td></tr>
        <tr><td><b>Deux fois par mois</b></td><td>Publier une actualité.</td></tr>
        <tr><td><b>Chaque mois</b></td><td>Vérifier les horaires, les téléphones et les offres en cours sur la page d'accueil.</td></tr>
        <tr><td><b>Chaque saison</b></td><td>Mettre en avant les bons produits : stores et pergolas au printemps, portes et fenêtres à l'automne.</td></tr>
      </table>
    <?= $close('bonnes-pratiques') ?>

    <?= $open('faq') ?>
      <p class="gch__lead">Les situations les plus courantes et leur solution.</p>
      <h3>Je ne vois pas ma modification sur le site</h3>
      <p>Vérifiez que le message de confirmation est apparu après « Enregistrer ». Puis rechargez la page du site : touche <kbd>F5</kbd> (ou <kbd>Ctrl</kbd> + <kbd>F5</kbd>), ou tirez l'écran vers le bas sur téléphone.</p>
      <h3>« Session expirée : reconnectez-vous »</h3>
      <p>Après une longue pause, ou après une déconnexion, la session se ferme. Avant de vous reconnecter, copiez le texte que vous étiez en train d'écrire (<kbd>Ctrl</kbd> + <kbd>A</kbd> puis <kbd>Ctrl</kbd> + <kbd>C</kbd> dans le champ) : vous pourrez le recoller.</p>
      <h3>Ma photo ne s'envoie pas</h3>
      <p>Elle est sans doute trop lourde (plus de 15 Mo) ou dans un format refusé (HEIC d'iPhone, PDF, Word). Enregistrez-la en JPG, ou réduisez-la, puis recommencez.</p>
      <h3>J'ai effacé ou cassé quelque chose par erreur</h3>
      <p>Si vous n'avez pas encore enregistré, quittez simplement l'écran sans enregistrer. Sinon, prévenez l'administrateur tout de suite, en précisant la page et l'heure : il peut remettre le contenu en place à partir d'une sauvegarde, à condition d'en avoir une assez récente.</p>
      <h3>Un client dit avoir écrit, mais je ne trouve pas sa demande</h3>
      <p>Cherchez son nom, son téléphone ou son e-mail dans la recherche des Demandes, avec « Tous les types » et « Tous les statuts », puis regardez dans le dossier Spam. Les e-mails de notification peuvent aussi arriver dans les courriers indésirables (spams) : vérifiez-les. Si la demande n'est nulle part, prévenez l'administrateur.</p>
      <h3>Nous sommes deux à modifier le site</h3>
      <p>Évitez de modifier la même page en même temps : c'est le dernier qui enregistre qui gagne, et les changements de l'autre sont écrasés. Pour les demandes, les notes indiquent qui a fait quoi.</p>
      <h3>Je ne trouve pas les avis clients, la barre d'actions du site ou les réglages de l'assistant</h3>
      <p>Ces réglages (avis Google, boutons et fenêtres d'action du site, assistant, e-mails, comptes) sont réservés à l'administrateur. Demandez-lui le changement souhaité.</p>
    <?= $close('faq') ?>

    <div class="card" style="margin-bottom:22px">
      <h2>Besoin d'aide ?</h2>
      <?php if ($admins): ?>
        <p class="bo-small">Pour une question, un problème ou un réglage hors de votre portée, contactez l'administrateur du site :</p>
        <ul class="bo-small">
          <?php foreach ($admins as $a): ?><li><b><?= e($a['name'] ?: $a['email']) ?></b> — <a href="mailto:<?= e($a['email']) ?>"><?= e($a['email']) ?></a></li><?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p class="bo-small">Pour une question, un problème ou un réglage hors de votre portée, contactez l'administrateur du site.</p>
      <?php endif; ?>
      <?php if ($isAdmin): ?>
        <p class="bo-muted" style="margin-top:10px"><b>Note pour les administrateurs :</b> ce guide est écrit pour le rôle Éditeur. Les rubriques Avis clients, Conversion, Assistant IA, Facebook, E-mails, Utilisateurs, Mises à jour et Sauvegarde ne sont ni visibles ni décrites pour lui.</p>
      <?php endif; ?>
    </div>
  </div>
</div>
