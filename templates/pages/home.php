<?php
/**
 * Accueil.
 *
 * @var array $facets    compteurs de l'index des offres
 * @var array $families  familles de métier avec compteur
 * @var array $latest    dernières offres
 * @var array $profiles  derniers profils
 * @var array $trades    métiers du bandeau défilant
 */
use App\Core\Config;
use App\Core\View;
use App\Services\I18n;
use App\Support\Icon;

$spots = [
    ['rod' => 22, 'beam' => [180, 440], 'color' => '#FF4B3E', 'rgb' => '255,75,62',  'alpha' => .34, 'class' => 'spot-1'],
    ['rod' => 34, 'beam' => [200, 420], 'color' => '#FFC531', 'rgb' => '255,197,49', 'alpha' => .30, 'class' => 'spot-2'],
    ['rod' => 18, 'beam' => [190, 460], 'color' => '#6D4AFF', 'rgb' => '109,74,255', 'alpha' => .34, 'class' => 'spot-3'],
    ['rod' => 28, 'beam' => [170, 400], 'color' => '#0FBFA4', 'rgb' => '15,191,164', 'alpha' => .30, 'class' => 'spot-4'],
];
$tabs = [
    'seeker'    => I18n::t('home.how_seeker'),
    'recruiter' => I18n::t('home.how_recruiter'),
];
?>
<section class="hero">
  <div class="hero-catwalk"></div>
  <div class="hero-rig">
    <?php foreach ($spots as $spot): ?>
      <div class="spot <?= e($spot['class']) ?>">
        <span class="spot-rod" style="height:<?= (int) $spot['rod'] ?>px"></span>
        <span class="spot-body"><span class="spot-lens" style="background:<?= e($spot['color']) ?>"></span></span>
        <span class="spot-beam" style="width:<?= (int) $spot['beam'][0] ?>px;height:<?= (int) $spot['beam'][1] ?>px;
              background:linear-gradient(to bottom, rgba(<?= e($spot['rgb']) ?>,<?= e($spot['alpha']) ?>), rgba(<?= e($spot['rgb']) ?>,0))"></span>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="hero-inner">
    <span class="pill-status" data-reveal>
      <span class="dot"></span>
      <?= e(I18n::t('home.status',
            number_format((int) ($facets['total'] ?? 0), 0, ',', ' '),
            number_format((int) ($facets['today'] ?? 0), 0, ',', ' '))) ?>
    </span>

    <h1 data-reveal><?= e(I18n::t('home.h1_a')) ?><br><span class="highlight"><?= e(I18n::t('home.h1_b')) ?></span></h1>

    <p class="hero-lede" data-reveal><?= e(I18n::t('home.lede')) ?></p>

    <div class="hero-stickers" data-reveal>
      <span class="sticker sticker-coral"><?= e(I18n::t('home.badge_free')) ?></span>
      <span class="sticker sticker-white"><?= e(I18n::t('home.badge_quick')) ?></span>
      <span class="sticker sticker-violet"><?= e(I18n::t('home.badge_since', (string) Config::get('site.since'))) ?></span>
    </div>

    <form class="searchbar" method="get" action="<?= e(I18n::url('/offres')) ?>" data-reveal role="search">
      <div class="sb-field">
        <?= Icon::svg('search', 18, '#6B6590') ?>
        <label class="visually-hidden" for="q"><?= e(I18n::t('search.keyword')) ?></label>
        <input id="q" type="search" name="q" placeholder="<?= e(I18n::t('search.keyword')) ?>">
      </div>
      <div class="sb-sep"></div>
      <div class="sb-field">
        <?= Icon::svg('pin', 18, '#6B6590') ?>
        <label class="visually-hidden" for="city"><?= e(I18n::t('search.city')) ?></label>
        <input id="city" type="search" name="city" data-places placeholder="<?= e(I18n::t('search.city')) ?>">
      </div>
      <button type="submit" class="btn btn-coral"><?= e(I18n::t('search.submit')) ?></button>
    </form>

    <div class="hero-cats" data-reveal>
      <?php foreach ($families as $family): ?>
        <a href="<?= e(I18n::url('/offres')) ?>?category=<?= e(urlencode((string) $family['name'])) ?>"><?= e($family['name']) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<div class="ticker" aria-hidden="true">
  <div class="ticker-track">
    <?php $line = implode(' ✦ ', $trades) . ' ✦'; ?>
    <span><?= e($line) ?></span><span><?= e($line) ?></span>
  </div>
</div>

<section class="container">
  <div class="stats-band" data-reveal>
    <?php foreach ([
        ['n' => $stats['jobs'],      'label' => 'stats.jobs',      'color' => '#FF4B3E'],
        ['n' => $stats['cv'],        'label' => 'stats.cv',        'color' => '#0FBFA4'],
        ['n' => $stats['employers'], 'label' => 'stats.employers', 'color' => '#6D4AFF'],
    ] as $stat): ?>
      <div class="stat">
        <span class="stat-n" style="color:<?= e($stat['color']) ?>">
          <?= e(number_format((int) $stat['n'], 0, ',', ' ')) ?>
        </span>
        <span class="stat-l"><?= e(I18n::t($stat['label'])) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<div class="container"><?= View::partial('partials/ad', ['slot' => 'home_top']) ?></div>

<section class="section container">
  <div class="section-head" data-reveal>
    <div>
      <h2><?= e(I18n::t('home.families')) ?></h2>
      <p><?= e(I18n::t('home.families_note')) ?></p>
    </div>
    <a class="btn btn-ghost btn-sm" href="<?= e(I18n::url('/offres')) ?>"><?= e(I18n::t('home.see_all')) ?></a>
  </div>

  <div class="grid-families">
    <?php foreach ($families as $family): $color = tile_color((string) $family['name']); ?>
      <a class="card card-link family-card" data-reveal
         href="<?= e(I18n::url('/offres')) ?>?category=<?= e(urlencode((string) $family['name'])) ?>">
        <span class="tile tile-46" style="background:<?= e($color) ?>;color:<?= e(on_color($color)) ?>">
          <?= e(mb_substr((string) $family['name'], 0, 1)) ?>
        </span>
        <span>
          <span class="name"><?= e($family['name']) ?></span><br>
          <span class="count"><?= e(I18n::t('employers.jobs', (int) $family['count'])) ?></span>
        </span>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<section class="section container">
  <div class="section-head" data-reveal>
    <div>
      <h2><?= e(I18n::t('home.fresh')) ?></h2>
      <p><?= e(I18n::t('home.fresh_note')) ?></p>
    </div>
    <a class="btn btn-ghost btn-sm" href="<?= e(I18n::url('/offres')) ?>"><?= e(I18n::t('home.see_all')) ?></a>
  </div>

  <div class="grid-jobs">
    <?php foreach ($latest as $job): ?><?= View::partial('partials/job-card', ['job' => $job]) ?><?php endforeach; ?>
  </div>
</section>

<section class="section container" data-tabs>
  <div class="section-head" data-reveal>
    <h2><?= e(I18n::t('home.how')) ?></h2>
    <div class="tabs" role="tablist">
      <?php foreach ($tabs as $key => $label): ?>
        <button type="button" role="tab" data-tab="<?= e($key) ?>"
                aria-selected="<?= $key === 'seeker' ? 'true' : 'false' ?>"><?= e($label) ?></button>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<div class="container" style="padding-bottom:10px">
  <?php foreach (array_keys($tabs) as $key): ?>
    <div class="grid-steps" data-tab-panel="<?= e($key) ?>"<?= $key === 'seeker' ? '' : ' hidden' ?>>
      <?php for ($n = 1; $n <= 3; $n++): ?>
        <div class="card step-card">
          <div class="n"><?= e(str_pad((string) $n, 2, '0', STR_PAD_LEFT)) ?></div>
          <h3><?= e(I18n::t("how.$key.$n.title")) ?></h3>
          <p><?= e(I18n::t("how.$key.$n.body")) ?></p>
        </div>
      <?php endfor; ?>
    </div>
  <?php endforeach; ?>
</div>

<section class="section container">
  <div class="section-head" data-reveal>
    <div>
      <h2><?= e(I18n::t('home.profiles')) ?></h2>
      <p><?= e(I18n::t('home.profiles_note')) ?></p>
    </div>
    <a class="btn btn-ghost btn-sm" href="<?= e(I18n::url('/cv')) ?>"><?= e(I18n::t('home.see_all')) ?></a>
  </div>

  <div class="grid-cv">
    <?php foreach ($profiles as $cv): ?><?= View::partial('partials/cv-card', ['cv' => $cv]) ?><?php endforeach; ?>
  </div>
</section>

<div class="container"><?= View::partial('partials/ad', ['slot' => 'home_mid']) ?></div>

<div class="container">
  <div class="cta-banner" data-reveal>
    <h2><?= e(I18n::t('home.cta_title')) ?></h2>
    <p><?= e(I18n::t('home.cta_note')) ?></p>
    <div class="row">
      <a class="btn btn-coral btn-lg" href="<?= e(I18n::url('/deposer-un-cv')) ?>"><?= e(I18n::t('cta.post_cv')) ?></a>
      <a class="btn btn-violet btn-lg" href="<?= e(I18n::url('/deposer-une-annonce')) ?>"><?= e(I18n::t('cta.post_job')) ?></a>
    </div>
  </div>
</div>
