<?php
/** @var array $c contenu de l'accueil  @var array $g contenus communs  @var array $s paramètres */
use LD\View;

$h = $c['hero'];
$solCards = LD\Solutions::enabled();
$services = $g['services'] ?? [];
$eng = $g['engagements'] ?? [];
$reviews = LD\Reviews::featured(3);
$gBadge = LD\GoogleReviews::badge();
$rvWidget = LD\Reviews::widgetPosition('home');
?>
<section class="hero" aria-labelledby="hero-title">
  <div class="dots dots--hero" aria-hidden="true"></div>
  <div class="blob blob--line blob--hero-a" aria-hidden="true"></div><div class="blob blob--fill blob--hero-b" aria-hidden="true"></div>
  <div class="hero__in">
    <div class="hero__txt">
      <div class="tags"><?php foreach ($h['tags'] as $tg): ?><span class="tag"><?= t($tg) ?></span><?php endforeach; ?><?php if (trim((string) ($h['made_in_france'] ?? '')) !== ''): ?><span class="tag tag--fr"><?= fr_flag() ?><?= t($h['made_in_france']) ?></span><?php endif; ?></div>
      <h1 class="hero__title" id="hero-title"><?= t($h['title_line1']) ?><br><span class="grad-anim"><?= t($h['title_line2']) ?></span></h1>
      <div class="rot">
        <span class="rot__intro"><?= t($h['rotating_intro']) ?></span>
        <span class="rot__box"><span class="rot__ic" aria-hidden="true"><?= icon('check', 12, '#161922') ?></span><span class="rot__word" data-rotate="<?= json_attr($h['rotating_words']) ?>" aria-live="off"><?= t($h['rotating_words'][0] ?? '') ?></span></span>
      </div>
      <p class="hero__p"><?= t($h['text']) ?></p>
      <div class="btns">
        <a class="btn btn-red btn-xl btn-lift" href="/on-prend-rdv"><?= t($h['cta_primary']) ?></a>
        <a class="btn btn-outline btn-xl2" href="<?= e($h['cta_secondary_href']) ?>"><?= t($h['cta_secondary']) ?></a>
      </div>
      <p class="note"><span class="dot-green" aria-hidden="true"></span><?= t($h['note']) ?></p>
    </div>

    <div class="hero__vis" data-hero-seq>
      <div class="hero__photo"><?= slot($h['photo'], 'Photo de Frédéric — à fournir', $h['photo_alt'], ['eager' => true, 'sizes' => '(max-width: 600px) 82vw, 443px']) ?></div>
      <div class="mcard mcard--1" data-step="5">
        <span class="mcard__ic" aria-hidden="true"><?= icon('check16', 16, '#6f8d12', 2.4) ?></span>
        <span class="mcard__t"><b><?= t($h['card1_title']) ?></b><br><span><?= t($h['card1_sub']) ?></span></span>
      </div>
      <div class="mcard mcard--2" data-step="6">
        <span class="mcard__ic" aria-hidden="true"><?= icon('check16', 16, '#6f8d12', 2.4) ?></span>
        <span class="mcard__t"><b><?= t($h['card2_title']) ?></b><br><span><?= t($h['card2_sub']) ?></span></span>
      </div>
      <div class="hero__chat">
        <div class="typing" data-step="1" data-only aria-hidden="true"><span></span><span></span><span></span></div>
        <div class="bub bub--client" data-step="2"><p class="bub__n"><?= t($h['bubble_client_name']) ?></p><p class="bub__t"><?= t($h['bubble_client']) ?></p></div>
        <div class="typing typing--fred" data-step="3" data-only aria-hidden="true"><span></span><span></span><span></span></div>
        <div class="bub bub--fred" data-step="4"><p class="bub__n"><?= t($h['bubble_fred_name']) ?></p><p class="bub__t"><?= t($h['bubble_fred']) ?></p></div>
      </div>
    </div>
  </div>
</section>

<section class="marquee" aria-label="Tâches automatisées">
  <div class="marquee__track">
    <?php foreach (array_merge($c['marquee'], $c['marquee']) as $i => $m): ?><span class="marquee__it"<?= $i >= count($c['marquee']) ? ' aria-hidden="true"' : '' ?>><?= t($m) ?><i aria-hidden="true"></i></span><?php endforeach; ?>
  </div>
</section>

<section class="stats" aria-label="Chiffres clés">
  <div class="stats__in">
    <?php foreach ($c['stats'] as $st): ?>
      <div class="stat"><span class="stat__v<?= !empty($st['accent']) ? ' accent' : '' ?>"><?php if (!empty($st['count']) && is_numeric($st['value'])): ?><span data-count-to="<?= e($st['value']) ?>"><?= e($st['value']) ?></span><?php else: ?><?= e($st['value']) ?><?php endif; ?><?= e(str_replace(' ', "\u{00A0}", (string) $st['suffix'])) ?></span><span class="stat__l"><?= t($st['label']) ?></span></div>
    <?php endforeach; ?>
  </div>
</section>

<section class="sec" id="ce-que-je-construis">
  <div class="wrap">
    <?= ask($c['services']['ask']) ?>
    <div class="intro2" data-reveal>
      <h2 class="h2"><?= t($c['services']['title']) ?></h2>
      <p class="lead"><?= t($c['services']['intro']) ?></p>
    </div>
    <div class="ag" style="--min:340px">
      <?php foreach ($services as $i => $sv): ?>
        <a class="svc" href="/sur-mesure-ia#<?= e($sv['id']) ?>" data-reveal data-delay="<?= ($i % 3) * 90 ?>">
          <span class="svc__top"><span class="svc__n"><?= sprintf('%02d', $i + 1) ?></span><span class="svc__arrow" aria-hidden="true">→</span></span>
          <h3 class="svc__t"><?= t($sv['title']) ?></h3>
          <p class="svc__d"><?= t($sv['home_text']) ?></p>
          <?php if (!empty($sv['home_ia'])): ?><span class="svc__ia"><span class="badge-ia">+ IA</span><span><?= t($sv['home_ia']) ?></span></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
    <div class="center mt44"><a class="btn btn-dark btn-lg" href="/on-prend-rdv"><?= t($c['services']['cta']) ?></a></div>
  </div>
</section>

<?php if (!empty($c['bureau']['enabled'])): $b = $c['bureau']; ?>
<section class="sec" aria-label="<?= e($b['title_before'] . ' ' . $b['title_after']) ?>">
  <div class="bureau" data-bureau>
    <div class="bureau__sticky">
      <div class="bureau__titles">
        <h2 class="bureau__t bureau__t--before"><?= t($b['title_before']) ?></h2>
        <p class="bureau__t bureau__t--after" aria-hidden="true"><?= t($b['title_after']) ?></p>
      </div>
      <div class="bureau__stage">
        <div class="bureau__frame"><div class="bureau__bar"><i></i><i></i><i></i><span><?= t($b['window']) ?></span></div></div>
        <?php foreach ($b['items'] as $it): $kind = in_array($it['kind'], ['post', 'xls', 'mail'], true) ? $it['kind'] : 'post'; ?>
          <div class="bitem bitem--<?= $kind ?>" data-x="<?= (float) $it['x'] ?>" data-y="<?= (float) $it['y'] ?>" data-rot="<?= (float) $it['rot'] ?>" style="left:<?= (float) $it['x'] ?>%;top:<?= (float) $it['y'] ?>%;transform:rotate(<?= (float) $it['rot'] ?>deg)">
            <span class="bitem__ic" data-icon="<?= $kind === 'post' ? '!' : ($kind === 'xls' ? 'XLS' : '@') ?>"><?= $kind === 'post' ? '!' : ($kind === 'xls' ? 'XLS' : '@') ?></span>
            <span class="bitem__l"><span class="bitem__b"><?= t($it['before']) ?></span><span class="bitem__a"><?= t($it['after']) ?></span></span>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="bureau__prog" aria-hidden="true"><div data-bureau-prog></div></div>
      <p class="bureau__hint" data-bureau-hint data-start="<?= e($b['hint_start']) ?>" data-progress="<?= e($b['hint_progress']) ?>" data-done="<?= e($b['hint_done']) ?>"><?= t($b['hint_start']) ?></p>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="sec sec--ink" id="ia">
  <div class="blob blob--line blob--ia-a" aria-hidden="true"></div><div class="blob blob--fill blob--ia-b" aria-hidden="true"></div>
  <div class="wrap">
    <div class="ia-head" data-reveal>
      <span class="kicker kicker--lt"><?= t($c['ia']['kicker']) ?></span>
      <h2 class="h2"><?= t($c['ia']['title']) ?> <span class="accent-lt"><?= t($c['ia']['title_accent']) ?></span></h2>
      <p class="lead lead--dk"><?= t($c['ia']['intro']) ?></p>
    </div>
    <div class="ba-tabs" role="tablist" data-reveal data-ba-tabs>
      <button type="button" role="tab" aria-selected="false" data-ba="before"><?= t($c['ia']['tab_before']) ?></button>
      <button type="button" role="tab" aria-selected="true" class="is-on" data-ba="after"><?= t($c['ia']['tab_after']) ?></button>
    </div>
    <div class="ag ba-grid" style="--min:300px;--gap:14px" data-reveal>
      <?php foreach ($c['ia']['rows'] as $row): ?>
        <div class="ba is-after" data-before="<?= e(nbsp($row['before'])) ?>" data-after="<?= e(nbsp($row['after'])) ?>">
          <span class="ba__ic" aria-hidden="true">✓</span><span class="ba__t"><?= t($row['after']) ?></span>
        </div>
      <?php endforeach; ?>
      <div class="ba-ctrl"><span><?= t($c['ia']['control']) ?></span></div>
    </div>
    <div class="benefits">
      <?php foreach ($c['ia']['benefits'] as $bf): ?><div class="benefit"><h3><?= t($bf['t']) ?></h3><p><?= t($bf['d']) ?></p></div><?php endforeach; ?>
    </div>
  </div>
</section>

<section class="sec sec--rose" id="demo">
  <div class="wrap">
    <?= ask($c['demo']['ask']) ?>
    <div class="intro2 intro2--44" data-reveal>
      <h2 class="h2"><?= t($c['demo']['title']) ?> <span class="accent"><?= t($c['demo']['title_accent']) ?></span></h2>
      <p class="lead"><?= t($c['demo']['intro']) ?></p>
    </div>
    <div data-reveal="scale"><?= View::partial('demo-devis') ?></div>
  </div>
</section>

<section class="sec" id="simulateur">
  <div class="wrap">
    <?= ask($c['simulator']['ask']) ?>
    <?= ask($c['simulator']['ask_dark'], true) ?>
    <div class="intro2 intro2--44" data-reveal>
      <h2 class="h2"><?= t($c['simulator']['title']) ?></h2>
      <p class="lead"><?= t($c['simulator']['intro']) ?></p>
    </div>
    <div data-reveal="scale"><?= View::partial('simulator') ?></div>
  </div>
</section>

<section class="sec sec--rose" id="deux-facons">
  <div class="wrap">
    <?php $w = $c['two_ways']; ?>
    <?= ask($w['ask']) ?>
    <h2 class="h2 h2--two" data-reveal><?= t($w['title']) ?> <span class="accent"><?= t($w['title_accent']) ?></span></h2>
    <div class="ag" style="--min:460px">
      <article class="way way--dev" data-reveal>
        <span class="way__tag"><?= t($w['dev_tag']) ?></span>
        <h3 class="way__t"><?= t($w['dev_title']) ?></h3>
        <p class="way__p"><?= t($w['dev_text']) ?></p>
        <div class="way__pts"><?php foreach ($w['dev_points'] as $pt): ?><div class="pt"><?= tick() ?><?= t($pt) ?></div><?php endforeach; ?></div>
        <a class="btn btn-red btn-md self-start" href="/sur-mesure-ia"><?= t($w['dev_cta']) ?></a>
      </article>
      <article class="way way--form" data-reveal data-delay="120">
        <span class="way__tag"><?= t($w['form_tag']) ?></span>
        <h3 class="way__t"><?= t($w['form_title']) ?></h3>
        <p class="way__p"><?= t($w['form_text']) ?></p>
        <div class="way__pts"><?php foreach ($w['form_points'] as $pt): ?><div class="pt"><?= tick() ?><?= t($pt) ?></div><?php endforeach; ?></div>
        <div class="way__new"><span class="badge-new"><?= t($w['form_new_badge']) ?></span><span><?= t($w['form_new']) ?></span></div>
        <a class="btn btn-white btn-md self-start" href="/formation"><?= t($w['form_cta']) ?></a>
      </article>
    </div>
    <div class="ag mt16" style="--min:460px">
      <?php foreach ($w['extra'] as $ex): ?>
        <a class="way-x" href="<?= e($ex['href']) ?>" data-reveal><h3><?= t($ex['title']) ?></h3><p><?= t($ex['text']) ?></p></a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="sec" id="accompagnement">
  <div class="wrap">
    <?= ask($c['engagements']['ask']) ?>
    <div class="eng-head" data-reveal>
      <span class="kicker"><?= t($c['engagements']['kicker']) ?></span>
      <h2 class="h2 h2--58"><?= t($c['engagements']['title']) ?></h2>
    </div>
    <div class="ag mb16" style="--min:460px">
      <div class="eng-big eng-big--red" data-reveal>
        <span class="eng-big__ic eng-big__ic--float"><?= icon('phone', 28, '#fff') ?></span>
        <h3><?= t($eng[0]['t'] ?? '') ?></h3>
        <p><?= t($eng[0]['d'] ?? '') ?></p>
      </div>
      <div class="eng-big eng-big--ink" data-reveal data-delay="120">
        <span class="eng-big__ic eng-big__ic--lime eng-big__ic--float2"><?= icon('clock', 28, '#c6e36b') ?></span>
        <h3><?= t($eng[1]['t'] ?? '') ?></h3>
        <p><?= t($eng[1]['d'] ?? '') ?></p>
      </div>
    </div>
    <div class="ag" style="--min:230px">
      <?php foreach (array_slice($eng, 2) as $i => $en): ?>
        <div class="eng" data-reveal data-delay="<?= $i * 70 ?>"><span class="eng__n"><?= sprintf('%02d', $i + 3) ?></span><h3><?= t($en['t']) ?></h3><p><?= t($en['d']) ?></p></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="sec sec--rose" id="qui">
  <div class="wrap about">
    <?php $ab = $c['about']; ?>
    <div class="about__vis" data-reveal="scale">
      <div class="about__photo"><?= slot($ab['photo'], 'Photo de Frédéric en situation — à fournir', $ab['photo_alt'], ['sizes' => '(max-width: 560px) 92vw, 520px']) ?></div>
      <div class="about__stat"><span class="about__num"><span data-count-to="<?= e($ab['stat_value']) ?>"><?= e($ab['stat_value']) ?></span>+</span><span><?= t($ab['stat_label']) ?></span></div>
    </div>
    <div class="about__txt" data-reveal>
      <?= ask($ab['ask'], false, true) ?>
      <h2 class="h2 h2--56"><?= t($ab['title']) ?></h2>
      <p class="about__p"><?= t($ab['text']) ?></p>
      <blockquote class="quote"><?= t($ab['quote']) ?></blockquote>
      <a class="btn btn-dark btn-md self-start" href="/qui-suis-je"><?= t($ab['cta']) ?></a>
    </div>
  </div>
</section>

<?php if ($solCards): ?>
<section class="sec" id="solutions">
  <div class="wrap">
    <?= ask($c['solutions']['ask']) ?>
    <div class="head-split" data-reveal>
      <div class="head-split__l"><h2 class="h2"><?= t($c['solutions']['title']) ?></h2><p class="lead"><?= t($c['solutions']['intro']) ?></p></div>
      <a class="link-u" href="/mes-solutions"><?= t($c['solutions']['link']) ?></a>
    </div>
    <div class="ag" style="--min:460px">
      <?php foreach ($solCards as $i => $so): $dark = $so['kind'] === 'intensiti' || ($so['theme'] ?? '') === 'sombre'; ?>
        <a class="prod <?= $dark ? 'prod--ink' : 'prod--rose' ?>" href="/mes-solutions#<?= e($so['id']) ?>" data-reveal<?= $i ? ' data-delay="' . min(360, $i * 120) . '"' : '' ?>>
          <div class="prod__shot"><div><?= slot($so['image'] ?? '', 'Capture ' . $so['name'] . ' — à fournir', 'Capture d’écran ' . $so['name'], ['sizes' => '(max-width: 600px) 80vw, 560px']) ?></div></div>
          <div class="prod__foot">
            <div class="prod__txt"><h3><?= t($so['name']) ?></h3><?= trim((string) ($so['short'] ?? '')) !== '' ? '<p>' . t($so['short']) . '</p>' : (trim((string) ($so['tagline'] ?? '')) !== '' ? '<p>' . t($so['tagline']) . '</p>' : '<p class="todo todo--sm">À fournir : phrase de présentation</p>') ?></div>
            <span class="prod__btn<?= $dark ? '' : ' prod__btn--red' ?>"><?= t(($so['cta'] ?? '') ?: 'Découvrir ' . $so['name']) ?></span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
      </a>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="sec sec--rose" id="etapes">
  <div class="wrap">
    <?= ask($c['steps']['ask']) ?>
    <h2 class="h2 h2--58 h2--steps" data-reveal><?= t($c['steps']['title']) ?></h2>
    <div class="ag" style="--min:210px">
      <?php foreach ($c['steps']['items'] as $i => $st): ?>
        <div class="step" data-reveal data-delay="<?= $i * 80 ?>"><span class="step__top"><span class="step__n"><?= $i + 1 ?></span><span class="step__line" aria-hidden="true"></span></span><h3><?= t($st['t']) ?></h3><p><?= t($st['d']) ?></p></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="sec sec--formula" id="formule">
  <div class="dots dots--white" aria-hidden="true"></div>
  <div class="wrap formula-wrap">
    <span class="pill-w"><?= t($g['formula']['kicker'] ?? 'Ma formule') ?></span>
    <?= View::partial('formula') ?>
    <p class="formula__hint" data-formula-hint data-touch="<?= e($c['formula']['hint_touch']) ?>"><?= t($c['formula']['hint']) ?></p>
    <p class="formula__text"><?= t($c['formula']['text']) ?></p>
    <a class="btn btn-white-shadow btn-xl" href="/on-prend-rdv"><?= t($c['formula']['cta']) ?></a>
  </div>
</section>

<section class="sec" id="avis">
  <div class="wrap">
    <?= ask($c['reviews']['ask']) ?>
    <div class="head-split" data-reveal>
      <h2 class="h2"><?= t($c['reviews']['title']) ?></h2>
      <div class="head-split__r">
        <?php if ($gBadge): ?><?= View::partial('google-badge', ['badge' => $gBadge]) ?><?php endif; ?>
        <a class="link-u" href="/avis"><?= t($c['reviews']['link']) ?></a>
      </div>
    </div>
    <?php if ($rvWidget !== 'replace' && ($reviews || $rvWidget === '')): ?>
      <div class="ag" style="--min:320px">
        <?php if ($reviews): foreach ($reviews as $i => $rv): ?>
          <?= View::partial('review', ['r' => $rv, 'variant' => 'home', 'delay' => $i * 90]) ?>
        <?php endforeach; else: foreach ($c['reviews']['placeholders'] as $i => $ph): ?>
          <?= View::partial('review', ['r' => null, 'tag' => $ph['tag'], 'who' => $ph['who'], 'variant' => 'home', 'delay' => $i * 90]) ?>
        <?php endforeach; endif; ?>
      </div>
    <?php endif; ?>
    <?php if ($rvWidget !== ''): ?><?= View::partial('reviews-widget') ?><?php endif; ?>
  </div>
</section>

<section class="final-wrap">
  <div class="final final--split" data-reveal="scale">
    <div class="blob blob--line blob--final-a" aria-hidden="true"></div><div class="blob blob--fill blob--final-b" aria-hidden="true"></div>
    <div class="final__txt">
      <h2 class="h2 h2--final"><?= t($c['final']['title']) ?></h2>
      <p class="final__p"><?= t($c['final']['text']) ?></p>
      <div class="btns">
        <a class="btn btn-red btn-lg btn-red-lt" href="/on-prend-rdv"><?= t($c['final']['cta']) ?></a>
        <a class="btn btn-ghost-dark btn-lg2"<?= link_attrs(linkedin_url()) ?>>LinkedIn</a>
      </div>
    </div>
    <div class="final__chat">
      <div class="fbub fbub--l"><?= t($c['final']['bubble_client']) ?></div>
      <div class="fbub fbub--r"><?= t($c['final']['bubble_fred']) ?></div>
      <button type="button" class="final__bot" data-open-bot><?= t($c['final']['bot_button']) ?></button>
    </div>
  </div>
</section>
