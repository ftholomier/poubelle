<?php $n = (array) content('network'); $stats = (array) content('stats'); $values = (array) content('values'); ?>

<?php partial('page-hero', [
    'eyebrow' => $n['eyebrow'] ?? 'Le réseau',
    'title' => $n['title'] ?? '',
    'lead' => $n['lead'] ?? '',
    'actions' => true,
    'ctaSource' => 'network-hero',
]); ?>

<section class="section section--tight">
  <div class="container">
    <div class="stats" data-reveal>
      <?php foreach ($stats as $s): ?>
        <div class="stat">
          <div class="stat__value"><span class="count" data-count="<?= e((string) ($s['value'] ?? 0)) ?>">0</span><?= e($s['suffix'] ?? '') ?></div>
          <div class="stat__label"><?= e($s['label'] ?? '') ?></div>
          <div class="stat__sub"><?= e($s['sub'] ?? '') ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section">
  <span class="glow glow--amber" style="width:600px;height:600px;top:-180px;right:-260px;opacity:.22" aria-hidden="true"></span>
  <div class="container">
    <div class="network">
      <div data-reveal="left">
        <span class="eyebrow">Notre histoire</span>
        <h2 class="h2" style="margin:16px 0 22px"><?= e($n['story_title'] ?? '') ?></h2>
        <div class="stack" style="--gap:16px">
          <?php foreach ((array) ($n['story'] ?? []) as $para): ?>
            <p class="lead" style="font-size:1.02rem"><?= e($para) ?></p>
          <?php endforeach; ?>
        </div>
        <div class="cities">
          <?php foreach ((array) ($n['cities'] ?? []) as $c): ?>
            <span class="city"><?= icon('pin') ?> <?= e($c) ?></span>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="pillars" data-reveal="right">
        <?php foreach ((array) ($n['pillars'] ?? []) as $p): ?>
          <div class="pillar">
            <span class="pillar__n"><?= e($p['n'] ?? '') ?></span>
            <div>
              <h3><?= e($p['title'] ?? '') ?></h3>
              <p><?= e($p['text'] ?? '') ?></p>
            </div>
          </div>
        <?php endforeach; ?>
        <a class="btn btn--block btn--magnet" style="margin-top:8px" href="<?= e(url('candidater')) ?>" data-cta="network-pillars">
          Rejoindre le réseau <?= icon('arrow') ?>
        </a>
      </div>
    </div>
  </div>
</section>

<section class="section section--tight">
  <div class="container">
    <div class="section-head" data-reveal style="margin-bottom:36px">
      <span class="eyebrow"><?= e($values['eyebrow'] ?? '') ?></span>
      <h2 class="h2" style="margin-top:16px">Trois valeurs qui décident de tout.</h2>
    </div>
    <div class="values">
      <?php foreach ((array) ($values['items'] ?? []) as $i => $v): ?>
        <article class="value" data-reveal style="--d:<?= $i * 110 ?>ms">
          <div class="value__n">0<?= $i + 1 ?></div>
          <h3 class="h3"><?= e($v['title'] ?? '') ?></h3>
          <p class="muted" style="margin-top:10px"><?= e($v['text'] ?? '') ?></p>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php partial('reviews'); ?>
<?php partial('cta-final'); ?>
