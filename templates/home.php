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
      <img src="<?= e(img($s['image'], 1600)) ?>" srcset="<?= e(srcset($s['image'], [800, 1200, 1600])) ?>" sizes="100vw" alt="" <?= $i === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?> style="object-position:<?= e($s['focus']) ?>">
    </div>
  <?php endforeach; ?>
  <div class="hero__shade" aria-hidden="true"></div>
  <div class="hero__ui wrap">
    <div class="hero__top">
      <div class="hero__text" data-hero-text>
        <?php foreach ($slides as $i => $s): ?>
          <div class="hero__copy<?= $i === 0 ? ' is-on' : '' ?>" data-copy="<?= $i ?>">
            <span class="eyebrow eyebrow--yellow eyebrow--lg"><?= e($s['kind']) ?></span>
            <<?= $i === 0 ? 'h1' : 'h2' ?> class="hero__title"><?= e($s['title']) ?></<?= $i === 0 ? 'h1' : 'h2' ?>>
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

<section class="palmares">
  <div class="palmares__grid wrap--flush">
    <?php foreach ($palmares as $p): ?>
      <div class="palmares__cell" data-reveal>
        <span class="num palmares__n" data-count><?= e($p['years']) ?></span>
        <span class="palmares__t"><?= e(t($p['title'])) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<section class="counters">
  <div class="counters__grid">
    <?php foreach ($counters as $c): ?>
      <div class="counters__cell" data-reveal>
        <span class="num counters__n" data-count><?= e($c['n']) ?></span>
        <span class="counters__l"><?= e($c['label']) ?></span>
        <span class="counters__t"><?= e($c['text']) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<?php if ($jour): ?>
<section class="wrap" style="padding-top:var(--section)">
  <div class="today" data-reveal>
    <a class="today__media" href="<?= e(url($jour['path'])) ?>" tabindex="-1" aria-hidden="true">
      <?php if ($jour['image']): ?><img src="<?= e(img($jour['image'], 1200)) ?>" srcset="<?= e(srcset($jour['image'], [640, 1200])) ?>" sizes="(max-width:900px) 100vw, 50vw" alt="" loading="lazy"><?php else: ?><span class="ph"><?= icon_photo() ?></span><?php endif; ?>
    </a>
    <div class="today__body">
      <div class="row gap-14"><span class="badge"><?= e(t('Ce jour-là')) ?></span><span class="italic muted" style="font-size:18px"><?= e($jourLabel) ?></span></div>
      <h2 class="h-2" style="font-size:clamp(38px,4.6vw,64px)"><?= e(($jour['home'] ?: $jour['event']) . ($jour['away'] ? ' – ' . $jour['away'] : '')) ?></h2>
      <div class="row gap-14">
        <?php if ($jour['us'] !== null): ?><span class="num today__score"><?= e($jour['sh'] ? $jour['us'] . ' – ' . $jour['them'] : $jour['them'] . ' – ' . $jour['us']) ?></span><?php endif; ?>
        <span class="muted" style="font-size:18px"><?= e(trim(($jour['label'] ?: $jour['comp']) . ' · ' . substr((string) $jour['date'], 0, 4), ' ·')) ?></span>
      </div>
      <p class="text-lg" style="margin:0;font-size:19px"><?= e(t("Chaque jour, le musée ressort un match qui s'est joué à la même date.")) ?></p>
      <a class="btn btn--navy" href="<?= e(url($jour['path'])) ?>" style="align-self:flex-start"><?= e(t('Lire la fiche du match')) ?></a>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if (!empty($chiffre)): $cs = $chiffre['stat']; ?>
<section class="wrap" style="padding-top:clamp(48px,6vw,80px)" aria-labelledby="chiffre-du-jour">
  <div class="dayfig" data-reveal>
    <div class="dayfig__fig">
      <span class="badge badge--yellow"><?= e(t('Le chiffre du jour')) ?></span>
      <p class="dayfig__value"><b class="num"<?= preg_match('/^\d{1,3}(?:[\x{00A0}\x{202F} ,]\d{3})*$/u', $cs['value']) ? ' data-count' : '' ?>><?= e($cs['value']) ?></b><?php if ($cs['unit'] !== ''): ?> <span><?= e($cs['unit']) ?></span><?php endif; ?></p>
    </div>
    <div class="dayfig__body">
      <span class="dayfig__kicker"><?= e(t('N° {n} sur {t}', ['n' => $cs['n'], 't' => $chiffre['count']])) ?> · <?= e($chiffre['chapter']) ?></span>
      <h2 class="h-2 dayfig__label" id="chiffre-du-jour"><?= e($cs['label']) ?></h2>
      <?php if ($cs['who']): ?>
      <ul class="dayfig__who">
        <?php foreach (array_slice($cs['who'], 0, 2) as $w): ?>
          <li><a href="<?= e($w['href']) ?>"><span class="dayfig__img"><?php if (!empty($w['image'])): ?><img src="<?= e(img($w['image'], 160)) ?>" alt="" loading="lazy" width="40" height="40"><?php else: ?><span aria-hidden="true"><?= e(mb_strtoupper(mb_substr($w['name'], 0, 1))) ?></span><?php endif; ?></span><span><b><?= e($w['name']) ?></b><?php if (!empty($w['meta'])): ?><small><?= e($w['meta']) ?></small><?php endif; ?></span></a></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
      <p class="dayfig__text"><?= e($cs['text']) ?></p>
      <a class="btn btn--yellow" href="<?= e(url('/chiffres/') . '#' . $cs['key']) ?>" style="align-self:flex-start"><?= e(t('Les {n} chiffres du FCSM', ['n' => $chiffre['count']])) ?> →</a>
    </div>
  </div>
</section>
<?php endif; ?>

<section id="parcours" class="wrap section">
  <div class="sechead" data-reveal>
    <div class="stack gap-8" style="flex:1 1 420px;min-width:0">
      <span class="eyebrow"><?= e(t('Salle 1 · Le parcours')) ?></span>
      <h2 class="h-section"><?= e(t('Les grandes époques')) ?></h2>
    </div>
    <p class="muted" style="margin:0;max-width:40ch;font-size:19px;line-height:1.45"><?= e(t('Choisissez une époque pour parcourir ses saisons, ses joueurs et ses objets.')) ?></p>
  </div>
  <div class="eras" role="tablist" data-eras>
    <?php foreach ($eras as $i => $era): ?>
      <button type="button" class="eras__tab<?= $i === 1 ? ' is-on' : '' ?>" role="tab" aria-selected="<?= $i === 1 ? 'true' : 'false' ?>" data-era="<?= $i ?>">
        <b><?= e($era['range']) ?></b><span><?= e(t($era['name'])) ?></span>
      </button>
    <?php endforeach; ?>
  </div>
  <?php foreach ($eras as $i => $era): ?>
    <div class="eras__panel<?= $i === 1 ? ' is-on' : '' ?>" data-era-panel="<?= $i ?>" role="tabpanel"<?= $i === 1 ? '' : ' hidden' ?>>
      <div class="eras__media frame">
        <?php if (!empty($era['image'])): ?><img src="<?= e(img($era['image'], 1200)) ?>" alt="" loading="lazy"><?php else: ?><span class="ph"><?= icon_photo() ?></span><?php endif; ?>
      </div>
      <div class="stack gap-20 eras__body">
        <span class="num blue" style="font-size:clamp(56px,7vw,96px);line-height:.85"><?= e($era['range']) ?></span>
        <h3 class="h-3"><?= e(t($era['name'])) ?></h3>
        <p style="margin:0;font-size:20px;line-height:1.5"><?= rich_inline(t($era['text'])) ?></p>
        <div class="facts">
          <?php foreach ($era['facts'] as $f): ?>
            <div class="facts__row"><b><?= e($f['y']) ?></b><span><?= e(t($f['t'])) ?></span></div>
          <?php endforeach; ?>
        </div>
        <a class="link-under" href="<?= e(url($era['href'] ?? '/interactif/frise/')) ?>"><?= e(t('Visiter cette époque')) ?> →</a>
      </div>
    </div>
  <?php endforeach; ?>
</section>

<section id="collections" class="bg-navy">
  <div class="wrap section">
    <div class="stack gap-8 mb-40" data-reveal>
      <span class="eyebrow"><?= e(t('Salle 2 · Les collections')) ?></span>
      <h2 class="h-section"><?= e(t('Les réserves du musée')) ?></h2>
    </div>
    <div class="grid-auto" style="--min:280px">
      <?php foreach ($reserves as $r): ?>
        <a class="reserve" href="<?= e($r['href']) ?>" data-reveal>
          <div class="reserve__media">
            <?php if (!empty($r['image'])): ?><img src="<?= e(img($r['image'], 800)) ?>" alt="" loading="lazy"><?php else: ?><span class="ph" style="background:var(--navy-2)"><?= icon_photo() ?></span><?php endif; ?>
          </div>
          <div class="reserve__cap"><span><?= e(t($r['name'])) ?></span><i><?= e(t($r['desc'])) ?></i></div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php if ($legends): ?>
<section id="legendes" class="wrap section">
  <div class="sechead" data-reveal>
    <div class="stack gap-8" style="flex:1 1 420px;min-width:0">
      <span class="eyebrow"><?= e(t('Salle 3 · Les légendes')) ?></span>
      <h2 class="h-section"><?= e(t('Ils ont porté le lion')) ?></h2>
    </div>
    <a class="link-under" href="<?= e(Site::catUrl(Site::C_JOUEURS)) ?>"><?= e(t('Tous les joueurs')) ?> →</a>
  </div>
  <div class="grid-auto" style="--min:230px">
    <?php foreach ($legends as $l): ?>
      <a class="card" href="<?= e(url($l['path'])) ?>" data-reveal>
        <div class="card__media" style="--ratio:3/4"><img src="<?= e(img($l['image'], 480)) ?>" srcset="<?= e(srcset($l['image'], [320, 480, 800])) ?>" sizes="(max-width:600px) 90vw, 300px" alt="<?= e($l['p']['name']) ?>" loading="lazy"></div>
        <div class="card__body">
          <span class="h-card"><?= e($l['p']['name']) ?></span>
          <span class="card__meta"><?= e(ucfirst((string) ($l['p']['position'] ?: (in_array('entraineur', $l['p']['roles'], true) ? t('Entraîneur') : t('Joueur'))))) ?></span>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<section class="bg-sand bt">
  <div class="wrap section">
    <div class="stack gap-8 mb-40" data-reveal>
      <span class="eyebrow"><?= e(t('Salle 4 · À vivre')) ?></span>
      <h2 class="h-section"><?= e(t("Jouez avec l'histoire")) ?></h2>
    </div>
    <div class="grid-fit" style="--min:300px;--gap:24px;--align:stretch">
      <?php
      $tz = [
          ['href' => '/interactif/quiz/', 'k' => 'Quiz supporters', 't' => 'Êtes-vous un vrai Lionceau ?', 'd' => "Six questions sur l'histoire du club, un score à partager.", 'img' => $teasers['quiz'] ?? null],
          ['href' => '/interactif/maillots/', 'k' => 'Symboles', 't' => 'Le comparateur de maillots', 'd' => 'Faites glisser le curseur entre deux époques.', 'img' => $teasers['maillots'] ?? null],
          ['href' => '/interactif/frise/', 'k' => 'Frise', 't' => "De 1928 à aujourd'hui", 'd' => "Toute l'histoire du club sur une seule frise.", 'img' => $teasers['frise'] ?? null],
      ];
      foreach ($tz as $x): ?>
        <a class="card card--blue" href="<?= e(url($x['href'])) ?>" data-reveal>
          <div class="card__media" style="--ratio:16/10"><?php if ($x['img']): ?><img src="<?= e(img($x['img'], 800)) ?>" alt="" loading="lazy"><?php else: ?><span class="ph"><?= icon_photo() ?></span><?php endif; ?></div>
          <div class="card__body" style="padding:20px;gap:8px">
            <span class="eyebrow"><?= e(t($x['k'])) ?></span>
            <span class="h-display" style="font-size:32px"><?= e(t($x['t'])) ?></span>
            <span class="muted" style="font-size:17px;line-height:1.45"><?= e(t($x['d'])) ?></span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section id="don" class="bg-navy">
  <div class="wrap section grid-fit" style="--min:440px">
    <div class="stack gap-20" data-reveal>
      <span class="eyebrow"><?= e(t('Le centenaire')) ?></span>
      <h2 class="h-section"><?= e(t('Cent ans de lion.')) ?> <span class="yellow"><?= e(t('Aidez-nous à tout sauver.')) ?></span></h2>
      <p style="margin:0;font-size:20px;line-height:1.5;max-width:44ch"><?= e(t('Chaque don sert à numériser des archives : affiches, photos, programmes, coupures de presse.')) ?></p>
      <a class="btn btn--yellow" href="<?= e(url('/faire-un-don/')) ?>" style="align-self:flex-start">♥ <?= e(t('Faire un don')) ?></a>
    </div>
    <div class="stack gap-14" data-reveal>
      <a href="<?= e(url('/centenaire/')) ?>" class="display" style="font-weight:800;font-size:16px;letter-spacing:.14em;text-transform:uppercase;color:var(--yellow);border-bottom:2px solid var(--yellow);align-self:flex-start"><?= e(t('100 ans, 100 moments · Onze de légende')) ?> →</a>
      <span class="display" style="font-weight:700;font-size:16px;letter-spacing:.2em;text-transform:uppercase;color:var(--mist)"><?= e(t('Compte à rebours')) ?> · 20 <?= e(t('mai')) ?> 1928 → <?= e(date_fr($centenary)) ?></span>
      <?= countdown_html($centenary) ?>
    </div>
  </div>
</section>

<section id="saisons" class="bg-sand bt bb">
  <div class="wrap grid-fit" style="--min:360px;--gap:40px;padding-top:clamp(48px,6vw,80px);padding-bottom:clamp(48px,6vw,80px)">
    <div class="stack gap-14">
      <span class="eyebrow"><?= e(t('Les saisons')) ?></span>
      <h2 class="h-display" style="font-size:clamp(40px,5vw,64px)"><?= e(t('Retrouvez une saison')) ?></h2>
      <p class="muted" style="margin:0;font-size:19px;line-height:1.45"><?= e(t('Effectif, résultats, affiches et coupures de presse, saison après saison.')) ?></p>
    </div>
    <div class="stack gap-14">
      <form class="searchbar" action="<?= e(url('/recherche/')) ?>" method="get" role="search">
        <label class="sr-only" for="q-season"><?= e(t('Rechercher')) ?></label>
        <input id="q-season" name="q" placeholder="<?= e(t('Ex. 1980-81, Genghini, Coupe de France…')) ?>">
        <button type="submit"><?= e(t('Chercher')) ?></button>
      </form>
      <div class="chips">
        <?php foreach ($decades as $d): ?><a class="chip" href="<?= e($d['href']) ?>"><?= e($d['label']) ?></a><?php endforeach; ?>
        <a class="chip" href="<?= e(url('/saisons/')) ?>"><?= e(t('Toutes les saisons')) ?> →</a>
      </div>
    </div>
  </div>
</section>

<section id="contribuer" class="wrap section grid-fit" style="--min:400px">
  <div class="frame shadow-blue" style="aspect-ratio:1/1;max-height:460px" data-reveal>
    <?php $cimg = $teasers['contribuer'] ?? null; if ($cimg): ?><img src="<?= e(img($cimg, 800)) ?>" alt="" loading="lazy"><?php else: ?><span class="ph"><?= icon_photo() ?></span><?php endif; ?>
  </div>
  <div class="stack gap-20" data-reveal>
    <span class="eyebrow"><?= e(t('Contribuer')) ?></span>
    <h2 class="h-section"><?= e(t("Votre grenier fait partie de l'histoire.")) ?></h2>
    <p style="margin:0;font-size:20px;line-height:1.5;max-width:44ch"><?= e(t("Un billet de Bonal, une photo de famille au stade, un fanion oublié ? Confiez-nous une image : nous l'ajoutons aux collections, avec votre nom.")) ?></p>
    <a class="btn btn--navy" href="<?= e(url('/contribuer/')) ?>" style="align-self:flex-start"><?= e(t('Proposer un objet')) ?></a>
  </div>
</section>
