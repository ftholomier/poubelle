<?php
use App\Core\Url;
use App\Core\View;
use App\Services\Ads;
use App\Services\Categories;
use App\Services\Settings;

/** @var array $numbers @var array $cats @var array $chipCats @var array $featured @var array $testimonial */
$h = static fn (string $k, string $d = '') => (string) Settings::get('home.' . $k, $d);
$fill = static fn (string $s) => str_replace(['{nb}', '{demandes}'], [nf($numbers['pros']), nf($numbers['requests'])], $s);
$img1 = $h('hero_image_1') ?: '/assets/img/hero-dj.svg';
$img2 = $h('hero_image_2') ?: '/assets/img/hero-magic.svg';
$ticker = (array) Settings::get('home.ticker', []);
$steps = (array) Settings::get('home.steps', []);
$stepColors = ['var(--white)', 'var(--yellow)', 'var(--coral)'];
?>
<section class="hero" data-screen-label="Hero">
  <div class="hero-head">
    <div class="badge badge-tilt hero-in" style="--d:0s">● <?= e($fill($h('badge'))) ?></div>
    <?php $words = array_values(array_filter([[$h('title_1'), ''], [$h('title_2'), ''], [$h('title_em'), 'serif'], [$h('title_3'), '']], static fn ($w) => trim($w[0]) !== '')); ?>
    <h1 class="hero-title"><?php foreach ($words as $i => [$w, $cls]): ?><span class="w hero-in<?= $cls ? ' ' . $cls : '' ?>" style="--d:<?= number_format(0.08 + $i * 0.09, 2, '.', '') ?>s"><?= e($w) ?></span><?= $i < count($words) - 1 ? ' ' : '' ?><?php endforeach; ?></h1>
  </div>
  <div class="hero-grid">
    <div class="hero-copy">
      <p class="hero-lead hero-in" style="--d:.42s"><?= e($h('subtitle')) ?></p>

      <form class="search-box hero-in" style="--d:.5s" action="/recherche/" method="get" role="search" data-search-form>
        <div class="search-field">
          <span id="hero-quoi">Quoi ?</span>
          <select name="cat" aria-labelledby="hero-quoi" data-pretty>
            <option value="" data-color="#1c1233">Tous les pros</option>
            <?php foreach ($cats as $slug => $c): ?><option value="<?= e($slug) ?>" data-color="<?= e($c['color'] ?? '#ffd23f') ?>" data-emoji="<?= e($c['emoji'] ?? '') ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <label class="search-field autocomplete">
          <span>Où ?</span>
          <input type="text" name="ou" placeholder="Lyon, Nantes…" autocomplete="off" data-commune-input aria-label="Ville ou code postal">
          <input type="hidden" name="insee" data-commune-insee>
        </label>
        <button type="submit" class="btn-go">C'est parti !</button>
      </form>

      <div class="hero-cta hero-in" style="--d:.58s">
        <a class="btn btn-ink" href="/devis/"><span aria-hidden="true">📣</span> Déposer ma demande</a>
        <p><strong><?= e($h('hero_request_title')) ?></strong> <?= e($h('hero_request_text')) ?></p>
      </div>

      <div class="popular hero-in" style="--d:.66s">
        <span>Populaire :</span>
        <?php foreach ((array) Settings::get('home.popular', []) as $p): ?>
          <a class="pill-ghost" href="<?= e($p['url'] ?? '/recherche/') ?>"><?= e($p['label'] ?? '') ?></a>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="hero-visual" aria-hidden="true">
      <span class="confetti c1">✦</span><span class="confetti c2">●</span><span class="confetti c3">♪</span><span class="confetti c4">★</span><span class="confetti c5">✦</span><span class="confetti c6">●</span>
      <div class="frame frame-1"><img src="<?= e($img1) ?>" alt="" width="520" height="340" fetchpriority="high"></div>
      <div class="frame frame-2"><img src="<?= e($img2) ?>" alt="" width="460" height="280"></div>
      <div class="eq-pill"><span class="eq"><i></i><i></i><i></i><i></i></span> Ça bouge ce soir</div>
      <div class="quote-card">
        <div class="stars"><?= $testimonial['stars'] ? '★★★★★' : '✦ ✦ ✦' ?></div>
        <p><?= e($testimonial['text']) ?></p>
        <small>— <?= e($testimonial['author']) ?></small>
      </div>
      <?php if (Settings::get('home.sticker_show', true)): ?>
        <div class="sticker"><?= e($h('sticker_1')) ?><br><?= e($h('sticker_2')) ?><br><span class="serif"><?= e($h('sticker_3')) ?></span></div>
      <?php endif; ?>
    </div>
  </div>
</section>

<div class="ticker-clip"><div class="ticker" aria-hidden="true">
  <div class="ticker-track">
    <?php for ($loop = 0; $loop < 2; $loop++): foreach ($ticker as $i => $word): ?>
      <span class="<?= $i % 2 ? 'serif' : '' ?>"><?= e($word) ?></span><span class="star">✦</span>
    <?php endforeach; endfor; ?>
  </div>
</div></div>

<section id="demande" class="section" data-screen-label="Demande de devis" style="padding-bottom:0">
  <div class="request">
    <div>
      <div class="kicker"><?= e($h('request_kicker')) ?></div>
      <h2><?= e($h('request_title')) ?> <span class="serif"><?= e($h('request_title_em')) ?></span>.</h2>
      <p><?= e($h('request_text')) ?></p>
      <ol class="request-steps">
        <?php foreach (array_slice((array) Settings::get('home.request_steps', []), 0, 3) as $i => $st): ?>
          <li><span class="n"><?= $i + 1 ?></span><span><strong><?= e($st['t'] ?? '') ?></strong> <?= e($st['d'] ?? '') ?></span></li>
        <?php endforeach; ?>
      </ol>
      <div class="actions">
        <a class="btn btn-coral" href="/devis/">Déposer ma demande →</a>
        <?php if (($note = $fill($h('request_note'))) !== ''): ?><span class="request-note"><?= e($note) ?></span><?php endif; ?>
      </div>
    </div>
    <div class="request-demo" aria-hidden="true">
      <div class="request-card">
        <div class="request-card-head">📣 Votre demande</div>
        <ul><li>💍 Mariage · Lyon (69)</li><li>📅 Samedi 12 juin</li><li>👥 120 invités</li><li>🎧 DJ et photobooth</li></ul>
      </div>
      <div class="request-sent">↓ envoyée à tous les pros du secteur</div>
      <div class="request-replies">
        <div class="reply"><span class="av" style="--c:var(--coral)">D</span><span><b>Un DJ</b> vous a répondu</span><small>2 h</small></div>
        <div class="reply"><span class="av" style="--c:var(--lime)">P</span><span><b>Un photobooth</b> vous a répondu</span><small>5 h</small></div>
        <div class="reply"><span class="av" style="--c:var(--white)">A</span><span><b>Un animateur</b> vous a répondu</span><small>1 j</small></div>
      </div>
    </div>
  </div>
</section>

<section id="pros" class="section" data-screen-label="Pros" data-home-pros>
  <div class="section-head">
    <h2 class="h2"><?= e($h('pros_title')) ?> <span class="serif"><?= e($h('pros_title_em')) ?></span></h2>
    <div class="result-label" data-result-label><?= nf($numbers['pros']) ?> pros référencés</div>
  </div>
  <div class="chips" role="group" aria-label="Filtrer par métier">
    <button type="button" class="chip is-on" data-home-cat="" aria-pressed="true"><span class="dot" style="background:#1c1233"></span>Tous les pros</button>
    <?php foreach ($chipCats as $slug => $c): ?>
      <button type="button" class="chip" data-home-cat="<?= e($slug) ?>" aria-pressed="false"><span class="dot" style="background:<?= e($c['color']) ?>"></span><?= e($c['name']) ?></button>
    <?php endforeach; ?>
    <a class="chip" href="/recherche/"><span class="dot" style="background:var(--cream)"></span>+ <?= max(0, count($cats) - count($chipCats)) ?> métiers</a>
  </div>
  <div class="pro-grid" data-home-grid>
    <?php foreach ($featured as $i => $p): ?><?= View::partial('front/partials/pro-card', ['p' => $p, 'lazy' => $i > 2]) ?><?php endforeach; ?>
  </div>
  <div class="empty hidden" data-home-empty>Personne ici pour l'instant… <a class="link" href="/recherche/">voir tous les pros</a></div>
  <div class="center mt-4"><a class="btn btn-ink" href="/recherche/">Voir tous les pros sur la carte <?= icon('map', 18) ?></a></div>
</section>

<?= Ads::slot('home_mid') ?>

<section id="events" class="band band-purple" data-screen-label="Événements">
  <div class="wrap" style="padding-top:96px;padding-bottom:96px">
    <h2 class="h2"><?= e($h('events_title')) ?> <span class="serif"><?= e($h('events_title_em')) ?></span> ?</h2>
    <div class="occasions">
      <?php foreach (Categories::occasions() as $slug => $o): ?>
        <a class="occasion<?= ($o['fg'] ?? '') === '#fff6e8' ? ' on-dark' : '' ?>" href="<?= e(Url::occasion($slug)) ?>" style="--bg:<?= e($o['bg']) ?>;--fg:<?= e($o['fg']) ?>;--tilt:<?= (float) $o['tilt'] ?>deg">
          <span class="emo" aria-hidden="true"><?= e($o['emoji']) ?></span>
          <span><strong><?= e($o['name']) ?></strong><small><?= e($o['hint']) ?></small></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section id="how" class="section" data-screen-label="Comment ça marche" style="padding-bottom:110px">
  <h2 class="h2" style="max-width:760px"><?= e($h('how_title')) ?> <span class="serif"><?= e($h('how_title_em')) ?></span>.</h2>
  <div class="steps">
    <?php foreach ($steps as $i => $s): ?>
      <div class="step">
        <span class="num" style="--n:<?= $stepColors[$i % 3] ?>"><?= sprintf('%02d', $i + 1) ?></span>
        <h3><?= e($s['t'] ?? '') ?></h3>
        <p><?= e($s['d'] ?? '') ?></p>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="mt-4"><a class="btn btn-coral" href="/devis/">Déposer ma demande →</a></div>
</section>

<section id="join" class="section" data-screen-label="Espace pro" style="padding-top:0;padding-bottom:110px">
  <div class="join">
    <div>
      <div class="kicker"><?= e($h('join_kicker')) ?></div>
      <h2><?= e($h('join_title')) ?> <span class="serif"><?= e($h('join_title_em')) ?></span>.</h2>
      <p><?= e($h('join_text')) ?></p>
      <div class="actions">
        <a href="/inscription-pro/" class="btn btn-yellow">Inscrire mon activité</a>
        <a href="/professionnels/" class="btn-outline">Voir les avantages</a>
      </div>
    </div>
    <div class="join-stats">
      <div class="stat stat-1"><b><?= e($fill($h('stat1_value'))) ?></b><span><?= e($h('stat1_label')) ?></span></div>
      <div class="stat stat-2"><b><?= e($fill($h('stat2_value'))) ?></b><span><?= e($h('stat2_label')) ?></span></div>
    </div>
  </div>
</section>
