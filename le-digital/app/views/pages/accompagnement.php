<?php
/** @var array $c  @var array $g */
use LD\View;

$eng = $g['engagements'] ?? [];
$fo = $c['formules']['items'];
$cur = $fo[0] ?? [];
?>
<section class="hero-c" aria-labelledby="acc-title">
  <div class="dots dots--top" aria-hidden="true"></div>
  <div class="hero-c__in">
    <span class="pill-r pill-r--700"><?= t($c['hero']['tag']) ?></span>
    <h1 class="hero-c__title" id="acc-title"><?= t($c['hero']['title']) ?> <span class="grad-text"><?= t($c['hero']['title_accent']) ?></span></h1>
    <p class="hero-c__p"><?= t($c['hero']['text']) ?></p>
  </div>
</section>

<section class="sec">
  <div class="wrap">
    <h2 class="h2 mb48" data-reveal><?= t($c['engagements']['title']) ?></h2>
    <div class="ag mb16" style="--min:460px">
      <div class="eng-xl eng-xl--red" data-reveal>
        <span class="eng-xl__n">01</span>
        <h3><?= t($eng[0]['t'] ?? '') ?></h3>
        <p><?= t($eng[0]['d_long'] ?? '') ?></p>
        <a class="eng-xl__tel" href="<?= e(tel_href()) ?>"><?= icon('phone', 18, '#cc1517') ?><?= e(phone_label()) ?></a>
      </div>
      <div class="eng-xl eng-xl--ink" data-reveal data-delay="120">
        <span class="eng-xl__n">02</span>
        <h3><?= t($eng[1]['t'] ?? '') ?></h3>
        <p><?= t($eng[1]['d_long'] ?? '') ?></p>
        <span class="eng-xl__badge"><i aria-hidden="true"></i><?= t($c['engagements']['badge_24h']) ?></span>
      </div>
    </div>
    <div class="ag" style="--min:300px">
      <?php foreach (array_slice($eng, 2) as $i => $en): ?>
        <div class="eng eng--lg" data-reveal data-delay="<?= ($i % 3) * 90 ?>"><span class="eng__n"><?= sprintf('%02d', $i + 3) ?></span><h3><?= t($en['t']) ?></h3><p><?= t($en['d_long']) ?></p></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="sec sec--quiz">
  <div class="quiz-wrap" data-reveal="scale"><?= View::partial('quiz') ?></div>
</section>

<section class="sec sec--rose">
  <div class="wrap">
    <div class="intro2 intro2--44" data-reveal>
      <h2 class="h2"><?= t($c['formules']['title']) ?></h2>
      <p class="lead"><?= t($c['formules']['intro']) ?></p>
    </div>
    <div class="ag mb16" style="--min:260px;--gap:12px" role="tablist" data-formules="<?= json_attr($fo) ?>">
      <?php foreach ($fo as $i => $f): ?>
        <button type="button" role="tab" class="fpick<?= $i === 0 ? ' is-on' : '' ?>" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>" data-formule="<?= $i ?>"><span class="fpick__l"><?= t($c['formules']['si_label']) ?></span><span class="fpick__t"><?= t($f['si']) ?></span></button>
      <?php endforeach; ?>
    </div>
    <div class="fcur" data-reveal="scale" aria-live="polite">
      <div class="fcur__txt" data-fcur-anim>
        <span class="kicker" data-fcur-k><?= t($cur['k'] ?? '') ?></span>
        <h3 class="fcur__t" data-fcur-t><?= t($cur['t'] ?? '') ?></h3>
        <p class="fcur__d" data-fcur-d><?= t($cur['d'] ?? '') ?></p>
      </div>
      <div class="fcur__side">
        <div class="fcur__how"><b><?= t($c['formules']['how_label']) ?></b><span data-fcur-how><?= t($cur['how'] ?? '') ?></span></div>
        <a class="btn btn-dark btn-md self-start" href="<?= e($cur['href'] ?? '/on-prend-rdv') ?>" data-fcur-cta><?= t($cur['cta'] ?? '') ?></a>
      </div>
    </div>
  </div>
</section>

<section class="sec sec--formula sec--formula-acc">
  <div class="wrap formula-wrap formula-wrap--acc">
    <span class="pill-w"><?= t($g['formula']['kicker'] ?? 'Ma formule') ?></span>
    <p class="formula-static" data-reveal="scale"><?= e(implode('', array_map(static fn($tk) => $tk['t'] === '+' ? '+' : (in_array($tk['t'], ['x', '='], true) ? ' ' . $tk['t'] . ' ' : $tk['t']), $g['formula']['tokens'] ?? []))) ?></p>
    <p class="formula__text formula__text--acc"><?= t($c['formula']['text']) ?></p>
  </div>
</section>

<?= View::partial('final-dark', ['title' => $c['final']['title'], 'text' => $c['final']['text'], 'cta' => $c['final']['cta'], 'variant' => 'acc']) ?>
