<?php
/**
 * Layout du back-office connecté : barre latérale + contenu.
 *
 * @var App\Core\View $view
 * @var App\Core\Auth|mixed $auth
 * @var string $slot
 * @var array|null $page
 */
use App\Core\Csrf;
use App\Core\Session;

$chemin = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/');
$succes = Session::flash('succes');
$erreur = Session::flash('erreur');
// Le menu est groupé : vingt écrans à plat se cherchent, six familles de
// trois se trouvent. L'ordre suit celui du travail — le contenu d'abord, les
// réglages techniques en dernier.
$menu = [
  ['Le site', [
    ['/admin',                 'Tableau de bord'],
    ['/admin/site',            'Coordonnées & menu'],
    ['/admin/accueil',         'Page d’accueil'],
    ['/admin/pages',           'Pages du site'],
  ]],
  ['Contenu', [
    ['/admin/demarches',       'Démarches'],
    ['/admin/actualites',      'Actualités'],
    ['/admin/listes/agenda',   'Agenda'],
    ['/admin/listes/documents', 'Documents'],
  ]],
  ['La commune', [
    ['/admin/conseil',              'Conseil municipal'],
    ['/admin/listes/commissions',   'Commissions & comités'],
    ['/admin/listes/associations',  'Associations'],
    ['/admin/listes/numeros',       'Numéros utiles'],
    ['/admin/listes/services-etat', 'Services de l’État'],
  ]],
  ['Nous joindre', [
    ['/admin/contact',       'Page contact'],
    ['/admin/demande',       'Écrire à la mairie'],
    ['/admin/conversations', 'Conversations'],
  ]],
  ['Apparence', [
    ['/admin/photos',    'Photos'],
    ['/admin/apparence', 'Apparence'],
    ['/admin/avis',      'Avis Google'],
    ['/admin/assistant', 'Assistant IA'],
  ]],
  ['Réglages', [
    ['/admin/referencement', 'Référencement'],
    ['/admin/langues',       'Langues'],
    ['/admin/avance',        'Éditeur avancé'],
    ['/admin/parametres',    'Paramètres'],
    ['/admin/mises-a-jour',  'Mises à jour'],
  ]],
];
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($page['titre'] ?? 'Administration') ?> — Back-office de la mairie</title>
<link rel="icon" href="<?= asset('assets/img/logo/favicon-512.png') ?>" type="image/png">
<link rel="stylesheet" href="<?= asset('assets/css/admin.css') ?>">
</head>
<body class="bo">
<div class="bo-cadre">
  <aside class="bo-lateral">
    <a class="bo-lateral__logo" href="<?= url('/admin') ?>">
      <img src="<?= asset('assets/img/logo/logo-lachapelle-clair.svg') ?>" alt="Mairie de Lachapelle-sous-Chaux — back-office">
    </a>
    <nav class="bo-lateral__nav" aria-label="Navigation du back-office">
      <?php foreach ($menu as [$famille, $ecrans]): ?>
        <p class="bo-lateral__famille"><?= e($famille) ?></p>
        <?php foreach ($ecrans as [$url, $libelle]):
            $actif = $chemin === $url || ($url !== '/admin' && str_starts_with($chemin, $url)); ?>
          <a href="<?= url($url) ?>"<?= $actif ? ' aria-current="page"' : '' ?>>
            <?= e($libelle) ?>
            <?php /* Les conversations non lues portent leur compte : c'est là
                     que se trouvent les demandes de rappel, elles ne doivent
                     pas attendre qu'on pense à ouvrir l'écran. */ ?>
            <?php if ($url === '/admin/conversations' && ($nonLues ?? 0) > 0): ?>
              <span class="bo-lateral__compte"><?= (int) $nonLues ?></span>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </nav>
    <div class="bo-lateral__pied">
      <a href="<?= url('/') ?>" target="_blank" rel="noopener">Voir le site ↗</a>
      <form method="post" action="<?= url('/admin/deconnexion') ?>">
        <?= Csrf::champ() ?>
        <button type="submit">Déconnexion</button>
      </form>
    </div>
  </aside>

  <main class="bo-principal">
    <header class="bo-entete">
      <h1><?= e($page['titre'] ?? 'Administration') ?></h1>
      <span class="bo-entete__qui">Connecté : <?= e($auth->identifiant()) ?></span>
    </header>

    <?php if ($succes): ?><p class="bo-message bo-message--succes"><?= e($succes) ?></p><?php endif; ?>
    <?php if ($erreur): ?><p class="bo-message bo-message--erreur"><?= e($erreur) ?></p><?php endif; ?>

    <?= $slot ?>
  </main>
</div>
<script src="<?= asset('assets/js/admin.js') ?>" defer></script>
</body>
</html>
