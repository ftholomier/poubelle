<?php
/**
 * « L'effectif de la saison » en page d'album Panini (App\Front\SeasonAlbum::build).
 * Variables : $album (groups, count, coach, minutes), $season.
 */
$lines = ['G' => t('Gardiens'), 'D' => t('Défenseurs'), 'M' => t('Milieux'), 'A' => t('Attaquants'), 'X' => t('Poste inconnu')];
$one = ['G' => t('Gardien'), 'D' => t('Défenseur'), 'M' => t('Milieu'), 'A' => t('Attaquant'), 'X' => t('Poste inconnu')];
$logo = \App\Core\Club::s('logo_400');
$sticker = function (array $p, string $role) use ($album): string {
    $stats = (int) $p['mj'] . ' ' . ($p['mj'] > 1 ? t('matchs') : t('match'))
        . ' · ' . (int) $p['goals'] . ' ' . ($p['goals'] > 1 || ($p['goals'] === 0 && \App\Services\I18n::isEn()) ? t('buts') : t('but'))
        . ($album['minutes'] && $p['min'] > 0 ? ' · ' . number_format((int) $p['min'], 0, ',', ' ') . ' ' . t('min') : '');
    if (!$p['stuck']) {
        return '<div class="alb__slot is-empty"><span class="alb__no" aria-hidden="true">' . (int) $p['no'] . '</span>'
            . '<span class="alb__ph" aria-hidden="true">' . icon_photo() . '</span>'
            . '<span class="alb__name"><b>' . e($p['display']) . '</b><span>' . e($role) . '</span></span>'
            . '<span class="alb__stats">' . e($stats) . '</span>'
            . '<span class="sr-only">' . e(t('Vignette non collée : pas encore de fiche joueur.')) . '</span></div>';
    }
    $img = $p['image']
        ? '<img src="' . e(img($p['image'], 320)) . '" alt="' . e(t('Photo de {n}', ['n' => $p['display']])) . '" loading="lazy" decoding="async" width="240" height="300">'
        : '<span class="alb__ph" aria-hidden="true">' . icon_photo() . '</span>';
    return '<a class="alb__card" href="' . e($p['href']) . '"><span class="alb__in">'
        . '<span class="alb__face alb__front"><span class="alb__no">' . (int) $p['no'] . '</span><span class="alb__photo">' . $img . '</span>'
        . '<span class="alb__name"><b>' . e($p['display']) . '</b><span>' . e($role) . '</span></span></span>'
        . '<span class="alb__face alb__back" aria-hidden="true"><span class="alb__bn">' . e($p['display']) . '</span><span class="alb__bs">' . e($stats) . '</span><span class="alb__go">' . e(t('Voir la fiche')) . ' →</span></span>'
        . '</span><span class="alb__stats">' . e($stats) . '</span></a>';
};
?>
<section id="album" class="alb" aria-labelledby="alb-title">
  <div class="wrap">
    <div class="alb__page">
      <header class="alb__head">
        <?php if ($logo): ?><span class="alb__team"><img src="<?= e($logo) ?>" alt="" loading="lazy" width="80" height="80"><small><?= e(t('Emplacement équipe')) ?></small></span><?php endif; ?>
        <div>
          <p class="alb__kicker"><?= e(t("L'effectif de la saison")) ?></p>
          <h2 id="alb-title" class="alb__title"><?= e(t('Album {s}', ['s' => $season])) ?></h2>
          <p class="alb__lead"><?= e(t('{n} joueurs entrés en jeu, d’après les compositions des matchs fichés. Une case grise : joueur sans fiche, vignette pas encore collée.', ['n' => $album['count']])) ?></p>
        </div>
      </header>
      <?php foreach ($lines as $k => $title): if (empty($album['groups'][$k])) continue; ?>
        <h3 class="alb__line"><?= e($title) ?> <small>(<?= count($album['groups'][$k]) ?>)</small></h3>
        <ul class="alb__grid" data-line="<?= e($k) ?>">
          <?php foreach ($album['groups'][$k] as $p): ?><li class="alb__item"><?= $sticker($p, $one[$k]) ?></li><?php endforeach; ?>
        </ul>
      <?php endforeach; ?>
      <?php if ($album['coach']): $c = $album['coach'] + ['mj' => $album['coach']['n'], 'goals' => 0, 'min' => 0]; ?>
        <h3 class="alb__line"><?= e(t('Entraîneur')) ?></h3>
        <ul class="alb__grid alb__grid--coach"><li class="alb__item alb__item--coach"><?php
          $cs = (int) $c['n'] . ' ' . ($c['n'] > 1 ? t('matchs dirigés') : t('match dirigé'));
          if ($c['stuck']): ?><a class="alb__card alb__card--coach" href="<?= e($c['href']) ?>"><span class="alb__in"><span class="alb__face alb__front"><span class="alb__no"><?= (int) $c['no'] ?></span><span class="alb__photo"><?= $c['image'] ? '<img src="' . e(img($c['image'], 320)) . '" alt="' . e(t('Photo de {n}', ['n' => $c['display']])) . '" loading="lazy" decoding="async" width="240" height="300">' : '<span class="alb__ph" aria-hidden="true">' . icon_photo() . '</span>' ?></span><span class="alb__name"><b><?= e($c['display']) ?></b><span><?= e(t('Entraîneur')) ?></span></span></span><span class="alb__face alb__back" aria-hidden="true"><span class="alb__bn"><?= e($c['display']) ?></span><span class="alb__bs"><?= e($cs) ?></span><span class="alb__go"><?= e(t('Voir la fiche')) ?> →</span></span></span><span class="alb__stats"><?= e($cs) ?></span></a><?php
          else: ?><div class="alb__slot is-empty"><span class="alb__no" aria-hidden="true"><?= (int) $c['no'] ?></span><span class="alb__ph" aria-hidden="true"><?= icon_photo() ?></span><span class="alb__name"><b><?= e($c['display']) ?></b><span><?= e(t('Entraîneur')) ?></span></span><span class="alb__stats"><?= e($cs) ?></span></div><?php endif; ?></li></ul>
      <?php endif; ?>
    </div>
  </div>
</section>
