<?php
$site = Content::site();
$ui = Content::ui();
$h = Content::page('home');
$hero = $h['hero'];
$products = Content::products();
$reviews = Content::reviews();
$rating = Content::rating();
$news = array_slice(Content::posts(), 0, 3);
$devis = Content::path('devis');
// Titre du héros : le mot mis en avant passe en jaune
$title = e($hero['title']);
if (!empty($hero['highlight'])) {
    $hl = e($hero['highlight']);
    $title = preg_replace('/' . preg_quote($hl, '/') . '/u', '<em>' . $hl . '</em>', $title, 1);
}
$title = preg_replace('/ (&amp;|&) /u', '<br>$1 ', (string) $title, 1);
$title = preg_replace('/ ([!?:;])/u', '&nbsp;$1', (string) $title);
$partners = array_values(array_filter($ui['partners']['logos'] ?? [], fn($l) => !empty($l['image'])));
$num = 0;
$n = function () use (&$num) { return sprintf('%02d', ++$num); };
?>
<div class="rv-herowrap" id="rv-herowrap">
  <section class="rv-hero">
    <video class="rv-hero__video" id="rv-video" src="<?= e($hero['video']) ?>" poster="<?= e($hero['poster']) ?>" autoplay muted loop playsinline disablepictureinpicture preload="auto" aria-hidden="true" tabindex="-1"></video>
    <img class="rv-hero__shape rv-hero__shape--lg" src="/assets/img/forme-gauche-gris.png" alt="">
    <img class="rv-hero__shape rv-hero__shape--lt" src="/assets/img/forme-gauche-trait.png" alt="">
    <img class="rv-hero__shape rv-hero__shape--r" src="/assets/img/forme-droite.png" alt="">
    <div class="rv-hero__in">
      <div class="rv-hero__txt">
        <?php if (!empty($hero['badge'])): ?><div class="rv-hero__badge" data-reveal="up" data-reveal-delay="60"><?= e($hero['badge']) ?></div><?php endif; ?>
        <h1 data-reveal="up" data-reveal-delay="150"><?= $title ?></h1>
        <?php if (!empty($hero['text'])): ?><p class="rv-hero__p" data-reveal="up" data-reveal-delay="260"><?= e($hero['text']) ?></p><?php endif; ?>
        <div class="rv-hero__btns" data-reveal="up" data-reveal-delay="360">
          <?= View::btn(($ui['cta_bar']['devis'] ?? 'Demandez un devis') . ' →', $devis, 'btn-y') ?>
          <?= View::btn($site['phone'], 'tel:' . tel($site['phone']), 'btn-ow') ?>
        </div>
        <?php if (!empty($hero['checks'])): ?>
          <div class="rv-hero__checks" data-reveal="up" data-reveal-delay="460">
            <?php foreach ($hero['checks'] as $c): ?><span>✓ <?= e($c) ?></span><?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </section>
  <section class="rv-stats" aria-label="Chiffres clés">
    <div class="rv-stats__in">
      <?php foreach ($h['stats'] as $s): ?>
        <div>
          <div class="rv-stats__n" data-reveal="zoom" data-count="<?= (int) $s['value'] ?>" data-prefix="<?= e($s['prefix'] ?? '') ?>" data-suffix="<?= e($s['suffix'] ?? '') ?>"><?= e(($s['prefix'] ?? '') . $s['value'] . ($s['suffix'] ?? '')) ?></div>
          <div class="rv-stats__l"><?= e($s['label']) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
</div>

<?php if ($partners): ?>
<section class="rv-partners">
  <div class="rv-partners__in">
    <div class="rv-partners__t"><?= e($ui['partners']['title'] ?? '') ?></div>
    <div class="rv-partners__g">
      <?php foreach ($partners as $p): ?>
        <div><?php if (!empty($p['url'])): ?><a href="<?= e($p['url']) ?>" target="_blank" rel="noopener"><?php endif; ?><img src="<?= e($p['image']) ?>" alt="<?= e($p['name'] ?? '') ?>" loading="lazy"><?php if (!empty($p['url'])): ?></a><?php endif; ?></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="sec">
  <div class="sec__head" data-reveal="up">
    <div style="max-width:640px">
      <?= View::eyebrow($n(), $h['products']['eyebrow']) ?>
      <h2 class="h2 h2--xl"><?= e($h['products']['title']) ?></h2>
    </div>
    <a class="lnk" href="<?= e(Content::path('produits')) ?>"><?= e($h['products']['link']) ?> →</a>
  </div>
  <div class="grid-prod">
    <?php foreach ($products as $i => $p): ?>
      <a class="pcard" href="<?= e($p['path']) ?>" data-reveal="up" data-reveal-delay="<?= $i * 80 ?>">
        <img src="<?= e($p['image']) ?>" alt="<?= e($p['title']) ?>" loading="lazy">
        <div class="pcard__shade"></div>
        <div class="pcard__b">
          <div class="pcard__bar"></div>
          <div class="pcard__t"><?= e($p['title']) ?></div>
          <div class="pcard__s"><?= e(excerpt((string) $p['summary'], 92)) ?></div>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<section class="sec">
  <div data-reveal="up">
    <?= View::eyebrow($n(), $h['philosophy']['title']) ?>
  </div>
  <div class="philo">
    <?php foreach ($h['philosophy']['items'] as $i => $f): $isDevis = mb_strtolower($f['title']) === 'devis'; ?>
      <<?= $isDevis ? 'a href="' . e($devis) . '"' : 'div' ?> class="philo__c" data-reveal="up" data-reveal-delay="<?= $i * 110 ?>">
        <div class="philo__s"><?= sprintf('%02d', $i + 1) ?> · <?= e($f['title']) ?></div>
        <div class="philo__t"><?= e($f['title']) ?></div>
        <div class="philo__bar"></div>
        <div class="philo__x"><?= e($f['text']) ?></div>
      </<?= $isDevis ? 'a' : 'div' ?>>
    <?php endforeach; ?>
  </div>
</section>

<?php $pr = $h['presentation']; ?>
<section class="split">
  <div class="split__in">
    <div class="split__t" data-reveal="left">
      <?= View::eyebrow($n(), $pr['eyebrow'], true) ?>
      <h2 class="h2"><?= e($pr['title']) ?></h2>
      <p class="split__p"><?= e($pr['text']) ?></p>
      <?= View::btn($pr['button'], Content::path('presentation'), 'btn-y split__btn') ?>
    </div>
    <div class="split__img" data-reveal="right"><img src="<?= e($pr['image']) ?>" alt="<?= e($site['name']) ?>" loading="lazy"></div>
  </div>
</section>

<?php if ($reviews): ?>
<section class="sec">
  <div class="sec__head" data-reveal="up" style="gap:20px 40px">
    <div>
      <?= View::eyebrow($n(), $h['reviews']['eyebrow']) ?>
      <h2 class="h2"><?= e($h['reviews']['title']) ?></h2>
    </div>
    <div class="rating">
      <div style="display:flex;align-items:center;gap:14px">
        <div class="rating__n"><?= e($rating['label']) ?></div>
        <div>
          <div class="stars">★★★★★</div>
          <div class="rating__c"><a href="<?= e(Content::path('avis')) ?>"><?= (int) $rating['count'] ?> <?= e($rating['source']) ?></a></div>
        </div>
      </div>
      <div class="arrows">
        <button class="arrow" type="button" data-avis="-1" aria-label="Avis précédent">←</button>
        <button class="arrow" type="button" data-avis="1" aria-label="Avis suivant">→</button>
      </div>
    </div>
  </div>
  <div class="avis-track" id="rv-avis">
    <?php foreach ($reviews as $i => $a): ?>
      <?= View::reviewCard($a, $i < 3 ? $i * 110 : 0) ?>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($news): $actu = Content::page('actu'); ?>
<section class="sec">
  <div class="sec__head" data-reveal="up">
    <div>
      <?= View::eyebrow($n(), $h['news']['eyebrow']) ?>
      <h2 class="h2"><?= e($h['news']['title']) ?></h2>
    </div>
    <div style="display:flex;flex-wrap:wrap;gap:12px 22px;align-items:center">
      <?php if (!empty($site['facebook'])): ?><a class="fb-link" href="<?= e($site['facebook']) ?>" target="_blank" rel="noopener"><span class="fb-f">f</span><?= e($actu['facebook_text'] ?? 'Suivez-nous sur Facebook') ?></a><?php endif; ?>
      <a class="lnk" href="<?= e(Content::path('actu')) ?>">Toutes les actus →</a>
    </div>
  </div>
  <div class="grid-3">
    <?php foreach ($news as $i => $p): ?><?= View::newsCard($p, $i) ?><?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php View::render('partials/cta_block', ['cta' => $h['cta']]) ?>
