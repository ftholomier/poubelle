<?php $m = (array) content('missions'); $skills = (array) content('skills'); $steps = (array) content('steps'); ?>

<?php partial('page-hero', [
    'eyebrow' => $m['eyebrow'] ?? 'Le métier',
    'title' => $m['title'] ?? '',
    'lead' => $m['lead'] ?? '',
    'actions' => true,
    'ctaSource' => 'job-hero',
]); ?>

<section class="section">
  <span class="glow glow--red" style="width:560px;height:560px;top:-120px;left:-250px;opacity:.2" aria-hidden="true"></span>
  <div class="container">
    <div class="section-head" data-reveal>
      <span class="eyebrow">Les différentes missions</span>
      <h2 class="h2">Huit temps forts, du premier contact à la remise des clés.</h2>
      <p class="lead">Sélectionnez une mission pour découvrir ce qu’elle implique concrètement.</p>
    </div>

    <div class="missions" data-tabs data-reveal>
      <div class="mission-list" role="tablist" aria-label="Missions de l’agent commercial immobilier">
        <?php foreach ((array) ($m['items'] ?? []) as $i => $it): ?>
          <button class="mission-tab" type="button" role="tab"
                  id="mtab-<?= $i ?>" aria-controls="mpanel-<?= $i ?>" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>">
            <span class="mission-tab__n"><?= sprintf('%02d', $i + 1) ?></span>
            <span>
              <span class="mission-tab__title"><?= e($it['title'] ?? '') ?></span>
              <span class="mission-tab__short"><?= e($it['short'] ?? '') ?></span>
            </span>
          </button>
        <?php endforeach; ?>
      </div>

      <div>
        <?php foreach ((array) ($m['items'] ?? []) as $i => $it): ?>
          <div class="mission-panel" role="tabpanel" id="mpanel-<?= $i ?>" aria-labelledby="mtab-<?= $i ?>" <?= $i === 0 ? '' : 'hidden' ?> tabindex="0">
            <div class="mission-panel__n"><?= sprintf('%02d', $i + 1) ?></div>
            <h3 class="h2" style="font-size:clamp(1.5rem,2.6vw,2.1rem)"><?= e($it['title'] ?? '') ?></h3>
            <p><?= e($it['text'] ?? '') ?></p>
            <a class="btn" style="margin-top:26px" href="<?= e(url('candidater')) ?>" data-cta="job-mission-<?= $i ?>">
              Ce métier me parle, je candidate <?= icon('arrow') ?>
            </a>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<section class="section section--tight">
  <div class="container">
    <div class="section-head" data-reveal>
      <span class="eyebrow"><?= e($skills['eyebrow'] ?? '') ?></span>
      <h2 class="h2"><?= e($skills['title'] ?? '') ?></h2>
      <p class="lead"><?= e($skills['lead'] ?? '') ?></p>
    </div>
    <div class="skills">
      <?php foreach ((array) ($skills['items'] ?? []) as $i => $s): ?>
        <article class="skill" data-reveal style="--d:<?= min($i * 70, 350) ?>ms">
          <span class="skill__i"><?= sprintf('%02d', $i + 1) ?></span>
          <h3><?= e($s['title'] ?? '') ?></h3>
          <p><?= e($s['text'] ?? '') ?></p>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="section-head" data-reveal>
      <span class="eyebrow"><?= e($steps['eyebrow'] ?? '') ?></span>
      <h2 class="h2"><?= e($steps['title'] ?? '') ?></h2>
    </div>
    <div class="timeline">
      <?php foreach ((array) ($steps['items'] ?? []) as $st): ?>
        <div class="tl-item">
          <span class="tl-dot"><?= e($st['n'] ?? '') ?></span>
          <div class="tl-body">
            <span class="tl-duration"><?= icon('clock') ?> <?= e($st['duration'] ?? '') ?></span>
            <h3 class="h3"><?= e($st['title'] ?? '') ?></h3>
            <p><?= e($st['text'] ?? '') ?></p>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php partial('cta-final'); ?>
