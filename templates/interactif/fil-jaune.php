<?php
/**
 * Le Fil jaune : recherche, défi du jour, records.
 * Variables : $players, $rec, $error, $qa, $qb, $daily (from, to, best, a, b), $connected, $duos, $far, $outside
 */
use App\Core\View;
use App\Front\Fil;

$fmt = fn ($n) => number_format((int) $n, 0, ',', ' ');
$avg = number_format((float) $rec['average'], 1, ',', ' ');
$face = function (?array $p, string $cls = 'fjface'): string {
    if (!$p) {
        return '';
    }
    return '<span class="' . $cls . '">' . ($p['image'] ? '<img src="' . e(img($p['image'], 160)) . '" alt="" loading="lazy">' : '<span class="fjface__ph" aria-hidden="true">' . e(mb_substr($p['name'], 0, 1)) . '</span>') . '</span>';
};
?>
<section class="mhead fjhead">
  <div class="wrap mhead__inner" style="padding-bottom:clamp(32px,4vw,56px)">
    <nav class="crumbs" aria-label="<?= e(t("Fil d'Ariane")) ?>"><a href="<?= e(url('/')) ?>"><?= e(t('Accueil')) ?></a><span aria-hidden="true">/</span><a href="<?= e(url('/interactif/')) ?>"><?= e(t('Interactif')) ?></a><span aria-hidden="true">/</span><span aria-current="page"><?= e(t('Le Fil jaune')) ?></span></nav>
    <span class="eyebrow eyebrow--lg eyebrow--yellow"><?= e(t('Jouer')) ?></span>
    <h1 class="mhead__title"><?= e(t('Le Fil jaune')) ?></h1>
    <p class="mhead__intro"><?= e(t('Tous les Lionceaux sont reliés. Choisissez deux joueurs : le musée trouve la chaîne des matchs joués ensemble qui les relie, de coéquipier en coéquipier.')) ?></p>
    <p class="fjhead__facts"><b><?= $fmt($rec['family']) ?></b> <?= e(t('joueurs dans la même famille')) ?> · <b><?= (int) $rec['diameter'] ?></b> <?= e(t('passes au plus')) ?> · <b><?= e($avg) ?></b> <?= e(t('en moyenne')) ?></p>
  </div>
</section>

<div class="wrap fjland">
  <section class="fjsec" aria-labelledby="fj-search">
    <h2 class="h-section" id="fj-search"><?= e(t('Relier deux joueurs')) ?></h2>
    <?php if ($error): ?><p class="fjerror" role="alert"><?= e($error) ?></p><?php endif; ?>
    <?= View::partial('partials/fil-form', ['players' => $players, 'a' => $qa, 'b' => $qb, 'id' => 'fj']) ?>
    <p class="fjnote"><?= e(t('Un seul joueur ? Laissez le second champ vide pour voir sa constellation : tous ses coéquipiers, du plus fidèle au plus rare.')) ?></p>
  </section>

  <?php if ($daily && $daily['a'] && $daily['b']): ?>
  <section class="fjsec fjgame" id="defi" aria-labelledby="fj-daily" data-fj-game data-date="<?= e($daily['date']) ?>" data-from="<?= (int) $daily['from'] ?>" data-to="<?= (int) $daily['to'] ?>" data-best="<?= (int) $daily['best'] ?>"
    data-solution="<?= e(Fil::chainUrl($daily['from'], $daily['to'])) ?>" data-day="<?= e(date_fr($daily['date'])) ?>">
    <div class="fjgame__head">
      <span class="eyebrow fjgame__eyebrow"><?= e(t('Défi du jour')) ?> · <?= e(date_fr($daily['date'])) ?></span>
      <h2 class="h-section" id="fj-daily"><?= e(t('À vous de tirer le fil')) ?></h2>
      <p class="fjgame__lead"><?= e(t('Partez de {a} et arrivez à {b} en choisissant à chaque fois un coéquipier. Le plus court chemin compte {n} passes.', ['a' => $daily['a']['name'], 'b' => $daily['b']['name'], 'n' => $daily['best']])) ?></p>
    </div>
    <div class="fjgame__duel">
      <div class="fjgame__end"><?= $face($daily['a']) ?><span><b><?= e($daily['a']['name']) ?></b><small><?= e($daily['a']['years']) ?></small></span></div>
      <span class="fjgame__thread" aria-hidden="true"></span>
      <div class="fjgame__end"><?= $face($daily['b']) ?><span><b><?= e($daily['b']['name']) ?></b><small><?= e($daily['b']['years']) ?></small></span></div>
    </div>
    <ol class="fjgame__chain" data-fj-chain aria-label="<?= e(t('Votre chaîne')) ?>"></ol>
    <div class="fjgame__pick" data-fj-pick hidden>
      <label class="fjgame__k" for="fj-filter" data-fj-pick-label></label>
      <input id="fj-filter" type="search" class="fjgame__filter" data-fj-filter placeholder="<?= e(t('Filtrer les noms…')) ?>" autocomplete="off">
      <ul class="fjgame__mates" data-fj-mates></ul>
    </div>
    <p class="fjgame__msg" data-fj-msg aria-live="polite"></p>
    <div class="row" style="gap:12px;flex-wrap:wrap">
      <button type="button" class="qbtn qbtn--sm" data-fj-start><?= e(t('Commencer')) ?></button>
      <button type="button" class="linkbtn" data-fj-undo hidden><?= e(t('Annuler le dernier choix')) ?></button>
      <button type="button" class="linkbtn" data-fj-share hidden><?= e(t('Partager mon score')) ?></button>
      <a class="linkbtn" href="<?= e(Fil::chainUrl($daily['from'], $daily['to'])) ?>" data-fj-solution><?= e(t('Voir la solution')) ?></a>
    </div>
    <noscript><p class="fjnote"><?= e(t('Le défi se joue avec JavaScript ; la solution reste accessible par le lien ci-dessus.')) ?></p></noscript>
  </section>
  <?php endif; ?>

  <section class="fjsec" aria-labelledby="fj-records">
    <h2 class="h-section" id="fj-records"><?= e(t('Les records du Fil jaune')) ?></h2>
    <div class="fjrec">
      <div class="fjrec__box">
        <h3 class="h-3"><?= e(t('Les plus connectés')) ?></h3>
        <ol class="fjrec__list">
          <?php foreach ($connected as $p): ?>
            <li><a href="<?= e(Fil::starUrl($p['id'])) ?>"><?= $face($p, 'fjface fjface--sm') ?><span><b><?= e($p['name']) ?></b><small><?= e(t('{n} coéquipiers', ['n' => $p['n']])) ?> · <?= e($p['years']) ?></small></span></a></li>
          <?php endforeach; ?>
        </ol>
      </div>
      <div class="fjrec__box">
        <h3 class="h-3"><?= e(t('Les inséparables')) ?></h3>
        <ol class="fjrec__list">
          <?php foreach ($duos as $d): ?>
            <li><a href="<?= e(Fil::chainUrl($d['a']['id'], $d['b']['id'])) ?>"><span class="fjrec__n"><?= (int) $d['n'] ?></span><span><b><?= e($d['a']['name']) ?> &amp; <?= e($d['b']['name']) ?></b><small><?= e(t('matchs joués ensemble')) ?></small></span></a></li>
          <?php endforeach; ?>
        </ol>
      </div>
      <?php if ($far && $far[0] && $far[1]): ?>
      <div class="fjrec__box fjrec__box--far">
        <h3 class="h-3"><?= e(t('Les plus éloignés')) ?></h3>
        <p><?= e(t('{a} ({ya}) et {b} ({yb}) : {n} passes les séparent, le maximum.', ['a' => $far[0]['name'], 'ya' => $far[0]['years'], 'b' => $far[1]['name'], 'yb' => $far[1]['years'], 'n' => $rec['diameter']])) ?></p>
        <a class="btn btn--sm btn--navy" href="<?= e(Fil::chainUrl($far[0]['id'], $far[1]['id'])) ?>"><?= e(t('Voir la chaîne')) ?></a>
      </div>
      <?php endif; ?>
    </div>
    <?php if ($outside): ?>
      <p class="fjnote fjnote--box"><?= e(t('{n} joueurs des années 1960 et 1970 forment pour l’instant une famille à part ({names}…) : il manque les compositions qui les relient aux générations suivantes. Vous en avez ? Le musée les recherche.', ['n' => count($outside), 'names' => implode(', ', array_map(fn ($p) => $p['name'], array_slice($outside, 0, 3)))])) ?>
        <a href="<?= e(url('/contribuer/')) ?>"><?= e(t('Contribuer')) ?> →</a></p>
    <?php endif; ?>
    <p class="fjnote"><?= e(t('Calculé à partir des compositions des fiches de match (titulaires et remplaçants entrés en jeu). Les compositions d’avant 1980 sont encore rares.')) ?></p>
  </section>
</div>
