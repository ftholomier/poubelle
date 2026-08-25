<?php
$hero = (array) content('hero');
$stats = (array) content('stats');
$problem = (array) content('problem');
$benefits = (array) content('benefits');
$values = (array) content('values');
$compare = (array) content('compare');
$sim = (array) content('simulator');
$steps = (array) content('steps');
$skills = (array) content('skills');
$faq = (array) content('faq');
$marquee = (array) content('marquee');
?>

<!-- ====================================================== HÉROS -->
<section class="hero">
  <div class="hero__bg" aria-hidden="true">
    <span class="glow glow--red"></span>
    <span class="glow glow--amber"></span>
    <span class="glow glow--blue"></span>
  </div>
  <div class="hero__grid" aria-hidden="true"></div>

  <div class="container hero__inner">
    <div>
      <span class="chip chip--red" data-reveal><span class="dot"></span> <?= e($hero['badge'] ?? '') ?></span>

      <h1 class="display hero__title">
        <span class="split-word"><i style="--d:60ms"><?= e($hero['title_before'] ?? '') ?></i></span><br>
        <span class="hero__rotator">
          <?php foreach ((array) ($hero['rotating'] ?? []) as $w): ?><span><?= e($w) ?></span><?php endforeach; ?>
        </span><br>
        <span class="split-word"><i style="--d:180ms"><?= e($hero['title_after'] ?? '') ?></i></span>
      </h1>

      <p class="lead" data-reveal style="--d:120ms"><?= e($hero['lead'] ?? '') ?></p>

      <ul class="hero__proofs" style="list-style:none;padding:0">
        <?php foreach ((array) ($hero['proofs'] ?? []) as $i => $p): ?>
          <li data-reveal style="--d:<?= 180 + $i * 80 ?>ms"><?= icon('check-circle') ?> <span><?= e($p) ?></span></li>
        <?php endforeach; ?>
      </ul>

      <div class="hero__actions" data-reveal style="--d:380ms">
        <a class="btn btn--lg btn--magnet" href="<?= e(url('candidater')) ?>" data-cta="hero-primary">
          <?= e($hero['cta_primary'] ?? 'Candidater') ?> <?= icon('arrow') ?>
        </a>
        <a class="btn btn--ghost btn--lg" href="#simulateur" data-cta="hero-simulator">
          <?= e($hero['cta_secondary'] ?? 'Simuler mes revenus') ?>
        </a>
      </div>
      <p class="hero__note" data-reveal style="--d:440ms"><?= icon('lock') ?> <?= e($hero['cta_note'] ?? '') ?></p>
    </div>

    <!-- Preuve sociale immédiate -->
    <aside class="hero__panel" data-reveal="right" style="--d:260ms">
      <div class="hero__panel-head">
        <div>
          <div class="avatars" aria-hidden="true">
            <span>KL</span><span>CY</span><span>DJ</span><span>BP</span><span class="is-more">60+</span>
          </div>
          <p class="small muted" style="margin-top:10px">Agents déjà en poste dans le réseau</p>
        </div>
        <span class="stars" aria-label="Note 5 sur 5"><?= str_repeat(icon('star'), 5) ?></span>
      </div>

      <div class="hero__figure grad-text"><span class="count" data-count="2000">0</span>+</div>
      <p class="muted small">biens vendus par le réseau depuis 2017</p>

      <div class="hero__panel-list">
        <div class="hero__panel-row">
          <span class="muted small">Statut</span>
          <b>Agent commercial indépendant</b>
        </div>
        <div class="hero__panel-row">
          <span class="muted small">Plafond de rémunération</span>
          <b class="grad-text">Aucun</b>
        </div>
        <div class="hero__panel-row">
          <span class="muted small">Réponse à votre candidature</span>
          <b><?= e(settings('funnel.response_delay', '48 h')) ?></b>
        </div>
      </div>

      <a class="btn btn--block btn--magnet" style="margin-top:24px" href="<?= e(url('candidater')) ?>" data-cta="hero-panel">
        Vérifier si mon secteur est libre <?= icon('arrow') ?>
      </a>
    </aside>
  </div>

  <div class="scroll-cue" aria-hidden="true"><span>Explorer</span><i></i></div>
</section>

<!-- ================================================ BANDEAU -->
<?php if ($marquee): ?>
<div class="marquee" aria-hidden="true">
  <div class="marquee__track">
    <?php for ($i = 0; $i < 2; $i++): foreach ($marquee as $m): ?>
      <span class="marquee__item"><?= e($m) ?></span>
    <?php endforeach; endfor; ?>
  </div>
</div>
<?php endif; ?>

<!-- ================================================ CHIFFRES -->
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

<!-- ============================================ PROBLÈME / GAIN -->
<section class="section" id="pourquoi">
  <span class="glow glow--red" style="width:520px;height:520px;top:10%;left:-260px;opacity:.25" aria-hidden="true"></span>
  <div class="container">
    <div class="section-head" data-reveal>
      <span class="eyebrow"><?= e($problem['eyebrow'] ?? '') ?></span>
      <h2 class="h2"><?= e($problem['title'] ?? '') ?></h2>
      <p class="lead"><?= e($problem['lead'] ?? '') ?></p>
    </div>

    <div class="painlist">
      <?php foreach ((array) ($problem['items'] ?? []) as $i => $it): ?>
        <div class="painrow" data-reveal style="--d:<?= $i * 90 ?>ms">
          <p class="painrow__pain"><s><?= e($it['pain'] ?? '') ?></s></p>
          <span class="painrow__arrow" aria-hidden="true"><?= icon('arrow') ?></span>
          <p class="painrow__gain"><?= e($it['gain'] ?? '') ?></p>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="cluster" style="margin-top:36px;justify-content:center" data-reveal>
      <a class="btn btn--lg btn--magnet" href="<?= e(url('candidater')) ?>" data-cta="problem">
        <?= e($problem['cta'] ?? 'Candidater') ?> <?= icon('arrow') ?>
      </a>
    </div>
  </div>
</section>

<!-- ============================================== SIMULATEUR -->
<section class="section" id="simulateur">
  <span class="glow glow--amber" style="width:600px;height:600px;bottom:-200px;right:-220px;opacity:.3" aria-hidden="true"></span>
  <div class="container">
    <div class="section-head" data-reveal>
      <span class="eyebrow"><?= e($sim['eyebrow'] ?? '') ?></span>
      <h2 class="h2"><?= e($sim['title'] ?? '') ?></h2>
      <p class="lead"><?= e($sim['lead'] ?? '') ?></p>
    </div>

    <div class="simulator" id="simulator" data-reveal data-config='<?= e(json_encode([
        "agency_fee_rate" => $sim["agency_fee_rate"] ?? 4.5,
        "tiers" => $sim["tiers"] ?? [],
    ], JSON_UNESCAPED_UNICODE)) ?>'>
      <div class="sim-panel">
        <div class="sim-field">
          <div class="sim-field__head">
            <label class="sim-field__label" for="sim-price"><?= e(arr($sim, 'avg_price.label', 'Prix moyen')) ?></label>
            <span class="sim-field__value" id="sim-price-out">—</span>
          </div>
          <input type="range" id="sim-price"
                 min="<?= e((string) arr($sim, 'avg_price.min', 80000)) ?>"
                 max="<?= e((string) arr($sim, 'avg_price.max', 500000)) ?>"
                 step="<?= e((string) arr($sim, 'avg_price.step', 5000)) ?>"
                 value="<?= e((string) arr($sim, 'avg_price.default', 180000)) ?>">
          <div class="sim-scale">
            <span><?= e(nb(arr($sim, 'avg_price.min', 0))) ?> €</span>
            <span><?= e(nb(arr($sim, 'avg_price.max', 0))) ?> €</span>
          </div>
        </div>

        <div class="sim-field">
          <div class="sim-field__head">
            <label class="sim-field__label" for="sim-sales"><?= e(arr($sim, 'sales.label', 'Ventes par an')) ?></label>
            <span class="sim-field__value" id="sim-sales-out">—</span>
          </div>
          <input type="range" id="sim-sales"
                 min="<?= e((string) arr($sim, 'sales.min', 2)) ?>"
                 max="<?= e((string) arr($sim, 'sales.max', 30)) ?>"
                 step="<?= e((string) arr($sim, 'sales.step', 1)) ?>"
                 value="<?= e((string) arr($sim, 'sales.default', 10)) ?>">
          <div class="sim-scale">
            <span><?= e((string) arr($sim, 'sales.min', 2)) ?> ventes</span>
            <span><?= e((string) arr($sim, 'sales.max', 30)) ?> ventes</span>
          </div>
        </div>

        <div class="sim-breakdown" style="margin-top:34px">
          <div><span class="muted">Honoraires d’agence par vente</span><b id="sim-fee">—</b></div>
          <div><span class="muted">Honoraires générés sur l’année</span><b id="sim-gross">—</b></div>
          <div><span class="muted">Votre part selon le palier</span><b id="sim-rate">—</b></div>
        </div>
      </div>

      <div class="sim-result">
        <span class="sim-tier" id="sim-tier">Palier</span>
        <p class="muted small" style="margin-top:22px">Votre rémunération annuelle estimée</p>
        <div class="sim-amount" id="sim-amount">—</div>
        <p class="sim-sub">soit <b id="sim-month">—</b> par mois en moyenne</p>

        <ul class="sim-ladder" id="sim-ladder">
          <?php foreach ((array) ($sim['tiers'] ?? []) as $i => $t): ?>
            <li data-tier="<?= e((string) ($t['name'] ?? '')) ?>">
              <span class="sim-ladder__dot" aria-hidden="true"></span>
              <span class="sim-ladder__name"><?= e($t['name'] ?? '') ?></span>
              <span class="sim-ladder__range">
                <?= (int) ($t['from'] ?? 0) <= 1 ? 'jusqu’à ' . (int) ($t['to'] ?? 0) : ((int) ($t['to'] ?? 0) >= 900 ? (int) ($t['from'] ?? 0) . '+' : (int) ($t['from'] ?? 0) . '–' . (int) ($t['to'] ?? 0)) ?> ventes/an
              </span>
              <b class="sim-ladder__rate"><?= e(nb($t['rate'] ?? 0)) ?> %</b>
            </li>
          <?php endforeach; ?>
        </ul>

        <a class="btn btn--lg btn--block btn--magnet" style="margin-top:auto" href="<?= e(url('candidater')) ?>" data-cta="simulator">
          <?= e($sim['cta'] ?? 'Candidater') ?> <?= icon('arrow') ?>
        </a>
        <p class="sim-disclaimer"><?= e(str_replace('{rate}', rtrim(rtrim(number_format((float) ($sim['agency_fee_rate'] ?? 4.5), 2, ',', ' '), '0'), ','), (string) ($sim['disclaimer'] ?? ''))) ?></p>
      </div>
    </div>
  </div>
</section>

<!-- ================================================ AVANTAGES -->
<section class="section" id="avantages">
  <div class="container">
    <div class="section-head" data-reveal>
      <span class="eyebrow"><?= e($benefits['eyebrow'] ?? '') ?></span>
      <h2 class="h2"><?= e($benefits['title'] ?? '') ?></h2>
      <p class="lead"><?= e($benefits['lead'] ?? '') ?></p>
    </div>

    <div class="bento">
      <?php foreach ((array) ($benefits['items'] ?? []) as $i => $b): ?>
        <article class="card benefit" data-reveal style="--d:<?= min($i * 60, 360) ?>ms">
          <span class="benefit__icon"><?= icon((string) ($b['icon'] ?? 'star')) ?></span>
          <h3 class="h4"><?= e($b['title'] ?? '') ?></h3>
          <p><?= e($b['text'] ?? '') ?></p>
          <span class="benefit__tag"><?= e($b['tag'] ?? '') ?></span>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- =================================================== VALEURS -->
<section class="section section--tight">
  <div class="container">
    <div class="section-head" data-reveal style="margin-bottom:36px">
      <span class="eyebrow"><?= e($values['eyebrow'] ?? '') ?></span>
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

<!-- ================================================ COMPARATIF -->
<section class="section" id="comparatif">
  <div class="container">
    <div class="section-head" data-reveal>
      <span class="eyebrow"><?= e($compare['eyebrow'] ?? '') ?></span>
      <h2 class="h2"><?= e($compare['title'] ?? '') ?></h2>
      <p class="lead"><?= e($compare['lead'] ?? '') ?></p>
    </div>

    <div class="compare" data-reveal>
      <div class="compare__scroll">
        <table>
          <caption class="sr-only">Comparaison entre agence traditionnelle, réseau en ligne et Suisse Immo</caption>
          <thead>
            <tr>
              <th scope="col">Critère</th>
              <?php foreach ((array) ($compare['columns'] ?? []) as $i => $col): ?>
                <th scope="col" class="<?= $i === 2 ? 'col-win' : '' ?>"><?= e($col) ?></th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ((array) ($compare['rows'] ?? []) as $row): ?>
              <tr>
                <th scope="row"><?= e($row['label'] ?? '') ?></th>
                <td><?= e($row['a'] ?? '') ?></td>
                <td><?= e($row['b'] ?? '') ?></td>
                <td class="col-win"><?= e($row['c'] ?? '') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</section>

<!-- ==================================================== ÉTAPES -->
<section class="section" id="parcours">
  <span class="glow glow--blue" style="width:520px;height:520px;top:20%;right:-240px;opacity:.22" aria-hidden="true"></span>
  <div class="container">
    <div class="section-head" data-reveal>
      <span class="eyebrow"><?= e($steps['eyebrow'] ?? '') ?></span>
      <h2 class="h2"><?= e($steps['title'] ?? '') ?></h2>
      <p class="lead"><?= e($steps['lead'] ?? '') ?></p>
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

<!-- =============================================== COMPÉTENCES -->
<section class="section section--tight" id="profil">
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

<?php partial('reviews'); ?>

<!-- ======================================================= FAQ -->
<section class="section" id="faq">
  <div class="container container--narrow">
    <div class="section-head" data-reveal style="text-align:center;margin-inline:auto">
      <span class="eyebrow"><?= e($faq['eyebrow'] ?? '') ?></span>
      <h2 class="h2"><?= e($faq['title'] ?? '') ?></h2>
    </div>
    <div class="faq">
      <?php foreach ((array) ($faq['items'] ?? []) as $i => $f): ?>
        <details class="faq-item" data-reveal style="--d:<?= min($i * 55, 300) ?>ms"<?= $i === 0 ? ' open' : '' ?>>
          <summary><?= e($f['q'] ?? '') ?></summary>
          <div class="faq-answer"><?= e($f['a'] ?? '') ?></div>
        </details>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php partial('cta-final'); ?>
