<?php
/** @var array $c */
use LD\View;

$h = $c['hero'];
$fm = $c['formats'];
$fi = $fm['items'][0] ?? [];
$stages = ['linear-gradient(135deg,#cc1517,#e8402a)', '#161922', '#cf1d1a'];
$review = LD\Reviews::featured(1, 'Formation')[0] ?? LD\Reviews::featured(1)[0] ?? null;
$gBadge = $review ? null : LD\GoogleReviews::badge();
?>
<section class="hero hero--formation" aria-labelledby="fo-title">
  <div class="dots dots--hero2" aria-hidden="true"></div>
  <div class="hero__in">
    <div class="hero__txt hero__txt--28">
      <a class="new-pill" href="#formation-ia"><span class="badge-new badge-new--pill"><?= t($h['badge']) ?></span><?= t($h['badge_text']) ?></a>
      <h1 class="hero__title hero__title--fo" id="fo-title"><?= t($h['title']) ?> <span class="grad-text"><?= t($h['title_accent']) ?></span></h1>
      <p class="hero__p hero__p--580"><?= t($h['text']) ?></p>
      <div class="btns">
        <a class="btn btn-red btn-xl" href="/on-prend-rdv"><?= t($h['cta_primary']) ?></a>
        <a class="btn btn-outline btn-xl2" href="#programme"><?= t($h['cta_secondary']) ?></a>
      </div>
      <p class="note"><span class="dot-green" aria-hidden="true"></span><?= t($h['note']) ?></p>
    </div>
    <div class="fo-vis">
      <div class="fo-vis__photo"><?= slot($h['photo'], 'Photo de Frédéric en formation', $h['photo_alt'], ['eager' => true, 'sizes' => '(max-width: 600px) 92vw, 540px']) ?></div>
      <div class="fo-vis__float"><span><?= t($h['float_label']) ?></span><b><?= t($h['float_title']) ?></b></div>
    </div>
  </div>
</section>

<section class="sec">
  <div class="wrap">
    <h2 class="h2 mb48" data-reveal><?= t($c['why']['title']) ?></h2>
    <div class="ag" style="--min:320px">
      <?php foreach ($c['why']['items'] as $i => $w): ?>
        <div class="why<?= $i === 0 ? ' why--ink' : '' ?>" data-reveal<?= $i ? ' data-delay="' . ($i * 90) . '"' : '' ?>>
          <span class="why__n"><?= sprintf('%02d', $i + 1) ?></span>
          <h3><?= t($w['t']) ?></h3>
          <?php if (trim((string) $w['d']) !== ''): ?><p><?= t($w['d']) ?></p><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="sec sec--rose">
  <div class="wrap">
    <h2 class="h2 mb20" data-reveal><?= t($fm['title']) ?></h2>
    <p class="lead mb44" data-reveal><?= t($fm['intro']) ?></p>
    <div class="fmt-tabs" role="tablist" data-formats="<?= json_attr(array_map(static fn($f, $i) => $f + ['stage' => $stages[$i % 3]], $fm['items'], array_keys($fm['items']))) ?>">
      <?php foreach ($fm['items'] as $i => $f): ?><button type="button" role="tab" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"<?= $i === 0 ? ' class="is-on"' : '' ?> data-fmt="<?= $i ?>"><?= t($f['t']) ?></button><?php endforeach; ?>
    </div>
    <div class="fmt" data-reveal="scale">
      <div class="fmt__txt">
        <span class="kicker" data-fmt-k><?= t($fi['k'] ?? '') ?></span>
        <h3 class="fmt__t" data-fmt-t><?= t($fi['t'] ?? '') ?></h3>
        <p class="fmt__d" data-fmt-d><?= t($fi['d'] ?? '') ?></p>
        <p class="fmt__detail" data-fmt-detail<?= empty($fi['detail']) ? ' hidden' : '' ?>><?= t($fi['detail'] ?? '') ?></p>
        <a class="btn btn-dark btn-md self-start" href="/on-prend-rdv"><?= t($fm['cta']) ?></a>
      </div>
      <div class="fmt__stage" data-fmt-stage style="background:<?= e($stages[0]) ?>"><span data-fmt-big><?= t($fi['big'] ?? '') ?></span></div>
    </div>
  </div>
</section>

<section class="sec" id="programme">
  <div class="wrap wrap--1000">
    <div class="prog-head" data-reveal>
      <h2 class="h2"><?= t($c['programme']['title']) ?></h2>
    </div>
    <div class="acc" data-accordion>
      <?php $last = count($c['programme']['modules']) - 1; foreach ($c['programme']['modules'] as $i => $m): ?>
        <div class="acc__it<?= $i === 0 ? ' is-open' : '' ?><?= $i === $last ? ' acc__it--ia' : '' ?>" data-reveal>
          <button type="button" class="acc__btn" aria-expanded="<?= $i === 0 ? 'true' : 'false' ?>" aria-controls="mod-<?= $i ?>">
            <span class="acc__n"><?= sprintf('%02d', $i + 1) ?></span><span class="acc__t"><?= t($m['t']) ?></span><span class="acc__plus" aria-hidden="true">+</span>
          </button>
          <p class="acc__d" id="mod-<?= $i ?>"<?= $i === 0 ? '' : ' hidden' ?>><?= t($m['d']) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="sec sec--ink" id="formation-ia">
  <div class="glow glow--fo" aria-hidden="true"></div>
  <div class="wrap fo-ia">
    <?php $ia = $c['ia']; ?>
    <div class="fo-ia__l" data-reveal>
      <span class="badge-new badge-new--lg"><?= t($ia['badge']) ?></span>
      <h2 class="h2 h2--foia"><?= t($ia['title']) ?></h2>
      <p class="lead lead--dk2"><?= t($ia['text']) ?></p>
      <div class="ag" style="--min:220px;--gap:12px">
        <div class="fo-ia__box"><b><?= t($ia['for_title']) ?></b><span><?= t($ia['for_text']) ?></span></div>
        <div class="fo-ia__box"><b><?= t($ia['why_title']) ?></b><span><?= t($ia['why_text']) ?></span></div>
      </div>
      <?php if (trim((string) $ia['format']) !== ''): ?><p class="fo-ia__fmt"><?= t($ia['format']) ?></p><?php endif; ?>
      <a class="btn btn-white btn-lg btn-hover-lime self-start" href="/on-prend-rdv"><?= t($ia['cta']) ?></a>
    </div>
    <div class="fo-ia__r" data-reveal data-delay="120">
      <span class="kicker kicker--muted"><?= t($ia['skills_title']) ?></span>
      <?php foreach ($ia['skills'] as $sk): ?><div class="skill"><?= tick(30, 12) ?><span><?= t($sk) ?></span></div><?php endforeach; ?>
    </div>
  </div>
</section>

<section class="sec">
  <div class="wrap ag" style="--min:400px">
    <?php $fn = $c['financing']; ?>
    <div class="fin" data-reveal>
      <h2 class="h2 h2--fin"><?= t($fn['title']) ?></h2>
      <p class="fin__p"><?= t($fn['text']) ?></p>
      <?php if (trim((string) $fn['more']) !== ''): ?><p class="fin__p"><?= t($fn['more']) ?></p><?php endif; ?>
    </div>
    <div class="fin-rv" data-reveal data-delay="100">
      <?php if ($review): ?>
        <span class="rv__stars" role="img" aria-label="<?= (int) $review['rating'] ?> étoiles sur 5"><?= str_repeat('★', max(1, min(5, (int) $review['rating']))) ?></span>
        <blockquote class="fin-rv__q"><?= tp($review['text']) ?></blockquote>
        <p class="fin-rv__who"><b><?= e($review['name']) ?></b><?= trim((string) ($review['activity'] ?? '')) !== '' ? ' · ' . e($review['activity']) : '' ?></p>
      <?php elseif ($gBadge): ?>
        <?= View::partial('google-badge', ['badge' => $gBadge, 'write' => true]) ?>
      <?php endif; ?>
      <a class="fin-rv__link" href="/avis"><?= t($c['reviews']['link']) ?></a>
    </div>
  </div>
</section>

<?= View::partial('final-grad', ['title' => $c['final']['title'], 'text' => null, 'cta' => $c['final']['cta']]) ?>
