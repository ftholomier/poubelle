<?php
/** Accueil — maquette « Musee FCSM ». */

use App\Front\Site;
use App\Services\I18n;

$centenary = Site::centenaryDate();
$pad = fn ($n) => str_pad((string) $n, 2, '0', STR_PAD_LEFT);
?>
<?php if ($slides): ?>
<section class="hero" data-slider aria-roledescription="carrousel" aria-label="<?= e(t('À la une')) ?>">
  <?php foreach ($slides as $i => $s): ?>
    <div class="hero__slide<?= $i === 0 ? ' is-on' : '' ?>" data-slide="<?= $i ?>" aria-hidden="<?= $i === 0 ? 'false' : 'true' ?>">
      <?php if ($i === 0): ?>
      <img src="<?= e(img($s['image'], 1600)) ?>" srcset="<?= e(srcset($s['image'], [800, 1200, 1600])) ?>" sizes="100vw" alt="" fetchpriority="high" style="object-position:<?= e($s['focus']) ?>">
      <?php else: /* Chargée par home.js juste avant d'être montrée : l'accueil ne télécharge qu'une photo. */ ?>
      <img data-src="<?= e(img($s['image'], 1600)) ?>" data-srcset="<?= e(srcset($s['image'], [800, 1200, 1600])) ?>" sizes="100vw" alt="" style="object-position:<?= e($s['focus']) ?>">
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  <div class="hero__shade" aria-hidden="true"></div>
  <div class="hero__ui wrap">
    <div class="hero__top">
      <div class="hero__text" data-hero-text>
        <?php foreach ($slides as $i => $s): ?>
          <div class="hero__copy<?= $i === 0 ? ' is-on' : '' ?>" data-copy="<?= $i ?>">
            <span class="eyebrow eyebrow--yellow eyebrow--lg"><?= e($s['kind']) ?></span>
            <?php $tl = mb_strlen((string) $s['title']); ?>
            <<?= $i === 0 ? 'h1' : 'h2' ?> class="hero__title<?= $tl > 32 ? ' hero__title--xl' : ($tl > 22 ? ' hero__title--l' : '') ?>"><?= e($s['title']) ?></<?= $i === 0 ? 'h1' : 'h2' ?>>
            <?php if ($s['text']): ?><p class="hero__lead"><?= e($s['text']) ?></p><?php endif; ?>
            <a class="btn btn--yellow" href="<?= e($s['href']) ?>" tabindex="<?= $i === 0 ? '0' : '-1' ?>"><?= e(t('En savoir plus')) ?></a>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="hero__count">
        <div class="hero__num"><span data-hero-num>01</span><small>/ <?= $pad(count($slides)) ?></small></div>
        <div class="row gap-8">
          <button type="button" class="hero__arrow" data-prev aria-label="<?= e(t('Précédent')) ?>">←</button>
          <button type="button" class="hero__arrow" data-next aria-label="<?= e(t('Suivant')) ?>">→</button>
        </div>
      </div>
    </div>
    <div class="hero__thumbs" role="tablist">
      <?php foreach ($slides as $i => $s): ?>
        <button type="button" class="hero__thumb<?= $i === 0 ? ' is-on' : '' ?>" data-goto="<?= $i ?>" role="tab" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>">
          <span class="hero__bar"><i></i></span>
          <span class="hero__tkind"><?= $pad($i + 1) ?> · <?= e($s['kind']) ?></span>
          <span class="hero__ttitle"><?= e($s['title']) ?></span>
        </button>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="hband" aria-label="<?= e(t('Aujourd’hui au musée')) ?>">
  <div class="wrap">
    <div class="hband__head"><span class="eyebrow"><?= e(t('Aujourd’hui au musée')) ?></span></div>
    <div class="hbento hbento--today">
      <?php if ($jour): ?>
      <a class="hcard hcard--jour" href="<?= e(url($jour['path'])) ?>">
        <span class="hcard__media"><?php if ($jour['image']): ?><img src="<?= e(img($jour['image'], 640)) ?>" alt="" loading="lazy"><?php else: ?><span class="ph"><?= icon_photo() ?></span><?php endif; ?></span>
        <span class="hcard__body">
          <span class="hcard__k"><?= e(t('Ce jour-là')) ?> · <?= e($jourLabel) ?></span>
          <span class="hcard__t"><?= e(($jour['home'] ?: $jour['event']) . ($jour['away'] ? ' – ' . $jour['away'] : '')) ?></span>
          <?php if ($jour['us'] !== null): ?><span class="num hcard__score"><?= e($jour['sh'] ? $jour['us'] . ' – ' . $jour['them'] : $jour['them'] . ' – ' . $jour['us']) ?></span><?php endif; ?>
          <span class="hcard__meta"><?= e(trim(($jour['label'] ?: $jour['comp']) . ' · ' . substr((string) $jour['date'], 0, 4), ' ·')) ?></span>
          <span class="hcard__more"><?= e(t('Lire la fiche du match')) ?> →</span>
        </span>
      </a>
      <?php endif; ?>
      <?php if (!empty($chiffre)): $cs = $chiffre['stat']; ?>
      <a class="hcard hcard--fig" href="<?= e(url('/chiffres/') . '#' . $cs['key']) ?>">
        <span class="hcard__k"><?= e(t('Le chiffre du jour')) ?> · <?= e(t('N° {n} sur {t}', ['n' => $cs['n'], 't' => $chiffre['count']])) ?></span>
        <span class="hcard__big"><b class="num"<?= preg_match('/^\d{1,3}(?:[\x{00A0}\x{202F} ,]\d{3})*$/u', $cs['value']) ? ' data-count' : '' ?>><?= e($cs['value']) ?></b><?php if ($cs['unit'] !== ''): ?> <small><?= e($cs['unit']) ?></small><?php endif; ?></span>
        <span class="hcard__t hcard__t--s"><?= e($cs['label']) ?></span>
        <span class="hcard__more"><?= e(t('Les {n} chiffres du FCSM', ['n' => $chiffre['count']])) ?> →</span>
      </a>
      <?php endif; ?>
      <div class="hcard hcard--cent">
        <span class="hcard__k"><?= e(t('Le centenaire')) ?> · 20 <?= e(t('mai')) ?> 1928 → <?= e(date_fr($centenary)) ?></span>
        <span class="hcard__t hcard__t--s"><?= e(t('Cent ans de lion.')) ?> <?= e(t('Aidez-nous à tout sauver.')) ?></span>
        <?= countdown_html($centenary) ?>
        <span class="row gap-8" style="flex-wrap:wrap">
          <a class="btn btn--navy btn--sm" href="<?= e(url('/faire-un-don/')) ?>">♥ <?= e(t('Faire un don')) ?></a>
          <a class="link-under" href="<?= e(url('/centenaire/')) ?>"><?= e(t('100 ans, 100 moments')) ?> →</a>
        </span>
      </div>
    </div>
  </div>
</section>

<section class="hfind" aria-labelledby="hfind-t">
  <div class="wrap hfind__in">
    <div class="hfind__head">
      <span class="eyebrow eyebrow--yellow"><?= e(t('Rechercher dans le musée')) ?></span>
      <h2 class="hfind__t" id="hfind-t"><?= e(t('Retrouvez tout')) ?></h2>
      <p class="hfind__lead"><?= e(t('Un match, une saison, un joueur, un récit, une affiche… près d’un siècle d’archives du FCSM.')) ?></p>
    </div>
    <div class="hfind__box">
      <form class="searchbar searchbar--xl" action="<?= e(url('/recherche/')) ?>" method="get" role="search">
        <label class="sr-only" for="q-home"><?= e(t('Rechercher')) ?></label>
        <input id="q-home" type="search" name="q" placeholder="<?= e(t('Ex. Sochaux – Nantes 1988, Genghini, Bonal, Coupe de France…')) ?>">
        <button type="submit"><?= e(t('Chercher')) ?></button>
      </form>
      <div class="hfind__links">
        <a href="<?= e(url('/matchs/')) ?>"><?= e(t('Matchs')) ?></a>
        <a href="<?= e(url('/saisons/')) ?>"><?= e(t('Saisons')) ?></a>
        <a href="<?= e(Site::catUrl(Site::C_JOUEURS)) ?>"><?= e(t('Joueurs')) ?></a>
        <a href="<?= e(url('/grands-recits/')) ?>"><?= e(t('Grands récits')) ?></a>
        <a href="<?= e(url('/face-a-face/')) ?>"><?= e(t('Face-à-face')) ?></a>
        <a href="<?= e(url('/records/')) ?>"><?= e(t('Records')) ?></a>
        <a href="<?= e(url('/palmares/')) ?>"><?= e(t('Palmarès')) ?></a>
      </div>
      <div class="hfind__dec">
        <span><?= e(t('Par décennie')) ?></span>
        <?php foreach ($decades as $d): ?><a href="<?= e($d['href']) ?>"><?= e($d['label']) ?></a><?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<section id="parcours" class="hband bg-navy">
  <div class="wrap">
    <div class="hband__head"><span class="eyebrow"><?= e(t('Le musée')) ?></span><h2 class="hband__t"><?= e(t('Un siècle de Lions, réuni dans un seul musée')) ?></h2></div>
    <div class="heras heras--full">
      <div class="eras eras--compact" role="tablist" data-eras>
        <?php foreach ($eras as $i => $era): ?>
          <button type="button" class="eras__tab<?= $i === 1 ? ' is-on' : '' ?>" role="tab" aria-selected="<?= $i === 1 ? 'true' : 'false' ?>" data-era="<?= $i ?>"><b><?= e($era['range']) ?></b><span><?= e(t($era['name'])) ?></span></button>
        <?php endforeach; ?>
      </div>
      <?php foreach ($eras as $i => $era): ?>
        <div class="eras__panel heras__panel<?= $i === 1 ? ' is-on' : '' ?>" data-era-panel="<?= $i ?>" role="tabpanel"<?= $i === 1 ? '' : ' hidden' ?>>
          <div class="heras__media"><?php if (!empty($era['image'])): ?><img src="<?= e(img($era['image'], 1200)) ?>" alt="" loading="lazy"><?php else: ?><span class="ph"><?= icon_photo() ?></span><?php endif; ?></div>
          <div class="heras__body">
            <span class="num yellow heras__range"><?= e($era['range']) ?></span>
            <b class="heras__name"><?= e(t($era['name'])) ?></b>
            <p><?= rich_inline(t($era['text'])) ?></p>
            <div class="facts facts--s"><?php foreach ($era['facts'] as $f): ?><div class="facts__row"><b><?= e($f['y']) ?></b><span><?= e(t($f['t'])) ?></span></div><?php endforeach; ?></div>
            <a class="link-under" href="<?= e(url($era['href'] ?? '/interactif/frise/')) ?>"><?= e(t('Visiter cette époque')) ?> →</a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
