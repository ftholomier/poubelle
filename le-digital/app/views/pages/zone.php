<?php
/** @var array $c  @var array $city */
$fill = static fn(string $s) => strtr($s, ['{prep}' => $city['prep'], '{of}' => $city['of'], '{city}' => $city['name']]);
?>
<section class="hero-z" aria-labelledby="zone-title">
  <div class="blob blob--line blob--zone" aria-hidden="true"></div>
  <div class="hero-z__in">
    <nav class="city-pills" aria-label="Zones d'intervention">
      <?php foreach ($c['cities'] as $ct): ?><a href="/zones/<?= e($ct['slug']) ?>"<?= $ct['slug'] === $city['slug'] ? ' class="is-on" aria-current="page"' : '' ?>><?= e($ct['name']) ?></a><?php endforeach; ?>
      <a href="/partout-en-france"><?= fr_flag() ?> Partout en France</a>
    </nav>
    <h1 class="hero-z__title" id="zone-title"><?= t($c['title']) ?> <span class="grad-text"><?= e($city['name']) ?></span></h1>
    <p class="hero-z__p"><?= t($fill($c['text'])) ?></p>
    <div class="btns">
      <a class="btn btn-red btn-xl" href="/on-prend-rdv"><?= t($fill($c['cta_primary'])) ?></a>
      <a class="btn btn-outline btn-xl2" href="/sur-mesure-ia"><?= t($c['cta_secondary']) ?></a>
    </div>
  </div>
</section>
<section class="sec">
  <div class="wrap wrap--zone ag" style="--min:300px">
    <?php foreach ($c['cards'] as $i => $card): ?>
      <a class="zcard zcard--<?= $i % 3 ?>" href="<?= e($card['href']) ?>" data-reveal<?= $i ? ' data-delay="' . ($i * 90) . '"' : '' ?>><h2><?= t($card['title']) ?></h2><p><?= t($fill($card['text'])) ?></p></a>
    <?php endforeach; ?>
  </div>
</section>
