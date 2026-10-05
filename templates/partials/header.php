<?php
/** En-tête : bandeau « En direct du musée », logo, recherche, taille du texte, langue, contribuer, don, menu + méga-menus. */

use App\Front\Site;
use App\Services\I18n;

$nav = Site::nav($active ?? '');
$ticker = Site::ticker();
$lang = I18n::lang();
$official = trim((string) \App\Core\Settings::get('social.official', ''));
?>
<header class="site-header<?= $ticker ? ' has-ticker' : '' ?>" data-header>
  <?php if ($ticker): ?>
  <div class="ticker" role="region" aria-label="<?= e(t('En direct du musée')) ?>">
    <span class="ticker__label"><span class="ticker__dot" aria-hidden="true"></span><?= e(t('En direct du musée')) ?></span>
    <div class="ticker__viewport">
      <div class="ticker__track">
        <?php foreach ([0, 1] as $copy): ?>
          <?php foreach ($ticker as $t): ?>
            <a href="<?= e($t['href']) ?>"<?= $copy ? ' aria-hidden="true" tabindex="-1"' : '' ?>><span class="k"><?= e($t['k']) ?></span><span><?= e($t['v']) ?></span><span class="star" aria-hidden="true">★</span></a>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <div class="masthead">
    <a class="masthead__logo" href="<?= e(url('/')) ?>" aria-label="<?= e(t('Accueil Sochaux rétro')) ?>">
      <img src="/assets/img/logo-sochaux-retro.png" alt="Sochaux rétro" width="150" height="170">
    </a>
    <div class="masthead__inner">
      <div class="masthead__top">
        <span class="masthead__name">Sochaux rétro</span>
        <span class="masthead__tag"><span class="masthead__tagtext"><?= e(t('Le musée en ligne du FCSM · depuis 1928')) ?></span><?php if ($official !== ''): ?><a class="masthead__official" href="<?= e($official) ?>" rel="noopener" target="_blank" title="<?= e(t('Site officiel du FC Sochaux-Montbéliard')) ?>"><?= e(t('Site officiel du FCSM')) ?> ↗</a><?php endif; ?></span>
        <button type="button" class="hbtn hsearch" data-search-open aria-label="<?= e(t('Rechercher dans le musée')) ?>">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#0E1F4D" stroke-width="2.6" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="M15.5 15.5L21 21"/></svg>
          <span><?= e(t('Un match, un joueur, une saison…')) ?></span>
        </button>
        <button type="button" class="hbtn hsize" data-textsize aria-label="<?= e(t('Taille du texte')) ?>" title="<?= e(t('Taille du texte')) ?>">A<small>A</small><span class="lbl" data-textsize-label>100%</span></button>
        <?php if (count(I18n::enabled()) > 1): ?>
        <div class="hlang" data-lang>
          <button type="button" class="hbtn" data-lang-toggle aria-haspopup="true" aria-expanded="false"><?= e(strtoupper($lang)) ?> ▾</button>
          <div class="hlang__menu" role="menu">
            <?php foreach (I18n::enabled() as $code): ?>
              <a role="menuitem" href="<?= e(I18n::switchUrl($path ?? '/', $code)) ?>" hreflang="<?= e($code) ?>" class="<?= $code === $lang ? 'is-on' : '' ?>"><span><?= e(I18n::LANGS[$code]) ?></span><b><?= e(strtoupper($code)) ?></b></a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
        <a class="hcontrib" href="<?= e(url('/contribuer/')) ?>"><?= e(t('Contribuer')) ?></a>
        <a class="hdon" href="<?= e(url('/faire-un-don/')) ?>">♥ <?= e(t('Faire un don')) ?></a>
        <button type="button" class="hburger" data-burger aria-label="<?= e(t('Menu')) ?>" aria-expanded="false">☰</button>
      </div>
      <nav class="mainnav" aria-label="<?= e(t('Menu principal')) ?>">
        <?php foreach ($nav as $item): ?>
          <?php if (!empty($item['mega'])): ?>
            <a href="<?= e($item['href']) ?>" class="<?= $item['active'] ? 'is-active' : '' ?>" data-mega-trigger="<?= e($item['key']) ?>" aria-haspopup="true" aria-expanded="false"<?= $item['active'] ? ' aria-current="page"' : '' ?>><?= e($item['label']) ?><span class="caret" aria-hidden="true">▾</span></a>
          <?php else: ?>
            <a href="<?= e($item['href']) ?>" class="<?= $item['active'] ? 'is-active' : '' ?>"<?= $item['active'] ? ' aria-current="page"' : '' ?>><?= e($item['label']) ?></a>
          <?php endif; ?>
        <?php endforeach; ?>
      </nav>
    </div>
  </div>

  <?php foreach ($nav as $item): if (empty($item['mega'])) continue; $mg = $item['mega']; ?>
  <div class="mega" data-mega="<?= e($item['key']) ?>">
    <?php if ($mg['kind'] === 'matchs'): ?>
      <div class="mega__inner" style="grid-template-columns:minmax(0,1fr) minmax(0,1.5fr) minmax(0,.8fr)">
        <div class="mega__col">
          <span class="mega__title"><?= e(t('Par compétition')) ?></span>
          <div class="mega__pills">
            <?php foreach ($mg['competitions'] as $c): ?><a class="mega__pill" href="<?= e($c['href']) ?>"><?= e($c['label']) ?></a><?php endforeach; ?>
          </div>
        </div>
        <div class="mega__col">
          <div class="mega__head"><span class="mega__title"><?= e(t('Par décennie')) ?></span><a class="mega__all" href="<?= e(url('/matchs/')) ?>"><?= e(t('Tous les matchs')) ?> →</a></div>
          <div class="mega__decades">
            <?php foreach ($mg['decades'] as $d): ?>
              <a class="mega__decade" href="<?= e($d['href']) ?>"><b><?= e($d['short']) ?></b><span><?= e(decade_label((int) $d['full'], true, true)) ?></span></a>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="mega__col mega__col--sep">
          <span class="mega__title"><?= e(t('Explorer')) ?></span>
          <?php foreach ($mg['explore'] as $x): ?><a class="mega__link" href="<?= e($x['href']) ?>"><?= e($x['label']) ?></a><?php endforeach; ?>
        </div>
      </div>
    <?php elseif ($mg['kind'] === 'columns'): ?>
      <div class="mega__inner" style="grid-template-columns:repeat(<?= count($mg['cols']) ?>,minmax(0,1fr))">
        <?php foreach ($mg['cols'] as $i => $col): ?>
          <div class="mega__col<?= $i ? ' mega__col--sep' : '' ?>">
            <span class="mega__title"><?= e($col['title']) ?></span>
            <a class="mega__link" href="<?= e($col['all']['href']) ?>"><?= e($col['all']['label']) ?> <small class="mega__all">(<?= (int) $col['all']['count'] ?>)</small></a>
            <?php foreach ($col['subs'] as $s): ?>
              <a class="mega__sub" href="<?= e($s['href']) ?>"><span><?= e($s['label']) ?></span><small><?= (int) $s['count'] ?></small></a>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php elseif ($mg['kind'] === 'list'): $col = $mg['col']; ?>
      <div class="mega__inner" style="grid-template-columns:minmax(0,1fr) minmax(0,2fr)">
        <div class="mega__col">
          <span class="mega__title"><?= e($col['title']) ?></span>
          <?php if (!empty($col['desc'])): ?><p class="mega__all" style="margin:0"><?= e($col['desc']) ?></p><?php endif; ?>
          <a class="mega__link" href="<?= e($col['all']['href']) ?>"><?= e($col['all']['label']) ?> →</a>
        </div>
        <div class="mega__col mega__col--sep">
          <div class="mega__pills">
            <?php foreach ($col['subs'] as $s): ?><a class="mega__pill" href="<?= e($s['href']) ?>"><?= e($s['label']) ?> <small style="opacity:.7">(<?= (int) $s['count'] ?>)</small></a><?php endforeach; ?>
          </div>
        </div>
      </div>
    <?php elseif ($mg['kind'] === 'interactif'): ?>
      <?php // Une colonne par groupe (un groupe de plus de 5 sur deux colonnes), une ligne compacte par outil : le menu reste bas. ?>
      <div class="mega__inner mega__inner--tools" style="grid-template-columns:<?= e(implode(' ', array_map(fn ($g) => count($g['tools']) > 5 ? 'minmax(0,2fr)' : 'minmax(0,1fr)', $mg['groups']))) ?>">
        <?php foreach ($mg['groups'] as $g): ?>
          <div class="mega__col mega__group">
            <span class="mega__title"><?= e($g['title']) ?></span>
            <div class="mega__list<?= count($g['tools']) > 5 ? ' mega__list--2' : '' ?>">
              <?php foreach ($g['tools'] as $x): ?>
                <a class="mega__item" href="<?= e($x['href']) ?>" title="<?= e($x['d']) ?>"><span class="mega__icon"><?= e($x['icon']) ?></span><span><b><?= e($x['label']) ?></b><small><?= e($x['d']) ?></small></span></a>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
        <div class="mega__foot"><a class="mega__all" href="<?= e(url('/interactif/')) ?>"><?= e(t('Toute la rubrique Interactif')) ?> →</a><a class="mega__all" href="<?= e(url('/centenaire/')) ?>"><?= e(t('Le centenaire')) ?> →</a></div>
      </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>

  <div class="mobilemenu" data-mobilemenu>
    <?php foreach ($nav as $item): ?>
      <?php if (!empty($item['mega']) && $item['key'] !== 'interactif'): ?>
        <details>
          <summary class="<?= $item['active'] ? 'is-active' : '' ?>"><?= e($item['label']) ?></summary>
          <div class="mobilemenu__subs">
            <a href="<?= e($item['href']) ?>"><?= e(t('Tout voir')) ?></a>
            <?php
            $mg = $item['mega'];
            $subs = match ($mg['kind']) {
                'matchs' => array_merge($mg['competitions'], array_map(fn ($d) => ['label' => decade_label((int) $d['full'], true), 'href' => $d['href']], $mg['decades']), $mg['explore']),
                'columns' => array_merge(...array_map(fn ($c) => array_merge([$c['all']], $c['subs']), $mg['cols'])),
                'list' => $mg['col']['subs'],
                default => [],
            };
            foreach ($subs as $s): ?><a href="<?= e($s['href']) ?>"><?= e($s['label']) ?></a><?php endforeach; ?>
          </div>
        </details>
      <?php elseif ($item['key'] !== 'interactif'): ?>
        <a href="<?= e($item['href']) ?>" class="<?= $item['active'] ? 'is-active' : '' ?>"><?= e($item['label']) ?></a>
      <?php endif; ?>
    <?php endforeach; ?>
    <span class="mobilemenu__label"><?= e(t('Interactif')) ?></span>
    <div class="mobilemenu__subs">
      <?php foreach (Site::interactiveTools() as $g): foreach ($g['tools'] as $x): ?><a href="<?= e($x['href']) ?>"><?= e($x['label']) ?></a><?php endforeach; endforeach; ?>
    </div>
    <div class="mobilemenu__tools">
      <?php foreach (I18n::enabled() as $code): ?><a href="<?= e(I18n::switchUrl($path ?? '/', $code)) ?>" class="<?= $code === $lang ? 'is-on' : '' ?>"><?= e(strtoupper($code)) ?></a><?php endforeach; ?>
      <button type="button" data-textsize><?= e(t('Taille du texte')) ?> · <span data-textsize-label>100%</span></button>
      <a href="<?= e(url('/contribuer/')) ?>"><?= e(t('Contribuer')) ?></a>
    </div>
    <?php if ($official !== ''): ?><a class="mobilemenu__official" href="<?= e($official) ?>" rel="noopener" target="_blank"><?= e(t('Site officiel du FC Sochaux-Montbéliard')) ?> ↗</a><?php endif; ?>
  </div>

  <div class="searchlayer" data-searchlayer role="dialog" aria-modal="true" aria-label="<?= e(t('Rechercher dans le musée')) ?>">
    <div class="searchlayer__inner">
      <div class="row between">
        <span class="eyebrow eyebrow--yellow"><?= e(t('Rechercher dans le musée')) ?></span>
        <button type="button" class="searchlayer__close" data-search-close><?= e(t('Fermer')) ?> ✕</button>
      </div>
      <form action="<?= e(url('/recherche/')) ?>" method="get" role="search">
        <label class="sr-only" for="q-global"><?= e(t('Recherche')) ?></label>
        <input id="q-global" type="search" name="q" autocomplete="off" placeholder="<?= e(t('Un joueur, un match, une saison…')) ?>" data-search-input>
      </form>
      <span class="searchlayer__count" data-search-count><?= e(t('Suggestions')) ?></span>
      <div data-search-results></div>
    </div>
  </div>
</header>
