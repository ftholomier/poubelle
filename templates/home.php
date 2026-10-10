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

<?php /* Sous le slider : chiffres du club sur une bande, puis des bandes de cartes côte à côte (accueil compact). */ ?>
<section class="hstrip" aria-label="<?= e(t('Le club en chiffres')) ?>">
  <div class="hstrip__grid">
    <?php foreach ($palmares as $p): ?>
      <a class="hstrip__cell hstrip__cell--y" href="<?= e(url('/palmares/')) ?>"><span class="num" data-count><?= e($p['years']) ?></span><small><?= e(t($p['title'])) ?></small></a>
    <?php endforeach; ?>
    <?php foreach ($counters as $c): ?>
      <div class="hstrip__cell" title="<?= e($c['text']) ?>"><span class="num" data-count><?= e($c['n']) ?></span><small><?= e($c['label']) ?></small></div>
    <?php endforeach; ?>
  </div>
</section>

<section class="hband" aria-label="<?= e(t('Aujourd’hui au musée')) ?>">
  <div class="wrap">
    <div class="hband__head"><span class="eyebrow"><?= e(t('Aujourd’hui au musée')) ?></span></div>
    <div class="hbento hbento--today">
      <?php if ($jour): ?>
      <a class="hcard hcard--jour" href="<?= e(url($jour['path'])) ?>" data-reveal>
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
      <a class="hcard hcard--fig" href="<?= e(url('/chiffres/') . '#' . $cs['key']) ?>" data-reveal>
        <span class="hcard__k"><?= e(t('Le chiffre du jour')) ?> · <?= e(t('N° {n} sur {t}', ['n' => $cs['n'], 't' => $chiffre['count']])) ?></span>
        <span class="hcard__big"><b class="num"<?= preg_match('/^\d{1,3}(?:[\x{00A0}\x{202F} ,]\d{3})*$/u', $cs['value']) ? ' data-count' : '' ?>><?= e($cs['value']) ?></b><?php if ($cs['unit'] !== ''): ?> <small><?= e($cs['unit']) ?></small><?php endif; ?></span>
        <span class="hcard__t hcard__t--s"><?= e($cs['label']) ?></span>
        <span class="hcard__more"><?= e(t('Les {n} chiffres du FCSM', ['n' => $chiffre['count']])) ?> →</span>
      </a>
      <?php endif; ?>
      <div class="hcard hcard--cent" data-reveal>
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

<section id="parcours" class="hband bg-navy">
  <div class="wrap">
    <div class="hband__head"><span class="eyebrow"><?= e(t('Le musée')) ?></span><h2 class="hband__t"><?= e(t('Un siècle de Lions, réuni dans un seul musée')) ?></h2></div>
    <div class="hbento hbento--museum<?= empty($teaser) ? ' hbento--one' : '' ?>">
      <?php if (!empty($teaser)): ?>
      <video class="hteaser__video" controls playsinline preload="none" poster="<?= e(teaser_src('jpg', !\App\Front\Seo::closed())) ?>" width="1920" height="1080" aria-label="<?= e(t('Teaser vidéo du musée')) ?>" data-reveal>
        <source src="<?= e(teaser_src('mp4', !\App\Front\Seo::closed())) ?>" type="video/mp4">
      </video>
      <?php endif; ?>
      <div class="heras">
        <span class="hcard__k"><?= e(t('Les grandes époques')) ?></span>
        <div class="eras eras--compact" role="tablist" data-eras>
          <?php foreach ($eras as $i => $era): ?>
            <button type="button" class="eras__tab<?= $i === 1 ? ' is-on' : '' ?>" role="tab" aria-selected="<?= $i === 1 ? 'true' : 'false' ?>" data-era="<?= $i ?>"><b><?= e($era['range']) ?></b><span><?= e(t($era['name'])) ?></span></button>
          <?php endforeach; ?>
        </div>
        <?php foreach ($eras as $i => $era): ?>
          <div class="eras__panel heras__panel<?= $i === 1 ? ' is-on' : '' ?>" data-era-panel="<?= $i ?>" role="tabpanel"<?= $i === 1 ? '' : ' hidden' ?>>
            <div class="heras__media"><?php if (!empty($era['image'])): ?><img src="<?= e(img($era['image'], 640)) ?>" alt="" loading="lazy"><?php else: ?><span class="ph"><?= icon_photo() ?></span><?php endif; ?></div>
            <div class="heras__body">
              <span class="num yellow heras__range"><?= e($era['range']) ?></span>
              <b class="heras__name"><?= e(t($era['name'])) ?></b>
              <p><?= rich_inline(t($era['text'])) ?></p>
              <div class="facts facts--s"><?php foreach (array_slice($era['facts'], 0, 3) as $f): ?><div class="facts__row"><b><?= e($f['y']) ?></b><span><?= e(t($f['t'])) ?></span></div><?php endforeach; ?></div>
              <a class="link-under" href="<?= e(url($era['href'] ?? '/interactif/frise/')) ?>"><?= e(t('Visiter cette époque')) ?> →</a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<section id="collections" class="hband">
  <div class="wrap">
    <div class="hband__head"><span class="eyebrow"><?= e(t('Les collections')) ?></span><h2 class="hband__t"><?= e(t('Explorer le musée')) ?></h2></div>
    <div class="hbento hbento--explore">
      <div>
        <span class="hcard__k"><?= e(t('Les réserves du musée')) ?></span>
        <div class="htiles htiles--res">
          <?php foreach ($reserves as $r): ?>
            <a class="htile" href="<?= e($r['href']) ?>">
              <?php if (!empty($r['image'])): ?><img src="<?= e(img($r['image'], 480)) ?>" alt="" loading="lazy"><?php else: ?><span class="ph"><?= icon_photo() ?></span><?php endif; ?>
              <span class="htile__cap"><?= e(t($r['name'])) ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php if ($legends): ?>
      <div id="legendes">
        <span class="hcard__k"><?= e(t('Ils ont porté le lion')) ?> <a class="link-under hband__all" href="<?= e(Site::catUrl(Site::C_JOUEURS)) ?>"><?= e(t('Tous les joueurs')) ?> →</a></span>
        <div class="htiles htiles--leg">
          <?php foreach ($legends as $l): ?>
            <a class="htile htile--port" href="<?= e(url($l['path'])) ?>">
              <img src="<?= e(img($l['image'], 320)) ?>" alt="<?= e($l['p']['name']) ?>" loading="lazy">
              <span class="htile__cap"><?= e($l['p']['name']) ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<section class="hband bg-navy">
  <div class="wrap">
    <div class="hband__head"><span class="eyebrow"><?= e(t('À vivre')) ?></span><h2 class="hband__t"><?= e(t('Jouer et s’offrir l’histoire')) ?></h2></div>
    <div class="hbento hbento--play<?= empty($shop) ? ' hbento--one' : '' ?>">
      <div>
        <span class="hcard__k"><?= e(t("Jouez avec l'histoire")) ?></span>
        <div class="htiles htiles--play">
          <?php
          $tz = [
              ['href' => '/interactif/quiz/', 'k' => 'Quiz supporters', 't' => 'Êtes-vous un vrai Lionceau ?', 'img' => $teasers['quiz'] ?? null],
              ['href' => '/interactif/maillots/', 'k' => 'Symboles', 't' => 'Le comparateur de maillots', 'img' => $teasers['maillots'] ?? null],
              ['href' => '/interactif/frise/', 'k' => 'Frise', 't' => "De 1928 à aujourd'hui", 'img' => $teasers['frise'] ?? null],
          ];
          foreach ($tz as $x): ?>
            <a class="hplay" href="<?= e(url($x['href'])) ?>">
              <span class="hplay__media"><?php if ($x['img']): ?><img src="<?= e(img($x['img'], 480)) ?>" alt="" loading="lazy"><?php else: ?><span class="ph"><?= icon_photo() ?></span><?php endif; ?></span>
              <span class="hcard__k"><?= e(t($x['k'])) ?></span>
              <span class="hplay__t"><?= e(t($x['t'])) ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php if (!empty($shop)): ?>
      <div id="boutique">
        <span class="hcard__k"><?= e(t('La boutique du musée')) ?> · <?= e(t('Portez l’histoire du FCSM.')) ?> <a class="link-under hband__all" href="<?= e(url('/boutique/')) ?>"><?= e(t('Découvrir la boutique')) ?> →</a></span>
        <div class="home-shop home-shop--s" style="--n:<?= count($shop) ?>">
          <?php foreach ($shop as $p): ?>
            <a class="home-shop__card" href="<?= e($p['href']) ?>">
              <span class="home-shop__img"><img src="<?= e($p['img']) ?>" alt="" loading="lazy" decoding="async"></span>
              <span class="home-shop__kind"><?= e($p['kind']) ?></span>
              <span class="home-shop__name"><?= e($p['name']) ?></span>
              <span class="home-shop__price"><?= e($p['price']) ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<section id="saisons" class="hband bg-sand bt">
  <div class="wrap">
    <div class="hbento hbento--part">
      <div class="hcard">
        <span class="hcard__k"><?= e(t('Les saisons')) ?></span>
        <span class="hcard__t hcard__t--s"><?= e(t('Retrouvez une saison')) ?></span>
        <form class="searchbar" action="<?= e(url('/recherche/')) ?>" method="get" role="search">
          <label class="sr-only" for="q-season"><?= e(t('Rechercher')) ?></label>
          <input id="q-season" name="q" placeholder="<?= e(t('Ex. 1980-81, Genghini, Coupe de France…')) ?>">
          <button type="submit"><?= e(t('Chercher')) ?></button>
        </form>
        <div class="chips chips--s">
          <?php foreach ($decades as $d): ?><a class="chip" href="<?= e($d['href']) ?>"><?= e($d['label']) ?></a><?php endforeach; ?>
          <a class="chip" href="<?= e(url('/saisons/')) ?>"><?= e(t('Toutes les saisons')) ?> →</a>
        </div>
      </div>
      <div id="contribuer" class="hcard hcard--contrib">
        <span class="hcard__media"><?php $cimg = $teasers['contribuer'] ?? null; if ($cimg): ?><img src="<?= e(img($cimg, 480)) ?>" alt="" loading="lazy"><?php else: ?><span class="ph"><?= icon_photo() ?></span><?php endif; ?></span>
        <span class="hcard__body">
          <span class="hcard__k"><?= e(t('Contribuer')) ?></span>
          <span class="hcard__t hcard__t--s"><?= e(t("Votre grenier fait partie de l'histoire.")) ?></span>
          <span class="hcard__meta"><?= e(t("Un billet de Bonal, une photo de famille au stade, un fanion oublié ? Confiez-nous une image : nous l'ajoutons aux collections, avec votre nom.")) ?></span>
          <a class="btn btn--navy btn--sm" href="<?= e(url('/contribuer/')) ?>" style="align-self:flex-start"><?= e(t('Proposer un objet')) ?></a>
        </span>
      </div>
    </div>
  </div>
</section>
