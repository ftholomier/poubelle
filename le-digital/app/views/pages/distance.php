<?php
/** Page « Partout en France, à distance ». @var array $c */
use LD\View;

$cities = LD\Content::get('zones')['cities'] ?? [];
?>
<section class="hero-z hero-z--dist" aria-labelledby="dist-title">
  <div class="blob blob--line blob--zone" aria-hidden="true"></div>
  <div class="hero-z__in">
    <nav class="city-pills" aria-label="Zones d'intervention">
      <a href="/partout-en-france" class="is-on" aria-current="page"><?= fr_flag() ?> Partout en France</a>
      <?php foreach ($cities as $ct): ?><a href="/zones/<?= e($ct['slug']) ?>"><?= e($ct['name']) ?></a><?php endforeach; ?>
    </nav>
    <?php if (trim((string) $c['kicker']) !== ''): ?><span class="dist-kicker"><?= t($c['kicker']) ?></span><?php endif; ?>
    <h1 class="hero-z__title" id="dist-title"><?= t($c['title']) ?> <span class="grad-text"><?= t($c['title_accent']) ?></span></h1>
    <p class="hero-z__p"><?= t($c['text']) ?></p>
    <div class="btns">
      <a class="btn btn-red btn-xl" href="/on-prend-rdv"><?= t($c['cta_primary']) ?></a>
      <a class="btn btn-outline btn-xl2" href="/accompagnement"><?= t($c['cta_secondary']) ?></a>
    </div>
    <?php if (!empty($c['stats'])): ?>
      <dl class="dist-stats">
        <?php foreach ($c['stats'] as $st): ?><div><dt><?= t($st['v']) ?></dt><dd><?= t($st['l']) ?></dd></div><?php endforeach; ?>
      </dl>
    <?php endif; ?>
  </div>
</section>

<section class="sec">
  <div class="wrap">
    <?= ask($c['how_ask']) ?>
    <h2 class="h2 mb48" data-reveal><?= t($c['how_title']) ?></h2>
    <div class="ag" style="--min:260px">
      <?php foreach ($c['how'] as $i => $st): ?>
        <div class="why<?= $i === 0 ? ' why--ink' : '' ?>" data-reveal<?= $i ? ' data-delay="' . ($i * 90) . '"' : '' ?>>
          <span class="why__n"><?= sprintf('%02d', $i + 1) ?></span>
          <h3><?= t($st['t']) ?></h3>
          <p><?= t($st['d']) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="sec sec--rose">
  <div class="wrap">
    <h2 class="h2 mb48" data-reveal><?= t($c['offers_title']) ?></h2>
    <div class="ag" style="--min:300px">
      <?php foreach ($c['offers'] as $i => $o): ?>
        <a class="zcard zcard--<?= $i % 3 ?>" href="<?= e($o['href']) ?>" data-reveal<?= $i ? ' data-delay="' . ($i * 90) . '"' : '' ?>><h3 class="zcard__t"><?= t($o['title']) ?></h3><p><?= t($o['text']) ?></p></a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="sec">
  <div class="wrap wrap--1000">
    <h2 class="h2 mb48" data-reveal><?= t($c['faq_title']) ?></h2>
    <div class="acc" data-accordion>
      <?php foreach ($c['faq'] as $i => $f): ?>
        <div class="acc__it<?= $i === 0 ? ' is-open' : '' ?>" data-reveal>
          <button type="button" class="acc__btn" aria-expanded="<?= $i === 0 ? 'true' : 'false' ?>" aria-controls="faq-<?= $i ?>">
            <span class="acc__n"><?= sprintf('%02d', $i + 1) ?></span><span class="acc__t"><?= t($f['q']) ?></span><span class="acc__plus" aria-hidden="true">+</span>
          </button>
          <p class="acc__d" id="faq-<?= $i ?>"<?= $i === 0 ? '' : ' hidden' ?>><?= t($f['a']) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
    <?php if ($cities): ?>
      <div class="dist-local" data-reveal>
        <h2 class="dist-local__t"><?= t($c['zones_title']) ?></h2>
        <p><?= t($c['zones_text']) ?></p>
        <nav class="city-pills" aria-label="Rencontres en Bourgogne-Franche-Comté">
          <?php foreach ($cities as $ct): ?><a href="/zones/<?= e($ct['slug']) ?>"><?= e($ct['name']) ?></a><?php endforeach; ?>
        </nav>
      </div>
    <?php endif; ?>
  </div>
</section>

<?= View::partial('final-dark', ['title' => $c['final_title'], 'text' => null, 'cta' => $c['final_cta'], 'variant' => 'acc']) ?>
